<?php
/**
 * BatchSizer tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Providers\RateLimits;
use Omnium\Translate\Queue\BatchSizer;
use Omnium\Translate\Tests\TestCase;

final class BatchSizerTest extends TestCase {

	/**
	 * Build segments: hash-i => str_repeat of i chars.
	 *
	 * @param int $count  Number of segments.
	 * @param int $length Characters per segment.
	 * @return array<string, string>
	 */
	private function segments( int $count, int $length ): array {
		$segments = array();
		for ( $i = 1; $i <= $count; $i++ ) {
			$segments[ 'hash-' . $i ] = str_repeat( 'a', $length );
		}
		return $segments;
	}

	public function test_respects_max_size(): void {
		$batches = BatchSizer::chunk( $this->segments( 45, 10 ), new RateLimits( 50, 20, 100000 ) );
		$this->assertCount( 3, $batches );
		$this->assertCount( 20, $batches[0] );
		$this->assertCount( 20, $batches[1] );
		$this->assertCount( 5, $batches[2] );
	}

	public function test_size_hint_is_clamped_between_20_and_50(): void {
		// Hint above 50: still 50 max.
		$batches = BatchSizer::chunk( $this->segments( 120, 1 ), new RateLimits( 50, 80, 100000 ) );
		$this->assertSame( 50, count( $batches[0] ) );

		// Hint below 20: floor at 20.
		$batches = BatchSizer::chunk( $this->segments( 30, 1 ), new RateLimits( 50, 5, 100000 ) );
		$this->assertSame( 20, count( $batches[0] ) );
	}

	public function test_respects_char_budget(): void {
		// 10 chars each, budget 25: one segment per batch once adding would overflow.
		$batches = BatchSizer::chunk( $this->segments( 3, 10 ), new RateLimits( 50, 50, 25 ) );
		$this->assertCount( 2, $batches );
		$this->assertCount( 2, $batches[0] );
		$this->assertCount( 1, $batches[1] );
	}

	public function test_preserves_order_and_keys(): void {
		$batches = BatchSizer::chunk( array( 'a' => 'first', 'b' => 'second' ), new RateLimits( 50, 20, 100000 ) );
		$this->assertSame( array( 'a', 'b' ), array_keys( $batches[0] ) );
	}

	public function test_empty_input_gives_no_batch(): void {
		$this->assertSame( array(), BatchSizer::chunk( array(), new RateLimits() ) );
	}
}
