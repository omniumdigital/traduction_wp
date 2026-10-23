# Recette Elementor, WooCommerce, Yoast SEO et Rank Math — 2026-10-23

Verdict : ✅ VALIDÉ

Environnement : WordPress 7.1.3, Elementor 4.3.5, WooCommerce 11.2.1, Yoast SEO 28.7 et Rank Math 1.0.280 sur `http://localhost:8882`.

## Préparation et matrice PHP

```bash
git rebase feat/moteur
docker run --rm -v "$PWD":/app -w /app php:8.1-cli php vendor/bin/phpunit
docker run --rm -v "$PWD":/app -w /app php:8.3-cli php vendor/bin/phpunit
docker run --rm -v "$PWD":/app -w /app composer:2 sh -c \
  'composer install -q && vendor/bin/phpcs && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/phpunit'
npm ci --ignore-scripts
cd env/elementor && npx wp-env start
bash env/elementor/seed.sh
```

- ✅ Rebase sur `feat/moteur` terminé sans conflit.
- ✅ PHP 8.1.34 : 143 tests, 367 assertions.
- ✅ PHP 8.3.36 : 143 tests, 367 assertions.
- ✅ Image Composer / PHP 8.5.11 : PHPCS 67/67 fichiers, PHPStan niveau 6 sans erreur, PHPUnit 143 tests et 363 assertions (un test conditionnel ignoré).
- ✅ `npm ci` : 395 paquets audités, aucune vulnérabilité.
- ✅ Seed idempotent : page Elementor 11, trois images locales avec textes alternatifs et produit WooCommerce 13.

## Routage, source et éditeur Elementor

- ✅ `/en/atelier-ceramique/` et `/es/atelier-ceramique/` répondent HTTP 200 avec respectivement `lang="en"` et `lang="es"`.
- ✅ Les liens internes absolus et relatifs vers la boutique sont réécrits sous `/en/` et `/es/`.
- ✅ La source est strictement identique avec et sans Omnium Translate : SHA-256 commun `8d0eae0fab99c36b023c845a4da4c1bd82a3572a0283a1c69a71a7da93924c85`.
- ✅ La comparaison inactive/active a modifié directement l'option `active_plugins`, sans déclencher les hooks d'activation d'Elementor qui régénèrent ses fichiers CSS et leurs cache-busters ; `cmp` ne relève alors aucun octet différent.
- ✅ L'éditeur authentifié `/wp-admin/post.php?post=11&action=elementor` répond HTTP 200 et charge l'interface Elementor (2 833 366 octets).
- ✅ La prévisualisation authentifiée `?elementor-preview=11&ver=1` répond HTTP 200 ; ni l'éditeur ni la prévisualisation ne reçoivent de lien préfixé `/en/` par le moteur.

## File, confidentialité et échec sans clé

Le conteneur CLI de wp-env ne peut pas joindre le site par `localhost`. Comme pour la recette Oxygen, le crawl est lancé avec un filtre de test strictement local :

```bash
wp omtr translate 11 --user=admin \
  --exec='WP_CLI::add_hook("after_wp_load", function(){ add_filter("home_url", function($url){ return str_replace("localhost", "host.docker.internal", $url); }); });'
wp action-scheduler run --hooks=omtr_scan_url,omtr_translate_batch --batches=20 --user=admin \
  --exec='WP_CLI::add_hook("after_wp_load", function(){ add_filter("home_url", function($url){ return str_replace("localhost", "host.docker.internal", $url); }); });'
```

- ✅ 46 segments humains extraits avec Yoast ; les deux segments SEO propres à Rank Math sont ajoutés lors du second passage.
- ✅ Courriel `contact@atelier-ceramique.example`, téléphone `+33 4 78 22 11 09` et valeur de formulaire `Jean Dupont` : 0 occurrence dans `wp_omtr_segments`.
- ✅ Placeholders `Entrez votre prénom` et `Écrivez votre message ici` : présents ; texte alternatif `Bol en grès émaillé bleu nuit` : présent.
- ✅ Sans clé Anthropic, les lots passent proprement au statut `failed` pour EN et ES ; le verrou est libéré.
- ✅ Les journaux ne contiennent que `Batch failed after 1 attempt(s) (http=0).` : aucun segment, aucune réponse fournisseur et aucune clé.
- ✅ La commande Action Scheduler filtrée par hooks est utilisée sous Rank Math : sa commande WP-CLI `--group=omtr` a signalé à tort un groupe absent alors que la table et `as_get_scheduled_actions()` contenaient bien le groupe 4 ; le traitement normal des actions et le code du plugin ne sont pas affectés.

## Sécurité du rendu

Trois traductions `edited` sont enregistrées via `TranslationRepository::save_edit()` avec des charges `<script>` et `<img onerror>` dans un titre, un alt et un placeholder.

- ✅ Aucun script injecté, aucune image injectée et aucun attribut `onerror` n'apparaissent dans le HTML rendu.
- ✅ `alert(91)`, `alert(92)` et `alert(93)` subsistent uniquement comme texte neutre.
- ✅ La balise autorisée `<strong>` est conservée dans le titre ; les attributs alt et placeholder restent du texte simple échappé.

## Yoast SEO

Configuration : Yoast actif, Rank Math désactivé, puis seed par défaut.

- ✅ Title, meta description, `og:title`, `og:description`, `og:url` et Twitter Card sont présents sur `/en/atelier-ceramique/`.
- ✅ Les traductions `edited` de title et description sont injectées dans title, description et Open Graph : `Handmade pottery in Lyon | unique pieces` et `Handmade stoneware bowls, vases and plates from our Lyon workshop.`
- ✅ Le bloc JSON-LD Yoast est conservé et accepté par `json_decode()`.

## Rank Math

```bash
SEO=rankmath bash env/elementor/seed.sh
```

Configuration : Yoast désactivé, Rank Math actif, assistant local marqué terminé et module OpenGraph actif.

- ✅ Title, meta description, `og:title`, `og:description`, `og:url`, `twitter:title` et `twitter:description` sont présents.
- ✅ Les traductions `edited` sont injectées dans toutes les variantes textuelles : `Lyon handmade ceramics | stoneware workshop` et `Discover handmade wood-fired stoneware from our family workshop in Lyon.`
- ✅ Le bloc JSON-LD Rank Math reste valide selon `json_decode()`.

## WooCommerce

- ✅ Panier vide : `/en/cart/` répond HTTP 200.
- ✅ Après ajout du produit 13 dans une session anonyme, `/en/cart/` et `/en/checkout/` répondent HTTP 200 ; la commande contient le composant `woocommerce-checkout`.
- ✅ `/en/?wc-ajax=get_refreshed_fragments` répond HTTP 200 en JSON, avec `widget_shopping_cart_content`, le produit et un `cart_hash`.
- ✅ Les pages personnalisées panier/commande sont rendues mais ne sont jamais crawlées ni envoyées au fournisseur IA.

## Performance

Mesure corrigée par le coordinateur le 2026-10-23. La première série comparait `/en/` plugin actif à `/en/` plugin inactif ; or, plugin inactif, cette URL redirige vers la page source et ajoute le coût d'une redirection, d'où un surcoût négatif (−27,56 ms) non significatif.

Méthode : 3 requêtes de chauffe puis 30 requêtes mesurées par cas (`curl -s -o /dev/null -w '%{time_total}'`), cache objet WordPress non persistant (aucun Redis/Memcached), même page `atelier-ceramique`.

| Cas | Médiane | Moyenne |
| --- | ---: | ---: |
| Plugin inactif, page source `/atelier-ceramique/` | 82,9 ms | 83,6 ms |
| Plugin actif, page source `/atelier-ceramique/` | 81,6 ms | 96,2 ms |
| Plugin actif, page traduite `/en/atelier-ceramique/` | 89,1 ms | 105,4 ms |

- Surcoût de la page traduite par rapport à la page source sans plugin : **+6,2 ms en médiane**, +21,8 ms en moyenne (quelques requêtes lentes tirent la moyenne vers le haut).
- ✅ Objectif de la charte `< 50 ms` respecté sur cette série. À remesurer avec un cache objet persistant et un cache de page (WP Rocket, LiteSpeed) au jalon 4.

## Conclusion

La recette Elementor est validée avec Yoast puis Rank Math. Aucun bug produit nécessitant une modification de `src/` n'a été trouvé pendant cette reprise ; les particularités relevées concernent uniquement le réseau interne wp-env et le filtre `--group` de la commande WP-CLI Action Scheduler sous Rank Math, tous deux contournés dans les commandes de recette sans modifier le plugin.
