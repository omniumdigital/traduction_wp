# Audit sécurité — déclencheurs éditeurs, édition visuelle, cookie, purge — 2026-10-24

Verdict : ✅ VALIDÉ

Périmètre : fichiers modifiés/ajoutés de la branche `omniumdigital/feat-editor-visual`
(`src/Admin/TranslationActions.php`, `src/Admin/AdminModule.php`, `src/Admin/RestController.php`,
`src/Admin/Administration.php`, `src/Core/Settings.php`, `src/Core/TranslationCachePurger.php`,
`src/Queue/BatchJob.php`, `src/Render/{OutputBuffer,RenderModule,Translator,VisualEditor}.php`,
`src/Switcher/{Switcher,SwitcherModule,LanguagePreference}.php`, `assets/src/editor/`,
`assets/src/visual-editor/`, `assets/src/admin/Settings.jsx`, `assets/src/switcher/index.js`).
`src/Seo` volontairement hors périmètre (autre agent).

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS (WordPress CS) | ✅ Réussi dans Docker (`composer:2`, phpcs.xml.dist) |
| PHPStan niveau 6 | ✅ 111 fichiers, 0 erreur |
| PHPUnit | ✅ 313 tests, 918 assertions, 1 ignoré (préexistant) ; `--group security` au vert |
| Composer audit (locked) | ✅ Aucun avis de sécurité |
| npm audit --audit-level=high | ✅ 0 vulnérabilité |
| gitleaks | ⚠️ Non installé sur l’hôte ; exécuté en CI. Vérification manuelle : aucun secret/motif `sk-…` dans le diff |

## Recherche de motifs dangereux

- Fonctions interdites (`eval`, `assert`, `exec`, `unserialize`, `extract`, …) : aucune. Le seul
  hit `extract(` est l’appel de méthode `$this->extractor->extract()` (src/Render/Translator.php:75).
- `file_get_contents`/`fopen` sur URL, `include`/`require` dynamique : aucun.
- Superglobales : toutes passées par `wp_unslash` + `sanitize_*`/`absint`
  (VisualEditor.php:27, LanguagePreference.php:30/34, TranslationActions.php:111).
- `$wpdb` : requêtes préparées (`VisualEditor::ids_by_hash` : placeholders générés depuis le nombre
  de hash uniquement, `%i` pour le nom de table ; aucun tri/colonne dynamique).
- Sorties : `esc_html`/`esc_attr`/`esc_url` partout dans le nouveau markup (méta-box, admin bar,
  sélecteur) ; le marquage `data-omtr-segment` est un entier casté en chaîne.
- `wp_redirect` : aucun ; seules `wp_safe_redirect` (LanguagePreference.php:44,
  TranslationActions.php:118) sur des URLs construites depuis `home_url()`/`admin_url()`.
- `defined( 'ABSPATH' ) || exit;` présent dans les 3 nouveaux fichiers PHP.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Basse | src/Render/VisualEditor.php:27 | `$_GET['omtr_edit']` lu sans nonce | Accepté : indicateur de présentation en lecture seule, sans effet de bord, doublé d’un contrôle `omtr_manage` côté serveur ; commenté dans le code et couvert par test |

Aucun finding Critique ou Haute.

## Checklist manuelle

### 3.1 Réponses de l’IA = entrées non fiables
- OK — Les traductions restent réinsérées en nœuds DOM (`Translator::replace_text_node`) ;
  le `span data-omtr-segment` ne modifie pas la sanitization (src/Render/Translator.php:180-215).
- OK — L’édition visuelle passe par `PUT /segments/{id}` : `Sanitizer::inline_html` appliqué au
  schéma REST puis au dépôt, identique à l’éditeur admin (RestController.php:329,
  SegmentEditorRepository.php:105) ; statut `edited` (TranslationRepository.php:129).
- OK — Tests XSS stockée (TranslatorSecurityTest) et XSS via édition (RestControllerSecurityTest :
  charge utile `<script>` neutralisée).

### 3.2 Contrôle d’accès
- OK — `permission_callback` `omtr_manage` sur chaque route REST, dont `PUT /segments/{id}`
  (RestController.php:175) ; aucune route publique en écriture.
- OK — Mode visuel réservé : `VisualEditor::active()` exige option activée + `omtr_manage` +
  langue cible + contenu singulier, hors admin/AJAX (VisualEditor.php:26-29) ;
  `?omtr_edit=1` sans capability → aucun marquage (VisualEditorSecurityTest).
- OK — Méta-box et panneau éditeur masqués sans `omtr_manage` (TranslationActions.php:49-56,
  74-81) ; handler `admin_post` = capability + `check_admin_referer` (TranslationActions.php:107-112).
- OK — Script éditeur visuel et configuration (nonce REST) injectés uniquement si `active()`
  (VisualEditor.php:56-70).

### 3.3 Clés API
- OK — Aucune manipulation de clé dans le diff ; aucune clé rendue au navigateur.

### 3.4 Abus et coûts
- OK — Le mode édition désactive la capture « à la volée » (OutputBuffer.php:55-58) : aucune mise
  en file depuis une requête d’éditeur ; aucun appel IA dans une requête visiteur.
- OK — `POST /translate` inchangé (estimation, verrou, quotas).

### 3.5 Réseau et redirections
- OK — Redirection cookie : `wp_safe_redirect` 302 vers l’accueil de la langue via `UrlTranslator`
  (LanguagePreference.php:43-45) ; uniquement accueil source sans préfixe + arrivée externe +
  langue configurée ; jamais selon Accept-Language.
- OK — Aucun nouvel endpoint fournisseur, aucune ressource tierce (JS vanilla servi par le plugin).

### 3.6 Fichiers et imports
- OK — Aucun import/export modifié.

### 3.7 Base de données et désinstallation
- OK — `VisualEditor::ids_by_hash` : lecture préparée, aucun tri/colonne dynamique, aucune
  écriture.
- OK — La purge de cache n’est déclenchée que pour un `post_id` résolu en contenu public connu
  (RestController.php:330-332, BatchJob.php:130-136) — pas de purge aveugle d’identifiants
  arbitraires ; `omtr_translations_updated` documenté (docs/admin-api.md).
