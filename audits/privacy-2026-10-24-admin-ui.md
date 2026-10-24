# Audit confidentialité — interface admin — 2026-10-24

Verdict : ✅ VALIDÉ (périmètre `assets/src/admin`)

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| API REST WordPress du site | `assets/src/admin/index.jsx` | Réglages, segments, glossaire et actions d’administration | ✅ |
| URL de création de clé du fournisseur | `assets/src/admin/index.jsx` (lien reçu par `/providers`) | Navigation explicite de l’administrateur uniquement | ✅ |

Aucun appel vers Omnium, aucune ressource tierce chargée par le front public et aucune télémétrie dans l’interface.

## Checklist

- La clé n’est jamais incluse dans l’export/import de configuration; le champ est de type `password` et est vidé après enregistrement.
- Les données affichées dans le journal sont celles filtrées par l’API; aucune collecte de visiteur, cookie ou stockage local n’est ajouté.
- L’avertissement de palier gratuit et la région UE/hors UE sont visibles dans le choix du fournisseur.
- La traduction complète demande une estimation puis une confirmation explicite.
- Le sélecteur utilise le markup de prévisualisation et les variables `--omtr-*`; aucun CDN, drapeau distant ou police externe.
- La commande `wp i18n make-json` est documentée dans `docs/admin-ui-i18n.md`; `wp-env` n’était pas initialisé pendant l’audit.
