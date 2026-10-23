<?php
/**
 * Guarded on-the-fly enqueue trigger.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\RequestGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules an anonymous re-scan of a known public page when a visitor sees
 * untranslated segments on it.
 *
 * The visitor's rendered HTML is never used as a translation source: it can
 * reflect query-string input (search terms, tracking parameters, notices) or
 * session data (WooCommerce mini-cart). Only the page's canonical URL is queued;
 * ScanJob then fetches it anonymously, without query string or cookies
 * (CLAUDE.md §10 and §11, cost attacks).
 */
final class OnTheFlyTrigger {

	/**
	 * Minimum delay between two on-the-fly scans of the same URL, whatever the
	 * query string or language prefix used to reach it (one hour).
	 */
	private const WINDOW = 3600;

	/**
	 * Deferred target, kept in memory until shutdown.
	 *
	 * @var array{url: string, post_id: int}|null
	 */
	private ?array $pending = null;

	/**
	 * Constructor.
	 *
	 * @param Settings                  $settings     Plugin settings.
	 * @param RequestGuard              $guard        Request eligibility guard.
	 * @param PublicContent             $content      Public content service.
	 * @param TranslationQueueInterface $queue        Translation queue.
	 * @param WordCap                   $word_cap     Monthly word cap.
	 * @param HourlyQuota               $hourly_quota Hourly segment quota.
	 * @param QueueState                $state        Manual pause state.
	 */
	public function __construct(
		private Settings $settings,
		private RequestGuard $guard,
		private PublicContent $content,
		private TranslationQueueInterface $queue,
		private WordCap $word_cap,
		private HourlyQuota $hourly_quota,
		private QueueState $state
	) {}

	/**
	 * Remember the current page if it showed untranslated segments.
	 * No database write happens here.
	 *
	 * @param int $missing_segments Segments rendered without a stored translation.
	 */
	public function capture( int $missing_segments ): void {
		if ( $missing_segments <= 0 || ! (bool) $this->settings->get( 'on_the_fly' ) ) {
			return;
		}
		if ( $this->state->is_paused() || $this->word_cap->is_exhausted() || ! $this->hourly_quota->allows( 1 ) ) {
			return;
		}
		if ( ! $this->guard->is_crawlable_or_ai_eligible() ) {
			return;
		}

		$target = $this->content->resolve( (int) get_queried_object_id() );
		if ( null === $target ) {
			return;
		}
		$this->pending = $target;
	}

	/**
	 * Queue the canonical URL at shutdown, at most once per window.
	 */
	public function flush(): void {
		if ( null === $this->pending ) {
			return;
		}
		$target        = $this->pending;
		$this->pending = null;

		// The key derives from the normalized permalink, so query strings and
		// language prefixes cannot be used to bypass the window.
		$key = 'omtr_otf_' . hash( 'sha256', $target['url'] );
		if ( false !== get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, self::WINDOW );

		$this->queue->enqueue_url( $target['url'], $target['post_id'] );
	}
}
