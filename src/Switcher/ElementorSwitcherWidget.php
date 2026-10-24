<?php
/**
 * Elementor language-switcher widget.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the shared switcher renderer in Elementor.
 */
final class ElementorSwitcherWidget extends \Elementor\Widget_Base {

	/**
	 * Renderer injected when Elementor registers the widget type.
	 *
	 * Elementor later creates full widget instances itself with its native
	 * `(data, args)` constructor, so constructor injection is not available.
	 *
	 * @var Switcher|null
	 */
	private static ?Switcher $switcher = null;

	/**
	 * Inject the shared renderer before registering the prototype.
	 *
	 * @param Switcher $switcher Language switcher renderer.
	 */
	public static function set_switcher( Switcher $switcher ): void {
		self::$switcher = $switcher;
	}

	/** Widget identifier. */
	public function get_name(): string {
		return 'omtr-switcher';
	}

	/** Widget title. */
	public function get_title(): string {
		return __( 'Language selector', 'omnium-translate' );
	}

	/** Elementor icon identifier. */
	public function get_icon(): string {
		return 'eicon-globe';
	}

	/**
	 * Elementor widget category.
	 *
	 * @return list<string>
	 */
	public function get_categories(): array {
		return array( 'general' );
	}

	/**
	 * Search keywords in Elementor's widget browser.
	 *
	 * @return list<string>
	 */
	public function get_keywords(): array {
		return array( 'language', 'translation', 'switcher', 'locale' );
	}

	/** Register the two presentation controls. */
	protected function register_controls(): void {
		$this->start_controls_section(
			'content',
			array( 'label' => __( 'Language selector', 'omnium-translate' ) )
		);
		$this->add_control(
			'variant',
			array(
				'label'   => __( 'Layout', 'omnium-translate' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'dropdown',
				'options' => array(
					'dropdown' => __( 'Dropdown', 'omnium-translate' ),
					'inline'   => __( 'Inline', 'omnium-translate' ),
					'floating' => __( 'Floating', 'omnium-translate' ),
				),
			)
		);
		$this->add_control(
			'display',
			array(
				'label'   => __( 'Display', 'omnium-translate' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'flag-name',
				'options' => array(
					'flag-name' => __( 'Flag and name', 'omnium-translate' ),
					'flag'      => __( 'Flag only', 'omnium-translate' ),
					'name'      => __( 'Name only', 'omnium-translate' ),
					'code'      => __( 'Language code', 'omnium-translate' ),
				),
			)
		);
		$this->end_controls_section();
	}

	/** Render through the audited server-side component. */
	protected function render(): void {
		if ( null === self::$switcher ) {
			return;
		}
		$settings = $this->get_settings_for_display();
		$html     = self::$switcher->render(
			array(
				'variant' => isset( $settings['variant'] ) ? (string) $settings['variant'] : 'dropdown',
				'display' => isset( $settings['display'] ) ? (string) $settings['display'] : 'flag-name',
			)
		);
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Switcher escapes every dynamic URL, attribute and label.
	}
}
