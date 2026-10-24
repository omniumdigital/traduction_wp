# Contrat de l'API d'administration (jalon 5)

Contrat figé entre le backend REST (`src/Admin`, `src/Core`, `src/Switcher`) et l'interface React (`assets/src/admin`). Toute évolution passe par une modification de ce fichier.

Toutes les routes sont sous `omtr/v1`, avec `permission_callback` = `Capabilities::rest_permission` (capability `omtr_manage`) et le nonce REST standard (`X-WP-Nonce`, fourni par `apiFetch` via `wp_create_nonce( 'wp_rest' )`). Les erreurs sont des `WP_Error` avec un message traduisible générique : jamais de clé, de chemin serveur ni de réponse brute de fournisseur.

## Page d'administration

- Menu de premier niveau « Omnium Translate » (slug `omnium-translate`, capability `omtr_manage`, icône `dashicons-translation`).
- La page affiche `<div id="omtr-admin"></div>` et charge `assets/build/admin.js` (+ `admin.asset.php`) et le CSS de `@wordpress/components` (`wp-components`).
- Données initiales passées par `wp_add_inline_script( 'omtr-admin', 'window.omtrAdmin = …', 'before' )` :

```json
{
  "restNamespace": "omtr/v1",
  "wizardDone": false,
  "siteUrl": "https://exemple.test",
  "flagsUrl": "https://exemple.test/wp-content/plugins/omnium-translate/assets/flags/",
  "locale": "fr_FR",
  "version": "1.0.0"
}
```

`flagsUrl` est l’URL de base locale des drapeaux SVG embarqués dans le plugin, terminée
par `/`. L’interface l’utilise pour l’aperçu du sélecteur ; si elle est absente, l’aperçu
affiche uniquement le nom de la langue et ne charge aucune ressource distante.

Tant que `wizardDone` est faux, l'interface affiche l'assistant au lieu des onglets.

## Existant (ne pas modifier)

| Méthode | Route | Rôle |
| --- | --- | --- |
| POST | `/queue/(pause|resume)` | pause / reprise de la file |
| POST | `/estimate` | `{post_ids?, urls?, all?, languages?}` → `{pages, segments, words, input_tokens, output_tokens, provider, model, currency, cost, failures}` |
| GET/POST | `/glossary` | liste (`order_by`, `order`) / création `{term, target_lang, translation, rule}` |
| PUT/DELETE | `/glossary/{id}` | modification / suppression |
| POST | `/glossary/import` | import CSV (fichier `file`, ≤ 1 Mo) |
| GET | `/glossary/export` | export CSV |
| GET | `/segments` | éditeur de segments (filtres existants) |
| PUT | `/segments/{id}` | édition manuelle (`target_lang`, `text`) |
| POST/DELETE | `/segments/{id}/lock` | verrouiller / déverrouiller |
| POST | `/translate` | `{post_id}` → met une page en file |
| POST | `/crawl` | crawler interne (nouvelles ou modifiées) |

## Nouvelles routes

### `GET /settings` · `PUT /settings`

`PUT` accepte un objet partiel ; chaque champ est validé par le schéma REST puis sanitizé. Réponse identique à `GET` :

```json
{
  "settings": {
    "source_lang": "fr",
    "target_langs": ["en", "es"],
    "provider": "claude",
    "model": "",
    "auto_translate": false,
    "on_the_fly": false,
    "monthly_word_cap": 0,
    "hourly_quota": 0,
    "noindex_partial": false,
    "pref_cookie": false,
    "site_tone": "",
    "site_context": "",
    "flags": {"en": "gb"},
    "switcher": {
      "variant": "dropdown",
      "display": "flag-name",
      "position": "bottom-right",
      "dark_mode": false,
      "colors": {"bg": "#ffffff", "fg": "#0a0a0a", "hover": "#f4f4f5", "border": "#e4e4e7"},
      "radius": 8,
      "flag_size": 18
    },
    "uninstall_purge": false,
    "updates_disabled": false
  },
  "languages": [{"code": "en", "name": "English", "flags": ["gb", "us"]}],
  "flags": ["gb", "us", "fr", "…"]
}
```

Règles : `source_lang` et `target_langs` parmi `languages[].code`, source exclue des cibles ; `provider` parmi les fournisseurs enregistrés ; `model` parmi `models()` du fournisseur (vide = modèle recommandé) ; couleurs `#rrggbb` ; `radius` 0–24 ; `flag_size` 12–32 ; `position` parmi `bottom-right`, `bottom-left`, `top-right`, `top-left` ; `site_tone` ≤ 200 caractères, `site_context` ≤ 1000. `uninstall_purge` et `updates_disabled` sont stockés dans leurs options dédiées (`Options::UNINSTALL_PURGE`, `Options::UPDATES_DISABLED`).

### `GET /providers`

```json
[{
  "id": "claude",
  "label": "Anthropic (Claude)",
  "region": "non-eu",
  "free_tier": false,
  "key_url": "https://console.anthropic.com/settings/keys",
  "models": [{"id": "…", "label": "…", "recommended": true}],
  "pricing": {"<model>": {"input": 1.0, "output": 5.0, "source": "https://…", "verified_at": "2026-10-23"}},
  "key": {"set": true, "masked": "sk-…a1B2"}
}]
```

La clé déchiffrée n'est jamais renvoyée : seul `ApiKeyStore::masked()`.

### `PUT /providers/{id}/key` · `DELETE /providers/{id}/key`

`PUT {key}` : chaîne 10–300 caractères imprimables sans espace. Chiffre et stocke la clé. Réponse `{"set": true, "masked": "…"}`. `DELETE` → `{"set": false, "masked": ""}`.

### `POST /providers/{id}/test`

Teste la clé stockée (`test_key()`). Réponse `{"ok": bool, "message": string, "http_code": int}` ; message générique, jamais la réponse brute.

### `GET /dashboard`

```json
{
  "languages": [{"code": "en", "coverage": 87.5}],
  "queue": {"paused": false, "running": true, "pending": 12},
  "words": {"used": 1200, "cap": 0},
  "segments": {"total": 540, "auto": 400, "edited": 12, "locked": 3, "failed": 2},
  "log": [{"level": "error", "context": "provider", "message": "…", "created_at": "2026-10-24 10:00:00"}]
}
```

`log` : 20 dernières entrées, telles que stockées (déjà sans contenu de segment, clé, IP ni identifiant utilisateur).

### `POST /translate/all`

Met en file toutes les URLs publiques (`PublicContent::all()` puis `enqueue_urls`). Réponse `202 {"queued": true, "pages": n}` ou `409` avec `last_reason()` (verrou, plafond…). L'interface affiche d'abord `/estimate {all: true}` et demande confirmation.

### `POST /wizard/complete`

`{insert_menu: bool}` : marque l'assistant comme terminé (option `omtr_wizard_done`) et, si demandé, ajoute un élément sélecteur de langue au menu affecté au premier emplacement de thème enregistré (même élément que `MenuIntegration`). Réponse `{"wizard_done": true, "menu_inserted": bool}`.

### `GET /config/export` · `POST /config/import`

Export : `{"format": "omnium-translate-config", "version": 1, "settings": {…sans uninstall_purge, updates_disabled…}, "glossary": [{term, target_lang, translation, rule}]}`. **Jamais de clé API** (test unitaire `@group security`). Import : même format, corps JSON ≤ 1 Mo, validé champ par champ avec les règles de `PUT /settings` ; le glossaire importé remplace l'existant seulement si `replace_glossary: true`, sinon ajout sans doublon. Réponse `{"imported": true, "glossary": n}`.

## Interface (assets/src/admin)

- **Assistant** (3 écrans) : langues (source + cibles) → IA (choix du fournisseur avec badge UE / hors UE et avertissement palier gratuit, lien de création de clé, saisie, « Tester la clé » obligatoire avant de continuer) → estimation `/estimate {all:true}`, lancement `/translate/all` et case « Ajouter le sélecteur au menu principal » → `/wizard/complete`.
- **Onglets** : Tableau de bord · Traductions · Glossaire · Réglages.
  - Tableau de bord : couverture par langue, état de la file (pause / reprise), mots du mois, journal, boutons « Traduire les nouveautés » (`/crawl`) et « Traduire tout le site » (estimation + confirmation).
  - Traductions : éditeur de segments (liste filtrable, édition, verrouillage).
  - Glossaire : CRUD, import / export CSV, ton et contexte du site.
  - Réglages : langues et drapeaux, fournisseur / modèle / clé, plafonds et déclencheurs, SEO (`noindex_partial`), sélecteur (variante, affichage, couleurs, arrondi, taille drapeau, mode sombre) avec aperçu en direct et alerte si contraste texte/fond < 4,5:1, export / import de configuration, mises à jour, désinstallation.
- Toutes les chaînes via `@wordpress/i18n` (`__( '…', 'omnium-translate' )`), source en anglais ; traductions FR chargées par `wp_set_script_translations()` à partir d'un JSON généré dans `languages/`.
