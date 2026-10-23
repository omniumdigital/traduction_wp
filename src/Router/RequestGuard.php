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
		if ( is_admin() || wp_doing_cron() || ( wp_doing_ajax() && ! $this->is_woocommerce_fragment_request() ) ) {
			return false;
		}
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || $this->has_rest_path_prefix() ) {
			return false;
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

	/**
	 * Whether a front request may be crawled or enqueue content for AI translation.
	 *
	 * Rendering stored translations has deliberately broader eligibility: a visitor may
	 * see translated cart, checkout and account templates, but their personalized HTML
	 * must never be crawled or sent to an AI provider.
	 */
	public function is_crawlable_or_ai_eligible(): bool {
		if ( ! $this->is_public_front_request() || is_user_logged_in() || is_preview() || post_password_required() ) {
			return false;
		}

		foreach ( array( 'is_cart', 'is_checkout', 'is_account_page' ) as $conditional ) {
			if ( function_exists( $conditional ) && $conditional() ) {
				return false;
			}
		}

		return ! ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' ) );
	}

	/**
	 * Detect WooCommerce's public cart-fragment refresh request.
	 */
	private function is_woocommerce_fragment_request(): bool {
		if ( ! isset( $_GET['wc-ajax'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only request classification.
			return false;
		}
		$action = sanitize_key( wp_unslash( (string) $_GET['wc-ajax'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return 'get_refreshed_fragments' === $action;
	}

	/**
	 * Detect REST requests before WordPress defines REST_REQUEST.
	 */
	private function has_rest_path_prefix(): bool {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || ! function_exists( 'rest_get_url_prefix' ) ) {
			return false;
		}
		$request_uri = sanitize_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ) );
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$prefix      = trim( rest_get_url_prefix(), '/' );
		return '' !== $prefix && 1 === preg_match( '#(?:^|/)' . preg_quote( $prefix, '#' ) . '(?:/|$)#', $path );
	}
}
