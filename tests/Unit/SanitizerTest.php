<?php
/**
 * Sanitizer contract tests: the whitelist is a security boundary.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Security\Sanitizer;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class SanitizerTest extends TestCase {

	public function test_whitelist_has_no_dangerous_attributes(): void {
		foreach ( Sanitizer::allowed_inline_tags() as $tag => $attributes ) {
			$this->assertNotContains( $tag, array( 'script', 'style', 'img', 'iframe', 'svg', 'object' ) );
			foreach ( array_keys( $attributes ) as $attribute ) {
				$this->assertStringStartsNotWith( 'on', $attribute );
				$this->assertNotContains( $attribute, array( 'style', 'src', 'srcset' ) );
			}
		}
	}
}
