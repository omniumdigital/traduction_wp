<?php
/**
 * Guarded on-the-fly enqueue trigger.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Store\SegmentRepositoryInterface;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/** Defers unknown front-end segments until shutdown under strict guards. */
final class OnTheFlyTrigger {
	private const WINDOW = 300;

	/**
	 * Deferred request payload.
	 *
	 * @var array{html: string, url: string, post_id: int, lang: string}|null
	 */
	private ?array $pending = null;

	/**
	 * Constructor.
	 *
	 * @param Settings                       $settings Plugin settings.
	 * @param RequestGuard                   $guard Request eligibility guard.
	 * @param CurrentLanguage                $language Current target language.
	 * @param PublicContent                  $content Public content service.
	 * @param SegmentExtractorInterface      $extractor Segment extractor.
	 * @param SegmentRepositoryInterface     $segments Segment repository.
	 * @param TranslationRepositoryInterface $translations Translation repository.
	 * @param ActionSchedulerQueue           $queue Translation queue.
	 * @param WordCap                        $word_cap Monthly word cap.
	 * @param HourlyQuota                    $hourly_quota Hourly segment quota.
	 * @param QueueState                     $state Manual pause state.
	 */
	public function __construct(
		private Settings $settings,
		private RequestGuard $guard,
		private CurrentLanguage $language,
		private PublicContent $content,
		private SegmentExtractorInterface $extractor,
		private SegmentRepositoryInterface $segments,
		private TranslationRepositoryInterface $translations,
		private ActionSchedulerQueue $queue,
		private WordCap $word_cap,
		private HourlyQuota $hourly_quota,
		private QueueState $state
	) {}

	/**
	 * Remember eligible HTML in memory; no database write happens here.
	 *
	 * @param string $html Rendered source HTML.
	 */
	public function capture( string $html ): void {
		if ( ! (bool) $this->settings->get( 'on_the_fly' ) || $this->state->is_paused() || $this->word_cap->is_exhausted() || ! $this->hourly_quota->allows( 1 ) || ! $this->guard->is_crawlable_or_ai_eligible() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		$target  = $this->content->resolve( $post_id );
		if ( null === $target ) {
			return;
		}
		$key = 'omtr_otf_' . hash( 'sha256', $target['url'] );
		if ( false !== get_transient( $key ) ) {
			return;
		}
		$this->pending = array(
			'html'    => $html,
			'url'     => $target['url'],
			'post_id' => $post_id,
			'lang'    => $this->language->get(),
		);
	}

	/** Write and enqueue after the response has been rendered. */
	public function flush(): void {
		if ( null === $this->pending ) {
			return;
		}
		$pending       = $this->pending;
		$this->pending = null;
		$key           = 'omtr_otf_' . hash( 'sha256', $pending['url'] );
		if ( false !== get_transient( $key ) ) {
			return;
		}
		set_transient( $key, 1, self::WINDOW );
		$found    = $this->extractor->extract( $pending['html'], $this->settings->source_lang() );
		$existing = $this->translations->find_many( array_keys( $found ), $pending['lang'] );
		$missing  = array_diff_key( $found, $existing );
		if ( array() === $missing ) {
			return;
		}
		$ids = $this->segments->upsert_many( $missing, $this->settings->source_lang() );
		$this->queue->enqueue_segments( array_values( $ids ), $pending['lang'] );
	}
}
