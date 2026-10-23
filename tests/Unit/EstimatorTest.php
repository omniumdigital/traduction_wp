<?php
/** Estimator tests. @package Omnium\Translate */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\Estimator;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\FakeProvider;
use Omnium\Translate\Tests\Unit\Doubles\FakeSegmentExtractor;
use Omnium\Translate\Tests\Unit\Doubles\FakeTranslationRepository;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

final class EstimatorTest extends TestCase {
	public function test_counts_only_untranslated_language_pairs_and_unknown_price_is_not_an_error(): void {
		$options = new OptionStore();
		$options->install();
		$options->data[ Options::SETTINGS ] = array(
			'source_lang'  => 'fr',
			'target_langs' => array( 'en', 'es' ),
			'provider'     => 'fake',
			'model'        => 'fake-model',
		);
		Functions\when( 'home_url' )->justReturn( 'https://example.test' );
		Functions\when( 'wp_parse_url' )->alias( 'parse_url' );
		Functions\when( 'add_filter' )->justReturn( true );
		Functions\when( 'remove_filter' )->justReturn( true );
		Functions\when( 'is_wp_error' )->justReturn( false );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( 200 );
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( '<p>Bonjour le monde</p>' );
		Functions\when( 'wp_safe_remote_get' )->justReturn( array( 'response' => array( 'code' => 200 ), 'body' => '<p>Bonjour le monde</p>' ) );
		Functions\when( 'sanitize_key' )->alias( static fn( string $value ): string => strtolower( $value ) );

		$extractor           = new FakeSegmentExtractor();
		$extractor->segments = array( 'a' => 'Bonjour', 'b' => 'le monde' );
		$translations        = new FakeTranslationRepository();
		$translations->seed( 'a', 'en', 'Hello' );
		$registry = new ProviderRegistry();
		$registry->add( new FakeProvider() );
		$settings  = new Settings();
		$estimator = new Estimator( $settings, new PublicContent(), $extractor, $translations, new WordCap( $settings ), $registry, new Pricing() );

		$result = $estimator->estimate( array( array( 'post_id' => 1, 'url' => 'https://example.test/page/?x=1' ) ) );

		$this->assertSame( 3, $result['segments'] );
		$this->assertSame( 5, $result['words'] );
		$this->assertSame( 7, $result['input_tokens'] );
		$this->assertNull( $result['cost'] );
	}
}
