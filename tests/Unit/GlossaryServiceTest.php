<?php
/**
 * Glossary service tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Glossary\GlossaryService;
use Omnium\Translate\Tests\TestCase;

final class GlossaryServiceTest extends TestCase {

	public function test_builds_language_rules_with_tone_and_context(): void {
		Functions\when( 'get_option' )->alias(
			static fn( string $key, mixed $default = null ): mixed => Options::SETTINGS === $key
				? array( 'site_tone' => 'Warm', 'site_context' => 'Bakery' )
				: $default
		);

		$wpdb         = $this->createMock( \wpdb::class );
		$wpdb->prefix = 'wp_';
		$GLOBALS['wpdb'] = $wpdb;
		$wpdb->expects( $this->once() )->method( 'prepare' )->willReturn( 'prepared' );
		$wpdb->expects( $this->once() )->method( 'get_results' )->with( 'prepared', ARRAY_A )->willReturn(
			array(
				array( 'id' => '1', 'term' => 'baguette', 'target_lang' => 'en', 'translation' => 'French bread', 'rule' => 'translate' ),
				array( 'id' => '2', 'term' => 'Omnium', 'target_lang' => 'en', 'translation' => '', 'rule' => 'keep' ),
			)
		);

		$glossary = ( new GlossaryService( new GlossaryRepository( $wpdb ), new Settings() ) )->for_language( 'en' );

		$this->assertSame( array( 'baguette' => 'French bread' ), $glossary->translate );
		$this->assertSame( array( 'Omnium' ), $glossary->keep );
		$this->assertSame( 'Warm', $glossary->tone );
		$this->assertSame( 'Bakery', $glossary->context );
	}
}
