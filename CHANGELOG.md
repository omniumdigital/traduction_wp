# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnage sémantique.

## [Non publié]

### Ajouté
- Déclenchement de la traduction depuis l’éditeur de blocs (panneau « Traduire cette page ») et une méta-box équivalente dans l’éditeur classique.
- Édition visuelle privée des traductions en front (option `visual_editor`, désactivée par défaut) : bouton dans la barre d’admin, segments cliquables, enregistrement via l’API REST, réponse jamais mise en cache.
- Cookie de préférence de langue facultatif (option `pref_cookie`) : `omtr_lang` fonctionnel posé au clic, redirection 302 uniquement pour une arrivée externe sur l’accueil source sans préfixe ; mention ajoutée à la politique de confidentialité.
- Purge des caches de page après mise à jour des traductions (`clean_post_cache`, LiteSpeed, WP Rocket, hook `omtr_translations_updated`).
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
- Désactivation du plugin : l'élément de menu du sélecteur est mis en brouillon (jamais supprimé) pour éviter un lien mort « #omtr-switcher » tant que le plugin est inactif ; il est republié automatiquement à la réactivation.
- API REST des réglages : acceptation des paramètres de transport `_locale`, `_embed`, `_fields`, `_method`, `_envelope`, `_wpnonce` (échec de `PUT /settings` sinon).
