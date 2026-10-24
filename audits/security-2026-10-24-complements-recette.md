# Audit sécurité — compléments de recette v1 — 2026-10-24
Verdict : ✅ VALIDÉ (périmètre du changement)

## Périmètre
`env/oxygen/.wp-env.json`, `env/recette/admin-playwright.cjs`, `env/recette/front-resources.cjs`, `env/recette/front-resources-diff.py`, `env/recette/jsonld-product.py`, `env/recette/v1.sh`. Aucun fichier `src/`, endpoint REST, contrat ou clé de production modifié. Les deux skills installés sont identiques aux annexes de `CLAUDE.md`.

## Contrôles automatiques
| Outil | Résultat |
| --- | --- |
| Docker Composer : `vendor/bin/phpcs` | ✅ 112/112 fichiers, 0 erreur |
| Docker Composer : `vendor/bin/phpstan analyse --memory-limit=1G` | ✅ niveau 6, 0 erreur |
| Docker Composer : `vendor/bin/phpunit` | ✅ 338 tests, 1004 assertions, 2 skips préexistants |
| Docker Composer : `vendor/bin/phpunit --group=security` | ✅ 191 tests, 578 assertions |
| Docker Composer : `composer audit --no-interaction` | ✅ aucun avis de vulnérabilité |
| `npm audit --audit-level=high` | ✅ 0 vulnérabilité |
| Gitleaks Docker, historique Git (133 commits, dépôt worktree monté) | ✅ aucune fuite ; le scan naïf du dossier entier ramasse des dépendances temporaires et n'est pas utilisé comme verdict |
| `git diff --check`, `bash -n env/recette/v1.sh` | ✅ |

## Recherche de motifs dangereux
Lecture ciblée des fichiers modifiés : pas de fonction PHP interdite, de route publique, de requête SQL, de redirection, d'import ou de sortie de données client. Le harnais utilise une clé factice `local-mock-…`, absente des réponses réseau après les parcours FR/EN Elementor et Oxygen (`KEY_IN_API_RESPONSES=0`). `v1.sh` conserve l'interdiction de `wp plugin uninstall` et `wp plugin delete` ; ses seules transitions sont activate/deactivate. Les URL du site de test restent locales et le module Playwright est installé dans `env/recette/tmp/` ignoré par Git.

## Checklist manuelle (sections 3.1 à 3.7 du skill)
| Section | Résultat et preuve |
| --- | --- |
| 3.1 Réponses IA non fiables | OK : aucun changement de `Sanitizer`/`JsonLdProcessor` ; le contrôle structuré lit le JSON-LD sans l'injecter. `tests/Unit/JsonLdProcessorTest.php` reste vert dans le groupe sécurité. |
| 3.2 Accès REST/admin | OK : aucune route ni permission modifiée ; le parcours admin utilise uniquement `admin/password` du conteneur wp-env local. |
| 3.3 Clés API | OK : clé factice dans le navigateur de test ; 0 occurrence dans les réponses réseau, 0 dans le DOM après l'assistant sur les deux builders et les deux langues. |
| 3.4 Coûts et file | OK : le fournisseur reste le mu-plugin simulé ; une relance pendant un verrou actif renvoie 409, comportement attendu, sans second run concurrent. |
| 3.5 Réseau et redirections | OK : aucune destination de production ajoutée ; `front-resources.cjs` observe les requêtes sans en créer dans le plugin. |
| 3.6 Fichiers et imports | OK : pas de changement d'import/export ; les JSON de mesure sont écrits seulement dans `env/recette/tmp/` ignoré. |
| 3.7 Base et désinstallation | OK : pas de migration ; aucun appel à `wp plugin uninstall/delete`, et la purge déjà testée n'est pas modifiée. |

## Findings
Aucun finding Critique ou Haute. Les avertissements Yoast sur le tableau de bord WordPress sont documentés dans la recette et ne proviennent pas d'Omnium.
