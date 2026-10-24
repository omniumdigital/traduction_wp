<?php
/** Textdomain loading timing tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Plugin;
use Omnium\Translate\Tests\TestCase;

final class PluginTextdomainTest extends TestCase {

	/**
	 * WordPress 6.7+ logs a `_load_textdomain_just_in_time` notice for any
	 * domain loaded before `init`; load_textdomain() must defer to that hook.
	 */
	public function test_loads_the_textdomain_with_the_plugin_slug(): void {
		if ( ! defined( 'OMTR_FILE' ) ) {
			define( 'OMTR_FILE', '/tmp/omnium-translate/omnium-translate.php' );
		}
		Functions\when( 'plugin_basename' )->alias( static fn( string $file ): string => basename( dirname( $file ) ) . '/' . basename( $file ) );
		$domain = null;
		Functions\when( 'load_plugin_textdomain' )->alias(
			static function ( string $text_domain ) use ( &$domain ): bool {
				$domain = $text_domain;
				return true;
			}
		);

		Plugin::instance()->load_textdomain();

		$this->assertSame( 'omnium-translate', $domain );
	}
}
