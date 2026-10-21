<?php
/**
 * Logger test double recording every call.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit\Doubles;

use Omnium\Translate\Core\LoggerInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Records log lines so tests can assert what was written (and what was not).
 */
final class SpyLogger implements LoggerInterface {

	/**
	 * Recorded lines: [level, context, message].
	 *
	 * @var list<array{0: string, 1: string, 2: string}>
	 */
	public array $lines = array();

	/**
	 * {@inheritDoc}
	 *
	 * @param string $level   Level.
	 * @param string $context Context.
	 * @param string $message Message.
	 */
	public function log( string $level, string $context, string $message ): void {
		$this->lines[] = array( $level, $context, $message );
	}

	/**
	 * All messages joined, for quick content assertions.
	 */
	public function joined(): string {
		$messages = array();
		foreach ( $this->lines as $line ) {
			$messages[] = $line[2];
		}
		return implode( "\n", $messages );
	}
}
