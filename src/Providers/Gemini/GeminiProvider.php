<?php
/**
 * Google Gemini provider (generateContent API).
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers\Gemini;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\Glossary;
use Omnium\Translate\Providers\ErrorClassifier;
use Omnium\Translate\Providers\ProviderException;
use Omnium\Translate\Providers\ProviderInterface;
use Omnium\Translate\Providers\PromptBuilder;
use Omnium\Translate\Providers\RateLimits;
use Omnium\Translate\Providers\ResponseValidator;
use Omnium\Translate\Providers\Result;
use Omnium\Translate\Security\ApiKeyStore;

defined( 'ABSPATH' ) || exit;

/**
 * Calls the Gemini API generateContent method over HTTPS with the client's own key.
 *
 * API facts verified against the official documentation on 2026-10-23:
 * - Endpoint: POST https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent
 *   (hard-coded base, HTTPS only). The model segment only ever comes from the
 *   fixed models() whitelist below, never from user input.
 *   Source: https://ai.google.dev/api/generate-content
 * - Authentication: x-goog-api-key header (the key must not travel in the URL,
 *   where it could leak into logs). Source: https://ai.google.dev/gemini-api/docs/api-key
 * - Body: contents[].parts[].text, systemInstruction.parts[].text,
 *   generationConfig.maxOutputTokens and generationConfig.responseMimeType.
 * - Response text: candidates[].content.parts[].text, skipping parts flagged
 *   as model thoughts ("thought": true).
 * - Models and prices: https://ai.google.dev/gemini-api/docs/models and
 *   https://ai.google.dev/gemini-api/docs/pricing
 */
final class GeminiProvider implements ProviderInterface {

	private const ENDPOINT_TEMPLATE = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent';

	/**
	 * Default model: the fastest and most cost-effective current stable text
	 * model, explicitly "optimized for high-volume agentic tasks, translation,
	 * and simple data processing".
	 *
	 * Verified on 2026-10-23 at
	 * https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite
	 * ($0.30 / $2.50 per MTok, paid tier) and
	 * https://ai.google.dev/gemini-api/docs/pricing
	 */
	private const DEFAULT_MODEL = 'gemini-3.5-flash-lite';

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
		return 'gemini';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Google Gemini';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array(
			self::DEFAULT_MODEL => 'Gemini 3.5 Flash-Lite — fast and cost-effective (recommended)',
			'gemini-3.8-flash'  => 'Gemini 3.8 Flash — best speed/quality balance',
			'gemini-4-argon'    => 'Gemini 4 Argon — highest quality',
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
		return 'https://aistudio.google.com/apikey';
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
		return true;
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
		$numbered = $this->prompts->number( $segments );

		$body = array(
			'contents'          => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => $this->prompts->user_prompt( $numbered ) ),
					),
				),
			),
			'systemInstruction' => array(
				'parts' => array(
					array( 'text' => $this->prompts->system_prompt( $source, $target, $glossary ) ),
				),
			),
			'generationConfig'  => array(
				'maxOutputTokens'  => $this->max_tokens_for( $segments ),
				'responseMimeType' => 'application/json',
			),
		);

		// One extra attempt is allowed when the response is not valid strict JSON.
		$attempts = 0;
		while ( true ) {
			++$attempts;
			$raw       = $this->request( $body );
			$validated = $this->validator->validate( $this->text_from_response( $raw ), array_keys( $numbered ), $numbered, $glossary );
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
			'contents'         => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => 'ping' ),
					),
				),
			),
			'generationConfig' => array(
				'maxOutputTokens' => 16,
			),
		);

		try {
			$this->request( $body );
		} catch ( ProviderException $e ) {
			if ( 0 === $e->http_code ) {
				return Result::error( __( 'Connection to Google failed.', 'omnium-translate' ), 0 );
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
	 * Chosen model, or the recommended default. The value only ever feeds the
	 * hard-coded endpoint template, so it must come from the whitelist.
	 */
	private function model(): string {
		$chosen = (string) $this->settings->get( 'model' );
		if ( isset( $this->models()[ $chosen ] ) ) {
			return $chosen;
		}
		return self::DEFAULT_MODEL;
	}

	/**
	 * Send a generateContent request and return its raw body.
	 *
	 * @param array<string, mixed> $body Request body.
	 * @return string Raw JSON response body.
	 * @throws ProviderException Generic message with the HTTP code only: never the raw response or key.
	 */
	private function request( array $body ): string {
		$key = $this->keys->get( 'gemini' );
		if ( null === $key || '' === $key ) {
			throw new ProviderException( 'No API key is configured for this provider.', 0, false );
		}

		$encoded = wp_json_encode( $body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $encoded ) {
			throw new ProviderException( 'The request could not be encoded.', 0, false );
		}

		$response = wp_remote_post(
			sprintf( self::ENDPOINT_TEMPLATE, rawurlencode( $this->model() ) ),
			array(
				'headers'   => array(
					'x-goog-api-key' => $key,
					'content-type'   => 'application/json',
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
			// Fixed local classification only: the raw body is untrusted and never stored.
			$exception = new ProviderException( ErrorClassifier::classify( (string) wp_remote_retrieve_body( $response ), $code ), $code, false );
			throw $exception;
		}

		return (string) wp_remote_retrieve_body( $response );
	}

	/**
	 * Concatenate the text parts of the first candidate, skipping thought parts.
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
		if ( ! is_array( $decoded ) || ! isset( $decoded['candidates'][0]['content']['parts'] ) || ! is_array( $decoded['candidates'][0]['content']['parts'] ) ) {
			return '';
		}

		$texts = array();
		foreach ( $decoded['candidates'][0]['content']['parts'] as $part ) {
			if ( is_array( $part ) && isset( $part['text'] ) && is_string( $part['text'] ) && true !== ( $part['thought'] ?? false ) ) {
				$texts[] = $part['text'];
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
	 * for JSON syntax, translation expansion and thinking tokens.
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
