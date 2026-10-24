# Traductions JavaScript de l’administration

L’environnement `wp-env` n’était pas démarré lors de la livraison. Après avoir lancé
`wp-env start`, générer le JSON WordPress avec :

```bash
npx wp-env run cli wp i18n make-json /var/www/html/wp-content/plugins/omnium-translate/languages --pretty-print
```

La commande produit le fichier JSON de traductions à côté du fichier `.po`; le backend
charge ensuite ce catalogue avec `wp_set_script_translations()`.
