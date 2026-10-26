<?php
/**
 * Crypto tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Security\Crypto;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class CryptoTest extends TestCase {

	public function test_round_trip(): void {
		$crypto = new Crypto();
		$this->assertSame( 'sk-test-123456789', $crypto->decrypt( $crypto->encrypt( 'sk-test-123456789' ) ) );
	}

	public function test_nonce_is_random(): void {
		$crypto = new Crypto();
		$this->assertNotSame( $crypto->encrypt( 'same' ), $crypto->encrypt( 'same' ) );
	}

	public function test_ciphertext_does_not_contain_plaintext(): void {
		$this->assertStringNotContainsString( 'sk-test', ( new Crypto() )->encrypt( 'sk-test-123456789' ) );
	}

	public function test_tampered_payload_is_rejected(): void {
		$crypto  = new Crypto();
		$payload = $crypto->encrypt( 'secret' );
		$this->assertNull( $crypto->decrypt( substr( $payload, 0, -2 ) . 'AA' ) );
		$this->assertNull( $crypto->decrypt( 'garbage' ) );
	}

	/**
	 * Hosts without the sodium extension run WordPress's sodium_compat polyfill:
	 * keys it encrypted (fixture below, test constants) must stay readable natively.
	 */
	public function test_decrypts_payload_written_by_sodium_compat(): void {
		$this->assertSame(
			'polyfill-fixture-secret',
			( new Crypto() )->decrypt( 'v1:LjZYmrBdiXn/2EFc5u34JZa1CsZ+hlCkeQ2I/QFSj1Fd10BVFzMGpJxo/b1lP54ZH86MpMVT7boXvAJKNpry' )
		);
	}

	public function test_mask_hides_secret(): void {
		$this->assertSame( 'sk-…a1B2', Crypto::mask( 'sk-ant-api03-verylongkeya1B2' ) );
		$this->assertSame( '••••', Crypto::mask( 'abc' ) );
	}
}
