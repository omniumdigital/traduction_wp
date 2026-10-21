<?php
/**
 * Storage module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Repositories for segments, translations, pages, slugs and log (CLAUDE.md section 4).
 * TODO(jalon 2): bind SegmentRepositoryInterface and TranslationRepositoryInterface implementations.
 */
final class StoreModule implements ModuleInterface {

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
