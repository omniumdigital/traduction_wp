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
 * Detects emails, FR/international phone numbers, IBANs and payment-card
 * numbers before a segment can be sent to an external provider.
 */
final class PiiFilter {

	/**
	 * Whether the text contains personal data.
	 *
	 * @param string $text Source segment.
	 */
	public function contains_pii( string $text ): bool {
		if ( 1 === preg_match( '/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,63}\b/iu', $text ) ) {
			return true;
		}

		if ( 1 === preg_match( '/(?<!\w)0[1-9](?:[ .\-]?\d{2}){4}(?!\w)/u', $text )
			|| 1 === preg_match( '/(?<!\w)(?:\+|00)\d(?:[ ().\-]*\d){7,14}(?!\w)/u', $text ) ) {
			return true;
		}

		if ( $this->contains_valid_iban( $text ) || $this->contains_valid_card( $text ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Detect a checksum-valid IBAN candidate.
	 *
	 * @param string $text Source segment.
	 */
	private function contains_valid_iban( string $text ): bool {
		$matched = preg_match_all( '/\b[A-Z]{2}\d{2}(?:[ \x{00A0}\-]?[A-Z0-9]){11,30}\b/iu', $text, $matches );
		if ( ! is_int( $matched ) || 0 === $matched ) {
			return false;
		}

		foreach ( $matches[0] as $candidate ) {
			$iban = strtoupper( (string) preg_replace( '/[^A-Z0-9]/i', '', $candidate ) );
			if ( strlen( $iban ) < 15 || strlen( $iban ) > 34 ) {
				continue;
			}
			$rearranged = substr( $iban, 4 ) . substr( $iban, 0, 4 );
			$remainder  = 0;
			foreach ( str_split( $rearranged ) as $character ) {
				$digits = ctype_alpha( $character ) ? (string) ( ord( $character ) - 55 ) : $character;
				foreach ( str_split( $digits ) as $digit ) {
					$remainder = ( ( $remainder * 10 ) + (int) $digit ) % 97;
				}
			}
			if ( 1 === $remainder ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect a card-number candidate that passes the Luhn checksum.
	 *
	 * @param string $text Source segment.
	 */
	private function contains_valid_card( string $text ): bool {
		$matched = preg_match_all( '/(?<!\d)(?:\d[ \-]?){12,18}\d(?!\d)/', $text, $matches );
		if ( ! is_int( $matched ) || 0 === $matched ) {
			return false;
		}

		foreach ( $matches[0] as $candidate ) {
			$digits = (string) preg_replace( '/\D/', '', $candidate );
			$sum    = 0;
			$double = false;
			for ( $index = strlen( $digits ) - 1; $index >= 0; --$index ) {
				$digit = (int) $digits[ $index ];
				if ( $double ) {
					$digit *= 2;
					if ( $digit > 9 ) {
						$digit -= 9;
					}
				}
				$sum   += $digit;
				$double = ! $double;
			}
			if ( 0 === $sum % 10 ) {
				return true;
			}
		}

		return false;
	}
}
