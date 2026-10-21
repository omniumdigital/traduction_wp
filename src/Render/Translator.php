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
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Injects stored translations and rewrites internal links without queueing work.
 */
final class Translator {

	private const ATTRIBUTES = array( 'alt', 'title', 'placeholder', 'aria-label' );

	/**
	 * Constructor.
	 *
	 * @param CurrentLanguage                $current_language Current request language.
	 * @param SegmentExtractorInterface      $extractor        Segment extractor.
	 * @param TranslationRepositoryInterface $translations     Translation repository.
	 * @param UrlTranslator                  $url_translator    Internal URL translator.
	 */
	public function __construct(
		private CurrentLanguage $current_language,
		private SegmentExtractorInterface $extractor,
		private TranslationRepositoryInterface $translations,
		private UrlTranslator $url_translator
	) {}

	/**
	 * Translate one complete HTML response using stored translations only.
	 *
	 * @param string $html Rendered response.
	 */
	public function translate( string $html ): string {
		if ( ! $this->current_language->is_translated() || '' === trim( $html ) ) {
			return $html;
		}

		$source_lang = $this->current_language->source();
		$segments    = $this->extractor->extract( $html, $source_lang );
		$cache_key   = 'omtr_render_' . hash( 'sha256', $this->current_language->get() . "\n" . $html );
		$stored      = get_transient( $cache_key );
		if ( ! is_array( $stored ) ) {
			$stored = $this->translations->find_many( array_keys( $segments ), $this->current_language->get() );
			set_transient( $cache_key, $stored, 5 * MINUTE_IN_SECONDS );
		}

		$document = $this->load_document( $html );
		if ( null === $document ) {
			return $html;
		}
		$this->walk( $document, $source_lang, $stored, false );
		$this->set_document_language( $document );
		$this->rewrite_links( $document );
		$output = $document->saveHTML();
		return false === $output ? $html : preg_replace( '/^<\?xml encoding="UTF-8"\?>\s*/', '', $output ) ?? $html;
	}

	/**
	 * Load HTML safely.
	 *
	 * @param string $html HTML document.
	 */
	private function load_document( string $html ): ?\DOMDocument {
		if ( ! class_exists( \DOMDocument::class ) ) {
			return null;
		}
		$document = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded   = $document->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $loaded ? $document : null;
	}

	/**
	 * Translate nodes recursively.
	 *
	 * @param \DOMNode              $node        Current node.
	 * @param string                $source_lang Source language.
	 * @param array<string, string> $stored      Stored translations.
	 * @param bool                  $excluded    Ancestor exclusion state.
	 */
	private function walk( \DOMNode $node, string $source_lang, array $stored, bool $excluded ): void {
		if ( $node instanceof \DOMElement ) {
			$excluded = $excluded || $this->is_excluded_element( $node );
			if ( ! $excluded ) {
				foreach ( self::ATTRIBUTES as $attribute ) {
					if ( $node->hasAttribute( $attribute ) ) {
						$translation = $this->lookup( $node->getAttribute( $attribute ), $source_lang, $stored );
						if ( null !== $translation ) {
							$node->setAttribute( $attribute, $this->safe_attribute( $translation ) );
						}
					}
				}
				if ( 'meta' === strtolower( $node->tagName ) && $this->is_translatable_meta( $node ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
					$translation = $this->lookup( $node->getAttribute( 'content' ), $source_lang, $stored );
					if ( null !== $translation ) {
						$node->setAttribute( 'content', $this->safe_attribute( $translation ) );
					}
				}
			}
		}

		if ( ! $excluded && XML_TEXT_NODE === $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			$translation = $this->lookup( (string) $node->nodeValue, $source_lang, $stored ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			if ( null !== $translation ) {
				$this->replace_text_node( $node, $translation );
				return;
			}
		}

		$children = iterator_to_array( $node->childNodes ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		foreach ( $children as $child ) {
			$this->walk( $child, $source_lang, $stored, $excluded );
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
	 * @param \DOMNode $text_node   Text node to replace.
	 * @param string   $translation Sanitized translation.
	 */
	private function replace_text_node( \DOMNode $text_node, string $translation ): void {
		$parent = $text_node->parentNode; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		$owner  = $text_node->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		if ( null === $parent || null === $owner ) {
			return;
		}

		$fragment = new \DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$loaded   = $fragment->loadHTML( '<?xml encoding="UTF-8"><div id="omtr-fragment">' . Sanitizer::inline_html( $translation ) . '</div>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( ! $loaded ) {
			return;
		}
		$wrapper = $fragment->getElementById( 'omtr-fragment' );
		if ( null === $wrapper ) {
			return;
		}
		foreach ( iterator_to_array( $wrapper->childNodes ) as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			$parent->insertBefore( $owner->importNode( $child, true ), $text_node );
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
	 * @param \DOMElement $element Element.
	 */
	private function is_excluded_element( \DOMElement $element ): bool {
		if ( in_array( strtolower( $element->tagName ), array( 'script', 'style', 'code', 'pre', 'textarea' ), true ) || $element->hasAttribute( 'data-omtr-skip' ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			return true;
		}
		$classes = preg_split( '/\s+/', $element->getAttribute( 'class' ) );
		$classes = false === $classes ? array() : $classes;
		return in_array( 'notranslate', $classes, true );
	}

	/**
	 * Meta translation allowlist.
	 *
	 * @param \DOMElement $element Meta element.
	 */
	private function is_translatable_meta( \DOMElement $element ): bool {
		$name = $element->getAttribute( 'name' );
		$key  = strtolower( '' !== $name ? $name : $element->getAttribute( 'property' ) );
		return 'description' === $key || str_starts_with( $key, 'og:' ) || str_starts_with( $key, 'twitter:' );
	}

	/**
	 * Set the root HTML language.
	 *
	 * @param \DOMDocument $document Document.
	 */
	private function set_document_language( \DOMDocument $document ): void {
		$elements = $document->getElementsByTagName( 'html' );
		if ( $elements->length > 0 ) {
			$root = $elements->item( 0 );
			if ( $root instanceof \DOMElement ) {
				$root->setAttribute( 'lang', esc_attr( $this->current_language->get() ) );
			}
		}
	}

	/**
	 * Rewrite same-site links for the active language.
	 *
	 * @param \DOMDocument $document Document.
	 */
	private function rewrite_links( \DOMDocument $document ): void {
		foreach ( $document->getElementsByTagName( 'a' ) as $anchor ) {
			if ( $anchor->hasAttribute( 'href' ) ) {
				$href = $anchor->getAttribute( 'href' );
				$anchor->setAttribute( 'href', $this->url_translator->translate( $href, $this->current_language->get() ) );
			}
		}
	}
}
