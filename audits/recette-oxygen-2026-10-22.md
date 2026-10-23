# Recette Oxygen 6, WooCommerce et Rank Math — 2026-10-22

Verdict : ✅ VALIDÉ

Environnement : WordPress 7.1.3, PHP 8.1, Oxygen 6.1.3, WooCommerce 11.2.1 et Rank Math 1.0.280 sur `http://localhost:8883`.

## Préparation reproductible

```bash
npm ci --ignore-scripts
npm run env:oxygen
./env/oxygen/seed.sh
```

Résultat : installation npm sans vulnérabilité et création idempotente d'une page Oxygen, d'un template global à zone de contenu, de trois images locales, d'un produit simple et des réglages `omtr_settings` (`fr` vers `en` et `es`). Le script utilise les métas Oxygen 6 `_oxygen_data` et `_oxygen_template_settings`; aucun fichier du plugin Oxygen n'est modifié ou suivi par Git.

## Page Oxygen et builder

- ✅ Page construite dans l'arbre Oxygen, avec en-tête/menu, hero, trois cartes illustrées et leurs textes alternatifs, FAQ, formulaire, email/téléphone et pied de page.
- ✅ Ouverture de `/?oxygen=builder&id=<page_id>` dans le navigateur Orca : barre Oxygen, bouton Save, iframe et tous les éléments visibles, dont le formulaire `HtmlCode`; aucune erreur d'élément et aucun buffer de traduction dans l'éditeur.
- ✅ Le format requis par Oxygen a été confirmé : propriétés racine encodées en objet et statut d'arbre `exported`.

## Routage et invariance de la source

```bash
curl -sS http://localhost:8883/en/atelier-oxygen/
curl -sS http://localhost:8883/es/atelier-oxygen/
```

- ✅ Les deux réponses sont HTTP 200 avec respectivement `lang="en"` et `lang="es"`.
- ✅ Quatre liens internes par page sont préfixés (`/en/…` ou `/es/…`), ancres comprises.
- ✅ Après échauffement, comparaison active/inactive avec `cmp` : strictement identique, SHA-256 commun `9b75dc086ad59ac649029c20708f09d13ed90e000701de40a35285c448857959`.

## File de traduction et confidentialité

Le conteneur CLI de wp-env ne résout pas le site via `localhost`; la recette emploie donc le filtre local suivant, uniquement pour le test :

```bash
wp omtr translate <page_id> --user=admin \
  --exec='WP_CLI::add_hook("after_wp_load", function(){ add_filter("home_url", function($url){ return str_replace("localhost", "host.docker.internal", $url); }); });'
wp action-scheduler run --group=omtr --batches=20 --user=admin \
  --exec='WP_CLI::add_hook("after_wp_load", function(){ add_filter("home_url", function($url){ return str_replace("localhost", "host.docker.internal", $url); }); });'
```

- ✅ Scan terminé et quatre lots traités ; sans clé, les traductions passent en `failed` avec une erreur générique, sans secret ni contenu dans le message.
- ✅ Email, téléphone, valeur de l'input et contenu du textarea : 0 segment.
- ✅ Placeholders `Votre nom complet` et `Décrivez votre projet` : présents.
- ⏭️ `OMTR_TEST_ANTHROPIC_KEY` était absente du shell : traduction réelle et contrôle « deuxième exécution sans nouvel appel IA » non testés.

## Sécurité du rendu

Trois traductions `edited` ont été injectées avec `<script>alert(9x)</script><img src=x onerror=alert(9x)>` dans un titre, un alt et un placeholder.

- ✅ Aucun élément `<script>` ni attribut `onerror` malveillant dans `/en/atelier-oxygen/`.
- ✅ Les trois textes `alert(91)`, `alert(92)` et `alert(93)` subsistent comme texte neutre, preuve du passage par `Security\Sanitizer`.

## Rank Math

- ✅ Fixture configurée sans connexion distante (`rank_math_registration_skip`) afin d'activer le frontend local.
- ✅ `/en/atelier-oxygen/` contient le `title`, la meta description, `og:title`, `og:description` et `og:url` attendus, avec échappement HTML valide.

## WooCommerce

- ✅ Produit simple publié : `Carnet Horizon`, 24,00, en stock.
- ✅ Mode « coming soon » désactivé par la fixture ; `/cart/`, `/en/cart/` et `/checkout/` répondent HTTP 200.
- ✅ Après `/?add-to-cart=<product_id>`, l'API Store du panier retourne un article `Carnet Horizon`; le bloc Checkout est présent et charge correctement.

## Anomalies trouvées et corrigées

1. Le placeholder d'un `textarea` était perdu avec sa valeur : les attributs publics sont maintenant extraits avant d'exclure les descendants privés, avec test unitaire.
2. La copie embarquée d'Action Scheduler pouvait être sélectionnée avant celle de WooCommerce : chargement différé à `plugins_loaded` priorité `-100`, seulement si aucune version n'est déjà enregistrée, avec test unitaire.
3. La fixture Oxygen nécessitait la casse `HtmlCode`, un objet vide pour les propriétés racine et le statut `exported`; la fixture finale est validée dans le builder.

Le chargeur a aussi été testé en intégration : sans WooCommerce, la classe `ActionScheduler` du plugin est disponible ; avec WooCommerce actif, la réflexion pointe sur `woocommerce.latest-stable/packages/action-scheduler`, donc la version de WooCommerce est bien prioritaire.

## Vérifications finales

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 sh -c \
  'composer install -q && vendor/bin/phpcs && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/phpunit'
npm run lint
npm run build
npm audit --audit-level=high
docker run --rm -v "$PWD":/app -w /app composer:2 composer audit --no-interaction
```

- ✅ PHPCS : 67/67 fichiers.
- ✅ PHPStan niveau 6 : aucune erreur.
- ✅ PHPUnit : 140 tests, 343 assertions.
- ✅ Biome et build esbuild : succès.
- ✅ npm audit et Composer audit : aucune vulnérabilité.
- ✅ gitleaks : 30 commits, aucune fuite.
