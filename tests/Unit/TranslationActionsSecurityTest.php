<?php
/**
 * Admin translation action security tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Admin\TranslationActions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/** @group security */
final class TranslationActionsSecurityTest extends TestCase {
	private TranslationActions $actions;

	protected function setUp(): void {
		parent::setUp();
		( new OptionStore() )->install();
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/post/' );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_post_type_object' )->justReturn( (object) array( 'public' => true ) );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'absint' )->alias( static fn( mixed $value ): int => abs( (int) $value ) );
		$settings = new Settings();
		$queue = new ActionSchedulerQueue( $settings, new RunLock(), new WordCap( $settings ), new HourlyQuota( $settings ), new ProviderRegistry(), new SpyLogger() );
		$this->actions = new TranslationActions( $queue, new PublicContent() );
	}

	protected function tearDown(): void {
		$_GET = array();
		parent::tearDown();
	}

	public function test_visitor_never_gets_row_action(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$post = new \WP_Post();
		$post->ID = 7;
		$this->assertSame( array(), $this->actions->row_action( array(), $post ) );
	}

	public function test_missing_nonce_is_refused_before_enqueue(): void {
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\expect( 'check_admin_referer' )->once()->with( 'omtr_translate_0' )->andThrow( new \RuntimeException( 'invalid nonce' ) );
		$this->expectException( \RuntimeException::class );
		$this->actions->handle();
	}
}
