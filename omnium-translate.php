<?php
/**
 * Plugin Name:       Omnium Translate
 * Plugin URI:        https://github.com/omniumdigital/traduction_wp
 * Description:       Traduction automatique par IA, sans abonnement : tout tourne sur votre site, avec votre propre clé API.
 * Version:           0.1.0
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

define( 'OMTR_VERSION', '0.1.0' );
define( 'OMTR_FILE', __FILE__ );
define( 'OMTR_DIR', plugin_dir_path( __FILE__ ) );
define( 'OMTR_URL', plugin_dir_url( __FILE__ ) );
define( 'OMTR_MIN_PHP', '8.1' );

if ( version_compare( PHP_VERSION, OMTR_MIN_PHP, '<' ) || ! extension_loaded( 'sodium' ) || ! is_readable( OMTR_DIR . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Omnium Translate requiert PHP 8.1+, l\'extension sodium et une installation complète (dossier vendor).', 'omnium-translate' ) . '</p></div>';
		}
	);
	return;
}

require_once OMTR_DIR . 'vendor/autoload.php';

\Omnium\Translate\Core\ActionSchedulerLoader::register();

register_activation_hook( __FILE__, array( \Omnium\Translate\Core\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \Omnium\Translate\Core\Deactivator::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \Omnium\Translate\Core\Plugin::instance(), 'boot' ) );
