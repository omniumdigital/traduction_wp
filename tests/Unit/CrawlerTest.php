<?php
/**
 * Internal crawler tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\Crawler;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Store\PageRepository;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/** Verifies content-hash change detection. */
final class CrawlerTest extends TestCase {
	public function test_only_new_or_changed_html_is_queued(): void {
		( new OptionStore() )->install();
		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_post_types' )->justReturn( array( 'post' => 'post' ) );
		Functions\when( 'get_posts' )->justReturn( array( 7 ) );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/post/' );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_post_type_object' )->justReturn( (object) array( 'public' => true ) );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_filter' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '<html><p>Public</p></html>' );
		Functions\when( 'wp_safe_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ), 'body' => '<html><p>Public</p></html>' ) );
		Functions\when( 'current_time' )->justReturn( '2026-10-23 12:00:00' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'crawl-run' );
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array() );
		$scheduled = 0;
		Functions\when( 'as_schedule_single_action' )->alias(
			static function () use ( &$scheduled ): int {
				return ++$scheduled;
			}
		);

		$stored = null;
		$last_args = array();
		$wpdb = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturnCallback(
			static function ( string $sql, mixed ...$args ) use ( &$last_args ): string {
				$last_args = $args;
				return $sql;
			}
		);
		$wpdb->method( 'get_var' )->willReturnCallback(
			static function () use ( &$stored ): ?string {
				return $stored;
			}
		);
		$wpdb->method( 'query' )->willReturnCallback(
			static function () use ( &$stored, &$last_args ): int {
				$stored = isset( $last_args[3] ) ? (string) $last_args[3] : $stored;
				return 1;
			}
		);
		$settings = new Settings();
		$queue = new ActionSchedulerQueue( $settings, new RunLock(), new WordCap( $settings ), new HourlyQuota( $settings ), new ProviderRegistry(), new SpyLogger() );
		$crawler = new Crawler( new PublicContent(), new PageRepository( $wpdb ), $queue, new SpyLogger() );

		$first = $crawler->crawl();
		$second = $crawler->crawl();

		$this->assertSame( 1, $first['changed'] );
		$this->assertTrue( $first['queued'] );
		$this->assertSame( 1, $second['unchanged'] );
		$this->assertFalse( $second['queued'] );
		$this->assertSame( 1, $scheduled );
	}
}
