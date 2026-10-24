<?php
/**
 * Language switcher module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode, template-function and WordPress-menu switcher (CLAUDE.md section 8).
 */
final class SwitcherModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( LanguagePreference::class, static fn( Container $c ) => new LanguagePreference( $c->get( Settings::class ), $c->get( CurrentLanguage::class ), $c->get( UrlTranslator::class ) ) );
		$container->set( SwitcherAssets::class, static fn() => new SwitcherAssets() );
		$container->set(
			Switcher::class,
			static fn( Container $c ) => new Switcher(
				$c->get( Settings::class ),
				$c->get( CurrentLanguage::class ),
				$c->get( UrlTranslator::class ),
				$c->get( SwitcherAssets::class )
			)
		);
		$container->set( MenuIntegration::class, static fn( Container $c ) => new MenuIntegration( $c->get( Switcher::class ) ) );
		$container->set( GutenbergBlock::class, static fn( Container $c ) => new GutenbergBlock( $c->get( Switcher::class ) ) );
		$container->set( ElementorIntegration::class, static fn( Container $c ) => new ElementorIntegration( $c->get( Switcher::class ) ) );
		$container->set( OxygenIntegration::class, static fn() => new OxygenIntegration() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		add_action( 'template_redirect', array( $container->get( LanguagePreference::class ), 'redirect' ), 0 );
		add_shortcode( 'omtr_switcher', array( $container->get( Switcher::class ), 'shortcode' ) );
		add_action( 'wp_footer', array( $container->get( SwitcherAssets::class ), 'print_late_styles' ), 1 );
		add_action( 'admin_head-nav-menus.php', array( $container->get( MenuIntegration::class ), 'register_meta_box' ) );
		add_filter( 'walker_nav_menu_start_el', array( $container->get( MenuIntegration::class ), 'filter_item_output' ), 10, 4 );
		add_action( 'init', array( $container->get( GutenbergBlock::class ), 'register' ) );
		add_action( 'elementor/widgets/register', array( $container->get( ElementorIntegration::class ), 'register_widget' ) );
		$oxygen = $container->get( OxygenIntegration::class );
		$oxygen->register_elements();
		add_action( 'breakdance_loaded', array( $oxygen, 'register_elements' ), 9 );
	}
}
