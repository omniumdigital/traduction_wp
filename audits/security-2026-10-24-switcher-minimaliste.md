# Audit sécurité — v1.0.1 — 2026-10-24
Verdict : ✅ VALIDÉ

Périmètre : `src/Switcher/Switcher.php`, `src/Core/Settings.php`, `assets/css/switcher.css`, `assets/src/admin/Settings.jsx`, `assets/src/admin/SwitcherPreview.jsx`, le fournisseur simulé et les tests associés. Le rapport complet du code reste `audits/security-2026-10-24.md`.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php` (Docker `composer:2`) | ✅ 112 fichiers, aucune erreur |
| `vendor/bin/phpstan analyse --level=6 --memory-limit=1G` (Docker `composer:2`) | ✅ 112 fichiers, aucune erreur |
| `vendor/bin/phpunit --group=security` (Docker `composer:2`) | ✅ 195 tests, 603 assertions |
| `composer audit` (Docker `composer:2`) | ✅ aucune alerte |
| `npm audit --audit-level=high` | ✅ 0 vulnérabilité |
| `npm run lint` / `npm run build` | ✅ 20 fichiers contrôlés, build généré |
| `gitleaks detect --no-banner` (Docker `zricethezav/gitleaks`) | ✅ 141 commits, aucune fuite |

## Recherche de motifs dangereux

- Aucune fonction interdite, lecture d’URL par fichier, inclusion dynamique, redirection ou appel réseau ajouté par ce changement.
- Le HTML du sélecteur continue d’échapper URL, codes de langue, libellés, classes et variables CSS (`src/Switcher/Switcher.php:129-172,180-193,235-250`).
- Les valeurs de présentation restent bornées et les couleurs limitées au format hexadécimal (`src/Switcher/Switcher.php:180-193`).
- Le nouveau marquage `data-omtr-skip`, `translate="no"` et `notranslate` est statique et ne transporte aucune entrée utilisateur (`src/Switcher/Switcher.php:144-165`).

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding nouveau | — |

## Checklist manuelle

### 3.1 Réponses de l’IA

- OK — Le sélecteur est désormais exclu à la fois de l’extraction et de la réinsertion des traductions par `data-omtr-skip` et `notranslate` (`src/Switcher/Switcher.php:144-165`, règles existantes dans `HtmlSegmentExtractor.php:307-318` et `Translator.php:238-245`).
- OK — Aucune réponse IA n’est injectée dans ce composant. Les nœuds, attributs, JSON strict, prompt délimité et tests XSS restent couverts par l’audit complet `security-2026-10-24.md`, section 3.1.
- OK — Le test `tests/Unit/SwitcherTest.php:103-111` exige les trois marqueurs d’exclusion.

### 3.2 Contrôle d’accès

- OK — Aucun endpoint REST, formulaire, action AJAX ou capability modifié. Les contrôles décrits dans `security-2026-10-24.md`, section 3.2, restent inchangés.

### 3.3 Clés API

- OK — Aucun accès, stockage, affichage ou journalisation de clé modifié. Le fournisseur simulé ne lit pas de clé réelle.

### 3.4 Abus et coûts

- OK — Aucun déclencheur ni appel fournisseur ajouté au rendu visiteur. L’exclusion du sélecteur réduit au contraire les segments susceptibles d’être mis en file.

### 3.5 Réseau et redirections

- OK — Aucun nouvel appel réseau. Les liens de langue restent construits par `UrlTranslator` et échappés avec `esc_url()` (`src/Switcher/Switcher.php:129-138`).

### 3.6 Fichiers et imports

- OK — Aucun import, export ou traitement de fichier modifié.

### 3.7 Base de données et désinstallation

- OK — Aucun schéma, SQL de production, option ou comportement de désinstallation ajouté. La valeur par défaut du drapeau passe seulement de 18 à 16 px (`src/Core/Settings.php:37-50`).

Aucun finding Critique ou Haute ouvert.
