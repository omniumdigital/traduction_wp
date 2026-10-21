<?php
/**
 * Internal URL language translator.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Adds or removes a target-language prefix on same-site URLs only.
 */
final class UrlTranslator {

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( private Settings $settings ) {}

	/**
	 * Return a page URL for a supported language.
	 *
	 * @param string $url  Absolute or root-relative URL.
	 * @param string $lang Source or enabled target language.
	 */
	public function translate( string $url, string $lang ): string {
		$allowed = array_merge( array( $this->settings->source_lang() ), $this->settings->target_langs() );
		if ( ! in_array( $lang, $allowed, true ) || '' === $url || str_starts_with( $url, '#' ) ) {
			return $url;
		}

		$home        = wp_parse_url( home_url( '/' ) );
		$is_relative = str_starts_with( $url, '/' ) && ! str_starts_with( $url, '//' );
		$parts       = wp_parse_url( $is_relative ? home_url( $url ) : $url );
		if ( false === $parts || false === $home || ! isset( $parts['host'], $home['host'] ) ) {
			return $url;
		}
		$scheme_matches = ( $parts['scheme'] ?? '' ) === ( $home['scheme'] ?? '' );
		$port_matches   = ( $parts['port'] ?? null ) === ( $home['port'] ?? null );
		if ( ! $scheme_matches || ! $port_matches || 0 !== strcasecmp( (string) $parts['host'], (string) $home['host'] ) ) {
			return $url;
		}

		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		if ( str_starts_with( $path, '/wp-admin' ) || str_starts_with( $path, '/wp-json' ) ) {
			return $url;
		}
		foreach ( $this->settings->target_langs() as $target ) {
			$prefix = '/' . rawurlencode( $target );
			if ( $path === $prefix || str_starts_with( $path, $prefix . '/' ) ) {
				$path = substr( $path, strlen( $prefix ) );
				$path = '' === $path ? '/' : $path;
				break;
			}
		}
		if ( $lang !== $this->settings->source_lang() ) {
			$path = '/' . rawurlencode( $lang ) . ( '/' === $path ? '/' : '/' . ltrim( $path, '/' ) );
		}

		$query    = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$fragment = isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '';
		if ( $is_relative ) {
			return $path . $query . $fragment;
		}

		$authority = (string) $parts['scheme'] . '://' . (string) $parts['host'];
		if ( isset( $parts['port'] ) ) {
			$authority .= ':' . (int) $parts['port'];
		}
		return $authority . $path . $query . $fragment;
	}
}
