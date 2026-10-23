<?php
/**
 * Pricing table tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\Claude\ClaudeProvider;
use Omnium\Translate\Providers\Gemini\GeminiProvider;
use Omnium\Translate\Providers\Mistral\MistralProvider;
use Omnium\Translate\Providers\OpenAI\OpenAIProvider;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Providers\PromptBuilder;
use Omnium\Translate\Providers\ProviderInterface;
use Omnium\Translate\Providers\ResponseValidator;
use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Security\Crypto;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;

final class PricingTest extends TestCase {

	private Pricing $pricing;

	/**
	 * Every registered provider, built like ProvidersModule does.
	 *
	 * @return array<string, ProviderInterface>
	 */
	private function providers(): array {
		$options = new OptionStore();
		$options->install();

		$keys      = new ApiKeyStore( new Crypto() );
		$settings  = new Settings();
		$prompts   = new PromptBuilder();
		$validator = new ResponseValidator();

		return array(
			'claude'  => new ClaudeProvider( $keys, $settings, $prompts, $validator ),
			'openai'  => new OpenAIProvider( $keys, $settings, $prompts, $validator ),
			'mistral' => new MistralProvider( $keys, $settings, $prompts, $validator ),
			'gemini'  => new GeminiProvider( $keys, $settings, $prompts, $validator ),
		);
	}

	protected function setUp(): void {
		parent::setUp();
		$this->pricing = new Pricing();
	}

	/**
	 * Provide provider ids.
	 *
	 * @return list<array{string}>
	 */
	public static function provider_ids(): array {
		return array(
			array( 'claude' ),
			array( 'openai' ),
			array( 'mistral' ),
			array( 'gemini' ),
		);
	}

	/**
	 * Every model exposed by a provider must have a price entry with a source.
	 *
	 * @dataProvider provider_ids
	 *
	 * @param string $provider_id Provider id.
	 */
	public function test_every_model_has_a_price_entry_with_a_source( string $provider_id ): void {
		$provider = $this->providers()[ $provider_id ];

		$this->assertNotEmpty( $provider->models() );
		foreach ( array_keys( $provider->models() ) as $model ) {
			$price = $this->pricing->get( $provider_id, (string) $model );
			$this->assertNotNull(
				$price,
				"Missing price entry for {$provider_id}/{$model}."
			);
			$this->assertGreaterThan( 0, $price['input'] );
			$this->assertGreaterThan( 0, $price['output'] );
			$this->assertStringStartsWith( 'https://', $price['source'] );
		}
	}

	public function test_verified_at_is_set_to_a_real_date(): void {
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', Pricing::VERIFIED_AT );
		$this->assertNotSame( '', Pricing::VERIFIED_AT );
	}

	public function test_unknown_model_returns_null(): void {
		$this->assertNull( $this->pricing->get( 'claude', 'claude-unknown' ) );
		$this->assertNull( $this->pricing->get( 'deepseek', 'whatever' ) );
	}

	public function test_estimate_computes_indicative_cost(): void {
		// 1M input + 1M output tokens of Claude Haiku 5.5: $0.10 + $0.50.
		$this->assertSame( 0.6, $this->pricing->estimate( 'claude', 'claude-haiku-5-5', 1000000, 1000000 ) );
		// 100k input + 100k output tokens: one tenth of that.
		$this->assertSame( 0.06, $this->pricing->estimate( 'claude', 'claude-haiku-5-5', 100000, 100000 ) );
		// Unknown model: no estimate.
		$this->assertNull( $this->pricing->estimate( 'claude', 'claude-unknown', 100, 100 ) );
	}

	public function test_mistral_prices_reflect_the_eu_endpoint_upcharge(): void {
		// EU regional endpoint: standard list price x1.1 (regional-inference doc).
		$medium = $this->pricing->get( 'mistral', 'mistral-medium-3-5' );
		$this->assertSame( 1.65, $medium['input'] );
		$this->assertSame( 8.25, $medium['output'] );

		$large = $this->pricing->get( 'mistral', 'mistral-large-4' );
		$this->assertEqualsWithDelta( 1.496, $large['input'], 0.0001 );
		$this->assertEqualsWithDelta( 4.598, $large['output'], 0.0001 );

		$this->assertStringContainsString( 'regional-inference', $medium['source'] );
	}
}
