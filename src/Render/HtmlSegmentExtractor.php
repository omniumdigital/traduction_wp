<?php
/**
 * HTML segment extractor.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Render;

use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Store\SegmentKey;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts visible text and selected attributes from a rendered HTML document.
 */
final class HtmlSegmentExtractor implements SegmentExtractorInterface {

	private const ATTRIBUTES = array( 'alt', 'title', 'placeholder', 'aria-label' );

	/**
	 * Constructor.
	 *
	 * @param PiiFilter $pii_filter Personal-data filter.
	 */
	public function __construct( private PiiFilter $pii_filter ) {}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $html        Full HTML document.
	 * @param string $source_lang Source language.
	 */
	public function extract( string $html, string $source_lang ): array {
		$document = self::load_document( $html );
		if ( null === $document ) {
			return array();
		}
		return $this->extract_from_document( $document, $source_lang );
	}

	/**
	 * Extract segments from an already parsed document.
	 *
	 * @param \DOMDocument|\Dom\HTMLDocument $document   Parsed HTML document.
	 * @param string                         $source_lang Source language.
	 * @return array<string, string> SegmentKey hash => normalized source text.
	 */
	public function extract_from_document( \DOMDocument|\Dom\HTMLDocument $document, string $source_lang ): array {
		$segments = array();
		$this->walk( $document, $source_lang, $segments, false );
		return $segments;
	}

	/**
	 * Load possibly imperfect theme HTML without emitting warnings.
	 *
	 * @param string $html HTML document.
	 */
	public static function load_document( string $html ): \DOMDocument|\Dom\HTMLDocument|null {
		if ( '' === trim( $html ) ) {
			return null;
		}
		if ( class_exists( 'Dom\\HTMLDocument' ) ) {
			$document_class = 'Dom\\HTMLDocument';
			try {
				return $document_class::createFromString( $html, LIBXML_NOERROR );
			} catch ( \ValueError ) {
				return null;
			}
		}
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
	 * Serialize a parsed document with its matching HTML parser.
	 *
	 * @param \DOMDocument|\Dom\HTMLDocument $document Parsed HTML document.
	 */
	public static function save_document( \DOMDocument|\Dom\HTMLDocument $document ): string|false {
		if ( $document instanceof \DOMDocument ) {
			return $document->saveHTML();
		}
		return $document->saveHtml();
	}

	/**
	 * Walk DOM nodes and collect eligible strings.
	 *
	 * @param \DOMNode|\Dom\Node    $node        Current node.
	 * @param string                $source_lang Source language.
	 * @param array<string, string> $segments    Accumulator.
	 * @param bool                  $excluded    Whether an ancestor is excluded.
	 */
	private function walk( \DOMNode|\Dom\Node $node, string $source_lang, array &$segments, bool $excluded ): void {
		if ( $node instanceof \DOMElement || $node instanceof \Dom\Element ) {
			$excluded = $excluded || $this->is_excluded_element( $node );
			if ( ! $excluded ) {
				foreach ( self::ATTRIBUTES as $attribute ) {
					if ( $node->hasAttribute( $attribute ) ) {
						$this->add( $node->getAttribute( $attribute ), $source_lang, $segments );
					}
				}
				if ( 'meta' === strtolower( $node->tagName ) && $this->is_translatable_meta( $node ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
					$this->add( $node->getAttribute( 'content' ), $source_lang, $segments );
				}
			}
		}

		if ( ! $excluded && XML_TEXT_NODE === $node->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			$this->add( (string) $node->nodeValue, $source_lang, $segments ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		}
		foreach ( $node->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			$this->walk( $child, $source_lang, $segments, $excluded );
		}
	}

	/**
	 * Whether an element and its descendants must be ignored.
	 *
	 * @param \DOMElement|\Dom\Element $element Element.
	 */
	private function is_excluded_element( \DOMElement|\Dom\Element $element ): bool {
		$tag = strtolower( $element->tagName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		if ( in_array( $tag, array( 'script', 'style', 'code', 'pre', 'textarea' ), true ) ) {
			return true;
		}
		if ( $element->hasAttribute( 'data-omtr-skip' ) ) {
			return true;
		}
		$classes = preg_split( '/\s+/', $element->getAttribute( 'class' ) );
		$classes = false === $classes ? array() : $classes;
		return in_array( 'notranslate', $classes, true );
	}

	/**
	 * Whether a meta content attribute is translatable.
	 *
	 * @param \DOMElement|\Dom\Element $element Meta element.
	 */
	private function is_translatable_meta( \DOMElement|\Dom\Element $element ): bool {
		$name = $element->getAttribute( 'name' );
		$key  = strtolower( '' !== $name ? $name : $element->getAttribute( 'property' ) );
		return 'description' === $key || str_starts_with( $key, 'og:' ) || str_starts_with( $key, 'twitter:' );
	}

	/**
	 * Add one normalized, non-PII, textual segment.
	 *
	 * @param string                $text        Candidate text.
	 * @param string                $source_lang Source language.
	 * @param array<string, string> $segments    Accumulator.
	 */
	private function add( string $text, string $source_lang, array &$segments ): void {
		$normalized = SegmentKey::normalize( $text );
		if ( '' === $normalized || 1 !== preg_match( '/[\p{L}\p{N}]/u', $normalized ) || $this->pii_filter->contains_pii( $normalized ) ) {
			return;
		}
		$segments[ SegmentKey::hash( $source_lang, $normalized ) ] = $normalized;
	}
}
