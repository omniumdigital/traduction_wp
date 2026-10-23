<?php
/**
 * Encrypted storage of provider API keys.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Security;

use Omnium\Translate\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * The decrypted key never leaves the server: only get() returns it, for
 * provider HTTP calls. Anything shown to a browser must use masked().
 */
final class ApiKeyStore {

	/**
	 * Constructor.
	 *
	 * @param Crypto $crypto Crypto service.
	 */
	public function __construct( private Crypto $crypto ) {}

	/**
	 * Store a key for a provider.
	 *
	 * @param string $provider Provider id.
	 * @param string $key      Plaintext key (already sanitized).
	 */
	public function set( string $provider, string $key ): void {
		$keys              = $this->raw();
		$keys[ $provider ] = $this->crypto->encrypt( $key );
		update_option( Options::API_KEYS, $keys, false );
	}

	/**
	 * Decrypted key, for server-side provider calls only.
	 *
	 * @param string $provider Provider id.
	 */
	public function get( string $provider ): ?string {
		$keys = $this->raw();
		return isset( $keys[ $provider ] ) ? $this->crypto->decrypt( $keys[ $provider ] ) : null;
	}

	/**
	 * Masked key for display, or empty string.
	 *
	 * @param string $provider Provider id.
	 */
	public function masked( string $provider ): string {
		$key = $this->get( $provider );
		return null === $key ? '' : Crypto::mask( $key );
	}

	/**
	 * Remove a provider key.
	 *
	 * @param string $provider Provider id.
	 */
	public function delete( string $provider ): void {
		$keys = $this->raw();
		unset( $keys[ $provider ] );
		update_option( Options::API_KEYS, $keys, false );
	}

	/**
	 * Encrypted payloads keyed by provider.
	 *
	 * @return array<string, string>
	 */
	private function raw(): array {
		$keys = get_option( Options::API_KEYS, array() );
		return is_array( $keys ) ? array_filter( $keys, 'is_string' ) : array();
	}
}
