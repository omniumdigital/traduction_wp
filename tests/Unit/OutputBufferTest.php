<?php
/**
 * Output buffer tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Render\HtmlSegmentExtractor;
use Omnium\Translate\Render\OutputBuffer;
use Omnium\Translate\Render\Translator;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\RequestGuard;
use Omnium\Translate\Router\UrlTranslator;
use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Store\TranslationRepositoryInterface;
use Omnium\Translate\Tests\TestCase;

final class OutputBufferTest extends TestCase {

	public function test_process_leaves_source_language_output_unchanged(): void {
		Functions\when( 'get_option' )->justReturn( array( 'source_lang' => 'fr', 'target_langs' => array( 'en' ) ) );
		$current    = new CurrentLanguage( 'fr' );
		$repository = $this->createMock( TranslationRepositoryInterface::class );
		$translator = new Translator(
			$current,
			new HtmlSegmentExtractor( new PiiFilter() ),
			$repository,
			new UrlTranslator( new Settings() )
		);
		$buffer = new OutputBuffer( $current, new RequestGuard(), $translator );

		$this->assertSame( '<p>Bonjour</p>', $buffer->process( '<p>Bonjour</p>' ) );
	}
}
