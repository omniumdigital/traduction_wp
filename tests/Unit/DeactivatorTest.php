<?php
/** Deactivation routine tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Deactivator;
use Omnium\Translate\Switcher\MenuIntegration;
use Omnium\Translate\Tests\TestCase;

/** @group security */
final class DeactivatorTest extends TestCase {

	/** The switcher menu item is drafted, never deleted, so the site stays intact. */
	public function test_drafts_switcher_menu_item_without_deleting_it(): void {
		Functions\when( 'as_unschedule_all_actions' )->justReturn( null );
		Functions\when( 'get_posts' )->alias(
			static function ( array $args ) {
				if ( 'nav_menu_item' !== $args['post_type'] || 'publish' !== $args['post_status'] ) {
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
		Functions\when( 'wp_delete_post' )->alias(
			static function () {
				self::fail( 'The menu item must not be deleted on deactivation.' );
			}
		);

		Deactivator::deactivate();

		$this->assertSame( array( array( 'ID' => 42, 'post_status' => 'draft' ) ), $updated );
	}

	/** No switcher menu item: nothing is updated. */
	public function test_does_nothing_without_a_switcher_menu_item(): void {
		Functions\when( 'as_unschedule_all_actions' )->justReturn( null );
		Functions\when( 'get_posts' )->justReturn( array() );
		$updated = false;
		Functions\when( 'wp_update_post' )->alias(
			static function () use ( &$updated ) {
				$updated = true;
			}
		);

		Deactivator::deactivate();

		$this->assertFalse( $updated );
	}
}
