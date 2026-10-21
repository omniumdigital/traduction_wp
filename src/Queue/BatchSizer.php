<?php
/**
 * Batch chunking driven by provider rate limits.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Providers\RateLimits;

defined( 'ABSPATH' ) || exit;

/**
 * CLAUDE.md section 5: batches of 20 to 50 segments. The provider hint is
 * clamped to that range, and the character budget is respected as well.
 */
final class BatchSizer {

	/**
	 * Hard floor for a batch size.
	 */
	private const MIN_BATCH = 20;

	/**
	 * Hard ceiling for a batch size.
	 */
	private const MAX_BATCH = 50;

	/**
	 * Split segments into batches.
	 *
	 * @param array<string, string> $texts  Segment hash => source text.
	 * @param RateLimits            $limits Provider limits.
	 * @return list<array<string, string>> Batches of hash => source text.
	 */
	public static function chunk( array $texts, RateLimits $limits ): array {
		$max_size  = max( self::MIN_BATCH, min( self::MAX_BATCH, max( 1, $limits->max_batch_size ) ) );
		$max_chars = max( 1, $limits->max_batch_chars );

		$batches = array();
		$current = array();
		$chars   = 0;

		foreach ( $texts as $hash => $text ) {
			$length = strlen( $text );
			if ( array() !== $current && ( count( $current ) >= $max_size || ( $chars + $length ) > $max_chars ) ) {
				$batches[] = $current;
				$current   = array();
				$chars     = 0;
			}
			$current[ $hash ] = $text;
			$chars           += $length;
		}

		if ( array() !== $current ) {
			$batches[] = $current;
		}

		return $batches;
	}
}
