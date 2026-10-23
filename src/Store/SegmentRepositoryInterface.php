<?php
/**
 * Segments repository contract.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Access to {prefix}omtr_segments. All queries use $wpdb->prepare().
 */
interface SegmentRepositoryInterface {

	/**
	 * Insert missing segments, return ids for every hash.
	 *
	 * @param array<string, string> $segments    Hash => normalized source text.
	 * @param string                $source_lang Source language.
	 * @return array<string, int> Hash => segment id.
	 */
	public function upsert_many( array $segments, string $source_lang ): array;

	/**
	 * Source texts by hash.
	 *
	 * @param list<string> $hashes Hashes.
	 * @return array<string, string> Hash => source text.
	 */
	public function texts_by_hash( array $hashes ): array;

	/**
	 * Hashes of segments by id, in ONE query. Unknown ids are omitted.
	 *
	 * @param list<int> $ids Segment ids.
	 * @return array<int, string> Segment id => hash.
	 */
	public function hashes_by_ids( array $ids ): array;
}
