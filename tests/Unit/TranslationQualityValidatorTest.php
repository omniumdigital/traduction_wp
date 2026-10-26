<?php
/**
 * Translation quality validator tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Glossary\Glossary;
use Omnium\Translate\Providers\TranslationQualityValidator;
use Omnium\Translate\Tests\TestCase;

/** @group security */
final class TranslationQualityValidatorTest extends TestCase {

	private TranslationQualityValidator $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->validator = new TranslationQualityValidator();
	}

	public function test_accepts_reordered_numbered_placeholders(): void {
		$this->assertTrue(
			$this->validator->is_valid(
				'%1$s ordered %2$d items',
				'%2$d articles commandés par %1$s',
				new Glossary()
			)
		);
	}

	public function test_rejects_missing_or_added_placeholders(): void {
		$this->assertFalse( $this->validator->is_valid( 'Price: %s', 'Prix', new Glossary() ) );
		$this->assertFalse( $this->validator->is_valid( 'Price', 'Prix : %s', new Glossary() ) );
	}

	public function test_rejects_changed_inline_html_structure(): void {
		$this->assertTrue( $this->validator->is_valid( '<strong>Hello</strong>', '<strong>Bonjour</strong>', new Glossary() ) );
		$this->assertFalse( $this->validator->is_valid( '<strong>Hello</strong>', '<em>Bonjour</em>', new Glossary() ) );
		$this->assertFalse( $this->validator->is_valid( '<a href="/shop">Shop</a>', '<a href="https://evil.test">Boutique</a>', new Glossary() ) );
		$this->assertFalse( $this->validator->is_valid( 'Hello', '<script>alert(1)</script>Bonjour', new Glossary() ) );
	}

	public function test_enforces_keep_and_translate_glossary_rules(): void {
		$glossary = new Glossary( array( 'basket' => 'panier' ), array( 'Omnium' ) );
		$this->assertTrue( $this->validator->is_valid( 'Omnium basket', 'panier Omnium', $glossary ) );
		$this->assertFalse( $this->validator->is_valid( 'Omnium basket', 'corbeille Omnium', $glossary ) );
		$this->assertFalse( $this->validator->is_valid( 'Omnium basket', 'panier Société', $glossary ) );
		$this->assertTrue( $this->validator->is_valid( 'An article', 'Un article', new Glossary( array( 'art' => 'œuvre' ) ) ) );
	}

	public function test_rejects_long_unchanged_source_and_extreme_length(): void {
		$source = 'This complete source sentence contains enough words to require a real translation.';
		$this->assertFalse( $this->validator->is_valid( $source, $source, new Glossary() ) );
		$this->assertFalse( $this->validator->is_valid( $source, 'Oui', new Glossary() ) );
	}
}
