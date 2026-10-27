<?php
/**
 * Translated slug generation.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\SlugRepository;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Generates safe, ASCII and unique translated slugs outside visitor requests.
 */
final class SlugGenerator {

	/** Reserved WordPress endpoints which must not become content slugs. */
	private const RESERVED = array( 'admin', 'api', 'author', 'category', 'comments', 'embed', 'feed', 'page', 'tag', 'wp-admin', 'wp-json', 'wp-login', 'wp-register' );

	/**
	 * Constructor.
	 *
	 * @param SlugRepository                 $slugs       Slug repository.
	 * @param TranslationRepositoryInterface $translations Translation repository.
	 * @param Settings                       $settings    Plugin settings.
	 */
	public function __construct(
		private SlugRepository $slugs,
		private TranslationRepositoryInterface $translations,
		private Settings $settings
	) {}

	/**
	 * Generate and persist one translated slug.
	 *
	 * A slug is only built from a stored translation of the post title; the
	 * source post_name is never persisted as a fallback. An existing slug is
	 * never overwritten — except when it is still the source-slug fallback
	 * written by the 1.0.x versions, which is then replaced by the translated
	 * slug (the old value is kept in slug_history for the 301 redirect).
	 *
	 * @param int    $post_id Post id.
	 * @param string $lang    Target language.
	 */
	public function generate( int $post_id, string $lang ): ?string {
		if ( ! $this->is_supported_post( $post_id ) || ! in_array( $lang, $this->settings->target_langs(), true ) ) {
			return null;
		}

		$title      = (string) get_the_title( $post_id );
		$normalized = SegmentKey::normalize( $title );
		$hash       = SegmentKey::hash( $this->settings->source_lang(), $normalized );
		$translated = $this->translations->find_many( array( $hash ), $lang );

		// Without a translated title there is nothing reliable to build a
		// slug from: keep waiting for the title segment to be translated.
		if ( ! isset( $translated[ $hash ] ) ) {
			return null;
		}

		$base = $this->normalize( (string) $translated[ $hash ] );
		if ( '' === $base ) {
			return null;
		}

		$existing = $this->slugs->get( $post_id, $lang );
		// A slug that is not a copy of the source URL was set manually (or
		// already translated): never overwrite it.
		if ( null !== $existing && ! $this->is_source_copy( $existing, $post_id, $title ) ) {
			return $existing;
		}

		$slug = $this->unique( $base, $lang, $post_id );
		if ( $slug === $existing ) {
			return $slug;
		}
		$this->slugs->save( $post_id, $lang, $slug );
		return $slug;
	}

	/**
	 * Whether a stored slug is still a copy of the source URL.
	 *
	 * Covers the 1.0.x fallback (normalized post_name) and a slug built from
	 * the untranslated source title, with or without a "-2" style suffix.
	 *
	 * @param string $slug    Stored translated slug.
	 * @param int    $post_id Post id.
	 * @param string $title   Source title.
	 */
	private function is_source_copy( string $slug, int $post_id, string $title ): bool {
		$post    = get_post( $post_id );
		$sources = array_filter(
			array(
				$post instanceof \WP_Post ? $this->normalize( (string) $post->post_name ) : '',
				$this->normalize( $title ),
			)
		);
		foreach ( $sources as $source ) {
			if ( $slug === $source || 1 === preg_match( '/^' . preg_quote( $source, '/' ) . '-\d+$/', $slug ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Validate, normalize and persist one manually supplied slug.
	 *
	 * @param int    $post_id   Post id.
	 * @param string $lang      Target language.
	 * @param string $candidate Candidate slug.
	 */
	public function set( int $post_id, string $lang, string $candidate ): ?string {
		if ( ! $this->is_supported_post( $post_id ) || ! in_array( $lang, $this->settings->target_langs(), true ) || ! $this->is_safe_candidate( $candidate ) ) {
			return null;
		}
		$base = $this->normalize( $candidate );
		if ( '' === $base || in_array( $base, self::RESERVED, true ) ) {
			return null;
		}
		$slug = $this->unique( $base, $lang, $post_id );
		$this->slugs->save( $post_id, $lang, $slug );
		return $slug;
	}

	/**
	 * Convert a title to its ASCII URL form.
	 *
	 * @param string $value Title or slug.
	 */
	private function normalize( string $value ): string {
		return sanitize_title( remove_accents( wp_strip_all_tags( $value ) ) );
	}

	/**
	 * Reject path traversal, markup, URLs and path separators before sanitizing.
	 *
	 * @param string $candidate Candidate slug.
	 */
	private function is_safe_candidate( string $candidate ): bool {
		$candidate = trim( $candidate );
		return '' !== $candidate
			&& strlen( $candidate ) <= 200
			&& ! str_contains( $candidate, '..' )
			&& ! str_contains( $candidate, '/' )
			&& ! str_contains( $candidate, '\\' )
			&& ! str_contains( $candidate, '<' )
			&& ! str_contains( $candidate, '>' )
			&& ! preg_match( '/^[a-z][a-z0-9+.-]*:/i', $candidate )
			&& ! preg_match( '/[\x00-\x1F\x7F]/', $candidate );
	}

	/**
	 * Apply -2, -3... until the slug is free in both translated and source URL spaces.
	 *
	 * @param string $base    Base slug.
	 * @param string $lang    Target language.
	 * @param int    $post_id Post id allowed to own the slug.
	 */
	private function unique( string $base, string $lang, int $post_id ): string {
		$slug   = $base;
		$suffix = 2;
		while ( $this->conflicts( $slug, $lang, $post_id ) ) {
			$slug = substr( $base, 0, 195 - strlen( (string) $suffix ) ) . '-' . $suffix;
			++$suffix;
		}
		return $slug;
	}

	/**
	 * Check reserved, translated, post and category slugs conservatively.
	 *
	 * @param string $slug    Candidate slug.
	 * @param string $lang    Target language.
	 * @param int    $post_id Post id allowed to own the slug.
	 */
	private function conflicts( string $slug, string $lang, int $post_id ): bool {
		if ( in_array( $slug, self::RESERVED, true ) || $this->slugs->exists( $slug, $lang, $post_id ) ) {
			return true;
		}
		$post_types = get_post_types( array( 'public' => true ), 'names' );
		$existing   = get_page_by_path( $slug, OBJECT, array_values( $post_types ) );
		if ( $existing instanceof \WP_Post && $existing->ID !== $post_id ) {
			return true;
		}
		return ! empty( term_exists( $slug, 'category' ) );
	}

	/**
	 * Only published, public posts receive translated slugs.
	 *
	 * @param int $post_id Post id.
	 */
	private function is_supported_post( int $post_id ): bool {
		$post_type = get_post_type( $post_id );
		$object    = is_string( $post_type ) ? get_post_type_object( $post_type ) : null;
		return $post_id > 0 && 'publish' === get_post_status( $post_id ) && null !== $object && (bool) $object->public;
	}
}
