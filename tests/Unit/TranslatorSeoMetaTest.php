<?php
/**
 * SEO meta rendering regression tests (Elementor + Yoast / Rank Math recette).
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\HtmlSegmentExtractor;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\TranslationRepositoryInterface;
use Omnium\Translate\Tests\TestCase;

/**
 * Constant or URL-valued SEO metas and link titles must never be rewritten
 * with a stored translation, while textual metas are translated.
 *
 * @group security
 */
final class TranslatorSeoMetaTest extends TestCase {

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

	public function test_non_textual_seo_meta_and_link_titles_stay_intact(): void {
		$html      = '<!doctype html><html lang="fr"><head>'
			. '<link rel="alternate" type="application/rss+xml" title="Site Feed" href="https://example.test/feed/">'
			. '<meta name="description" content="Bienvenue sur notre site">'
			. '<meta property="og:locale" content="en_US">'
			. '<meta property="og:type" content="article">'
			. '<meta property="og:title" content="Mon titre de page">'
			. '<meta property="og:url" content="https://example.test/page/">'
			. '<meta name="twitter:card" content="summary_large_image">'
			. '<meta name="twitter:title" content="Mon titre pour Twitter">'
			. '</head><body><p>Corps de page</p></body></html>';
		$extractor = new HtmlSegmentExtractor( new PiiFilter() );
		$stored    = array();
		foreach ( array( 'en_US', 'article', 'summary_large_image', 'Site Feed', 'https://example.test/page/' ) as $bait ) {
			$stored[ SegmentKey::hash( 'fr', $bait ) ] = 'PIEGE ' . $bait;
		}
		$stored[ SegmentKey::hash( 'fr', 'Bienvenue sur notre site' ) ] = 'Welcome to our website';
		$stored[ SegmentKey::hash( 'fr', 'Mon titre de page' ) ]        = 'My page title';
		$stored[ SegmentKey::hash( 'fr', 'Mon titre pour Twitter' ) ]   = 'My Twitter title';
		$stored[ SegmentKey::hash( 'fr', 'Corps de page' ) ]            = 'Page body';

		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->method( 'find_many' )->willReturn( $stored );
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );

		$translator = new Translator( $current, $extractor, $repository, new UrlTranslator( new Settings() ) );
		$output     = $translator->translate( $html );

		$this->assertStringContainsString( 'content="en_US"', $output );
		$this->assertStringContainsString( 'content="article"', $output );
		$this->assertStringContainsString( 'content="summary_large_image"', $output );
		$this->assertStringContainsString( 'content="https://example.test/page/"', $output );
		$this->assertStringContainsString( 'title="Site Feed"', $output );
		$this->assertStringNotContainsString( 'PIEGE', $output );
		$this->assertStringContainsString( 'content="Welcome to our website"', $output );
		$this->assertStringContainsString( 'content="My page title"', $output );
		$this->assertStringContainsString( 'content="My Twitter title"', $output );
		$this->assertStringContainsString( 'Page body', $output );
	}
}
