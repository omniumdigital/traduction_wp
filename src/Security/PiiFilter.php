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
 * TODO(jalon 2): detect emails, FR/international phone numbers, IBAN, card numbers (Luhn),
 * with one unit test per pattern (see privacy-audit skill, step 2).
 */
final class PiiFilter {

	/**
	 * Whether the text contains personal data.
	 *
	 * @param string $text Source segment.
	 */
	public function contains_pii( string $text ): bool {
		unset( $text );
		return false;
	}
}
