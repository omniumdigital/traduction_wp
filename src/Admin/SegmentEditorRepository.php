<?php
/**
 * Segment editor database repository.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Admin;

use Omnium\Translate\Core\Tables;
use Omnium\Translate\Security\Sanitizer;
use Omnium\Translate\Store\TranslationRepository;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Lists and edits translations for the admin segment editor.
 */
final class SegmentEditorRepository {
	private const SORT_COLUMNS = array( 'id', 'source_text', 'target_lang', 'status', 'updated_at' );
	private const SORT_ORDERS  = array( 'ASC', 'DESC' );
	private const STATUSES     = array( 'auto', 'edited', 'locked', 'failed' );

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database connection.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * List translations with pagination and safe filters.
	 *
	 * @param array{page?: int, per_page?: int, target_lang?: string, status?: string, search?: string, orderby?: string, order?: string} $filters Filters.
	 * @return array{items: list<array<string, mixed>>, total: int}
	 */
	public function list( array $filters ): array {
		$page        = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$per_page    = min( 100, max( 1, (int) ( $filters['per_page'] ?? 20 ) ) );
		$target_lang = (string) ( $filters['target_lang'] ?? '' );
		$status      = (string) ( $filters['status'] ?? '' );
		$search      = (string) ( $filters['search'] ?? '' );
		$orderby     = (string) ( $filters['orderby'] ?? 'updated_at' );
		$orderby     = in_array( $orderby, self::SORT_COLUMNS, true ) ? $orderby : 'updated_at';
		$order       = strtoupper( (string) ( $filters['order'] ?? 'DESC' ) );
		$order       = in_array( $order, self::SORT_ORDERS, true ) ? $order : 'DESC';
		$offset      = ( $page - 1 ) * $per_page;

		$segments     = Tables::name( Tables::SEGMENTS );
		$translations = Tables::name( Tables::TRANSLATIONS );
		$where        = array( '1 = %d' );
		$args         = array( 1 );
		if ( '' !== $target_lang ) {
			$where[] = 't.target_lang = %s';
			$args[]  = $target_lang;
		}
		if ( in_array( $status, self::STATUSES, true ) ) {
			$where[] = 't.status = %s';
			$args[]  = $status;
		}
		if ( '' !== $search ) {
			$where[] = '(s.source_text LIKE %s OR t.text LIKE %s)';
			$like    = '%' . $this->wpdb->esc_like( $search ) . '%';
			$args[]  = $like;
			$args[]  = $like;
		}
		$where_sql = implode( ' AND ', $where );

		// Dynamic clauses are composed exclusively from constants and whitelists above.
		// Placeholder counts depend only on the safe filter list assembled above.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$count_sql = $this->wpdb->prepare(
			"SELECT COUNT(*) FROM %i s INNER JOIN %i t ON t.segment_id = s.id WHERE {$where_sql}",
			$segments,
			$translations,
			...$args
		);
		$list_sql  = $this->wpdb->prepare(
			"SELECT s.id, s.source_text, s.source_lang, t.target_lang, t.text, t.status, t.provider, t.model, t.updated_at
			FROM %i s INNER JOIN %i t ON t.segment_id = s.id WHERE {$where_sql}
			ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
			$segments,
			$translations,
			...array_merge( $args, array( $per_page, $offset ) )
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		$total = (int) $this->wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$rows  = $this->wpdb->get_results( $list_sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		return array(
			'items' => array_values( $rows ),
			'total' => $total,
		);
	}

	/**
	 * Save a manual edit with mandatory sanitization.
	 *
	 * @param int    $segment_id  Segment id.
	 * @param string $target_lang Target language.
	 * @param string $text        Edited translation.
	 */
	public function edit( int $segment_id, string $target_lang, string $text ): void {
		( new TranslationRepository( $this->wpdb ) )->save_edit( $segment_id, $target_lang, Sanitizer::inline_html( $text ) );
	}

	/**
	 * Set a translation to locked or auto.
	 *
	 * @param int    $segment_id  Segment id.
	 * @param string $target_lang Target language.
	 * @param bool   $locked      Whether to lock the translation.
	 */
	public function set_locked( int $segment_id, string $target_lang, bool $locked ): bool {
		$table  = Tables::name( Tables::TRANSLATIONS );
		$status = $locked ? TranslationRepositoryInterface::STATUS_LOCKED : TranslationRepositoryInterface::STATUS_AUTO;
		$sql    = $this->wpdb->prepare(
			'UPDATE %i SET status = %s, updated_at = %s WHERE segment_id = %d AND target_lang = %s',
			$table,
			$status,
			current_time( 'mysql', true ),
			$segment_id,
			$target_lang
		);
		$done   = $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		if ( false !== $done ) {
			$version = wp_cache_incr( TranslationRepository::CACHE_VERSION_KEY, 1, TranslationRepository::CACHE_GROUP );
			if ( false === $version ) {
				wp_cache_set( TranslationRepository::CACHE_VERSION_KEY, 1, TranslationRepository::CACHE_GROUP );
			}
		}
		return false !== $done;
	}
}
