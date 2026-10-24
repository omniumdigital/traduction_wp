# Audit sécurité — décodage des entités HTML parasites — 2026-10-24
Verdict : ✅ VALIDÉ

## Périmètre
`src/Render/HtmlSegmentExtractor.php` (sérialisation du document traduit), `tests/Unit/Html5RenderingTest.php`.

## Contexte
Sur PHP < 8.4 (sans `Dom\HTMLDocument`), le sérialiseur HTML4 historique de `DOMDocument` convertit tout caractère non-ASCII des nœuds texte créés ou importés après le chargement initial (donc toute traduction insérée) en entité HTML (`é` → `&eacute;`). Le texte source (non modifié) n'est pas affecté. Le rendu visuel est correct dans tout navigateur, mais le HTML produit diffère inutilement de la source et complique toute comparaison texte brut (recette, indexation, outils tiers).

## Correctif
`decode_spurious_entities()` protège les cinq entités structurelles XML (`&amp; &lt; &gt; &quot; &#039;`) par des jetons temporaires, décode tout le reste via `html_entity_decode()`, puis restaure les jetons protégés. Appliqué uniquement sur la sortie de `saveHTML()`, avant la restauration des blocs sensibles (`<script>`, `<style>`, `<svg>`, `<math>`, `<template>`) qui ne passent jamais par ce chemin.

## Contrôles automatiques
| Outil | Résultat |
| --- | --- |
| phpcs | ✅ 0 erreur, 0 avertissement |
| phpstan niveau 6 | ✅ Aucune erreur |
| phpunit (dont le nouveau test `test_translated_accented_text_is_not_entity_encoded`) | ✅ 334 tests, 994 assertions |

## Findings
Aucun.

## Checklist manuelle
- [x] Les entités structurelles (`&amp; &lt; &gt; &quot; &#039;`) restent intactes : un texte traduit contenant littéralement `&`, `<`, `"` ou `'` reste correctement échappé après le correctif (vérifié par le test, qui inclut `&` dans le texte source et la traduction).
- [x] Aucune réintroduction de caractères `<`/`>` non échappés dans un contexte de balise : le décodage ne touche que les entités nommées/numériques autres que les cinq protégées, jamais une balise réelle.
- [x] Les blocs sensibles (`<script>`, `<style>`, JSON-LD) sont restaurés **après** le décodage et ne passent jamais par `html_entity_decode` : leur contenu reste strictement byte-for-byte celui du document source (test `test_translated_html5_keeps_special_elements_intact` toujours vert).
- [x] Pas de nouvelle entrée utilisateur : la fonction opère uniquement sur la sortie déjà sanitizée (`wp_kses` via `Sanitizer::inline_html`) du pipeline de traduction.

Règle de décision : aucun finding Critique/Haute → pas de blocage.
