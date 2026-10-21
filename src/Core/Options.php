<?php
/**
 * Option names. Every option of the plugin is listed here so uninstall can remove them.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Option name constants.
 */
final class Options {

	public const DB_VERSION         = 'omtr_db_version';
	public const SETTINGS           = 'omtr_settings';
	public const API_KEYS           = 'omtr_api_keys';
	public const UNINSTALL_PURGE    = 'omtr_uninstall_purge';
	public const UPDATES_DISABLED   = 'omtr_updates_disabled';
	public const TRANSLATION_LOCK   = 'omtr_translation_lock';
	public const MONTH_WORDS        = 'omtr_month_words';
	public const HOUR_SEGMENTS      = 'omtr_hour_segments';
	public const MONTH_WORDS_LOCK   = 'omtr_month_words_lock';
	public const HOUR_SEGMENTS_LOCK = 'omtr_hour_segments_lock';

	/**
	 * All option names.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array(
			self::DB_VERSION,
			self::SETTINGS,
			self::API_KEYS,
			self::UNINSTALL_PURGE,
			self::UPDATES_DISABLED,
			self::TRANSLATION_LOCK,
			self::MONTH_WORDS,
			self::HOUR_SEGMENTS,
			self::MONTH_WORDS_LOCK,
			self::HOUR_SEGMENTS_LOCK,
		);
	}
}
