<?php
/**
 * Translations repository contract.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Store;

defined( 'ABSPATH' ) || exit;

/**
 * Access to {prefix}omtr_translations. Statuses: auto, edited, locked, failed.
 * "edited" and "locked" rows are never overwritten by an automatic translation.
 */
interface TranslationRepositoryInterface {

	public const STATUS_AUTO   = 'auto';
	public const STATUS_EDITED = 'edited';
	public const STATUS_LOCKED = 'locked';
	public const STATUS_FAILED = 'failed';

	/**
	 * Fetch translations for many segments in ONE query.
	 *
	 * @param list<string> $hashes      Segment hashes.
	 * @param string       $target_lang Target language.
	 * @return array<string, string> Hash => sanitized translated text (failed rows excluded).
	 */
	public function find_many( array $hashes, string $target_lang ): array;

	/**
	 * Save automatic translations. Skips rows with status edited or locked.
	 *
	 * @param array<int, string> $texts       Segment id => sanitized text.
	 * @param string             $target_lang Target language.
	 * @param string             $provider    Provider id.
	 * @param string             $model       Model id.
	 */
	public function save_auto( array $texts, string $target_lang, string $provider, string $model ): void;

	/**
	 * Save a manual edit (status edited). Text must be sanitized by the caller.
	 *
	 * @param int    $segment_id  Segment id.
	 * @param string $target_lang Target language.
	 * @param string $text        Sanitized text.
	 */
	public function save_edit( int $segment_id, string $target_lang, string $text ): void;

	/**
	 * Mark segments as failed.
	 *
	 * @param list<int> $segment_ids Segment ids.
	 * @param string    $target_lang Target language.
	 */
	public function mark_failed( array $segment_ids, string $target_lang ): void;
}
