<?php
/**
 * Segment repository test double.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit\Doubles;

use Omnium\Translate\Store\SegmentRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * In-memory segments: hash => text, with auto-increment ids.
 */
final class FakeSegmentRepository implements SegmentRepositoryInterface {

	/**
	 * hash => source text.
	 *
	 * @var array<string, string>
	 */
	public array $texts = array();

	/**
	 * hash => id.
	 *
	 * @var array<string, int>
	 */
	public array $ids = array();

	/**
	 * Calls to upsert_many.
	 *
	 * @var list<array{segments: array<string, string>, source_lang: string}>
	 */
	public array $upserts = array();

	/**
	 * Next id.
	 */
	private int $next_id = 1;

	/**
	 * Seed a segment.
	 *
	 * @param string $hash Hash.
	 * @param string $text Text.
	 */
	public function seed( string $hash, string $text ): void {
		$this->texts[ $hash ] = $text;
		$this->ids[ $hash ]   = $this->next_id++;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, string> $segments    Hash => text.
	 * @param string                $source_lang Source language.
	 * @return array<string, int>
	 */
	public function upsert_many( array $segments, string $source_lang ): array {
		$this->upserts[] = array(
			'segments'    => $segments,
			'source_lang' => $source_lang,
		);
		foreach ( $segments as $hash => $text ) {
			if ( ! isset( $this->ids[ $hash ] ) ) {
				$this->seed( $hash, $text );
			}
		}
		return array_intersect_key( $this->ids, $segments );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<string> $hashes Hashes.
	 * @return array<string, string>
	 */
	public function texts_by_hash( array $hashes ): array {
		$return = array();
		foreach ( $hashes as $hash ) {
			if ( isset( $this->texts[ $hash ] ) ) {
				$return[ $hash ] = $this->texts[ $hash ];
			}
		}
		return $return;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<int> $ids Segment ids.
	 * @return array<int, string>
	 */
	public function hashes_by_ids( array $ids ): array {
		$return = array();
		foreach ( $ids as $id ) {
			foreach ( $this->ids as $hash => $segment_id ) {
				if ( $segment_id === $id ) {
					$return[ $id ] = $hash;
				}
			}
		}
		return $return;
	}
}
