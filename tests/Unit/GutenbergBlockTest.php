<?php
/**
 * Gutenberg switcher block tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Switcher\GutenbergBlock;
use Omnium\Translate\Switcher\Switcher;
use Omnium\Translate\Switcher\SwitcherAssets;
use Omnium\Translate\Tests\TestCase;

/**
 * Verifies metadata registration and safe dynamic rendering.
 *
 * @group security
 */
final class GutenbergBlockTest extends TestCase {

	/**
	 * Registered metadata directory.
	 *
	 * @var string
	 */
	private string $registered_path = '';

	/**
	 * Registered block arguments.
	 *
	 * @var array<string, mixed>
	 */
	private array $registered_args = array();

	/**
	 * Block under test.
	 *
	 * @var GutenbergBlock
	 */
	private GutenbergBlock $block;

	/** Set up WordPress function doubles. */
	protected function setUp(): void {
		parent::setUp();
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
		Functions\when( 'register_block_type' )->alias(
			function ( string $path, array $args ): bool {
				$this->registered_path = $path;
				$this->registered_args = $args;
				return true;
			}
		);

		$settings      = new Settings();
		$switcher      = new Switcher( $settings, new CurrentLanguage( 'fr' ), new UrlTranslator( $settings ), new SwitcherAssets() );
		$this->block   = new GutenbergBlock( $switcher );
		$GLOBALS['wp'] = (object) array( 'request' => 'article' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Controlled request fixture.
	}

	/** Remove the request fixture. */
	protected function tearDown(): void {
		unset( $GLOBALS['wp'] );
		parent::tearDown();
	}

	/** The block uses metadata and exposes only its renderer callback. */
	public function test_registers_block_metadata_and_callback(): void {
		Functions\when( 'generate_block_asset_handle' )->justReturn( 'omnium-translate-switcher-editor-script' );
		Functions\expect( 'wp_set_script_translations' )->once()->with( 'omnium-translate-switcher-editor-script', 'omnium-translate', OMTR_DIR . 'languages' );
		$this->block->register();

		$this->assertSame( OMTR_DIR . 'assets/blocks/switcher', $this->registered_path );
		$this->assertSame( array( $this->block, 'render' ), $this->registered_args['render_callback'] ?? null );
	}

	/** Valid attributes are forwarded to the shared renderer. */
	public function test_renders_selected_variant_and_display(): void {
		$html = $this->block->render(
			array(
				'variant' => 'inline',
				'display' => 'code',
			)
		);

		$this->assertStringContainsString( 'omtr-switcher--inline', $html );
		$this->assertStringContainsString( 'omtr-switcher--code', $html );
		$this->assertStringContainsString( 'href="https://example.test/en/article"', $html );
	}

	/** Invalid persisted attributes fall back to safe defaults. */
	public function test_rejects_invalid_persisted_attributes(): void {
		$html = $this->block->render(
			array(
				'variant' => 'evil\"><script>',
				'display' => 'evil',
			)
		);

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( 'omtr-switcher--dropdown', $html );
		$this->assertStringContainsString( 'omtr-switcher--flag-name', $html );
	}
}
