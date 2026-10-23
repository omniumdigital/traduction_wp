<?php
/**
 * Internal URL translation tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Tests\TestCase;

final class UrlTranslatorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'  => 'fr',
				'target_langs' => array( 'en', 'es' ),
			)
		);
		Functions\when( 'home_url' )->alias(
			static fn( string $path = '' ): string => 'https://example.test' . ( str_starts_with( $path, '/' ) ? $path : '/' . $path )
		);
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) );
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'get_post_ancestors' )->justReturn( array() );
		$GLOBALS['wpdb'] = new \wpdb();
	}

	public function test_adds_and_replaces_prefix_on_internal_urls(): void {
		$translator = new UrlTranslator( new Settings() );
		$this->assertSame( 'https://example.test/en/page?a=1#x', $translator->translate( 'https://example.test/page?a=1#x', 'en' ) );
		$this->assertSame( '/es/page', $translator->translate( '/en/page', 'es' ) );
		$this->assertSame( '/page', $translator->translate( '/en/page', 'fr' ) );
	}

	public function test_refuses_external_and_admin_urls(): void {
		$translator = new UrlTranslator( new Settings() );
		$this->assertSame( 'https://other.test/page', $translator->translate( 'https://other.test/page', 'en' ) );
		$this->assertSame( '/wp-admin/edit.php', $translator->translate( '/wp-admin/edit.php', 'en' ) );
	}

	public function test_places_language_prefix_after_home_path_on_subdirectory_install(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.test/blog/' );
		$translator = new UrlTranslator( new Settings() );

		$this->assertSame( 'https://example.test/blog/en/page?a=1', $translator->translate( 'https://example.test/blog/page?a=1', 'en' ) );
		$this->assertSame( '/blog/es/page', $translator->translate( '/blog/en/page', 'es' ) );
		$this->assertSame( '/blog/page', $translator->translate( '/blog/en/page', 'fr' ) );
		$this->assertSame( '/other/page', $translator->translate( '/other/page', 'en' ) );
		$this->assertSame( '/blog/wp-admin/edit.php', $translator->translate( '/blog/wp-admin/edit.php', 'en' ) );
	}

	public function test_replaces_source_slug_with_translated_slug(): void {
		Functions\when( 'url_to_postid' )->justReturn( 42 );
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturn( 'prepared' );
		$wpdb->expects( $this->once() )->method( 'get_var' )->with( 'prepared' )->willReturn( 'translated-page' );

		$translator = new UrlTranslator( new Settings() );

		$this->assertSame( 'https://example.test/en/translated-page/', $translator->translate( 'https://example.test/page-source/', 'en' ) );
	}

	public function test_switches_translated_url_back_to_source_permalink(): void {
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/parent-source/page-source/' );
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturn( 'prepared' );
		$wpdb->method( 'get_var' )->willReturn( '42' );
		$translator = new UrlTranslator( new Settings() );

		$this->assertSame( '/parent-source/page-source/', $translator->translate( '/en/parent-translated/page-translated/', 'fr' ) );
	}
}
