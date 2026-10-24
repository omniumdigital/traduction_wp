<?php
/** Acceptance fixture executed by WP-CLI inside wp-env only. */
defined( 'ABSPATH' ) || exit;

$stage = $args[0] ?? '';
$builder = $args[1] ?? 'gutenberg';
wp_set_current_user( get_user_by( 'login', 'admin' )->ID );
$rest = static function ( string $method, string $path, array $data = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, '/omtr/v1' . $path );
	foreach ( $data as $name => $value ) {
		$request->set_param( $name, $value );
	}
	$response = rest_ensure_response( rest_do_request( $request ) );
	WP_CLI::log( $method . ' ' . $path . '=' . $response->get_status() );
	return $response;
};

if ( 'seed' === $stage ) {
	$sample_slug = 'oxygen' === $builder ? 'atelier-oxygen' : 'atelier-ceramique';
	$sample = get_page_by_path( $sample_slug );
	$prefix = function_exists( '\\Breakdance\\BreakdanceOxygen\\Strings\\__bdox' ) ? \Breakdance\BreakdanceOxygen\Strings\__bdox( '_meta_prefix' ) : '';
	for ( $i = 1; $i <= 30; ++$i ) {
		$slug = sprintf( 'recette-v1-%02d', $i );
		$prior = get_page_by_path( $slug );
		$id = wp_insert_post( array(
			'ID' => $prior ? $prior->ID : 0,
			'post_type' => 'page',
			'post_status' => 'publish',
			'post_title' => 'Recette publique numéro ' . $i,
			'post_name' => $slug,
			'post_content' => '<!-- wp:heading --><h2>Recette publique numéro ' . $i . '</h2><!-- /wp:heading --><!-- wp:paragraph --><p>Présentation de notre atelier et de ses services ' . $i . '.</p><!-- /wp:paragraph -->',
		), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		if ( $sample && 'elementor' === $builder ) {
			foreach ( array( '_elementor_data', '_elementor_edit_mode', '_elementor_template_type', '_elementor_version' ) as $key ) {
				update_post_meta( $id, $key, wp_slash( get_post_meta( $sample->ID, $key, true ) ) );
			}
		} elseif ( $sample && 'oxygen' === $builder && $prefix ) {
			\Breakdance\Data\set_meta( $id, $prefix . 'data', \Breakdance\Data\get_meta( $sample->ID, $prefix . 'data' ) );
		}
	}
	WP_CLI::log( 'PUBLIC_PAGES=' . count( get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'posts_per_page' => -1 ) ) ) );
} elseif ( 'wizard' === $stage ) {
	$rest( 'PUT', '/settings', array( 'source_lang' => 'fr', 'target_langs' => array( 'en', 'es' ), 'provider' => 'claude' ) );
	$rest( 'PUT', '/providers/claude/key', array( 'key' => 'local-mock-' . str_repeat( 'x', 20 ) ) );
	$test = $rest( 'POST', '/providers/claude/test' );
	WP_CLI::log( 'KEY_TEST=' . wp_json_encode( $test->get_data() ) );
	$rest( 'POST', '/estimate', array( 'all' => true ) );
	$complete = $rest( 'POST', '/wizard/complete', array( 'insert_menu' => true ) );
	WP_CLI::log( 'WIZARD=' . wp_json_encode( $complete->get_data() ) );
	$rest( 'POST', '/translate/all' );
} elseif ( 'retranslate' === $stage ) {
	$rest( 'POST', '/translate/all' );
} elseif ( 'edited' === $stage ) {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT segment_id FROM %i WHERE target_lang = %s AND status = %s LIMIT 1', $wpdb->prefix . 'omtr_translations', 'en', 'auto' ) );
	if ( ! $id ) {
		WP_CLI::error( 'No automatic translation exists.' );
	}
	$rest( 'PUT', '/segments/' . $id, array( 'target_lang' => 'en', 'text' => '[EN] Correction manuelle stable' ) );
	WP_CLI::log( 'EDITED_ID=' . $id );
} elseif ( 'purge' === $stage ) {
	$rest( 'PUT', '/settings', array( 'uninstall_purge' => true ) );
}
