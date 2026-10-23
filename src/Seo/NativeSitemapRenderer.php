<?php
/**
 * WordPress core sitemap renderer with xhtml alternates.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

defined( 'ABSPATH' ) || exit;

/**
 * Decorates WordPress's renderer, changing only URL sitemaps.
 */
final class NativeSitemapRenderer {

	/**
	 * Constructor.
	 *
	 * @param object            $core       Original WP_Sitemaps_Renderer.
	 * @param SitemapAlternates $alternates Alternate entry builder.
	 */
	public function __construct( private object $core, private SitemapAlternates $alternates ) {}

	/**
	 * Delegate sitemap-index rendering unchanged.
	 *
	 * @param array<int, array<string, mixed>> $sitemaps Sitemap index entries.
	 */
	public function render_index( array $sitemaps ): void {
		if ( is_callable( array( $this->core, 'render_index' ) ) ) {
			$this->core->render_index( $sitemaps );
		}
	}

	/**
	 * Render an xhtml-enabled URL sitemap.
	 *
	 * @param list<array<string, mixed>> $url_list Source URL entries.
	 */
	public function render_sitemap( array $url_list ): void {
		header( 'Content-Type: application/xml; charset=UTF-8' );
		echo $this->get_sitemap_xml( $url_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Every dynamic value is escaped in get_sitemap_xml().
	}

	/**
	 * Build native sitemap XML.
	 *
	 * @param list<array<string, mixed>> $url_list Source URL entries.
	 */
	public function get_sitemap_xml( array $url_list ): string {
		$xml  = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n";
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
		foreach ( $this->alternates->expand( $url_list ) as $expanded ) {
			$entry = $expanded['entry'];
			$xml  .= "\t<url>\n";
			$xml  .= "\t\t<loc>" . esc_xml( esc_url( (string) $entry['loc'] ) ) . "</loc>\n";
			foreach ( array( 'lastmod', 'changefreq', 'priority' ) as $field ) {
				if ( isset( $entry[ $field ] ) && is_scalar( $entry[ $field ] ) ) {
					$xml .= "\t\t<{$field}>" . esc_xml( (string) $entry[ $field ] ) . "</{$field}>\n";
				}
			}
			$xml .= $this->alternates->links_xml( $expanded['source_url'] );
			$xml .= "\t</url>\n";
		}
		return $xml . '</urlset>';
	}
}
