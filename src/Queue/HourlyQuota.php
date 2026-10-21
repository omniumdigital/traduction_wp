<?php
/**
 * Hourly quota for on-the-fly segment enqueuing.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Limits the number of segments accepted by enqueue_segments() each UTC hour.
 * A zero setting means unlimited. Full-site/page scans use the monthly word cap
 * instead and do not consume this visitor-triggered quota.
 */
final class HourlyQuota {
	private const LOCK_STALE_AFTER = 30;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Plugin settings.
	 */
	public function __construct( private Settings $settings ) {}

	/**
	 * Configured segment limit, zero when unlimited.
	 */
	public function limit(): int {
		$limit = $this->settings->get( 'hourly_quota' );
		return is_numeric( $limit ) ? max( 0, (int) $limit ) : 0;
	}

	/**
	 * Segments charged during the current UTC hour.
	 */
	public function used(): int {
		$payload = $this->raw();
		if ( array() === $payload || gmdate( 'Y-m-d-H' ) !== $payload['hour'] ) {
			return 0;
		}
		return max( 0, $payload['segments'] );
	}

	/**
	 * Whether a number of segments fits within the quota.
	 *
	 * @param int $segments Segment count.
	 */
	public function allows( int $segments ): bool {
		$limit = $this->limit();
		return 0 === $limit || ( $this->used() + max( 0, $segments ) ) <= $limit;
	}

	/**
	 * Atomically reserve on-the-fly segment capacity.
	 *
	 * @param int $segments Segment count.
	 */
	public function reserve( int $segments ): bool {
		if ( $segments <= 0 || 0 === $this->limit() ) {
			return true;
		}
		if ( ! $this->acquire_counter_lock() ) {
			return false;
		}

		try {
			if ( ! $this->allows( $segments ) ) {
				return false;
			}
			$this->charge( $segments );
			return true;
		} finally {
			delete_option( Options::HOUR_SEGMENTS_LOCK );
		}
	}

	/**
	 * Charge accepted segments to the current UTC hour.
	 *
	 * @param int $segments Segment count.
	 */
	public function charge( int $segments ): void {
		if ( $segments <= 0 || 0 === $this->limit() ) {
			return;
		}

		$hour    = gmdate( 'Y-m-d-H' );
		$payload = $this->raw();
		$used    = array() !== $payload && $payload['hour'] === $hour ? $payload['segments'] : 0;
		update_option(
			Options::HOUR_SEGMENTS,
			array(
				'hour'     => $hour,
				'segments' => $used + $segments,
			),
			false
		);
	}

	/**
	 * Stored payload.
	 *
	 * @return array{hour: string, segments: int}|array{}
	 */
	private function raw(): array {
		$payload = get_option( Options::HOUR_SEGMENTS );
		if ( ! is_array( $payload ) || ! isset( $payload['hour'], $payload['segments'] ) ) {
			return array();
		}
		if ( ! is_string( $payload['hour'] ) || ! is_numeric( $payload['segments'] ) ) {
			return array();
		}
		return array(
			'hour'     => $payload['hour'],
			'segments' => max( 0, (int) $payload['segments'] ),
		);
	}

	/**
	 * Acquire the atomic option lock, recovering a crashed writer after 30 s.
	 */
	private function acquire_counter_lock(): bool {
		if ( add_option( Options::HOUR_SEGMENTS_LOCK, time(), '', false ) ) {
			return true;
		}
		$created_at = get_option( Options::HOUR_SEGMENTS_LOCK );
		if ( ! is_numeric( $created_at ) || ( time() - (int) $created_at ) > self::LOCK_STALE_AFTER ) {
			delete_option( Options::HOUR_SEGMENTS_LOCK );
			return add_option( Options::HOUR_SEGMENTS_LOCK, time(), '', false );
		}
		return false;
	}
}
