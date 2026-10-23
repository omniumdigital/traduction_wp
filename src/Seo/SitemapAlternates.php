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
		$links      = $this->links_xml( $source_url, true );
		$source     = preg_replace( '#</url>\s*$#', $links . "\t</url>\n", $output );
		$result     = is_string( $source ) ? $source : $output;

		foreach ( $this->languages() as $language ) {
			if ( $language === $this->settings->source_lang() ) {
				continue;
			}
			$result .= $this->url_xml( $this->url_translator->translate( $source_url, $language ), $source_url );
		}
		return $result;
	}

	/**
	 * Expand a native WordPress sitemap URL list.
	 *
	 * @param list<array<string, mixed>> $url_list Source URL entries.
	 * @return list<array{entry: array<string, mixed>, source_url: string}>
	 */
	public function expand( array $url_list ): array {
		$expanded = array();
		foreach ( $url_list as $entry ) {
			if ( ! isset( $entry['loc'] ) || ! is_string( $entry['loc'] ) || '' === $entry['loc'] ) {
				continue;
			}
			$source_url   = $this->source_url( $entry['loc'] );
			$entry['loc'] = $source_url;
			$expanded[]   = array(
				'entry'      => $entry,
				'source_url' => $source_url,
			);
			foreach ( $this->languages() as $language ) {
				if ( $language === $this->settings->source_lang() ) {
					continue;
				}
				$localized        = $entry;
				$localized['loc'] = $this->url_translator->translate( $source_url, $language );
				$expanded[]       = array(
					'entry'      => $localized,
					'source_url' => $source_url,
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
	 */
	public function links_xml( string $source_url, bool $declare_namespace = false ): string {
		$xml       = '';
		$namespace = $declare_namespace ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '';
		foreach ( $this->languages() as $language ) {
			$xml      .= sprintf(
				"\t\t<xhtml:link%s rel=\"alternate\" hreflang=\"%s\" href=\"%s\" />\n",
				$namespace,
				esc_xml( $language ),
				$this->escape_url( $this->url_translator->translate( $source_url, $language ) )
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
	 */
	private function url_xml( string $url, string $source_url ): string {
		return "\t<url>\n\t\t<loc>" . $this->escape_url( $url ) . "</loc>\n" . $this->links_xml( $source_url, true ) . "\t</url>\n";
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
	 * @return list<string>
	 */
	private function languages(): array {
		$languages = array_values( array_unique( array_merge( array( $this->settings->source_lang() ), $this->settings->target_langs() ) ) );
		if ( ! (bool) $this->settings->get( 'noindex_partial' ) ) {
			return $languages;
		}
		return array_values( array_filter( $languages, fn( string $language ): bool => $this->coverage->is_complete( $language ) ) );
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
