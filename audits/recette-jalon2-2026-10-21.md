# Recette jalon 2 — Gutenberg — 2026-10-21

Verdict : ✅ RÉUSSIE

Environnement : WordPress Gutenberg seul via `wp-env`, PHP 8.1.34, site HTTP `http://localhost:8881`, compte `admin`. Docker 29.4.0 était disponible.

## Préparation

```bash
npm ci --ignore-scripts
npm run env:gutenberg
npx wp-env run cli wp rewrite structure '/%postname%/' --hard
npx wp-env run cli wp option update omtr_settings \
  '{"source_lang":"fr","target_langs":["en"],"provider":"claude","model":"","auto_translate":false,"on_the_fly":false,"monthly_word_cap":0,"hourly_quota":0,"noindex_partial":false,"pref_cookie":false,"site_tone":"","site_context":""}' \
  --format=json
npx wp-env run cli wp post create --post_type=page --post_status=publish \
  --post_title='Recette Reprise Jalon Deux' --post_name='recette-reprise-jalon-deux' \
  --post_content='<p>Bonjour reprise, ceci est un texte public unique.</p><p>Contactez recette-reprise@example.test pour une donnée privée.</p><figure><img src="https://example.test/image-reprise.jpg" alt="Une image de reprise démonstrative"></figure><p><a href="http://localhost:8881/recette-reprise-jalon-deux/">Lien interne de reprise</a></p>' \
  --porcelain
```

Résultats : installation npm sans script, `0 vulnerabilities`, site démarré sur le port 8881, page publiée avec l'id `6`, réglages relus correctement (`fr` → `en`).

## Routeur et rendu source

```bash
curl -fsS http://localhost:8881/en/recette-reprise-jalon-deux/ -o /tmp/omtr-reprise-en.html
curl -fsS http://localhost:8881/recette-reprise-jalon-deux/ -o /tmp/omtr-reprise-source-active.html
npx wp-env run cli wp plugin deactivate traduction_wp
curl -fsS http://localhost:8881/recette-reprise-jalon-deux/ -o /tmp/omtr-reprise-source-inactive.html
npx wp-env run cli wp plugin activate traduction_wp
cmp -s /tmp/omtr-reprise-source-active.html /tmp/omtr-reprise-source-inactive.html
shasum -a 256 /tmp/omtr-reprise-source-active.html /tmp/omtr-reprise-source-inactive.html
```

Résultats :

- `/en/recette-reprise-jalon-deux/` répond 200 et contient `<html lang="en">` ;
- le lien de contenu est réécrit en `http://localhost:8881/en/recette-reprise-jalon-deux/` ;
- la source non préfixée est strictement identique avec et sans le plugin (`cmp` = 0), SHA-256 commun `1e899bddbb3673e2dd7683c788598395529bcfa3b489e63ba40c5478e171866b` ;
- le texte et l'attribut `alt` source restent affichés tant qu'aucune traduction n'existe.

## File de traduction sans clé API

Le conteneur CLI de `wp-env` ne peut pas joindre `localhost:8881` dans son propre espace réseau. Pour exercer le vrai crawler sans modifier la configuration publique, le filtre `home_url` ci-dessous remplace uniquement l'hôte durant les commandes WP-CLI ; `host.docker.internal:8881` reste le même serveur et le code vérifie strictement schéma, hôte et port.

```bash
npx wp-env run cli wp \
  --exec='WP_CLI::add_hook("after_wp_load", function(){ add_filter("home_url", function($url){ return str_replace("localhost", "host.docker.internal", $url); }); });' \
  omtr translate 6 --user=admin

# La désactivation/réactivation ci-dessus a recréé une migration interne
# Action Scheduler dans cet environnement persistant : la terminer d'abord.
npx wp-env run cli wp action-scheduler run \
  --hooks=action_scheduler/migration_hook --batch-size=10 --batches=10

npx wp-env run cli wp \
  --exec='WP_CLI::add_hook("after_wp_load", function(){ add_filter("home_url", function($url){ return str_replace("localhost", "host.docker.internal", $url); }); });' \
  action-scheduler run --group=omtr --batch-size=100 --batches=10
```

Résultats :

- `wp omtr translate 6` : succès, aucun `ArgumentCountError` ;
- `wp omtr status --user=admin` : tableau affiché sans fatal via `WP_CLI\Utils\format_items()` ;
- actions `omtr_scan_url` et `omtr_translate_batch` terminées ;
- les segments propres à la page sont créés, dont le texte public (`id=44`), le lien (`id=46`) et l'attribut `alt` (`id=45`) ;
- `SELECT COUNT(*) ... WHERE source_text LIKE '%recette-reprise@example.test%'` retourne `0` : l'email est exclu par `PiiFilter` ;
- sans clé Anthropic, les cinq traductions propres à la page ont le statut `failed`, sans fatal ;
- le log reste générique : `Batch failed after 1 attempt(s) (http=0).` ; aucune occurrence de l'email, du texte source ou d'une clé (`sensitive_logs = 0`).

## Traduction manuelle et XSS

```bash
npx wp-env run cli wp db query "
UPDATE wp_omtr_translations
SET text='Hello reprise safe <script>alert(71)</script><img src=x onerror=alert(71)>',
    status='edited', provider='manual', model='', updated_at=UTC_TIMESTAMP()
WHERE segment_id=44 AND target_lang='en';
UPDATE wp_omtr_translations
SET text='A safe reprise image <script>alert(72)</script><img src=x onerror=alert(72)>',
    status='edited', provider='manual', model='', updated_at=UTC_TIMESTAMP()
WHERE segment_id=45 AND target_lang='en';"
curl -fsS http://localhost:8881/en/recette-reprise-jalon-deux/ \
  -o /tmp/omtr-reprise-en-edited.html
```

Résultats : les lignes sont bien `edited`/`manual`. La page affiche `Hello reprise safe alert(71)` et l'attribut `alt="A safe reprise image alert(72)"`. Aucun `<script>alert(...)`, `<img src=x ...>` ni attribut `onerror` injecté n'est présent (`XSS_NEUTRALIZED`) ; `<html lang="en">` et le lien interne préfixé restent corrects.

## Contrôles complémentaires

```bash
npm audit --audit-level=high
npm run lint
npm run build
docker run --rm -v "$PWD":/app -w /app composer:2 \
  sh -c 'composer install -q && vendor/bin/phpcs && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/phpunit'
```

Résultats finaux : npm audit 0 vulnérabilité, Biome valide, build valide, PHPCS 64/64 fichiers, PHPStan sans erreur, PHPUnit 133 tests et 332 assertions.
