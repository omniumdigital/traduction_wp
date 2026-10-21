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
		$raw = trim( $raw );

		// Strict JSON only: reject code fences, prefixed prose, BOM, anything but an object.
		if ( '' === $raw || '{' !== $raw[0] || '}' !== substr( $raw, -1 ) ) {
			return null;
		}

		try {
			$decoded = json_decode( $raw, true, 64, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			return null;
		}

		if ( ! is_array( $decoded ) ) {
			return null;
		}

		$allowed = array();
		foreach ( $expected as $number ) {
			$allowed[ (string) $number ] = (int) $number;
		}

		$valid = array();
		foreach ( $decoded as $key => $value ) {
			// Keep only ids that were sent; reject everything else.
			if ( ! is_string( $key ) || ! ctype_digit( $key ) || ! isset( $allowed[ $key ] ) ) {
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
