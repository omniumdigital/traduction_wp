<?php
/**
 * Gutenberg language-switcher block.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Registers a dynamic block backed by the shared server renderer.
 */
final class GutenbergBlock {

	/**
	 * Constructor.
	 *
	 * @param Switcher $switcher Language switcher renderer.
	 */
	public function __construct( private Switcher $switcher ) {}

	/** Register block metadata and its server render callback. */
	public function register(): void {
		register_block_type(
			OMTR_DIR . 'assets/blocks/switcher',
			array( 'render_callback' => array( $this, 'render' ) )
		);
	}

	/**
	 * Render the dynamic block.
	 *
	 * @param array<string, mixed> $attributes Validated block attributes.
	 */
	public function render( array $attributes = array() ): string {
		return $this->switcher->render( $attributes );
	}
}
