# Omnium Translate

Extension WordPress de traduction automatique par IA, sans abonnement : tout tourne sur le site du client, avec sa propre clé API.

La charte du projet (architecture, sécurité, confidentialité, plan) est dans [`CLAUDE.md`](CLAUDE.md).

## Développement

```bash
composer install
npm ci
npm run env:gutenberg   # voir env/README.md pour les autres sites
vendor/bin/phpunit && vendor/bin/phpstan analyse && vendor/bin/phpcs
```

Sans PHP local : `docker run --rm -v "$PWD":/app -w /app composer:2 vendor/bin/phpunit`.
