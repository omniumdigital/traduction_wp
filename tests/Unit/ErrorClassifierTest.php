<?php
/**
 * Provider error classification tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Providers\ErrorClassifier;
use Omnium\Translate\Tests\TestCase;

/**
 * Only fixed local strings may be kept from a provider error: the body is
 * matched, never echoed (it can contain submitted content).
 *
 * @group security
 */
final class ErrorClassifierTest extends TestCase {

	public function test_too_large_variants_map_to_one_fixed_string(): void {
		$bodies = array(
			'{"object":"error","message":"Request too large: input is too long","type":"invalid_request_error"}',
			'{"error":{"message":"This model\'s maximum context length is 8192 tokens.","type":"invalid_request_error"}}',
			'{"error":{"message":"max_tokens is too large: 30000. This model supports at most 16384."}}',
			'413 Request Entity Too Large',
		);
		foreach ( $bodies as $body ) {
			$this->assertSame( ErrorClassifier::TOO_LARGE, ErrorClassifier::classify( $body, 400 ), $body );
		}
	}

	public function test_model_errors_map_to_bad_model(): void {
		$this->assertSame(
			ErrorClassifier::BAD_MODEL,
			ErrorClassifier::classify( '{"error":{"message":"The model `mistral-x` does not exist"}}', 404 )
		);
	}

	public function test_format_errors_map_to_bad_format(): void {
		$this->assertSame(
			ErrorClassifier::BAD_FORMAT,
			ErrorClassifier::classify( '{"message":"Invalid request: unexpected field \'foo\'"}', 400 )
		);
	}

	public function test_auth_codes_map_to_key_refused_without_reading_the_body(): void {
		$this->assertSame(
			ErrorClassifier::KEY_REFUSED,
			ErrorClassifier::classify( '{"error":{"message":"whatever the provider says"}}', 401 )
		);
	}

	public function test_unknown_body_maps_to_a_fixed_generic_string(): void {
		$this->assertSame(
			ErrorClassifier::UNKNOWN,
			ErrorClassifier::classify( 'totally unexpected <script>alert(1)</script> ' . PHP_EOL . 'body', 400 )
		);
		$this->assertStringNotContainsString( 'script', ErrorClassifier::UNKNOWN );
	}

	public function test_billing_errors_map_to_billing(): void {
		$bodies = array(
			'{"type":"error","error":{"type":"invalid_request_error","message":"Your credit balance is too low to access the Anthropic API."}}',
			'{"error":{"message":"You exceeded your current quota.","type":"insufficient_quota"}}',
		);
		foreach ( $bodies as $body ) {
			$this->assertSame( ErrorClassifier::BILLING, ErrorClassifier::classify( $body, 400 ), $body );
		}
	}

	public function test_gemini_invalid_key_and_region_on_http_400(): void {
		$this->assertSame(
			ErrorClassifier::KEY_REFUSED,
			ErrorClassifier::classify( '{"error":{"code":400,"message":"API key not valid. Please pass a valid API key.","status":"INVALID_ARGUMENT"}}', 400 )
		);
		$this->assertSame(
			ErrorClassifier::REGION,
			ErrorClassifier::classify( '{"error":{"code":400,"message":"User location is not supported for the API use.","status":"FAILED_PRECONDITION"}}', 400 )
		);
	}

	public function test_provider_code_keeps_identifiers_only(): void {
		$this->assertSame( 'invalid_request_error/error', ErrorClassifier::provider_code( '{"type":"error","error":{"type":"invalid_request_error","message":"secret segment text"}}' ) );
		$this->assertSame( 'INVALID_ARGUMENT/400', ErrorClassifier::provider_code( '{"error":{"code":400,"message":"x","status":"INVALID_ARGUMENT"}}' ) );
		$this->assertSame( '', ErrorClassifier::provider_code( '{"error":{"type":"<script>alert(1)</script>","code":"has spaces in it"}}' ) );
		$this->assertSame( '', ErrorClassifier::provider_code( 'not json' ) );
	}

	public function test_provider_code_never_echoes_the_api_key(): void {
		$key = 'fake_key_0123456789_abcdefghij_klmnop';
		$this->assertSame( 'INVALID_ARGUMENT', ErrorClassifier::provider_code( '{"error":{"code":"' . $key . '","status":"INVALID_ARGUMENT"}}', $key ) );
		$this->assertSame( '', ErrorClassifier::provider_code( '{"error":{"type":"' . substr( $key, 0, 20 ) . '"}}', $key ) );
	}
}
