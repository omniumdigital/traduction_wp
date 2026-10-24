<?php
/**
 * Minimal Elementor runtime doubles for unit tests.
 *
 * @package Omnium\Translate
 */

namespace Elementor;

if ( ! class_exists( Widget_Base::class ) ) {
	/** Minimal widget base surface used by the integration tests. */
	abstract class Widget_Base {
		/** @var array<string, mixed> */
		public array $test_settings = array();

		/** @var array<string, array<string, mixed>> */
		public array $test_controls = array();

		/** @param array<string, mixed> $data Widget data. */
		public function __construct( array $data = array(), ?array $args = null ) {
			unset( $data, $args );
		}

		/** @param array<string, mixed> $args Section arguments. */
		protected function start_controls_section( string $id, array $args ): void {
			unset( $id, $args );
		}

		/** @param array<string, mixed> $args Control arguments. */
		protected function add_control( string $id, array $args ): void {
			$this->test_controls[ $id ] = $args;
		}

		protected function end_controls_section(): void {}

		/** @return array<string, mixed> */
		public function get_settings_for_display(): array {
			return $this->test_settings;
		}
	}
}

if ( ! class_exists( Controls_Manager::class ) ) {
	/** Elementor control constants used by the widget. */
	final class Controls_Manager {
		public const SELECT = 'select';
	}
}

if ( ! class_exists( Widgets_Manager::class ) ) {
	/** Minimal widget registry. */
	final class Widgets_Manager {
		/** @var list<Widget_Base> */
		public array $registered = array();

		public function register( Widget_Base $widget ): void {
			$this->registered[] = $widget;
		}
	}
}
