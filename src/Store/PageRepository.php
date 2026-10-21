<?php
/**
 * Page scan repository.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

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
}
