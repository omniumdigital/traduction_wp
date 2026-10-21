<?php
/**
 * Action Scheduler backed translation queue.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderInterface;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Providers\RateLimits;
use Omnium\Translate\Store\SegmentRepositoryInterface;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules work in Action Scheduler, group "omtr". Methods only enqueue:
 * no provider call ever happens during a visitor request (CLAUDE.md section 5).
 *
 * The enqueue_url() method accepts only URLs of this site (home_url() host),
 * drops the query string and fragments, and refuses non-public paths.
 */
final class ActionSchedulerQueue implements TranslationQueueInterface {

	public const GROUP        = 'omtr';
	public const ACTION_SCAN  = 'omtr_scan_url';
	public const ACTION_BATCH = 'omtr_translate_batch';

	/**
	 * Total attempts for a batch (initial run included).
	 */
	public const MAX_ATTEMPTS = 5;

	/**
	 * Backoff between batch attempts: 30 s, 2 min, 10 min, 1 h.
	 */
	private const BACKOFF = array( 30, 120, 600, 3600 );

	/**
	 * Path prefixes never scanned nor translated (admin, feeds, account and
	 * order pages: their rendered HTML must never reach a provider).
	 */
	private const BLOCKED_PATHS = array(
		'wp-admin',
		'wp-login.php',
		'wp-register.php',
		'wp-json',
		'xmlrpc.php',
		'cart',
		'checkout',
		'my-account',
		'order-received',
	);

	/**
	 * Reason of the last enqueue refusal, for CLI feedback.
	 *
	 * @var string
	 */
	private string $reason = '';

	/**
	 * Constructor.
	 *
	 * @param Settings                            $settings     Plugin settings.
	 * @param RunLock                             $lock         Translation run lock.
	 * @param WordCap                             $word_cap     Monthly word cap.
	 * @param ProviderRegistry                    $providers    Provider registry.
	 * @param LoggerInterface                     $logger       Logger.
	 * @param SegmentRepositoryInterface|null     $segments     Segments repository (only enqueue_segments needs it).
	 * @param TranslationRepositoryInterface|null $translations Translations repository (only enqueue_segments needs it).
	 */
	public function __construct(
		private Settings $settings,
		private RunLock $lock,
		private WordCap $word_cap,
		private ProviderRegistry $providers,
		private LoggerInterface $logger,
		private ?SegmentRepositoryInterface $segments = null,
		private ?TranslationRepositoryInterface $translations = null
	) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $url     URL under home_url(), query string ignored.
	 * @param int    $post_id Related post id, 0 if none.
	 * @return bool False if refused (foreign URL, cap reached, lock held).
	 */
	public function enqueue_url( string $url, int $post_id = 0 ): bool {
		$normalized = $this->normalize_url( $url );
		if ( null === $normalized ) {
			$this->reason = __( 'The URL is not part of this site or uses a non-public path.', 'omnium-translate' );
			return false;
		}

		if ( $this->word_cap->is_exhausted() ) {
			$this->reason = __( 'The monthly word cap is reached.', 'omnium-translate' );
			$this->logger->log( 'warning', 'queue', 'Enqueue refused: monthly word cap reached.' );
			return false;
		}

		if ( $this->lock->is_held() ) {
			$this->reason = __( 'A translation run is already in progress.', 'omnium-translate' );
			$this->logger->log( 'warning', 'queue', 'Enqueue refused: translation run already in progress.' );
			return false;
		}

		$run_id = (string) wp_generate_uuid4();
		if ( ! $this->lock->acquire( $run_id ) ) {
			$this->reason = __( 'A translation run is already in progress.', 'omnium-translate' );
			return false;
		}

		$this->schedule_scan( $normalized, $post_id, $run_id );
		$this->logger->log( 'info', 'queue', sprintf( 'Scan scheduled (post_id=%d, url=%s).', $post_id, $normalized ) );

		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<int> $segment_ids Segment ids.
	 * @param string    $target_lang Target language.
	 */
	public function enqueue_segments( array $segment_ids, string $target_lang ): void {
		if ( array() === $segment_ids || null === $this->segments || null === $this->translations ) {
			return;
		}

		$ids = array_values( array_filter( array_map( 'intval', $segment_ids ) ) );
		if ( array() === $ids ) {
			return;
		}

		$id_hash = $this->segments->hashes_by_ids( $ids );
		if ( array() === $id_hash ) {
			$this->logger->log( 'warning', 'queue', 'Enqueue of unknown segment ids refused.' );
			return;
		}

		$texts = $this->segments->texts_by_hash( array_values( $id_hash ) );

		// Never pay twice: drop segments already translated for this language.
		$existing = $this->translations->find_many( array_keys( $texts ), $target_lang );
		$missing  = array_diff_key( $texts, $existing );

		if ( array() === $missing ) {
			$this->logger->log( 'info', 'queue', 'No untranslated segment to enqueue.' );
			return;
		}

		$provider = $this->current_provider();
		$limits   = null !== $provider ? $provider->limits() : new RateLimits();

		$scheduled = 0;
		foreach ( BatchSizer::chunk( $missing, $limits ) as $batch ) {
			$words = $this->word_cap->count_words( $batch );
			if ( ! $this->word_cap->allows( $words ) ) {
				$this->logger->log( 'warning', 'queue', 'Segment batch dropped: monthly word cap reached.' );
				break;
			}
			$this->word_cap->charge( $words );

			$payload = array();
			foreach ( $batch as $hash => $text ) {
				$id = array_search( $hash, $id_hash, true );
				if ( is_int( $id ) ) {
					$payload[ $id ] = $hash;
				}
			}
			$this->schedule_batch( $payload, $target_lang, '', 1 );
			++$scheduled;
		}

		$this->logger->log( 'info', 'queue', sprintf( 'Segments enqueued for translation (batches=%d, lang=%s).', $scheduled, $target_lang ) );
	}

	/**
	 * Reason of the last refusal, for CLI feedback.
	 */
	public function last_reason(): string {
		return $this->reason;
	}

	/**
	 * Normalize a URL: keep scheme, host, port and path of this site only.
	 * Query strings and fragments are dropped (CLAUDE.md sections 6 and 11).
	 *
	 * @param string $url Raw URL.
	 * @return string|null Normalized URL, null when refused.
	 */
	public function normalize_url( string $url ): ?string {
		$parts = wp_parse_url( trim( $url ) );
		if ( ! is_array( $parts ) || ! isset( $parts['scheme'], $parts['host'] ) ) {
			return null;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return null;
		}

		$home = wp_parse_url( (string) home_url() );
		if ( ! is_array( $home ) || ! isset( $home['host'] ) ) {
			return null;
		}
		if ( strtolower( (string) $parts['host'] ) !== strtolower( (string) $home['host'] ) ) {
			return null;
		}

		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		if ( ! $this->is_translatable_path( $path ) ) {
			return null;
		}

		$port = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		return $scheme . '://' . strtolower( (string) $parts['host'] ) . $port . $path;
	}

	/**
	 * Schedule the scan action of a run.
	 *
	 * @param string $url     Normalized URL.
	 * @param int    $post_id Post id.
	 * @param string $run_id  Run id.
	 */
	public function schedule_scan( string $url, int $post_id, string $run_id ): void {
		as_schedule_single_action(
			time(),
			self::ACTION_SCAN,
			array(
				'url'     => $url,
				'post_id' => $post_id,
				'run_id'  => $run_id,
			),
			self::GROUP
		);
	}

	/**
	 * Schedule (or reschedule with delay) a batch action.
	 *
	 * @param array<int, string> $segments Segment id => hash.
	 * @param string             $lang     Target language.
	 * @param string             $run_id   Run id, '' when standalone.
	 * @param int                $attempt  Attempt number (1-based).
	 * @param int                $delay    Seconds to wait before execution.
	 */
	public function schedule_batch( array $segments, string $lang, string $run_id, int $attempt, int $delay = 0 ): void {
		as_schedule_single_action(
			time() + max( 0, $delay ),
			self::ACTION_BATCH,
			array(
				'segments' => $segments,
				'lang'     => $lang,
				'run_id'   => $run_id,
				'attempt'  => $attempt,
			),
			self::GROUP
		);
	}

	/**
	 * Backoff delay before the next attempt of a batch.
	 *
	 * @param int $attempt Current attempt number (1-based).
	 */
	public function backoff_delay( int $attempt ): int {
		$index = max( 0, min( count( self::BACKOFF ) - 1, $attempt - 1 ) );
		return self::BACKOFF[ $index ];
	}

	/**
	 * Count outstanding batch actions (pending or in progress).
	 */
	public function pending_batch_count(): int {
		return count(
			as_get_scheduled_actions(
				array(
					'hook'     => self::ACTION_BATCH,
					'group'    => self::GROUP,
					'status'   => array( 'pending', 'in-progress' ),
					'per_page' => 0,
				),
				'ids'
			)
		);
	}

	/**
	 * Settle a run: release the lock once no batch of the group is outstanding.
	 *
	 * @param string $run_id Run id.
	 */
	public function settle_run( string $run_id ): void {
		if ( '' === $run_id ) {
			return;
		}
		if ( $this->pending_batch_count() > 0 ) {
			return;
		}
		$this->lock->release( $run_id );
		$this->logger->log( 'info', 'queue', 'Translation run finished, lock released.' );
	}

	/**
	 * Current provider, null when unconfigured.
	 */
	public function current_provider(): ?ProviderInterface {
		$id = (string) $this->settings->get( 'provider' );
		return $this->providers->get( $id );
	}

	/**
	 * Whether a path may be scanned/translated.
	 *
	 * @param string $path URL path.
	 */
	private function is_translatable_path( string $path ): bool {
		$clean = trim( strtolower( $path ), '/' );
		if ( '' === $clean ) {
			return true;
		}
		foreach ( self::BLOCKED_PATHS as $blocked ) {
			if ( $clean === $blocked || 0 === strpos( $clean, $blocked . '/' ) ) {
				return false;
			}
		}
		return true;
	}
}
