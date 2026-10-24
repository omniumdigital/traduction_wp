#!/usr/bin/env bash
# Final v1 acceptance: bash env/recette/v1.sh gutenberg|elementor|oxygen
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
KIND="${1:?site type required}"
case "$KIND" in
	gutenberg) SITE="$ROOT"; PORT=8881 ;;
	elementor) SITE="$ROOT/env/elementor"; PORT=8882 ;;
	oxygen) SITE="$ROOT/env/oxygen"; PORT=8883 ;;
	*) exit 2 ;;
esac
cd "$SITE"
W() { npx wp-env run cli wp "$@" 2>&1 | sed '/^ℹ/d; /^✔/d'; }
PHP='wp-content/plugins/omnium-translate/env/recette/v1.php'
BASE="http://localhost:$PORT"
EXEC="WP_CLI::add_hook('after_wp_load', function () { add_filter('home_url', function (\$url) { return str_replace('localhost:${PORT}', 'host.docker.internal:${PORT}', \$url); }); });"
cleanup() { npx wp-env stop >/dev/null 2>&1 || true; }
trap cleanup EXIT
echo "START $KIND $BASE"
npx wp-env start
W plugin activate omnium-translate >/dev/null
W option update WPLANG fr_FR >/dev/null
W rewrite structure '/%postname%/' >/dev/null
if [ "$KIND" = elementor ]; then bash "$ROOT/env/elementor/seed.sh"; fi
if [ "$KIND" = oxygen ]; then bash "$ROOT/env/oxygen/seed.sh"; fi
W eval-file "$PHP" seed "$KIND"
W --exec="$EXEC" eval-file "$PHP" wizard "$KIND"
pending() { W eval 'global $wpdb; WP_CLI::line( (string) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE status = '\''pending'\'' AND hook IN ('\''omtr_scan_url'\'','\''omtr_translate_batch'\'')" ) );' | tail -1 | tr -d '\r'; }
calls() { W eval 'WP_CLI::line( (string) get_option( "omtr_mock_calls", 0 ) );' | tail -1 | tr -d '\r'; }
for attempt in $(seq 1 100); do
	count="$(pending)"
	[ "$count" -eq 0 ] && break
	W --exec="$EXEC" action-scheduler run --hooks=omtr_scan_url,omtr_translate_batch --batch-size=100 --batches=20 >/dev/null
done
echo "QUEUE_PENDING=$(pending) MOCK_CALLS=$(calls)"
for lang in en es; do
	upper="$(printf '%s' "$lang" | tr '[:lower:]' '[:upper:]')"
	for slug in recette-v1-01 recette-v1-30; do
		body="$(curl -fsS -w '\nHTTP:%{http_code}' "$BASE/$lang/$slug/")"
		printf 'PAGE %s/%s %s PREFIX=%s HREFLANG=%s CANONICAL=%s\n' "$lang" "$slug" "$(printf '%s' "$body" | tail -1)" "$(printf '%s' "$body" | grep -c "\[$upper\]" || true)" "$(printf '%s' "$body" | grep -ci 'hreflang=' || true)" "$(printf '%s' "$body" | grep -ci 'rel="canonical"' || true)"
	done
	if [ "$KIND" != gutenberg ]; then
		for slug in cart checkout my-account; do
			echo "WOO $lang/$slug HTTP=$(curl -sS -o /dev/null -w '%{http_code}' "$BASE/$lang/$slug/")"
		done
	fi
done
before="$(calls)"
W --exec="$EXEC" eval-file "$PHP" edited "$KIND"
W --exec="$EXEC" eval-file "$PHP" retranslate "$KIND"
for attempt in $(seq 1 100); do
	count="$(pending)"
	[ "$count" -eq 0 ] && break
	W --exec="$EXEC" action-scheduler run --hooks=omtr_scan_url,omtr_translate_batch --batch-size=100 --batches=20 >/dev/null
done
after="$(calls)"
echo "RETRANSLATE_CALLS=$((after - before))"
W eval 'global $wpdb; WP_CLI::line( wp_json_encode( $wpdb->get_row( "SELECT status, text FROM {$wpdb->prefix}omtr_translations WHERE status = '\''edited'\'' LIMIT 1", ARRAY_A ) ) );'
W eval 'WP_CLI::line( file_exists( WP_CONTENT_DIR . "/debug.log" ) ? file_get_contents( WP_CONTENT_DIR . "/debug.log" ) : "DEBUG_LOG_EMPTY" );' | tail -20
W eval-file "$PHP" purge "$KIND"
W plugin deactivate omnium-translate
echo "SOURCE_AFTER_DEACTIVATION=$(curl -fsS "$BASE/recette-v1-01/" | grep -c 'Recette publique numéro 1' || true)"
# The plugin is bind mounted to the worktree. WP-CLI plugin uninstall would
# delete the worktree; invoke its uninstall procedure without removing files.
W eval 'define( "WP_UNINSTALL_PLUGIN", "omnium-translate/omnium-translate.php" ); include WP_PLUGIN_DIR . "/omnium-translate/uninstall.php";'
W eval 'global $wpdb; WP_CLI::line( "TABLES=" . $wpdb->get_var( "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name LIKE '\''wp\\_omtr\\_%'\''" ) ); WP_CLI::line( "OPTIONS=" . $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\''omtr\\_%'\'' OR option_name LIKE '\''_transient\\_omtr\\_%'\''" ) ); WP_CLI::line( "META=" . $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key LIKE '\''\\_omtr\\_%'\''" ) );'
echo "END $KIND"
