# Audit sécurité — WooCommerce — 2026-10-23
Verdict : ✅ VALIDÉ

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS | ✅ Pass |
| PHPStan niveau 6 | ✅ 0 erreur |
| PHPUnit `@group security` | ✅ 130 tests, 358 assertions |
| Composer audit | ✅ Aucune vulnérabilité |
| npm audit (high) | ✅ 0 vulnérabilité |
| Gitleaks | ✅ Aucun secret détecté (78 commits, scan Docker) |
| PHPUnit PHP 8.1 | ✅ 272 tests, 778 assertions |
| PHPUnit PHP 8.5 | ✅ 272 tests, 774 assertions, 1 test ignoré |

## Findings

| Sévérité | Fichier | Problème constaté | Correctif vérifié |
| --- | --- | --- | --- |
| Haute | `src/Render/Translator.php`, `src/Woo/FragmentTranslator.php`, `src/Woo/CatalogTranslator.php` | Un fragment passé à `translate()` ressortait comme document complet, y compris dans les valeurs textuelles. | `translate_fragment()` isole le contenu avec des marqueurs internes; les intégrations Woo utilisent cette méthode. Tests PHP 8.1 et 8.5. |
| Moyenne | `src/Woo/EmailTemplateTranslator.php` | Sujet traduit non formaté, réglage marchand ignoré, gabarit vide renvoyé vide. | Lecture de `get_option()` avant le défaut, traduction du gabarit seul, puis `format_string()`; valeur formatée préservée si gabarit vide. |

Aucun finding Critique ou Haute restant. Les variations ne traduisent que `availability_html` et `variation_description`; prix, SKU, identifiants, attributs et JSON commercial restent intacts.

## Checklist manuelle

- 3.1 Réponses IA : ✅ les sorties passent par `Translator::walk()`/`Sanitizer`; `translate_fragment()` n'utilise que le contenu compris entre ses marqueurs. Les sujets et libellés sont ramenés en texte brut. Aucun HTML de variation commerciale n'est reconstruit côté client.
- 3.2 Accès : ✅ aucune route, action d'écriture ou endpoint public ajouté.
- 3.3 Clés API : ✅ aucun accès aux clés ajouté.
- 3.4 Coûts : ✅ fragments et emails utilisent uniquement les traductions stockées; les gabarits manquants passent par la file existante. `upsert_many()` et `enqueue_segments()` ne sont pas appelés lorsque toutes les traductions sont présentes.
- 3.5 Réseau : ✅ aucun appel réseau ajouté; la langue du Referer est limitée à l'hôte courant et aux langues configurées.
- 3.6 Fichiers : ✅ aucun import/export ou écriture de fichier ajouté.
- 3.7 Base : ✅ commande HPOS via `update_meta_data()` uniquement; méta limitée à `_omtr_order_language`.

Recette UI Elementor + WooCommerce (`127.0.0.1:8882`) : endpoint disponible (HTTP 200), parcours manuel non exécuté dans ce terminal.
