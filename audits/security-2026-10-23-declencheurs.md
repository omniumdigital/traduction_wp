# Audit sécurité — v0.1.0 — 2026-10-23

Verdict : ✅ VALIDÉ

Périmètre : estimation, plafonds, pause/reprise et déclencheurs du jalon 3, plus leurs points d’intégration dans `src/Admin`, `src/Queue` et `src/Render`.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php` | ✅ 81 fichiers, aucune erreur |
| `vendor/bin/phpstan analyse --level=6 --memory-limit=1G` | ✅ aucune erreur |
| `vendor/bin/phpunit --group=security` | ✅ 82 tests, 228 assertions |
| `composer audit` | ✅ aucune vulnérabilité connue |
| `npm audit --audit-level=high` | ✅ 0 vulnérabilité |
| `gitleaks detect --no-banner` | ✅ 51 commits, aucune fuite |

## Recherche de motifs dangereux

- Aucun `eval`, `assert`, `unserialize`, `extract`, exécuteur système, include dynamique, redirection non sûre ou `wp_ajax_nopriv_*` dans `src/`.
- Les seules lectures directes de superglobal ajoutées sont `$_GET['post_id']`, immédiatement passées par `wp_unslash()` et `absint()` avant la vérification du nonce (`src/Admin/TranslationActions.php:76-77`).
- Les accès SQL du périmètre passent par les repositories et `$wpdb->prepare()` ; le nom des tables vient de `Core\Tables`.
- Tous les fichiers PHP ajoutés commencent par la garde `defined( 'ABSPATH' ) || exit;`.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding bloquant ou non bloquant sur le périmètre audité. | — |

## Checklist manuelle

### 3.1 Réponses de l’IA = entrées non fiables

- **OK** — Toute réponse fournisseur passe par `Sanitizer::inline_html()` avant stockage (`src/Queue/BatchJob.php:116-126`).
- **OK** — La whitelist est limitée à `strong`, `em`, `b`, `i`, `br`, `span` et `a[href]`, avec protocoles sûrs (`src/Security/Sanitizer.php:23-42`).
- **OK** — La réinsertion utilise des nœuds DOM et les attributs passent par `esc_attr()` (`src/Render/Translator.php:92-120`, `153-180`).
- **OK** — La réponse JSON invalide est rejetée par `ResponseValidator`; les tests d’injection XSS/prompt sont dans `BatchJobTest`, `ResponseValidatorTest`, `PromptBuilderTest` et `TranslatorSecurityTest`.
- **OK** — Le prompt déclare les segments comme données non fiables et interdit d’exécuter leurs instructions (`src/Providers/PromptBuilder.php:45-56`, `83-89`).

### 3.2 Contrôle d’accès

- **OK** — Toutes les routes REST du jalon utilisent `Capabilities::rest_permission` (`src/Admin/RestController.php:45-103`, `src/Admin/TriggerController.php:34-62`).
- **OK** — L’action `admin-post` vérifie `omtr_manage` puis un nonce lié au post (`src/Admin/TranslationActions.php:71-84`).
- **OK** — Aucun endpoint d’écriture public ni hook `wp_ajax_nopriv_*`.
- **OK** — Les éditions manuelles passent par le même `Sanitizer` que l’IA.

### 3.3 Clés API

- **OK** — Stockage chiffré et accès déchiffré uniquement par `ApiKeyStore` (`src/Security/ApiKeyStore.php:33-56`).
- **OK** — Aucune nouvelle route, estimation ou sortie CLI ne renvoie une clé.
- **OK** — Les erreurs fournisseur restent génériques et n’incluent jamais le corps brut (`src/Providers/Claude/ClaudeProvider.php:207-246`).

### 3.4 Abus et coûts

- **OK** — Une URL doit appartenir exactement à `home_url()`, être liée à un contenu publié public et ne pas être sensible (`src/Queue/PublicContent.php:121-177`, `src/Queue/ActionSchedulerQueue.php:114-145`).
- **OK** — La query string est supprimée lors de la normalisation (`src/Queue/PublicContent.php:121-152`).
- **OK** — L’à-la-volée impose option active, visiteur anonyme, garde de requête, pause, quota horaire, plafond mensuel et anti-rafale (`src/Queue/OnTheFlyTrigger.php:64-104`).
- **OK** — Le plafond est réservé par lot et les traductions existantes sont retirées avant planification (`src/Queue/ScanJob.php:119-145`, `src/Queue/ActionSchedulerQueue.php:194-220`).
- **OK** — `RunLock` empêche deux traductions complètes concurrentes (`src/Queue/RunLock.php:31-76`).
- **OK** — Aucun appel IA pendant le rendu : le rendu ne conserve qu’une charge en mémoire et l’écriture est exécutée à `shutdown`.

### 3.5 Réseau et redirections

- **OK** — Le crawler n’accepte que l’hôte, le schéma et le port exacts du site puis utilise `wp_safe_remote_get()`, zéro redirection (`src/Queue/PublicContent.php:77-118`).
- **OK** — L’unique redirection ajoutée utilise `wp_safe_redirect()` (`src/Admin/TranslationActions.php:80-84`).
- **OK** — Les endpoints fournisseur restent codés en dur et HTTPS.

### 3.6 Fichiers et imports

- **OK** — Aucun import/export ajouté. Les protections CSV existantes restent couvertes par `GlossaryCsvSecurityTest`.
- **OK** — L’estimation et les déclencheurs n’exposent aucune configuration secrète.

### 3.7 Base et désinstallation

- **OK** — Les écritures de hash de page utilisent `PageRepository` et des requêtes préparées.
- **OK** — La nouvelle option `omtr_queue_paused` figure dans `Options::all()` ; le transient `omtr_otf_*` respecte le préfixe et la purge générique d’uninstall (`uninstall.php:40-57`).
- **OK** — La désinstallation vérifie `WP_UNINSTALL_PLUGIN`, désabonne Action Scheduler et supprime clé/options/tables si la purge est demandée (`uninstall.php:11-57`).
