<?php
/**
 * Translation completion rate.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Tables;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Calculates and caches the proportion of known segments translated per language.
 */
final class TranslationCoverage {

	private const CACHE_TTL = 300;

	/**
	 * Request-local percentages.
	 *
	 * @var array<string, float>
	 */
	private array $percentages = array();

	/**
	 * Request-local page percentages.
	 *
	 * @var array<string, float>
	 */
	private array $page_percentages = array();

	/**
	 * Constructor.
	 *
	 * @param \wpdb    $wpdb     WordPress database connection.
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( private \wpdb $wpdb, private Settings $settings ) {}

	/**
	 * Completion percentage between zero and one hundred.
	 *
	 * A site with no known source segments is complete: there is nothing pending.
	 * The source language is intrinsically complete.
	 *
	 * @param string $language Language code.
	 */
	public function percentage( string $language ): float {
		if ( $language === $this->settings->source_lang() ) {
			return 100.0;
		}
		if ( isset( $this->percentages[ $language ] ) ) {
			return $this->percentages[ $language ];
		}

		// The coverage version is bumped every time a translation changes
		// (TranslationRepository, SegmentEditorRepository, PageRepository):
		// without it in the key, a stale 0% would survive a retranslation
		// for the whole TTL. page_percentage() below versions its key too.
		$version   = (int) get_option( Options::COVERAGE_VERSION, 0 );
		$cache_key = 'omtr_translation_coverage_' . md5( $version . '|' . $language . '|' . $this->settings->source_lang() );
		$cached    = get_transient( $cache_key );
		if ( is_numeric( $cached ) ) {
			$this->percentages[ $language ] = (float) $cached;
			return $this->percentages[ $language ];
		}

		$segments     = Tables::name( Tables::SEGMENTS );
		$translations = Tables::name( Tables::TRANSLATIONS );
		$sql          = $this->wpdb->prepare(
			'SELECT COUNT(DISTINCT s.id) AS total,
			COUNT(DISTINCT CASE WHEN t.status IN (%s, %s, %s) THEN s.id END) AS translated
			FROM %i s
			LEFT JOIN %i t ON t.segment_id = s.id AND t.target_lang = %s
			WHERE s.source_lang = %s',
			TranslationRepositoryInterface::STATUS_AUTO,
			TranslationRepositoryInterface::STATUS_EDITED,
			TranslationRepositoryInterface::STATUS_LOCKED,
			$segments,
			$translations,
			$language,
			$this->settings->source_lang()
		);
		$rows         = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$total        = isset( $rows[0]['total'] ) ? (int) $rows[0]['total'] : 0;
		$translated   = isset( $rows[0]['translated'] ) ? (int) $rows[0]['translated'] : 0;
		$percentage   = 0 === $total ? 100.0 : min( 100.0, 100.0 * $translated / $total );

		set_transient( $cache_key, $percentage, self::CACHE_TTL );
		$this->percentages[ $language ] = $percentage;
		return $percentage;
	}

	/**
	 * Whether all known segments have an accepted translation.
	 *
	 * @param string $language Language code.
	 */
	public function is_complete( string $language ): bool {
		return $this->percentage( $language ) >= 100.0;
	}

	/**
	 * Completion percentage for one scanned public page.
	 *
	 * Unknown pages are incomplete by default. A known page with no
	 * translatable segment is complete because there is nothing to translate.
	 *
	 * @param string $language Target language.
	 * @param int    $post_id  Related post id, or zero for a URL-only entry.
	 * @param string $url      Canonical source URL.
	 */
	public function page_percentage( string $language, int $post_id, string $url ): float {
		if ( $language === $this->settings->source_lang() ) {
			return 100.0;
		}

		$identity = $post_id > 0 ? 'post:' . $post_id : 'url:' . hash( 'sha256', $url );
		$key      = $language . '|' . $identity;
		if ( isset( $this->page_percentages[ $key ] ) ) {
			return $this->page_percentages[ $key ];
		}

		$version   = (int) get_option( Options::COVERAGE_VERSION, 0 );
		$cache_key = 'omtr_page_coverage_' . md5( $version . '|' . $this->settings->source_lang() . '|' . $key );
		$cached    = get_transient( $cache_key );
		if ( is_numeric( $cached ) ) {
			$this->page_percentages[ $key ] = (float) $cached;
			return $this->page_percentages[ $key ];
		}

		$pages         = Tables::name( Tables::PAGES );
		$page_segments = Tables::name( Tables::PAGE_SEGMENTS );
		$translations  = Tables::name( Tables::TRANSLATIONS );
		$where         = $post_id > 0 ? 'p.id = (SELECT MAX(p2.id) FROM ' . $pages . ' p2 WHERE p2.post_id = %d)' : 'p.url_hash = %s';
		$identity_arg  = $post_id > 0 ? $post_id : hash( 'sha256', $url );
		// The dynamic WHERE clause is selected from two hard-coded fragments and contributes the final placeholder.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$sql = $this->wpdb->prepare(
			"SELECT COUNT(DISTINCT p.id) AS tracked,
			COUNT(DISTINCT ps.segment_id) AS total,
			COUNT(DISTINCT CASE WHEN t.status IN (%s, %s, %s) THEN ps.segment_id END) AS translated
			FROM %i p
			LEFT JOIN %i ps ON ps.page_id = p.id
			LEFT JOIN %i t ON t.segment_id = ps.segment_id AND t.target_lang = %s
				WHERE {$where}",
			TranslationRepositoryInterface::STATUS_AUTO,
			TranslationRepositoryInterface::STATUS_EDITED,
			TranslationRepositoryInterface::STATUS_LOCKED,
			$pages,
			$page_segments,
			$translations,
			$language,
			$identity_arg
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$rows       = $this->wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		$tracked    = isset( $rows[0]['tracked'] ) ? (int) $rows[0]['tracked'] : 0;
		$total      = isset( $rows[0]['total'] ) ? (int) $rows[0]['total'] : 0;
		$translated = isset( $rows[0]['translated'] ) ? (int) $rows[0]['translated'] : 0;
		$percentage = 0 === $tracked ? 0.0 : ( 0 === $total ? 100.0 : min( 100.0, 100.0 * $translated / $total ) );

		set_transient( $cache_key, $percentage, self::CACHE_TTL );
		$this->page_percentages[ $key ] = $percentage;
		return $percentage;
	}

	/**
	 * Whether one scanned public page is completely translated.
	 *
	 * @param string $language Target language.
	 * @param int    $post_id  Related post id.
	 * @param string $url      Canonical source URL.
	 */
	public function is_page_complete( string $language, int $post_id, string $url ): bool {
		return $this->page_percentage( $language, $post_id, $url ) >= 100.0;
	}
}
