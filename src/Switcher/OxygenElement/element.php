<?php
/**
 * Oxygen 6 language-switcher element.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher\OxygenElement;

defined( 'ABSPATH' ) || exit;

use function Breakdance\Elements\c;

\Breakdance\ElementStudio\registerElementForEditing(
	'OxygenElement\\LanguageSwitcher',
	\Breakdance\Util\getDirectoryPathRelativeToPluginFolder( __DIR__ )
);

/**
 * Element Studio definition for the shared language switcher.
 */
final class LanguageSwitcher extends \Breakdance\Elements\Element {

	/**
	 * Return the Oxygen builder icon.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function uiIcon() {
		return 'GlobeIcon';
	}

	/**
	 * Return the wrapper tag.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function tag() {
		return 'div';
	}

	/**
	 * Return the translated element name.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function name() {
		return __( 'Language selector', 'omnium-translate' );
	}

	/**
	 * Return the generated CSS class.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function className() {
		return 'omtr-oxygen-switcher';
	}

	/**
	 * Return the Oxygen element category.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function category() {
		return 'other';
	}

	/**
	 * Return the stable element slug.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function slug() {
		return __CLASS__;
	}

	/**
	 * Return the server-side rendering marker.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function template() {
		return '%%SSR%%';
	}

	/**
	 * Return default control values.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function defaultProperties() {
		return array(
			'variant' => 'dropdown',
			'display' => 'flag-name',
		);
	}

	/**
	 * Return the layout and display controls.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function contentControls() {
		return array(
			c(
				'variant',
				__( 'Layout', 'omnium-translate' ),
				array(),
				array(
					'type'  => 'dropdown',
					'items' => array(
						array(
							'value' => 'dropdown',
							'text'  => __( 'Dropdown', 'omnium-translate' ),
						),
						array(
							'value' => 'inline',
							'text'  => __( 'Inline', 'omnium-translate' ),
						),
						array(
							'value' => 'floating',
							'text'  => __( 'Floating', 'omnium-translate' ),
						),
					),
				)
			),
			c(
				'display',
				__( 'Display', 'omnium-translate' ),
				array(),
				array(
					'type'  => 'dropdown',
					'items' => array(
						array(
							'value' => 'flag-name',
							'text'  => __( 'Flag and name', 'omnium-translate' ),
						),
						array(
							'value' => 'flag',
							'text'  => __( 'Flag only', 'omnium-translate' ),
						),
						array(
							'value' => 'name',
							'text'  => __( 'Name only', 'omnium-translate' ),
						),
						array(
							'value' => 'code',
							'text'  => __( 'Language code', 'omnium-translate' ),
						),
					),
				)
			),
		);
	}

	/**
	 * Restrict this element to Oxygen mode.
	 *
	 * @phpstan-ignore missingType.return
	 */
	public static function availableIn() {
		return array( 'oxygen' );
	}
}
