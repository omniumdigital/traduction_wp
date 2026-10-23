<?php
/**
 * Trigger REST security tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Admin\TriggerController;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\Crawler;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Store\PageRepository;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentExtractor;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/** @group security */
final class TriggerControllerSecurityTest extends TestCase {
	public function test_anonymous_user_cannot_call_any_trigger_route(): void {
		( new OptionStore() )->install();
		$routes = array();
		Functions\when( 'current_user_can' )->justReturn( false );
		Functions\when( 'register_rest_route' )->alias(
			static function ( string $namespace, string $route, array $args ) use ( &$routes ): bool {
				$routes[] = array( 'namespace' => $namespace, 'route' => $route, 'args' => $args );
				return true;
			}
		);
		$settings = new Settings();
		$queue = new ActionSchedulerQueue( $settings, new RunLock(), new WordCap( $settings ), new HourlyQuota( $settings ), new ProviderRegistry(), new SpyLogger() );
		$content = new PublicContent();
		$crawler = new Crawler( $content, new PageRepository( new \wpdb() ), $queue, new FakeSegmentExtractor(), $settings, new SpyLogger() );
		( new TriggerController( $queue, $content, $crawler ) )->register_routes();

		$this->assertCount( 2, $routes );
		foreach ( $routes as $route ) {
			$this->assertSame( array( Capabilities::class, 'rest_permission' ), $route['args']['permission_callback'] );
			$this->assertFalse( call_user_func( $route['args']['permission_callback'] ) );
		}
	}
}
