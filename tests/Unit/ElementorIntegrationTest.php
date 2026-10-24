<?php
/**
 * Elementor switcher widget tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Switcher\ElementorIntegration;
use Omnium\Translate\Switcher\ElementorSwitcherWidget;
use Omnium\Translate\Switcher\Switcher;
use Omnium\Translate\Switcher\SwitcherAssets;
use Omnium\Translate\Tests\TestCase;

/**
 * Verifies optional Elementor registration, controls and rendering.
 *
 * @group security
 */
final class ElementorIntegrationTest extends TestCase {

	/**
	 * Elementor registry double.
	 *
	 * @var \Elementor\Widgets_Manager
	 */
	private \Elementor\Widgets_Manager $manager;

	/** Set up WordPress function doubles and the optional integration. */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg();
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
		Functions\when( 'esc_attr' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'esc_attr__' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'esc_html' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'esc_url' )->alias( static fn( string $value ): string => htmlspecialchars( $value, ENT_QUOTES ) );
		Functions\when( 'wp_enqueue_style' )->justReturn( null );
		Functions\when( 'wp_enqueue_script' )->justReturn( null );

		$this->manager = new \Elementor\Widgets_Manager();
		$settings      = new Settings();
		$switcher      = new Switcher( $settings, new CurrentLanguage( 'fr' ), new UrlTranslator( $settings ), new SwitcherAssets() );
		( new ElementorIntegration( $switcher ) )->register_widget( $this->manager );
		$GLOBALS['wp'] = (object) array( 'request' => 'article' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Controlled request fixture.
	}

	/** Remove the request fixture. */
	protected function tearDown(): void {
		unset( $GLOBALS['wp'] );
		parent::tearDown();
	}

	/** Elementor receives exactly one identifiable widget. */
	public function test_registers_widget_metadata(): void {
		$this->assertCount( 1, $this->manager->registered );
		$widget = $this->widget();

		$this->assertSame( 'omtr-switcher', $widget->get_name() );
		$this->assertSame( 'Language selector', $widget->get_title() );
		$this->assertSame( array( 'general' ), $widget->get_categories() );
		$this->assertContains( 'translation', $widget->get_keywords() );
	}

	/** The widget exposes only allowlisted presentation choices. */
	public function test_registers_variant_and_display_controls(): void {
		$widget = $this->widget();
		( new \ReflectionMethod( $widget, 'register_controls' ) )->invoke( $widget );

		$this->assertSame( array( 'dropdown', 'inline', 'floating' ), array_keys( $widget->test_controls['variant']['options'] ) );
		$this->assertSame( array( 'flag-name', 'flag', 'name', 'code' ), array_keys( $widget->test_controls['display']['options'] ) );
	}

	/** Front rendering delegates to the shared escaped component. */
	public function test_renders_widget_settings_safely(): void {
		$widget                = $this->widget();
		$widget->test_settings = array(
			'variant' => 'inline',
			'display' => 'code',
		);

		ob_start();
		( new \ReflectionMethod( $widget, 'render' ) )->invoke( $widget );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'omtr-switcher--inline', $html );
		$this->assertStringContainsString( 'omtr-switcher--code', $html );
		$this->assertStringContainsString( 'href="https://example.test/en/article"', $html );
	}

	/** Return the widget registered during setup. */
	private function widget(): ElementorSwitcherWidget {
		$widget = $this->manager->registered[0];
		$this->assertInstanceOf( ElementorSwitcherWidget::class, $widget );
		return $widget;
	}
}
