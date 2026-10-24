# Audit confidentialité — compléments de recette v1 — 2026-10-24
Verdict : ✅ VALIDÉ (plugin testé)

## Périmètre et flux sortants
Seuls l'environnement Oxygen et les scripts de recette ont changé ; aucun `src/` ni actif front de production n'a changé. La recherche `wp_remote_*`/`wp_safe_remote_*` et des URL dans `src/` et `assets/src/` retrouve les flux existants : API Anthropic (`ClaudeProvider.php:224`), OpenAI (`OpenAIProvider.php:217`), Mistral UE (`MistralProvider.php:242`), Gemini (`GeminiProvider.php:242`), crawler vers `home_url()` (`ScanJob.php:194`, `PublicContent.php:99`) et vérification de version GitHub (`Core/Updater.php`). Aucun appel de ces fournisseurs n'est exécuté par la recette ; `env/recette/mock-provider.php` reste local.

## Checklist (étapes 2 à 5 du skill)
| Section | Résultat et preuve |
| --- | --- |
| 2. Contenu envoyé à l'IA | OK : aucun changement du crawler, du filtre PII, des exclusions ou des prompts ; test avec fournisseur simulé uniquement. |
| 3. Stockage et journaux | OK : aucun changement du stockage ni des logs de production. Les captures JSON temporaires contiennent uniquement URL et statuts HTTP de sites `localhost`, aucune clé ni contenu de segment. |
| 4. Côté visiteurs | OK pour le plugin : mesure Playwright des attributs `src`/`href` de `script`, `link`, `img`, `iframe` et des requêtes réseau sur `/recette-v1-01/` et `/en/recette-v1-01/`, plugin OFF/ON. Origines ajoutées : **0** sur Gutenberg, Elementor et Oxygen, en source et en EN. Gutenberg/Oxygen : 0 origine externe ; Elementor : `fonts.googleapis.com` et `fonts.gstatic.com` présents OFF comme ON (Elementor/thème). Aucun actif, cookie ou redirection de production modifié. |
| 5. Transparence | OK : aucune politique, option ou fournisseur modifié. La limite du critère littéral « zéro requête tierce du site entier » est explicitée dans `audits/recette-v1-2026-10-24.md`. |

## Contrôles complémentaires
`npm run lint`, `npm run build`, `npm audit --audit-level=high`, contrôles PHP complets et groupe sécurité : tous verts. Les parcours admin FR/EN Elementor et Oxygen rapportent `KEY_IN_API_RESPONSES=0` et `KEY_IN_DOM_AFTER_WIZARD=false`. L'archive `woocommerce.zip` sert uniquement à rendre la détection Rank Math/WooCommerce conforme au chemin canonique dans le conteneur de test.

## Findings
Aucun finding Critique ou Haute du plugin. La ressource Google Fonts du site Elementor reste une dépendance du builder/thème et doit être décidée séparément si le critère littéral est exigé pour l'installation entière.
