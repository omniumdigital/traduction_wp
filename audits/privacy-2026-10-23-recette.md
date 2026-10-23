# Audit confidentialité — recette jalon 3 — 2026-10-23

Verdict : ✅ VALIDÉ

Périmètre : changement du calcul d'empreinte du crawler dans `src/Queue/Crawler.php` et câblage de l'extracteur dans `src/Queue/QueueModule.php`.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| `home_url()` | `src/Queue/PublicContent.php:99` | GET anonyme, sans cookie, sans redirection | Oui |
| API Anthropic | `src/Providers/Claude/ClaudeProvider.php:224` | Segments publics filtrés, langues, glossaire, ton | Oui, inchangé |
| API OpenAI | `src/Providers/OpenAI/OpenAIProvider.php:217` | Idem | Oui, inchangé |
| API Mistral UE | `src/Providers/Mistral/MistralProvider.php:242` | Idem | Oui, inchangé |
| API Gemini | `src/Providers/Gemini/GeminiProvider.php:242` | Idem | Oui, inchangé |
| GitHub Releases | `src/Core/Updater.php:19` | Requête de version, aucune donnée du site | Oui, inchangé |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding | — |

## Checklist

- **Contenu envoyé : OK.** Le crawler récupère toujours le rendu public anonyme via `PublicContent`; le nouveau calcul réutilise exactement `SegmentExtractorInterface` et la langue source configurée (`src/Queue/Crawler.php:48-57`). Les emails des fixtures sont absents de la table des segments sur les trois sites.
- **Minimisation : OK.** L'empreinte persistée est un SHA-256 de la carte triée des segments ; ni HTML, ni segment, ni identifiant visiteur n'est ajouté à la table `pages` (`src/Queue/Crawler.php:55-67`).
- **Stabilité/confidentialité : OK.** Les identifiants techniques WooCommerce aléatoires ne déclenchent plus de nouveau traitement. Un changement de texte traduisible modifie toujours la carte et donc l'empreinte.
- **PII : OK.** L'extracteur injecté conserve le filtre PII existant ; les tests couvrent email, téléphone, IBAN et carte avec Luhn (`tests/Unit/PiiFilterTest.php:25-38`).
- **Stockage et journaux : OK.** Les logs restent techniques et purgés après 30 jours (`src/Store/DbLogger.php:16,55-74`). La recette confirme l'absence d'email et de texte de segment dans les logs.
- **Visiteurs : OK.** La recette utilise une vraie session WordPress et confirme qu'un visiteur connecté ne met rien en file ; `/cart/` est également exclu. Aucun cookie, stockage navigateur ou chargement front n'est ajouté par le changement.
- **Exports/désinstallation/transparence : inchangés.** Aucun nouveau champ, flux, cookie ou donnée exportée.

La cartographie par recherche de `wp_remote_*`, `wp_safe_remote_*` et des URL codées en dur ne montre aucune nouvelle destination.

