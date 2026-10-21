<?php
/**
 * Base test case.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests;

use Brain\Monkey;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Sets up Brain Monkey around each test.
 */
abstract class TestCase extends BaseTestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
