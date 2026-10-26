<?php
/**
 * Provider error classification.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Maps an HTTP error body to a fixed, local reason string.
 *
 * The provider response is untrusted: it may echo submitted content, so its
 * raw message is never stored or displayed (CLAUDE.md sections 10 and 11).
 * Only the matched fixed classification is kept, which is enough to tell the
 * administrator what to fix (model, input size, request shape) and lets the
 * queue react — e.g. split a batch rejected as too large.
 */
final class ErrorClassifier {

	/** The request input or output exceeds what the model accepts. */
	public const TOO_LARGE = 'Request rejected: input or output too large for the model.';

	/** The configured model does not exist or is not available. */
	public const BAD_MODEL = 'Request rejected: unknown or unavailable model.';

	/** The request body does not match what the provider expects. */
	public const BAD_FORMAT = 'Request rejected: invalid request format.';

	/** The key was refused (quota plan, wrong scope, disabled). */
	public const KEY_REFUSED = 'Request rejected: the key was not accepted for this request.';

	/** The account has no credit, no billing set up or no quota left. */
	public const BILLING = 'Request rejected: no credit or quota left on the provider account.';

	/** The provider does not serve the server's location. */
	public const REGION = 'Request rejected: the provider does not serve this server location.';

	/** No known pattern matched. */
	public const UNKNOWN = 'Request rejected by the provider.';

	/**
	 * Classify a provider error body into one fixed reason string.
	 *
	 * @param string $raw_body Raw error response body, never persisted.
	 * @param int    $http_code HTTP status code.
	 */
	public static function classify( string $raw_body, int $http_code ): string {
		$text = self::flatten( $raw_body );

		if ( 401 === $http_code || 403 === $http_code ) {
			return self::KEY_REFUSED;
		}
		if ( 402 === $http_code || preg_match( '/(credit balance|billing|insufficient_quota|exceeded your current quota|payment required|purchase credits)/i', $text ) ) {
			return self::BILLING;
		}
		if ( preg_match( '/(api key not valid|api_key_invalid|invalid api key|invalid x-api-key|permission_denied)/i', $text ) ) {
			return self::KEY_REFUSED;
		}
		if ( preg_match( '/(location is not supported|unsupported_country|country, region, or territory not supported)/i', $text ) ) {
			return self::REGION;
		}
		if ( preg_match( '/\b(too (large|long|big)|context length|max_tokens|token limit|request entity|payload too large|input.*too long)\b/i', $text ) ) {
			return self::TOO_LARGE;
		}
		if ( preg_match( '/(no such model|unknown model|invalid model|model.{0,60}(does not exist|not found|not available|not supported|deprecated))/i', $text ) ) {
			return self::BAD_MODEL;
		}
		if ( preg_match( '/\b(invalid request|malformed|bad request|invalid json|schema violation|unexpected field|unsupported parameter|unsupported value|empty (field|message|string))\b/i', $text ) ) {
			return self::BAD_FORMAT;
		}
		return self::UNKNOWN;
	}

	/**
	 * Provider machine error code (e.g. "invalid_request_error", "INVALID_ARGUMENT").
	 *
	 * Only identifier-shaped values from code/type/status fields are kept, so
	 * the free-text message (which may echo submitted content) never leaks;
	 * the code is what makes an otherwise unknown rejection diagnosable.
	 *
	 * @param string $raw_body Raw error response body, never persisted.
	 * @return string Sanitized code, empty string when none.
	 */
	public static function provider_code( string $raw_body ): string {
		$decoded = json_decode( $raw_body, true );
		if ( ! is_array( $decoded ) ) {
			return '';
		}
		$error = isset( $decoded['error'] ) && is_array( $decoded['error'] ) ? $decoded['error'] : array();
		$parts = array();
		foreach ( array( $error['status'] ?? null, $error['type'] ?? null, $error['code'] ?? null, $decoded['type'] ?? null, $decoded['code'] ?? null ) as $candidate ) {
			if ( is_int( $candidate ) ) {
				$candidate = (string) $candidate;
			}
			if ( is_string( $candidate ) && 1 === preg_match( '/^[A-Za-z0-9_.:-]{1,64}$/', $candidate ) && ! in_array( $candidate, $parts, true ) ) {
				$parts[] = $candidate;
			}
		}
		return implode( '/', array_slice( $parts, 0, 3 ) );
	}

	/**
	 * Flatten a JSON error body to its message-ish text for matching only.
	 *
	 * @param string $raw_body Raw body.
	 */
	private static function flatten( string $raw_body ): string {
		$decoded = json_decode( $raw_body, true );
		if ( is_array( $decoded ) ) {
			$candidates = array( $decoded['message'] ?? null, $decoded['error']['message'] ?? null, $decoded['error']['type'] ?? null );
			$messages   = array_filter( $candidates, 'is_string' );
			if ( array() !== $messages ) {
				return implode( ' ', $messages );
			}
		}
		return $raw_body;
	}
}
