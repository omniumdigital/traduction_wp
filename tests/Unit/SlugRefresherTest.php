<?php
/**
 * Background translated-slug refresh tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Router\SlugGenerator;
use Omnium\Translate\Router\SlugRefresher;
use Omnium\Translate\Store\SlugRepository;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

final class SlugRefresherTest extends TestCase {

	private OptionStore $options;

	private SpyLogger $logger;

	/** @var list<array{0: string, 1: array<mixed>, 2: string}> */
	private array $enqueued = array();

	protected function setUp(): void {
		parent::setUp();
		$this->options = new OptionStore();
		$this->options->install();
		$this->logger = new SpyLogger();
		Functions\when( 'as_enqueue_async_action' )->alias(
			function ( string $hook, array $args, string $group ): int {
				$this->enqueued[] = array( $hook, $args, $group );
				return 1;
			}
		);
	}

	private function refresher(): SlugRefresher {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$slugs        = new SlugRepository( $wpdb );
		return new SlugRefresher(
			new SlugGenerator( $slugs, new FakeTranslationRepository(), new Settings() ),
			$slugs,
			new PublicContent(),
			new Settings(),
			$this->logger
		);
	}

	public function test_nothing_is_scheduled_without_a_request(): void {
		Functions\when( 'as_has_scheduled_action' )->justReturn( false );
		$this->refresher()->maybe_schedule();
		$this->assertSame( array(), $this->enqueued );
	}

	public function test_a_request_schedules_one_background_action(): void {
		Functions\when( 'as_has_scheduled_action' )->justReturn( false );
		SlugRefresher::request();
		$this->refresher()->maybe_schedule();
		$this->assertSame( array( array( SlugRefresher::ACTION, array(), ActionSchedulerQueue::GROUP ) ), $this->enqueued );
	}

	public function test_an_already_scheduled_refresh_is_not_duplicated(): void {
		Functions\when( 'as_has_scheduled_action' )->justReturn( true );
		SlugRefresher::request();
		$this->refresher()->maybe_schedule();
		$this->assertSame( array(), $this->enqueued );
	}

	public function test_refresh_clears_the_request_and_logs_without_content(): void {
		Functions\when( 'get_post_types' )->justReturn( array( 'page' => 'page' ) );
		Functions\when( 'get_posts' )->justReturn( array() );
		SlugRefresher::request();

		$this->assertSame( 0, $this->refresher()->refresh() );
		$this->assertArrayNotHasKey( Options::SLUG_REFRESH, $this->options->data );
		$this->assertSame( array( 'info', 'router:slugs', 'Translated slugs refreshed: 0 changed.' ), $this->logger->lines[0] );
	}
}
