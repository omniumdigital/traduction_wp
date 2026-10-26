<?php
/**
 * Translation repository tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Store\TranslationRepository;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class TranslationRepositoryTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_kses' )->alias( static fn( string $text ): string => strip_tags( $text, '<strong><em><b><i><br><span><a>' ) );
		Functions\when( 'current_time' )->justReturn( '2026-10-21 12:00:00' );
		Functions\when( 'wp_cache_incr' )->justReturn( 1 );
		Functions\when( 'wp_cache_set' )->justReturn( true );
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );
	}

	public function test_find_many_uses_one_join_query(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->once() )->method( 'prepare' )->willReturn( 'prepared-select' );
		$wpdb->expects( $this->once() )->method( 'get_results' )->with( 'prepared-select', ARRAY_A )->willReturn(
			array( array( 'hash' => 'abc', 'text' => '<strong>Hello</strong>' ) )
		);

		$repository = new TranslationRepository( $wpdb );
		$this->assertSame( array( 'abc' => '<strong>Hello</strong>' ), $repository->find_many( array( 'abc', 'def' ), 'en' ) );
	}

	public function test_save_auto_sanitizes_and_protects_edited_and_locked_rows(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$prepared_sql = '';
		$prepared_args = array();
		$wpdb->expects( $this->once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $sql, mixed ...$args ) use ( &$prepared_sql, &$prepared_args ): string {
				$prepared_sql  = $sql;
				$prepared_args = $args;
				return 'prepared-upsert';
			}
		);
		$wpdb->expects( $this->once() )->method( 'query' )->with( 'prepared-upsert' );

		$repository = new TranslationRepository( $wpdb );
		$repository->save_auto( array( 7 => '<script>x</script><strong>Safe</strong>' ), 'en', 'claude', 'model' );

		$this->assertStringContainsString( 'status IN', $prepared_sql );
		$this->assertContains( 'edited', $prepared_args );
		$this->assertContains( 'locked', $prepared_args );
		$this->assertContains( 'x<strong>Safe</strong>', $prepared_args );
	}
}
