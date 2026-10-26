<?php
/**
 * Plugin Name:       Omnium Translate
 * Plugin URI:        https://github.com/omniumdigital/traduction_wp
 * Description:       Traduction automatique par IA, sans abonnement : tout tourne sur votre site, avec votre propre clé API.
 * Version:           1.1.1
 * Requires at least: 6.9
 * Requires PHP:      8.1
 * Author:            Omnium Digital
 * Author URI:        https://omnium-digital.fr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       omnium-translate
 * Domain Path:       /languages
 * WC requires at least: 9.0
 * WC tested up to:     11.2
 *
 * @package Omnium\Translate
 */

defined( 'ABSPATH' ) || exit;

define( 'OMTR_VERSION', '1.1.1' );
define( 'OMTR_FILE', __FILE__ );
define( 'OMTR_DIR', plugin_dir_path( __FILE__ ) );
define( 'OMTR_URL', plugin_dir_url( __FILE__ ) );
define( 'OMTR_MIN_PHP', '8.1' );

// Without the native sodium extension, WordPress loads its bundled sodium_compat
// polyfill (wp-includes/compat.php) before plugins, so only the functions matter.
$omtr_missing = '';
if ( version_compare( PHP_VERSION, OMTR_MIN_PHP, '<' ) ) {
	$omtr_missing = 'php';
} elseif ( ! function_exists( 'sodium_crypto_secretbox' ) || ! function_exists( 'sodium_crypto_secretbox_open' ) ) {
	$omtr_missing = 'sodium';
} elseif ( ! is_readable( OMTR_DIR . 'vendor/autoload.php' ) ) {
	$omtr_missing = 'vendor';
}
if ( '' !== $omtr_missing ) {
	add_action(
		'admin_notices',
		static function () use ( $omtr_missing ): void {
			// Only administrators can act on it, and it reveals the PHP version.
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			// Built here, after init, so translations are not loaded too early.
			$messages = array(
				/* translators: 1: required PHP version, 2: current PHP version. */
				'php'    => sprintf( __( 'Omnium Translate requires PHP %1$s or later (current version: %2$s). Ask your host to upgrade PHP.', 'omnium-translate' ), OMTR_MIN_PHP, PHP_VERSION ),
				'sodium' => __( 'Omnium Translate cannot encrypt API keys: neither the PHP sodium extension nor the WordPress sodium_compat library is available.', 'omnium-translate' ),
				'vendor' => __( 'Omnium Translate is incomplete (vendor folder missing). Install the omnium-translate.zip file from the GitHub release, not the source code archive.', 'omnium-translate' ),
			);
			echo '<div class="notice notice-error"><p>' . esc_html( $messages[ $omtr_missing ] ) . '</p></div>';
		}
	);
	return;
}
unset( $omtr_missing );

require_once OMTR_DIR . 'vendor/autoload.php';

/**
 * Return a language switcher for use in theme templates.
 *
 * @param array<string, mixed> $args Display options.
 */
function omtr_switcher( array $args = array() ): string {
	$container = \Omnium\Translate\Core\Plugin::instance()->container();
	if ( ! $container->has( \Omnium\Translate\Switcher\Switcher::class ) ) {
		return '';
	}
	return $container->get( \Omnium\Translate\Switcher\Switcher::class )->render( $args );
}

\Omnium\Translate\Core\ActionSchedulerLoader::register();

register_activation_hook( __FILE__, array( \Omnium\Translate\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Omnium\Translate\Core\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \Omnium\Translate\Core\Plugin::instance(), 'boot' ) );
