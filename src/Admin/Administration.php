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
use Omnium\Translate\Providers\ProviderInterface;
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
	/** Core REST transport parameters: never settings, always allowed. */
	private const CORE_REST_PARAMS = array(
		'_embed'    => true,
		'_fields'   => true,
		'_method'   => true,
		'_envelope' => true,
		'_locale'   => true,
		'_wpnonce'  => true,
	);
	private const LANGUAGES        = array(
		'de'    => array( 'Deutsch', array( 'de', 'at', 'ch' ) ),
		'de-at' => array( 'Deutsch (Österreich)', array( 'at' ) ),
		'de-ch' => array( 'Deutsch (Schweiz)', array( 'ch' ) ),
		'en'    => array( 'English', array( 'gb', 'us', 'ca', 'au', 'ie' ) ),
		'en-au' => array( 'English (Australia)', array( 'au' ) ),
		'en-ca' => array( 'English (Canada)', array( 'ca' ) ),
		'en-gb' => array( 'English (United Kingdom)', array( 'gb' ) ),
		'en-ie' => array( 'English (Ireland)', array( 'ie' ) ),
		'en-us' => array( 'English (United States)', array( 'us' ) ),
		'es'    => array( 'Español', array( 'es', 'mx', 'ar' ) ),
		'es-ar' => array( 'Español (Argentina)', array( 'ar' ) ),
		'es-es' => array( 'Español (España)', array( 'es' ) ),
		'es-mx' => array( 'Español (México)', array( 'mx' ) ),
		'fr'    => array( 'Français', array( 'fr', 'be', 'ch', 'ca' ) ),
		'fr-be' => array( 'Français (Belgique)', array( 'be' ) ),
		'fr-ca' => array( 'Français (Canada)', array( 'ca' ) ),
		'fr-ch' => array( 'Français (Suisse)', array( 'ch' ) ),
		'fr-fr' => array( 'Français (France)', array( 'fr' ) ),
		'it'    => array( 'Italiano', array( 'it', 'ch' ) ),
		'it-ch' => array( 'Italiano (Svizzera)', array( 'ch' ) ),
		'it-it' => array( 'Italiano (Italia)', array( 'it' ) ),
		'nl'    => array( 'Nederlands', array( 'nl', 'be' ) ),
		'nl-be' => array( 'Nederlands (België)', array( 'be' ) ),
		'nl-nl' => array( 'Nederlands (Nederland)', array( 'nl' ) ),
		'pt'    => array( 'Português', array( 'pt', 'br' ) ),
		'pt-br' => array( 'Português (Brasil)', array( 'br' ) ),
		'pt-pt' => array( 'Português (Portugal)', array( 'pt' ) ),
	);
	private const FLAGS            = array( 'at', 'au', 'be', 'br', 'ca', 'ch', 'de', 'es', 'fr', 'gb', 'ie', 'it', 'mx', 'nl', 'pt', 'us', 'ar' );

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
		add_menu_page( 'Omnium Translate', 'Omnium Translate', Capabilities::MANAGE, 'omnium-translate', array( $this, 'page' ), self::menu_icon() );
	}

	/**
	 * Omnium logo as a data URI: WordPress repaints its fills to the admin color scheme.
	 */
	private static function menu_icon(): string {
		$svg = (string) file_get_contents( OMTR_DIR . 'assets/images/omnium-icon.svg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local plugin file, not a URL.
		return '' !== $svg ? 'data:image/svg+xml;base64,' . base64_encode( $svg ) : 'dashicons-translation'; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Data URI required by add_menu_page().
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
		wp_enqueue_style( 'omtr-switcher', OMTR_URL . 'assets/css/switcher.css', array(), OMTR_VERSION );
		if ( file_exists( OMTR_DIR . 'assets/build/admin.css' ) ) {
			wp_enqueue_style( 'omtr-admin', OMTR_URL . 'assets/build/admin.css', array( 'wp-components', 'omtr-switcher' ), $asset['version'] );
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
			'/translations/retry'             => array( array( 'POST', 'retry_failed', array() ) ),
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
		/*
		 * apiFetch appends _locale=user (and may add _fields/_method) to every
		 * browser request as query parameters. They are REST transport values:
		 * strip them before the strict unknown-parameter validation below.
		 */
		$values = array_diff_key( $request->get_params(), self::CORE_REST_PARAMS );
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
	 * When the key is saved for a provider that is not the active one and the
	 * active provider has no key, the active provider switches to it: without
	 * this, every enqueue would target a provider that cannot answer.
	 *
	 * @param \WP_REST_Request $request REST request.
	 */
	public function put_key( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$id       = (string) $request['id'];
		$provider = $this->providers->get( $id );
		if ( null === $provider ) {
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
		$previous = (string) $this->settings->get( 'provider' );
		$active   = $this->activate_provider_with_key( $provider );
		return new \WP_REST_Response(
			array(
				'set'      => true,
				'masked'   => $this->keys->masked( $id ),
				'provider' => $active,
				'switched' => $active !== $previous,
			)
		);
	}

	/**
	 * Make the freshly keyed provider the active one when the current active
	 * provider has no usable key, then return the active provider id.
	 *
	 * @param ProviderInterface $provider Freshly keyed provider.
	 */
	private function activate_provider_with_key( ProviderInterface $provider ): string {
		$active = (string) $this->settings->get( 'provider' );
		if ( $active === $provider->id() ) {
			return $active;
		}
		$active_provider = $this->providers->get( $active );
		$has_key         = false;
		if ( null !== $active_provider ) {
			try {
				$has_key = '' !== $this->keys->masked( $active );
			} catch ( \Throwable $error ) {
				$has_key = false;
			}
		}
		if ( $has_key ) {
			return $active;
		}
		$update = array( 'provider' => $provider->id() );
		if ( ! isset( $provider->models()[ (string) $this->settings->get( 'model' ) ] ) ) {
			// The stored model belongs to the previous provider: fall back to its recommended default.
			$update['model'] = '';
		}
		$this->settings->update( $update );
		return $provider->id();
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
					// Provider results only hold fixed strings, the HTTP code and a sanitized provider code.
					'message'   => $result->ok ? __( 'API key is valid.', 'omnium-translate' ) : $result->message,
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
				'coverage' => $this->coverage->display_percentage( $code ),
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
		$log_table  = Tables::name( Tables::LOG );
		$log        = $this->wpdb->get_results( $this->wpdb->prepare( 'SELECT level, context, message, created_at FROM %i ORDER BY created_at DESC LIMIT %d', $log_table, 20 ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared identifier.
		$log        = $log ? $log : array();
		$last_error = null;
		foreach ( $log as $entry ) {
			if ( 'error' === (string) $entry['level'] ) {
				$last_error = array(
					'message'    => (string) $entry['message'],
					'created_at' => (string) $entry['created_at'],
				);
				break;
			}
		}
		return new \WP_REST_Response(
			array(
				'languages' => $langs,
				'queue'     => array(
					'paused'        => $this->state->is_paused(),
					'running'       => $this->lock->is_held(),
					'pending'       => $this->queue->pending_work_count(),
					'batches'       => $this->queue->pending_batch_count(),
					'last_activity' => isset( $log[0]['created_at'] ) ? (string) $log[0]['created_at'] : null,
				),
				'words'     => array(
					'used'             => $this->words->used(),
					'cap'              => $this->words->limit(),
					'translated_month' => $this->words->translated_this_month(),
					'translated_total' => $this->words->translated_total(),
				),
				'segments'  => $counts,
				'failures'  => array(
					'count'  => $counts['failed'],
					'reason' => $last_error,
				),
				'log'       => $log,
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

	/**
	 * Re-queue every failed segment for its target language.
	 *
	 * Failed rows keep the (segment, language) pair, so the retry is scoped
	 * per language: only what actually failed goes back to the provider,
	 * nothing already translated is paid twice.
	 */
	public function retry_failed(): \WP_REST_Response|\WP_Error {
		if ( ! $this->queue->provider_is_ready() ) {
			return new \WP_Error( 'omtr_enqueue_refused', $this->queue->last_reason(), array( 'status' => 409 ) );
		}
		$retried = 0;
		foreach ( $this->settings->target_langs() as $lang ) {
			$ids = $this->failed_segment_ids( $lang );
			if ( array() === $ids ) {
				continue;
			}
			$this->queue->enqueue_segments( $ids, $lang );
			$retried += count( $ids );
		}
		return new \WP_REST_Response( array( 'retried' => $retried ), 202 );
	}

	/**
	 * Segment ids whose latest translation is marked failed for a language.
	 *
	 * @param string $target_lang Target language code.
	 * @return list<int>
	 */
	private function failed_segment_ids( string $target_lang ): array {
		$sql  = $this->wpdb->prepare(
			'SELECT DISTINCT segment_id FROM %i WHERE target_lang = %s AND status = %s',
			Tables::name( Tables::TRANSLATIONS ),
			$target_lang,
			'failed'
		);
		$rows = $this->wpdb->get_col( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
		return array_values( array_filter( array_map( 'intval', (array) $rows ) ) );
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
		$selected = $this->providers->get( (string) $this->settings->get( 'provider' ) );
		$items    = '';
		foreach ( $this->providers->all() as $provider ) {
			$region = 'eu' === $provider->region() ? __( 'processing in the EU', 'omnium-translate' ) : __( 'processing outside the EU', 'omnium-translate' );
			$items .= '<li>' . esc_html( $provider->label() . ' (' . $region . ')' ) . '</li>';
		}
		$text = __( 'Omnium Translate sends the text of public pages, the glossary, and the site tone and context to the AI provider selected by the site owner, only for translation. Visitor account, order and form data is excluded; detected personal data is filtered before sending. Nothing is sent to Omnium Digital.', 'omnium-translate' );
		if ( null !== $selected ) {
			/* translators: %s: provider name. */
			$text .= ' ' . sprintf( __( 'Provider currently selected: %s.', 'omnium-translate' ), esc_html( $selected->label() ) );
		}
		$text  .= ' ' . __( 'Available providers and processing location (update this policy if you change provider):', 'omnium-translate' );
		$cookie = __( 'If the site owner enables the language preference, choosing a language stores a strictly functional cookie (omtr_lang, 12 months maximum) containing only the language code, never any identifier.', 'omnium-translate' );
		wp_add_privacy_policy_content( 'Omnium Translate', wp_kses_post( '<p>' . $text . '</p><ul>' . $items . '</ul><p>' . $cookie . '</p>' ) );
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
