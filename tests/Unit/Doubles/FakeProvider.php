<?php
/**
 * Provider test double with canned behavior.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit\Doubles;

use Omnium\Translate\Glossary\Glossary;
use Omnium\Translate\Providers\ProviderException;
use Omnium\Translate\Providers\ProviderInterface;
use Omnium\Translate\Providers\RateLimits;

defined( 'ABSPATH' ) || exit;

/**
 * Returns a canned response or throws a canned exception; records calls.
 */
final class FakeProvider implements ProviderInterface {

	/**
	 * Response to return: hash => text.
	 *
	 * @var array<string, string>
	 */
	public array $response = array();

	/**
	 * Exception to throw instead of a response.
	 */
	public ?ProviderException $exception = null;

	/**
	 * Segments received by translate_batch.
	 *
	 * @var list<array<string, string>>
	 */
	public array $calls = array();

	/**
	 * {@inheritDoc}
	 */
	public function id(): string {
		return 'claude';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label(): string {
		return 'Fake Provider';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return array<string, string>
	 */
	public function models(): array {
		return array( 'fake-fast' => 'Fake Fast (recommended)', 'fake-big' => 'Fake Big' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function limits(): RateLimits {
		return new RateLimits( 50, 20, 10000 );
	}

	public function key_url(): string {
		return 'https://example.test/keys';
	}

	public function region(): string {
		return 'eu';
	}

	public function has_free_tier(): bool {
		return false;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param array<string, string> $segments Hash => text.
	 * @param string                $source   Source.
	 * @param string                $target   Target.
	 * @param Glossary              $glossary Glossary.
	 * @return array<string, string>
	 * @throws ProviderException When the canned exception is set.
	 */
	public function translate_batch( array $segments, string $source, string $target, Glossary $glossary ): array {
		unset( $source, $target, $glossary );
		$this->calls[] = $segments;
		if ( null !== $this->exception ) {
			throw $this->exception;
		}
		return array_intersect_key( $this->response, $segments );
	}

	/**
	 * {@inheritDoc}
	 */
	public function test_key(): \Omnium\Translate\Providers\Result {
		return \Omnium\Translate\Providers\Result::ok();
	}
}
