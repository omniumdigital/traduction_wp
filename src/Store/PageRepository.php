<?php
/**
 * Page scan repository.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Tracks public pages by a one-way URL hash.
 */
final class PageRepository {

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database connection.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * Store the latest page scan.
	 *
	 * @param int    $post_id     Post id, or zero for an archive.
	 * @param string $url         Public URL.
	 * @param string $content_hash Rendered content hash.
	 */
	public function save_scan( int $post_id, string $url, string $content_hash ): void {
		$table    = Tables::name( Tables::PAGES );
		$url_hash = hash( 'sha256', $url );
		$sql      = $this->wpdb->prepare(
			'INSERT INTO %i (post_id, url_hash, content_hash, last_scan_at)
			VALUES (%d, %s, %s, %s)
			ON DUPLICATE KEY UPDATE post_id = VALUES(post_id), content_hash = VALUES(content_hash), last_scan_at = VALUES(last_scan_at)',
			$table,
			$post_id,
			$url_hash,
			$content_hash,
			current_time( 'mysql', true )
		);
		$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
	}

	/**
	 * Find the stored content hash for a public URL.
	 *
	 * @param string $url Public URL.
	 */
	public function content_hash( string $url ): ?string {
		$table = Tables::name( Tables::PAGES );
		$sql   = $this->wpdb->prepare(
			'SELECT content_hash FROM %i WHERE url_hash = %s',
			$table,
			hash( 'sha256', $url )
		);
		$value = $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		return null === $value ? null : (string) $value;
	}

	/**
	 * Replace the segment inventory of a scanned public page.
	 *
	 * Deleting the old mapping before inserting the new one is deliberately
	 * fail-safe for SEO: an interrupted refresh makes the page incomplete
	 * instead of exposing a stale translated variant.
	 *
	 * @param int       $post_id     Related post id.
	 * @param string    $url         Canonical source URL.
	 * @param list<int> $segment_ids Current segment ids.
	 */
	public function replace_segments( int $post_id, string $url, array $segment_ids ): void {
		$pages         = Tables::name( Tables::PAGES );
		$page_segments = Tables::name( Tables::PAGE_SEGMENTS );
		$url_hash      = hash( 'sha256', $url );
		$sql           = $this->wpdb->prepare( 'SELECT id FROM %i WHERE url_hash = %s', $pages, $url_hash );
		$page_id       = (int) $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.

		if ( $page_id <= 0 ) {
			$this->save_scan( $post_id, $url, '' );
			$page_id = (int) $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above with immutable values.
		}
		if ( $page_id <= 0 ) {
			return;
		}

		$delete = $this->wpdb->prepare( 'DELETE FROM %i WHERE page_id = %d', $page_segments, $page_id );
		$this->wpdb->query( $delete ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.

		$segment_ids = array_values( array_unique( array_filter( array_map( 'absint', $segment_ids ) ) ) );
		foreach ( $segment_ids as $segment_id ) {
			$insert = $this->wpdb->prepare(
				'INSERT IGNORE INTO %i (page_id, segment_id) VALUES (%d, %d)',
				$page_segments,
				$page_id,
				$segment_id
			);
			$this->wpdb->query( $insert ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		}
		$this->invalidate_coverage_cache();
	}

	/** Advance persistent page-coverage cache generation. */
	private function invalidate_coverage_cache(): void {
		$version = (int) get_option( Options::COVERAGE_VERSION, 0 );
		update_option( Options::COVERAGE_VERSION, $version + 1, false );
	}
}
