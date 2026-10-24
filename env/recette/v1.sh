#!/usr/bin/env bash
# Recette finale v1 (CLAUDE.md section 14) sur un site wp-env, sans clé API réelle.
# Le fournisseur est simulé par le mu-plugin env/recette/mock-provider.php.
#
# Usage (depuis la racine du repo, un site à la fois) :
#   bash env/recette/v1.sh gutenberg   # port 8881 (+ parcours admin Playwright)
#   bash env/recette/v1.sh elementor   # port 8882 (Elementor + Woo + Yoast)
#   bash env/recette/v1.sh oxygen      # port 8883 (Oxygen + Woo + Rank Math)
#
# Journal : env/recette/tmp/v1-<site>.log
#
# Correction des défauts du harnais précédent :
#  - le comptage SQL de la file renvoie désormais toujours un entier (cast int
#    côté PHP) et tout résultat non numérique est traité comme « à recompter » ;
#  - plus aucune vérification ne peut faire échouer le script bash : chaque
#    curl/grep est isolé, les codes HTTP sont enregistrés, jamais subis ;
#  - la désinstallation physique n'utilise NI `wp plugin uninstall` NI
#    `wp plugin delete` (le plugin est monté depuis le worktree : ces
#    commandes effaceraient les sources). uninstall.php est exécuté via
#    `wp eval` avec WP_UNINSTALL_PLUGIN défini, après `wp plugin deactivate`.

set -uo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
KIND="${1:?usage: v1.sh gutenberg|elementor|oxygen}"
case "$KIND" in
	gutenberg) SITE="$ROOT"; PORT=8881 ;;
	elementor) SITE="$ROOT/env/elementor"; PORT=8882 ;;
	oxygen) SITE="$ROOT/env/oxygen"; PORT=8883 ;;
	*) echo "usage: v1.sh gutenberg|elementor|oxygen" >&2; exit 2 ;;
esac

BASE="http://localhost:$PORT"
TMP="$ROOT/env/recette/tmp/v1-$KIND"
LOG="$ROOT/env/recette/tmp/v1-$KIND.log"
mkdir -p "$TMP"
: > "$LOG"

PASS=0; FAIL=0
note()  { printf '%s\n' "$*" | tee -a "$LOG"; }
check() { # check <nom> <0|1> <détail>
	if [ "$2" = "1" ]; then note "OK  $1 :: $3"; PASS=$((PASS + 1));
	else note "KO  $1 :: $3"; FAIL=$((FAIL + 1)); fi
}

cd "$SITE" || { echo "dossier introuvable: $SITE" >&2; exit 1; }

# --- helpers -----------------------------------------------------------------

W() { npx wp-env run cli wp "$@" 2>&1 | LC_ALL=C tr -d '\r' | awk '!/^ℹ/ && !/^✔/ && !/^$/ { print }'; }
# Le conteneur CLI ne voit pas localhost:$PORT : réécrire home_url le temps de
# la commande, en conservant les validations same-site du plugin.
EXEC="WP_CLI::add_hook('after_wp_load', function () { add_filter('home_url', function (\$url) { return str_replace('localhost:${PORT}', 'host.docker.internal:${PORT}', \$url); }); });"
WX() { W --exec="$EXEC" "$@"; }
# Dernier entier KEY=<n> d'une sortie WP-CLI ; vide si absent (jamais d'erreur bash).
wint() { W eval "$1" 2>/dev/null | grep -oE "$2=[0-9]+" | tail -1 | cut -d= -f2; }
wstr() { W eval "$1" 2>/dev/null | grep -oE "$2=.*" | tail -1 | cut -d= -f2-; }

PHPFILE='wp-content/plugins/omnium-translate/env/recette/v1.php'

pending() { wint 'global $wpdb; WP_CLI::line( "PENDING=" . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE status = '\''pending'\'' AND hook IN ('\''omtr_scan_url'\'','\''omtr_translate_batch'\'')" ) );' PENDING; }
mock_calls() { wint 'WP_CLI::line( "CALLS=" . (int) get_option( "omtr_mock_calls", 0 ) );' CALLS; }

drain_queue() { # exécute la file jusqu'à épuisement ; echo 0 si épuisée
	local attempt count
	for attempt in $(seq 1 150); do
		count="$(pending)"
		case "$count" in ''|*[!0-9]*) count=1 ;; esac # sortie illisible => continuer à vider
		[ "$count" -eq 0 ] && { echo 0; return 0; }
		WX action-scheduler run --hooks=omtr_scan_url,omtr_translate_batch --batch-size=100 --batches=20 >/dev/null 2>&1
	done
	pending
}

fetch() { # fetch <url> <fichier> : echo le code HTTP, jamais d'échec bash
	local code
	code="$(curl -sS -o "$2" -w '%{http_code}' --max-time 60 "$1" 2>/dev/null)"
	case "$code" in ''|*[!0-9]*) echo 000 ;; *) echo "$code" ;; esac
}

count_occurrences() { grep -oF -- "$2" "$1" 2>/dev/null | wc -l | tr -d ' '; }

median_of() { # median_of <url> <sortie-temps> : médiane de 20 requêtes (s)
	local i
	: > "$2"
	for i in $(seq 1 20); do
		curl -sS -o /dev/null --max-time 60 -w '%{time_total}\n' "$1" >> "$2" 2>/dev/null || printf '999.000\n' >> "$2"
	done
	sort -n "$2" | awk '{ a[NR] = $1 } END { printf "%.3f", a[int((NR + 1) / 2)] }'
}

cleanup() { npx wp-env stop >/dev/null 2>&1 || true; }
trap cleanup EXIT

# --- démarrage ---------------------------------------------------------------

note "=== RECETTE V1 — $KIND — $BASE — $(date '+%F %T')"
note "-- Démarrage du site"
npx wp-env start >/dev/null 2>&1
sleep 2
code="$(fetch "$BASE/" "$TMP/home.html")"
check "SITE_UP" "$([ "$code" = "200" ] && echo 1 || echo 0)" "accueil HTTP $code"

# Thème avec emplacements de menu (un thème de bloc n'en expose aucun) :
# le sélecteur doit pouvoir être rendu par le menu WordPress.
if ! W theme is-installed twentytwentyone >/dev/null 2>&1; then
	W theme install twentytwentyone >/dev/null 2>&1
fi
W theme activate twentytwentyone >/dev/null 2>&1
note "THEME=$(W theme status twentytwentyone 2>/dev/null | tail -1)"

W option update WPLANG fr_FR >/dev/null 2>&1
W rewrite structure '/%postname%/' --hard >/dev/null 2>&1

# Plugins du site (le plugin testé est monté ; jamais désinstallé physiquement).
case "$KIND" in
	elementor) bash "$ROOT/env/elementor/seed.sh" >> "$LOG" 2>&1 ;;
	oxygen)    bash "$ROOT/env/oxygen/seed.sh" >> "$LOG" 2>&1 ;;
esac

# Contenu de recette : 30 pages par builder, produits Woo, menu + élément sélecteur.
W eval-file "$PHPFILE" seed "$KIND" >> "$LOG" 2>&1
pages="$(wstr 'WP_CLI::line( "PAGES=" . count( get_posts( array( "post_type" => "page", "post_status" => "publish", "posts_per_page" => -1, "fields" => "ids" ) ) ) );' PAGES)"
check "SEED_30_PAGES" "$([ "${pages:-0}" -ge 30 ] 2>/dev/null && echo 1 || echo 0)" "$pages pages publiques"
products="$(wstr 'WP_CLI::line( "PRODUCTS=" . (int) wp_count_posts( "product" )->publish );' PRODUCTS)"
if [ "$KIND" != gutenberg ]; then
	check "SEED_PRODUCTS" "$([ "${products:-0}" -ge 5 ] 2>/dev/null && echo 1 || echo 0)" "$products produits Woo"
else
	note "    produits Woo : sans objet (site Gutenberg sans WooCommerce) — PRODUCTS=${products:-0}"
fi

# État « plugin désactivé » pour l'instantané de référence des pages sources.
W plugin deactivate omnium-translate >/dev/null 2>&1
for n in 01 15 30; do
	fetch "$BASE/recette-v1-$n/" "$TMP/before-$n.html" >/dev/null
done
note "SNAPSHOT_BEFORE=$(wc -c < "$TMP/before-01.html" | tr -d ' ') octets (page 01)"

# --- assistant + traduction --------------------------------------------------

W plugin activate omnium-translate >/dev/null 2>&1
check "PLUGIN_ACTIVE" "$(W plugin is-active omnium-translate >/dev/null 2>&1 && echo 1 || echo 0)" "omnium-translate activé"

if [ "$KIND" = gutenberg ]; then
	# Parcours navigateur de l'assistant (fr_FR, chronométré) : voir admin-playwright.cjs.
	PW_DIR="$ROOT/env/recette/tmp/pw"
	if [ ! -d "$PW_DIR/node_modules/playwright" ]; then
		PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 npm install --prefix "$PW_DIR" --no-save --no-package-lock playwright@1.63.0 >/dev/null 2>&1
	fi
	W user meta update admin locale fr_FR >/dev/null 2>&1
	note "-- Assistant via navigateur headless (fr_FR, chronométré)"
	OMTR_BASE="$BASE" OMTR_LOCALE=fr_FR OMTR_TIMED=1 \
		PLAYWRIGHT_MODULE="$PW_DIR/node_modules/playwright" \
		node "$ROOT/env/recette/admin-playwright.cjs" > "$TMP/playwright-fr.log" 2>&1
	cat "$TMP/playwright-fr.log" | tee -a "$LOG" | sed 's/^/    /'
	wizard_seconds="$(sed -n 's/^INSTALL_WIZARD_BROWSER_SECONDS=//p' "$TMP/playwright-fr.log" | tail -1)"
	check "INSTALL_ASSISTANT_5MIN" "$(awk -v t="${wizard_seconds:-9999}" 'BEGIN { print (t < 300) ? 1 : 0 }')" "${wizard_seconds:-échec} s (montage wp-env, pas de téléchargement du plugin)"
	key_dom="$(sed -n 's/^KEY_IN_DOM_AFTER_SAVE=//p' "$TMP/playwright-fr.log" | tail -1)"
	key_net="$(sed -n 's/^KEY_IN_API_RESPONSES=//p' "$TMP/playwright-fr.log" | tail -1)"
	check "CLE_JAMAIS_EN_CLAIR" "$([ "${key_dom:-1}" = "false" ] && [ "${key_net:-1}" = "0" ] && echo 1 || echo 0)" "DOM=$key_dom réponses=$key_net"
else
	note "-- Assistant via API REST (admin-api.md)"
	WX eval-file "$PHPFILE" wizard "$KIND" >> "$LOG" 2>&1
	key_claude="$(wstr 'WP_CLI::line( "KT=ok" );' KT >/dev/null; grep -oE 'KEY_TEST_[A-Z]+=[a-z:]+' "$LOG" | tail -4 | tr '\n' ' ')"
	for p in CLAUDE OPENAI MISTRAL GEMINI; do
		v="$(grep -oE "KEY_TEST_$p=ok" "$LOG" | tail -1)"
		check "WIZARD_KEY_TEST_$p" "$([ -n "$v" ] && echo 1 || echo 0)" "test de clé simulée"
	done
fi
check "WIZARD_DONE" "$(wstr '$v = get_option( "omtr_wizard_done" ); WP_CLI::line( "WD=" . ( $v ? "ok" : "ko" ) );' WD | grep -qx ok && echo 1 || echo 0)" "option omtr_wizard_done"

note "-- Exécution de la file Action Scheduler"
left="$(drain_queue)"
check "QUEUE_DRAINED" "$([ "${left:-x}" = "0" ] && echo 1 || echo 0)" "actions omtr_* en attente : ${left:-inconnu}"
calls_after_queue="$(mock_calls)"
note "    MOCK_CALLS=$calls_after_queue"

# --- traduction des pages ----------------------------------------------------

case "$KIND" in
	gutenberg) MARKER='Présentation de notre atelier et de ses services' ;;
	elementor) MARKER='Atelier de céramique artisanale à Lyon' ;;
	oxygen)    MARKER='Donnez de l’élan à vos idées' ;;
esac

# URL EN/ES de la page 01 depuis son hreflang (slugs traduits fiables).
fetch "$BASE/recette-v1-01/" "$TMP/src-01.html" >/dev/null
EN_URL="$(grep -oE '<link rel="alternate" href="[^"]+" hreflang="en"' "$TMP/src-01.html" | grep -oE 'href="[^"]+"' | head -1 | cut -d'"' -f2)"
ES_URL="$(grep -oE '<link rel="alternate" href="[^"]+" hreflang="es"' "$TMP/src-01.html" | grep -oE 'href="[^"]+"' | head -1 | cut -d'"' -f2)"
check "HREFLANG_URLS" "$([ -n "$EN_URL" ] && [ -n "$ES_URL" ] && echo 1 || echo 0)" "en=$EN_URL es=$ES_URL"

for lang in en es; do
	upper="$(printf '%s' "$lang" | tr '[:lower:]' '[:upper:]')"
	[ "$lang" = en ] && LURL="$EN_URL" || LURL="$ES_URL"
	ok_count=0; miss_count=0; err_pages=""
	for n in $(seq -w 1 30); do
		if [ "$n" = "01" ]; then url="$LURL"; else url="$BASE/$lang/recette-v1-$n/"; fi
		code="$(fetch "$url" "$TMP/page-$lang-$n.html")"
		total="$(count_occurrences "$TMP/page-$lang-$n.html" "$MARKER")"
		prefixed="$(count_occurrences "$TMP/page-$lang-$n.html" "[$upper] $MARKER")"
		if [ "$code" = "200" ] && [ "$prefixed" -gt 0 ] 2>/dev/null && [ "$total" = "$prefixed" ]; then
			ok_count=$((ok_count + 1))
		else
			miss_count=$((miss_count + 1)); err_pages="$err_pages $n(http=$code,total=$total,prefixed=$prefixed)"
		fi
	done
	check "PAGES_${upper}_TRADUITES" "$([ "$ok_count" = "30" ] && echo 1 || echo 0)" "$ok_count/30 pages OK$err_pages"
done

W eval-file "$PHPFILE" coverage >> "$LOG" 2>&1
cov_en="$(wstr '$c = \Omnium\Translate\Core\Plugin::instance()->container()->get( \Omnium\Translate\Seo\TranslationCoverage::class ); delete_transient( "omtr_translation_coverage_" . md5( "en|fr" ) ); WP_CLI::line( "COV=" . $c->percentage( "en" ) );' COV)"
cov_es="$(wstr '$c = \Omnium\Translate\Core\Plugin::instance()->container()->get( \Omnium\Translate\Seo\TranslationCoverage::class ); delete_transient( "omtr_translation_coverage_" . md5( "es|fr" ) ); WP_CLI::line( "COV=" . $c->percentage( "es" ) );' COV)"
check "COUVERTURE_100" "$(awk -v a="${cov_en:-0}" -v b="${cov_es:-0}" 'BEGIN { print (a >= 100 && b >= 100) ? 1 : 0 }')" "EN=${cov_en:-?}% ES=${cov_es:-?}%"
failed="$(wint 'global $wpdb; WP_CLI::line( "F=" . (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}omtr_translations WHERE status = '\''failed'\''" ) );' F)"
check "AUCUN_SEGMENT_FAILED" "$([ "${failed:-1}" = "0" ] && echo 1 || echo 0)" "$failed traductions en échec"

# --- SEO : hreflang, x-default, canonical, sitemaps ---------------------------

for lang in en es; do
	upper="$(printf '%s' "$lang" | tr '[:lower:]' '[:upper:]')"
	[ "$lang" = en ] && LURL="$EN_URL" || LURL="$ES_URL"
	fetch "$LURL" "$TMP/seo-$lang.html" >/dev/null
	hl="$(grep -c "hreflang=\"$lang\"" "$TMP/seo-$lang.html" || true)"
	xd="$(grep -c 'hreflang="x-default"' "$TMP/seo-$lang.html" || true)"
	can="$(grep -oE '<link rel="canonical" href="[^"]+"' "$TMP/seo-$lang.html" | grep -cF "$LURL" || true)"
	check "SEO_${upper}_HEAD" "$([ "${hl:-0}" -ge 1 ] && [ "${xd:-0}" -ge 1 ] && [ "${can:-0}" -ge 1 ] && echo 1 || echo 0)" "hreflang=$hl x-default=$xd canonical=$can"
done

code="$(fetch "$BASE/wp-sitemap.xml" "$TMP/wp-sitemap.xml")"
sm_en="$(grep -c "recette-v1-01</loc>" "$TMP/wp-sitemap.xml" >/dev/null 2>&1 && grep -c "/en/" "$TMP/wp-sitemap.xml" || echo 0)"
sm_xhtml="$(grep -c 'xhtml:link' "$TMP/wp-sitemap.xml" || true)"
check "SITEMAP_NATIF" "$([ "$code" = "200" ] && [ "${sm_en:-0}" -ge 1 ] && [ "${sm_xhtml:-0}" -ge 1 ] && echo 1 || echo 0)" "wp-sitemap.xml HTTP $code, /en/: $sm_en, xhtml:link: $sm_xhtml"
# Sitemap du plugin SEO actif (Yoast sur elementor, Rank Math sur oxygen).
if [ "$KIND" != gutenberg ]; then
	fetch "$BASE/sitemap_index.xml" "$TMP/seo-index.xml" >/dev/null
	sub="$(grep -oE '<loc>[^<]+</loc>' "$TMP/seo-index.xml" | grep -oE 'http[^<]+' | head -1)"
	fetch "$sub" "$TMP/seo-sitemap.xml" >/dev/null
	seo_en="$(grep -c "/en/" "$TMP/seo-sitemap.xml" || true)"
	check "SITEMAP_SEO_PLUGIN" "$([ "${seo_en:-0}" -ge 1 ] && echo 1 || echo 0)" "$sub → /en/: $seo_en"
fi

# --- sélecteur de langue ------------------------------------------------------

fetch "$BASE/recette-v1-01/" "$TMP/front-switcher.html" >/dev/null
switcher="$(grep -c 'class="omtr-switcher' "$TMP/front-switcher.html" || true)"
if [ "$KIND" = oxygen ]; then
	# Sur Oxygen, le gabarit remplace l'en-tête du thème : le sélecteur est
	# vérifié sur la page composée avec l'élément Oxygen Omnium.
	fetch "$BASE/recette-v1-selecteur/" "$TMP/oxygen-switcher.html" >/dev/null
	sw_el="$(grep -c 'class="omtr-switcher' "$TMP/oxygen-switcher.html" || true)"
	check "SWITCHER_ELEMENT_OXYGEN" "$([ "${sw_el:-0}" -ge 1 ] && echo 1 || echo 0)" "rendu public de l'élément Oxygen : $sw_el"
else
	check "SWITCHER_MENU" "$([ "${switcher:-0}" -ge 1 ] && echo 1 || echo 0)" "sélecteur rendu dans le menu : $switcher"
fi

# --- aucune URL tierce dans le HTML front -------------------------------------

: > "$TMP/external-urls.txt"
for f in "$TMP/src-01.html" "$TMP/seo-en.html" "$TMP/seo-es.html"; do
	[ -f "$f" ] || continue
	grep -oE 'https?://[^"'"'"' <>)]+' "$f" | grep -v "^https\\?://localhost:$PORT" | grep -v "^http://localhost:$PORT" >> "$TMP/external-urls.txt" || true
done
ext="$(sort -u "$TMP/external-urls.txt" | grep -v '^$' | tr '\n' ' ')"
check "AUCUNE_URL_TIERCE" "$([ -z "$ext" ] && echo 1 || echo 0)" "URLS externes: ${ext:-aucune}"

# --- WooCommerce : pages préfixées + emails -----------------------------------

if [ "$KIND" != gutenberg ]; then
	for lang in en es; do
		st="$(for page in cart checkout my-account; do
			c="$(fetch "$BASE/$lang/$page/" "$TMP/woo-$lang-$page.html")"
			printf '%s=%s ' "$page" "$c"
		done)"
		ok="$(printf '%s' "$st" | grep -cv '=200' || true)"
		check "WOO_PAGES_${lang}" "$([ "${ok:-1}" = "0" ] && echo 1 || echo 0)" "$st"
	done
	note "-- Email WooCommerce dans la langue de la commande"
	WX eval-file "$PHPFILE" email "$KIND" > "$TMP/email-1.log" 2>&1
	s1="$(sed -n 's/^EMAIL_SUBJECT=//p' "$TMP/email-1.log" | tail -1)"
	if ! printf '%s' "$s1" | grep -q '^\[EN\]'; then
		left="$(drain_queue)" # l'email a pu mettre des segments en file
		WX eval-file "$PHPFILE" email "$KIND" > "$TMP/email-2.log" 2>&1
		s1="$(sed -n 's/^EMAIL_SUBJECT=//p' "$TMP/email-2.log" | tail -1)"
	fi
	h1="$(sed -n 's/^EMAIL_HEADING=//p' "$TMP/email-1.log" "$TMP/email-2.log" | tail -1)"
	check "WOO_EMAIL_LANGUE_COMMANDE" "$(printf '%s' "$s1" | grep -q '^\[EN\]' && printf '%s' "$h1" | grep -q '^\[EN\]' && echo 1 || echo 0)" "sujet: $s1 | entête: $h1"
fi

# --- retraduction d'un site inchangé = zéro appel ------------------------------

note "-- Correction manuelle puis retraduction"
W eval-file "$PHPFILE" edited "$KIND" > "$TMP/edited.log" 2>&1
edited_id="$(sed -n 's/^EDITED_ID=//p' "$TMP/edited.log" | tail -1)"
edited_text="$(sed -n 's/^EDITED_TEXT=//p' "$TMP/edited.log" | tail -1)"
check "SEGMENT_EDITE_POSE" "$([ -n "$edited_id" ] && [ -n "$edited_text" ] && echo 1 || echo 0)" "segment $edited_id corrigé"
before="$(mock_calls)"
WX eval-file "$PHPFILE" retranslate "$KIND" >/dev/null 2>&1
left="$(drain_queue)"
after="$(mock_calls)"
delta=$(( ${after:-0} - ${before:-0} ))
check "RETRADUCTION_0_APPEL" "$([ "$delta" = "0" ] && [ "${left:-x}" = "0" ] && echo 1 || echo 0)" "delta appels=$delta, file restante=${left:-?}"
W eval-file "$PHPFILE" edited-check "$KIND" "$edited_id" "$edited_text" > "$TMP/edited-check.log" 2>&1
preserved="$(sed -n 's/^EDITED_PRESERVED=//p' "$TMP/edited-check.log" | grep -oE '^(ok|ko)' | tail -1)"
status_ed="$(sed -n 's/^EDITED_STATUS=//p' "$TMP/edited-check.log" | tail -1)"
check "EDITED_JAMAIS_ECRASE" "$([ "${preserved:-ko}" = "ok" ] && echo 1 || echo 0)" "statut=$status_ed préservé=$preserved"

# --- journal debug.log --------------------------------------------------------

W eval 'WP_CLI::line( "LOG=" . ( file_exists( WP_CONTENT_DIR . "/debug.log" ) ? filesize( WP_CONTENT_DIR . "/debug.log" ) : 0 ) );' >/dev/null 2>&1
W eval 'WP_CLI::line( file_exists( WP_CONTENT_DIR . "/debug.log" ) ? file_get_contents( WP_CONTENT_DIR . "/debug.log" ) : "EMPTY" );' > "$TMP/debug.log" 2>/dev/null
php_errors="$(grep -cE 'PHP (Fatal error|Parse error|Warning|Notice|Deprecated)' "$TMP/debug.log" || true)"
omtr_errors="$(grep -E 'PHP (Fatal error|Parse error|Warning|Notice|Deprecated)' "$TMP/debug.log" 2>/dev/null | grep -c 'omnium-translate' || true)"
check "DEBUG_LOG_SANS_ERREUR_PHP" "$([ "${omtr_errors:-1}" = "0" ] && echo 1 || echo 0)" "$php_errors lignes PHP au total, dont $omtr_errors dans le plugin"
[ "${php_errors:-0}" -gt 0 ] && note "    $(grep -E 'PHP (Fatal error|Parse error|Warning|Notice|Deprecated)' "$TMP/debug.log" | head -5 | sed 's/^/    /')"

# --- parcours admin navigateur complémentaire ---------------------------------

if [ "$KIND" = gutenberg ]; then
	note "-- Parcours navigateur complémentaire (en_US, onglets)"
	W user meta update admin locale '' >/dev/null 2>&1
	W eval-file "$PHPFILE" reset-wizard >/dev/null 2>&1
	before_en="$(mock_calls)"
	OMTR_BASE="$BASE" OMTR_LOCALE=en_US OMTR_TIMED=0 \
		PLAYWRIGHT_MODULE="$PW_DIR/node_modules/playwright" \
		node "$ROOT/env/recette/admin-playwright.cjs" > "$TMP/playwright-en.log" 2>&1
	cat "$TMP/playwright-en.log" | tee -a "$LOG" | sed 's/^/    /'
	console_en="$(sed -n 's/^CONSOLE_ERRORS=//p' "$TMP/playwright-en.log" | tail -1)"
	key_dom_en="$(sed -n 's/^KEY_IN_DOM_AFTER_SAVE=//p' "$TMP/playwright-en.log" | tail -1)"
	after_en="$(mock_calls)"
	check "BROWSER_TABS_EN" "$([ "${console_en:-9}" = "0" ] && [ "${key_dom_en:-1}" = "false" ] && echo 1 || echo 0)" "console=$console_en erreurs, clé en clair dans le DOM=$key_dom_en"
	check "BROWSER_WIZARD_EN_0_APPEL" "$([ "$(( ${after_en:-0} - ${before_en:-0} ))" = "0" ] && echo 1 || echo 0)" "delta appels=$(( ${after_en:-0} - ${before_en:-0} )) après relance navigateur"
fi

# --- surcoût d'une page traduite en cache -------------------------------------

note "-- Mesure du surcoût (médiane de 20 requêtes, cache chaud)"
median_of "$EN_URL" "$TMP/times-en.txt" > "$TMP/median-en.txt"; m_en="$(cat "$TMP/median-en.txt")"
median_of "$BASE/recette-v1-01/" "$TMP/times-src-on.txt" > "$TMP/median-src-on.txt"; m_src_on="$(cat "$TMP/median-src-on.txt")"
W plugin deactivate omnium-translate >/dev/null 2>&1
fetch "$BASE/recette-v1-01/" "$TMP/warm.html" >/dev/null; fetch "$BASE/recette-v1-01/" "$TMP/warm.html" >/dev/null
median_of "$BASE/recette-v1-01/" "$TMP/times-src-off.txt" > "$TMP/median-src-off.txt"; m_src_off="$(cat "$TMP/median-src-off.txt")"
overhead="$(awk -v a="${m_en:-0}" -v b="${m_src_off:-0}" 'BEGIN { printf "%.0f", (a - b) * 1000 }')"
note "PERF medianEN=${m_en}s medianSRC_pluginON=${m_src_on}s medianSRC_pluginOFF=${m_src_off}s surcout=${overhead}ms (HTTP complet via Docker, sans cache objet persistant)"
check "SURCOIT_50MS" "$(awk -v o="${overhead:-9999}" 'BEGIN { print (o < 50) ? 1 : 0 }')" "surcoût médian mesuré : ${overhead} ms"

# --- désactivation : site intact ----------------------------------------------

for n in 01 15 30; do
	fetch "$BASE/recette-v1-$n/" "$TMP/after-$n.html" >/dev/null
	if cmp -s "$TMP/before-$n.html" "$TMP/after-$n.html"; then
		check "DESACTIVATION_INTACTE_$n" 1 "octets identiques"
	else
		check "DESACTIVATION_INTACTE_$n" 0 "différences: $(diff "$TMP/before-$n.html" "$TMP/after-$n.html" | head -4 | tr '\n' '|' | cut -c1-160)"
	fi
done

# --- désinstallation avec purge (SANS wp plugin uninstall/delete) --------------

W plugin activate omnium-translate >/dev/null 2>&1
W eval-file "$PHPFILE" purge "$KIND" >/dev/null 2>&1
W plugin deactivate omnium-translate >/dev/null 2>&1
# uninstall.php exécuté via wp eval : WP_UNINSTALL_PLUGIN défini, Action
# Scheduler chargé pour purger les tâches, fichiers jamais supprimés.
W eval 'require_once WP_PLUGIN_DIR . "/omnium-translate/vendor/woocommerce/action-scheduler/action-scheduler.php"; define( "WP_UNINSTALL_PLUGIN", "omnium-translate/omnium-translate.php" ); include WP_PLUGIN_DIR . "/omnium-translate/uninstall.php";' >> "$LOG" 2>&1
W eval-file "$PHPFILE" traces "$KIND" > "$TMP/traces.log" 2>&1
t_tables="$(sed -n 's/^TRACES_TABLES=//p' "$TMP/traces.log" | tail -1)"
t_options="$(sed -n 's/^TRACES_OPTIONS=//p' "$TMP/traces.log" | tail -1)"
t_meta="$(sed -n 's/^TRACES_META=//p' "$TMP/traces.log" | tail -1)"
t_actions="$(sed -n 's/^TRACES_ACTIONS=//p' "$TMP/traces.log" | tail -1)"
t_mock="$(sed -n 's/^TRACES_MOCK_OPTION=//p' "$TMP/traces.log" | tail -1)"
t_menu="$(sed -n 's/^TRACES_MENU_ITEMS=//p' "$TMP/traces.log" | tail -1)"
check "UNINSTALL_PURGE" "$([ "${t_tables:-1}" = "0" ] && [ "${t_options:-1}" = "0" ] && [ "${t_meta:-1}" = "0" ] && [ "${t_actions:-1}" = "0" ] && [ "${t_mock:-x}" = "absent" ] && [ "${t_menu:-1}" = "0" ] && echo 1 || echo 0)" "tables=$t_tables options=$t_options meta=$t_meta actions=$t_actions mock=$t_mock menu=$t_menu"

# --- bilan --------------------------------------------------------------------

note "=== BILAN $KIND : $PASS OK, $FAIL KO — $(date '+%F %T')"
[ "$FAIL" -eq 0 ] || exit 1
