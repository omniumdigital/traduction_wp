# Audit confidentialité — 0.1.0 — 2026-10-23

**Verdict : ✅ VALIDÉ** pour les changements URL, sitemap, extraction JSON-LD et rendu du jalon 4.

## Flux sortants

| Destination | Emplacement | Données | Conforme |
| --- | --- | --- | --- |
| API du fournisseur IA choisi | `src/Providers/{Claude,OpenAI,Mistral,Gemini}/*Provider.php` | Segments publics filtrés, langues et contexte autorisé | Oui, flux existant |
| `home_url()` | `src/Queue/PublicContent.php:99`, `src/Queue/ScanJob.php:194` | Requête anonyme sans cookie | Oui, flux existant |
| GitHub Releases | `src/Core/Updater.php` | Requête de version | Oui, flux existant |

La recherche de `wp_remote_*`, `wp_safe_remote_*`, `curl_*`, `fsockopen` et des URL codées dans `src/` n'a montré aucun nouveau flux dans les fichiers modifiés. Les URL de schema.org et de namespaces XML sont des identifiants de données, sans requête réseau.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Aucun | — | Aucun finding sur les changements | — |

## Checklist

| Point | État | Preuve |
| --- | --- | --- |
| 2. Contenu envoyé à l'IA | OK | `PublicContent.php:36,99-106,146-163` limite le crawl aux contenus publiés et anonymes, sans cookies. `JsonLdProcessor.php:81` applique le même `PiiFilter` aux champs JSON-LD ; `HtmlSegmentExtractor.php` respecte `data-omtr-skip` et un test vérifie cette exclusion. Les identifiants, prix et URL ne deviennent pas des segments. |
| 3. Stockage et journaux | OK | Aucun stockage ou log ajouté. `Translator.php:81` lit les traductions existantes ; le rendu JSON-LD n'appelle aucun fournisseur. |
| 4. Visiteurs | OK | Aucun cookie, stockage navigateur ou ressource externe ajouté. Les URL internes sont réécrites localement par `UrlTranslator`. |
| 5. Transparence | OK, inchangé | Aucun changement de fournisseur, de politique ou de désinstallation. Aucune télémétrie ajoutée. |

La vérification manuelle dans un navigateur WordPress et la désinstallation sur un site de test restent des contrôles de recette de la version, non des conditions introduites par ce changement. Le fallback PHP 8.1 conserve les scripts JSON-LD octet pour octet au rendu, tout en les extrayant pour la file de traduction du contenu public.
