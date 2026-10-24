# Audit sécurité — interface admin — 2026-10-24

Verdict : ✅ VALIDÉ (périmètre `assets/src/admin`)

## Contrôles automatiques

| Outil | Résultat |
| --- | --- |
| PHPCS | ✅ Docker, aucune erreur |
| PHPStan niveau 6 | ✅ Docker, aucune erreur |
| PHPUnit | ✅ Docker, 287 tests, 1 test ignoré |
| npm audit --audit-level=high | ✅ 0 vulnérabilité |
| npm run build | ✅ réussi |
| npm run lint | ✅ réussi |
| composer audit | Non disponible dans l’environnement hôte |
| gitleaks | Non disponible dans l’environnement hôte |

## Findings

Aucun finding Critique ou Haute dans le code livré. Les outils non installés sur l’hôte doivent être exécutés par la CI avant fusion.

## Checklist manuelle

- Clé API : champ `password`, envoi uniquement à `/providers/{id}/key`; le masque API fourni par le serveur est affiché sans clé en clair.
- REST : toutes les mutations passent par `apiFetch` et les routes du contrat; le nonce standard est injecté par WordPress.
- XSS : aucune utilisation de `dangerouslySetInnerHTML`; les valeurs sont rendues par React.
- Accessibilité : labels des contrôles, focus visible, boutons clavier, `fieldset`/`legend` pour les choix et rôle `tabpanel`.
- Import : limite client à 1 Mo; la validation complète reste côté route REST conformément au contrat.
- Export : déclenché par la route REST; aucune clé n’est ajoutée côté client.
