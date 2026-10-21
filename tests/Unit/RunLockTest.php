<?php
/**
 * RunLock tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Core\Options;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

final class RunLockTest extends TestCase {

	private OptionStore $options;

	protected function setUp(): void {
		parent::setUp();
		$this->options = new OptionStore();
		$this->options->install();
	}

	public function test_acquire_and_release(): void {
		$lock = new RunLock();

		$this->assertTrue( $lock->acquire( 'run-a' ) );
		$this->assertTrue( $lock->is_held() );
		$this->assertSame( 'run-a', $lock->current_run() );

		$lock->release( 'run-a' );
		$this->assertFalse( $lock->is_held() );
	}

	public function test_second_acquire_is_refused(): void {
		$lock = new RunLock();
		$this->assertTrue( $lock->acquire( 'run-a' ) );
		$this->assertFalse( $lock->acquire( 'run-b' ) );
		$this->assertSame( 'run-a', $lock->current_run() );
	}

	public function test_release_by_another_run_is_ignored(): void {
		$lock = new RunLock();
		$lock->acquire( 'run-a' );

		$lock->release( 'run-b' );

		$this->assertSame( 'run-a', $lock->current_run() );
	}

	public function test_stale_lock_can_be_stolen(): void {
		$lock = new RunLock();
		$lock->acquire( 'run-a' );

		// Simulate a crashed run: timestamp 4 hours old.
		$this->options->data[ Options::TRANSLATION_LOCK ] = array(
			'run' => 'run-a',
			'at'  => time() - 14400,
		);

		$this->assertFalse( $lock->is_held() );
		$this->assertTrue( $lock->acquire( 'run-b' ) );
		$this->assertSame( 'run-b', $lock->current_run() );
	}

	public function test_heartbeat_refreshes_fresh_lock_only(): void {
		$lock = new RunLock();
		$lock->acquire( 'run-a' );

		$this->options->data[ Options::TRANSLATION_LOCK ] = array(
			'run' => 'run-a',
			'at'  => time() - 14400,
		);
		$lock->heartbeat( 'run-a' );

		$payload = $this->options->data[ Options::TRANSLATION_LOCK ];
		$this->assertLessThanOrEqual( time() + 5, $payload['at'] );
		$this->assertGreaterThan( time() - 60, $payload['at'] );
	}
}
