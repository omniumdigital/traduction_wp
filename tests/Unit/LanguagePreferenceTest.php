<?php
/**
 * Optional language preference redirect tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Switcher\LanguagePreference;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class LanguagePreferenceTest extends TestCase {

	private bool $nocache = false;

	/** @var array<int, mixed> */
	private array $redirect = array();

	protected function setUp(): void {
		parent::setUp();
		$this->nocache  = false;
		$this->redirect = array();

		$_SERVER['REQUEST_URI'] = '/';
		$_COOKIE['omtr_lang']   = 'en';

		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'  => 'fr',
				'target_langs' => array( 'en', 'es' ),
				'pref_cookie'  => true,
			)
		);
		Functions\when( 'home_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test' . ( str_starts_with( $path, '/' ) ? $path : '/' . $path )
		);
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url, int $component = -1 ): array|string|int|null|false => -1 === $component ? parse_url( $url ) : parse_url( $url, $component )
		);
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'get_post_ancestors' )->justReturn( array() );
		Functions\when( 'is_front_page' )->justReturn( true );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'sanitize_key' )->alias( static fn( string $key ): string => strtolower( $key ) );
		Functions\when( 'wp_unslash' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'esc_url_raw' )->alias( static fn( string $url ): string => $url );
		Functions\when( 'wp_get_referer' )->justReturn( false );
		Functions\when( 'nocache_headers' )->alias( function (): void {
			$this->nocache = true;
		} );
		Functions\when( 'wp_safe_redirect' )->alias(
			function ( string $url, int $status = 302 ): void {
				$this->redirect = array( $url, $status );
				throw new \RuntimeException( 'redirected' );
			}
		);
		$GLOBALS['wpdb'] = new \wpdb();
	}

	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_URI'], $_COOKIE['omtr_lang'] );
		parent::tearDown();
	}

	private function preference( ?CurrentLanguage $language = null ): LanguagePreference {
		return new LanguagePreference( new Settings(), $language ?? new CurrentLanguage( 'fr' ), new UrlTranslator( new Settings() ) );
	}

	/** An external arrival on the source homepage redirects once, uncached. */
	public function test_redirects_external_arrival_to_configured_homepage(): void {
		try {
			$this->preference()->redirect();
			$this->fail( 'Expected a redirect.' );
		} catch ( \RuntimeException $exception ) {
			$this->assertSame( 'redirected', $exception->getMessage() );
		}
		$this->assertTrue( $this->nocache );
		$this->assertSame( array( 'https://example.test/en/', 302 ), $this->redirect );
	}

	/** An internal referer proves the visitor already chose their language. */
	public function test_never_redirects_when_referer_is_internal(): void {
		Functions\when( 'wp_get_referer' )->justReturn( 'https://example.test/a-propos/' );
		$this->preference()->redirect();
		$this->assertFalse( $this->nocache );
		$this->assertSame( array(), $this->redirect );
	}

	/** Only configured target languages may drive the redirect. */
	public function test_never_redirects_for_unconfigured_cookie_language(): void {
		$_COOKIE['omtr_lang'] = 'de';
		$this->preference()->redirect();
		$this->assertFalse( $this->nocache );
		$this->assertSame( array(), $this->redirect );
	}

	/** The option stays opt-in. */
	public function test_never_redirects_when_preference_is_disabled(): void {
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		$this->preference()->redirect();
		$this->assertFalse( $this->nocache );
		$this->assertSame( array(), $this->redirect );
	}

	/** Only the exact unprefixed source homepage redirects; deep links stay put. */
	public function test_never_redirects_translated_page_or_deep_link(): void {
		$translated = new CurrentLanguage( 'fr' );
		$translated->set( 'en' );
		$this->preference( $translated )->redirect();
		$this->assertFalse( $this->nocache );

		$_SERVER['REQUEST_URI'] = '/ma-page/';
		$this->preference()->redirect();
		$this->assertFalse( $this->nocache );
		$this->assertSame( array(), $this->redirect );
	}
}
