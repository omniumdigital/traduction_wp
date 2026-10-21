<?php
/**
 * Segment identity tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Tests\TestCase;

final class SegmentKeyTest extends TestCase {

	public function test_whitespace_variants_share_a_hash(): void {
		$a = SegmentKey::hash( 'fr', SegmentKey::normalize( "  Bonjour \n le   monde " ) );
		$b = SegmentKey::hash( 'fr', SegmentKey::normalize( 'Bonjour le monde' ) );
		$this->assertSame( $a, $b );
	}

	public function test_source_language_changes_hash(): void {
		$this->assertNotSame( SegmentKey::hash( 'fr', 'Hello' ), SegmentKey::hash( 'en', 'Hello' ) );
	}
}
