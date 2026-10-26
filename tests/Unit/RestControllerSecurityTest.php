<?php
/**
 * Admin REST API security tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Admin\RestController;
use Omnium\Translate\Admin\SegmentEditorRepository;
use Omnium\Translate\Glossary\GlossaryCsv;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class RestControllerSecurityTest extends TestCase {
	/** @var list<array{namespace: string, route: string, args: array<mixed>}> */
	private array $routes = array();

	protected function setUp(): void {
		parent::setUp();
		$test = $this;
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $namespace, string $route, array $args ) use ( $test ): bool {
				$test->routes[] = compact( 'namespace', 'route', 'args' );
				return true;
			}
		);
	}

	public function test_every_route_denies_user_without_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->controller( new \wpdb() )->register_routes();

		$this->assertNotEmpty( $this->routes );
		foreach ( $this->routes as $route ) {
			$endpoints = isset( $route['args'][0] ) ? $route['args'] : array( $route['args'] );
			foreach ( $endpoints as $endpoint ) {
				$this->assertSame( array( Capabilities::class, 'rest_permission' ), $endpoint['permission_callback'] );
				$this->assertFalse( call_user_func( $endpoint['permission_callback'] ), 'WordPress converts this denied permission to HTTP 403.' );
			}
		}
	}

	public function test_segment_edit_sanitizes_xss_and_sets_edited_status(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$payload       = '<script>alert(1)</script><strong>Safe</strong>';
		Functions\when( 'wp_kses' )->alias(
			static fn( string $value, array $tags, array $protocols ): string => strip_tags( $value, '<strong>' )
		);
		Functions\when( 'current_time' )->justReturn( '2026-10-23 10:00:00' );
		Functions\when( 'wp_cache_incr' )->justReturn( 1 );
		Functions\when( 'get_option' )->justReturn( 0 );
		Functions\when( 'update_option' )->justReturn( true );
		$prepared_args = array();
		$wpdb->expects( $this->once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $sql, mixed ...$args ) use ( &$prepared_args ): string {
				unset( $sql );
				$prepared_args = $args;
				return 'prepared';
			}
		);
		$wpdb->expects( $this->once() )->method( 'query' )->with( 'prepared' );

		$response = $this->controller( $wpdb )->edit_segment( new \WP_REST_Request( array( 'id' => 7, 'target_lang' => 'en', 'text' => $payload ) ) );

		$this->assertSame( array( 'status' => 'edited' ), $response->data );
		$this->assertContains( 'alert(1)<strong>Safe</strong>', $prepared_args );
		$this->assertContains( 'edited', $prepared_args );
		$this->assertStringNotContainsString( '<script>', implode( '|', array_map( 'strval', $prepared_args ) ) );
	}

	public function test_segment_sort_schema_refuses_non_whitelisted_column(): void {
		$this->controller( new \wpdb() )->register_routes();
		$route = current( array_filter( $this->routes, static fn( array $item ): bool => '/segments' === $item['route'] ) );
		$this->assertIsArray( $route );
		$this->assertNotContains( 'DROP TABLE', $route['args']['args']['orderby']['enum'] );
		$this->assertSame( array( 'id', 'source_text', 'target_lang', 'status', 'updated_at' ), $route['args']['args']['orderby']['enum'] );
	}

	private function controller( \wpdb $wpdb ): RestController {
		$GLOBALS['wpdb'] = $wpdb;
		$glossary        = new GlossaryRepository( $wpdb );
		return new RestController( $glossary, new GlossaryCsv( $glossary ), new SegmentEditorRepository( $wpdb ) );
	}
}
