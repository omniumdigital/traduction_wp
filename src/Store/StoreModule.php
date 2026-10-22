<?php
/**
 * Storage module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\LoggerInterface;
use Omnium\Translate\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Repositories for segments, translations, pages, slugs and log (CLAUDE.md section 4).
 * TODO(jalon 4): slugs repository.
 */
final class StoreModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( SegmentRepositoryInterface::class, static fn() => new SegmentRepository( $GLOBALS['wpdb'] ) );
		$container->set( TranslationRepositoryInterface::class, static fn() => new TranslationRepository( $GLOBALS['wpdb'] ) );
		$container->set( PageRepository::class, static fn() => new PageRepository( $GLOBALS['wpdb'] ) );
		$container->set( LoggerInterface::class, static fn() => new DbLogger( $GLOBALS['wpdb'] ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		$logger = $container->get( LoggerInterface::class );
		if ( $logger instanceof DbLogger ) {
			add_action( 'init', array( $logger, 'schedule_purge' ) );
			add_action( DbLogger::PURGE_HOOK, array( $logger, 'purge' ) );
		}
	}
}
