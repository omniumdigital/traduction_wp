<?php
/**
 * Action Scheduler loader tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\ActionSchedulerLoader;
use Omnium\Translate\Tests\TestCase;

final class ActionSchedulerLoaderTest extends TestCase {

	public function test_registers_at_the_start_of_plugins_loaded(): void {
		Functions\expect( 'add_action' )
			->once()
			->with( 'plugins_loaded', array( ActionSchedulerLoader::class, 'load' ), -100 );

		ActionSchedulerLoader::register();
		$this->addToAssertionCount( 1 );
	}
}
