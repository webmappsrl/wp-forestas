# Filtri e dettagli dai dati dello shard

> Lavoro d'origine: oc:9018. Artefatti in
> `docs/features/9018-sito-wordpress-filtri-e-dettagli-hanno-i-valori-dello-shard-scritti-nel-codice-invece-di-leggerli-dal-configjson-e-dalle-api-come-lapp/`.
> La parte generica (copia del `config.json`, faccette `from_config`, `from_families`, `from_overlays`, aree degli
> overlay, parametro `layer`, indirizzi vecchi nello storico) è del plugin `wp-package` 0.6.0: il suo `README.md`.

## Come funziona oggi

- **Filtri della ricerca dallo shard, come le app.** `forestas_search_track_facets()` (`inc/search.php`) dice solo
  quali filtri e in che ordine: percorribilità e validazione dal registro degli stati (ordine e stato di assenza,
  nomi dai termini), tipo di percorso (`MAP.filters.layers`), attività (`MAP.filters.activities`), un filtro per
  scala di difficoltà (famiglie `*-difficulty` di `LABELS.taxonomies`, tranne l'accessibilità) e le aree degli overlay
  del `config.json` (Aree Catastali, Aree geografiche). I POI hanno i tipi (`MAP.filters.poi_types`) e le stesse
  aree, calcolate sul server con le geometrie. Niente Provincia, Comune e «Area» per nome.
- **Parametri in inglese**, quelli del plugin: `q`, `tab`, `pg`, `view`, `map`; chiavi `walkability`, `validation`,
  `type`, `activity`, una per scala (`hiking-difficulty`…), `poi-type`, `content`, `entity-type`, `role`; tutte le
  aree in `layer` (`?layer=3,50`), come le app. In inglese anche i valori che scrive il sito: `tab` (`trails`,
  `points-of-interest`, `content`, `organisations`) e i tipi di `content` (`news`, `event`, `good-to-know`, `page`,
  `FORESTAS_SEARCH_TYPES`); i valori che vengono dallo shard (identifier, id dei layer, tipi e ruoli degli enti) restano
  i suoi.
- **Vecchi link della ricerca di Drupal** (`forestas_search_from_drupal()`): `?search_api_fulltext=…` (anche senza
  `f`) e `f[N]=cerca_contenuto_nel_sito:<tipo>` vanno con un 302 ai parametri nuovi; il tipo di percorso diventa il
  layer del filtro «Tipo di percorso» con più percorsi di quel route-type (`forestas_search_layer_for_route_type()`,
  con il plugin 0.7.0 dalla copia delle tracce (knowledge 9003); senza copia in cache due ore, rinnovata dal cron del `config.json` con `forestas_search_layers_refresh()`: con lo shard giù resta
  la corrispondenza di prima, e senza cache un fallimento si ricorda 10 minuti, così il visitatore non aspetta il
  timeout a ogni vecchio link). `?s=` va alla ricerca con `q`.
- **Pagina di ricerca in inglese** `/en/search-in-our-website/` (`config/pagine.json`; prima `/en/cerca-nel-sito`, come
  la view di Drupal): il vecchio indirizzo porta al nuovo con un 301 che tiene i parametri
  (`forestas_search_old_slug_target()`).
- **Riga del percorso**: all'inizio l'ultimo luogo di `taxonomyWheres`, il comune (`meta_last_field`), come la card
  delle app.
- **Dettaglio.** Il tipo viene da `FORESTAS_ROUTE_TYPE_KINDS` (`inc/dettaglio.php`), un punto solo; un route-type che
  il child non conosce, o nessuno, è «altro»: nome dello shard, colore neutro (`#1A1A1A`), breadcrumb senza pagina
  d'elenco, «Il percorso in breve». Nomi del tipo, della validazione e delle scale di difficoltà dallo shard; un nome
  solo italiano porta `lang="it"` sulla pagina inglese (riga compatta, tacche, tabella: la riga del grado ha un pezzo
  per scala). La legenda della mappa del POI ha una voce per colore, con tutti i tipi di quel colore. Linee
  della mappa nel colore del layer (traccia della scheda: primo layer colorato dei suoi `layers`; percorsi vicini del
  POI: il layer del loro route-type), etichette e fasce nei colori del child (passano AA). Anello da `roundtrip`;
  download GPX, KML e GeoJSON da `forestas.downloads`; area sosta con e senza il prefisso `poi_type_`; un ruolo
  degli enti nuovo compare con il valore grezzo.
- **Pulsante «Stampa»** nel dettaglio di percorsi e POI, solo se la stampa dell'app è accesa (`OPTIONS.ec_pdf_enabled`
  del `config.json`, «Print PDF» dell'App in Nova): link alla scheda PDF dello shard nella lingua della pagina
  (`wmwp_detail_print_url()` del plugin), in una nuova finestra, `rel="nofollow"`; il `robots.txt` dello shard esclude
  `/print/`, perché la rotta accoda una compilazione per ogni scheda che manca.
- **Indirizzi Drupal dei dettagli** (`inc/redirect-drupal.php`): il filtro `wmwp_detail_legacy_paths` dà al plugin il
  percorso di `forestas.url` (italiano e inglese) di percorsi e POI; il plugin lo mette nello storico, non attuale, e
  ne fa un 301 alla pagina nuova dello stesso elemento.

## Perché così

- Lo shard è l'unica fonte dei dati (scrum del 19/11); il sito funziona come la webapp e legge il `config.json`
  (Giuseppe, scrum del 23/11 e del 24/11: «Tu devi leggere sempre dalla configurazione»). Se al sito manca qualcosa,
  va nel `config.json`, non nel codice del sito.
- **La fonte vince anche quando è sbagliata** (Rubens, 24/11): `trail` fra le attività e l'ordine delle opzioni come
  li dà lo shard; gli errori vanno nel ticket sullo shard (`notes.md`, «Da chiedere allo shard»).
- **Un filtro per scala di difficoltà**: unire per valore metteva insieme «Facile» di quattro scale diverse.
- **Colore delle linee non come le app**: map-core usa il primo layer della traccia, che su Forestas è un'area
  catastale senza colore, quindi tutte le linee sarebbero rosse.
- **Indirizzi Drupal nello storico prima che servano**: un WordPress nuovo, come quello di produzione, deve averli
  tutti prima del cambio di dominio; l'elenco di Elastic non ha `forestas.url`, quindi per i percorsi si legge il
  dettaglio una volta.

## Ripieghi dichiarati, e quando si tolgono

Ognuno in un punto solo; si tolgono quando lo shard pubblica il dato (ticket sullo shard da `notes.md`).

- Nome dello stato di assenza: «Sconosciuta» (plugin) e «Non verificato» (`inc/statuses.php`): nessun dato lo porta.
- Nomi delle legende delle tacche (`FORESTAS_STATUS_LEGEND_NAMES`) e delle tre tipologie di disabilità: elencano
  anche gli stati che il percorso non ha (decisione del 24/11: lo stile dell'accessibilità non si rompe; lo shard deve
  avere gli stessi nomi e le stesse icone).
- Nomi estesi dei gradi di accessibilità (Allegato H): lo shard ha solo le sigle.
- Ordine della scala escursionistica (`FORESTAS_DIFFICULTY_ORDER`), finché lo shard non pubblica `sort_order`.
- Tipi e ruoli degli enti, classi di inclusività: finché l'API non li dà.
- Nome del tipo di percorso quando lo shard non lo dà (`forestas_dettaglio_nome_tipo()`: «Sentiero», «Itinerario»,
  «Cammino», «Percorso» per un tipo che il child non conosce), così la testata non resta vuota.

## Come ci siamo arrivati

Dialogo e challenge del 23-24/11 (`notes.md`): due revisori hanno trovato, fra l'altro, il `config.json` di UAT
scritto a metà dell'import notturno (manca `mtb`), il prefisso `poi_type_` che azzerava i tipi dei POI, le aree di
Geohub su UAT e la percorribilità senza nome per i percorsi senza stato.
