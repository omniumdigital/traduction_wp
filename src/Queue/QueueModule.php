<?php
/**
 * Translation queue module.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Container;
use Omnium\Translate\Core\ModuleInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Action Scheduler jobs (group "omtr"), batching, retry/backoff, rate limit, lock, caps
 * (CLAUDE.md section 5). Never call a provider during a visitor request. TODO(jalon 2).
 */
final class QueueModule implements ModuleInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function register( Container $container ): void {
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Container $container Container.
	 */
	public function boot( Container $container ): void {
	}
}
