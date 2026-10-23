<?php
/**
 * SEO module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Seo;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;

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
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
	}
}
