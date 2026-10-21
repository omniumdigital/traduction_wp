<?php
/**
 * Segment normalization and hashing.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for segment identity. Render, Queue and Store must all use it,
 * otherwise "never pay twice" breaks.
 */
final class SegmentKey {

	/**
	 * Normalize a source text: Unicode NFC, collapsed whitespace, trimmed.
	 *
	 * @param string $text Raw text.
	 */
	public static function normalize( string $text ): string {
		if ( class_exists( \Normalizer::class ) ) {
			$normalized = \Normalizer::normalize( $text, \Normalizer::FORM_C );
			if ( false !== $normalized ) {
				$text = $normalized;
			}
		}
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * Hash of a normalized text in a source language.
	 *
	 * @param string $source_lang Source language code.
	 * @param string $normalized  Output of normalize().
	 */
	public static function hash( string $source_lang, string $normalized ): string {
		return hash( 'sha256', $source_lang . "\n" . $normalized );
	}
}
