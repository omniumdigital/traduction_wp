<?php
/** Optional language preference redirect.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;

defined( 'ABSPATH' ) || exit;

/** Redirects only an external arrival at the unprefixed source homepage. */
final class LanguagePreference {
	/** Constructor.
	 *
	 * @param Settings        $settings Site settings.
	 * @param CurrentLanguage $language Current language.
	 * @param UrlTranslator   $urls URL translator.
	 */
	public function __construct( private Settings $settings, private CurrentLanguage $language, private UrlTranslator $urls ) {}

	/** Apply a configured functional preference. */
	public function redirect(): void {
		if ( ! (bool) $this->settings->get( 'pref_cookie' ) || $this->language->is_translated() || ! is_front_page() || is_admin() ) {
			return;
		}
		$cookie = isset( $_COOKIE['omtr_lang'] ) ? sanitize_key( wp_unslash( $_COOKIE['omtr_lang'] ) ) : '';
		if ( ! in_array( $cookie, $this->settings->target_langs(), true ) ) {
			return;
		}
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$home_path   = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( ! is_string( $home_path ) || $request_uri !== $home_path ) {
			return;
		}
		$referer = wp_get_referer();
		if ( is_string( $referer ) && wp_parse_url( $referer, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			return;
		}
		nocache_headers();
		wp_safe_redirect( $this->urls->translate( home_url( '/' ), $cookie ), 302 );
		exit;
	}
}
