<?php
/**
 * Segment extractor test double.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit\Doubles;

use Omnium\Translate\Render\SegmentExtractorInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Returns canned segments and records the HTML it received.
 */
final class FakeSegmentExtractor implements SegmentExtractorInterface {

	/**
	 * Segments to return: hash => text.
	 *
	 * @var array<string, string>
	 */
	public array $segments = array();

	/**
	 * HTML received by the last extract call.
	 */
	public string $received_html = '';

	/**
	 * Source language received by the last extract call.
	 */
	public string $received_lang = '';

	/**
	 * {@inheritDoc}
	 *
	 * @param string $html        Full HTML document.
	 * @param string $source_lang Source language.
	 * @return array<string, string>
	 */
	public function extract( string $html, string $source_lang ): array {
		$this->received_html = $html;
		$this->received_lang = $source_lang;
		return $this->segments;
	}
}
