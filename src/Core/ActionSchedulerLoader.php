<?php
/**
 * Deferred Action Scheduler loader.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Lets other plugins, notably WooCommerce, register their bundled version first.
 */
final class ActionSchedulerLoader {

	/**
	 * Register the loader before the plugin boot callback.
	 */
	public static function register(): void {
		add_action( 'plugins_loaded', array( self::class, 'load' ), -100 );
	}

	/**
	 * Load the bundled scheduler only when no other plugin registered one.
	 */
	public static function load(): void {
		if ( class_exists( 'ActionScheduler_Versions', false ) ) {
			return;
		}

		require_once OMTR_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
	}
}
