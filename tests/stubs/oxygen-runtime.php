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

/**
 * Mirrors the Oxygen 6.1/6.2 signature (plugin/elements/elements-helpers.php):
 * $enable_media_queries and $enable_hover are required.
 *
 * @param array<int, mixed> $children
 * @param mixed             $options
 * @param array<int, mixed> $keywords
 * @param array<string, mixed>|null $dynamic
 * @return array<string, mixed>
 */
function c( $slug, $label, $children, $options, $enable_media_queries, $enable_hover, $keywords = array(), $dynamic = null ): array {
	$control = array(
		'slug'               => $slug,
		'label'              => $label,
		'options'            => $options,
		'enableMediaQueries' => $enable_media_queries,
		'enableHover'        => $enable_hover,
		'children'           => $children,
		'keywords'           => $keywords,
	);
	if ( $dynamic ) {
		$control['dynamic'] = $dynamic;
	}
	return $control;
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
