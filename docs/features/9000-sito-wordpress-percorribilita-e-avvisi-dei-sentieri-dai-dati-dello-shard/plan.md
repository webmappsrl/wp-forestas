> Ticket: oc:9000

# Sito WordPress: percorribilità e avvisi dei sentieri, dai dati dello shard — piano

> **Per chi esegue:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (consigliato) o
> superpowers:executing-plans, task per task. I passi usano le caselle (`- [ ]`). **Nessun `git commit`, `git add`,
> `git push`**: i commit qui sotto sono istruzioni per il dev, che li fa dopo la review.

**Obiettivo:** il sito mostra percorribilità e avvisi dello shard, ha la pagina `/avvisi`, filtri per stato e i POI
nella ricerca unica; prima, le letture di dati nati in `wm-package` passano dal child al plugin.

**Architettura:** ciò che legge dati di `wm-package` sta in `wp-package` (funzioni che restituiscono dati pronti,
nessun markup); ciò che legge dati di forestas e tutta la grafica del wireframe stanno nel child `forestas-child`,
che sovrascrive i template del plugin. Il registro degli stati del plugin dà a ogni stato livello e totale per le
tacche; il child ci aggiunge le famiglie di forestas.

**Tecnologie:** WordPress, PHP 8.3, WPML, Impreza; JS senza build per la ricerca (`node --test` per i test); WP-CLI.

**Spec:** le due overview:
`wp-forestas/docs/features/9000-sito-wordpress-percorribilita-e-avvisi-dei-sentieri-dai-dati-dello-shard/overview.md`
e `wp-package/docs/features/9000-sito-wordpress-percorribilita-e-avvisi-dei-sentieri-dai-dati-dello-shard/overview.md`.

**Repo e branch** (già creati da `develop`): `feature/oc-9000-sito-wordpress-percorribilita-e-avvisi-dei-sentieri-dai-dati-dello-shard`
in `wp-forestas` (`/Volumes/WebmappDev/Sites/Webmapp/Laravel/forestas/wp-forestas`) e in `wp-package`
(`/Volumes/WebmappDev/Sites/Webmapp/Wordpress plugin/wp-package`). In `forestas` solo il puntatore di `wp-forestas`,
su indicazione del dev.

## Vincoli globali

- Identificatori, nomi di file nuovi, chiavi in inglese; commenti, docblock e documentazione in italiano. I file
  esistenti con nome italiano (`inc/dettaglio.php`…) non si rinominano.
- Testi del child: `forestas_testo( 'it', 'en' )`. Testi del plugin: `__( 'English', 'wp-package' )` con traduzione
  italiana in `languages/` (`.po`, `.mo`, JSON degli script); `wp i18n` si lancia su una **copia** del plugin, mai
  sul clone montato in sola lettura (`compose.local.yml`).
- Test del child: `docker exec -w /var/www/html wordpress-forestas wp --allow-root eval-file wp-content/themes/forestas-child/tests/<file>.php` → `PASS`.
- Test PHP del plugin: `docker exec -w /var/www/html/wp-content/plugins/wp-package wordpress-forestas wp --allow-root eval-file tests/php/<file>.php` → `PASS`.
- Test JS del plugin: `cd "/Volumes/WebmappDev/Sites/Webmapp/Wordpress plugin/wp-package" && node --test tests/js/*.test.js` → tutti `ok`.
- Il WordPress locale (`http://localhost:8090`) legge lo shard dev (`https://forestas.dev.maphub.it`, app id 1):
  nessuna scrittura sullo shard.
- Dopo ogni modifica di CSS o JS del child si alza `Version` in `themes/forestas-child/style.css` e si controlla il
  `?ver=` servito.
- Rotta nuova del child: si alza `FORESTAS_REGOLE_VERSIONE` in `inc/news.php`.
- Gli indirizzi dei dettagli non cambiano (oc:8983).

## Punti da guardare in review

1. **Traccia senza `properties.walkability` o senza `taxonomies`** (513 su 773 sul dev): niente avvisi PHP, niente
   fascia, indicatore «Sconosciuta» 0 su 3. Test nel task 5 e 6.
2. **Termine che il registro non conosce** (uno stato aggiunto in Nova prima dell'API): stato scritto a parole, 0
   tacche, nessun errore. Test nel task 4.
3. **`not_accessible` come stringa** (`"1"`, `"true"`, `"false"`, `"0"`): acceso solo per `true`, `1`, `"1"`,
   `"true"`. Test nel task 5.
4. **`api/v1/avvisi` che risponde 500 o JSON non valido** con e senza una copia precedente: copia servita, oppure
   messaggio d'errore e non «nessun avviso». Test nel task 7.
5. **Avvisi senza `published_at`**: in fondo con tutti e due gli ordinamenti. Test nel task 7.

---

### Task 1: fotografia «prima» dei dettagli e degli indirizzi

**Repo:** nessuno (file fuori da git, sul disco esterno).

**File:**
- Crea: `/Volumes/WebmappDev/tmp/oc9000/snapshot.sh` (non versionato; si cancella a fine lavoro)

- [x] **Passo 1:** script che, dato `prima` o `dopo` come argomento, scarica con `curl` da `http://localhost:8090`
      l'HTML di un campione fisso di dettagli e lo salva in `/Volumes/WebmappDev/tmp/oc9000/<argomento>/`, più
      l'elenco degli indirizzi dell'indice (`wp eval 'echo wp_json_encode( wmwp_detail_index()["urls"] );'`).
      Campione, in italiano e in `/en/`: tracce 99 e 27 (percorribili), 2 tracce senza percorribilità, 1 cammino,
      1 itinerario, 2 POI di cui uno con galleria e luoghi; gli id si scelgono una volta dall'indice di ricerca del
      dev e si scrivono nello script. Prima del confronto lo script toglie le parti che cambiano a ogni richiesta
      (nonce, `?ver=` uguali, timestamp).
- [x] **Passo 2:** `snapshot.sh prima`. Atteso: 16 file HTML non vuoti e `urls.json`.

### Task 2: letture del dettaglio nel plugin

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-letture-del-dettaglio-nel-plugin)

**Repo:** `wp-package`.

**File:**
- Crea: `includes/detail-data.php`; registrato in `index.php` accanto agli altri `includes/detail-*.php`
- Test: `tests/php/detail-data.php`

**Interfacce prodotte** (`$lang` è il codice WPML della pagina):
- `wmwp_text( $value, string $lang ): array{text: string, lang: ?string}`: stringa → `lang` null; oggetto per lingua →
  prima `$lang`, poi `it`, poi la prima chiave non vuota; un valore lista si unisce con `"\n"`. È la logica di oggi di
  `forestas_dettaglio_testo_lingua()`, che resta nel child come involucro.
- `wmwp_detail_blocks( array $properties, string $lang ): list<array{title: string, content: string, lang: ?string}>` — blocchi `info` di `config_detail`, righe senza testo saltate.
- `wmwp_detail_gallery( array $properties ): list<array{url: string, thumbnail: string, caption: string, author: string, credits: string}>`
- `wmwp_detail_related_pois( array $properties, string $lang ): list<array{id: int, name: string, type: ?string, image: ?string}>`
- `wmwp_detail_stats( array $properties ): array{distance: ?float, ascent: ?float, descent: ?float, ele_min: ?float, ele_max: ?float, duration_forward: ?float}` — `null` per il valore assente o 0 dove oggi il child mostra il trattino.
- `wmwp_detail_places( array $properties, string $lang ): list<array{name: string, admin_level: ?int}>` — da `taxonomies.where` (tracce) e da `taxonomy_where` con `_admin_level` (POI).
- `wmwp_detail_poi_types( array $properties, string $lang ): list<array{identifier: string, name: string, lang: ?string}>`

- [x] **Passo 1:** test `tests/php/detail-data.php` con le proprietà di una traccia e di un POI ridotte da quelle
      del dev (stesso stile di `tests/php/detail-render.php`): un caso per funzione, più il caso vuoto (`[]`) che
      restituisce liste vuote e `null` senza avvisi PHP; `wmwp_text( ['en' => '', 'it' => 'Ciao'], 'en' )` dà
      `['text' => 'Ciao', 'lang' => 'it']`.
- [x] **Passo 2:** lanciarlo: FAIL, funzioni non definite.
- [x] **Passo 3:** implementare le funzioni portando la logica dalle funzioni del child (`inc/dettaglio.php`:
      `forestas_dettaglio_blocchi()`, `_galleria()`, parte POI di `_correlati()`, `_tabelle()` per i valori,
      `_luoghi()` e `_poi()` per la lettura grezza dei luoghi, `_poi()` per i tipi). Nessun testo visibile: le regole
      di Forestas (aree che iniziano con «Area», etichette delle tabelle) restano nel child.
- [x] **Passo 4:** test: PASS; tutti gli altri `tests/php/*.php`: PASS.
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `refactor(oc:9000): letture del dettaglio dei dati di wm-package nel plugin`

### Task 3: famiglia del tipo delle tracce indicata dal tema, e il child sulle letture del plugin

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-tipo-delle-tracce-dal-tema-e-child-sulle-letture-del-plugin)

**Repo:** `wp-package` e `wp-forestas`.

**File:**
- Modifica (plugin): `includes/detail-index.php:332`, `includes/detail-base.php`, `includes/search.php` (config della
  source `tracks`), `assets/js/source-tracks.js:97`
- Modifica (child): `inc/dettaglio.php` (le sei letture), `wp-package/track.php`, `wp-package/poi.php`, `functions.php`
- Test: `tests/php/detail-index.php`, `tests/js/source-tracks.test.js` (plugin); `tests/dettaglio.php` (child)

**Interfacce:**
- Produce (plugin): `wmwp_track_kind_taxonomy(): string` = `apply_filters( 'wmwp_track_kind_taxonomy', '' )`; il
  `kind` dell'indice e il badge della card leggono `terms.<famiglia>`; con `''` nessun tipo e base `track`. La source
  `tracks` passa al JS `kind_field` (`'terms.route-type'` per Forestas, `''` di default).
- Consuma (child): le funzioni del task 2; `add_filter( 'wmwp_track_kind_taxonomy', fn() => 'route-type' )`.

- [x] **Passo 1:** test del plugin: con il filtro vuoto l'indice non ha `kind` e la base è `track`; con `route-type`
      l'indice ha il `kind` di oggi. Test JS: `toCard` senza `kind_field` non ha `typeKey` da `route-type`.
- [x] **Passo 2:** test: FAIL.
- [x] **Passo 3:** implementare nel plugin; nel child sostituire le letture con le funzioni del plugin (le funzioni
      del child restano con lo stesso nome e la stessa struttura di ritorno, così template e test non cambiano) e
      aggiungere il filtro.
- [x] **Passo 4:** test del plugin (PHP e JS) e del child: PASS.
- [x] **Passo 5 — controllo che nulla si sia rotto:** `snapshot.sh dopo`, poi
      `diff -r /Volumes/WebmappDev/tmp/oc9000/prima /Volumes/WebmappDev/tmp/oc9000/dopo`. Atteso: nessuna
      differenza; ogni differenza si spiega o si corregge prima di andare avanti. **Fermarsi e mostrare l'esito al
      dev.**
- [x] **Passo 6 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** plugin `refactor(oc:9000): famiglia del tipo delle tracce indicata dal
      tema`; child `refactor(oc:9000): letture del dettaglio dal plugin`.

### Task 4: registro degli stati nel plugin

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-registro-degli-stati)

**Repo:** `wp-package`.

**File:**
- Crea: `includes/status.php`
- Test: `tests/php/status.php`

**Interfacce prodotte:**
- `wmwp_walkability_taxonomy(): string` = `apply_filters( 'wmwp_walkability_taxonomy', 'walkability-status' )`
- `wmwp_status_families(): array<string, array{absent: ?string, states: list<array{identifier: string, name: array<string, string>}>}>`
  = `apply_filters( 'wmwp_status_families', [ <percorribilità> ] )`. Percorribilità: stati
  `<famiglia>-unknown` («Sconosciuta» / «Unknown», `absent`), `-not-walkable` («Non percorribile» / «Not walkable»),
  `-partially-walkable` («Parzialmente percorribile» / «Partially walkable»), `-walkable` («Percorribile» /
  «Walkable»), come `WalkabilityTaxonomyInstaller::TERMS` di `wm-package`.
- `wmwp_status_level( string $family, ?string $identifier ): array{level: int, total: int, known: bool}` — `total` =
  stati che non sono `absent`; `level` = posizione fra quelli (da 1); `absent`, `null`, famiglia o stato sconosciuti
  → `level` 0; `known` false per un identifier che il registro non ha.
- `wmwp_status_name( string $family, string $identifier, string $lang ): array{text: string, lang: ?string}`

- [x] **Passo 1:** test: percorribilità `not-walkable` → `[1, 3, true]`, `partially-walkable` → `[2, 3, true]`,
      `walkable` → `[3, 3, true]`, `unknown` e `null` → `[0, 3, …]`, `walkability-status-nuovo` → `[0, 3, false]`; una
      famiglia aggiunta con il filtro e uno stato in più cambia `total`; il filtro sul nome della famiglia cambia i
      prefissi.
- [x] **Passo 2:** FAIL. **Passo 3:** implementare. **Passo 4:** PASS.
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `feat(oc:9000): registro degli stati delle famiglie`

### Task 5: percorribilità e avviso dal dettaglio nel plugin

**Repo:** `wp-package`.

**File:**
- Crea: `includes/detail-status.php`
- Modifica: `templates/track.php` (template di base)
- Test: `tests/php/detail-status.php`

**Interfacce prodotte:**
- `wmwp_walkability( array $properties, string $lang ): array{identifier: ?string, name: string, name_lang: ?string, level: int, total: int, absent: bool, note: string, note_lang: ?string, updated_at: ?string}`
  — `updated_at` = `properties.walkability.last_update` solo se `properties.walkability.user` non è `null`; `absent`
  vero senza termine o con lo stato `absent`; nome dal termine, con ripiego sul registro.
- `wmwp_track_notice( array $properties, string $lang ): ?array{message: string, lang: ?string, updated_at: ?string}`
  — `null` se `not_accessible` non è `true`, `1`, `"1"`, `"true"` (minuscole) o se il messaggio è vuoto in tutte le
  lingue; `updated_at` da `not_accessible_updated_at`.

- [x] **Passo 1:** test: traccia 99 del dev (`user` null) → `updated_at` null; stessa traccia con `user` 3 →
      `updated_at` valorizzato; nessuna `walkability` né `taxonomies` → `absent` true, `level` 0, nessun avviso PHP;
      `not_accessible` `"false"`, `"0"`, `false` → `null`; `"true"` con messaggio solo italiano e `$lang` `en` →
      `lang` `it`.
- [x] **Passo 2:** FAIL. **Passo 3:** implementare; il template di base mostra in testo semplice l'avviso (se c'è) e
      la percorribilità (se non `absent`), con la data solo se c'è. **Passo 4:** PASS.
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `feat(oc:9000): percorribilità e avviso del dettaglio nel plugin`

### Task 6: dettaglio del child — tacche, fascia della percorribilità, avviso, via segnalazioni e recensioni

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6-dettaglio-del-child)

**Repo:** `wp-forestas`.

**File:**
- Crea: `themes/forestas-child/inc/statuses.php` (incluso da `functions.php`)
- Modifica: `inc/dettaglio.php` (`forestas_dettaglio_indicatori()`, livelli della validazione e dell'accessibilità),
  `wp-package/track.php` (fascia, avviso, indicatori; tolte righe 109, 133, 433-435), `assets/css/dettaglio.css`,
  `style.css` (Version)
- Test: `tests/dettaglio.php`

**Interfacce:**
- Produce: `forestas_status_families( array $families ): array`, agganciata a `wmwp_status_families`: aggiunge
  `validation-status` (`absent` `validation-status-unverified` «Non verificato» / «Not verified»;
  `-under-review` «In revisione-validazione» / «Under review»; `-pre-registration` «In pre-accatastamento o
  manutenzione» / «Pre-registration or maintenance»; `-validated` «Validato» / «Validated»; `-certified`
  «Certificato» / «Certified») e `inclusivity-difficulty` (`absent` null; `-at` AT, `-ae` AE, `-aee` AEE).
- Consuma: `wmwp_status_level()`, `wmwp_status_name()`, `wmwp_walkability()`, `wmwp_track_notice()`.

- [x] **Passo 1:** test del child: `forestas_dettaglio_indicatori()` dà percorribilità `2 su 3` per
      `partially-walkable`, `0 su 3` «Sconosciuta» senza termine; validazione `4 su 4` per `certified`, `0 su 4` per
      `unverified`; accessibilità `3 su 3` per AEE; l'HTML del dettaglio di un itinerario con avviso contiene la
      fascia «Avviso» e non la fascia della percorribilità; nessun dettaglio contiene «Segnala», «Recensioni» o
      «Opinioni e recensioni».
- [x] **Passo 2:** FAIL.
- [x] **Passo 3:** implementare. Grafica dalla tavola del wireframe (markup e CSS di `index.html` dell'artifact
      `https://claude.ai/artifact/4T3ZRSP1LCrbCuJdNuEGXQ`, letto con `Artifact read` `path: "index.html"`), colori di
      «Fascia della percorribilità» in `implementazione-wordpress.md` di oc:8783; titolo della fascia «<stato>, stato
      aggiornato il <data>» con `<time datetime>` e data nell'ora italiana, solo se `updated_at`; riga «Nota: …»;
      tacche `aria-hidden` e testo per lo screen reader «livello N su M, <stato>»; legenda della «i» dagli stati del
      registro. Fascia «Avviso» al posto del segnaposto, anche negli itinerari, con `lang` sul testo di ripiego.
- [x] **Passo 4:** test: PASS; Version alzata; controllo nel browser su `localhost:8090` di un sentiero percorribile,
      uno senza percorribilità e un itinerario, a 1440px e 375px. I casi «parzialmente percorribile» e «con avviso»
      che oggi il dev non ha si provano con un mu-plugin locale non versionato (`wp-content/mu-plugins/zz-prova-9000.php`,
      filtro `http_response` sul JSON di una traccia, come `zz-prova-config-detail.php`), cancellato a fine prova.
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `feat(oc:9000): percorribilità, avviso e tacche dinamiche nel dettaglio`

### Task 7: pagina `/avvisi`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-7-pagina-avvisi)

**Repo:** `wp-forestas`.

**File:**
- Crea: `themes/forestas-child/inc/notices.php`, `templates/notices-list.php`, `assets/css/notices.css`
- Modifica: `functions.php`, `inc/news.php` (`FORESTAS_REGOLE_VERSIONE`), `style.css` (Version)
- Test: `tests/notices.php`

**Interfacce prodotte:**
- `forestas_notices(): array{ok: bool, items: list<array{track_id: int, ref: string, name: array<string, string>, message: array<string, string>, published_at: ?string}>}`
  — `api/v1/avvisi` dall'origine del plugin (`wmwp_configured_api_urls()['origin']`), transient di 60 secondi,
  ultima copia buona nell'opzione `forestas_notices_copy` (non autoload); `ok` false solo senza nessuna copia.
- `forestas_notices_sorted( array $items, string $order ): array` — `$order` `recent` | `oldest`; senza
  `published_at` sempre in fondo.
- Rotte `^avvisi/?$` e `^avvisi/page/([0-9]+)/?$` (query var `forestas_notices`), `/en/avvisi` come `/en/enti`;
  parametro `?ordine=meno-recenti` (default più recenti); 10 avvisi per pagina; selettore di lingua WPML e `hreflang`
  come in `inc/entes.php`.

- [x] **Passo 1:** test: risposta finta (`pre_http_request`) con tre avvisi, uno senza data → ordinamenti corretti e
      quello senza data in fondo; risposta 500 con copia → `ok` true e copia; risposta 500 senza copia → `ok` false;
      JSON non valido → come il 500; la pagina con `ok` false mostra il messaggio d'errore, con zero avvisi «Al
      momento non ci sono avvisi attivi»; il link di ogni avviso è l'indirizzo dell'indice dei dettagli
      (`wmwp_detail_url()`), e un `track_id` assente, 0 o che l'indice non ha dà il nome senza link (review interna,
      18/11; prima `wmwp_detail_link_template( 'track' )`); la data si scrive «aggiornato il …».
- [x] **Passo 2:** FAIL. **Passo 3:** implementare (grafica dalla tavola 10 del wireframe, paginazione con il markup
      di quella della ricerca, ordinamento come link con `aria-current`). **Passo 4:** PASS; `/avvisi` e `/en/avvisi`
      rispondono 200 dopo il cambio di `FORESTAS_REGOLE_VERSIONE`.
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `feat(oc:9000): pagina degli avvisi`

### Task 8: valore per i risultati senza campo nel motore dei filtri

**Repo:** `wp-package`.

**File:**
- Modifica: `assets/js/search-logic.js` (`filterHits`, `computeFacets`)
- Test: `tests/js/search-logic.test.js` (la fixture ha già un percorso senza `terms`, il 529)

**Interfaccia prodotta:** proprietà facoltativa `missing: string` di una faccetta `terms`: un risultato senza il campo
vale come se avesse quel valore, per il filtro e per i conteggi.

- [x] **Passo 1:** test: con `missing: 'walkability-status-unknown'` il 529 conta in quell'opzione e si trova
      sceglendola; senza `missing` il comportamento è quello di oggi.
- [x] **Passo 2:** FAIL. **Passo 3:** implementare. **Passo 4:** tutti i test JS: PASS.
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `feat(oc:9000): valore per i risultati senza il campo di un filtro`

### Task 9: filtri «Percorribilità» e «Validazione catastale» dal registro

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-9-filtri-dal-registro)

**Repo:** `wp-forestas`.

**File:**
- Modifica: `themes/forestas-child/inc/search.php` (`forestas_search_track_facets()`,
  `forestas_search_term_labels()`)
- Test: `tests/search-facets.php` (nuovo)

**Interfaccia prodotta:** `forestas_search_status_facet( string $family, string $key, string $label ): array` —
faccetta `terms` su `terms.<famiglia>`, un gruppo per stato del registro nell'ordine del registro, `missing` = lo
stato `absent` della famiglia.

- [x] **Passo 1:** test: «Percorribilità» ha quattro gruppi in ordine Sconosciuta, Non percorribile, Parzialmente
      percorribile, Percorribile e `missing` `walkability-status-unknown`; «Validazione catastale» ha i cinque stati e
      `missing` `validation-status-unverified`; in `inc/search.php` nessuna occorrenza di `validation-status-walkable`
      né di `validation-status-not-walkable`.
- [x] **Passo 2:** FAIL. **Passo 3:** implementare. **Passo 4:** PASS; nel browser, `/cerca-nel-sito` col filtro
      «Percorribile» trova 219 percorsi sul dev (18/11) e «Sconosciuta» 513.
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `feat(oc:9000): filtri di percorribilità e validazione per stato`

### Task 10: sorgente dei POI nel plugin

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-10-sorgente-dei-poi-nel-plugin)

**Repo:** `wp-package`.

**File:**
- Modifica: `includes/detail-index.php` (`wmwp_detail_fetch_items()`: dallo stesso download di `pois.geojson`
  scrive anche l'opzione `wmwp_poi_search_copy`, non autoload, con id, nome, `searchable`, immagine, inizio del
  testo, tipi; stesso controllo che scarta un calo sotto la metà), `includes/search.php` (source `pois`),
  `index.php` (disattivazione), `uninstall.php`, `templates/card.php` se serve
- Crea: `includes/search-pois.php`, `assets/js/source-pois.js`
- Test: `tests/php/search-pois.php`, `tests/js/source-pois.test.js`

**Interfacce prodotte:**
- `GET /wp-json/wmwp/v1/pois?q=&page=&per_page=&lang=&poi_type[]=` → `{ total, items: Card[], facets: FacetView[] }`
  nella forma di `search.js` (righe 6-13) e di `L.computeFacets()`; `q` al massimo 200 caratteri, `per_page` al
  massimo 48; ricerca su `searchable` nella lingua con ripiego sull'italiano; prima chi ha il testo nel nome; un POI
  con più tipi conta in ognuno; nomi dei tipi con ripiego e `lang`.
- Source JS `pois` registrata con `window.wmWpSearch.registerSource( 'pois', { search } )`; Card con `typeKey`
  `poi`, link da `wmwp_detail_link_template( 'poi' )`.

- [x] **Passo 1:** test PHP: copia costruita da un `pois.geojson` finto (`pre_http_request`); «Bolotana» trova la
      cascata solo con la descrizione italiana o col ripiego; un POI col nome che contiene il testo viene prima di uno
      che lo ha solo nel testo; faccetta `poi-type` con conteggi; `q` di 500 caratteri tagliato; senza copia la
      risposta è un errore (non `total` 0). Test JS: `search()` traduce la risposta in Card.
- [x] **Passo 2:** FAIL. **Passo 3:** implementare; testi nuovi con `__()`. **Passo 4:** PASS (tutti i test PHP e JS).
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `feat(oc:9000): punti di interesse nella ricerca unica`

### Task 11: POI nella ricerca di Forestas

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-11-poi-nella-ricerca-di-forestas)

**Repo:** `wp-forestas`.

**File:**
- Modifica: `themes/forestas-child/inc/search.php` (ordine dei tab e faccetta `poi-type` con il filtro
  `wp_package_search_sources`; redirect dei vecchi link `cerca_contenuto_nel_sito:poi` al tab dei POI),
  `wp-package/card.php` (card del POI come il risultato di ricerca di Drupal: immagine, nome, inizio del testo, tipo
  di contenuto «Punti di interesse», nessuna tipologia), `style.css` (Version) se cambia il CSS
- Test: `tests/search-facets.php`

- [x] **Passo 1:** test: la source `pois` è attiva con la faccetta `poi-type`; il redirect porta a `?tipo=pois` (nome
      del parametro del tab come nella config della ricerca).
- [x] **Passo 2:** FAIL. **Passo 3:** implementare. **Passo 4:** PASS; nel browser «nuraghe» mostra il gruppo dei POI
      in «Tutti» e il tab con i conteggi per tipo; la card porta al dettaglio del POI.
- [x] **Passo 5 (commit, a cura del dev; riunito nel commit del repo, vedi notes):** `feat(oc:9000): punti di interesse nella ricerca del sito`

### Task 13: icona del sito dallo script (aggiunta il 19/11)

**Repo:** `wp-forestas` (script di configurazione e `config/`).

- [x] **Passo 1:** test `docker/scripts/config/tests/icona-sito.php` su una cartella di config temporanea: in prova
      nessun allegato creato e una differenza; con la conferma allegato creato con `_wp_forestas_file` e l'impronta,
      `site_icon` impostato; secondo giro nessuna differenza e nessun allegato in più; file cambiato → allegato nuovo;
      file mancante → avviso e falso; export dell'allegato → `@file:media/icona-sito.png` e file uguale; un'icona
      caricata dal pannello si esporta con lo stesso nome. Rimette `site_icon` e cancella gli allegati di prova.
- [x] **Passo 2:** FAIL. **Passo 3:** `allegati.php` (ricerca, import con le misure di `WP_Site_Icon`, export,
      descrizione per il ritratto), `site_icon` in `WPF_OPZIONI_SITO` e `WPF_IMPREZA_COPIE`, `@file:` in
      `wpf_sito_applica()`, export in `export.php` e cartella `media/` in `export_in` di `bin/wordpress-config.sh`;
      `config/media/icona-sito.png` dalla «S» del logo del child su un cerchio bianco, proporzioni della favicon di
      Drupal.
- [x] **Passo 4:** PASS; `applica` in prova e con `--conferma` sul locale, `<link rel="icon">` nella pagina;
      `esporta` senza differenze in `git diff config/` oltre all'icona; howto `ricreare-wordpress-da-zero.md` e
      README aggiornati.

### Task 14: filtri del tab «Enti» della ricerca unica (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** casi nuovi in `tests/entes-search.php`: ruoli nelle tracce della copia di prova; source con i due
      filtri (nomi in italiano e inglese); voci contate senza il proprio filtro e con l'altro, ordinate per numero e
      nome, voce scelta con 0 che resta; tipi e ruoli sconosciuti fuori; con un testo i ruoli dalla copia; parametri
      nel canonical.
- [x] **Passo 2:** FAIL. **Passo 3:** `forestas_entes_role_labels()`, `forestas_entes_roles()`,
      `forestas_entes_roles_by_slug()` e `forestas_entes_search_filter()` in `inc/entes.php`; argomenti `tipo-ente` e
      `ruolo` della rotta, `facets` nella risposta e nella source, chiavi nel canonical in `inc/search.php`; filtri
      passati e voci disegnate in `assets/js/search.js`; `Version` 1.17.5.
- [x] **Passo 4:** PASS (anche `entes-copy`, `entes-route`, `entes-view`, `search-facets`); nel browser
      `/cerca-nel-sito/?tipo=enti&ruolo=gestore`: 46 enti, «Soggetto gestore» spuntato, con «Comune» 17, nessun
      errore in console.

### Task 15: filtri dei luoghi dei punti di interesse (aggiunto il 19/11)

**Repo:** `wp-package`, poi `wp-forestas` (child).

- [x] **Passo 1 (plugin):** casi nuovi in `tests/php/search-pois.php` (luoghi nella copia, anche da stringa JSON;
      faccette dei luoghi dalla source con `include`, `include_pattern`, `exclude`, `exclude_pattern`; conteggi senza
      il proprio filtro e con gli altri, tipi compresi; OR dentro un filtro; voce scelta a 0; filtri sconosciuti
      ignorati; copia senza luoghi: 200 e ricostruzione programmata) e in `tests/js/source-pois.test.js`
      (`filter[chiave][]`). FAIL.
- [x] **Passo 2 (plugin):** `wheres` nella copia, `wmwp_poi_search_place_facets()`, `wmwp_poi_search_keep_place()`,
      `wmwp_poi_search_schedule_rebuild()`, parametro `filter` e conteggi incrociati in `includes/search-pois.php`;
      `filter[chiave][]` in `assets/js/source-pois.js`; README, CHANGELOG (0.5.0). PASS, tutti i test PHP e JS.
- [x] **Passo 3 (child):** controllo in `tests/search-facets.php` (FAIL), poi area, provincia e comune di
      `forestas_search_track_facets()` nella source `pois` di `inc/search.php` (PASS, tutti i test del child); nel
      browser `?tipo=punti-di-interesse&provincia=Nuoro`: 262 POI, con Bitti 33, nessun errore in console.

### Task 16: stile dei filtri della ricerca unica come quelli dei post (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** `.wmwp-facet__count` in `style.css` con il riquadro di `.forestas-filtri__numero` (stessa forma e
      stesso colore, `--forestas-filtro` anche su `.wmwp-search`), fondo pieno con `.wmwp-facet:has(input:checked)`;
      testo a 11,5px come prima; `Version` 1.17.6.
- [x] **Passo 2:** nel browser `?tipo=percorsi&provincia=Nuoro`: riquadri su tutte le voci, pieni nel filtro
      «Provincia»; CSS servito con `?ver=1.17.6`.

### Task 17: filtri della ricerca apribili e «Mostra di più» come nei post (aggiunto il 19/11)

**Repo:** `wp-package`, poi `wp-forestas` (child).

- [x] **Passo 1 (plugin):** `wmwp_search_collapsible_facets` (config `collapsible_facets`), pulsante nel `legend` in
      `renderFacets()` di `assets/js/search.js`, «Mostra di più» nascosto a filtro chiuso; traduzione «Mostra di più»;
      README e CHANGELOG. Test JS e PHP della ricerca: PASS.
- [x] **Passo 2 (child):** filtro acceso in `inc/search.php`; stile di `.wmwp-facet__toggle`, `.wmwp-facet__chevron` e
      `.wmwp-facet__more` in `style.css` (Version 1.17.8). Nel browser: all'apertura aperto solo «Provincia» (con
      Nuoro scelta), «Attività» si apre con Invio e il focus resta sul pulsante, «Mostra di più» → «Mostra meno»;
      testo a 12,5px come prima, nessun errore in console.

### Task 18: intestazione e breadcrumb della ricerca (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** controllo in `tests/search-facets.php` su `[forestas_search]` (intestazione, breadcrumb, niente
      titolo di prima né «Condividi»): FAIL.
- [x] **Passo 2:** `[forestas_search]` in `inc/search.php` con l'intestazione e `forestas_percorso()`;
      `cerca-nel-sito` in `FORESTAS_PAGINE_SENZA_CONDIVIDI`; tolto lo stile di `.forestas-search__title`; Version
      1.17.10. PASS, tutti i test del child.
- [x] **Passo 3:** nel browser IT a 1280px e EN a 375px: stessa intestazione della pagina Enti, un solo `h1`,
      `navigation` «Briciole di pane» / «Breadcrumb» con `aria-current`, nessuno scorrimento orizzontale.

### Task 19: dettaglio della traccia dal file v1 (aggiunto il 19/11)

**Repo:** `wp-package`, poi `wp-forestas` (child).

- [x] **Passo 1 (plugin):** casi in `tests/php/detail-aws.php` (file della versione, 304 sullo stesso file, ripiego sul
      file senza versione, niente ETag fra file diversi, versione non valida): FAIL. `wmwp_track_json_version()` e
      lettura in `wmwp_detail_fetch()` (`includes/detail-render.php`); README e CHANGELOG. PASS, tutti i test.
- [x] **Passo 2 (child):** controllo in `tests/dettaglio.php` (FAIL), filtro a `v1` in `inc/dettaglio.php` (PASS);
      stesso HTML del dettaglio dal file v1 e da quello senza versione su 15 tracce IT/EN.

### Task 20: via il pulsante «Stampa» (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** controllo in `tests/dettaglio.php` (traccia generata e template del POI senza «Stampa»): FAIL.
- [x] **Passo 2:** tolto il pulsante da `wp-package/track.php` e `wp-package/poi.php` (un commento dice che torna con
      oc:8982), commento di `assets/js/dettaglio.js` allineato; Version 1.17.11. PASS.
- [x] **Passo 3:** nel browser sentiero e POI, IT ed EN, desktop e 375px: nella riga restano «Scarica GPX» (solo
      tracce) e «Condividi», nessun errore, niente scorrimento orizzontale.

### Task 21: breadcrumb nel dettaglio di traccia e POI (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** controlli in `tests/dettaglio.php` (breadcrumb di sentiero e itinerario nel dettaglio generato, fra
      testata e pulsanti; POI e cammino dalla funzione; il template del POI la stampa): FAIL.
- [x] **Passo 2:** `forestas_dettaglio_percorso()` e `forestas_voce_menu_principale()` (nome dal menu principale, non
      dal titolo della pagina) in `inc/dettaglio.php`, stampata dopo la testata in
      `wp-package/track.php` e `wp-package/poi.php`. PASS, tutti i test del child.
- [x] **Passo 3:** nel browser sentiero, itinerario, cammino (EN, 375px) e POI (IT 375px, EN): `navigation`
      «Briciole di pane» / «Breadcrumb» con i link e la pagina corrente, nomi lunghi a capo, niente scorrimento
      orizzontale.

### Task 22: etichetta del tipo del POI come nel wireframe (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** dalla tavola (markup e CSS di `index.html` dell'artifact, sezione `s2p`): `.tipo.pt`, fondo
      #B7127F, testo bianco, 11px, 600, 3px 8px, angoli di 2px, la stessa `.tipo` delle tracce.
- [x] **Passo 2:** tolta `.wm-tipo--poi` da `assets/css/dettaglio.css` (il POI usa `.wm-tipo` con `--wm-c`);
      Version 1.17.12. Nel browser, desktop e 375px: fondo rgb(183, 18, 127), testo bianco, 11px, 600, 3px 8px, 2px.

### Task 23: quota e area sosta nel riquadro del POI (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** controlli in `tests/dettaglio.php` (quota da `ele` arrotondata, 0 valido, non numerica assente;
      area sosta dal tipo; tipologia senza l'area sosta, o l'area sosta se è l'unico tipo; «1.293 m»): FAIL.
- [x] **Passo 2:** `quota`, `area_sosta` e tipologia in `forestas_dettaglio_poi()`, `forestas_dettaglio_quota()` in
      `inc/dettaglio.php`; due righe in `wp-package/poi.php`, solo quando c'è il dato. PASS, tutti i test del child.
- [x] **Passo 3:** nel browser Perda Liana (POI 278, quota scritta per la prova nella cache locale e poi tolta) IT a
      1280px ed EN a 375px, e Sos Nibberos senza le due righe: righe con `rowheader`, nomi in inglese nostri.

### Task 24: percorsi vicini del POI sotto la galleria (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] Spostata la sezione in `wp-package/poi.php`; nel browser l'ordine è `wm-breve`, `wm-punto`, `wm-galleria`,
      `wm-vicini` (Perda Liana IT ed EN, Sos Nibberos). `tests/dettaglio.php`: PASS.

### Task 25: POI correlati nel dettaglio del POI (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** controlli in `tests/dettaglio.php` (card dai `related_pois`, senza id fuori, vuoto senza dati,
      il template li mostra): FAIL.
- [x] **Passo 2:** `forestas_dettaglio_poi_correlati()` e `poi_correlati` in `forestas_dettaglio_poi()`; sezione
      «Punti di interesse correlati» in `wp-package/poi.php` dopo i percorsi vicini, con `forestas_dettaglio_griglia()`.
      PASS, tutti i test del child.
- [x] **Passo 3:** nel browser S'Arcu 'e Laconitha (12 correlati) IT a 1280px ed EN a 375px: tre card, «Mostra tutti»
      le apre tutte, link al dettaglio nella lingua, `region` con il titolo e card con `heading` di livello 3.

### Task 26: filtri dei post con lo stile della ricerca (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** riscritto lo stile di `.forestas-filtri*` in `style.css` con i valori di `.wmwp-facet*` (misure,
      colori, casella, riquadro dei numeri, «Mostra di più»); commento di `forestas_filtri()` allineato; Version
      1.17.13.
- [x] **Passo 2:** nel browser Eventi (gruppo aperto), Enti con «Comune» scelto, la ricerca per confronto e Tutte le
      news a 375px: stesse misure misurate (titolo 12,5px 500 #6B6B6B, voce 13px #4A4A4A, numero 11,5px 700, freccia
      alta 24px), focus visibile su freccia e voci, niente scorrimento orizzontale. Tutti i test del child: PASS.

### Task 27: «Filtri attivi» nelle liste dei post e degli enti (aggiunto il 19/11)

**Repo:** `wp-forestas` (child).

- [x] **Passo 1:** `tests/filtri-attivi.php` nuovo (markup, una voce per filtro, «Togli tutti i filtri», lista degli
      eventi con e senza filtro, template degli enti): FAIL.
- [x] **Passo 2:** `forestas_filtri_togli_url()`, `forestas_filtri_attivi_html()` e `forestas_filtri_attivi_griglia()`
      in `inc/griglie.php`, in cima alla lista di `forestas_griglia()` e in `templates/entes-list.php`; stile come
      `.wmwp-search__active` (Version 1.17.14). PASS, tutti i test del child.
- [x] **Passo 3:** nel browser Eventi con due filtri (il clic su una voce toglie solo quella), Enti a 375px ed Enti in
      inglese: link con il nome del gruppo, focus visibile, contrasti 8,82:1 e 9,24:1, niente scorrimento orizzontale.

### Task 12: rilascio e controlli finali

**Repo:** `wp-package`, poi `wp-forestas`.

- [x] **Passo 1 (plugin):** `Version` in `index.php` (minore successiva a 0.4.0), `CHANGELOG.md`, traduzioni
      rigenerate su una copia del plugin e copiate in `languages/`; tutti i test PHP e JS: PASS.
- [x] **Passo 2:** rifare `snapshot.sh dopo` e il confronto del task 3: le uniche differenze sono quelle volute
      (fasce, tacche, avviso, via segnalazioni e recensioni); nessun indirizzo cambiato in `urls.json`.
- [x] **Passo 3 (child):** tutti i test del child: PASS; `Version` alzata.
- [ ] **Passo 4:** dopo il merge del plugin, `WP_PACKAGE_REF` in `wp-forestas/docker/scripts/init-wordpress.sh` con
      l'hash letto da `git rev-parse` (commit a sé, nella PR del ticket di `wp-forestas`).
- [x] **Passo 5:** cartella di lavoro e mu-plugin di prova del confronto non restano nel repo né nel container.
- [ ] **Passo 6 (a cura del dev):** dopo il merge, su dev e UAT `scripts/wordpress-up.sh` di `forestas`; verifica con
      dati veri (un sentiero parzialmente percorribile e un avviso creati in Nova).
