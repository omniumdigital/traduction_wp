<?php
/**
 * WooCommerce catalog and variation labels.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Woo;

use Omnium\Translate\Render\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * Applies stored translations to human-facing variation fields only.
 */
final class CatalogTranslator {

	/**
	 * Constructor.
	 *
	 * @param Translator $translator Stored-translation renderer.
	 */
	public function __construct( private Translator $translator ) {}

	/** Register WooCommerce variation rendering filters. */
	public function register_hooks(): void {
		add_filter( 'woocommerce_available_variation', array( $this, 'translate_variation' ), 20, 3 );
		add_filter( 'woocommerce_dropdown_variation_attribute_options_args', array( $this, 'translate_selector_args' ), 20, 1 );
	}

	/**
	 * Translate human-facing variation HTML; identifiers and commercial values stay intact.
	 *
	 * @param mixed $variation Variation data array.
	 * @param mixed $product   Variable product (unused).
	 * @param mixed $variation_product Variation product (unused).
	 * @return mixed
	 */
	public function translate_variation( mixed $variation, mixed $product = null, mixed $variation_product = null ): mixed {
		unset( $product, $variation_product );
		if ( ! is_array( $variation ) ) {
			return $variation;
		}
		foreach ( array( 'availability_html', 'variation_description' ) as $key ) {
			if ( isset( $variation[ $key ] ) && is_string( $variation[ $key ] ) ) {
				$variation[ $key ] = $this->translator->translate_fragment( $variation[ $key ] );
			}
		}
		return $variation;
	}

	/**
	 * Translate visible selector labels while preserving option values/slugs.
	 *
	 * @param mixed $args Variation selector arguments.
	 * @return mixed
	 */
	public function translate_selector_args( mixed $args ): mixed {
		if ( ! is_array( $args ) ) {
			return $args;
		}
		foreach ( array( 'show_option_none', 'aria-label' ) as $key ) {
			if ( isset( $args[ $key ] ) && is_string( $args[ $key ] ) ) {
				$args[ $key ] = $this->translator->translate_fragment( $args[ $key ] );
				$args[ $key ] = trim( wp_strip_all_tags( $args[ $key ] ) );
			}
		}
		return $args;
	}
}
