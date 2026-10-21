<?php
/**
 * Database logger tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Store\DbLogger;
use Omnium\Translate\Tests\TestCase;

final class DbLoggerTest extends TestCase {

	public function test_schedules_one_recurring_purge_in_omtr_group(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		Functions\expect( 'as_has_scheduled_action' )->once()->with( DbLogger::PURGE_HOOK, array(), 'omtr' )->andReturn( false );
		Functions\expect( 'as_schedule_recurring_action' )->once()->with( \Mockery::type( 'int' ), DAY_IN_SECONDS, DbLogger::PURGE_HOOK, array(), 'omtr', true );

		( new DbLogger( $wpdb ) )->schedule_purge();
		$this->assertTrue( true );
	}
}
