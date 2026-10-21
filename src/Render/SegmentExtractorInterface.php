<?php
/**
 * Segment extraction contract.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Extracts translatable segments from a final rendered HTML document.
 * Used by the front renderer and by the queue when scanning a page.
 */
interface SegmentExtractorInterface {

	/**
	 * Extract segments, honoring exclusions (data-omtr-skip, .notranslate, script,
	 * style, code, pre, form values) and dropping segments flagged by the PiiFilter.
	 *
	 * @param string $html        Full HTML document.
	 * @param string $source_lang Source language.
	 * @return array<string, string> SegmentKey hash => normalized source text.
	 */
	public function extract( string $html, string $source_lang ): array;
}
