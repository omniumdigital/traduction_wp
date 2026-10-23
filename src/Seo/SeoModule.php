<?php
/**
 * SEO module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Router\UrlTranslator;

defined( 'ABSPATH' ) || exit;

/**
 * Translated slugs, hreflang, canonical, sitemaps, JSON-LD, Yoast / Rank Math
 * (CLAUDE.md section 7). TODO(jalon 4).
 */
final class SeoModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set(
			HeadTags::class,
			static fn( Container $c ) => new HeadTags(
				$c->get( Settings::class ),
				$c->get( CurrentLanguage::class ),
				$c->get( RequestGuard::class ),
				$c->get( UrlTranslator::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		$head_tags = $container->get( HeadTags::class );
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( $head_tags, 'render' ), 1 );
		add_filter( 'wpseo_canonical', array( $head_tags, 'filter_yoast_canonical' ), 99 );
		add_filter( 'rank_math/frontend/canonical', array( $head_tags, 'filter_rank_math_canonical' ), 99 );
	}
}
