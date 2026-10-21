<?php
/**
 * Symmetric encryption for API keys.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Security;

defined( 'ABSPATH' ) || exit;

/**
 * Sodium secretbox with a random nonce per encryption.
 *
 * Key: OMTR_ENCRYPTION_KEY from wp-config.php if defined, otherwise derived from
 * AUTH_KEY + SECURE_AUTH_SALT. Changing those constants makes stored keys unreadable.
 */
final class Crypto {

	private const PREFIX = 'v1:';
	private const INFO   = 'omnium-translate-api-key';

	/**
	 * Encrypt a plaintext.
	 *
	 * @param string $plaintext Secret.
	 * @return string Prefixed base64 payload (nonce + ciphertext).
	 */
	public function encrypt( string $plaintext ): string {
		$key    = $this->key();
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plaintext, $nonce, $key );
		sodium_memzero( $key );

		return self::PREFIX . base64_encode( $nonce . $cipher ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- binary storage encoding, not obfuscation.
	}

	/**
	 * Decrypt a payload produced by encrypt().
	 *
	 * @param string $payload Stored payload.
	 * @return string|null Plaintext, or null when the payload is invalid or the key changed.
	 */
	public function decrypt( string $payload ): ?string {
		if ( ! str_starts_with( $payload, self::PREFIX ) ) {
			return null;
		}
		$raw = base64_decode( substr( $payload, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- binary storage encoding, not obfuscation.
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return null;
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$key    = $this->key();
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, $key );
		sodium_memzero( $key );

		return false === $plain ? null : $plain;
	}

	/**
	 * Masked form for display, e.g. "sk-…a1B2".
	 *
	 * @param string $secret Plaintext secret.
	 */
	public static function mask( string $secret ): string {
		$len = strlen( $secret );
		if ( $len <= 8 ) {
			return str_repeat( '•', max( 4, $len ) );
		}
		return substr( $secret, 0, 3 ) . '…' . substr( $secret, -4 );
	}

	/**
	 * Whether the encryption key comes from a dedicated constant.
	 */
	public static function has_dedicated_key(): bool {
		return defined( 'OMTR_ENCRYPTION_KEY' ) && '' !== (string) OMTR_ENCRYPTION_KEY;
	}

	/**
	 * Derive the 32-byte encryption key.
	 *
	 * @throws \RuntimeException When no key material is available.
	 */
	private function key(): string {
		if ( self::has_dedicated_key() ) {
			$material = (string) OMTR_ENCRYPTION_KEY;
		} else {
			$auth     = defined( 'AUTH_KEY' ) ? (string) AUTH_KEY : '';
			$salt     = defined( 'SECURE_AUTH_SALT' ) ? (string) SECURE_AUTH_SALT : '';
			$material = $auth . $salt;
		}
		if ( '' === $material ) {
			throw new \RuntimeException( 'No encryption key material available.' );
		}
		return hash_hkdf( 'sha256', $material, SODIUM_CRYPTO_SECRETBOX_KEYBYTES, self::INFO );
	}
}
