# Audit sécurité — jalon 3 fournisseurs (non versionné) — 2026-10-23
Verdict : ✅ VALIDÉ

Périmètre : `git diff --name-only main...HEAD -- src` sur la branche des fournisseurs : `Providers/Claude/ClaudeProvider.php`, `Providers/OpenAI/OpenAIProvider.php`, `Providers/Mistral/MistralProvider.php`, `Providers/Gemini/GeminiProvider.php`, `Providers/Pricing.php`, `Providers/ProvidersModule.php`. Audit réalisé par le coordinateur (Claude Code), le code ayant été écrit par GLM.

## Contrôles automatiques
| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs` | ✅ 0 erreur |
| `vendor/bin/phpstan analyse --level=6` | ✅ 0 erreur |
| `vendor/bin/phpunit` | ✅ 212 tests sous PHP 8.1 et 8.3 ; 212 tests (1 ignoré, chemin de repli HTML) sous PHP 8.5 |
| `vendor/bin/phpunit --group=security` | ✅ 84 tests, 238 assertions |
| `composer audit` | ✅ aucune vulnérabilité |
| `npm audit --audit-level=high` | ✅ 0 vulnérabilité |
| `gitleaks` (historique git) | ✅ aucune fuite |

## Recherche de motifs dangereux
Aucune occurrence dans le périmètre : fonctions interdites, backticks, `file_get_contents`/`fopen`, superglobales, requêtes `$wpdb`, `wp_redirect`, `wp_remote_get`, `echo`/`print`. Chaque fichier commence par `defined( 'ABSPATH' ) || exit;`.

## Findings
| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding sur le périmètre. | — |

## Checklist manuelle

### 3.1 Réponses de l'IA = entrées non fiables
- OK — Les fournisseurs renvoient le texte brut ; la sanitization (`Sanitizer::inline_html`) et la réinsertion en nœuds texte restent assurées par `Queue\BatchJob` et `Render\Translator`, inchangés.
- OK — Réponse non conforme rejetée via `ResponseValidator` (ids inconnus, JSON invalide), testé pour chaque fournisseur.
- OK — Prompt partagé (`PromptBuilder`) : contenu délimité par `<segments>` comme donnée non fiable, consigne d'ignorer toute instruction ; test `test_prompt_injection_attempt_is_wrapped_as_untrusted_data` pour OpenAI, Mistral et Gemini.
- OK — Tests de réponse IA contenant `<script>`, `onerror` et `javascript:` pour chaque nouveau fournisseur.

### 3.2 Contrôle d'accès
- Sans objet : aucune route REST, action admin ou AJAX ajoutée.

### 3.3 Clés API
- OK — Clé lue uniquement via `ApiKeyStore::get()` au moment de l'appel.
- OK — Transport en en-tête : `Authorization: Bearer` (OpenAI, Mistral), `x-goog-api-key` (Gemini). La clé Gemini ne transite jamais dans l'URL.
- OK — `ProviderException` porte des messages génériques et le code HTTP, jamais la réponse brute ni la clé ; vérifié par `assertStringNotContainsString` dans les tests de chaque fournisseur.

### 3.4 Abus et coûts
- OK — Appels IA uniquement depuis les jobs Action Scheduler (inchangé). `Pricing` n'utilise jamais un prix promotionnel temporaire (Gemini 3.8 Flash et 4 Argon au tarif normal) pour ne pas sous-estimer le coût.

### 3.5 Réseau et redirections
- OK — Endpoints codés en dur et en HTTPS : `api.anthropic.com/v1/messages`, `api.openai.com/v1/responses`, `api.eu.mistral.ai/v1/chat/completions`, `generativelanguage.googleapis.com/v1beta/models/%s:generateContent`.
- OK — Pour Gemini, l'identifiant de modèle inséré dans l'URL est filtré par liste blanche (`models()`) puis passé par `rawurlencode`.
- OK — `timeout` 60 s et `sslverify` à `true` sur chaque appel.

### 3.6 Fichiers et imports
- Sans objet.

### 3.7 Base de données et désinstallation
- Sans objet : aucune requête SQL ni option ajoutée.
