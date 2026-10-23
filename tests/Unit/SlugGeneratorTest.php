<?php
/**
 * Translated slug generation tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\SlugGenerator;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Store\SlugRepository;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;

/** @group security */
final class SlugGeneratorTest extends TestCase {

	private FakeTranslationRepository $translations;

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		Functions\when( 'get_post_status' )->justReturn( 'publish' );
		Functions\when( 'get_post_type' )->justReturn( 'page' );
		Functions\when( 'get_post_type_object' )->justReturn( (object) array( 'public' => true ) );
		Functions\when( 'get_post_types' )->justReturn( array( 'page' => 'page', 'post' => 'post' ) );
		Functions\when( 'get_page_by_path' )->justReturn( null );
		Functions\when( 'term_exists' )->justReturn( false );
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( string $value ): string => strip_tags( $value ) );
		Functions\when( 'remove_accents' )->alias(
			static fn( string $value ): string => strtr( $value, array( 'Ü' => 'U', 'é' => 'e', 'É' => 'E', 'à' => 'a' ) )
		);
		Functions\when( 'sanitize_title' )->alias(
			static fn( string $value ): string => trim( (string) preg_replace( '/[^a-z0-9]+/', '-', strtolower( $value ) ), '-' )
		);
		$this->translations = new FakeTranslationRepository();
	}

	public function test_generates_ascii_slug_from_translated_title_and_adds_unique_suffix(): void {
		$post            = new \WP_Post();
		$post->ID        = 42;
		$post->post_name = 'titre-source';
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_the_title' )->justReturn( 'Titre source' );
		$hash = SegmentKey::hash( 'fr', SegmentKey::normalize( 'Titre source' ) );
		$this->translations->seed( $hash, 'en', 'Über Café' );

		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturnCallback( static fn( string $sql, mixed ...$args ): string => $sql . '|' . implode( '|', array_map( 'strval', $args ) ) );
		$wpdb->expects( $this->exactly( 4 ) )->method( 'get_var' )->willReturnOnConsecutiveCalls( '9', null, null, null );
		$wpdb->expects( $this->exactly( 2 ) )->method( 'query' );

		$this->assertSame( 'uber-cafe-2', $this->generator( $wpdb )->generate( 42, 'en' ) );
	}

	public function test_falls_back_to_source_slug_without_title_translation(): void {
		$post            = new \WP_Post();
		$post->ID        = 42;
		$post->post_name = 'page-source';
		Functions\when( 'get_post' )->justReturn( $post );
		Functions\when( 'get_the_title' )->justReturn( 'Titre source' );

		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturnCallback( static fn( string $sql, mixed ...$args ): string => $sql . '|' . implode( '|', array_map( 'strval', $args ) ) );
		$wpdb->expects( $this->exactly( 3 ) )->method( 'get_var' )->willReturn( null );
		$wpdb->expects( $this->exactly( 2 ) )->method( 'query' );

		$this->assertSame( 'page-source', $this->generator( $wpdb )->generate( 42, 'en' ) );
	}

	/** @dataProvider maliciousSlugs */
	public function test_refuses_malicious_manual_slugs( string $candidate ): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->never() )->method( 'query' );

		$this->assertNull( $this->generator( $wpdb )->set( 42, 'en', $candidate ) );
	}

	/** @return iterable<string, array{string}> */
	public static function maliciousSlugs(): iterable {
		yield 'traversal' => array( '../admin' );
		yield 'markup' => array( '<script>alert(1)</script>' );
		yield 'absolute URL' => array( 'https://evil.test/x' );
	}

	private function generator( \wpdb $wpdb ): SlugGenerator {
		return new SlugGenerator( new SlugRepository( $wpdb ), $this->translations, new Settings() );
	}
}
