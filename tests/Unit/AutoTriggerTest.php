<?php
/**
 * Automatic publication trigger tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\AutoTrigger;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/** Ensures save_post only schedules deferred work. */
final class AutoTriggerTest extends TestCase {
	/** Automatic mode schedules an action without enqueuing a provider batch. */
	public function test_published_post_is_deferred(): void {
		$options = new OptionStore();
		$options->install();
		$options->data[ Options::SETTINGS ] = array( 'auto_translate' => true );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/post/' );
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_post_type_object' )->justReturn( (object) array( 'public' => true ) );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'wp_is_post_revision' )->justReturn( false );
		Functions\when( 'wp_is_post_autosave' )->justReturn( false );
		Functions\when( 'as_has_scheduled_action' )->justReturn( false );
		Functions\expect( 'as_schedule_single_action' )
			->once()
			->with( \Mockery::type( 'int' ), AutoTrigger::ACTION, array( 'post_id' => 7 ), ActionSchedulerQueue::GROUP );

		$settings = new Settings();
		$queue = new ActionSchedulerQueue( $settings, new RunLock(), new WordCap( $settings ), new HourlyQuota( $settings ), new ProviderRegistry(), new SpyLogger() );
		$post = new \WP_Post();
		$post->ID = 7;
		$post->post_status = 'publish';
		( new AutoTrigger( $settings, new PublicContent(), $queue ) )->on_save( 7, $post, true );
		$this->assertTrue( true );
	}
}
