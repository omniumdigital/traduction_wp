# Audit confidentialité — intégration jalon 2 — 2026-10-21

Verdict : ✅ VALIDÉ

Périmètre : crawler et rendu front modifiés pendant la recette d'intégration. Aucun finding Critique ou Haute ouvert.

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| API Anthropic codée en dur | `src/Providers/Claude/ClaudeProvider.php:197` | Segments publics filtrés, langues, glossaire, ton/contexte | ✅ inchangé |
| `home_url()` exact | `src/Queue/ScanJob.php:148` | GET anonyme de la page publique, sans cookie | ✅ schéma/hôte/port stricts, aucune redirection |
| GitHub Releases public | `src/Core/Updater.php:19` | Vérification de version | ✅ inchangé |

Aucun autre flux sortant ni ressource front tierce trouvé par les recherches prescrites.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif |
| --- | --- | --- | --- |
| Haute — corrigé | `src/Queue/ScanJob.php:185` | Une redirection HTTP pouvait faire lire au crawler un autre hôte public. | `redirection => 0`, validation défensive de l'URL et filtres WordPress limités au endpoint exact du site. |

## Checklist

### Données envoyées à l'IA

- ✅ Crawl anonyme avec `cookies => array()` et TLS vérifié (`src/Queue/ScanJob.php:181`).
- ✅ Aucun suivi de redirection ; l'URL doit correspondre exactement à `home_url()` (`src/Queue/ScanJob.php:149`).
- ✅ La recette de reprise confirme l'exclusion de `recette-reprise@example.test` : `email_segments = 0`.
- ✅ Aucun appel IA visiteur ; valeurs de formulaire, pages privées/protégées et chemins sensibles restent exclus par les composants existants.

### Stockage et journaux

- ✅ Recette réelle : `sensitive_logs = 0` pour email, contenu source et motif de clé.
- ✅ Erreur sans clé : `Batch failed after 1 attempt(s) (http=0).`, sans réponse brute.
- ✅ Aucune donnée visiteur ajoutée ; purge 30 jours inchangée.

### Côté visiteurs

- ✅ Aucun cookie, stockage navigateur, ressource distante ou redirection de langue ajouté.
- ✅ La page source est strictement identique plugin actif/inactif (SHA-256 documenté dans la recette).
- ✅ Les changements front se limitent à la langue traduite et aux liens internes préfixés.

### Transparence client

- ✅ Aucune télémétrie ni destination Omnium.
- N/A : politique et interface fournisseur relèvent d'un jalon ultérieur, non modifié ici.
