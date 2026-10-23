<?php
/**
 * Router module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Language URL prefixes (/en/...), language detection, internal link targets
 * (CLAUDE.md sections 4 and 7).
 */
final class RouterModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( RequestGuard::class, static fn() => new RequestGuard() );
		$container->set(
			CurrentLanguage::class,
			static fn( Container $c ) => new CurrentLanguage( $c->get( Settings::class )->source_lang() )
		);
		$container->set(
			LanguageRouter::class,
			static fn( Container $c ) => new LanguageRouter(
				$c->get( CurrentLanguage::class ),
				$c->get( Settings::class ),
				$c->get( RequestGuard::class )
			)
		);
		$container->set( UrlTranslator::class, static fn( Container $c ) => new UrlTranslator( $c->get( Settings::class ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		$router = $container->get( LanguageRouter::class );
		add_filter( 'do_parse_request', array( $router, 'strip_language_prefix' ), 0 );
	}
}
