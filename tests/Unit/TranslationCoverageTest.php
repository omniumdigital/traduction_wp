<?php
/**
 * Translation coverage cache invalidation tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Seo\TranslationCoverage;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

/**
 * The transient key must embed the coverage version: repositories bump it on
 * every translation change, so a redone failed translation must refresh the
 * displayed percentage immediately instead of serving the stale cached one.
 */
final class TranslationCoverageTest extends TestCase {

	private OptionStore $options;

	/**
	 * In-memory transients.
	 *
	 * @var array<string, mixed>
	 */
	private array $transients = array();

	protected function setUp(): void {
		parent::setUp();
		$this->options       = new OptionStore();
		$this->transients    = array();
		$this->options->data['omtr_settings'] = array(
			'source_lang'  => 'fr',
			'target_langs' => array( 'en' ),
		);
		$this->options->install();

		Functions\when( 'get_transient' )->alias(
			function ( string $key ) {
				return array_key_exists( $key, $this->transients ) ? $this->transients[ $key ] : false;
			}
		);
		Functions\when( 'set_transient' )->alias(
			function ( string $key, $value, int $expires = 0 ): bool {
				$this->transients[ $key ] = $value;
				return true;
			}
		);
	}

	public function test_percentage_reflects_a_new_translation_right_after_the_version_bump(): void {
		// First request: half translated, cached for 300 seconds.
		$before = $this->coverage( self::row( 2, 1 ) );
		$this->assertSame( 50.0, $before->percentage( 'en' ) );

		// The failed translation is redone: every writer bumps the version.
		$this->options->data[ Options::COVERAGE_VERSION ] = 1;

		// Next request must recompute instead of reading the stale entry.
		$after = $this->coverage( self::row( 2, 2 ) );
		$this->assertSame( 100.0, $after->percentage( 'en' ) );
	}

	public function test_percentage_is_cached_within_a_coverage_version(): void {
		$coverage = $this->coverage( self::row( 2, 1 ) );

		$this->assertSame( 50.0, $coverage->percentage( 'en' ) );
		// Same request, same version: no second SQL round-trip.
		$this->assertSame( 50.0, $coverage->percentage( 'en' ) );
	}

	/**
	 * One get_results result set per expected computation.
	 *
	 * @param int $total     Known segments.
	 * @param int $translated Segments with an accepted translation.
	 * @return list<array<string, string>>
	 */
	private static function row( int $total, int $translated ): array {
		return array(
			array(
				'total'      => (string) $total,
				'translated' => (string) $translated,
			),
		);
	}

	/**
	 * Coverage instance whose next N computations return the given rows.
	 *
	 * @param list<array<string, string>> ...$rows One result set per computation.
	 */
	private function coverage( array ...$rows ): TranslationCoverage {
		$wpdb            = $this->createMock( \wpdb::class );
		$wpdb->prefix    = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturnCallback( static fn( string $sql, mixed ...$args ): string => $sql . '|' . implode( '|', array_map( 'strval', $args ) ) );
		$wpdb->method( 'get_results' )->willReturnOnConsecutiveCalls( ...$rows );
		return new TranslationCoverage( $wpdb, new Settings() );
	}
}
