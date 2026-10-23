<?php
/**
 * Registry of available providers.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Holds provider instances keyed by id.
 */
final class ProviderRegistry {

	/**
	 * Providers.
	 *
	 * @var array<string, ProviderInterface>
	 */
	private array $providers = array();

	/**
	 * Add a provider.
	 *
	 * @param ProviderInterface $provider Provider.
	 */
	public function add( ProviderInterface $provider ): void {
		$this->providers[ $provider->id() ] = $provider;
	}

	/**
	 * Get a provider by id.
	 *
	 * @param string $id Provider id.
	 */
	public function get( string $id ): ?ProviderInterface {
		return $this->providers[ $id ] ?? null;
	}

	/**
	 * All providers.
	 *
	 * @return array<string, ProviderInterface>
	 */
	public function all(): array {
		return $this->providers;
	}
}
