# Audit confidentialité — jalon 2 rendu + stockage — 2026-10-21

Verdict : ✅ VALIDÉ pour le périmètre rendu, routeur, stockage et filtre PII

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Aucun flux ajouté | — | — | ✅ |
| GitHub Releases | `src/Core/Updater.php:19` | Requête de version existante, aucune donnée du site | ✅ hors périmètre de la modification |

Les recherches prescrites sur `wp_remote_*`, `wp_safe_remote_*`, `curl_*`, `fsockopen`, `file_get_contents(https...)` et les URL littérales ne révèlent aucun autre flux dans `src/`. Le rendu visiteur ne déclenche ni appel fournisseur ni mise en file.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Information | `src/Render/HtmlSegmentExtractor.php:65-81` | Le fallback PHP 8.1–8.3 repose sur le parseur HTML4 `DOMDocument` et peut normaliser du balisage HTML5 pendant un rendu traduit | PHP 8.4+ utilise `Dom\HTMLDocument`; conserver les tests HTML5 et élargir les fixtures lors de l'ajout de composants web |

Aucun finding Critique, Haute, Moyenne ou Basse dans le périmètre audité.

## Checklist

### Ce qui part vers l'IA

- N/A — aucun crawler ni appel IA dans ce lot.
- OK — `is_public_front_request()` autorise l'affichage de traductions déjà stockées sur panier/commande/compte, tout en excluant admin, cron, REST précoce, éditeurs et AJAX hors fragments Woo (`src/Router/RequestGuard.php:20-38`, `src/Router/RequestGuard.php:64-82`).
- OK — la méthode distincte `is_crawlable_or_ai_eligible()` exclut panier, commande, compte, `order-received`, utilisateurs connectés, aperçus et pages protégées (`src/Router/RequestGuard.php:47-58`). Elle est destinée au futur crawler/file à la volée ; aucun envoi IA n'existe encore dans ce lot.
- OK — emails, téléphones FR/internationaux, IBAN modulo 97 et cartes Luhn sont filtrés par `PiiFilter` et couverts par tests de sécurité.
- OK — `input[value]` n'est jamais extrait et `textarea` est exclu ; seuls `placeholder`, `alt`, `title` et `aria-label` sont lus (`src/Render/HtmlSegmentExtractor.php:104-142`).
- N/A — emails Woo et prompts appartiennent à d'autres lots.

### Stockage et journaux

- OK — le logger borne et assainit niveau, contexte et message ; son contrat interdit contenu, clé, IP et identifiant utilisateur.
- OK — purge quotidienne des logs de plus de 30 jours par Action Scheduler, groupe `omtr`.
- OK — aucune erreur fournisseur brute ni donnée visiteur ajoutée au stockage.
- OK — le cache de rendu est un cache objet stable dérivé de la langue, de la génération et des hashes de segments triés ; les variations de nonce/HTML dynamique ne créent pas d'option (`src/Render/Translator.php:59-68`, `tests/Unit/Html5RenderingTest.php:85-107`).
- N/A — aucun export ajouté.

### Côté visiteurs

- OK — aucun cookie, stockage navigateur, script, style, police ou ressource externe ajouté.
- OK — aucune lecture d'`Accept-Language` et aucune redirection navigateur.
- OK — une URI source encodée/unicode reste strictement inchangée ; le routeur n'écrit `REQUEST_URI` que lorsqu'un préfixe cible est reconnu (`src/Router/LanguageRouter.php:37-58`).
- OK — le rendu en langue source ne démarre pas le buffer et retourne le document HTML5 octet pour octet (`tests/Unit/Html5RenderingTest.php:51-62`).
- OK — les liens réécrits restent limités à l'origine de `home_url()`.

### Limites HTML restantes

- Sous PHP 8.4+, le parseur HTML5 natif conserve sémantiquement les SVG, templates, noscript, attributs JSON et scripts testés. La sérialisation d'une page traduite peut néanmoins normaliser la casse du doctype, les espaces et les guillemets d'attributs ; seule la langue source est garantie octet pour octet.
- Sous PHP 8.1–8.3, `DOMDocument::loadHTML()` reste un parseur HTML4 : du HTML5 très mal formé, les namespaces SVG/MathML ou des composants web complexes peuvent être normalisés. Les scripts, styles, templates et valeurs de formulaires restent exclus de l'extraction, mais une recette navigateur sur les trois sites de test demeure nécessaire avant livraison.

### Transparence et désinstallation

- N/A — politique de confidentialité, réglages fournisseur et désinstallation ne sont pas modifiés par ce lot.
- OK — aucune télémétrie ni aucun ping ajouté.
