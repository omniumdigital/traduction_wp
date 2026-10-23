# Audit confidentialité — SEO jalon 4 — v0.1.0 — 2026-10-23

Verdict : ✅ VALIDÉ pour le périmètre SEO relu (pas une validation générale RGPD du plugin).

Périmètre : balises de tête, couverture des traductions, robots et sitemaps dans `src/Seo/`, déjà commités dans `a52b413`, `511a434` et `ea0d0b1`. Revue du code effectuée le 2026-10-23. Aucun nouveau flux sortant, cookie, stockage visiteur ou ressource externe front n’est ajouté.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| API Anthropic | `src/Providers/Claude/ClaudeProvider.php:224` | Segments publics filtrés, langues, glossaire, ton | ✅ HTTPS codé en dur; code fournisseur inchangé |
| API OpenAI | `src/Providers/OpenAI/OpenAIProvider.php:217` | Segments publics filtrés, langues, glossaire, ton | ✅ HTTPS codé en dur; code fournisseur inchangé |
| API Mistral UE | `src/Providers/Mistral/MistralProvider.php:242` | Segments publics filtrés, langues, glossaire, ton | ✅ HTTPS codé en dur; code fournisseur inchangé |
| API Gemini | `src/Providers/Gemini/GeminiProvider.php:242` | Segments publics filtrés, langues, glossaire, ton | ✅ HTTPS codé en dur; code fournisseur inchangé |
| `home_url()` (crawler interne) | `src/Queue/PublicContent.php:99-108`, `src/Queue/ScanJob.php:193-202` | GET anonyme, sans cookie de session | ✅ requête sûre vers le domaine local; code crawler inchangé |
| GitHub Releases | `src/Core/Updater.php:19` | Vérification de version sans donnée de site | ✅ code updater inchangé |

Les primitives réseau recensées dans `src/` sont les appels fournisseur, le crawler anonyme et l’updater. Les URL de namespaces XML (`www.w3.org`, `www.sitemaps.org`) ne déclenchent aucune requête réseau.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding dans les changements SEO relus | — |

## Checklist

### Données envoyées à l’IA

- ✅ Le changement SEO n’ajoute aucun envoi à l’IA.
- ✅ Le crawler reste anonyme : `cookies => array()`, en-tête `Cookie` vide, zéro redirection (`src/Queue/PublicContent.php:99-108`).
- ✅ Les contenus privés/protégés, compte, panier, commande, aperçus, admin, REST, AJAX et utilisateurs connectés restent exclus par les gardes existantes (`src/Router/RequestGuard.php:20-59`, `src/Queue/PublicContent.php:126-177`).
- ✅ Le filtre PII existant détecte les emails, téléphones, IBAN et cartes (Luhn) (`src/Security/PiiFilter.php:24-123`); les tests correspondants existent dans la suite.
- ✅ Le SEO ne touche pas à l’extraction des champs, aux prompts ou aux emails Woo.
- Non réaudité : le comportement complet de ces mécanismes inchangés n’a pas été rejoué sur un site WordPress de test pendant cette tâche.

### Stockage et journaux

- ✅ Le nouveau cache contient uniquement un pourcentage agrégé par langue, sans URL, segment, IP ou identité (`src/Seo/TranslationCoverage.php:54-84`).
- ✅ La requête lit des comptes de segments et traductions, pas leur contenu (`src/Seo/TranslationCoverage.php:61-77`).
- ✅ Aucun nouveau log n’est ajouté par le SEO; aucun export ni accès aux clés API n’est modifié.
- N/A pour ce diff : purge globale des logs et conformité globale des erreurs fournisseur ne changent pas.

### Côté visiteurs

- ✅ Aucun cookie, `localStorage`, `sessionStorage`, redirection selon le navigateur ou ressource externe n’est ajouté.
- ✅ Les sorties ajoutées sont des balises HTML/XML, des directives robots et des liens de sitemap; elles ne contiennent pas de données de visiteur.
- Non vérifié manuellement sur un site de test : absence de requêtes tierces dans l’onglet réseau d’une page réelle.

### Transparence pour le client

- ✅ Aucune télémétrie, ping d’activation ou statistique d’usage envoyée à Omnium n’est ajoutée par ce jalon.
- ✅ Les informations de localisation et de palier des fournisseurs ne sont pas modifiées.
- ✅ La désinstallation existante supprime les options/transients `omtr_*`, y compris le cache de couverture (`uninstall.php:44-52`).
- N/A pour ce diff : texte de politique de confidentialité, avertissements fournisseur et comportement de désinstallation ne sont pas modifiés; ils n’ont pas été validés de nouveau sur un site de test.
