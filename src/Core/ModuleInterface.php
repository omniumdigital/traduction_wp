<?php
/**
 * Contract for a feature module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Each folder of src/ exposes one module. register() only binds services in the
 * container (no side effects); boot() adds WordPress hooks.
 */
interface ModuleInterface {

	/**
	 * Bind services in the container.
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void;

	/**
	 * Hook into WordPress. Called after every module has been registered.
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void;
}
