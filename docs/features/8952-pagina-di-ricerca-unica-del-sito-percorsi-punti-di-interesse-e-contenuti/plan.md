> Ticket: oc:8952

# Pagina di ricerca unica del sito — piano di implementazione

> **Per chi esegue:** sub-skill richiesta: `superpowers:subagent-driven-development` (consigliata) o
> `superpowers:executing-plans`, un task alla volta. I passi usano le checkbox (`- [ ]`) per tenere traccia.

**Obiettivo:** la pagina `/cerca-nel-sito` del nuovo sito, con il tab «Tutti», un tab per source e i filtri dei
percorsi calcolati nel browser, costruita su un plugin nuovo condiviso (`wp-package`) e sul child theme.

**Architettura:** il plugin stampa in PHP solo lo scheletro della pagina e il `<template>` della card; un
controller JS disegna tab, gruppi, card e filtri, e chiede i risultati alle source registrate. La source
«Percorsi» (plugin) scarica dall'API Elasticsearch dello shard tutti i percorsi per il testo e calcola in JS filtri,
conteggi e pagine; la source «Contenuti del sito» (child) chiama una rotta REST del child. L'init di wp-forestas
scarica il plugin a un commit fisso, o in locale collega il clone di sviluppo.

**Tecnologie:** WordPress 7.1 (PHP 8.3 nel container), JS vanilla senza build (eccetto la mappa: build con Vite in `map/`, Task 10b) (moduli UMD, test con `node --test`,
Node 16 sull'host), Impreza + WPML (`it`, `en-us`), Bash per `init-wordpress.sh`.

**Spec:** [overview.md](overview.md) (approvata il 09/11), decisioni e fonti in [notes.md](notes.md).

## Vincoli globali

- **Mai `git commit`, `git push` o un branch senza istruzione esplicita dell'utente**, anche per i subagent: i
  passi «Commit» qui sotto sono testo da proporre all'utente. Il puntatore di `wp-forestas` in `forestas` (gitlink)
  si aggiorna solo su sua indicazione e mai insieme al lavoro.
- **wp-forestas è pubblico:** nessun valore reale nei file tracciati; shard, app id e percorsi locali solo nel
  `.env`, `.env-example` con segnaposto. `git diff config/` prima di ogni commit.
- Prefisso del plugin `wmwp_` (funzioni, opzioni, handle), costanti `WMWP_*`, text domain `wp-package`, testi di
  base in inglese; nessun percorso con il nome della cartella scritto nel codice.
- Versione del plugin `0.1.0` (Semantic Versioning, fino alla 1.0 l'interfaccia può cambiare).
- Hook pubblici con i nomi dell'overview: `wp_package_search_sources`, `wp_package_search_params`.
- Parametri di Forestas nell'indirizzo: `search_api_fulltext` (testo), `tipo` (tab), `pagina` (1 = predefinita,
  non scritta), filtri `attivita`, `difficolta`, `dove`; valori separati da virgola; valori dei filtri = identifier
  dello shard (per `dove` il nome).
- 12 risultati per pagina; nel tab «Tutti» le prime 4 card per gruppo.
- I numeri dei tab dipendono solo dal testo cercato; i filtri cambiano il numero dentro il tab.
- Ogni modifica a CSS o JS del child alza la `Version` in `themes/forestas-child/style.css`.
- Commenti e documentazione in italiano, termini tecnici in inglese. Messaggi di commit `feat(oc:8952): …`.
- Le lacune dell'API non si correggono in `wm-package`: vanno alla sessione «OC:8916[GAR] Analisi wp-forestas e
  shard Forestas».
- `init-wordpress.sh` sta nell'immagine: una sua modifica gira solo dopo `scripts/wordpress-up.sh` di forestas.

## Punti da guardare in review

1. **Testo fatto solo di caratteri speciali** (`/`, `(`, `"`): nessun errore 500, la pagina si comporta come senza
   testo. Test in Task 3 (`cleanQuery('/') === ''`).
2. **Percorsi senza un campo o con nomi sporchi** (manca `terms`, nome con la tabulazione iniziale come
   `"\tCMSB - TAPPA 04…"` sul dev): niente eccezioni, titoli senza spazi iniziali. Test in Task 3, verifica in
   Task 6, passo 2.
3. **Indirizzo con chiavi o valori che non esistono** (vecchio link, refuso, `pagina=99`): chiave ignorata, valore
   mostrato a zero e togliibile, pagina riportata all'ultima. Test in Task 3.
4. **Pagina inglese:** numeri `179.5 km`, nomi da `name.en` con ripiego su `name.it`, link dei contenuti con `/en/`.
   Test in Task 3 e Task 8.
5. **Shard che non risponde o è lento:** errore nel gruppo e nel tab «Percorsi» senza un falso 0, «Contenuti del
   sito» funziona; una risposta vecchia non sostituisce la nuova. Verifica in Task 6, passo 5.

---

## Fase A — plugin `wp-package`

Cartella del plugin: `P="/Volumes/WebmappDev/Sites/Webmapp/Wordpress plugin/wp-package"`.
Copia di partenza: `S="/Volumes/WebmappDev/Local Sites/sentieroitalia/app/public/wp-content/plugins/wm-package"`.

### Task 1: Fork e base del plugin

**File:**
- Crea (clone): `$P` da `https://github.com/webmappsrl/wp-geohub`, ramo `osm2cai` (`8255db5`)
- Riscrive: `$P/index.php`; crea `$P/includes/settings.php`, `$P/includes/config.php`, `$P/includes/i18n.php`
- Tiene: `$P/hooks/transient.php`, `$P/config/wm_default_config.json` (*in esecuzione non entrati, come
  `includes/config.php`: vedi notes, «Divergenze», «Task 1: niente configurazione dell'app»*)
- Crea: `$P/README.md`, `$P/CHANGELOG.md`
- Cancella: tutto ciò che è nella tabella «Cosa resta fuori» di notes.md

**Interfacce:**
- Produce: costanti `WMWP_VERSION` (`'0.1.0'`), `WMWP_PATH` (`plugin_dir_path( __FILE__ )`), `WMWP_URL`
  (`plugin_dir_url( __FILE__ )`); opzioni `wmwp_shard` (string) e `wmwp_app_id` (int);
  `wmwp_get_shards_config( bool $force_refresh = false ): array` (da `wm_get_shards_config`, `admin_page.php:267`,
  stessa cache in transient e stesso ripiego);
  `wmwp_get_api_urls( string $shard, int $app_id ): array` con la chiave `elastic_api` = `{origin}/api/v2/elasticsearch`
  (senza i casi `geohub` e osm2cai di `admin_page.php:446-480`); `wmwp_elastic_url(): string` (`''` se shard o app
  id mancano); `wmwp_app_param(): string` = `"{$shard}_{$app_id}"` (sul dev `forestasdev_1`, verificato il 09/11).
  Gli URL si calcolano a ogni richiesta, non si salvano.

- [x] **Passo 1: clone.** `git clone --branch osm2cai https://github.com/webmappsrl/wp-geohub "$P"`, poi
  `git -C "$P" remote rename origin upstream` (il remoto `origin` arriva con il repo di Giuseppe). Controllo:
  `git -C "$P" log -1 --format=%h` dà `8255db5`.
- [x] **Passo 2: togliere ciò che resta fuori** (tabella di notes.md): `actions/`, `wp-cli/`, `post_types/`,
  `custom_fields/`, `restrict-admin-capabilities.php`, `functions/controls.php`, `functions/imports.php`,
  `shortcodes/` (le tre funzioni di formattazione si riscrivono in JS al Task 3), `assets/css/`, `hooks/dashboard.php`,
  `languages/*`, `docs/`. `admin_page.php` e `functions/functions.php` restano solo finché i passi 3-4 non ne hanno
  preso le parti.
- [x] **Passo 3: `index.php`** con intestazione `Plugin Name: WP Package`, `Version: 0.1.0`,
  `Text Domain: wp-package`, `Domain Path: /languages`, le tre costanti e i `require_once` di `includes/*.php` e
  `hooks/transient.php`.
- [x] **Passo 4: `includes/settings.php` e `includes/config.php`.** Dalle sezioni «Backend Configuration» (riga 580)
  e «APIs» (riga 621) di `admin_page.php` e dal salvataggio `wm_save_options` (2462-2513) solo shard e app id; da
  `functions/functions.php` `wm_get_config_api_url` (1039), `wm_fetch_remote_config` (1063),
  `wm_get_default_config` (1089), `wm_update_config_id` (1139), tutto rinominato `wmwp_*`. La settings page mostra in
  sola lettura l'URL di Elasticsearch calcolato. Poi si cancellano `admin_page.php` e `functions/`.
- [x] **Passo 5: `includes/i18n.php`:** `wmwp_load_textdomain()` su `init` con
  `load_plugin_textdomain( 'wp-package', false, dirname( plugin_basename( WMWP_PATH . 'index.php' ) ) . '/languages' )`.
- [x] **Passo 6: controlli.** `grep -rn "wm-package\|wm_\(get\|fetch\|update\|save\|is\)_" "$P" --include='*.php'` non
  trova niente; `for f in $(cd "$P" && find . -name '*.php'); do docker exec -i wordpress-forestas php -l < "$P/$f"; done`
  dà «No syntax errors» per ogni file. L'attivazione si prova al Task 2.
- [x] **Passo 7: README e CHANGELOG.** `README.md`: cos'è, installazione, sezione «Interfaccia pubblica» (riempita
  al Task 7), avviso «fino alla 1.0 l'interfaccia può cambiare». `CHANGELOG.md` con `## 0.1.0 — non rilasciata`.
- [x] **Passo 8: commit (istruzione per l'utente, repo `wp-package`).**
  `git add -A && git commit -m "feat(oc:8952): base di wp-package dal fork di wp-geohub (osm2cai)"`

### Task 2: Installazione in wp-forestas — clone di sviluppo, download, attivazione

**File:**
- wp-forestas — crea: `compose.local.yml` (solo il volume del clone di sviluppo), `docker/wp-package-empty/.gitkeep`;
  modifica: `.env-example`
- forestas — modifica: `local.compose.yml` (righe 3-4: `include` con
  `- path: [wp-forestas/compose.yml, wp-forestas/compose.local.yml]`). `compose.yml`, usato dai server, non cambia.
- wp-forestas — modifica:
  `docker/scripts/init-wordpress.sh` (righe 12-18 costanti, 235-250 passo 6, 396-402 permessi),
  `docker/scripts/config/ritratto.php:30`

**Interfacce:**
- Consuma: opzioni `wmwp_shard`, `wmwp_app_id` (Task 1); slug del plugin `wp-package`.
- Produce: variabili del `.env` `WP_PACKAGE_DIR`, `WP_PACKAGE_SHARD`, `WP_PACKAGE_APP_ID`; costante dell'init
  `WP_PACKAGE_REF`; file `wp-content/plugins/wp-package/.wpf-ref` con il commit scaricato.

Il clone di sviluppo si monta, solo in locale, **fuori** da `/var/www/html`, e l'init lo collega con un link
simbolico: così il valore predefinito del mount (una cartella vuota del repo) non copre mai un plugin scaricato,
`find` del passo 9 non entra nel link (senza `-L`) e il mount è in sola lettura. WordPress gestisce i plugin
collegati con un link simbolico (`wp_register_plugin_realpath`). Il mount sta in un file compose a parte, incluso
solo da `local.compose.yml` (Docker Compose 2.31 in locale accetta un elenco di file in `include`;
`scripts/wordpress-up.sh` usa gli stessi file con cui gira lo shard, righe 19-34).

- [x] **Passo 1: compose e `.env-example`.** `compose.local.yml` con il solo servizio `wordpress` e il volume
  `- ${WP_PACKAGE_DIR:-./docker/wp-package-empty}:/opt/wp-forestas/wp-package-dev:ro`, più un commento
  che dice che serve solo in locale. In forestas, `local.compose.yml` lo include dopo `compose.yml`. In
  `.env-example`: `WP_PACKAGE_DIR=` (commento: percorso del clone di sviluppo, solo in locale),
  `WP_PACKAGE_SHARD=` e `WP_PACKAGE_APP_ID=` (commento: scritti nelle opzioni solo se vuote; dev `forestasdev`
  e 1 — valori pubblici, li ha il README).
- [x] **Passo 2: costanti dell'init.** Al posto di `GEOHUB_DIR`/`GEOHUB_REF`/`GEOHUB_TARBALL`:
  `WMWP_DIR=${WP_PATH}/wp-content/plugins/wp-package`, `WMWP_DEV_DIR=/opt/wp-forestas/wp-package-dev`,
  `WP_PACKAGE_REF=` (vuoto finché il repo GitHub non esiste; commento con il comando
  `git ls-remote https://github.com/webmappsrl/wp-package refs/heads/main` come per `GEOHUB_REF`),
  `WMWP_TARBALL="https://github.com/webmappsrl/wp-package/archive/${WP_PACKAGE_REF}.tar.gz"`.
- [x] **Passo 3: passo 6 dell'init**, in quest'ordine:
  1. se esiste `${WMWP_DEV_DIR}/index.php`: `WMWP_DIR` diventa un link simbolico a `WMWP_DEV_DIR` (una cartella
     scaricata prima si toglie: è una copia ricreabile), niente download;
  2. altrimenti, se `WP_PACKAGE_REF` è vuoto: `log "AVVISO: wp-package non ancora pubblicato, nessun download"`;
  3. altrimenti, se `${WMWP_DIR}/.wpf-ref` non contiene `WP_PACKAGE_REF`: download in una cartella temporanea
     con gli stessi tempi massimi di oggi (`RETE_CONNESSIONE`, `RETE_ATTESA`), sostituzione della cartella e scrittura
     di `.wpf-ref`; se fallisce, AVVISO e si riprova al riavvio successivo, la cartella vecchia resta;
  4. se esiste `${WMWP_DIR}/index.php`: `attiva_plugin wp-package <sì se appena scaricato o collegato>`, poi per
     `wmwp_shard` e `wmwp_app_id`: se l'opzione è vuota e la variabile del `.env` no, `$WP option update`.
- [x] **Passo 4: permessi (riga 396).** Il commento dice che il clone di sviluppo sta fuori da `/var/www/html` e che
  `find` senza `-L` non entra nel link; il comando non cambia.
- [x] **Passo 5: `ritratto.php:30`:** `[ 'us-core', 'wp-package' ]`.
- [x] **Passo 6: prova in locale.** Nel `.env` locale `WP_PACKAGE_DIR` = `$P`, `WP_PACKAGE_SHARD=forestasdev`,
  `WP_PACKAGE_APP_ID=1`. Da `forestas`: `scripts/wordpress-up.sh`. Atteso:
  - `docker logs wordpress-forestas 2>&1 | grep init-wordpress` mostra «attivo il plugin wp-package» e nessun
    errore;
  - `docker exec wordpress-forestas readlink /var/www/html/wp-content/plugins/wp-package` dà
    `/opt/wp-forestas/wp-package-dev`;
  - `docker exec wordpress-forestas wp --allow-root eval 'echo wmwp_elastic_url(), " ", wmwp_app_param();'` dà
    `https://forestas.dev.maphub.it/api/v2/elasticsearch forestasdev_1`;
  - `ls -ln "$P"` sull'host: proprietario invariato.
- [x] **Passo 7: wp-geohub fuori dal locale:** `wp --allow-root plugin deactivate wm-package && wp --allow-root plugin
  delete wm-package` nel container; `wp --allow-root plugin list` mostra `wp-package` attivo e niente `wm-package`.
- [x] **Passo 8: senza variabile e sui server.** Con `WP_PACKAGE_DIR` tolto dal `.env`, da `forestas`
  `docker compose -f local.compose.yml config -q` e `docker compose -f compose.yml config -q` non danno errori; il
  riavvio dà l'AVVISO del punto 2 e il sito parte. `docker compose -f compose.yml config | grep wp-package` non
  trova niente: sui server il mount non c'è.
- [ ] **Passo 9: commit (istruzione per l'utente).** wp-forestas:
  `git add compose.local.yml .env-example docker/wp-package-empty docker/scripts/init-wordpress.sh docker/scripts/config/ritratto.php`
  `git commit -m "feat(oc:8952): l'init installa wp-package al posto di wp-geohub, con il clone di sviluppo in locale"`;
  forestas, senza il gitlink: `git add local.compose.yml && git commit -m "feat(oc:8952): mount del clone di sviluppo di wp-package in locale"`.

### Task 3: Logica pura della ricerca (testata con Node)

**File:**
- Crea: `$P/assets/js/search-logic.js` (UMD: `module.exports` in Node, `window.wmWpSearchLogic` nel browser)
- Test: `$P/tests/js/search-logic.test.js` (`node:test`, `node:assert/strict`), fixture
  `$P/tests/js/fixtures/hits.json` (5 percorsi ridotti dal dev, con `name`, `ref`, `taxonomyActivities`,
  `taxonomyWheres`, `taxonomyIcons`, `terms`, `distance`, `feature_image`; uno senza `terms`, uno con il nome che
  inizia con `\t`)

**Interfacce (produce):**
- `cleanQuery(text: string): string` — sostituisce con uno spazio `+ = & | > < ! ( ) { } [ ] ^ " ~ * ? : \ /`,
  scrive `-` come `\-`, comprime gli spazi, toglie quelli ai bordi. Verificato sul dev il 09/11: `a/b`, `(oliena`,
  `oliena:`, `"oliena` danno 500; `B\-401` dà 200 con 2 risultati, come `B-401`.
- `facetValues(hit, facet, lang): Array<{id: string, label: string}>` — `facet.type` `'list'` (array di stringhe;
  `label` da `hit.taxonomyIcons[id].label[lang]`, poi `.it`, poi l'id) o `'terms'` (`facet.field` come
  `'terms.hiking-difficulty'`; id = `identifier`, `label` da `name[lang]`, poi `name.it`); scarta i valori che
  iniziano con `facet.exclude_prefix`; un campo mancante dà `[]`.
- `filterHits(hits, facets, selected: {[key]: string[]}, lang): Hit[]` — OR dentro un filtro, AND fra filtri.
- `computeFacets(hits, facets, selected, lang): FacetView[]` — `FacetView = {key, label, disabled, values:
  [{id, label, count, selected}]}`; i conteggi di un filtro si calcolano con tutti gli altri filtri e senza il suo;
  valori con `count > 0` o selezionati; ordine per conteggio decrescente, poi `label` (`localeCompare`).
- `parseState(search: string, params: ParamMap, facetKeys: string[]): State` e
  `stateToSearch(state: State, params: ParamMap): string` — `State = {text, tab, page, filters}`; `ParamMap =
  {q, tab, page}` (nomi dei parametri); chiavi sconosciute ignorate; `page` assente = 1; tab assente = `''`
  («Tutti»); valori dei filtri separati da virgola; `stateToSearch` omette i valori predefiniti e usa
  `URLSearchParams`.
- `paginate(items, page, perPage): {items, page, pages}` — `page` riportata fra 1 e `pages`.
- `formatKm(km, lang)`, `formatMeters(m, lang)`, `formatDuration(minutes, lang)` — `Intl.NumberFormat`; durata
  «2 h 5 min» come `wm_format_track_duration` (`single_track.php:44`): ore solo se > 0, minuti sempre; `''` per
  `null`, `''` e 0.
- `countLabel(n, nouns: {one, other}, lang): string` — `Intl.PluralRules`, `%s` sostituito con il numero formattato.

- [x] **Passo 1: test che falliscono**, uno per funzione, con questi valori:
  - `cleanQuery('(oliena')` → `'oliena'`; `cleanQuery('a/b')` → `'a b'`; `cleanQuery('B-401')` → `'B\\-401'`;
    `cleanQuery('  perda   liana ')` → `'perda liana'`; `cleanQuery('/')` → `''`;
  - con la fixture e i filtri `attivita` (list, `taxonomyActivities`, `exclude_prefix: 'sardegnasentieri:'`) e
    `dove` (list, `taxonomyWheres`): selezionato `attivita: ['mtb']` → i conteggi di `attivita` sono quelli senza
    filtro, quelli di `dove` solo dei percorsi con `mtb`; selezionati `attivita: ['mtb']` e un `dove` senza percorsi
    `mtb` → `filterHits` vuoto e il valore di `dove` presente con `count: 0, selected: true`; il percorso senza
    `terms` non lancia eccezioni con `difficolta` (`terms.hiking-difficulty`); con `lang` `'en'` un termine senza
    `name.en` ha come `label` `name.it`;
  - `parseState('?search_api_fulltext=perda+liana&tipo=percorsi&attivita=hiking,mtb&pagina=2&xyz=1', {q:
    'search_api_fulltext', tab: 'tipo', page: 'pagina'}, ['attivita'])` → `{text: 'perda liana', tab: 'percorsi',
    page: 2, filters: {attivita: ['hiking', 'mtb']}}`, e `stateToSearch` di quello stato restituisce la stessa
    stringa senza `xyz`; pagina 1 e tab vuoto non compaiono;
  - `paginate(Array(30), 99, 12).page` → `3`;
  - `formatKm(179.5, 'it')` → `'179,5 km'`, `'en'` → `'179.5 km'`; `formatMeters(1234, 'it')` → `'1.234 m'`,
    `'en'` → `'1,234 m'`; `formatDuration(125, 'it')` → `'2 h 5 min'`, `formatDuration(0, 'it')` → `''`;
  - `countLabel(1, {one: '%s percorso', other: '%s percorsi'}, 'it')` → `'1 percorso'`, `28` → `'28 percorsi'`.
- [x] **Passo 2:** `node --test "$P"/tests/js/*.test.js` → FAIL (modulo mancante).
- [x] **Passo 3: implementazione** in `search-logic.js`, senza dipendenze.
- [x] **Passo 4:** `node --test "$P"/tests/js/*.test.js` → tutti PASS.
- [x] **Passo 5: commit (istruzione per l'utente, repo `wp-package`).**
  `git add assets/js/search-logic.js tests/js && git commit -m "feat(oc:8952): logica della ricerca: testo, filtri, indirizzo, numeri"`

### Task 4: Shortcode, scheletro, template e registro delle source (PHP)

**File:**
- Crea: `$P/includes/search.php`, `$P/templates/search.php`, `$P/templates/card.php`, `$P/templates/row.php`

**Interfacce:**
- Consuma: `wmwp_elastic_url()`, `wmwp_app_param()` (Task 1).
- Produce:
  - shortcode `[wmwp_search]`;
  - `wmwp_locate_template( string $name ): string` — `get_stylesheet_directory() . '/wp-package/' . $name` se
    esiste, poi il tema padre, poi `WMWP_PATH . 'templates/' . $name`;
  - filter `wp_package_search_sources( array $sources ): array` — ogni source: `id`, `slug` (valore nell'indirizzo),
    `label`, `nouns` (`['one' => '%s …', 'other' => '%s …']`), `preview` (4), `per_page` (12), `script` (handle JS
    che registra la source), `layout` (`'cards'` o `'rows'`: come si disegna il suo tab), `search_label` e
    `placeholder` (testi del campo quando il suo tab è aperto), più le chiavi proprie (per `tracks`: `endpoint`, `app`, `facets`, `link_template`);
    il plugin registra `tracks` con `slug` `tracks`, `facets` `[]`, `link_template` `''`;
  - filter `wp_package_search_params( array $params ): array` — predefiniti `['q' => 'q', 'tab' => 'tab',
    'page' => 'page']`;
  - handle JS `wmwp-search-logic`, `wmwp-search` (dipende da `wmwp-search-logic`, `wp-i18n`), `wmwp-source-tracks`
    (dipende da `wmwp-search`), accodati solo dallo shortcode; configurazione in
    `window.wmWpSearchConfig = {params, sources, lang, labels}` con `wp_add_inline_script( 'wmwp-search', …, 'before' )`.
- [x] **Passo 1: `templates/search.php`:** form `role="search"` con `<label>` e campo `type="search"`; `<div
  role="tablist">` vuoto; un `role="tabpanel"` per il tab «Tutti» e uno per source; colonna dei filtri;
  regione `role="status"` unica; `<noscript>` con un form `GET` verso `home_url( '/' )` e campo `s`.
- [x] **Passo 2: `templates/card.php` e `templates/row.php`**, stampati una sola volta dentro `<template
  id="wmwp-card">` e `<template id="wmwp-row">`: elementi con `data-wmwp="image|type|code|title|link|meta|badge"`,
  classi radice `wmwp-card` e `wmwp-row`; il JS li riempie, il PHP non stampa risultati. La riga segue la tavola 2
  del wireframe (codice, nome, meta, stato), la card il tab «Tutti».
- [x] **Passo 3: `includes/search.php`**: shortcode, `wmwp_locate_template`, i due filter, registrazione e
  accodamento degli script, configurazione inline.
- [x] **Passo 4: verifica.** Pagina di prova locale con `[wmwp_search]` (`wp --allow-root post create --post_type=page
  --post_status=draft --post_title=prova-ricerca --post_content='[wmwp_search]' --porcelain`); l'HTML contiene
  `<template id="wmwp-card">`, `role="tablist"`, `<noscript>`, `window.wmWpSearchConfig` con l'endpoint
  `https://forestas.dev.maphub.it/api/v2/elasticsearch`. Con `cp "$P/templates/card.php"` in
  `themes/forestas-child/wp-package/card.php` e una classe in più, la pagina usa quella del tema; poi il file di
  prova si toglie. Pagina di prova cancellata alla fine (`wp post delete <id> --force`).
- [x] **Passo 5: commit (istruzione per l'utente, repo `wp-package`).**
  `git add includes/search.php templates && git commit -m "feat(oc:8952): shortcode della ricerca, template sostituibili, registro delle source"`

### Task 5: Controller JS della pagina

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-e-10-niente-riquadro-indirizzo-di-questa-ricerca-review-gate-0911)

**File:**
- Crea: `$P/assets/js/search.js`

**Interfacce:**
- Consuma: `window.wmWpSearchLogic` (Task 3), `window.wmWpSearchConfig` e il markup (Task 4), `wp.i18n`.
- Produce: `window.wmWpSearch.registerSource( id: string, impl: { search(query, signal): Promise<SourceResult> } )`;
  `query = {text, page, perPage, filters, lang}` (`text` già ripulito da `cleanQuery`);
  `SourceResult = {total: number, items: Card[], facets?: FacetView[], summary?: string}`;
  `Card = {id, title, url: string|null, typeKey, typeLabel, image: {src, alt}|null, code: string|null, meta:
  string[], badge: {key, label}|null}`.

Comportamento richiesto (overview, sezioni plugin e accessibilità):
- tab di una source con `layout: 'rows'`: righe da `wmwp-row`; con `'cards'`: card da `wmwp-card`; sopra i risultati il
  riquadro «Indirizzo di questa ricerca» con l'indirizzo completo della ricerca (sola lettura, selezionabile) e la riga
  del totale; label e placeholder del campo da `search_label`/`placeholder` del tab aperto («Cerca nel sito» nel
  tab «Tutti»);
- tab «Tutti»: per ogni source con almeno un risultato, `<h2>` con `countLabel`, le prime `preview` card e il
  pulsante «Vedi tutti …» che apre il tab; tab veri (`aria-selected`, frecce sinistra e destra, numero nel nome);
- stato nell'indirizzo con `parseState`/`stateToSearch` (le chiavi dei filtri sono l'unione dei `facets[].key` di
  tutte le source; il tab è lo `slug` della source): `history.replaceState` mentre si scrive (attesa 400 ms),
  `pushState` con Invio e ai cambi di filtro, tab o pagina; `popstate` ridisegna dallo stato (in esecuzione: la
  prima pausa di quello che si scrive fa `pushState`, vedi notes, «Divergenze», «Task 5: un passo della
  cronologia per ogni ricerca scritta»);
- ogni ricerca annulla la precedente (`AbortController`); durante l'attesa i vecchi risultati restano, la zona ha
  `aria-busy="true"`;
- una source che fallisce mostra l'errore nel suo gruppo e nel suo tab, il numero del tab diventa «—»;
- filtri: `<fieldset>` + `<legend>` + `<input type="checkbox">`, primi 5 valori e «Mostra altro», filtri `disabled`
  visibili e spenti con il loro testo d'aiuto; la regola «OR dentro lo stesso filtro, AND fra filtri diversi»
  scritta sopra; il comando per togliere tutti i filtri;
- senza risultati: il messaggio con cosa provare e il comando per togliere i filtri;
- un solo `role="status"` con una frase intera (`summary` della source o `countLabel`);
- paginazione 12 per pagina con `paginate`.
- [x] **Passo 1: implementazione** di `search.js`, testi con `wp.i18n.__`/`_n` e text domain `wp-package`.
- [x] **Passo 2: verifica nel browser** sulla pagina di prova del Task 4, con una source finta registrata dalla
  console (`wmWpSearch.registerSource('finta', {search: () => Promise.resolve({total: 30, items: …})})`): gruppi, tab,
  «Vedi tutti», pagine, Indietro e Avanti del browser ripristinano testo, tab e pagina; nessun errore in console.
- [x] **Passo 3: commit (istruzione per l'utente, repo `wp-package`).**
  `git add assets/js/search.js && git commit -m "feat(oc:8952): controller JS della pagina di ricerca"`

### Task 6: Source «Percorsi»

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#selezione-e-scarico-di-più-percorsi-review-gate-0911)

**File:**
- Crea: `$P/assets/js/source-tracks.js`

**Interfacce:**
- Consuma: `registerSource`, `Card`, `SourceResult` (Task 5); `filterHits`, `computeFacets`, `paginate`, i
  formattatori, `countLabel` (Task 3); dalla configurazione della source `tracks`: `endpoint`, `app`, `facets`,
  `link_template`, `nouns`.
- Produce: la source `tracks`. Una richiesta `GET {endpoint}?app={app}&query={text}` (senza `query` se il testo è
  vuoto) per ogni testo, risposte in una `Map` per testo; filtri, conteggi e pagine calcolati sugli `hits`.
  Card: titolo `name[lang] ?? name.it` senza spazi ai bordi, `typeKey`/`typeLabel` da `terms['route-type'][0]`,
  immagine `feature_image` con `alt=""` (il titolo segue), `code` = `ref`, `url` da `link_template` con `{id}`, `{ref}`
  (`null` se `link_template` è vuoto: card senza link), `meta` con lunghezza (`+` dislivello) e tempo solo se maggiori
  di 0, `badge` `null` finché l'API non dà lo stato di validazione. `layout` `'rows'`.
  `summary`: «N sentieri · X km complessivi», come la tavola 2 del wireframe (i km solo se la somma è maggiore di 0).
- [x] **Passo 1: implementazione.**
- [x] **Passo 2: verifica sul dev** dalla pagina di prova: `?q=oliena&tab=tracks` dà lo stesso numero di
  `curl -s "https://forestas.dev.maphub.it/api/v2/elasticsearch?app=forestasdev_1&query=oliena" | jq '.hits|length'`;
  con `?q=cmsb` la card «CMSB - TAPPA 04 – DA BUGGERRU A PORTIXEDDU» ha il titolo senza la tabulazione iniziale
  dell'API.
- [x] **Passo 3: filtri.** Con una configurazione di prova dei `facets` (filter nel child, Task 9): scelto `mtb` le
  altre attività restano con i conteggi senza filtro; aggiungere `hiking` allarga i risultati (OR); un `dove` in più li
  restringe (AND); clic su un filtro: nessuna nuova richiesta nel pannello Network dei DevTools; testo `(oliena` → nessun 500.
- [x] **Passo 4: indirizzo.** Ricaricare la pagina con l'indirizzo copiato ripristina testo, tab, filtri e pagina;
  `?q=oliena&tab=tracks&page=99` mostra l'ultima pagina; un valore inesistente compare a zero e si toglie.
- [x] **Passo 5: errori.** Con `wmwp_shard` a un valore inesistente (`wp option update wmwp_shard nessuno`): errore
  nel gruppo e nel tab «Percorsi», numero «—», nessun errore JS non gestito; poi l'opzione torna `forestasdev`.
  Con la rete rallentata nel browser, scrivendo «sent» e poi «sentiero» restano i risultati di «sentiero».
- [x] **Passo 6: commit (istruzione per l'utente, repo `wp-package`).**
  `git add assets/js/source-tracks.js && git commit -m "feat(oc:8952): source dei percorsi da Elasticsearch, filtri calcolati nel browser"`

### Task 7: Traduzioni e interfaccia pubblica

**File:**
- Crea: `$P/languages/wp-package.pot`, `wp-package-it_IT.po`, `.mo`, i `.json` di `wp i18n make-json`
- Modifica: `$P/includes/search.php` (`wp_set_script_translations( 'wmwp-search', 'wp-package', WMWP_PATH . 'languages' )`
  e lo stesso per `wmwp-source-tracks`), `$P/README.md`, `$P/CHANGELOG.md`
- [x] **Passo 1:** nel container, con il plugin collegato:
  `wp --allow-root i18n make-pot <cartella del plugin> <cartella>/languages/wp-package.pot --domain=wp-package`
  (va fatto da una copia scrivibile: il mount è in sola lettura; in alternativa WP-CLI sull'host).
- [x] **Passo 2:** traduzioni italiane nel `.po` (plurali compresi), poi `wp i18n make-mo` e `wp i18n make-json
  --no-purge`.
- [x] **Passo 3: verifica.** Pagina di prova in italiano: «1 percorso», «28 percorsi», «Mostra altro», messaggio
  senza risultati in italiano; in inglese i testi di base.
- [x] **Passo 4: README «Interfaccia pubblica»:** i due filter con le chiavi di ogni source e del `Card`,
  `wmWpSearch.registerSource()` con `query` e `SourceResult`, i template `search.php` e `card.php` e le classi
  `wmwp-card`, `wmwp-card--type-<typeKey>`; tutto il resto è interno. `CHANGELOG.md`: voci della 0.1.0.
- [x] **Passo 5: commit (istruzione per l'utente, repo `wp-package`).**
  `git add languages includes/search.php README.md CHANGELOG.md && git commit -m "feat(oc:8952): traduzioni italiane e interfaccia pubblica del plugin"`

## Fase B — child theme e configurazione (wp-forestas)

### Task 8: Rotta REST «Contenuti del sito»

**File:**
- Crea: `themes/forestas-child/inc/search.php`; modifica `themes/forestas-child/functions.php` (`require_once` dopo
  `inc/da-sapere.php`, riga 315)

**Interfacce:**
- Produce: `GET /wp-json/forestas/v1/search?q=<testo>&type=<news,evento,da-sapere,pagina>&page=<n>&lang=<it|en>`,
  `permission_callback` pubblico, argomenti sanificati; risposta `{total, by_type: {news, evento, 'da-sapere',
  pagina}, items: Card[]}` (stesso `Card` del Task 5, `typeKey` = chiave del tipo, più `excerpt`).
  Tipi: `post` → `news`, `evento`, `da_sapere` → `da-sapere`, `page` → `pagina`. Lingua con
  `do_action( 'wpml_switch_language', $lang )` prima della query. Una `WP_Query` con `s`, `fields => 'ids'`,
  `posts_per_page => -1` sui quattro tipi dà `by_type` (che non dipende da `type`); `total` e la pagina di 12 sono
  sugli id filtrati per `type`. Immagine: `medium_large` con `_wp_attachment_image_alt`.
- [x] **Passo 1: implementazione.**
- [x] **Passo 2: verifica** in locale (valori di `wp/v2/search` del 09/11):
  `curl -s 'http://127.0.0.1:8090/wp-json/forestas/v1/search?q=sentiero' | jq -c '.total, .by_type'` →
  `101` e `{"news":64,"evento":9,"da-sapere":22,"pagina":6}`; con `&type=evento` `total` 9 e `by_type` uguale;
  `.items | length` 12 e la prima card con `image.src`; `&lang=en&q=trail` → `total` 34 e `url` con `/en/`.
- [ ] **Passo 3: commit (istruzione per l'utente, repo `wp-forestas`).**
  `git add themes/forestas-child/inc/search.php themes/forestas-child/functions.php`
  `git commit -m "feat(oc:8952): rotta REST dei contenuti del sito per la ricerca"`

### Task 9: Shortcode del child, source, parametri, pagina e redirect

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#niente-filtro-percorribilità-review-gate-0911)

**File:**
- Modifica: `themes/forestas-child/inc/search.php`; crea `themes/forestas-child/assets/js/search.js`
- Modifica: `config/post.json` (nuovo `us_content_template` con chiave `template-pagina-ricerca`),
  `config/pagine.json` (voce `cerca-nel-sito`), `themes/forestas-child/style.css` (`Version`)

**Interfacce:**
- Consuma: shortcode `wmwp_search`, filter `wp_package_search_sources` e `wp_package_search_params`,
  `registerSource` (Task 4-5); rotta del Task 8.
- Produce:
  - shortcode `[forestas_search]`: con `shortcode_exists( 'wmwp_search' )` accoda `forestas-search` (dipende da
    `wmwp-search`) e restituisce `do_shortcode( '[wmwp_search]' )`; senza, un form `role="search"` verso `?s=`;
  - nel filter delle source: `tracks` con `slug` `percorsi`, `label` «Percorsi», `nouns` «sentiero/sentieri» (en
    «trail/trails», come la tavola 2), `search_label` «Cerca un percorso», `placeholder` «Nome, codice o luogo — per
    esempio «721» o «Punta La Marmora»» (testi della tavola 2) e `facets` in quest'ordine: `percorribilita` (`disabled`, aiuto «Dato in arrivo» / «Coming
    soon»), `attivita` (list, `taxonomyActivities`, `exclude_prefix` `sardegnasentieri:`), `difficolta` (terms,
    `terms.hiking-difficulty`), `dove` (list, `taxonomyWheres`); più la source `content` (`slug` `contenuti`,
    «contenuto/contenuti», `script` `forestas-search`, `layout` `'cards'`), con il filtro `contenuto` sui tipi del Task 8;
  - nel filter dei parametri: `['q' => 'search_api_fulltext', 'tab' => 'tipo', 'page' => 'pagina']`;
  - `forestas_search_url(): string` — link della pagina con `_wpf_pagina` = `cerca-nel-sito` nella lingua corrente,
    `''` se non c'è;
  - `forestas_search_from_drupal( array $query ): ?array` — da `f[N]=cerca_contenuto_nel_sito:<tipo>` ai parametri
    nuovi: `sentiero`, `itinerario` → `tipo=percorsi`; `news`, `evento` → `tipo=contenuti&contenuto=<tipo>`;
    `page` → `tipo=contenuti&contenuto=pagina`; `poi` e valori sconosciuti → nessun `tipo`; `search_api_fulltext`
    resta; `null` senza `f`.
  - su `template_redirect`: con il plugin attivo, una ricerca `?s=` va con un 302 a `forestas_search_url()` con
    `search_api_fulltext`; sulla pagina di ricerca, `f[N]` va con un 302 a `forestas_search_from_drupal()`.
  Tutti i testi con `forestas_testo()`. Il child non chiama funzioni del plugin: solo filter, `shortcode_exists()` e
  `window.wmWpSearch` (in `search.js` si controlla che esista prima di `registerSource`).
- [x] **Passo 1: Drupal di riferimento.** Da `https://www.sardegnasentieri.it/cerca-nel-sito` e
  `/en/cerca-nel-sito` (curl con User-Agent da browser) titolo IT/EN e presenza della testata: decidono `titolo` della
  voce e se il template ha `[forestas_testata]`.
- [x] **Passo 2: `config/post.json`** — `template-pagina-ricerca` come `template-pagina-mappa`, con
  `[forestas_search]` (e `[forestas_testata]` se il passo 1 la trova). **`config/pagine.json`** — voce
  `{"chiave": "cerca-nel-sito", "nid": null, "titolo": {"it": …, "en": …}, "template": "ricerca", "slug": {"it":
  "cerca-nel-sito", "en": "cerca-nel-sito"}}` (su Drupal `/en/cerca-nel-sito` risponde 200, verificato il 09/11).
- [x] **Passo 3: implementazione** dello shortcode, dei filter, di `search.js` (source `content` che chiama la
  rotta con `lang` e il segnale di annullamento), dei due redirect; `Version` del child alzata.
- [x] **Passo 4: applicare in locale.** `bin/wordpress-config.sh applica --conferma`, poi, con il tunnel della copia
  Drupal aperto (howto `docs/howto/copia-database-drupal.md` di forestas), `bin/wordpress-config.sh pagine --conferma`;
  `bin/wordpress-config.sh esporta` e `git diff config/` mostra solo il template e la voce nuovi.
- [x] **Passo 5: verifica.**
  - `/cerca-nel-sito/?search_api_fulltext=perda+liana` mostra «Percorsi» e «Contenuti del sito» con i numeri;
    `/en/cerca-nel-sito/` in inglese;
  - `curl -sI 'http://127.0.0.1:8090/cerca-nel-sito/?search_api_fulltext=sentiero&f%5B0%5D=cerca_contenuto_nel_sito%3Asentiero'`
    → `302` e `Location` con `search_api_fulltext=sentiero&tipo=percorsi`; con `%3Apage` → `tipo=contenuti&contenuto=pagina`;
  - `curl -sI 'http://127.0.0.1:8090/?s=oliena'` → `302` verso `/cerca-nel-sito/?search_api_fulltext=oliena`;
    il campo «Cerca nel sito» dell'header, in italiano e in inglese, porta lì;
  - **senza plugin:** `wp --allow-root plugin deactivate wp-package`; `/cerca-nel-sito/` mostra il campo semplice,
    nessuno shortcode come testo, `?s=oliena` non viene reindirizzato, `docker logs wordpress-forestas --since 5m`
    senza errori PHP; poi `wp --allow-root plugin activate wp-package`.
- [ ] **Passo 6: commit (istruzione per l'utente, repo `wp-forestas`).**
  `git add themes/forestas-child config/post.json config/pagine.json`
  `git commit -m "feat(oc:8952): pagina /cerca-nel-sito con le source del child e i redirect da Drupal"`

### Task 10: Aspetto dal wireframe e accessibilità

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-e-10-niente-riquadro-indirizzo-di-questa-ricerca-review-gate-0911)

**File:**
- Modifica: `themes/forestas-child/style.css` (una sezione «Ricerca» con le classi `wmwp-*`); `Version` alzata.
- [x] **Passo 1: riferimento.** Tavola 2 del wireframe (`https://claude.ai/artifact/4T3ZRSP1LCrbCuJdNuEGXQ`,
  sezione `s1`; si legge con l'azione `read` e `path: "index.html"` del tool Artifact, perché la pagina pesa 12,5 MB):
  markup di `.cols`, `.rail`, `.facet`, `.cnt`, `.rule`, `.perma`, `.toolbar`, `.res`, `.row`, `.code`, `.nm`,
  `.meta`, `.badge` e i token di `:root`. Pagina di prova con quel markup e quel CSS, misure calcolate nel browser a
  1440px.
- [x] **Passo 2: CSS** della colonna dei filtri, delle righe dei percorsi, del riquadro dell'indirizzo, della card
  (colore del tipo con `wmwp-card--type-<typeKey>`: `#DE1B0D` sentiero, `#58810B` itinerario — testo scurito se
  sotto 4,5:1, come nota il wireframe —, `#1A416F` cammino, `#0F6E6A` da sapere, `#C2410C` news), del focus
  visibile (`#0B63C5`); sotto 1024px filtri a schermo intero aperti da un pulsante.
- [x] **Passo 3: confronto** con la tavola a 1440px: stesse misure di colonna, righe, testi e colori; differenze
  allineate o annotate in notes.md.
- [x] **Passo 4: accessibilità.** Solo tastiera: ordine di tabulazione uguale a quello visivo anche con i filtri a
  schermo intero, frecce sui tab, focus sempre visibile; VoiceOver: titoli dei gruppi, nome e numero dei tab,
  `role="status"` letto una volta per cambio, gruppi di checkbox con la `legend`. Anche da loggato sotto i 600px e
  dopo lo scorrimento (header compatto). *In esecuzione la parte dello screen reader si è verificata sull'albero di
  accessibilità di Chromium, non con VoiceOver (il browser delle prove non ha finestra): la prova con VoiceOver o NVDA
  resta alla verifica su dev («Fine del lavoro» dell'overview). Audit completo WCAG 2.1 AA il 10/11: notes, «Audit di
  accessibilità del 10/11».*
- [ ] **Passo 5: commit (istruzione per l'utente, repo `wp-forestas`).**
  `git add themes/forestas-child && git commit -m "feat(oc:8952): aspetto della pagina di ricerca dal wireframe"`

### Task 10b: Mappa dei risultati (entrata il 10/11, dopo lo scrum)

Rubens: «va fatta ora come discusso»; «la mappa deve essere un componente riutilizzabile per più punti del sito
oltre alla ricerca unica, sarà usato nei dettagli di track, poi layer etc». Decisioni e misure: notes.md, «Mappa dei
risultati».

- [x] **Passo 1: build del componente.** `map/` in `wp-package`: Vite 5 con `@vitejs/plugin-vue`, il componente
  `FeatureCollectionMap` di wm-package dal submodule `map/wm-package` (dal 10/11, decisione di Giuseppe; prima
  scaricato da GitHub), `src/index.js` con `window.wmWpMap.mount()`; risultato versionato in `assets/dist/`.
- [x] **Passo 2: tracce condivise.** `assets/js/track-api.js` (gruppi di 10, quattro richieste, cache per id) usato da
  scarico e mappa; `assets/js/track-map.js` (FeatureCollection della mappa). Test RED→GREEN: `track-api` 6/6,
  `track-map` 2/2.
- [x] **Passo 3: vista nell'indirizzo.** `view`/`map` in `wmwp_search_params()` e in `parseState`/`stateToSearch`
  (test RED→GREEN, 30/30); il child usa `vista`/`mappa`.
- [x] **Passo 4: ricerca.** Selettore «Elenco | Mappa» nel template, `map` nella source `tracks`, vista mappa nel
  controller (file della mappa caricato alla prima apertura, una mappa sola aggiornata a ogni ricerca, avanzamento e
  inquadratura nella nota, clic sul percorso verso `url`).
- [x] **Passo 5: shortcode e asset.** `includes/map.php`: `wmwp-map` registrato, `[wmwp_map]` con `tracks` o
  `geojson`, `assets/js/map-embed.js`.
- [x] **Passo 6: aspetto e testi.** CSS del child dalla tavola 2 (`.seg`, `.res-map`, `.map-ctl`, `.cap`), child
  1.8.17; traduzioni (56 su 62, restano nomi propri); README, `map/README.md`, CHANGELOG.
- [x] **Passo 7: verifica nel browser** (Playwright): vedi notes.md.
- [x] **Passo 8: schermo intero** (chiesto il 10/11): controllo `FullScreen` di OpenLayers aggiunto nel wrapper
  (`fullScreen` in `mount()`), pulsante della tavola (`.map-full`) sotto i +/−, child 1.8.19.
- [x] **Passo 9: selezionati in primo piano** (chiesto il 10/11): `selected` in `wmWpTrackMap.collection()` (test
  RED→GREEN, 4/4) e in `map.collection()`; la mappa si ridisegna quando cambia la selezione. Gli altri risultati con lo
  stile `.lg-near` della tavola (tratteggio blu ardesia), verificati anche con la carta di base bloccata.
- [x] **Passo 10: carta dell'app** (chiesto il 10/11): `wmwp_map_tiles()` dal `config.json` dell'app (test PHP
  `map-tiles.php` RED→GREEN), `tiles` in `mount()` (sorgente XYZ al posto di OSM, zoom delle tile 16, vista 17).
- [x] **Passo 11: colori dei layer** (chiesto il 10/11): `wmwp_parse_map_layer_colors()` e `wmwp_app_map_config()` (test
  PHP esteso, RED→GREEN), `layers` in `track-api.js` (6/6), colore per layer in `track-map.js` (6/6).
- [x] **Passo 12: popup al clic** (chiesto il 10/11): `popup` in `mount()` (Overlay di OpenLayers), `map.card()`
  in `source-tracks.js`, card del template `wmwp-card`; niente navigazione al clic.
- [x] **Passo 13: mappa disegnata man mano** (difetto trovato da Rubens il 10/11): `cached()` in `track-api.js`
  (test RED→GREEN, 8/8), `partial` in `onProgress` di `map.collection`, `update(…, { keepView })` nel wrapper.
- [x] **Passo 14: selezione dal popup** (chiesto il 10/11): pulsante nel popup (`wmwp-map-popup__select`), `setSelected(…, quiet)`,
  `update(…, { keepView, keepPopup })`; child 1.8.20.

## Fase C — documentazione e chiusura

### Task 11: Documentazione

**File:**
- wp-forestas: `CLAUDE.md` (la trappola sulla cartella `wm-package` diventa quella di `wp-package`: commit fisso
  `WP_PACKAGE_REF`, file `.wpf-ref`, clone di sviluppo collegato, mount in sola lettura), `README.md` (righe 5,
  59-87), `docs/knowledge/inizializzazione-wordpress.md`, `docs/howto/ricreare-wordpress-da-zero.md` (passi e AVVISI
  attesi, data dell'ultima verifica se si rifà il ciclo)
- forestas: `docs/knowledge/wordpress-nello-shard.md`, `docs/howto/messa-in-opera-wordpress-uat.md`,
  `docs/howto/messa-in-opera-wordpress-dev.md`: variabili nuove del `.env`, `scripts/wordpress-up.sh` dopo il
  puntatore, `wm-package` da disattivare e cancellare a mano quando arriva `wp-package`, come tornare indietro
  (cancellare `/cerca-nel-sito` nelle due lingue, poi `applica --conferma`)
- [x] **Passo 1:** aggiornamenti, controllando con `grep -rn "wp-geohub\|GEOHUB\|wm-package" CLAUDE.md README.md docs/`
  che restino solo i riferimenti storici voluti.
- [x] **Passo 2:** controllo della lingua dei documenti con il grep di wm-plan («Il controllo prima di consegnare»).
- [ ] **Passo 3: commit (istruzione per l'utente).** wp-forestas:
  `git commit -m "docs(oc:8952): installazione di wp-package e uscita di wp-geohub"`; forestas, senza il gitlink:
  `git commit -m "docs(oc:8952): wp-package nella messa in opera di WordPress"`.

### Task 12: Verifica finale e messa in opera su dev

- [x] **Passo 1: locale.** Ciclo intero in italiano e inglese: ricerca «perda liana», tab, filtri, pagine,
  Indietro, indirizzo copiato e riaperto, errore dello shard, plugin disattivato; `node --test "$P"/tests/js/*.test.js` PASS.
- [x] **Passo 2: repo GitHub.** Creato da Giuseppe nello scrum del 10/11: `webmappsrl/wp-package`, pubblico, vuoto;
  il plugin ha preso lo stesso nome (notes.md, «Nome del plugin»). `git -C "$P" remote add origin
  https://github.com/webmappsrl/wp-package`; storia nuova su un branch orfano (notes.md, «Storia del repository»); push
  solo su istruzione dell'utente. *Fatto il 10/11 su istruzione di Rubens («puoi fare tutto…, il plugin puoi mettere
  già su main»): push di `main` (`77e7c1d`), `develop` e del branch del ticket; PR #1 verso `develop` chiusa con squash
  (`d4b643f`), PR #2 da `develop` a `main` chiusa con merge commit («Merge branch 'develop'», `9537a61`); `develop`
  allineato a `main` con un fast-forward.*
- [x] **Passo 3: commit fisso.** `WP_PACKAGE_REF` = hash da `git ls-remote https://github.com/webmappsrl/wp-package
  refs/heads/main`; in locale, senza `WP_PACKAGE_DIR`, `scripts/wordpress-up.sh` scarica il plugin, scrive
  `.wpf-ref` e lo attiva; un secondo avvio non riscarica; cambiato l'hash, riscarica. *Fatto il 10/11:
  `WP_PACKAGE_REF=9537a61d9beedaa1ee7c51b9c5d4e785775a7698`. Senza `WP_PACKAGE_DIR` l'init toglie il link al clone,
  scarica, scrive `.wpf-ref`; il plugin resta attivo e la ricerca dà gli stessi numeri; un riavvio con lo stesso
  hash non riscarica; senza `.wpf-ref` riscarica. Trovato e corretto: `cp -a` copiava i permessi della cartella
  temporanea (700) sulla cartella del plugin, ora `chmod 755`. Poi `WP_PACKAGE_DIR` rimesso. Attenzione nelle prove:
  `scripts/wordpress-up.sh` senza modifiche non riavvia il container, quindi l'init non riparte; serve `docker
  restart`.*
- [ ] **Passo 4: dev** (dopo push e puntatore, su istruzione dell'utente): sul server `git submodule update --init
  --recursive`, `.env` con `WP_PACKAGE_SHARD`/`APP_ID`, `scripts/wordpress-up.sh`, `wm-package` tolto a mano,
  `applica --conferma` e `pagine --conferma`; verifica su `wp.forestas.dev.maphub.it` come al passo 1.
