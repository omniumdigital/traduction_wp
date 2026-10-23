#!/usr/bin/env bash
# Recette du jalon 3 sur un site wp-env.
# Usage (depuis la racine du repo) : bash env/recette/jalon3.sh <dossier-env> <port> <post_id>
#   bash env/recette/jalon3.sh . 8881 6
#   bash env/recette/jalon3.sh env/elementor 8882 11
#   bash env/recette/jalon3.sh env/oxygen 8883 11
# Aucune clé API n'est nécessaire : sans clé, les lots doivent échouer proprement.
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SITE_DIR="$1"
PORT="$2"
POST_ID="$3"
cd "$ROOT/$SITE_DIR" || exit 1

W() { npx wp-env run cli wp "$@" 2>&1 | awk '!/^ℹ/ && !/^✔/ && !/^$/ { sub(/✔ Ran .*/, ""); sub(/✖ Command failed.*/, ""); if (length) print }'; }
# wp-env exécute WP-CLI dans un conteneur où localhost ne désigne pas le serveur web.
# Le filtre ne vit que le temps de la commande et conserve toutes les validations same-site du plugin.
EXEC="WP_CLI::add_hook('after_wp_load', function () { add_filter('home_url', function (\$url) { return str_replace('localhost:${PORT}', 'host.docker.internal:${PORT}', \$url); }); });"
RUN_QUEUE() { W --exec="$EXEC" action-scheduler run --hooks=omtr_scan_url,omtr_translate_batch --batch-size=50 --batches=20 >/dev/null; }
PENDING_SCANS() { W action-scheduler action list --hook=omtr_scan_url --status=pending --format=count | tail -1; }
PENDING_AUTO() { W action-scheduler action list --hook=omtr_auto_translate_post --status=pending --format=count | tail -1; }
SQL() { W db query "$1" --skip-column-names | tail -1; }
PASS=0; FAIL=0
check() { if [ "$2" = "1" ]; then echo "OK $1"; PASS=$((PASS + 1)); else echo "KO $1"; FAIL=$((FAIL + 1)); fi; }
has_match() { grep -qE "$1" && echo 1 || echo 0; }
has_no_match() { grep -qE "$1" && echo 0 || echo 1; }
settings() { W option update omtr_settings "{\"source_lang\":\"fr\",\"target_langs\":[\"en\",\"es\"],\"provider\":\"${3:-claude}\",\"model\":\"${4:-}\",\"on_the_fly\":$1,\"auto_translate\":$2}" --format=json >/dev/null; }
clear_queue() {
	SQL "DELETE FROM wp_actionscheduler_actions WHERE hook IN ('omtr_scan_url','omtr_translate_batch','omtr_auto_translate_post') AND status = 'pending'" >/dev/null
	SQL "DELETE FROM wp_options WHERE option_name LIKE '%omtr_otf_%' OR option_name LIKE '%omtr_translation_lock%'" >/dev/null
}

if ! W eval 'WP_CLI::line( class_exists( "Omnium\\Translate\\Core\\Plugin" ) ? "ready" : "not-ready" );' | grep -q '^ready$'; then
	echo "ERREUR : Omnium Translate n'est pas chargé (vérifier vendor/ puis réactiver le plugin)." >&2
	exit 1
fi

cleanup() {
	settings false false
	clear_queue
}
trap cleanup EXIT

SLUG="$(W post get "$POST_ID" --field=post_name | tail -1)"
echo "== Site ${SITE_DIR} (port ${PORT}), page ${POST_ID} « ${SLUG} »"
settings false false
clear_queue

echo "-- Clé API"
KEYS="$(W omtr key status --user=admin)"
echo "$KEYS" | sed 's/^/   /'
check "aucune clé affichée en clair (masque uniquement)" "$(echo "$KEYS" | has_no_match '(sk-|AIza)[A-Za-z0-9_-]{12,}')"

echo "-- Glossaire"
W omtr glossary add Omnium en '' keep --user=admin >/dev/null
W omtr glossary add atelier en workshop translate --user=admin >/dev/null
check "règles ajoutées" "$([ "$(SQL 'SELECT COUNT(*) FROM wp_omtr_glossary')" -ge 2 ] && echo 1 || echo 0)"
mkdir -p "$ROOT/env/recette/tmp"
printf 'term,target_lang,translation,rule\n=HYPERLINK("http://evil.test"),en,x,translate\nbonjour,en,<script>alert(1)</script>hello,translate\n' > "$ROOT/env/recette/tmp/glossaire.csv"
W omtr glossary import wp-content/plugins/omnium-translate/env/recette/tmp/glossaire.csv --user=admin >/dev/null
EXPORT="$(W omtr glossary export --user=admin)"
check "export CSV : formule neutralisée par une apostrophe" "$(echo "$EXPORT" | has_match '^"?\x27=HYPERLINK')"
check "export CSV : aucune balise <script>" "$(echo "$EXPORT" | has_no_match '<script')"

echo "-- Estimation"
EST="$(W --exec="$EXEC" omtr estimate "$POST_ID" --user=admin)"
echo "$EST" | sed 's/^/   /'
check "estimation produite" "$(echo "$EST" | has_match '[Ss]egment')"

echo "-- Estimation par fournisseur"
for spec in claude:claude-haiku-5-5 openai:gpt-6-luna mistral:mistral-medium-3-5 gemini:gemini-3.5-flash-lite; do
	provider="${spec%%:*}"
	model="${spec#*:}"
	settings false false "$provider" "$model"
	PROVIDER_EST="$(W --exec="$EXEC" omtr estimate "$POST_ID" --user=admin)"
	check "coût affiché pour ${provider}/${model}" "$(echo "$PROVIDER_EST" | has_match '^cost[[:space:]]+0\.[0-9]*[1-9][0-9]*$')"
done
settings false false claude modele-sans-tarif
UNKNOWN_EST="$(W --exec="$EXEC" omtr estimate "$POST_ID" --user=admin)"
check "modèle sans tarif => coût inconnu" "$(echo "$UNKNOWN_EST" | has_match '^cost[[:space:]]+(unknown cost|coût inconnu)$')"
settings false false

echo "-- Traduction d'une page sans clé"
W --exec="$EXEC" omtr translate "$POST_ID" --user=admin >/dev/null
RUN_QUEUE
SEGS=$(SQL "SELECT COUNT(*) FROM wp_omtr_segments")
check "segments extraits par le crawler anonyme (${SEGS})" "$([ "${SEGS:-0}" -gt 0 ] && echo 1 || echo 0)"
check "aucun email dans les segments" "$([ "$(SQL "SELECT COUNT(*) FROM wp_omtr_segments WHERE source_text REGEXP '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\\\.[A-Za-z]{2,}'")" = "0" ] && echo 1 || echo 0)"
FAILED=$(SQL "SELECT COUNT(*) FROM wp_omtr_translations WHERE status = 'failed'")
check "sans clé, les lots échouent proprement (${FAILED} segments failed)" "$([ "${FAILED:-0}" -gt 0 ] && echo 1 || echo 0)"
check "logs sans email ni texte de segment" "$([ "$(SQL "SELECT COUNT(*) FROM wp_omtr_log WHERE message LIKE '%@%' OR message IN (SELECT source_text FROM wp_omtr_segments)")" = "0" ] && echo 1 || echo 0)"

echo "-- Crawler : contenus nouveaux ou modifiés"
W --exec="$EXEC" omtr crawl --user=admin | sed 's/^/   /'
RUN_QUEUE
SECOND="$(W --exec="$EXEC" omtr crawl --user=admin)"
echo "$SECOND" | sed 's/^/   2e passage : /'
SECOND_CHANGED="$(echo "$SECOND" | tail -1 | awk '{print $2}')"
check "deuxième crawl inchangé => aucune page remise en file" "$([ "${SECOND_CHANGED:-x}" = "0" ] && echo 1 || echo 0)"
RUN_QUEUE

echo "-- Pause et reprise"
W omtr pause --user=admin >/dev/null
check "file en pause : traduction refusée" "$(W omtr translate "$POST_ID" --user=admin | has_match '[Pp]ause')"
W omtr resume --user=admin >/dev/null
clear_queue

echo "-- Droits, REST, éditeur, actions admin"
PHPOUT="$(W eval-file wp-content/plugins/omnium-translate/env/recette/jalon3.php "$POST_ID")"
echo "$PHPOUT" | sed 's/^/   /'
PASS=$((PASS + $(echo "$PHPOUT" | grep -c '^OK ')))
FAIL=$((FAIL + $(echo "$PHPOUT" | grep -c '^KO ')))

RULE_ID="$(SQL "SELECT id FROM wp_omtr_glossary WHERE term = 'atelier' AND target_lang = 'en' ORDER BY id DESC LIMIT 1")"
AFFECTED="$(W omtr glossary affected "$RULE_ID" --user=admin)"
check "modification du glossaire propose la retraduction des auto" "$(echo "$AFFECTED" | has_match 'atelier de recette pour le glossaire')"

echo "-- Traduction à la volée (option activée)"
settings true false
clear_queue
for i in $(seq 1 20); do curl -s -o /dev/null "http://localhost:${PORT}/en/${SLUG}/?s=INJECTIONRECETTE${i}&utm_source=${i}"; done
check "20 requêtes avec ?s= différents => 1 seule mise en file ($(PENDING_SCANS))" "$([ "$(PENDING_SCANS)" = "1" ] && echo 1 || echo 0)"
check "URL mise en file canonique, sans query string" "$(W action-scheduler action list --hook=omtr_scan_url --status=pending --fields=args --format=csv | has_no_match 'INJECTION|\?s=')"
clear_queue
W --exec="$EXEC" omtr translate "$POST_ID" --user=admin >/dev/null
RUN_QUEUE
check "aucun texte injecté par ?s= en base" "$([ "$(SQL "SELECT COUNT(*) FROM wp_omtr_segments WHERE source_text LIKE '%INJECTIONRECETTE%'")" = "0" ] && echo 1 || echo 0)"
clear_queue
COOKIE_JAR="$ROOT/env/recette/tmp/cookies-${PORT}.txt"
curl -s -c "$COOKIE_JAR" -o /dev/null -d 'log=admin&pwd=password&wp-submit=Log In' "http://localhost:${PORT}/wp-login.php"
curl -s -b "$COOKIE_JAR" -o /dev/null "http://localhost:${PORT}/en/${SLUG}/"
check "visiteur connecté => aucune mise en file" "$([ "$(PENDING_SCANS)" -eq 0 ] && echo 1 || echo 0)"
check "panier (/en/cart/) ne déclenche rien" "$(curl -s -o /dev/null "http://localhost:${PORT}/en/cart/"; [ "$(PENDING_SCANS)" -eq 0 ] && echo 1 || echo 0)"

echo "-- Traduction automatique à la publication (option activée)"
settings false true
clear_queue
W --exec="$EXEC" post update "$POST_ID" --post_excerpt="Recette jalon 3 $(date +%s)" >/dev/null
check "mise à jour d'un contenu public => action automatique différée ($(PENDING_AUTO))" "$([ "$(PENDING_AUTO)" -ge 1 ] && echo 1 || echo 0)"
W --exec="$EXEC" eval "do_action( 'omtr_auto_translate_post', ${POST_ID} );" >/dev/null
check "action automatique => scan en file ($(PENDING_SCANS))" "$([ "$(PENDING_SCANS)" -ge 1 ] && echo 1 || echo 0)"
W --exec="$EXEC" post update "$POST_ID" --post_excerpt='' >/dev/null

cleanup
trap - EXIT
echo "== Bilan ${SITE_DIR} : ${PASS} OK, ${FAIL} KO"
