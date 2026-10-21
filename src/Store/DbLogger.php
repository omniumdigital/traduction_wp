<?php
/**
 * Database logger.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Stores short technical messages and purges them after 30 days.
 */
final class DbLogger implements LoggerInterface {

	public const PURGE_HOOK = 'omtr_purge_logs';
	public const GROUP      = 'omtr';

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database connection.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $level   Log level.
	 * @param string $context Technical context.
	 * @param string $message Generic message without personal or source content.
	 */
	public function log( string $level, string $context, string $message ): void {
		$allowed_levels = array( 'debug', 'info', 'warning', 'error' );
		$level          = in_array( $level, $allowed_levels, true ) ? $level : 'info';
		$context        = substr( sanitize_key( $context ), 0, 64 );
		$message        = substr( sanitize_text_field( $message ), 0, 500 );
		$table          = Tables::name( Tables::LOG );
		$sql            = $this->wpdb->prepare(
			'INSERT INTO %i (level, context, message, created_at) VALUES (%s, %s, %s, %s)',
			$table,
			$level,
			$context,
			$message,
			current_time( 'mysql', true )
		);
		$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
	}

	/**
	 * Schedule the daily retention job once Action Scheduler is ready.
	 */
	public function schedule_purge(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}
		if ( ! as_has_scheduled_action( self::PURGE_HOOK, array(), self::GROUP ) ) {
			as_schedule_recurring_action( time() + DAY_IN_SECONDS, DAY_IN_SECONDS, self::PURGE_HOOK, array(), self::GROUP, true );
		}
	}

	/**
	 * Delete log rows older than 30 days.
	 */
	public function purge(): void {
		$table = Tables::name( Tables::LOG );
		$sql   = $this->wpdb->prepare(
			'DELETE FROM %i WHERE created_at < %s',
			$table,
			gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) )
		);
		$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
	}
}
