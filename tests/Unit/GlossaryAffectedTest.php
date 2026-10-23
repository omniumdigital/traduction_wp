<?php
/**
 * Affected glossary translations tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Tests\TestCase;

final class GlossaryAffectedTest extends TestCase {

	public function test_only_queries_auto_translations_for_rule_language_and_term(): void {
		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$prepared_queries = array();
		$prepared_args    = array();
		$wpdb->expects( $this->exactly( 2 ) )->method( 'prepare' )->willReturnCallback(
			static function ( string $sql, mixed ...$args ) use ( &$prepared_queries, &$prepared_args ): string {
				$prepared_queries[] = $sql;
				$prepared_args[]    = $args;
				return 'prepared-' . count( $prepared_queries );
			}
		);
		$wpdb->expects( $this->exactly( 2 ) )->method( 'get_results' )->willReturnOnConsecutiveCalls(
			array( array( 'id' => 8, 'term' => '100%', 'target_lang' => 'en', 'translation' => 'all', 'rule' => 'translate' ) ),
			array( array( 'segment_id' => 4, 'source_text' => '100% cotton', 'translation_id' => 9, 'translation' => 'cotton', 'target_lang' => 'en', 'status' => 'auto' ) )
		);
		$wpdb->method( 'esc_like' )->willReturn( '100\\%' );

		$affected = ( new GlossaryRepository( $wpdb ) )->affected( 8 );

		$this->assertCount( 1, $affected );
		$this->assertSame( 'auto', $affected[0]['status'] );
		$this->assertStringContainsString( 't.status = %s', $prepared_queries[1] );
		$this->assertContains( 'auto', $prepared_args[1] );
		$this->assertContains( '%100\\%%', $prepared_args[1] );
		$this->assertNotContains( 'edited', $prepared_args[1] );
		$this->assertNotContains( 'locked', $prepared_args[1] );
	}
}
