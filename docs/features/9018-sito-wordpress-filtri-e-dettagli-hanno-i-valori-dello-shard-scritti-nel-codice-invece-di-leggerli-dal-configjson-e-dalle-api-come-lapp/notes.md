> Ticket: oc:9018

# Notes — Sito WordPress: filtri e dettagli leggono i valori dello shard dal config.json e dalle API

## Deviazioni dal piano

Le deviazioni, task per task, sono nella sezione «Divergenze dal piano, task per task» più sotto. Fra quelle senza un
task a sé:

- **Anello senza `roundtrip`:** quando lo shard non dà il campo, il dettaglio torna alla regola di prima, fine entro
  50 m dall'inizio (`forestas_dettaglio_anello()`, `inc/dettaglio.php`), invece di dire «non è un anello». Il piano
  chiedeva solo `roundtrip`; il ripiego evita che un anello senza il campo mostri anche la bandiera dell'arrivo sopra
  la partenza (in un anello la mappa ha solo la partenza).

## Bug trovati

- **Tacche dell'accessibilità spente** (già nel ticket): il registro del child ha
  `inclusivity-difficulty-ae` minuscolo (`themes/forestas-child/inc/statuses.php:80`), lo shard crea
  `inclusivity-difficulty-AE` maiuscolo; `wmwp_status_level()` confronta in modo stretto e dà livello 0.
  Il test `tests/dettaglio.php:254` usa il minuscolo e non se ne accorge.

## Decisioni

- **Tag di Orchestrator:** non associati, su indicazione del dev (23/11).
- **Le FeatureCollection non si dichiarano nel sito** (Giuseppe, Slack del 23/11 ore 12:41: «non devi
  dichiarare le featurecollection che usi ma le leggi dal file di configurazione, è tutto lì, come
  infatti fa già la webapp»). Il plugin le legge da `MAP.controls.overlays` del `config.json`: niente
  opzione nel plugin, niente elenco nel child. Supera la scelta fatta poco prima nel dialogo (una
  checkbox per overlay nella pagina di impostazioni del plugin).
- **Quali overlay diventano filtri** (deciso dal dev, 23/11): solo quelli le cui feature hanno
  `layer_id`; gli altri restano solo sulla mappa e non entrano nella ricerca. Nessun nome di overlay
  nel codice: la regola è sul campo. Sul dev (23/11) solo due dei cinque overlay hanno
  feature con `layer_id`: Aree Catastali (`1.geojson`, 7 feature) e Aree geografiche (`2.geojson`, 39).
  Aree forestali (`3.geojson`: 1 feature, `name`, `clickable: false`), Aree montane (`4.geojson`: 1
  feature senza proprietà) e Aree no caccia (`5.geojson`: 463 poligoni con `ISTITUTO`, `NOME`,
  `Superf_ha`) sono solo sfondi della mappa. Nella webapp il clic su una feature con `layer_id` apre
  quel layer (`map-core/src/directives/feature-collection.directive.ts:158-159`).
- **Niente filtri Provincia e Comune nella ricerca** (deciso dal dev, 23/11): come le app, i filtri
  di luogo sono solo gli overlay con `layer_id` (sul dev Aree Catastali e Aree geografiche). Nella
  ricerca i luoghi arrivano solo come nomi (`taxonomyWheres` di Elastic; percorso 328 sul dev: `ZSUD1,
  ZSUD3, Area D (Sud-OVEST), Sardegna, Sulcis Iglesiente, Buggerru, Fluminimaggiore`), senza il tipo:
  distinguerli vorrebbe dire scrivere nomi nel codice (`FORESTAS_SEARCH_PROVINCES`, `inc/search.php:27`).
  Spariscono quindi anche i filtri `provincia`, `comune` e `area` (quello per nome `^Area `) dei
  percorsi e dei POI (`inc/search.php:606-630`, `696`). Il tipo del luogo (`_admin_level`: 8 comune,
  6 provincia, 4 regione) c'è solo nel JSON del dettaglio: lì le app lo usano
  (`taxonomy-where.component.ts:45-47`) e il plugin anche (`wmwp_detail_places()`,
  `includes/detail-data.php:192`), quindi il dettaglio non cambia.
- **POI nelle aree: con le geometrie del geojson** (deciso dal dev, 23/11: «fai come dice Giuseppe»).
  Allo scrum del 23/11 (08:30, trascrizione verificata) Giuseppe: «quando filtri per zone geografiche
  di quel modo, ti scarichi il geo Jason e usi quello. […] Le geometrie per fare il filtro le devi
  avere a priori. Sta cosa dei poi è inutile che li buttiamo su elastic». I POI non stanno nei layer
  delle Aree geografiche (0 su 976 su dev e UAT, voluto da oc:9001: «Solo percorsi, niente POI»), quindi
  per i POI l'area si ricava dalla geometria: il punto cade dentro il poligono dell'area. I percorsi
  restano filtrati con `layers`. Le app non fanno questo calcolo (nessun point-in-polygon in wm-core e
  map-core): è una differenza voluta. La frase della description di oc:9018 «per i POI il filtro usa le
  geometrie del geojson» l'ha scritta una sessione di Claude il 23/11 alle 08:44 riassumendo lo scrum,
  non Giuseppe con queste parole.
- **Riga del percorso nei risultati: l'ultimo luogo dell'elenco** (deciso dal dev, 23/11), come la
  card delle app (`search-box.component.html:10-11`). Oggi la riga mostra tutti i comuni (`meta_facet`
  `comune`, `inc/search.php:661`, tavola 2), che senza il filtro Comune non si riconoscono più.
  Verificato il 23/11 incrociando `taxonomyWheres` di Elastic con `taxonomy_wheres.properties.admin_level`
  del DB (sola lettura): l'ultimo luogo è un comune in 772 percorsi su 772 sul dev e 170 su 170 su UAT, e
  i comuni stanno sempre tutti in fondo all'elenco. Sul dev 18 percorsi finiscono con «Cagliari» (comune
  e provincia omonimi): l'ultimo è il comune, la provincia sta prima (es. percorso 205). 575 percorsi del
  dev hanno più comuni: la riga ne mostra uno, gli altri restano nel dettaglio. L'ordine viene da
  `properties.taxonomy_where` della traccia (`EcTrack.php:836`), non da una regola del codice: un test
  sui dati lo controlla.

- **Ordine dei valori dei filtri che non sono in `MAP.filters`** (deciso dal dev, 23/11): ripiego
  nel child, un ordine per famiglia (per la difficoltà T, E, EE, EEA) scritto in un solo punto; un
  valore che non vi compare si mostra lo stesso, in fondo. Le app questo problema non lo hanno: non
  riordinano nulla, prendono l'ordine dell'array di `MAP.filters`, e il filtro per difficoltà non ce
  l'hanno (la difficoltà compare solo nel dettaglio come valore singolo,
  `tab-detail.component.html:40-50`). È un ripiego temporaneo: si toglie quando lo shard dichiara
  questi filtri in `MAP.filters` con le opzioni ordinate (sezione «Da chiedere allo shard»).

- **Nomi dei valori: quelli dello shard** (deciso dal dev, 23/11), come le app (`wmtrans.utils.ts:21-28`):
  il child non ha più nomi propri. I nomi che il child aveva scritti nel codice sono nella tabella qui
  sotto: il dev valuterà se cambiare l'import dello shard perché importi questi nomi, o lasciare quelli
  dello shard.

- **Colori dei tipi di percorso** (deciso dal dev, 23/11, dopo una prima scelta presa senza il dato del
  contrasto):
  - **linee dei percorsi sulla mappa: colore del layer** (`MAP.layers[].style.color`). Sul dev
    l'Itinerario passa da `#58810B` a `#608d0d`. Corretto nella challenge del 24/11: non la regola
    delle app (`layers[0]`, rosso di default se senza colore: su UAT 166 percorsi su 170 hanno
    un'area catastale come primo layer) ma quella di oggi del plugin, primo layer colorato nell'ordine
    di `MAP.layers` (`track-map.js:44-50`), con `strokeColor` solo se non vuoto (oggi `''` ovunque);
  - **etichette e fasce del dettaglio: colori del child**, come oggi (`inc/dettaglio.php:307-309`,
    `style.css`): sono design del sito, presi da Drupal, e passano il contrasto AA. Il verde dello shard
    con il testo bianco sta a 3,95:1 (sotto 4,5:1), quello del child a 4,61:1 (description del ticket);
  - **tipo di percorso che il child non conosce:** etichetta con il colore neutro del testo del sito
    (passa AA) e il nome dello shard; oggi diventerebbe «sentiero» in rosso (`inc/dettaglio.php:198`).

- **Parametri dell'indirizzo della ricerca in inglese** (deciso dal dev, 23/11), come la webapp
  (`track`, `poi`, `layer`, `filter`, `search`: `url-handler.service.ts:33-41`): il child smette di
  tradurre i parametri del plugin (`wp_package_search_params`, `inc/search.php:475-485`) e le chiavi dei
  filtri passano in inglese. Il valore di `tab` è lo slug della source, uguale nelle due lingue (in inglese
  anche lui, decisione del 24/11 più sotto); il testo cercato passa da `search_api_fulltext` a `q`. Il sito non è ancora pubblico (produzione ancora
  Drupal), quindi nessun indirizzo salvato si rompe; i vecchi link di Drupal (`f[N]=…`) li converte già
  il redirect del child (`inc/search.php:816-877`), che va aggiornato ai nomi nuovi.

- **La fonte vince sempre, anche se sbagliata** (deciso dal dev, 24/11): il sito mostra quello che dà lo
  shard; un errore nei dati non si nasconde nel sito, si segna in «Da chiedere allo shard». Primo caso:
  il filtro Attività prende le opzioni di `MAP.filters` (dev: c'è `trail` «Sentiero», manca
  `accessible-trails`) e il child smette di escludere `trail`, `itinerary`, `walk` per nome
  (`inc/search.php:575`).
- **Cosa lo shard dà già (dev, 24/11)**, verificato in `taxonomy_terms`: tipologie di disabilità con
  nome solo italiano e icona (`inclusivity-disabilities-physical` `txn-mobility-disability`,
  `-cognitive` `txn-cognitive-disability`, `-sensory` senza icona); stati della percorribilità con nome
  italiano e inglese e icona (`walkability-status-unknown` «Sconosciuta / Unknown» `wm-icon-question`,
  `-not-walkable` `wm-icon-close`, `-partially-walkable` `wm-icon-alert`, `-walkable` `wm-icon-check`).
  Manca solo l'elenco degli stati di una famiglia con lo stato di assenza, in un'API.
- **Icone e nomi solo dallo shard, senza ripiego** (deciso dal dev, 24/11): «anche le icone devono
  stare nello shard». Rivisto nella challenge per le disabilità: il dettaglio mostra su ogni percorso
  le tre tipologie accese o spente, e le spente non arrivano da nessuna API; il dev ha deciso «un mix di
  entrambe, le icone e i nomi devono essere uguali nello shard perché in futuro potrebbero servire
  all'app, ma lo stile dell'accessibilità del sito non va rotto e quindi può continuare come ora». Il
  child tiene elenco, nomi e icone delle tre; lo shard si allinea al sito (ticket sullo shard). Per
  coerenza si tolgono invece i nomi degli stati della percorribilità scritti nel plugin
  (`includes/status.php`): il registro tiene solo ordine e stato di assenza. Eccezione decisa nella
  challenge (24/11): resta il nome dello stato di assenza, «Sconosciuta / Unknown», perché molti percorsi
  non hanno nessuno stato e nessuna API pubblica quel termine (`taxonomy_termables`, 24/11): su UAT 98
  su 170 (63 percorribili, 8 non percorribili, 1 «sconosciuta»); sul dev 509 su 778 alle 15:30 (228 e
  41), dopo l'import del pomeriggio.
- **Regola della configurazione** (Giuseppe, scrum del 24/11): «Tu devi leggere sempre dalla
  configurazione. Cascasse il mondo, il dato da là lo devi prendere perché da lì lo prende anche la
  web app»; «Se senti l'esigenza che manca qualcosa e la web app non ce l'ha, allora va nella
  configurazione lo stesso e poi è la web app che si deve allineare». Conferma l'impostazione del
  ticket: quello che al sito manca nel `config.json` va nel ticket sullo shard, non nel codice del sito.

### Nomi che il child aveva nel codice (develop 0be0415, 23/11)

Confronto con il `config.json` del dev scaricato il 23/11; «—» dove lo shard non dà il nome in quella
lingua o non pubblica la famiglia in `MAP.filters`.

| Identifier | Child (it / en) | Shard, `config.json` dev (it / en) |
|---|---|---|
| `hiking` | Camminate giornaliere / Day hikes | Alte vie / High routes |
| `trekking` | Sentieri lunghi / Long-distance trails | Dorsali escursionistiche a tappe (per esperti) / Trekking (experts) |
| `mtb` | Mountain Bike / Mountain biking | Mountain Bike / Mountain Bike |
| `horse` | Turismo equestre / Horse riding | Turismo equestre / Horse |
| `nordic-walking` | Passeggiate e Nordic Walk / Walks and Nordic walking | Passeggiate e Nordic Walk / Nordic Walking |
| `hiking-difficulty-T` | T — turistico / T — tourist | — (nome del termine: «T») |
| `hiking-difficulty-E` | E — escursionistico / E — hiking | — («E») |
| `hiking-difficulty-EE` | EE — per esperti / EE — for experts | — («EE») |
| `hiking-difficulty-EEA` | EEA — attrezzato / EEA — equipped | — («EEA») |
| `route-type-trail` | Sentiero / Trail | — |
| `route-type-itinerary` | Itinerario / Itinerary | — |
| `route-type-walk` | Cammino / Walk | — |
| `validation-status-validated` | Validato / Validated | — |
| `validation-status-certified` | Certificato / Certified | — |
| `validation-status-under-review` | In revisione-validazione / Under review | — |
| `validation-status-pre-registration` | In pre-accatastamento o manutenzione / Pre-registration or maintenance | — |
| `validation-status-unverified` | Non verificato / Not verified | — |

Fonti nel child: attività `inc/search.php:578-584`, difficoltà `inc/search.php:597-601`, tipo di
percorso `inc/search.php:491-493`, validazione `inc/search.php:506-510`. I nomi inglesi di tipo di
percorso e validazione sono stati scritti da noi perché l'API li dava solo in italiano (commento a
`inc/search.php:497-501`). I nomi inglesi dei tre tipi di percorso sono già inseriti a mano in Nova su
ogni ambiente (oc:8969).

## Come fanno le app (wm-core `origin/develop` f5e2b7c, 23/11)

webmapp-app usa lo stesso commit di wm-core; wm-webapp (6058c60) non ha differenze su filtri, pipe e
store. Il sito deve comportarsi allo stesso modo.

- **Opzioni dei filtri:** solo da `MAP.filters` del `config.json`; ogni chiave di tipo `select`
  diventa un filtro (`store/conf/conf.selector.ts:53-58`). Una famiglia non dichiarata lì non esiste.
- **Ordine:** quello dell'array `options`, nessun ordinamento proprio; le opzioni selezionate passano
  in testa (`filters/select-filter/select-filter.component.html:54`,
  `pipes/wm-filter-by-selection.pipe.ts:13-16`).
- **Conteggi:** da Elastic; un'opzione con 0 percorsi non si mostra
  (`select-filter.component.html:59`, `store/features/ec/ec.selector.ts:157`).
- **Nomi:** dell'opzione, nella lingua corrente, poi quella di default, poi il primo non vuoto
  (`pipes/wmtrans.utils.ts:21-28`).
- **Difficoltà, percorribilità, validazione:** le app non hanno questi filtri.
- **Aree:** gli overlay sono solo layer della mappa; si filtra per area solo scegliendo un layer
  (`&layer=<id>` a Elastic, `store/features/ec/ec.service.ts:196`). Un filtro di ricerca per area
  nelle app non c'è.
- **Cursori** (`track_distance`, `track_duration`): filtrati da Elastic, non dal client.

## Challenge (24/11)

- **Redirect da Drupal: indirizzi salvati prima del cambio di dominio** (deciso dal dev). Verifiche su
  UAT: l'elenco delle tracce di Elastic (`/api/v2/elasticsearch`) ha `sardegnasentieri_id` ma non
  `forestas`, mentre la `pois.geojson` (`/api/v2/app/1/pois.geojson`) porta `forestas.url` per 976 POI
  su 976. Drupal ha le pagine inglesi con il prefisso `en` (`language.negotiation`): l'alias inglese è
  uguale all'italiano in 152 sentieri su 153 e in 170 POI su 412. I 170 percorsi dello shard hanno un
  solo alias italiano ciascuno (nessun alias storico); 39 percorsi e 37 POI hanno `forestas.url`
  codificato.
- **`MAP.filters` scritto a metà import** (deciso dal dev: il sito resta come le app, si corregge lo
  shard). Su UAT le attività di `MAP.filters` sono 5 (trekking, trail, horse, hiking, nordic-walking)
  mentre l'aggregazione di Elastic ne ha 7 (anche `mtb` 18 e `educational-trails` 1). Il file su storage
  (`/wmfe/forestasuat/1/config.json`) ha Last-Modified 05:00:32 UTC, cioè le 06:00:32: l'import con
  `--reset` delle 06:00 ha creato le tracce fra le 06:00:30 e le 06:01:01 e attacca le attività dopo.
  Le tracce `mtb` sono dell'utente 2, proprietario dell'app: lo stesso file riscritto dopo l'import le
  conterrebbe. `TaxonomyActivity` non ha hook che riscrivano il `config.json`; `TaxonomyTerm` lo
  riscrive solo per le famiglie (`parent_id` nullo, `TaxonomyTerm.php:69-78`).
- **Filtro del tipo di percorso da `MAP.filters.layers`** (deciso dal dev, come le app e la regola di
  Giuseppe del 24/11). Su UAT le opzioni sono i layer 11, 10, 8 (Sentieri, Itinerari, Cammini di
  Sardegna; sul dev l'ordine è 8, 10, 11); l'aggregazione `layers` di Elastic ha 170 percorsi nel layer
  11, e tutti i 170 sono `route-type-trail`: oggi i due filtri danno lo stesso risultato. Il filtro del
  child sul `route-type` si toglie.
- **Filtri di luogo in un solo parametro `layer`** (deciso dal dev, come le app). Le voci di
  `MAP.controls.overlays` hanno solo `label`, `icon`, `url`, `type` (dev e UAT, 24/11): niente id;
  «Aree Catastali» senza `en`; il numero della FeatureCollection cambia fra ambienti.
- **Difficoltà: un filtro per scala** (deciso dal dev). Sul dev il 24/11, 15:30: `easy` è «Facile» in
  quattro scale (ippovia, mobilità dolce, Nordic Walking, sentiero didattico); `hard` è «Difficile» per
  ippovia e mobilità dolce e «Impegnativo» per Nordic Walking e sentiero didattico; 89 percorsi hanno 2
  scale e 12 ne hanno 3. I nomi delle scale sono in `LABELS.taxonomies` (solo italiano). Le app non
  hanno un filtro della difficoltà.
- **Sentieri di Drupal che UAT non ha** (solo da sapere, non di questo ticket): Drupal ha 612 nodi
  `sentiero` pubblicati. UAT ha 170 percorsi (nid da 134 a 1075, tutti `/sentieri/`): i 442 con alias
  `/sentiero/` (nid da 1255 a 5791) lì non ci sono. Il dev alle 14 del 24/11 aveva anche lui 170
  percorsi; alle 15:30 ne ha 778 (608 creati nelle ultime 3 ore, import in corso): 173 `/sentieri/`,
  442 `/sentiero/`, 134 `/itinerario/`, 2 `/itinerari/`, 27 `/node/`. Le prove del redirect con
  `/sentiero/` e `/itinerario/` si fanno sul dev.

- **Correzioni dirette dell'overview, senza domanda** (fatti verificati il 24/11): tipi dei POI confrontati
  come `poi_type_<identifier>` come le app; chiave doppia `poi_type` ignorata; aree di un overlay solo
  con `layer_id` presente in `MAP.layers`; appartenenza dei POI alle aree rifatta anche quando cambia la
  copia dei POI; opzione scelta visibile anche a zero; `config.json` e geojson scaricati con WP-Cron, con
  richiesta condizionale (`ETag` e `Last-Modified` presenti) e chiave di cache nuova; parametro della
  pagina `pg`, non `page`; `strokeColor` vuoto (`''`) trattato come assente; redirect dei link di Drupal
  con il solo testo e di `?s=`; indirizzi codificati nella prova del redirect.
- **Rischi della challenge rimasti fuori, ipotetici:** `roundtrip` dello shard diverso dai 50 m del
  plugin (serve una MultiLineString o una fine fra 50 e 110 m: su 60 tracce di UAT shard e plugin
  concordano); secondo tema che usa il plugin; storage degli overlay non raggiungibile dal container;
  301 sbagliato dal ripiego per slug (serve un alias vecchio uguale allo slug nuovo di un altro
  elemento); forma nuova di una cache letta dal codice vecchio dopo un rollback; ultimo luogo che non è
  un comune.
- **Itinerari e cammini con dati veri:** il revisore li dava come non verificabili (sul dev alle 14 i
  percorsi erano tutti sentieri); alle 15:30 il dev ha 617 `route-type-trail`, 108
  `route-type-itinerary` e 53 `route-type-walk`. Le prove si fanno sul dev.
- **Rischi già noti:** child e plugin si rilasciano insieme (un plugin vecchio con il child nuovo resta
  senza filtri di luogo); 11 POI su 976 di UAT cadono fuori da tutte le aree catastali e 9 fuori dalle
  geografiche, e con un filtro di luogo non si trovano; Provincia e Comune tolti rispetto alla tavola 2
  (deciso dal dev il 23/11: come le app).

- **Slug inglese della ricerca** (deciso dal dev, 24/11: «fallo qui»): `search-in-our-website` in `config/pagine.json`.
  Su Drupal la ricerca è la view `cerca_nel_sito` con percorso `cerca-nel-sito`, senza alias né traduzione del percorso
  (copia del DB, `config`), quindi `/en/cerca-nel-sito`; oc:8952 l'aveva copiato. Delle 23 pagine di
  `config/pagine.json` 5 avevano già lo slug inglese tradotto (`contacts`, `interactive-map`, `legal-notes`, `credits`,
  `accessibility-statement`). Il vecchio indirizzo porta al nuovo con un 301 che tiene la query
  (`forestas_search_old_slug_target()`, priorità 8, prima del redirect delle pagine che la perderebbe). Lo slug lo
  applica `bin/wordpress-config.sh pagine --conferma`, che aggiorna anche le pagine che esistono già
  (`contenuti-lib.php:368`); in locale, senza la copia di Drupal, messo con `wp post update`. Provato in locale:
  `/en/cerca-nel-sito/?search_api_fulltext=oliena` → `/en/search-in-our-website/?q=oliena`, vecchio filtro dei sentieri
  → `?tab=trails&type=11`, canonical e selettore della lingua con lo slug nuovo.

- **Valori della query string in inglese** (deciso dal dev, 24/11: «parametri nella query string tutti in inglese
  vai»): oltre alle chiavi, anche i valori che il sito scrive da sé. `tab`: `percorsi` → `trails`,
  `punti-di-interesse` → `points-of-interest`, `contenuti` → `content`, `enti` → `organisations` (slug delle source,
  `inc/search.php`); `content`: `evento` → `event`, `da-sapere` → `good-to-know`, `pagina` → `page`, `news` resta
  (`FORESTAS_SEARCH_TYPES`). Cambiano con loro la tabella dei vecchi link di Drupal
  (`forestas_search_from_drupal()`) e le classi delle card (`.wmwp-card--type-event`, `.wmwp-card--type-good-to-know`,
  `style.css` 1.18.2). Restano come sono i valori che vengono dallo shard (identifier dei filtri, id dei layer, tipi e
  ruoli degli enti, `comune`, `gestore`…): la fonte vince. Un indirizzo con i valori vecchi (`?tab=percorsi&q=monte`)
  apre il tab «Tutti» con il testo cercato, senza errori (provato nel browser); non serve un redirect, perché il sito non è pubblico e i link di Drupal li converte già il
  redirect. Provato in locale: i 9 test del child passano; `?tab=content&content=event` apre «Contenuti del sito» con
  le sole card degli eventi, `?tab=organisations` e `?tab=points-of-interest` il loro tab, nessun errore in console;
  il vecchio filtro degli eventi di Drupal porta a `?tab=content&content=event`.

## Divergenze dal piano, task per task

### Task 1: faccette

- **Legende delle tacche** (validazione, percorribilità, sigle AT/AE/AEE): elencano tutti gli stati con il nome, e i
  nomi degli stati che il percorso non ha non arrivano da nessuna API. Come per le tre tipologie di disabilità
  (decisione del dev nella challenge), restano com'erano, con i nomi nel child in un punto solo
  (`FORESTAS_STATUS_LEGEND_NAMES`, `inc/statuses.php`); lo shard deve pubblicare l'elenco degli stati.
- **Ordine dei filtri** quello di oggi (percorribilità, validazione, tipo, attività, scale, aree; tavola 2), non quello
  scritto nel piano.
- **Identificatori in inglese** (regola di wm-plan): `FORESTAS_DIFFICULTY_ORDER` invece di
  `FORESTAS_ORDINE_DIFFICOLTA`.
- **Aggiunte al plugin** che il piano non aveva: `meta_last_field` della source tracks, `lang` sui nomi dei termini e sul
  titolo della faccetta, faccetta dei tipi dei POI riconosciuta come la prima che non è un luogo (vedi il `notes.md`
  del plugin).
- **Chiavi in inglese anche per contenuti ed enti**: `content`, `entity-type`, `role`, nella rotta degli enti e nello
  script del child.
- Il codice è stato scritto prima di aggiornare `tests/search-facets.php`: il test aggiornato passa, ma non l'abbiamo
  visto fallire prima.

### Task 2: vecchi link di Drupal

Il canonical prende le chiavi da tutte le source, nell'ordine delle source (`q`, `tab`, filtri, `pg`), con i valori in
ordine naturale (8 prima di 11), come `stateToSearch()`.

### Task 3: dettaglio

- **Tipo «altro»** per un route-type sconosciuto o assente: `#1A1A1A`, fascia `#F2F2F2`, «Il percorso in breve»,
  breadcrumb senza pagina d'elenco.
- **Nomi estesi dei gradi** (Allegato H, «Accessibile Escursionistico») restano nel child: lo shard ha solo le sigle, e
  «AE · AE» non spiegherebbe niente. Stessa regola delle legende.
- **Colori delle linee della mappa del dettaglio**: la traccia della scheda dal primo layer colorato dei suoi `layers`;
  i percorsi vicini del POI, che non portano i layer, dal layer del loro route-type (corrispondenza calcolata dal cron
  del `config.json`, in pagina solo dalla cache), altrimenti il colore del tipo; legenda con i nomi dei tipi dello
  shard, una voce per colore.
- **Tipi di ente sconosciuti**: nella card e in `/enti` restano nascosti come prima («Ente», nessuna categoria); nei
  filtri della ricerca compaiono grezzi, come i ruoli (`tests/entes-search.php` aggiornato).

### Task 4: indirizzi Drupal

La prova sui dati veri l'abbiamo fatta in locale, con WordPress collegato allo shard di dev: sul dev e su UAT si rifà
dopo il deploy (Task 5).

### Task 1, Step 5: filtri sui dati veri

Fatto la prima volta il 24/11 pomeriggio, sui dati di dev (778 percorsi) e di UAT (170): zero errori, conteggi uguali al
DB dello shard; il dettaglio per opzione non era stato scritto. Rifatto sul dev il 24/11 sera, con le prime tre opzioni
di ogni filtro: conteggi del sito in locale (sul dev, 19:42 UTC) contro il DB del dev (sola lettura, 19:41 UTC). Tutti
uguali:

| Filtro | Opzioni (sito = DB) |
|---|---|
| Percorribilità | Sconosciuta 509 (percorsi senza stato), Non percorribile 41, Percorribile 228 |
| Stato validazione | Non verificato 305 (senza stato), In revisione 188, In pre-accatastamento 237 |
| Tipo percorso | Cammini 53 (layer 8), Itinerari 108 (10), Sentieri 617 (11) |
| Attività | Itinerario 161, Alte vie 547 (`hiking`), Turismo equestre 79 |
| Difficoltà escursionistica | T 170, E 474, EE 61 |
| Difficoltà mountain bike | MC 49, TC 30, BC 20 |
| Difficoltà ippovia | Medio 8, Facile 7 |
| Difficoltà mobilità dolce | Medio 21, Facile 20, Difficile 14 |
| Aree geografiche (percorsi) | Goceano 20, Campidano di Cagliari 14, Monte Acuto e Monte Nieddu 30 |
| Aree Catastali (percorsi) | Area B 136, Area C 106, Area D 173 |
| Tipi dei POI | Accessibilità 6, Alberi monumentali 8, Area di sosta 36 |
| Aree dei POI (calcolate dal plugin con le geometrie) | Goceano 11, Campidano 31, Monte Acuto 7, Area B 101, Area C 153, Area D 289 |

Le aree dei POI si confrontano con PostGIS sulle geometrie degli overlay (`ST_Intersects` con i poligoni del geojson),
non con `layers.geometry` del DB: quella non è il poligono dell'area (il layer 3 conterebbe 691 POI invece di 289).

### Task 6, Step 1: accessibilità e screen reader

Fatto la prima volta il 24/11 pomeriggio (`fieldset`/`legend`, «Show more», `lang="it"`, opzione scelta a zero, 375
px, nessun errore in console). Rifatto il 24/11 sera dopo la review interna, nel browser a 375 px, da anonimo e da
collegato (barra di amministrazione presente):

- ricerca inglese, tab dei percorsi: ogni filtro è un `fieldset` con `legend`; le legend delle scale di difficoltà e
  di «Stato validazione» e i nomi solo italiani delle opzioni hanno `lang="it"`; «Geographical areas» ha 38 aree con
  «Show more» (`aria-expanded="false"`), «Aree Catastali» 7; il pulsante «Filters» apre i filtri;
- un'opzione scelta che scende a zero (difficoltà EEA con l'area 14) resta spuntata con il suo nome, «EEA 0»;
- dettaglio inglese del cammino 263: validazione e grado di difficoltà con `lang="it"` nella riga compatta e nella
  tabella, «Print (opens in a new window)»;
- nessun errore in console.

### Task 5: messa in opera

Fatte solo le procedure (`forestas/docs/howto/messa-in-opera-wordpress-uat.md` e `-dev.md`). Restano al dev: commit e
merge dei due repo, `WP_PACKAGE_REF` con l'hash del plugin, `scripts/wordpress-up.sh` su dev e UAT, pulizia delle 170
righe `i:` dello storico del dev.

### Pulsante «Stampa» (24/11 sera)

Aggiunto dopo l'esecuzione del piano (Task 7), chiesto dal dev («si fallo qui», 24/11) dopo il pull di forestas
`develop` 1123b46, che porta l'opzione di stampa dell'App (`OPTIONS.ec_pdf_enabled`) e toglie `print_url` dal dettaglio
del POI.

- Il link lo costruisce il plugin (`wmwp_detail_print_url()`), perché la stampa è del wm-package e vale per ogni sito;
  il child mette solo il pulsante, con la classe dei download (`forestas-pulsante`), fra i download e «Condividi».
  Drupal non ha un pulsante di stampa: il posto è quello del wireframe di oc:8969.
- Nuova finestra: la rotta mostra un'attesa e poi il PDF, e la pagina del percorso resta aperta; annunciata con il
  testo nascosto che il child usa già per i video («(si apre in una nuova finestra)»).
- `rel="nofollow"` e `Disallow: /print/` nel `robots.txt` dello shard (`forestas/public/robots.txt`, prima vuoto):
  la rotta accoda una compilazione per ogni scheda che manca (`Print.md`, «Trappole»). Il link punta allo shard,
  quindi conta il `robots.txt` dello shard, non quello di WordPress.
- Verifiche (24/11, WordPress locale sul `config.json` del dev, `ec_pdf_enabled` acceso): `tests/dettaglio.php` visto
  fallire e poi PASS, 9 test del child PASS, plugin 25 su 26 (`detail-render` come prima) e 94 test JS. Nelle pagine
  vere il cammino 263 ha `https://forestas.dev.maphub.it/print/ec-track/263?lang=it`, la pagina inglese
  `?lang=en` e «Print (opens in a new window)», il POI 177 `print/ec-poi/177?lang=it`. Nel browser, a 1280 e 375 px:
  stesso aspetto degli altri pulsanti, nome per lo screen reader «Stampa (si apre in una nuova finestra)», focus
  visibile (3 px), altezza 42-48 px, nessun errore in console.
- Su UAT la chiave `ec_pdf_enabled` oggi non c'è: il pulsante non compare finché qualcuno non accende «Print PDF»
  sull'App di Forestas in Nova (knowledge 8982 di forestas: va acceso a mano in ogni ambiente).

## Review interna del 24/11 sera (sessione di review di wp-forestas)

Quattro bloccanti, uno da chiarire e 15 cleanup. Dopo le correzioni passano tutti i 9 test del child.

- **B1, «Stampa» senza guardia:** con il plugin fissato a `2c332f5` (0.5.0) il dettaglio usa già il template del
  plugin (`forestas_plugin_ready()` vuole la 0.6.0), quindi l'errore fatale non poteva succedere. Il pulsante sta
  comunque in un helper solo, `forestas_dettaglio_stampa()`, con `function_exists()`, usato dai due template.
- **B2, B3, lingua della validazione e del grado di difficoltà:** la validazione porta la lingua del nome
  (`forestas_dettaglio_indicatori()`, riga compatta e tabella); la riga «Grado di difficoltà» ha un valore HTML con
  `<span lang>` sulle parti in un'altra lingua (chiave `html` delle righe di `forestas_dettaglio_gruppo()`), e
  `forestas_dettaglio_difficolta()` dà chiavi con nome (`scala`, `scala_lingua`, `grado`, `grado_lingua`).
- **B4:** `docs/howto/ricreare-wordpress-da-zero.md` con i parametri, i filtri e lo slug inglese di oggi, verificati in
  locale.
- **B5:** le verifiche c'erano, mancava l'esito scritto: sezioni «Task 1, Step 5» e «Task 6, Step 1» più sopra, rifatte.
- **Cleanup fatti:** `$known` tolta e commenti dei valori sconosciuti corretti (`inc/entes.php`); «Deviazioni dal
  piano» in testa a questo file, con il ripiego dei 50 m; regola unica delle scale di difficoltà
  (`forestas_famiglia_difficolta()`, `FORESTAS_DIFFICULTY_SUFFIX`, `FORESTAS_DIFFICULTY_EXCLUDE`); redirect del vecchio
  slug più leggibile e `FORESTAS_SEARCH_OLD_SLUGS` in testa al file; con lo shard giù un vecchio link Drupal per tipo
  non lo richiama per 10 minuti (`FORESTAS_SEARCH_LAYERS_FAILED`); `?? []` sul registro; i test rimettono com'erano
  `config.json`, overlay e cache anche se si interrompono con un errore; nome del tipo mai vuoto
  (`forestas_dettaglio_nome_tipo()`); costruzione dell'ente in una funzione sola; legenda della mappa del POI con tutti
  i tipi di uno stesso colore in una voce; docblock dei redirect Drupal senza `/node/<nid>`, che non è coperto.
- **Cleanup lasciati:** la trappola in `CLAUDE.md` sul parametro `layer` e sui ripieghi va nella fase di aggiornamento
  del contesto, con `wm-context-guard`, prima del commit; il controllo su UAT del redirect del vecchio slug inglese
  (`$wp->request` con WPML) è nella procedura di messa in opera di UAT, perché si fa dopo il rilascio.
- **Secondo giro:** B2 valeva anche per il riquadro delle tacche della validazione, ora con la lingua del nome
  (`validazione_tacche.lingua`, stampata con `forestas_detail_lang_text()`); la riga del grado usa la stessa funzione;
  il cron della corrispondenza route-type → layer (`forestas_search_layers_refresh()`) non cancella più la cache prima di
  riscaricarla: con lo shard giù resta quella di prima.

## Da chiedere allo shard (ticket da creare a fine lavoro)

A fine lavoro va creato un ticket con quello che serve sullo shard, su indicazione del dev (23/11). Si
aggiunge all'elenco «Nello shard (da chiedere tramite oc:8916)» della description di oc:9018.

- **Pubblicare l'ordine dei termini.** `taxonomy_terms.sort_order` esiste già
  (`wm-package/src/Models/TaxonomyTerm.php:57`) e sul dev ha l'ordine ufficiale (verificato il 23/11):
  `hiking-difficulty-T=0, -E=1, -EE=2, -EEA=3`; `mtb-difficulty-TC=0, MC=1, BC=2, OC=3, EC=4`;
  `horse`, `nordic-walking`, `soft-mobility`, `educational-trails-difficulty-easy=0, medium=1, hard=2`;
  `inclusivity-difficulty-AT=0, AE=1, AEE=2`. È lo stesso ordine del `weight` dei termini di
  `categorie_fruibilita_sentieri` nella copia Drupal su UAT (Turistico 0, Escursionistico 1,
  Escursionista Esperto 2, Escursionista Esperto Attrezzato 3) e della scala CAI. Lo shard lo usa
  (termini di una traccia, `TaxonomyAbleModel.php:64`; famiglie in `LABELS.taxonomies`,
  `AppConfigService.php:277`) ma non lo pubblica in nessuna API: nella ricerca i termini arrivano
  senza posizione.
- **Dichiarare in `MAP.filters` i filtri che il sito ha in più**, con le opzioni già in `sort_order` e
  i nomi nelle due lingue: le app leggono le opzioni solo da lì, quindi è il modo per avere sito e app
  allineati. Oggi sul dev `MAP.filters` ha solo `activities`, `layers`, `poi_types`, `track_duration`,
  `track_distance`. Mancano: difficoltà, percorribilità, validazione, tipo e ruolo degli enti
  (provincia e comune non servono: il dev ha deciso di non averli, come le app).
- **`activities` in `MAP.filters`** (dev, 23/11): ha 7 opzioni (`hiking`, `trail`, `trekking`,
  `nordic-walking`, `horse`, `mtb`, `educational-trails`). Contiene `trail` («Sentiero», solo in
  italiano), che è un tipo di percorso, e non contiene `accessible-trails`. Le opzioni vengono da
  `get_unique_taxonomies('taxonomyActivities')` (`AppConfigService.php:704`), non per `sort_order`.
  Il 20/11 le opzioni erano vuote.
- **Nomi solo in italiano nel `config.json`:** le opzioni di `layers` sono costruite con il solo `it`
  (`AppConfigService.php:724-726`); l'overlay «Aree Catastali» non ha il nome inglese.
- **Attività in `MAP.filters`:** togliere `trail` (è un tipo di percorso) e aggiungere
  `accessible-trails`; il sito le mostra come le dà lo shard (decisione del 24/11).
- **Colore di «Sentieri Accessibili» su UAT** (`#ffff00`, layer 9): giallo sulla carta chiara, sotto il
  3:1 di WCAG 1.4.11; sul dev è `#e100ff`. I percorsi di quel layer lo prendono come colore della linea
  perché in `MAP.layers` viene prima di «Sentieri».
- **Riscrivere il `config.json` alla fine dell'import** (dopo attività e ricalcolo dei layer) **e quando
  un'attività cambia nome**: oggi su UAT è scritto a metà del reset notturno e `MAP.filters` perde `mtb`
  (vedi «Challenge»).
- **Indirizzo inglese di Drupal** in `properties.forestas` (oggi solo l'alias italiano più recente,
  `SardegnaSentieriDatabaseSource.php:767-776`): per 242 POI su 412 l'alias inglese è diverso
  dall'italiano e il redirect delle pagine inglesi non lo può ricavare.
- **Elenco degli stati di una famiglia, con lo stato di assenza**, in un'API: oggi il registro della
  percorribilità è scritto nel plugin (`includes/status.php`) e quello di validazione e gradi di
  accessibilità nel child (`inc/statuses.php`). In alternativa, per la percorribilità: assegnare
  `walkability-status-unknown` ai percorsi senza stato (24/11: 98 su 170 su UAT, 509 su 778 sul dev), così il nome «Sconosciuta»
  arriva con i dati e il plugin toglie il suo ripiego.
- **Tipologie di disabilità: nomi e icone uguali a quelli del sito** (deciso dal dev, 24/11), per le
  app: nomi «Motoria / Motor», «Sensoriale / Sensory», «Psichica cognitiva / Cognitive»
  (`wp-package/track.php:185-187` del child) e icone `txn-disability-motor`, `-sensory`, `-cognitive`
  (`inc/dettaglio.php:464-466`, già nel set dello shard). Oggi lo shard ha «Fisica e motoria»
  `txn-mobility-disability`, «Cognitiva e psichica» `txn-cognitive-disability`, «Sensoriale» senza icona,
  nessun nome inglese. Le icone nei termini erano già la bozza di ticket di oc:8969 ancora da creare. Anche la stampa di
  oc:8982 ha le sue copie delle tre icone (`forestas/resources/print/icons/*.svg`, disegni del tema):
  quando le icone saranno tutte nei termini, la stampa dovrebbe prenderle da lì.
- **Nomi della difficoltà:** lo shard ha «T», «E», «EE», «EEA»; Drupal ha i nomi lunghi
  («Turistico», «Escursionistico», «Escursionista Esperto», «Escursionista Esperto Attrezzato»).
  Quale nome mostrare è contenuto da concordare con il cliente (scrum del 19/11).
- **Filtro Provincia, se il dev riapre la decisione del 23/11** (verificato il 25/11, da decidere): in Elastic
  `taxonomyWheres` ha solo i nomi (percorso 263 sul dev: `ZSUD1, ZSUD3, Area D (Sud-OVEST), Iglesiente e
  Marganai, Sardegna, Sulcis Iglesiente, Buggerru, Fluminimaggiore`). Lo shard conosce `_admin_level` (4 regione,
  6 provincia, 8 comune), ma lo usa solo per ordinare l'elenco e poi lo toglie
  (`TaxonomyWhereDisplayService::orderedNames()`, `wm-package/src/Services/TaxonomyWhereDisplayService.php:140-160`;
  `EcTrack.php:863`): dai nomi non si distinguono province e comuni. Su Geohub l'app 32 ha 5 layer di provincia
  (186 Nuoro, 187 Cagliari, 188 Sassari, 189 Oristano, 190 Sud Sardegna), che però non stanno né nella home, né in un
  overlay, né in `MAP.filters`. Layer dei comuni: nessuno. Sono le province vecchie: per Buggerru lo shard ha «Sulcis
  Iglesiente», Geohub «Sud Sardegna». Proposta: layer di provincia creati dai luoghi dello shard con `admin_level` 6,
  non da Geohub, in un overlay «Province»; il sito li mostra con il meccanismo delle aree senza modifiche. In
  alternativa il livello in `taxonomyWheres` di Elastic, ma va contro «niente dati nuovi in Elastic» (Giuseppe, 24/11).

## Info per altri ticket

- **Pulsante «Stampa» del sito** (fatto poi in questo ticket, 24/11 sera: «Pulsante «Stampa»» nelle divergenze del
  Task 5. Prima non era di questo ticket: l'overview di oc:8982 lo lascia «a un altro ticket»; il child oggi non lo mostra, `wp-package/track.php:132` e `wp-package/poi.php:73`). Scrum del 24/11, Alessandro e Rubens: il link al PDF non resterà fra
  le `properties` di tracce e POI. Il sito legge dal `config.json` se la stampa è accesa e costruisce da
  sé l'indirizzo della rotta di stampa, come fa per i filtri; se il flag stesse nelle `properties`,
  accenderlo o spegnerlo vorrebbe dire riscrivere i geojson di tutte le tracce. Giuseppe: un solo flag,
  quello dell'app, per tracce e POI; il flag della singola traccia si ignora; la colonna
  `print_track_enable` dell'app (oggi in `OPTIONS`, `AppConfigService.php:912`) non si usa più e il
  nuovo flag va fra le `properties` dell'app. Aggiornamento del 24/11 sera (forestas `develop` 1123b46, wm-package
  a588928): la chiave è `OPTIONS.ec_pdf_enabled` del `config.json` (campo «Print PDF» della Resource `App`, acceso a
  mano in Nova in ogni ambiente), la rotta è `/print/{ec-track|ec-poi}/{id}` e `print_url` non c'è più nemmeno nel
  dettaglio del POI (knowledge 8982 di forestas). Letto il 24/11: sul dev `ec_pdf_enabled` è `true`, su UAT la chiave
  non c'è.
- **QR code delle schede PDF** (oc:8982, forestas 1123b46): porta a
  `<FORESTAS_WEBSITE_URL>/?wmwp_detail=track|poi&wmwp_id=<id dello shard>`, che il plugin risolve nel dettaglio
  (`wmwp_detail_url_by_id()`, `includes/detail-route.php`). Provato in locale con il branch di oc:9018:
  `?wmwp_detail=track&wmwp_id=263` → 302 a `/cammino/cmsb-tappa-04-da-buggerru-a-portixeddu/`, `poi` 177 → 302 al
  dettaglio del POI, un id che non esiste → 404. La scheda non usa `forestas.url`, che resta l'indirizzo di Drupal
  per i redirect di questo ticket. **La lingua si perde** (segnalato dal dev, 24/11): `ForestasPrintSheet::link()` non
  riceve la lingua della scheda, quindi anche `print-en.pdf` porta alla pagina italiana. Provato sul dev:
  `wp.forestas.dev.maphub.it/?wmwp_detail=poi&wmwp_id=74` → 302 a `/punti-di-interesse/museo-del-cervo-…/`,
  `…/en/?wmwp_detail=poi&wmwp_id=74` → 302 a `/en/punti-di-interesse/museo-del-cervo-…/`, `…/?…&lang=en` → pagina
  italiana. Basta che lo shard metta `/<lingua>` dopo il dominio per le lingue diverse dalla predefinita dell'app: il
  plugin non cambia. Da dire ad Alessandro (oc:8982).
- **Colore della traccia nella scheda PDF diverso da quello del sito**: la scheda usa il primo layer della traccia
  per rank, altrimenti il rosso (`WM_PRINT_MAP_STROKE_COLOR` lo fissa per tutto lo shard); il sito usa il primo
  layer colorato di `MAP.layers` (questo ticket). Una traccia il cui primo layer è un'area catastale senza colore
  esce rossa nel PDF e del colore del suo tipo sul sito (in locale, 24/11, la traccia 119 esce rossa nel PDF:
  knowledge 8982). Da dire ad Alessandro.
- **Data nel PDF** (Giuseppe, 24/11): la scheda porta in piè di pagina la data di generazione, perché è
  una fotografia di quel giorno. Lavoro di Alessandro (oc:8982), non tocca il sito.

## Follow-up

- Il ticket sullo shard descritto sopra, da creare a fine lavoro.
