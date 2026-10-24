<?php
/**
 * Queue module registration tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\QueueModule;
use Omnium\Translate\Queue\TranslationQueueInterface;
use Omnium\Translate\Tests\TestCase;

/** Ensures consumers can resolve the queue by its shared contract. */
final class QueueModuleTest extends TestCase {

	/** Both the implementation and its interface are registered. */
	public function test_registers_translation_queue_contract(): void {
		$container = new Container();

		( new QueueModule() )->register( $container );

		$this->assertTrue( $container->has( ActionSchedulerQueue::class ) );
		$this->assertTrue( $container->has( TranslationQueueInterface::class ) );
	}
}
