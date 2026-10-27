=== Omnium Translate ===
Contributors: omniumdigital
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Traduisez les pages WordPress rendues avec le fournisseur IA et la clé API de votre choix. Les traductions restent sur votre site, sans compte ni abonnement Omnium, et aucune donnée n’est envoyée à Omnium.

== Description ==

Omnium Translate traduit le contenu final rendu des pages et stocke les traductions dans votre base WordPress. Les demandes sont envoyées directement au fournisseur IA sélectionné avec votre propre clé API. Les nouvelles traductions sont traitées en arrière-plan.

Les intégrations prises en charge comprennent Gutenberg, Elementor, Oxygen 6 et, en option, WooCommerce. Le sélecteur de langue est disponible sous forme de shortcode, bloc Gutenberg, widget Elementor, élément Oxygen, élément de menu WordPress ou fonction PHP.

Prérequis : WordPress 6.9 ou supérieur et PHP 8.1 ou supérieur. Aucune extension PHP à activer : sans l’extension Sodium, la bibliothèque sodium_compat fournie par WordPress prend le relais. WooCommerce 9.0 à 11.2 est facultatif.

Dans l’éditeur WordPress, utilisez le panneau « Traduire cette page » pour mettre une page en file. Un éditeur visuel facultatif permet aux utilisateurs autorisés de corriger les segments traduits directement sur la page. La traduction à la volée est protégée par un quota de 100 segments par heure par défaut, réglable dans les paramètres.

Les champs textuels JSON-LD sont traduits et les URL internes réécrites, tandis que les prix et identifiants produit sont préservés. Après une modification, l’extension vide son cache ainsi que ceux de WP Rocket et LiteSpeed lorsqu’ils sont disponibles. Les intégrations peuvent aussi écouter l’action omtr_translations_updated($post_id, $lang).

Avant enregistrement, chaque réponse IA doit être complète et conserver exactement les variables, URL et balises HTML du texte source. Le glossaire est vérifié automatiquement. Une réponse manifestement anormale est refusée et retentée une fois par le fournisseur.

La couverture SEO est calculée pour chaque page : avec la protection des traductions partielles, activée par défaut, une variante incomplète reste en noindex et n’est pas ajoutée aux groupes hreflang ni aux sitemaps. Les variantes régionales courantes comme en-us, en-gb, fr-ca et pt-br sont proposées dans les réglages.

Le cookie facultatif de préférence mémorise uniquement la langue sélectionnée. Lorsqu’il est activé, il redirige seulement une arrivée externe sur la page d’accueil sans préfixe. Aucun cookie n’est créé par défaut.

== Installation ==

1. Téléchargez omnium-translate.zip depuis la version GitHub souhaitée.
2. Dans WordPress, ouvrez Extensions > Ajouter une extension > Téléverser une extension, envoyez le ZIP et activez Omnium Translate.
3. Ouvrez Omnium Translate dans l’administration et suivez l’assistant de configuration.
4. Choisissez les langues source et cibles, sélectionnez un fournisseur IA, ajoutez et testez sa clé API, vérifiez l’estimation puis lancez la traduction.

Créez une clé API auprès du fournisseur choisi :

* Anthropic / Claude : https://console.anthropic.com/settings/keys (hors UE ; pas de palier gratuit)
* OpenAI : https://platform.openai.com/api-keys (hors UE ; pas de palier gratuit)
* Mistral : https://console.mistral.ai/api-keys (UE ; palier gratuit limité)
* Google Gemini : https://aistudio.google.com/apikey (hors UE ; palier gratuit limité)

Les régions, paliers gratuits et conditions d’utilisation des données peuvent évoluer. Un palier gratuit peut autoriser le fournisseur à réutiliser les contenus transmis ; vérifiez ses conditions en vigueur avant utilisation.

Les clés API sont chiffrées avec Sodium. Vous pouvez définir une clé de chiffrement dédiée dans wp-config.php :

    define( 'OMTR_ENCRYPTION_KEY', 'replace-with-a-long-random-secret-value' );

Si cette constante n’est pas définie, l’extension dérive sa clé de chiffrement des valeurs WordPress AUTH_KEY et SECURE_AUTH_SALT.

Ajoutez un sélecteur avec [omtr_switcher]. Attributs facultatifs : variant="dropdown|inline|floating" et display="flag-name|flag|name|code". Vous pouvez aussi utiliser le bloc Omnium Translate, le widget Elementor, l’élément Oxygen 6, l’élément de menu ou appeler omtr_switcher() depuis un modèle de thème.

== Frequently Asked Questions ==

= Quel contenu est envoyé au fournisseur IA ? =

Le texte public rendu, les langues source et cibles et, lorsqu’ils sont configurés, le glossaire, le ton et le contexte sont envoyés au fournisseur sélectionné. Les adresses email, numéros de téléphone, IBAN et cartes de paiement détectés sont exclus. L’extension n’envoie aucune donnée à Omnium.

= L’extension crée-t-elle des cookies ? =

Aucun cookie n’est créé par défaut : la langue se trouve dans l’URL. Un cookie fonctionnel facultatif peut être activé dans les réglages. Il redirige seulement une arrivée externe sur la page d’accueil de la langue source sans préfixe.

= Comment configurer le cache de pages ? =

Les versions linguistiques utilisent des préfixes comme /en/ et /es/, tandis que la langue source n’en utilise pas. Configurez WP Rocket, LiteSpeed Cache ou votre cache pour varier selon l’URL complète. L’extension purge les caches WP Rocket et LiteSpeed après une mise à jour. Les autres intégrations peuvent utiliser l’action omtr_translations_updated($post_id, $lang).

= Que vérifier avant la mise en production ? =

Testez la configuration avec une vraie clé API, puis contrôlez les pages traduites et les parcours du site. Avant la première mise en production chez un client, faites relire l’extension par un second développeur ou lancez une analyse Patchstack ou WPScan.

= Que se passe-t-il lors de la désinstallation ? =

La clé API chiffrée et les tâches planifiées sont supprimées. Les traductions et réglages sont conservés par défaut. Activez l’option de purge complète avant la désinstallation pour retirer aussi les tables, options, transients et métadonnées de l’extension.

= Comment les mises à jour sont-elles distribuées ? =

L’extension recherche les versions GitHub publiques avec Plugin Update Checker. Cette vérification peut être désactivée dans les réglages.

== Changelog ==

= 1.2.1 =
* Un texte déjà dans la langue cible (comme l'article « Hello world! » en anglais) ne fait plus échouer tout son lot : seuls les segments refusés sont renvoyés à l'IA, et une traduction identique est acceptée au second essai.

= 1.2.0 =
* Assistant : le ton et le contexte du site se renseignent dès l'installation.
* Logo Omnium comme icône de l'extension (menu d'administration et écran des mises à jour).
* Couverture des langues affichée au centième, sans jamais arrondir à 100 % une langue incomplète.

= 1.1.2 =
* Erreurs fournisseur diagnostiquables : le code d’erreur du fournisseur (par exemple `invalid_request_error`) est affiché avec le motif, sans jamais reprendre son message brut.
* Crédit ou quota épuisé, clé Gemini invalide et localisation non desservie sont désormais reconnus explicitement.
* Bouton « Tester la clé » ajouté dans l’onglet Réglages.

= 1.1.1 =
* Erreurs de lots explicites : chaque rejet du fournisseur (HTTP 400/401…) est classifié en motif fixe et lisible (« entrée ou sortie trop grande pour le modèle », « modèle inconnu », « clé refusée »…), affiché dans le bandeau d’échec du tableau de bord — jamais le message brut du fournisseur.
* Lots rejetés comme trop grands : le lot est scindé en deux moitiés réessayées automatiquement, ce qui isole le segment responsable au lieu de faire échouer ses voisins à chaque relance.
* Segment plus long qu’une requête : il est écarté avant l’appel avec un motif explicite (« trop long pour une requête unique ») au lieu d’un échec générique.
* Verrou de run auto-réparé : un run dont toutes les actions sont mortes ne bloque plus le site pendant 3 heures, le verrou est libéré dès qu’aucun travail ne reste en file.

= 1.1.0 =
* Suivi de la traduction en direct : le tableau de bord se rafraîchit toutes les 5 secondes pendant un run, avec barres de progression par langue, nombre de tâches et de lots en file et horodatage de la dernière activité.
* Segments en échec : le tableau de bord affiche la cause de l’échec la plus récente et un bouton « Relancer les segments en échec » qui ne renvoie que ce qui a échoué, par langue, sans jamais repayer le contenu déjà traduit.
* La relance est refusée avec un message clair tant qu’aucune clé API n’est configurée pour le fournisseur actif.

= 1.0.3 =
* Sélecteur de langue : les liens du sélecteur ne sont plus réécrits vers la langue courante sur une page traduite ; chaque entrée pointe à nouveau vers sa langue.
* Slugs traduits fiables : le titre du contenu est toujours traduit même lorsqu’il n’apparaît pas tel quel dans le HTML (titres masqués des builders), le slug source n’est plus jamais enregistré comme repli et un slug modifié manuellement n’est plus écrasé ; les anciens replis sont remplacés avec redirection 301.
* Couverture de traduction : le taux affiché se met à jour immédiatement après une nouvelle traduction.
* Menu d’administration : la capability est rétablie au démarrage pour les installations mises à jour sans réactivation.
* Fournisseur actif : saisir une clé pour un autre fournisseur l’active automatiquement quand le fournisseur courant n’a pas de clé ; toute mise en file est refusée tant qu’aucune clé n’est configurée, avec un message explicite.
* Statistiques : compteur des mots réellement traduits (mois en cours et total) et segments en échec affichés sur le tableau de bord avec indication de relance.
* Sélecteur en affichage « drapeau seul » ou « code » : la liste déroulante s’ajuste à son contenu et la langue active reste repérable.

= 1.0.2 =
* L’extension fonctionne sans l’extension PHP Sodium, grâce à la bibliothèque sodium_compat fournie par WordPress : plus rien à activer côté serveur.
* Le message d’erreur d’installation indique précisément ce qui manque (version de PHP, chiffrement ou dossier vendor).

= 1.0.1 =
* Contrôle qualité des réponses IA (variables, URL, balises et glossaire conservés) avec nouvel essai automatique.
* Couverture de traduction par page : les variantes incomplètes sont exclues de hreflang et des sitemaps ; noindex des traductions partielles activé par défaut.
* Variantes linguistiques régionales et estimation des coûts plus précise.
* Interface locale française et sélecteur de langue plus compact, inspiré de shadcn/ui.
* Le sélecteur est exclu de la traduction afin d’éviter les marqueurs de langue artificiels.

= 1.0.0 =
* Traduction depuis l’éditeur et correction facultative dans un éditeur visuel en front.
* Traduction JSON-LD, purge des caches, cookie de préférence facultatif et quota horaire configurable.
* Sélecteur de langue, glossaire, slugs traduits, métadonnées SEO et prise en charge facultative de WooCommerce.
