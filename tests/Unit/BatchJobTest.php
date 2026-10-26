<?php
/**
 * BatchJob tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Glossary\GlossaryService;
use Omnium\Translate\Providers\ProviderException;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\BatchJob;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Security\Sanitizer;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeProvider;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentRepository;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/**
 * @group security
 */
final class BatchJobTest extends TestCase {

	private OptionStore $options;
	private FakeProvider $provider;
	private FakeSegmentRepository $segments;
	private FakeTranslationRepository $translations;
	private SpyLogger $logger;
	private RunLock $lock;
	private WordCap $word_cap;
	private BatchJob $job;

	/**
	 * Scheduled actions.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $scheduled = array();

	protected function setUp(): void {
		parent::setUp();
		$this->options = new OptionStore();
		$this->options->install();
		$this->options->data[ Options::SETTINGS ] = array(
			'source_lang' => 'fr',
			'provider'    => 'claude',
		);

		$this->provider     = new FakeProvider();
		$this->segments     = new FakeSegmentRepository();
		$this->translations = new FakeTranslationRepository();
		$this->logger       = new SpyLogger();
		$this->lock         = new RunLock();

		$this->segments->seed( 'hash-a', 'Texte source secret' );
		$this->translations->id_hash = array_flip( $this->segments->ids );

		$registry = new ProviderRegistry();
		$registry->add( $this->provider );
		$queue = new ActionSchedulerQueue(
			new Settings(),
			$this->lock,
			new WordCap( new Settings() ),
			new HourlyQuota( new Settings() ),
			$registry,
			$this->logger,
			$this->segments,
			$this->translations
		);

		$test = $this;
		Functions\when( 'as_schedule_single_action' )->alias(
			static function ( $timestamp, $hook, $args = array(), $group = '' ) use ( $test ): int {
				$test->scheduled[] = $args + array(
					'timestamp' => $timestamp,
					'hook'      => $hook,
					'group'     => $group,
				);
				return 1;
			}
		);
		Functions\when( 'as_get_scheduled_actions' )->alias(
			static fn( array $query ): array => ActionSchedulerQueue::ACTION_BATCH === $query['hook'] ? array( 123 ) : array()
		);

		$wpdb            = new \wpdb();
		$GLOBALS['wpdb'] = $wpdb;
		$this->word_cap  = new WordCap( new Settings() );
		$this->job       = new BatchJob(
			new Settings(),
			$this->lock,
			$queue,
			$this->segments,
			$this->translations,
			$this->logger,
			new GlossaryService( new GlossaryRepository( $wpdb ), new Settings() ),
			null,
			null,
			null,
			$this->word_cap
		);
	}

	public function test_sanitizes_untrusted_ai_html_before_storage(): void {
		$payload = '<script>alert(1)</script><img src=x onerror=alert(2)><a href="javascript:alert(3)">link</a><strong>ok</strong>';
		$this->segments->texts['hash-a'] = '<a>Texte</a><strong>source</strong>';
		$this->provider->response = array( 'hash-a' => $payload );
		$this->lock->acquire( 'run-a' );

		Functions\expect( 'wp_kses' )
			->once()
			->with( $payload, Sanitizer::allowed_inline_tags(), array( 'http', 'https', 'mailto', 'tel' ) )
			->andReturn( 'alert(1)alert(2)<a>link</a><strong>ok</strong>' );

		$this->job->handle( array( 1 => 'hash-a' ), 'en', 'run-a', 1 );

		$this->assertSame( 'alert(1)alert(2)<a>link</a><strong>ok</strong>', $this->translations->autos[0]['texts'][1] );
		$this->assertStringNotContainsString( 'Texte source secret', $this->logger->joined() );
		$this->assertStringNotContainsString( $payload, $this->logger->joined() );
		$this->assertFalse( $this->lock->is_held() );
	}

	public function test_provider_independent_quality_gate_rejects_missing_placeholder(): void {
		$this->segments->texts['hash-a'] = 'Prix : %s';
		$this->provider->response        = array( 'hash-a' => 'Price' );
		Functions\when( 'wp_kses' )->returnArg();

		$this->job->handle( array( 1 => 'hash-a' ), 'en', '', 1 );

		$this->assertCount( 0, $this->translations->autos );
		$this->assertSame( array( 1 ), $this->translations->failures[0]['ids'] );
	}

	public function test_already_translated_segment_is_never_sent_again(): void {
		$this->translations->seed( 'hash-a', 'en', 'Already translated' );

		$this->job->handle( array( 1 => 'hash-a' ), 'en', '', 1 );

		$this->assertCount( 0, $this->provider->calls );
		$this->assertCount( 0, $this->translations->autos );
	}

	public function test_retryable_error_uses_backoff_without_logging_provider_message(): void {
		$this->provider->exception = new ProviderException( 'RAW SECRET PROVIDER RESPONSE', 429, true );

		$this->job->handle( array( 1 => 'hash-a' ), 'en', 'run-a', 1 );

		$this->assertCount( 1, $this->scheduled );
		$this->assertSame( 2, $this->scheduled[0]['attempt'] );
		$this->assertSame( 30, $this->scheduled[0]['timestamp'] - time() );
		$this->assertStringNotContainsString( 'RAW SECRET PROVIDER RESPONSE', $this->logger->joined() );
		$this->assertCount( 0, $this->translations->failures );
	}

	public function test_fifth_retryable_failure_is_marked_failed(): void {
		$this->provider->exception = new ProviderException( 'generic', 503, true );

		$this->job->handle( array( 1 => 'hash-a' ), 'en', '', 5 );

		$this->assertCount( 0, $this->scheduled );
		$this->assertSame( array( 1 ), $this->translations->failures[0]['ids'] );
	}

	public function test_unknown_segment_ids_never_reach_provider(): void {
		$this->job->handle( array( 999 => 'unknown-hash' ), 'en', '', 1 );

		$this->assertCount( 0, $this->provider->calls );
	}

	public function test_successful_batch_records_translated_source_words(): void {
		$this->segments->texts['hash-a'] = 'Bonjour tout le monde';
		$this->provider->response        = array( 'hash-a' => 'Hello whole wide world' );
		Functions\when( 'wp_kses' )->returnArg();

		$this->job->handle( array( 1 => 'hash-a' ), 'en', '', 1 );

		$this->assertCount( 1, $this->translations->autos );
		$this->assertSame( 4, $this->word_cap->translated_this_month() );
		$this->assertSame( 4, $this->word_cap->translated_total() );
	}

	public function test_failed_batch_records_no_translated_words(): void {
		$this->provider->exception = new ProviderException( 'generic', 401, false );

		$this->job->handle( array( 1 => 'hash-a' ), 'en', '', 1 );

		$this->assertCount( 0, $this->translations->autos );
		$this->assertSame( array( 1 ), $this->translations->failures[0]['ids'] );
		$this->assertSame( 0, $this->word_cap->translated_this_month() );
		$this->assertSame( 0, $this->word_cap->translated_total() );
	}

	public function test_quality_rejected_segment_is_not_counted_as_translated(): void {
		$this->segments->texts['hash-a'] = 'Prix : %s euros';
		$this->provider->response        = array( 'hash-a' => 'Prix : 12 euros' );
		Functions\when( 'wp_kses' )->returnArg();

		$this->job->handle( array( 1 => 'hash-a' ), 'en', '', 1 );

		$this->assertCount( 0, $this->translations->autos );
		$this->assertSame( 0, $this->word_cap->translated_total() );
	}

	public function test_partial_batch_records_only_saved_source_words(): void {
		$this->segments->texts['hash-a'] = 'Bonjour le monde';
		$this->segments->texts['hash-b'] = 'Prix : %s euros';
		$this->provider->response        = array(
			'hash-a' => 'Hello the world',
			'hash-b' => 'Prix : 12 euros',
		);
		Functions\when( 'wp_kses' )->returnArg();

		$this->job->handle( array( 1 => 'hash-a', 2 => 'hash-b' ), 'en', '', 1 );

		$this->assertCount( 1, $this->translations->autos );
		$this->assertSame( 3, $this->word_cap->translated_this_month() );
		$this->assertSame( 3, $this->word_cap->translated_total() );
	}
}
