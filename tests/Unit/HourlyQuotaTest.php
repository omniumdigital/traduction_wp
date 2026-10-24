<?php
/**
 * HourlyQuota tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

final class HourlyQuotaTest extends TestCase {

	private OptionStore $options;

	protected function setUp(): void {
		parent::setUp();
		$this->options = new OptionStore();
		$this->options->install();
	}

	/** @group security */
	public function test_zero_limit_uses_finite_fallback(): void {
		$this->options->data[ Options::SETTINGS ] = array( 'hourly_quota' => 0 );
		$quota = new HourlyQuota( new Settings() );
		$this->assertSame( 100, $quota->limit() );
		$this->assertFalse( $quota->allows( 101 ) );
		$quota->charge( 10 );
		$this->assertSame( 10, $quota->used() );
	}

	public function test_charges_and_resets_each_hour(): void {
		$this->options->data[ Options::SETTINGS ] = array( 'hourly_quota' => 3 );
		$quota = new HourlyQuota( new Settings() );

		$this->assertTrue( $quota->allows( 2 ) );
		$quota->charge( 2 );
		$this->assertSame( 2, $quota->used() );
		$this->assertFalse( $quota->allows( 2 ) );

		$this->options->data[ Options::HOUR_SEGMENTS ] = array(
			'hour'     => gmdate( 'Y-m-d-H', strtotime( '-2 hours' ) ),
			'segments' => 3,
		);
		$this->assertSame( 0, $quota->used() );
		$this->assertTrue( $quota->allows( 3 ) );
	}

	public function test_reservation_is_refused_while_counter_lock_is_held(): void {
		$this->options->data[ Options::SETTINGS ] = array( 'hourly_quota' => 3 );
		$this->options->data[ Options::HOUR_SEGMENTS_LOCK ] = time();

		$this->assertFalse( ( new HourlyQuota( new Settings() ) )->reserve( 1 ) );
		$this->assertArrayNotHasKey( Options::HOUR_SEGMENTS, $this->options->data );
	}
}
