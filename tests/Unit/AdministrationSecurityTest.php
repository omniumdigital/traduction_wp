<?php
/**
 * Administration API security tests.
 *
 * @package Omnium\Translate
 */

namespace Omnium\Translate\Tests\Unit;

use Brain\Monkey\Functions;
use Omnium\Translate\Admin\Administration;
use Omnium\Translate\Core\Options;
use Omnium\Translate\Core\Settings;
use Omnium\Translate\Glossary\GlossaryRepository;
use Omnium\Translate\Providers\Claude\ClaudeProvider;
use Omnium\Translate\Providers\Pricing;
use Omnium\Translate\Providers\PromptBuilder;
use Omnium\Translate\Providers\ProviderRegistry;
use Omnium\Translate\Providers\ResponseValidator;
use Omnium\Translate\Queue\ActionSchedulerQueue;
use Omnium\Translate\Queue\HourlyQuota;
use Omnium\Translate\Queue\PublicContent;
use Omnium\Translate\Queue\QueueState;
use Omnium\Translate\Queue\RunLock;
use Omnium\Translate\Queue\WordCap;
use Omnium\Translate\Security\ApiKeyStore;
use Omnium\Translate\Security\Capabilities;
use Omnium\Translate\Security\Crypto;
use Omnium\Translate\Seo\TranslationCoverage;
use Omnium\Translate\Tests\TestCase;
use Omnium\Translate\Tests\Unit\Doubles\OptionStore;
use Omnium\Translate\Tests\Unit\Doubles\SpyLogger;

/** @group security */
final class AdministrationSecurityTest extends TestCase {
	/** @var list<array<string, mixed>> */
	private array $routes = array();

	/** Set up WordPress doubles. */
	protected function setUp(): void {
		parent::setUp();
		( new OptionStore() )->install();
		Functions\when( 'register_rest_route' )->alias(
			function ( string $namespace, string $route, array $args ): bool {
				$this->routes[] = compact( 'namespace', 'route', 'args' );
				return true;
			}
		);
		Functions\when( 'is_wp_error' )->alias( static fn( mixed $value ): bool => $value instanceof \WP_Error );
		Functions\when( '__' )->alias( static fn( string $text ): string => $text );
		Functions\when( 'sanitize_text_field' )->alias( static fn( string $text ): string => strip_tags( $text ) );
		Functions\when( 'rest_sanitize_value_from_schema' )->alias( static fn( mixed $value ): mixed => $value );
		Functions\when( 'rest_validate_value_from_schema' )->alias(
			static function ( mixed $value, array $schema ): bool|\WP_Error {
				if ( isset( $schema['properties']['colors']['properties'] ) && is_array( $value ) ) {
					foreach ( (array) ( $value['colors'] ?? array() ) as $color ) {
						if ( ! is_string( $color ) || ! preg_match( '/^#[0-9a-fA-F]{6}$/', $color ) ) {
							return new \WP_Error( 'bad_color' );
						}
					}
				}
				return true;
			}
		);
	}

	/** Remove the database fixture. */
	protected function tearDown(): void {
		unset( $GLOBALS['wpdb'] );
		parent::tearDown();
	}

	/** Every route denies a user without omtr_manage. */
	public function test_all_routes_require_manage_capability(): void {
		Functions\when( 'current_user_can' )->justReturn( false );
		$this->api()->register_routes();
		$this->assertCount( 9, $this->routes );
		foreach ( $this->routes as $route ) {
			foreach ( $route['args'] as $endpoint ) {
				$this->assertSame( array( Capabilities::class, 'rest_permission' ), $endpoint['permission_callback'] );
				$this->assertFalse( call_user_func( $endpoint['permission_callback'] ) );
				$this->assertIsArray( $endpoint['args'] );
			}
		}
	}

	/** A malicious saved setting cannot leak into settings or export responses. */
	public function test_keys_never_appear_in_settings_providers_or_export(): void {
		$secret = 'a_secret_api_key_value';
		$store  = new OptionStore();
		$store->install();
		$store->data[ Options::SETTINGS ] = array( 'api_key' => $secret );
		( new ApiKeyStore( new Crypto() ) )->set( 'claude', $secret );
		$api = $this->api();
		$this->assertStringNotContainsString( $secret, json_encode( $api->get_settings()->data ) );
		$this->assertStringNotContainsString( $secret, json_encode( $api->list_providers()->data ) );
		$this->assertStringNotContainsString( $secret, json_encode( $api->export_config()->data ) );
		$error = $api->put_key(
			new \WP_REST_Request(
				array(
					'id'  => 'claude',
					'key' => $secret . ' with spaces',
				)
			)
		);
		$this->assertInstanceOf( \WP_Error::class, $error );
		$this->assertStringNotContainsString( $secret, json_encode( $error ) );
	}

	/** Large import bodies are rejected before decoding. */
	public function test_json_import_over_one_megabyte_is_rejected(): void {
		$request = new class() extends \WP_REST_Request {
			public function get_body(): string {
				return str_repeat( 'x', 1048577 );
			}
		};
		$this->assertInstanceOf( \WP_Error::class, $this->api()->import_config( $request ) );
	}

	/** Invalid switcher color is rejected without altering settings. */
	public function test_invalid_color_is_rejected(): void {
		$request = new \WP_REST_Request( array( 'switcher' => array( 'colors' => array( 'bg' => 'red;url(foo)' ) ) ) );
		$this->assertInstanceOf( \WP_Error::class, $this->api()->put_settings( $request ) );
	}

	/** Partial updates preserve other settings and dedicated options. */
	public function test_partial_settings_update(): void {
		$api      = $this->api();
		$response = $api->put_settings(
			new \WP_REST_Request(
				array(
					'target_langs'     => array( 'en' ),
					'switcher'         => array( 'radius' => 12 ),
					'updates_disabled' => true,
				)
			)
		);
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( array( 'en' ), $response->data['settings']['target_langs'] );
		$this->assertSame( 12, $response->data['settings']['switcher']['radius'] );
		$this->assertSame( 'dropdown', $response->data['settings']['switcher']['variant'] );
		$this->assertSame( 'gb', $response->data['settings']['flags']['en'] );
		$this->assertTrue( $response->data['settings']['updates_disabled'] );
	}

	/** Shorter language lists replace the stored list instead of merging by index. */
	public function test_shorter_target_list_is_not_merged_by_index(): void {
		$api = $this->api();
		$api->put_settings( new \WP_REST_Request( array( 'target_langs' => array( 'en', 'es' ) ) ) );
		$response = $api->put_settings(
			new \WP_REST_Request(
				array(
					'source_lang'  => 'es',
					'target_langs' => array( 'fr' ),
				)
			)
		);
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame( 'es', $response->data['settings']['source_lang'] );
		$this->assertSame( array( 'fr' ), $response->data['settings']['target_langs'] );
	}

	/** A valid JSON configuration imports its settings and glossary. */
	public function test_imports_valid_configuration(): void {
		$body     = json_encode(
			array(
				'format'   => 'omnium-translate-config',
				'version'  => 1,
				'settings' => array( 'target_langs' => array( 'en' ) ),
				'glossary' => array(
					array(
						'term'        => 'Brand',
						'target_lang' => 'en',
						'translation' => '',
						'rule'        => 'keep',
					),
				),
			)
		);
		$request  = new class( $body ) extends \WP_REST_Request {
			public function __construct( private string $body ) {
				parent::__construct();
			}
			public function get_body(): string {
				return $this->body;
			}
		};
		$response = $this->api()->import_config( $request );
		$this->assertInstanceOf( \WP_REST_Response::class, $response );
		$this->assertSame(
			array(
				'imported' => true,
				'glossary' => 1,
			),
			$response->data
		);
	}

	/** Wizard completion persists without inserting a menu when unrequested. */
	public function test_completes_wizard(): void {
		$api      = $this->api();
		$response = $api->complete_wizard( new \WP_REST_Request( array( 'insert_menu' => false ) ) );
		$this->assertSame(
			array(
				'wizard_done'   => true,
				'menu_inserted' => false,
			),
			$response->data
		);
		$this->assertTrue( get_option( Options::WIZARD_DONE ) );
	}

	/** Build the real service graph without network access. */
	private function api(): Administration {
		$settings  = new Settings();
		$keys      = new ApiKeyStore( new Crypto() );
		$providers = new ProviderRegistry();
		$providers->add( new ClaudeProvider( $keys, $settings, new PromptBuilder(), new ResponseValidator() ) );
		$state           = new QueueState();
		$lock            = new RunLock();
		$words           = new WordCap( $settings );
		$wpdb            = new \wpdb();
		$GLOBALS['wpdb'] = $wpdb; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Test database fixture.
		return new Administration(
			$settings,
			$providers,
			new Pricing(),
			$keys,
			new GlossaryRepository( $wpdb ),
			new ActionSchedulerQueue( $settings, $lock, $words, new HourlyQuota( $settings ), $providers, new SpyLogger() ),
			$state,
			$lock,
			$words,
			new PublicContent(),
			new TranslationCoverage( $wpdb, $settings ),
			$wpdb
		);
	}
}
