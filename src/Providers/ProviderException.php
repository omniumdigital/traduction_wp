<?php
/**
 * Provider error.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Message must be generic (no raw response, no key). $retryable drives the queue backoff (429/5xx).
 */
final class ProviderException extends \RuntimeException {

	/**
	 * Constructor.
	 *
	 * @param string $message   Generic message.
	 * @param int    $http_code HTTP status code, 0 if none.
	 * @param bool   $retryable Whether the queue should retry with backoff.
	 * @param string $provider_code Sanitized provider error code (see ErrorClassifier::provider_code()).
	 */
	public function __construct( string $message, public readonly int $http_code = 0, public readonly bool $retryable = false, public readonly string $provider_code = '' ) {
		parent::__construct( $message, $http_code );
	}
}
