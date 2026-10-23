<?php
/**
 * Translation completion rate.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

use Omnium\Translate\Core\Settings;
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

		$cache_key = 'omtr_translation_coverage_' . md5( $language . '|' . $this->settings->source_lang() );
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
}
