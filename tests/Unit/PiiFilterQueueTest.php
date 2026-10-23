<?php
/**
 * PII filter tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Tests\TestCase;

/**
 * PII detection is a privacy and security boundary before provider calls.
 *
 * @group security
 */
final class PiiFilterQueueTest extends TestCase {

	private PiiFilter $filter;

	protected function setUp(): void {
		parent::setUp();
		$this->filter = new PiiFilter();
	}

	public function test_detects_email(): void {
		$this->assertTrue( $this->filter->contains_pii( 'Contact: client@example.com' ) );
	}

	public function test_detects_french_and_international_phones(): void {
		$this->assertTrue( $this->filter->contains_pii( 'Appelez le 06 12 34 56 78.' ) );
		$this->assertTrue( $this->filter->contains_pii( 'Call +44 20 7946 0958.' ) );
	}

	public function test_detects_valid_iban(): void {
		$this->assertTrue( $this->filter->contains_pii( 'IBAN: FR76 3000 6000 0112 3456 7890 189' ) );
	}

	public function test_detects_luhn_valid_card_number(): void {
		$this->assertTrue( $this->filter->contains_pii( 'Card: 4242 4242 4242 4242' ) );
	}

	public function test_ignores_safe_content_and_invalid_checksums(): void {
		$this->assertFalse( $this->filter->contains_pii( 'Livraison rapide partout en France.' ) );
		$this->assertFalse( $this->filter->contains_pii( 'Reference 4242 4242 4242 4241' ) );
	}
}
