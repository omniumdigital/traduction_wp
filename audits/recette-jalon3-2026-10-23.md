# Recette jalon 3 — 2026-10-23

Verdict : ✅ VALIDÉ — 0 KO sur les trois sites de test

## Périmètre et environnement

- Branche : `omniumdigital/feat-recette-jalon3`.
- WordPress/PHP : trois environnements `wp-env`, PHP 8.1, ports 8881 à 8883.
- Sites : Gutenberg seul ; Elementor + WooCommerce avec Yoast puis Rank Math ; Oxygen 6 + WooCommerce + Rank Math.
- Aucune clé API réelle n'a été utilisée.

Les environnements du dépôt principal ont d'abord été arrêtés, puis les trois sites ont été démarrés depuis ce worktree après `npm ci --ignore-scripts` et installation Composer. Les fixtures ont été recréées avec `env/elementor/seed.sh`, `env/oxygen/seed.sh` et une page Gutenberg publiée contenant texte, email, image avec alternative et lien interne.

## Commandes de recette

```bash
bash env/recette/jalon3.sh . 8881 4
bash env/recette/jalon3.sh env/elementor 8882 11
SEO=rankmath bash env/elementor/seed.sh
bash env/recette/jalon3.sh env/elementor 8882 11
bash env/recette/jalon3.sh env/oxygen 8883 10
```

## Bilan par site

| Site | SEO | Résultat final | Crawl consécutif |
| --- | --- | ---: | --- |
| Gutenberg, port 8881 | Natif | 49 OK, 0 KO | 3/3 pages inchangées |
| Elementor + WooCommerce, port 8882 | Yoast | 49 OK, 0 KO | 6/6 pages inchangées |
| Elementor + WooCommerce, port 8882 | Rank Math | 49 OK, 0 KO | 6/6 pages inchangées |
| Oxygen + WooCommerce, port 8883 | Rank Math | 49 OK, 0 KO | 5/5 pages inchangées |

Contrôles couverts : absence de fuite de clé, glossaire et CSV, proposition de retraduction avec `wp omtr glossary affected`, estimation pour Claude/OpenAI/Mistral/Gemini et coût inconnu, extraction anonyme et filtre PII, échec propre sans clé, pause/reprise, droits REST, tri neutralisé, XSS d'édition, statuts `edited`/`locked`, rafale à la volée sans query string, refus des visiteurs connectés et pages panier, et déclenchement automatique différé.

## Bugs trouvés et corrigés

1. Le script initial supposait les sorties GNU `sed`, capturait parfois le suffixe de `wp-env` dans les compteurs, et ne tenait pas compte du réseau du conteneur CLI. Le script est maintenant portable, nettoie les sorties, vérifie que le plugin est chargé et utilise temporairement `host.docker.internal` uniquement dans les commandes CLI.
2. Le faux cookie du contrôle « utilisateur connecté » ne prouvait rien. La recette ouvre maintenant une vraie session WordPress dans un cookie jar temporaire.
3. Le crawler hachait le HTML brut. WooCommerce produit un identifiant `quantity_*` aléatoire à chaque rendu, ce qui remettait une page produit inchangée en file. `Crawler` hache désormais les segments traduisibles normalisés et triés ; un test reproduit deux HTML aux identifiants techniques différents mais aux segments identiques.

## Validation automatisée

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 sh -c \
  'composer install -q && vendor/bin/phpcs && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/phpunit'
# PHPCS : 84/84 ; PHPStan : 0 erreur ; PHPUnit : 230 tests, 662 assertions, 1 sauté.

docker run --rm -v "$PWD":/app -w /app php:8.1-cli php vendor/bin/phpunit
# PHPUnit PHP 8.1 : 230 tests, 666 assertions, 0 échec.
```

## Limites

- Sans clé API réelle, la qualité d'une traduction fournisseur et le succès d'un appel réel restent à vérifier.
- La règle « retraduire un site inchangé = zéro appel IA » est validée jusqu'à la détection (deuxième crawl : zéro page remise en file) ; le compteur d'appels réels devra être confirmé avec une clé.

