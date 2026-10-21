---
name: privacy-audit
description: Contrôle de confidentialité et RGPD du plugin WordPress Omnium Translate. À utiliser avant chaque release, et dès qu'un changement touche à ce qui est envoyé aux fournisseurs IA, au crawler, aux logs, aux exports, aux cookies, aux ressources chargées en front ou à la désinstallation.
---

# Audit de confidentialité — Omnium Translate

Tu es responsable de la confidentialité du plugin. Règle d'or : seul le contenu **public** du site peut partir vers le fournisseur IA choisi par le client, et **rien** ne part vers Omnium. Chaque point est vérifié dans le code et tracé dans le rapport.

## Étape 1 — Cartographie des flux sortants

Liste TOUS les appels réseau du plugin :

```bash
grep -rnE "wp_remote_(get|post|request)|wp_safe_remote_|curl_|fsockopen|file_get_contents\(\s*['\"]https?" src/
grep -rnE "https?://" src/ assets/src/ --include=*.php --include=*.js --include=*.ts --include=*.css
```

Produis un tableau : destination · fichier:ligne · données envoyées · justification. Destinations autorisées uniquement :

| Destination | Données autorisées |
| --- | --- |
| API du fournisseur IA sélectionné | Segments de texte public, langues, glossaire, ton du site |
| GitHub Releases (repo du plugin) | Aucune donnée du site (simple requête de version) |
| `home_url()` (crawler interne) | Requête anonyme sans cookie de session |

Toute autre destination = finding **Critique**. Aucune URL tierce en front (CDN, polices Google, drapeaux distants, analytics).

## Étape 2 — Ce qui part vers l'IA

- [ ] Le crawler rend les pages **sans cookie d'authentification ni de session WooCommerce** (vérifier les arguments `cookies` et headers de la requête).
- [ ] Sont exclus du crawl et de la traduction à la volée : pages compte, panier, commande, `order-received`, pages protégées par mot de passe, brouillons, contenus privés, prévisualisations, admin, REST, AJAX (hors fragments Woo traduits par gabarit).
- [ ] La traduction à la volée ne s'exécute **jamais** pour un utilisateur connecté (son rendu peut contenir ses données).
- [ ] Le filtre PII avant envoi détecte et exclut : emails, numéros de téléphone FR/internationaux, IBAN, numéros de carte (Luhn). Test unitaire présent pour chaque motif.
- [ ] Les valeurs de champs de formulaire (`value` des `input`, `textarea`) ne sont jamais extraites comme segments ; seuls `placeholder` et `label` le sont.
- [ ] Les emails transactionnels Woo sont traduits par gabarit (chaînes avec variables), jamais avec le contenu réel d'une commande.
- [ ] Le prompt ne contient ni URL d'admin, ni nom d'utilisateur, ni donnée de configuration autre que langues, glossaire et ton.

## Étape 3 — Stockage et journaux

- [ ] La table `log` ne contient ni texte de segment complet, ni clé API, ni IP, ni identifiant utilisateur. Contexte = identifiants techniques (job id, code HTTP, fournisseur).
- [ ] Purge automatique des logs après 30 jours (tâche planifiée vérifiée).
- [ ] Les erreurs du fournisseur ne sont pas stockées brutes (elles peuvent renvoyer le texte envoyé).
- [ ] Aucune donnée de visiteur stockée par le plugin (pas d'IP, pas de user-agent, pas de statistiques).
- [ ] L'export de configuration JSON et l'export CSV du glossaire n'incluent jamais la clé API.

## Étape 4 — Côté visiteurs

- [ ] Aucun cookie posé par défaut. Le cookie de préférence de langue (si activé) est strictement fonctionnel, `SameSite=Lax`, `Secure`, durée ≤ 12 mois, sans identifiant unique.
- [ ] Aucun `localStorage` / `sessionStorage` contenant autre chose que la langue choisie.
- [ ] Drapeaux SVG et CSS/JS du sélecteur servis depuis le plugin ; aucune requête tierce dans l'onglet réseau d'une page front (vérification manuelle sur le site de test).
- [ ] Pas de redirection automatique selon la langue du navigateur.

## Étape 5 — Transparence pour le client

- [ ] Texte de politique de confidentialité enregistré via `wp_add_privacy_policy_content()`, à jour avec la liste des fournisseurs et leur localisation.
- [ ] Avertissement visible dans les réglages pour les paliers gratuits (réutilisation possible des données par le fournisseur).
- [ ] Indication de localisation par fournisseur dans le sélecteur de fournisseur (UE / hors UE).
- [ ] `uninstall.php` propose la suppression complète : tables, options, transients, tâches planifiées, clé chiffrée. Vérifier qu'aucune option `omtr_*` ne subsiste après désinstallation sur le site de test.
- [ ] Aucune télémétrie, ping d'activation ou statistique d'usage envoyé à Omnium.

## Étape 6 — Rapport

Produis `audits/privacy-YYYY-MM-DD.md` :

```markdown
# Audit confidentialité — vX.Y.Z — YYYY-MM-DD
Verdict : ✅ VALIDÉ / ❌ BLOQUANT

## Flux sortants
| Destination | Fichier:ligne | Données | Conforme |

## Findings
| Sévérité | Fichier:ligne | Problème | Correctif proposé |

## Checklist
(étapes 2 à 5 avec OK/KO et preuve)
```

Sévérités : **Critique** (donnée personnelle envoyée à l'IA ou à un tiers, flux sortant non autorisé, clé API exposée), **Haute** (donnée personnelle stockée ou loguée, cookie non fonctionnel), **Moyenne** (transparence incomplète), **Basse**.

Un finding Critique ou Haute = verdict BLOQUANT, pas de release. Propose le correctif sous forme de diff, puis relance l'audit sur les fichiers corrigés.
