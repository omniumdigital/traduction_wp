# Audit sécurité — widget Elementor et file WooCommerce — 2026-10-24
Verdict : ✅ VALIDÉ

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS | ✅ Pass (`src/`, bootstrap et désinstallation) |
| PHPStan niveau 6 | ✅ 0 erreur, stubs Elementor optionnels inclus |
| PHPUnit `@group security` | ✅ 144 tests, 407 assertions |
| Composer audit | ✅ Aucune vulnérabilité |
| npm audit (high) | ✅ 0 vulnérabilité après mise à jour de `body-parser` 1.20.8 vers 1.20.9 |
| Gitleaks | ✅ Aucun secret détecté (81 commits, scan Docker) |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif appliqué |
| --- | --- | --- | --- |
| Haute (corrigée) | `package-lock.json` | `body-parser` 1.20.8, dépendance de développement via `@wordpress/env`, était concerné par GHSA-27r3-jxmh-p828. | Lockfile mis à jour vers 1.20.9; `npm audit --audit-level=high` repasse à 0. |
| Moyenne (corrigée) | `src/Queue/QueueModule.php:74-77` | `TranslationQueueInterface` n'était pas liée à son implémentation, provoquant une erreur fatale quand WooCommerce initialisait la traduction des emails. | Liaison explicite vers `ActionSchedulerQueue`; test de régression `QueueModuleTest`. |
| Moyenne (corrigée) | `src/Switcher/ElementorSwitcherWidget.php` | Une première implémentation utilisait un constructeur personnalisé incompatible avec la création d'instances Elementor. | Conservation du constructeur Elementor natif; renderer injecté lors de l'enregistrement du prototype (`ElementorIntegration.php:29-31`). |

Aucun finding Critique ou Haute restant.

## Checklist manuelle

- 3.1 Réponses IA : ✅ le widget appelle exclusivement `Switcher::render()`; aucun `innerHTML`, HTML Elementor ou contenu IA n'est interprété. Les deux valeurs de contrôle sont revalidées par les listes blanches PHP.
- 3.2 Contrôle d'accès : ✅ aucune route, action d'écriture ou endpoint public ajouté. Enregistrement sur le hook officiel `elementor/widgets/register`.
- 3.3 Clés API : ✅ aucun accès aux clés.
- 3.4 Abus et coûts : ✅ le widget lit uniquement les traductions déjà disponibles et ne déclenche aucun appel fournisseur. La correction de liaison de file ne modifie ni quotas, ni verrou, ni conditions d'enqueue.
- 3.5 Réseau et redirections : ✅ aucun appel réseau ajouté; liens issus de `home_url()` et de `UrlTranslator` puis échappés.
- 3.6 Fichiers et imports : ✅ aucun import, export ou téléversement ajouté.
- 3.7 Base et désinstallation : ✅ aucune table, option ou requête SQL ajoutée.

L'exception PHPCS de `ElementorSwitcherWidget.php:117` est documentée dans le code : la valeur imprimée provient entièrement du renderer `Switcher`, qui échappe chaque URL, attribut et libellé. Les tests d'injection du renderer restent exécutés dans le groupe sécurité.
