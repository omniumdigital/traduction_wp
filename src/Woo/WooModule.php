<?php
/**
 * WooCommerce module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Woo;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Router\CurrentLanguage;

defined( 'ABSPATH' ) || exit;

/**
 * Cart fragments, emails in the order language, attributes; loaded only when
 * WooCommerce is active (CLAUDE.md section 7). TODO(jalon 4).
 */
final class WooModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$container->set( OrderLanguage::class, static fn( Container $c ) => new OrderLanguage( $c->get( CurrentLanguage::class ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		if ( ! $container->has( OrderLanguage::class ) ) {
			return;
		}
		$container->get( OrderLanguage::class )->register_hooks();
	}
}
