<?php
/**
 * Activation routine.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Switcher\MenuIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin activation.
 */
final class Activator {

	/**
	 * Create tables, grant the management capability and restore the
	 * switcher menu item drafted by Deactivator.
	 */
	public static function activate(): void {
		Migrations::maybe_migrate();
		Capabilities::grant();
		self::publish_switcher_menu_items();
	}

	/**
	 * Republish the switcher's menu item drafted on deactivation so the
	 * client's menu is restored exactly as it was.
	 */
	private static function publish_switcher_menu_items(): void {
		// One-time activation cleanup; no index needed for a query that runs once.
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$items = get_posts(
			array(
				'post_type'   => 'nav_menu_item',
				'post_status' => 'draft',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_key'    => '_menu_item_url',
				'meta_value'  => MenuIntegration::ITEM_URL,
			)
		);
		// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		foreach ( $items as $omtr_menu_item_id ) {
			wp_update_post(
				array(
					'ID'          => (int) $omtr_menu_item_id,
					'post_status' => 'publish',
				)
			);
		}
	}
}
