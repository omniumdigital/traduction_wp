<?php
/**
 * HTML segment extractor tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Render\HtmlSegmentExtractor;
use Omnium\Translate\Security\PiiFilter;
use Omnium\Translate\Store\SegmentKey;
use Omnium\Translate\Tests\TestCase;

final class HtmlSegmentExtractorTest extends TestCase {

	public function test_extracts_text_attributes_and_meta_while_honoring_exclusions(): void {
		$extractor = new HtmlSegmentExtractor( new PiiFilter() );
		$html      = '<html><head><title>Accueil</title><meta name="description" content="Bienvenue ici"></head><body>'
			. '<p>Bonjour monde</p><img alt="Belle image"><input value="Secret" placeholder="Votre nom">'
			. '<textarea placeholder="Votre message">Valeur privée</textarea><pre>Code privé</pre><div class="notranslate">Ignorer</div>'
			. '<span data-omtr-skip>Masqué</span><p>hello@example.com</p></body></html>';

		$segments = $extractor->extract( $html, 'fr' );

		$this->assertSame( 'Bonjour monde', $segments[ SegmentKey::hash( 'fr', 'Bonjour monde' ) ] );
		$this->assertContains( 'Accueil', $segments );
		$this->assertContains( 'Bienvenue ici', $segments );
		$this->assertContains( 'Belle image', $segments );
		$this->assertContains( 'Votre nom', $segments );
		$this->assertContains( 'Votre message', $segments );
		$this->assertNotContains( 'Secret', $segments );
		$this->assertNotContains( 'Valeur privée', $segments );
		$this->assertNotContains( 'Code privé', $segments );
		$this->assertNotContains( 'Ignorer', $segments );
		$this->assertNotContains( 'Masqué', $segments );
		$this->assertNotContains( 'hello@example.com', $segments );
	}
}
