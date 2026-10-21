<?php
/**
 * PHPUnit bootstrap: unit tests run without WordPress, using Brain Monkey.
 *
 * @package Omnium\Translate
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'AUTH_KEY', 'test-auth-key-not-a-secret' );
define( 'SECURE_AUTH_SALT', 'test-salt-not-a-secret' );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'DAY_IN_SECONDS', 86400 );

if ( ! class_exists( 'wpdb' ) ) {
	/** Minimal wpdb surface used by repository unit tests. */
	class wpdb {
		public string $prefix = 'wp_';

		public function prepare( string $query, mixed ...$args ): string {
			return $query . '|' . implode( '|', array_map( 'strval', $args ) );
		}

		public function query( string $query ): int|bool {
			return 1;
		}

		public function get_results( string $query, string $output = 'OBJECT' ): array {
			return array();
		}

		public function get_var( string $query ): string|null {
			return null;
		}
	}
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
