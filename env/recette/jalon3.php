<?php
/**
 * Jalon 3 acceptance checks run inside a wp-env site:
 * npx wp-env run cli wp eval-file wp-content/plugins/omnium-translate/env/recette/jalon3.php <post_id>
 *
 * Prints one "OK|KO <check>" line per check. Never commit keys or client data here.
 *
 * @package Omnium\Translate
 */

// phpcs:ignoreFile -- test fixture executed with wp eval-file, not plugin code.

$omtr_post_id = isset( $args[0] ) ? (int) $args[0] : 0;
$omtr_results = array();

$omtr_check = static function ( string $label, bool $ok ) use ( &$omtr_results ): void {
	$omtr_results[] = ( $ok ? 'OK ' : 'KO ' ) . $label;
};

$omtr_rest = static function ( string $method, string $route, array $params = array() ): WP_REST_Response {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	return rest_ensure_response( rest_do_request( $request ) );
};

// Access control: anonymous and low-privilege users are refused on every route.
$omtr_routes = array(
	array( 'GET', '/omtr/v1/glossary' ),
	array( 'GET', '/omtr/v1/segments' ),
	array( 'POST', '/omtr/v1/translate', array( 'post_id' => $omtr_post_id ) ),
	array( 'POST', '/omtr/v1/crawl' ),
	array( 'POST', '/omtr/v1/queue/pause' ),
	array( 'GET', '/omtr/v1/glossary/export' ),
);

wp_set_current_user( 0 );
foreach ( $omtr_routes as $route ) {
	$status = $omtr_rest( $route[0], $route[1], $route[2] ?? array() )->get_status();
	$omtr_check( "anonyme refusé {$route[0]} {$route[1]} ({$status})", in_array( $status, array( 401, 403 ), true ) );
}

$omtr_subscriber = get_user_by( 'login', 'omtr_recette_abonne' );
if ( ! $omtr_subscriber ) {
	$omtr_subscriber = get_user_by( 'id', wp_create_user( 'omtr_recette_abonne', wp_generate_password(), 'abonne@example.test' ) );
}
wp_set_current_user( $omtr_subscriber->ID );
foreach ( $omtr_routes as $route ) {
	$status = $omtr_rest( $route[0], $route[1], $route[2] ?? array() )->get_status();
	$omtr_check( "abonné refusé {$route[0]} {$route[1]} ({$status})", 403 === $status );
}

$omtr_editor = get_user_by( 'login', 'omtr_recette_editeur' );
if ( ! $omtr_editor ) {
	$omtr_editor = get_user_by( 'id', wp_create_user( 'omtr_recette_editeur', wp_generate_password(), 'editeur@example.test' ) );
	$omtr_editor->set_role( 'editor' );
}
wp_set_current_user( $omtr_editor->ID );
$status = $omtr_rest( 'GET', '/omtr/v1/glossary' )->get_status();
$omtr_check( "éditeur sans omtr_manage refusé ({$status})", 403 === $status );

// Administrator.
$omtr_admin = get_user_by( 'login', 'admin' );
wp_set_current_user( $omtr_admin->ID );
$omtr_check( 'admin possède omtr_manage', current_user_can( 'omtr_manage' ) );
$omtr_check( 'admin GET /glossary 200', 200 === $omtr_rest( 'GET', '/omtr/v1/glossary' )->get_status() );
$omtr_check( 'admin GET /segments 200', 200 === $omtr_rest( 'GET', '/omtr/v1/segments' )->get_status() );
$status = $omtr_rest( 'GET', '/omtr/v1/segments', array( 'orderby' => 'source_text; DROP TABLE wp_users' ) )->get_status();
$omtr_check( "tri hors liste blanche refusé ({$status})", 400 === $status );

// Segment editor: XSS in a manual edit is neutralised whatever the role.
global $wpdb;
$omtr_segment_id = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i ORDER BY id DESC LIMIT 1', $wpdb->prefix . 'omtr_segments' ) );
if ( $omtr_segment_id > 0 ) {
	$response = $omtr_rest(
		'PUT',
		'/omtr/v1/segments/' . $omtr_segment_id,
		array(
			'target_lang' => 'en',
			'text'        => 'Bonjour <script>alert(1)</script><img src=x onerror=alert(1)><a href="javascript:alert(1)">x</a>',
		)
	);
	$stored = (string) $wpdb->get_var(
		$wpdb->prepare(
			'SELECT text FROM %i WHERE segment_id = %d AND target_lang = %s',
			$wpdb->prefix . 'omtr_translations',
			$omtr_segment_id,
			'en'
		)
	);
	$omtr_check( 'édition REST acceptée (' . $response->get_status() . ')', 200 === $response->get_status() );
	$omtr_check( 'édition REST : script/onerror/javascript: neutralisés', '' !== $stored && false === stripos( $stored, '<script' ) && false === stripos( $stored, 'onerror' ) && false === stripos( $stored, 'javascript:' ) );
	$status = $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE segment_id = %d AND target_lang = %s', $wpdb->prefix . 'omtr_translations', $omtr_segment_id, 'en' ) );
	$omtr_check( "édition => statut edited ({$status})", 'edited' === $status );
} else {
	$omtr_check( 'au moins un segment en base pour tester l\'éditeur', false );
}

// Row action and admin bar for the admin only.
$omtr_post   = get_post( $omtr_post_id );
$omtr_action = apply_filters( 'page' === $omtr_post->post_type ? 'page_row_actions' : 'post_row_actions', array(), $omtr_post );
$omtr_check( 'action rapide « Translate » présente pour l\'admin', (bool) preg_grep( '/admin-post\.php\?action=omtr_translate_post.*_wpnonce=/', $omtr_action ) );
wp_set_current_user( $omtr_editor->ID );
$omtr_action = apply_filters( 'page' === $omtr_post->post_type ? 'page_row_actions' : 'post_row_actions', array(), $omtr_post );
$omtr_check( 'action rapide absente pour un éditeur', ! preg_grep( '/omtr_translate_post/', $omtr_action ) );

foreach ( $omtr_results as $line ) {
	WP_CLI::log( $line );
}
$omtr_failures = count( preg_grep( '/^KO /', $omtr_results ) );
WP_CLI::log( sprintf( 'Bilan : %d contrôles, %d échec(s).', count( $omtr_results ), $omtr_failures ) );
