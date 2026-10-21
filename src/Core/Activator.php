<?php
/**
 * Activation routine.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation.
 */
final class Activator {

	/**
	 * Create tables and grant the management capability.
	 */
	public static function activate(): void {
		Migrations::maybe_migrate();
		Capabilities::grant();
	}
}
