# Audit sécurité — v1.0.1 — 2026-10-26

Verdict : ✅ VALIDÉ

Périmètre : durcissement de la qualité IA, couverture SEO par page, variantes régionales, assistant de configuration, files de traduction et harnais de recette. Revue des fichiers modifiés de `src/`, du bootstrap, de la désinstallation et des sources JavaScript.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php` | OK — 113 fichiers |
| `vendor/bin/phpstan analyse --level=6` | OK — 113 fichiers, 0 erreur |
| `vendor/bin/phpunit --group=security` | OK — 204 tests, 624 assertions |
| `vendor/bin/phpunit` | OK — 352 tests, 1 055 assertions, 2 ignorés |
| `composer audit` | OK — aucun avis de sécurité |
| `npm audit --audit-level=high` | OK — 0 vulnérabilité |
| `npm run lint` / `npm run build` | OK — 20 fichiers contrôlés, assets et traductions compilés |
| `gitleaks detect --no-banner` (image Docker officielle) | OK — 141 commits, aucune fuite |
| `git diff --check` | OK |

## Recherche de motifs dangereux

- Aucune fonction d’exécution dynamique ou système interdite dans `src/`, le bootstrap ou la désinstallation.
- `src/Glossary/GlossaryCsv.php:55,76,121` ne lit qu’un fichier local validé et des flux `php://memory`; aucune lecture d’URL.
- Les accès SQL modifiés utilisent des noms de tables fixes et des requêtes préparées. La nouvelle table de correspondance page/segment est créée par `src/Core/Migrations.php` et nommée dans `src/Core/Tables.php`.
- Tous les fichiers PHP de `src/` portent le garde `defined( 'ABSPATH' ) || exit;`.
- Les seuls appels HTTP sont les quatre endpoints HTTPS codés en dur des fournisseurs et les deux lectures sûres limitées à `home_url()`.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding Critique, Haute, Moyenne ou Basse ouvert dans ce périmètre. | — |

## Checklist manuelle

### 3.1 Réponses de l’IA — OK

- `src/Providers/TranslationQualityValidator.php` refuse les réponses vides, les caractères de contrôle, les marqueurs/URL modifiés, les balises ou attributs HTML altérés, les violations du glossaire, les ratios extrêmes et les longues sorties inchangées.
- `src/Providers/ResponseValidator.php` exige exactement tous les identifiants demandés; les quatre fournisseurs lui transmettent les sources et le glossaire et conservent un seul nouvel essai contrôlé.
- `src/Queue/BatchJob.php` refait la validation après sanitation avant toute persistance : un fournisseur tiers ne peut pas contourner la barrière.
- `src/Security/Sanitizer.php` conserve la whitelist stricte `strong`, `em`, `b`, `i`, `br`, `span`, `a[href]`; `src/Render/Translator.php` réinsère via le DOM et échappe les attributs.
- `src/Providers/PromptBuilder.php` délimite les segments comme données non fiables et interdit de suivre leurs instructions. Les tests `TranslationQualityValidatorTest`, `ResponseValidatorTest`, `BatchJobTest` et les tests fournisseurs couvrent les rejets et la seconde tentative.

### 3.2 Contrôle d’accès — OK

- Toutes les routes de `src/Admin/Administration.php`, `RestController.php`, `TriggerController.php` et `Router/SlugRestController.php` utilisent `Capabilities::rest_permission`, fondé sur `current_user_can( 'omtr_manage' )`.
- Les actions admin vérifient capability et nonce dans `src/Admin/TranslationActions.php`; aucune écriture publique ni `wp_ajax_nopriv_*`.
- Les traductions manuelles passent toujours par `Security\Sanitizer` dans `src/Admin/SegmentEditorRepository.php`.

### 3.3 Clés API — OK

- Chiffrement `sodium_crypto_secretbox` avec nonce aléatoire dans `src/Security/Crypto.php`; accès déchiffré exclusivement via `ApiKeyStore`.
- L’API ne renvoie que le masque. `assets/src/admin/Wizard.jsx` efface désormais la clé du state immédiatement après stockage; la recette vérifie `KEY_IN_DOM_AFTER_SAVE=false` et `KEY_IN_API_RESPONSES=0` en français et en anglais.
- Les erreurs fournisseur restent génériques; aucune réponse brute ni clé n’est loguée.

### 3.4 Abus et coûts — OK

- `OnTheFlyTrigger` et `PublicContent` limitent les URL aux contenus publics connus, sans query string; quota horaire, plafond mensuel et verrou global sont appliqués côté serveur.
- Aucun fournisseur n’est appelé durant la requête visiteur : la traduction passe par Action Scheduler.
- `src/Queue/Estimator.php` inclut désormais enveloppe du prompt/JSON, sortie attendue et limite de lot du fournisseur, ce qui réduit le risque de sous-estimation des coûts.

### 3.5 Réseau et redirections — OK

- Endpoints HTTPS codés en dur dans les quatre providers, `sslverify` actif.
- `PublicContent` et `ScanJob` bornent schéma, hôte et port à `home_url()` et utilisent `wp_safe_remote_get` sans redirection.
- Redirections via `wp_safe_redirect` ou URL construite depuis le site.

### 3.6 Fichiers et imports — OK

- CSV limité à 1 Mo, extension/MIME contrôlés, valeurs sanitizées, lecture mémoire seulement; cellules de formule neutralisées à l’export.
- Export JSON limité aux réglages publics et au glossaire, sans option de clé.

### 3.7 Base et désinstallation — OK

- La migration v3 crée `page_segments` avec noms fixes et invalide les anciens inventaires afin de reconstruire la couverture par page.
- Les colonnes de tri restent en whitelist. `uninstall.php` garde `WP_UNINSTALL_PLUGIN` et purge tables, options, transients, métas, tâches et clé selon le choix utilisateur.
- Recette réelle : zéro trace après purge sur Gutenberg, Elementor et Oxygen.

## Recette de sécurité et non-régression

- Gutenberg : **28/28** contrôles, clé absente du DOM/réseau, console sans erreur, sitemap natif et désinstallation validés.
- Elementor + WooCommerce + Yoast : **34/34** contrôles, JSON-LD produit intact, aucune origine tierce ajoutée, surcoût médian 27 ms.
- Oxygen + WooCommerce + Rank Math : **34/34** contrôles, JSON-LD produit intact, aucune origine tierce ajoutée, surcoût médian négligeable.

