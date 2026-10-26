<?php
/**
 * Language switcher tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Switcher\Switcher;
use Omnium\Translate\Switcher\SwitcherAssets;
use Omnium\Translate\Tests\TestCase;

/**
 * Verifies accessible, escaped server rendering.
 *
 * @group security
 */
final class SwitcherTest extends TestCase {

	/**
	 * Number of stylesheet enqueue calls.
	 *
	 * @var int
	 */
	private int $style_calls = 0;

	/**
	 * Number of script enqueue calls.
	 *
	 * @var int
	 */
	private int $script_calls = 0;

	/**
	 * Number of late stylesheet print calls.
	 *
	 * @var int
	 */
	private int $print_calls = 0;

	/**
	 * Whether the stylesheet was printed by the test double.
	 *
	 * @var bool
	 */
	private bool $style_done = false;

	/** Set up WordPress function doubles. */
	protected function setUp(): void {
		parent::setUp();
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
		Functions\when( 'wp_enqueue_style' )->alias(
			function (): void {
				++$this->style_calls;
			}
		);
		Functions\when( 'wp_enqueue_script' )->alias(
			function (): void {
				++$this->script_calls;
			}
		);
		Functions\when( 'wp_style_is' )->alias( fn(): bool => $this->style_done );
		Functions\when( 'wp_print_styles' )->alias(
			function (): void {
				++$this->print_calls;
				$this->style_done = true;
			}
		);
		$GLOBALS['wp'] = (object) array( 'request' => 'article' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Controlled request fixture.
	}

	/** Remove the request fixture. */
	protected function tearDown(): void {
		unset( $GLOBALS['wp'] );
		parent::tearDown();
	}

	/** Dropdown output exposes accessible controls and safe language links. */
	public function test_renders_accessible_dropdown_with_localized_links(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'  => 'fr',
				'target_langs' => array( 'en', 'es' ),
			)
		);
		$current = new CurrentLanguage( 'fr' );
		$current->set( 'en' );

		$html = $this->switcher( $current )->render();

		$this->assertStringContainsString( 'aria-label="Language selector"', $html );
		$this->assertStringContainsString( 'data-omtr-skip', $html );
		$this->assertStringContainsString( 'translate="no"', $html );
		$this->assertStringContainsString( 'notranslate', $html );
		$this->assertStringContainsString( 'aria-expanded="false"', $html );
		$this->assertStringContainsString( 'href="https://example.test/article"', $html );
		$this->assertStringContainsString( 'href="https://example.test/en/article"', $html );
		$this->assertStringContainsString( 'aria-current="page"', $html );
		$this->assertStringContainsString( 'assets/flags/gb.svg', $html );
		$this->assertSame( 1, $this->style_calls );
		$this->assertSame( 1, $this->script_calls );
	}

	/** Preference attributes are absent by default and contain only configured codes when enabled. */
	public function test_optional_preference_cookie_marker(): void {
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		$this->assertStringNotContainsString( 'data-omtr-pref=', $this->switcher( new CurrentLanguage( 'fr' ) )->render() );

		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ), 'pref_cookie' => true ) );
		$this->assertStringContainsString( 'data-omtr-pref="en"', $this->switcher( new CurrentLanguage( 'fr' ) )->render() );
	}

	/** Inline/code options work and assets are only requested once. */
	public function test_inline_code_variant_and_assets_are_rendered_once(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'  => 'fr',
				'target_langs' => array( 'en' ),
			)
		);
		$switcher = $this->switcher( new CurrentLanguage( 'fr' ) );

		$html = $switcher->render(
			array(
				'variant' => 'inline',
				'display' => 'code',
			)
		);
		$switcher->render();

		$this->assertStringContainsString( 'omtr-switcher--inline', $html );
		$this->assertStringContainsString( '>FR</span>', $html );
		$this->assertStringNotContainsString( '<details', $html );
		$this->assertSame( 1, $this->style_calls );
		$this->assertSame( 1, $this->script_calls );
	}

	/** Stored appearance and country flag are reflected safely in markup. */
	public function test_uses_configured_appearance_and_flag(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'  => 'fr',
				'target_langs' => array( 'en' ),
				'flags'        => array( 'en' => 'us' ),
				'switcher'     => array(
					'variant'   => 'floating',
					'position'  => 'top-left',
					'dark_mode' => true,
					'colors'    => array( 'bg' => '#123456' ),
					'radius'    => 12,
					'flag_size' => 22,
				),
			)
		);
		$html = $this->switcher( new CurrentLanguage( 'fr' ) )->render();
		$this->assertStringContainsString( 'omtr-switcher--floating', $html );
		$this->assertStringContainsString( 'omtr-switcher--top-left', $html );
		$this->assertStringContainsString( 'omtr-switcher--auto-dark', $html );
		$this->assertStringContainsString( '--omtr-bg:#123456', $html );
		$this->assertStringContainsString( '--omtr-radius:12px', $html );
		$this->assertStringContainsString( 'assets/flags/us.svg', $html );
	}

	/** Untrusted settings and shortcode values cannot inject markup or classes. */
	public function test_rejects_unsafe_languages_and_invalid_variants(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'  => 'fr',
				'target_langs' => array( 'en', 'bad\"><script>alert(1)</script>' ),
			)
		);

		$html = $this->switcher( new CurrentLanguage( 'fr' ) )->render(
			array(
				'variant' => 'evil',
				'display' => 'evil',
			)
		);

		$this->assertStringNotContainsString( '<script>alert', $html );
		$this->assertStringContainsString( 'omtr-switcher--dropdown', $html );
		$this->assertStringContainsString( 'omtr-switcher--flag-name', $html );
	}

	/** A single configured language does not load assets or output navigation. */
	public function test_outputs_nothing_without_a_target_language(): void {
		Functions\when( 'get_option' )->justReturn(
			array(
				'source_lang'  => 'fr',
				'target_langs' => array(),
			)
		);

		$this->assertSame( '', $this->switcher( new CurrentLanguage( 'fr' ) )->render() );
		$this->assertSame( 0, $this->style_calls );
		$this->assertSame( 0, $this->script_calls );
	}

	/** A shortcode rendered after wp_head prints its enqueued stylesheet once. */
	public function test_prints_late_styles_once_when_the_switcher_was_rendered(): void {
		$assets = new SwitcherAssets();

		$assets->print_late_styles();
		$assets->enqueue();
		$assets->print_late_styles();
		$assets->print_late_styles();

		$this->assertSame( 1, $this->print_calls );
	}

	/** Stylesheet size remains under the 4 KB budget without external resources. */
	public function test_switcher_css_is_under_4kb(): void {
		$css_path = dirname( __DIR__, 2 ) . '/assets/css/switcher.css';
		$this->assertFileExists( $css_path );
		$size = (int) filesize( $css_path );
		$this->assertLessThanOrEqual( 4096, $size );
	}

	/** Flag and code displays adjust the dropdown list width to their content. */
	public function test_switcher_css_adjusts_list_width_for_flag_and_code_displays(): void {
		$css_path = dirname( __DIR__, 2 ) . '/assets/css/switcher.css';
		$css      = (string) file_get_contents( $css_path );
		$this->assertStringContainsString( '.omtr-switcher--flag .omtr-switcher__dropdown > .omtr-switcher__list', $css );
		$this->assertStringContainsString( '.omtr-switcher--code .omtr-switcher__dropdown > .omtr-switcher__list', $css );
		$this->assertStringContainsString( 'min-width: 100%', $css );
	}

	/** Active language remains visible with hover background in flag-only mode. */
	public function test_switcher_css_highlights_active_language_in_flag_only_mode(): void {
		$css_path = dirname( __DIR__, 2 ) . '/assets/css/switcher.css';
		$css      = (string) file_get_contents( $css_path );
		$this->assertStringContainsString( '.omtr-switcher--flag .is-active', $css );
		$this->assertStringContainsString( 'background: var(--omtr-hover)', $css );
	}

	/**
	 * Build the service under test.
	 *
	 * @param CurrentLanguage $current Current language fixture.
	 */
	private function switcher( CurrentLanguage $current ): Switcher {
		$settings = new Settings();
		return new Switcher( $settings, $current, new UrlTranslator( $settings ), new SwitcherAssets() );
	}
}
