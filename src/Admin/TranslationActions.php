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

	/** Register the classic editor action. */
	public function register_meta_box(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			return;
		}
		foreach ( get_post_types( array( 'public' => true ) ) as $post_type ) {
			add_meta_box( 'omtr-translate', __( 'Translate this page', 'omnium-translate' ), array( $this, 'meta_box' ), $post_type, 'side' );
		}
	}

	/** Render a nonce-protected classic editor form.
	 *
	 * @param \WP_Post $post Current post.
	 */
	public function meta_box( \WP_Post $post ): void {
		if ( ! Capabilities::current_user_can_manage() || null === $this->content->resolve( (int) $post->ID ) ) {
			return;
		}
		echo '<form method="get" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="omtr_translate_post">';
		echo '<input type="hidden" name="post_id" value="' . esc_attr( (string) $post->ID ) . '">';
		wp_nonce_field( 'omtr_translate_' . (int) $post->ID );
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Translate this page', 'omnium-translate' ) . '</button></form>';
	}

	/** Load the block editor panel only for translators. */
	public function enqueue_block_editor(): void {
		if ( ! Capabilities::current_user_can_manage() || ! file_exists( OMTR_DIR . 'assets/build/editor.asset.php' ) ) {
			return;
		}
		$asset = require OMTR_DIR . 'assets/build/editor.asset.php';
		wp_enqueue_script( 'omtr-editor', OMTR_URL . 'assets/build/editor.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'omtr-editor', 'omnium-translate', OMTR_DIR . 'languages' );
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
