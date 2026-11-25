> Ticket: oc:9003

# Sito WordPress: pagine Sentieri, Itinerari, Cammini e Punti di interesse

Parte di Forestas (child `forestas-child` e `config/`). I componenti generici (griglia, copia delle tracce, slider)
sono nel plugin: `wp-package`, `docs/features/9003-sito-wordpress-pagine-sentieri-itinerari-cammini-punti-di-interesse-e-carte/overview.md`.

## Cosa cambia

Le pagine `/sentieri/`, `/itinerari/`, `/cammini/` e `/punti-di-interesse/` oggi hanno solo la testata. Dopo questo
lavoro mostrano, sotto la testata, una griglia di card con le faccette, costruita da uno shortcode di `wp-package`.
La prima pagina della griglia arriva già nell'HTML, costruita sul server da una copia delle tracce (come i POI):

| Pagina | Cosa mostra la griglia | Faccette |
|---|---|---|
| `/sentieri/` | i percorsi del layer «Sentieri» | quelle dei percorsi della ricerca unica tranne «Tipo di percorso», più gli slider di distanza e tempo |
| `/itinerari/` | i percorsi del layer «Itinerari» | come `/sentieri/`, più «Tipologia itinerario» |
| `/cammini/` | i percorsi del layer «Cammini di Sardegna» | come `/itinerari/` |
| `/punti-di-interesse/` | i POI | tipo di POI e aree, come il tab dei POI della ricerca unica |

Le pagine per categoria di Drupal (`/sentieri-per-attivita/<tid>`, `/itinerari-per-tipologia/<tid>`,
`/poi-per-tipologia/<tid>`) diventano la stessa pagina con una faccetta scelta nell'indirizzo, per esempio
`/sentieri/?activity=mtb` o `/itinerari/?itinerary-type=itinerary-type-long-distance`: nessuna sottopagina.

La ricerca unica `/cerca-nel-sito/` aggiunge gli slider di distanza e tempo al tab dei percorsi, come le app.

## Perché

Le voci del menu principale portano oggi a pagine vuote, e le pagine per categoria esistono solo sul sito Drupal.
Decisioni del dialogo (Rubens, 19/11 e 25/11):

- **Le pagine non portano alla ricerca unica**: hanno la loro griglia, con il tipo fisso e le faccette. Le categorie
  di Drupal diventano valori di una faccetta (un solo elenco per tipo, tav. 9 di oc:8783), quindi non servono le
  immagini dei termini, che nello shard non ci sono.
- **Tutto dallo shard, come le app**: lo shortcode riceve un layer, come il box `layer` della HOME del
  `config.json`; faccette e slider vengono da `MAP.filters` e `LABELS.taxonomies`. Il child non scrive id di layer né
  valori di termini: il layer di una pagina lo ricava dal route-type (`FORESTAS_ROUTE_TYPE_KINDS`) contando, sulla
  copia delle tracce, il layer di «Tipo percorso» che ha più percorsi di quel route-type, come fa oggi
  `forestas_search_layer_for_route_type()` (`inc/search.php`).
- **Griglie costruite sul server da una copia** (25/11): l'API Elastic dello shard pesa 2,76 MB su UAT, senza
  compressione e senza cache; scaricata nel browser a ogni visita delle voci del menu renderebbe le pagine lente sul
  telefono, vuote per i crawler e senza JavaScript, e vuote con lo shard giù. Con la copia del plugin (ultima copia
  buona, come POI, enti e dettagli) la prima pagina è nell'HTML e i filtri passano da una rotta REST del plugin.
- **Ordine per nome**, quello dell'API dello shard (`orderBy('name.keyword', 'asc')`), come le app.
- **Basi dei dettagli invariate**: `/sentiero/<slug>/`, `/itinerario/<slug>/`, `/cammino/<slug>/` (la maggior parte
  degli indirizzi Drupal usa già il singolare: 442 sentieri su 615, 135 itinerari su 136); i POI restano sotto
  `/punti-di-interesse/<slug>/`.
- **Codice generico nel plugin, configurazione nel child** (regola del repo): `wp-package` sa disegnare la griglia di
  un layer e gli slider; il child dice quale layer e quali faccette.

## Requisiti

### Pagine d'elenco

- [x] `/sentieri/`, `/itinerari/`, `/cammini/` mostrano sotto la testata la griglia dello shortcode di `wp-package`
  con il layer del loro route-type (`route-type-trail`, `route-type-itinerary`, `route-type-walk`), ricavato dalla
  copia delle tracce, senza id scritti. Se il layer non si trova, la griglia mostra un messaggio («I sentieri non
  sono disponibili in questo momento»), mai tutti i percorsi.
- [x] Faccette di `/sentieri/`: le stesse e nello stesso ordine di `forestas_search_track_facets()` tranne «Tipo di
  percorso», più gli slider `track_distance` e `track_duration` di `MAP.filters`, con gli estremi aperti (un cursore
  al suo estremo non pone limiti da quel lato; vedi l'overview del plugin). Uno slider compare solo se almeno un
  percorso della griglia ha quel valore: oggi su UAT ci sono su `/sentieri/` e non su `/itinerari/` e `/cammini/`.
- [x] In locale (WordPress collegato a dev) gli slider si provano con i dati di UAT caricati nella copia con
  `wp wmwp tracks-copy load <file.json>`; la procedura sta nella pagina di conoscenza.
- [x] `/itinerari/` e `/cammini/`: come `/sentieri/`, più «Tipologia itinerario» dalla famiglia `itinerary-type` di
  `LABELS.taxonomies`, costruita come le faccette delle scale di difficoltà (`from_families`).
- [x] `/punti-di-interesse/` mostra la griglia dei POI con le faccette del tab dei POI della ricerca unica (tipo di
  POI da `MAP.filters.poi_types`, aree degli overlay).
- [x] 9 card per pagina per i percorsi, 12 per i POI (come le view di Drupal); card uguale a quella della ricerca
  (`forestas-child/wp-package/card.php`); niente mappa nella griglia.
- [x] Campo «cerca» nelle quattro pagine (chiesto il 25/11, dopo le review): il testo dei percorsi lo cerca l'API dello
  shard come nella ricerca unica, quello dei POI la rotta dei POI; nome e suggerimento del campo per tipo.
- [x] `/itinerari/` si intitola «Itinerari» («Itineraries»), come la voce del menu (chiesto il 25/11).
- [x] `/cammini/` ha il suo sottotitolo («Scopri i cammini più belli…», con l'inglese), non quello di Itinerari
  (chiesto il 25/11).
- [x] Nomi del conteggio propri di ogni pagina («27 sentieri», «12 itinerari», «5 cammini», «40 punti di interesse»).
- [x] Lo stato (faccette, slider, pagina) è nell'indirizzo con i parametri del plugin (`activity`, `layer`,
  `hiking-difficulty`…, `pg`): un indirizzo filtrato si condivide e funziona con «Indietro».
- [x] La pagina `/punti-di-interesse/` si intitola «Punti di interesse» (IT) al posto di «Da vedere»: oggi il titolo
  italiano di una pagina con nid lo scrive Drupal a ogni sincronizzazione (`docker/scripts/config/pagine.php`,
  righe 72–95), quindi va cambiato lì, con l'impronta `_wpf_testo_drupal`.
- [x] Le stesse pagine in inglese, con i testi dello shard nella lingua della pagina (italiano con `lang="it"` dove
  l'inglese manca, come nel resto del sito).
- [x] Il child usa le funzioni nuove del plugin solo se ci sono (controllo per funzione, non alzando
  `FORESTAS_WMWP_MIN_VERSION`): fra l'aggiornamento del child (git pull) e quello del plugin
  (`scripts/wordpress-up.sh`) le pagine restano con la sola testata, senza shortcode scritti né errori, e il resto del
  child non si spegne.

### Ricerca unica

- [x] Il tab dei percorsi di `/cerca-nel-sito/` mostra gli slider `track_distance` e `track_duration`, nello stesso
  modo delle griglie.

### Accessibilità e UX

- [x] [UX] Slider con due `input type="range"` nativi, ognuno con il suo `<label>` visibile, il valore scritto
  accanto e `aria-valuetext` con l'unità («5 km», «1 ora e 30 minuti»): usabili da tastiera e senza trascinare
  (WCAG 2.1.1, 2.5.7).
- [x] [UX] Il numero dei risultati dopo un filtro si annuncia con un messaggio di stato completo («27 sentieri»),
  senza spostare il focus (4.1.3); con zero risultati un messaggio e, se c'è un filtro attivo, il pulsante per
  toglierlo.
- [x] [UX] Cambio di pagina della griglia: il focus va al titolo dei risultati, non resta sul numero cliccato.
- [x] [UX] Immagini delle card con dimensioni riservate e caricamento differito (niente salti di impaginazione).
- [x] Controllo WCAG 2.2 AA fatto da chi sviluppa su tutte le pagine nuove, con tastiera e VoiceOver, in Chrome,
  Firefox e Safari su macOS, sotto i 600px e da desktop, slider compresi; ciò che non va si corregge in questo lavoro.
  Esito di ogni prova in `notes.md`.

### Documentazione e messa in opera

- [x] `WP_PACKAGE_REF` di `docker/scripts/init-wordpress.sh` al commit del plugin con questo lavoro (`9bb0552`, `main`
  del plugin con la 0.7.0).
- [x] Pagina di conoscenza e procedura di messa in opera aggiornate; `docs/howto/ricreare-wordpress-da-zero.md` se
  cambia la sequenza.
- [x] Mancanze dello shard raccolte in `notes.md` per il ticket di fine lavoro.

## Rischi

[UX] Slider: un controllo nuovo, diverso dalle faccette a spunta, è il punto più esposto per tastiera e screen reader.
Mitigazione: input nativi, nessun cursore disegnato a mano, e la prova del requisito di accessibilità.

**Dati incompleti oggi.** Su UAT 404 sentieri su 617 hanno la distanza e 369 il tempo; itinerari e cammini nessuno;
su dev quasi nessuno (nell'indice il valore mancante è 0). Gli slider funzionano, ma su questi dati escludono molti
percorsi.

**Mattine su UAT.** Durante il reset notturno l'indice è parziale: la copia delle tracce non si sostituisce con una
non plausibile (ultima copia buona), così le griglie restano sui dati del giorno prima.

## Out of scope

- **Le pagine delle aree (`/carte`, `/carte/<area>`)** e la mappa delle aree: non ora (Rubens, 25/11). Tutto ciò che è
  emerso è in `notes.md`, «Per un ticket futuro: le pagine delle aree».
- La mappa delle aree in home: con la home (oc:9005).
- «Alte vie» e «Dorsali» come categorie distinte dalle attività `hiking` e `trekking`: da valutare nello shard.
- Lo slider del dislivello: compare da solo quando lo shard lo aggiunge a `MAP.filters`.
- I redirect dagli indirizzi Drupal delle pagine d'elenco e per categoria (`/sentieri-per-attivita/…`): con il
  lancio. Quelli dei dettagli sono di oc:9018.
- Le pagine di dettaglio sotto la pagina d'elenco (`/sentieri/<slug>/`).
- La ricerca unica resta sul modello attuale (indice nel browser).

## Moduli toccati

Repo `wp-forestas`:

- `themes/forestas-child/inc/listings.php` (nuovo): shortcode del child per le quattro pagine, layer dal route-type,
  faccette e nomi del conteggio per pagina.
- `themes/forestas-child/inc/search.php`: slider nel tab dei percorsi; faccette condivise con le griglie.
- `themes/forestas-child/functions.php`: inclusione del file nuovo.
- `themes/forestas-child/style.css` e `assets/`: stile di griglia e slider (lo stile dei filtri sta in
  `.wmwp-facet*` e `.forestas-filtri*`: si cambiano insieme).
- `config/pagine.json`: template delle quattro pagine, titolo di `da-vedere`.
- `docker/scripts/config/pagine.php`: titolo di una pagina con nid preso da `config/pagine.json`.
- `themes/forestas-child/inc/pagine-statiche.php`: i parametri dell'indirizzo passano nel 301 dei vecchi indirizzi
  (`/da-vedere/?pg=2` → `/punti-di-interesse/?pg=2`).
- `docker/scripts/init-wordpress.sh`: `WP_PACKAGE_REF`.
- `docs/knowledge/`, `docs/howto/`, `CLAUDE.md` (indice).
