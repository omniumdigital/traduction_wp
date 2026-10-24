<?php
/**
 * Uninstall routine tests.
 *
 * @package Omnium\Translate\Tests
 */

namespace Omnium\Translate\Tests;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Tables;

/**
 * @group security
 */
final class UninstallTest extends TestCase {

	/**
	 * Recording wpdb double.
	 */
	private RecordingWpdb $wpdb;

	/**
	 * Set up the global database double.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->wpdb              = new RecordingWpdb();
		$GLOBALS['wpdb']         = $this->wpdb;
		$GLOBALS['wp_the_query'] = null;
	}

	/**
	 * Run the real uninstall.php with stubbed WordPress APIs.
	 *
	 * @param bool $purge Whether the admin chose a full purge.
	 */
	private function run_uninstall( bool $purge ): void {
		$marker = new class {
			/**
			 * Roles without capabilities.
			 *
			 * @var array<string, object>
			 */
			public array $role_objects = array();
		};

		Functions\expect( 'wp_roles' )->atLeast()->once()->andReturn( $marker );
		Functions\stubs(
			array(
				'esc_sql' => static fn( string $value ): string => $value,
			)
		);
		Functions\expect( 'get_option' )->atLeast()->once()->andReturnUsing(
			static function ( string $name ) use ( $purge ) {
				if ( Options::UNINSTALL_PURGE === $name ) {
					return $purge;
				}
				return false;
			}
		);
		Functions\expect( 'get_posts' )->once()->andReturn( array( 12, 34 ) );
		Functions\expect( 'wp_delete_post' )->twice()->andReturnUsing(
			static function ( int $id ): bool {
				return $id > 0;
			}
		);
		Functions\expect( 'delete_option' )->atLeast()->once()->andReturn( true );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'omnium-translate/omnium-translate.php' );
		}
		require dirname( __DIR__, 2 ) . '/uninstall.php';
	}

	/** @testDox Le marqueur de menu est retiré même sans purge complète. */
	public function test_removes_switcher_menu_items_without_purge(): void {
		$this->run_uninstall( false );

		$drops = array_filter(
			$this->wpdb->queries,
			static fn( string $sql ): bool => str_contains( $sql, 'DROP TABLE' )
		);
		$this->assertSame( array(), array_values( $drops ), 'No table must be dropped without purge.' );
	}

	/** @testDox La purge retire tables, options et méta _omtr_. */
	public function test_purge_drops_tables_and_deletes_traces(): void {
		$this->run_uninstall( true );

		$drops = array_filter(
			$this->wpdb->queries,
			static fn( string $sql ): bool => str_contains( $sql, 'DROP TABLE' )
		);
		$this->assertCount( count( Tables::all() ), $drops );

		$deletes = array_filter(
			$this->wpdb->queries,
			static fn( string $sql ): bool => str_contains( $sql, 'DELETE FROM' )
		);
		$this->assertCount( 2, $deletes, 'Options and postmeta LIKE purge queries must run.' );
		$this->assertStringContainsString( 'LIKE', array_values( $deletes )[0] ?? '' );
	}

	/** @testDox L'élément de menu retiré porte exactement l'URL marqueur du module. */
	public function test_menu_marker_matches_switcher_module(): void {
		$this->assertSame( '#omtr-switcher', \Omnium\Translate\Switcher\MenuIntegration::ITEM_URL );
	}
}

/** Minimal wpdb double recording every query. */
final class RecordingWpdb {

	public string $prefix = 'wp_';

	public string $options = 'wp_options';

	public string $postmeta = 'wp_postmeta';

	public string $omtr_segments = 'wp_omtr_segments';

	/** @var list<string> */
	public array $queries = array();

	/**
	 * Record and accept any query.
	 *
	 * @param string $query SQL.
	 */
	public function query( string $query ): int {
		$this->queries[] = $query;
		return 1;
	}

	/**
	 * Prepare a statement.
	 *
	 * @param string $query SQL.
	 * @param mixed  ...$args Arguments.
	 */
	public function prepare( string $query, mixed ...$args ): string {
		return $query . '|' . implode( '|', array_map( 'strval', $args ) );
	}

	/**
	 * Escape LIKE wildcards.
	 *
	 * @param string $text Raw text.
	 */
	public function esc_like( string $text ): string {
		return addcslashes( $text, '_%\\' );
	}
}
