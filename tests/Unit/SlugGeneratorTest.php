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

	/**
	 * Executed SQL, in call order.
	 *
	 * @var list<string>
	 */
	private array $queries = array();

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

	/**
	 * Slug is built from the title translation even when the raw title never
	 * appears in the rendered HTML (hidden Elementor heading, rewritten title).
	 */
	public function test_generates_ascii_slug_from_translated_title_and_adds_unique_suffix(): void {
		$this->seed_title_translation( 'Titre source', 'Über Café' );
		$this->mock_post( 'a-propos' );

		// get_var: current slug (none), slug conflict ('9' = other post), history conflict (none), save re-read (none).
		$slug = $this->generator( $this->db( array( null, '9', null, null, null ) ) )->generate( 42, 'en' );

		$this->assertSame( 'uber-cafe-2', $slug );
	}

	/**
	 * Without a stored translation of the title, nothing is persisted: the
	 * source post_name is never saved as a fallback slug anymore.
	 */
	public function test_returns_null_without_a_title_translation_and_never_touches_the_db(): void {
		Functions\when( 'get_the_title' )->justReturn( 'Titre source' );
		$this->mock_post( 'a-propos' );

		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->never() )->method( 'query' );

		$this->assertNull( $this->generator( $wpdb )->generate( 42, 'en' ) );
	}

	/** A manually edited slug is never overwritten by a later generation. */
	public function test_manual_slug_is_preserved(): void {
		$this->seed_title_translation( 'Titre source', 'About us' );
		$this->mock_post( 'a-propos' );

		// get_var: existing manual slug, different from the source fallback.
		$wpdb = $this->db( array( 'notre-equipe' ) );

		$this->assertSame( 'notre-equipe', $this->generator( $wpdb )->generate( 42, 'en' ) );
		$this->assertSame( array(), $this->queries );
	}

	/**
	 * The 1.0.x fallback (source post_name stored as the translated slug) is
	 * the only replaceable value: it is swapped for the translated slug and
	 * kept in slug_history for the 301 redirect.
	 */
	public function test_legacy_source_slug_fallback_is_replaced_and_kept_in_history(): void {
		$this->seed_title_translation( 'Titre source', 'About us' );
		$this->mock_post( 'a-propos' );

		// get_var: existing fallback slug, slug conflict, history conflict, save re-read.
		$slug = $this->generator( $this->db( array( 'a-propos', null, null, 'a-propos' ) ) )->generate( 42, 'en' );

		$this->assertSame( 'about-us', $slug );
		$this->assertCount( 3, $this->queries ); // history restore-delete, slug upsert, slug_history insert.
		$this->assertStringContainsString( 'omtr_slug_history', $this->queries[2] );
		$this->assertStringContainsString( 'a-propos', $this->queries[2] );
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

	/**
	 * Stub the scanned post and its translated title.
	 *
	 * @param string $title      Source title.
	 * @param string $translated Stored English translation.
	 */
	private function seed_title_translation( string $title, string $translated ): void {
		Functions\when( 'get_the_title' )->justReturn( $title );
		$hash = SegmentKey::hash( 'fr', SegmentKey::normalize( $title ) );
		$this->translations->seed( $hash, 'en', $translated );
	}

	/**
	 * Stub the scanned post.
	 *
	 * @param string $post_name Source post_name.
	 */
	private function mock_post( string $post_name ): void {
		$post            = new \WP_Post();
		$post->ID        = 42;
		$post->post_name = $post_name;
		Functions\when( 'get_post' )->justReturn( $post );
	}

	/**
	 * wpdb double recording every write, with a scripted get_var sequence.
	 *
	 * @param list<string|null> $vars Consecutive get_var results.
	 */
	private function db( array $vars ): \wpdb {
		$this->queries    = array();
		$test             = $this;
		$wpdb             = $this->createMock( \wpdb::class );
		$wpdb->prefix     = 'wp_';
		$GLOBALS['wpdb']  = $wpdb;
		$wpdb->method( 'prepare' )->willReturnCallback( static fn( string $sql, mixed ...$args ): string => $sql . '|' . implode( '|', array_map( 'strval', $args ) ) );
		$wpdb->method( 'get_var' )->willReturnOnConsecutiveCalls( ...$vars );
		$wpdb->method( 'query' )->willReturnCallback(
			static function ( string $sql ) use ( $test ): int {
				$test->queries[] = $sql;
				return 1;
			}
		);
		return $wpdb;
	}

	private function generator( \wpdb $wpdb ): SlugGenerator {
		return new SlugGenerator( new SlugRepository( $wpdb ), $this->translations, new Settings() );
	}
}
