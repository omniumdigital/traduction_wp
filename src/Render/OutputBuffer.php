<?php
/**
 * Final output buffer.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Render;

use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Queue\OnTheFlyTrigger;

defined( 'ABSPATH' ) || exit;

/**
 * Captures only translated, public front-end HTML responses.
 */
final class OutputBuffer {

	/**
	 * Constructor.
	 *
	 * @param CurrentLanguage      $current_language Current request language.
	 * @param RequestGuard         $guard            Front-request guard.
	 * @param Translator           $translator       HTML translator.
	 * @param OnTheFlyTrigger|null $on_the_fly  Guarded deferred trigger.
	 */
	public function __construct(
		private CurrentLanguage $current_language,
		private RequestGuard $guard,
		private Translator $translator,
		private ?OnTheFlyTrigger $on_the_fly = null
	) {}

	/**
	 * Start buffering at template_redirect.
	 */
	public function start(): void {
		if ( ! $this->current_language->is_translated() || ! $this->guard->is_public_front_request() || is_feed() || is_robots() ) {
			return;
		}
		ob_start( array( $this, 'process' ) );
	}

	/**
	 * Output-buffer callback.
	 *
	 * @param string $html Final output.
	 */
	public function process( string $html ): string {
		if ( null !== $this->on_the_fly ) {
			$this->on_the_fly->capture( $html );
		}
		return $this->translator->translate( $html );
	}
}
