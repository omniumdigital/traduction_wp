<?php
/**
 * Language switcher module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode, block, Elementor widget, Oxygen element, menu item, omtr_switcher()
 * (CLAUDE.md section 8). TODO(jalon 5).
 */
final class SwitcherModule implements ModuleInterface {

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
