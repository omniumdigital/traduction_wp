<?php
/**
 * Glossary module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Glossary;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Glossary rules, tone and site context, CSV import/export (CLAUDE.md section 6).
 * TODO(jalon 3).
 */
final class GlossaryModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( GlossaryRepository::class, static fn() => new GlossaryRepository( $GLOBALS['wpdb'] ) );
		$container->set(
			GlossaryService::class,
			static fn( Container $c ): GlossaryService => new GlossaryService(
				$c->get( GlossaryRepository::class ),
				$c->get( Settings::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
	}
}
