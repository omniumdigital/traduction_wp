<?php
/**
 * Translation workload estimator.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Queue;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Render\SegmentExtractorInterface;
use Omnium\Translate\Store\TranslationRepositoryInterface;

defined( 'ABSPATH' ) || exit;

/**
 * Estimates only untranslated segment/language pairs.
 *
 * Token heuristic: natural-language text averages about 0.75 words per token,
 * therefore both input and output are estimated at ceil(words * 4 / 3).
 * This deliberately remains indicative; provider-side prompt overhead varies.
 */
final class Estimator {
	/**
	 * Constructor.
	 *
	 * @param Settings                       $settings Settings.
	 * @param PublicContent                  $content Public content service.
	 * @param SegmentExtractorInterface      $extractor Segment extractor.
	 * @param TranslationRepositoryInterface $translations Translation repository.
	 * @param WordCap                        $word_cap Word counter and cap.
	 * @param ProviderRegistry               $providers Provider registry.
	 * @param Pricing                        $pricing Price table.
	 */
	public function __construct(
		private Settings $settings,
		private PublicContent $content,
		private SegmentExtractorInterface $extractor,
		private TranslationRepositoryInterface $translations,
		private WordCap $word_cap,
		private ProviderRegistry $providers,
		private Pricing $pricing
	) {}

	/**
	 * Estimate a set of public pages.
	 *
	 * @param list<array{post_id: int, url: string}> $targets   Public targets.
	 * @param list<string>|null                      $languages Target languages, configured languages by default.
	 * @return array{pages: int, segments: int, words: int, input_tokens: int, output_tokens: int, provider: string, model: string, currency: string, cost: float|null, failures: int}
	 */
	public function estimate( array $targets, ?array $languages = null ): array {
		$languages = null === $languages ? $this->settings->target_langs() : array_values( array_unique( array_map( 'sanitize_key', $languages ) ) );
		$segments  = 0;
		$words     = 0;
		$pages     = 0;
		$failures  = 0;
		foreach ( $targets as $target ) {
			$html = $this->content->fetch( $target['url'] );
			if ( null === $html ) {
				++$failures;
				continue;
			}
			++$pages;
			$found = $this->extractor->extract( $html, $this->settings->source_lang() );
			foreach ( $languages as $language ) {
				$existing  = $this->translations->find_many( array_keys( $found ), $language );
				$missing   = array_diff_key( $found, $existing );
				$segments += count( $missing );
				$words    += $this->word_cap->count_words( $missing );
			}
		}
		$provider_id = (string) $this->settings->get( 'provider' );
		$model       = (string) $this->settings->get( 'model' );
		$provider    = $this->providers->get( $provider_id );
		if ( '' === $model && null !== $provider ) {
			$first = array_key_first( $provider->models() );
			$model = is_string( $first ) ? $first : '';
		}
		$input_tokens  = (int) ceil( $words * 4 / 3 );
		$output_tokens = (int) ceil( $words * 4 / 3 );
		return array(
			'pages'         => $pages,
			'segments'      => $segments,
			'words'         => $words,
			'input_tokens'  => $input_tokens,
			'output_tokens' => $output_tokens,
			'provider'      => $provider_id,
			'model'         => $model,
			'currency'      => Pricing::CURRENCY,
			'cost'          => $this->pricing->estimate( $provider_id, $model, $input_tokens, $output_tokens ),
			'failures'      => $failures,
		);
	}
}
