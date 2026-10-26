<?php
/**
 * Plugin entry point.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the container and boots modules.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Service container.
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private bool $booted = false;

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->container = new Container();
	}

	/**
	 * Get the singleton.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Container accessor.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Load the plugin's translations. Hooked on `init` (see boot()).
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'omnium-translate', false, dirname( plugin_basename( OMTR_FILE ) ) . '/languages' );
	}

	/**
	 * Ordered list of modules. Order matters: dependencies first.
	 *
	 * @return list<ModuleInterface>
	 */
	private function modules(): array {
		return array(
			new CoreModule(),
			new \Omnium\Translate\Security\SecurityModule(),
			new \Omnium\Translate\Store\StoreModule(),
			new \Omnium\Translate\Providers\ProvidersModule(),
			new \Omnium\Translate\Glossary\GlossaryModule(),
			new \Omnium\Translate\Queue\QueueModule(),
			new \Omnium\Translate\Router\RouterModule(),
			new \Omnium\Translate\Render\RenderModule(),
			new \Omnium\Translate\Seo\SeoModule(),
			new \Omnium\Translate\Woo\WooModule(),
			new \Omnium\Translate\Switcher\SwitcherModule(),
			new \Omnium\Translate\Admin\AdminModule(),
		);
	}

	/**
	 * Register and boot all modules. Hooked on plugins_loaded.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		// WordPress 6.7+ expects textdomains to load on `init` or later; loading
		// them here (on `plugins_loaded`) triggers a `_load_textdomain_just_in_time`
		// notice for every translatable string used before `init` fires.
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );

		Migrations::maybe_migrate();

		// "Replace existing" updates skip the activation routine when the
		// previous version bailed during bootstrap: restore the admin
		// capability once, unless it was deliberately removed.
		Capabilities::ensure_granted();

		$modules = $this->modules();
		foreach ( $modules as $module ) {
			$module->register( $this->container );
		}
		foreach ( $modules as $module ) {
			$module->boot( $this->container );
		}
	}
}
