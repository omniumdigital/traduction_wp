<?php
/**
 * Multilingual sitemap tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Seo\NativeSitemapIntegration;
use Omnium\Translate\Seo\NativeSitemapRenderer;
use Omnium\Translate\Seo\SitemapAlternates;
use Omnium\Translate\Seo\TranslationCoverage;
use Omnium\Translate\Tests\TestCase;

/**
 * Covers native WordPress, Yoast and Rank Math sitemap output.
 *
 * @group security
 */
final class SitemapAlternatesTest extends TestCase {

	/** @var \wpdb Database double. */
	private \wpdb $wpdb;

	/** @var array<string, mixed> Settings fixture. */
	private array $options;

	/** Configure WordPress function doubles. */
	protected function setUp(): void {
		parent::setUp();
		$this->options = array(
			'source_lang'      => 'fr',
			'target_langs'     => array( 'en', 'es' ),
			'noindex_partial'  => false,
		);
		Functions\when( 'get_option' )->alias( fn(): array => $this->options );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://safe.test' . $path );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ): mixed => parse_url( $url, $component ) // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test double for wp_parse_url().
		);
		Functions\when( 'esc_url' )->returnArg();
		Functions\when( 'esc_xml' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_XML1 | ENT_QUOTES ) );

		$this->wpdb         = $this->createMock( \wpdb::class );
		$this->wpdb->prefix = 'wp_';
		$GLOBALS['wpdb']    = $this->wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Controlled database fixture.
	}

	/** Remove the database fixture. */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/** Yoast and Rank Math per-URL filters receive reciprocal links and translated entries. */
	public function test_plugin_sitemaps_include_every_localized_url_and_alternate(): void {
		$xml = $this->alternates()->filter_plugin_url(
			"\t<url>\n\t\t<loc>https://safe.test/article</loc>\n\t</url>\n",
			array( 'loc' => 'https://safe.test/article' )
		);

		$this->assertSame( 3, substr_count( $xml, '<url>' ) );
		$this->assertStringContainsString( '<loc>https://safe.test/en/article</loc>', $xml );
		$this->assertStringContainsString( '<loc>https://safe.test/es/article</loc>', $xml );
		$this->assertSame( 3, substr_count( $xml, 'hreflang="x-default"' ) );
		$this->assertSame( 3, substr_count( $xml, 'hreflang="en"' ) );
		$this->assertStringContainsString( 'xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml );
	}

	/** Native WordPress sitemap XML has the namespace and one URL per language. */
	public function test_native_sitemap_renderer_includes_localized_entries(): void {
		$renderer = new NativeSitemapRenderer( new \stdClass(), $this->alternates() );
		$xml      = $renderer->get_sitemap_xml(
			array(
				array(
					'loc'     => 'https://safe.test/article?a=1&b=2',
					'lastmod' => '2026-10-23T10:00:00+00:00',
				),
			)
		);

		$this->assertStringContainsString( 'xmlns:xhtml="http://www.w3.org/1999/xhtml"', $xml );
		$this->assertSame( 3, substr_count( $xml, '<url>' ) );
		$this->assertStringContainsString( 'https://safe.test/en/article?a=1&amp;b=2', $xml );
		$this->assertSame( 3, substr_count( $xml, '<lastmod>' ) );
	}

	/** Native integration preserves the original renderer for index delegation. */
	public function test_native_integration_replaces_only_the_renderer(): void {
		$core              = new \stdClass();
		$sitemaps          = new \stdClass();
		$sitemaps->renderer = $core;

		( new NativeSitemapIntegration( $this->alternates() ) )->register_renderer( $sitemaps );
		$this->assertInstanceOf( NativeSitemapRenderer::class, $sitemaps->renderer );
	}

	/** Partial targets are absent from both URL entries and alternate links. */
	public function test_noindex_partial_language_is_excluded_from_sitemaps(): void {
		$this->options['noindex_partial'] = true;
		$this->wpdb->expects( $this->exactly( 2 ) )->method( 'prepare' )->willReturn( 'coverage' );
		$this->wpdb->expects( $this->exactly( 2 ) )->method( 'get_results' )->with( 'coverage', ARRAY_A )->willReturnOnConsecutiveCalls(
			array( array( 'total' => '2', 'translated' => '2' ) ),
			array( array( 'total' => '2', 'translated' => '1' ) )
		);
		$xml = $this->alternates()->filter_plugin_url( '<url><loc>https://safe.test/article</loc></url>', array( 'loc' => 'https://safe.test/article' ) );

		$this->assertStringContainsString( 'hreflang="en"', $xml );
		$this->assertStringNotContainsString( 'hreflang="es"', $xml );
		$this->assertStringNotContainsString( '<loc>https://safe.test/es/article</loc>', $xml );
	}

	/** Build the alternate entry service. */
	private function alternates(): SitemapAlternates {
		$settings = new Settings();
		return new SitemapAlternates( $settings, new UrlTranslator( $settings ), new TranslationCoverage( $this->wpdb, $settings ) );
	}
}
