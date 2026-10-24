# Audit confidentialité — décodage des entités HTML parasites — 2026-10-24
Verdict : ✅ VALIDÉ

## Étape 1 — Flux sortants
Aucun flux réseau ajouté ou modifié. Le correctif n'opère que sur la sérialisation locale du DOM déjà traduit (`DOMDocument::saveHTML()`), sans appel réseau ni nouvelle donnée collectée.

## Étape 2 — Ce qui part vers l'IA
Sans objet : aucune donnée supplémentaire n'est extraite ni envoyée. Le correctif agit après la traduction, uniquement sur le texte déjà reçu du fournisseur.

## Étape 3 — Stockage et journaux
Sans objet : aucune écriture en base ni en log modifiée.

## Étape 4 — Côté visiteurs
Le HTML rendu aux visiteurs est inchangé visuellement (les entités `&eacute;` et le caractère `é` s'affichent de façon identique dans un navigateur) ; seul le code source brut devient cohérent avec la page d'origine. Aucune ressource tierce, aucun cookie, aucun comportement de redirection modifiés.

## Étape 5 — Transparence
Sans objet pour ce correctif.

## Findings
Aucun.

Règle de décision : aucun finding Critique/Haute → pas de blocage.
