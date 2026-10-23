# Audit sécurité — SEO jalon 4 — v0.1.0 — 2026-10-23

Verdict : ✅ VALIDÉ pour le périmètre SEO relu (pas une validation générale du plugin).

Périmètre : changements SEO déjà commités dans `a52b413`, `511a434` et `ea0d0b1`, notamment `src/Seo/` et leurs tests. Revue du code effectuée le 2026-10-23. Aucun finding Critique ou Haute identifié dans ce périmètre.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS (`src/`, bootstrap, désinstallation) | ✅ 90 fichiers conformes |
| PHPStan (configuration du dépôt, limite mémoire 1G) | ✅ aucune erreur |
| PHPUnit complet (PHP 8.5.11) | ✅ 245 tests, 708 assertions, 1 ignoré |
| PHPUnit complet (PHP 8.1.34) | ✅ 245 tests, 712 assertions |
| PHPUnit `--group=security` | ✅ 112 tests, 310 assertions |
| Composer audit | ✅ aucune vulnérabilité connue |
| npm audit `--audit-level=high` | ✅ 0 vulnérabilité |
| gitleaks sur les fichiers du dépôt | ✅ aucun secret après exclusion temporaire de `vendor/phpstan/phpstan/phpstan.phar` (5 alertes générées uniquement dans cette dépendance ignorée par Git) |
| gitleaks sur l’historique Git | ⚠️ non exécutable dans le conteneur : le gitdir du worktree pointe hors du workspace monté (`/Users/corentin/orca/traduction_wp/.git/worktrees/feat-seo`) |
| Recherche de motifs dangereux dans `src/Seo/` | ✅ aucune fonction interdite, entrée superglobale ou primitive réseau |
| Garde `ABSPATH` | ✅ présente dans les cinq fichiers PHP SEO |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding dans les changements SEO relus | — |

## Checklist manuelle

### 3.1 Réponses de l’IA

- ✅ Le module SEO ne manipule aucune réponse IA. Les codes de langue sont échappés avec `esc_attr`, les URLs avec `esc_url` (`src/Seo/HeadTags.php:47-59`).
- ✅ Le XML des sitemaps échappe les URLs avec `esc_url` puis `esc_xml`, et les valeurs scalaires avec `esc_xml` (`src/Seo/SitemapAlternates.php:93-126`, `src/Seo/NativeSitemapRenderer.php:51-64`).
- ✅ Les tests SEO vérifient les valeurs et l’échappement des `href`, ainsi que l’usage de `home_url()` contre le Host spoofing (`tests/Unit/HeadTagsTest.php:73-112`).
- N/A pour ce diff : validation JSON, délimitation du prompt et test d’injection prompt portent sur le fournisseur, inchangé par ce jalon.

### 3.2 Contrôle d’accès

- ✅ Aucun endpoint REST, action AJAX/admin ou écriture publique ajouté par le module SEO.
- ✅ Les balises de tête sont limitées aux requêtes publiques appropriées (`src/Seo/HeadTags.php:91-99`); couverture robots limitée aux pages publiques (`src/Seo/Robots.php:70-81`).
- N/A pour ce diff : contrôles de nonce/capability et sanitization d’édition manuelle ne sont pas modifiés.

### 3.3 Clés API

- ✅ Aucun accès aux clés API, au stockage des clés ou au code fournisseur n’est ajouté ou modifié par le SEO.
- N/A pour ce diff : les propriétés globales de chiffrement et de traitement d’erreur ne sont pas réauditées ici.

### 3.4 Abus et coûts

- ✅ Aucun appel IA ni mise en file n’est déclenché par le module SEO.
- ✅ L’agrégation de couverture est préparée et ne compte que les statuts acceptés (`src/Seo/TranslationCoverage.php:61-77`).
- N/A pour ce diff : quotas, verrou et traduction à la volée ne sont pas modifiés.

### 3.5 Réseau et redirections

- ✅ Aucun appel réseau ni redirection ajouté dans `src/Seo/`.
- ✅ Les canonical et hreflang sont dérivés de `home_url()` et des fonctions de traduction d’URL (`src/Seo/HeadTags.php:85-108`).
- ✅ Les tests vérifient l’échappement des liens et le rejet d’un Host de requête non fiable (`tests/Unit/HeadTagsTest.php:91-112`).

### 3.6 Fichiers et imports

- ✅ Aucun import, export ou accès fichier n’est ajouté par le module SEO.
- N/A pour ce diff : les contrôles CSV/JSON ne sont pas modifiés.

### 3.7 Base de données et désinstallation

- ✅ La seule requête de lecture ajoutée utilise `wpdb::prepare()`; les identifiants de tables utilisent les placeholders `%i` (`src/Seo/TranslationCoverage.php:61-77`).
- ✅ La clé de cache commence par `omtr_` et la désinstallation existante supprime les transients correspondants (`src/Seo/TranslationCoverage.php:54`, `uninstall.php:44-52`).
- ✅ Aucun changement de schéma ni de `DB_VERSION`.
