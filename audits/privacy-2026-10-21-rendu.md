# Audit confidentialité — jalon 2 rendu + stockage — 2026-10-21

Verdict : ✅ VALIDÉ pour le périmètre rendu, routeur, stockage et filtre PII

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Aucun flux ajouté | — | — | ✅ |
| GitHub Releases | `src/Core/Updater.php:19` | Requête de version existante, aucune donnée du site | ✅ hors périmètre de la modification |

Les recherches `wp_remote_*`, `wp_safe_remote_*`, `curl_*`, `fsockopen`, `file_get_contents(https...)` et URLs littérales ne révèlent aucun autre flux dans `src/`. Le rendu visiteur ne déclenche ni appel fournisseur ni mise en file.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Information | `package-lock.json` | Avis npm hérités limités aux outils de développement, sans données visiteur et non livrés | Mise à niveau séparée de l'outillage |

Aucun finding Critique ou Haute dans le périmètre audité.

## Checklist

### Ce qui part vers l'IA

- N/A — aucun crawler ni appel IA dans ce lot.
- OK — admin, REST, AJAX, cron, panier, commande, compte et éditeurs Elementor/Oxygen sont exclus par `RequestGuard` (`src/Router/RequestGuard.php:20-42`).
- OK — aucune traduction à la volée n'est implémentée ou appelée par le rendu (`src/Render/Translator.php:45-67`).
- OK — emails, téléphones FR/internationaux, IBAN avec contrôle modulo 97 et cartes avec Luhn sont détectés (`src/Security/PiiFilter.php:24-123`) ; un test de sécurité couvre chaque motif (`tests/Unit/PiiFilterTest.php:25-46`).
- OK — `input[value]` n'est jamais extrait et tout contenu de `textarea` est exclu ; seuls les attributs explicitement autorisés, dont `placeholder`, sont lus (`src/Render/HtmlSegmentExtractor.php:19`, `src/Render/HtmlSegmentExtractor.php:71-109`).
- N/A — emails Woo et prompts appartiennent à d'autres lots.

### Stockage et journaux

- OK — le logger borne et assainit niveau, contexte et message ; son contrat interdit contenu, clé, IP et identifiant utilisateur (`src/Store/DbLogger.php:30-51`, `src/Core/LoggerInterface.php:13-27`).
- OK — purge quotidienne des lignes de plus de 30 jours par Action Scheduler, groupe `omtr` (`src/Store/DbLogger.php:54-76`).
- OK — aucune erreur fournisseur brute ni donnée visiteur ajoutée au stockage.
- N/A — aucun export ajouté.

### Côté visiteurs

- OK — aucun cookie, stockage navigateur, script, style, police ou ressource externe ajouté.
- OK — aucun examen de `Accept-Language` et aucune redirection navigateur ; seule la langue explicite de l'URL est mémorisée (`src/Router/LanguageRouter.php:37-61`).
- OK — les liens réécrits restent strictement limités à l'origine de `home_url()` (`src/Router/UrlTranslator.php:32-73`).

### Transparence et désinstallation

- N/A — politique de confidentialité, réglages fournisseur et désinstallation ne sont pas modifiés par ce lot et restent des jalons ultérieurs.
- OK — aucune télémétrie ni ping ajouté.
