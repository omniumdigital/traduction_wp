<?php
/**
 * Classic admin translation actions.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Admin;

use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/** Adds nonce-protected page/post translation buttons. */
final class TranslationActions {
	/**
	 * Constructor.
	 *
	 * @param ActionSchedulerQueue $queue Translation queue.
	 * @param PublicContent        $content Public content service.
	 */
	public function __construct(
		private ActionSchedulerQueue $queue,
		private PublicContent $content
	) {}

	/**
	 * Add a row action for eligible posts.
	 *
	 * @param array<string, string> $actions Existing actions.
	 * @param \WP_Post              $post Post object.
	 * @return array<string, string>
	 */
	public function row_action( array $actions, \WP_Post $post ): array {
		if ( ! Capabilities::current_user_can_manage() || null === $this->content->resolve( (int) $post->ID ) ) {
			return $actions;
		}
		$url                       = wp_nonce_url(
			admin_url( 'admin-post.php?action=omtr_translate_post&post_id=' . (int) $post->ID ),
			'omtr_translate_' . (int) $post->ID
		);
		$actions['omtr_translate'] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Translate', 'omnium-translate' ) );
		return $actions;
	}

	/**
	 * Add a front-end admin bar button.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public function admin_bar( \WP_Admin_Bar $bar ): void {
		if ( is_admin() || ! Capabilities::current_user_can_manage() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		if ( null === $this->content->resolve( $post_id ) ) {
			return;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=omtr_translate_post&post_id=' . $post_id ), 'omtr_translate_' . $post_id );
		$bar->add_node(
			array(
				'id'    => 'omtr-translate',
				'title' => esc_html__( 'Translate', 'omnium-translate' ),
				'href'  => esc_url( $url ),
			)
		);
	}

	/** Handle the nonce-protected admin-post request. */
	public function handle(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to translate this content.', 'omnium-translate' ), '', array( 'response' => 403 ) );
		}
		$post_id = isset( $_GET['post_id'] ) ? absint( wp_unslash( $_GET['post_id'] ) ) : 0;
		check_admin_referer( 'omtr_translate_' . $post_id );
		$target   = $this->content->resolve( $post_id );
		$queued   = null !== $target && $this->queue->enqueue_url( $target['url'], $target['post_id'] );
		$referer  = wp_get_referer();
		$redirect = is_string( $referer ) && '' !== $referer ? $referer : admin_url( 'edit.php' );
		$redirect = add_query_arg( 'omtr_status', $queued ? 'queued' : 'refused', $redirect );
		wp_safe_redirect( $redirect );
		exit;
	}
}
