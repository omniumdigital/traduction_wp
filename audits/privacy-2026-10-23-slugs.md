# Audit confidentialité — slugs traduits — 2026-10-23

Verdict : ✅ VALIDÉ pour le périmètre slugs. Aucun nouveau flux sortant, cookie ou stockage de données de visiteur.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Fournisseur IA sélectionné | `src/Providers/{Claude,OpenAI,Mistral,Gemini}/*Provider.php` | Segments publics envoyés par la file, flux existant | Oui, inchangé |
| Site courant `home_url()` | `src/Queue/ScanJob.php:194`, `src/Queue/PublicContent.php:99` | Requête de contenu public, cookies vides | Oui, inchangé |
| GitHub Releases | `src/Core/Updater.php:19` | Vérification de version, flux existant | Oui, inchangé |
| Aucun | `src/Router/`, `src/Store/SlugRepository.php` | Aucun appel réseau | Oui |

Les URL présentes dans les commentaires ou liens d'administration sont documentaires ; elles ne constituent pas un appel réseau du routage. Aucun asset externe n'a été ajouté.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Aucun | — | Aucun problème dans le périmètre modifié | — |

## Checklist

- **Envoi IA : OK, inchangé.** Les slugs lus en front proviennent uniquement de la base du site ; aucune traduction IA n'est lancée par `LanguageRouter` ou `UrlTranslator`. Le crawler utilise `cookies => array()` (`ScanJob.php:200`) et `PublicContent` vide explicitement l'en-tête Cookie (`PublicContent.php:105-106`). Les filtres de publication, de requête et de PII restent dans `RequestGuard`, `PublicContent` et `Security/PiiFilter`.
- **Stockage et journaux : OK.** `omtr_slug_history` conserve seulement `post_id`, langue et ancien slug public (`Core/Migrations.php:123`) ; aucune IP, clé, session ou donnée de formulaire. Aucun nouveau log.
- **Visiteurs : OK.** Aucun cookie, `localStorage`, `sessionStorage`, ressource tierce ou redirection selon la langue du navigateur ajouté. La 301 ne dépend que du chemin explicite de la requête. Recette Gutenberg sur le port 8884 : URL traduite 200, ancien slug source 200, ancien slug traduit 301, nouveau 200.
- **Transparence et effacement : OK pour le changement.** La table d'historique figure dans `Tables::all()` et est donc supprimée par la purge complète de `uninstall.php`. Le changement n'ajoute aucun fournisseur, donnée personnelle ou flux nécessitant une nouvelle mention de confidentialité.
