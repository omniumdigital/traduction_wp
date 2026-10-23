<?php
/**
 * WordPress native sitemap integration.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Installs the xhtml-aware renderer after WordPress initializes sitemaps.
 */
final class NativeSitemapIntegration {

	/**
	 * Constructor.
	 *
	 * @param SitemapAlternates $alternates Alternate entry builder.
	 */
	public function __construct( private SitemapAlternates $alternates ) {}

	/**
	 * Replace only the renderer while keeping core providers and routing intact.
	 *
	 * @param object $sitemaps WP_Sitemaps instance.
	 */
	public function register_renderer( object $sitemaps ): void {
		if ( ! isset( $sitemaps->renderer ) || ! is_object( $sitemaps->renderer ) ) {
			return;
		}
		$sitemaps->renderer = new NativeSitemapRenderer( $sitemaps->renderer, $this->alternates );
	}
}
