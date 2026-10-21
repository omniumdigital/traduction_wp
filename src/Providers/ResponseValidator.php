<?php
/**
 * Strict validation of AI provider responses.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * A provider response is untrusted input. Only a strict JSON object whose
 * keys are among the sent numbers is accepted; everything else is rejected.
 */
final class ResponseValidator {

	/**
	 * Validate a raw provider response.
	 *
	 * @param string    $raw      Raw response body text.
	 * @param list<int> $expected Numbers that were sent (1-based).
	 * @return array<int, string>|null Number => translation, or null when the response is invalid.
	 */
	public function validate( string $raw, array $expected ): ?array {
		if ( str_contains( $raw, "\0" ) ) {
			return null;
		}
		$raw = trim( $raw );

		// Strict JSON only: reject code fences, prefixed prose, BOM, anything but an object.
		if ( '' === $raw || '{' !== $raw[0] || '}' !== substr( $raw, -1 ) ) {
			return null;
		}

		try {
			$decoded = json_decode( $raw, false, 64, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			return null;
		}

		// Decode to an object so a JSON array cannot masquerade as an object.
		if ( ! $decoded instanceof \stdClass ) {
			return null;
		}

		$allowed = array();
		foreach ( $expected as $number ) {
			$allowed[ (string) $number ] = (int) $number;
		}

		$valid = array();
		foreach ( get_object_vars( $decoded ) as $key => $value ) {
			// Keep only ids that were sent; reject everything else.
			$key = (string) $key;
			if ( ! ctype_digit( $key ) || ! isset( $allowed[ $key ] ) ) {
				continue;
			}
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				continue;
			}
			$valid[ $allowed[ $key ] ] = $value;
		}

		if ( array() === $valid ) {
			return null;
		}

		return $valid;
	}
}
