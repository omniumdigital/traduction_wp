# Audit confidentialité — v0.1.0 — 2026-10-21

Verdict : ✅ VALIDÉ

Périmètre : flux sortants, prompts, crawler et journaux du moteur IA/file de la branche `omniumdigital/feat-moteur-ia`. Aucun finding Critique ou Haute ne reste ouvert.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| `https://api.anthropic.com/v1/messages` | `src/Providers/Claude/ClaudeProvider.php:197` | Langues, segments publics filtrés, glossaire, ton/contexte | ✅ endpoint codé en dur, clé client, TLS vérifié |
| `home_url()` | `src/Queue/ScanJob.php:152` | Requête GET anonyme de la page publique | ✅ origine validée, `wp_safe_remote_get`, cookies vides |
| GitHub Releases du dépôt public | `src/Core/Updater.php:19` | Requête de version uniquement (flux préexistant) | ✅ destination autorisée |

Aucun autre appel réseau ni aucune ressource front tierce n'a été trouvé.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Critique — corrigé | `src/Security/PiiFilter.php:25` | Le filtre PII du socle était un stub et laissait passer les données personnelles. | Détection email, téléphone FR/international, IBAN valide (modulo 97) et carte valide (Luhn), avec tests dédiés. |
| Haute — corrigé | `src/Queue/ScanJob.php:152` | L'absence de cookies était implicite. | `cookies => array()` explicite ; aucun en-tête de session transmis. |
| Haute — corrigé | `src/Queue/ActionSchedulerQueue.php:102` | Les statuts privés/protégés et pages WooCommerce personnalisées n'étaient pas tous exclus côté serveur. | Résolution du post, contrôle `publish`/mot de passe et exclusion des ids panier, commande et compte. |
| Haute — corrigé | `src/Queue/ActionSchedulerQueue.php:126` | L'URL publique complète était écrite dans le log. | Les logs ne conservent plus que `post_id`, compteurs, langue et codes HTTP techniques. |
| Haute — corrigé | `src/Queue/BatchJob.php:139` | Une exception fournisseur pouvait théoriquement porter un corps brut jusque dans le job. | Le job n'enregistre jamais `getMessage()` ; tests avec un message secret simulé. Le fournisseur construit uniquement des messages génériques. |

## Checklist — données envoyées à l'IA

- ✅ Crawl sans cookie d'authentification/WooCommerce (`src/Queue/ScanJob.php:152`).
- ✅ Seules les réponses HTTP 200 anonymes sont extraites (`src/Queue/ScanJob.php:163`).
- ✅ Brouillons, contenus privés/protégés, admin, REST, panier, commande, compte et `order-received` sont refusés ; les ids WooCommerce couvrent les slugs personnalisés (`src/Queue/ActionSchedulerQueue.php:43`, `src/Queue/ActionSchedulerQueue.php:102`, `src/Queue/ActionSchedulerQueue.php:388`).
- ✅ Aucun appel IA pendant une requête visiteur : `enqueue_url()` et `enqueue_segments()` planifient uniquement des actions ; `BatchJob` seul appelle le fournisseur.
- ✅ Filtre PII complet : emails, téléphones, IBAN et cartes Luhn (`src/Security/PiiFilter.php:25`), tests dans `tests/Unit/PiiFilterTest.php:27`.
- ✅ L'extracteur est injecté via `SegmentExtractorInterface`, dont le contrat exclut valeurs de formulaire et PII ; son implémentation appartient à la branche Render.
- ✅ Le prompt ne contient que langues, glossaire, ton/contexte et segments ; aucune URL admin, identité utilisateur ou donnée de commande (`src/Providers/PromptBuilder.php:44`).

## Checklist — stockage et journaux

- ✅ Aucun texte de segment, clé, IP, identifiant utilisateur, URL complète ou réponse brute n'est journalisé (`src/Queue/BatchJob.php:119`, `src/Queue/ScanJob.php:59`).
- ✅ Les erreurs fournisseur sont génériques et seuls les codes HTTP alimentent les logs (`src/Providers/Claude/ClaudeProvider.php:211`, `src/Queue/BatchJob.php:139`).
- ✅ Aucune donnée visiteur n'est stockée ; les compteurs ne contiennent que période et volume (`src/Queue/HourlyQuota.php:93`, `src/Queue/WordCap.php:116`).
- N/A : aucun export de configuration ou glossaire dans le périmètre.
- ℹ️ La purge automatique des logs est une responsabilité de l'implémentation `LoggerInterface`, hors de cette branche.

## Checklist — côté visiteurs

- ✅ Aucun cookie, `localStorage`, `sessionStorage`, script, style ou ressource front ajouté.
- ✅ Aucune redirection automatique ajoutée.
- N/A : aucun sélecteur/drapeau/front dans cette branche ; la vérification navigateur sera faite avec la branche front intégrée.

## Checklist — transparence client

- ✅ Aucune télémétrie, ping d'activation ou statistique d'usage vers Omnium.
- ✅ Les seuls flux sont le fournisseur choisi, GitHub Releases et le crawler interne.
- ✅ Les nouvelles options de compteurs/verrous sont préfixées `omtr_` et listées pour la désinstallation (`src/Core/Options.php:17`).
- ℹ️ Politique de confidentialité, avertissement des paliers gratuits et localisation fournisseur relèvent de l'interface d'administration d'un jalon ultérieur, non modifiée ici.

