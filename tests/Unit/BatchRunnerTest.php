<?php
/**
 * BatchRunner tests: per-segment retry instead of all-or-nothing batches.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Omnium\Translate\Glossary\Glossary;
use Omnium\Translate\Providers\BatchRunner;
use Omnium\Translate\Providers\PromptBuilder;
use Omnium\Translate\Providers\ProviderException;
use Omnium\Translate\Providers\ResponseValidator;
use Omnium\Translate\Tests\TestCase;

/**
 * @group security
 */
final class BatchRunnerTest extends TestCase {

	private const ENGLISH = 'Welcome to WordPress. This is your first post. Edit or delete it, then start writing!';

	/** @var list<array<int, string>> */
	private array $sent = array();

	private function runner(): BatchRunner {
		return new BatchRunner( new PromptBuilder(), new ResponseValidator() );
	}

	/**
	 * Fake provider replying with canned bodies, recording each request.
	 *
	 * @param list<string|ProviderException> $replies Replies in order.
	 */
	private function send( array $replies ): callable {
		return function ( array $numbered ) use ( &$replies ): string {
			$this->sent[] = $numbered;
			$reply        = array_shift( $replies );
			if ( $reply instanceof ProviderException ) {
				throw $reply;
			}
			return (string) $reply;
		};
	}

	/** English text on a French site, translated to English: kept, without failing its neighbours. */
	public function test_text_already_in_target_language_does_not_fail_the_batch(): void {
		$segments = array(
			'h1' => 'Bonjour tout le monde',
			'h2' => self::ENGLISH,
			'h3' => 'Mentions légales',
		);
		$result   = $this->runner()->run(
			$segments,
			new Glossary(),
			$this->send(
				array(
					json_encode( array( '1' => 'Hello everyone', '2' => self::ENGLISH, '3' => 'Legal notice' ) ),
					json_encode( array( '1' => self::ENGLISH ) ),
				)
			)
		);

		$this->assertSame( array( 'h1' => 'Hello everyone', 'h3' => 'Legal notice', 'h2' => self::ENGLISH ), $result );
		$this->assertCount( 2, $this->sent );
		// Only the rejected segment is sent again (and paid again).
		$this->assertSame( array( 1 => self::ENGLISH ), $this->sent[1] );
	}

	/** A segment invalid twice is left out; the valid ones are still returned. */
	public function test_segment_invalid_twice_is_dropped_alone(): void {
		$segments = array(
			'h1' => 'Prix : %s',
			'h2' => 'Panier',
		);
		$result   = $this->runner()->run(
			$segments,
			new Glossary(),
			$this->send(
				array(
					json_encode( array( '1' => 'Price', '2' => 'Cart' ) ),
					json_encode( array( '1' => 'Price' ) ),
				)
			)
		);

		$this->assertSame( array( 'h2' => 'Cart' ), $result );
	}

	/** A structurally invalid response twice still fails with the fixed message. */
	public function test_invalid_structure_twice_throws(): void {
		$this->expectException( ProviderException::class );
		$this->expectExceptionMessage( 'The provider returned an invalid response format.' );
		$this->runner()->run( array( 'h1' => 'Panier' ), new Glossary(), $this->send( array( 'not json', '{"9":"x"}' ) ) );
	}

	/** An HTTP error on the retry keeps the translations already obtained. */
	public function test_http_error_on_retry_keeps_first_attempt_results(): void {
		$result = $this->runner()->run(
			array(
				'h1' => 'Panier',
				'h2' => 'Prix : %s',
			),
			new Glossary(),
			$this->send(
				array(
					json_encode( array( '1' => 'Cart', '2' => 'Price' ) ),
					new ProviderException( 'The provider returned a retryable HTTP error.', 429, true ),
				)
			)
		);

		$this->assertSame( array( 'h1' => 'Cart' ), $result );
	}

	/** Identical output is still refused on the first attempt (lazy model guard). */
	public function test_identical_output_is_refused_on_first_attempt(): void {
		$validator = new ResponseValidator();
		$this->assertSame(
			array(),
			$validator->validate_partial( json_encode( array( '1' => self::ENGLISH ) ), array( 1 ), array( 1 => self::ENGLISH ), new Glossary() )
		);
		$this->assertSame(
			array( 1 => self::ENGLISH ),
			$validator->validate_partial( json_encode( array( '1' => self::ENGLISH ) ), array( 1 ), array( 1 => self::ENGLISH ), new Glossary(), true )
		);
	}

	/** Unknown keys still reject the whole response, even in partial mode. */
	public function test_unknown_key_rejects_the_whole_response(): void {
		$this->assertNull( ( new ResponseValidator() )->validate_partial( '{"1":"Cart","7":"<script>"}', array( 1 ), array( 1 => 'Panier' ), new Glossary() ) );
	}
}
