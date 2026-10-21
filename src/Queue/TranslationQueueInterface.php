<?php
/**
 * Translation queue contract.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Schedules work in Action Scheduler (group "omtr"). Methods only enqueue:
 * they never call a provider synchronously.
 */
interface TranslationQueueInterface {

	/**
	 * Schedule a scan + translation of one public URL of the site, for every target language.
	 *
	 * @param string $url     URL under home_url(), without query string.
	 * @param int    $post_id Related post id, 0 if none.
	 * @return bool False if refused (foreign URL, cap reached, lock held).
	 */
	public function enqueue_url( string $url, int $post_id = 0 ): bool;

	/**
	 * Schedule translation of known segments into a language.
	 *
	 * @param list<int> $segment_ids Segment ids.
	 * @param string    $target_lang Target language.
	 */
	public function enqueue_segments( array $segment_ids, string $target_lang ): void;
}
