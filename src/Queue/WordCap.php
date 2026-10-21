<?php
/**
 * Monthly word cap applied before any paid enqueuing.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * CLAUDE.md sections 5 and 11: the optional monthly word cap (Settings
 * monthly_word_cap, 0 = unlimited) must be enforced server-side before
 * scheduling batches, to prevent cost attacks.
 *
 * Words are charged when batches are scheduled (pre-charged), so a cap can
 * never be overrun by retries: a retried batch is the same Action Scheduler
 * action rescheduled, not a new enqueuing.
 */
final class WordCap {

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( private Settings $settings ) {}

	/**
	 * Configured cap, 0 = unlimited.
	 */
	public function limit(): int {
		$cap = $this->settings->get( 'monthly_word_cap' );
		return is_numeric( $cap ) ? max( 0, (int) $cap ) : 0;
	}

	/**
	 * Words consumed this month (counter resets automatically each month).
	 */
	public function used(): int {
		$payload = $this->raw();
		if ( array() === $payload || gmdate( 'Y-m' ) !== $payload['month'] ) {
			return 0;
		}
		return max( 0, (int) $payload['words'] );
	}

	/**
	 * Words still allowed this month. PHP_INT_MAX when unlimited.
	 */
	public function remaining(): int {
		$limit = $this->limit();
		if ( 0 === $limit ) {
			return \PHP_INT_MAX;
		}
		return max( 0, $limit - $this->used() );
	}

	/**
	 * Whether the cap is fully consumed (refuse new paid work).
	 */
	public function is_exhausted(): bool {
		return 0 === $this->remaining();
	}

	/**
	 * Whether a batch of the given word count still fits in the cap.
	 *
	 * @param int $words Word count of the work about to be scheduled.
	 */
	public function allows( int $words ): bool {
		return $this->remaining() >= $words;
	}

	/**
	 * Charge words against the current month.
	 *
	 * @param int $words Word count, ignored when negative or zero.
	 */
	public function charge( int $words ): void {
		if ( $words <= 0 ) {
			return;
		}
		$month = gmdate( 'Y-m' );
		$raw   = $this->raw();
		$used  = ( array() !== $raw && $raw['month'] === $month ) ? (int) $raw['words'] : 0;
		update_option(
			Options::MONTH_WORDS,
			array(
				'month' => $month,
				'words' => $used + $words,
			),
			false
		);
	}

	/**
	 * Count words in a set of texts (Unicode letters and numbers).
	 *
	 * @param array<string, string> $texts Texts.
	 */
	public function count_words( array $texts ): int {
		$total = 0;
		foreach ( $texts as $text ) {
			$matches = preg_match_all( '/[\p{L}\p{N}]+/u', $text );
			$total  += is_int( $matches ) ? $matches : 0;
		}
		return $total;
	}

	/**
	 * Stored payload.
	 *
	 * @return array{month: string, words: int}|array{}
	 */
	private function raw(): array {
		$payload = get_option( Options::MONTH_WORDS );
		if ( ! is_array( $payload ) || ! isset( $payload['month'], $payload['words'] ) ) {
			return array();
		}
		if ( ! is_string( $payload['month'] ) || ! is_numeric( $payload['words'] ) ) {
			return array();
		}
		return array(
			'month' => $payload['month'],
			'words' => (int) $payload['words'],
		);
	}
}
