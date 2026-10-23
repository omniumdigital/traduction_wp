<?php
/**
 * Provider rate limits.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Batching and throttling hints for the queue.
 */
final class RateLimits {

	/**
	 * Constructor.
	 *
	 * @param int $requests_per_minute Max requests per minute.
	 * @param int $max_batch_size      Max segments per batch (20 to 50).
	 * @param int $max_batch_chars     Max characters per batch.
	 */
	public function __construct(
		public readonly int $requests_per_minute = 50,
		public readonly int $max_batch_size = 30,
		public readonly int $max_batch_chars = 12000
	) {}
}
