<?php
/**
 * Scan job: extract segments of a page and schedule translation batches.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Store\SegmentRepositoryInterface;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Handler of the "omtr_scan_url" action (group "omtr").
 *
 * The page is fetched with wp_safe_remote_get(): anonymous request, no cookie,
 * no session, and redirects are restricted to allowed hosts. Only public
 * content rendered for a logged-out visitor can ever reach a provider
 * (CLAUDE.md section 10).
 */
final class ScanJob {

	/**
	 * Constructor.
	 *
	 * @param Settings                       $settings     Plugin settings.
	 * @param RunLock                        $lock         Translation run lock.
	 * @param WordCap                        $word_cap     Monthly word cap.
	 * @param ActionSchedulerQueue           $queue        Queue.
	 * @param SegmentExtractorInterface      $extractor    Segment extractor.
	 * @param SegmentRepositoryInterface     $segments     Segments repository.
	 * @param TranslationRepositoryInterface $translations Translations repository.
	 * @param LoggerInterface                $logger       Logger.
	 */
	public function __construct(
		private Settings $settings,
		private RunLock $lock,
		private WordCap $word_cap,
		private ActionSchedulerQueue $queue,
		private SegmentExtractorInterface $extractor,
		private SegmentRepositoryInterface $segments,
		private TranslationRepositoryInterface $translations,
		private LoggerInterface $logger
	) {}

	/**
	 * Run the scan.
	 *
	 * @param string $url     Normalized URL (already validated by enqueue_url).
	 * @param int    $post_id Related post id.
	 * @param string $run_id  Run id owning the lock.
	 * @throws \RuntimeException When the page cannot be fetched (marks the action failed).
	 */
	public function handle( string $url, int $post_id, string $run_id ): void {
		// The run was cancelled or replaced: do nothing.
		if ( $this->lock->current_run() !== $run_id ) {
			$this->logger->log( 'warning', 'queue:scan', 'Scan skipped: run no longer owns the lock.' );
			return;
		}

		$html = $this->fetch( $url );
		if ( null === $html ) {
			$this->queue->settle_run( $run_id );
			$this->logger->log( 'error', 'queue:scan', sprintf( 'Scan failed: the page could not be fetched (post_id=%d).', $post_id ) );
			throw new \RuntimeException( 'omtr: scan fetch failed' );
		}

		$source = $this->settings->source_lang();
		$found  = $this->extractor->extract( $html, $source );

		if ( array() === $found ) {
			$this->logger->log( 'info', 'queue:scan', sprintf( 'Scan finished: no translatable segment (post_id=%d).', $post_id ) );
			$this->queue->settle_run( $run_id );
			return;
		}

		$hash_ids = $this->segments->upsert_many( $found, $source );

		$total_batches = 0;
		foreach ( $this->settings->target_langs() as $lang ) {
			$total_batches += $this->schedule_language( $found, $hash_ids, $lang, $run_id );
		}

		if ( 0 === $total_batches ) {
			$this->logger->log( 'info', 'queue:scan', sprintf( 'Scan finished: nothing left to translate (post_id=%d).', $post_id ) );
			$this->queue->settle_run( $run_id );
			return;
		}

		$this->logger->log( 'info', 'queue:scan', sprintf( 'Scan finished: %d batch(es) scheduled (post_id=%d).', $total_batches, $post_id ) );
	}

	/**
	 * Schedule batches of untranslated segments for one language.
	 *
	 * @param array<string, string> $found     Hash => source text.
	 * @param array<string, int>    $hash_ids  Hash => segment id.
	 * @param string                $lang      Target language.
	 * @param string                $run_id    Run id owning the lock.
	 * @return int Number of batches scheduled.
	 */
	private function schedule_language( array $found, array $hash_ids, string $lang, string $run_id ): int {
		// Never pay twice: only segments without a translation for this language.
		$existing = $this->translations->find_many( array_keys( $found ), $lang );
		$missing  = array_diff_key( $found, $existing );

		if ( array() === $missing ) {
			return 0;
		}

		$provider = $this->queue->current_provider();
		$limits   = null !== $provider ? $provider->limits() : new \Omnium\Translate\Providers\RateLimits();

		$scheduled = 0;
		foreach ( BatchSizer::chunk( $missing, $limits ) as $batch ) {
			$words = $this->word_cap->count_words( $batch );
			if ( ! $this->word_cap->reserve( $words ) ) {
				$this->logger->log( 'warning', 'queue:scan', sprintf( 'Scan stopped early: monthly word cap reached (lang=%s).', $lang ) );
				return $scheduled;
			}

			$payload = array();
			foreach ( array_keys( $batch ) as $hash ) {
				if ( isset( $hash_ids[ $hash ] ) ) {
					$payload[ (int) $hash_ids[ $hash ] ] = $hash;
				}
			}

			// The run id travels with every batch so the lock can be settled.
			$this->queue->schedule_batch( $payload, $lang, $run_id, 1 );
			++$scheduled;
		}

		return $scheduled;
	}

	/**
	 * Fetch the page anonymously.
	 *
	 * @param string $url Normalized URL.
	 * @return string|null HTML body, null on failure.
	 */
	private function fetch( string $url ): ?string {
		$home   = wp_parse_url( (string) home_url() );
		$target = wp_parse_url( $url );
		if ( ! is_array( $home ) || ! is_array( $target ) || ! isset( $home['scheme'], $home['host'], $target['scheme'], $target['host'] ) ) {
			return null;
		}

		$home_scheme = strtolower( (string) $home['scheme'] );
		$home_host   = strtolower( (string) $home['host'] );
		$target_port = isset( $target['port'] ) ? (int) $target['port'] : ( 'https' === strtolower( (string) $target['scheme'] ) ? 443 : 80 );
		$home_port   = isset( $home['port'] ) ? (int) $home['port'] : ( 'https' === $home_scheme ? 443 : 80 );
		if ( strtolower( (string) $target['scheme'] ) !== $home_scheme || strtolower( (string) $target['host'] ) !== $home_host || $target_port !== $home_port ) {
			return null;
		}

		// wp_safe_remote_get() rejects loopback/private home hosts and custom
		// ports by default. Allow only this installation's exact home endpoint
		// while the anonymous request runs (not arbitrary redirect targets).
		$allow_home_host = static function ( bool $is_external, string $host ) use ( $home_host ): bool {
			return strtolower( $host ) === $home_host ? true : $is_external;
		};
		$allow_home_port = static function ( array $ports ) use ( $home_port ): array {
			if ( ! in_array( $home_port, $ports, true ) ) {
				$ports[] = $home_port;
			}
			return $ports;
		};
		add_filter( 'http_request_host_is_external', $allow_home_host, 10, 2 );
		add_filter( 'http_allowed_safe_ports', $allow_home_port );

		// Anonymous crawl: the WP HTTP API sends no cookie by default, and
		// redirects are refused so content can never be fetched from another host.
		try {
			$response = wp_safe_remote_get(
				$url,
				array(
					'timeout'     => 30,
					'redirection' => 0,
					'sslverify'   => true,
					'cookies'     => array(),
					'user-agent'  => 'Omnium-Translate/' . OMTR_VERSION . ' (internal crawler)',
				)
			);
		} finally {
			remove_filter( 'http_allowed_safe_ports', $allow_home_port );
			remove_filter( 'http_request_host_is_external', $allow_home_host, 10 );
		}

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$body = (string) wp_remote_retrieve_body( $response );
		return '' === $body ? null : $body;
	}
}
