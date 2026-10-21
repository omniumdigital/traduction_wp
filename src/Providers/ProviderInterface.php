<?php
/**
 * AI provider contract.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Providers;

use Omnium\Translate\Glossary\Glossary;

defined( 'ABSPATH' ) || exit;

/**
 * Implementations call a hard-coded HTTPS endpoint via wp_remote_post()
 * (timeout 60 s, sslverify on). Never called during a visitor request.
 */
interface ProviderInterface {

	/**
	 * Stable provider id (e.g. "claude"), used as option key and in the translations table.
	 */
	public function id(): string;

	/**
	 * Human readable name.
	 */
	public function label(): string;

	/**
	 * Translate a batch of segments.
	 *
	 * The response is untrusted: implementations must validate the strict JSON
	 * shape and return only ids that were sent. Missing ids = not translated.
	 *
	 * @param array<string, string> $segments Segment hash => source text.
	 * @param string                $source   Source language code.
	 * @param string                $target   Target language code.
	 * @param Glossary              $glossary Glossary, tone and site context.
	 * @return array<string, string> Segment hash => raw translated text (sanitized later by the caller).
	 * @throws ProviderException On HTTP, quota or format errors.
	 */
	public function translate_batch( array $segments, string $source, string $target, Glossary $glossary ): array;

	/**
	 * Check the configured key with a minimal request.
	 */
	public function test_key(): Result;

	/**
	 * Available models, model id => label. The first entry is the recommended default.
	 *
	 * @return array<string, string>
	 */
	public function models(): array;

	/**
	 * Rate limits to respect when batching.
	 */
	public function limits(): RateLimits;
}
