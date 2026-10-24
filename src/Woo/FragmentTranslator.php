<?php
/**
 * Stored translations for WooCommerce cart fragments.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Woo;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\CurrentLanguage;

defined( 'ABSPATH' ) || exit;

/**
 * Translates fragment HTML with stored translations only.
 */
final class FragmentTranslator {

	/**
	 * Constructor.
	 *
	 * @param CurrentLanguage $current_language Current request language.
	 * @param Settings        $settings         Plugin settings.
	 * @param Translator      $translator       Stored-translation renderer.
	 */
	public function __construct(
		private CurrentLanguage $current_language,
		private Settings $settings,
		private Translator $translator
	) {}

	/** Register WooCommerce's documented fragment filter. */
	public function register_hooks(): void {
		add_filter( 'woocommerce_add_to_cart_fragments', array( $this, 'translate_fragments' ), 20, 1 );
	}

	/**
	 * Translate each fragment in the language of the requesting page.
	 *
	 * @param mixed $fragments Selector => HTML map.
	 * @return mixed
	 */
	public function translate_fragments( mixed $fragments ): mixed {
		if ( ! is_array( $fragments ) ) {
			return $fragments;
		}
		$lang = $this->requested_language();
		if ( null === $lang ) {
			return $fragments;
		}
		$previous = $this->current_language->get();
		$this->current_language->set( $lang );
		foreach ( $fragments as $selector => $html ) {
			if ( is_string( $selector ) && is_string( $html ) ) {
				$fragments[ $selector ] = $this->translator->translate_fragment( $html );
			}
		}
		$this->current_language->set( $previous );
		return $fragments;
	}

	/** Resolve only source or configured target languages. */
	private function requested_language(): ?string {
		$allowed = array_values( array_unique( array_merge( array( $this->settings->source_lang() ), $this->settings->target_langs() ) ) );
		if ( isset( $_GET['lang'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fragment language hint.
			$lang = sanitize_key( wp_unslash( (string) $_GET['lang'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return in_array( $lang, $allowed, true ) ? $lang : null;
		}
		if ( isset( $_GET['language'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only fragment language hint.
			$lang = sanitize_key( wp_unslash( (string) $_GET['language'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return in_array( $lang, $allowed, true ) ? $lang : null;
		}
		$referer = wp_get_referer();
		if ( ! is_string( $referer ) || '' === $referer ) {
			return $this->current_language->get();
		}
		$referer_parts = wp_parse_url( $referer );
		$home_parts    = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $referer_parts ) || ! is_array( $home_parts ) || ( $referer_parts['host'] ?? '' ) !== ( $home_parts['host'] ?? '' ) ) {
			return $this->current_language->get();
		}
		$path  = trim( (string) ( $referer_parts['path'] ?? '' ), '/' );
		$first = '' === $path ? '' : sanitize_key( (string) strtok( $path, '/' ) );
		return in_array( $first, $allowed, true ) ? $first : $this->current_language->get();
	}
}
