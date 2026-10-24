<?php
/**
 * Minimal Oxygen 6 / Element Studio runtime doubles.
 *
 * @package Omnium\Translate
 */

namespace Breakdance\Elements;

if ( ! class_exists( Element::class ) ) {
	/** Base class used to inspect the third-party element definition. */
	abstract class Element {}
}

/** @return array<string, mixed> */
function c( string $slug, string $label, array $children = array(), array $options = array() ): array {
	return array(
		'slug'     => $slug,
		'label'    => $label,
		'children' => $children,
		'options'  => $options,
	);
}

namespace Breakdance\ElementStudio;

if ( ! isset( $GLOBALS['omtr_oxygen_elements'] ) ) {
	$GLOBALS['omtr_oxygen_elements'] = array();
}
if ( ! isset( $GLOBALS['omtr_oxygen_locations'] ) ) {
	$GLOBALS['omtr_oxygen_locations'] = array();
}

function registerElementForEditing( string $slug, string $directory_path ): void {
	$GLOBALS['omtr_oxygen_elements'][] = array( 'slug' => $slug, 'directoryPath' => $directory_path );
}

function registerSaveLocation( string $directory_path, string $namespace, string $type, string $label, bool $advanced, bool $excluded = false ): void {
	$GLOBALS['omtr_oxygen_locations'][] = compact( 'directory_path', 'namespace', 'type', 'label', 'advanced', 'excluded' );
}

namespace Breakdance\Util;

function getDirectoryPathRelativeToPluginFolder( string $directory ): string {
	return $directory;
}
