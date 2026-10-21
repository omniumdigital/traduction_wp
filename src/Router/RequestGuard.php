<?php
/**
 * Front-request eligibility checks.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Keeps routing and rendering out of administrative and builder requests.
 */
final class RequestGuard {

	/**
	 * Whether language routing and final-output translation may run.
	 */
	public function is_public_front_request(): bool {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		foreach ( array( 'is_cart', 'is_checkout', 'is_account_page' ) as $conditional ) {
			if ( function_exists( $conditional ) && $conditional() ) {
				return false;
			}
		}

		$builder_keys = array( 'elementor-preview', 'ct_builder', 'oxygen_iframe', 'oxygen_gutenberg' );
		foreach ( $builder_keys as $key ) {
			if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request classification.
				$value = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( '' !== $value ) {
					return false;
				}
			}
		}
		return true;
	}
}
