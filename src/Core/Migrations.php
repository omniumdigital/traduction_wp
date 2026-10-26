<?php
/**
 * Versioned database migrations.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Applies schema changes based on the omtr_db_version option.
 *
 * To change the schema: bump DB_VERSION, update schema() (dbDelta handles
 * added columns and indexes) and, for data changes, add a step in steps().
 */
final class Migrations {

	public const DB_VERSION = 3;

	/**
	 * Run migrations if the stored version is behind.
	 */
	public static function maybe_migrate(): void {
		$current = (int) get_option( Options::DB_VERSION, 0 );
		if ( $current >= self::DB_VERSION ) {
			return;
		}
		self::migrate( $current );
	}

	/**
	 * Apply schema and data steps from $from to DB_VERSION.
	 *
	 * @param int $from Stored version.
	 */
	public static function migrate( int $from ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( self::schema() );

		foreach ( self::steps() as $version => $step ) {
			if ( $version > $from ) {
				$step();
			}
		}

		update_option( Options::DB_VERSION, self::DB_VERSION, false );
	}

	/**
	 * Data migration steps keyed by target version.
	 *
	 * @return array<int, callable(): void>
	 */
	private static function steps(): array {
		return array(
			3 => static function (): void {
				global $wpdb;
				// Existing v2 pages have no page-to-segment inventory. Clearing
				// their content fingerprint makes the next crawl rebuild it.
				$sql = $wpdb->prepare( 'UPDATE %i SET content_hash = %s', Tables::name( Tables::PAGES ), '' );
				$wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time schema migration, prepared immediately above.
			},
		);
	}

	/**
	 * Full schema in dbDelta format.
	 *
	 * Segment identity: segments.hash = sha256( source_lang . "\n" . normalized source text ).
	 * A translation is unique per (segment_id, target_lang), so the lookup key
	 * (source text + target language) maps to exactly one row.
	 *
	 * @return list<string>
	 */
	public static function schema(): array {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$segments      = Tables::name( Tables::SEGMENTS );
		$translations  = Tables::name( Tables::TRANSLATIONS );
		$pages         = Tables::name( Tables::PAGES );
		$page_segments = Tables::name( Tables::PAGE_SEGMENTS );
		$slugs         = Tables::name( Tables::SLUGS );
		$slug_history  = Tables::name( Tables::SLUG_HISTORY );
		$glossary      = Tables::name( Tables::GLOSSARY );
		$log           = Tables::name( Tables::LOG );

		return array(
			"CREATE TABLE {$segments} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  hash char(64) NOT NULL,
  source_text longtext NOT NULL,
  source_lang varchar(12) NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY hash (hash)
) {$charset};",
			"CREATE TABLE {$page_segments} (
  page_id bigint(20) unsigned NOT NULL,
  segment_id bigint(20) unsigned NOT NULL,
  PRIMARY KEY  (page_id,segment_id),
  KEY segment_id (segment_id)
) {$charset};",
			"CREATE TABLE {$translations} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  segment_id bigint(20) unsigned NOT NULL,
  target_lang varchar(12) NOT NULL,
  text longtext NOT NULL,
  status varchar(10) NOT NULL DEFAULT 'auto',
  provider varchar(32) NOT NULL DEFAULT '',
  model varchar(100) NOT NULL DEFAULT '',
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY segment_lang (segment_id,target_lang),
  KEY target_status (target_lang,status)
) {$charset};",
			"CREATE TABLE {$pages} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  url_hash char(64) NOT NULL,
  content_hash char(64) NOT NULL DEFAULT '',
  last_scan_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY url_hash (url_hash),
  KEY post_id (post_id)
) {$charset};",
			"CREATE TABLE {$slugs} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  target_lang varchar(12) NOT NULL,
  slug varchar(200) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY post_lang (post_id,target_lang),
  UNIQUE KEY lang_slug (target_lang,slug)
) {$charset};",
			"CREATE TABLE {$slug_history} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  post_id bigint(20) unsigned NOT NULL,
  target_lang varchar(12) NOT NULL,
  slug varchar(200) NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY lang_slug (target_lang,slug),
  KEY post_lang (post_id,target_lang)
) {$charset};",
			"CREATE TABLE {$glossary} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  term varchar(255) NOT NULL,
  target_lang varchar(12) NOT NULL,
  translation varchar(255) NOT NULL DEFAULT '',
  rule varchar(10) NOT NULL DEFAULT 'translate',
  PRIMARY KEY  (id),
  KEY target_lang (target_lang)
) {$charset};",
			"CREATE TABLE {$log} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  level varchar(10) NOT NULL,
  context varchar(64) NOT NULL DEFAULT '',
  message text NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY created_at (created_at)
) {$charset};",
		);
	}
}
