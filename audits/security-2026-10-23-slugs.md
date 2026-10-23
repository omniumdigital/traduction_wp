# Audit sécurité — slugs traduits — 2026-10-23

Verdict : ✅ VALIDÉ pour les fichiers du jalon slugs (`c539b25..aa53882`). Aucun finding critique ou haut.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS | 88/88 fichiers conformes |
| PHPStan niveau 6 | 0 erreur |
| PHPUnit | 246 tests, 698 assertions, 1 test ignoré (PHP 8.5) ; PHP 8.1 : 246 tests, 702 assertions |
| PHPUnit `@group security` | 104 tests, 282 assertions ; droits REST et slugs malveillants couverts |
| Composer audit | 0 vulnérabilité |
| npm audit `--audit-level=high` | 0 vulnérabilité |
| Gitleaks | Archive Git des fichiers suivis : 0 fuite. Le scan du répertoire complet signale 5 faux positifs dans `vendor/phpstan/phpstan/phpstan.phar`, non suivi. |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Aucun | — | Aucun problème dans le périmètre modifié | — |

Un cas de redirection d'ancien slug vers un contenu devenu privé a été corrigé avant validation : `LanguageRouter` n'enregistre l'identifiant historique qu'après vérification du statut `publish`, avec un test de non-redirection.

## Checklist manuelle

- **3.1 Réponses IA : OK, inchangé.** Les slugs sont normalisés par `remove_accents` et `sanitize_title` (`src/Router/SlugGenerator.php:91`) ; le rendu des traductions utilise `Security/Sanitizer.php:41` et `Render/Translator.php:207`. Le prompt isole les segments (`Providers/PromptBuilder.php:85`).
- **3.2 Contrôle d'accès : OK.** REST `omtr/v1/slugs` passe par `Capabilities::rest_permission` (`src/Router/SlugRestController.php:32`) ; les tests `SlugRestControllerSecurityTest` refusent anonyme et abonné. Les slugs `../`, `<script>` et URL absolue sont refusés dans `SlugGeneratorTest`.
- **3.3 Clés API : OK, inchangé.** Aucune clé lue ou émise par les fichiers slugs ; seul `Security/ApiKeyStore` y accède.
- **3.4 Coûts : OK.** Le routeur et `UrlTranslator` n'appellent aucun fournisseur ; la génération est déclenchée par la file ou WP-CLI, pas par une requête visiteur.
- **3.5 Réseau et redirections : OK.** La cible provient du permalien WordPress, traduit par `UrlTranslator`, puis `wp_safe_redirect(..., 301)` (`src/Router/LanguageRouter.php:131`). Recette HTTP locale : ancien slug 301 vers le nouveau ; nouveau slug 200.
- **3.6 Fichiers et imports : OK, inchangé.** Aucun import, export ou fichier créé par le routage des slugs.
- **3.7 SQL et désinstallation : OK.** Requêtes de `SlugRepository` préparées, noms de tables issus de `Tables` ; migration `DB_VERSION=2` ; `Tables::all()` inclut `slug_history` pour la purge dans `uninstall.php`.

Recherche des motifs dangereux sur les fichiers modifiés : aucune fonction interdite, requête SQL brute, redirection ouverte ou sortie HTML non échappée. `REQUEST_URI` est conservée octet pour octet après retrait du préfixe (`LanguageRouter.php:53`) puis utilisée comme chemin de routage, jamais comme cible de redirection.
