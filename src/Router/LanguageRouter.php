<?php
/**
 * URL-prefix language router.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Removes a supported language prefix before WordPress parses the request.
 */
final class LanguageRouter {

	/**
	 * Constructor.
	 *
	 * @param CurrentLanguage $current_language Current request language.
	 * @param Settings        $settings         Plugin settings.
	 * @param RequestGuard    $guard            Front-request guard.
	 */
	public function __construct(
		private CurrentLanguage $current_language,
		private Settings $settings,
		private RequestGuard $guard
	) {}

	/**
	 * Filter do_parse_request before WP::parse_request reads REQUEST_URI.
	 *
	 * @param bool $do_parse Whether WordPress should parse the request.
	 */
	public function strip_language_prefix( bool $do_parse ): bool {
		if ( ! $this->guard->is_public_front_request() || ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return $do_parse;
		}

		$request_uri = (string) $_SERVER['REQUEST_URI'];
		$query_start = strpos( $request_uri, '?' );
		$path        = false === $query_start ? $request_uri : substr( $request_uri, 0, $query_start );
		$targets     = array_values( array_unique( $this->settings->target_langs() ) );

		foreach ( $targets as $target ) {
			$prefix = '/' . rawurlencode( $target );
			if ( $path === $prefix || str_starts_with( $path, $prefix . '/' ) ) {
				$this->current_language->set( $target );
				$path = substr( $path, strlen( $prefix ) );
				$path = '' === $path ? '/' : $path;
				$_SERVER['REQUEST_URI'] = $path . ( false === $query_start ? '' : substr( $request_uri, $query_start ) );
				break;
			}
		}

		return $do_parse;
	}
}
