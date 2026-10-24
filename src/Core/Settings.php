<?php
/**
 * Typed access to the omtr_settings option.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Settings repository. API keys are NOT stored here (see Security\ApiKeyStore).
 */
final class Settings {

	/**
	 * Defaults. Keys added later must be declared here.
	 *
	 * @var array<string, mixed>
	 */
	private const DEFAULTS = array(
		'source_lang'      => 'fr',
		'target_langs'     => array(),
		'provider'         => 'claude',
		'model'            => '',
		'auto_translate'   => false,
		'on_the_fly'       => false,
		'monthly_word_cap' => 0,
		'hourly_quota'     => 0,
		'noindex_partial'  => false,
		'pref_cookie'      => false,
		'visual_editor'    => false,
		'site_tone'        => '',
		'site_context'     => '',
		'flags'            => array( 'en' => 'gb' ),
		'switcher'         => array(
			'variant'   => 'dropdown',
			'display'   => 'flag-name',
			'position'  => 'bottom-right',
			'dark_mode' => false,
			'colors'    => array(
				'bg'     => '#ffffff',
				'fg'     => '#0a0a0a',
				'hover'  => '#f4f4f5',
				'border' => '#e4e4e7',
			),
			'radius'    => 8,
			'flag_size' => 18,
		),
	);

	/**
	 * Read a setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public function get( string $key ) {
		$all = $this->all();
		return $all[ $key ] ?? ( self::DEFAULTS[ $key ] ?? null );
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored   = get_option( Options::SETTINGS, array() );
		$all      = array_merge( self::DEFAULTS, is_array( $stored ) ? array_intersect_key( $stored, self::DEFAULTS ) : array() );
		$switcher = is_array( $all['switcher'] ) ? array_intersect_key( $all['switcher'], self::DEFAULTS['switcher'] ) : array();
		if ( isset( $switcher['colors'] ) ) {
			$switcher['colors'] = is_array( $switcher['colors'] ) ? array_intersect_key( $switcher['colors'], self::DEFAULTS['switcher']['colors'] ) : array();
		}
		$all['switcher'] = array_replace_recursive( self::DEFAULTS['switcher'], $switcher );
		$flags           = is_array( $all['flags'] ) ? array_intersect_key( $all['flags'], array_flip( array( 'de', 'en', 'es', 'fr', 'it', 'nl', 'pt' ) ) ) : array();
		$flags           = array_filter( $flags, static fn( mixed $flag ): bool => is_string( $flag ) && in_array( $flag, array( 'at', 'au', 'be', 'br', 'ca', 'ch', 'de', 'es', 'fr', 'gb', 'ie', 'it', 'mx', 'nl', 'pt', 'us', 'ar' ), true ) );
		$all['flags']    = array_merge( self::DEFAULTS['flags'], $flags );
		return $all;
	}

	/**
	 * Update settings. Callers must sanitize values before calling.
	 *
	 * @param array<string, mixed> $values Sanitized values; unknown keys are dropped.
	 */
	public function update( array $values ): void {
		$clean = array_intersect_key( $values, self::DEFAULTS );
		update_option( Options::SETTINGS, array_merge( $this->all(), $clean ), false );
	}

	/**
	 * Source language code.
	 */
	public function source_lang(): string {
		return (string) $this->get( 'source_lang' );
	}

	/**
	 * Enabled target language codes.
	 *
	 * @return list<string>
	 */
	public function target_langs(): array {
		$langs = $this->get( 'target_langs' );
		return is_array( $langs ) ? array_values( array_map( 'strval', $langs ) ) : array();
	}
}
