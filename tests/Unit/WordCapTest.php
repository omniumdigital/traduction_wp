<?php
/**
 * WordCap tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

final class WordCapTest extends TestCase {

	private OptionStore $options;

	protected function setUp(): void {
		parent::setUp();
		$this->options = new OptionStore();
		$this->options->install();
	}

	private function make_cap( int $limit ): WordCap {
		$this->options->data['omtr_settings'] = array( 'monthly_word_cap' => $limit );
		return new WordCap( new Settings() );
	}

	public function test_zero_limit_means_unlimited(): void {
		$cap = $this->make_cap( 0 );

		$this->assertSame( \PHP_INT_MAX, $cap->remaining() );
		$this->assertFalse( $cap->is_exhausted() );
		$this->assertTrue( $cap->allows( 1000000 ) );
	}

	public function test_charge_and_exhaustion(): void {
		$cap = $this->make_cap( 100 );

		$this->assertTrue( $cap->allows( 80 ) );
		$cap->charge( 80 );
		$this->assertSame( 80, $cap->used() );
		$this->assertSame( 20, $cap->remaining() );

		$this->assertFalse( $cap->allows( 21 ) );
		$this->assertTrue( $cap->allows( 20 ) );
		$cap->charge( 20 );
		$this->assertTrue( $cap->is_exhausted() );
	}

	public function test_counter_resets_when_the_month_changes(): void {
		$cap = $this->make_cap( 100 );
		$cap->charge( 90 );

		$this->options->data[ Options::MONTH_WORDS ] = array(
			'month' => gmdate( 'Y-m', strtotime( '-40 days' ) ),
			'words' => 90,
		);

		$this->assertSame( 0, $cap->used() );
		$this->assertSame( 100, $cap->remaining() );
	}

	public function test_count_words_handles_unicode_and_punctuation(): void {
		$cap = $this->make_cap( 0 );

		$this->assertSame( 4, $cap->count_words( array( 'Bonjour tout le monde' ) ) );
		$this->assertSame( 4, $cap->count_words( array( 'À côté, très bien !' ) ) );
		$this->assertSame( 2, $cap->count_words( array( 'Prix : {prix} euros' ) ) );
		$this->assertSame( 0, $cap->count_words( array( '---' ) ) );
	}

	public function test_reservation_is_refused_while_counter_lock_is_held(): void {
		$cap = $this->make_cap( 100 );
		$this->options->data[ Options::MONTH_WORDS_LOCK ] = time();

		$this->assertFalse( $cap->reserve( 10 ) );
		$this->assertSame( 0, $cap->used() );
	}
}
