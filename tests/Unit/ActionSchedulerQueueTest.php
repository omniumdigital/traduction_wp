<?php
/**
 * ActionSchedulerQueue tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\QueueState;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Security\Crypto;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeProvider;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentRepository;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/**
 * URL acceptance is a security boundary (SSRF / cost attack guard).
 *
 * @group security
 */
final class ActionSchedulerQueueTest extends TestCase {

	private OptionStore $options;
	private SpyLogger $logger;
	private FakeSegmentRepository $segments;
	private FakeTranslationRepository $translations;
	private ActionSchedulerQueue $queue;
	private QueueState $state;
	private ApiKeyStore $keys;
	private RunLock $lock;
	private int $sequence = 0;

	/**
	 * Recorded as_schedule_single_action calls.
	 *
	 * @var list<array{0: int, 1: string, 2: array<string, mixed>, 3: string}>
	 */
	private array $scheduled = array();

	protected function setUp(): void {
		parent::setUp();

		$this->options       = new OptionStore();
		$this->options->install();
		$this->logger        = new SpyLogger();
		$this->segments      = new FakeSegmentRepository();
		$this->translations  = new FakeTranslationRepository();
		$this->translations->id_hash = array();
		$this->state         = new QueueState();

		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_generate_uuid4' )->alias(
			function () {
				return 'run-uuid-' . ++$this->sequence;
			}
		);
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array() );

		$queue    = $this;
		$registry = new ProviderRegistry();
		$registry->add( new FakeProvider() );

		// The active provider (claude) starts with a stored key, like a
		// configured site: refusal tests remove it explicitly.
		$this->keys = new ApiKeyStore( new Crypto() );
		$this->keys->set( 'claude', 'unit-test-key-not-a-secret' );

		$this->queue = new ActionSchedulerQueue(
			new Settings(),
			$this->lock = new RunLock(),
			new WordCap( new Settings() ),
			new HourlyQuota( new Settings() ),
			$registry,
			$this->logger,
			$this->segments,
			$this->translations,
			$this->state,
			$this->keys
		);

		Functions\when( 'as_schedule_single_action' )->alias(
			static function ( $timestamp, $hook, $args = array(), $group = '' ) use ( $queue ): int {
				$queue->scheduled[] = array( $timestamp, $hook, $args, $group );
				return count( $queue->scheduled );
			}
		);
	}

	public function test_normalize_url_accepts_site_url_and_drops_query(): void {
		$this->assertSame(
			'https://example.test/ma-page/',
			$this->queue->normalize_url( 'https://example.test/ma-page/?utm=x&foo=bar#section' )
		);
	}

	public function test_normalize_url_refuses_different_scheme_or_port(): void {
		$this->assertNull( $this->queue->normalize_url( 'http://example.test/page/' ) );
		$this->assertNull( $this->queue->normalize_url( 'https://example.test:8443/page/' ) );
	}

	public function test_normalize_url_refuses_foreign_host(): void {
		$this->assertNull( $this->queue->normalize_url( 'https://evil.example.com/page/' ) );
		$this->assertNull( $this->queue->normalize_url( 'https://sub.example.test/page/' ) );
	}

	public function test_normalize_url_honours_home_subdirectory(): void {
		Functions\when( 'home_url' )->justReturn( 'https://example.test/wordpress' );

		$this->assertSame(
			'https://example.test/wordpress/page/',
			$this->queue->normalize_url( 'https://example.test/wordpress/page/?x=1' )
		);
		$this->assertNull( $this->queue->normalize_url( 'https://example.test/other/' ) );
		$this->assertNull( $this->queue->normalize_url( 'https://example.test/wordpress/wp-admin/' ) );
	}

	public function test_normalize_url_refuses_non_http_schemes(): void {
		$this->assertNull( $this->queue->normalize_url( 'file:///etc/passwd' ) );
		$this->assertNull( $this->queue->normalize_url( 'ftp://example.test/file' ) );
	}

	public function test_normalize_url_refuses_non_public_paths(): void {
		$this->assertNull( $this->queue->normalize_url( 'https://example.test/wp-admin/admin.php' ) );
		$this->assertNull( $this->queue->normalize_url( 'https://example.test/wp-login.php' ) );
		$this->assertNull( $this->queue->normalize_url( 'https://example.test/checkout/' ) );
		$this->assertNull( $this->queue->normalize_url( 'https://example.test/cart?add-to-cart=1' ) );
	}

	public function test_enqueue_url_schedules_scan_and_takes_lock(): void {
		$this->assertTrue( $this->queue->enqueue_url( 'https://example.test/page/?utm=x', 42 ) );

		$this->assertCount( 1, $this->scheduled );
		list( , $hook, $args, $group ) = $this->scheduled[0];
		$this->assertSame( ActionSchedulerQueue::ACTION_SCAN, $hook );
		$this->assertSame( 'omtr', $group );
		$this->assertSame( 'https://example.test/page/', $args['url'] );
		$this->assertSame( 42, $args['post_id'] );
		$this->assertSame( 'run-uuid-1', $args['run_id'] );

		$lock = new RunLock();
		$this->assertSame( 'run-uuid-1', $lock->current_run() );
	}

	public function test_enqueue_url_refuses_foreign_url_without_scheduling(): void {
		$this->assertFalse( $this->queue->enqueue_url( 'https://evil.example.com/x' ) );
		$this->assertCount( 0, $this->scheduled );
		$this->assertNotSame( '', $this->queue->last_reason() );
	}

	public function test_paused_queue_refuses_every_enqueue(): void {
		$this->state->pause();
		$this->segments->seed( 'hash-a', 'Hello' );

		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/page/', 42 ) );
		$this->queue->enqueue_segments( array( $this->segments->ids['hash-a'] ), 'en' );
		$this->assertCount( 0, $this->scheduled );
		$this->assertStringContainsString( 'paused', $this->queue->last_reason() );
	}

	public function test_enqueue_url_refuses_draft_or_password_protected_post(): void {
		Functions\when( 'get_post_status' )->justReturn( 'draft' );
		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/draft/', 8 ) );

		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'post_password_required' )->justReturn( true );
		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/protected/', 9 ) );
		$this->assertCount( 0, $this->scheduled );
	}

	public function test_enqueue_url_refuses_custom_woocommerce_private_page(): void {
		$this->options->data['woocommerce_myaccount_page_id'] = 12;
		Functions\when( 'url_to_postid' )->justReturn( 12 );

		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/espace-client/' ) );
		$this->assertCount( 0, $this->scheduled );
	}

	public function test_enqueue_url_refused_while_run_lock_is_held(): void {
		$lock = new RunLock();
		$lock->acquire( 'other-run' );

		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/page/' ) );
		$this->assertCount( 0, $this->scheduled );
	}

	public function test_enqueue_url_refused_when_word_cap_is_exhausted(): void {
		$this->options->data['omtr_settings'] = array( 'monthly_word_cap' => 10 );
		( new WordCap( new Settings() ) )->charge( 10 );

		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/page/' ) );
		$this->assertCount( 0, $this->scheduled );
	}

	public function test_enqueue_url_refused_when_active_provider_has_no_key(): void {
		unset( $this->options->data[ Options::API_KEYS ] );

		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/page/', 42 ) );
		$this->assertCount( 0, $this->scheduled );

		$this->assertStringContainsString( 'No API key is configured', $this->queue->last_reason() );
		$this->assertStringContainsString( 'Fake Provider', $this->queue->last_reason() );
		$this->assertStringContainsString( 'has no API key', $this->logger->joined() );
		$this->assertStringNotContainsString( 'unit-test-key-not-a-secret', $this->logger->joined() );
	}

	public function test_enqueue_segments_refused_when_active_provider_has_no_key(): void {
		unset( $this->options->data[ Options::API_KEYS ] );
		$this->segments->seed( 'hash-a', 'Hello' );
		$this->translations->id_hash = array_flip( $this->segments->ids );

		$this->queue->enqueue_segments( array( $this->segments->ids['hash-a'] ), 'en' );

		$this->assertCount( 0, $this->scheduled );
		$this->assertStringContainsString( 'has no API key', $this->logger->joined() );
	}

	public function test_enqueue_refused_when_no_provider_is_configured(): void {
		$this->options->data[ Options::SETTINGS ] = array( 'provider' => 'unknown' );

		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/page/' ) );
		$this->assertCount( 0, $this->scheduled );
		$this->assertStringContainsString( 'No provider is configured.', $this->queue->last_reason() );
	}

	public function test_enqueue_url_with_key_for_active_provider_still_schedules(): void {
		$this->assertTrue( $this->queue->enqueue_url( 'https://example.test/page/', 42 ) );
		$this->assertCount( 1, $this->scheduled );
	}

	/** A lock left behind by a fatal (nothing pending any more) is released instead of blocking new runs. */
	public function test_dead_run_lock_is_released_when_nothing_is_pending(): void {
		// First run acquires the lock and finishes cleanly.
		$this->assertTrue( $this->queue->enqueue_url( 'https://example.test/page/', 42 ) );
		$run = $this->options->data[ Options::TRANSLATION_LOCK ]['run'];
		$this->queue->settle_run( $run, false );

		// Simulate the aftermath of a fatal: lock re-acquired, queue empty.
		$this->lock->acquire( 'dead-run' );
		$this->scheduled = array();
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array() );

		$this->assertTrue( $this->queue->enqueue_url( 'https://example.test/page/', 42 ) );
		$this->assertStringContainsString( 'Released a dead translation run lock', $this->logger->joined() );

		// A live run is still protected: with pending work the refusal stands.
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array( 1 ) );
		$this->assertFalse( $this->queue->enqueue_url( 'https://example.test/third/', 44 ) );
		$this->assertStringContainsString( 'already in progress', $this->queue->last_reason() );
	}

	public function test_enqueue_segments_resolves_ids_and_skips_translated(): void {
		$this->segments->seed( 'hash-a', 'Hello' );
		$this->segments->seed( 'hash-b', 'World' );
		$this->segments->seed( 'hash-c', 'Already' );
		$id_hash = array_flip( $this->segments->ids );
		$this->translations->id_hash = $id_hash;
		$this->translations->seed( 'hash-c', 'en', 'Déjà traduit' );

		$this->queue->enqueue_segments( array_values( $this->segments->ids ), 'en' );

		$this->assertCount( 1, $this->scheduled );
		list( , $hook, $args, $group ) = $this->scheduled[0];
		$this->assertSame( ActionSchedulerQueue::ACTION_BATCH, $hook );
		$this->assertSame( 'omtr', $group );
		$this->assertSame( 'en', $args['lang'] );
		$this->assertSame( 1, $args['attempt'] );

		$sent_hashes = array_values( $args['segments'] );
		$this->assertContains( 'hash-a', $sent_hashes );
		$this->assertContains( 'hash-b', $sent_hashes );
		$this->assertNotContains( 'hash-c', $sent_hashes );
	}

	public function test_enqueue_segments_unknown_ids_are_skipped(): void {
		$this->queue->enqueue_segments( array( 999, 1000 ), 'en' );
		$this->assertCount( 0, $this->scheduled );
		$this->assertStringContainsString( 'unknown segment ids', $this->logger->joined() );
	}

	public function test_enqueue_segments_applies_word_cap(): void {
		$this->options->data['omtr_settings'] = array( 'monthly_word_cap' => 1 );
		$this->segments->seed( 'hash-a', 'Hello world' );
		$this->translations->id_hash = array_flip( $this->segments->ids );

		$this->queue->enqueue_segments( array( $this->segments->ids['hash-a'] ), 'en' );

		$this->assertCount( 0, $this->scheduled );
		$this->assertStringContainsString( 'word cap', $this->logger->joined() );
	}

	public function test_enqueue_segments_applies_hourly_quota(): void {
		$this->options->data['omtr_settings'] = array( 'hourly_quota' => 1 );
		$this->segments->seed( 'hash-a', 'Hello' );
		$this->segments->seed( 'hash-b', 'World' );
		$this->translations->id_hash = array_flip( $this->segments->ids );

		$this->queue->enqueue_segments( array_values( $this->segments->ids ), 'en' );

		$this->assertCount( 0, $this->scheduled );
		$this->assertStringContainsString( 'hourly quota', $this->logger->joined() );
	}

	public function test_backoff_schedule_is_30s_2m_10m_1h(): void {
		$this->assertSame( 30, $this->queue->backoff_delay( 1 ) );
		$this->assertSame( 120, $this->queue->backoff_delay( 2 ) );
		$this->assertSame( 600, $this->queue->backoff_delay( 3 ) );
		$this->assertSame( 3600, $this->queue->backoff_delay( 4 ) );
		$this->assertSame( 3600, $this->queue->backoff_delay( 5 ) );
	}
}
