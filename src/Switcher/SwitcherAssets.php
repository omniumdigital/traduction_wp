<?php
/**
 * Conditional language switcher assets.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Loads front assets only after a switcher is rendered.
 */
final class SwitcherAssets {

	/**
	 * Whether the assets were already requested on this page.
	 *
	 * @var bool
	 */
	private bool $enqueued = false;

	/** Enqueue the small, dependency-free front bundle once. */
	public function enqueue(): void {
		if ( $this->enqueued ) {
			return;
		}
		$this->enqueued = true;
		wp_enqueue_style( 'omtr-switcher', OMTR_URL . 'assets/css/switcher.css', array(), OMTR_VERSION );
		wp_enqueue_script( 'omtr-switcher', OMTR_URL . 'assets/build/switcher.js', array(), OMTR_VERSION, true );
	}

	/**
	 * Print a stylesheet enqueued by a switcher rendered after wp_head.
	 *
	 * Shortcodes in post content run after WordPress normally prints styles.
	 */
	public function print_late_styles(): void {
		if ( $this->enqueued && ! wp_style_is( 'omtr-switcher', 'done' ) ) {
			wp_print_styles( 'omtr-switcher' );
		}
	}
}
