<?php
/**
 * Database table names.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Table name helpers ({$wpdb->prefix}omtr_*).
 */
final class Tables {

	public const SEGMENTS      = 'segments';
	public const TRANSLATIONS  = 'translations';
	public const PAGES         = 'pages';
	public const PAGE_SEGMENTS = 'page_segments';
	public const SLUGS         = 'slugs';
	public const SLUG_HISTORY  = 'slug_history';
	public const GLOSSARY      = 'glossary';
	public const LOG           = 'log';

	/**
	 * Full table name.
	 *
	 * @param string $table One of the constants above.
	 */
	public static function name( string $table ): string {
		global $wpdb;
		return $wpdb->prefix . 'omtr_' . $table;
	}

	/**
	 * All short table names.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		return array( self::SEGMENTS, self::TRANSLATIONS, self::PAGES, self::PAGE_SEGMENTS, self::SLUGS, self::SLUG_HISTORY, self::GLOSSARY, self::LOG );
	}
}
