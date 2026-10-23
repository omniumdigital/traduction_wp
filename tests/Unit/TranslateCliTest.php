<?php
/**
 * TranslateCli tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\Crawler;
use Omnium\Translate\Queue\Estimator;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\QueueState;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\TranslateCli;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeProvider;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentExtractor;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentRepository;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;
use Omnium\Translate\Store\PageRepository;

final class TranslateCliTest extends TestCase {

	private TranslateCli $command;

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::reset();
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		$options = new OptionStore();
		$options->install();
		$options->data[ Options::SETTINGS ] = array(
			'source_lang'  => 'fr',
			'target_langs' => array( 'en' ),
			'provider'     => 'claude',
		);

		Functions\when( '__' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'cli-run' );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_post_type_object' )->justReturn( (object) array( 'public' => true ) );
		Functions\when( 'get_post_types' )->justReturn( array( 'post' => 'post' ) );
		Functions\when( 'get_posts' )->justReturn( array( 42 ) );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'as_schedule_single_action' )->justReturn( 1 );
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array() );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_filter' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '<p>Bonjour</p>' );
		Functions\when( 'wp_safe_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ), 'body' => '<p>Bonjour</p>' ) );
		$settings = new Settings();
		$lock     = new RunLock();
		$word_cap = new WordCap( $settings );
		$hourly   = new HourlyQuota( $settings );
		$registry = new ProviderRegistry();
		$registry->add( new FakeProvider() );
		$queue = new ActionSchedulerQueue(
			$settings,
			$lock,
			$word_cap,
			$hourly,
			$registry,
			new SpyLogger(),
			new FakeSegmentRepository(),
			new FakeTranslationRepository()
		);

		$content         = new PublicContent();
		$translations    = new FakeTranslationRepository();
		$estimator       = new Estimator( $settings, $content, new FakeSegmentExtractor(), $translations, $word_cap, $registry, new Pricing() );
		$crawler         = new Crawler( $content, new PageRepository( new \wpdb() ), $queue, new FakeSegmentExtractor(), $settings, new SpyLogger() );
		$this->command   = new TranslateCli( $queue, $settings, $lock, $word_cap, $hourly, $estimator, $content, new QueueState(), $crawler );
	}

	public function test_registers_only_the_omtr_command_in_cli_context(): void {
		$this->command->register();
		$this->assertSame( 'omtr', \WP_CLI::$calls['add_command'][0][0] );
		$this->assertSame( $this->command, \WP_CLI::$calls['add_command'][0][1] );
	}

	public function test_translate_post_id_resolves_permalink_and_enqueues(): void {
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/page/?preview=no' );

		$this->command->translate( array( '42' ), array() );

		$this->assertArrayHasKey( 'success', \WP_CLI::$calls );
		$this->assertArrayNotHasKey( 'error', \WP_CLI::$calls );
	}

	public function test_translate_foreign_url_is_refused(): void {
		$this->command->translate( array( 'https://evil.example/page/' ), array() );

		$this->assertArrayHasKey( 'error', \WP_CLI::$calls );
		$this->assertArrayNotHasKey( 'success', \WP_CLI::$calls );
	}

	public function test_translate_all_displays_estimate_and_asks_for_confirmation(): void {
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/page/' );
		Functions\when( 'WP_CLI\\Utils\\format_items' )->justReturn( null );

		$this->command->translate( array(), array( 'all' => true ) );

		$this->assertArrayHasKey( 'confirm', \WP_CLI::$calls );
		$this->assertArrayHasKey( 'success', \WP_CLI::$calls );
	}

	public function test_status_formats_provider_queue_and_limits(): void {
		Functions\expect( 'WP_CLI\\Utils\\format_items' )
			->once()
			->with( 'table', \Mockery::type( 'array' ), array( 'key', 'value' ) );

		$this->command->status( array(), array() );

		$this->assertArrayHasKey( 'line', \WP_CLI::$calls );
	}
}
