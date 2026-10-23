<?php
/**
 * Anthropic Claude provider (Messages API).
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers\Claude;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\Glossary;
use Omnium\Translate\Providers\ProviderException;
use Omnium\Translate\Providers\ProviderInterface;
use Omnium\Translate\Providers\PromptBuilder;
use Omnium\Translate\Providers\RateLimits;
use Omnium\Translate\Providers\ResponseValidator;
use Omnium\Translate\Providers\Result;
use Omnium\Translate\Security\ApiKeyStore;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the Anthropic Messages API over HTTPS with the client's own key.
 *
 * API facts verified against the official documentation on 2026-10-23:
 * - Endpoint: POST https://api.anthropic.com/v1/messages (hard-coded, HTTPS only).
 * - Required headers: x-api-key, anthropic-version: 2023-06-01, content-type: application/json.
 * - Model ids and pricing: https://platform.claude.com/docs/en/about-claude/models/overview
 */
final class ClaudeProvider implements ProviderInterface {

	private const ENDPOINT    = 'https://api.anthropic.com/v1/messages';
	private const API_VERSION = '2023-06-01';

	/**
	 * Default model: the fastest and least expensive current Claude model.
	 *
	 * Claude Haiku 5.5 (comparative latency "Fastest", from $0.10 / $0.50 per
	 * MTok), verified on 2026-10-23 at
	 * https://platform.claude.com/docs/en/models/haiku-5-5/overview — the
	 * previous default claude-haiku-4-5-20251001 is legacy and past its
	 * retirement floor ("not sooner than October 15, 2026").
	 *
	 * Since the 4.6 generation every Claude API id, dateless included, is a
	 * pinned snapshot, so this id cannot silently change behind an alias.
	 */
	private const DEFAULT_MODEL = 'claude-haiku-5-5';

	/**
	 * Constructor.
	 *
	 * @param ApiKeyStore       $keys      API key storage (sole access to the decrypted key).
	 * @param Settings          $settings  Plugin settings (chosen model).
	 * @param PromptBuilder     $prompts   Shared prompt builder.
	 * @param ResponseValidator $validator Shared response validator.
	 */
	public function __construct(
		private ApiKeyStore $keys,
		private Settings $settings,
		private PromptBuilder $prompts,
		private ResponseValidator $validator
	) {}

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'claude';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Anthropic Claude';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array(
			self::DEFAULT_MODEL => 'Claude Haiku 5.5 — fast and cost-effective (recommended)',
			'claude-sonnet-5-5' => 'Claude Sonnet 5.5 — best speed/quality balance',
			'claude-opus-5-5'   => 'Claude Opus 5.5 — highest quality',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function limits(): RateLimits {
		return new RateLimits( 50, 30, 12000 );
	}

	/**
	 * {@inheritDoc}
	 */
	public function key_url(): string {
		return 'https://console.anthropic.com/settings/keys';
	}

	/**
	 * {@inheritDoc}
	 */
	public function region(): string {
		return 'non-eu';
	}

	/**
	 * {@inheritDoc}
	 */
	public function has_free_tier(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, string> $segments Segment hash => source text.
	 * @param string                $source   Source language code.
	 * @param string                $target   Target language code.
	 * @param Glossary              $glossary Glossary, tone and site context.
	 * @return array<string, string> Segment hash => raw translated text.
	 * @throws ProviderException On HTTP, quota or format errors.
	 */
	public function translate_batch( array $segments, string $source, string $target, Glossary $glossary ): array {
		if ( array() === $segments ) {
			return array();
		}

		$body = array(
			'model'      => $this->model(),
			'max_tokens' => $this->max_tokens_for( $segments ),
			'system'     => $this->prompts->system_prompt( $source, $target, $glossary ),
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $this->prompts->user_prompt( $this->prompts->number( $segments ) ),
				),
			),
		);

		// One extra attempt is allowed when the response is not valid strict JSON.
		$attempts = 0;
		while ( true ) {
			++$attempts;
			$raw       = $this->request( $body );
			$validated = $this->validator->validate( $this->text_from_response( $raw ), array_keys( $this->prompts->number( $segments ) ) );
			if ( null !== $validated ) {
				return $this->map_to_hashes( $validated, array_keys( $segments ) );
			}
			if ( $attempts >= 2 ) {
				throw new ProviderException( 'The provider returned an invalid response format.', 0, false );
			}
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function test_key(): Result {
		$body = array(
			'model'      => $this->model(),
			'max_tokens' => 1,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => 'ping',
				),
			),
		);

		try {
			$this->request( $body );
		} catch ( ProviderException $e ) {
			if ( 0 === $e->http_code ) {
				return Result::error( __( 'Connection to Anthropic failed.', 'omnium-translate' ), 0 );
			}
			return Result::error(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Key test failed (HTTP %d).', 'omnium-translate' ),
					$e->http_code
				),
				$e->http_code
			);
		}

		return Result::ok( __( 'Key works.', 'omnium-translate' ) );
	}

	/**
	 * Chosen model, or the recommended default.
	 */
	private function model(): string {
		$chosen = (string) $this->settings->get( 'model' );
		if ( isset( $this->models()[ $chosen ] ) ) {
			return $chosen;
		}
		return self::DEFAULT_MODEL;
	}

	/**
	 * Send a Messages API request and return its raw body.
	 *
	 * @param array<string, mixed> $body Request body.
	 * @return string Raw JSON response body.
	 * @throws ProviderException Generic message with the HTTP code only: never the raw response or key.
	 */
	private function request( array $body ): string {
		$key = $this->keys->get( 'claude' );
		if ( null === $key || '' === $key ) {
			throw new ProviderException( 'No API key is configured for this provider.', 0, false );
		}

		$encoded = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			throw new ProviderException( 'The request could not be encoded.', 0, false );
		}

		$response = wp_remote_post(
			self::ENDPOINT,
			array(
				'headers'   => array(
					'x-api-key'         => $key,
					'anthropic-version' => self::API_VERSION,
					'content-type'      => 'application/json',
				),
				'body'      => $encoded,
				'timeout'   => 60,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new ProviderException( 'The request to the provider failed.', 0, true );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $code || $code >= 500 ) {
			$exception = new ProviderException( 'The provider returned a retryable HTTP error.', $code, true );
			throw $exception;
		}
		if ( 200 !== $code ) {
			$exception = new ProviderException( 'The provider returned an HTTP error.', $code, false );
			throw $exception;
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Concatenate the text blocks of a Messages API response.
	 *
	 * @param string $raw Raw JSON response body.
	 * @return string Extracted text, empty string when unreadable.
	 */
	private function text_from_response( string $raw ): string {
		try {
			$decoded = json_decode( $raw, true, 64, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			return '';
		}
		if ( ! is_array( $decoded ) || ! isset( $decoded['content'] ) || ! is_array( $decoded['content'] ) ) {
			return '';
		}

		$texts = array();
		foreach ( $decoded['content'] as $block ) {
			if ( is_array( $block ) && isset( $block['type'], $block['text'] ) && 'text' === $block['type'] && is_string( $block['text'] ) ) {
				$texts[] = $block['text'];
			}
		}
		return implode( "\n", $texts );
	}

	/**
	 * Map validated numbers back to segment hashes.
	 *
	 * @param array<int, string> $validated Number => translated text.
	 * @param list<string>       $hashes    Hashes in sent order.
	 * @return array<string, string>
	 */
	private function map_to_hashes( array $validated, array $hashes ): array {
		$mapped = array();
		foreach ( $validated as $number => $text ) {
			if ( isset( $hashes[ $number - 1 ] ) ) {
				$mapped[ $hashes[ $number - 1 ] ] = $text;
			}
		}
		return $mapped;
	}

	/**
	 * Output token budget for a batch: roughly the input size, with headroom
	 * for JSON syntax, translation expansion and adaptive thinking.
	 *
	 * @param array<string, string> $segments Segment hash => source text.
	 */
	private function max_tokens_for( array $segments ): int {
		$chars = 0;
		foreach ( $segments as $text ) {
			$chars += strlen( $text );
		}
		$tokens = (int) ceil( $chars / 2 ) + 2048;
		return (int) max( 2048, min( 30000, $tokens ) );
	}
}
