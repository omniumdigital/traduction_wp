<?php
/**
 * AI providers module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\Claude\ClaudeProvider;
use Omnium\Translate\Providers\Gemini\GeminiProvider;
use Omnium\Translate\Providers\Mistral\MistralProvider;
use Omnium\Translate\Providers\OpenAI\OpenAIProvider;
use Omnium\Translate\Security\ApiKeyStore;

defined( 'ABSPATH' ) || exit;

/**
 * Registers providers in the ProviderRegistry (CLAUDE.md section 5).
 * Milestone 3: Claude, OpenAI, Mistral (EU endpoint), Gemini. DeepSeek out of scope.
 */
final class ProvidersModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
		$container->set( PromptBuilder::class, static fn() => new PromptBuilder() );
		$container->set( TranslationQualityValidator::class, static fn() => new TranslationQualityValidator() );
		$container->set( ResponseValidator::class, static fn( Container $c ) => new ResponseValidator( $c->get( TranslationQualityValidator::class ) ) );
		$container->set( Pricing::class, static fn() => new Pricing() );

		$container->set(
			ClaudeProvider::class,
			static fn( Container $c ): ClaudeProvider => new ClaudeProvider(
				$c->get( ApiKeyStore::class ),
				$c->get( Settings::class ),
				$c->get( PromptBuilder::class ),
				$c->get( ResponseValidator::class )
			)
		);

		$container->set(
			OpenAIProvider::class,
			static fn( Container $c ): OpenAIProvider => new OpenAIProvider(
				$c->get( ApiKeyStore::class ),
				$c->get( Settings::class ),
				$c->get( PromptBuilder::class ),
				$c->get( ResponseValidator::class )
			)
		);

		$container->set(
			MistralProvider::class,
			static fn( Container $c ): MistralProvider => new MistralProvider(
				$c->get( ApiKeyStore::class ),
				$c->get( Settings::class ),
				$c->get( PromptBuilder::class ),
				$c->get( ResponseValidator::class )
			)
		);

		$container->set(
			GeminiProvider::class,
			static fn( Container $c ): GeminiProvider => new GeminiProvider(
				$c->get( ApiKeyStore::class ),
				$c->get( Settings::class ),
				$c->get( PromptBuilder::class ),
				$c->get( ResponseValidator::class )
			)
		);

		$container->set(
			ProviderRegistry::class,
			static function ( Container $c ): ProviderRegistry {
				$registry = new ProviderRegistry();
				$registry->add( $c->get( ClaudeProvider::class ) );
				$registry->add( $c->get( OpenAIProvider::class ) );
				$registry->add( $c->get( MistralProvider::class ) );
				$registry->add( $c->get( GeminiProvider::class ) );
				return $registry;
			}
		);

		$container->set(
			ApiKeyCli::class,
			static fn( Container $c ): ApiKeyCli => new ApiKeyCli(
				$c->get( ApiKeyStore::class ),
				$c->get( ProviderRegistry::class )
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$container->get( ApiKeyCli::class )->register();
		}
	}
}
