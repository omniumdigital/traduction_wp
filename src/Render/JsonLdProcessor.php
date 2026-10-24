<?php
/**
 * Structured data text and URL translation.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Render;

use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Security\Sanitizer;
use Omnium\Translate\Store\SegmentKey;

defined( 'ABSPATH' ) || exit;

/** Processes only explicitly allowed JSON-LD properties. */
final class JsonLdProcessor {

	private const TEXT_FIELDS = array( 'name', 'description', 'headline', 'alternativeHeadline', 'caption', 'text', 'reviewBody', 'articleBody' );
	private const URL_FIELDS  = array( 'url', '@id', 'item', 'mainEntityOfPage' );

	/**
	 * Extract human text from a valid JSON-LD block.
	 *
	 * @param string    $json        JSON source.
	 * @param string    $source_lang Source language.
	 * @param PiiFilter $pii_filter  Personal-data filter.
	 * @return array<string, string>
	 */
	public static function segments( string $json, string $source_lang, PiiFilter $pii_filter ): array {
		$data = json_decode( $json );
		if ( JSON_ERROR_NONE !== json_last_error() || ( ! is_object( $data ) && ! is_array( $data ) ) ) {
			return array();
		}
		$segments = array();
		self::walk_segments( $data, $source_lang, $pii_filter, $segments );
		return $segments;
	}

	/**
	 * Rebuild a valid block using stored translations only.
	 *
	 * @param string                $json        JSON source.
	 * @param string                $source_lang Source language.
	 * @param string                $target_lang Target language.
	 * @param array<string, string> $stored      Translation map.
	 * @param UrlTranslator         $urls        Internal URL translator.
	 */
	public static function translate( string $json, string $source_lang, string $target_lang, array $stored, UrlTranslator $urls ): string {
		$data = json_decode( $json );
		if ( JSON_ERROR_NONE !== json_last_error() || ( ! is_object( $data ) && ! is_array( $data ) ) ) {
			return $json;
		}
		self::walk_translations( $data, $source_lang, $target_lang, $stored, $urls );
		$encoded = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
		return is_string( $encoded ) ? $encoded : $json;
	}

	/**
	 * Collect allowed fields recursively.
	 *
	 * @param mixed                 $data        JSON value.
	 * @param string                $source_lang Source language.
	 * @param PiiFilter             $pii_filter  Personal-data filter.
	 * @param array<string, string> $segments    Accumulator.
	 */
	private static function walk_segments( mixed $data, string $source_lang, PiiFilter $pii_filter, array &$segments ): void {
		if ( is_array( $data ) ) {
			foreach ( $data as $child ) {
				self::walk_segments( $child, $source_lang, $pii_filter, $segments );
			}
			return;
		}
		if ( ! is_object( $data ) ) {
			return;
		}
		foreach ( $data as $key => $value ) {
			if ( in_array( $key, self::TEXT_FIELDS, true ) && is_string( $value ) ) {
				$normalized = SegmentKey::normalize( $value );
				if ( '' !== $normalized && 1 === preg_match( '/[\p{L}\p{N}]/u', $normalized ) && ! $pii_filter->contains_pii( $normalized ) ) {
					$segments[ SegmentKey::hash( $source_lang, $normalized ) ] = $normalized;
				}
			} elseif ( is_object( $value ) || is_array( $value ) ) {
				self::walk_segments( $value, $source_lang, $pii_filter, $segments );
			}
		}
	}

	/**
	 * Apply stored text and local URL translations recursively.
	 *
	 * @param mixed                 $data        JSON value.
	 * @param string                $source_lang Source language.
	 * @param string                $target_lang Target language.
	 * @param array<string, string> $stored      Translation map.
	 * @param UrlTranslator         $urls        Internal URL translator.
	 */
	private static function walk_translations( mixed &$data, string $source_lang, string $target_lang, array $stored, UrlTranslator $urls ): void {
		if ( is_array( $data ) ) {
			foreach ( $data as &$child ) {
				self::walk_translations( $child, $source_lang, $target_lang, $stored, $urls );
			}
			return;
		}
		if ( ! is_object( $data ) ) {
			return;
		}
		foreach ( $data as $key => &$value ) {
			if ( 'inLanguage' === $key && is_string( $value ) ) {
				$value = $target_lang;
			} elseif ( in_array( $key, self::TEXT_FIELDS, true ) && is_string( $value ) ) {
				$hash = SegmentKey::hash( $source_lang, $value );
				if ( isset( $stored[ $hash ] ) ) {
					$value = Sanitizer::plain_text( $stored[ $hash ] );
				}
			} elseif ( in_array( $key, self::URL_FIELDS, true ) && is_string( $value ) ) {
				$value = $urls->translate( $value, $target_lang );
			} elseif ( is_object( $value ) || is_array( $value ) ) {
				self::walk_translations( $value, $source_lang, $target_lang, $stored, $urls );
			}
		}
	}
}
