# Percorribilità, avvisi e filtri sul sito

> Lavoro d'origine: oc:9000. Artefatti in
> `docs/features/9000-sito-wordpress-percorribilita-e-avvisi-dei-sentieri-dai-dati-dello-shard/`.
> La parte generica (registro degli stati, letture del dettaglio, ricerca dei POI, filtri apribili) è del plugin
> `wp-package`: il suo `README.md` e la sua pagina di conoscenza di oc:9000. I dati vengono dallo shard: percorribilità
> oc:8989, avvisi oc:8981 (pagina di conoscenza `8981-avvisi-dei-sentieri.md` di forestas).

## Come funziona oggi

- **Stati e tacche.** Il registro degli stati è del plugin (`wmwp_status_families()`): la percorribilità
  (`walkability-status`) ce l'ha di suo, il child aggiunge `validation-status` e `inclusivity-difficulty`
  (`inc/statuses.php`). Dal registro vengono la fascia della percorribilità e gli indicatori a tacche del dettaglio
  (tante tacche quanti sono gli stati, assenza a 0), i nomi e le legende (`forestas_status_names()`,
  `forestas_status_legend()`) e i filtri «Percorribilità» e «Validazione catastale» della ricerca. I percorsi senza
  termine contano come «Sconosciuta» e «Non verificato» (`missing` delle faccette del plugin).
- **Avvisi.** Fascia «Avviso» in cima al dettaglio di sentieri, cammini e itinerari (`wmwp_track_notice()`); pagina
  `/avvisi` (`inc/notices.php`, `templates/notices-list.php`) da `api/v1/avvisi` dello shard, con un transient di
  60 secondi e l'ultima risposta buona in un'opzione, ordinabile per data e a pagine.
- **Ricerca unica** (`inc/search.php`, `assets/js/search.js`): tab «Punti di interesse» dalla sorgente del plugin, con i
  filtri tipo, area, provincia e comune (gli stessi di `forestas_search_track_facets()`); tab «Enti» con «Tipo di ente»
  e «Ruolo sui sentieri», calcolati dalla rotta `forestas/v1/entes-search` (`forestas_entes_search_filter()`; i ruoli
  vengono sempre dalla copia degli enti). Intestazione e breadcrumb come le altre pagine.
- **Stile dei filtri.** Quello della ricerca è il riferimento: numero nel riquadro arancione (pieno nel filtro con una
  voce scelta), filtri che si aprono e chiudono dal titolo (`wmwp_search_collapsible_facets`), «Mostra di più». La
  colonna dei filtri dei post (`forestas_filtri_colonna()`: News, Eventi, Da sapere, Enti) ha lo stesso aspetto, con
  la casella disegnata davanti ai link, e sopra la lista «Filtri attivi» (`forestas_filtri_attivi_html()`).
- **Icona del sito** in `config/media/icona-sito.png`, indicata da `config/sito.json` con `@file:` e messa da
  `bin/wordpress-config.sh applica` (README, «Configurazione versionata»).
- **Test.** `themes/forestas-child/tests/` (`dettaglio.php`, `notices.php`, `search-facets.php`, `entes-search.php`,
  `filtri-attivi.php`) e `docker/scripts/config/tests/icona-sito.php`, uno alla volta nel container.

## Perché così

- **Registro nel plugin, elenco degli stati scritto lì** (oc:9000): lo shard non espone l'elenco degli stati di una
  famiglia; quando lo farà, il registro leggerà l'API. Ordine e assenza vengono dal registro, non dal colore.
- **Percorribilità separata dalla validazione** (oc:8989): la validazione catastale è lo stato della pratica, la
  percorribilità com'è il sentiero sul terreno; la riga della ricerca mostra il semaforo della percorribilità, con il
  nome sempre scritto (il colore non basta, WCAG 1.4.1).
- **Filtri dei post con l'aspetto della ricerca, comportamento di Drupal** (oc:9000): le voci restano link, una per
  gruppo, perché ogni voce cambia l'indirizzo; anche «Filtri attivi» sono link, e riportano alla prima pagina.
- **Ruoli degli enti dalla copia** (oc:9000): la ricerca dello shard non dà i sentieri degli enti; un ente non ancora
  nella copia non ha ruoli.
- **Filtri dei luoghi dei POI calcolati dal plugin sul server** (oc:9000): `pois.geojson` pesa 6 MB e non arriva al
  browser; il child dichiara solo le faccette, con le regole dei percorsi.

## Messa in opera

- `WP_PACKAGE_REF` al commit del plugin con la 0.5.0, poi `scripts/wordpress-up.sh` di forestas: con un plugin più
  vecchio il child degrada e non mostra percorribilità, avvisi, filtri degli stati né tab dei POI.
- Dopo l'aggiornamento il tab dei POI può rispondere 503 finché la prima ricostruzione dell'indice non scrive la copia
  dei POI (al massimo 5 minuti dopo l'ultima ricostruzione).
- Su UAT solo dopo oc:8989 e il suo import: prima i percorsi non hanno `walkability-status`.
- L'icona del sito la mette `bin/wordpress-config.sh applica --conferma`: il cron `allinea` non applica `sito.json`.
- Il file v1 della traccia c'è solo dove lo shard ha `WMFE_TRACK_VERSIONS` (oc:8997): altrove il plugin legge il file
  senza versione.

## Come ci siamo arrivati

- **Tab «Enti» senza filtri** (oc:8990, superato da oc:9000).
- **Filtri dei post con titolo, voci e numeri di Drupal** (oc:8760, superati da oc:9000): il riferimento è diventato
  la ricerca unica; tolto anche il grassetto del gruppo attivo.
