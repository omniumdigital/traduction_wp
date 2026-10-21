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
