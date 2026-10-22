# CLAUDE.md — Omnium Translate

Extension WordPress de traduction automatique par IA, développée par Omnium Digital pour être installée chez plusieurs clients (WP + Elementor ou Oxygen, WooCommerce parfois). Sans abonnement, sans serveur Omnium : tout tourne sur le site du client, avec sa propre clé API.

Ce fichier est la charte du projet. Claude Code le lit à chaque session et doit s'y conformer strictement.

---

## 0. Première session : installation des skills (OBLIGATOIRE)

Avant toute autre tâche, vérifie que ces deux fichiers existent :

- `.claude/skills/security-audit/SKILL.md`
- `.claude/skills/privacy-audit/SKILL.md`

S'ils sont absents ou différents du contenu de l'**Annexe A** et de l'**Annexe B** en fin de fichier, crée-les (ou remplace-les) avec le contenu **exact** du bloc correspondant, sans les lignes de délimitation `~~~~`. Puis commite :

```
chore: installer les skills security-audit et privacy-audit
```

Quand ce fichier `CLAUDE.md` est mis à jour, refais cette vérification : les annexes font foi.

---

## 1. Principes non négociables

- Tout tourne sur le site du client : traductions stockées dans sa base, appels IA directs avec sa clé.
- Agnostique du builder : on traduit le **HTML final rendu**, jamais les données internes d'Elementor (`_elementor_data`) ou d'Oxygen.
- On ne paie jamais deux fois : un segment déjà traduit n'est jamais renvoyé à l'IA.
- Sécurité et confidentialité by design : le plugin ne doit jamais devenir une porte d'entrée sur le site.
- Zéro télémétrie : aucune donnée n'est envoyée à Omnium.
- Désactiver le plugin laisse le site d'origine intact.

## 2. Identité et environnement

| Élément | Valeur |
| --- | --- |
| Nom | Omnium Translate |
| Slug | `omnium-translate` |
| Préfixes | `OMTR_` (constantes), `omtr_` (fonctions, options, hooks, tables) |
| Namespace | `Omnium\Translate` |
| Licence | GPL-2.0-or-later |
| WordPress min. | 6.9 (testé jusqu’à 7.1) |
| PHP min. | 8.1 |
| WooCommerce | 9.0 à la dernière version (testé jusqu’à 11.2), optionnel |

Hors périmètre v1 : traduction des médias, multisite, gestion de licences, sous-domaines par langue.

## 3. Workflow de développement

**Environnement local** : `wp-env` (Docker), configuration dans `.wp-env.json`. Trois sites de test :
1. WP + Elementor + WooCommerce
2. WP + Oxygen 6 + WooCommerce
3. WP + Gutenberg seul

Plugins SEO de test : Yoast SEO et Rank Math.

**Git**
- `main` = stable. Travail sur `feat/*` et `fix/*`, merge par pull request.
- Versionnage sémantique, tag `vX.Y.Z`. La version du header du plugin = le tag.
- `CHANGELOG.md` mis à jour à chaque release.
- Messages de commit en français, format Conventional Commits (`feat:`, `fix:`, `chore:`, `docs:`, `test:`, `refactor:`, `security:`).

**CI GitHub Actions**
1. Sur PR : PHPCS (WordPress Coding Standards + règles sécurité), PHPStan niveau 6, PHPUnit, lint JS/CSS.
2. `composer audit` et `npm audit --audit-level=high` : échec si vulnérabilité haute ou critique.
3. gitleaks : bloque tout secret commité.
4. Sur tag `v*` : build du zip propre (sans `tests/`, sources JS, `node_modules`, `audits/`, `.claude/`) et publication en GitHub Release.

**Mises à jour clients** : librairie Plugin Update Checker (YahnisElsts) branchée sur les Releases du repo **public**. Aucun token GitHub dans le plugin. Option pour désactiver la vérification.

**Interdit dans le repo** : clés API (même de test), noms, domaines ou données de clients, fichiers `.env`.

## 4. Architecture

### Flux d'une page traduite
1. Requête `/en/ma-page/` → le routeur retire le préfixe, mémorise la langue, WordPress résout `/ma-page/`.
2. Rendu normal (Elementor, Oxygen, Woo…) capturé par output buffering.
3. Parseur (`WP_HTML_Tag_Processor` / DOMDocument) : nœuds texte + attributs `alt`, `title`, `placeholder`, `aria-label` + balises meta.
4. Normalisation puis hash SHA-256 (langue source + texte normalisé, voir `Store\SegmentKey`) ; une traduction unique par (segment, langue cible).
5. Lecture des traductions existantes en **une seule requête**. Segments manquants → mis en file ; le texte source s'affiche en attendant.
6. Réécriture des liens internes vers la langue courante, envoi de la page.

**Exclusions** : `data-omtr-skip`, classe `notranslate`, `<script>`, `<style>`, `<code>`, `<pre>`, admin, éditeurs de builders, REST et AJAX (sauf fragments WooCommerce).

### Structure des fichiers
```
omnium-translate/
├── omnium-translate.php        # bootstrap, header, constantes
├── uninstall.php
├── src/
│   ├── Core/        # Plugin, Container, Activator, Migrations
│   ├── Router/      # préfixes d'URL, détection langue
│   ├── Render/      # output buffer, parseur, réécriture liens
│   ├── Store/       # repositories segments / traductions
│   ├── Providers/   # ProviderInterface + Claude, OpenAI, Mistral, Gemini, Pricing
│   ├── Queue/       # jobs Action Scheduler, retry, rate limit
│   ├── Glossary/
│   ├── Seo/         # hreflang, sitemaps, Yoast / Rank Math
│   ├── Woo/         # emails, fragments panier, attributs
│   ├── Switcher/    # shortcode, bloc, widgets Elementor et Oxygen, menu
│   ├── Admin/       # réglages, assistant, tableau de bord
│   └── Security/    # Crypto, Capabilities, Nonces, Sanitizer, PiiFilter
├── assets/
├── languages/       # interface FR / EN
├── tests/
├── audits/          # rapports des skills (non inclus dans le zip)
└── .claude/skills/
```

### Tables (`{$wpdb->prefix}omtr_`)
| Table | Colonnes principales |
| --- | --- |
| `segments` | id, hash (unique), source_text, source_lang, created_at |
| `translations` | id, segment_id, target_lang, text, status (`auto`/`edited`/`locked`/`failed`), provider, model, updated_at |
| `pages` | post_id, url_hash, content_hash, last_scan_at |
| `slugs` | post_id, target_lang, slug (unique par langue) |
| `glossary` | id, term, target_lang, translation, rule (`translate`/`keep`) |
| `log` | id, level, context, message, created_at — purge 30 jours |

Migrations versionnées via l'option `omtr_db_version`. Toute requête via `$wpdb->prepare()`.

**Performance** : cache objet des traductions par page (transients ou Redis), compatibilité WP Rocket, LiteSpeed Cache, cache serveur par variation d'URL. Objectif : < 50 ms de surcoût sur une page en cache.

## 5. Fournisseurs IA

| Fournisseur | Page de création de clé | Palier gratuit |
| --- | --- | --- |
| Anthropic (Claude) | https://console.anthropic.com/settings/keys | Non |
| OpenAI | https://platform.openai.com/api-keys | Non |
| Mistral | https://console.mistral.ai/api-keys | Oui, débit limité |
| Google Gemini | https://aistudio.google.com/apikey | Oui, débit limité |

DeepSeek est hors périmètre. Liens et conditions à revérifier avant chaque release. Avertissement affiché pour les paliers gratuits (réutilisation possible des données par le fournisseur).

**`ProviderInterface`**
- `translate_batch(array $segments, string $source, string $target, Glossary $g): array`
- `test_key(): Result`
- `models(): array` (un modèle recommandé par défaut, rapide et peu coûteux)
- `limits(): RateLimits`

Appels via `wp_remote_post()`, timeout 60 s, SSL vérifié, **endpoints codés en dur**.

**Prompt**
- Lot JSON numéroté, réponse en JSON strict validée avant enregistrement.
- Conserver balises inline et variables (`%s`, `{prix}`), respecter le glossaire et le ton du site, ne jamais ajouter de contenu.
- Contenu délimité comme donnée + consigne d'ignorer toute instruction qu'il contient.
- Réponse invalide : 1 nouvel essai, puis statut `failed`.

**File (Action Scheduler)**
- Lots de 20 à 50 segments.
- Erreur 429/5xx : backoff 30 s, 2 min, 10 min, 1 h ; 5 essais max.
- Reprise après coupure, pause/reprise manuelles.
- Estimation avant lancement (segments, mots, coût indicatif) à partir de la table de prix `Providers\Pricing` : prix par million de tokens en entrée et en sortie pour chaque modèle, avec source officielle et date de vérification, revérifiés avant chaque release.
- Plafond mensuel optionnel en mots.
- Verrou : une seule traduction complète à la fois.
- **Jamais d'appel IA pendant la requête d'un visiteur.**

## 6. Déclencheurs, glossaire, corrections

- **Page** : bouton dans l'éditeur, action rapide dans les listes, bouton barre d'admin en front.
- **Nouvelles ou modifiées** : crawler interne + comparaison `content_hash`.
- **Tout le site** : toutes les URLs publiques, avec estimation et confirmation.
- **Automatique** (option, off par défaut) : à la publication/mise à jour.
- **À la volée** (option) : segment inconnu en front → mis en file, uniquement pour les URLs de contenus publiés connus, query strings ignorées, jamais pour un utilisateur connecté.

**Glossaire** : règles « ne jamais traduire » et « toujours traduire par », champ ton et contexte du site, import/export CSV, proposition de retraduction après modification.

**Corrections** : éditeur de segments dans l'admin, mode d'édition visuel en front (option). Statut `edited` jamais écrasé ; `locked` pour figer.

## 7. SEO

- Préfixes `/en/`, `/es/`… ; langue source sans préfixe.
- Slugs traduits (ASCII, uniques), modifiables, redirection 301 sur changement.
- Pas de redirection automatique selon la langue du navigateur.
- `<html lang>`, `hreflang` + `x-default` (en `<link>` et dans les sitemaps), `canonical` auto-référent par langue.
- Title, meta description, Open Graph, Twitter Cards traduits.
- JSON-LD : champs texte traduits, URLs réécrites, prix et SKU intacts.
- Sitemaps : Yoast, Rank Math, sitemap WordPress natif, avec `xhtml:link`.
- WooCommerce : fiches, catégories, attributs, variations, fragments panier, emails dans la langue de la commande (méta de commande), pages panier/commande/compte préfixées.
- Option `noindex` d'une langue tant qu'elle n'est pas à 100 %.

## 8. Sélecteur de langue

Style moderne et minimaliste inspiré de shadcn/ui.

- **Variantes** : dropdown (défaut), inline (pastilles), flottant (position réglable).
- **Affichage** : drapeau + nom, drapeau seul, nom seul, code. Nom dans sa propre langue.
- **Style par défaut** : arrondi 8 px, bordure 1 px subtile, ombre légère sur le menu, police héritée du thème, transition 150 ms, chevron discret, coche sur la langue active.

| Paramètre | Variable CSS | Défaut |
| --- | --- | --- |
| Fond | `--omtr-bg` | `#ffffff` |
| Texte | `--omtr-fg` | `#0a0a0a` |
| Fond survol | `--omtr-hover` | `#f4f4f5` |
| Bordure | `--omtr-border` | `#e4e4e7` |
| Arrondi | `--omtr-radius` | `8px` |
| Taille drapeau | `--omtr-flag-size` | `18px` |

- Aperçu en direct dans les réglages, alerte si contraste < 4,5:1, mode sombre auto optionnel (`prefers-color-scheme`).
- **Drapeaux** : SVG flag-icons (MIT) **embarqués**, jamais de CDN. Drapeau choisissable par langue ; par défaut `en` = Royaume-Uni (`gb`).
- **Intégrations** : shortcode `[omtr_switcher variant="dropdown" display="flag-name"]`, bloc Gutenberg, widget Elementor, élément Oxygen, élément de menu, fonction `omtr_switcher()`.
- JS vanilla ≤ 3 Ko, CSS ≤ 4 Ko, chargés seulement si le sélecteur est présent. Vrais `<a href>` (fonctionne sans JS). `aria-expanded`, `aria-haspopup`, navigation clavier, focus visible.

## 9. Admin et assistant

**Assistant (3 écrans)** : langues → IA (lien de création de clé, saisie, « Tester la clé » obligatoire) → estimation et lancement + insertion du sélecteur dans le menu principal.

**Onglets** : Tableau de bord · Traductions · Glossaire · Réglages. Interface React (`@wordpress/components`), FR et EN.

**Export de configuration** JSON (langues, glossaire, style, SEO), **jamais la clé API**.

## 10. Confidentialité (RGPD)

- Seul le contenu de pages **publiques rendues en session anonyme** part vers l'IA.
- Jamais : pages compte/panier/commande, brouillons, contenus privés ou protégés, valeurs de formulaires, données de commande.
- Filtre PII avant envoi (emails, téléphones, IBAN, cartes) → segment exclu et signalé.
- Emails Woo traduits par gabarit, jamais avec les données client.
- Rien envoyé à Omnium. Seuls flux sortants autorisés : fournisseur IA choisi, GitHub Releases, `home_url()` (crawler).
- Pas de cookie par défaut (langue dans l'URL). Cookie de préférence optionnel, fonctionnel uniquement.
- Aucune ressource externe en front.
- `wp_add_privacy_policy_content()` avec fournisseur, nature des données, localisation. Localisation UE / hors UE affichée par fournisseur.
- Logs sans contenu de segment, clé, IP ni identifiant utilisateur.
- Désinstallation : conserver les traductions ou tout supprimer (tables, options, transients, tâches, clé).

## 11. Sécurité — règles obligatoires

**Toute réponse de l'IA est une entrée non fiable.**

| Risque | Parade |
| --- | --- |
| XSS via traduction IA | Réinsertion en nœuds texte ; balises inline whitelistées via `wp_kses` ; `esc_attr` sur les attributs |
| XSS via édition manuelle | Même sanitization quel que soit le rôle |
| Attaque par coût | File à la volée limitée aux URLs connues, query strings ignorées, quota horaire, plafond mensuel |
| Vol de clé API | Chiffrement ; jamais renvoyée au navigateur, en REST, export, log ou erreur |
| CSRF | Nonce sur chaque action, formulaire et appel REST |
| Élévation de privilèges | Capability `omtr_manage` partout ; `permission_callback` obligatoire sur chaque route REST |
| Injection SQL | `$wpdb->prepare()`, colonnes et tris en whitelist |
| SSRF | Crawler limité à `home_url()` via `wp_safe_remote_get()` |
| Open redirect | `wp_safe_redirect()` |
| Fichier malveillant | Import CSV en mémoire, ≤ 1 Mo, sanitizé ; neutralisation des formules à l'export |

**Chiffrement des clés** : `sodium_crypto_secretbox`, nonce aléatoire par chiffrement. Clé de chiffrement = constante `OMTR_ENCRYPTION_KEY` dans `wp-config.php` si définie (proposée dans l'assistant), sinon dérivée de `AUTH_KEY` + `SECURE_AUTH_SALT` via `hash_hkdf`. Affichage masqué (`sk-…a1B2`).

**Règles de code**
- Chaque fichier PHP commence par `defined('ABSPATH') || exit;`.
- Entrées : `wp_unslash` + `sanitize_*`, typage strict, schéma de validation sur les routes REST.
- Sorties : `esc_html`, `esc_attr`, `esc_url`, `wp_kses` au moment de l'affichage, admin compris.
- Interdits : `eval`, `assert`, `unserialize` sur données externes, `extract`, `$_REQUEST` brut, `file_get_contents` sur URL, `include` dynamique, `exec` et assimilés.
- Aucun endpoint public non authentifié en écriture, aucun `wp_ajax_nopriv_*`.
- Erreurs sans chemin serveur, clé ni réponse brute du fournisseur.
- `SECURITY.md` : procédure de signalement, correctif critique sous 7 jours.

## 12. Usage des skills (obligatoire)

- Après toute modification de `src/` (REST, admin, rendu, fournisseurs, clés, file) → lancer **`security-audit`** sur les fichiers modifiés.
- Après toute modification du crawler, des prompts, des logs, des exports, des cookies ou du front → lancer **`privacy-audit`**.
- Avant tout tag `v*` → lancer les deux sur l'ensemble du code.
- **Aucun tag de release sans les deux rapports du jour au verdict VALIDÉ** dans `audits/`.
- Un finding Critique ou Haute = correction immédiate, avant toute autre tâche.

Les skills ne remplacent pas un regard extérieur : avant la première mise en production chez un client, relecture par un second développeur ou scan Patchstack / WPScan.

## 13. Plan de développement

1. **Socle** : repo, CI, wp-env, bootstrap, migrations, crypto, capability, skills installés.
2. **Moteur** : routeur, output buffering, parseur, tables, fournisseur Claude, file, traduction d'une page.
3. **Multi-IA et contrôle** : 3 autres fournisseurs (OpenAI, Mistral, Gemini), test de clé, estimation, plafonds, déclencheurs, glossaire, éditeur.
4. **SEO et WooCommerce** : slugs, hreflang, canonical, sitemaps, JSON-LD, fragments, emails.
5. **Interface et livraison** : sélecteur, assistant, tableau de bord, export, audits validés, v1.0.0.

Chaque jalon est testé sur les trois sites de test avant de passer au suivant.

## 14. Critères de recette v1

- [ ] Installation + assistant < 5 minutes sur un site vierge.
- [ ] Sites Elementor + Woo et Oxygen + Woo de 30 pages traduits en EN et ES sans erreur visible.
- [ ] Panier, commande et emails Woo fonctionnels dans chaque langue.
- [ ] Retraduire un site inchangé = zéro appel IA.
- [ ] Une traduction corrigée manuellement n'est jamais écrasée.
- [ ] Hreflang validé par un outil externe, sitemaps par langue présents.
- [ ] < 50 ms de surcoût sur une page traduite en cache.
- [ ] Aucune requête tierce dans l'onglet réseau d'une page front.
- [ ] `security-audit` et `privacy-audit` VALIDÉ.
- [ ] Désactivation : site intact. Désinstallation complète : aucune trace en base.

## 15. Conventions

- Code et commentaires techniques en anglais ; interface, docs et commits en français.
- PSR-4 sous `src/`, une classe par fichier, injection de dépendances via le container.
- Toute chaîne d'interface traduisible (`__()`, text domain `omnium-translate`).
- Pas de nouvelle dépendance Composer ou npm sans justification dans la PR.
- Toute fonctionnalité livrée avec ses tests.

---

## Annexe A — `.claude/skills/security-audit/SKILL.md`

~~~~markdown
---
name: security-audit
description: Audit de sécurité du plugin WordPress Omnium Translate. À utiliser avant chaque merge sur main, avant chaque release (tag v*), et dès qu'un fichier touchant aux routes REST, à l'admin, au rendu front, aux fournisseurs IA, au stockage des clés ou à la file de traduction est modifié.
---

# Audit de sécurité — Omnium Translate

Tu es auditeur sécurité WordPress. Ton objectif : garantir que ce plugin ne devienne jamais une porte d'entrée sur les sites clients. Tu ne valides rien « à l'œil » : chaque point est vérifié dans le code et tracé dans le rapport.

## Périmètre

- Par défaut : les fichiers modifiés depuis `main` (`git diff --name-only main...HEAD`).
- Avant une release : tout `src/`, `omnium-translate.php`, `uninstall.php`, `assets/` (JS source).

## Étape 1 — Contrôles automatiques

Exécute et reporte le résultat de chaque commande :

```bash
vendor/bin/phpcs --standard=phpcs.xml.dist src/ omnium-translate.php uninstall.php
vendor/bin/phpstan analyse --level=6
vendor/bin/phpunit --group=security
composer audit
npm audit --audit-level=high
gitleaks detect --no-banner
```

Une commande en échec = audit en échec. Ne contourne jamais un avertissement par `phpcs:ignore` sans justification écrite dans le code et dans le rapport.

## Étape 2 — Recherche de motifs dangereux

Recherche dans `src/` et signale chaque occurrence avec fichier:ligne :

- Fonctions interdites : `eval(`, `assert(`, `create_function`, `unserialize(`, `extract(`, `exec(`, `shell_exec(`, `system(`, `passthru(`, `proc_open(`, `popen(`, `` ` `` (backticks).
- `file_get_contents(` ou `fopen(` sur une URL ; `include`/`require` avec une variable.
- `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, `$_SERVER` utilisés sans `wp_unslash` + `sanitize_*`.
- `$wpdb->query(`, `get_results(`, `get_var(`, `get_row(` sans `$wpdb->prepare()`.
- `echo` / `print` / `printf` d'une variable sans `esc_html`, `esc_attr`, `esc_url` ou `wp_kses`.
- `wp_redirect(` (doit être `wp_safe_redirect`), `wp_remote_get(` vers une URL non codée en dur (doit être `wp_safe_remote_get` limité à `home_url()` pour le crawler).
- Fichiers PHP sans `defined('ABSPATH') || exit;` en tête.

## Étape 3 — Revue manuelle des points critiques

Pour chaque point, réponds OK / KO avec la preuve (fichier:ligne).

### 3.1 Réponses de l'IA = entrées non fiables
- [ ] Les traductions sont réinsérées dans le DOM comme nœuds texte, jamais via `innerHTML` côté serveur ou client.
- [ ] Les balises inline autorisées passent par une whitelist `wp_kses` stricte (`strong`, `em`, `b`, `i`, `br`, `span`, `a[href]` avec `esc_url`). Aucun attribut `on*`, `style`, `src` en `javascript:` ou `data:`.
- [ ] Les attributs traduits (`alt`, `title`, `placeholder`, `aria-label`, meta) sont échappés avec `esc_attr`.
- [ ] Une réponse IA non conforme au JSON attendu est rejetée, jamais affichée.
- [ ] Le prompt délimite le contenu comme donnée et ordonne d'ignorer toute instruction qu'il contient.
- [ ] Un test existe avec une réponse IA contenant `<script>`, `<img onerror>`, `javascript:` et une tentative d'injection de prompt.

### 3.2 Contrôle d'accès
- [ ] Chaque route REST a un `permission_callback` qui vérifie `current_user_can('omtr_manage')`. Aucun `__return_true` sur une route qui écrit ou lit des données admin.
- [ ] Chaque action admin (`admin_post_*`, `wp_ajax_*`, formulaires) vérifie capability ET nonce (`check_admin_referer` / `check_ajax_referer` / `wp_verify_nonce`).
- [ ] Aucun `wp_ajax_nopriv_*` ni route publique en écriture.
- [ ] L'édition manuelle d'une traduction applique la même sanitization quel que soit le rôle.

### 3.3 Clés API
- [ ] Stockage chiffré via `sodium_crypto_secretbox` avec nonce aléatoire par chiffrement.
- [ ] La clé déchiffrée n'est jamais renvoyée au navigateur, dans une réponse REST, un export, un log ou un message d'erreur. Seul un masque est affiché.
- [ ] Les erreurs fournisseur sont résumées (code HTTP + message générique), jamais la réponse brute.

### 3.4 Abus et coûts
- [ ] La file « à la volée » n'accepte que des URLs de contenus publiés connus ; les query strings sont ignorées.
- [ ] Quota horaire et plafond mensuel appliqués côté serveur avant toute mise en file.
- [ ] Verrou empêchant deux traductions complètes simultanées.
- [ ] Aucun appel IA déclenché pendant la requête d'un visiteur.

### 3.5 Réseau et redirections
- [ ] Endpoints des fournisseurs codés en dur, HTTPS, `sslverify` jamais désactivé.
- [ ] Crawler limité au domaine `home_url()`.
- [ ] Redirections (routeur, 301 de slugs, sélecteur) via `wp_safe_redirect` ou URLs construites à partir de `home_url()`.

### 3.6 Fichiers et imports
- [ ] Import CSV : lecture en mémoire, taille ≤ 1 Mo, type vérifié, valeurs sanitizées, rien écrit sur disque.
- [ ] Export CSV : cellules commençant par `=`, `+`, `-`, `@` préfixées d'une apostrophe.
- [ ] L'export de configuration JSON n'inclut jamais la clé API.

### 3.7 Base de données et désinstallation
- [ ] Noms de colonnes et ordres de tri dynamiques passés en whitelist.
- [ ] `uninstall.php` vérifie `WP_UNINSTALL_PLUGIN` et supprime tables, options, transients, tâches Action Scheduler et clé si l'utilisateur l'a choisi.

## Étape 4 — Rapport

Produis `audits/security-YYYY-MM-DD.md` :

```markdown
# Audit sécurité — vX.Y.Z — YYYY-MM-DD
Verdict : ✅ VALIDÉ / ❌ BLOQUANT

## Contrôles automatiques
| Outil | Résultat |

## Findings
| Sévérité | Fichier:ligne | Problème | Correctif proposé |

## Checklist manuelle
(points 3.1 à 3.7 avec OK/KO et preuve)
```

Sévérités : **Critique** (exploitable à distance sans compte, fuite de clé, XSS stockée), **Haute** (exploitable avec un compte bas privilège, attaque par coût), **Moyenne**, **Basse**.

Règle de décision : un finding Critique ou Haute = verdict BLOQUANT, pas de release. Propose le correctif sous forme de diff, puis relance l'audit sur les fichiers corrigés.
~~~~

## Annexe B — `.claude/skills/privacy-audit/SKILL.md`

~~~~markdown
---
name: privacy-audit
description: Contrôle de confidentialité et RGPD du plugin WordPress Omnium Translate. À utiliser avant chaque release, et dès qu'un changement touche à ce qui est envoyé aux fournisseurs IA, au crawler, aux logs, aux exports, aux cookies, aux ressources chargées en front ou à la désinstallation.
---

# Audit de confidentialité — Omnium Translate

Tu es responsable de la confidentialité du plugin. Règle d'or : seul le contenu **public** du site peut partir vers le fournisseur IA choisi par le client, et **rien** ne part vers Omnium. Chaque point est vérifié dans le code et tracé dans le rapport.

## Étape 1 — Cartographie des flux sortants

Liste TOUS les appels réseau du plugin :

```bash
grep -rnE "wp_remote_(get|post|request)|wp_safe_remote_|curl_|fsockopen|file_get_contents\(\s*['\"]https?" src/
grep -rnE "https?://" src/ assets/src/ --include=*.php --include=*.js --include=*.ts --include=*.css
```

Produis un tableau : destination · fichier:ligne · données envoyées · justification. Destinations autorisées uniquement :

| Destination | Données autorisées |
| --- | --- |
| API du fournisseur IA sélectionné | Segments de texte public, langues, glossaire, ton du site |
| GitHub Releases (repo du plugin) | Aucune donnée du site (simple requête de version) |
| `home_url()` (crawler interne) | Requête anonyme sans cookie de session |

Toute autre destination = finding **Critique**. Aucune URL tierce en front (CDN, polices Google, drapeaux distants, analytics).

## Étape 2 — Ce qui part vers l'IA

- [ ] Le crawler rend les pages **sans cookie d'authentification ni de session WooCommerce** (vérifier les arguments `cookies` et headers de la requête).
- [ ] Sont exclus du crawl et de la traduction à la volée : pages compte, panier, commande, `order-received`, pages protégées par mot de passe, brouillons, contenus privés, prévisualisations, admin, REST, AJAX (hors fragments Woo traduits par gabarit).
- [ ] La traduction à la volée ne s'exécute **jamais** pour un utilisateur connecté (son rendu peut contenir ses données).
- [ ] Le filtre PII avant envoi détecte et exclut : emails, numéros de téléphone FR/internationaux, IBAN, numéros de carte (Luhn). Test unitaire présent pour chaque motif.
- [ ] Les valeurs de champs de formulaire (`value` des `input`, `textarea`) ne sont jamais extraites comme segments ; seuls `placeholder` et `label` le sont.
- [ ] Les emails transactionnels Woo sont traduits par gabarit (chaînes avec variables), jamais avec le contenu réel d'une commande.
- [ ] Le prompt ne contient ni URL d'admin, ni nom d'utilisateur, ni donnée de configuration autre que langues, glossaire et ton.

## Étape 3 — Stockage et journaux

- [ ] La table `log` ne contient ni texte de segment complet, ni clé API, ni IP, ni identifiant utilisateur. Contexte = identifiants techniques (job id, code HTTP, fournisseur).
- [ ] Purge automatique des logs après 30 jours (tâche planifiée vérifiée).
- [ ] Les erreurs du fournisseur ne sont pas stockées brutes (elles peuvent renvoyer le texte envoyé).
- [ ] Aucune donnée de visiteur stockée par le plugin (pas d'IP, pas de user-agent, pas de statistiques).
- [ ] L'export de configuration JSON et l'export CSV du glossaire n'incluent jamais la clé API.

## Étape 4 — Côté visiteurs

- [ ] Aucun cookie posé par défaut. Le cookie de préférence de langue (si activé) est strictement fonctionnel, `SameSite=Lax`, `Secure`, durée ≤ 12 mois, sans identifiant unique.
- [ ] Aucun `localStorage` / `sessionStorage` contenant autre chose que la langue choisie.
- [ ] Drapeaux SVG et CSS/JS du sélecteur servis depuis le plugin ; aucune requête tierce dans l'onglet réseau d'une page front (vérification manuelle sur le site de test).
- [ ] Pas de redirection automatique selon la langue du navigateur.

## Étape 5 — Transparence pour le client

- [ ] Texte de politique de confidentialité enregistré via `wp_add_privacy_policy_content()`, à jour avec la liste des fournisseurs et leur localisation.
- [ ] Avertissement visible dans les réglages pour les paliers gratuits (réutilisation possible des données par le fournisseur).
- [ ] Indication de localisation par fournisseur dans le sélecteur de fournisseur (UE / hors UE).
- [ ] `uninstall.php` propose la suppression complète : tables, options, transients, tâches planifiées, clé chiffrée. Vérifier qu'aucune option `omtr_*` ne subsiste après désinstallation sur le site de test.
- [ ] Aucune télémétrie, ping d'activation ou statistique d'usage envoyé à Omnium.

## Étape 6 — Rapport

Produis `audits/privacy-YYYY-MM-DD.md` :

```markdown
# Audit confidentialité — vX.Y.Z — YYYY-MM-DD
Verdict : ✅ VALIDÉ / ❌ BLOQUANT

## Flux sortants
| Destination | Fichier:ligne | Données | Conforme |

## Findings
| Sévérité | Fichier:ligne | Problème | Correctif proposé |

## Checklist
(étapes 2 à 5 avec OK/KO et preuve)
```

Sévérités : **Critique** (donnée personnelle envoyée à l'IA ou à un tiers, flux sortant non autorisé, clé API exposée), **Haute** (donnée personnelle stockée ou loguée, cookie non fonctionnel), **Moyenne** (transparence incomplète), **Basse**.

Un finding Critique ou Haute = verdict BLOQUANT, pas de release. Propose le correctif sous forme de diff, puis relance l'audit sur les fichiers corrigés.
~~~~
