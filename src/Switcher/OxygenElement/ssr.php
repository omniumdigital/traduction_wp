<?php
/**
 * Server-side rendering for the Oxygen language-switcher element.
 *
 * @package Omnium\Translate
 */

defined( 'ABSPATH' ) || exit;

$variant = isset( $propertiesData['variant'] ) && is_string( $propertiesData['variant'] )
	? $propertiesData['variant']
	: 'dropdown';
$display = isset( $propertiesData['display'] ) && is_string( $propertiesData['display'] )
	? $propertiesData['display']
	: 'flag-name';

$switcher = \Omnium\Translate\Core\Plugin::instance()->container()->get( \Omnium\Translate\Switcher\Switcher::class );
echo $switcher->render( array( 'variant' => $variant, 'display' => $display ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Switcher escapes every dynamic URL, attribute and label.
