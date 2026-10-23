# Audit sécurité — glossaire et éditeur — 2026-10-23

Verdict : ✅ VALIDÉ

Périmètre : fichiers modifiés par les quatre commits du jalon glossaire, avec contrôle automatique de l’ensemble du plugin.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php` | ✅ 73/73 fichiers |
| `vendor/bin/phpstan analyse --level=6` | ✅ aucune erreur |
| `vendor/bin/phpunit --group=security` | ✅ 75 tests, 208 assertions |
| `composer audit` | ✅ aucune vulnérabilité |
| `npm audit --audit-level=high` | ✅ 0 vulnérabilité |
| `gitleaks detect --no-banner` | ✅ 47 commits, aucun secret (binaire Docker officiel, dépôts commun et worktree montés) |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding Critique, Haute, Moyenne ou Basse dans le périmètre | — |

## Recherche de motifs dangereux

- Fonctions interdites (`eval`, `assert`, `unserialize`, `extract`, exécution de processus) : aucune occurrence.
- Accès entrants bruts : aucune nouvelle superglobale ; les occurrences existantes de `RequestGuard` appliquent `wp_unslash` et `sanitize_*`.
- SQL : toutes les nouvelles requêtes passent par `$wpdb->prepare()` ; les colonnes et ordres variables sont limités par listes blanches (`GlossaryRepository.php:53-65`, `SegmentEditorRepository.php:38-90`).
- Fichiers : seul un chemin CSV local, lisible, limité et typé est lu ; `php://memory` est utilisé pour le parsing et l’export (`GlossaryCsv.php:35-140`).
- Tous les nouveaux fichiers PHP contiennent la garde `defined( 'ABSPATH' ) || exit;`.

## Checklist manuelle

### 3.1 Réponses IA

- OK — sanitation centrale par `Sanitizer::inline_html()` et whitelist `wp_kses` (`src/Security/Sanitizer.php:23-41`).
- OK — sanitation avant stockage automatique (`src/Queue/BatchJob.php:101-111`) et avant édition manuelle (`src/Admin/RestController.php:215-217`, `src/Admin/SegmentEditorRepository.php:104-105`).
- OK — les attributs utilisent le chemin texte simple puis `esc_attr` dans le moteur de rendu existant.
- OK — validation JSON fournisseur et tests d’injection existants ; aucun changement dans `src/Providers/`.

### 3.2 Contrôle d’accès

- OK — toutes les routes utilisent `Capabilities::rest_permission` (`src/Admin/RestController.php:38-130`).
- OK — aucune route publique, aucun `__return_true`, aucun `wp_ajax_nopriv_*`.
- OK — le nonce `wp_rest` est vérifié par l’authentification cookie du cœur WordPress ; la capability `omtr_manage` est ensuite obligatoire.
- OK — test sans capability et test XSS : `tests/Unit/RestControllerSecurityTest.php`.

### 3.3 Clés API

- OK — aucun accès aux clés ajouté ; la porte unique reste `ApiKeyStore` et les valeurs stockées sont chiffrées (`src/Security/ApiKeyStore.php:33-77`).
- OK — aucun export du glossaire ne consulte les options ou clés API.

### 3.4 Abus et coûts

- OK — aucun appel IA depuis les nouvelles routes ; la retraduction est seulement proposée par lecture SQL.
- OK — `affected()` exige strictement `status = auto`, donc `edited` et `locked` sont exclus (`src/Glossary/GlossaryRepository.php:151-183`).
- OK — quotas, plafond et verrou restent appliqués par la file existante.

### 3.5 Réseau et redirections

- OK — aucun nouvel appel réseau ou redirection.
- OK — le crawler existant reste limité à l’hôte de `home_url()`, sans cookie et sans redirection (`src/Queue/ScanJob.php:148-206`).

### 3.6 Fichiers et imports

- OK — taille maximale 1 Mio, extension et MIME contrôlés avant lecture (`src/Glossary/GlossaryCsv.php:35-59`).
- OK — colonnes exactes et valeurs sanitizées/validées (`src/Glossary/GlossaryCsv.php:70-110`).
- OK — export anti-formule couvrant `=`, `+`, `-`, `@`, tabulation et retours chariot (`src/Glossary/GlossaryCsv.php:117-174`).
- OK — tests sécurité pour formules, surtaille, colonnes et HTML (`tests/Unit/GlossaryCsvSecurityTest.php`).

### 3.7 Base et désinstallation

- OK — requêtes préparées et tris/colonnes en listes blanches.
- OK — désinstallation protégée par `WP_UNINSTALL_PLUGIN`, suppression optionnelle des tables/options/transients/tâches et suppression systématique des clés (`uninstall.php:11-58`).

