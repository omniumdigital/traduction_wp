<?php
/**
 * Reproducible Oxygen 6, WooCommerce and Rank Math fixture for manual testing.
 *
 * Run through seed.sh; this file executes inside a fully booted WordPress CLI.
 *
 * @package Omnium\Translate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Oxygen tree node.
 *
 * @param int                  $id         Node id.
 * @param string               $type       Oxygen element class.
 * @param array<string, mixed> $properties Element properties.
 * @param list<array<string, mixed>> $children Child nodes.
 * @param int                  $parent_id  Parent node id.
 * @return array<string, mixed>
 */
function omtr_oxygen_node( int $id, string $type, array $properties, array $children, int $parent_id ): array {
	return array(
		'id'        => $id,
		'data'      => array(
			'type'       => $type,
			'properties' => $properties,
		),
		'children'  => $children,
		'_parentId' => $parent_id,
	);
}

/**
 * Shared advanced properties for semantic tags and inline fixture styling.
 *
 * @param string $tag   HTML tag.
 * @param string $style Inline CSS.
 * @param array<int, array{name:string,value:string}> $attributes Extra attributes.
 * @return array<string, mixed>
 */
function omtr_oxygen_settings( string $tag, string $style = '', array $attributes = array() ): array {
	if ( '' !== $style ) {
		$attributes[] = array( 'name' => 'style', 'value' => $style );
	}

	return array(
		'settings' => array(
			'advanced' => array(
				'tag'        => $tag,
				'attributes' => $attributes,
			),
		),
	);
}

/**
 * Text node.
 *
 * @param int    $id        Node id.
 * @param string $text      Text or allowed inline HTML.
 * @param string $tag       HTML tag.
 * @param int    $parent_id Parent id.
 * @param string $style     Inline CSS.
 * @return array<string, mixed>
 */
function omtr_oxygen_text( int $id, string $text, string $tag, int $parent_id, string $style = '' ): array {
	$properties = array_merge_recursive(
		array( 'content' => array( 'content' => array( 'text' => $text ) ) ),
		omtr_oxygen_settings( $tag, $style )
	);

	return omtr_oxygen_node( $id, 'OxygenElements\\Text', $properties, array(), $parent_id );
}

/**
 * Link node.
 *
	 * @param int    $id        Node id.
	 * @param string $text      Label.
	 * @param string $url       URL.
	 * @param int    $parent_id Parent id.
	 * @param string $style     Inline CSS.
	 * @return array<string, mixed>
 */
function omtr_oxygen_link( int $id, string $text, string $url, int $parent_id, string $style = '' ): array {
	$properties = array_merge_recursive(
		array( 'content' => array( 'content' => array( 'text' => $text, 'url' => $url ) ) ),
		omtr_oxygen_settings( 'a', $style )
	);

	return omtr_oxygen_node( $id, 'OxygenElements\\TextLink', $properties, array(), $parent_id );
}

/**
 * Image node using a local upload URL and a custom alternative text.
 *
	 * @param int    $id        Node id.
	 * @param string $url       Image URL.
	 * @param string $alt       Alternative text.
	 * @param int    $parent_id Parent id.
	 * @return array<string, mixed>
 */
function omtr_oxygen_image( int $id, string $url, string $alt, int $parent_id ): array {
	return omtr_oxygen_node(
		$id,
		'OxygenElements\\Image',
		array(
			'content'  => array(
				'image' => array(
					'from'                     => 'url',
					'url'                      => $url,
					'alt'                      => 'custom',
					'alt_when_from_url'        => 'custom',
					'custom_alt_when_from_url' => $alt,
					'lazy_load'                => true,
				),
			),
			'settings' => array(
				'advanced' => array(
					'attributes' => array(
						array( 'name' => 'style', 'value' => 'width:100%;height:180px;object-fit:cover;border-radius:12px;background:#dbeafe' ),
					),
				),
			),
		),
		array(),
		$parent_id
	);
}

// Recreate only fixture-owned content.
$fixture_slugs = array( 'atelier-oxygen', 'produit-recette-oxygen' );
foreach ( $fixture_slugs as $fixture_slug ) {
	$existing = get_page_by_path( $fixture_slug, OBJECT, array( 'page', 'product' ) );
	if ( $existing instanceof WP_Post ) {
		wp_delete_post( $existing->ID, true );
	}
}
foreach ( get_posts( array( 'post_type' => 'oxygen_template', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => '_omtr_fixture', 'meta_value' => 'oxygen' ) ) as $template ) {
	wp_delete_post( $template->ID, true );
}

// Tiny local PNG assets: deterministic, no external front-end request.
$upload = wp_upload_dir();
$png    = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLhJAAAAABJRU5ErkJggg==', true );
$image_urls = array();
foreach ( array( 'strategie', 'design', 'croissance' ) as $image_name ) {
	$file_name = 'omtr-' . $image_name . '.png';
	$file_path = trailingslashit( $upload['path'] ) . $file_name;
	if ( is_string( $png ) ) {
		WP_Filesystem();
		global $wp_filesystem;
		$wp_filesystem->put_contents( $file_path, $png, FS_CHMOD_FILE );
	}
	$image_urls[] = trailingslashit( $upload['url'] ) . $file_name;
}

$header = omtr_oxygen_node(
	10,
	'OxygenElements\\Container',
	omtr_oxygen_settings( 'header', 'display:flex;align-items:center;justify-content:space-between;padding:24px 6%;background:#0f172a;color:#fff' ),
	array(
		omtr_oxygen_text( 11, 'Atelier Horizon', 'strong', 10, 'font-size:22px' ),
		omtr_oxygen_node(
			12,
			'OxygenElements\\Container',
			omtr_oxygen_settings( 'nav', 'display:flex;gap:24px', array( array( 'name' => 'aria-label', 'value' => 'Navigation principale' ) ) ),
			array(
				omtr_oxygen_link( 13, 'Accueil', '/atelier-oxygen/', 12, 'color:#fff' ),
				omtr_oxygen_link( 14, 'Nos services', '/atelier-oxygen/#services', 12, 'color:#fff' ),
				omtr_oxygen_link( 15, 'Boutique', '/boutique/', 12, 'color:#fff' ),
			),
			10
		),
	),
	1
);

$hero = omtr_oxygen_node(
	20,
	'OxygenElements\\Container',
	omtr_oxygen_settings( 'section', 'padding:90px 8%;text-align:center;background:#eff6ff' ),
	array(
		omtr_oxygen_text( 21, 'Donnez de l’élan à vos idées', 'h1', 20, 'font-size:52px;line-height:1.1;margin:0 0 20px' ),
		omtr_oxygen_text( 22, 'Une équipe créative accompagne les entreprises ambitieuses de la stratégie au lancement.', 'p', 20, 'font-size:20px;max-width:760px;margin:0 auto 28px' ),
		omtr_oxygen_link( 23, 'Découvrir notre approche', '/atelier-oxygen/#services', 20, 'display:inline-block;padding:14px 22px;background:#2563eb;color:#fff;border-radius:8px' ),
	),
	30
);

$card_data = array(
	array( 'Stratégie claire', 'Nous transformons vos objectifs en feuille de route concrète.', 'Équipe réunie autour d’une stratégie', $image_urls[0] ),
	array( 'Design utile', 'Chaque interface est pensée pour être simple, accessible et mémorable.', 'Maquettes de design sur un bureau', $image_urls[1] ),
	array( 'Croissance durable', 'Nous mesurons, apprenons et améliorons chaque étape du parcours.', 'Graphique de croissance durable', $image_urls[2] ),
);
$cards = array();
foreach ( $card_data as $index => $card ) {
	$card_id = 42 + ( $index * 10 );
	$cards[] = omtr_oxygen_node(
		$card_id,
		'OxygenElements\\Container',
		omtr_oxygen_settings( 'article', 'padding:20px;border:1px solid #dbeafe;border-radius:16px;background:#fff' ),
		array(
			omtr_oxygen_image( $card_id + 1, $card[3], $card[2], $card_id ),
			omtr_oxygen_text( $card_id + 2, $card[0], 'h3', $card_id, 'font-size:24px;margin:18px 0 8px' ),
			omtr_oxygen_text( $card_id + 3, $card[1], 'p', $card_id, 'line-height:1.6' ),
		),
		72
	);
}
$services = omtr_oxygen_node(
	40,
	'OxygenElements\\Container',
	omtr_oxygen_settings( 'section', 'padding:72px 8%', array( array( 'name' => 'id', 'value' => 'services' ) ) ),
	array(
		omtr_oxygen_text( 41, 'Trois expertises, un même cap', 'h2', 40, 'font-size:38px;text-align:center;margin-bottom:36px' ),
		omtr_oxygen_node( 72, 'OxygenElements\\Container', omtr_oxygen_settings( 'div', 'display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px' ), $cards, 40 ),
	),
	30
);

$faq = omtr_oxygen_node(
	80,
	'OxygenElements\\Container',
	omtr_oxygen_settings( 'section', 'padding:72px 16%;background:#f8fafc' ),
	array(
		omtr_oxygen_text( 81, 'Questions fréquentes', 'h2', 80, 'font-size:38px' ),
		omtr_oxygen_node( 82, 'OxygenElements\\Container', omtr_oxygen_settings( 'details', 'padding:18px 0;border-bottom:1px solid #cbd5e1' ), array( omtr_oxygen_text( 83, 'Combien de temps dure un projet ?', 'summary', 82, 'font-weight:700' ), omtr_oxygen_text( 84, 'La plupart des accompagnements durent entre quatre et huit semaines.', 'p', 82 ) ), 80 ),
		omtr_oxygen_node( 85, 'OxygenElements\\Container', omtr_oxygen_settings( 'details', 'padding:18px 0;border-bottom:1px solid #cbd5e1' ), array( omtr_oxygen_text( 86, 'Travaillez-vous avec les petites équipes ?', 'summary', 85, 'font-weight:700' ), omtr_oxygen_text( 87, 'Oui, notre méthode s’adapte aux équipes agiles comme aux organisations établies.', 'p', 85 ) ), 80 ),
	),
	30
);

$form_html = '<form action="/atelier-oxygen/" method="post"><label for="omtr-name">Votre nom</label><input id="omtr-name" name="name" type="text" placeholder="Votre nom complet" value="Valeur privée non traduite"><label for="omtr-message">Votre message</label><textarea id="omtr-message" name="message" placeholder="Décrivez votre projet">Contenu privé non traduit</textarea><button type="submit">Envoyer la demande</button></form>';
$contact = omtr_oxygen_node(
	90,
	'OxygenElements\\Container',
	omtr_oxygen_settings( 'section', 'padding:72px 16%', array( array( 'name' => 'id', 'value' => 'contact' ) ) ),
	array(
		omtr_oxygen_text( 91, 'Parlons de votre projet', 'h2', 90, 'font-size:38px' ),
		omtr_oxygen_text( 92, 'Quelques lignes suffisent pour commencer la conversation.', 'p', 90 ),
		omtr_oxygen_node( 93, 'OxygenElements\\HtmlCode', array( 'content' => array( 'content' => array( 'html_code' => $form_html ) ) ), array(), 90 ),
	),
	30
);

$main = omtr_oxygen_node( 30, 'OxygenElements\\Container', omtr_oxygen_settings( 'main' ), array( $hero, $services, $faq, $contact ), 1 );
$footer = omtr_oxygen_node(
	100,
	'OxygenElements\\Container',
	omtr_oxygen_settings( 'footer', 'padding:36px 8%;background:#0f172a;color:#fff' ),
	array(
		omtr_oxygen_text( 101, 'Restons en contact', 'h2', 100 ),
		omtr_oxygen_text( 102, 'bonjour@atelier-horizon.test', 'p', 100 ),
		omtr_oxygen_text( 103, '+33 1 84 80 20 24', 'p', 100 ),
	),
	1
);

$page_tree = array(
	'root'        => array(
		'id'       => 1,
		'data'     => array( 'type' => 'root', 'properties' => (object) array() ),
		'children' => array( $header, $main, $footer ),
	),
	'_nextNodeId' => 104,
	'status'      => 'exported',
);

$oxygen_meta_prefix = \Breakdance\BreakdanceOxygen\Strings\__bdox( '_meta_prefix' );
$page_id            = wp_insert_post(
	array(
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'Atelier Horizon — Page Oxygen',
		'post_name'    => 'atelier-oxygen',
		'post_content' => '',
	)
);
\Breakdance\Data\set_meta( $page_id, $oxygen_meta_prefix . 'data', array( 'tree_json_string' => wp_json_encode( $page_tree ) ) );
update_post_meta( $page_id, 'rank_math_title', 'Atelier Horizon | Stratégie et design' );
update_post_meta( $page_id, 'rank_math_description', 'Atelier Horizon accompagne les entreprises de la stratégie au lancement avec une méthode claire et créative.' );
update_post_meta( $page_id, 'rank_math_facebook_title', 'Atelier Horizon — Donnez de l’élan à vos idées' );
update_post_meta( $page_id, 'rank_math_facebook_description', 'Découvrez notre approche en stratégie, design et croissance durable.' );
update_option( 'rank_math_is_configured', true );
update_option( 'rank_math_registration_skip', true );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_store_pages_only', 'no' );

$template_tree = array(
	'root'        => array(
		'id'       => 1,
		'data'     => array( 'type' => 'root', 'properties' => (object) array() ),
		'children' => array(
			omtr_oxygen_node( 2, 'OxygenElements\\TemplateContentArea', omtr_oxygen_settings( 'div' ), array(), 1 ),
		),
	),
	'_nextNodeId' => 3,
	'status'      => 'exported',
);
$template_id = wp_insert_post(
	array(
		'post_type'   => 'oxygen_template',
		'post_status' => 'publish',
		'post_title'  => 'OMTR — Toutes les pages',
	)
);
\Breakdance\Data\set_meta( $template_id, $oxygen_meta_prefix . 'data', array( 'tree_json_string' => wp_json_encode( $template_tree ) ) );
\Breakdance\Data\set_meta( $template_id, $oxygen_meta_prefix . 'template_settings', wp_json_encode( array( 'type' => 'everywhere', 'ruleGroups' => array(), 'priority' => 10, 'fallback' => true ) ) );
update_post_meta( $template_id, '_omtr_fixture', 'oxygen' );

if ( function_exists( 'wc_create_pages' ) ) {
	wc_create_pages();
}
$product = new WC_Product_Simple();
$product->set_name( 'Carnet Horizon' );
$product->set_slug( 'produit-recette-oxygen' );
$product->set_status( 'publish' );
$product->set_description( 'Un carnet sobre pour transformer les idées en prochaines étapes.' );
$product->set_short_description( 'Carnet relié, papier ivoire.' );
$product->set_regular_price( '24.00' );
$product->set_stock_status( 'instock' );
$product_id = $product->save();

update_option(
	'omtr_settings',
	array(
		'source_lang'      => 'fr',
		'target_langs'     => array( 'en', 'es' ),
		'provider'         => 'claude',
		'model'            => '',
		'auto_translate'   => false,
		'on_the_fly'       => false,
		'monthly_word_cap' => 0,
		'hourly_quota'     => 0,
		'noindex_partial'  => false,
		'pref_cookie'      => false,
		'site_tone'        => 'clair et chaleureux',
		'site_context'     => 'studio de stratégie et de design',
	),
	false
);

\Breakdance\Render\generateCacheForPost( $page_id );
\Breakdance\Render\generateCacheForPost( $template_id );

WP_CLI::success( sprintf( 'Page %d, template %d et produit %d recréés.', $page_id, $template_id, $product_id ) );
