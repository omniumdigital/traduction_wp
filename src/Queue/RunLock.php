<?php
/**
 * Lock ensuring a single complete translation run at a time.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Options;

defined( 'ABSPATH' ) || exit;

/**
 * CLAUDE.md section 5: only one complete translation at a time. The lock is
 * an option row: add_option() fails when the row already exists, which makes
 * acquisition atomic even with parallel Action Scheduler runners.
 *
 * A lock older than STALE_AFTER is considered dead (crashed run) and can be
 * stolen. Batch executions refresh the timestamp (heartbeat), so the stale
 * window only elapses when nothing runs at all.
 */
final class RunLock {

	/**
	 * A lock with no activity for 3 hours is stale: the longest backoff gap
	 * is 1 hour, which leaves a wide margin.
	 */
	private const STALE_AFTER = 10800;

	/**
	 * Try to acquire the lock for a run.
	 *
	 * @param string $run_id Run identifier.
	 * @return bool True when acquired.
	 */
	public function acquire( string $run_id ): bool {
		$payload = array(
			'run' => $run_id,
			'at'  => time(),
		);

		// add_option() is an INSERT: it fails when the lock already exists.
		if ( add_option( Options::TRANSLATION_LOCK, $payload, '', false ) ) {
			return true;
		}

		if ( ! $this->clear_stale() ) {
			return false;
		}

		return add_option( Options::TRANSLATION_LOCK, $payload, '', false );
	}

	/**
	 * Whether the lock is currently held (fresh).
	 */
	public function is_held(): bool {
		return null !== $this->current_run();
	}

	/**
	 * Run id currently holding the lock, null when free (stale locks are cleared).
	 */
	public function current_run(): ?string {
		$payload = get_option( Options::TRANSLATION_LOCK );
		if ( ! is_array( $payload ) || ! isset( $payload['run'], $payload['at'] ) ) {
			return null;
		}
		if ( ! is_string( $payload['run'] ) || ! is_numeric( $payload['at'] ) ) {
			return null;
		}
		if ( ( time() - (int) $payload['at'] ) > self::STALE_AFTER ) {
			return null;
		}
		return $payload['run'];
	}

	/**
	 * Refresh the lock timestamp while a run makes progress.
	 *
	 * @param string $run_id Run identifier.
	 */
	public function heartbeat( string $run_id ): void {
		$current = $this->raw_run();
		if ( $current === $run_id ) {
			update_option(
				Options::TRANSLATION_LOCK,
				array(
					'run' => $run_id,
					'at'  => time(),
				),
				false
			);
		}
	}

	/**
	 * Release the lock, only when it belongs to the given run.
	 *
	 * @param string $run_id Run identifier.
	 */
	public function release( string $run_id ): void {
		if ( $this->raw_run() === $run_id ) {
			delete_option( Options::TRANSLATION_LOCK );
		}
	}

	/**
	 * Delete the lock when it is stale. Returns whether a stale lock was cleared.
	 */
	private function clear_stale(): bool {
		$payload = get_option( Options::TRANSLATION_LOCK );
		if ( ! is_array( $payload ) || ! isset( $payload['at'] ) || ! is_numeric( $payload['at'] ) ) {
			// Malformed payload: clear it.
			delete_option( Options::TRANSLATION_LOCK );
			return true;
		}
		if ( ( time() - (int) $payload['at'] ) > self::STALE_AFTER ) {
			delete_option( Options::TRANSLATION_LOCK );
			return true;
		}
		return false;
	}

	/**
	 * Raw run id stored in the lock, whatever its age.
	 */
	private function raw_run(): ?string {
		$payload = get_option( Options::TRANSLATION_LOCK );
		if ( ! is_array( $payload ) || ! isset( $payload['run'] ) || ! is_string( $payload['run'] ) ) {
			return null;
		}
		return $payload['run'];
	}
}
