# Audit sécurité — jalon 2 rendu + stockage — 2026-10-21

Verdict : ✅ VALIDÉ pour le périmètre rendu, routeur, stockage et filtre PII

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php` | ✅ 53 fichiers, aucune erreur ni avertissement |
| `vendor/bin/phpstan analyse --level=6 --memory-limit=1G` | ✅ aucune erreur |
| `vendor/bin/phpunit --group=security` | ✅ 15 tests, 39 assertions |
| `composer audit --no-interaction` | ✅ aucun avis de sécurité |
| `npm audit --audit-level=high` | ⚠️ 20 vulnérabilités hautes héritées de `@wordpress/scripts`, uniquement dans les outils de développement ; correction disponible seulement par mise à niveau majeure forcée, explicitement laissée hors périmètre par le coordinateur |
| `npm audit --omit=dev --audit-level=high` | ✅ 0 vulnérabilité dans les dépendances livrées |
| `gitleaks detect --no-banner` | ✅ 3 commits, environ 1,04 Mo analysé, aucune fuite |

Les suppressions PHPCS dans `SegmentRepository` et `TranslationRepository` sont limitées aux listes `IN` dont seul le nombre de placeholders `%s` est généré dynamiquement. Les noms de tables passent par `%i` et toutes les valeurs passent effectivement à `$wpdb->prepare()` ; chaque autre suppression sur requête marque une variable produite immédiatement par `prepare()`. Les suppressions sur les propriétés DOM couvrent uniquement les noms publics natifs camelCase de PHP (`nodeType`, `nodeValue`, `childNodes`, etc.).

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Information | `package-lock.json` | Vulnérabilités héritées dans les outils npm de développement, absentes du zip et des dépendances de production | Traiter la montée majeure de `@wordpress/scripts` dans un chantier dépendances séparé |

Aucun finding Critique ou Haute dans le périmètre audité.

## Recherche de motifs dangereux

- Aucune fonction interdite (`eval`, `assert`, `unserialize`, exécution de processus) dans `src/`.
- Aucun accès réseau ajouté ; aucune redirection ajoutée.
- Les accès `$_GET` et `$_SERVER` du routeur sont en lecture seule et passent par `wp_unslash()` puis `sanitize_text_field()` ou `sanitize_url()` (`src/Router/RequestGuard.php:35-36`, `src/Router/LanguageRouter.php:38-42`).
- Toutes les requêtes ajoutées utilisent `$wpdb->prepare()` ; les identifiants de tables utilisent `%i`.
- Tous les nouveaux fichiers PHP contiennent la garde `defined( 'ABSPATH' ) || exit;`.

## Checklist manuelle

### 3.1 Réponses de l'IA = entrées non fiables

- OK — réinsertion via import de nœuds DOM, pas par concaténation dans le document (`src/Render/Translator.php:147-174`).
- OK — double passage par `Sanitizer::inline_html()` avant stockage et avant sortie (`src/Store/TranslationRepository.php:72`, `src/Render/Translator.php:137-143`) ; whitelist `wp_kses` (`src/Security/Sanitizer.php:23-41`).
- OK — attributs et métadonnées passent par texte brut puis `esc_attr()` (`src/Render/Translator.php:99-110`, `src/Render/Translator.php:177-184`).
- N/A — validation JSON fournisseur et construction du prompt appartiennent au lot Providers/ réservé à l'autre agent et ne sont pas modifiées ici.
- OK — test de sortie avec `<script>`, `<img onerror>`, URL `javascript:` et instruction hostile traitée comme simple donnée (`tests/Unit/TranslatorSecurityTest.php:43-63`).

### 3.2 Contrôle d'accès

- N/A — aucune route REST ni action d'administration ajoutée.
- OK — `save_edit()` applique lui-même `Sanitizer::inline_html()`, indépendamment du rôle appelant (`src/Store/TranslationRepository.php:112-127`).
- OK — aucun `wp_ajax_nopriv_*` ajouté.

### 3.3 Clés API

- N/A — aucun changement au stockage ni à l'affichage des clés.

### 3.4 Abus et coûts

- OK — le rendu effectue exclusivement extraction, cache et lecture groupée ; aucun fournisseur ni service de file n'est injecté (`src/Render/Translator.php:45-67`).
- N/A — quotas, verrou et traduction à la volée appartiennent au lot Queue/ réservé à l'autre agent.

### 3.5 Réseau et redirections

- OK — aucun appel réseau ni redirection. `UrlTranslator` refuse les autres origines et les chemins admin/REST (`src/Router/UrlTranslator.php:32-51`).

### 3.6 Fichiers et imports

- N/A — aucun import ou export ajouté.

### 3.7 Base de données et désinstallation

- OK — noms de tables fixes issus de `Tables`, passés par `%i`, valeurs préparées (`src/Store/TranslationRepository.php:38-55`, `src/Store/SegmentRepository.php:35-90`).
- N/A — aucun changement de schéma ni de désinstallation.
