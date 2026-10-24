<?php
/**
 * Final v1 acceptance fixture, executed by WP-CLI inside wp-env only.
 *
 * Every stage prints stable KEY=VALUE lines parsed by v1.sh.
 *
 * @package Omnium\Translate
 */

defined( 'ABSPATH' ) || exit;

$stage   = $args[0] ?? '';
$builder = $args[1] ?? 'gutenberg';
wp_set_current_user( get_user_by( 'login', 'admin' )->ID );

$rest = static function ( string $method, string $path, array $data = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, '/omtr/v1' . $path );
	foreach ( $data as $name => $value ) {
		$request->set_param( $name, $value );
	}
	$response = rest_ensure_response( rest_do_request( $request ) );
	WP_CLI::log( 'REST ' . $method . ' ' . $path . '=' . $response->get_status() );
	return $response;
};

$slug_for = static fn( int $i ): string => sprintf( 'recette-v1-%02d', $i );

if ( 'seed' === $stage ) {
	// Deterministic starting state: fresh wizard, zero mock calls, empty log.
	update_option( 'omtr_mock_calls', 0, false );
	delete_option( 'omtr_wizard_done' );
	update_option( 'omtr_uninstall_purge', false, false );
	@file_put_contents( WP_CONTENT_DIR . '/debug.log', 'recette-v1 start ' . gmdate( 'c' ) . "\n" );

	// Main navigation menu with the switcher marker item, assigned everywhere.
	$menu = wp_get_nav_menu_object( 'Menu principal' );
	if ( ! $menu ) {
		$menu_id = wp_create_nav_menu( 'Menu principal' );
		$menu    = wp_get_nav_menu_object( $menu_id );
	}
	$has_marker = false;
	foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
		if ( '#omtr-switcher' === $item->url ) {
			$has_marker = true;
			break;
		}
	}
	if ( ! $has_marker ) {
		wp_update_nav_menu_item(
			$menu->term_id,
			0,
			array(
				'menu-item-title'  => 'Sélecteur de langue',
				'menu-item-url'    => '#omtr-switcher',
				'menu-item-status' => 'publish',
				'menu-item-type'   => 'custom',
			)
		);
	}
	if ( ! wp_get_nav_menu_items( $menu->term_id ) || 1 >= count( (array) wp_get_nav_menu_items( $menu->term_id ) ) ) {
		wp_update_nav_menu_item( $menu->term_id, 0, array( 'menu-item-title' => 'Accueil', 'menu-item-url' => home_url( '/' ), 'menu-item-status' => 'publish', 'menu-item-type' => 'custom' ) );
		$sample_page = get_page_by_path( $slug_for( 1 ) );
		if ( $sample_page ) {
			wp_update_nav_menu_item( $menu->term_id, 0, array( 'menu-item-title' => 'Atelier', 'menu-item-object' => 'page', 'menu-item-object-id' => $sample_page->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish' ) );
		}
	}
	$locations = get_theme_mod( 'nav_menu_locations', array() );
	foreach ( array_keys( get_registered_nav_menus() ) as $location ) {
		$locations[ $location ] = $menu->term_id;
	}
	set_theme_mod( 'nav_menu_locations', $locations );

	$sample_slug = 'oxygen' === $builder ? 'atelier-oxygen' : 'atelier-ceramique';
	$sample      = get_page_by_path( $sample_slug );
	$meta_prefix = defined( 'BREAKDANCE_MODE' ) && function_exists( '\\Breakdance\\BreakdanceOxygen\\Strings\\__bdox' )
		? \Breakdance\BreakdanceOxygen\Strings\__bdox( '_meta_prefix' )
		: '';

	for ( $i = 1; $i <= 30; ++$i ) {
		$slug   = $slug_for( $i );
		$prior  = get_page_by_path( $slug );
		$suffix = '— édition ' . $i;
		$id     = wp_insert_post(
			array(
				'ID'          => $prior ? $prior->ID : 0,
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Recette publique numéro ' . $i,
				'post_name'   => $slug,
				'post_content' => '<!-- wp:heading --><h2>Recette publique numéro ' . $i . '</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Présentation de notre atelier et de ses services ' . $i . '.</p><!-- /wp:paragraph -->',
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}

		if ( $sample && 'elementor' === $builder ) {
			$data = json_decode( (string) get_post_meta( $sample->ID, '_elementor_data', true ), true );
			if ( is_array( $data ) ) {
				$mark = static function ( array &$elements ) use ( &$mark, $suffix ): void {
					foreach ( $elements as &$element ) {
						$type = $element['widgetType'] ?? '';
						if ( 'heading' === $type && isset( $element['settings']['title'] ) ) {
							$element['settings']['title'] .= ' ' . $suffix;
						} elseif ( 'text-editor' === $type && isset( $element['settings']['editor'] ) ) {
							$element['settings']['editor'] = preg_replace( '~</p>$~', ' ' . $suffix . '</p>', (string) $element['settings']['editor'] ) ?? $element['settings']['editor'];
						}
						if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
							$mark( $element['elements'] );
						}
					}
					unset( $element );
				};
				$mark( $data );
				update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
			}
			foreach ( array( '_elementor_edit_mode', '_elementor_template_type', '_elementor_version' ) as $key ) {
				update_post_meta( $id, $key, wp_slash( (string) get_post_meta( $sample->ID, $key, true ) ) );
			}
		} elseif ( $sample && 'oxygen' === $builder && $meta_prefix ) {
			$tree = \Breakdance\Data\get_meta( $sample->ID, $meta_prefix . 'data' );
			if ( is_array( $tree ) && isset( $tree['tree_json_string'] ) ) {
				$decoded = json_decode( (string) $tree['tree_json_string'], true );
				if ( is_array( $decoded ) ) {
					$mark = static function ( array &$nodes ) use ( &$mark, $suffix ): void {
						foreach ( $nodes as &$node ) {
							$text = $node['data']['properties']['content']['content']['text'] ?? null;
							if ( is_string( $text ) && '' !== $text && 'OxygenElements\Text' === ( $node['data']['type'] ?? '' ) ) {
								$node['data']['properties']['content']['content']['text'] = preg_replace( '~</?p[^>]*>~', '', $text ) . ' ' . $suffix;
							}
							if ( ! empty( $node['children'] ) && is_array( $node['children'] ) ) {
								$mark( $node['children'] );
							}
						}
						unset( $node );
					};
					$mark( $decoded );
					\Breakdance\Data\set_meta( $id, $meta_prefix . 'data', array( 'tree_json_string' => wp_json_encode( $decoded ) ) );
				}
			}
		}
	}

	// WooCommerce fixture products on the sites that run WooCommerce.
	$products = 0;
	if ( class_exists( 'WC_Product_Simple' ) ) {
		for ( $p = 1; $p <= 5; ++$p ) {
			$slug     = sprintf( 'recette-v1-produit-%02d', $p );
			$existing = get_page_by_path( $slug, OBJECT, array( 'product' ) );
			if ( $existing instanceof WP_Post ) {
				++$products;
				continue;
			}
			$product = new WC_Product_Simple();
			$product->set_name( 'Produit de recette ' . $p );
			$product->set_slug( $slug );
			$product->set_status( 'publish' );
			$product->set_description( 'Description française du produit de recette numéro ' . $p . ', tournée dans notre atelier.' );
			$product->set_short_description( 'Produit de recette ' . $p . '.' );
			$product->set_regular_price( 10 + $p );
			$product->set_stock_status( 'instock' );
			$product->save();
			++$products;
		}
	}

	// Oxygen-only: dedicated page composed with the Omnium switcher element.
	if ( 'oxygen' === $builder && $meta_prefix ) {
		$existing = get_page_by_path( 'recette-v1-selecteur' );
		$page_id  = $existing ? $existing->ID : wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'Sélecteur de langue de la recette',
				'post_name'   => 'recette-v1-selecteur',
				'post_content' => '',
			)
		);
		$node     = static fn( int $id, string $type, array $properties, array $children, int $parent ): array => array(
			'id'       => $id,
			'data'     => array( 'type' => $type, 'properties' => $properties ),
			'children' => $children,
			'_parentId' => $parent,
		);
		$text     = static fn( int $id, string $content, string $tag, int $parent ): array => $node(
			$id,
			'OxygenElements\Text',
			array(
				'content'  => array( 'content' => array( 'text' => $content ) ),
				'settings' => array( 'advanced' => array( 'tag' => $tag ) ),
			),
			array(),
			$parent
		);
		$switcher = $node(
			201,
			'Omnium\Translate\Switcher\OxygenElement\LanguageSwitcher',
			array( 'variant' => 'dropdown', 'display' => 'flag-name' ),
			array(),
			200
		);
		$tree     = array(
			'root'        => array(
				'id'       => 1,
				'data'     => array( 'type' => 'root', 'properties' => (object) array() ),
				'children' => array(
					$node(
						200,
						'OxygenElements\Container',
						array( 'settings' => array( 'advanced' => array( 'tag' => 'main', 'attributes' => array( array( 'name' => 'id', 'value' => 'selecteur' ) ) ) ) ),
						array(
							$text( 202, 'Page composée pour la recette : le sélecteur ci-dessous vient de l’élément Oxygen Omnium.', 'h2', 200 ),
							$switcher,
						),
						1
					),
				),
			),
			'_nextNodeId' => 203,
			'status'      => 'exported',
		);
		\Breakdance\Data\set_meta( $page_id, $meta_prefix . 'data', array( 'tree_json_string' => wp_json_encode( $tree ) ) );
		\Breakdance\Render\generateCacheForPost( (int) $page_id );
		WP_CLI::line( 'SWITCHER_PAGE=' . $page_id );
	}

	wp_cache_flush();
	WP_CLI::line( 'PUBLIC_PAGES=' . count( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) ) ) );
	WP_CLI::line( 'PRODUCTS=' . $products );
	WP_CLI::line( 'MENU_ID=' . $menu->term_id );
} elseif ( 'wizard' === $stage ) {
	$rest( 'PUT', '/settings', array( 'source_lang' => 'fr', 'target_langs' => array( 'en', 'es' ), 'provider' => 'claude' ) );
	foreach ( array( 'claude', 'openai', 'mistral', 'gemini' ) as $provider ) {
		$rest( 'PUT', '/providers/' . $provider . '/key', array( 'key' => 'local-mock-' . str_repeat( 'x', 20 ) ) );
		$test = $rest( 'POST', '/providers/' . $provider . '/test' );
		$body = $test->get_data();
		WP_CLI::line( 'KEY_TEST_' . strtoupper( $provider ) . '=' . ( empty( $body['ok'] ) ? 'ko:' . ( $body['message'] ?? '' ) : 'ok' ) );
	}
	$estimate = $rest( 'POST', '/estimate', array( 'all' => true ) );
	$data     = $estimate->get_data();
	WP_CLI::line( 'ESTIMATE=' . wp_json_encode( array_intersect_key( is_array( $data ) ? $data : array(), array_flip( array( 'pages', 'segments', 'words', 'cost', 'currency' ) ) ) ) );
	$complete = $rest( 'POST', '/wizard/complete', array( 'insert_menu' => true ) );
	$body     = $complete->get_data();
	WP_CLI::line( 'WIZARD=' . wp_json_encode( $body ) );
	$all = $rest( 'POST', '/translate/all' );
	WP_CLI::line( 'TRANSLATE_ALL=' . wp_json_encode( $all->get_data() ) );
} elseif ( 'coverage' === $stage ) {
	foreach ( array( 'en', 'es' ) as $lang ) {
		delete_transient( 'omtr_translation_coverage_' . md5( $lang . '|fr' ) );
	}
	global $wpdb;
	$coverage = \Omnium\Translate\Core\Plugin::instance()->container()->get( \Omnium\Translate\Seo\TranslationCoverage::class );
	WP_CLI::line( 'COVERAGE_EN=' . $coverage->percentage( 'en' ) );
	WP_CLI::line( 'COVERAGE_ES=' . $coverage->percentage( 'es' ) );
	WP_CLI::line( 'SEGMENTS=' . (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'omtr_segments' ) );
	WP_CLI::line( 'FAILED=' . (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $wpdb->prefix . 'omtr_translations WHERE status = ' . $wpdb->prepare( '%s', 'failed' ) ) );
	WP_CLI::line( 'MOCK_CALLS=' . (int) get_option( 'omtr_mock_calls', 0 ) );
} elseif ( 'edited' === $stage ) {
	global $wpdb;
	$table  = $wpdb->prefix . 'omtr_translations';
	$row    = $wpdb->get_row( $wpdb->prepare( "SELECT t.id, t.segment_id FROM {$table} t WHERE t.target_lang = %s AND t.status = %s AND t.text LIKE %s LIMIT 1", 'en', 'auto', '[EN]%' ), ARRAY_A );
	if ( ! $row ) {
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT t.id, t.segment_id FROM {$table} t WHERE t.target_lang = %s AND t.status = %s LIMIT 1", 'en', 'auto' ), ARRAY_A );
	}
	if ( ! $row ) {
		WP_CLI::error( 'No automatic translation found.' );
	}
	$correction = '[EN] Correction manuelle stable';
	$rest( 'PUT', '/segments/' . (int) $row['segment_id'], array( 'target_lang' => 'en', 'text' => $correction ) );
	WP_CLI::line( 'EDITED_ID=' . (int) $row['segment_id'] );
	WP_CLI::line( 'EDITED_TEXT=' . $correction );
} elseif ( 'edited-check' === $stage ) {
	$segment_id = (int) ( $args[2] ?? 0 );
	$correction = (string) ( $args[3] ?? '' );
	global $wpdb;
	$table = $wpdb->prefix . 'omtr_translations';
	$row   = $wpdb->get_row( $wpdb->prepare( "SELECT status, text FROM {$table} WHERE segment_id = %d AND target_lang = %s", $segment_id, 'en' ), ARRAY_A );
	WP_CLI::line( 'EDITED_STATUS=' . ( $row['status'] ?? 'missing' ) );
	WP_CLI::line( 'EDITED_PRESERVED=' . ( is_array( $row ) && $correction === $row['text'] ? 'ok' : 'ko:' . ( $row['text'] ?? '' ) ) );
} elseif ( 'retranslate' === $stage ) {
	$rest( 'POST', '/translate/all' );
} elseif ( 'email' === $stage ) {
	if ( ! class_exists( 'WC_Email_Customer_Processing_Order' ) || ! function_exists( 'wc_create_order' ) ) {
		WP_CLI::error( 'WooCommerce is not active on this site.' );
	}
	$product = get_page_by_path( 'recette-v1-produit-01', OBJECT, array( 'product' ) );
	if ( ! $product ) {
		$product = get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'posts_per_page' => 1 ) )[0] ?? null;
	}
	if ( ! $product ) {
		WP_CLI::error( 'No product available for the order.' );
	}
	$order = wc_create_order();
	$order->add_product( wc_get_product( $product->ID ), 2 );
	$order->set_address(
		array(
			'first_name' => 'Test',
			'last_name'  => 'Recette',
			'email'      => 'client@example.test',
			'address_1'  => '1 rue de la Recette',
			'city'       => 'Lyon',
			'postcode'   => '69001',
			'country'    => 'FR',
		),
		'billing'
	);
	$order->update_meta_data( '_omtr_order_language', 'en' );
	$order->calculate_totals();
	$order->save();

	$mailer = WC()->mailer();
	$email  = $mailer->emails['WC_Email_Customer_Processing_Order'] ?? null;
	if ( ! is_object( $email ) ) {
		WP_CLI::error( 'Customer processing email unavailable.' );
	}
	$email->object = $order;
	WP_CLI::line( 'ORDER_ID=' . $order->get_id() );
	WP_CLI::line( 'EMAIL_SUBJECT=' . $email->get_subject() );
	WP_CLI::line( 'EMAIL_HEADING=' . $email->get_heading() );
} elseif ( 'purge' === $stage ) {
	$rest( 'PUT', '/settings', array( 'uninstall_purge' => true ) );
} elseif ( 'reset-wizard' === $stage ) {
	update_option( 'omtr_wizard_done', false, false );
	WP_CLI::line( 'WIZARD_RESET=ok' );
} elseif ( 'traces' === $stage ) {
	global $wpdb;
	$tables    = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'omtr_' ) . '%' ) );
	$options   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( 'omtr_' ) . '%', $wpdb->esc_like( '_transient_omtr_' ) . '%' ) );
	$meta      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_omtr_' ) . '%' ) );
	$as_table  = $wpdb->prefix . 'actionscheduler_actions';
	$actions   = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $as_table ) ) ? (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$as_table} WHERE hook LIKE %s", $wpdb->esc_like( 'omtr_' ) . '%' ) ) : -1;
	$mock      = get_option( 'omtr_mock_calls' );
	$menu_item = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", '_menu_item_url', '#omtr-switcher' ) );
	WP_CLI::line( "TRACES_TABLES={$tables}" );
	WP_CLI::line( "TRACES_OPTIONS={$options}" );
	WP_CLI::line( "TRACES_META={$meta}" );
	WP_CLI::line( "TRACES_ACTIONS={$actions}" );
	WP_CLI::line( 'TRACES_MOCK_OPTION=' . ( null === $mock ? 'absent' : 'present' ) );
	WP_CLI::line( "TRACES_MENU_ITEMS={$menu_item}" );
} else {
	WP_CLI::error( "Unknown stage: {$stage}" );
}
