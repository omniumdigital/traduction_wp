<?php
/**
 * Glossary value object passed to providers.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Rules for one target language plus site tone and context.
 */
final class Glossary {

	/**
	 * Constructor.
	 *
	 * @param array<string, string> $translate Term => forced translation.
	 * @param list<string>          $keep      Terms never translated.
	 * @param string                $tone      Site tone.
	 * @param string                $context   Site context.
	 */
	public function __construct(
		public readonly array $translate = array(),
		public readonly array $keep = array(),
		public readonly string $tone = '',
		public readonly string $context = ''
	) {}
}
