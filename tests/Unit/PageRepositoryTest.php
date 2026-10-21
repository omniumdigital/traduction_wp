<?php
/**
 * Page repository tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

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
}
