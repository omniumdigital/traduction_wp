<?php
/**
 * Rendered HTML translator.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Render;

use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Security\Sanitizer;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\TranslationRepository;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Injects stored translations and rewrites internal links without queueing work.
 */
final class Translator {

	private const ATTRIBUTES = array( 'alt', 'title', 'placeholder', 'aria-label' );

	/**
	 * Meta name/property keys whose content is human text. Must stay in sync
	 * with HtmlSegmentExtractor::TRANSLATABLE_META: non-textual SEO metas
	 * (locale, type, URL, card…) must never be rewritten with a translation.
	 */
	/**
	 * Number of segments of the last translated response without a stored translation.
	 *
	 * @var int
	 */
	private int $missing = 0;

	private const TRANSLATABLE_META = array( 'description', 'og:title', 'og:description', 'og:site_name', 'twitter:title', 'twitter:description' );

	/**
	 * Constructor.
	 *
	 * @param CurrentLanguage                $current_language Current request language.
	 * @param SegmentExtractorInterface      $extractor        Segment extractor.
	 * @param TranslationRepositoryInterface $translations     Translation repository.
	 * @param UrlTranslator                  $url_translator    Internal URL translator.
	 * @param VisualEditor|null              $visual_editor     Optional private editor.
	 */
	public function __construct(
		private CurrentLanguage $current_language,
		private SegmentExtractorInterface $extractor,
		private TranslationRepositoryInterface $translations,
		private UrlTranslator $url_translator,
		private ?VisualEditor $visual_editor = null
	) {}

	/**
	 * Translate one complete HTML response using stored translations only.
	 *
	 * @param string $html Rendered response.
	 */
	public function translate( string $html ): string {
		$this->missing = 0;
		if ( ! $this->current_language->is_translated() || '' === trim( $html ) ) {
			return $html;
		}

		$source_lang = $this->current_language->source();
		$document    = HtmlSegmentExtractor::load_document( $html );
		if ( null === $document ) {
			return $html;
		}
		$segments = $this->extractor instanceof HtmlSegmentExtractor
			? $this->extractor->extract_from_document( $document, $source_lang )
			: $this->extractor->extract( $html, $source_lang );
		$hashes   = array_keys( $segments );
		sort( $hashes, SORT_STRING );
		$version   = wp_cache_get( TranslationRepository::CACHE_VERSION_KEY, TranslationRepository::CACHE_GROUP );
		$version   = is_int( $version ) ? $version : 0;
		$cache_key = 'render:' . hash( 'sha256', $this->current_language->get() . "\n" . $version . "\n" . implode( "\n", $hashes ) );
		$editing   = null !== $this->visual_editor && $this->visual_editor->active();
		$stored    = $editing ? false : wp_cache_get( $cache_key, TranslationRepository::CACHE_GROUP );
		if ( ! is_array( $stored ) ) {
			$stored = $this->translations->find_many( $hashes, $this->current_language->get() );
			if ( ! $editing ) {
				wp_cache_set( $cache_key, $stored, TranslationRepository::CACHE_GROUP, 5 * MINUTE_IN_SECONDS );
			}
		}
		$this->missing = count( array_diff_key( $segments, $stored ) );

		$ids = $editing ? $this->visual_editor->ids_by_hash( $hashes ) : array();
		$this->walk( $document, $source_lang, $stored, false, $ids );
		$this->set_document_language( $document );
		$this->rewrite_links( $document );
		$output = HtmlSegmentExtractor::save_document( $document );
		return false === $output ? $html : $output;
	}

	/**
	 * Segments of the last translated response that had no stored translation.
	 * Computed during translate(), so callers get it without parsing the page again.
	 */
	public function missing_count(): int {
		return $this->missing;
	}

	/**
	 * Translate nodes recursively.
	 *
	 * @param \DOMNode|\Dom\Node    $node        Current node.
	 * @param string                $source_lang Source language.
	 * @param array<string, string> $stored      Stored translations.
	 * @param bool                  $excluded    Ancestor exclusion state.
	 * @param array<string, int>    $ids         Existing segment ids.
	 */
	private function walk( \DOMNode|\Dom\Node $node, string $source_lang, array $stored, bool $excluded, array $ids ): void {
		if ( $node instanceof \DOMElement || $node instanceof \Dom\Element ) {
			$tag = strtolower( $node->tagName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			if ( ! $excluded && 'omtr-ph' === $tag && $node instanceof \DOMElement ) {
				HtmlSegmentExtractor::translate_json_ld_placeholder( $node, $source_lang, $this->current_language->get(), $stored, $this->url_translator );
			}
			if ( ! $excluded && 'script' === $tag && 'application/ld+json' === strtolower( trim( (string) $node->getAttribute( 'type' ) ) ) ) {
				$node->textContent = JsonLdProcessor::translate( (string) $node->textContent, $source_lang, $this->current_language->get(), $stored, $this->url_translator ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			}
			$excluded = $excluded || $this->is_excluded_element( $node );
			if ( ! $excluded ) {
				foreach ( self::ATTRIBUTES as $attribute ) {
					if ( 'link' === $tag && 'title' === $attribute ) {
						continue; // Link titles (feeds, oEmbed) are never visible page text.
					}
					if ( $node->hasAttribute( $attribute ) ) {
						$translation = $this->lookup( $node->getAttribute( $attribute ), $source_lang, $stored );
						if ( null !== $translation ) {
							$node->setAttribute( $attribute, $this->safe_attribute( $translation ) );
						}
					}
				}
				if ( 'meta' === $tag && $this->is_translatable_meta( $node ) ) {
					$translation = $this->lookup( $node->getAttribute( 'content' ), $source_lang, $stored );
					if ( null !== $translation ) {
						$node->setAttribute( 'content', $this->safe_attribute( $translation ) );
					}
				}
			}
		}

		if ( ! $excluded && XML_TEXT_NODE === $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			$hash        = SegmentKey::hash( $source_lang, SegmentKey::normalize( (string) $node->nodeValue ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			$translation = $this->lookup( (string) $node->nodeValue, $source_lang, $stored ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			if ( null !== $translation ) {
				$parent_tag = $node->parentNode instanceof \DOMElement || $node->parentNode instanceof \Dom\Element ? strtolower( $node->parentNode->tagName ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
				$this->replace_text_node( $node, $translation, in_array( $parent_tag, array( 'title', 'option', 'svg' ), true ) ? 0 : ( $ids[ $hash ] ?? 0 ) );
				return;
			}
		}

		$children = iterator_to_array( $node->childNodes ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		foreach ( $children as $child ) {
			$this->walk( $child, $source_lang, $stored, $excluded, $ids );
		}
	}

	/**
	 * Find a translation by normalized segment identity.
	 *
	 * @param string                $text        Source text.
	 * @param string                $source_lang Source language.
	 * @param array<string, string> $stored      Translation map.
	 */
	private function lookup( string $text, string $source_lang, array $stored ): ?string {
		$normalized = SegmentKey::normalize( $text );
		if ( '' === $normalized ) {
			return null;
		}
		$hash = SegmentKey::hash( $source_lang, $normalized );
		return isset( $stored[ $hash ] ) ? Sanitizer::inline_html( $stored[ $hash ] ) : null;
	}

	/**
	 * Insert sanitized inline markup as DOM nodes.
	 *
	 * @param \DOMNode|\Dom\Node $text_node   Text node to replace.
	 * @param string             $translation Sanitized translation.
	 * @param int                $segment_id  Existing segment id for visual editing.
	 */
	private function replace_text_node( \DOMNode|\Dom\Node $text_node, string $translation, int $segment_id = 0 ): void {
		$parent = $text_node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		$owner  = $text_node->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		if ( null === $parent || null === $owner ) {
			return;
		}

		$fragment_html = '<div id="omtr-fragment">' . Sanitizer::inline_html( $translation ) . '</div>';
		if ( $owner instanceof \DOMDocument ) {
			$fragment = new \DOMDocument( '1.0', 'UTF-8' );
			$previous = libxml_use_internal_errors( true );
			$loaded   = $fragment->loadHTML( '<meta charset="UTF-8">' . $fragment_html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		} else {
			$fragment = HtmlSegmentExtractor::load_document( $fragment_html );
			$loaded   = null !== $fragment;
		}
		if ( ! $loaded ) {
			return;
		}
		$wrapper = $fragment->getElementById( 'omtr-fragment' );
		if ( null === $wrapper ) {
			return;
		}
		$target = $parent;
		if ( $segment_id > 0 ) {
			$target = $owner->createElement( 'span' );
			$target->setAttribute( 'data-omtr-segment', (string) $segment_id );
			$parent->insertBefore( $target, $text_node );
		}
		foreach ( iterator_to_array( $wrapper->childNodes ) as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			$target->insertBefore( $owner->importNode( $child, true ), $segment_id > 0 ? null : $text_node );
		}
		$parent->removeChild( $text_node );
	}

	/**
	 * Escape translated attributes and return their DOM value.
	 *
	 * @param string $translation Untrusted translation.
	 */
	private function safe_attribute( string $translation ): string {
		$escaped = esc_attr( Sanitizer::plain_text( $translation ) );
		return html_entity_decode( $escaped, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * Exclusion rules shared with extraction.
	 *
	 * @param \DOMElement|\Dom\Element $element Element.
	 */
	private function is_excluded_element( \DOMElement|\Dom\Element $element ): bool {
		if ( in_array( strtolower( $element->tagName ), array( 'script', 'style', 'code', 'pre', 'textarea' ), true ) || $element->hasAttribute( 'data-omtr-skip' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			return true;
		}
		$classes = preg_split( '/\s+/', (string) $element->getAttribute( 'class' ) );
		$classes = false === $classes ? array() : $classes;
		return in_array( 'notranslate', $classes, true );
	}

	/**
	 * Meta translation allowlist, in sync with the extractor.
	 *
	 * @param \DOMElement|\Dom\Element $element Meta element.
	 */
	private function is_translatable_meta( \DOMElement|\Dom\Element $element ): bool {
		$name = (string) $element->getAttribute( 'name' );
		$key  = strtolower( '' !== $name ? $name : (string) $element->getAttribute( 'property' ) );
		return in_array( $key, self::TRANSLATABLE_META, true );
	}

	/**
	 * Set the root HTML language.
	 *
	 * @param \DOMDocument|\Dom\HTMLDocument $document Document.
	 */
	private function set_document_language( \DOMDocument|\Dom\HTMLDocument $document ): void {
		$elements = $document->getElementsByTagName( 'html' );
		if ( $elements->length > 0 ) {
			$root = $elements->item( 0 );
			if ( $root instanceof \DOMElement || $root instanceof \Dom\Element ) {
				$root->setAttribute( 'lang', esc_attr( $this->current_language->get() ) );
			}
		}
	}

	/**
	 * Rewrite same-site links for the active language.
	 *
	 * @param \DOMDocument|\Dom\HTMLDocument $document Document.
	 */
	private function rewrite_links( \DOMDocument|\Dom\HTMLDocument $document ): void {
		foreach ( $document->getElementsByTagName( 'a' ) as $anchor ) {
			if ( $anchor->hasAttribute( 'href' ) ) {
				$href = $anchor->getAttribute( 'href' );
				$anchor->setAttribute( 'href', $this->url_translator->translate( $href, $this->current_language->get() ) );
			}
		}
	}

	/**
	 * Translate HTML without returning the parser's document shell.
	 *
	 * @param string $html HTML fragment or plain text.
	 */
	public function translate_fragment( string $html ): string {
		if ( ! $this->current_language->is_translated() || '' === trim( $html ) ) {
			return $html;
		}
		$token      = bin2hex( random_bytes( 12 ) );
		$start      = '<!--omtr-start-' . $token . '-->';
		$end        = '<!--omtr-end-' . $token . '-->';
		$translated = $this->translate( '<div data-omtr-fragment="1">' . $start . $html . $end . '</div>' );
		$from       = strpos( $translated, $start );
		$to         = strpos( $translated, $end );
		if ( false === $from || false === $to || $to < $from ) {
			return $html;
		}
		return substr( $translated, $from + strlen( $start ), $to - $from - strlen( $start ) );
	}
}
