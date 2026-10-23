<?php
/**
 * Glossary CSV security tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Glossary\GlossaryCsv;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class GlossaryCsvSecurityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $value ): string => trim( strip_tags( $value ) ) );
		Functions\when( 'sanitize_key' )->alias(
			static fn( string $value ): string => preg_replace( '/[^a-z0-9_-]/', '', strtolower( $value ) ) ?? ''
		);
	}

	public function test_export_neutralizes_every_formula_prefix(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->method( 'prepare' )->willReturn( 'prepared' );
		$wpdb->method( 'get_results' )->willReturn(
			array(
				array( 'id' => 1, 'term' => '=cmd', 'target_lang' => 'en', 'translation' => '+SUM(A1)', 'rule' => 'translate' ),
				array( 'id' => 2, 'term' => '-2+3', 'target_lang' => 'en', 'translation' => '@evil', 'rule' => 'translate' ),
				array( 'id' => 3, 'term' => "\tbad", 'target_lang' => 'en', 'translation' => "\rbad", 'rule' => 'translate' ),
			)
		);

		$csv = ( new GlossaryCsv( new GlossaryRepository( $wpdb ) ) )->export();

		$this->assertStringContainsString( "'=cmd", $csv );
		$this->assertStringContainsString( "'+SUM(A1)", $csv );
		$this->assertStringContainsString( "'-2+3", $csv );
		$this->assertStringContainsString( "'@evil", $csv );
		$this->assertStringContainsString( "'\tbad", $csv );
		$this->assertStringContainsString( "'\rbad", $csv );
	}

	public function test_rejects_content_over_one_megabyte(): void {
		$wpdb = new \wpdb();
		$this->expectException( \InvalidArgumentException::class );
		( new GlossaryCsv( new GlossaryRepository( $wpdb ) ) )->import_string( str_repeat( 'a', GlossaryCsv::MAX_BYTES + 1 ) );
	}

	public function test_rejects_invalid_columns(): void {
		$wpdb = new \wpdb();
		$this->expectException( \InvalidArgumentException::class );
		( new GlossaryCsv( new GlossaryRepository( $wpdb ) ) )->import_string( "term,language,value,rule\nfoo,en,bar,translate\n" );
	}

	public function test_import_sanitizes_html_before_storage(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$prepared_args = array();
		$wpdb->expects( $this->once() )->method( 'prepare' )->willReturnCallback(
			static function ( string $sql, mixed ...$args ) use ( &$prepared_args ): string {
				unset( $sql );
				$prepared_args = $args;
				return 'prepared';
			}
		);
		$wpdb->expects( $this->once() )->method( 'query' )->with( 'prepared' );

		$count = ( new GlossaryCsv( new GlossaryRepository( $wpdb ) ) )->import_string(
			"term,target_lang,translation,rule\n<script>alert(1)</script>Safe,en,<img src=x onerror=bad>Good,translate\n"
		);

		$this->assertSame( 1, $count );
		$this->assertContains( 'alert(1)Safe', $prepared_args );
		$this->assertContains( 'Good', $prepared_args );
		$this->assertStringNotContainsString( '<script', implode( '|', array_map( 'strval', $prepared_args ) ) );
	}
}
