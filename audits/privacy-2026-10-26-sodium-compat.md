# Audit confidentialité — v1.0.2 — 2026-10-26

Verdict : ✅ VALIDÉ

Périmètre : diff `v1.0.1` → `fix/sodium-compat` (`omnium-translate.php`, `src/Security/Crypto.php`, tests, Composer, traductions, docs). Le reste est inchangé depuis `audits/privacy-2026-10-26.md` (VALIDÉ).

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| — | — | Aucun nouvel appel réseau : le polyfill `sodium_compat` est local (fichiers de WordPress). | Oui |

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| — | — | Aucun finding. | — |

## Checklist

### Étape 2 — Ce qui part vers l'IA — OK (inchangé)
Aucun changement du crawler, de l'extraction, du filtre PII ni du prompt.

### Étape 3 — Stockage et journaux — OK
- Les clés API restent chiffrées au repos dans les deux modes (même format `v1:`), jamais en clair : sans implémentation de secretbox, l'extension refuse toujours de démarrer (`omnium-translate.php:34`).
- Aucun nouveau log ; le message de prérequis n'est pas stocké.

### Étape 4 — Côté visiteurs — OK (inchangé)
Aucun cookie, stockage navigateur ni ressource front ajoutés. La notice de prérequis est en admin uniquement, réservée à `activate_plugins` (`omnium-translate.php:43-46`).

### Étape 5 — Transparence — OK
- `README.md` et `readme.txt` indiquent qu'aucune extension PHP n'est à activer et expliquent le repli sur `sodium_compat`.
- Aucune télémétrie ajoutée.
