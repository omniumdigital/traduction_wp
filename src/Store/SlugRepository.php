<?php
/**
 * Translated slug repository.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

use Omnium\Translate\Core\Tables;

defined( 'ABSPATH' ) || exit;

/**
 * Stores one unique translated slug per post and target language.
 */
final class SlugRepository {

	/**
	 * Constructor.
	 *
	 * @param \wpdb $wpdb WordPress database connection.
	 */
	public function __construct( private \wpdb $wpdb ) {}

	/**
	 * Find the translated slug for a post and language.
	 *
	 * @param int    $post_id     Post id.
	 * @param string $target_lang Target language.
	 */
	public function get( int $post_id, string $target_lang ): ?string {
		$sql   = $this->wpdb->prepare(
			'SELECT slug FROM %i WHERE post_id = %d AND target_lang = %s',
			Tables::name( Tables::SLUGS ),
			$post_id,
			$target_lang
		);
		$value = $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.

		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * Resolve a translated slug to its post id.
	 *
	 * @param string $slug        Translated slug.
	 * @param string $target_lang Target language.
	 */
	public function find_post_id( string $slug, string $target_lang ): ?int {
		$sql   = $this->wpdb->prepare(
			'SELECT post_id FROM %i WHERE slug = %s AND target_lang = %s',
			Tables::name( Tables::SLUGS ),
			$slug,
			$target_lang
		);
		$value = $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.

		return null === $value ? null : (int) $value;
	}

	/**
	 * Whether another post already owns the slug in this language.
	 *
	 * @param string $slug        Translated slug.
	 * @param string $target_lang Target language.
	 * @param int    $exclude_id  Post id allowed to own the slug.
	 */
	public function exists( string $slug, string $target_lang, int $exclude_id = 0 ): bool {
		$sql = $this->wpdb->prepare(
			'SELECT post_id FROM %i WHERE slug = %s AND target_lang = %s AND post_id <> %d LIMIT 1',
			Tables::name( Tables::SLUGS ),
			$slug,
			$target_lang,
			$exclude_id
		);

		return null !== $this->wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
	}

	/**
	 * Insert or update a translated slug.
	 *
	 * @param int    $post_id     Post id.
	 * @param string $target_lang Target language.
	 * @param string $slug        Sanitized slug.
	 */
	public function save( int $post_id, string $target_lang, string $slug ): void {
		$sql = $this->wpdb->prepare(
			'INSERT INTO %i (post_id, target_lang, slug) VALUES (%d, %s, %s)
			ON DUPLICATE KEY UPDATE slug = VALUES(slug)',
			Tables::name( Tables::SLUGS ),
			$post_id,
			$target_lang,
			$slug
		);
		$this->wpdb->query( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
	}
}
