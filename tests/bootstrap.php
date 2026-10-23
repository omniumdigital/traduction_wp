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
		public int $insert_id = 0;

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

		public function esc_like( string $text ): string {
			return addcslashes( $text, '_%\\' );
		}
	}
}

if ( ! defined( 'OMTR_VERSION' ) ) {
	define( 'OMTR_VERSION', '0.0.0-test' );
}

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * Minimal WP_Error double: tests only rely on instanceof checks through
 * an is_wp_error() stub.
 */
if ( ! class_exists( 'WP_Error' ) ) {
	final class WP_Error {

		/**
		 * Error messages.
		 *
		 * @var list<string>
		 */
		public array $errors = array();

		/**
		 * Constructor.
		 *
		 * @param string $message Error message.
		 */
		public function __construct( string $message = '' ) {
			if ( '' !== $message ) {
				$this->errors[] = $message;
			}
		}
	}
}

/**
 * Minimal WP_CLI double: static methods record calls instead of printing.
 */
if ( ! class_exists( 'WP_CLI' ) ) {
	final class WP_CLI {

		/**
		 * Recorded calls: method => list of arg lists.
		 *
		 * @var array<string, list<array<int, mixed>>>
		 */
		public static array $calls = array();

		/**
		 * Record a call.
		 *
		 * @param string $method Method.
		 * @param mixed  ...$args Arguments.
		 */
		private static function record( string $method, ...$args ): void {
			self::$calls[ $method ][] = $args;
		}

		/**
		 * Recorded add_command.
		 *
		 * @param string|mixed $name Name.
		 * @param mixed        $command Command.
		 * @param mixed        $args Args.
		 * @return bool
		 */
		public static function add_command( $name, $command, $args = array() ): bool {
			self::record( 'add_command', $name, $command, $args );
			return true;
		}

		/**
		 * Recorded error.
		 *
		 * @param string|array|mixed $message Message.
		 * @return void
		 */
		public static function error( $message = '' ): void {
			self::record( 'error', $message );
		}

		/**
		 * Recorded success.
		 *
		 * @param string|mixed $message Message.
		 * @return void
		 */
		public static function success( $message = '' ): void {
			self::record( 'success', $message );
		}

		/**
		 * Recorded line.
		 *
		 * @param string|mixed $message Message.
		 * @return void
		 */
		public static function line( $message = '' ): void {
			self::record( 'line', $message );
		}

		/**
		 * Recorded log.
		 *
		 * @param string|mixed $message Message.
		 * @return void
		 */
		public static function log( $message = '' ): void {
			self::record( 'log', $message );
		}

		/**
		 * Recorded format_items.
		 *
		 * @param string|mixed $format Format.
		 * @param mixed        $items Items.
		 * @param mixed        $fields Fields.
		 * @return void
		 */
		public static function format_items( $format, $items, $fields ): void {
			self::record( 'format_items', $format, $items, $fields );
		}

		/**
		 * Reset recorded calls between tests.
		 *
		 * @return void
		 */
		public static function reset(): void {
			self::$calls = array();
		}
	}
}
