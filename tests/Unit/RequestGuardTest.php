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
		Functions\when( 'is_user_logged_in' )->justReturn( false );
		Functions\when( 'is_preview' )->justReturn( false );
		Functions\when( 'post_password_required' )->justReturn( false );
		Functions\when( 'wp_unslash' )->returnArg();
		Functions\when( 'sanitize_text_field' )->returnArg();
		Functions\when( 'sanitize_key' )->returnArg();
		Functions\when( 'sanitize_url' )->returnArg();
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url, int $component ): string|null => parse_url( $url, $component ) );
		Functions\when( 'rest_get_url_prefix' )->justReturn( 'wp-json' );
	}

	protected function tearDown(): void {
		$_GET = array();
		unset( $_SERVER['REQUEST_URI'] );
		parent::tearDown();
	}

	public function test_allows_regular_front_request(): void {
		$this->assertTrue( ( new RequestGuard() )->is_public_front_request() );
	}

	public function test_rejects_elementor_editor_request(): void {
		$_GET['elementor-preview'] = '42';
		$this->assertFalse( ( new RequestGuard() )->is_public_front_request() );
	}

	public function test_rejects_rest_path_before_rest_request_constant_exists(): void {
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts?context=view';
		$this->assertFalse( ( new RequestGuard() )->is_public_front_request() );
	}

	public function test_allows_woocommerce_fragment_ajax_for_rendering(): void {
		Functions\when( 'wp_doing_ajax' )->justReturn( true );
		$_GET['wc-ajax'] = 'get_refreshed_fragments';
		$this->assertTrue( ( new RequestGuard() )->is_public_front_request() );
	}

	public function test_cart_is_renderable_but_not_ai_eligible(): void {
		Functions\when( 'is_cart' )->justReturn( true );
		Functions\when( 'is_checkout' )->justReturn( false );
		Functions\when( 'is_account_page' )->justReturn( false );
		Functions\when( 'is_wc_endpoint_url' )->justReturn( false );
		$guard = new RequestGuard();

		$this->assertTrue( $guard->is_public_front_request() );
		$this->assertFalse( $guard->is_crawlable_or_ai_eligible() );
	}

	public function test_regular_anonymous_page_is_ai_eligible(): void {
		Functions\when( 'is_cart' )->justReturn( false );
		Functions\when( 'is_checkout' )->justReturn( false );
		Functions\when( 'is_account_page' )->justReturn( false );
		Functions\when( 'is_wc_endpoint_url' )->justReturn( false );
		$this->assertTrue( ( new RequestGuard() )->is_crawlable_or_ai_eligible() );
	}
}
