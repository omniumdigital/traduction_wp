<?php
/** Activation routine tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Activator;
use Omnium\Translate\Core\Migrations;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Switcher\MenuIntegration;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

/** @group security */
final class ActivatorTest extends TestCase {

	/** The switcher menu item drafted by Deactivator is republished on reactivation. */
	public function test_republishes_switcher_menu_item_drafted_on_deactivation(): void {
		Functions\when( 'get_option' )->justReturn( Migrations::DB_VERSION );
		Functions\when( 'get_role' )->justReturn( false );
		Functions\when( 'get_posts' )->alias(
			static function ( array $args ) {
				if ( 'nav_menu_item' !== $args['post_type'] || 'draft' !== $args['post_status'] ) {
					return array();
				}
				if ( MenuIntegration::ITEM_URL !== $args['meta_value'] ) {
					return array();
				}
				return array( 42 );
			}
		);
		$updated = array();
		Functions\when( 'wp_update_post' )->alias(
			static function ( array $post ) use ( &$updated ) {
				$updated[] = $post;
			}
		);

		Activator::activate();

		$this->assertSame( array( array( 'ID' => 42, 'post_status' => 'publish' ) ), $updated );
	}

	/** No drafted switcher menu item: nothing is updated. */
	public function test_does_nothing_without_a_drafted_switcher_menu_item(): void {
		Functions\when( 'get_option' )->justReturn( Migrations::DB_VERSION );
		Functions\when( 'get_role' )->justReturn( false );
		Functions\when( 'get_posts' )->justReturn( array() );
		$updated = false;
		Functions\when( 'wp_update_post' )->alias(
			static function () use ( &$updated ) {
				$updated = true;
			}
		);

		Activator::activate();

		$this->assertFalse( $updated );
	}

	/**
	 * Activation grants the management capability and records the one-shot
	 * flag so a later boot never re-grants over a deliberate removal.
	 */
	public function test_activation_grants_the_capability_and_records_the_flag(): void {
		$role = new class {
			/**
			 * Added capabilities.
			 *
			 * @var list<string>
			 */
			public array $caps = array();

			/**
			 * Record a capability addition.
			 *
			 * @param string $cap Capability.
			 */
			public function add_cap( string $cap ): void {
				$this->caps[] = $cap;
			}
		};
		Functions\when( 'get_role' )->justReturn( $role );
		Functions\when( 'get_posts' )->justReturn( array() );
		$options                     = new OptionStore();
		$options->data['omtr_db_version'] = Migrations::DB_VERSION;
		$options->install();

		Activator::activate();

		$this->assertSame( array( Capabilities::MANAGE ), $role->caps );
		$this->assertSame( 1, $options->data[ Options::CAPS_GRANTED ] );
	}
}
