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
 */
final class Pricing {

	public const CURRENCY    = 'USD';
	public const VERIFIED_AT = '';

	/**
	 * Price table. Filled per provider (jalon 3).
	 *
	 * @return array<string, array<string, array{input: float, output: float, source: string}>>
	 */
	public function table(): array {
		return array();
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
