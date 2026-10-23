# Audit confidentialité — v0.1.0 — 2026-10-23

Verdict : ✅ VALIDÉ

Périmètre : crawler, rendu front et déclencheur à la volée du jalon 3. Aucun flux vers Omnium n’a été ajouté.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| API Anthropic codée en dur | `src/Providers/Claude/ClaudeProvider.php:218-230` | Segments publics filtrés, langues, glossaire et contexte configuré | ✅ |
| `home_url()` exact | `src/Queue/PublicContent.php:77-118` | GET anonyme du HTML public, sans cookie, sans redirection | ✅ |
| `home_url()` exact (job de scan existant) | `src/Queue/ScanJob.php:160-220` | GET anonyme du HTML public, cookies vides, sans redirection | ✅ |
| GitHub Releases public | `src/Core/Updater.php:19-31` | Vérification de version, aucune donnée de contenu | ✅ |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding Critique, Haute, Moyenne ou Basse sur le périmètre modifié. | — |

## Checklist

### Ce qui part vers l’IA

- **OK** — Le crawler force `cookies => []`, un en-tête Cookie vide, SSL et zéro redirection (`src/Queue/PublicContent.php:99-109`).
- **OK** — Seuls les post types publics, au statut `publish`, sans mot de passe, sont résolus (`src/Queue/PublicContent.php:38-69`, `155-177`).
- **OK** — Admin, REST, compte, panier, commande, checkout et `order-received` sont exclus par la normalisation et `RequestGuard`.
- **OK** — L’à-la-volée refuse tout utilisateur connecté et toute URL inconnue ; la query string est ignorée (`src/Queue/OnTheFlyTrigger.php:64-82`, `src/Queue/PublicContent.php:121-152`).
- **OK** — Emails, téléphones, IBAN et cartes valides Luhn sont exclus par `PiiFilter` (`src/Security/PiiFilter.php:24-123`) avec tests unitaires dédiés.
- **OK** — L’extracteur ignore les éléments sensibles et n’extrait jamais la valeur saisie des champs ; seuls les attributs textuels autorisés sont considérés.
- **OK** — Le prompt ne contient que langues, glossaire, ton/contexte et segments publics, sans identité d’administrateur (`src/Providers/PromptBuilder.php:44-89`).
- **N/A jalon 3** — Les emails transactionnels Woo par gabarit relèvent du jalon 4 et ne sont pas touchés ici.

### Stockage et journaux

- **OK** — Le crawler ne conserve que des hash SHA-256 d’URL et de HTML, jamais le HTML complet (`src/Queue/Crawler.php:37-69`, `src/Store/PageRepository.php`).
- **OK** — L’anti-rafale conserve seulement un booléen sous une clé dérivée du hash d’URL pendant 300 secondes (`src/Queue/OnTheFlyTrigger.php:73-96`).
- **OK** — Aucun IP, user-agent visiteur, cookie ou identifiant utilisateur n’est stocké.
- **OK** — Les logs ajoutés ne contiennent que comptes, langues et identifiants techniques ; aucune source ni réponse brute.
- **OK** — Les logs sont purgés quotidiennement au-delà de 30 jours (`src/Store/DbLogger.php:54-76`).
- **OK** — L’estimation ne persiste ni contenu ni coût ; aucune clé API n’est exposée.

### Côté visiteurs

- **OK** — Aucun cookie, `localStorage` ou `sessionStorage` n’est ajouté.
- **OK** — Aucune ressource front externe n’est chargée.
- **OK** — Le rendu ne déclenche aucun appel fournisseur ; `capture()` ne fait aucune écriture et `flush()` est branché sur `shutdown` (`src/Queue/OnTheFlyTrigger.php:59-105`, `src/Queue/QueueModule.php`).
- **OK** — Aucune redirection selon la langue du navigateur n’est ajoutée.
- **N/A environnement unitaire** — La vérification manuelle de l’onglet réseau sera faite sur les trois sites `wp-env` avant release ; l’analyse statique ne trouve aucune nouvelle URL tierce front.

### Transparence client et suppression

- **OK périmètre actuel** — Les contrats fournisseur exposent déjà localisation et présence d’un palier gratuit ; leur affichage final et `wp_add_privacy_policy_content()` relèvent de l’interface du jalon 5.
- **OK** — Zéro télémétrie ou ping Omnium dans les flux recensés.
- **OK** — La désinstallation supprime les tâches, la clé chiffrée, toutes les options `omtr_*`, transients `omtr_*`, métas `_omtr_*` et tables lorsque la purge est choisie (`uninstall.php:21-57`).
