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
