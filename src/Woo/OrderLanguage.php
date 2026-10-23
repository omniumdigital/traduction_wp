<?php
/**
 * Records the language selected when a WooCommerce order is created.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Woo;

use Omnium\Translate\Router\CurrentLanguage;

defined( 'ABSPATH' ) || exit;

/**
 * HPOS-compatible order language persistence.
 */
final class OrderLanguage {

	/** Order meta key. */
	public const META_KEY = '_omtr_order_language';

	public function __construct( private CurrentLanguage $current_language ) {}

	/** Register both classic checkout and Store API checkout hooks. */
	public function register_hooks(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'record' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'record' ), 10, 1 );
	}

	/**
	 * Store only the validated language selected by the router.
	 *
	 * @param mixed $order WooCommerce order object.
	 * @param mixed $data  Classic checkout request data (unused).
	 */
	public function record( mixed $order, mixed $data = null ): void {
		unset( $data );
		if ( ! is_object( $order ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}
		$order->update_meta_data( self::META_KEY, $this->current_language->get() );
	}
}
