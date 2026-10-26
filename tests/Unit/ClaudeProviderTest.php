<?php
/**
 * ClaudeProvider tests (wp_remote_post mocked).
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\Glossary;
use Omnium\Translate\Providers\Claude\ClaudeProvider;
use Omnium\Translate\Providers\ProviderException;
use Omnium\Translate\Providers\PromptBuilder;
use Omnium\Translate\Providers\ResponseValidator;
use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Security\Crypto;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

final class ClaudeProviderTest extends TestCase {

	private OptionStore $options;

	/**
	 * Captured HTTP requests.
	 *
	 * @var list<array{url: string, args: array<string, mixed>}>
	 */
	private array $requests = array();

	/**
	 * Build the provider over stubbed options and an encrypted test key.
	 */
	private function make_provider( array $settings = array() ): ClaudeProvider {
		$this->options = new OptionStore();
		$this->options->install();

		$crypto = new Crypto();
		$store  = new ApiKeyStore( $crypto );
		$store->set( 'claude', 'sk-test-key-value' );

		$this->options->data[ Options::SETTINGS ] = $settings;

		Functions\when( '__' )->returnArg();
		Functions\when( 'wp_json_encode' )->alias(
			static fn( $data, $flags = 0 ) => json_encode( $data, $flags )
		);

		return new ClaudeProvider(
			$store,
			new Settings(),
			new PromptBuilder(),
			new ResponseValidator()
		);
	}

	/**
	 * Build a Messages API response body.
	 *
	 * @param array<int, string> $translations Number => text.
	 */
	private function response_body( array $translations ): string {
		$blocks = array(
			array(
				'type' => 'text',
				'text' => (string) json_encode( $translations, JSON_UNESCAPED_UNICODE ),
			),
		);
		return (string) json_encode(
			array(
				'id'          => 'msg_test',
				'content'     => $blocks,
				'stop_reason' => 'end_turn',
			)
		);
	}

	/**
	 * Stub the HTTP layer: capture requests, answer with a queue of responses.
	 *
	 * @param list<array{code: int, body: string}|array{wp_error: string}> $responses
	 */
	private function stub_http( array $responses ): void {
		$this->requests = array();
		$test           = $this;

		Functions\when( 'is_wp_error' )->alias(
			static fn( $thing ) => $thing instanceof \WP_Error
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static function ( $response ) {
				return is_array( $response ) && isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( $response ) => is_array( $response ) && isset( $response['body'] ) ? (string) $response['body'] : ''
		);
		Functions\when( 'wp_remote_post' )->alias(
			static function ( $url, $args ) use ( $test, &$responses ) {
				$test->requests[] = array(
					'url'  => $url,
					'args' => $args,
				);
				$next = array_shift( $responses );
				if ( null === $next ) {
					return array( 'response' => array( 'code' => 200 ), 'body' => '' );
				}
				if ( isset( $next['wp_error'] ) ) {
					return new \WP_Error( $next['wp_error'] );
				}
				return array(
					'response' => array( 'code' => $next['code'] ),
					'body'     => $next['body'],
				);
			}
		);
	}

	public function test_translate_batch_success(): void {
		$provider = $this->make_provider();
		$body     = $this->response_body( array( 1 => 'Bonjour', 2 => 'le monde' ) );
		$this->stub_http( array( array( 'code' => 200, 'body' => $body ) ) );

		$result = $provider->translate_batch(
			array( 'hash-1' => 'Hello', 'hash-2' => 'the world' ),
			'fr_source',
			'target',
			new Glossary()
		);

		$this->assertSame( array( 'hash-1' => 'Bonjour', 'hash-2' => 'le monde' ), $result );

		// Request shape: hardcoded endpoint, key header, version, timeout, sslverify.
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://api.anthropic.com/v1/messages', $this->requests[0]['url'] );
		$this->assertSame( 60, $this->requests[0]['args']['timeout'] );
		$this->assertTrue( $this->requests[0]['args']['sslverify'] );
		$this->assertSame( 'sk-test-key-value', $this->requests[0]['args']['headers']['x-api-key'] );
		$this->assertSame( '2023-06-01', $this->requests[0]['args']['headers']['anthropic-version'] );

		$sent = json_decode( (string) $this->requests[0]['args']['body'], true );
		$this->assertSame( 'claude-haiku-5-5', $sent['model'] );
		$this->assertGreaterThan( 0, $sent['max_tokens'] );
		$this->assertSame( 'user', $sent['messages'][0]['role'] );
	}

	public function test_model_setting_is_respected(): void {
		$provider = $this->make_provider( array( 'model' => 'claude-sonnet-5-5' ) );
		$this->stub_http( array( array( 'code' => 200, 'body' => $this->response_body( array( 1 => 'Hi' ) ) ) ) );

		$provider->translate_batch( array( 'hash-1' => 'Salut' ), 'fr', 'en', new Glossary() );

		$sent = json_decode( (string) $this->requests[0]['args']['body'], true );
		$this->assertSame( 'claude-sonnet-5-5', $sent['model'] );
	}

	public function test_invalid_json_gets_exactly_one_retry(): void {
		$provider = $this->make_provider();
		$this->stub_http(
			array(
				array( 'code' => 200, 'body' => 'not json at all' ),
				array( 'code' => 200, 'body' => $this->response_body( array( 1 => 'Bonjour' ) ) ),
			)
		);

		$result = $provider->translate_batch( array( 'hash-1' => 'Hello' ), 'fr', 'en', new Glossary() );

		$this->assertSame( array( 'hash-1' => 'Bonjour' ), $result );
		$this->assertCount( 2, $this->requests );
	}

	public function test_two_invalid_responses_fail_without_retryable_flag(): void {
		$provider = $this->make_provider();
		$this->stub_http(
			array(
				array( 'code' => 200, 'body' => 'garbage' ),
				array( 'code' => 200, 'body' => 'still garbage' ),
			)
		);

		try {
			$provider->translate_batch( array( 'hash-1' => 'Hello' ), 'fr', 'en', new Glossary() );
			$this->fail( 'Expected ProviderException.' );
		} catch ( ProviderException $e ) {
			$this->assertFalse( $e->retryable );
			$this->assertSame( 0, $e->http_code );
		}
	}

	public function test_http_429_is_retryable_with_generic_message(): void {
		$provider = $this->make_provider();
		$this->stub_http( array( array( 'code' => 429, 'body' => '{"error":"SECRET INBOUND TEXT"}' ) ) );

		try {
			$provider->translate_batch( array( 'hash-1' => 'Hello' ), 'fr', 'en', new Glossary() );
			$this->fail( 'Expected ProviderException.' );
		} catch ( ProviderException $e ) {
			$this->assertTrue( $e->retryable );
			$this->assertSame( 429, $e->http_code );
			$this->assertStringNotContainsString( 'SECRET INBOUND TEXT', $e->getMessage() );
		}
	}

	public function test_http_500_is_retryable(): void {
		$provider = $this->make_provider();
		$this->stub_http( array( array( 'code' => 503, 'body' => '{"type":"overloaded_error"}' ) ) );

		try {
			$provider->translate_batch( array( 'hash-1' => 'Hello' ), 'fr', 'en', new Glossary() );
			$this->fail( 'Expected ProviderException.' );
		} catch ( ProviderException $e ) {
			$this->assertTrue( $e->retryable );
			$this->assertSame( 503, $e->http_code );
		}
	}

	public function test_http_401_is_not_retryable(): void {
		$provider = $this->make_provider();
		$this->stub_http( array( array( 'code' => 401, 'body' => '{"type":"error","error":{"type":"authentication_error","message":"secret echoed text"}}' ) ) );

		try {
			$provider->translate_batch( array( 'hash-1' => 'Hello' ), 'fr', 'en', new Glossary() );
			$this->fail( 'Expected ProviderException.' );
		} catch ( ProviderException $e ) {
			$this->assertFalse( $e->retryable );
			$this->assertSame( 401, $e->http_code );
		}
	}

	public function test_network_error_is_retryable(): void {
		$provider = $this->make_provider();
		$this->stub_http( array( array( 'wp_error' => 'http_request_timeout' ) ) );

		try {
			$provider->translate_batch( array( 'hash-1' => 'Hello' ), 'fr', 'en', new Glossary() );
			$this->fail( 'Expected ProviderException.' );
		} catch ( ProviderException $e ) {
			$this->assertTrue( $e->retryable );
			$this->assertSame( 0, $e->http_code );
		}
	}

	public function test_missing_key_fails_before_any_request(): void {
		$provider = $this->make_provider();
		$this->options->data[ Options::API_KEYS ] = array();
		$this->stub_http( array() );

		try {
			$provider->translate_batch( array( 'hash-1' => 'Hello' ), 'fr', 'en', new Glossary() );
			$this->fail( 'Expected ProviderException.' );
		} catch ( ProviderException $e ) {
			$this->assertFalse( $e->retryable );
			$this->assertStringNotContainsString( 'sk-test-key-value', $e->getMessage() );
		}
		$this->assertCount( 0, $this->requests );
	}

	public function test_empty_batch_short_circuits(): void {
		$provider = $this->make_provider();
		$this->stub_http( array() );

		$result = $provider->translate_batch( array(), 'fr', 'en', new Glossary() );

		$this->assertSame( array(), $result );
		$this->assertCount( 0, $this->requests );
	}

	public function test_test_key_ok(): void {
		$provider = $this->make_provider();
		$this->stub_http( array( array( 'code' => 200, 'body' => $this->response_body( array() ) ) ) );

		$result = $provider->test_key();
		$this->assertTrue( $result->ok );
	}

	public function test_test_key_invalid_key(): void {
		$provider = $this->make_provider();
		$this->stub_http( array( array( 'code' => 401, 'body' => '{"type":"error","error":{"type":"authentication_error","message":"secret echoed text"}}' ) ) );

		$result = $provider->test_key();
		$this->assertFalse( $result->ok );
		$this->assertSame( 401, $result->http_code );
		// Only the identifier-shaped provider code is surfaced, never the free-text message.
		$this->assertStringContainsString( 'authentication_error', $result->message );
		$this->assertStringNotContainsString( 'secret echoed text', $result->message );
	}

	public function test_unknown_ids_make_the_response_invalid(): void {
		$provider = $this->make_provider();
		$body     = $this->response_body( array( 1 => 'Bonjour', 7 => 'injected' ) );
		$this->stub_http( array( array( 'code' => 200, 'body' => $body ) ) );

		$this->expectException( ProviderException::class );
		$provider->translate_batch( array( 'hash-1' => 'Hello' ), 'fr', 'en', new Glossary() );
	}
}
