<?php
/**
 * Indicative model prices for cost estimates.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

defined( 'ABSPATH' ) || exit;

/**
 * Hard-coded price table, in USD per million tokens. Values are indicative and
 * must be re-checked against each provider's official pricing page before every
 * release; update VERIFIED_AT and the source URL when you do.
 *
 * Shape of table():
 *   provider id => model id => array{
 *     input: float,   // USD per 1M input tokens
 *     output: float,  // USD per 1M output tokens
 *     source: string, // official pricing page
 *   }
 *
 * Every model returned by a provider's models() must have an entry here.
 * Temporary promotional prices are never used: estimates rely on the regular
 * price so they never under-state the cost.
 */
final class Pricing {

	public const CURRENCY    = 'USD';
	public const VERIFIED_AT = '2026-10-23';

	/**
	 * Price table, in USD per million tokens (standard tier).
	 *
	 * Verified on 2026-10-23 against each provider's official pricing page
	 * (see the "source" of every entry). Time-limited or tiered rates are
	 * noted inline and MUST be re-checked before every release.
	 *
	 * - Claude Haiku 5.5 is tiered: $0.10 / $0.50 per MTok apply to prompts
	 *   up to 100k tokens, which always covers our batch sizes.
	 * - Gemini 3.8 Flash: $0.75 / $3.75 until 2026-12-31, then $1.50 / $7.50.
	 * - Gemini 4 Argon: $2.00 / $10.00 until 2026-11-30, then $4.00 / $20.00.
	 * - Mistral prices are the EFFECTIVE prices of the EU regional endpoint
	 *   (api.eu.mistral.ai) used by MistralProvider: standard list price x1.1.
	 *
	 * @return array<string, array<string, array{input: float, output: float, source: string}>>
	 */
	public function table(): array {
		$claude  = 'https://platform.claude.com/docs/en/about-claude/pricing';
		$openai  = 'https://platform.openai.com/docs/pricing';
		$mistral = 'https://docs.mistral.ai/inference/pricing (standard, x1.1 EU endpoint) — https://docs.mistral.ai/inference/regional-inference';
		$gemini  = 'https://ai.google.dev/gemini-api/docs/pricing';

		return array(
			'claude'  => array(
				'claude-haiku-5-5'  => array(
					'input'  => 0.10,
					'output' => 0.50,
					'source' => $claude,
				),
				'claude-sonnet-5-5' => array(
					'input'  => 2.00,
					'output' => 10.00,
					'source' => $claude,
				),
				'claude-opus-5-5'   => array(
					'input'  => 4.00,
					'output' => 20.00,
					'source' => $claude,
				),
			),
			'openai'  => array(
				'gpt-6-luna'  => array(
					'input'  => 0.10,
					'output' => 0.50,
					'source' => $openai,
				),
				'gpt-6.1-sol' => array(
					'input'  => 2.00,
					'output' => 10.00,
					'source' => $openai,
				),
				'gpt-6-astra' => array(
					'input'  => 10.00,
					'output' => 50.00,
					'source' => $openai,
				),
			),
			'mistral' => array(
				'mistral-medium-3-5' => array(
					// Standard $1.50 / $7.50 x1.1 (EU regional inference upcharge).
					'input'  => 1.65,
					'output' => 8.25,
					'source' => $mistral,
				),
				'mistral-large-4'    => array(
					// Standard $1.36 / $4.18 x1.1 (EU regional inference upcharge).
					'input'  => 1.496,
					'output' => 4.598,
					'source' => $mistral,
				),
			),
			'gemini'  => array(
				'gemini-3.5-flash-lite' => array(
					'input'  => 0.30,
					'output' => 2.50,
					'source' => $gemini,
				),
				'gemini-3.8-flash'      => array(
					// Regular price. Promotional $0.75 / $3.75 until 2026-12-31 is ignored
					// so estimates never under-state the cost.
					'input'  => 1.50,
					'output' => 7.50,
					'source' => $gemini,
				),
				'gemini-4-argon'        => array(
					// Regular price. Promotional $2.00 / $10.00 until 2026-11-30 is ignored
					// so estimates never under-state the cost.
					'input'  => 4.00,
					'output' => 20.00,
					'source' => $gemini,
				),
			),
		);
	}

	/**
	 * Price entry for a model, or null if unknown.
	 *
	 * @param string $provider Provider id.
	 * @param string $model    Model id.
	 * @return array{input: float, output: float, source: string}|null
	 */
	public function get( string $provider, string $model ): ?array {
		$table = $this->table();
		return $table[ $provider ][ $model ] ?? null;
	}

	/**
	 * Indicative cost in USD, or null if the model has no price entry.
	 *
	 * @param string $provider      Provider id.
	 * @param string $model         Model id.
	 * @param int    $input_tokens  Estimated input tokens.
	 * @param int    $output_tokens Estimated output tokens.
	 */
	public function estimate( string $provider, string $model, int $input_tokens, int $output_tokens ): ?float {
		$price = $this->get( $provider, $model );
		if ( null === $price ) {
			return null;
		}
		return ( $input_tokens * $price['input'] + $output_tokens * $price['output'] ) / 1000000;
	}
}
