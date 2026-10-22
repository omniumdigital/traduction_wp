#!/bin/sh
# Recette Elementor + WooCommerce : jeu de données reproductible.
#
# Prérequis : `npm ci --ignore-scripts && npm run env:elementor` (port 8882,
# compte admin/password). Le script est idempotent : relancer met à jour le
# contenu existant au lieu de le dupliquer.
#
# Usage :
#   ./env/elementor/seed.sh            # Yoast actif, Rank Math désactivé
#   SEO=rankmath ./env/elementor/seed.sh  # Rank Math actif, Yoast désactivé
#
# La page cible est construite par méta _elementor_data (mode builder) :
# hero (titre, texte, bouton), section 3 colonnes image+alt, accordéon FAQ,
# champ avec placeholder (valeur de formulaire incluse pour vérifier son
# exclusion), pied avec courriel et téléphone (exclus par le filtre PII).
set -eu

cd "$(dirname "$0")"

CLI='npx wp-env run cli'

# Le plugin local est monté sous le slug stable omnium-translate ; les plugins
# téléchargés peuvent toutefois porter un suffixe wp-env (*.latest-stable).
# Résoudre chaque slug depuis les entêtes évite toute dépendance au dossier de
# travail. Le nom du plugin est incorporé au code PHP (base64) car un argument
# contenant une espace ne survit pas au passage docker/compose de wp-env.
find_plugin() {
	NAME_B64=$(printf '%s' "$1" | base64 | tr -d '\n')
	$CLI wp eval "\$needle = base64_decode( '$NAME_B64' ); foreach ( get_plugins() as \$file => \$data ) { if ( 0 === strpos( (string) ( \$data[\"Name\"] ?? \"\" ), \$needle ) ) { echo dirname( \$file ); break; } }" 2>/dev/null | tr -d '\r'
}

OMTR_SLUG=$(find_plugin 'Omnium Translate')
ELEMENTOR_SLUG=$(find_plugin 'Elementor')
WOO_SLUG=$(find_plugin 'WooCommerce')
YOAST_SLUG=$(find_plugin 'Yoast SEO')
RANKMATH_SLUG=$(find_plugin 'Rank Math')
for required in "$OMTR_SLUG" "$ELEMENTOR_SLUG" "$WOO_SLUG" "$YOAST_SLUG" "$RANKMATH_SLUG"; do
	if [ -z "$required" ]; then
		echo "ERREUR : plugin attendu introuvable." >&2
		exit 1
	fi
done
echo "Plugins : omtr=$OMTR_SLUG elementor=$ELEMENTOR_SLUG woo=$WOO_SLUG yoast=$YOAST_SLUG rankmath=$RANKMATH_SLUG"

# ---------------------------------------------------------------- Environnement
$CLI wp rewrite structure '/%postname%/' --hard
$CLI wp plugin activate "$ELEMENTOR_SLUG" "$WOO_SLUG" "$OMTR_SLUG"

if [ "${SEO:-yoast}" = "rankmath" ]; then
	$CLI wp plugin deactivate "$YOAST_SLUG"
	$CLI wp plugin activate "$RANKMATH_SLUG"
	# Rank Math ne sort aucune meta tant que son assistant n'est pas finalisé :
	# appliquer la configuration minimale qu'un admin obtiendrait en le terminant
	# (inscription ignorée, drapeau is_configured, module OpenGraph actif,
	# réglages de titres).
	$CLI wp option update rank_math_registration_skip 1
	$CLI wp option update rank_math_is_configured 1
	$CLI wp eval 'update_option( "rank_math_modules", array_values( array_unique( array_merge( (array) get_option( "rank_math_modules", array() ), array( "opengraph" ) ) ) ) );'
	$CLI wp option update rank_math_titles '{"title_separator":"|","pt_page_title":"%title% %sep% %sitename%","pt_page_description":"","homepage_title":"%sitename%","noindex_empty_taxonomies":"on"}' --format=json
else
	$CLI wp plugin deactivate "$RANKMATH_SLUG"
	$CLI wp plugin activate "$YOAST_SLUG"
fi

# Réglages : source fr, cibles en et es, aucun déclencheur automatique.
$CLI wp option update omtr_settings \
	'{"source_lang":"fr","target_langs":["en","es"],"provider":"claude","model":"","auto_translate":false,"on_the_fly":false,"monthly_word_cap":0,"hourly_quota":0,"noindex_partial":false,"pref_cookie":false,"site_tone":"","site_context":""}' \
	--format=json

# ------------------------------------------------------------- Page Elementor
PAGE_TITLE='Atelier de céramique'
PAGE_SLUG='atelier-ceramique'

# Métadonnées SEO du plugin actif (title + description distincts par plugin).
YOAST_META='{"_yoast_wpseo_title":"Atelier de céramique artisanale à Lyon | pièces uniques","_yoast_wpseo_metadesc":"Bols, vases et assiettes en grès façonnés à la main dans notre atelier lyonnais depuis 1998."}'
RANKMATH_META='{"rank_math_title":"Céramiques artisanales de Lyon | atelier de grès","rank_math_description":"Découvrez nos collections de grès façonnées main, cuites au bois dans un atelier familial lyonnais."}'

# Structure Elementor 4.x : sections / colonnes / widgets. Les contenus
# embarquent les cas de recette : texte public, alt d'images, lien interne
# (/shop/), placeholder de champ, valeur de formulaire (Jean Dupont, à
# exclure), courriel et téléphone (à exclure par PiiFilter).
ELEMENTOR_DATA=$(cat <<'ELEMENTOR_JSON'
[{"id":"7a1e001","elType":"section","settings":{"gap":"extended","padding":{"unit":"px","top":"80","right":"20","bottom":"80","left":"20","isLinked":false}},"elements":[{"id":"7a1e002","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"7a1e003","elType":"widget","widgetType":"heading","settings":{"title":"Atelier de céramique artisanale à Lyon","header_size":"h1","align":"center"},"elements":[]},{"id":"7a1e004","elType":"widget","widgetType":"text-editor","settings":{"editor":"<p>Depuis 1998, nous façonnons chaque pièce à la main selon la tradition du grès français. Nos collections évoluent au fil des saisons et des cuissons au bois.</p>","align":"center"},"elements":[]},{"id":"7a1e005","elType":"widget","widgetType":"button","settings":{"text":"Découvrir nos collections","link":{"url":"/shop/","is_external":"","nofollow":"","custom_attributes":""},"align":"center","size":"lg"},"elements":[]}],"isInner":false}],"isInner":false},{"id":"7a1e010","elType":"section","settings":{"gap":"default"},"elements":[{"id":"7a1e011","elType":"column","settings":{"_column_size":33,"_inline_size":33.33},"elements":[{"id":"7a1e012","elType":"widget","widgetType":"image","settings":{"image":{"url":"/wp-content/uploads/2026/10/bol-gres-bleu.jpg","id":""},"image_custom_alt":"Bol en grès émaillé bleu nuit"},"elements":[]},{"id":"7a1e013","elType":"widget","widgetType":"heading","settings":{"title":"Nos bols","header_size":"h3","align":"center"},"elements":[]}],"isInner":false},{"id":"7a1e021","elType":"column","settings":{"_column_size":33,"_inline_size":33.33},"elements":[{"id":"7a1e022","elType":"widget","widgetType":"image","settings":{"image":{"url":"/wp-content/uploads/2026/10/vase-tourne-main.jpg","id":""},"image_custom_alt":"Vase en grès tourné à la main"},"elements":[]},{"id":"7a1e023","elType":"widget","widgetType":"heading","settings":{"title":"Nos vases","header_size":"h3","align":"center"},"elements":[]}],"isInner":false},{"id":"7a1e031","elType":"column","settings":{"_column_size":33,"_inline_size":33.34},"elements":[{"id":"7a1e032","elType":"widget","widgetType":"image","settings":{"image":{"url":"/wp-content/uploads/2026/10/assiette-peinte.jpg","id":""},"image_custom_alt":"Assiette décorative peinte à la main"},"elements":[]},{"id":"7a1e033","elType":"widget","widgetType":"heading","settings":{"title":"Nos assiettes","header_size":"h3","align":"center"},"elements":[]}],"isInner":false}],"isInner":false},{"id":"7a1e040","elType":"section","settings":{},"elements":[{"id":"7a1e041","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"7a1e042","elType":"widget","widgetType":"accordion","settings":{"tabs":[{"_id":"7a1e043","tab_title":"Quels sont les délais de livraison ?","tab_content":"<p>Chaque commande est expédiée sous cinq jours ouvrés depuis notre atelier lyonnais.</p>"},{"_id":"7a1e044","tab_title":"Proposez-vous des cours de poterie ?","tab_content":"<p>Oui, des stages complets de tournage sont organisés chaque mois.</p>"}]},"elements":[]}],"isInner":false}],"isInner":false},{"id":"7a1e050","elType":"section","settings":{},"elements":[{"id":"7a1e051","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"7a1e052","elType":"widget","widgetType":"html","settings":{"html":"<form action=\"#\" method=\"post\"><label for=\"prenom\">Votre prénom</label><input id=\"prenom\" name=\"prenom\" type=\"text\" placeholder=\"Entrez votre prénom\" value=\"Jean Dupont\"> <label for=\"message\">Votre message</label><textarea id=\"message\" name=\"message\" placeholder=\"Écrivez votre message ici\"></textarea> <button type=\"submit\">Envoyer la demande</button></form>"},"elements":[]}],"isInner":false}],"isInner":false},{"id":"7a1e060","elType":"section","settings":{"background_background":"classic","background_color":"#f2efe9","padding":{"unit":"px","top":"60","right":"20","bottom":"60","left":"20","isLinked":false}},"elements":[{"id":"7a1e061","elType":"column","settings":{"_column_size":100,"_inline_size":null},"elements":[{"id":"7a1e062","elType":"widget","widgetType":"heading","settings":{"title":"Contact et informations pratiques","header_size":"h2","align":"center"},"elements":[]},{"id":"7a1e063","elType":"widget","widgetType":"text-editor","settings":{"editor":"<p>Pour toute demande personnalisée, contactez notre équipe directement à la boutique.</p>","align":"center"},"elements":[]},{"id":"7a1e064","elType":"widget","widgetType":"text-editor","settings":{"editor":"<p>contact@atelier-ceramique.example</p>","align":"center"},"elements":[]},{"id":"7a1e065","elType":"widget","widgetType":"text-editor","settings":{"editor":"<p>+33 4 78 22 11 09</p>","align":"center"},"elements":[]},{"id":"7a1e066","elType":"widget","widgetType":"button","settings":{"text":"Voir la boutique en ligne","link":{"url":"/shop/","is_external":"","nofollow":"","custom_attributes":""},"align":"center"},"elements":[]}],"isInner":false}],"isInner":false}]
ELEMENTOR_JSON
)

# Transmission du JSON par base64 : le contenu comporte apostrophes, accents
# et guillemets qui ne passent pas tels quels en argument WP-CLI.
DATA_B64=$(printf '%s' "$ELEMENTOR_DATA" | base64 | tr -d '\n')
if [ "${SEO:-yoast}" = "rankmath" ]; then
	SEO_META="$RANKMATH_META"
else
	SEO_META="$YOAST_META"
fi
SEO_B64=$(printf '%s' "$SEO_META" | base64 | tr -d '\n')

PAGE_ID=$(
	$CLI wp post list --post_type=page --name="$PAGE_SLUG" --field=ID 2>/dev/null | tr -d '\r'
)
if [ -z "$PAGE_ID" ]; then
	PAGE_ID=$(
		$CLI wp post create \
			--post_type=page --post_status=publish \
			--post_title="$PAGE_TITLE" --post_name="$PAGE_SLUG" \
			--post_content='' --porcelain 2>/dev/null | tr -d '\r'
	)
else
	$CLI wp post update "$PAGE_ID" --post_title="$PAGE_TITLE" --post_status=publish
fi
echo "Page Elementor : $PAGE_ID (slug $PAGE_SLUG)"

# Note : _elementor_data est enregistré par Elementor avec un sanitize_callback
# (wp_kses_post_deep dès que l'utilisateur courant n'a pas unfiltered_html) :
# l'écriture doit donc se faire --user=admin, comme un vrai éditeur Elementor.
# L'attribut alt du widget image ne provient que de la méta d'attachment
# (_wp_attachment_image_alt) quand l'image a un id : le seeder crée trois
# attachments locales (GD, sans réseau) et les branche dans les widgets.
SEED_PHP=$(cat <<'PHP_EOF'
$page_id = (int) base64_decode( '__PAGE_B64__' );
$elementor = json_decode( base64_decode( '__DATA_B64__' ), true );
$seo_meta = json_decode( base64_decode( '__SEO_B64__' ), true );
if ( ! is_array( $elementor ) || $page_id < 1 ) {
	WP_CLI::error( 'Donnees de seed invalides.' );
}

function omtr_seed_attachment( $title, $alt, $rgb ) {
	$existing = get_posts(
		array(
			'post_type'      => 'attachment',
			'title'          => $title,
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'post_status'    => 'inherit',
		)
	);
	if ( ! empty( $existing ) ) {
		return (int) $existing[0];
	}
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		WP_CLI::error( 'GD indisponible dans le conteneur.' );
	}
	$upload = wp_upload_dir();
	$name   = sanitize_title( $title ) . '.jpg';
	$path   = trailingslashit( $upload['path'] ) . $name;
	$image  = imagecreatetruecolor( 600, 400 );
	$color  = imagecolorallocate( $image, $rgb[0], $rgb[1], $rgb[2] );
	imagefill( $image, 0, 0, $color );
	imagejpeg( $image, $path, 80 );
	imagedestroy( $image );
	$id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $title,
			'post_status'    => 'inherit',
		),
		$path,
		0,
		true
	);
	if ( ! is_int( $id ) || is_wp_error( $id ) ) {
		WP_CLI::error( 'Echec de creation d attachment.' );
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $path ) );
	update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
	return (int) $id;
}

$images = array(
	'7a1e012' => array( 'Image bol gres bleu', 'Bol en grès émaillé bleu nuit', array( 24, 38, 84 ) ),
	'7a1e022' => array( 'Image vase tourne', 'Vase en grès tourné à la main', array( 122, 95, 70 ) ),
	'7a1e032' => array( 'Image assiette peinte', 'Assiette décorative peinte à la main', array( 160, 62, 52 ) ),
);
$attachment_ids = array();
foreach ( $images as $widget_id => $spec ) {
	$attachment_ids[ $widget_id ] = omtr_seed_attachment( $spec[0], $spec[1], $spec[2] );
}

$inject = function ( array &$elements ) use ( &$inject, $attachment_ids ) {
	foreach ( $elements as &$element ) {
		if ( 'widget' === $element['elType'] && isset( $attachment_ids[ $element['id'] ] ) ) {
			$attachment_id            = $attachment_ids[ $element['id'] ];
			$element['settings']['image'] = array(
				'id'  => (string) $attachment_id,
				'url' => wp_get_attachment_url( $attachment_id ),
			);
			unset( $element['settings']['image_custom_alt'] );
		}
		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$inject( $element['elements'] );
		}
	}
	unset( $element );
};
$inject( $elementor );

update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( $elementor ) ) );
update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_id, '_elementor_template_type', 'post' );
update_post_meta( $page_id, '_elementor_version', '4.3.5' );
if ( is_array( $seo_meta ) ) {
	foreach ( $seo_meta as $key => $value ) {
		update_post_meta( $page_id, $key, wp_slash( $value ) );
	}
}
WP_CLI::line( 'meta ok, attachments=' . implode( ',', $attachment_ids ) );
PHP_EOF
)

PAGE_B64=$(printf '%s' "$PAGE_ID" | base64 | tr -d '\n')
SEED_PHP=${SEED_PHP//'__PAGE_B64__'/$PAGE_B64}
SEED_PHP=${SEED_PHP//'__DATA_B64__'/$DATA_B64}
SEED_PHP=${SEED_PHP//'__SEO_B64__'/$SEO_B64}
SEED_B64=$(printf '%s' "$SEED_PHP" | base64 | tr -d '\n')

$CLI wp eval --user=admin "eval( base64_decode( '$SEED_B64' ) );"

# Régénération du CSS Elementor (supprime les fichiers CSS, recréés au
# premier rendu frontal) : le curl suivant sert aussi de chauffe.
$CLI wp eval --user=admin 'if ( class_exists( "\Elementor\Plugin" ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); echo "css clear ok"; } else { echo "elementor absent"; }'
curl -fsS -o /dev/null "http://localhost:8882/$PAGE_SLUG/"
sleep 1
curl -fsS -o /dev/null "http://localhost:8882/$PAGE_SLUG/"

# ------------------------------------------------------- Produit WooCommerce
PRODUCT_SLUG='bol-gres-bleu'
PRODUCT_ID=$(
	$CLI wp post list --post_type=product --name="$PRODUCT_SLUG" --field=ID 2>/dev/null | tr -d '\r'
)
if [ -z "$PRODUCT_ID" ]; then
	PRODUCT_ID=$(
		$CLI wp wc product create --user=1 \
			--name='Bol en grès émaillé bleu nuit' \
			--slug="$PRODUCT_SLUG" \
			--regular_price='34.90' \
			--sku='BOL-BLEU-001' \
			--description='Bol en grès émaillé, tourné à la main dans notre atelier lyonnais. Émail bleu nuit alimentaire, chaque pièce est unique.' \
			--short_description='Bol en grès émaillé bleu nuit, tourné à la main.' \
			--porcelain 2>/dev/null | tr -d '\r'
	)
else
	echo "Produit déjà présent : $PRODUCT_ID"
fi
echo "Produit WooCommerce : $PRODUCT_ID (slug $PRODUCT_SLUG)"

$CLI wp rewrite flush --hard
echo "Seed terminé : page=$PAGE_ID produit=$PRODUCT_ID SEO=${SEO:-yoast}"
