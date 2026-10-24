<?php
/** Activation routine tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Activator;
use Omnium\Translate\Core\Migrations;
use Omnium\Translate\Switcher\MenuIntegration;
use Omnium\Translate\Tests\TestCase;

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
}
