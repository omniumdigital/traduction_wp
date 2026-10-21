<?php
/**
 * WP-CLI commands: wp omtr translate, wp omtr status.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Minimal milestone 2 trigger: enqueue one page and inspect the queue state.
 *
 * The commands only enqueue work; the translation itself runs in Action
 * Scheduler, never during the CLI process or a visitor request.
 */
final class TranslateCli {

	/**
	 * Constructor.
	 *
	 * @param ActionSchedulerQueue $queue    Queue.
	 * @param Settings             $settings Plugin settings.
	 * @param RunLock              $lock     Translation run lock.
	 * @param WordCap              $word_cap Monthly word cap.
	 * @param HourlyQuota          $hourly_quota Hourly on-the-fly quota.
	 */
	public function __construct(
		private ActionSchedulerQueue $queue,
		private Settings $settings,
		private RunLock $lock,
		private WordCap $word_cap,
		private HourlyQuota $hourly_quota
	) {}

	/**
	 * Register the commands (called during boot, WP-CLI context only).
	 */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		\WP_CLI::add_command( 'omtr', self::class );
	}

	/**
	 * Enqueue the translation of a page for every target language.
	 *
	 * ## OPTIONS
	 *
	 * <post_id|url>
	 * : Post id or full URL of a public page of this site.
	 *
	 * ## EXAMPLES
	 *
	 *     wp omtr translate 42 --user=admin
	 *     wp omtr translate https://example.test/ma-page/ --user=admin
	 *
	 * @param list<string>         $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function translate( array $args, array $assoc_args ): void {
		$this->guard();
		unset( $assoc_args );

		if ( array() === $args ) {
			\WP_CLI::error( __( 'Provide a post id or a URL.', 'omnium-translate' ) );
			return;
		}

		$target = $args[0];

		if ( ctype_digit( $target ) ) {
			$permalink = get_permalink( (int) $target );
			if ( ! is_string( $permalink ) || '' === $permalink ) {
				\WP_CLI::error(
					sprintf(
						/* translators: %d: post id. */
						__( 'Post %d has no public URL.', 'omnium-translate' ),
						(int) $target
					)
				);
				return;
			}
			$target  = $permalink;
			$post_id = (int) $args[0];
		} else {
			$post_id = 0;
		}

		if ( $this->queue->enqueue_url( $target, $post_id ) ) {
			\WP_CLI::success( __( 'Translation queued: run "wp omtr status" and the Action Scheduler queue.', 'omnium-translate' ) );
			return;
		}

		$reason = $this->queue->last_reason();
		\WP_CLI::error( '' !== $reason ? $reason : __( 'The URL was refused.', 'omnium-translate' ) );
	}

	/**
	 * Show the translation engine status.
	 *
	 * ## EXAMPLES
	 *
	 *     wp omtr status --user=admin
	 *
	 * @param list<string>         $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function status( array $args, array $assoc_args ): void {
		$this->guard();
		unset( $args, $assoc_args );

		$provider = $this->queue->current_provider();
		$rows     = array(
			array(
				'key'   => 'provider',
				'value' => null !== $provider ? $provider->label() . ' (' . $provider->id() . ')' : __( 'none', 'omnium-translate' ),
			),
			array(
				'key'   => 'source_lang',
				'value' => $this->settings->source_lang(),
			),
			array(
				'key'   => 'target_langs',
				'value' => implode( ', ', $this->settings->target_langs() ),
			),
			array(
				'key'   => 'run_lock',
				'value' => $this->lock->is_held() ? __( 'held (a run is in progress)', 'omnium-translate' ) : __( 'free', 'omnium-translate' ),
			),
			array(
				'key'   => 'pending_batches',
				'value' => (string) $this->queue->pending_batch_count(),
			),
		);

		$limit = $this->word_cap->limit();
		if ( $limit > 0 ) {
			$rows[] = array(
				'key'   => 'monthly_word_cap',
				'value' => sprintf( '%d / %d', $this->word_cap->used(), $limit ),
			);
		} else {
			$rows[] = array(
				'key'   => 'monthly_word_cap',
				'value' => __( 'unlimited', 'omnium-translate' ),
			);
		}

		$hourly_limit = $this->hourly_quota->limit();
		$rows[]       = array(
			'key'   => 'hourly_segment_quota',
			'value' => $hourly_limit > 0
				? sprintf( '%d / %d', $this->hourly_quota->used(), $hourly_limit )
				: __( 'unlimited', 'omnium-translate' ),
		);

		if ( null !== $provider ) {
			$chosen = (string) $this->settings->get( 'model' );
			if ( '' === $chosen ) {
				$first  = array_key_first( $provider->models() );
				$chosen = is_string( $first ) ? $first : '';
			}
			$rows[] = array(
				'key'   => 'model',
				'value' => $chosen,
			);
		}

		\WP_CLI::line( sprintf( '# %s', __( 'Omnium Translate status', 'omnium-translate' ) ) );
		\WP_CLI::format_items( 'table', $rows, array( 'key', 'value' ) );
	}

	/**
	 * Refuse to run without the plugin capability (CLAUDE.md section 11:
	 * capability omtr_manage everywhere; use --user=admin in WP-CLI).
	 */
	private function guard(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			\WP_CLI::error( __( 'You need the omtr_manage capability (try --user=admin).', 'omnium-translate' ) );
		}
	}
}
