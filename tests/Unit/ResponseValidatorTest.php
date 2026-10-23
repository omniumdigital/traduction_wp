<?php
/**
 * ResponseValidator tests: the strict JSON contract is a security boundary.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Providers\ResponseValidator;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class ResponseValidatorTest extends TestCase {

	private ResponseValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new ResponseValidator();
	}

	public function test_valid_strict_json_is_mapped_to_ints(): void {
		$result = $this->validator->validate( '{"1":"Bonjour","2":"Monde"}', array( 1, 2 ) );
		$this->assertSame( array( 1 => 'Bonjour', 2 => 'Monde' ), $result );
	}

	public function test_markdown_code_fence_is_rejected(): void {
		$this->assertNull( $this->validator->validate( '```json{"1":"a"}```', array( 1 ) ) );
	}

	public function test_prose_around_json_is_rejected(): void {
		$this->assertNull( $this->validator->validate( 'Here you go: {"1":"a"}', array( 1 ) ) );
	}

	public function test_invalid_json_is_rejected(): void {
		$this->assertNull( $this->validator->validate( '{"1":"a"', array( 1 ) ) );
	}

	public function test_non_object_root_is_rejected(): void {
		$this->assertNull( $this->validator->validate( '["a","b"]', array( 1, 2 ) ) );
		$this->assertNull( $this->validator->validate( '"translated"', array( 1 ) ) );
	}

	public function test_unknown_ids_are_dropped(): void {
		$result = $this->validator->validate( '{"1":"ok","99":"injected"}', array( 1 ) );
		$this->assertSame( array( 1 => 'ok' ), $result );
	}

	public function test_only_unknown_ids_is_invalid(): void {
		$this->assertNull( $this->validator->validate( '{"98":"a","99":"b"}', array( 1, 2 ) ) );
	}

	public function test_non_string_values_are_dropped(): void {
		$result = $this->validator->validate( '{"1":"ok","2":1337}', array( 1, 2 ) );
		$this->assertSame( array( 1 => 'ok' ), $result );
	}

	public function test_empty_string_value_is_dropped(): void {
		$this->assertNull( $this->validator->validate( '{"1":"   "}', array( 1 ) ) );
	}

	public function test_empty_object_is_invalid(): void {
		$this->assertNull( $this->validator->validate( '{}', array( 1 ) ) );
	}

	public function test_empty_body_is_invalid(): void {
		$this->assertNull( $this->validator->validate( '', array( 1 ) ) );
	}

	public function test_null_bytes_are_not_accepted_as_object(): void {
		$this->assertNull( $this->validator->validate( "\0{\"1\":\"a\"}", array( 1 ) ) );
	}
}
