<?php
/**
 * Personal-data filter tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class PiiFilterTest extends TestCase {

	private PiiFilter $filter;

	protected function setUp(): void {
		parent::setUp();
		$this->filter = new PiiFilter();
	}

	public function test_detects_email(): void {
		$this->assertTrue( $this->filter->contains_pii( 'Contact: hello@example.fr' ) );
	}

	public function test_detects_french_phone(): void {
		$this->assertTrue( $this->filter->contains_pii( 'Appelez le 06 12 34 56 78.' ) );
	}

	public function test_detects_international_phone(): void {
		$this->assertTrue( $this->filter->contains_pii( 'Call +44 20 7946 0958.' ) );
	}

	public function test_detects_iban(): void {
		$this->assertTrue( $this->filter->contains_pii( 'IBAN GB82 WEST 1234 5698 7654 32' ) );
	}

	public function test_detects_luhn_valid_card(): void {
		$this->assertTrue( $this->filter->contains_pii( 'Card 4111 1111 1111 1111' ) );
	}

	public function test_rejects_invalid_card_and_plain_copy(): void {
		$this->assertFalse( $this->filter->contains_pii( 'Reference 4111 1111 1111 1112, spring collection.' ) );
	}
}
