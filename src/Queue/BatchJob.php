<?php
/**
 * Batch job: translate a batch of segments through the provider.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Core\TranslationCachePurger;
use Omnium\Translate\Glossary\GlossaryService;
use Omnium\Translate\Providers\ErrorClassifier;
use Omnium\Translate\Providers\ProviderException;
use Omnium\Translate\Providers\ProviderInterface;
use Omnium\Translate\Providers\TranslationQualityValidator;
use Omnium\Translate\Router\SlugGenerator;
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
	 * @param Settings                         $settings     Plugin settings.
	 * @param RunLock                          $lock         Translation run lock.
	 * @param ActionSchedulerQueue             $queue        Queue.
	 * @param SegmentRepositoryInterface       $segments     Segments repository.
	 * @param TranslationRepositoryInterface   $translations Translations repository.
	 * @param LoggerInterface                  $logger       Logger.
	 * @param GlossaryService                  $glossary     Glossary service.
	 * @param QueueState|null                  $state        Manual pause state.
	 * @param SlugGenerator|null               $slug_generator Translated slug generator.
	 * @param TranslationQualityValidator|null $quality      Final provider-independent quality gate.
	 * @param WordCap|null                     $word_cap     Word cap, used to count translated words (always injected by QueueModule).
	 */
	public function __construct(
		private Settings $settings,
		private RunLock $lock,
		private ActionSchedulerQueue $queue,
		private SegmentRepositoryInterface $segments,
		private TranslationRepositoryInterface $translations,
		private LoggerInterface $logger,
		private GlossaryService $glossary,
		private ?QueueState $state = null,
		private ?SlugGenerator $slug_generator = null,
		private ?TranslationQualityValidator $quality = null,
		private ?WordCap $word_cap = null
	) {}

	/**
	 * Run the batch.
	 *
	 * @param array<int, string> $segments Segment id => hash.
	 * @param string             $lang     Target language.
	 * @param string             $run_id   Run id owning the lock ('' when standalone).
	 * @param int                $attempt  Attempt number (1-based).
	 * @param int                $post_id  Related post id.
	 */
	public function handle( array $segments, string $lang, string $run_id, int $attempt, int $post_id = 0 ): void {
		if ( null !== $this->state && $this->state->is_paused() ) {
			$this->queue->schedule_batch( $segments, $lang, $run_id, $attempt, 60, $post_id );
			$this->logger->log( 'info', 'queue:batch', 'Batch deferred: queue is paused.' );
			return;
		}
		if ( '' !== $run_id && null === $this->lock->current_run() ) {
			$this->lock->acquire( $run_id );
		}
		if ( '' !== $run_id && $this->lock->current_run() !== $run_id ) {
			$this->queue->schedule_batch( $segments, $lang, $run_id, $attempt, 60, $post_id );
			$this->logger->log( 'warning', 'queue:batch', 'Batch deferred: another run owns the lock.' );
			return;
		}
		if ( '' !== $run_id ) {
			$this->lock->heartbeat( $run_id );
		}

		// Reload texts and drop anything deleted since scheduling.
		$hashes = array_values( $segments );
		$texts  = $this->segments->texts_by_hash( $hashes );
		if ( array() === $texts ) {
			$this->queue->settle_run( $run_id, true );
			return;
		}

		// Never pay twice: skip segments translated in the meantime (the
		// repository also keeps edited and locked rows in the result).
		$existing = $this->translations->find_many( array_keys( $texts ), $lang );
		$pending  = array_diff_key( $texts, $existing );
		if ( array() === $pending ) {
			// Nothing to translate, but the title may be translated already:
			// make sure the page also gets its translated URL.
			if ( $post_id > 0 && null !== $this->slug_generator ) {
				$this->slug_generator->generate( $post_id, $lang );
			}
			$this->logger->log( 'info', 'queue:batch', 'Batch skipped: every segment is already translated.' );
			$this->queue->settle_run( $run_id, true );
			return;
		}

		$provider = $this->queue->current_provider();
		if ( null === $provider ) {
			$this->logger->log( 'error', 'queue:batch', 'Batch failed: no provider is configured.' );
			$this->mark_failed( $pending, $segments, $lang );
			$this->queue->settle_run( $run_id, true );
			return;
		}

		$glossary = $this->glossary->for_language( $lang );

		// A segment longer than one provider request can carry would fail the
		// whole batch with an unretryable rejection: exclude it upfront and
		// mark it failed with an explicit reason instead.
		$max_chars = max( 1, $provider->limits()->max_batch_chars );
		$oversized = array_filter(
			$pending,
			static fn( string $text ): bool => strlen( $text ) > $max_chars
		);
		if ( array() !== $oversized ) {
			foreach ( array_keys( $oversized ) as $hash ) {
				$this->logger->log(
					'error',
					'queue:batch',
					sprintf( 'Segment skipped: too long for a single request (%d chars, max %d).', strlen( $oversized[ $hash ] ), $max_chars )
				);
			}
			$this->mark_failed_by_hash( array_keys( $oversized ), $segments, $lang );
			$pending = array_diff_key( $pending, $oversized );
			if ( array() === $pending ) {
				$this->queue->settle_run( $run_id, true );
				return;
			}
		}

		try {
			$translated = $provider->translate_batch( $pending, $this->settings->source_lang(), $lang, $glossary );
		} catch ( ProviderException $e ) {
			$this->handle_error( $e, $pending, $segments, $lang, $run_id, $attempt, $post_id );
			return;
		}

		$quality         = $this->quality ?? new TranslationQualityValidator();
		$saved           = array();
		$accepted_hashes = array();
		foreach ( $translated as $hash => $text ) {
			$id    = array_search( $hash, $segments, true );
			$clean = Sanitizer::inline_html( $text );
			// Identical-to-source text was already arbitrated by the provider's
			// second attempt (BatchRunner): only re-check structure and glossary here.
			if ( false !== $id && isset( $pending[ $hash ] ) && $quality->is_valid( $pending[ $hash ], $clean, $glossary, true ) ) {
				// Untrusted AI output: strict whitelist sanitization before storage.
				$saved[ $id ]      = $clean;
				$accepted_hashes[] = $hash;
			}
		}

		if ( array() !== $saved ) {
			$this->translations->save_auto( $saved, $lang, $provider->id(), $this->model_id( $provider ) );
			if ( null !== $this->word_cap ) {
				// Statistics only: source words of segments successfully saved.
				$this->word_cap->record_translated(
					$this->word_cap->count_words( array_intersect_key( $pending, array_flip( $accepted_hashes ) ) )
				);
			}
			if ( $post_id > 0 && null !== $this->slug_generator ) {
				$this->slug_generator->generate( $post_id, $lang );
			}
			TranslationCachePurger::purge( $post_id, $lang );
		}

		$untranslated = array_diff( array_keys( $pending ), $accepted_hashes );
		if ( array() !== $untranslated ) {
			$this->logger->log( 'warning', 'queue:batch', sprintf( 'Batch partial: %d segment(s) not translated.', count( $untranslated ) ) );
			$this->mark_failed_by_hash( $untranslated, $segments, $lang );
		}

		$this->logger->log( 'info', 'queue:batch', sprintf( 'Batch done: %d segment(s) saved (lang=%s, attempt=%d).', count( $saved ), $lang, $attempt ) );
		$this->queue->settle_run( $run_id, true );
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
	 * @param int                   $post_id  Related post id.
	 */
	private function handle_error( ProviderException $e, array $pending, array $segments, string $lang, string $run_id, int $attempt, int $post_id ): void {
		if ( $e->retryable && $attempt < ActionSchedulerQueue::MAX_ATTEMPTS ) {
			$delay = $this->queue->backoff_delay( $attempt );
			$this->logger->log(
				'warning',
				'queue:batch',
				sprintf( 'Batch retry scheduled (http=%d, attempt=%d/%d, delay=%ds).', $e->http_code, $attempt + 1, ActionSchedulerQueue::MAX_ATTEMPTS, $delay )
			);
			$this->queue->schedule_batch( $segments, $lang, $run_id, $attempt + 1, $delay, $post_id );
			return;
		}

		// A batch rejected as too large is split in half and each half is
		// retried: this isolates the oversized segment instead of failing
		// its 50 neighbours on every manual retry.
		if ( ErrorClassifier::TOO_LARGE === $e->getMessage() && count( $segments ) > 1 && $attempt < ActionSchedulerQueue::MAX_ATTEMPTS ) {
			$chunks = array_chunk( $segments, (int) ceil( count( $segments ) / 2 ), true );
			$this->logger->log(
				'warning',
				'queue:batch',
				sprintf( 'Batch rejected as too large: split into %d halves and rescheduled (http=%d, segments=%d).', count( $chunks ), $e->http_code, count( $segments ) )
			);
			foreach ( $chunks as $chunk ) {
				$this->queue->schedule_batch( $chunk, $lang, $run_id, $attempt + 1, 0, $post_id );
			}
			$this->queue->settle_run( $run_id, true );
			return;
		}

		$this->logger->log(
			'error',
			'queue:batch',
			sprintf( 'Batch failed after %d attempt(s) (http=%d%s): %s', $attempt, $e->http_code, '' !== $e->provider_code ? ', code=' . $e->provider_code : '', $e->getMessage() )
		);
		$this->mark_failed( $pending, $segments, $lang );
		$this->queue->settle_run( $run_id, true );
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
