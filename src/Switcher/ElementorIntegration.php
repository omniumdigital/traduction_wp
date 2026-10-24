<?php
/**
 * Elementor language-switcher integration.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the optional Elementor widget when Elementor initializes widgets.
 */
final class ElementorIntegration {

	/**
	 * Constructor.
	 *
	 * @param Switcher $switcher Language switcher renderer.
	 */
	public function __construct( private Switcher $switcher ) {}

	/**
	 * Register the widget with Elementor's manager.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widget manager.
	 */
	public function register_widget( \Elementor\Widgets_Manager $widgets_manager ): void {
		ElementorSwitcherWidget::set_switcher( $this->switcher );
		$widgets_manager->register( new ElementorSwitcherWidget() );
	}
}
