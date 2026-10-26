# Changelog

Format : [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versionnage sémantique.

## [Non publié]

## [1.1.2] - 2026-10-26

### Corrigé
- Rejet HTTP 400 non reconnu (« Request rejected by the provider ») : le code d’erreur machine du fournisseur (`type`/`code`/`status`, identifiant uniquement, jamais le message) est ajouté au journal, au bandeau d’échec du tableau de bord et au résultat du test de clé.
- Nouveaux motifs reconnus : crédit ou quota épuisé (Anthropic répond 400 quand le crédit est insuffisant), clé Gemini invalide (HTTP 400), localisation du serveur non desservie.
- Test de clé Anthropic : 16 tokens demandés au lieu de 1, comme les autres fournisseurs.

### Ajouté
- Bouton « Tester la clé » dans l’onglet Réglages (il n’existait que dans l’assistant).

## [1.1.1] - 2026-10-26

### Corrigé
- Erreurs de lots enfin explicites : les réponses d’erreur des fournisseurs sont classifiées en motifs fixes et lisibles (taille dépassée, modèle inconnu, format refusé, clé refusée), repris dans le journal et le bandeau d’échec du tableau de bord — le message brut du fournisseur, non fiable, n’est jamais stocké ni affiché.
- Un lot rejeté comme trop grand (HTTP 400) est scindé en deux moitiés réessayées automatiquement jusqu’à isolation du segment responsable : ses voisins ne sont plus condamnés à échouer à chaque relance manuelle.
- Un segment plus long qu’une requête unique est écarté avant l’appel au fournisseur, marqué en échec avec un motif explicite et décompté correctement.
- Verrou de traduction auto-réparé : un run interrompu brutalement (erreur fatale) ne bloque plus les lancements suivants pendant trois heures ; le verrou est libéré dès qu’aucun travail ne reste en file.
- Sous-espaces de noms : `ErrorClassifier` correctement importé dans les quatre fournisseurs (une référence non qualifiée provoquait une erreur fatale sur les lots en échec HTTP).

### Ajouté
- `Providers\ErrorClassifier` : classification locale des erreurs fournisseurs, avec tests unitaires couvrant chaque motif et l’absence de fuite du corps de réponse.

## [1.1.0] - 2026-10-26

### Ajouté
- Suivi de la traduction en direct : le tableau de bord se rafraîchit automatiquement (5 s) pendant qu’une traduction est en file, avec point d’activité, nombre de tâches et de lots en attente et horodatage de la dernière activité.
- Barres de progression de couverture par langue sur le tableau de bord (avec libellé accessible).
- Bloc « échecs » du tableau de bord : nombre de segments en échec et dernière erreur connue (message technique du journal).
- Route REST `POST /omtr/v1/translations/retry` : relance exactement les paires (segment, langue) en échec, par langue cible, sans jamais renvoyer un segment déjà traduit ; refusée en 409 avec la raison tant que le fournisseur actif n’a pas de clé.
- Bouton « Relancer les segments en échec » dans le bandeau d’échec du tableau de bord, avec confirmation du nombre de segments remis en file.

### Corrigé
- La file expose `provider_is_ready()` : l’interface et les routes peuvent vérifier qu’un run payant est possible sans mettre quoi que ce soit en file.

## [1.0.3] - 2026-10-26

### Corrigé
- Sélecteur de langue utilisable sur les pages traduites : les liens portant un attribut `hreflang` (entrées du sélecteur, alternatives) ne sont plus réécrits vers la langue courante.
- Slugs traduits : le titre du contenu est enregistré comme segment de page même lorsqu’il n’apparaît pas tel quel dans le HTML (titres masqués des builders), le slug source n’est plus jamais persisté comme repli, un slug réglé manuellement n’est plus écrasé, et les anciens replis des versions 1.0.x sont remplacés par le slug traduit avec conservation dans l’historique pour la redirection 301.
- Couverture de traduction : la clé de cache inclut la version de couverture, le pourcentage affiché reflète immédiatement une nouvelle traduction.
- Capability `omtr_manage` réaccordée une fois au démarrage pour les installations activées pendant un bootstrap interrompu puis mises à jour sans réactivation ; un retrait volontaire reste respecté.
- Fournisseur actif : l’enregistrement d’une clé pour un autre fournisseur l’active automatiquement quand le fournisseur courant n’a pas de clé, et toute mise en file payante est refusée tant qu’aucune clé n’est configurée, avec un message explicite.
- Statistiques « mots » : compteur des mots réellement traduits (mois en cours et total depuis l’installation), indépendant du plafond mensuel, incrémenté uniquement après enregistrement réussi.
- Tableau de bord : nombre de segments en échec affiché avec indication que « Traduire tout le site » les relance sans repayer le contenu déjà traduit.
- Sélecteur en menu déroulant « drapeau seul » ou « code » : la liste s’ajuste à son contenu au lieu de forcer 148 px, et la langue active reste repérable par son fond.

## [1.0.2] - 2026-10-26

### Corrigé
- L’extension ne se désactive plus sur les hébergements où l’extension PHP Sodium est absente : le chiffrement des clés API utilise alors la bibliothèque sodium_compat fournie par WordPress, avec un format de clé identique.
- Le message d’erreur d’installation indique précisément ce qui manque (version de PHP, chiffrement ou dossier vendor) au lieu d’un message global.

## [1.0.1] - 2026-10-26

### Ajouté
- Contrôle qualité déterministe des réponses IA : réponse complète obligatoire, conservation exacte des variables, URL et balises HTML, respect du glossaire, détection des sorties inchangées ou de longueur anormale.
- Couverture de traduction calculée page par page grâce à un inventaire persistant des segments ; les variantes incomplètes sont exclues de `hreflang` et des sitemaps lorsque la protection SEO est active.
- Variantes linguistiques régionales pour l’anglais, l’allemand, l’espagnol, le français, l’italien, le néerlandais et le portugais.

### Modifié
- L’option `noindex` des traductions partielles est désormais activée par défaut pour les nouvelles installations.
- L’estimation des coûts inclut le prompt système, l’enveloppe JSON, la taille réelle des lots et une marge d’expansion de la traduction.
- Les quatre fournisseurs retentent une réponse complète lorsqu’un contrôle de format ou de qualité échoue.
- Le sélecteur de langue adopte un rendu plus compact inspiré de shadcn/ui : hauteur réduite, drapeaux de 16 px, espacement resserré, bordure et ombre plus discrètes.
- L’environnement local utilise désormais l’interface WordPress et Omnium Translate en français.

### Corrigé
- La migration de base force un nouveau scan des pages existantes afin de construire leur inventaire SEO sans nouvel appel IA pour les segments déjà traduits.
- Les pages de recherche WordPress ne reçoivent plus de canonical ou de groupe `hreflang` potentiellement ambigu produit par l’extension.
- Le sélecteur est explicitement exclu du moteur de traduction afin que ses libellés natifs ne reçoivent plus de préfixes artificiels comme `[EN]` ou `[ES]`.
- Le fournisseur simulé des sites de test ne préfixe plus les traductions avec un code de langue entre crochets.
- Le cache de résultats PHPUnit (`.phpunit.result.cache`) est exclu du zip de distribution.

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
