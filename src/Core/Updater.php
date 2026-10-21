<?php
/**
 * Update checks against public GitHub Releases.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

/**
 * Wires Plugin Update Checker. No GitHub token, ever.
 */
final class Updater {

	public const REPOSITORY = 'https://github.com/omniumdigital/traduction_wp/';

	/**
	 * Register the update checker unless disabled.
	 */
	public function boot(): void {
		if ( get_option( Options::UPDATES_DISABLED ) ) {
			return;
		}
		$checker = PucFactory::buildUpdateChecker( self::REPOSITORY, OMTR_FILE, 'omnium-translate' );
		$api     = $checker->getVcsApi();
		if ( method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets( '/omnium-translate\.zip$/' );
		}
	}
}
