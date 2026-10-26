<?php
/**
 * Failed-segment retry and dashboard follow-up tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Admin\Administration;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\QueueState;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Security\Crypto;
use Omnium\Translate\Seo\TranslationCoverage;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeProvider;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentRepository;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/**
 * The retry route answers the aftermath of an incident (segments marked
 * failed after a misconfigured provider): it re-queues exactly the failed
 * pairs, per language, and refuses to run without a usable key.
 *
 * @group security
 */
final class RetryFailedTest extends TestCase {

	/**
	 * Scheduled batches, recorded by the Action Scheduler double.
	 *
	 * @var list<array{lang: string, segments: array<int, string>}>
	 */
	private array $scheduled = array();

	/**
	 * Set WordPress doubles.
	 */
	protected function setUp(): void {
		parent::setUp();
		( new OptionStore() )->install();
		Functions\when( '__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'is_wp_error' )->alias( static fn( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'as_get_scheduled_actions' )->justReturn( array() );
		Functions\when( 'as_schedule_single_action' )->alias(
			function ( int $timestamp, string $hook, array $args = array(), string $group = '' ): int {
				if ( 'omtr_translate_batch' === $hook ) {
					$this->scheduled[] = array(
						'lang'     => (string) ( $args['lang'] ?? '' ),
						'segments' => is_array( $args['segments'] ?? null ) ? $args['segments'] : array(),
					);
				}
				return 0;
			}
		);
	}

	/** Remove the database fixture. */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/** Without a key for the active provider the retry is refused with the same 409 reason as every paid enqueue. */
	public function test_retry_refused_without_key(): void {
		$api = $this->api( $this->wpdb( array() ), null );

		$response = $api->retry_failed();

		$this->assertInstanceOf( \WP_Error::class, $response );
		$this->assertSame( 'omtr_enqueue_refused', $response->get_error_code() );
		$this->assertSame( 409, $response->get_error_data()['status'] );
		$this->assertStringContainsString( 'No API key is configured', $response->get_error_message() );
		$this->assertSame( array(), $this->scheduled );
	}

	/** Failed pairs go back to the queue per language; nothing translated is re-sent. */
	public function test_retry_requeues_failed_segments_per_language(): void {
		$keys = new ApiKeyStore( new Crypto() );
		$keys->set( 'claude', 'unit-test-key-not-a-secret' );
		$segments = new FakeSegmentRepository();
		$segments->seed( 'hash-en', 'Bonjour' );
		$segments->seed( 'hash-es', 'Bienvenue' );
		$wpdb = $this->wpdb( array( array( '1' ), array( '2' ) ) );

		$response = $this->api( $wpdb, $keys, $segments, new FakeTranslationRepository() )->retry_failed();

		$this->assertSame( 2, $response->data['retried'] );
		$this->assertCount( 2, $this->scheduled );
		$this->assertSame( 'en', $this->scheduled[0]['lang'] );
		$this->assertSame( array( 1 => 'hash-en' ), $this->scheduled[0]['segments'] );
		$this->assertSame( 'es', $this->scheduled[1]['lang'] );
		$this->assertSame( array( 2 => 'hash-es' ), $this->scheduled[1]['segments'] );
	}

	/** Languages without failures are skipped without touching the queue. */
	public function test_retry_reports_zero_when_nothing_failed(): void {
		$keys = new ApiKeyStore( new Crypto() );
		$keys->set( 'claude', 'unit-test-key-not-a-secret' );
		$wpdb = $this->wpdb( array( array(), array() ) );

		$response = $this->api( $wpdb, $keys )->retry_failed();

		$this->assertSame( 0, $response->data['retried'] );
		$this->assertSame( array(), $this->scheduled );
	}

	/** The dashboard carries the follow-up payload: batches, last activity and the latest error reason. */
	public function test_dashboard_exposes_follow_up_payload(): void {
		$keys = new ApiKeyStore( new Crypto() );
		$keys->set( 'claude', 'unit-test-key-not-a-secret' );
		$coverage_en = array( array( 'total' => '10', 'translated' => '8' ) );
		$coverage_es = array( array( 'total' => '10', 'translated' => '5' ) );
		$log         = array(
			array(
				'level'      => 'info',
				'context'    => 'queuebatch',
				'message'    => 'Batch done.',
				'created_at' => '2026-10-26 19:00:02',
			),
			array(
				'level'      => 'error',
				'context'    => 'queue:batch',
				'message'    => 'Batch failed after 1 attempt(s) (http=0).',
				'created_at' => '2026-10-26 19:00:01',
			),
		);
		$statuses    = array( array( 'status' => 'auto', 'count' => '13' ), array( 'status' => 'failed', 'count' => '2' ) );
		$wpdb        = $this->wpdb( array() );
		$wpdb->method( 'get_results' )->willReturnOnConsecutiveCalls( $coverage_en, $coverage_es, $statuses, $log );
		$wpdb->method( 'get_var' )->willReturn( '15' );

		$data = $this->api( $wpdb, $keys )->dashboard()->data;

		$this->assertSame( 80.0, $data['languages'][0]['coverage'] );
		$this->assertSame( 50.0, $data['languages'][1]['coverage'] );
		$this->assertSame( 15, $data['segments']['total'] );
		$this->assertSame( 2, $data['segments']['failed'] );
		$this->assertSame( 2, $data['failures']['count'] );
		$this->assertSame( 'Batch failed after 1 attempt(s) (http=0).', $data['failures']['reason']['message'] );
		$this->assertSame( '2026-10-26 19:00:02', $data['queue']['last_activity'] );
		$this->assertIsInt( $data['queue']['batches'] );
	}

	/**
	 * Build the API under test.
	 *
	 * @param \wpdb                        $wpdb        Scripted database.
	 * @param ApiKeyStore|null             $keys        Key store, null for "no key".
	 * @param FakeSegmentRepository|null   $segments    Segment double.
	 * @param FakeTranslationRepository|null $translations Translation double.
	 */
	private function api( \wpdb $wpdb, ?ApiKeyStore $keys, ?FakeSegmentRepository $segments = null, ?FakeTranslationRepository $translations = null ): Administration {
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test database fixture.
		$settings  = new Settings();
		$settings->update( array( 'target_langs' => array( 'en', 'es' ) ) );
		$providers = new ProviderRegistry();
		$providers->add( new FakeProvider() );
		$keys   = $keys ?? new ApiKeyStore( new Crypto() );
		$logger = new SpyLogger();
		$queue  = new ActionSchedulerQueue(
			$settings,
			new RunLock(),
			new WordCap( $settings ),
			new HourlyQuota( $settings ),
			$providers,
			$logger,
			$segments,
			$translations,
			new QueueState(),
			$keys
		);
		return new Administration(
			$settings,
			$providers,
			new Pricing(),
			$keys,
			new GlossaryRepository( $wpdb ),
			$queue,
			new QueueState(),
			new RunLock(),
			new WordCap( $settings ),
			new PublicContent(),
			new TranslationCoverage( $wpdb, $settings ),
			$wpdb
		);
	}

	/**
	 * Scripted wpdb double: get_col plays the given sequences, everything
	 * else returns neutral values unless overridden by the test.
	 *
	 * @param list<list<string>> $cols Consecutive get_col results.
	 */
	private function wpdb( array $cols ): \wpdb {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$wpdb->method( 'prepare' )->willReturnCallback( static fn( string $sql, mixed ...$args ): string => $sql . '|' . implode( '|', array_map( 'strval', $args ) ) );
		if ( array() !== $cols ) {
			$wpdb->method( 'get_col' )->willReturnOnConsecutiveCalls( ...$cols );
		} else {
			$wpdb->method( 'get_col' )->willReturn( array() );
		}
		return $wpdb;
	}
}
