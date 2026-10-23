# Audit confidentialité — jalon 3 fournisseurs (non versionné) — 2026-10-23
Verdict : ✅ VALIDÉ

Périmètre : fournisseurs OpenAI, Mistral et Gemini, mise à jour des modèles Claude, table `Providers\Pricing`. Audit réalisé par le coordinateur (Claude Code).

## Flux sortants
| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| `https://api.anthropic.com/v1/messages` | `src/Providers/Claude/ClaudeProvider.php` | Segments publics, langues, glossaire, ton | ✅ (fournisseur choisi) |
| `https://api.openai.com/v1/responses` | `src/Providers/OpenAI/OpenAIProvider.php` | Segments publics, langues, glossaire, ton | ✅ (fournisseur choisi) |
| `https://api.eu.mistral.ai/v1/chat/completions` | `src/Providers/Mistral/MistralProvider.php` | Segments publics, langues, glossaire, ton | ✅ (fournisseur choisi, traitement garanti UE) |
| `https://generativelanguage.googleapis.com/v1beta/models/{modèle}:generateContent` | `src/Providers/Gemini/GeminiProvider.php` | Segments publics, langues, glossaire, ton | ✅ (fournisseur choisi) |

Un seul fournisseur est appelé : celui configuré. Les autres URL du périmètre sont des sources de documentation et des pages de création de clé (`key_url()`), jamais appelées par le plugin.

## Findings
| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Moyenne (suivi jalon 5) | `src/Providers/Mistral/MistralProvider.php`, `src/Providers/Gemini/GeminiProvider.php` | `has_free_tier()` vaut `true` pour Mistral et Gemini. La page de prix Google indique que les données du palier gratuit servent à améliorer ses produits. L'avertissement visible dans les réglages (§5 et §10) n'existe pas encore, faute d'interface d'administration. | Afficher l'avertissement dans l'assistant et les réglages au jalon 5, à partir de `has_free_tier()`. Non bloquant : aucune interface ne permet encore de choisir ces fournisseurs. |

## Checklist

### Étape 2 — Ce qui part vers l'IA
- OK — Les fournisseurs ne reçoivent que les segments fournis par la file (crawler anonyme, filtre PII en amont, inchangés), les langues, le glossaire et le ton via `PromptBuilder`.
- OK — Le prompt ne contient ni URL d'admin, ni nom d'utilisateur, ni autre donnée de configuration.

### Étape 3 — Stockage et journaux
- OK — Aucun log ni stockage ajouté ; les erreurs fournisseur restent génériques (code HTTP), jamais la réponse brute.

### Étape 4 — Côté visiteurs
- Sans objet : aucune ressource front, cookie ou stockage navigateur ajouté.

### Étape 5 — Transparence pour le client
- OK — Chaque fournisseur expose `key_url()`, `region()` (Mistral `eu` grâce à l'endpoint UE ; Claude, OpenAI, Gemini `non-eu`) et `has_free_tier()`, données nécessaires à l'assistant et à `wp_add_privacy_policy_content()`.
- À faire (jalon 5) — Avertissement palier gratuit et texte de politique de confidentialité ; voir finding Moyenne.
- OK — Aucune télémétrie vers Omnium.
