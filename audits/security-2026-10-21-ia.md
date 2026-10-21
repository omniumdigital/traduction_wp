# Audit sécurité — v0.1.0 — 2026-10-21

Verdict : ✅ VALIDÉ

Périmètre : moteur IA et file Action Scheduler de la branche `omniumdigital/feat-moteur-ia`, y compris les tests ajoutés. Aucun finding Critique ou Haute ne reste ouvert.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php` | ✅ 54/54 fichiers |
| `vendor/bin/phpstan analyse --level=6 --memory-limit=1G` | ✅ aucune erreur |
| `vendor/bin/phpunit --group=security` | ✅ 52 tests, 126 assertions |
| `composer audit` | ✅ aucune vulnérabilité connue |
| `npm audit --audit-level=high` | ✅ 0 vulnérabilité |
| `gitleaks detect --no-banner` | ✅ 18 commits, aucune fuite (image Docker officielle, binaire hôte absent) |

La recette complète demandée (`composer install`, PHPCS, PHPStan, PHPUnit) passe : 100 tests, 247 assertions.

## Recherche de motifs dangereux

- Aucun `eval`, `assert`, `create_function`, `unserialize`, `extract`, appel shell/processus, inclusion dynamique ou lecture d'URL par fichier trouvé dans `src/`.
- Aucun accès brut à `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE` ou `$_SERVER` dans le périmètre.
- Aucun `wp_redirect()` ni `wp_remote_get()` non sécurisé ; le crawler emploie `wp_safe_remote_get()` (`src/Queue/ScanJob.php:152`).
- Tous les fichiers PHP du périmètre possèdent la garde `defined( 'ABSPATH' ) || exit;`.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Haute — corrigé | `src/Providers/PromptBuilder.php:83` | Un segment contenant `</segments>` pouvait fermer le délimiteur du prompt. | Le JSON applique désormais `JSON_HEX_TAG`/`JSON_HEX_AMP`; test d'injection dans `tests/Unit/PromptBuilderTest.php:79`. |
| Haute — corrigé | `src/Queue/ActionSchedulerQueue.php:169` | Les plafonds pouvaient subir une course entre contrôle et incrément. | Réservations atomiques par verrous d'options dans `WordCap::reserve()` et `HourlyQuota::reserve()`. En cas de concurrence, le travail est refusé. |
| Haute — corrigé | `src/Queue/ActionSchedulerQueue.php:199` | La validation initiale de l'URL ne comparait que l'hôte et acceptait un autre schéma/port. | Comparaison stricte schéma, hôte, port et chemin de base de `home_url()` ; query string et fragment supprimés. |
| Haute — corrigé | `src/Queue/ActionSchedulerQueue.php:102` | Un brouillon, contenu protégé ou écran WooCommerce à slug personnalisé pouvait être mis en file. | Vérification `publish`, mot de passe et ids WooCommerce compte/panier/commande avant planification. |
| Moyenne — corrigé | `src/Providers/Claude/ClaudeProvider.php:41` | Les modèles `claude-*-5-5` étaient non documentés. | Identifiants actifs officiels ; défaut daté `claude-haiku-4-5-20251001`. |
| Moyenne — corrigé | `src/Providers/ResponseValidator.php:25` | Les clés JSON numériques devenaient des entiers PHP et invalidaient toute réponse correcte. | Décodage en objet strict, contrôle des clés attendues, rejet des racines tableau et octets nuls. |

## Checklist manuelle

### 3.1 Réponses IA non fiables

- ✅ Le job applique obligatoirement `Sanitizer::inline_html()` avant `save_auto()` (`src/Queue/BatchJob.php:106`).
- ✅ Whitelist `wp_kses` limitée à `strong`, `em`, `b`, `i`, `br`, `span`, `a[href]`, protocoles `http`, `https`, `mailto`, `tel` (`src/Security/Sanitizer.php:23`).
- ✅ JSON strict uniquement, ids inconnus ignorés et corps non conforme rejeté (`src/Providers/ResponseValidator.php:25`).
- ✅ Un seul nouvel essai après JSON invalide, puis erreur non rejouable (`src/Providers/Claude/ClaudeProvider.php:119`).
- ✅ Le prompt qualifie les segments de données non fiables et neutralise la fermeture du délimiteur (`src/Providers/PromptBuilder.php:44`, `src/Providers/PromptBuilder.php:83`).
- ✅ Tests `@group security` avec `<script>`, `<img onerror>`, `javascript:`, injection de prompt, JSON invalide et ids inconnus (`tests/Unit/BatchJobTest.php:102`, `tests/Unit/PromptBuilderTest.php:79`, `tests/Unit/ResponseValidatorTest.php`).
- ℹ️ Réinsertion DOM et échappement des attributs relèvent de la branche Render, hors périmètre de cette branche ; celle-ci ne stocke que le texte sanitizé.

### 3.2 Contrôle d'accès

- ✅ Aucune route REST, action AJAX ou action admin ajoutée.
- ✅ Les commandes WP-CLI vérifient `omtr_manage` (`src/Queue/TranslateCli.php:183`).
- ✅ Enregistrement CLI uniquement lorsque `WP_CLI` est défini et vrai (`src/Queue/TranslateCli.php:43`).

### 3.3 Clés API

- ✅ Seul `ApiKeyStore::get( 'claude' )` fournit la clé déchiffrée (`src/Providers/Claude/ClaudeProvider.php:186`).
- ✅ La clé n'apparaît dans aucun `Result`, log ou exception.
- ✅ Les erreurs ne conservent que le code HTTP et un message générique ; jamais le corps brut (`src/Providers/Claude/ClaudeProvider.php:211`).

### 3.4 Abus et coûts

- ✅ Origine `home_url()` stricte, query string ignorée, chemins sensibles et contenus non publics refusés (`src/Queue/ActionSchedulerQueue.php:96`, `src/Queue/ActionSchedulerQueue.php:199`).
- ✅ Quota horaire des segments à la volée et plafond mensuel de mots réservés atomiquement avant mise en file (`src/Queue/HourlyQuota.php:64`, `src/Queue/WordCap.php:88`).
- ✅ Verrou atomique d'une traduction complète (`src/Queue/RunLock.php:39`).
- ✅ Les chemins visiteurs ne font qu'appeler `as_schedule_single_action`; l'appel fournisseur est limité au hook de lot (`src/Queue/QueueModule.php:101`).
- ✅ Chaque lot recharge les traductions existantes juste avant l'appel IA (`src/Queue/BatchJob.php:74`).

### 3.5 Réseau et redirections

- ✅ Endpoint Anthropic HTTPS codé en dur, timeout 60 s et `sslverify => true` (`src/Providers/Claude/ClaudeProvider.php:32`, `src/Providers/Claude/ClaudeProvider.php:197`).
- ✅ Crawler via `wp_safe_remote_get()` après validation de `home_url()` (`src/Queue/ScanJob.php:152`).
- ✅ Aucune redirection ajoutée.

### 3.6 Fichiers et imports

- N/A : aucun import/export ajouté par cette branche.

### 3.7 Base et désinstallation

- ✅ Aucun SQL direct ajouté ; la file dépend exclusivement des interfaces Store.
- ✅ Les nouvelles options `omtr_*` et leurs verrous figurent dans `Options::all()` et sont donc prises en charge par la purge (`src/Core/Options.php:17`).

