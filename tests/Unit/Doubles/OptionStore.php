<?php
/**
 * In-memory option store for Brain Monkey function stubs.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit\Doubles;

use Brain\Monkey\Functions;

defined( 'ABSPATH' ) || exit;

/**
 * Stubs get_option / update_option / add_option / delete_option with an
 * in-memory store (add_option stays atomic like the real one).
 */
final class OptionStore {

	/**
	 * Stored values.
	 *
	 * @var array<string, mixed>
	 */
	public array $data = array();

	/**
	 * Install the function stubs.
	 */
	public function install(): void {
		$store = $this;
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = false ) use ( $store ) {
				return array_key_exists( $name, $store->data ) ? $store->data[ $name ] : $default;
			}
		);
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value ) use ( $store ): bool {
				$store->data[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'add_option' )->alias(
			static function ( $name, $value = '' ) use ( $store ): bool {
				if ( array_key_exists( $name, $store->data ) ) {
					return false;
				}
				$store->data[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $name ) use ( $store ): bool {
				unset( $store->data[ $name ] );
				return true;
			}
		);
	}
}
