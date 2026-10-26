<?php
/**
 * Capability grant tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

/**
 * The boot-time grant repairs installs updated via "Replace existing" without
 * re-running activation, while never re-granting over a deliberate removal.
 *
 * @group security
 */
final class CapabilitiesTest extends TestCase {

	private OptionStore $options;

	/**
	 * Administrator role double recording added capabilities.
	 */
	private FakeRole $role;

	protected function setUp(): void {
		parent::setUp();
		$this->options = new OptionStore();
		$this->options->install();
		$this->role = new FakeRole();
		Functions\when( 'get_role' )->justReturn( $this->role );
	}

	public function test_ensure_granted_grants_once_and_records_the_flag(): void {
		Capabilities::ensure_granted();
		Capabilities::ensure_granted();

		$this->assertSame( array( Capabilities::MANAGE ), $this->role->caps );
		$this->assertSame( 1, $this->options->data[ Options::CAPS_GRANTED ] );
	}

	public function test_ensure_granted_never_re_grants_after_a_deliberate_removal(): void {
		$this->options->data[ Options::CAPS_GRANTED ] = 1;

		Capabilities::ensure_granted();

		$this->assertSame( array(), $this->role->caps );
	}

	public function test_ensure_granted_retries_on_a_later_boot_when_no_role_exists_yet(): void {
		Functions\when( 'get_role' )->justReturn( false );

		Capabilities::ensure_granted();

		$this->assertArrayNotHasKey( Options::CAPS_GRANTED, $this->options->data );
	}
}

/**
 * Minimal WP_Role double.
 */
final class FakeRole {

	/**
	 * Added capabilities.
	 *
	 * @var list<string>
	 */
	public array $caps = array();

	/**
	 * Record a capability addition.
	 *
	 * @param string $cap Capability.
	 */
	public function add_cap( string $cap ): void {
		$this->caps[] = $cap;
	}
}
