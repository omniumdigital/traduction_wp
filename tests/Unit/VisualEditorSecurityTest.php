<?php
/** Visual editor authorization tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Render\VisualEditor;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\TranslationRepositoryInterface;
use Omnium\Translate\Tests\TestCase;

/** @group security */
final class VisualEditorSecurityTest extends TestCase {
	protected function tearDown(): void {
		unset( $_GET['omtr_edit'] );
		parent::tearDown();
	}

	/** Anonymous visitors and users without the capability cannot activate markup. */
	public function test_query_parameter_does_not_authorize_visual_editor(): void {
		$_GET['omtr_edit'] = '1';
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ), 'visual_editor' => true ) );
		Functions\when( 'wp_unslash' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( false );
		$language = new CurrentLanguage( 'fr' );
		$language->set( 'en' );
		$editor = new VisualEditor( new Settings(), $language );
		$this->assertFalse( $editor->active() );
	}

	/** The option is off by default even for an authorized translator. */
	public function test_default_setting_prevents_visual_editor(): void {
		$_GET['omtr_edit'] = '1';
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		Functions\when( 'wp_unslash' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'current_user_can' )->justReturn( true );
		$language = new CurrentLanguage( 'fr' );
		$language->set( 'en' );
		$this->assertFalse( ( new VisualEditor( new Settings(), $language ) )->active() );
	}

	/** Render editor harness: a translated paragraph with an authorized-or-not editor. */
	private function render_with_editor( bool $authorized ): string {
		$_GET['omtr_edit'] = '1';
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ), 'visual_editor' => true ) );
		Functions\when( 'wp_unslash' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => $value );
		Functions\when( 'wp_cache_get' )->justReturn( false );
		if ( $authorized ) {
			// Editing responses are private: nothing may reach the object cache.
			Functions\expect( 'wp_cache_set' )->never();
		} else {
			Functions\when( 'wp_cache_set' )->justReturn( true );
		}
		Functions\when( 'is_admin' )->justReturn( false );
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'current_user_can' )->justReturn( $authorized );
		Functions\when( 'home_url' )->alias( static fn( string $path = '' ): string => 'https://example.test' . $path );
		Functions\when( 'wp_parse_url' )->alias( static fn( string $url ): array|false => parse_url( $url ) );
		Functions\when( 'esc_attr' )->alias( static fn( string $text ): string => htmlspecialchars( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		Functions\when( 'wp_kses' )->alias( static fn( string $text ): string => strip_tags( $text, '<strong><em><b><i><br><span><a>' ) );

		$hash        = SegmentKey::hash( 'fr', 'Bonjour' );
		$wpdb        = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$wpdb->method( 'get_results' )->willReturn( array( array( 'hash' => $hash, 'id' => '5' ) ) );
		$GLOBALS['wpdb'] = $wpdb;

		$extractor = $this->createMock( SegmentExtractorInterface::class );
		$extractor->method( 'extract' )->willReturn( array( $hash => 'Bonjour' ) );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$repository->method( 'find_many' )->willReturn( array( $hash => 'Hello' ) );

		$language = new CurrentLanguage( 'fr' );
		$language->set( 'en' );
		$translator = new Translator( $language, $extractor, $repository, new UrlTranslator( new Settings() ), new VisualEditor( new Settings(), $language ) );
		return $translator->translate( '<!doctype html><html lang="fr"><body><p>Bonjour</p></body></html>' );
	}

	/** ?omtr_edit=1 alone never leaks segment markup to an unauthorized visitor. */
	public function test_unauthorized_request_renders_without_segment_markup(): void {
		$output = $this->render_with_editor( false );
		$this->assertStringContainsString( 'Hello', $output );
		$this->assertStringNotContainsString( 'data-omtr-segment', $output );
	}

	/** Authorized editors get clickable ids without a public cache write. */
	public function test_authorized_request_marks_segments_without_cache_write(): void {
		$output = $this->render_with_editor( true );
		$this->assertStringContainsString( 'data-omtr-segment="5"', $output );
		$this->assertStringContainsString( 'Hello', $output );
	}
}

