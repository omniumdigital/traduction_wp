<?php
/**
 * Slug REST endpoint security tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\SlugGenerator;
use Omnium\Translate\Router\SlugRestController;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Store\SlugRepository;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;

/** @group security */
final class SlugRestControllerSecurityTest extends TestCase {

	/** @dataProvider deniedUsers */
	public function test_anonymous_and_subscriber_users_are_denied( bool $logged_in ): void {
		unset( $logged_in );
		$routes = array();
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $namespace, string $path, array $args ) use ( &$routes ): bool {
				$routes[ $path ] = compact( 'namespace', 'path', 'args' );
				return true;
			}
		);
		Functions\when( 'current_user_can' )->justReturn( false );
		$wpdb         = new \wpdb();
		$GLOBALS['wpdb'] = $wpdb;
		( new SlugRestController( new SlugGenerator( new SlugRepository( $wpdb ), new FakeTranslationRepository(), new Settings() ) ) )->register_routes();

		// Every slug route, including the refresh trigger, requires omtr_manage.
		$this->assertSame( array( '/slugs', '/slugs/refresh' ), array_keys( $routes ) );
		foreach ( $routes as $route ) {
			$this->assertSame( 'omtr/v1', $route['namespace'] );
			$this->assertSame( array( Capabilities::class, 'rest_permission' ), $route['args']['permission_callback'] );
			$this->assertFalse( call_user_func( $route['args']['permission_callback'] ) );
		}
	}

	/** @return iterable<string, array{bool}> */
	public static function deniedUsers(): iterable {
		yield 'anonymous' => array( false );
		yield 'subscriber' => array( true );
	}
}
