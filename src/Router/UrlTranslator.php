<?php
/**
 * Internal URL language translator.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Store\SlugRepository;

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
	 * Slugs prepared for a sitemap batch.
	 *
	 * @var array<int, array<string, string>>|null
	 */
	private ?array $prepared_slugs = null;

	/**
	 * URL resolution cache for this request.
	 *
	 * @var array<string, int>
	 */
	private array $resolved_urls = array();

	/**
	 * Known post for the current translation.
	 *
	 * @var int|null
	 */
	private ?int $known_post_id = null;

	/**
	 * Translate a known post without resolving its URL to a post ID.
	 *
	 * @param int    $post_id Post identifier.
	 * @param string $lang Target language.
	 */
	public function translate_post( int $post_id, string $lang ): string {
		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return '';
		}
		$this->known_post_id = $post_id;
		try {
			return $this->translate( $permalink, $lang );
		} finally {
			$this->known_post_id = null;
		}
	}

	/**
	 * Load all slug translations needed by a sitemap page in one query.
	 *
	 * @param list<int>    $post_ids Post identifiers.
	 * @param list<string> $languages Target languages.
	 */
	public function preload_posts( array $post_ids, array $languages ): void {
		$ids = array();
		foreach ( $post_ids as $post_id ) {
			$ids[] = $post_id;
			foreach ( get_post_ancestors( $post_id ) as $ancestor ) {
				$ids[] = (int) $ancestor;
			}
		}
		$this->prepared_slugs = isset( $GLOBALS['wpdb'] ) && $GLOBALS['wpdb'] instanceof \wpdb
			? ( new SlugRepository( $GLOBALS['wpdb'] ) )->many( $ids, $languages )
			: array();
	}

	/**
	 * Resolve a source URL once per request.
	 *
	 * @param string $url Source URL.
	 */
	public function post_id_for_url( string $url ): int {
		if ( ! array_key_exists( $url, $this->resolved_urls ) ) {
			$this->resolved_urls[ $url ] = (int) url_to_postid( $url );
		}
		return $this->resolved_urls[ $url ];
	}

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
		if ( false === $home || ! isset( $home['host'] ) ) {
			return $url;
		}

		$home_authority = (string) ( $home['scheme'] ?? 'https' ) . '://' . (string) $home['host'];
		if ( isset( $home['port'] ) ) {
			$home_authority .= ':' . (int) $home['port'];
		}
		$parts = wp_parse_url( $is_relative ? $home_authority . $url : $url );
		if ( false === $parts || ! isset( $parts['host'] ) ) {
			return $url;
		}
		$scheme_matches = ( $parts['scheme'] ?? '' ) === ( $home['scheme'] ?? '' );
		$port_matches   = ( $parts['port'] ?? null ) === ( $home['port'] ?? null );
		if ( ! $scheme_matches || ! $port_matches || 0 !== strcasecmp( (string) $parts['host'], (string) $home['host'] ) ) {
			return $url;
		}

		$path      = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		$home_path = isset( $home['path'] ) ? rtrim( (string) $home['path'], '/' ) : '';
		if ( '' !== $home_path && $path !== $home_path && ! str_starts_with( $path, $home_path . '/' ) ) {
			return $url;
		}

		$relative_path = substr( $path, strlen( $home_path ) );
		$relative_path = '' === $relative_path ? '/' : $relative_path;
		if ( str_starts_with( $relative_path, '/wp-admin' ) || str_starts_with( $relative_path, '/wp-json' ) ) {
			return $url;
		}
		foreach ( $this->settings->target_langs() as $target ) {
			$prefix = '/' . rawurlencode( $target );
			if ( $relative_path === $prefix || str_starts_with( $relative_path, $prefix . '/' ) ) {
				$relative_path = substr( $relative_path, strlen( $prefix ) );
				$relative_path = '' === $relative_path ? '/' : $relative_path;
				$relative_path = $this->source_slug_path( $relative_path, $target, $home_path );
				break;
			}
		}
		if ( $lang !== $this->settings->source_lang() ) {
			$relative_path = $this->translated_slug_path( $relative_path, $lang, $home_authority . $home_path );
			$relative_path = '/' . rawurlencode( $lang ) . ( '/' === $relative_path ? '/' : '/' . ltrim( $relative_path, '/' ) );
		}
		$path = $home_path . $relative_path;

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

	/**
	 * Recover the source permalink when switching away from a translated URL.
	 *
	 * @param string $relative_path Path below the language prefix.
	 * @param string $lang          Language of the incoming URL.
	 * @param string $home_path     WordPress installation path.
	 */
	private function source_slug_path( string $relative_path, string $lang, string $home_path ): string {
		if ( '/' === $relative_path || ! isset( $GLOBALS['wpdb'] ) || ! $GLOBALS['wpdb'] instanceof \wpdb ) {
			return $relative_path;
		}
		$segments = explode( '/', trim( $relative_path, '/' ) );
		$post_id  = ( new SlugRepository( $GLOBALS['wpdb'] ) )->find_post_id( rawurldecode( (string) end( $segments ) ), $lang );
		if ( null === $post_id || 'publish' !== get_post_status( $post_id ) ) {
			return $relative_path;
		}
		$permalink = get_permalink( $post_id );
		$parts     = is_string( $permalink ) ? wp_parse_url( $permalink ) : false;
		if ( ! is_array( $parts ) || ! isset( $parts['path'] ) ) {
			return $relative_path;
		}
		$path = (string) $parts['path'];
		return '' !== $home_path && str_starts_with( $path, $home_path . '/' ) ? substr( $path, strlen( $home_path ) ) : $path;
	}

	/**
	 * Replace source hierarchy segments with stored translated slugs.
	 *
	 * The public constructor and translate() signature intentionally remain
	 * unchanged because SEO and rendering integrations depend on that contract.
	 *
	 * @param string $relative_path Source path below home_url().
	 * @param string $lang          Target language.
	 * @param string $home_base     Absolute home base without trailing slash.
	 */
	private function translated_slug_path( string $relative_path, string $lang, string $home_base ): string {
		$post_id = $this->known_post_id ?? $this->post_id_for_url( rtrim( $home_base, '/' ) . '/' . ltrim( $relative_path, '/' ) );
		if ( $post_id <= 0 || ! isset( $GLOBALS['wpdb'] ) || ! $GLOBALS['wpdb'] instanceof \wpdb ) {
			return $relative_path;
		}

		$repository = new SlugRepository( $GLOBALS['wpdb'] );
		$ids        = array_reverse( array_map( 'intval', get_post_ancestors( $post_id ) ) );
		$ids[]      = $post_id;
		$segments   = explode( '/', trim( $relative_path, '/' ) );
		$position   = count( $segments ) - 1;
		foreach ( array_reverse( $ids ) as $id ) {
			$translated = null !== $this->prepared_slugs ? ( $this->prepared_slugs[ $id ][ $lang ] ?? null ) : $repository->get( $id, $lang );
			if ( null !== $translated && $position >= 0 ) {
				$segments[ $position ] = rawurlencode( $translated );
			}
			--$position;
		}

		$trailing = str_ends_with( $relative_path, '/' ) ? '/' : '';
		return '/' . implode( '/', $segments ) . $trailing;
	}
}
