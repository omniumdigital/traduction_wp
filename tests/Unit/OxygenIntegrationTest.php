<?php
/**
 * Oxygen 6 language-switcher integration tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Switcher\OxygenIntegration;
use Omnium\Translate\Tests\TestCase;

/**
 * Verifies the real Oxygen 6 Element Studio registration contract.
 *
 * @group security
 */
final class OxygenIntegrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'plugin_basename' )->alias( static fn( string $file ): string => 'omnium-translate/' . basename( $file ) );
		if ( ! defined( 'BREAKDANCE_MODE' ) ) {
			define( 'BREAKDANCE_MODE', 'oxygen' );
		}
		$GLOBALS['omtr_oxygen_locations'] = array();
		require_once dirname( __DIR__, 2 ) . '/src/Switcher/OxygenElement/element.php';
	}

	public function test_registers_oxygen_save_location(): void {
		( new OxygenIntegration() )->register_elements();

		$this->assertCount( 1, $GLOBALS['omtr_oxygen_locations'] );
		$this->assertSame( 'element', $GLOBALS['omtr_oxygen_locations'][0]['type'] );
		$this->assertSame( 'OmniumTranslate', $GLOBALS['omtr_oxygen_locations'][0]['namespace'] );
		$this->assertStringEndsWith( '/src/Switcher/OxygenElement', $GLOBALS['omtr_oxygen_locations'][0]['directory_path'] );
	}

	public function test_element_registers_for_oxygen_only(): void {
		$this->assertSame( 'OxygenElement\\LanguageSwitcher', $GLOBALS['omtr_oxygen_elements'][0]['slug'] );
		$this->assertSame( array( 'oxygen' ), \Omnium\Translate\Switcher\OxygenElement\LanguageSwitcher::availableIn() );
	}

	public function test_element_exposes_allowlisted_controls(): void {
		$controls = \Omnium\Translate\Switcher\OxygenElement\LanguageSwitcher::contentControls();

		$this->assertSame( array( 'dropdown', 'inline', 'floating' ), array_column( $controls[0]['options']['items'], 'value' ) );
		$this->assertSame( array( 'flag-name', 'flag', 'name', 'code' ), array_column( $controls[1]['options']['items'], 'value' ) );
	}
}
