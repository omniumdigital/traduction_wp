<?php
/**
 * URL-prefix language router.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Store\SlugRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Removes a supported language prefix before WordPress parses the request.
 */
final class LanguageRouter {
	/**
	 * Post identified by a historical slug on this request.
	 *
	 * @var int|null
	 */
	private ?int $old_slug_post_id = null;

	/**
	 * Constructor.
	 *
	 * @param CurrentLanguage     $current_language Current request language.
	 * @param Settings            $settings         Plugin settings.
	 * @param RequestGuard        $guard            Front-request guard.
	 * @param SlugRepository|null $slugs            Translated slug repository.
	 * @param UrlTranslator|null  $url_translator   Internal URL translator.
	 */
	public function __construct(
		private CurrentLanguage $current_language,
		private Settings $settings,
		private RequestGuard $guard,
		private ?SlugRepository $slugs = null,
		private ?UrlTranslator $url_translator = null
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

		$request_uri = (string) $_SERVER['REQUEST_URI']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- The raw URI is only sliced so its original bytes remain untouched.
		$query_start = strpos( $request_uri, '?' );
		$path        = false === $query_start ? $request_uri : substr( $request_uri, 0, $query_start );
		$targets     = array_values( array_unique( $this->settings->target_langs() ) );
		$home        = wp_parse_url( home_url( '/' ) );
		$home_path   = is_array( $home ) && isset( $home['path'] ) ? rtrim( (string) $home['path'], '/' ) : '';

		foreach ( $targets as $target ) {
			$prefix = $home_path . '/' . rawurlencode( $target );
			if ( $path === $prefix || str_starts_with( $path, $prefix . '/' ) ) {
				$this->current_language->set( $target );
				$relative_path          = substr( $path, strlen( $prefix ) );
				$relative_path          = '' === $relative_path ? '/' : $relative_path;
				$relative_path          = $this->source_path( $relative_path, $target, $home_path );
				$path                   = $home_path . $relative_path;
				$_SERVER['REQUEST_URI'] = $path . ( false === $query_start ? '' : substr( $request_uri, $query_start ) );
				break;
			}
		}

		return $do_parse;
	}

	/**
	 * Replace a translated final path segment with the source permalink path.
	 *
	 * The translated slug is globally unique inside a language, so the final
	 * segment identifies hierarchical pages, posts and products alike.
	 *
	 * @param string $relative_path Path below the language prefix.
	 * @param string $lang          Target language.
	 * @param string $home_path     WordPress installation path.
	 */
	private function source_path( string $relative_path, string $lang, string $home_path ): string {
		if ( null === $this->slugs ) {
			return $relative_path;
		}
		$trimmed = trim( $relative_path, '/' );
		if ( '' === $trimmed ) {
			return $relative_path;
		}
		$parts   = explode( '/', $trimmed );
		$slug    = rawurldecode( (string) end( $parts ) );
		$post_id = $this->slugs->find_post_id( $slug, $lang );
		if ( null === $post_id ) {
			$post_id                = $this->slugs->find_historical_post_id( $slug, $lang );
			$this->old_slug_post_id = $post_id;
		}
		if ( null === $post_id || 'publish' !== get_post_status( $post_id ) ) {
			return $relative_path;
		}
		$permalink = get_permalink( $post_id );
		$parsed    = is_string( $permalink ) ? wp_parse_url( $permalink ) : false;
		if ( ! is_array( $parsed ) || ! isset( $parsed['path'] ) ) {
			return $relative_path;
		}
		$source_path = (string) $parsed['path'];
		if ( '' !== $home_path && str_starts_with( $source_path, $home_path . '/' ) ) {
			$source_path = substr( $source_path, strlen( $home_path ) );
		}
		return '/' . ltrim( $source_path, '/' );
	}

	/** Return the new URL for an obsolete slug, if the request matched one. */
	public function old_slug_redirect_url(): ?string {
		if ( null === $this->old_slug_post_id || null === $this->url_translator ) {
			return null;
		}
		$permalink = get_permalink( $this->old_slug_post_id );
		if ( ! is_string( $permalink ) ) {
			return null;
		}
		return $this->url_translator->translate( $permalink, $this->current_language->get() );
	}

	/** Redirect obsolete translated slugs after WordPress resolves the post. */
	public function redirect_old_slug(): void {
		$url = $this->old_slug_redirect_url();
		if ( null !== $url && wp_safe_redirect( $url, 301 ) ) {
			exit;
		}
	}
}
