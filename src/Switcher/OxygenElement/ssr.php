<?php
/**
 * Server-side rendering for the Oxygen language-switcher element.
 *
 * @package Omnium\Translate
 */

defined( 'ABSPATH' ) || exit;

// Oxygen's SSR contract provides the camelCase variable below.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

$variant = isset( $propertiesData['variant'] ) && is_string( $propertiesData['variant'] )
	? $propertiesData['variant']
	: 'dropdown';
$display = isset( $propertiesData['display'] ) && is_string( $propertiesData['display'] )
	? $propertiesData['display']
	: 'flag-name';

$switcher = \Omnium\Translate\Core\Plugin::instance()->container()->get( \Omnium\Translate\Switcher\Switcher::class );
// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- Switcher escapes every dynamic URL, attribute and label.
echo $switcher->render(
	array(
		'variant' => $variant,
		'display' => $display,
	)
); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Switcher escapes every dynamic URL, attribute and label.
// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped

// phpcs:enable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
