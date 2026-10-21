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
use Omnium\Translate\Tests\TestCase;

final class LanguageRouterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en', 'es' ) ) );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
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
}
