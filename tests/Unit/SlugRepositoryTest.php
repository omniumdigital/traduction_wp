<?php
/**
 * Translated slug repository tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Store\SlugRepository;
use Omnium\Translate\Tests\TestCase;

final class SlugRepositoryTest extends TestCase {

	public function test_reads_slug_with_a_prepared_query(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->once() )->method( 'prepare' )->with(
			$this->stringContains( 'post_id = %d AND target_lang = %s' ),
			'wp_omtr_slugs',
			42,
			'en'
		)->willReturn( 'prepared' );
		$wpdb->expects( $this->once() )->method( 'get_var' )->with( 'prepared' )->willReturn( 'translated-page' );

		$this->assertSame( 'translated-page', ( new SlugRepository( $wpdb ) )->get( 42, 'en' ) );
	}

	public function test_resolves_post_id_with_a_prepared_query(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->once() )->method( 'prepare' )->with(
			$this->stringContains( 'slug = %s AND target_lang = %s' ),
			'wp_omtr_slugs',
			'translated-page',
			'en'
		)->willReturn( 'prepared' );
		$wpdb->expects( $this->once() )->method( 'get_var' )->with( 'prepared' )->willReturn( '42' );

		$this->assertSame( 42, ( new SlugRepository( $wpdb ) )->find_post_id( 'translated-page', 'en' ) );
	}

	public function test_saves_slug_with_a_prepared_upsert(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->once() )->method( 'prepare' )->with(
			$this->stringContains( 'ON DUPLICATE KEY UPDATE' ),
			'wp_omtr_slugs',
			42,
			'en',
			'translated-page'
		)->willReturn( 'prepared' );
		$wpdb->expects( $this->once() )->method( 'query' )->with( 'prepared' );

		( new SlugRepository( $wpdb ) )->save( 42, 'en', 'translated-page' );
	}
}
