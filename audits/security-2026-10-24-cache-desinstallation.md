# Audit sécurité — purge du cache à la désinstallation — 2026-10-24
Verdict : ✅ VALIDÉ

## Périmètre
`uninstall.php` (ajout de `wp_cache_flush()`), `tests/Unit/UninstallTest.php`.

## Contexte
La recette v1 sur les sites Elementor et Gutenberg montrait, après désinstallation avec purge, qu'une option (`omtr_mock_calls`, propre au fournisseur simulé de test) restait lisible via `get_option()` bien que sa ligne en base ait été supprimée. Reproduit en isolation : une requête SQL brute (`$wpdb->query(DELETE ...)`) ne purge pas le cache mémoire non persistant de WordPress pour une option déjà chargée dans le même processus ; `get_option()` continue alors de servir la valeur mise en cache jusqu'à la fin du processus.

## Correctif
Ajout de `wp_cache_flush()` juste après les requêtes de purge brutes (tables, options, transients, postmeta, tâches Action Scheduler), avant la fin du script. Comportement standard WordPress documenté lors de toute manipulation directe de `wp_options`/`wp_postmeta` hors API.

## Contrôles automatiques
| Outil | Résultat |
| --- | --- |
| phpcs | ✅ 0 erreur, 0 avertissement |
| phpstan niveau 6 | ✅ Aucune erreur |
| phpunit | ✅ 337 tests, 1003 assertions |

## Checklist manuelle
- [x] `wp_cache_flush()` n'est appelé que dans la branche de purge complète (après la vérification `omtr_uninstall_purge`), jamais lors d'une simple désactivation.
- [x] Aucune donnée sensible exposée : l'appel ne fait que vider un cache local au processus, sans effet réseau ni stockage.
- [x] Reproduction et correctif vérifiés en isolation sur un site wp-env réel (`wp eval`), avant et après l'ajout de l'appel.

Règle de décision : aucun finding Critique/Haute → pas de blocage.
