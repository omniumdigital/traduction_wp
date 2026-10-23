<?php
/**
 * SEO head tags for localized public pages.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Router\UrlTranslator;

defined( 'ABSPATH' ) || exit;

/**
 * Outputs reciprocal hreflang links and one self-referencing canonical.
 */
final class HeadTags {

	/**
	 * Constructor.
	 *
	 * @param Settings            $settings         Plugin settings.
	 * @param CurrentLanguage     $current_language Current request language.
	 * @param RequestGuard        $guard            Front-request guard.
	 * @param UrlTranslator       $url_translator   Language URL translator.
	 * @param TranslationCoverage $coverage     Translation completion service.
	 */
	public function __construct(
		private Settings $settings,
		private CurrentLanguage $current_language,
		private RequestGuard $guard,
		private UrlTranslator $url_translator,
		private TranslationCoverage $coverage
	) {}

	/**
	 * Print tags in wp_head.
	 */
	public function render(): void {
		if ( ! $this->is_eligible() ) {
			return;
		}

		$source_url = $this->source_url();
		$post_id    = (int) get_queried_object_id();
		foreach ( $this->languages() as $language ) {
			printf(
				"<link rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
				esc_attr( $language ),
				esc_url( $post_id > 0 ? $this->url_translator->translate_post( $post_id, $language ) : $this->url_translator->translate( $source_url, $language ) )
			);
		}
		printf( "<link rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n", esc_url( $source_url ) );

		if ( ! $this->seo_plugin_handles_canonical() ) {
			printf( "<link rel=\"canonical\" href=\"%s\" />\n", esc_url( $this->canonical_url() ) );
		}
	}

	/**
	 * Replace Yoast SEO's canonical through its documented wpseo_canonical filter.
	 *
	 * @param string|false $canonical Existing canonical.
	 * @return string|false
	 */
	public function filter_yoast_canonical( string|false $canonical ): string|false {
		return $this->is_eligible() ? $this->canonical_url() : $canonical;
	}

	/**
	 * Replace Rank Math's canonical through its documented frontend filter.
	 *
	 * @param string|false $canonical Existing canonical.
	 * @return string|false
	 */
	public function filter_rank_math_canonical( string|false $canonical ): string|false {
		return $this->is_eligible() ? $this->canonical_url() : $canonical;
	}

	/**
	 * Current self-referencing localized URL.
	 */
	public function canonical_url(): string {
		$post_id = (int) get_queried_object_id();
		return $post_id > 0 ? $this->url_translator->translate_post( $post_id, $this->current_language->get() ) : $this->url_translator->translate( $this->source_url(), $this->current_language->get() );
	}

	/**
	 * Whether SEO tags apply to this request.
	 */
	private function is_eligible(): bool {
		return $this->guard->is_public_front_request()
			&& ! is_feed()
			&& ! is_robots()
			&& ! is_trackback()
			&& ! is_404()
			&& ! is_preview()
			&& ! post_password_required();
	}

	/**
	 * Canonical source URL built exclusively from the configured site URL.
	 */
	private function source_url(): string {
		$request = isset( $GLOBALS['wp']->request ) ? (string) $GLOBALS['wp']->request : '';
		$path    = '' === $request ? '/' : '/' . ltrim( $request, '/' );
		return home_url( $path );
	}

	/**
	 * Source and configured target languages.
	 *
	 * @return list<string>
	 */
	private function languages(): array {
		$languages = array_values( array_unique( array_merge( array( $this->settings->source_lang() ), $this->settings->target_langs() ) ) );
		if ( ! (bool) $this->settings->get( 'noindex_partial' ) ) {
			return $languages;
		}
		return array_values(
			array_filter(
				$languages,
				fn( string $language ): bool => $this->coverage->is_complete( $language )
			)
		);
	}

	/**
	 * Whether a supported SEO plugin owns the canonical output.
	 */
	private function seo_plugin_handles_canonical(): bool {
		return defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' );
	}
}
