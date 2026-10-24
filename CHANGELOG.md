# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnage sémantique.

## [Non publié]

### Ajouté
- Socle : bootstrap, container, modules, migrations des tables, chiffrement des clés API, capability `omtr_manage`, CI, environnements wp-env.
- Outillage JS : esbuild + Biome à la place de @wordpress/scripts (npm audit à 0).
- Interface React d’administration : assistant trois écrans, tableau de bord, éditeur de traductions, glossaire, réglages, aperçu du sélecteur avec contrôle de contraste, estimation avant traduction complète et import/export de configuration.
- Sélecteur de langue côté serveur : shortcode `[omtr_switcher]`, fonction `omtr_switcher()`, bloc Gutenberg, widget Elementor, élément de menu WordPress, variantes dropdown/inline/flottante, navigation clavier et ressources locales.

### Modifié
- WordPress 6.9 minimum (testé jusqu'à 7.1), Action Scheduler 4.2.
- Dépendance de développement `body-parser` mise à jour vers 1.20.9 après l'avis GHSA-27r3-jxmh-p828.

### Corrigé
- Liaison du contrat `TranslationQueueInterface` lorsque WooCommerce initialise la traduction des emails.
