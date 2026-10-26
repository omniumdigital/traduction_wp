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
