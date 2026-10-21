<?php
/**
 * Batch job: translate a batch of segments through the provider.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\Glossary;
use Omnium\Translate\Providers\ProviderException;
use Omnium\Translate\Providers\ProviderInterface;
use Omnium\Translate\Security\Sanitizer;
use Omnium\Translate\Store\SegmentRepositoryInterface;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Handler of the "omtr_translate_batch" action (group "omtr").
 *
 * Every AI response is untrusted: each translated text goes through
 * Sanitizer::inline_html() before storage (CLAUDE.md section 11), and
 * segments already translated are never sent again.
 *
 * Retry policy: retryable provider errors (HTTP 429 / 5xx, network) are
 * rescheduled with the backoff 30 s, 2 min, 10 min, 1 h, up to 5 attempts
 * in total; then the segments are marked failed.
 */
final class BatchJob {

	/**
	 * Constructor.
	 *
	 * @param Settings                       $settings     Plugin settings.
	 * @param RunLock                        $lock         Translation run lock.
	 * @param ActionSchedulerQueue           $queue        Queue.
	 * @param SegmentRepositoryInterface     $segments     Segments repository.
	 * @param TranslationRepositoryInterface $translations Translations repository.
	 * @param LoggerInterface                $logger       Logger.
	 */
	public function __construct(
		private Settings $settings,
		private RunLock $lock,
		private ActionSchedulerQueue $queue,
		private SegmentRepositoryInterface $segments,
		private TranslationRepositoryInterface $translations,
		private LoggerInterface $logger
	) {}

	/**
	 * Run the batch.
	 *
	 * @param array<int, string> $segments Segment id => hash.
	 * @param string             $lang     Target language.
	 * @param string             $run_id   Run id owning the lock ('' when standalone).
	 * @param int                $attempt  Attempt number (1-based).
	 */
	public function handle( array $segments, string $lang, string $run_id, int $attempt ): void {
		if ( '' !== $run_id ) {
			$this->lock->heartbeat( $run_id );
		}

		// Reload texts and drop anything deleted since scheduling.
		$hashes = array_values( $segments );
		$texts  = $this->segments->texts_by_hash( $hashes );
		if ( array() === $texts ) {
			$this->queue->settle_run( $run_id );
			return;
		}

		// Never pay twice: skip segments translated in the meantime (the
		// repository also keeps edited and locked rows in the result).
		$existing = $this->translations->find_many( array_keys( $texts ), $lang );
		$pending  = array_diff_key( $texts, $existing );
		if ( array() === $pending ) {
			$this->logger->log( 'info', 'queue:batch', 'Batch skipped: every segment is already translated.' );
			$this->queue->settle_run( $run_id );
			return;
		}

		$provider = $this->queue->current_provider();
		if ( null === $provider ) {
			$this->logger->log( 'error', 'queue:batch', 'Batch failed: no provider is configured.' );
			$this->mark_failed( $pending, $segments, $lang );
			$this->queue->settle_run( $run_id );
			return;
		}

		$glossary = new Glossary(
			array(),
			array(),
			(string) $this->settings->get( 'site_tone' ),
			(string) $this->settings->get( 'site_context' )
		);

		try {
			$translated = $provider->translate_batch( $pending, $this->settings->source_lang(), $lang, $glossary );
		} catch ( ProviderException $e ) {
			$this->handle_error( $e, $pending, $segments, $lang, $run_id, $attempt );
			return;
		}

		$saved = array();
		foreach ( $translated as $hash => $text ) {
			$id = array_search( $hash, $segments, true );
			if ( false !== $id ) {
				// Untrusted AI output: strict whitelist sanitization before storage.
				$saved[ $id ] = Sanitizer::inline_html( $text );
			}
		}

		if ( array() !== $saved ) {
			$this->translations->save_auto( $saved, $lang, $provider->id(), $this->model_id( $provider ) );
		}

		$untranslated = array_diff( array_keys( $pending ), array_keys( $translated ) );
		if ( array() !== $untranslated ) {
			$this->logger->log( 'warning', 'queue:batch', sprintf( 'Batch partial: %d segment(s) not translated.', count( $untranslated ) ) );
			$this->mark_failed_by_hash( $untranslated, $segments, $lang );
		}

		$this->logger->log( 'info', 'queue:batch', sprintf( 'Batch done: %d segment(s) saved (lang=%s, attempt=%d).', count( $saved ), $lang, $attempt ) );
		$this->queue->settle_run( $run_id );
	}

	/**
	 * Handle a provider error: backoff retry or final failure.
	 *
	 * @param ProviderException     $e        Provider error (generic message only).
	 * @param array<string, string> $pending  Hash => text of untranslated segments.
	 * @param array<int, string>    $segments Segment id => hash.
	 * @param string                $lang     Target language.
	 * @param string                $run_id   Run id.
	 * @param int                   $attempt  Attempt number (1-based).
	 */
	private function handle_error( ProviderException $e, array $pending, array $segments, string $lang, string $run_id, int $attempt ): void {
		if ( $e->retryable && $attempt < ActionSchedulerQueue::MAX_ATTEMPTS ) {
			$delay = $this->queue->backoff_delay( $attempt );
			$this->logger->log(
				'warning',
				'queue:batch',
				sprintf( 'Batch retry scheduled (http=%d, attempt=%d/%d, delay=%ds).', $e->http_code, $attempt + 1, ActionSchedulerQueue::MAX_ATTEMPTS, $delay )
			);
			$this->queue->schedule_batch( $segments, $lang, $run_id, $attempt + 1, $delay );
			return;
		}

		$this->logger->log(
			'error',
			'queue:batch',
			sprintf( 'Batch failed after %d attempt(s) (http=%d).', $attempt, $e->http_code )
		);
		$this->mark_failed( $pending, $segments, $lang );
		$this->queue->settle_run( $run_id );
	}

	/**
	 * Resolve the model id used for the storage row.
	 *
	 * @param ProviderInterface $provider Provider.
	 */
	private function model_id( ProviderInterface $provider ): string {
		$chosen = (string) $this->settings->get( 'model' );
		$models = $provider->models();
		if ( '' !== $chosen && isset( $models[ $chosen ] ) ) {
			return $chosen;
		}
		$first = array_key_first( $models );
		return is_string( $first ) ? $first : '';
	}

	/**
	 * Mark untranslated segments as failed, by hash set.
	 *
	 * @param array<string, string> $pending  Hash => text.
	 * @param array<int, string>    $segments Segment id => hash.
	 * @param string                $lang     Target language.
	 */
	private function mark_failed( array $pending, array $segments, string $lang ): void {
		$this->mark_failed_by_hash( array_keys( $pending ), $segments, $lang );
	}

	/**
	 * Mark segments failed by hash list.
	 *
	 * @param list<string>       $hashes   Segment hashes.
	 * @param array<int, string> $segments Segment id => hash.
	 * @param string             $lang     Target language.
	 */
	private function mark_failed_by_hash( array $hashes, array $segments, string $lang ): void {
		$ids = array();
		foreach ( $hashes as $hash ) {
			$id = array_search( $hash, $segments, true );
			if ( false !== $id ) {
				$ids[] = $id;
			}
		}
		if ( array() !== $ids ) {
			$this->translations->mark_failed( $ids, $lang );
		}
	}
}
