# Audit sécurité — intégration jalon 2 — 2026-10-21

Verdict : ✅ VALIDÉ

Périmètre : correctifs WP-CLI, routeur/sous-dossier, traducteur d'URL et crawler modifiés pendant la recette d'intégration. Aucun finding Critique ou Haute ouvert.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php` | ✅ 64/64 fichiers |
| `vendor/bin/phpstan analyse --level=6 --memory-limit=1G` | ✅ aucune erreur |
| `vendor/bin/phpunit --group=security` | ✅ 61 tests, 147 assertions |
| `composer audit` | ✅ aucune vulnérabilité connue |
| `npm audit --audit-level=high` | ✅ 0 vulnérabilité |
| `gitleaks detect --no-banner` | ✅ 22 commits, aucune fuite (image Docker officielle) |

Recherche de motifs dangereux : aucun appel interdit, aucune lecture d'URL par fichier, aucune redirection non sûre ni fichier PHP sans garde `ABSPATH`. L'accès brut à `REQUEST_URI` dans `LanguageRouter` reste limité à un découpage byte-for-byte, avec justification PHPCS (`src/Router/LanguageRouter.php:42`).

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Bloquant — corrigé | `src/Queue/TranslateCli.php:47` | WP-CLI instanciait la classe sans ses cinq dépendances. | Enregistrement de l'instance construite par le conteneur ; test d'identité `tests/Unit/TranslateCliTest.php:75`. |
| Haute — corrigé | `src/Queue/ScanJob.php:178` | Les redirections du crawler pouvaient atteindre un autre hôte public. | Redirections désactivées ; validation défensive stricte schéma/hôte/port avant requête. |
| Moyenne — corrigé | `src/Queue/TranslateCli.php:176` | `WP_CLI::format_items()` n'existe pas et causait un fatal réel. | Appel documenté `WP_CLI\Utils\format_items()` et test de non-régression. |

## Checklist manuelle

### 3.1 Réponses IA non fiables

- ✅ Traductions stockées et manuelles repassent par `Sanitizer` au rendu ; recette réelle avec `<script>`, `<img onerror>` neutralisée.
- ✅ Réinsertion DOM et attributs échappés couverts par les tests sécurité existants et la recette (`XSS_NEUTRALIZED`).
- ✅ JSON, prompt et tests d'injection existants inchangés et au vert.

### 3.2 Contrôle d'accès

- ✅ La commande conserve le contrôle `omtr_manage` (`src/Queue/TranslateCli.php:183`).
- ✅ Aucune route REST/AJAX/admin ajoutée.

### 3.3 Clés API

- ✅ Aucun changement de stockage ou d'exposition ; la recette sans clé produit uniquement une erreur générique.

### 3.4 Abus et coûts

- ✅ Aucun appel IA visiteur ; le CLI ne fait que planifier la file.
- ✅ Verrou, quotas et relecture des traductions existantes inchangés et testés.

### 3.5 Réseau et redirections

- ✅ `wp_safe_remote_get()` uniquement après comparaison exacte avec `home_url()` (`src/Queue/ScanJob.php:148`).
- ✅ L'exception temporaire de validation WordPress n'autorise que l'hôte et le port exacts du site, puis les filtres sont retirés en `finally` (`src/Queue/ScanJob.php:163`).
- ✅ `redirection => 0` empêche toute sortie vers un hôte de redirection (`src/Queue/ScanJob.php:185`).
- ✅ Test dédié : hôte étranger refusé et seul le port du site ajouté (`tests/Unit/ScanJobTest.php:267`).

### 3.6 Fichiers et imports

- N/A : aucun import/export modifié.

### 3.7 Base et désinstallation

- ✅ Aucun schéma ni SQL applicatif ajouté ; aucune migration requise.
