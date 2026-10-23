# Slugs traduits

Les slugs des contenus WordPress publics (pages, articles et types publics comme les produits WooCommerce) sont traduits. Les pages hiérarchiques utilisent les slugs traduits de chaque ancêtre lorsqu'ils sont disponibles. Le slug source reste accepté sous un préfixe de langue.

Limite du jalon 4 : les catégories et autres taxonomies conservent leur slug source. La table `omtr_slugs` identifie uniquement les contenus par `post_id` ; une traduction fiable des taxonomies nécessitera une identité dédiée (`term_id` et taxonomie) dans un futur schéma.
