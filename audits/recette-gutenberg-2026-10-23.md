# Recette Gutenberg — sélecteur de langue — 2026-10-23

Verdict : ✅ VALIDÉ

## Environnement

- WordPress : environnement `wp-env` Gutenberg seul
- PHP : 8.1
- URL temporaire : `http://localhost:8891` (le port 8881 était occupé par un autre worktree et n'a pas été interrompu)
- Plugin : Omnium Translate 0.1.0 actif

## Vérifications

| Contrôle | Résultat |
| --- | --- |
| Bloc présent dans `WP_Block_Type_Registry` | ✅ `omnium-translate/switcher` |
| Script éditeur enregistré | ✅ `omnium-translate-switcher-editor-script` |
| Script servi localement | ✅ `/wp-content/plugins/omnium-translate/assets/build/switcher-block.js` |
| Dépendances du bundle | ✅ globals WordPress uniquement |
| Rendu dynamique `inline` + `code` | ✅ liens FR et EN, `hreflang`, langue active et URL `/en/` corrects |
| HTML sauvegardé par le bloc | ✅ aucun (`save: () => null`) |
| Attributs invalides | ✅ repli PHP vers `dropdown` + `flag-name`, test d'injection présent |

## Limite

L'interaction visuelle dans l'éditeur (insertion à la souris et changement des deux listes) n'a pas été automatisée. L'enregistrement serveur, le chargement du bundle et le rendu public ont été vérifiés dans WordPress réel.
