# Audit confidentialité — backend administration — 2026-10-24

Verdict : ✅ VALIDÉ sur le périmètre modifié.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Fournisseur IA choisi (Anthropic, OpenAI, Mistral UE ou Gemini) | `src/Providers/*Provider.php` : appels `wp_remote_post` existants | Segments publics filtrés, langues, glossaire et ton | ✅ |
| Site courant `home_url()` | `src/Queue/PublicContent.php:99`, `src/Queue/ScanJob.php:194` | Requête anonyme sans cookie vers une URL publique du site | ✅ |
| GitHub Releases | `src/Core/Updater.php:19-28` | Vérification de version sans contenu du site | ✅ |
| Drapeaux, CSS et JS locaux | `src/Switcher/Switcher.php:237-247`, `src/Switcher/SwitcherAssets.php:28-31` | Aucune donnée transmise à un tiers | ✅ |

Aucun nouvel appel réseau n’est introduit par le backend admin. Les URL de création de clés et les sources de tarification de `/providers` sont des liens d’information, pas des appels automatiques.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Aucun | — | Aucun finding sur le périmètre modifié | — |

## Checklist

- **Contenu envoyé à l’IA — OK.** `/translate/all` sélectionne les contenus publics (`src/Admin/Administration.php:361-372`), puis la file vérifie à nouveau les pages publiées et exclut les chemins sensibles (`src/Queue/ActionSchedulerQueue.php:114-143`). Le crawler envoie des requêtes sans cookie (`src/Queue/PublicContent.php:99-105`, `src/Queue/ScanJob.php:194-200`). Le filtre PII existant couvre emails, téléphones, IBAN et cartes (`src/Security/PiiFilter.php:24-100`). Aucun nouveau prompt n’est ajouté.
- **Stockage et journaux — OK.** Les clés restent dans l’option chiffrée dédiée ; l’export JSON ne comprend que réglages et glossaire (`src/Admin/Administration.php:416-435`). Le tableau de bord lit 20 lignes de journal existantes sans leur ajouter de contenu personnel (`src/Admin/Administration.php:342-345`). La purge de 30 jours reste planifiée (`src/Store/DbLogger.php:57-75`).
- **Visiteurs — OK.** Aucun nouveau cookie, stockage navigateur, CDN, police ou télémétrie. Le sélecteur utilise les drapeaux et les ressources locales ; le mode sombre dépend de `prefers-color-scheme` uniquement si activé. Aucune redirection automatique n’est ajoutée.
- **Transparence — OK.** Le texte WordPress de confidentialité indique le fournisseur choisi, le texte des pages publiques et la localisation UE/hors UE (`src/Admin/Administration.php:495-503`). `/providers` expose région et palier gratuit pour l’interface (`src/Admin/Administration.php:220-245`). L’option de purge complète et `omtr_wizard_done` sont prises en charge par `uninstall.php` et `Options::all()`.

Vérification navigateur des requêtes réseau sur les sites de recette à refaire avec l’interface React une fois son bundle intégré.
