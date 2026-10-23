<?php
/**
 * WP-CLI commands for provider API keys.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Secure API key management through STDIN or a hidden prompt.
 */
final class ApiKeyCli {

	/**
	 * Key reader, injectable so unit tests never handle a real terminal.
	 *
	 * @var \Closure(): string
	 */
	private \Closure $read_key;

	/**
	 * Constructor.
	 *
	 * @param ApiKeyStore               $keys      Encrypted key store.
	 * @param ProviderRegistry          $providers Provider registry.
	 * @param (\Closure(): string)|null $read_key Key reader override for tests.
	 */
	public function __construct(
		private ApiKeyStore $keys,
		private ProviderRegistry $providers,
		?\Closure $read_key = null
	) {
		$this->read_key = $read_key ?? static function (): string {
			if ( defined( 'STDIN' ) && ! stream_isatty( STDIN ) ) {
				$value = stream_get_contents( STDIN );
				return false === $value ? '' : $value;
			}

			return (string) \WP_CLI::prompt(
				__( 'API key', 'omnium-translate' ),
				array( 'hide_input' => true )
			);
		};
	}

	/**
	 * Register the nested command.
	 */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		\WP_CLI::add_command( 'omtr key', $this );
	}

	/**
	 * Store a provider key read from STDIN or from a hidden prompt.
	 *
	 * ## OPTIONS
	 *
	 * <provider>
	 * : Registered provider id.
	 *
	 * ## EXAMPLES
	 *
	 *     printf '%s' "$OMTR_API_KEY" | wp omtr key set claude --user=admin
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function set( array $args, array $assoc_args ): void {
		if ( ! $this->guard() ) {
			return;
		}
		unset( $assoc_args );
		$provider = $this->provider_from_args( $args );
		if ( null === $provider ) {
			return;
		}

		$key = trim( ( $this->read_key )() );
		if ( '' === $key ) {
			\WP_CLI::error( __( 'The API key cannot be empty.', 'omnium-translate' ) );
			return;
		}

		$this->keys->set( $provider->id(), $key );
		\WP_CLI::success( __( 'API key stored securely.', 'omnium-translate' ) );
	}

	/**
	 * Display only masked keys for registered providers.
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function status( array $args, array $assoc_args ): void {
		if ( ! $this->guard() ) {
			return;
		}
		unset( $assoc_args );
		if ( array() !== $args ) {
			\WP_CLI::error( __( 'The status command does not accept a provider argument.', 'omnium-translate' ) );
			return;
		}

		$rows = array();
		foreach ( $this->providers->all() as $provider ) {
			$rows[] = array(
				'provider' => $provider->id(),
				'key'      => $this->keys->masked( $provider->id() ),
			);
		}

		\WP_CLI\Utils\format_items( 'table', $rows, array( 'provider', 'key' ) );
	}

	/**
	 * Delete a provider key.
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function delete( array $args, array $assoc_args ): void {
		if ( ! $this->guard() ) {
			return;
		}
		unset( $assoc_args );
		$provider = $this->provider_from_args( $args );
		if ( null === $provider ) {
			return;
		}

		$this->keys->delete( $provider->id() );
		\WP_CLI::success( __( 'API key deleted.', 'omnium-translate' ) );
	}

	/**
	 * Test a provider key and display its safe generic result.
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function test( array $args, array $assoc_args ): void {
		if ( ! $this->guard() ) {
			return;
		}
		unset( $assoc_args );
		$provider = $this->provider_from_args( $args );
		if ( null === $provider ) {
			return;
		}

		$result = $provider->test_key();
		if ( $result->ok ) {
			\WP_CLI::success( '' !== $result->message ? $result->message : __( 'API key is valid.', 'omnium-translate' ) );
			return;
		}

		\WP_CLI::error( '' !== $result->message ? $result->message : __( 'API key test failed.', 'omnium-translate' ) );
	}

	/**
	 * Resolve and validate the only provider argument.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	private function provider_from_args( array $args ): ?ProviderInterface {
		if ( 1 !== count( $args ) ) {
			\WP_CLI::error( __( 'Provide exactly one registered provider id.', 'omnium-translate' ) );
			return null;
		}

		$provider = $this->providers->get( $args[0] );
		if ( null === $provider ) {
			\WP_CLI::error( __( 'Unknown provider.', 'omnium-translate' ) );
			return null;
		}

		return $provider;
	}

	/**
	 * Require the plugin management capability.
	 */
	private function guard(): bool {
		if ( Capabilities::current_user_can_manage() ) {
			return true;
		}

		\WP_CLI::error( __( 'You need the omtr_manage capability (try --user=admin).', 'omnium-translate' ) );
		return false;
	}
}
