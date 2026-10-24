<?php
/** Translation cache purge tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\TranslationCachePurger;
use Omnium\Translate\Tests\TestCase;

/** @group security */
final class TranslationCachePurgerTest extends TestCase {
	/** A known post emits an integration event after core invalidation. */
	public function test_purges_known_post_and_emits_event(): void {
		$purged = 0;
		$event  = array();
		Functions\when( 'clean_post_cache' )->alias( static function ( int $post_id ) use ( &$purged ): void { $purged = $post_id; } );
		Functions\when( 'has_action' )->justReturn( false );
		Functions\when( 'do_action' )->alias( static function ( string $hook, int $post_id, string $lang ) use ( &$event ): void { $event = array( $hook, $post_id, $lang ); } );
		TranslationCachePurger::purge( 42, 'en' );
		$this->assertSame( 42, $purged );
		$this->assertSame( array( 'omtr_translations_updated', 42, 'en' ), $event );
	}

	/** Unknown posts cannot trigger a broad cache purge. */
	public function test_ignores_unknown_post(): void {
		$purged = false;
		Functions\when( 'clean_post_cache' )->alias( static function () use ( &$purged ): void { $purged = true; } );
		TranslationCachePurger::purge( 0, 'en' );
		$this->assertFalse( $purged );
	}
}
