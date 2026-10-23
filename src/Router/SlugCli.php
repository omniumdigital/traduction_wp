<?php
/**
 * WP-CLI translated slug commands.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Router;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/** Commands exposed below `wp omtr slugs`. */
final class SlugCli {

	/**
	 * Constructor.
	 *
	 * @param SlugGenerator $generator Slug generator.
	 * @param PublicContent $content   Public content resolver.
	 * @param Settings      $settings  Plugin settings.
	 */
	public function __construct( private SlugGenerator $generator, private PublicContent $content, private Settings $settings ) {}

	/** Register the nested CLI command. */
	public function register(): void {
		\WP_CLI::add_command( 'omtr slugs', $this );
	}

	/**
	 * Generate slugs for one post or every public post.
	 *
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 */
	public function generate( array $args, array $assoc_args ): void {
		$this->guard();
		$all = isset( $assoc_args['all'] );
		if ( ( $all && array() !== $args ) || ( ! $all && 1 !== count( $args ) ) ) {
			\WP_CLI::error( __( 'Provide one post id, or use --all.', 'omnium-translate' ) );
			return;
		}
		$targets = $all ? $this->content->all() : array_filter( array( $this->content->resolve( (int) $args[0] ) ) );
		$count   = 0;
		foreach ( $targets as $target ) {
			foreach ( $this->settings->target_langs() as $lang ) {
				if ( null !== $this->generator->generate( $target['post_id'], $lang ) ) {
					++$count;
				}
			}
		}
		\WP_CLI::success(
			sprintf(
				/* translators: %d: generated slug count. */
				__( '%d translated slug(s) generated.', 'omnium-translate' ),
				$count
			)
		);
	}

	/**
	 * Set one translated slug manually.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	public function set( array $args ): void {
		$this->guard();
		if ( 3 !== count( $args ) ) {
			\WP_CLI::error( __( 'Usage: wp omtr slugs set <post_id> <lang> <slug>.', 'omnium-translate' ) );
			return;
		}
		$slug = $this->generator->set( (int) $args[0], sanitize_key( (string) $args[1] ), (string) $args[2] );
		if ( null === $slug ) {
			\WP_CLI::error( __( 'The translated slug is invalid.', 'omnium-translate' ) );
			return;
		}
		\WP_CLI::success(
			sprintf(
				/* translators: %s: translated slug. */
				__( 'Translated slug set to %s.', 'omnium-translate' ),
				$slug
			)
		);
	}

	/** Refuse commands without the plugin capability. */
	private function guard(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			\WP_CLI::error( __( 'You need the omtr_manage capability (try --user=admin).', 'omnium-translate' ) );
		}
	}
}
