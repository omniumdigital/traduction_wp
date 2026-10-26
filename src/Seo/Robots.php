<?php
/**
 * Robots directives for incomplete translations.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;

defined( 'ABSPATH' ) || exit;

/**
 * Applies noindex,follow while the current target language is incomplete.
 */
final class Robots {

	/**
	 * Constructor.
	 *
	 * @param Settings            $settings         Plugin settings.
	 * @param CurrentLanguage     $current_language Current request language.
	 * @param RequestGuard        $guard            Front-request guard.
	 * @param TranslationCoverage $coverage         Translation completion service.
	 */
	public function __construct(
		private Settings $settings,
		private CurrentLanguage $current_language,
		private RequestGuard $guard,
		private TranslationCoverage $coverage
	) {}

	/**
	 * Add directives to WordPress core robots output.
	 *
	 * @param array<string, bool|string> $robots Existing directives.
	 * @return array<string, bool|string>
	 */
	public function filter_wp_robots( array $robots ): array {
		if ( ! $this->should_noindex() ) {
			return $robots;
		}
		unset( $robots['index'], $robots['nofollow'] );
		$robots['noindex'] = true;
		$robots['follow']  = true;
		return $robots;
	}

	/**
	 * Replace Yoast's robots string through its wpseo_robots filter.
	 *
	 * @param string $robots Existing directives.
	 */
	public function filter_yoast_robots( string $robots ): string {
		return $this->should_noindex() ? 'noindex, follow' : $robots;
	}

	/**
	 * Replace Rank Math's robots array through its frontend filter.
	 *
	 * @param array<int|string, string> $robots Existing directives.
	 * @return array<int|string, string>
	 */
	public function filter_rank_math_robots( array $robots ): array {
		return $this->should_noindex() ? array( 'noindex', 'follow' ) : $robots;
	}

	/**
	 * Whether noindex applies to this request.
	 */
	private function should_noindex(): bool {
		$post_id    = is_singular() ? (int) get_queried_object_id() : 0;
		$request    = isset( $GLOBALS['wp']->request ) ? (string) $GLOBALS['wp']->request : '';
		$source_url = home_url( '' === $request ? '/' : '/' . ltrim( $request, '/' ) );
		return (bool) $this->settings->get( 'noindex_partial' )
			&& $this->current_language->is_translated()
			&& $this->guard->is_public_front_request()
			&& ! is_feed()
			&& ! is_robots()
			&& ! $this->coverage->is_page_complete( $this->current_language->get(), $post_id, $source_url );
	}
}
