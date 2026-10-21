<?php
/**
 * Segment repository.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

use Omnium\Translate\Core\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Stores normalized source segments.
 */
final class SegmentRepository implements SegmentRepositoryInterface {

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database connection.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, string> $segments    Hash to normalized source text.
	 * @param string                $source_lang Source language.
	 */
	public function upsert_many( array $segments, string $source_lang ): array {
		if ( array() === $segments ) {
			return array();
		}

		$table = Tables::name( Tables::SEGMENTS );
		$now   = current_time( 'mysql', true );
		foreach ( $segments as $hash => $text ) {
			$sql = $this->wpdb->prepare(
				'INSERT INTO %i (hash, source_text, source_lang, created_at)
				VALUES (%s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE source_text = VALUES(source_text), source_lang = VALUES(source_lang)',
				$table,
				$hash,
				$text,
				$source_lang,
				$now
			);
			$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		}

		$placeholders = implode( ', ', array_fill( 0, count( $segments ), '%s' ) );
		// The variable contains only a count-derived list of %s placeholders; every value is still passed to prepare().
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder list is generated only from array length.
			'SELECT hash, id FROM %i WHERE hash IN (' . $placeholders . ')',
			$table,
			...array_keys( $segments )
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$rows   = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row['hash'] ] = (int) $row['id'];
		}
		return $result;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<string> $hashes Segment hashes.
	 */
	public function texts_by_hash( array $hashes ): array {
		if ( array() === $hashes ) {
			return array();
		}

		$table        = Tables::name( Tables::SEGMENTS );
		$placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );
		// The variable contains only a count-derived list of %s placeholders; every value is still passed to prepare().
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder list is generated only from array length.
			'SELECT hash, source_text FROM %i WHERE hash IN (' . $placeholders . ')',
			$table,
			...$hashes
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$rows   = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row['hash'] ] = (string) $row['source_text'];
		}
		return $result;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<int> $ids Segment ids.
	 */
	public function hashes_by_ids( array $ids ): array {
		if ( array() === $ids ) {
			return array();
		}

		$table        = Tables::name( Tables::SEGMENTS );
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// The variable contains only a count-derived list of %d placeholders; every value is still passed to prepare().
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder list is generated only from array length.
			'SELECT id, hash FROM %i WHERE id IN (' . $placeholders . ')',
			$table,
			...$ids
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$rows   = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (int) $row['id'] ] = (string) $row['hash'];
		}
		return $result;
	}
}
