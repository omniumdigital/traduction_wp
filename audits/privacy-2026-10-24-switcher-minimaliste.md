# Audit confidentialité — v1.0.1 — 2026-10-24
Verdict : ✅ VALIDÉ

Périmètre : nouveau rendu frontal du sélecteur, interface locale française et fournisseur simulé de l’environnement de recette. Le rapport complet reste `audits/privacy-2026-10-24.md`.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Anthropic | `src/Providers/Claude/ClaudeProvider.php:32,224-235` | Segments publics filtrés et clé en en-tête | ✅ inchangé |
| OpenAI | `src/Providers/OpenAI/OpenAIProvider.php:38,217-227` | Segments publics filtrés et clé en en-tête | ✅ inchangé |
| Mistral UE | `src/Providers/Mistral/MistralProvider.php:50,242-252` | Segments publics filtrés et clé en en-tête | ✅ inchangé |
| Gemini | `src/Providers/Gemini/GeminiProvider.php:41,242-252` | Segments publics filtrés et clé en en-tête | ✅ inchangé |
| `home_url()` | `src/Queue/PublicContent.php:99-108`, `src/Queue/ScanJob.php:194-203` | Requête anonyme sans cookie, sans redirection | ✅ inchangé |
| GitHub Releases | `src/Core/Updater.php:19-32` | Vérification de version, aucune donnée du site | ✅ inchangé |

Les recherches `wp_remote_*`, `wp_safe_remote_*`, `curl_*`, `fsockopen`, `file_get_contents(https...)` et `https?://` ne montrent aucun nouveau flux. Le CSS, le JavaScript et les drapeaux restent locaux.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding nouveau | — |

## Checklist

### Étape 2 — Ce qui part vers l’IA

- OK — Le sélecteur et tous ses descendants sont exclus par `data-omtr-skip`, `translate="no"` et `notranslate` (`src/Switcher/Switcher.php:144-165`). Ses libellés ne sont donc plus envoyés au fournisseur ni remplacés par une traduction.
- OK — Le changement ne touche ni le crawler anonyme, ni les exclusions WooCommerce et utilisateur connecté, ni le filtre PII, ni les formulaires, emails ou prompts. Les preuves complètes restent dans `privacy-2026-10-24.md`, étape 2.

### Étape 3 — Stockage et journaux

- OK — Aucun stockage visiteur, journal, option sensible ou export ajouté. Le nettoyage local des anciens préfixes `[EN]` et `[ES]` concerne uniquement la base de recette et ne fait pas partie du paquet distribué.

### Étape 4 — Côté visiteurs

- OK — Aucun cookie supplémentaire. Le cookie de préférence demeure optionnel et inchangé.
- OK — Aucun `localStorage`, `sessionStorage`, CDN, police, analytics ou ressource tierce ajouté.
- OK — Drapeaux sous `assets/flags/`, CSS sous `assets/css/` et JS sous `assets/build/`.
- OK — Contrôle HTTP sur les pages FR, EN et ES : 0 occurrence de `[EN]` ou `[ES]`; sélecteur rendu avec ses marqueurs d’exclusion et ses noms de langue natifs.

### Étape 5 — Transparence pour le client

- OK — Politique de confidentialité, régions fournisseur, avertissement des paliers gratuits et désinstallation inchangés.
- OK — Aucune télémétrie ni requête vers Omnium.

Aucun finding Critique ou Haute ouvert.
