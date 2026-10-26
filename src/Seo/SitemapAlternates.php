<?php
/**
 * Multilingual sitemap entry builder.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\UrlTranslator;

defined( 'ABSPATH' ) || exit;

/**
 * Expands one source sitemap URL into reciprocal localized entries.
 */
final class SitemapAlternates {

	/**
	 * Constructor.
	 *
	 * @param Settings            $settings       Plugin settings.
	 * @param UrlTranslator       $url_translator Language URL translator.
	 * @param TranslationCoverage $coverage       Translation completion service.
	 */
	public function __construct(
		private Settings $settings,
		private UrlTranslator $url_translator,
		private TranslationCoverage $coverage
	) {}

	/**
	 * Add xhtml:link alternates and translated URL entries to Yoast or Rank Math XML.
	 *
	 * Both plugins expose a per-URL rendered-XML filter. The second parameter is the
	 * original sitemap entry array in both implementations.
	 *
	 * @param string               $output Rendered source URL element.
	 * @param array<string, mixed> $entry  Sitemap entry containing loc.
	 */
	public function filter_plugin_url( string $output, array $entry ): string {
		if ( ! isset( $entry['loc'] ) || ! is_string( $entry['loc'] ) || '' === $entry['loc'] ) {
			return $output;
		}

		$source_url = $this->source_url( $entry['loc'] );
		$post_id    = isset( $entry['id'] ) ? (int) $entry['id'] : $this->url_translator->post_id_for_url( $source_url );
		$links      = $this->links_xml( $source_url, true, $post_id );
		$source     = preg_replace( '#</url>\s*$#', $links . "\t</url>\n", $output );
		$result     = is_string( $source ) ? $source : $output;

		foreach ( $this->languages( $post_id, $source_url ) as $language ) {
			if ( $language === $this->settings->source_lang() ) {
				continue;
			}
			$result .= $this->url_xml( $post_id > 0 ? $this->url_translator->translate_post( $post_id, $language ) : $this->url_translator->translate( $source_url, $language ), $source_url, $post_id );
		}
		return $result;
	}

	/**
	 * Expand a native WordPress sitemap URL list.
	 *
	 * @param list<array<string, mixed>> $url_list Source URL entries.
	 * @return list<array{entry: array<string, mixed>, source_url: string, post_id: int}>
	 */
	public function expand( array $url_list ): array {
		$prepared = array();
		$ids      = array();
		foreach ( $url_list as $entry ) {
			if ( ! isset( $entry['loc'] ) || ! is_string( $entry['loc'] ) || '' === $entry['loc'] ) {
				continue;
			}
			$source_url = $this->source_url( $entry['loc'] );
			$post_id    = isset( $entry['id'] ) ? (int) $entry['id'] : $this->url_translator->post_id_for_url( $source_url );
			$prepared[] = array(
				'entry'      => $entry,
				'source_url' => $source_url,
				'post_id'    => $post_id,
			);
			if ( $post_id > 0 ) {
				$ids[] = $post_id;
			}
		}
		$this->url_translator->preload_posts( $ids, array_values( array_unique( array_merge( array( $this->settings->source_lang() ), $this->settings->target_langs() ) ) ) );
		$expanded = array();
		foreach ( $prepared as $item ) {
			$entry        = $item['entry'];
			$source_url   = $item['source_url'];
			$post_id      = $item['post_id'];
			$entry['loc'] = $source_url;
			$expanded[]   = array(
				'entry'      => $entry,
				'source_url' => $source_url,
				'post_id'    => $post_id,
			);
			foreach ( $this->languages( $post_id, $source_url ) as $language ) {
				if ( $language === $this->settings->source_lang() ) {
					continue;
				}
				$localized        = $entry;
				$localized['loc'] = $post_id > 0 ? $this->url_translator->translate_post( $post_id, $language ) : $this->url_translator->translate( $source_url, $language );
				$expanded[]       = array(
					'entry'      => $localized,
					'source_url' => $source_url,
					'post_id'    => $post_id,
				);
			}
		}
		return $expanded;
	}

	/**
	 * Alternate link XML for a source URL.
	 *
	 * @param string $source_url        Unprefixed source URL.
	 * @param bool   $declare_namespace Whether the link must declare the xhtml namespace locally.
	 * @param int    $post_id           Known post identifier.
	 */
	public function links_xml( string $source_url, bool $declare_namespace = false, int $post_id = 0 ): string {
		$xml       = '';
		$namespace = $declare_namespace ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '';
		foreach ( $this->languages( $post_id, $source_url ) as $language ) {
			$xml      .= sprintf(
				"\t\t<xhtml:link%s rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
				$namespace,
				esc_xml( $language ),
				$this->escape_url( $post_id > 0 ? $this->url_translator->translate_post( $post_id, $language ) : $this->url_translator->translate( $source_url, $language ) )
			);
			$namespace = '';
		}
		$xml .= sprintf(
			"\t\t<xhtml:link%s rel=\"alternate\" hreflang=\"x-default\" href=\"%s\" />\n",
			$namespace,
			$this->escape_url( $source_url )
		);
		return $xml;
	}

	/**
	 * Render one minimal translated URL entry.
	 *
	 * @param string $url        Localized URL.
	 * @param string $source_url Unprefixed source URL.
	 * @param int    $post_id    Known post identifier.
	 */
	private function url_xml( string $url, string $source_url, int $post_id ): string {
		return "\t<url>\n\t\t<loc>" . $this->escape_url( $url ) . "</loc>\n" . $this->links_xml( $source_url, true, $post_id ) . "\t</url>\n";
	}

	/**
	 * Normalize an entry to the unprefixed source URL.
	 *
	 * @param string $url Sitemap entry URL.
	 */
	private function source_url( string $url ): string {
		return $this->url_translator->translate( $url, $this->settings->source_lang() );
	}

	/**
	 * Languages allowed in search indexes and hreflang groups.
	 *
	 * @param int    $post_id    Related post id.
	 * @param string $source_url Canonical source URL.
	 * @return list<string>
	 */
	private function languages( int $post_id, string $source_url ): array {
		$languages = array_values( array_unique( array_merge( array( $this->settings->source_lang() ), $this->settings->target_langs() ) ) );
		if ( ! (bool) $this->settings->get( 'noindex_partial' ) ) {
			return $languages;
		}
		return array_values( array_filter( $languages, fn( string $language ): bool => $this->coverage->is_page_complete( $language, $post_id, $source_url ) ) );
	}

	/**
	 * Escape an absolute URL for an XML attribute or node.
	 *
	 * @param string $url URL to escape.
	 */
	private function escape_url( string $url ): string {
		return esc_xml( esc_url( $url ) );
	}
}
