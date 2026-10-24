# Audit confidentialité — déclencheurs éditeurs, édition visuelle, cookie, purge — 2026-10-24

Verdict : ✅ VALIDÉ

Périmètre : fichiers modifiés/ajoutés de la branche `omniumdigital/feat-editor-visual`
touchant le front, les cookies et la transparence (`src/Render/VisualEditor.php`,
`src/Render/Translator.php`, `src/Render/OutputBuffer.php`, `src/Switcher/{Switcher,LanguagePreference}.php`,
`assets/src/switcher/index.js`, `assets/src/visual-editor/`, `assets/src/editor/`,
mention cookie dans `src/Admin/Administration.php`). `src/Seo` hors périmètre (autre agent).

## Flux sortants

| Destination | Fichier:ligne | Données | Conforme |
| --- | --- | --- | --- |
| API REST du site lui-même | assets/src/visual-editor/index.js:29 | Texte du segment édité + langue + id (utilisateur `omtr_manage` uniquement) | ✅ Flux interne, pas un tiers |
| Aucun nouvel appel réseau | assets/src/switcher/index.js:16-25 | Pose de cookie locale uniquement | ✅ |

`grep https?://` sur les nouveaux JS front : aucune URL tierce ; les scripts sont servis depuis
le plugin (OMTR_URL), pas de CDN.

## Findings

| Sévérité | Fichier:ligne | Problème | Correctif proposé |
| --- | --- | --- | --- |
| Basse | src/Render/VisualEditor.php:62-69 | La configuration inline expose l’URL REST, le nonce, la langue et l’id du post aux gestionnaires de page | Accepté : réponse privée non cacheable, réservée `omtr_manage`, aucun identifiant visiteur |

Aucun finding Critique ou Haute.

## Checklist

### Étape 2 — Ce qui part vers l’IA
- OK — Aucun nouveau flux vers un fournisseur. Le mode édition visuel désactive même la capture
  « à la volée » (src/Render/OutputBuffer.php:55-58) : le HTML personnalisé d’un utilisateur
  connecté ne peut jamais devenir source de traduction ni partir à l’IA.

### Étape 3 — Stockage et journaux
- OK — Aucun nouveau log ni stockage ; `ids_by_hash` lit sans écrire, et sans cache persistant
  (commentaire explicite : les ids de segments ne doivent pas fuiter dans une page publique).

### Étape 4 — Côté visiteurs
- OK — Cookie `omtr_lang` : posé uniquement si l’option `pref_cookie` est activée (attribut
  `data-omtr-pref` absent par défaut, src/Switcher/Switcher.php:137), uniquement au clic
  (geste utilisateur), valeur = code langue configuré validé (`^[a-z]{2,3}$`),
  `SameSite=Lax`, `Secure` en HTTPS, `Path=/`, `Max-Age=31536000` (12 mois), aucun identifiant
  (assets/src/switcher/index.js:16-25 ; build ≤ 3 Ko : 638 octets).
- OK — Redirection serveur : uniquement page d’accueil source sans préfixe + arrivée sans
  référent interne + langue cible configurée ; 302 via `wp_safe_redirect` + `nocache_headers`
  (src/Switcher/LanguagePreference.php:26-46). Aucune redirection selon Accept-Language
  (grep : aucun usage de `HTTP_ACCEPT_LANGUAGE`). Aucune boucle : les visites internes et les
  pages profondes ne redirigent jamais (LanguagePreferenceTest).
- OK — Aucun `localStorage`/`sessionStorage` dans les JS front (grep vide).
- OK — Mode édition visuel jamais appliqué aux visiteurs : sans `omtr_manage` ou sans option,
  aucun marquage `data-omtr-segment` (VisualEditorSecurityTest) ; réponse `nocache_headers()` et
  sans écriture dans le cache objet de page (VisualEditor.php:32-36, Translator.php:81-87,
  test `test_authorized_request_marks_segments_without_cache_write`).
- OK — Aucune ressource externe en front : script vanilla 1 Ko servi par le plugin, sans dépendance.

### Étape 5 — Transparence pour le client
- OK — Politique de confidentialité (`wp_add_privacy_policy_content`) complétée d’une phrase
  décrivant le cookie fonctionnel optionnel, sa durée maximale (12 mois) et l’absence
  d’identifiant (src/Admin/Administration.php:498-508, traduction FR dans le .po).
- OK — Aucune télémétrie, aucun ping ; la purge de cache (TranslationCachePurger) n’émet que des
  hooks locaux (`omtr_translations_updated`, LiteSpeed, WP Rocket) sans donnée visiteur.
