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
}
