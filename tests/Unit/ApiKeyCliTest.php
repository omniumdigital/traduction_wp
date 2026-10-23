<?php
/**
 * API key WP-CLI command tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Providers\ApiKeyCli;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Security\Crypto;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeProvider;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

/**
 * @group security
 */
final class ApiKeyCliTest extends TestCase {

	private ApiKeyCli $command;

	private OptionStore $options;

	protected function setUp(): void {
		parent::setUp();
		\WP_CLI::reset();
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		Functions\when( '__' )->returnArg();
		Functions\when( 'current_user_can' )->justReturn( true );
		$this->options = new OptionStore();
		$this->options->install();

		$registry = new ProviderRegistry();
		$registry->add( new FakeProvider() );
		$this->command = new ApiKeyCli(
			new ApiKeyStore( new Crypto() ),
			$registry,
			static fn(): string => 'sk-secret-value-1234'
		);
	}

	public function test_registers_nested_command(): void {
		$this->command->register();

		$this->assertSame( 'omtr key', \WP_CLI::$calls['add_command'][0][0] );
	}

	public function test_set_encrypts_key_without_disclosing_it(): void {
		$this->command->set( array( 'claude' ), array() );

		$stored = serialize( $this->options->data );
		$output = serialize( \WP_CLI::$calls );
		$this->assertStringNotContainsString( 'sk-secret-value-1234', $stored );
		$this->assertStringNotContainsString( 'sk-secret-value-1234', $output );
		$this->assertArrayHasKey( 'success', \WP_CLI::$calls );
	}

	public function test_status_displays_only_masked_key(): void {
		$this->command->set( array( 'claude' ), array() );
		\WP_CLI::reset();
		Functions\expect( 'WP_CLI\\Utils\\format_items' )
			->once()
			->with(
				'table',
				array( array( 'provider' => 'claude', 'key' => 'sk-…1234' ) ),
				array( 'provider', 'key' )
			);

		$this->command->status( array(), array() );

		$this->assertStringNotContainsString( 'sk-secret-value-1234', serialize( \WP_CLI::$calls ) );
	}

	public function test_unknown_provider_is_refused_without_reading_key(): void {
		$this->command = new ApiKeyCli(
			new ApiKeyStore( new Crypto() ),
			new ProviderRegistry(),
			static function (): string {
				throw new \RuntimeException( 'The key reader must not run.' );
			}
		);

		$this->command->set( array( 'unknown' ), array() );

		$this->assertSame( 'Unknown provider.', \WP_CLI::$calls['error'][0][0] );
	}

	public function test_delete_removes_key(): void {
		$this->command->set( array( 'claude' ), array() );
		\WP_CLI::reset();

		$this->command->delete( array( 'claude' ), array() );

		$this->assertArrayHasKey( 'success', \WP_CLI::$calls );
		$this->assertSame( array(), $this->options->data['omtr_api_keys'] );
	}

	public function test_test_displays_generic_provider_result(): void {
		$this->command->test( array( 'claude' ), array() );

		$this->assertSame( 'API key is valid.', \WP_CLI::$calls['success'][0][0] );
	}
}
