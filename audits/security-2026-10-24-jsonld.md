# Audit sécurité — JSON-LD — 2026-10-24

**Verdict : ✅ VALIDÉ** pour les modifications JSON-LD de cette branche.

## Contrôles automatiques

| Contrôle | Résultat |
| --- | --- |
| PHPCS (`src/`, bootstrap, désinstallation) | Conforme, 109 fichiers |
| PHPStan niveau 6 | Conforme, aucune erreur |
| PHPUnit `--group=security` | Conforme, 163 tests et 525 assertions |
| PHPUnit complet, PHP 8.5 et 8.3 | Conforme, 309 tests et 954 assertions ; un test préexistant ignoré sur chaque version |
| `composer audit` | Aucune vulnérabilité |
| `npm audit --audit-level=high` | 0 vulnérabilité |
| Gitleaks `detect --no-banner` via Docker | 108 commits analysés, aucune fuite ; binaire absent de l'hôte |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Basse, corrigée | `src/Render/HtmlSegmentExtractor.php:132-158` | Le repli DOMDocument restaurait les scripts JSON-LD sans les traduire. | Traduction des blocs protégés au passage sur le placeholder, avant restauration ; test de rendu sur PHP 8.3. |

La recherche des fonctions interdites, entrées brutes, requêtes SQL et redirections dans `src/` n'a révélé aucune nouvelle occurrence dangereuse dans les fichiers JSON-LD. Les occurrences existantes sont traitées par les audits de leurs modules respectifs ; aucune suppression de contrôle ni exception PHPCS n'a été ajoutée.

## Checklist manuelle

| Point | État | Preuve |
| --- | --- | --- |
| 3.1 Réponses IA non fiables | OK | `JsonLdProcessor.php` limite les propriétés, applique `Sanitizer::plain_text()` et encode avec `JSON_HEX_TAG`. `Translator.php` réinsère le HTML courant par nœuds DOM. `JsonLdProcessorTest.php` injecte `</script><script>alert(1)</script>` et vérifie que le JSON reste décodable et qu'aucune balise de script supplémentaire n'apparaît. |
| 3.2 Contrôle d'accès | OK, inchangé | Aucun endpoint, formulaire ni action admin ajouté par le traitement JSON-LD. |
| 3.3 Clés API | OK, inchangé | `Translator.php` lit uniquement `TranslationRepositoryInterface::find_many()` ; aucun accès à `ApiKeyStore`. |
| 3.4 Abus et coûts | OK | `HtmlSegmentExtractor.php` produit des hash `SegmentKey` ; `ScanJob.php:86-125` et `BatchJob` utilisent la file existante. `Translator.php:73-86` ne fait que consulter le cache et le dépôt, sans appel fournisseur. |
| 3.5 Réseau et redirections | OK | `UrlTranslator.php` réécrit des URL internes ; aucun appel réseau nouveau. Le crawler utilise `wp_safe_remote_get()` (`ScanJob.php:194`). |
| 3.6 Fichiers et imports | OK, inchangé | Aucun traitement de fichiers, import ou export ajouté. |
| 3.7 SQL et désinstallation | OK, inchangé | Aucune table, option ou requête SQL nouvelle. |

Le rendu du Product vérifie `name`, `description`, `@id`, `url`, `sku`, `gtin13`, `offers.price` et `offers.priceCurrency`. Deux rendus successifs n'effectuent qu'une lecture du dépôt grâce au cache partagé.
