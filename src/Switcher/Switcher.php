<?php
/**
 * Server-rendered language switcher.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Switcher;

use Omnium\Translate\Core\Settings;
use Omnium\Translate\Router\CurrentLanguage;
use Omnium\Translate\Router\UrlTranslator;

defined( 'ABSPATH' ) || exit;

/**
 * Builds accessible switcher markup and localized links.
 */
final class Switcher {

	private const VARIANTS = array( 'dropdown', 'inline', 'floating' );
	private const DISPLAYS = array( 'flag-name', 'flag', 'name', 'code' );

	/**
	 * Native language names.
	 *
	 * @var array<string, string>
	 */
	private const LANGUAGE_NAMES = array(
		'de' => 'Deutsch',
		'en' => 'English',
		'es' => 'Español',
		'fr' => 'Français',
		'it' => 'Italiano',
		'nl' => 'Nederlands',
		'pt' => 'Português',
	);

	/**
	 * Language-to-flag mapping.
	 *
	 * @var array<string, string>
	 */
	private const FLAGS = array(
		'de' => 'de',
		'en' => 'gb',
		'es' => 'es',
		'fr' => 'fr',
		'it' => 'it',
		'nl' => 'nl',
		'pt' => 'pt',
	);

	/**
	 * Number used to make aria-controls targets unique.
	 *
	 * @var int
	 */
	private int $instance = 0;

	/**
	 * Constructor.
	 *
	 * @param Settings        $settings         Plugin settings.
	 * @param CurrentLanguage $current_language Current request language.
	 * @param UrlTranslator   $url_translator   Localized URL builder.
	 * @param SwitcherAssets  $assets           Conditional front assets.
	 */
	public function __construct(
		private Settings $settings,
		private CurrentLanguage $current_language,
		private UrlTranslator $url_translator,
		private SwitcherAssets $assets
	) {}

	/**
	 * Shortcode callback.
	 *
	 * @param array<string, mixed>|string $attributes Shortcode attributes.
	 */
	public function shortcode( array|string $attributes = array() ): string {
		return $this->render( is_array( $attributes ) ? $attributes : array() );
	}

	/**
	 * Render a language switcher.
	 *
	 * @param array<string, mixed> $attributes Display overrides.
	 */
	public function render( array $attributes = array() ): string {
		return $this->render_markup( $attributes, 'nav' );
	}

	/** Render a switcher suitable for insertion inside an existing navigation. */
	public function render_menu(): string {
		return $this->render_markup( array(), 'div' );
	}

	/**
	 * Build switcher markup.
	 *
	 * @param array<string, mixed> $attributes Display overrides.
	 * @param string               $wrapper    Safe wrapper tag.
	 */
	private function render_markup( array $attributes, string $wrapper ): string {
		$languages = $this->languages();
		if ( count( $languages ) < 2 ) {
			return '';
		}

		$attributes = shortcode_atts(
			array(
				'variant' => 'dropdown',
				'display' => 'flag-name',
			),
			$attributes,
			'omtr_switcher'
		);
		$variant    = in_array( $attributes['variant'], self::VARIANTS, true ) ? (string) $attributes['variant'] : 'dropdown';
		$display    = in_array( $attributes['display'], self::DISPLAYS, true ) ? (string) $attributes['display'] : 'flag-name';
		$current    = $this->current_language->get();
		$source_url = $this->source_url();
		$id         = 'omtr-switcher-' . ( ++$this->instance );

		$this->assets->enqueue();
		$items = '';
		foreach ( $languages as $language ) {
			$active = $language === $current;
			$items .= sprintf(
				'<li class="omtr-switcher__item"><a class="omtr-switcher__link%s" href="%s" lang="%s" hreflang="%s"%s aria-label="%s">%s%s</a></li>',
				$active ? ' is-active' : '',
				esc_url( $this->url_translator->translate( $source_url, $language ) ),
				esc_attr( $language ),
				esc_attr( $language ),
				$active ? ' aria-current="page"' : '',
				esc_attr( $this->language_name( $language ) ),
				$this->language_content( $language, $display ),
				$active ? '<span class="omtr-switcher__check" aria-hidden="true">✓</span>' : ''
			);
		}

		$classes = 'omtr-switcher omtr-switcher--' . $variant . ' omtr-switcher--' . $display;
		if ( 'inline' === $variant ) {
			return sprintf(
				'<%1$s class="%2$s" aria-label="%3$s"><ul class="omtr-switcher__list">%4$s</ul></%1$s>',
				$wrapper,
				esc_attr( $classes ),
				esc_attr__( 'Language selector', 'omnium-translate' ),
				$items
			);
		}

		return sprintf(
			'<%1$s class="%2$s" aria-label="%3$s"><details class="omtr-switcher__dropdown" data-omtr-switcher><summary class="omtr-switcher__trigger" aria-haspopup="true" aria-expanded="false" aria-controls="%4$s">%5$s<span class="omtr-switcher__chevron" aria-hidden="true"></span></summary><ul class="omtr-switcher__list" id="%4$s">%6$s</ul></details></%1$s>',
			$wrapper,
			esc_attr( $classes ),
			esc_attr__( 'Language selector', 'omnium-translate' ),
			esc_attr( $id ),
			$this->language_content( $current, $display ),
			$items
		);
	}

	/**
	 * Return unique, valid configured languages.
	 *
	 * @return list<string>
	 */
	private function languages(): array {
		$languages = array_merge( array( $this->settings->source_lang() ), $this->settings->target_langs() );
		return array_values(
			array_unique(
				array_filter(
					$languages,
					static fn( string $language ): bool => 1 === preg_match( '/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $language )
				)
			)
		);
	}

	/** Return the source-language URL for the current request. */
	private function source_url(): string {
		$request = isset( $GLOBALS['wp']->request ) ? (string) $GLOBALS['wp']->request : '';
		return home_url( '' === $request ? '/' : '/' . ltrim( $request, '/' ) );
	}

	/**
	 * Return the language name in its own language.
	 *
	 * @param string $language Language code.
	 */
	private function language_name( string $language ): string {
		$base = strtolower( (string) strtok( $language, '-' ) );
		return self::LANGUAGE_NAMES[ $base ] ?? strtoupper( $language );
	}

	/**
	 * Build the selected flag/name/code representation.
	 *
	 * @param string $language Language code.
	 * @param string $display  Display mode.
	 */
	private function language_content( string $language, string $display ): string {
		$base      = strtolower( (string) strtok( $language, '-' ) );
		$flag_code = self::FLAGS[ $base ] ?? '';
		$flag      = '';
		if ( '' !== $flag_code && in_array( $display, array( 'flag-name', 'flag' ), true ) ) {
			$flag = sprintf(
				'<img class="omtr-switcher__flag" src="%s" alt="" width="24" height="18" loading="lazy" decoding="async" />',
				esc_url( OMTR_URL . 'assets/flags/' . $flag_code . '.svg' )
			);
		}

		if ( 'flag' === $display && '' !== $flag ) {
			return $flag;
		}
		$text = in_array( $display, array( 'code', 'flag' ), true ) ? strtoupper( $language ) : $this->language_name( $language );
		return $flag . '<span class="omtr-switcher__label">' . esc_html( $text ) . '</span>';
	}
}
