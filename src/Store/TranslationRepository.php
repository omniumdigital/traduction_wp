<?php
/**
 * Translation repository.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Tables;
use Omnium\Translate\Security\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Stores sanitized translated segments.
 */
final class TranslationRepository implements TranslationRepositoryInterface {

	public const CACHE_GROUP       = 'omtr';
	public const CACHE_VERSION_KEY = 'translation_version';

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database connection.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<string> $hashes      Segment hashes.
	 * @param string       $target_lang Target language.
	 */
	public function find_many( array $hashes, string $target_lang ): array {
		if ( array() === $hashes ) {
			return array();
		}

		$segments     = Tables::name( Tables::SEGMENTS );
		$translations = Tables::name( Tables::TRANSLATIONS );
		$placeholders = implode( ', ', array_fill( 0, count( $hashes ), '%s' ) );
		$args         = array_merge( array( $segments, $translations, $target_lang, self::STATUS_FAILED ), $hashes );
		// The variable contains only a count-derived list of %s placeholders; every value is still passed to prepare().
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$sql = $this->wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder list is generated only from array length.
			'SELECT s.hash, t.text
			FROM %i s
			INNER JOIN %i t ON t.segment_id = s.id
			WHERE t.target_lang = %s AND t.status <> %s AND s.hash IN (' . $placeholders . ')',
			...$args
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared
		$rows   = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$result = array();
		foreach ( $rows as $row ) {
			$result[ (string) $row['hash'] ] = Sanitizer::inline_html( (string) $row['text'] );
		}
		return $result;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<int, string> $texts       Segment id to translated text.
	 * @param string             $target_lang Target language.
	 * @param string             $provider    Provider id.
	 * @param string             $model       Model id.
	 */
	public function save_auto( array $texts, string $target_lang, string $provider, string $model ): void {
		if ( array() === $texts ) {
			return;
		}
		$table = Tables::name( Tables::TRANSLATIONS );
		$now   = current_time( 'mysql', true );
		foreach ( $texts as $segment_id => $text ) {
			$clean = Sanitizer::inline_html( $text );
			$sql   = $this->wpdb->prepare(
				'INSERT INTO %i (segment_id, target_lang, text, status, provider, model, updated_at)
				VALUES (%d, %s, %s, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE
				text = IF(status IN (%s, %s), text, VALUES(text)),
				status = IF(status IN (%s, %s), status, VALUES(status)),
				provider = IF(status IN (%s, %s), provider, VALUES(provider)),
				model = IF(status IN (%s, %s), model, VALUES(model)),
				updated_at = IF(status IN (%s, %s), updated_at, VALUES(updated_at))',
				$table,
				$segment_id,
				$target_lang,
				$clean,
				self::STATUS_AUTO,
				$provider,
				$model,
				$now,
				self::STATUS_EDITED,
				self::STATUS_LOCKED,
				self::STATUS_EDITED,
				self::STATUS_LOCKED,
				self::STATUS_EDITED,
				self::STATUS_LOCKED,
				self::STATUS_EDITED,
				self::STATUS_LOCKED,
				self::STATUS_EDITED,
				self::STATUS_LOCKED
			);
			$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		}
		$this->invalidate_render_cache();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $segment_id  Segment id.
	 * @param string $target_lang Target language.
	 * @param string $text        Edited translated text.
	 */
	public function save_edit( int $segment_id, string $target_lang, string $text ): void {
		$table = Tables::name( Tables::TRANSLATIONS );
		$sql   = $this->wpdb->prepare(
			'INSERT INTO %i (segment_id, target_lang, text, status, provider, model, updated_at)
			VALUES (%d, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE text = VALUES(text), status = VALUES(status), updated_at = VALUES(updated_at)',
			$table,
			$segment_id,
			$target_lang,
			Sanitizer::inline_html( $text ),
			self::STATUS_EDITED,
			'',
			'',
			current_time( 'mysql', true )
		);
		$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$this->invalidate_render_cache();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<int> $segment_ids Segment ids.
	 * @param string    $target_lang Target language.
	 */
	public function mark_failed( array $segment_ids, string $target_lang ): void {
		if ( array() === $segment_ids ) {
			return;
		}

		$table = Tables::name( Tables::TRANSLATIONS );
		$now   = current_time( 'mysql', true );
		foreach ( $segment_ids as $segment_id ) {
			$sql = $this->wpdb->prepare(
				'INSERT INTO %i (segment_id, target_lang, text, status, provider, model, updated_at)
				VALUES (%d, %s, %s, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE
				status = IF(status IN (%s, %s), status, VALUES(status)),
				updated_at = IF(status IN (%s, %s), updated_at, VALUES(updated_at))',
				$table,
				$segment_id,
				$target_lang,
				'',
				self::STATUS_FAILED,
				'',
				'',
				$now,
				self::STATUS_EDITED,
				self::STATUS_LOCKED,
				self::STATUS_EDITED,
				self::STATUS_LOCKED
			);
			$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		}
		$this->invalidate_render_cache();
	}

	/**
	 * Advance the object-cache generation after stored translations change.
	 */
	private function invalidate_render_cache(): void {
		$version = wp_cache_incr( self::CACHE_VERSION_KEY, 1, self::CACHE_GROUP );
		if ( false === $version ) {
			wp_cache_set( self::CACHE_VERSION_KEY, 1, self::CACHE_GROUP );
		}
		$coverage_version = (int) get_option( Options::COVERAGE_VERSION, 0 );
		update_option( Options::COVERAGE_VERSION, $coverage_version + 1, false );
	}
}
