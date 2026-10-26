# Omnium Translate

Omnium Translate est une extension WordPress de traduction de sites par IA. Les traductions sont stockées dans la base de données du site et les appels sont faits directement au fournisseur choisi avec la clé API du propriétaire du site. Aucun compte Omnium ni abonnement Omnium n'est requis et aucune donnée n'est envoyée à Omnium.

Le plugin traduit le HTML rendu, avec des intégrations pour Gutenberg, Elementor, Oxygen et WooCommerce. Les segments déjà traduits sont réutilisés ; les nouveaux travaux passent par une file traitée en arrière-plan.

## Prérequis

- WordPress 6.9 ou ultérieur (testé jusqu'à WordPress 7.1)
- PHP 8.1 ou ultérieur
- Aucune extension PHP à activer : les clés API sont chiffrées avec l'extension Sodium si elle est présente, sinon avec la bibliothèque sodium_compat fournie par WordPress
- WooCommerce 9.0 à 11.2 si vous souhaitez traduire un catalogue WooCommerce (facultatif)

## Installation et configuration

1. Téléchargez `omnium-translate.zip` depuis la [GitHub Release](https://github.com/omniumdigital/traduction_wp/releases) correspondant à la version souhaitée.
2. Dans WordPress, ouvrez **Extensions → Ajouter une extension → Téléverser une extension**, choisissez le ZIP et activez Omnium Translate.
3. Ouvrez **Omnium Translate** dans l'administration et suivez l'assistant : choisissez la langue source et les langues cibles, sélectionnez un fournisseur et un modèle, saisissez puis testez la clé, examinez l'estimation et lancez la traduction. L'assistant peut aussi ajouter le sélecteur au menu principal.

### Clé du fournisseur IA

Créez une clé chez le fournisseur que vous avez choisi, puis ajoutez-la dans l'assistant ou dans les réglages du plugin :

- [Anthropic / Claude](https://console.anthropic.com/settings/keys) — hors UE, pas de palier gratuit
- [OpenAI](https://platform.openai.com/api-keys) — hors UE, pas de palier gratuit
- [Mistral](https://console.mistral.ai/api-keys) — UE, palier gratuit à débit limité
- [Google Gemini](https://aistudio.google.com/apikey) — hors UE, palier gratuit à débit limité

Les conditions et la localisation peuvent évoluer : vérifiez-les sur le site du fournisseur. Avec un palier gratuit, le fournisseur peut réutiliser les données selon ses conditions ; l'interface affiche un avertissement correspondant.

La clé est chiffrée avec Sodium. Pour fournir une clé de chiffrement dédiée, définissez une constante secrète dans `wp-config.php`, avant la ligne de fin du fichier :

```php
define( 'OMTR_ENCRYPTION_KEY', 'remplacez-par-une-valeur-secrete-longue-et-aleatoire' );
```

Conservez cette valeur de façon sûre et ne la publiez pas. Si elle n'est pas définie, le plugin dérive la clé de chiffrement des sels WordPress `AUTH_KEY` et `SECURE_AUTH_SALT`.

## Traduire et corriger les pages

Dans l'éditeur WordPress, le panneau **« Traduire cette page »** met la page dans la file de traduction. La traduction est réalisée en arrière-plan. Le mode d'édition visuel en front peut être activé dans les réglages : un utilisateur autorisé peut alors corriger les segments directement sur la page traduite.

L'option de traduction à la volée ajoute à la file les segments manquants des pages publiques connues. Elle est limitée par un quota horaire, fixé à 100 segments par défaut et réglable dans l'administration.

## Ajouter le sélecteur de langue

Le sélecteur ne s'affiche que si au moins deux langues sont configurées. Il utilise la langue et la page courantes pour construire les liens.

- **Shortcode** : `[omtr_switcher]`. Les attributs facultatifs sont `variant="dropdown|inline|floating"` et `display="flag-name|flag|name|code"`. Exemple : `[omtr_switcher variant="dropdown" display="flag-name"]`. La position du sélecteur flottant et ses couleurs se règlent dans l'administration.
- **Bloc Gutenberg** : bloc « Omnium Translate — Language selector ».
- **Elementor** : widget « Omnium Translate ».
- **Oxygen 6** : élément Omnium Translate dans Oxygen Element Studio.
- **Menu WordPress** : dans **Apparence → Menus**, ajoutez l'élément « Language selector ».
- **Code PHP** : appelez `omtr_switcher()` dans un modèle de thème.

Les styles et le script du sélecteur sont chargés lorsqu'un sélecteur est rendu. Les drapeaux SVG sont inclus dans le plugin.

## SEO et caches

Le plugin traduit les champs textuels JSON-LD et réécrit les URL internes, tout en préservant les prix et identifiants. Les versions linguistiques utilisent un préfixe d'URL (`/en/`, `/es/`, etc.) ; la langue source n'a pas de préfixe.

Après la mise à jour de traductions, le plugin purge les caches de page WP Rocket et LiteSpeed lorsqu'ils sont actifs, ainsi que son cache de contenu. Les intégrations peuvent également écouter le hook `omtr_translations_updated($post_id, $lang)`. Configurez votre cache de page pour distinguer les URL complètes, préfixe de langue compris.

## WP-CLI

Les commandes requièrent WP-CLI et un utilisateur autorisé à gérer le plugin (souvent `--user=admin`). Elles ne traduisent pas pendant la commande : elles mettent le travail en file, traité ensuite par Action Scheduler.

```bash
wp omtr status --user=admin
wp omtr pause --user=admin
wp omtr resume --user=admin
wp omtr estimate 42 --user=admin
wp omtr estimate --all --user=admin
wp omtr translate 42 --user=admin
wp omtr translate --all --yes --user=admin
wp omtr crawl --user=admin
wp omtr key set claude --user=admin   # saisie masquée ou lecture depuis STDIN
wp omtr key status --user=admin       # affiche uniquement des clés masquées
wp omtr key test claude --user=admin
wp omtr key delete claude --user=admin
wp omtr slugs generate 42 --user=admin
wp omtr slugs generate --all --user=admin
wp omtr slugs set 42 en about-us --user=admin
wp omtr glossary list --user=admin
wp omtr glossary add "Omnium" en "Omnium" keep --user=admin
wp omtr glossary delete 1 --user=admin
wp omtr glossary import glossary.csv --user=admin
wp omtr glossary export --user=admin
wp omtr glossary affected 1 --user=admin
```

## Confidentialité

Le plugin envoie au fournisseur IA sélectionné des segments de contenu public du site, ainsi que les langues et, le cas échéant, le glossaire et le ton/contexte configurés. Le filtre PII écarte les segments détectés comme contenant des courriels, téléphones, IBAN ou numéros de carte. Le plugin n'envoie aucune donnée à Omnium. Les appels réseau de fonctionnement comprennent aussi la vérification des versions GitHub Releases et les requêtes anonymes du crawler vers le propre site.

Aucun cookie n'est posé par défaut : la langue est portée par l'URL. Un cookie fonctionnel de préférence peut être activé dans les réglages ; il mémorise la langue choisie et ne redirige qu'une arrivée externe sur l'accueil source sans préfixe de langue. Les paliers gratuits des fournisseurs peuvent avoir des conditions de réutilisation des données différentes ; consultez-les avant de les utiliser.

## Avant la mise en production

Testez la configuration avec une vraie clé API et vérifiez le rendu des pages traduites, les formulaires et les parcours de votre site. Avant la première mise en production chez un client, faites relire le plugin par un second développeur ou lancez un scan Patchstack ou WPScan.

## Désinstallation et mises à jour

La désactivation du plugin laisse les traductions stockées. À la désinstallation, la clé API chiffrée et les tâches planifiées sont supprimées. Les traductions et réglages sont conservés par défaut ; activez l'option de suppression complète dans les réglages avant de désinstaller pour supprimer également les tables, options, transients et métadonnées du plugin.

Les mises à jour sont recherchées sur les GitHub Releases publiques via Plugin Update Checker et le ZIP de release. Vous pouvez désactiver la vérification des mises à jour dans les réglages du plugin.

## Développement

L'environnement local utilise `wp-env` (Docker). La configuration principale se trouve dans `.wp-env.json` ; des configurations supplémentaires sont disponibles dans `env/` pour Elementor et Oxygen.

```bash
npm install
npm ci
npm run env:gutenberg
npm run env:elementor   # voir env/README.md pour les autres sites
npm run env:oxygen
npm run build
composer install
vendor/bin/phpunit && vendor/bin/phpstan analyse && vendor/bin/phpcs
```

Sans PHP local, les vérifications PHP peuvent être exécutées via Docker : `docker run --rm -v "$PWD":/app -w /app composer:2 sh -c 'composer install -q && vendor/bin/phpcs && vendor/bin/phpstan analyse --memory-limit=1G && vendor/bin/phpunit'`. `npm run env:stop` arrête les environnements. Le build utilise esbuild et génère les bundles JavaScript dans `assets/build/`.
