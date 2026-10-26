<?php
/**
 * Head tags tests.
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
use Omnium\Translate\Seo\TranslationCoverage;
use Omnium\Translate\Tests\TestCase;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Verifies safe, reciprocal SEO head output.
 *
 * @group security
 */
final class HeadTagsTest extends TestCase {
	/**
	 * Number of escaped href values.
	 *
	 * @var int
	 */
	private int $esc_url_calls = 0;

	/** Set up WordPress function doubles. */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'     => 'fr',
				'target_langs'    => array( 'en', 'es' ),
				'noindex_partial' => false,
			)
		);
		Functions\when( 'is_singular' )->justReturn( false );
		Functions\when( 'get_queried_object_id' )->justReturn( 0 );
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://safe.test' . $path );
		// No translated slug in these tests: UrlTranslator falls back to the source slug.
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ): mixed => parse_url( $url, $component ) // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test double for wp_parse_url().
		);
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_feed' )->justReturn( false );
		Functions\when( 'is_robots' )->justReturn( false );
		Functions\when( 'is_trackback' )->justReturn( false );
		Functions\when( 'is_404' )->justReturn( false );
		Functions\when( 'is_search' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'esc_attr' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias(
			function ( string $value ): string {
				++$this->esc_url_calls;
				return htmlspecialchars( $value, ENT_QUOTES );
			}
		);
		$GLOBALS['wp'] = (object) array( 'request' => 'article' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Controlled request fixture.
	}

	/** Remove the request fixture. */
	protected function tearDown(): void {
		unset( $GLOBALS['wp'] );
		parent::tearDown();
	}

	/** Output includes every language and the localized self-canonical. */
	public function test_outputs_reciprocal_hreflang_and_translated_self_canonical(): void {
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		$tags = $this->tags( $current );

		ob_start();
		$tags->render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'hreflang="fr" href="https://safe.test/article"', $html );
		$this->assertStringContainsString( 'hreflang="en" href="https://safe.test/en/article"', $html );
		$this->assertStringContainsString( 'hreflang="es" href="https://safe.test/es/article"', $html );
		$this->assertStringContainsString( 'hreflang="x-default" href="https://safe.test/article"', $html );
		$this->assertStringContainsString( 'rel="canonical" href="https://safe.test/en/article"', $html );
		$this->assertSame( 'https://safe.test/en/article', $tags->filter_yoast_canonical( 'https://unsafe.test/article' ) );
		$this->assertSame( 'https://safe.test/en/article', $tags->filter_rank_math_canonical( 'https://unsafe.test/article' ) );
	}

	/** URLs use home_url and every printed href passes through esc_url. */
	public function test_builds_urls_from_home_url_and_escapes_every_href(): void {
		$_SERVER['HTTP_HOST'] = 'attacker.test';

		ob_start();
		$this->tags( new CurrentLanguage( 'fr' ) )->render();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'attacker.test', $html );
		$this->assertStringContainsString( 'https://safe.test/article', $html );
		$this->assertSame( 5, $this->esc_url_calls );
		unset( $_SERVER['HTTP_HOST'] );
	}

	/**
	 * Excluded requests produce no tags.
	 *
	 * @dataProvider excluded_requests
	 * @param string $conditional WordPress request conditional.
	 */
	public function test_outputs_nothing_for_excluded_requests( string $conditional ): void {
		Functions\when( $conditional )->justReturn( true );
		ob_start();
		$this->tags( new CurrentLanguage( 'fr' ) )->render();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * Request conditionals excluded from SEO output.
	 *
	 * @return iterable<string, array{string}>
	 */
	public static function excluded_requests(): iterable {
		yield 'admin' => array( 'is_admin' );
		yield 'AJAX' => array( 'wp_doing_ajax' );
		yield 'feed' => array( 'is_feed' );
	}

	/** REST routes produce no tags. */
	#[RunInSeparateProcess]
	#[PreserveGlobalState( false )]
	public function test_outputs_nothing_for_rest_request(): void {
		define( 'REST_REQUEST', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- WordPress core request constant in isolated test.

		ob_start();
		$this->tags( new CurrentLanguage( 'fr' ) )->render();
		$this->assertSame( '', (string) ob_get_clean() );
	}

	/**
	 * Build the system under test.
	 *
	 * @param CurrentLanguage $current Current request language.
	 */
	private function tags( CurrentLanguage $current ): HeadTags {
		$settings = new Settings();
		return new HeadTags( $settings, $current, new RequestGuard(), new UrlTranslator( $settings ), new TranslationCoverage( $this->createMock( \wpdb::class ), $settings ) );
	}
}
