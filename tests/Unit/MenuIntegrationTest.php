<?php
/**
 * Navigation-menu switcher tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Switcher\MenuIntegration;
use Omnium\Translate\Switcher\Switcher;
use Omnium\Translate\Switcher\SwitcherAssets;
use Omnium\Translate\Tests\TestCase;

/**
 * Verifies safe integration with WordPress classic menus.
 *
 * @group security
 */
final class MenuIntegrationTest extends TestCase {

	/**
	 * Integration under test.
	 *
	 * @var MenuIntegration
	 */
	private MenuIntegration $integration;

	/** Set up WordPress function doubles. */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
		Functions\when( 'esc_html' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'esc_attr' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'esc_attr__' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'  => 'fr',
				'target_langs' => array( 'en' ),
			)
		);
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://example.test' . $path );
		Functions\when( 'wp_parse_url' )->alias(
			static fn( string $url ): array|false => parse_url( $url ) // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- Test double for wp_parse_url().
		);
		Functions\when( 'url_to_postid' )->justReturn( 0 );
		Functions\when( 'shortcode_atts' )->alias( static fn( array $defaults, array $attributes ): array => array_merge( $defaults, $attributes ) );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );

		$settings          = new Settings();
		$current           = new CurrentLanguage( 'fr' );
		$switcher          = new Switcher( $settings, $current, new UrlTranslator( $settings ), new SwitcherAssets() );
		$this->integration = new MenuIntegration( $switcher );
		$GLOBALS['wp']     = (object) array( 'request' => 'article' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Controlled request fixture.
	}

	/** Remove the request fixture. */
	protected function tearDown(): void {
		unset( $GLOBALS['wp'] );
		parent::tearDown();
	}

	/** The admin box emits only fields handled by WordPress core. */
	public function test_renders_native_menu_item_fields(): void {
		ob_start();
		$this->integration->render_meta_box();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'menu-item[-1][menu-item-type]', $html );
		$this->assertStringContainsString( 'value="custom"', $html );
		$this->assertStringContainsString( 'value="#omtr-switcher"', $html );
		$this->assertStringContainsString( 'Add to Menu', $html );
	}

	/** Ordinary menu items remain byte-for-byte unchanged. */
	public function test_preserves_regular_menu_items(): void {
		$item = (object) array( 'url' => 'https://example.test/contact' );

		$this->assertSame( '<a href="/contact">Contact</a>', $this->integration->filter_item_output( '<a href="/contact">Contact</a>', $item, 0, (object) array() ) );
	}

	/** The marker item is replaced with a non-nested accessible switcher. */
	public function test_replaces_marker_item_with_switcher(): void {
		$item = (object) array( 'url' => '#omtr-switcher' );

		$html = $this->integration->filter_item_output( '<a href="#omtr-switcher">Language selector</a>', $item, 0, (object) array() );

		$this->assertStringStartsWith( '<div class="omtr-switcher', $html );
		$this->assertStringNotContainsString( '<nav', $html );
		$this->assertStringContainsString( 'href="https://example.test/en/article"', $html );
	}
}
