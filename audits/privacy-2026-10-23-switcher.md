# Audit confidentialité — sélecteur de langue — 2026-10-23
Verdict : ✅ VALIDÉ

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Site courant (`home_url()`) | `src/Switcher/Switcher.php:165-168` | URL publique courante, utilisée uniquement pour construire les liens | ✅ |
| Ressources locales du plugin | `src/Switcher/SwitcherAssets.php:30-31`, `src/Switcher/Switcher.php:192-195` | CSS, JS et SVG statiques; aucune donnée transmise à un tiers | ✅ |
| Aucune nouvelle destination externe | `src/Switcher/*`, `assets/src/switcher/*` | Aucun appel HTTP, CDN, police, analytique ou télémétrie | ✅ |
| Aucune destination depuis l'éditeur | `assets/src/switcher-block/index.jsx:1-58` | Contrôles Gutenberg locaux; aucune requête applicative | ✅ |

Les flux existants vers les API Anthropic, OpenAI, Mistral et Gemini, GitHub Releases et le crawler limité à `home_url()` sont inchangés.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Aucun | — | Aucun finding sur le lot | — |

## Checklist

- Ce qui part vers l'IA : ✅ aucun segment, paramètre visiteur ou contenu n'est envoyé par le sélecteur; le crawler, le filtre PII et les exclusions existantes sont inchangés.
- Stockage et journaux : ✅ aucune donnée visiteur, langue choisie, IP, user-agent ou statistique n'est persistée ou journalisée. L'intégration menu ne stocke que le marqueur de configuration dans les tables natives de WordPress.
- Cookies et stockage navigateur : ✅ aucun cookie, `localStorage` ou `sessionStorage`; l'état du menu reste dans l'élément HTML `details` pendant la page courante.
- Ressources front : ✅ CSS (2 240 octets), JS compilé (372 octets) et sept SVG sont servis depuis le plugin; aucune ressource tierce (`src/Switcher/SwitcherAssets.php:30-31`, `src/Switcher/Switcher.php:192-195`).
- Éditeur Gutenberg : ✅ bundle de 2,6 Ko reposant uniquement sur les globals WordPress. L'URL `$schema` de `block.json` est une indication d'outillage statique et n'est jamais chargée au runtime (`assets/blocks/switcher/block.json:2`).
- Redirection navigateur : ✅ aucune détection de langue ni redirection automatique; seul un clic explicite sur un vrai lien change l'URL.
- Transparence : ✅ aucune destination, finalité ou donnée nouvelle à ajouter à la politique de confidentialité.

La recette Gutenberg `wp-env` a confirmé l'enregistrement du bloc, la résolution locale de son script et son rendu dynamique. La vérification réseau manuelle dans les environnements Elementor et Oxygen reste un critère de recette avant release.
