# Audit confidentialité — WooCommerce — 2026-10-23
Verdict : ✅ VALIDÉ

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| Aucune nouvelle destination | `src/Woo/*` | Aucun appel réseau | ✅ |
| Fournisseur IA, via file existante | `EmailTemplateTranslator.php` | Gabarits sans données de commande; jamais l'objet ou la valeur formatée | ✅ |

## Findings

Aucun finding Critique ou Haute. La méta de commande ne stocke que le code de langue; les fragments sont rendus avec les traductions existantes et ne sont jamais envoyés à l'IA.

## Checklist

- Crawler et traduction à la volée : ✅ aucun déclenchement ajouté aux emails/fragments; le renderer n'envoie rien pendant ces requêtes.
- Emails transactionnels : ✅ extraction de sujets/en-têtes textuels et contenu additionnel depuis les gabarits/réglages; la valeur déjà personnalisée est ignorée; emails admin sans méta de commande inchangés.
- Données de commande : ✅ nom, adresse, produits, montants et headers restent hors des segments; seule `_omtr_order_language` est persistée.
- Fragments : ✅ `lang`/`language` et Referer sont validés contre source + cibles; une langue étrangère/non configurée est refusée.
- Front : ✅ aucune ressource externe, cookie ou stockage navigateur ajouté.
- Transparence : ✅ aucun flux Omnium ni nouvelle destination réseau.

Recette UI Elementor + WooCommerce (`127.0.0.1:8882`) : endpoint disponible (HTTP 200), parcours manuel non exécuté dans ce terminal.
