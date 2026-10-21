<?php
/**
 * AI providers module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Registers providers in the ProviderRegistry (CLAUDE.md section 5).
 * TODO(jalon 2): Claude provider. TODO(jalon 3): OpenAI, Mistral, Gemini, DeepSeek.
 */
final class ProvidersModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( ProviderRegistry::class, static fn() => new ProviderRegistry() );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
	}
}
