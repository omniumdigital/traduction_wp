<?php
/**
 * Page repository tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Store\PageRepository;
use Omnium\Translate\Tests\TestCase;

final class PageRepositoryTest extends TestCase {

	public function test_reads_content_hash_using_prepared_url_hash(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->once() )->method( 'prepare' )->with(
			$this->stringContains( 'url_hash = %s' ),
			'wp_omtr_pages',
			hash( 'sha256', 'https://example.test/page' )
		)->willReturn( 'prepared' );
		$wpdb->expects( $this->once() )->method( 'get_var' )->with( 'prepared' )->willReturn( 'content-hash' );

		$this->assertSame( 'content-hash', ( new PageRepository( $wpdb ) )->content_hash( 'https://example.test/page' ) );
	}

	public function test_replaces_page_segment_inventory_and_invalidates_coverage(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$prepared = array();
		$wpdb->method( 'prepare' )->willReturnCallback(
			static function ( string $sql, mixed ...$args ) use ( &$prepared ): string {
				$prepared[] = array( $sql, $args );
				return 'prepared-' . count( $prepared );
			}
		);
		$wpdb->expects( $this->once() )->method( 'get_var' )->willReturn( '9' );
		$wpdb->expects( $this->exactly( 3 ) )->method( 'query' );
		Functions\when( 'absint' )->alias( static fn( mixed $value ): int => abs( (int) $value ) );
		Functions\when( 'get_option' )->justReturn( 4 );
		Functions\expect( 'update_option' )->once()->with( Options::COVERAGE_VERSION, 5, false )->andReturn( true );

		( new PageRepository( $wpdb ) )->replace_segments( 7, 'https://example.test/page', array( 2, 3, 3 ) );

		$this->assertStringContainsString( 'DELETE FROM %i', $prepared[1][0] );
		$this->assertStringContainsString( 'INSERT IGNORE INTO %i', $prepared[2][0] );
		$this->assertStringContainsString( 'INSERT IGNORE INTO %i', $prepared[3][0] );
	}
}
