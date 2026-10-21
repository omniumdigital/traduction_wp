<?php
/**
 * Language of the current request.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Set once by the router from the URL prefix; read by Render, Seo, Woo and Switcher.
 */
final class CurrentLanguage {

	/**
	 * Current language code, null until the router ran.
	 *
	 * @var string|null
	 */
	private ?string $lang = null;

	/**
	 * Constructor.
	 *
	 * @param string $source_lang Source language (no URL prefix).
	 */
	public function __construct( private string $source_lang ) {}

	/**
	 * Set by the router.
	 *
	 * @param string $lang Language code.
	 */
	public function set( string $lang ): void {
		$this->lang = $lang;
	}

	/**
	 * Current language, the source language by default.
	 */
	public function get(): string {
		return $this->lang ?? $this->source_lang;
	}

	/**
	 * Source language.
	 */
	public function source(): string {
		return $this->source_lang;
	}

	/**
	 * Whether the request targets a translated language.
	 */
	public function is_translated(): bool {
		return $this->get() !== $this->source_lang;
	}
}
