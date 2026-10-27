<?php
/**
 * Shared batch retry policy for AI providers.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

use Omnium\Translate\Glossary\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Sends a batch, keeps every valid translation and retries only the rest.
 *
 * Two attempts at most (CLAUDE.md section 5). A rejected segment no longer
 * fails its whole batch: only that segment is sent again. On the second
 * attempt a translation identical to its source is accepted, because the
 * model confirmed the text needs no translation (e.g. an English sentence
 * on a French site translated to English).
 */
final class BatchRunner {

	private const MAX_ATTEMPTS = 2;

	/**
	 * Constructor.
	 *
	 * @param PromptBuilder     $prompts   Shared prompt builder.
	 * @param ResponseValidator $validator Shared response validator.
	 */
	public function __construct(
		private PromptBuilder $prompts,
		private ResponseValidator $validator
	) {}

	/**
	 * Translate a batch with per-segment retry.
	 *
	 * @param array<string, string>                $segments Segment hash => source text.
	 * @param Glossary                             $glossary Glossary rules to enforce.
	 * @param callable(array<int, string>): string $send     Sends numbered segments, returns the model text.
	 * @return array<string, string> Segment hash => raw translated text (may be partial).
	 * @throws ProviderException On HTTP errors, or when no valid translation was obtained.
	 */
	public function run( array $segments, Glossary $glossary, callable $send ): array {
		$translated = array();
		$pending    = $segments;

		for ( $attempt = 1; $attempt <= self::MAX_ATTEMPTS && array() !== $pending; ++$attempt ) {
			$numbered = $this->prompts->number( $pending );
			$hashes   = array_keys( $pending );
			try {
				$text = $send( $numbered );
			} catch ( ProviderException $e ) {
				// Keep what the first attempt already paid for; the rest is marked failed.
				if ( array() !== $translated ) {
					return $translated;
				}
				throw $e;
			}

			$validated = $this->validator->validate_partial( $text, array_keys( $numbered ), $numbered, $glossary, self::MAX_ATTEMPTS === $attempt );
			if ( null === $validated ) {
				continue;
			}
			foreach ( $validated as $number => $value ) {
				$hash                = $hashes[ $number - 1 ];
				$translated[ $hash ] = $value;
				unset( $pending[ $hash ] );
			}
		}

		if ( array() === $translated ) {
			throw new ProviderException( 'The provider returned an invalid response format.', 0, false );
		}
		return $translated;
	}
}
