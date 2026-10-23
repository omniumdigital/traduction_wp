<?php
/**
 * WooCommerce privacy and security tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Queue\TranslationQueueInterface;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Store\SegmentRepositoryInterface;
use Omnium\Translate\Woo\CatalogTranslator;
use Omnium\Translate\Woo\EmailTemplateTranslator;
use Omnium\Translate\Woo\FragmentTranslator;
use Omnium\Translate\Woo\OrderLanguage;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class WooSecurityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.test/' );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) );
	}

	protected function tearDown(): void {
		unset( $_GET['lang'], $_GET['language'] );
		parent::tearDown();
	}

	public function test_unconfigured_fragment_language_is_refused(): void {
		$_GET['lang'] = 'de';
		$translator = $this->real_translator();
		$fragments = array( 'div.widget_shopping_cart_content' => '<div>Cart</div>' );

		$result = ( new FragmentTranslator( new CurrentLanguage( 'fr' ), new Settings(), $translator ) )->translate_fragments( $fragments );

		$this->assertSame( $fragments, $result );
	}

	public function test_catalog_translation_does_not_touch_commercial_variation_data(): void {
		$translator = $this->real_translator();
		$variation = array(
			'availability_html'     => '<p>In stock</p>',
			'variation_description' => 'Blue shirt',
			'price_html'            => '<span>99 €</span>',
			'sku'                   => 'SKU-42',
			'variation_id'          => 42,
		);

		$result = ( new CatalogTranslator( $translator ) )->translate_variation( $variation );

		$this->assertSame( $variation['price_html'], $result['price_html'] );
		$this->assertSame( $variation['sku'], $result['sku'] );
		$this->assertSame( $variation['variation_id'], $result['variation_id'] );
	}

	public function test_admin_email_object_is_not_translated(): void {
		$translator = $this->real_translator();
		$service = new EmailTemplateTranslator(
			new CurrentLanguage( 'fr' ),
			new Settings(),
			$this->createMock( SegmentExtractorInterface::class ),
			$this->createMock( SegmentRepositoryInterface::class ),
			$this->createMock( TranslationQueueInterface::class ),
			$translator
		);

		$this->assertSame( 'Order received', $service->translate_subject( 'Order received', new \stdClass(), new \stdClass() ) );
	}

	public function test_order_language_uses_only_order_meta_api(): void {
		$order = new class {
			public array $meta = array();

			public function update_meta_data( string $key, string $value ): void {
				$this->meta[ $key ] = $value;
			}
		};
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );
		( new OrderLanguage( $current ) )->record( $order );

		$this->assertSame( array( OrderLanguage::META_KEY => 'en' ), $order->meta );
	}

	/** Build a real renderer; its source-language fast path avoids external calls. */
	private function real_translator(): Translator {
		return new Translator(
			new CurrentLanguage( 'fr' ),
			$this->createMock( SegmentExtractorInterface::class ),
			$this->createMock( \Omnium\Translate\Store\TranslationRepositoryInterface::class ),
			new UrlTranslator( new Settings() )
		);
	}
}
