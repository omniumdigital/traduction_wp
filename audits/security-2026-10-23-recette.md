# Audit sécurité — recette jalon 3 — 2026-10-23

Verdict : ✅ VALIDÉ

Périmètre modifié : `src/Queue/Crawler.php` et son câblage dans `src/Queue/QueueModule.php`.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS | OK, 84/84 fichiers |
| PHPStan niveau 6 | OK, 0 erreur |
| PHPUnit complet | OK, 230 tests, 662 assertions, 1 sauté sous l'image Composer |
| PHPUnit PHP 8.1 | OK, 230 tests, 666 assertions |
| PHPUnit `--group=security` | OK, 97 tests, 264 assertions |
| Composer audit | OK, aucun avis de sécurité |
| npm audit `--audit-level=high` | OK, 0 vulnérabilité |
| gitleaks | OK, aucun secret dans l'archive des fichiers suivis (`zricethezav/gitleaks`, mode `--no-git`) |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding | — |

## Checklist manuelle

- **3.1 Réponses IA : OK.** Sanitization commune dans `src/Security/Sanitizer.php:40`, tests XSS/prompt dans `tests/Unit/TranslatorSecurityTest.php:49`. Le changement ne touche ni l'insertion ni les réponses fournisseur.
- **3.2 Contrôle d'accès : OK.** Les routes conservent leurs `permission_callback` (`src/Admin/RestController.php:54-191`) ; la recette refuse anonyme, abonné et éditeur.
- **3.3 Clés API : OK.** Aucun accès aux clés ajouté ; chiffrement secretbox inchangé (`src/Security/Crypto.php:32`).
- **3.4 Abus et coûts : OK.** Le nouveau hash porte uniquement sur les segments normalisés triés (`src/Queue/Crawler.php:55-64`) et empêche la remise en file causée par des attributs volatils. Deux crawls consécutifs donnent zéro changement sur les trois sites.
- **3.5 Réseau et redirections : OK.** Le crawler continue de déléguer exclusivement à `PublicContent`, qui impose le même site, `wp_safe_remote_get`, zéro redirection et zéro cookie (`src/Queue/PublicContent.php:73-108`).
- **3.6 Fichiers/imports : OK.** Aucun changement ; taille CSV limitée à 1 Mio et formules neutralisées (`src/Glossary/GlossaryCsv.php:16-71,169`).
- **3.7 Base/désinstallation : OK.** Aucun schéma ni requête dynamique ajouté. Le hash SHA-256 est stocké via le repository existant (`src/Queue/Crawler.php:57-67`).

Recherche manuelle des motifs interdits sur les deux fichiers modifiés : aucune occurrence. Le seul appel `extract()` est l'interface d'extraction de segments, pas la fonction PHP interdite.

