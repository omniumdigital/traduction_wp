# AGENTS.md

La charte du projet est [`CLAUDE.md`](CLAUDE.md) : lis-la entièrement avant toute tâche et respecte-la strictement (sécurité section 11, confidentialité section 10, conventions section 15).

## Architecture (ne pas modifier sans accord)

- Bootstrap `omnium-translate.php` → `Core\Plugin::boot()` sur `plugins_loaded`.
- Un module par dossier de `src/`, implémentant `Core\ModuleInterface` : `register()` déclare les services dans le `Core\Container` (aucun effet de bord), `boot()` pose les hooks. L'ordre est fixé dans `Core\Plugin::modules()`.
- Contrats partagés (signatures figées, à implémenter, pas à changer) :
  - `Providers\ProviderInterface`, `Result`, `RateLimits`, `ProviderException`, `ProviderRegistry`
  - `Store\SegmentRepositoryInterface`, `Store\TranslationRepositoryInterface`
  - `Store\SegmentKey` : identité unique d'un segment, utilisée par Render, Queue et Store
  - `Router\CurrentLanguage` : langue de la requête
  - `Render\SegmentExtractorInterface`, `Queue\TranslationQueueInterface`, `Core\LoggerInterface`
  - `Glossary\Glossary`
  - `Security\Sanitizer` : passage obligatoire de toute traduction (IA ou manuelle)
  - `Security\ApiKeyStore` : seule porte d'accès à la clé API déchiffrée
- Schéma de base : `Core\Migrations` (incrémenter `DB_VERSION` pour toute modification). Noms de tables : `Core\Tables`. Options : `Core\Options`.
- Toute nouvelle option ou transient commence par `omtr_` ; toute méta par `_omtr_`.
- Style : WordPress Coding Standards (méthodes et variables en snake_case), une classe par fichier PSR-4, `defined( 'ABSPATH' ) || exit;` en tête.

## Vérifications avant de terminer

Sans PHP local, via Docker :

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 sh -c 'composer install -q && vendor/bin/phpcs && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/phpunit'
```

Les trois doivent passer. Toute fonctionnalité est livrée avec ses tests ; les tests de sécurité portent `@group security`.
Après modification de `src/`, appliquer la checklist `.claude/skills/security-audit/SKILL.md` aux fichiers modifiés ; si tu touches au crawler, prompts, logs, exports, cookies ou front, appliquer aussi `.claude/skills/privacy-audit/SKILL.md`. Écrire le rapport dans `audits/`.

## Git

Commits en français, Conventional Commits. Ne jamais commiter de clé API, de `.env`, ni de données client. Ne pas pousser, ne pas merger : le coordinateur s'en charge.
