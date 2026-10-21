<?php
/**
 * Front-request guard tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Tests\TestCase;

final class RequestGuardTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'wp_doing_cron' )->justReturn( false );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
	}

	protected function tearDown(): void {
		$_GET = array();
		parent::tearDown();
	}

	public function test_allows_regular_front_request(): void {
		$this->assertTrue( ( new RequestGuard() )->is_public_front_request() );
	}

	public function test_rejects_elementor_editor_request(): void {
		$_GET['elementor-preview'] = '42';
		$this->assertFalse( ( new RequestGuard() )->is_public_front_request() );
	}
}
