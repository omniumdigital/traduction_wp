<?php
/**
 * Manual queue pause state.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Options;

defined( 'ABSPATH' ) || exit;

/** Stores the administrator-controlled pause flag. */
final class QueueState {
	/** Whether translation actions must defer their work. */
	public function is_paused(): bool {
		return (bool) get_option( Options::QUEUE_PAUSED, false );
	}

	/** Pause all new and scheduled translation work. */
	public function pause(): void {
		update_option( Options::QUEUE_PAUSED, true, false );
	}

	/** Allow queued work to continue. */
	public function resume(): void {
		delete_option( Options::QUEUE_PAUSED );
	}
}
