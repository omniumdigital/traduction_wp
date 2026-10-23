<?php
/**
 * WooCommerce fragment and email rendering regression tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Queue\TranslationQueueInterface;
use Omnium\Translate\Render\HtmlSegmentExtractor;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\SegmentRepositoryInterface;
use Omnium\Translate\Store\TranslationRepositoryInterface;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Woo\CatalogTranslator;
use Omnium\Translate\Woo\EmailTemplateTranslator;
use Omnium\Translate\Woo\FragmentTranslator;
use Omnium\Translate\Woo\OrderLanguage;

defined( 'ABSPATH' ) || exit;

/** @group security */
final class WooFragmentTranslationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://example.test' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $text ): string => trim( strip_tags( $text ) ) );
		Functions\when( 'esc_attr' )->alias( static fn( string $text ): string => htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		Functions\when( 'wp_kses' )->alias( static fn( string $text ): string => strip_tags( $text, '<strong><em><b><i><br><span><a>' ) );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( string $text ): string => strip_tags( $text ) );
	}

	protected function tearDown(): void {
		unset( $_GET['lang'] );
		parent::tearDown();
	}

	public function test_cart_fragment_and_source_bytes(): void {
		$current = new CurrentLanguage( 'fr' );
		$translator = $this->translator( $current, array( 'Panier' => 'Cart' ) );
		$source = "<div class='mini-cart'><span>Panier</span></div>";
		$this->assertSame( $source, $translator->translate_fragment( $source ) );
		$_GET['lang'] = 'en';
		$result = ( new FragmentTranslator( $current, new Settings(), $translator ) )->translate_fragments( array( '.mini-cart' => $source ) );
		$this->assertSame( '<div class="mini-cart"><span>Cart</span></div>', $result['.mini-cart'] );
		$this->assertStringNotContainsString( '<html', $result['.mini-cart'] );
		$this->assertStringNotContainsString( '<body', $result['.mini-cart'] );
		$this->assertStringNotContainsString( '<!DOCTYPE', $result['.mini-cart'] );
	}

	public function test_variation_label_is_plain_text(): void {
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		$translator = $this->translator( $current, array( 'Choisir une option' => 'Choose an option' ) );
		$result = ( new CatalogTranslator( $translator ) )->translate_selector_args( array( 'show_option_none' => 'Choisir une option' ) );
		$this->assertSame( 'Choose an option', $result['show_option_none'] );
	}

	public function test_email_template_is_formatted_and_customized_without_customer_extraction(): void {
		$current = new CurrentLanguage( 'fr' );
		$extractor = new HtmlSegmentExtractor( new PiiFilter() );
		$translator = $this->translator( $current, array( 'Votre commande {order_number}' => 'Your order {order_number}', 'Commande spéciale {order_number}' => 'Special order {order_number}' ) );
		$segments = $this->createMock( SegmentRepositoryInterface::class );
		$segments->expects( $this->never() )->method( 'upsert_many' );
		$queue = $this->createMock( TranslationQueueInterface::class );
		$queue->expects( $this->never() )->method( 'enqueue_segments' );
		$service = new EmailTemplateTranslator( $current, new Settings(), $extractor, $segments, $queue, $translator );
		$order = new class {
			public function get_meta( string $key, bool $single ): string {
				unset( $key, $single );
				return 'en';
			}
		};
		$email = new class {
			public string $template = 'Votre commande {order_number}';
			public function get_default_subject(): string {
				return 'Votre commande {order_number}';
			}
			public function get_option( string $key, string $fallback ): string {
				unset( $key );
				return $this->template ?: $fallback;
			}
			public function format_string( string $value ): string {
				return str_replace( '{order_number}', '314', $value );
			}
		};
		$this->assertSame( 'Your order 314', $service->translate_subject( 'Votre commande 314', $order, $email ) );
		$email->template = 'Commande spéciale {order_number}';
		$this->assertSame( 'Special order 314', $service->translate_subject( 'Commande spéciale 314', $order, $email ) );
		$email->template = '';
		$this->assertSame( 'Texte original 314', $service->translate_additional_content( 'Texte original 314', $order, $email ) );
		$this->assertSame( array(), $extractor->extract( '<div>client@example.test</div>', 'fr' ) );
	}

	/** @param array<string, string> $translations Source text => target text. */
	private function translator( CurrentLanguage $current, array $translations ): Translator {
		$stored = array();
		foreach ( $translations as $source => $target ) {
			$stored[ SegmentKey::hash( 'fr', $source ) ] = $target;
		}
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->method( 'find_many' )->willReturnCallback( static fn( array $hashes ): array => array_intersect_key( $stored, array_flip( $hashes ) ) );
		return new Translator( $current, new HtmlSegmentExtractor( new PiiFilter() ), $repository, new UrlTranslator( new Settings() ) );
	}
}
