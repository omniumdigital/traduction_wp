<?php
/**
 * Sanitizer contract tests: the whitelist is a security boundary.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
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

	public function test_ai_xss_payload_is_always_passed_to_strict_wp_kses(): void {
		$payload = '<script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">click</a>';

		Functions\expect( 'wp_kses' )
			->once()
			->with( $payload, Sanitizer::allowed_inline_tags(), array( 'http', 'https', 'mailto', 'tel' ) )
			->andReturn( 'alert(1)<a>click</a>' );

		$this->assertSame( 'alert(1)<a>click</a>', Sanitizer::inline_html( $payload ) );
	}
}
