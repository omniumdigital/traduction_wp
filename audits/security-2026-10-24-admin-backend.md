# Audit sécurité — backend administration — 2026-10-24

Verdict : ✅ VALIDÉ sur le périmètre modifié.

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS (`src/`, bootstrap, désinstallation) | ✅ 0 erreur et 0 avertissement |
| PHPStan niveau 6 | ✅ 0 erreur |
| PHPUnit `@group security` | ✅ 152 tests, 463 assertions |
| Composer audit | ✅ Aucune vulnérabilité |
| npm audit `high` | ✅ 0 vulnérabilité |
| Gitleaks | ✅ Scan des sources hors `vendor/` et `node_modules/` : aucune fuite. Le scan brut du répertoire de travail a trouvé 5 faux positifs `generic-api-key` dans le binaire tiers `vendor/phpstan/phpstan/phpstan.phar`. |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Aucun | — | Aucun finding sur les fichiers modifiés | — |

## Checklist manuelle

- **3.1 Traductions non fiables — OK.** Le nouveau code ne réinsère aucune traduction. Le chemin existant passe par `Security\Sanitizer` (`src/Security/Sanitizer.php:41`) ; les tests de rendu et de réponse fournisseur sont inclus dans la suite sécurité.
- **3.2 Contrôle d’accès — OK.** Toutes les nouvelles routes héritent de `Capabilities::rest_permission` (`src/Admin/Administration.php:115-175`), vérifié pour un utilisateur sans `omtr_manage` dans `tests/Unit/AdministrationSecurityTest.php`. Le nonce REST est fourni et contrôlé par WordPress ; aucune action AJAX ou route publique n’est ajoutée. La page utilise la même capability (`src/Admin/Administration.php:74`).
- **3.3 Clés API — OK.** `ApiKeyStore` chiffre les clés ; `/providers` n’expose que `masked()` (`src/Admin/Administration.php:220-245`). `/settings` et `/config/export` ne lisent pas les clés ; `Settings::all()` filtre les clés inconnues (`src/Core/Settings.php:68-82`). Les erreurs du test fournisseur sont génériques (`src/Admin/Administration.php:299-316`). Le test sécurité vérifie les quatre surfaces et les erreurs.
- **3.4 Coûts — OK.** `/translate/all` passe par `PublicContent::all()` puis `ActionSchedulerQueue::enqueue_urls()`, qui applique le verrou et le plafond (`src/Admin/Administration.php:361-372`, `src/Queue/ActionSchedulerQueue.php:114-168`). Aucun appel IA dans la requête visiteur.
- **3.5 Réseau et redirections — OK.** Aucun nouvel appel HTTP ni redirection ; les SVG sont servis localement (`src/Switcher/Switcher.php:237-247`). Les appels existants utilisent les endpoints HTTPS codés en dur et `wp_safe_remote_get()` pour le contenu public.
- **3.6 Imports et exports — OK.** L’import JSON refuse plus de 1 Mo avant décodage (`src/Admin/Administration.php:439-446`), valide le schéma et chaque champ (`src/Admin/Administration.php:518-731`). L’export ne contient que les réglages et le glossaire, jamais la clé (`src/Admin/Administration.php:416-435`). Aucun nouveau fichier importé n’est écrit sur disque.
- **3.7 Base et désinstallation — OK.** Les requêtes du tableau de bord utilisent `%i` dans `$wpdb->prepare()` (`src/Admin/Administration.php:319-345`). La nouvelle option `omtr_wizard_done` figure dans `Options::all()` et la purge existante de `uninstall.php` l’efface en mode purge.

Recherche de motifs dangereux : aucun nouvel `eval`, `unserialize`, `exec`, accès direct non validé à une superglobale, SQL dynamique non préparé, `wp_redirect`, ni sortie de paramètre REST non échappée dans les fichiers modifiés. Les seules ressources de la page admin sont le bundle local et les composants WordPress.
