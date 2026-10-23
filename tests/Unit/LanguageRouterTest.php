<?php
/**
 * Language router tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\LanguageRouter;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Store\SlugRepository;
use Omnium\Translate\Tests\TestCase;

final class LanguageRouterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en', 'es' ) ) );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'home_url' )->justReturn( 'https://example.test/' );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) );
	}

	public function test_strips_supported_prefix_before_wordpress_parsing(): void {
		$_SERVER['REQUEST_URI'] = '/en/bonjour/?a=1';
		$current                = new CurrentLanguage( 'fr' );
		$router                 = new LanguageRouter( $current, new Settings(), new RequestGuard() );

		$this->assertTrue( $router->strip_language_prefix( true ) );
		$this->assertSame( 'en', $current->get() );
		$this->assertSame( '/bonjour/?a=1', $_SERVER['REQUEST_URI'] );
	}

	public function test_preserves_unprefixed_source_uri_byte_for_byte(): void {
		$uri                    = '/caf%C3%A9/été/%2Fbrut?redirect=%2Fen%2Ffoo&mot=élève';
		$_SERVER['REQUEST_URI'] = $uri;
		$current                = new CurrentLanguage( 'fr' );
		$router                 = new LanguageRouter( $current, new Settings(), new RequestGuard() );

		$this->assertTrue( $router->strip_language_prefix( true ) );
		$this->assertSame( 'fr', $current->get() );
		$this->assertSame( $uri, $_SERVER['REQUEST_URI'] );
	}

	public function test_preserves_encoded_path_and_query_when_stripping_prefix(): void {
		$_SERVER['REQUEST_URI'] = '/en/caf%C3%A9/été/%2Fbrut?redirect=%2Fen%2Ffoo';
		$current                = new CurrentLanguage( 'fr' );
		$router                 = new LanguageRouter( $current, new Settings(), new RequestGuard() );

		$router->strip_language_prefix( true );

		$this->assertSame( '/caf%C3%A9/été/%2Fbrut?redirect=%2Fen%2Ffoo', $_SERVER['REQUEST_URI'] );
	}

	public function test_strips_prefix_after_home_path_on_subdirectory_install(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.test/blog/' );
		$_SERVER['REQUEST_URI'] = '/blog/en/bonjour/?a=1';
		$current                = new CurrentLanguage( 'fr' );
		$router                 = new LanguageRouter( $current, new Settings(), new RequestGuard() );

		$router->strip_language_prefix( true );

		$this->assertSame( 'en', $current->get() );
		$this->assertSame( '/blog/bonjour/?a=1', $_SERVER['REQUEST_URI'] );
	}

	public function test_resolves_translated_slug_to_source_permalink_in_subdirectory(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.test/blog/' );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/blog/parent-source/page-source/' );
		$_SERVER['REQUEST_URI'] = '/blog/en/parent-translated/page-translated/?a=1';
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturn( 'prepared' );
		$wpdb->expects( $this->once() )->method( 'get_var' )->with( 'prepared' )->willReturn( '42' );
		$current = new CurrentLanguage( 'fr' );
		$router  = new LanguageRouter( $current, new Settings(), new RequestGuard(), new SlugRepository( $wpdb ) );

		$router->strip_language_prefix( true );

		$this->assertSame( 'en', $current->get() );
		$this->assertSame( '/blog/parent-source/page-source/?a=1', $_SERVER['REQUEST_URI'] );
	}

	public function test_historical_slug_targets_new_translated_url(): void {
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/source-page/' );
		Functions\when( 'url_to_postid' )->justReturn( 42 );
		Functions\when( 'get_post_ancestors' )->justReturn( array() );
		$_SERVER['REQUEST_URI'] = '/en/old-page/';
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturn( 'prepared' );
		$wpdb->method( 'get_var' )->willReturnOnConsecutiveCalls( null, '42', 'new-page' );
		$current = new CurrentLanguage( 'fr' );
		$router  = new LanguageRouter( $current, new Settings(), new RequestGuard(), new SlugRepository( $wpdb ), new UrlTranslator( new Settings() ) );

		$router->strip_language_prefix( true );
		$this->assertSame( '/source-page/', $_SERVER['REQUEST_URI'] );
		$this->assertSame( 'https://example.test/en/new-page/', $router->old_slug_redirect_url() );
	}

	public function test_does_not_redirect_historical_slug_for_unpublished_post(): void {
		Functions\when( 'get_post_status' )->justReturn( 'private' );
		$_SERVER['REQUEST_URI'] = '/en/old-page/';
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturn( 'prepared' );
		$wpdb->method( 'get_var' )->willReturnOnConsecutiveCalls( null, '42' );
		$router = new LanguageRouter( new CurrentLanguage( 'fr' ), new Settings(), new RequestGuard(), new SlugRepository( $wpdb ), new UrlTranslator( new Settings() ) );

		$router->strip_language_prefix( true );
		$this->assertSame( '/old-page/', $_SERVER['REQUEST_URI'] );
		$this->assertNull( $router->old_slug_redirect_url() );
	}
}
