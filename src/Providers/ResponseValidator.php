<?php
/**
 * Strict validation of AI provider responses.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

use Omnium\Translate\Glossary\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * A provider response is untrusted input. Only a strict JSON object whose
 * keys are among the sent numbers is accepted; everything else is rejected.
 */
final class ResponseValidator {
	/**
	 * Deterministic structural and terminology checks.
	 *
	 * @var TranslationQualityValidator
	 */
	private TranslationQualityValidator $quality;

	/**
	 * Build the strict response validator.
	 *
	 * @param TranslationQualityValidator|null $quality Quality validator override.
	 */
	public function __construct( ?TranslationQualityValidator $quality = null ) {
		$this->quality = $quality ?? new TranslationQualityValidator();
	}

	/**
	 * Validate a raw provider response.
	 *
	 * @param string                  $raw      Raw response body text.
	 * @param list<int>               $expected Numbers that were sent (1-based).
	 * @param array<int, string>|null $sources  Numbered source segments for quality validation.
	 * @param Glossary|null           $glossary Glossary rules to enforce.
	 * @return array<int, string>|null Number => translation, or null when the response is invalid.
	 */
	public function validate( string $raw, array $expected, ?array $sources = null, ?Glossary $glossary = null ): ?array {
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
			// The contract is all-or-nothing: unknown, missing or malformed entries
			// trigger the provider's one allowed format/quality retry.
			$key = (string) $key;
			if ( ! ctype_digit( $key ) || ! isset( $allowed[ $key ] ) ) {
				return null;
			}
			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				return null;
			}
			$number = $allowed[ $key ];
			if ( null !== $sources && null !== $glossary && isset( $sources[ $number ] ) && ! $this->quality->is_valid( $sources[ $number ], $value, $glossary ) ) {
				return null;
			}
			$valid[ $number ] = $value;
		}

		if ( count( $valid ) !== count( $allowed ) ) {
			return null;
		}
		ksort( $valid );

		return $valid;
	}
}
