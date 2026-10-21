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
		'site_tone'        => '',
		'site_context'     => '',
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
		$stored = get_option( Options::SETTINGS, array() );
		return array_merge( self::DEFAULTS, is_array( $stored ) ? $stored : array() );
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
