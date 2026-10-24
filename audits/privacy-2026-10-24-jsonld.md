# Audit confidentialité — JSON-LD — 2026-10-24

**Verdict : ✅ VALIDÉ** pour les modifications JSON-LD de cette branche.

## Flux sortants

| Destination | Emplacement | Données | Conforme |
| --- | --- | --- | --- |
| API du fournisseur IA choisi | `src/Providers/{Claude,OpenAI,Mistral,Gemini}/*Provider.php` | Segments publics filtrés, langues et contexte autorisé | Oui, flux existant |
| `home_url()` | `src/Queue/PublicContent.php:99`, `src/Queue/ScanJob.php:194` | Requête anonyme sans cookie | Oui, flux existant |
| GitHub Releases | `src/Core/Updater.php` | Requête de version | Oui, flux existant |

La recherche de tous les appels `wp_remote_*`, `wp_safe_remote_*`, `curl_*` et `fsockopen` et des URL littérales dans `src/` et `assets/src/` n'a trouvé aucun nouveau flux JSON-LD. `schema.org` et les espaces de noms XML sont des identifiants, sans requête réseau.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Aucun | — | Aucun nouveau flux ni donnée personnelle exposée | — |

## Checklist

| Point | État | Preuve |
| --- | --- | --- |
| 2. Contenu envoyé à l'IA | OK | `JsonLdProcessor.php:80-84` applique `PiiFilter` aux seuls champs textuels autorisés ; `HtmlSegmentExtractor.php:229-287` respecte les exclusions `data-omtr-skip`. `ScanJob.php:86-125` n'envoie à la file que les segments du crawl public anonyme. Les prix, SKU, GTIN et URL ne sont pas extraits. |
| 3. Stockage et journaux | OK | Aucun log ou stockage nouveau ; `Translator.php:73-86` lit les traductions existantes dans le cache et le dépôt. Aucun contenu visiteur n'est envoyé depuis le rendu. |
| 4. Visiteurs | OK | Aucun cookie, stockage navigateur ou ressource distante ajouté. La traduction et la réécriture JSON-LD sont locales. |
| 5. Transparence | OK, inchangé | Aucune modification des fournisseurs, de la notice de confidentialité ou de la désinstallation. |

La recette navigateur sur les trois environnements WordPress et la désinstallation complète restent à exécuter au niveau de la recette v1 ; elles ne sont pas couvertes par ces tests unitaires.
