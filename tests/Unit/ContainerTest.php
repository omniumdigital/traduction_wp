<?php
/**
 * Container tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Core\Container;
use Omnium\Translate\Tests\TestCase;

final class ContainerTest extends TestCase {

	public function test_get_returns_same_instance(): void {
		$container = new Container();
		$container->set( \ArrayObject::class, static fn() => new \ArrayObject() );

		$this->assertSame( $container->get( \ArrayObject::class ), $container->get( \ArrayObject::class ) );
	}

	public function test_unknown_service_throws(): void {
		Functions\when( 'esc_html' )->returnArg();
		$this->expectException( \OutOfBoundsException::class );
		( new Container() )->get( \ArrayObject::class );
	}
}
