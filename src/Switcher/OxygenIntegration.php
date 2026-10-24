<?php
/**
 * Oxygen 6 language-switcher integration.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the optional Oxygen Element Studio save location.
 */
final class OxygenIntegration {

	/**
	 * Prevent duplicate save locations when Oxygen fires its lifecycle hook.
	 *
	 * @var bool
	 */
	private bool $registered = false;

	/**
	 * Register the element directory when Oxygen 6 is available.
	 *
	 * Oxygen loads the element PHP files itself after this hook. Registering a
	 * save location (rather than requiring the element here) keeps Oxygen
	 * optional and follows its third-party element contract.
	 */
	public function register_elements(): void {
		if ( $this->registered ) {
			return;
		}
		if ( ! defined( 'BREAKDANCE_MODE' ) || 'oxygen' !== BREAKDANCE_MODE ) {
			return;
		}
		if ( ! function_exists( '\Breakdance\ElementStudio\registerSaveLocation' ) ) {
			return;
		}
		$this->registered = true;

		$plugin_directory = dirname( plugin_basename( OMTR_FILE ) );
		\Breakdance\ElementStudio\registerSaveLocation(
			$plugin_directory . '/src/Switcher',
			'OmniumTranslate',
			'element',
			__( 'Omnium Translate', 'omnium-translate' ),
			false,
			true
		);
	}
}
