<?php
/**
 * Queue pause state tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Queue\QueueState;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

/** Tests the persistent manual queue switch. */
final class QueueStateTest extends TestCase {
	/** Pause and resume update the shared option state. */
	public function test_pause_and_resume(): void {
		$options = new OptionStore();
		$options->install();
		$state = new QueueState();

		$this->assertFalse( $state->is_paused() );
		$state->pause();
		$this->assertTrue( $state->is_paused() );
		$state->resume();
		$this->assertFalse( $state->is_paused() );
	}
}
