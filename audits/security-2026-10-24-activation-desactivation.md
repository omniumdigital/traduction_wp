# Audit sécurité — Activator/Deactivator menu switcher — 2026-10-24
Verdict : ✅ VALIDÉ

## Périmètre
`src/Core/Activator.php`, `src/Core/Deactivator.php`, `tests/Unit/ActivatorTest.php`, `tests/Unit/DeactivatorTest.php`, `tests/Unit/UninstallTest.php` (correction de fuite d'état entre tests), `src/Admin/Administration.php` (alignement PHPCS uniquement), `uninstall.php` (justification PHPCS).

## Contrôles automatiques
| Outil | Résultat |
| --- | --- |
| phpcs (WordPress.*, via Docker composer:2) | ✅ 0 erreur, 0 avertissement |
| phpstan niveau 6 | ✅ Aucune erreur |
| phpunit (dont `@group security`) | ✅ 332 tests, 992 assertions, 1 skip (préexistant) |

## Findings
Aucun.

## Checklist manuelle
- [x] Aucune entrée utilisateur impliquée : `Activator::activate()` et `Deactivator::deactivate()` sont déclenchés par WordPress (`register_activation_hook` / `register_deactivation_hook`), sans paramètre externe.
- [x] Requête `get_posts()` avec `meta_key`/`meta_value` : valeur de filtre = constante `MenuIntegration::ITEM_URL` codée en dur, aucune donnée externe. `wp_update_post()` reçoit uniquement un ID entier casté et un statut littéral (`draft`/`publish`).
- [x] Pas de suppression de données : le correctif remplace la suppression de l'élément de menu par un passage en brouillon/publication, conforme à la charte « désactiver laisse le site intact ».
- [x] Pas de nouvel endpoint, pas de nouvelle capability, pas de nouvelle route REST.
- [x] `uninstall.php` : la justification `phpcs:disable/enable` documente que la requête `meta_key`/`meta_value` est un nettoyage ponctuel (uninstall), donc le sniff de requête lente ne s'applique pas ; même raisonnement appliqué à l'identique dans Activator/Deactivator.

Règle de décision : aucun finding Critique/Haute → pas de blocage.
