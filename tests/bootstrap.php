<?php
/**
 * PHPUnit bootstrap: unit tests run without WordPress, using Brain Monkey.
 *
 * @package Omnium\Translate
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'AUTH_KEY', 'test-auth-key-not-a-secret' );
define( 'SECURE_AUTH_SALT', 'test-salt-not-a-secret' );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
