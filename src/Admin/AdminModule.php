<?php
/**
 * Admin module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Admin;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, wizard, dashboard, segment editor; React UI with @wordpress/components
 * (CLAUDE.md section 9). Every action: capability omtr_manage + nonce. TODO(jalon 3 and 5).
 */
final class AdminModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
	}
}
