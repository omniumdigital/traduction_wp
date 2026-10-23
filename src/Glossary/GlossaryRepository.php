<?php
/**
 * Glossary database repository.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Glossary;

use Omnium\Translate\Core\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and retrieves glossary rules.
 */
final class GlossaryRepository {
	private const SORT_COLUMNS = array( 'id', 'term', 'target_lang', 'translation', 'rule' );
	private const SORT_ORDERS  = array( 'ASC', 'DESC' );

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database connection.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * Rules for one target language.
	 *
	 * @param string $target_lang Target language.
	 * @return list<array{id: int, term: string, target_lang: string, translation: string, rule: string}>
	 */
	public function for_language( string $target_lang ): array {
		$table = Tables::name( Tables::GLOSSARY );
		$sql   = $this->wpdb->prepare(
			'SELECT id, term, target_lang, translation, rule FROM %i WHERE target_lang = %s ORDER BY term ASC, id ASC',
			$table,
			$target_lang
		);
		$rows  = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.

		return $this->normalize_rows( $rows );
	}

	/**
	 * List glossary rules with whitelisted sorting.
	 *
	 * @param string $order_by Sort column.
	 * @param string $order    Sort order.
	 * @return list<array{id: int, term: string, target_lang: string, translation: string, rule: string}>
	 */
	public function all( string $order_by = 'term', string $order = 'ASC' ): array {
		$order_by = in_array( $order_by, self::SORT_COLUMNS, true ) ? $order_by : 'term';
		$order    = strtoupper( $order );
		$order    = in_array( $order, self::SORT_ORDERS, true ) ? $order : 'ASC';
		$table    = Tables::name( Tables::GLOSSARY );
		// Both interpolated values are selected from the strict whitelists above.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $this->wpdb->prepare(
			"SELECT id, term, target_lang, translation, rule FROM %i ORDER BY {$order_by} {$order}, id ASC",
			$table
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared query with whitelisted identifiers.

		return $this->normalize_rows( $rows );
	}

	/**
	 * Find one rule.
	 *
	 * @param int $id Rule id.
	 * @return array{id: int, term: string, target_lang: string, translation: string, rule: string}|null
	 */
	public function find( int $id ): ?array {
		$table = Tables::name( Tables::GLOSSARY );
		$sql   = $this->wpdb->prepare(
			'SELECT id, term, target_lang, translation, rule FROM %i WHERE id = %d',
			$table,
			$id
		);
		$rows  = $this->normalize_rows( $this->wpdb->get_results( $sql, ARRAY_A ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		return $rows[0] ?? null;
	}

	/**
	 * Add a glossary rule.
	 *
	 * @param string $term         Source term.
	 * @param string $target_lang Target language.
	 * @param string $translation Forced translation.
	 * @param string $rule         Rule type.
	 */
	public function add( string $term, string $target_lang, string $translation, string $rule ): int {
		$table = Tables::name( Tables::GLOSSARY );
		$sql   = $this->wpdb->prepare(
			'INSERT INTO %i (term, target_lang, translation, rule) VALUES (%s, %s, %s, %s)',
			$table,
			$term,
			$target_lang,
			$translation,
			$rule
		);
		$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Update a glossary rule.
	 *
	 * @param int    $id           Rule id.
	 * @param string $term         Source term.
	 * @param string $target_lang Target language.
	 * @param string $translation Forced translation.
	 * @param string $rule         Rule type.
	 */
	public function update( int $id, string $term, string $target_lang, string $translation, string $rule ): bool {
		$table = Tables::name( Tables::GLOSSARY );
		$sql   = $this->wpdb->prepare(
			'UPDATE %i SET term = %s, target_lang = %s, translation = %s, rule = %s WHERE id = %d',
			$table,
			$term,
			$target_lang,
			$translation,
			$rule,
			$id
		);
		return false !== $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
	}

	/**
	 * Delete a glossary rule.
	 *
	 * @param int $id Rule id.
	 */
	public function delete( int $id ): bool {
		$table = Tables::name( Tables::GLOSSARY );
		$sql   = $this->wpdb->prepare( 'DELETE FROM %i WHERE id = %d', $table, $id );
		return false !== $this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
	}

	/**
	 * Find auto translations whose source contains the rule term.
	 *
	 * Edited and locked translations are excluded by the SQL status condition.
	 *
	 * @param int $id Glossary rule id.
	 * @return list<array{segment_id: int, source_text: string, translation_id: int, translation: string, target_lang: string, status: string}>
	 */
	public function affected( int $id ): array {
		$rule = $this->find( $id );
		if ( null === $rule ) {
			return array();
		}

		$segments     = Tables::name( Tables::SEGMENTS );
		$translations = Tables::name( Tables::TRANSLATIONS );
		$like         = '%' . $this->wpdb->esc_like( $rule['term'] ) . '%';
		$sql          = $this->wpdb->prepare(
			'SELECT s.id AS segment_id, s.source_text, t.id AS translation_id, t.text AS translation, t.target_lang, t.status
			FROM %i s INNER JOIN %i t ON t.segment_id = s.id
			WHERE t.target_lang = %s AND t.status = %s AND s.source_text LIKE %s
			ORDER BY s.id ASC',
			$segments,
			$translations,
			$rule['target_lang'],
			'auto',
			$like
		);
		$rows         = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$result       = array();
		foreach ( $rows as $row ) {
			$result[] = array(
				'segment_id'     => (int) $row['segment_id'],
				'source_text'    => (string) $row['source_text'],
				'translation_id' => (int) $row['translation_id'],
				'translation'    => (string) $row['translation'],
				'target_lang'    => (string) $row['target_lang'],
				'status'         => (string) $row['status'],
			);
		}
		return $result;
	}

	/**
	 * Normalize database rows.
	 *
	 * @param array<int, array<string, mixed>> $rows Database rows.
	 * @return list<array{id: int, term: string, target_lang: string, translation: string, rule: string}>
	 */
	private function normalize_rows( array $rows ): array {
		$result = array();
		foreach ( $rows as $row ) {
			$result[] = array(
				'id'          => (int) $row['id'],
				'term'        => (string) $row['term'],
				'target_lang' => (string) $row['target_lang'],
				'translation' => (string) $row['translation'],
				'rule'        => (string) $row['rule'],
			);
		}
		return $result;
	}
}
