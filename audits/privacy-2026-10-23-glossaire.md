# Audit confidentialité — glossaire et éditeur — 2026-10-23

Verdict : ✅ VALIDÉ

Périmètre : glossaire, import/export CSV, proposition de retraduction et API REST admin. Aucun nouveau flux sortant, cookie, journal, crawler ou code front n’est introduit.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| API Anthropic codée en dur | `src/Providers/Claude/ClaudeProvider.php:218` | Segments publics filtrés, langues, glossaire, ton et contexte | ✅ |
| `home_url()` | `src/Queue/ScanJob.php:148-206` | GET anonyme sans cookie, sans redirection | ✅ |
| GitHub Releases du plugin | `src/Core/Updater.php:19` | Requête de version, aucune donnée du site | ✅ |

Aucun autre appel réseau et aucune URL tierce front n’ont été trouvés par les deux recherches imposées.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding Critique, Haute, Moyenne ou Basse dans le périmètre | — |

## Checklist — données envoyées à l’IA

- OK — crawl anonyme avec `cookies => array()`, `redirection => 0`, SSL vérifié (`src/Queue/ScanJob.php:178-190`).
- OK — admin, REST, AJAX non autorisé, utilisateurs connectés, previews, contenus protégés, panier, commande, compte et `order-received` sont exclus (`src/Router/RequestGuard.php:20-58`).
- OK — la file exige un contenu publié et non protégé (`src/Queue/ActionSchedulerQueue.php:372-397`).
- OK — filtre PII emails/téléphones/IBAN/cartes Luhn (`src/Security/PiiFilter.php:24-123`) et tests unitaires dédiés.
- OK — les valeurs de formulaires ne sont pas extraites par l’extracteur existant.
- OK — le prompt reçoit uniquement langues, glossaire, ton et contexte ; le nouveau `GlossaryService` ne collecte aucune donnée utilisateur.

## Checklist — stockage et journaux

- OK — la fonctionnalité stocke uniquement règles de glossaire et traductions ; aucun identifiant visiteur, IP ou user-agent.
- OK — aucune donnée n’est envoyée à Omnium.
- OK — les journaux restent techniques, sanitizés et purgés à 30 jours (`src/Store/DbLogger.php:37-76`).
- OK — l’export CSV contient exclusivement `term,target_lang,translation,rule` et ne dépend pas d’`ApiKeyStore` (`src/Glossary/GlossaryCsv.php:117-140`).
- OK — l’import/export utilise `php://memory` et ne crée aucun fichier persistant côté serveur.

## Checklist — visiteurs

- OK — aucune modification front, aucun cookie, stockage navigateur, CDN, police, image ou redirection.
- OK — les routes REST sont privées et réservées à `omtr_manage` ; aucune donnée du glossaire ou des segments n’est publique.

## Checklist — transparence et désinstallation

- OK — aucun changement de fournisseur, de localisation ou de flux nécessitant une modification du texte de transparence au titre de ce périmètre.
- OK — l’export du glossaire ne peut pas inclure de clé API.
- OK — les tables, options, transients, tâches et clé chiffrée restent couverts par la désinstallation (`uninstall.php:21-58`).
- OK — aucune télémétrie ni statistique d’usage.

