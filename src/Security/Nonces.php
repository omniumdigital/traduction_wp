<?php
/**
 * Nonce helpers.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Namespaced nonce actions. REST routes rely on the core wp_rest nonce
 * (X-WP-Nonce) plus a permission_callback; this class covers admin forms and admin-post.
 */
final class Nonces {

	/**
	 * Prefixed action name.
	 *
	 * @param string $action Short action.
	 */
	public static function action( string $action ): string {
		return 'omtr_' . $action;
	}

	/**
	 * Print a nonce field for a form.
	 *
	 * @param string $action Short action.
	 */
	public static function field( string $action ): void {
		wp_nonce_field( self::action( $action ), '_omtr_nonce' );
	}

	/**
	 * Verify capability and nonce for an admin request; dies on failure.
	 *
	 * @param string $action Short action.
	 */
	public static function check_admin( string $action ): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to perform this action.', 'omnium-translate' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( self::action( $action ), '_omtr_nonce' );
	}
}
