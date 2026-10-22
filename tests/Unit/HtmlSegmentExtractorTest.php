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

	public function test_ignores_constant_seo_meta_and_link_titles(): void {
		$extractor = new HtmlSegmentExtractor( new PiiFilter() );
		$html      = '<html><head>'
			. '<link rel="alternate" type="application/rss+xml" title="Site Feed" href="https://example.test/feed/">'
			. '<meta name="description" content="Bienvenue sur notre site">'
			. '<meta property="og:locale" content="en_US">'
			. '<meta property="og:type" content="article">'
			. '<meta property="og:title" content="Mon titre de page">'
			. '<meta property="og:description" content="Description pour les réseaux">'
			. '<meta property="og:site_name" content="Mon site">'
			. '<meta property="og:url" content="https://example.test/page/">'
			. '<meta name="twitter:card" content="summary_large_image">'
			. '<meta name="twitter:title" content="Mon titre pour Twitter">'
			. '<meta name="twitter:description" content="Description pour Twitter">'
			. '</head><body><p>Corps de page</p></body></html>';

		$segments = $extractor->extract( $html, 'fr' );

		$this->assertContains( 'Bienvenue sur notre site', $segments );
		$this->assertContains( 'Mon titre de page', $segments );
		$this->assertContains( 'Description pour les réseaux', $segments );
		$this->assertContains( 'Mon site', $segments );
		$this->assertContains( 'Mon titre pour Twitter', $segments );
		$this->assertContains( 'Description pour Twitter', $segments );
		$this->assertContains( 'Corps de page', $segments );
		$this->assertNotContains( 'en_US', $segments );
		$this->assertNotContains( 'article', $segments );
		$this->assertNotContains( 'https://example.test/page/', $segments );
		$this->assertNotContains( 'summary_large_image', $segments );
		$this->assertNotContains( 'Site Feed', $segments );
	}
}
