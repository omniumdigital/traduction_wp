<?php
/** Front-end visual editor gate and assets.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Render;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Core\Tables;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/** Enables private, uncached editing of translated text. */
final class VisualEditor {
	/** Constructor.
	 *
	 * @param Settings        $settings Site settings.
	 * @param CurrentLanguage $language Current language.
	 */
	public function __construct( private Settings $settings, private CurrentLanguage $language ) {}

	/** Whether this request can contain editor markup. */
	public function active(): bool {
		$edit = isset( $_GET['omtr_edit'] ) ? sanitize_text_field( wp_unslash( $_GET['omtr_edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only presentation flag, additionally gated by capability.
		return '1' === $edit && (bool) $this->settings->get( 'visual_editor' ) && $this->language->is_translated() && Capabilities::current_user_can_manage() && ! is_admin() && ! wp_doing_ajax() && is_singular();
	}

	/** Prevent storage of a personalized response. */
	public function no_cache(): void {
		if ( $this->active() ) {
			nocache_headers();
		}
	}

	/** Add an editor toggle to the toolbar.
	 *
	 * @param \WP_Admin_Bar $bar Toolbar.
	 */
	public function admin_bar( \WP_Admin_Bar $bar ): void {
		if ( ! (bool) $this->settings->get( 'visual_editor' ) || ! $this->language->is_translated() || ! Capabilities::current_user_can_manage() || is_admin() ) {
			return;
		}
		$bar->add_node(
			array(
				'id'    => 'omtr-visual-editor',
				'title' => $this->active() ? esc_html__( 'Exit visual editor', 'omnium-translate' ) : esc_html__( 'Edit translations', 'omnium-translate' ),
				'href'  => esc_url( $this->active() ? remove_query_arg( 'omtr_edit' ) : add_query_arg( 'omtr_edit', '1' ) ),
			)
		);
	}

	/** Load a dependency-free editor only on authorized requests. */
	public function enqueue(): void {
		if ( ! $this->active() ) {
			return;
		}
		wp_enqueue_script( 'omtr-visual-editor', OMTR_URL . 'assets/build/visual-editor.js', array(), OMTR_VERSION, true );
		$config = array(
			'url'      => rest_url( 'omtr/v1/segments/' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'language' => $this->language->get(),
			'postId'   => (int) get_queried_object_id(),
			'save'     => __( 'Save translation', 'omnium-translate' ),
			'cancel'   => __( 'Cancel', 'omnium-translate' ),
		);
		wp_add_inline_script( 'omtr-visual-editor', 'window.omtrVisualEditor = ' . wp_json_encode( $config ) . ';', 'before' );
	}

	/** Resolve existing ids without writing new segments.
	 *
	 * @param list<string> $hashes Segment hashes.
	 * @return array<string, int>
	 */
	public function ids_by_hash( array $hashes ): array {
		if ( ! $this->active() || array() === $hashes ) {
			return array();
		}
		$wpdb         = $GLOBALS['wpdb'];
		$placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );
		// The placeholder string is derived exclusively from the number of hashes.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $wpdb->prepare( 'SELECT hash, id FROM %i WHERE hash IN (' . $placeholders . ')', Tables::name( Tables::SEGMENTS ), ...$hashes );
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		// No persistent cache: editor-only segment ids must reflect the current translation and cannot leak into a public page.
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared above; private uncached response.
		$ids  = array();
		foreach ( $rows as $row ) {
			$ids[ (string) $row['hash'] ] = (int) $row['id'];
		}
		return $ids;
	}
}
