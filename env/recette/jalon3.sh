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

W() { npx wp-env run cli wp "$@" 2>&1 | grep -v '^ℹ\|^✔\|^$'; }
# Le conteneur CLI joint le site via host.docker.internal (localhost y désigne le conteneur lui-même).
EXEC="WP_CLI::add_hook('after_wp_load', function () { add_filter('home_url', function (\$u) { return str_replace('localhost:${PORT}', 'host.docker.internal:${PORT}', \$u); }); });"
RUN_QUEUE() { W --exec="$EXEC" action-scheduler run --hooks=omtr_scan_url,omtr_translate_batch --batch-size=50 --batches=20 >/dev/null; }
PENDING_SCANS() { W action-scheduler action list --hook=omtr_scan_url --status=pending --format=count | tail -1; }
SQL() { W db query "$1" --skip-column-names | tail -1; }
PASS=0; FAIL=0
check() { if [ "$2" = "1" ]; then echo "OK $1"; PASS=$((PASS + 1)); else echo "KO $1"; FAIL=$((FAIL + 1)); fi; }
settings() { W option update omtr_settings "{\"source_lang\":\"fr\",\"target_langs\":[\"en\",\"es\"],\"on_the_fly\":$1,\"auto_translate\":$2}" --format=json >/dev/null; }
clear_queue() {
	SQL "DELETE FROM wp_actionscheduler_actions WHERE hook IN ('omtr_scan_url','omtr_translate_batch') AND status = 'pending'" >/dev/null
	SQL "DELETE FROM wp_options WHERE option_name LIKE '%omtr_otf_%' OR option_name LIKE '%omtr_translation_lock%'" >/dev/null
}

SLUG="$(W post get "$POST_ID" --field=post_name | tail -1)"
echo "== Site ${SITE_DIR} (port ${PORT}), page ${POST_ID} « ${SLUG} »"
settings false false
clear_queue

echo "-- Clé API"
KEYS="$(W omtr key status --user=admin)"
echo "$KEYS" | sed 's/^/   /'
check "aucune clé affichée en clair (masque uniquement)" "$(echo "$KEYS" | grep -cE '(sk-|AIza)[A-Za-z0-9_-]{12,}' | sed 's/^0$/1/;t;s/.*/0/')"

echo "-- Glossaire"
W omtr glossary add Omnium en '' keep --user=admin >/dev/null
W omtr glossary add atelier en workshop translate --user=admin >/dev/null
check "règles ajoutées" "$([ "$(W omtr glossary list --user=admin --format=count | tail -1)" -ge 2 ] && echo 1 || echo 0)"
mkdir -p "$ROOT/env/recette/tmp"
printf 'term,target_lang,translation,rule\n=HYPERLINK("http://evil.test"),en,x,translate\nbonjour,en,<script>alert(1)</script>hello,translate\n' > "$ROOT/env/recette/tmp/glossaire.csv"
W omtr glossary import wp-content/plugins/omnium-translate/env/recette/tmp/glossaire.csv --user=admin >/dev/null
EXPORT="$(W omtr glossary export --user=admin)"
check "export CSV : formule neutralisée par une apostrophe" "$(echo "$EXPORT" | grep -c "^\"\?'=HYPERLINK" | sed 's/^[1-9].*/1/')"
check "export CSV : aucune balise <script>" "$(echo "$EXPORT" | grep -c '<script' | sed 's/^0$/1/;t;s/.*/0/')"

echo "-- Estimation"
EST="$(W omtr estimate "$POST_ID" --user=admin)"
echo "$EST" | sed 's/^/   /'
check "estimation produite" "$(echo "$EST" | grep -ci 'segment' | sed 's/^[1-9].*/1/')"

echo "-- Traduction d'une page sans clé"
W omtr translate "$POST_ID" --user=admin >/dev/null
RUN_QUEUE
SEGS=$(SQL "SELECT COUNT(*) FROM wp_omtr_segments")
check "segments extraits par le crawler anonyme (${SEGS})" "$([ "${SEGS:-0}" -gt 0 ] && echo 1 || echo 0)"
check "aucun email dans les segments" "$([ "$(SQL "SELECT COUNT(*) FROM wp_omtr_segments WHERE source_text REGEXP '[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\\\\.[A-Za-z]{2,}'")" = "0" ] && echo 1 || echo 0)"
FAILED=$(SQL "SELECT COUNT(*) FROM wp_omtr_translations WHERE status = 'failed'")
check "sans clé, les lots échouent proprement (${FAILED} segments failed)" "$([ "${FAILED:-0}" -gt 0 ] && echo 1 || echo 0)"
check "logs sans email ni texte de segment" "$([ "$(SQL "SELECT COUNT(*) FROM wp_omtr_log WHERE message LIKE '%@%' OR message IN (SELECT source_text FROM wp_omtr_segments)")" = "0" ] && echo 1 || echo 0)"

echo "-- Crawler : contenus nouveaux ou modifiés"
W omtr crawl --user=admin | sed 's/^/   /'
RUN_QUEUE
SECOND="$(W omtr crawl --user=admin)"
echo "$SECOND" | sed 's/^/   2e passage : /'
RUN_QUEUE

echo "-- Pause et reprise"
W omtr pause --user=admin >/dev/null
check "file en pause : traduction refusée" "$(W omtr translate "$POST_ID" --user=admin | grep -ci 'pause' | sed 's/^[1-9].*/1/')"
W omtr resume --user=admin >/dev/null
clear_queue

echo "-- Droits, REST, éditeur, actions admin"
PHPOUT="$(W eval-file wp-content/plugins/omnium-translate/env/recette/jalon3.php "$POST_ID")"
echo "$PHPOUT" | sed 's/^/   /'
PASS=$((PASS + $(echo "$PHPOUT" | grep -c '^OK ')))
FAIL=$((FAIL + $(echo "$PHPOUT" | grep -c '^KO ')))

echo "-- Traduction à la volée (option activée)"
settings true false
clear_queue
for i in $(seq 1 20); do curl -s -o /dev/null "http://localhost:${PORT}/en/${SLUG}/?s=INJECTIONRECETTE${i}&utm_source=${i}"; done
check "20 requêtes avec ?s= différents => 1 seule mise en file ($(PENDING_SCANS))" "$([ "$(PENDING_SCANS)" = "1" ] && echo 1 || echo 0)"
check "URL mise en file canonique, sans query string" "$(W action-scheduler action list --hook=omtr_scan_url --status=pending --fields=args --format=csv | grep -c 'INJECTION\|?s=' | sed 's/^0$/1/;t;s/.*/0/')"
RUN_QUEUE
check "aucun texte injecté par ?s= en base" "$([ "$(SQL "SELECT COUNT(*) FROM wp_omtr_segments WHERE source_text LIKE '%INJECTIONRECETTE%'")" = "0" ] && echo 1 || echo 0)"
clear_queue
curl -s -o /dev/null -b "wordpress_logged_in_x=1" "http://localhost:${PORT}/en/${SLUG}/" # cookie factice : simple contrôle de non-régression
check "panier (/en/cart/) ne déclenche rien" "$(curl -s -o /dev/null "http://localhost:${PORT}/en/cart/"; [ "$(PENDING_SCANS)" -le 1 ] && echo 1 || echo 0)"

echo "-- Traduction automatique à la publication (option activée)"
settings false true
clear_queue
W post update "$POST_ID" --post_excerpt="Recette jalon 3 $(date +%s)" >/dev/null
check "mise à jour d'un contenu public => scan différé en file ($(PENDING_SCANS))" "$([ "$(PENDING_SCANS)" -ge 1 ] && echo 1 || echo 0)"
W post update "$POST_ID" --post_excerpt='' >/dev/null

settings false false
clear_queue
echo "== Bilan ${SITE_DIR} : ${PASS} OK, ${FAIL} KO"
