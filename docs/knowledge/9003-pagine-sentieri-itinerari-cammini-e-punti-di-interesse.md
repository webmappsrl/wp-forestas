# Pagine Sentieri, Itinerari, Cammini e Punti di interesse

> Lavoro d'origine: oc:9003. Artefatti in
> `docs/features/9003-sito-wordpress-pagine-sentieri-itinerari-cammini-punti-di-interesse-e-carte/`. La parte generica
> (copia delle tracce, griglie, slider) è del plugin `wp-package` 0.7.0: il suo `README.md`, «Griglie», e la pagina
> `docs/knowledge/9003-griglie-dei-percorsi-e-slider.md` del plugin. La messa in opera è in
> [docs/howto/messa-in-opera-delle-pagine-d-elenco.md](../howto/messa-in-opera-delle-pagine-d-elenco.md).

## Come funziona oggi

- **Template `griglia`** (`config/pagine.json`): `sentieri`, `itinerari`, `cammini` e `da-vedere` usano
  l'us_content_template `template-pagina-griglia` di `config/post.json`, cioè la testata e `[forestas_listing]`.
- **`[forestas_listing]`** (`themes/forestas-child/inc/listings.php`) sceglie la griglia dalla `_wpf_pagina` della pagina
  (`FORESTAS_LISTING_PAGES`) e chiama `[wmwp_tracks_grid]` o `[wmwp_pois_grid]` del plugin. Senza le funzioni del plugin
  0.7.0 non scrive nulla: la pagina resta con la sola testata.
- **Configurazione delle griglie** (filter `wmwp_grid_config`, `forestas_listing_config()`):
  - **layer**: dal route-type del tipo (`FORESTAS_ROUTE_TYPE_KINDS`) con `forestas_search_layer_for_route_type()`, la
    stessa della ricerca, che con `wmwp_tracks_layer_for_term()` del plugin cerca sulla copia delle tracce, fra le opzioni di «Tipo percorso» del `config.json` (`MAP.filters.layers`), il layer
    con più percorsi di quel route-type. Nessun id scritto: su UAT e dev oggi è 11 Sentieri, 10 Itinerari, 8 Cammini
    di Sardegna. Senza copia o senza percorsi la griglia mostra un messaggio, mai tutti i percorsi;
  - **faccette**: quelle della ricerca (`forestas_search_track_facets()`) senza «Tipo di percorso»; per itinerari e
    cammini anche «Tipologia itinerario» (famiglia `itinerary-type` di `LABELS.taxonomies`, dopo le attività);
  - **slider** di distanza e tempo (`track_distance`, `track_duration` di `MAP.filters`), anche nella ricerca unica;
    compaiono solo se qualche percorso della griglia ha il valore: oggi su UAT ci sono su `/sentieri/`, non su
    `/itinerari/` e `/cammini/`, e su dev quasi nessun percorso ha i valori;
  - **9 card per pagina** per i percorsi, **12** per i POI, come le view di Drupal; nomi del conteggio per pagina
    («27 sentieri», «5 cammini»). La griglia dei POI usa le faccette del tab dei POI della ricerca.
- **Categorie di Drupal come faccette**: al posto delle pagine per categoria (`/sentieri-per-attivita/<tid>`,
  `/itinerari-per-tipologia/<tid>`, `/poi-per-tipologia/<tid>`) si usa la faccetta nell'indirizzo della pagina
  d'elenco, per esempio `/sentieri/?activity=mtb` o `/itinerari/?itinerary-type=itinerary-type-long-distance`. I
  redirect dai vecchi indirizzi non ci sono ancora: si fanno con il lancio.
- **Ordine** per nome italiano senza spazi ai lati, come la ricerca unica: l'API dello shard ordina per nome grezzo, e
  i nomi che iniziano con un tab o uno spazio finivano in testa.
- **Titoli come le voci del menu**: `da-vedere` («Punti di interesse») e `itinerari` («Itinerari», su Drupal «Itinerari e
  Cammini di Sardegna») hanno `titolo` in `config/pagine.json`, e `docker/scripts/config/pagine.php` lo usa al posto del
  titolo di Drupal anche per una pagina con nid, prima dell'impronta del testo. Allo stesso modo `sottotitolo` per
  lingua: Cammini, che prende la testata da Itinerari, ha il suo («Scopri i cammini più belli…»).
- **Campo «cerca»** in tutte e quattro le pagine, con nome e suggerimento per tipo (`forestas_listing_search_texts()`):
  il testo dei percorsi lo cerca l'API dello shard, come nella ricerca unica, i POI la rotta dei POI.
- **Ricerca unica**: `forestas_search_layer_for_route_type()` (colore delle linee, vecchi link di Drupal) usa la stessa
  corrispondenza dalla copia, quando c'è; senza copia resta il download di prima.
- **Prova in locale** (WordPress su dev, dove quasi nessun percorso ha distanza e tempo): si scarica la risposta
  dell'API di UAT e la si carica nella copia, poi si torna a dev:

  ```bash
  curl -s "https://forestas.uat.maphub.it/api/v2/elasticsearch?app=forestasuat_1" -o /Volumes/WebmappDev/tmp/tracce-uat.json
  docker cp /Volumes/WebmappDev/tmp/tracce-uat.json wordpress-forestas:/tmp/tracce-uat.json
  docker exec wordpress-forestas wp --allow-root wmwp tracks-copy load /tmp/tracce-uat.json
  docker exec wordpress-forestas wp --allow-root wmwp tracks-copy refresh   # di nuovo i dati di dev
  ```

## Perché così

- **Una griglia per pagina, non la ricerca unica** (Rubens, 19/11 e 25/11): le voci del menu hanno i loro contenuti; le
  categorie diventano valori di una faccetta (un solo elenco per tipo, tav. 9 di oc:8783), quindi non servono pagine per
  categoria né immagini dei termini, che nello shard non ci sono.
- **Dati dallo shard, come le app** (regola del `config.json`, scrum del 24/11): layer come il box `layer` della HOME
  dell'app, filtri e slider da `MAP.filters`, ordine dell'API. Il child non scrive id né valori di termini.
- **Griglie costruite sul server da una copia** (challenge del 25/11): l'API Elastic pesa 2,76 MB su UAT, senza
  compressione né cache; scaricata nel browser a ogni visita delle voci del menu, le pagine sarebbero lente sul
  telefono, vuote per i crawler e senza JavaScript, e vuote con lo shard giù.
- **Slider con gli estremi aperti** (25/11): un cursore al suo estremo non pone limiti da quel lato, così «fino a 10 km»
  tiene anche i sentieri sotto 1 km (63 su UAT). Le app chiudono l'intervallo appena si tocca lo slider: è una
  differenza voluta, da proporre a `wm-core`.
- **Campo «cerca» con il testo cercato dallo shard** (Rubens, 25/11, dopo le review): la stessa parola dà gli stessi
  percorsi nella pagina e nella ricerca unica; cercare il testo nella copia avrebbe raddoppiato la copia (`searchable`
  pesa 1,46 MB su UAT) e dato risultati diversi da Elasticsearch.
- **Basi dei dettagli invariate** (`/sentiero/<slug>/`…): la maggior parte degli indirizzi Drupal usa già il singolare
  (442 sentieri su 615, 135 itinerari su 136).

## Come ci siamo arrivati

- **Pagine che portano alla ricerca unica filtrata** (descrizione del ticket del 24/11, superata): le pagine hanno la loro
  griglia.
- **Distanza e tempo a fasce fisse come Drupal** (19/11, superata il 25/11): scritte nel child, sarebbero rimaste
  indietro rispetto ai limiti del `config.json`; ora sono gli slider di `MAP.filters`.
- **Griglia come interfaccia nuova** (`grid.js` del piano, superata durante il lavoro): la griglia è la ricerca in
  modalità griglia, con le faccette, gli annunci e il pannello dei filtri già provati.
- **Pagine delle aree (`/carte`, `/carte/<area>`)**: accorpate il 24/11, rinviate il 25/11. Tutto ciò che è emerso è in
  `notes.md` del lavoro, «Per un ticket futuro: le pagine delle aree».
