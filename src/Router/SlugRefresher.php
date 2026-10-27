<?php
/**
 * Background refresh of translated slugs.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Core\TranslationCachePurger;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Store\SlugRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Rebuilds the translated slug of every published content.
 *
 * Slugs used to be generated only right after a batch saved new
 * translations, so a site translated before translated slugs existed kept
 * its source (French) URLs. A refresh is requested by the database
 * migration and by the admin button, then runs once in the background.
 * Manual slugs are never overwritten (SlugGenerator::generate()), and
 * replaced slugs stay in slug_history for the 301 redirect.
 */
final class SlugRefresher {

	public const ACTION = 'omtr_refresh_slugs';

	/**
	 * Constructor.
	 *
	 * @param SlugGenerator   $generator Slug generator.
	 * @param SlugRepository  $slugs     Slug repository.
	 * @param PublicContent   $content   Public content resolver.
	 * @param Settings        $settings  Plugin settings.
	 * @param LoggerInterface $logger    Logger.
	 */
	public function __construct(
		private SlugGenerator $generator,
		private SlugRepository $slugs,
		private PublicContent $content,
		private Settings $settings,
		private LoggerInterface $logger
	) {}

	/** Hook the background action and the pending-request check. */
	public function boot(): void {
		add_action( self::ACTION, array( $this, 'run' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ), 20 );
	}

	/** Ask for a refresh; it is scheduled on the next request. */
	public static function request(): void {
		update_option( Options::SLUG_REFRESH, 1, false );
	}

	/** Schedule the background refresh once when one was requested. */
	public function maybe_schedule(): void {
		if ( ! get_option( Options::SLUG_REFRESH ) || ! function_exists( 'as_enqueue_async_action' ) || ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( self::ACTION, array(), ActionSchedulerQueue::GROUP ) ) {
			as_enqueue_async_action( self::ACTION, array(), ActionSchedulerQueue::GROUP );
		}
	}

	/** Action Scheduler callback. */
	public function run(): void {
		$this->refresh();
	}

	/**
	 * Regenerate every translated slug that is missing or still a source copy.
	 *
	 * @return int Number of slugs changed.
	 */
	public function refresh(): int {
		delete_option( Options::SLUG_REFRESH );
		$changed = 0;
		foreach ( $this->content->all() as $target ) {
			$post_id = (int) $target['post_id'];
			if ( $post_id <= 0 ) {
				continue;
			}
			foreach ( $this->settings->target_langs() as $lang ) {
				$before = $this->slugs->get( $post_id, $lang );
				$after  = $this->generator->generate( $post_id, $lang );
				if ( null !== $after && $after !== $before ) {
					++$changed;
					TranslationCachePurger::purge( $post_id, $lang );
				}
			}
		}
		$this->logger->log( 'info', 'router:slugs', sprintf( 'Translated slugs refreshed: %d changed.', $changed ) );
		return $changed;
	}
}
