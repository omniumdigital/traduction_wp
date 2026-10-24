# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnage sémantique.

## [Non publié]

## [1.0.0] - 2026-10-24

Première version publique.

### Ajouté
- Traduction automatique du HTML rendu (Gutenberg, Elementor, Oxygen 6), indépendante du constructeur de pages, avec réutilisation des segments déjà traduits : un site inchangé ne déclenche aucun nouvel appel IA.
- Quatre fournisseurs IA avec la clé du client : Anthropic (Claude), OpenAI, Mistral (point d'accès UE) et Google Gemini ; test de clé, choix du modèle, estimation des segments, mots et coût avant lancement.
- File de traduction en arrière-plan (Action Scheduler) : lots, reprises avec délais progressifs, pause et reprise, verrou d'exécution, plafond mensuel de mots et quota horaire pour la traduction à la volée (100 segments par défaut).
- Déclencheurs : bouton « Traduire cette page » dans l'éditeur de blocs et l'éditeur classique, action rapide dans les listes, barre d'admin, crawler des pages nouvelles ou modifiées, traduction de tout le site, traduction automatique à la publication et à la volée (options).
- Glossaire (« ne jamais traduire », « toujours traduire par »), ton et contexte du site, import et export CSV.
- Éditeur de segments dans l'admin (statuts modifié et verrouillé jamais écrasés) et mode d'édition visuel en front (option).
- SEO : préfixes de langue, slugs traduits avec redirections 301, `hreflang` et `x-default`, canonical, `<html lang>`, balises meta, Open Graph et Twitter traduites, JSON-LD traduit (prix et SKU intacts), sitemaps WordPress, Yoast SEO et Rank Math, `noindex` des langues incomplètes (option).
- WooCommerce : fiches, catégories, attributs, fragments du panier, pages panier, commande et compte préfixées, emails dans la langue de la commande.
- Sélecteur de langue (dropdown, pastilles, flottant) : shortcode `[omtr_switcher]`, fonction `omtr_switcher()`, bloc Gutenberg, widget Elementor, élément Oxygen, élément de menu ; drapeaux SVG embarqués et réglables par langue, style personnalisable avec aperçu en direct et contrôle du contraste.
- Administration React : assistant en 3 écrans, tableau de bord, traductions, glossaire, réglages, export et import de configuration (sans clé API). Interface en français et en anglais.
- Cookie de préférence de langue (option, désactivé par défaut) et purge des caches WP Rocket, LiteSpeed et WordPress après mise à jour des traductions.
- Mises à jour depuis les GitHub Releases (désactivables) et désinstallation au choix : conserver les traductions ou tout supprimer.

### Sécurité
- Clés API chiffrées (libsodium), jamais renvoyées au navigateur, dans un export, un journal ou une erreur.
- Réponses de l'IA traitées comme non fiables : JSON strict validé, réinsertion en texte, balises inline en liste blanche.
- Capability `omtr_manage` et nonce sur chaque action et route REST ; aucune route publique en écriture.
- Filtre des données personnelles (emails, téléphones, IBAN, cartes) avant tout envoi ; seules les pages publiques rendues en session anonyme partent vers l'IA ; aucune donnée envoyée à Omnium.
- Protection contre les attaques par coût : traduction à la volée limitée aux contenus publiés connus, query strings ignorées, quota horaire réservé avant mise en file.
