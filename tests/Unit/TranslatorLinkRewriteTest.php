<?php
/**
 * Link rewriting regression tests (Elementor + language switcher recette).
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
use Omnium\Translate\Store\TranslationRepositoryInterface;
use Omnium\Translate\Tests\TestCase;

/**
 * Anchors carrying an hreflang attribute target an explicit language
 * (switcher entries): they must never be rewritten to the current language,
 * while ordinary internal links still are — in full documents and fragments.
 */
final class TranslatorLinkRewriteTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://example.test' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) );
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $text ): string => trim( strip_tags( $text ) ) );
		Functions\when( 'esc_attr' )->alias( static fn( string $text ): string => htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		Functions\when( 'wp_kses' )->alias( static fn( string $text ): string => strip_tags( $text, '<strong><em><b><i><br><span><a>' ) );
	}

	/**
	 * Build a translator for the English rendering of a French page.
	 */
	private function translator(): Translator {
		$extractor = $this->createMock( SegmentExtractorInterface::class );
		$extractor->method( 'extract' )->willReturn( array() );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->method( 'find_many' )->willReturn( array() );
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		return new Translator( $current, $extractor, $repository, new UrlTranslator( new Settings() ) );
	}

	public function test_switcher_hreflang_links_stay_intact_on_a_translated_page(): void {
		$html      = '<!doctype html><html lang="fr"><head><title>À propos</title></head><body>'
			. '<nav class="omtr-switcher notranslate" data-omtr-skip translate="no"><ul class="omtr-switcher__list">'
			. '<li><a class="omtr-switcher__link is-active" href="https://example.test/en/a-propos/" lang="en" hreflang="en" aria-current="page">English</a></li>'
			. '<li><a class="omtr-switcher__link" href="https://example.test/a-propos/" lang="fr" hreflang="fr">Français</a></li>'
			. '<li><a class="omtr-switcher__link" href="https://example.test/es/sobre/" lang="es" hreflang="es">Español</a></li>'
			. '</ul></nav>'
			. '<main><a href="https://example.test/contact/">Contact</a></main>'
			. '</body></html>';

		$output = $this->translator()->translate( $html );

		// The "Français" entry still points at the source page, "Español" at
		// its own language: hreflang anchors are never rewritten.
		$this->assertStringContainsString( 'href="https://example.test/a-propos/"', $output );
		$this->assertStringContainsString( 'href="https://example.test/es/sobre/"', $output );
		// An ordinary internal link is still localized to the current language.
		$this->assertStringContainsString( 'href="https://example.test/en/contact/"', $output );
		$this->assertStringNotContainsString( 'href="https://example.test/contact/"', $output );
	}

	public function test_translate_fragment_keeps_hreflang_links_intact(): void {
		$fragment = '<a class="omtr-switcher__link" href="https://example.test/a-propos/" lang="fr" hreflang="fr">Français</a> <a href="https://example.test/contact/">Contact</a>';

		$output = $this->translator()->translate_fragment( $fragment );

		$this->assertStringContainsString( 'href="https://example.test/a-propos/"', $output );
		$this->assertStringContainsString( 'href="https://example.test/en/contact/"', $output );
	}
}
