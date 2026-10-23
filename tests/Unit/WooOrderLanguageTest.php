<?php
/**
 * WooCommerce order language tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Actions;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Woo\OrderLanguage;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class WooOrderLanguageTest extends TestCase {

	public function test_records_only_current_language_using_order_api(): void {
		$order = new class {
			/** @var array<string, string> */
			public array $meta = array();

			public function update_meta_data( string $key, string $value ): void {
				$this->meta[ $key ] = $value;
			}
		};
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );

		( new OrderLanguage( $current ) )->record( $order, array( 'billing_email' => 'private@example.test' ) );

		$this->assertSame( array( OrderLanguage::META_KEY => 'en' ), $order->meta );
	}

	public function test_ignores_invalid_order_objects_without_storing_data(): void {
		$current = new CurrentLanguage( 'fr' );
		( new OrderLanguage( $current ) )->record( new \stdClass() );
		$this->addToAssertionCount( 1 );
	}

	public function test_registers_classic_and_blocks_hooks(): void {
		$order_language = new OrderLanguage( new CurrentLanguage( 'fr' ) );
		Actions\expectAdded( 'woocommerce_checkout_create_order' )->once();
		Actions\expectAdded( 'woocommerce_store_api_checkout_order_processed' )->once();

		$order_language->register_hooks();
	}
}
