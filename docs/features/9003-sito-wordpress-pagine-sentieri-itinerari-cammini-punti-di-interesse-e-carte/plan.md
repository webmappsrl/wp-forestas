> Ticket: oc:9003

# Pagine Sentieri, Itinerari, Cammini e Punti di interesse — piano di implementazione (wp-forestas)

> **Per chi esegue:** usare superpowers:subagent-driven-development o superpowers:executing-plans, task per task. I
> passi sono caselle (`- [ ]`). **Nessun commit automatico:** i passi «Commit» sono istruzioni per il dev.

**Goal:** le quattro pagine del menu mostrano sotto la testata la griglia del plugin, con il layer ricavato dal
route-type e le faccette di Forestas; la ricerca unica ha gli slider; `/punti-di-interesse/` si intitola «Punti di
interesse».

**Architecture:** un nuovo template di pagina `griglia` (us_content_template `template-pagina-griglia`: testata più
`[forestas_listing]`) per le quattro pagine; `inc/listings.php` registra con il filter `wmwp_grid_config` del plugin
la configurazione di ogni pagina (layer, faccette, card per pagina, nomi del conteggio). Il layer viene da
`wmwp_tracks_layer_for_term()` del plugin, sulla copia delle tracce; `forestas_search_layer_for_route_type()` usa la
stessa funzione.

**Tech Stack:** WordPress child di Impreza, PHP 8.3, test con `wp eval-file`, Playwright per le prove nel browser.

**Spec:** `docs/features/9003-sito-wordpress-pagine-sentieri-itinerari-cammini-punti-di-interesse-e-carte/overview.md`
e l'overview gemella in `wp-package`; il piano del plugin (`wp-package`, stessa cartella) definisce le interfacce
usate qui.

## Global Constraints

- Identificatori in inglese; commenti, docs e commit in italiano; scope `oc:9003`.
- Nessun id di layer né valore di termine scritto nel child; route-type solo da `FORESTAS_ROUTE_TYPE_KINDS`.
- Il child usa una funzione nuova del plugin solo se `function_exists()`: nessun aumento di
  `FORESTAS_WMWP_MIN_VERSION`.
- Testi con `forestas_testo( 'italiano', 'inglese' )`.
- Test che toccano option o transient veri li ripristinano anche su errore (`set_exception_handler`, come
  `themes/forestas-child/tests/search-facets.php`).
- Dopo ogni modifica di CSS o JS del child: Version di `style.css` alzata e `?ver=` servito controllato.
- `us_content_template` si modifica in locale e si esporta (`bin/wordpress-config.sh esporta`); `git diff config/`
  prima di ogni commit (repo pubblico).
- 9 card per pagina per i percorsi, 12 per i POI.

## Review Focus

- **Plugin vecchio con child nuovo** (fra git pull e `wordpress-up.sh`): la pagina mostra la sola testata, senza
  shortcode scritti — test nel Task 1.
- **Copia delle tracce assente o layer non trovato**: messaggio, mai tutti i percorsi — test nel Task 1.
- **Pagina inglese**: stesse griglie, testi dello shard nella lingua o in italiano con `lang="it"` — prova nel Task 5.
- **Titolo di `da-vedere` dopo una sincronizzazione da Drupal**: resta «Punti di interesse» — prova nel Task 2.
- **Ricerca unica con gli slider senza dati** (WordPress su dev): gli slider non compaiono — test nel Task 3.

---

### Task 1: configurazione delle griglie e shortcode `[forestas_listing]`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-slider-nelle-faccette-della-ricerca)

**Files:**
- Create: `themes/forestas-child/inc/listings.php`, `themes/forestas-child/tests/listings.php`
- Modify: `themes/forestas-child/functions.php` (require), `themes/forestas-child/inc/search.php`
  (`forestas_search_layer_for_route_type()` usa `wmwp_tracks_layer_for_term()` se c'è; resta la firma)

**Interfaces:**
- Consumes (plugin): `wmwp_tracks_layer_for_term( string $family, string $term, array $allowed ): ?int`,
  `wmwp_app_config()`, filter `wmwp_grid_config`, shortcode `[wmwp_tracks_grid id]` / `[wmwp_pois_grid id]`.
- Produces: `const FORESTAS_LISTING_PAGES` — chiave `_wpf_pagina` → tipo: `'sentieri' => 'sentiero'`,
  `'itinerari' => 'itinerario'`, `'cammini' => 'cammino'`, `'da-vedere' => 'poi'`.
- Produces: `forestas_listing_route_type( string $kind ): ?string` — inverso di `FORESTAS_ROUTE_TYPE_KINDS`.
- Produces: `forestas_listing_layer( string $kind ): ?int` — layer di «Tipo percorso» (`MAP.filters.layers`) con più
  tracce di quel route-type.
- Produces: `forestas_listing_track_facets( string $kind ): array` — `forestas_search_track_facets()` senza la
  faccetta `type`, più `['from_config' => 'track_distance']`, `['from_config' => 'track_duration']`, e per
  `itinerario` e `cammino` `['from_families' => 'itinerary-type']` dopo `activity`.
- Produces: `forestas_listing_config( ?array $config, string $id ): ?array` agganciata a `wmwp_grid_config` per gli id
  `sentieri`, `itinerari`, `cammini`, `da-vedere`; nomi del conteggio: «%s sentiero/sentieri», «%s itinerario/
  itinerari», «%s cammino/cammini», «%s punto di interesse/punti di interesse» (EN: trail/s, itinerary/itineraries,
  walk/s, point/s of interest).
- Produces: shortcode `[forestas_listing]` — dalla `_wpf_pagina` della pagina corrente sceglie l'id e chiama lo
  shortcode del plugin; plugin senza `wmwp_tracks_grid` → stringa vuota.

- [x] **Step 1: test che falliscono** (`tests/listings.php`, copia delle tracce e `config.json` di fixture del plugin
  al posto di quelli del sito, ripristinati alla fine):
  - `forestas_listing_route_type('cammino')` → `route-type-walk`;
  - `forestas_listing_layer('sentiero')` sulla fixture → il layer «Sentieri» della fixture;
  - `forestas_listing_track_facets('sentiero')` non ha `type`, ha `track_distance` e `track_duration`, non ha
    `itinerary-type`; `('itinerario')` ha `itinerary-type` subito dopo `activity`;
  - `forestas_listing_config(null,'sentieri')` ha `per_page 9`; `('da-vedere')` `per_page 12` e niente `layer`;
  - layer null (copia vuota) → config con `layer null`; shortcode senza la funzione del plugin → `''`.
- [x] **Step 2:** `wp eval-file wp-content/themes/forestas-child/tests/listings.php` → FAIL.
- [x] **Step 3:** implementare.
- [x] **Step 4:** test → PASS; `tests/search-facets.php` → PASS (stessa corrispondenza route-type → layer).
- [x] **Step 5: Commit**: `feat(oc:9003): griglie delle pagine d'elenco con il layer dal route-type`

### Task 2: template `griglia` e titolo di `da-vedere`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-template-griglia-scritto-a-mano-in-postjson)

**Files:**
- Modify: `config/pagine.json` (`"template": "griglia"` per `sentieri`, `itinerari`, `cammini`, `da-vedere`;
  `"titolo": { "it": "Punti di interesse", "en": "Points of interest" }` per `da-vedere`)
- Modify: `config/post.json` (us_content_template `template-pagina-griglia`: copia di `template-pagina-testata` con
  una seconda riga `[forestas_listing]`, fatta in locale e esportata)
- Modify: `docker/scripts/config/pagine.php` (righe 72–95: per una pagina con nid e `titolo` nella lingua, il titolo
  di `config/pagine.json` sostituisce quello di Drupal prima del calcolo dell'impronta `_wpf_testo_drupal`)

- [x] **Step 1:** `bin/wordpress-config.sh pagine` (prova, senza `--conferma`) → le quattro pagine cambiano template e
  `da-vedere` ha titolo «Punti di interesse»; nessun altro cambiamento.
- [x] **Step 2:** `bin/wordpress-config.sh pagine --conferma`, poi una seconda prova → nessuna differenza (l'impronta
  non fa riscrivere a ogni giro).
- [x] **Step 3:** nel browser `/sentieri/`, `/itinerari/`, `/cammini/`, `/punti-di-interesse/` e le `/en/…`: testata e
  griglia; breadcrumb dei dettagli dei POI «Home › Punti di interesse › …».
- [x] **Step 4:** `git diff config/` senza segreti.
- [x] **Step 5: Commit**: `feat(oc:9003): template griglia per le pagine d'elenco e titolo Punti di interesse`

### Task 3: slider nella ricerca unica

**Files:**
- Modify: `themes/forestas-child/inc/search.php` (`forestas_search_track_facets()`: aggiungere
  `['from_config' => 'track_distance']` e `['from_config' => 'track_duration']` dopo le difficoltà)
- Modify: `themes/forestas-child/tests/search-facets.php`

- [x] **Step 1: test che fallisce** — con la fixture UAT le faccette dei percorsi hanno `track_distance` e
  `track_duration` di tipo `slider`, dopo le difficoltà e prima delle aree.
- [x] **Step 2:** `wp eval-file wp-content/themes/forestas-child/tests/search-facets.php` → FAIL.
- [x] **Step 3:** implementare.
- [x] **Step 4:** test → PASS.
- [x] **Step 5: Commit**: `feat(oc:9003): slider di distanza e tempo nella ricerca unica`

### Task 4: stile di griglia e slider

**Files:**
- Modify: `themes/forestas-child/style.css` (`.wmwp-facet*` e `.forestas-filtri*` insieme; stile degli slider; Version
  1.18.2 → 1.19.0)

- [x] **Step 1:** stile della griglia a 3 colonne come le card della ricerca e delle liste del child; slider con le
  misure e i colori delle faccette; a 375px faccette ripiegabili come nella ricerca.
- [x] **Step 2:** Playwright a 1440px e 375px su `/sentieri/` e `/cerca-nel-sito/?tab=trails`: `?ver=1.19.0` servito,
  schermate confrontate con la ricerca unica.
- [x] **Step 3: Commit**: `feat(oc:9003): stile delle griglie e degli slider`

### Task 5: prova completa e accessibilità

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-prove-insieme-al-plugin)

- [x] **Step 1:** WordPress locale su dev: `wp wmwp tracks-copy refresh`; le quattro pagine in IT e EN; con dev gli
  slider non compaiono su `/cammini/`.
- [x] **Step 2:** `wp wmwp tracks-copy load /Volumes/WebmappDev/tmp-9003/es-uat.json`: su `/sentieri/` gli slider ci
  sono; «fino a 10 km» (`?track_distance=-10`) tiene i sentieri sotto 1 km; cursore alto a destra = nessun massimo;
  conteggi delle faccette uguali a quelli della ricerca unica con lo stesso filtro (`?tab=trails&type=<layer>`).
- [x] **Step 3:** accessibilità come il Task 8 del piano del plugin, sulle quattro pagine: tastiera, `ariaSnapshot`,
  VoiceOver in Safari, Chromium, Firefox e WebKit, 1440px e 375px; correggere ciò che non va; esito in `notes.md`.
- [x] **Step 4:** `wp wmwp tracks-copy refresh` per tornare ai dati di dev.

### Task 6: messa in opera e documentazione

**Files:**
- Modify: `docker/scripts/init-wordpress.sh` (`WP_PACKAGE_REF` = hash del merge del plugin su `main`, come per oc:9018, letto con
  `git rev-parse`)
- Create o Modify: `docs/knowledge/` (pagina delle pagine d'elenco: griglie, copia, layer dal route-type, slider,
  prova in locale con `tracks-copy load`), `docs/howto/` (messa in opera: `scripts/wordpress-up.sh`, poi
  `bin/wordpress-config.sh pagine --conferma` e `applica --conferma` per il template), `docs/howto/ricreare-wordpress-
  da-zero.md` se cambia la sequenza, `CLAUDE.md` (riga in «Conoscenza»), `notes.md`

- [x] **Step 1:** scrivere docs e procedura; controllo di forma con `wm-context-guard`.
- [x] **Step 2: Commit**: `docs(oc:9003): pagine d'elenco, messa in opera e plugin 0.7.0`
- [ ] **Step 3:** dopo il merge di `develop` in `wp-forestas`, puntatore del submodule in `forestas` solo su
  indicazione del dev (commit a sé, nella PR del ticket).
