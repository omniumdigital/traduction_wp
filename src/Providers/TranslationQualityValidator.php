<?php
/**
 * Deterministic quality checks for AI translations.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

use Omnium\Translate\Glossary\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Rejects structurally unsafe or clearly unusable translations before they
 * can be stored. Linguistic style remains reviewable by a human; invariants
 * such as placeholders, markup and glossary rules are enforced here.
 */
final class TranslationQualityValidator {

	/**
	 * Whether a source/translation pair satisfies every deterministic rule.
	 *
	 * @param string   $source      Original source segment.
	 * @param string   $translation Candidate translation.
	 * @param Glossary $glossary    Mandatory terminology rules.
	 * @param bool     $allow_identical Accept a translation identical to the source
	 *                                  (text already in the target language).
	 */
	public function is_valid( string $source, string $translation, Glossary $glossary, bool $allow_identical = false ): bool {
		$source      = trim( $source );
		$translation = trim( $translation );
		if ( '' === $translation || preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $translation ) ) {
			return false;
		}

		if ( $this->tokens( $source ) !== $this->tokens( $translation ) ) {
			return false;
		}
		if ( $this->html_signature( $source ) !== $this->html_signature( $translation ) ) {
			return false;
		}

		foreach ( $glossary->keep as $term ) {
			if ( '' !== $term && $this->contains_term( $source, $term ) && ! $this->contains( $translation, $term ) ) {
				return false;
			}
		}
		foreach ( $glossary->translate as $term => $required ) {
			if ( '' !== $term && '' !== $required && $this->contains_term( $source, $term ) && ! $this->contains( $translation, $required ) ) {
				return false;
			}
		}

		$source_text      = trim( strip_tags( $source ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure validator runs in isolation and needs no WordPress state.
		$translation_text = trim( strip_tags( $translation ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- Pure validator runs in isolation and needs no WordPress state.
		$source_length    = $this->length( $source_text );
		$target_length    = $this->length( $translation_text );
		if ( $source_length >= 20 && ( $target_length < (int) floor( $source_length * 0.15 ) || $target_length > (int) ceil( $source_length * 6 ) ) ) {
			return false;
		}

		$word_count = preg_match_all( '/[\p{L}\p{N}]+/u', $source_text );
		if ( ! $allow_identical && $source_length >= 32 && is_int( $word_count ) && $word_count >= 5 && $this->fold( $source_text ) === $this->fold( $translation_text ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Variables and absolute URLs whose exact values must survive translation.
	 * Ordering may change because grammar sometimes requires numbered variables
	 * to move, but the multiset must remain identical.
	 *
	 * @param string $text Text to inspect.
	 * @return list<string>
	 */
	private function tokens( string $text ): array {
		preg_match_all(
			'~%(?:\d+\$)?[-+0-9\'.]*[bcdeEfFgGosuxX]|\{\{[^{}]+\}\}|\{[A-Za-z_][A-Za-z0-9_.-]*\}|https?://[^\s<>"\']+~u',
			$text,
			$matches
		);
		$tokens = array_map( 'strval', $matches[0] );
		sort( $tokens, SORT_STRING );
		return $tokens;
	}

	/**
	 * Build the ordered exact tag signature, including attributes.
	 *
	 * @param string $text Text to inspect.
	 * @return list<string>
	 */
	private function html_signature( string $text ): array {
		preg_match_all( '~<\s*/?\s*[a-z][a-z0-9]*\b[^>]*>~i', $text, $matches );
		$signature = array();
		foreach ( $matches[0] as $tag ) {
			$normalized  = preg_replace( '/\s+/u', ' ', trim( $tag ) );
			$signature[] = is_string( $normalized ) ? $normalized : $tag;
		}
		return $signature;
	}

	/**
	 * Match a glossary term without treating it as a substring of a word.
	 *
	 * @param string $text Text to search.
	 * @param string $term Glossary term.
	 */
	private function contains_term( string $text, string $term ): bool {
		$pattern = '~(?<![\p{L}\p{N}])' . preg_quote( $term, '~' ) . '(?![\p{L}\p{N}])~iu';
		return 1 === preg_match( $pattern, $text );
	}

	/**
	 * Case-insensitive Unicode substring check.
	 *
	 * @param string $haystack Text to search.
	 * @param string $needle   Expected fragment.
	 */
	private function contains( string $haystack, string $needle ): bool {
		return str_contains( $this->fold( $haystack ), $this->fold( $needle ) );
	}

	/**
	 * Unicode-aware lowercase where available.
	 *
	 * @param string $text Text to fold.
	 */
	private function fold( string $text ): string {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Unicode-aware character length where available.
	 *
	 * @param string $text Text to measure.
	 */
	private function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}
}
