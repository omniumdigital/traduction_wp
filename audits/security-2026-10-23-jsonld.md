# Audit sécurité — 0.1.0 — 2026-10-23

**Verdict : ✅ VALIDÉ** pour les changements URL, sitemap et JSON-LD du jalon 4.

## Contrôles automatiques

| Contrôle | Résultat |
| --- | --- |
| PHPCS sur `src/`, bootstrap et désinstallation | Conforme, 95 fichiers |
| PHPStan niveau 6 | Conforme |
| PHPUnit `--group=security` | Conforme, 125 tests / 351 assertions ; suite complète : 270 tests |
| `composer audit` | Aucune vulnérabilité signalée |
| `npm audit --audit-level=high` | 0 vulnérabilité |
| Gitleaks `detect --no-banner` via Docker | 77 commits analysés, aucune fuite |
| PHP 8.1 | Suite complète conforme |

Le binaire Gitleaks n'est pas installé sur l'hôte ; l'image Docker a analysé le dépôt et son historique Git. La recherche des motifs interdits n'a relevé aucun appel dangereux dans les fichiers modifiés. Les appels SQL du `SlugRepository` passent par `prepare()` avec des paramètres numériques et textuels ; la liste de placeholders est construite localement.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Aucun | — | Aucun finding bloquant sur les changements | — |

## Checklist manuelle

| Point | État | Preuve |
| --- | --- | --- |
| 3.1 Traductions non fiables et XSS | OK | `JsonLdProcessor.php:115` passe par `Sanitizer::plain_text` ; `:56` encode avec `JSON_HEX_TAG`. Test d'injection `</script>` dans `JsonLdProcessorTest.php`. Les textes HTML ordinaires restent réinsérés comme nœuds par `Translator.php`. |
| 3.2 Contrôle d'accès | OK, inchangé | Aucune route ni action admin ajoutée. Les routes existantes déclarent `permission_callback` dans `Admin/RestController.php` et `Admin/TriggerController.php`. |
| 3.3 Clés API | OK, inchangé | Aucun accès à la clé ajouté ; le rendu lit seulement `TranslationRepositoryInterface::find_many` (`Translator.php:81`). |
| 3.4 Abus et coût IA | OK | Aucun appel de fournisseur dans le rendu JSON-LD ; les segments sont extraits par `HtmlSegmentExtractor.php` et pris en charge par la file existante. |
| 3.5 Réseau et redirections | OK | `UrlTranslator.php` construit seulement des URL internes ; aucun nouvel appel réseau. Le crawler existant utilise `wp_safe_remote_get`. |
| 3.6 Fichiers et imports | OK, inchangé | Aucun import ou export modifié. |
| 3.7 SQL et désinstallation | OK | `SlugRepository::many()` utilise `prepare()` ; aucune table ou option nouvelle, donc aucune migration ou modification de désinstallation. |

Le test de sitemap de 100 contenus vérifie une résolution par URL et une seule lecture SQL groupée des slugs. Les blocs JSON invalides restent inchangés ; la langue source et le fallback DOMDocument conservent les scripts intacts.
