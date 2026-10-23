<?php
/**
 * Partial-translation SEO tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Seo\HeadTags;
use Omnium\Translate\Seo\Robots;
use Omnium\Translate\Seo\TranslationCoverage;
use Omnium\Translate\Tests\TestCase;

/**
 * Verifies cached coverage and noindex behaviour.
 *
 * @group security
 */
final class SeoNoindexTest extends TestCase {

	/** @var \wpdb Database double. */
	private \wpdb $wpdb;

	/** @var string SQL passed to prepare(). */
	private string $prepared_sql = '';

	/** @var list<mixed> Arguments passed to prepare(). */
	private array $prepared_args = array();

	/**
	 * Settings fixture.
	 *
	 * @var array<string, mixed>
	 */
	private array $options;

	/** Set up WordPress and database doubles. */
	protected function setUp(): void {
		parent::setUp();
		$this->options = array(
			'source_lang'     => 'fr',
			'target_langs'    => array( 'en' ),
			'noindex_partial' => true,
		);
		Functions\when( 'get_option' )->alias( fn(): array => $this->options );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_robots' )->justReturn( false );
		Functions\when( 'is_trackback' )->justReturn( false );
		Functions\when( 'is_404' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\when( 'get_queried_object_id' )->justReturn( 0 );
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://safe.test' . $path );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ): mixed => parse_url( $url, $component ) // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test double for wp_parse_url().
		);
		Functions\when( 'esc_attr' )->returnArg();
		Functions\when( 'esc_url' )->returnArg();

		$this->wpdb         = $this->createMock( \wpdb::class );
		$this->wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $this->wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Controlled database fixture.
		$GLOBALS['wp']   = (object) array( 'request' => 'article' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Controlled request fixture.
	}

	/** Remove global fixtures. */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'], $GLOBALS['wp'] );
		parent::tearDown();
	}

	/** Coverage counts only auto, edited and locked translations and caches the result. */
	public function test_coverage_uses_accepted_statuses_and_cache(): void {
		$this->mock_coverage_query( 4, 3 );

		$coverage = new TranslationCoverage( $this->wpdb, new Settings() );
		$this->assertSame( 75.0, $coverage->percentage( 'en' ) );
		$this->assertContains( 'auto', $this->prepared_args );
		$this->assertContains( 'edited', $this->prepared_args );
		$this->assertContains( 'locked', $this->prepared_args );
		$this->assertNotContains( 'failed', $this->prepared_args );
		$this->assertStringContainsString( 'COUNT(DISTINCT', $this->prepared_sql );
	}

	/** A cached percentage avoids all database work. */
	public function test_coverage_reads_cache(): void {
		Functions\when( 'get_transient' )->justReturn( 42.5 );
		$this->wpdb->expects( $this->never() )->method( 'prepare' );
		$this->assertSame( 42.5, ( new TranslationCoverage( $this->wpdb, new Settings() ) )->percentage( 'en' ) );
	}

	/** Incomplete translated pages are noindex,follow for all supported SEO outputs. */
	public function test_incomplete_language_is_noindex_follow(): void {
		$this->mock_coverage_query( 2, 1 );
		$current          = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		$robots = new Robots( new Settings(), $current, new RequestGuard(), new TranslationCoverage( $this->wpdb, new Settings() ) );

		$this->assertSame(
			array(
				'noindex' => true,
				'follow'  => true,
			),
			$robots->filter_wp_robots( array( 'index' => true ) )
		);
		$this->assertSame( 'noindex, follow', $robots->filter_yoast_robots( 'index, follow' ) );
		$this->assertSame( array( 'noindex', 'follow' ), $robots->filter_rank_math_robots( array( 'index', 'follow' ) ) );
	}

	/** The option is off by default and leaves robots directives untouched. */
	public function test_noindex_option_is_off_by_default(): void {
		$this->options = array(
			'source_lang'  => 'fr',
			'target_langs' => array( 'en' ),
		);
		$current       = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		$robots = new Robots( new Settings(), $current, new RequestGuard(), new TranslationCoverage( $this->wpdb, new Settings() ) );

		$this->assertSame( array( 'index' => true ), $robots->filter_wp_robots( array( 'index' => true ) ) );
	}

	/** Incomplete target languages are omitted from hreflang links. */
	public function test_incomplete_language_is_removed_from_hreflang(): void {
		$this->mock_coverage_query( 2, 1 );
		$settings         = new Settings();
		$current          = new CurrentLanguage( 'fr' );
		$coverage         = new TranslationCoverage( $this->wpdb, $settings );
		$tags             = new HeadTags( $settings, $current, new RequestGuard(), new UrlTranslator( $settings ), $coverage );

		ob_start();
		$tags->render();
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'hreflang="fr"', $html );
		$this->assertStringNotContainsString( 'hreflang="en"', $html );
	}

	/**
	 * Configure one aggregate coverage query.
	 *
	 * @param int $total      Known source segments.
	 * @param int $translated Accepted translations.
	 */
	private function mock_coverage_query( int $total, int $translated ): void {
		$this->wpdb->expects( $this->once() )->method( 'prepare' )->willReturnCallback(
			function ( string $sql, mixed ...$args ): string {
				$this->prepared_sql  = $sql;
				$this->prepared_args = $args;
				return 'prepared-coverage';
			}
		);
		$this->wpdb->expects( $this->once() )->method( 'get_results' )->with( 'prepared-coverage', ARRAY_A )->willReturn(
			array(
				array(
					'total'      => (string) $total,
					'translated' => (string) $translated,
				),
			)
		);
	}
}
