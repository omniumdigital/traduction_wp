# Sites de test (wp-env)

| Site | Commande | URL | Plugins |
| --- | --- | --- | --- |
| Gutenberg seul | `npm run env:gutenberg` | http://localhost:8881 | - |
| Elementor + Woo | `npm run env:elementor` | http://localhost:8882 | Elementor, WooCommerce, Yoast SEO, Rank Math (activer l'un ou l'autre) |
| Oxygen 6 + Woo | `npm run env:oxygen` | http://localhost:8883 | Oxygen 6, WooCommerce, Rank Math |

Oxygen est commercial : décompresser le plugin dans `env/oxygen/oxygen/` (ignoré par git) avant `npm run env:oxygen`.

Identifiants par défaut wp-env : `admin` / `password`.
