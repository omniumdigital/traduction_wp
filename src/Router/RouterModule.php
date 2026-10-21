<?php
/**
 * Router module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Language URL prefixes (/en/...), language detection, internal link targets
 * (CLAUDE.md sections 4 and 7). TODO(jalon 2).
 */
final class RouterModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set(
			CurrentLanguage::class,
			static fn( Container $c ) => new CurrentLanguage( $c->get( Settings::class )->source_lang() )
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
	}
}
