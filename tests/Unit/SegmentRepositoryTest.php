<?php
/**
 * Segment repository tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Store\SegmentRepository;
use Omnium\Translate\Tests\TestCase;

final class SegmentRepositoryTest extends TestCase {

	public function test_upsert_returns_ids_for_all_hashes(): void {
		Functions\when( 'current_time' )->justReturn( '2026-10-21 12:00:00' );
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->exactly( 3 ) )->method( 'prepare' )->willReturnOnConsecutiveCalls( 'insert-a', 'insert-b', 'select' );
		$wpdb->expects( $this->exactly( 2 ) )->method( 'query' );
		$wpdb->expects( $this->once() )->method( 'get_results' )->with( 'select', ARRAY_A )->willReturn(
			array(
				array( 'hash' => 'a', 'id' => '1' ),
				array( 'hash' => 'b', 'id' => '2' ),
			)
		);

		$repository = new SegmentRepository( $wpdb );
		$this->assertSame( array( 'a' => 1, 'b' => 2 ), $repository->upsert_many( array( 'a' => 'A', 'b' => 'B' ), 'fr' ) );
	}

	public function test_hashes_by_ids_uses_one_query_and_omits_unknown_ids(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->once() )->method( 'prepare' )->willReturn( 'prepared-select' );
		$wpdb->expects( $this->once() )->method( 'get_results' )->with( 'prepared-select', ARRAY_A )->willReturn(
			array(
				array( 'id' => '3', 'hash' => 'hash-three' ),
				array( 'id' => '7', 'hash' => 'hash-seven' ),
			)
		);

		$repository = new SegmentRepository( $wpdb );
		$this->assertSame( array( 3 => 'hash-three', 7 => 'hash-seven' ), $repository->hashes_by_ids( array( 3, 7, 99 ) ) );
	}

	public function test_hashes_by_ids_skips_database_for_empty_list(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->never() )->method( 'prepare' );

		$repository = new SegmentRepository( $wpdb );
		$this->assertSame( array(), $repository->hashes_by_ids( array() ) );
	}
}
