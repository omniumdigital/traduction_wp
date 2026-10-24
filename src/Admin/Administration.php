<?php
/**
 * Administration page, REST API and privacy notice.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Admin;

use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Core\Tables;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\QueueState;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Seo\TranslationCoverage;

defined( 'ABSPATH' ) || exit;

/** Handles the milestone-five administration contract. */
final class Administration {
	private const NAMESPACE = 'omtr/v1';
	private const LANGUAGES = array(
		'de' => array( 'Deutsch', array( 'de', 'at', 'ch' ) ),
		'en' => array( 'English', array( 'gb', 'us', 'ca', 'au', 'ie' ) ),
		'es' => array( 'Español', array( 'es', 'mx', 'ar' ) ),
		'fr' => array( 'Français', array( 'fr', 'be', 'ch', 'ca' ) ),
		'it' => array( 'Italiano', array( 'it', 'ch' ) ),
		'nl' => array( 'Nederlands', array( 'nl', 'be' ) ),
		'pt' => array( 'Português', array( 'pt', 'br' ) ),
	);
	private const FLAGS     = array( 'at', 'au', 'be', 'br', 'ca', 'ch', 'de', 'es', 'fr', 'gb', 'ie', 'it', 'mx', 'nl', 'pt', 'us', 'ar' );

	/**
	 * Construct the administration service.
	 *
	 * @param Settings             $settings Plugin settings.
	 * @param ProviderRegistry     $providers Provider registry.
	 * @param Pricing              $pricing Provider prices.
	 * @param ApiKeyStore          $keys Encrypted keys.
	 * @param GlossaryRepository   $glossary Glossary storage.
	 * @param ActionSchedulerQueue $queue Translation queue.
	 * @param QueueState           $state Queue state.
	 * @param RunLock              $lock Translation run lock.
	 * @param WordCap              $words Monthly word cap.
	 * @param PublicContent        $content Public content resolver.
	 * @param TranslationCoverage  $coverage Language coverage.
	 * @param \wpdb                $wpdb WordPress database.
	 */
	public function __construct(
		private Settings $settings,
		private ProviderRegistry $providers,
		private Pricing $pricing,
		private ApiKeyStore $keys,
		private GlossaryRepository $glossary,
		private ActionSchedulerQueue $queue,
		private QueueState $state,
		private RunLock $lock,
		private WordCap $words,
		private PublicContent $content,
		private TranslationCoverage $coverage,
		private \wpdb $wpdb
	) {}

	/** Register the top-level menu. */
	public function menu(): void {
		add_menu_page( 'Omnium Translate', 'Omnium Translate', Capabilities::MANAGE, 'omnium-translate', array( $this, 'page' ), 'dashicons-translation' );
	}

	/** Render the React mount or a build warning. */
	public function page(): void {
		echo '<div class="wrap"><div id="omtr-admin"></div>';
		if ( ! file_exists( OMTR_DIR . 'assets/build/admin.js' ) || ! file_exists( OMTR_DIR . 'assets/build/admin.asset.php' ) ) {
			echo '<p>' . esc_html__( 'The administration interface is not built yet.', 'omnium-translate' ) . '</p>';
		}
		echo '</div>';
	}

	/** Enqueue the generated admin entry when present.
	 *
	 * @param string $hook Current admin hook.
	 */
	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_omnium-translate' !== $hook || ! file_exists( OMTR_DIR . 'assets/build/admin.js' ) || ! file_exists( OMTR_DIR . 'assets/build/admin.asset.php' ) ) {
			return;
		}
		$asset_path = OMTR_DIR . 'assets/build/admin.asset.php';
		$asset      = require $asset_path;
		wp_enqueue_style( 'wp-components' );
		if ( file_exists( OMTR_DIR . 'assets/build/admin.css' ) ) {
			wp_enqueue_style( 'omtr-admin', OMTR_URL . 'assets/build/admin.css', array( 'wp-components' ), $asset['version'] );
		}
		wp_enqueue_script( 'omtr-admin', OMTR_URL . 'assets/build/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_add_inline_script(
			'omtr-admin',
			'window.omtrAdmin = ' . wp_json_encode(
				array(
					'restNamespace' => self::NAMESPACE,
					'wizardDone'    => (bool) get_option( Options::WIZARD_DONE, false ),
					'siteUrl'       => home_url(),
					'locale'        => get_locale(),
					'version'       => OMTR_VERSION,
					'flagsUrl'      => OMTR_URL . 'assets/flags/',
				)
			) . ';',
			'before'
		);
		wp_set_script_translations( 'omtr-admin', 'omnium-translate', OMTR_DIR . 'languages' );
	}

	/** Register all private routes. */
	public function register_routes(): void {
		$permission = array( Capabilities::class, 'rest_permission' );
		$routes     = array(
			'/settings'                       => array(
				array( 'GET', 'get_settings', array() ),
				array( 'PUT', 'put_settings', $this->settings_args() ),
			),
			'/providers'                      => array( array( 'GET', 'list_providers', array() ) ),
			'/providers/(?P<id>[a-z]+?)/key'  => array(
				array(
					'PUT',
					'put_key',
					array(
						'id'  => $this->provider_arg(),
						'key' => array(
							'type'              => 'string',
							'required'          => true,
							'minLength'         => 10,
							'maxLength'         => 300,
							'pattern'           => '^[!-~]+$',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array( 'DELETE', 'delete_key', array( 'id' => $this->provider_arg() ) ),
			),
			'/providers/(?P<id>[a-z]+?)/test' => array( array( 'POST', 'test_key', array( 'id' => $this->provider_arg() ) ) ),
			'/dashboard'                      => array( array( 'GET', 'dashboard', array() ) ),
			'/translate/all'                  => array( array( 'POST', 'translate_all', array() ) ),
			'/wizard/complete'                => array(
				array(
					'POST',
					'complete_wizard',
					array(
						'insert_menu' => array(
							'type'              => 'boolean',
							'required'          => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
						),
					),
				),
			),
			'/config/export'                  => array( array( 'GET', 'export_config', array() ) ),
			'/config/import'                  => array(
				array(
					'POST',
					'import_config',
					$this->import_args(),
				),
			),
		);
		foreach ( $routes as $path => $endpoints ) {
			$handlers = array();
			foreach ( $endpoints as $endpoint ) {
				$handlers[] = array(
					'methods'             => $endpoint[0],
					'callback'            => array( $this, $endpoint[1] ),
					'permission_callback' => $permission,
					'args'                => $endpoint[2],
				);
			}
			register_rest_route( self::NAMESPACE, $path, $handlers );
		}
	}

	/** Return settings and language choices. */
	public function get_settings(): \WP_REST_Response {
		$settings                     = $this->settings->all();
		$settings['uninstall_purge']  = (bool) get_option( Options::UNINSTALL_PURGE, false );
		$settings['updates_disabled'] = (bool) get_option( Options::UPDATES_DISABLED, false );
		$languages                    = array();
		foreach ( self::LANGUAGES as $code => $item ) {
			$languages[] = array(
				'code'  => $code,
				'name'  => $item[0],
				'flags' => $item[1],
			);
		}
		return new \WP_REST_Response(
			array(
				'settings'  => $settings,
				'languages' => $languages,
				'flags'     => self::FLAGS,
			)
		);
	}

	/** Validate and apply a partial settings object.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function put_settings( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$values = $request->get_params();
		$result = $this->validate_settings( $values );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$this->apply_settings( $result );
		return $this->get_settings();
	}

	/** Public provider metadata; never include decrypted keys. */
	public function list_providers(): \WP_REST_Response {
		$rows   = array();
		$prices = $this->pricing->table();
		foreach ( $this->providers->all() as $provider ) {
			$pricing = array();
			foreach ( $prices[ $provider->id() ] ?? array() as $model => $price ) {
				$pricing[ $model ] = $price + array( 'verified_at' => Pricing::VERIFIED_AT );
			}
			$masked = $this->keys->masked( $provider->id() );
			$models = array();
			foreach ( $provider->models() as $model_id => $label ) {
				$models[] = array(
					'id'          => $model_id,
					'label'       => $label,
					'recommended' => empty( $models ),
				);
			}
			$rows[] = array(
				'id'        => $provider->id(),
				'label'     => $provider->label(),
				'region'    => $provider->region(),
				'free_tier' => $provider->has_free_tier(),
				'key_url'   => $provider->key_url(),
				'models'    => $models,
				'pricing'   => $pricing,
				'key'       => array(
					'set'    => '' !== $masked,
					'masked' => $masked,
				),
			);
		}
		return new \WP_REST_Response( $rows );
	}

	/** Store a provider key.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function put_key( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (string) $request['id'];
		if ( null === $this->providers->get( $id ) ) {
			return $this->invalid();
		}
		$key = (string) $request['key'];
		if ( ! preg_match( '/^[!-~]{10,300}$/D', $key ) ) {
			return $this->invalid();
		}
		try {
			$this->keys->set( $id, $key );
		} catch ( \Throwable $error ) {
			return $this->unavailable();
		}
		return new \WP_REST_Response(
			array(
				'set'    => true,
				'masked' => $this->keys->masked( $id ),
			)
		);
	}

	/** Remove a provider key.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function delete_key( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id = (string) $request['id'];
		if ( null === $this->providers->get( $id ) ) {
			return $this->invalid();
		}
		$this->keys->delete( $id );
		return new \WP_REST_Response(
			array(
				'set'    => false,
				'masked' => '',
			)
		);
	}

	/** Test a stored provider key without exposing provider text.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function test_key( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$provider = $this->providers->get( (string) $request['id'] );
		if ( null === $provider ) {
			return $this->invalid();
		}
		try {
			$result = $provider->test_key();
			return new \WP_REST_Response(
				array(
					'ok'        => $result->ok,
					'message'   => $result->ok ? __( 'API key is valid.', 'omnium-translate' ) : __( 'API key test failed.', 'omnium-translate' ),
					'http_code' => $result->http_code,
				)
			);
		} catch ( \Throwable $error ) {
			return $this->unavailable();
		}
	}

	/** Dashboard aggregates. */
	public function dashboard(): \WP_REST_Response {
		$langs = array();
		foreach ( $this->settings->target_langs() as $code ) {
			$langs[] = array(
				'code'     => $code,
				'coverage' => $this->coverage->percentage( $code ),
			);
		}
		$counts          = array_fill_keys( array( 'total', 'auto', 'edited', 'locked', 'failed' ), 0 );
		$segment_table   = Tables::name( Tables::SEGMENTS );
		$counts['total'] = (int) $this->wpdb->get_var( $this->wpdb->prepare( 'SELECT COUNT(*) FROM %i', $segment_table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared identifier.
		$table           = Tables::name( Tables::TRANSLATIONS );
		$rows            = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT status, COUNT(*) AS count FROM %i GROUP BY status', $table ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared identifier.
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['count'];
			}
		}
		$log_table = Tables::name( Tables::LOG );
		$log       = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT level, context, message, created_at FROM %i ORDER BY created_at DESC LIMIT %d', $log_table, 20 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared identifier.
		return new \WP_REST_Response(
			array(
				'languages' => $langs,
				'queue'     => array(
					'paused'  => $this->state->is_paused(),
					'running' => $this->lock->is_held(),
					'pending' => $this->queue->pending_work_count(),
				),
				'words'     => array(
					'used' => $this->words->used(),
					'cap'  => $this->words->limit(),
				),
				'segments'  => $counts,
				'log'       => $log ? $log : array(),
			)
		);
	}

	/** Queue all published public pages. */
	public function translate_all(): \WP_REST_Response|\WP_Error {
		$targets = $this->content->all();
		if ( ! $this->queue->enqueue_urls( $targets ) ) {
			return new \WP_Error( 'omtr_enqueue_refused', $this->queue->last_reason(), array( 'status' => 409 ) );
		}
		return new \WP_REST_Response(
			array(
				'queued' => true,
				'pages'  => count( $targets ),
			),
			202
		);
	}

	/** Mark wizard complete and optionally add a menu marker.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function complete_wizard( \WP_REST_Request $request ): \WP_REST_Response {
		$inserted = false;
		if ( $request['insert_menu'] ) {
			$locations = get_registered_nav_menus();
			$assigned  = get_nav_menu_locations();
			foreach ( array_keys( $locations ) as $location ) {
				$menu_id = (int) ( $assigned[ $location ] ?? 0 );
				if ( $menu_id > 0 ) {
					$found = false;
					foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
						if ( '#omtr-switcher' === $item->url ) {
							$found = true;
							break;
						}
					}
					$menu_item_id = $found ? 1 : wp_update_nav_menu_item(
						$menu_id,
						0,
						array(
							'menu-item-title'  => __( 'Language selector', 'omnium-translate' ),
							'menu-item-url'    => '#omtr-switcher',
							'menu-item-status' => 'publish',
							'menu-item-type'   => 'custom',
						)
					);
					$inserted     = ! is_wp_error( $menu_item_id ) && $menu_item_id > 0;
					break;
				}
			}
		}
		update_option( Options::WIZARD_DONE, true, false );
		return new \WP_REST_Response(
			array(
				'wizard_done'   => true,
				'menu_inserted' => $inserted,
			)
		);
	}

	/** Export only non-sensitive settings and glossary entries. */
	public function export_config(): \WP_REST_Response {
		$settings = $this->settings->all();
		unset( $settings['uninstall_purge'], $settings['updates_disabled'] );
		$glossary = array_map(
			static function ( array $row ): array {
				return array_intersect_key( $row, array_flip( array( 'term', 'target_lang', 'translation', 'rule' ) ) );
			},
			$this->glossary->all()
		);
		return new \WP_REST_Response(
			array(
				'format'   => 'omnium-translate-config',
				'version'  => 1,
				'settings' => $settings,
				'glossary' => $glossary,
			)
		);
	}

	/** Import a size-limited JSON configuration.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function import_config( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$body = $request->get_body();
		if ( strlen( $body ) > 1048576 ) {
			return new \WP_Error( 'omtr_import_too_large', __( 'Configuration exceeds 1 MB.', 'omnium-translate' ), array( 'status' => 413 ) );
		}
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) || 'omnium-translate-config' !== ( $data['format'] ?? null ) || 1 !== ( $data['version'] ?? null ) || ! isset( $data['settings'], $data['glossary'] ) || ! is_array( $data['settings'] ) || ! is_array( $data['glossary'] ) ) {
			return $this->invalid();
		}
		$settings = $this->validate_settings( $data['settings'], false );
		if ( is_wp_error( $settings ) ) {
			return $settings;
		}
		$rules = array();
		foreach ( $data['glossary'] as $row ) {
			if ( ! is_array( $row ) || ! isset( $row['term'], $row['target_lang'], $row['translation'], $row['rule'] ) || ! is_string( $row['term'] ) || ! is_string( $row['target_lang'] ) || ! is_string( $row['translation'] ) || ! in_array( $row['rule'], array( 'keep', 'translate' ), true ) || ! isset( self::LANGUAGES[ $row['target_lang'] ] ) ) {
				return $this->invalid();
			}
			$term        = sanitize_text_field( $row['term'] );
			$translation = sanitize_text_field( $row['translation'] );
			if ( '' === $term || ( 'translate' === $row['rule'] && '' === $translation ) ) {
				return $this->invalid();
			}
			$rules[] = array( $term, $row['target_lang'], $translation, $row['rule'] );
		}
		$this->apply_settings( $settings );
		$existing = $this->glossary->all();
		if ( $request['replace_glossary'] ) {
			foreach ( $existing as $row ) {
				$this->glossary->delete( $row['id'] );
			}
			$existing = array();
		}
		$seen = array();
		foreach ( $existing as $row ) {
			$seen[ $row['term'] . "\0" . $row['target_lang'] . "\0" . $row['rule'] ] = true;
		}
		$count = 0;
		foreach ( $rules as $rule ) {
			$key = $rule[0] . "\0" . $rule[1] . "\0" . $rule[3];
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$this->glossary->add( ...$rule );
			$seen[ $key ] = true;
			++$count;
		}
		return new \WP_REST_Response(
			array(
				'imported' => true,
				'glossary' => $count,
			)
		);
	}

	/** Add the installer's disclosure to the WordPress policy editor. */
	public function privacy_policy(): void {
		$provider = $this->providers->get( (string) $this->settings->get( 'provider' ) );
		if ( null === $provider ) {
			return;
		}
		$region = 'eu' === $provider->region() ? __( 'the EU', 'omnium-translate' ) : __( 'a country outside the EU', 'omnium-translate' );
		$text   = sprintf( /* translators: 1: selected provider name, 2: processing region. */ __( 'Omnium Translate sends public page text to %1$s for translation. Processing takes place in %2$s. No visitor account, order or form data is sent.', 'omnium-translate' ), esc_html( $provider->label() ), esc_html( $region ) );
		$cookie = __( 'If the site owner enables the language preference, choosing a language stores a strictly functional cookie (omtr_lang, 12 months maximum) containing only the language code, never any identifier.', 'omnium-translate' );
		wp_add_privacy_policy_content( 'Omnium Translate', wp_kses_post( '<p>' . $text . '</p><p>' . $cookie . '</p>' ) );
	}

	/** REST schema for a provider id.
	 *
	 * @return array<string, mixed>
	 */
	private function provider_arg(): array {
		return array(
			'type'              => 'string',
			'required'          => true,
			'enum'              => array_keys( $this->providers->all() ),
			'sanitize_callback' => 'sanitize_key',
		);
	}

	/** Schema for every import field.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function import_args(): array {
		return array(
			'format'           => array(
				'type'     => 'string',
				'required' => true,
				'enum'     => array( 'omnium-translate-config' ),
			),
			'version'          => array(
				'type'     => 'integer',
				'required' => true,
				'enum'     => array( 1 ),
			),
			'settings'         => array(
				'type'                 => 'object',
				'required'             => true,
				'additionalProperties' => false,
				'properties'           => $this->settings_args(),
			),
			'glossary'         => array(
				'type'     => 'array',
				'required' => true,
				'items'    => array(
					'type'                 => 'object',
					'additionalProperties' => false,
					'required'             => array( 'term', 'target_lang', 'translation', 'rule' ),
					'properties'           => array(
						'term'        => array(
							'type'      => 'string',
							'minLength' => 1,
							'maxLength' => 500,
						),
						'target_lang' => array(
							'type' => 'string',
							'enum' => array_keys( self::LANGUAGES ),
						),
						'translation' => array(
							'type'      => 'string',
							'maxLength' => 500,
						),
						'rule'        => array(
							'type' => 'string',
							'enum' => array( 'keep', 'translate' ),
						),
					),
				),
			),
			'replace_glossary' => array(
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			),
		);
	}

	/** Full top-level settings schema.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function settings_args(): array {
		$langs    = array_keys( self::LANGUAGES );
		$bool     = array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
		);
		$language = array(
			'type'              => 'string',
			'enum'              => $langs,
			'sanitize_callback' => 'sanitize_key',
		);
		$color    = array(
			'type'              => 'string',
			'pattern'           => '^#[0-9a-fA-F]{6}$',
			'sanitize_callback' => 'sanitize_hex_color',
		);
		return array(
			'source_lang'      => $language,
			'target_langs'     => array(
				'type'              => 'array',
				'uniqueItems'       => true,
				'items'             => $language,
				'sanitize_callback' => static fn( array $value ): array => array_values( array_map( 'sanitize_key', $value ) ),
			),
			'provider'         => array(
				'type'              => 'string',
				'enum'              => array_keys( $this->providers->all() ),
				'sanitize_callback' => 'sanitize_key',
			),
			'model'            => array(
				'type'              => 'string',
				'maxLength'         => 100,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'auto_translate'   => $bool,
			'on_the_fly'       => $bool,
			'monthly_word_cap' => array(
				'type'              => 'integer',
				'minimum'           => 0,
				'sanitize_callback' => 'absint',
			),
			'hourly_quota'     => array(
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
			),
			'noindex_partial'  => $bool,
			'pref_cookie'      => $bool,
			'visual_editor'    => $bool,
			'site_tone'        => array(
				'type'              => 'string',
				'maxLength'         => 200,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'site_context'     => array(
				'type'              => 'string',
				'maxLength'         => 1000,
				'sanitize_callback' => 'sanitize_textarea_field',
			),
			'flags'            => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array_fill_keys(
					$langs,
					array(
						'type'              => 'string',
						'enum'              => self::FLAGS,
						'sanitize_callback' => 'sanitize_key',
					)
				),
			),
			'switcher'         => array(
				'type'                 => 'object',
				'additionalProperties' => false,
				'properties'           => array(
					'variant'   => array(
						'type'              => 'string',
						'enum'              => array( 'dropdown', 'inline', 'floating' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'display'   => array(
						'type'              => 'string',
						'enum'              => array( 'flag-name', 'flag', 'name', 'code' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'position'  => array(
						'type'              => 'string',
						'enum'              => array( 'bottom-right', 'bottom-left', 'top-right', 'top-left' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'dark_mode' => $bool,
					'colors'    => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array_fill_keys( array( 'bg', 'fg', 'hover', 'border' ), $color ),
					),
					'radius'    => array(
						'type'              => 'integer',
						'minimum'           => 0,
						'maximum'           => 24,
						'sanitize_callback' => 'absint',
					),
					'flag_size' => array(
						'type'              => 'integer',
						'minimum'           => 12,
						'maximum'           => 32,
						'sanitize_callback' => 'absint',
					),
				),
			),
			'uninstall_purge'  => $bool,
			'updates_disabled' => $bool,
		);
	}

	/** Validate settings beyond JSON schema, including cross-field rules.
	 *
	 * @param array<string, mixed> $values Candidate values.
	 * @param bool                 $allow_options Whether dedicated options may be changed.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function validate_settings( array $values, bool $allow_options = true ): array|\WP_Error {
		$schema = $this->settings_args();
		foreach ( $values as $key => $value ) {
			if ( ! isset( $schema[ $key ] ) || ( ! $allow_options && in_array( $key, array( 'uninstall_purge', 'updates_disabled' ), true ) ) || true !== rest_validate_value_from_schema( $value, $schema[ $key ], $key ) ) {
				return $this->invalid();
			}
			$values[ $key ] = rest_sanitize_value_from_schema( $value, $schema[ $key ], $key );
		}
		$merged = array_merge( $this->settings->all(), $values );
		if ( isset( $values['switcher'] ) ) {
			$merged['switcher'] = array_replace_recursive( $this->settings->get( 'switcher' ), $values['switcher'] );
		}
		if ( in_array( $merged['source_lang'], $merged['target_langs'], true ) || null === $this->providers->get( $merged['provider'] ) ) {
			return $this->invalid();
		}
		if ( '' !== $merged['model'] ) {
			$ids = array_keys( $this->providers->get( $merged['provider'] )->models() );
			if ( ! in_array( $merged['model'], $ids, true ) ) {
				return $this->invalid();
			}
		}
		return $values;
	}

	/** Apply options only after complete validation.
	 *
	 * @param array<string, mixed> $values Validated settings.
	 */
	private function apply_settings( array $values ): void {
		if ( array_key_exists( 'uninstall_purge', $values ) ) {
			update_option( Options::UNINSTALL_PURGE, (bool) $values['uninstall_purge'], false );
			unset( $values['uninstall_purge'] );
		}
		if ( array_key_exists( 'updates_disabled', $values ) ) {
			update_option( Options::UPDATES_DISABLED, (bool) $values['updates_disabled'], false );
			unset( $values['updates_disabled'] );
		}
		if ( isset( $values['switcher'] ) ) {
			$values['switcher'] = array_replace_recursive( $this->settings->get( 'switcher' ), $values['switcher'] );
		}
		$this->settings->update( $values );
	}

	/** Generic validation error. */
	private function invalid(): \WP_Error {
		return new \WP_Error( 'omtr_invalid_config', __( 'Invalid administration data.', 'omnium-translate' ), array( 'status' => 400 ) );
	}

	/** Generic operational error. */
	private function unavailable(): \WP_Error {
		return new \WP_Error( 'omtr_provider_error', __( 'The provider operation failed.', 'omnium-translate' ), array( 'status' => 503 ) );
	}
}
