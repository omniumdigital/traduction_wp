<?php
/**
 * Deactivation routine.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

use Omnium\Translate\Switcher\MenuIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation. Must leave the site intact: data is kept,
 * only scheduled work is stopped.
 */
final class Deactivator {

	public const AS_GROUP = 'omtr';

	/**
	 * Stop scheduled jobs and hide the switcher menu item.
	 */
	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), self::AS_GROUP );
		}
		self::draft_switcher_menu_items();
	}

	/**
	 * Move the switcher's menu item to draft so it stops rendering as a
	 * dead "#omtr-switcher" link once the plugin is inactive, without
	 * deleting it: Activator republishes it on reactivation.
	 */
	private static function draft_switcher_menu_items(): void {
		// One-time deactivation cleanup; no index needed for a query that runs once.
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		$items = get_posts(
			array(
				'post_type'   => 'nav_menu_item',
				'post_status' => 'publish',
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
					'post_status' => 'draft',
				)
			);
		}
	}
}
