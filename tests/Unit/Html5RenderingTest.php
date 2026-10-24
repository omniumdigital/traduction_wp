<?php
/**
 * HTML5 rendering regression tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\HtmlSegmentExtractor;
use Omnium\Translate\Render\OutputBuffer;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\TranslationRepository;
use Omnium\Translate\Store\TranslationRepositoryInterface;
use Omnium\Translate\Tests\TestCase;

final class Html5RenderingTest extends TestCase {

	private const HTML5 = <<<'HTML'
<!doctype html>
<html lang="fr"><head>
<style>.sample::after { content: "</div>"; }</style>
<script type="application/ld+json">{"name":"Produit","offers":{"price":"19.90"}}</script>
<script>window.sample = "</div>";</script>
</head><body data-config='{"currency":"EUR","enabled":true}'>
<svg id="logo" viewBox="0 0 10 10" aria-hidden="true"><path d="M0 0h10v10z"></path></svg>
<math><mi>x</mi></math>
<template id="card-template"><article data-kind="card"><strong>Modèle intact</strong></article></template>
<noscript><p>Activez JavaScript</p></noscript>
<main><p>Bonjour HTML5</p></main>
</body></html>
HTML;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://example.test' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $text ): string => trim( strip_tags( $text ) ) );
		Functions\when( 'esc_attr' )->alias( static fn( string $text ): string => htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		Functions\when( 'wp_kses' )->alias( static fn( string $text ): string => strip_tags( $text, '<strong><em><b><i><br><span><a>' ) );
	}

	public function test_source_language_is_returned_byte_for_byte(): void {
		$current    = new CurrentLanguage( 'fr' );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->expects( $this->never() )->method( 'find_many' );
		$translator = $this->translator( $current, $repository );
		$buffer     = new OutputBuffer( $current, new RequestGuard(), $translator );
		$level      = ob_get_level();

		$buffer->start();
		$this->assertSame( $level, ob_get_level(), 'Source-language requests must not start the rewriting buffer.' );
		$this->assertSame( self::HTML5, $buffer->process( self::HTML5 ) );
	}

	public function test_translated_html5_keeps_special_elements_intact(): void {
		$hash       = SegmentKey::hash( 'fr', 'Bonjour HTML5' );
		$current    = new CurrentLanguage( 'fr' );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->expects( $this->once() )->method( 'find_many' )->willReturn( array( $hash => 'Hello HTML5' ) );
		$current->set( 'en' );

		$output   = $this->translator( $current, $repository )->translate( self::HTML5 );
		$document = HtmlSegmentExtractor::load_document( $output );
		$body     = null === $document ? null : $document->getElementsByTagName( 'body' )->item( 0 );

		$this->assertNotNull( $document );
		$this->assertSame( 'en', $document->documentElement?->getAttribute( 'lang' ) );
		$this->assertSame( '{"currency":"EUR","enabled":true}', $body?->getAttribute( 'data-config' ) );
		$this->assertSame( $this->sensitive_blocks( self::HTML5 ), $this->sensitive_blocks( $output ), 'Sensitive blocks must be restored byte for byte.' );
		$this->assertStringContainsString( '<noscript><p>Activez JavaScript</p></noscript>', $output );
		$this->assertStringContainsString( '<p>Hello HTML5</p>', $output );
		$this->assertStringNotContainsString( '<?xml', $output );
	}

	public function test_translated_accented_text_is_not_entity_encoded(): void {
		if ( class_exists( 'Dom\\HTMLDocument' ) ) {
			$this->markTestSkipped( 'DOMDocument fallback only: the legacy HTML4 serializer entity-encodes inserted text.' );
		}
		$html       = '<!doctype html><html lang="fr"><body><p>Bonjour & bienvenue</p></body></html>';
		$hash       = SegmentKey::hash( 'fr', 'Bonjour & bienvenue' );
		$current    = new CurrentLanguage( 'fr' );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->expects( $this->once() )->method( 'find_many' )->willReturn( array( $hash => 'Présentation étudiée & validée' ) );
		$current->set( 'en' );

		$output = $this->translator( $current, $repository )->translate( $html );

		$this->assertStringContainsString( '<p>Présentation étudiée &amp; validée</p>', $output );
		$this->assertStringNotContainsString( '&eacute;', $output );
		$this->assertStringNotContainsString( '&#233;', $output );
	}

	/**
	 * @group security
	 */
	public function test_fallback_entity_decoder_preserves_markup_and_attribute_boundaries(): void {
		$method = new \ReflectionMethod( HtmlSegmentExtractor::class, 'decode_spurious_entities' );
		$input  = '<p>&#60;script&#62;alert(1)&#60;/script&#62; &#x3C;img src=x onerror=alert(1)&#x3E; &lt script&gt;</p>';
		$input .= '<a title="safe&#34; onmouseover=alert(1)" href="javascript&#58;alert(1)">link</a>';
		$input .= '<p>&#233; &eacute; &#x1F600; &lt; &amp; &quot; &#39;</p>';

		$output = $method->invoke( null, $input );

		$this->assertStringContainsString( '&#60;script&#62;', $output );
		$this->assertStringContainsString( '&#x3C;img', $output );
		$this->assertStringContainsString( '&lt script&gt;', $output );
		$this->assertStringContainsString( 'safe&#34; onmouseover=', $output );
		$this->assertStringContainsString( 'javascript&#58;alert(1)', $output );
		$this->assertStringContainsString( 'é é 😀 &lt; &amp; &quot; &#39;', $output );
		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringNotContainsString( '<img src=x', $output );
	}

	/**
	 * @group security
	 */
	public function test_legacy_dom_serializer_keeps_unicode_and_escaped_markup(): void {
		$document = new \DOMDocument( '1.0', 'UTF-8' );
		$document->loadHTML( '<html><head><meta charset="UTF-8"></head><body><p></p></body></html>' );
		$paragraph = $document->getElementsByTagName( 'p' )->item( 0 );
		$this->assertNotNull( $paragraph );
		$paragraph->appendChild( $document->createTextNode( 'Été <script>alert(1)</script> & suite' ) );

		$output = HtmlSegmentExtractor::save_document( $document );

		$this->assertIsString( $output );
		$this->assertStringContainsString( 'Été &lt;script&gt;alert(1)&lt;/script&gt; &amp; suite', $output );
		$this->assertStringNotContainsString( '<script>alert(1)</script>', $output );
	}

	public function test_modern_parser_rewrites_json_ld_with_stored_translations(): void {
		if ( ! class_exists( 'Dom\\HTMLDocument' ) ) {
			$this->markTestSkipped( 'HTML5 parser only.' );
		}
		Functions\when( 'wp_json_encode' )->alias( static fn( mixed $data, int $flags = 0 ): string|false => json_encode( $data, $flags ) );
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		$hash = SegmentKey::hash( 'fr', 'Produit' );
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->expects( $this->once() )->method( 'find_many' )->willReturn( array( $hash => 'Product' ) );
		$html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Produit","url":"https://example.test/produit","inLanguage":"fr"}</script></head></html>';
		$output = $this->translator( $current, $repository )->translate( $html );
		$this->assertStringContainsString( '"name":"Product"', $output );
		$this->assertStringContainsString( '"url":"https://example.test/en/produit"', $output );
		$this->assertStringContainsString( '"inLanguage":"en"', $output );
	}

	public function test_dynamic_html_with_same_segments_reuses_stable_cache_key(): void {
		$cache_keys = array();
		Functions\when( 'wp_cache_get' )->alias(
			static fn( string $key ): int|false => TranslationRepository::CACHE_VERSION_KEY === $key ? 7 : false
		);
		Functions\when( 'wp_cache_set' )->alias(
			static function ( string $key ) use ( &$cache_keys ): bool {
				$cache_keys[] = $key;
				return true;
			}
		);
		$hash       = SegmentKey::hash( 'fr', 'Bonjour HTML5' );
		$current    = new CurrentLanguage( 'fr' );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->expects( $this->exactly( 2 ) )->method( 'find_many' )->willReturn( array( $hash => 'Hello HTML5' ) );
		$current->set( 'en' );
		$translator = $this->translator( $current, $repository );

		$translator->translate( str_replace( '<body ', '<body data-nonce="first" ', self::HTML5 ) );
		$translator->translate( str_replace( '<body ', '<body data-nonce="second" ', self::HTML5 ) );

		$this->assertCount( 2, $cache_keys );
		$this->assertSame( $cache_keys[0], $cache_keys[1] );
	}

	public function test_fallback_detects_case_insensitive_sensitive_closing_tags(): void {
		if ( class_exists( 'Dom\\HTMLDocument' ) ) {
			$this->markTestSkipped( 'DOMDocument fallback only.' );
		}

		$html       = '<!doctype html><html lang="fr"><head><script>window.sample = "</div>";</ScRiPt></head><body><p>Bonjour HTML5</p></body></html>';
		$hash       = SegmentKey::hash( 'fr', 'Bonjour HTML5' );
		$current    = new CurrentLanguage( 'fr' );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->expects( $this->once() )->method( 'find_many' )->willReturn( array( $hash => 'Hello HTML5' ) );
		$current->set( 'en' );

		$output = $this->translator( $current, $repository )->translate( $html );

		$this->assertStringContainsString( '<script>window.sample = "</div>";</ScRiPt>', $output );
		$this->assertStringContainsString( '<p>Hello HTML5</p>', $output );
		$this->assertStringNotContainsString( '<?xml', $output );
	}

	private function translator( CurrentLanguage $current, TranslationRepositoryInterface $repository ): Translator {
		return new Translator(
			$current,
			new HtmlSegmentExtractor( new PiiFilter() ),
			$repository,
			new UrlTranslator( new Settings() )
		);
	}

	/**
	 * Return raw-text and foreign-content blocks in document order.
	 *
	 * @return list<string>
	 */
	private function sensitive_blocks( string $html ): array {
		preg_match_all( '~<(script|style|svg|math|template)\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>.*?</\1\s*>~is', $html, $matches );
		return $matches[0];
	}
}
