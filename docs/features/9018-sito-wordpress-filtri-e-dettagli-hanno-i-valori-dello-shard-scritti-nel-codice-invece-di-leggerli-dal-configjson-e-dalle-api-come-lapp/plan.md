> Ticket: oc:9018

# Piano del child: filtri e dettagli con i dati dello shard

> **Per chi esegue:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (consigliato) o
> superpowers:executing-plans. I passi usano le caselle (`- [ ]`). **Nessun commit automatico:** i commit
> sotto sono istruzioni per il dev. Il piano del plugin (`wp-package/docs/features/9018-…/plan.md`) va
> eseguito prima: questo piano usa le sue chiavi `from_config`, `from_families`, `from_overlays` e il
> filtro `wmwp_detail_legacy_paths`.

**Obiettivo:** il child smette di scrivere valori, nomi e luoghi dello shard; sceglie solo quali filtri
mostrare, in che ordine e con che aspetto, e porta con un 301 i vecchi indirizzi di Drupal.

**Architettura:** le faccette di `inc/search.php` diventano richieste al plugin (`from_config`,
`from_families`, `from_overlays`) più le famiglie a stati del registro; i ripieghi che restano
(ordine delle famiglie fuori da `MAP.filters`, tipi e ruoli degli enti, classi di inclusività, le tre
tipologie di disabilità, nome dello stato di assenza) stanno ciascuno in un solo punto con il commento
«ripiego: si toglie quando lo shard…».

**Stack:** PHP 8.3 nel child di Impreza, test con `wp eval-file`, CSS del child.

**Spec:** `docs/features/9018-…/overview.md` e `notes.md` (sezioni «Decisioni» e «Challenge (24/11)»).

## Vincoli globali

- La fonte vince anche quando è sbagliata: nessun valore escluso o rinominato nel child (Rubens, 24/11).
- Il blocco dell'accessibilità del dettaglio resta com'è (tre tipologie accese o spente).
- Colore del tipo e fascia dei titoli del dettaglio: quelli del child (`forestas_dettaglio_colori()`).
- Ogni modifica di CSS o JS alza `Version` in `style.css` e si controlla il `?ver=` servito.
- Test: `docker exec wordpress-forestas wp eval-file wp-content/themes/forestas-child/tests/<file>.php --allow-root`,
  stampa `PASS` o `FAIL: …`.
- Commenti e documentazione in italiano, termini tecnici in inglese.

## Review Focus

1. **Link di Drupal con il solo testo** (`/cerca?search_api_fulltext=oliena`): il testo arriva nella
   ricerca nuova come `q=oliena`; test in Task 2.
2. **Tipo di percorso che il child non conosce** (un `route-type-x` nuovo in Nova): nome dello shard,
   colore neutro, breadcrumb generica, nessun «sentiero» in rosso; test in Task 3.
3. **Indirizzo Drupal codificato** (`/sentieri/s%E2%80%99araighinos-t-511b`): 301 alla pagina giusta;
   test in Task 4.
4. **Pagina inglese con nomi solo italiani** (difficoltà, layer): il nome porta `lang="it"`; test in Task 1.
5. **Filtro scelto che va a zero** (area + testo che la azzera): l'opzione resta e si toglie; prova nel
   browser in Task 6.

---

### Task 1: faccette dei percorsi e dei POI dallo shard

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-faccette)

**File:**
- Modifica: `themes/forestas-child/inc/search.php:27` (via `FORESTAS_SEARCH_PROVINCES`), `:475-485`
  (il child non traduce più i parametri del plugin), `:489-520` (via `forestas_search_route_type_labels()`
  e i nomi di `forestas_search_term_labels()` che vengono dallo shard), `:552-630`
  (`forestas_search_track_facets()`), `:655-700` (source dei POI, via `meta_facet` `comune`)
- Test: `themes/forestas-child/tests/search-facets.php`

**Interfacce:**
- Consuma dal plugin: faccette `{ key: 'activity', from_config: 'activities' }`,
  `{ key: 'type', from_config: 'layers' }`, `{ key: 'poi-type', from_config: 'poi_types' }`,
  `{ key_prefix: '', from_families: '-difficulty', exclude: ['inclusivity-difficulty'], order: FORESTAS_ORDINE_DIFFICOLTA }`,
  `{ from_overlays: true }`.
- Produce: costante `FORESTAS_ORDINE_DIFFICOLTA` (ordine di ripiego per famiglia, un solo punto:
  `hiking-difficulty` T, E, EE, EEA; le altre famiglie come le dà lo shard).
- Chiavi in inglese, uguali nelle lingue: `activity`, `type`, `poi-type`, `walkability`, `validation`,
  una per scala (`hiking-difficulty`, `mtb-difficulty`…), `layer` per i luoghi, `content`, `entity-type`,
  `role`.

- [x] **Step 1: test che fallisce** (`tests/search-facets.php`, con `config.json` e risultati finti):
  - le faccette dei percorsi, in ordine: tipo (`layers`), attività, percorribilità, validazione, una per
    scala di difficoltà, aree catastali, aree geografiche;
  - nessuna faccetta `provincia`, `comune`, `area`; nessuna chiave italiana;
  - attività con `trail` fra le opzioni (nessuna esclusione);
  - su pagina inglese, `labels` dei layer in italiano con `lang_labels` `it`;
  - i POI hanno tipo e le due faccette di luogo, nessun `meta_facet`;
  - la riga del percorso mostra l'ultimo elemento di `taxonomyWheres` (es. `Oliena`).
- [x] **Step 2:** lanciare; atteso `FAIL`.
- [x] **Step 3:** implementare.
- [x] **Step 4:** lanciare `tests/search-facets.php` e `tests/filtri-attivi.php`; atteso `PASS`.
- [x] **Step 5:** test sui dati veri: sul dev e su UAT, per 3 opzioni di ogni filtro, i risultati scelti
  hanno davvero quel valore (conteggio rifatto nel DB dello shard, con l'ora).
- [x] **Step 6 (commit del dev):** `feat(oc:9018): filtri di percorsi e POI dal config.json dello shard` — fatto nel commit unico `6c7183a` (24/11), su richiesta del dev

### Task 2: vecchi link della ricerca di Drupal

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-vecchi-link-di-drupal)

**File:**
- Modifica: `themes/forestas-child/inc/search.php:816-890` (`forestas_search_from_drupal()`, il
  `template_redirect` e `forestas_search_canonical_args()`)
- Test: `themes/forestas-child/tests/search-facets.php`

**Interfacce:**
- Produce: `forestas_search_layer_for_route_type( string $route_type ): ?int`: il layer di
  `MAP.filters.layers` che contiene più percorsi di quel `route-type` nei risultati di Elastic (lettura
  rara, cache di un'ora); la tabella «chiave di Drupal → `route-type-*`» resta nel codice.

- [x] **Step 1: test che falliscono:**
  - `?f[0]=tipo:sentiero&search_api_fulltext=oliena` → redirect a `?type=11&q=oliena` (layer finto 11);
  - `?search_api_fulltext=oliena` senza `f` → redirect a `?q=oliena`;
  - `?s=oliena` → redirect alla pagina di ricerca con `q=oliena`;
  - il canonical usa `q`, `type`, `pg`.
- [x] **Step 2:** lanciare; atteso `FAIL`.
- [x] **Step 3:** implementare.
- [x] **Step 4:** lanciare `tests/search-facets.php`; atteso `PASS`.
- [x] **Step 5 (commit del dev):** `feat(oc:9018): redirect dei vecchi link di ricerca ai parametri nuovi` — fatto nel commit unico `6c7183a` (24/11), su richiesta del dev

### Task 3: dettaglio con i dati dello shard

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-dettaglio)

**File:**
- Modifica: `themes/forestas-child/inc/dettaglio.php:181-200` (`forestas_dettaglio_tipo()`: tabella
  `route-type-*` → tipo in un solo punto, tipo sconosciuto), `:305-316` (colori, colore neutro),
  `:326-346` (ruoli: ripiego dichiarato, ruolo nuovo con il valore grezzo), `:439-450`
  (`forestas_dettaglio_gradi_accessibilita()`: sigle maiuscole `AT`, `AE`, `AEE`), `:967-990`
  (`forestas_dettaglio_difficolta()`: famiglie e nomi da `LABELS.taxonomies`), `:1169-1350` (mappa: il
  child non passa colori alla mappa; legenda del POI con i nomi dei tipi dei percorsi correlati;
  `forestas_dettaglio_anello()` da `roundtrip`), download (`forestas.downloads`: GPX, KML, GeoJSON), area
  sosta attrezzata con e senza prefisso `poi_type_`
- Modifica: `themes/forestas-child/inc/statuses.php:28-100` (nomi tolti dalla validazione e dai gradi,
  resta l'ordine; resta il nome dell'assenza «Non verificato / Not verified», ripiego dichiarato),
  `themes/forestas-child/inc/entes.php:693-750` (tipo nuovo: valore grezzo, senza categoria),
  `themes/forestas-child/style.css` (classe del tipo sconosciuto con il colore del testo, `Version`)
- Non si tocca: `forestas_dettaglio_accessibilita()` e il blocco di `wp-package/track.php:182-205`
- Test: `themes/forestas-child/tests/dettaglio.php`

- [x] **Step 1: test che falliscono** (proprietà finte prese dal dev, 24/11):
  - grado `inclusivity-difficulty-AE` → livello 2 su 3, nome dello shard;
  - `route-type-itinerary` → tipo `itinerario`; `route-type-x` → nome dello shard, classe del tipo
    sconosciuto, breadcrumb generica;
  - difficoltà per attività: una famiglia `ebike-difficulty` nuova compare con il nome di
    `LABELS.taxonomies`;
  - `roundtrip: true` con inizio e fine lontani → «Anello»; `roundtrip: false` vicini → niente;
  - `forestas.downloads` con `kml` e `geojson` → tre link;
  - `poi_type_equipped-rest-area` e `equipped-rest-area` → area sosta attrezzata in entrambi i casi;
  - ruolo `nuovo-ruolo` → compare con il valore grezzo;
  - le tre tipologie di disabilità restano tre, accese o spente come oggi.
- [x] **Step 2:** lanciare; atteso `FAIL`.
- [x] **Step 3:** implementare; alzare `Version` di `style.css`.
- [x] **Step 4:** lanciare `tests/dettaglio.php`, `tests/notices.php`, `tests/entes-view.php`; atteso `PASS`.
- [x] **Step 5 (commit del dev):** `feat(oc:9018): dettaglio con tipi, gradi, difficoltà e download dello shard` — fatto nel commit unico `6c7183a` (24/11), su richiesta del dev

### Task 4: indirizzi Drupal dei dettagli

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-indirizzi-drupal)

**File:**
- Crea: `themes/forestas-child/inc/redirect-drupal.php` (agganciato da `functions.php`)
- Test: `themes/forestas-child/tests/redirect-drupal.php`

**Interfacce:**
- Consuma: filtro del plugin `wmwp_detail_legacy_paths( array $paths, string $type, array $props )`.
- Produce: per ogni elemento con `forestas.url`, il percorso italiano (`sentieri/…`, `sentiero/…`,
  `itinerario/…`, `da-vedere/…`, `poi/…`, `node/<nid>`) e lo stesso con `en`.

- [x] **Step 1: test che fallisce:** `forestas.url` = `https://www.sardegnasentieri.it/sentieri/s%E2%80%99araighinos-t-511b`
  → due percorsi (`it` e `en`, stesso path); un url non di sardegnasentieri.it → nessun percorso; url vuoto
  → nessun percorso.
- [x] **Step 2:** lanciare; atteso `FAIL`.
- [x] **Step 3:** implementare.
- [x] **Step 4:** lanciare; atteso `PASS`.
- [ ] **Step 5:** sul dev e su UAT, dopo un giro dell'indice: script di prova che per ogni percorso e POI
  chiede l'indirizzo Drupal (italiano, inglese, i 39 percorsi e 37 POI codificati di UAT) e controlla
  301 → pagina corrente dell'elemento con lo stesso nid; atteso 0 errori. Prove di `/sentiero/` e
  `/itinerario/` sul dev. *Fatto in locale sui dati del dev (3512 indirizzi su 3512, 24/11); sui server dev e UAT
  dopo il deploy.*
- [x] **Step 6 (commit del dev):** `feat(oc:9018): redirect 301 dei vecchi indirizzi Drupal di percorsi e POI` — fatto nel commit unico `6c7183a` (24/11), su richiesta del dev

### Task 5: messa in opera su dev e UAT

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-messa-in-opera)

**File:**
- Modifica: `docker/scripts/init-wordpress.sh` (`WP_PACKAGE_REF` con l'hash del plugin), `docs/howto/messa-in-opera-wordpress-uat.md`
  e `-dev.md` (controllo che il `.env` dello shard non abbia `EC_TRACK_MODEL`; giro dell'indice dopo il
  deploy)

- [ ] **Step 1:** dev: copia di `wp_wmwp_detail_paths`, poi tolte le 170 righe con chiave `i:` (come su
  UAT il 23/11); conteggio prima e dopo. *Procedura scritta in `forestas/docs/howto/messa-in-opera-wordpress-dev.md`;
  si fa dopo il deploy sul dev.*
- [ ] **Step 2:** aggiornare `WP_PACKAGE_REF`; dopo il merge, `scripts/wordpress-up.sh` di forestas su dev
  e UAT; `wp cron event run` del giro del `config.json` e dell'indice. *`WP_PACKAGE_REF` si aggiorna con l'hash del
  plugin dopo il suo merge; il resto dopo il deploy.*
- [ ] **Step 3:** controllo su dev e UAT: i filtri della ricerca hanno i nomi dello shard, le aree
  catastali (7) e, sul dev, le aree geografiche (39); su UAT le geografiche non compaiono finché non c'è
  oc:9001. *Dopo il deploy.*
- [ ] **Step 4 (commit del dev):** `chore(oc:9018): plugin 0.6.0 e procedura di messa in opera` *La procedura è nel commit `47b45a7` di forestas (24/11); resta `WP_PACKAGE_REF`, dopo il merge del plugin.*

### Task 6: accessibilità e documentazione

**File:**
- Crea: `docs/knowledge/9018-sito-wordpress-filtri-e-dettagli-dai-dati-dello-shard.md`
- Modifica: `CLAUDE.md` (riga nella tabella «Conoscenza», trappola sul parametro `layer` e sui ripieghi)

- [x] **Step 1:** WCAG 2.2 AA e screen reader su ricerca e dettaglio, anche sotto i 600px e da loggato:
  `fieldset`/`legend` delle faccette, «Mostra altri» con 39 aree, nomi lunghi a capo, `lang="it"` sui nomi
  italiani della pagina inglese, opzione scelta a zero togliibile, linee della mappa con le tile bloccate.
- [x] **Step 2:** scrivere la pagina di conoscenza (come funziona, perché così, ripieghi e quando si tolgono)
  e la riga in `CLAUDE.md`.
- [x] **Step 3 (commit del dev):** `docs(oc:9018): pagina di conoscenza dei filtri dallo shard` — fatto nel commit unico `6c7183a` (24/11), su richiesta del dev

### Task 7: pulsante «Stampa» (aggiunto il 24/11 sera, chiesto dal dev)

> Aggiunto dopo l'esecuzione: forestas `develop` 1123b46 porta l'opzione di stampa dell'App. Dettagli in
> [notes.md](notes.md#pulsante-stampa-2411-sera).

**File:**
- Modifica: `themes/forestas-child/wp-package/track.php`, `wp-package/poi.php` (pulsante al posto del commento
  «torna con oc:8982»), `themes/forestas-child/tests/dettaglio.php`; `forestas/public/robots.txt`.
- Plugin: `wmwp_detail_print_url()` (overview e notes del plugin).

- [x] **Step 1:** test in `tests/dettaglio.php`: niente «Stampa» con la stampa spenta (traccia e POI), link
  `<origin>/print/ec-track/<id>?lang=it` e `ec-poi` con `rel="nofollow noopener"`, `target="_blank"` e testo per lo
  screen reader con la stampa accesa; visto fallire.
- [x] **Step 2:** pulsante nei due template, fra i download e «Condividi»; test PASS.
- [x] **Step 3:** `Disallow: /print/` nel `robots.txt` dello shard.
- [x] **Step 4 (commit del dev):** `feat(oc:9018): pulsante «Stampa» dalla stampa dell'app` — fatto nel commit unico `6c7183a` (24/11), su richiesta del dev
