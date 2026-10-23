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

	public function test_mask_hides_secret(): void {
		$this->assertSame( 'sk-…a1B2', Crypto::mask( 'sk-ant-api03-verylongkeya1B2' ) );
		$this->assertSame( '••••', Crypto::mask( 'abc' ) );
	}
}
