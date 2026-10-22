<?php
/**
 * Render module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Render;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Output buffering of the final HTML, segment extraction, translation injection
 * as text nodes, internal link rewriting (CLAUDE.md section 4).
 */
final class RenderModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( SegmentExtractorInterface::class, static fn( Container $c ) => new HtmlSegmentExtractor( $c->get( PiiFilter::class ) ) );
		$container->set(
			Translator::class,
			static fn( Container $c ) => new Translator(
				$c->get( CurrentLanguage::class ),
				$c->get( SegmentExtractorInterface::class ),
				$c->get( TranslationRepositoryInterface::class ),
				$c->get( UrlTranslator::class )
			)
		);
		$container->set(
			OutputBuffer::class,
			static fn( Container $c ) => new OutputBuffer(
				$c->get( CurrentLanguage::class ),
				$c->get( RequestGuard::class ),
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
		$output_buffer = $container->get( OutputBuffer::class );
		add_action( 'template_redirect', array( $output_buffer, 'start' ), 0 );
	}
}
