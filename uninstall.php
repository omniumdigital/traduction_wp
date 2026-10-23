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

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( '', array(), Deactivator::AS_GROUP );
}

Capabilities::revoke();

// The encrypted API key is always removed.
delete_option( Options::API_KEYS );

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
