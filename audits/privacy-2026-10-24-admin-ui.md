# Audit confidentialité — interface admin — 2026-10-24

Verdict : ✅ VALIDÉ

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| API REST WordPress du site | `assets/src/admin/api.js` | Paramètres admin, fichier CSV choisi par l’administrateur | ✅ |
| Aucune ressource tierce | `assets/src/admin/SwitcherPreview.jsx` | Drapeaux uniquement via `window.omtrAdmin.flagsUrl` | ✅ |

L’aperçu n’utilise pas de CDN : lorsque `flagsUrl` est absent, il affiche le nom sans
charger de drapeau distant. L’export CSV est téléchargé depuis la réponse REST locale.

## Checklist

- Étape 2 : OK, ce changement ne modifie ni crawler, ni prompt, ni données envoyées à l’IA.
- Étape 3 : OK, aucun stockage ou journal supplémentaire.
- Étape 4 : OK, les ressources d’aperçu sont locales et aucun cookie/storage n’est ajouté.
- Étape 5 : OK, l’interface conserve les avertissements fournisseur existants.
- Export/import : OK, l’import est limité à 1 Mo côté client et l’export appelle la route du contrat.
