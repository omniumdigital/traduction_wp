<?php
/**
 * ScanJob tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\ScanJob;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeProvider;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentExtractor;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentRepository;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

final class ScanJobTest extends TestCase {

	private OptionStore $options;
	private SpyLogger $logger;
	private FakeSegmentExtractor $extractor;
	private FakeSegmentRepository $segments;
	private FakeTranslationRepository $translations;
	private RunLock $lock;
	private ScanJob $job;
	private WordCap $word_cap;

	/**
	 * Recorded batch schedules.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $batches = array();

	/**
	 * Fetch responses queue.
	 *
	 * @var list<array{code: int, body: string}|array{wp_error: string}>
	 */
	private array $fetches = array();

	/**
	 * Captured wp_safe_remote_get call.
	 *
	 * @var array{url: string, args: array<string, mixed>}|null
	 */
	private ?array $captured_fetch = null;

	/**
	 * Filters temporarily registered by the crawler.
	 *
	 * @var array<string, callable>
	 */
	private array $added_filters = array();

	protected function setUp(): void {
		parent::setUp();

		$this->options       = new OptionStore();
		$this->options->install();
		$this->options->data['omtr_settings'] = array(
			'source_lang'      => 'fr',
			'target_langs'     => array( 'en', 'es' ),
			'monthly_word_cap' => 100,
		);

		$this->logger        = new SpyLogger();
		$this->extractor     = new FakeSegmentExtractor();
		$this->segments      = new FakeSegmentRepository();
		$this->translations  = new FakeTranslationRepository();
		$this->translations->id_hash = array();
		$this->lock          = new RunLock();
		$this->word_cap      = new WordCap( new Settings() );
		$test                = $this;

		Functions\when( '__' )->returnArg();
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'wp_generate_uuid4' )->justReturn( 'run-uuid' );
		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ) => $thing instanceof \WP_Error
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( $response ) => is_array( $response ) && isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( $response ) => is_array( $response ) && isset( $response['body'] ) ? (string) $response['body'] : ''
		);
		Functions\when( 'add_filter' )->alias(
			function ( string $hook, callable $callback ) use ( $test ): bool {
				$test->added_filters[ $hook ] = $callback;
				return true;
			}
		);
		Functions\when( 'remove_filter' )->justReturn( true );

		$registry = new ProviderRegistry();
		$registry->add( new FakeProvider() );

		$queue = new ActionSchedulerQueue(
			new Settings(),
			$this->lock,
			$this->word_cap,
			new HourlyQuota( new Settings() ),
			$registry,
			$this->logger,
			$this->segments,
			$this->translations
		);

		Functions\when( 'as_schedule_single_action' )->alias(
			static function ( $timestamp, $hook, $args = array(), $group = '' ) use ( $test ): int {
				if ( ActionSchedulerQueue::ACTION_BATCH === $hook ) {
					$test->batches[] = $args;
				}
				return 0;
			}
		);
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array() );
		Functions\when( 'wp_safe_remote_get' )->alias(
			static function ( $url, $args = array() ) use ( $test ) {
				$test->captured_fetch = array(
					'url'  => $url,
					'args' => $args,
				);
				$next = array_shift( $test->fetches );
				if ( null === $next ) {
					return array( 'response' => array( 'code' => 200 ), 'body' => '<html></html>' );
				}
				if ( isset( $next['wp_error'] ) ) {
					return new \WP_Error( $next['wp_error'] );
				}
				return array(
					'response' => array( 'code' => $next['code'] ),
					'body'     => $next['body'],
				);
			}
		);

		$this->job = new ScanJob(
			new Settings(),
			$this->lock,
			$this->word_cap,
			$queue,
			$this->extractor,
			$this->segments,
			$this->translations,
			$this->logger
		);
	}

	private function hold_lock( string $run_id = 'run-uuid' ): void {
		$this->lock->acquire( $run_id );
	}

	public function test_scan_extracts_upserts_and_schedules_per_language(): void {
		$this->extractor->segments = array(
			'hash-a' => 'Bonjour',
			'hash-b' => 'le monde',
		);
		$this->hold_lock();

		$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );

		$this->assertCount( 2, $this->batches ); // en + es.
		$this->assertSame( array( 'en', 'es' ), array_column( $this->batches, 'lang' ) );
		$this->assertSame( 'fr', $this->segments->upserts[0]['source_lang'] );
		$this->assertContains( 'Bonjour', $this->segments->upserts[0]['segments'] );

		// Word cap charged once per scheduled batch (2 segments x 2 languages).
		$this->assertSame( 6, $this->word_cap->used() );
	}

	public function test_scan_never_reschedules_already_translated_segments(): void {
		$this->extractor->segments = array(
			'hash-a' => 'Bonjour',
			'hash-b' => 'le monde',
		);
		$this->translations->seed( 'hash-a', 'en', 'Hello' );
		$this->translations->seed( 'hash-b', 'en', 'the world' );
		$this->translations->seed( 'hash-a', 'es', 'Hola' );
		$this->translations->seed( 'hash-b', 'es', 'el mundo' );
		$this->hold_lock();

		$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );

		$this->assertCount( 0, $this->batches );
		$this->assertSame( 0, $this->word_cap->used() );
		$this->assertStringContainsString( 'nothing left to translate', $this->logger->joined() );
		// Lock released: nothing outstanding.
		$this->assertFalse( $this->lock->is_held() );
	}

	public function test_scan_translates_only_missing_language_pairs(): void {
		$this->extractor->segments = array( 'hash-a' => 'Bonjour' );
		$this->translations->seed( 'hash-a', 'en', 'Hello' ); // en done, es missing.
		$this->hold_lock();

		$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );

		$this->assertCount( 1, $this->batches );
		$this->assertSame( 'es', $this->batches[0]['lang'] );
	}

	public function test_scan_fetch_failure_releases_lock_and_throws(): void {
		$this->fetches = array( array( 'wp_error' => 'http_request_failed' ) );
		$this->hold_lock();

		try {
			$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );
			$this->fail( 'Expected RuntimeException.' );
		} catch ( \RuntimeException $e ) {
			$this->assertFalse( $this->lock->is_held() );
		}
	}

	public function test_scan_non_200_is_a_failure(): void {
		$this->fetches = array( array( 'code' => 302, 'body' => 'moved' ) );
		$this->hold_lock();

		$this->expectException( \RuntimeException::class );
		$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );
	}

	public function test_scan_stops_when_word_cap_is_reached(): void {
		$this->options->data['omtr_settings']['monthly_word_cap'] = 1;
		$this->extractor->segments = array( 'hash-a' => 'Deux mots' ); // 2 words > cap 1.
		$this->hold_lock();

		$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );

		$this->assertCount( 0, $this->batches );
		$this->assertStringContainsString( 'word cap', $this->logger->joined() );
	}

	public function test_scan_ignored_when_lock_belongs_to_another_run(): void {
		$this->hold_lock( 'another-run' );
		$this->extractor->segments = array( 'hash-a' => 'Bonjour' );

		$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );

		$this->assertCount( 0, $this->batches );
		$this->assertStringContainsString( 'no longer owns the lock', $this->logger->joined() );
	}

	public function test_scan_passes_anonymous_request_to_safe_remote_get(): void {
		$this->hold_lock();

		$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );

		$this->assertNotNull( $this->captured_fetch );
		$this->assertSame( 'https://example.test/page/', $this->captured_fetch['url'] );
		$this->assertTrue( $this->captured_fetch['args']['sslverify'] );
		$this->assertSame( array(), $this->captured_fetch['args']['cookies'] );
		$this->assertSame( 0, $this->captured_fetch['args']['redirection'] );
	}

	public function test_scan_temporarily_allows_only_the_exact_home_endpoint(): void {
		Functions\when( 'home_url' )->justReturn( 'http://example.test:8881' );
		$this->hold_lock();

		$this->job->handle( 'http://example.test:8881/page/', 7, 'run-uuid' );

		$host_filter = $this->added_filters['http_request_host_is_external'];
		$port_filter = $this->added_filters['http_allowed_safe_ports'];
		$this->assertTrue( $host_filter( false, 'example.test' ) );
		$this->assertFalse( $host_filter( false, 'evil.test' ) );
		$this->assertSame( array( 80, 443, 8881 ), $port_filter( array( 80, 443 ) ) );
	}

	public function test_scan_uses_extractor_with_source_language(): void {
		$this->extractor->segments = array();
		$this->hold_lock();

		$this->job->handle( 'https://example.test/page/', 7, 'run-uuid' );

		$this->assertSame( 'fr', $this->extractor->received_lang );
	}
}
