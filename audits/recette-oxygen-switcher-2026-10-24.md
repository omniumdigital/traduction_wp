# Recette Oxygen — sélecteur de langue — 2026-10-24

## Environnement

- Oxygen 6.1.3, WooCommerce 11.2.1, Rank Math 1.0.280.
- Commande : `npm run env:oxygen`.
- Le port 8883 étant occupé, port local temporaire utilisé : `8893` (configuration non committée, restaurée après la recette).
- Environnement arrêté après la recette avec `npx wp-env stop`.

## Résultats

| Vérification | Résultat |
|---|---|
| Plugin Omnium Translate actif | ✅ |
| Oxygen actif et mode `oxygen` | ✅ |
| Élément enregistré | ✅ `OxygenElement\\LanguageSwitcher` |
| Save location Element Studio | ✅ `omnium-translate/src/Switcher` |
| Contrôles variant/display | ✅ vérifiés par PHPUnit et définition Oxygen réelle |
| Renderer local FR/EN | ✅ shortcode rendu par WordPress avec FR (`/`) et EN (`/en/`) |
| Alternates publics FR/EN | ✅ page publique contient les liens `hreflang="fr"` et `hreflang="en"` |
| Éditeur Oxygen | ✅ requête `post.php?post=10&action=edit&oxygen=1` HTTP 200 |

## Limite observée

Le thème Oxygen Zero vierge ne rend pas le contenu `post_content` d’une page sans composition Oxygen enregistrée : le shortcode de la page de recette n’apparaît donc pas dans le corps HTML public. Le renderer a néanmoins été vérifié directement via `do_shortcode()` et produit les liens attendus (`FR` vers `/`, `EN` vers `/en/`), tandis que la page publique produit bien les alternates hreflang. Une vérification visuelle dans une page Oxygen composée devra être faite sur un gabarit Oxygen réel.

## Vérifications qualité

- PHPCS : ✅
- PHPStan : ✅
- PHPUnit complet : ✅ 290 tests, 833 assertions, 1 test ignoré.
- PHPUnit sécurité : ✅ 147 tests, 415 assertions.
- `npm audit --audit-level=high` : ✅
