<?php
/**
 * Structured data translation tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\HtmlSegmentExtractor;
use Omnium\Translate\Render\JsonLdProcessor;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\TranslationRepositoryInterface;
use Omnium\Translate\Tests\TestCase;

/** @group security */
final class JsonLdProcessorTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://example.test' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- WordPress test double.
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'wp_json_encode' )->alias( static fn( mixed $data, int $flags = 0 ): string|false => json_encode( $data, $flags ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $text ): string => strip_tags( $text ) );
		Functions\when( 'wp_kses' )->alias( static fn( string $text ): string => strip_tags( $text, '<strong><em><b><i><br><span><a>' ) );
		Functions\when( 'esc_attr' )->alias( static fn( string $text ): string => htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	public function test_extracts_and_translates_yoast_graph(): void {
		$json = '{"@context":"https://schema.org","@graph":[{"@type":"WebPage","@id":"https://example.test/article#webpage","name":"Titre source","description":"Description source","inLanguage":"fr"},{"@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","name":"Accueil","item":"https://example.test/"}]}]}';
		$segments = JsonLdProcessor::segments( $json, 'fr', new PiiFilter() );
		$this->assertCount( 3, $segments );
		$output = $this->translate( $json, array( 'Titre source' => 'Source title', 'Description source' => 'Source description', 'Accueil' => 'Home' ) );
		$data = json_decode( $output );
		$this->assertSame( 'Source title', $data->{'@graph'}[0]->name );
		$this->assertSame( 'https://example.test/en/article#webpage', $data->{'@graph'}[0]->{'@id'} );
		$this->assertSame( 'en', $data->{'@graph'}[0]->inLanguage );
		$this->assertSame( 'Home', $data->{'@graph'}[1]->itemListElement[0]->name );
		$this->assertSame( 'https://schema.org', $data->{'@context'} );
	}

	public function test_rank_math_faq_and_woocommerce_product_offer(): void {
		$json = '{"@context":"https://schema.org","@type":"Product","name":"Produit","sku":"SKU-42","gtin13":"1234567890123","offers":{"@type":"Offer","name":"Offre spéciale","price":"19.90","priceCurrency":"EUR","url":"https://example.test/produit"},"mainEntity":[{"@type":"Question","name":"Question fréquente","acceptedAnswer":{"@type":"Answer","text":"Réponse utile"}}]}';
		$output = $this->translate( $json, array( 'Produit' => 'Product', 'Offre spéciale' => 'Special offer', 'Question fréquente' => 'Frequent question', 'Réponse utile' => 'Useful answer' ) );
		$data = json_decode( $output );
		$this->assertSame( 'Product', $data->name );
		$this->assertSame( 'Special offer', $data->offers->name );
		$this->assertSame( 'Useful answer', $data->mainEntity[0]->acceptedAnswer->text );
		$this->assertSame( '19.90', $data->offers->price );
		$this->assertSame( 'EUR', $data->offers->priceCurrency );
		$this->assertSame( 'SKU-42', $data->sku );
		$this->assertSame( '1234567890123', $data->gtin13 );
		$this->assertSame( 'https://example.test/en/produit', $data->offers->url );
	}

	public function test_invalid_json_is_unchanged_and_script_breakout_is_encoded(): void {
		$this->assertSame( '{"name":', $this->translate( '{"name":', array() ) );
		$json = '{"name":"Nom"}';
		$output = $this->translate( $json, array( 'Nom' => '</script><script>alert(1)</script>' ) );
		$this->assertStringNotContainsString( '</script>', $output );
		$this->assertStringContainsString( '\\u003C', $this->translate( '{"name":"<\\/script><script>alert(1)<\\/script>"}', array() ) );
	}

	public function test_crawler_extracts_json_ld_on_fallback_parser(): void {
		$html = '<html><head><script type="application/ld+json">{"@type":"Product","name":"Produit","offers":{"price":"19.90"}}</script></head></html>';
		$segments = ( new HtmlSegmentExtractor( new PiiFilter() ) )->extract( $html, 'fr' );
		$this->assertArrayHasKey( SegmentKey::hash( 'fr', 'Produit' ), $segments );
		$this->assertCount( 1, $segments );
	}

	public function test_skipped_json_ld_does_not_enter_translation_queue(): void {
		$html = '<html><body><div data-omtr-skip><script type="application/ld+json">{"name":"Nom privé"}</script></div></body></html>';
		$segments = ( new HtmlSegmentExtractor( new PiiFilter() ) )->extract( $html, 'fr' );
		$this->assertArrayNotHasKey( SegmentKey::hash( 'fr', 'Nom privé' ), $segments );
	}

	public function test_rendered_product_uses_shared_cache_and_escapes_untrusted_translation(): void {
		$html = '<!doctype html><html><head><script type="application/ld+json">'
			. '{"@type":"Product","@id":"https://example.test/produit#product","url":"https://example.test/produit","name":"Produit","description":"Description source","sku":"SKU-42","gtin13":"1234567890123","offers":{"price":"19.90","priceCurrency":"EUR"}}'
			. '</script></head><body><p>Produit</p></body></html>';
		$expected = array(
			SegmentKey::hash( 'fr', 'Produit' )            => 'Product',
			SegmentKey::hash( 'fr', 'Description source' ) => '</script><script>alert(1)</script>',
		);
		$cache = array();
		Functions\when( 'wp_cache_get' )->alias( static function ( string $key, string $group ) use ( &$cache ): mixed {
			return $cache[ $group ][ $key ] ?? false;
		} );
		Functions\when( 'wp_cache_set' )->alias( static function ( string $key, mixed $value, string $group ) use ( &$cache ): bool {
			$cache[ $group ][ $key ] = $value;
			return true;
		} );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->expects( $this->once() )
			->method( 'find_many' )
			->with( $this->callback( static function ( array $hashes ) use ( $expected ): bool {
				return array_keys( $expected ) === array_values( array_intersect( array_keys( $expected ), $hashes ) ) && 2 === count( $hashes );
			} ), 'en' )
			->willReturn( $expected );
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		$translator = new Translator( $current, new HtmlSegmentExtractor( new PiiFilter() ), $repository, new UrlTranslator( new Settings() ) );

		foreach ( array( 1, 2 ) as $run ) {
			$output = $translator->translate( $html );
			$this->assertSame( 0, $translator->missing_count() );
			$this->assertStringNotContainsString( '</script><script>', $output );
			$this->assertSame( 1, substr_count( $output, '</script>' ) );
			$this->assertMatchesRegularExpression( '~<script type="application/ld\+json">(.*?)</script>~s', $output );
			preg_match( '~<script type="application/ld\+json">(.*?)</script>~s', $output, $matches );
			$data = json_decode( $matches[1] );
			$this->assertSame( JSON_ERROR_NONE, json_last_error() );
			$this->assertSame( 'Product', $data->name );
			$this->assertSame( 'alert(1)', $data->description );
			$this->assertSame( 'https://example.test/en/produit#product', $data->{'@id'} );
			$this->assertSame( 'https://example.test/en/produit', $data->url );
			$this->assertSame( '19.90', $data->offers->price );
			$this->assertSame( 'EUR', $data->offers->priceCurrency );
			$this->assertSame( 'SKU-42', $data->sku );
			$this->assertSame( '1234567890123', $data->gtin13 );
			$this->assertStringContainsString( 'Product', $output );
		}
	}

	/** @param array<string, string> $translations Existing source-to-target translations. */
	private function translate( string $json, array $translations ): string {
		$stored = array();
		foreach ( $translations as $source => $target ) {
			$stored[ SegmentKey::hash( 'fr', $source ) ] = $target;
		}
		return JsonLdProcessor::translate( $json, 'fr', 'en', $stored, new UrlTranslator( new Settings() ) );
	}
}
