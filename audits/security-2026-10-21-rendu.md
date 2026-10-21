# Audit sécurité — jalon 2 rendu + stockage — 2026-10-21

Verdict : ✅ VALIDÉ pour le périmètre rendu, routeur, stockage et filtre PII

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php` | ✅ 53 fichiers, aucune erreur ni avertissement |
| `vendor/bin/phpstan analyse --level=6 --memory-limit=1G` | ✅ aucune erreur |
| `vendor/bin/phpunit --group=security` | ✅ 15 tests, 39 assertions |
| `composer audit --no-interaction` | ✅ aucun avis de sécurité |
| `npm audit --audit-level=high` | ✅ 0 vulnérabilité |
| `gitleaks detect --no-banner` (image officielle, worktree complet monté) | ✅ 16 commits, environ 1,30 Mo analysé, aucune fuite |

Les suppressions PHPCS SQL restent limitées aux listes `IN` dont seul le nombre de placeholders est généré ; les identifiants de tables utilisent `%i` et toutes les valeurs passent à `$wpdb->prepare()`. La suppression PHPCS de `LanguageRouter.php:42` est intentionnelle : l'URI serveur brute n'est ni exécutée ni affichée, seulement découpée sur un préfixe connu afin de préserver exactement ses octets encodés.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Information | `src/Render/HtmlSegmentExtractor.php:65-81` | PHP 8.1–8.3 utilise le parseur HTML4 `DOMDocument`, susceptible de normaliser certaines constructions HTML5 complexes sur une page traduite | Limite documentée ; PHP 8.4+ utilise `Dom\HTMLDocument`. La langue source ne passe jamais dans le parseur |

Aucun finding Critique, Haute, Moyenne ou Basse dans le périmètre audité.

## Recherche de motifs dangereux

- Aucune fonction interdite (`eval`, `assert`, `unserialize`, exécution de processus) dans `src/`.
- Aucun nouvel appel réseau ni aucune redirection.
- Les paramètres d'éditeur et Woo AJAX passent par `wp_unslash()` puis `sanitize_text_field()`/`sanitize_key()` (`src/Router/RequestGuard.php:28-35`, `src/Router/RequestGuard.php:64-69`). Le chemin REST de classification passe par `wp_unslash()` et `sanitize_url()` (`src/Router/RequestGuard.php:75-82`).
- Toutes les requêtes ajoutées utilisent `$wpdb->prepare()` ; `hashes_by_ids()` effectue une requête unique et omet les identifiants inconnus (`src/Store/SegmentRepository.php:103-123`).
- Tous les fichiers PHP de `src/` contiennent la garde `defined( 'ABSPATH' ) || exit;`.

## Checklist manuelle

### 3.1 Réponses de l'IA = entrées non fiables

- OK — réinsertion par import de nœuds DOM, jamais par affectation d'HTML au document (`src/Render/Translator.php:137-170`).
- OK — traduction filtrée par `Sanitizer::inline_html()` au stockage, à la lecture et avant création du fragment (`src/Store/TranslationRepository.php:58`, `src/Store/TranslationRepository.php:78`, `src/Render/Translator.php:127-149`).
- OK — attributs traduits passent par texte brut puis `esc_attr()` (`src/Render/Translator.php:173-180`).
- N/A — validation JSON fournisseur et prompt sont hors de ce lot.
- OK — test de sortie avec `<script>`, `<img onerror>`, `javascript:` et instruction hostile (`tests/Unit/TranslatorSecurityTest.php`).
- OK — test HTML5 réaliste couvrant SVG, template, noscript, JSON-LD et contenu de script piégeux (`tests/Unit/Html5RenderingTest.php:64-82`).

### 3.2 Contrôle d'accès

- N/A — aucune route REST ni action d'administration ajoutée.
- OK — `save_edit()` applique directement `Sanitizer::inline_html()` quel que soit l'appelant (`src/Store/TranslationRepository.php:119-135`).
- OK — aucun `wp_ajax_nopriv_*` ajouté.

### 3.3 Clés API

- N/A — aucun changement au stockage ni à l'affichage des clés.

### 3.4 Abus et coûts

- OK — le rendu ne possède aucune dépendance fournisseur ou file et ne fait que lire les traductions stockées (`src/Render/Translator.php:46-74`).
- OK — l'éligibilité future au crawl/IA exclut session connectée, aperçu, page protégée, panier, commande, compte et `order-received` (`src/Router/RequestGuard.php:47-58`).
- N/A — quotas et verrou de traduction appartiennent au lot Queue.

### 3.5 Réseau et redirections

- OK — aucun appel réseau ajouté. `UrlTranslator` refuse les autres origines et les chemins admin/REST.

### 3.6 Fichiers et imports

- N/A — aucun import ou export ajouté.

### 3.7 Base de données et désinstallation

- OK — noms de tables fixes via `Tables`, passés par `%i`, valeurs préparées (`src/Store/TranslationRepository.php:36-60`, `src/Store/SegmentRepository.php:103-123`).
- OK — le cache visiteur utilise uniquement `wp_cache_*`, groupe `omtr`, sans transient ni écriture `wp_options` (`src/Render/Translator.php:59-68`).
- N/A — aucun changement de schéma ni de désinstallation.
