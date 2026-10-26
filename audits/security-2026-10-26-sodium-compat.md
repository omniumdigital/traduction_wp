# Audit sécurité — v1.0.2 — 2026-10-26

Verdict : ✅ VALIDÉ

Périmètre : fichiers modifiés depuis `v1.0.1` sur `fix/sodium-compat` : `omnium-translate.php`, `src/Security/Crypto.php`, `tests/Unit/CryptoTest.php`, `composer.json`, `composer.lock`, `languages/omnium-translate-fr_FR.po`, docs. Le reste du code est inchangé depuis `audits/security-2026-10-26.md` (VALIDÉ).

Contexte : sur un site client en PHP 8.3 sans l'extension `sodium`, l'extension refusait de démarrer. WordPress charge pourtant le polyfill `sodium_compat` (`wp-includes/compat.php:300-301`, avant les extensions) dès que l'extension native manque.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| `vendor/bin/phpcs` (config `phpcs.xml.dist`) | OK — 0 erreur |
| `vendor/bin/phpstan analyse --level=6` | OK — 0 erreur |
| `vendor/bin/phpunit --group=security` (PHP 8.1) | OK — 205 tests, 625 assertions |
| `vendor/bin/phpunit` (PHP 8.3) | OK — 353 tests, 1 060 assertions, 1 ignoré |
| `composer audit` | OK — aucun avis |
| `npm audit --audit-level=high` | OK — 0 vulnérabilité |
| `gitleaks detect --no-git` | Aucune nouvelle alerte (les 8 alertes connues restent limitées aux fichiers ignorés `env/oxygen/oxygen/`, `env/recette/tmp/`, `phpstan.phar`) |

Tests d'intégration ad hoc (non commités) :
- Image WordPress 6.9 avec `php -n` (sodium absente) : chiffrement/déchiffrement OK, payload altéré rejeté, payload écrit par l'extension native lu par le polyfill et inversement.
- wp-env, WP-CLI avec `PHP_INI_SCAN_DIR` sans `docker-php-ext-sodium.ini` : extension démarrée, `ApiKeyStore::set/get/masked` OK (option restaurée ensuite).
- Bootstrap simulé : dossier vendor absent, sodium et polyfill absents, PHP 8.0 : chaque cas affiche son message dédié.

## Recherche de motifs dangereux

Aucun nouvel usage de fonction interdite, superglobale, requête SQL, appel réseau ou redirection dans le diff. `omnium-translate.php` garde `defined( 'ABSPATH' ) || exit;`.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Basse (accepté) | `src/Security/Crypto.php:73-79` | Avec le polyfill, la clé dérivée n'est pas effacée de la mémoire de façon sûre (PHP pur ne le permet pas) : elle est seulement écrasée. | Risque résiduel accepté : la clé ne vit que pendant la requête, comme toute donnée PHP. L'extension native reste utilisée et effacée par `sodium_memzero()` quand elle est présente. |
| Basse (corrigé) | `omnium-translate.php:43-46` | Le nouveau message de prérequis affiche la version de PHP ; `admin_notices` est visible de tout utilisateur ayant accès à l'admin. | Appliqué : notice limitée à `current_user_can( 'activate_plugins' )`. |

## Checklist manuelle (points touchés)

### 3.3 Clés API — OK
- Même primitive dans les deux cas : `sodium_crypto_secretbox` (XSalsa20-Poly1305), nonce `random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES )` par chiffrement, clé HKDF-SHA256 inchangée (`src/Security/Crypto.php:32-39,106-118`). Le polyfill est `sodium_compat` de Paragon IE, embarqué par WordPress (`polyfill-1.0.8` en WP 6.9), utilisé seulement si l'extension native manque.
- Format `v1:` inchangé ; compatibilité croisée prouvée par `tests/Unit/CryptoTest.php` (`test_decrypts_payload_written_by_sodium_compat`, fixture chiffrée avec les constantes de test, pas un secret réel).
- La clé déchiffrée ne sort toujours que vers les fournisseurs ; aucun changement d'exposition.
- Le refus de démarrer reste en place si aucune implémentation de `sodium_crypto_secretbox` / `_open` n'existe (`omnium-translate.php:34`) : jamais de stockage en clair.

### Sorties et erreurs — OK
- Messages de prérequis échappés avec `esc_html()`, construits dans le callback `admin_notices` (après `init`), sans chemin serveur ni donnée sensible ; visibles des seuls administrateurs.

### Dépendances — OK
- `ext-sodium` passe de `require` à `suggest` dans `composer.json` ; `composer.lock` ne change que par son `content-hash` et sa section `platform`. Aucune nouvelle dépendance.

### Points 3.1, 3.2, 3.4 à 3.7
Non touchés par ce diff ; état validé dans `audits/security-2026-10-26.md`.
