<?php
/**
 * Guarded on-the-fly trigger tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\OnTheFlyTrigger;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\QueueState;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeProvider;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentExtractor;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentRepository;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/** @group security */
final class OnTheFlyTriggerSecurityTest extends TestCase {
	private FakeSegmentRepository $segments;
	private FakeSegmentExtractor $extractor;
	private OnTheFlyTrigger $trigger;
	private array $scheduled = array();
	private array $transients = array();

	protected function setUp(): void {
		parent::setUp();
		$options = new OptionStore();
		$options->install();
		$options->data[ Options::SETTINGS ] = array(
			'source_lang'      => 'fr',
			'on_the_fly'       => true,
			'hourly_quota'     => 10,
			'monthly_word_cap' => 100,
		);
		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'get_queried_object_id' )->justReturn( 7 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/page/?utm=ignored' );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_post_type' )->justReturn( 'post' );
		Functions\when( 'get_post_type_object' )->justReturn( (object) array( 'public' => true ) );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'sanitize_url' )->returnArg();
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->alias(
			function ( string $key, mixed $value, int $expiration ): bool {
				$this->transients[] = compact( 'key', 'value', 'expiration' );
				return true;
			}
		);
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array() );
		Functions\when( 'as_schedule_single_action' )->alias(
			function ( int $timestamp, string $hook, array $args, string $group ): int {
				unset( $timestamp, $group );
				$this->scheduled[] = array( 'hook' => $hook, 'args' => $args );
				return 1;
			}
		);
		$this->segments           = new FakeSegmentRepository();
		$this->extractor          = new FakeSegmentExtractor();
		$this->extractor->segments = array( 'hash-a' => 'Bonjour monde' );
		$translations            = new FakeTranslationRepository();
		$registry                = new ProviderRegistry();
		$registry->add( new FakeProvider() );
		$settings = new Settings();
		$word_cap = new WordCap( $settings );
		$hourly   = new HourlyQuota( $settings );
		$queue    = new ActionSchedulerQueue( $settings, new RunLock(), $word_cap, $hourly, $registry, new SpyLogger(), $this->segments, $translations, new QueueState() );
		$lang     = new CurrentLanguage( 'fr' );
		$lang->set( 'en' );
		$this->trigger = new OnTheFlyTrigger( $settings, new RequestGuard(), $lang, new PublicContent(), $this->extractor, $this->segments, $translations, $queue, $word_cap, $hourly, new QueueState() );
	}

	public function test_anonymous_known_page_queues_at_shutdown_and_ignores_query_string(): void {
		$this->trigger->capture( '<p>Bonjour monde</p>' );
		$this->assertCount( 0, $this->segments->upserts );
		$this->trigger->flush();
		$this->assertCount( 1, $this->segments->upserts );
		$this->assertSame( ActionSchedulerQueue::ACTION_BATCH, $this->scheduled[0]['hook'] );
		$this->assertSame( 'omtr_otf_' . hash( 'sha256', 'https://example.test/page/' ), $this->transients[0]['key'] );
	}

	public function test_logged_in_user_never_queues_on_the_fly(): void {
		Functions\when( 'is_user_logged_in' )->justReturn( true );
		$this->trigger->capture( '<p>Private toolbar</p>' );
		$this->trigger->flush();
		$this->assertCount( 0, $this->scheduled );
	}

	public function test_unknown_url_is_refused(): void {
		Functions\when( 'get_queried_object_id' )->justReturn( 0 );
		$this->trigger->capture( '<p>Unknown</p>' );
		$this->trigger->flush();
		$this->assertCount( 0, $this->scheduled );
	}
}
