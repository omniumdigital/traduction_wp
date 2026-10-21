<?php
/**
 * Logger contract.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Writes to {prefix}omtr_log, purged after 30 days. Messages must never contain
 * segment text, API keys, IPs, user ids or raw provider responses.
 */
interface LoggerInterface {

	/**
	 * Write a log line.
	 *
	 * @param string $level   One of: debug, info, warning, error.
	 * @param string $context Technical context (e.g. "queue", "provider:claude", job id).
	 * @param string $message Generic message.
	 */
	public function log( string $level, string $context, string $message ): void;
}
