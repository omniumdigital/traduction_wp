<?php
/**
 * Mistral AI provider (Chat Completions API, EU regional endpoint).
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers\Mistral;

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
 * Calls the Mistral Chat Completions API over HTTPS with the client's own key.
 *
 * The EU regional endpoint https://api.eu.mistral.ai is hard-coded on purpose:
 * it guarantees that inference inputs and outputs are processed inside the
 * European Union (CLAUDE.md section 10), which is Mistral's main value for
 * this plugin. The global endpoint api.mistral.ai commits to no inference
 * location, and would make the "EU" label shown to the site owner a lie.
 * Regional inference is billed at 1.1x the standard list price.
 *
 * API facts verified against the official documentation on 2026-10-23:
 * - Endpoint: POST https://api.eu.mistral.ai/v1/chat/completions (hard-coded,
 *   HTTPS only). Source: https://docs.mistral.ai/inference/regional-inference
 * - Authentication: Authorization: Bearer <key>.
 * - Body: model, messages (system + user), max_tokens. The shape is the same
 *   as on the global endpoint. Source: https://docs.mistral.ai/api/endpoint/chat
 * - Response text: choices[0].message.content.
 *
 * Model availability on the EU endpoint is not published per model; only
 * mistral-medium-latest and mistral-large-latest appear in the official EU
 * examples of https://docs.mistral.ai/inference/regional-inference. Mistral
 * Small 4 (the cheapest generalist) is therefore NOT offered here: it was
 * still undocumented on the EU endpoint as of 2026-10-23. Re-check
 * https://docs.mistral.ai/inference/regional-inference at every release and
 * add it back if Mistral documents or confirms its EU availability.
 */
final class MistralProvider implements ProviderInterface {

	private const ENDPOINT = 'https://api.eu.mistral.ai/v1/chat/completions';

	/**
	 * Default model: the cheapest generalist model documented as served on
	 * the EU regional endpoint.
	 *
	 * Mistral Medium 3.5 (id mistral-medium-3-5), GA, recommended by
	 * Mistral's FAQ for most tasks. Verified on 2026-10-23 at
	 * https://docs.mistral.ai/models/mistral-medium-3-5-26-04 and
	 * https://docs.mistral.ai/inference/regional-inference (EU SDK example).
	 * A Public Preview model must never be the default, so Mistral Large 4
	 * is only the second choice.
	 */
	private const DEFAULT_MODEL = 'mistral-medium-3-5';

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
		return 'mistral';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Mistral AI';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array(
			self::DEFAULT_MODEL => 'Mistral Medium 3.5 — recommended default (EU hosted)',
			'mistral-large-4'   => 'Mistral Large 4 — highest quality (preview)',
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
		return 'https://console.mistral.ai/api-keys';
	}

	/**
	 * {@inheritDoc}
	 */
	public function region(): string {
		return 'eu';
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

		$body = array(
			'model'      => $this->model(),
			'max_tokens' => $this->max_tokens_for( $segments ),
			'messages'   => array(
				array(
					'role'    => 'system',
					'content' => $this->prompts->system_prompt( $source, $target, $glossary ),
				),
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
			'max_tokens' => 16,
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
				return Result::error( __( 'Connection to Mistral failed.', 'omnium-translate' ), 0 );
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
	 * Send a Chat Completions request and return its raw body.
	 *
	 * @param array<string, mixed> $body Request body.
	 * @return string Raw JSON response body.
	 * @throws ProviderException Generic message with the HTTP code only: never the raw response or key.
	 */
	private function request( array $body ): string {
		$key = $this->keys->get( 'mistral' );
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
					'Authorization' => 'Bearer ' . $key,
					'content-type'  => 'application/json',
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
	 * Extract the first choice message content of a Chat Completions response.
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
		if ( ! is_array( $decoded ) || ! isset( $decoded['choices'][0]['message']['content'] ) ) {
			return '';
		}

		$content = $decoded['choices'][0]['message']['content'];
		return is_string( $content ) ? $content : '';
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
	 * for JSON syntax and translation expansion.
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
