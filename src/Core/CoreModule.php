<?php
/**
 * Core services.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Registers shared core services (settings, updater).
 */
final class CoreModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( Settings::class, static fn() => new Settings() );
		$container->set( Updater::class, static fn() => new Updater() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		$container->get( Updater::class )->boot();
	}
}
