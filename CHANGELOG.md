# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnage sémantique.

## [Non publié]

## [1.0.0] - 2026-10-24

### Ajouté
- Traduction des pages depuis l’éditeur WordPress et correction visuelle facultative des traductions sur le site.
- Traduction en arrière-plan, glossaire, slugs traduits et sélecteur de langue intégré à WordPress, Gutenberg, Elementor et Oxygen.
- Prise en charge facultative de WooCommerce, des données structurées JSON-LD et des principaux plugins SEO.
- Quota horaire configurable pour la traduction à la volée (100 segments par défaut) et mémorisation facultative de la langue choisie.

### Modifié
- Compatibilité WordPress 6.9 ou ultérieur et PHP 8.1 ou ultérieur ; ajout de la purge des caches WP Rocket et LiteSpeed après mise à jour des traductions.

### Corrigé
- Fiabilisation de la traduction des données structurées, des pages localisées et de la conservation des traductions lors de la désactivation ou désinstallation.

### Sécurité
- Clés API chiffrées, contenu public filtré avant envoi au fournisseur et contrôles d’accès pour les actions de traduction et d’édition.
