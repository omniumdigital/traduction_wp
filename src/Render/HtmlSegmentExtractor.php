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

	private const ATTRIBUTES                 = array( 'alt', 'title', 'placeholder', 'aria-label' );
	private const SENSITIVE_ELEMENTS_PATTERN = '~<(script|style|svg|math|template)\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>.*?</\1\s*>~is';

	/**
	 * Original sensitive blocks and the temporary charset marker, keyed by fallback document.
	 *
	 * On PHP below 8.4, DOMDocument uses an HTML4 parser which mutates raw-text
	 * content and case-sensitive foreign markup. Those blocks are therefore kept
	 * out of the parser and restored byte for byte when the document is saved.
	 * Consequently, template contents and SVG titles are not extracted on this
	 * fallback path.
	 *
	 * @var \WeakMap<\DOMDocument, array{blocks: array<int, string>, token: string}>|null
	 */
	private static ?\WeakMap $fallback_state = null;

	/**
	 * Meta name/property keys whose content is human text. Other SEO metas
	 * (og:locale, og:type, og:url, og:image, twitter:card…) hold constants
	 * or URLs that must never be sent to translation.
	 */
	private const TRANSLATABLE_META = array( 'description', 'og:title', 'og:description', 'og:site_name', 'twitter:title', 'twitter:description' );

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
		list( $protected_html, $blocks, $token ) = self::protect_fallback_html( $html );
		$document                                = new \DOMDocument( '1.0', 'UTF-8' );
		$previous                                = libxml_use_internal_errors( true );
		$loaded                                  = $document->loadHTML( $protected_html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		if ( $loaded ) {
			self::$fallback_state            ??= new \WeakMap();
			self::$fallback_state[ $document ] = array(
				'blocks' => $blocks,
				'token'  => $token,
			);
		}
		return $loaded ? $document : null;
	}

	/**
	 * Serialize a parsed document with its matching HTML parser.
	 *
	 * @param \DOMDocument|\Dom\HTMLDocument $document Parsed HTML document.
	 */
	public static function save_document( \DOMDocument|\Dom\HTMLDocument $document ): string|false {
		if ( $document instanceof \DOMDocument ) {
			$output = $document->saveHTML();
			if ( false === $output ) {
				return false;
			}
			$output = self::decode_spurious_entities( $output );
			if ( null === self::$fallback_state || ! isset( self::$fallback_state[ $document ] ) ) {
				return $output;
			}
			$state = self::$fallback_state[ $document ];
			unset( self::$fallback_state[ $document ] );
			return self::restore_fallback_html( $output, $state['blocks'], $state['token'] );
		}
		return $document->saveHtml();
	}

	/**
	 * Undo the legacy DOMDocument HTML4 serializer's quirk of entity-encoding
	 * every non-ASCII character in text nodes created or imported after the
	 * initial parse (e.g. inserted translations), while leaving the five
	 * structurally significant XML entities untouched.
	 *
	 * @param string $html Serialized HTML.
	 */
	private static function decode_spurious_entities( string $html ): string {
		static $placeholders = array(
			'&amp;'  => "\x01amp\x01",
			'&lt;'   => "\x01lt\x01",
			'&gt;'   => "\x01gt\x01",
			'&quot;' => "\x01quot\x01",
			'&#039;' => "\x01apos\x01",
		);
		$protected           = strtr( $html, $placeholders );
		$decoded             = html_entity_decode( $protected, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return strtr( $decoded, array_flip( $placeholders ) );
	}

	/**
	 * Translate a protected JSON-LD script in the HTML4 parser fallback.
	 *
	 * @param \DOMElement                            $placeholder Placeholder for a protected block.
	 * @param string                                 $source_lang Source language.
	 * @param string                                 $target_lang Target language.
	 * @param array<string, string>                  $stored      Stored translations.
	 * @param \Omnium\Translate\Router\UrlTranslator $urls Internal URL translator.
	 */
	public static function translate_json_ld_placeholder( \DOMElement $placeholder, string $source_lang, string $target_lang, array $stored, \Omnium\Translate\Router\UrlTranslator $urls ): void {
		$document = $placeholder->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
		if ( null === $document || null === self::$fallback_state || ! isset( self::$fallback_state[ $document ] ) ) {
			return;
		}
		$state  = self::$fallback_state[ $document ];
		$marker = $placeholder->getAttribute( 'data-omtr-placeholder' );
		$prefix = $state['token'] . '-';
		if ( ! str_starts_with( $marker, $prefix ) || ! ctype_digit( substr( $marker, strlen( $prefix ) ) ) ) {
			return;
		}
		$index = (int) substr( $marker, strlen( $prefix ) );
		$block = $state['blocks'][ $index ] ?? '';
		if ( ! preg_match( '~^(<script\b[^>]*\btype=["\']application/ld\+json["\'][^>]*>)(.*?)(</script\s*>)$~is', $block, $matches ) ) {
			return;
		}
		$state['blocks'][ $index ]         = $matches[1] . JsonLdProcessor::translate( $matches[2], $source_lang, $target_lang, $stored, $urls ) . $matches[3];
		self::$fallback_state[ $document ] = $state;
	}

	/**
	 * Replace raw-text and foreign-content elements before the HTML4 fallback parses them.
	 *
	 * @param string $html Original HTML.
	 * @return array{string, array<int, string>, string} Protected HTML, blocks and marker token.
	 */
	private static function protect_fallback_html( string $html ): array {
		$blocks = array();
		$token  = substr( hash( 'sha256', $html ), 0, 20 );
		while ( str_contains( $html, $token ) ) {
			$token .= 'x';
		}

		$protected = preg_replace_callback(
			self::SENSITIVE_ELEMENTS_PATTERN,
			static function ( array $matches ) use ( &$blocks, $token ): string {
				$index            = count( $blocks );
				$blocks[ $index ] = $matches[0];
				return '<omtr-ph data-omtr-placeholder="' . $token . '-' . $index . '"></omtr-ph>';
			},
			$html
		);
		$protected = null === $protected ? $html : $protected;
		$meta      = '<meta charset="UTF-8" data-omtr-charset="' . $token . '">';
		$with_meta = preg_replace_callback(
			'~<head\b(?:"[^"]*"|\'[^\']*\'|[^\'">])*?>~i',
			static fn( array $matches ): string => $matches[0] . $meta,
			$protected,
			1,
			$count
		);

		return array( 0 === $count || null === $with_meta ? $meta . $protected : $with_meta, $blocks, $token );
	}

	/**
	 * Remove the charset marker and restore protected blocks byte for byte.
	 *
	 * @param string             $html   Serialized fallback document.
	 * @param array<int, string> $blocks Original sensitive blocks.
	 * @param string             $token  Unique marker token.
	 */
	private static function restore_fallback_html( string $html, array $blocks, string $token ): string {
		$quoted_token = preg_quote( $token, '~' );
		$restored     = preg_replace(
			'~<meta\b(?=[^>]*\bdata-omtr-charset=["\']' . $quoted_token . '["\'])[^>]*>~i',
			'',
			$html
		);
		$restored     = null === $restored ? $html : $restored;

		foreach ( $blocks as $index => $block ) {
			$pattern  = '~<omtr-ph\b(?=[^>]*\bdata-omtr-placeholder=["\']' . $quoted_token . '-' . $index . '["\'])[^>]*>\s*</omtr-ph>~i';
			$replaced = preg_replace_callback( $pattern, static fn(): string => $block, $restored, 1 );
			$restored = null === $replaced ? $restored : $replaced;
		}

		return $restored;
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
			$tag = strtolower( $node->tagName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			if ( ! $excluded && 'omtr-ph' === $tag && $node instanceof \DOMElement && null !== self::$fallback_state ) {
				$document = $node->ownerDocument; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
				if ( null !== $document && isset( self::$fallback_state[ $document ] ) ) {
					$state  = self::$fallback_state[ $document ];
					$marker = $node->getAttribute( 'data-omtr-placeholder' );
					$prefix = $state['token'] . '-';
					if ( str_starts_with( $marker, $prefix ) ) {
						$index = (int) substr( $marker, strlen( $prefix ) );
						$block = $state['blocks'][ $index ] ?? '';
						if ( preg_match( '~^<script\b[^>]*\btype=["\']application/ld\+json["\'][^>]*>(.*?)</script\s*>$~is', $block, $matches ) ) {
							$segments = array_merge( $segments, JsonLdProcessor::segments( $matches[1], $source_lang, $this->pii_filter ) );
						}
					}
				}
			}

			if ( ! $excluded && 'script' === $tag && 'application/ld+json' === strtolower( trim( (string) $node->getAttribute( 'type' ) ) ) ) {
				$segments = array_merge( $segments, JsonLdProcessor::segments( (string) $node->textContent, $source_lang, $this->pii_filter ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
			}
			$excluded = $excluded || $this->is_excluded_element( $node );
			if ( ! $excluded ) {
				foreach ( self::ATTRIBUTES as $attribute ) {
					if ( 'link' === $tag && 'title' === $attribute ) {
						continue; // Link titles (feeds, oEmbed) are never visible page text.
					}
					if ( $node->hasAttribute( $attribute ) ) {
						$this->add( $node->getAttribute( $attribute ), $source_lang, $segments );
					}
				}
				if ( 'meta' === $tag && $this->is_translatable_meta( $node ) ) {
					$this->add( $node->getAttribute( 'content' ), $source_lang, $segments );
				}
				// A textarea placeholder is public interface text, but its child
				// text is a visitor-provided form value and must stay excluded.
				$excluded = 'textarea' === strtolower( $node->tagName ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Native DOM API.
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
		if ( in_array( $tag, array( 'script', 'style', 'code', 'pre' ), true ) ) {
			return true;
		}
		if ( $element->hasAttribute( 'data-omtr-skip' ) ) {
			return true;
		}
		$classes = preg_split( '/\s+/', (string) $element->getAttribute( 'class' ) );
		$classes = false === $classes ? array() : $classes;
		return in_array( 'notranslate', $classes, true );
	}

	/**
	 * Whether a meta content attribute is translatable human text.
	 *
	 * @param \DOMElement|\Dom\Element $element Meta element.
	 */
	private function is_translatable_meta( \DOMElement|\Dom\Element $element ): bool {
		$name = (string) $element->getAttribute( 'name' );
		$key  = strtolower( '' !== $name ? $name : (string) $element->getAttribute( 'property' ) );
		return in_array( $key, self::TRANSLATABLE_META, true );
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
