# Audit confidentialité — v1.0.1 — 2026-10-26

Verdict : ✅ VALIDÉ

Périmètre : changements IA, crawler, couverture SEO, rendu front, variantes régionales, logs implicites de validation et recettes. Aucun fournisseur réel n’a été appelé : le harnais utilise un fournisseur local déterministe.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Anthropic `api.anthropic.com/v1/messages` | `src/Providers/Claude/ClaudeProvider.php:32,225` | Clé en en-tête, langues, segments publics, glossaire, ton/contexte filtré | OK |
| OpenAI `api.openai.com/v1/responses` | `src/Providers/OpenAI/OpenAIProvider.php:38,218` | Même périmètre; clé en en-tête Authorization | OK |
| Mistral UE `api.eu.mistral.ai/v1/chat/completions` | `src/Providers/Mistral/MistralProvider.php:50,243` | Même périmètre; traitement via endpoint régional UE | OK |
| Gemini `generativelanguage.googleapis.com/...:generateContent` | `src/Providers/Gemini/GeminiProvider.php:41,243` | Même périmètre; clé en en-tête | OK |
| `home_url()` — inventaire/estimation | `src/Queue/PublicContent.php:99` | GET anonyme d’un contenu public, sans cookie ni redirection | OK |
| `home_url()` — scan différé | `src/Queue/ScanJob.php:208` | GET anonyme d’un contenu public, sans cookie ni redirection | OK |
| GitHub Releases du dépôt public | `src/Core/Updater.php:19` | Vérification de version, aucune donnée de contenu | OK |
| REST du même site | `assets/src/admin/api.js`, `assets/src/visual-editor/index.js` | Administration protégée par nonce/capability | OK |

La recherche exhaustive des appels réseau et URL littérales ne trouve aucune destination Omnium, télémétrie, CDN, police ou drapeau distant ajouté par l’extension. Les URLs restantes sont des liens d’administration/documentation et des namespaces XML.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding Critique, Haute, Moyenne ou Basse ouvert dans ce périmètre. | — |

## Checklist

### Étape 2 — Données envoyées à l’IA — OK

- `PublicContent` et `ScanJob` forcent une requête anonyme, cookies vides, aucune redirection, et valident le même schéma/hôte/port que `home_url()`.
- Comptes, panier, commande, paiement, pages protégées, brouillons, contenus privés, preview, admin, REST/AJAX et rendu d’un utilisateur connecté sont exclus par `PublicContent` et `RequestGuard`.
- `PiiFilter` détecte email, téléphone FR/international, IBAN et carte Luhn; `HtmlSegmentExtractor` et `JsonLdProcessor` les excluent. Tests unitaires dédiés présents.
- Aucun `value` d’input ni contenu de textarea n’est extrait; seuls les textes publics et attributs éditoriaux autorisés le sont.
- Les emails WooCommerce sont traduits depuis leurs gabarits et variables, jamais depuis une commande client réelle.
- Le prompt contient uniquement langues, segments publics, glossaire et ton/contexte filtré. L’ajout de contexte voisin sert la cohérence mais ne change pas la nature des données transmises.

### Étape 3 — Stockage et journaux — OK

- Les logs ne stockent que niveau, message générique et identifiants techniques; aucun texte complet, clé, IP, user-agent visiteur ou identifiant utilisateur.
- Purge automatique après 30 jours dans `DbLogger`; erreurs fournisseur brutes non persistées.
- La nouvelle table `page_segments` ne contient que des identifiants techniques de page et segment, nécessaires au calcul SEO local; aucune donnée visiteur.
- Exports de configuration et glossaire sans clé API.

### Étape 4 — Visiteurs — OK

- Aucun cookie par défaut. Le cookie optionnel de langue contient seulement le code, `SameSite=Lax`, `Secure` sous HTTPS et durée de 365 jours.
- Aucun `localStorage`/`sessionStorage`, fingerprinting ou détection automatique de langue navigateur.
- Drapeaux et assets servis localement. Les captures réseau différentielles des trois recettes trouvent zéro origine ajoutée par l’extension; les Google Fonts d’Elementor sont déjà présentes extension désactivée.
- Le sélecteur minimaliste et les variantes régionales ne transmettent aucune donnée supplémentaire.

### Étape 5 — Transparence et suppression — OK

- Le texte WordPress de politique de confidentialité liste les fournisseurs et leur région, les catégories de données envoyées et la nécessité de mettre la politique à jour après changement.
- L’administration affiche l’avertissement relatif aux paliers gratuits et la localisation de chaque fournisseur.
- La purge de désinstallation supprime tables, options/transients, métas, tâches planifiées et clé chiffrée. Les trois recettes confirment tous les compteurs à zéro.
- Aucune télémétrie, activation ping ou statistique d’usage vers Omnium.

## Vérifications en environnement réel local

| Environnement | Résultat confidentialité |
| --- | --- |
| Gutenberg | 28/28; aucune clé dans DOM/réponse; aucune origine tierce ajoutée |
| Elementor + WooCommerce + Yoast | 34/34; JSON-LD prix/SKU intact; aucune origine ajoutée par l’extension |
| Oxygen + WooCommerce + Rank Math | 34/34; JSON-LD prix/SKU intact; aucune origine ajoutée par l’extension |

Limite assumée : les appels aux API réelles et leurs réponses 429/5xx ne sont pas exécutés sans clés client. Les payloads, destinations et traitements d’erreur sont couverts statiquement et par doubles locaux.
