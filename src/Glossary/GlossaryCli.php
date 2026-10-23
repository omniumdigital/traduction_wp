<?php
/**
 * Glossary WP-CLI commands.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Glossary;

use Omnium\Translate\Security\Capabilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers wp omtr glossary subcommands.
 */
final class GlossaryCli {

	/**
	 * Constructor.
	 *
	 * @param GlossaryRepository $repository Glossary repository.
	 * @param GlossaryCsv        $csv        CSV service.
	 * @param GlossaryService    $service    Glossary service.
	 */
	public function __construct(
		private GlossaryRepository $repository,
		private GlossaryCsv $csv,
		private GlossaryService $service
	) {}

	/** Register CLI commands. */
	public function register(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}
		\WP_CLI::add_command( 'omtr glossary list', array( $this, 'list_items' ) );
		\WP_CLI::add_command( 'omtr glossary add', array( $this, 'add' ) );
		\WP_CLI::add_command( 'omtr glossary delete', array( $this, 'delete' ) );
		\WP_CLI::add_command( 'omtr glossary import', array( $this, 'import' ) );
		\WP_CLI::add_command( 'omtr glossary export', array( $this, 'export' ) );
		\WP_CLI::add_command( 'omtr glossary affected', array( $this, 'affected' ) );
	}

	/**
	 * List glossary rules.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	public function list_items( array $args = array() ): void {
		unset( $args );
		$this->authorize();
		\WP_CLI\Utils\format_items( 'table', $this->repository->all(), array( 'id', 'term', 'target_lang', 'translation', 'rule' ) );
	}

	/**
	 * Add a glossary rule.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	public function add( array $args ): void {
		$this->authorize();
		if ( 4 !== count( $args ) ) {
			\WP_CLI::error( __( 'Usage: wp omtr glossary add <term> <target_lang> <translation> <rule>.', 'omnium-translate' ) );
			return;
		}
		$term        = sanitize_text_field( $args[0] );
		$target_lang = sanitize_key( $args[1] );
		$translation = sanitize_text_field( $args[2] );
		$rule        = sanitize_key( $args[3] );
		try {
			GlossaryCsv::validate_values( $term, $target_lang, $translation, $rule );
			$id = $this->repository->add( $term, $target_lang, $translation, $rule );
			/* translators: %d: glossary rule id. */
			\WP_CLI::success( sprintf( __( 'Glossary rule %d added.', 'omnium-translate' ), $id ) );
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Delete a glossary rule.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	public function delete( array $args ): void {
		$this->authorize();
		$id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( 0 === $id ) {
			\WP_CLI::error( __( 'Provide a glossary rule id.', 'omnium-translate' ) );
			return;
		}
		$this->repository->delete( $id );
		\WP_CLI::success( __( 'Glossary rule deleted.', 'omnium-translate' ) );
	}

	/**
	 * Import glossary rules.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	public function import( array $args ): void {
		$this->authorize();
		if ( ! isset( $args[0] ) ) {
			\WP_CLI::error( __( 'Provide a CSV file.', 'omnium-translate' ) );
			return;
		}
		try {
			$count = $this->csv->import( $args[0] );
			/* translators: %d: number of imported glossary rules. */
			\WP_CLI::success( sprintf( __( '%d glossary rules imported.', 'omnium-translate' ), $count ) );
		} catch ( \InvalidArgumentException $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}

	/**
	 * Export glossary rules.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	public function export( array $args = array() ): void {
		unset( $args );
		$this->authorize();
		\WP_CLI::line( $this->csv->export() );
	}

	/**
	 * List auto translations affected by a glossary rule.
	 *
	 * @param list<string> $args Positional arguments.
	 */
	public function affected( array $args ): void {
		$this->authorize();
		$id = isset( $args[0] ) ? absint( $args[0] ) : 0;
		if ( 0 === $id ) {
			\WP_CLI::error( __( 'Provide a glossary rule id.', 'omnium-translate' ) );
			return;
		}
		\WP_CLI\Utils\format_items(
			'table',
			$this->service->affected( $id ),
			array( 'segment_id', 'source_text', 'translation_id', 'translation', 'target_lang', 'status' )
		);
	}

	/** Require the plugin management capability. */
	private function authorize(): void {
		if ( ! Capabilities::current_user_can_manage() ) {
			\WP_CLI::error( __( 'You need the omtr_manage capability (try --user=admin).', 'omnium-translate' ) );
		}
	}
}
