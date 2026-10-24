# Recette Elementor — widget de langue — 2026-10-24

Verdict : ✅ VALIDÉ

## Environnement

- WordPress `wp-env`, PHP 8.1
- Elementor 4.3.5
- WooCommerce actif
- URL temporaire : `http://localhost:8892` (le port 8882 appartenait à un autre worktree et n'a pas été interrompu)

## Vérifications

| Contrôle | Résultat |
| --- | --- |
| Plugin Omnium Translate actif | ✅ 0.1.0 |
| Démarrage avec WooCommerce | ✅ après liaison de `TranslationQueueInterface` |
| Widget présent dans le registre Elementor | ✅ `omtr-switcher` / `ElementorSwitcherWidget` |
| Contrôles du widget | ✅ `variant` et `display` |
| Création par `elements_manager` | ✅ instance complète construite avec `(data, args)` natifs |
| Rendu `inline` + `code` | ✅ liens FR/EN, `hreflang`, langue active, URL `/en/` |
| Page publique | ✅ HTTP 200 |

## Correctifs issus de la recette

1. Ajout de la liaison manquante entre `TranslationQueueInterface` et `ActionSchedulerQueue`, qui bloquait l'initialisation WooCommerce.
2. Suppression du constructeur personnalisé du widget : Elementor doit pouvoir créer les instances de page avec sa signature native.

## Limite

Le glisser-déposer visuel dans l'éditeur n'a pas été automatisé. Le registre, les contrôles, la construction d'une instance complète et son rendu ont été vérifiés dans Elementor réel.
