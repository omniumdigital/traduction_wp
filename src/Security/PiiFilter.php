<?php
/**
 * Personal data detection before sending text to an AI provider.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Security;

defined( 'ABSPATH' ) || exit;

/**
 * A segment containing PII is excluded from translation and flagged.
 *
 * Detects common personal identifiers without retaining or reporting their value.
 */
final class PiiFilter {

	/**
	 * Whether the text contains personal data.
	 *
	 * @param string $text Source segment.
	 */
	public function contains_pii( string $text ): bool {
		return $this->contains_email( $text )
			|| $this->contains_phone( $text )
			|| $this->contains_iban( $text )
			|| $this->contains_card( $text );
	}

	/**
	 * Detect an email address embedded in text.
	 *
	 * @param string $text Source segment.
	 */
	private function contains_email( string $text ): bool {
		return 1 === preg_match( '/(?<![\p{L}\p{N}._%+\-])[\p{L}\p{N}._%+\-]+@[\p{L}\p{N}.\-]+\.[\p{L}]{2,63}(?![\p{L}\p{N}.\-])/iu', $text );
	}

	/**
	 * Detect French and explicitly international phone numbers.
	 *
	 * @param string $text Source segment.
	 */
	private function contains_phone( string $text ): bool {
		$pattern = '/(?<!\d)(?:(?:\+|00)\d{1,3}[ .()\-]*(?:\d[ .()\-]*){6,12}|0[1-9](?:[ .\-]*\d{2}){4})(?!\d)/';
		return 1 === preg_match( $pattern, $text );
	}

	/**
	 * Detect a checksum-valid IBAN.
	 *
	 * @param string $text Source segment.
	 */
	private function contains_iban( string $text ): bool {
		if ( ! preg_match_all( '/(?<![A-Z0-9])[A-Z]{2}\d{2}(?:[ ]?[A-Z0-9]){11,30}(?![A-Z0-9])/i', $text, $matches ) ) {
			return false;
		}

		foreach ( $matches[0] as $candidate ) {
			$iban = strtoupper( str_replace( ' ', '', $candidate ) );
			if ( strlen( $iban ) < 15 || strlen( $iban ) > 34 ) {
				continue;
			}
			$rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );
			$numeric    = '';
			foreach ( str_split( $rearranged ) as $character ) {
				$numeric .= ctype_alpha( $character ) ? (string) ( ord( $character ) - 55 ) : $character;
			}
			$remainder = 0;
			foreach ( str_split( $numeric ) as $digit ) {
				$remainder = ( ( $remainder * 10 ) + (int) $digit ) % 97;
			}
			if ( 1 === $remainder ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect a payment-card number only when its Luhn checksum is valid.
	 *
	 * @param string $text Source segment.
	 */
	private function contains_card( string $text ): bool {
		if ( ! preg_match_all( '/(?<!\d)(?:\d[ -]?){12,18}\d(?!\d)/', $text, $matches ) ) {
			return false;
		}

		foreach ( $matches[0] as $candidate ) {
			$digits = (string) preg_replace( '/\D/', '', $candidate );
			if ( $this->passes_luhn( $digits ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate a 13-to-19 digit number with the Luhn algorithm.
	 *
	 * @param string $digits Numeric candidate.
	 */
	private function passes_luhn( string $digits ): bool {
		$length = strlen( $digits );
		if ( $length < 13 || $length > 19 || preg_match( '/^(\d)\1+$/', $digits ) ) {
			return false;
		}

		$sum    = 0;
		$parity = $length % 2;
		for ( $index = 0; $index < $length; ++$index ) {
			$digit = (int) $digits[ $index ];
			if ( $index % 2 === $parity ) {
				$digit *= 2;
				if ( $digit > 9 ) {
					$digit -= 9;
				}
			}
			$sum += $digit;
		}
		return 0 === $sum % 10;
	}
}
