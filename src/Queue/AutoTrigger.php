<?php
/**
 * Automatic publication trigger.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/** Defers automatic translation after a public post is saved. */
final class AutoTrigger {
	public const ACTION = 'omtr_auto_translate_post';

	/**
	 * Constructor.
	 *
	 * @param Settings             $settings Plugin settings.
	 * @param PublicContent        $content Public content service.
	 * @param ActionSchedulerQueue $queue Translation queue.
	 */
	public function __construct(
		private Settings $settings,
		private PublicContent $content,
		private ActionSchedulerQueue $queue
	) {}

	/**
	 * Schedule a post after publication/update, never translate in this request.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post Post object.
	 * @param bool     $update Whether this is an update.
	 */
	public function on_save( int $post_id, \WP_Post $post, bool $update ): void {
		unset( $update );
		if ( ! (bool) $this->settings->get( 'auto_translate' ) || 'publish' !== $post->post_status || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( null === $this->content->resolve( $post_id ) || as_has_scheduled_action( self::ACTION, array( 'post_id' => $post_id ), ActionSchedulerQueue::GROUP ) ) {
			return;
		}
		as_schedule_single_action( time() + 10, self::ACTION, array( 'post_id' => $post_id ), ActionSchedulerQueue::GROUP );
	}

	/**
	 * Run the deferred enqueue.
	 *
	 * @param int $post_id Post id.
	 */
	public function handle( int $post_id ): void {
		$target = $this->content->resolve( $post_id );
		if ( null === $target ) {
			return;
		}
		if ( ! $this->queue->enqueue_url( $target['url'], $target['post_id'] ) && $this->queue->last_reason() === __( 'A translation run is already in progress.', 'omnium-translate' ) ) {
			as_schedule_single_action( time() + 60, self::ACTION, array( 'post_id' => $post_id ), ActionSchedulerQueue::GROUP );
		}
	}
}
