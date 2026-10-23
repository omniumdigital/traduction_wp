<?php
/**
 * WooCommerce module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Woo;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Queue\TranslationQueueInterface;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Store\SegmentRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Cart fragments, emails in the order language, attributes; loaded only when
 * WooCommerce is active (CLAUDE.md section 7). TODO(jalon 4).
 */
final class WooModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}
		$container->set( OrderLanguage::class, static fn( Container $c ) => new OrderLanguage( $c->get( CurrentLanguage::class ) ) );
		$container->set(
			FragmentTranslator::class,
			static fn( Container $c ) => new FragmentTranslator(
				$c->get( CurrentLanguage::class ),
				$c->get( \Omnium\Translate\Core\Settings::class ),
				$c->get( Translator::class )
			)
		);
		$container->set( CatalogTranslator::class, static fn( Container $c ) => new CatalogTranslator( $c->get( Translator::class ) ) );
		$container->set(
			EmailTemplateTranslator::class,
			static fn( Container $c ) => new EmailTemplateTranslator(
				$c->get( CurrentLanguage::class ),
				$c->get( \Omnium\Translate\Core\Settings::class ),
				$c->get( SegmentExtractorInterface::class ),
				$c->get( SegmentRepositoryInterface::class ),
				$c->get( TranslationQueueInterface::class ),
				$c->get( Translator::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		if ( ! $container->has( OrderLanguage::class ) ) {
			return;
		}
		$container->get( OrderLanguage::class )->register_hooks();
		$container->get( FragmentTranslator::class )->register_hooks();
		$container->get( CatalogTranslator::class )->register_hooks();
		$container->get( EmailTemplateTranslator::class )->register_hooks();
	}
}
