<?php
/**
 * Sanitization of untrusted translated text (AI output and manual edits).
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Every translation goes through this class before storage and before output,
 * whatever its origin or the role of the editor.
 */
final class Sanitizer {

	/**
	 * Inline tags allowed inside a translated text segment.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function allowed_inline_tags(): array {
		return array(
			'strong' => array(),
			'em'     => array(),
			'b'      => array(),
			'i'      => array(),
			'br'     => array(),
			'span'   => array(),
			'a'      => array( 'href' => true ),
		);
	}

	/**
	 * Sanitize a translated text segment that may contain whitelisted inline markup.
	 *
	 * @param string $text Untrusted text.
	 */
	public static function inline_html( string $text ): string {
		return wp_kses( $text, self::allowed_inline_tags(), array( 'http', 'https', 'mailto', 'tel' ) );
	}

	/**
	 * Sanitize plain text (no markup allowed), e.g. for attributes and meta.
	 * Escaping for the output context (esc_attr) is still required at render time.
	 *
	 * @param string $text Untrusted text.
	 */
	public static function plain_text( string $text ): string {
		return sanitize_text_field( $text );
	}
}
