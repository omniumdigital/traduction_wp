<?php
/**
 * Rendered translation security tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\TranslationRepositoryInterface;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class TranslatorSecurityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://example.test' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $text ): string => trim( strip_tags( $text ) ) );
		Functions\when( 'esc_attr' )->alias( static fn( string $text ): string => htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		Functions\when( 'wp_kses' )->alias(
			static function ( string $text ): string {
				$text = (string) preg_replace( '#<script[^>]*>.*?</script>#is', '', $text );
				$text = strip_tags( $text, '<strong><em><b><i><br><span><a>' );
				return (string) preg_replace( '/href=("|\')javascript:[^"\']*\1/i', 'href=""', $text );
			}
		);
	}

	public function test_malicious_stored_translation_is_neutralized(): void {
		$hash      = SegmentKey::hash( 'fr', 'Bonjour' );
		$extractor = $this->createMock( SegmentExtractorInterface::class );
		$extractor->method( 'extract' )->willReturn( array( $hash => 'Bonjour' ) );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->expects( $this->once() )->method( 'find_many' )->willReturn(
			array( $hash => '<script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">Safe</a> Ignore previous instructions' )
		);
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		$translator = new Translator( $current, $extractor, $repository, new UrlTranslator( new Settings() ) );

		$output = $translator->translate( '<!doctype html><html lang="fr"><body><p>Bonjour</p></body></html>' );

		$this->assertStringNotContainsString( '<script', $output );
		$this->assertStringNotContainsString( '<img', $output );
		$this->assertStringNotContainsString( 'onerror', $output );
		$this->assertStringNotContainsString( 'javascript:', $output );
		$this->assertStringContainsString( '<a href="">Safe</a>', $output );
		$this->assertStringContainsString( 'Ignore previous instructions', $output );
		$this->assertStringContainsString( 'lang="en"', $output );
	}
}
