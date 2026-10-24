<?php
/**
 * Uninstall routine.
 *
 * Full purge only if the admin chose it (option omtr_uninstall_purge);
 * otherwise translations are kept for a later reinstall.
 *
 * @package Omnium\Translate
 */

defined( 'ABSPATH' ) || exit;
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/vendor/autoload.php';

use Omnium\Translate\Core\Deactivator;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Tables;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Switcher\MenuIntegration;

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), Deactivator::AS_GROUP );
}

Capabilities::revoke();

// The encrypted API key is always removed.
delete_option( Options::API_KEYS );

// The wizard and the classic menu screen can insert a marker item that would
// render as a dead "#omtr-switcher" link without the plugin: always remove it.
// One-time uninstall cleanup; no index needed for a query that runs once.
// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
foreach ( get_posts(
	array(
		'post_type'   => 'nav_menu_item',
		'post_status' => 'any',
		'numberposts' => -1,
		'fields'      => 'ids',
		'meta_key'    => '_menu_item_url',
		'meta_value'  => MenuIntegration::ITEM_URL,
	)
) as $omtr_menu_item_id ) {
	// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value
	wp_delete_post( (int) $omtr_menu_item_id, true );
}

if ( ! get_option( Options::UNINSTALL_PURGE ) ) {
	return;
}

global $wpdb;

foreach ( Tables::all() as $omtr_table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . esc_sql( Tables::name( $omtr_table ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- table names come from a hard-coded whitelist.
}

foreach ( Options::all() as $omtr_option ) {
	delete_option( $omtr_option );
}

// Options and transients added later by modules must use the omtr_ prefix to be caught here.
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'omtr_' ) . '%',
		$wpdb->esc_like( '_transient_omtr_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_omtr_' ) . '%'
	)
);
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->prepare(
		"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
		$wpdb->esc_like( '_omtr_' ) . '%'
	)
);

// Action Scheduler is shared infrastructure: only this plugin's own
// scheduled jobs are removed, never the whole table.
$omtr_as_actions_table = $wpdb->prefix . 'actionscheduler_actions';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $omtr_as_actions_table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$omtr_as_actions_table} WHERE hook LIKE %s", $wpdb->esc_like( 'omtr_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name comes from $wpdb->prefix, not external input.
}

// The raw queries above bypass the options/post meta cache: without an
// explicit flush, a value read earlier in this same request (e.g. by a
// module bootstrapped before WP_UNINSTALL_PLUGIN was defined) would still
// be served from the stale runtime cache even though its row is gone.
wp_cache_flush();
