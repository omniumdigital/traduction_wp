<?php
/**
 * Deactivation routine.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Runs on plugin deactivation. Must leave the site intact: data is kept,
 * only scheduled work is stopped.
 */
final class Deactivator {

	public const AS_GROUP = 'omtr';

	/**
	 * Stop scheduled jobs.
	 */
	public static function deactivate(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( '', array(), self::AS_GROUP );
		}
	}
}
