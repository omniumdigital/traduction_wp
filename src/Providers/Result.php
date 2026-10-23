<?php
/**
 * Outcome of a provider operation.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Immutable result. The message is safe to display: never a raw provider response or key.
 */
final class Result {

	/**
	 * Constructor.
	 *
	 * @param bool   $ok        Success flag.
	 * @param string $message   Safe, translated message.
	 * @param int    $http_code HTTP status code, 0 if none.
	 */
	public function __construct(
		public readonly bool $ok,
		public readonly string $message = '',
		public readonly int $http_code = 0
	) {}

	/**
	 * Success.
	 *
	 * @param string $message Message.
	 */
	public static function ok( string $message = '' ): self {
		return new self( true, $message );
	}

	/**
	 * Failure.
	 *
	 * @param string $message   Message.
	 * @param int    $http_code HTTP code.
	 */
	public static function error( string $message, int $http_code = 0 ): self {
		return new self( false, $message, $http_code );
	}
}
