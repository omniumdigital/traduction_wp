# Audit sécurité — sélecteur de langue — 2026-10-23
Verdict : ✅ VALIDÉ

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS | ✅ Pass (`src/`, bootstrap et désinstallation) |
| PHPStan niveau 6 | ✅ 0 erreur |
| PHPUnit `@group security` | ✅ 141 tests, 394 assertions |
| Composer audit | ✅ Aucune vulnérabilité |
| npm audit (high) | ✅ 0 vulnérabilité |
| Gitleaks | ✅ Aucun secret détecté (81 commits, scan Docker) |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Aucun | — | Aucun finding sur le lot | — |

Le sélecteur n'accepte que les variantes et modes d'affichage en liste blanche. Les codes de langue configurés sont validés par une expression stricte; classes, attributs, libellés et URL sont échappés au rendu. Le test de sécurité injecte un code de langue contenant une balise `script` et vérifie son exclusion. L'intégration menu ne remplace que le marqueur exact `#omtr-switcher`; les autres sorties du walker sont préservées octet pour octet.

Le bloc Gutenberg est dynamique : il ne sauvegarde aucun HTML et ne transmet au renderer que les attributs `variant` et `display`, contraints par `enum` dans `block.json` puis de nouveau passés dans les listes blanches PHP (`assets/blocks/switcher/block.json:11-21`, `src/Switcher/GutenbergBlock.php:32-39`). Un test couvre aussi des attributs persistés malveillants.

## Checklist manuelle

- 3.1 Réponses IA : ✅ aucun changement du chemin IA; le nouveau front n'utilise ni `innerHTML` ni contenu traduit comme balisage (`src/Switcher/Switcher.php:107-143`, `assets/src/switcher/index.js:1-14`).
- 3.2 Contrôle d'accès : ✅ aucune route REST, action AJAX ou option ajoutée. La boîte de menu fournit uniquement des champs au formulaire natif `nav-menus.php`; l'enregistrement reste protégé par les capability et nonce de WordPress Core (`src/Switcher/MenuIntegration.php:38-63`). Le bloc utilise l'enregistrement Gutenberg standard, sans endpoint personnalisé.
- 3.3 Clés API : ✅ aucun accès aux clés ajouté.
- 3.4 Abus et coûts : ✅ le sélecteur ne met rien en file et ne déclenche aucun fournisseur.
- 3.5 Réseau et redirections : ✅ les destinations sont construites depuis `home_url()` puis passées à `UrlTranslator`; chaque `href` passe par `esc_url()` (`src/Switcher/Switcher.php:117-124`, `src/Switcher/Switcher.php:165-168`).
- 3.6 Fichiers et imports : ✅ aucun import, export ou téléversement ajouté.
- 3.7 Base et désinstallation : ✅ aucune table, option ou requête SQL ajoutée; l'élément utilise le stockage standard des menus WordPress avec l'URL marqueur `#omtr-switcher`.

Vérification complémentaire : les nouveaux fichiers PHP portent tous la garde `ABSPATH`; aucun motif dangereux, sortie brute ou appel réseau n'est introduit par le lot.
