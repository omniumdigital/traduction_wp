<?php
/**
 * Translation repository test double.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit\Doubles;

use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * In-memory translations: hash => lang => [text, status].
 *
 * Mirrors the contract: edited and locked rows are never overwritten by
 * save_auto, and find_many excludes failed rows.
 */
final class FakeTranslationRepository implements TranslationRepositoryInterface {

	/**
	 * Stored rows.
	 *
	 * @var array<string, array<string, array{text: string, status: string}>>
	 */
	public array $rows = array();

	/**
	 * Calls to save_auto.
	 *
	 * @var list<array{texts: array<int, string>, lang: string, provider: string, model: string}>
	 */
	public array $autos = array();

	/**
	 * Calls to mark_failed.
	 *
	 * @var list<array{ids: list<int>, lang: string}>
	 */
	public array $failures = array();

	/**
	 * Seed a translation.
	 *
	 * @param string $hash   Hash.
	 * @param string $lang   Language.
	 * @param string $text   Text.
	 * @param string $status Status.
	 */
	public function seed( string $hash, string $lang, string $text, string $status = self::STATUS_AUTO ): void {
		$this->rows[ $hash ][ $lang ] = array(
			'text'   => $text,
			'status' => $status,
		);
	}

	/**
	 * Saved texts by hash (helper for assertions).
	 *
	 * @param string $lang Language.
	 * @return array<string, string>
	 */
	public function texts_by_lang( string $lang ): array {
		$return = array();
		foreach ( $this->rows as $hash => $langs ) {
			if ( isset( $langs[ $lang ] ) && self::STATUS_FAILED !== $langs[ $lang ]['status'] ) {
				$return[ $hash ] = $langs[ $lang ]['text'];
			}
		}
		return $return;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<string> $hashes      Hashes.
	 * @param string       $target_lang Language.
	 * @return array<string, string>
	 */
	public function find_many( array $hashes, string $target_lang ): array {
		$return = array();
		foreach ( $hashes as $hash ) {
			if ( isset( $this->rows[ $hash ][ $target_lang ] ) && self::STATUS_FAILED !== $this->rows[ $hash ][ $target_lang ]['status'] ) {
				$return[ $hash ] = $this->rows[ $hash ][ $target_lang ]['text'];
			}
		}
		return $return;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<int, string> $texts       Segment id => text.
	 * @param string             $target_lang Language.
	 * @param string             $provider    Provider id.
	 * @param string             $model       Model id.
	 */
	public function save_auto( array $texts, string $target_lang, string $provider, string $model ): void {
		$this->autos[] = array(
			'texts'    => $texts,
			'lang'     => $target_lang,
			'provider' => $provider,
			'model'    => $model,
		);
		foreach ( $texts as $id => $text ) {
			$hash = $this->hash_by_id( $id );
			if ( null === $hash ) {
				continue;
			}
			$current = $this->rows[ $hash ][ $target_lang ] ?? null;
			if ( null !== $current && in_array( $current['status'], array( self::STATUS_EDITED, self::STATUS_LOCKED ), true ) ) {
				continue;
			}
			$this->rows[ $hash ][ $target_lang ] = array(
				'text'   => $text,
				'status' => self::STATUS_AUTO,
			);
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int    $segment_id  Segment id.
	 * @param string $target_lang Language.
	 * @param string $text        Text.
	 */
	public function save_edit( int $segment_id, string $target_lang, string $text ): void {
		$hash = $this->hash_by_id( $segment_id );
		if ( null !== $hash ) {
			$this->rows[ $hash ][ $target_lang ] = array(
				'text'   => $text,
				'status' => self::STATUS_EDITED,
			);
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param list<int> $segment_ids Segment ids.
	 * @param string    $target_lang Language.
	 */
	public function mark_failed( array $segment_ids, string $target_lang ): void {
		$this->failures[] = array(
			'ids'  => $segment_ids,
			'lang' => $target_lang,
		);
		foreach ( $segment_ids as $id ) {
			$hash = $this->hash_by_id( $id );
			if ( null !== $hash ) {
				$this->rows[ $hash ][ $target_lang ] = array(
					'text'   => '',
					'status' => self::STATUS_FAILED,
				);
			}
		}
	}

	/**
	 * Reverse lookup id => hash (test helper, not part of the contract).
	 *
	 * @param int $id Segment id.
	 */
	private function hash_by_id( int $id ): ?string {
		foreach ( $this->id_hash as $known_id => $hash ) {
			if ( $known_id === $id ) {
				return $hash;
			}
		}
		return null;
	}

	/**
	 * id => hash map injected by tests alongside FakeSegmentRepository.
	 *
	 * @var array<int, string>
	 */
	public array $id_hash = array();
}
