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
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Store\SlugRepository;
use Omnium\Translate\Store\TranslationRepositoryInterface;

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
				$c->get( RequestGuard::class ),
				$c->get( SlugRepository::class ),
				$c->get( UrlTranslator::class )
			)
		);
		$container->set( UrlTranslator::class, static fn( Container $c ) => new UrlTranslator( $c->get( Settings::class ) ) );
		$container->set(
			SlugGenerator::class,
			static fn( Container $c ) => new SlugGenerator(
				$c->get( SlugRepository::class ),
				$c->get( TranslationRepositoryInterface::class ),
				$c->get( Settings::class )
			)
		);
		$container->set(
			SlugCli::class,
			static fn( Container $c ) => new SlugCli( $c->get( SlugGenerator::class ), $c->get( PublicContent::class ), $c->get( Settings::class ) )
		);
		$container->set( SlugRestController::class, static fn( Container $c ) => new SlugRestController( $c->get( SlugGenerator::class ) ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		$router = $container->get( LanguageRouter::class );
		add_filter( 'do_parse_request', array( $router, 'strip_language_prefix' ), 0 );
		add_action( 'template_redirect', array( $router, 'redirect_old_slug' ), 0 );
		add_action( 'rest_api_init', array( $container->get( SlugRestController::class ), 'register_routes' ) );
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$container->get( SlugCli::class )->register();
		}
	}
}
