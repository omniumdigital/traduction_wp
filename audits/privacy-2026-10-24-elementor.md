# Audit confidentialité — widget Elementor — 2026-10-24
Verdict : ✅ VALIDÉ

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Aucune nouvelle destination | `src/Switcher/ElementorIntegration.php`, `ElementorSwitcherWidget.php` | Enregistrement local et deux réglages de présentation | ✅ |
| Site courant | `Switcher.php` via le renderer partagé | URL publique utilisée pour construire les liens de langues | ✅ |

Les flux existants vers les fournisseurs IA, GitHub Releases et le crawler `home_url()` sont inchangés.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Aucun | — | Aucun finding de confidentialité | — |

## Checklist

- Ce qui part vers l'IA : ✅ aucun contenu Elementor, réglage du widget ou donnée visiteur n'est envoyé par cette intégration.
- Stockage et journaux : ✅ Elementor ne sauvegarde que `variant` et `display`; aucune langue visiteur, IP, identifiant, contenu ou statistique n'est ajoutée aux logs.
- Cookies et stockage navigateur : ✅ aucun cookie, `localStorage` ou `sessionStorage` ajouté.
- Ressources front : ✅ le widget réutilise exclusivement le CSS, le JS et les SVG locaux du sélecteur.
- Redirection navigateur : ✅ aucune détection ni redirection automatique; changement uniquement par clic sur un lien.
- Transparence : ✅ aucune nouvelle finalité, donnée collectée ou destination externe.

Recette effectuée sous Elementor 4.3.5 et PHP 8.1, avec page publique HTTP 200 et rendu serveur FR/EN.
