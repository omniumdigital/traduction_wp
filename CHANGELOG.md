# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnage sémantique.

## [Non publié]

### Ajouté
- Backend de l’administration : assistant, réglages, fournisseurs, tableau de bord, traduction complète et import/export JSON sécurisé.
- Personnalisation du sélecteur et drapeaux alternatifs locaux sous licence MIT ; notice de confidentialité liée au fournisseur choisi.
- Socle : bootstrap, container, modules, migrations des tables, chiffrement des clés API, capability `omtr_manage`, CI, environnements wp-env.
- Outillage JS : esbuild + Biome à la place de @wordpress/scripts (npm audit à 0).
- Interface React d’administration : assistant trois écrans, tableau de bord, éditeur de traductions, glossaire, réglages, aperçu du sélecteur avec contrôle de contraste, estimation avant traduction complète et import/export de configuration.
- Interface admin découpée en modules, chaînes extractibles avec `@wordpress/i18n`, réglage SEO `noindex_partial`, choix des drapeaux, import/export CSV du glossaire et aperçu fidèle du sélecteur.
- Sélecteur de langue côté serveur : shortcode `[omtr_switcher]`, fonction `omtr_switcher()`, bloc Gutenberg, widget Elementor, élément de menu WordPress, variantes dropdown/inline/flottante, navigation clavier et ressources locales.
- Intégration Oxygen 6 : élément Element Studio avec variantes de disposition et modes d’affichage du sélecteur.

### Modifié
- WordPress 6.9 minimum (testé jusqu'à 7.1), Action Scheduler 4.2.
- Dépendance de développement `body-parser` mise à jour vers 1.20.9 après l'avis GHSA-27r3-jxmh-p828.

### Corrigé
- Traduction des champs texte JSON-LD Yoast, Rank Math et WooCommerce sur les pages localisées, y compris avec le parseur HTML de PHP 8.1 à 8.3 ; URL internes réécrites, prix et identifiants produit préservés, sortie JSON échappée.
- Liaison du contrat `TranslationQueueInterface` lorsque WooCommerce initialise la traduction des emails.
