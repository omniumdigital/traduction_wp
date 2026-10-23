<?php
/**
 * Constants defined at runtime by the bootstrap file or wp-config.php.
 *
 * @package Omnium\Translate
 */

define( 'OMTR_VERSION', '0.0.0' );
define( 'OMTR_FILE', __FILE__ );
define( 'OMTR_DIR', dirname( __DIR__ ) . '/' );
define( 'OMTR_URL', 'https://example.test/' );
define( 'OMTR_ENCRYPTION_KEY', '' );
define( 'OMTR_MIN_PHP', '8.1' );

/**
 * Minimal WP-CLI stub for static analysis (the real class only exists when
 * the WP-CLI context loads it; there is no composer stub package available).
 *
 * @phpstan-type CliCommand callable|class-string|object|string
 */
final class WP_CLI {

	/**
	 * Register a command.
	 *
	 * @param string              $name    Command name.
	 * @param callable|object|string $command Command implementation.
	 * @param array<string,mixed> $args    Command args.
	 * @return bool
	 */
	public static function add_command( $name, $command, $args = array() ) {
		unset( $name, $command, $args );
		return true;
	}

	/**
	 * Display an error and exit.
	 *
	 * @param string|array<string> $message Message.
	 * @return void
	 */
	public static function error( $message ) {
		unset( $message );
	}

	/**
	 * Display a success message.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function success( $message ) {
		unset( $message );
	}

	/**
	 * Display a line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function line( $message = '' ) {
		unset( $message );
	}

	/**
	 * Display a log line.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	public static function log( $message ) {
		unset( $message );
	}

	/**
	 * Ask for confirmation.
	 *
	 * @param string $message Confirmation message.
	 * @return void
	 */
	public static function confirm( $message ) {
		unset( $message );
	}

	/**
	 * Prompt for a value.
	 *
	 * @param string              $question Question.
	 * @param array<string,mixed> $args     Prompt arguments.
	 * @return string
	 */
	public static function prompt( $question, $args = array() ) {
		unset( $question, $args );
		return '';
	}

	/**
	 * Render items in a table or list.
	 *
	 * @param string              $format Format.
	 * @param array<int,array<string,mixed>> $items  Items.
	 * @param list<string>        $fields Fields.
	 * @return void
	 */
	public static function format_items( $format, $items, $fields ) {
		unset( $format, $items, $fields );
	}
}
