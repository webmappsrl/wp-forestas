> Ticket: oc:9003

# Notes — Pagine Sentieri, Itinerari, Cammini e Punti di interesse

## Decisioni

- **Plugin e `WP_PACKAGE_REF`** (25/11): `wp-package` 0.7.0 è entrato in `develop` con la PR #9 (`0f4934e`) e in `main`
  con il merge commit `9bb0552`; `WP_PACKAGE_REF` punta a `9bb0552`, cioè a `main` come per oc:9018 (stesso contenuto
  di `develop`, ed è il commit che riceve UAT). Un solo commit per repo invece dei commit per task del piano.
Dialogo di `wm-plan` del 19/11 e del 25/11 (Rubens):

- **Griglie, non ricerca unica** (19/11, confermato il 25/11): le quattro pagine hanno la loro griglia con il tipo
  fisso e le faccette. La descrizione del ticket del 24/11 diceva «portano alla ricerca già filtrata»: è da
  riallineare (insieme al titolo, che dice ancora «che portano alla ricerca filtrata»).
- **Categorie come faccette**: le pagine per categoria di Drupal diventano la stessa pagina con una faccetta
  nell'indirizzo; niente sottopagine.
- **POI**: griglia di card dei POI con le tipologie come faccetta (non una griglia di card delle tipologie).
- **Plugin e child**: dati del wm-package nel plugin `wp-package`, custom di Forestas nel child.
- **Faccette di `/sentieri/`**: le stesse della ricerca tranne «Tipo di percorso»; più gli slider.
- **Distanza e tempo come slider da `MAP.filters`**, come le app (25/11; il 19/11 si era scelto fasce fisse come
  Drupal, superato dalla regola di leggere dal `config.json`). Anche nel tab dei percorsi della ricerca unica.
- **Slider con gli estremi aperti** (challenge, 25/11): un cursore al suo estremo non pone limiti da quel lato; un
  percorso senza valore non passa un filtro attivo. Le app (`wm-core`) chiudono l'intervallo appena si tocca lo slider
  (`ElasticsearchController.php:220-226`, `gte`/`lte`): su UAT, «fino a 10 km» escluderebbe i 63 sentieri sotto 1 km.
  Comportamento da proporre anche a `wm-core`.
- **Slider nascosti senza dati** (challenge, 25/11): uno slider compare solo se almeno un percorso della griglia ha
  il valore (su UAT nessun itinerario né cammino ha distanza o tempo; su dev 3 sentieri e 4 itinerari). Le app lo
  mostrano sempre. Per provarli in locale (WordPress su dev): `wp wmwp tracks-copy load` con la risposta dell'API di
  UAT.
- **Ordine per nome ripulito** (review finale, 25/11): come deciso «per nome», ma sul nome italiano senza spazi e tab ai
  lati, come `sortName()` della ricerca unica; con l'ordine grezzo dell'API sei nomi con un tab o uno spazio in testa
  aprivano `/itinerari/` e `/cammini/`.
- **Ordine per nome dell'API dello shard**, come le app (25/11). `field_ordine_di_visualizzazione` di Drupal serve
  soprattutto a tenere in fila le 30 tappe del Sentiero Italia (più CMSB tappa 1 e 2 sentieri a 1): non si importa.
- **Layer, non route-type, nello shortcode**; il child ricava il layer dal route-type senza scrivere id.
- **«Tipologia itinerario»** su `/itinerari/` e `/cammini/` dalla famiglia `itinerary-type` di `LABELS.taxonomies`,
  anche se le app non la mostrano (non è in `MAP.filters`).
- **Basi dei dettagli invariate** (`/sentiero/<slug>/`…).
- **Griglie costruite sul server da una copia delle tracce** (challenge, 25/11): l'API Elastic pesa 2,76 MB su UAT
  senza compressione né cache.
- **Pagine delle aree fuori da questo lavoro** (challenge, 25/11): vedi sotto.
- Branch e cartella si chiamano ancora «…-e-carte»: il nome è nato prima che le aree uscissero dallo scope.

### Modifiche chieste dopo le review (25/11)

- **Campo «cerca» nelle quattro pagine** (Rubens): prima escluso perché le pagine per categoria di Drupal non lo avevano.
  Per i percorsi il testo lo cerca l'API dello shard (stessa chiamata della ricerca unica, `wmwp_grid_text_ids()`,
  risposta nell'object cache: su Forestas, senza object cache esterno, dura la richiesta; mai nei transient, perché la
  rotta è pubblica — review interna), poi layer e filtri sulla copia, in ordine di pertinenza come `rankByText()`; per i POI
  la rotta dei POI, che cercava già il testo. Con lo shard che non risponde al testo la griglia mostra l'errore della
  ricerca, non «0 risultati». Nome e suggerimento del campo per tipo (`forestas_listing_search_texts()`).
- **Titolo «Itinerari»** (Rubens): la pagina si chiamava «Itinerari e Cammini di Sardegna» come su Drupal; ora
  `titolo` in `config/pagine.json` («Itinerari» / «Itineraries»), come la voce del menu. Allineati la knowledge 8969 e
  la trappola del menu nel `CLAUDE.md`, che usavano quel titolo come esempio.
- **Sottotitolo di Cammini** (Rubens): prendeva quello di Itinerari («Scopri gli itinerari più belli…»); ora
  `sottotitolo` per lingua in `config/pagine.json` («Scopri i cammini più belli e suggestivi della Sardegna, con
  informazioni dettagliate sui percorsi che questi cammini attraversano.» / «Discover the most beautiful and evocative
  walks in Sardinia, with detailed information on the paths these walks cross.», sullo stile dell'inglese di Drupal per
  Itinerari). `docker/scripts/config/pagine.php` accetta `sottotitolo` come `titolo`, prima dell'impronta.
- **Voce «Sentieri per tutti» dell'header** (Rubens): puntava a `#`; ora `config/menu.json` la collega a
  `/sentieri/?activity=accessible-trails` (inglese «Trails for everyone» → `/en/sentieri/?activity=accessible-trails`),
  con indirizzi relativi che valgono in ogni ambiente. L'identifier `accessible-trails` è scritto nel menu su richiesta;
  la tav. 1 di oc:8783 prevede che in futuro «Sentieri per tutti» porti ai sentieri con il grado di accessibilità sopra
  una soglia: quando ci sarà, il link si cambia qui. Sui server il cron `allinea` applica da solo le differenze dei menu.

## Per un ticket futuro: le pagine delle aree

Il ticket del 24/11 aveva accorpato qui oc:9002 (`/carte` e `/carte/<area>`). Il 25/11 si è deciso di non farle ora.
Quanto verificato:

- **Drupal `/carte`**: «Territori della Sardegna – Le aree geografiche della Sardegna», le 34 zone del vocabolario
  `zona_geografica`, 9 per pagina in 4 pagine; ogni zona ha `/carte/<field_slug>` (es. `/carte/goceano`), con 12
  percorsi per pagina (`views.view.carte.yml:102`), solo sentieri e itinerari. Nessun menu del sito collega `/carte`
  (menu principale: Sentieri, Itinerari, Da vedere, Da sapere, Mappa interattiva, News; nessuna voce in
  `menu_link_content_data`).
- **Le «Carte» del wireframe di oc:8783 sono un'altra cosa**: «nuova classe di poligoni perimetrati sui progetti di
  sentieristica dei comuni; nome provvisorio» (`decisioni.md:172`), filtro della tav. 2 senza dati; decisione aperta
  su nome, fornitore dei poligoni e numero (`:382`, `:486`); le 39 aree il wireframe le chiama «aree territoriali».
- **Menu concordato** (`decisioni.md:75-77`): sette voci, senza Carte.
- **Slug**: dei 34 `field_slug` delle zone Drupal, 14 coincidono con lo slug del nome di una delle 39 aree di Geohub
  (anglona, arci-grighine, barbagia-di-seulo, campidano-di-cagliari, gennargentu-ogliastrino, goceano,
  gutturu-mannu, marghine, montiferru, planargia, sarcidano, sulcis, supramontes, trexenta).
- **Dati nello shard** (dev, 25/11): le 39 aree sono in `MAP.layers` del `config.json` con nome (39 anche in
  inglese), descrizione (38, solo italiano, troncata con «...» come arriva da Geohub tramite
  `TaxonomyWhereLayersImportService`), `feature_image` (35, conversione `thumbnail_400_200`; senza foto Mandrolisai,
  Marmilla - Le Giare, Gerrei e bacini del Flumendosa, Marghine). Geometrie nell'overlay «Aree geografiche»
  (`feature-collection/2.geojson` su dev, `3.geojson` su UAT: l'id cambia fra ambienti; nel plugin l'id di un overlay
  è la sua posizione, `app-overlays.php:274`), 39 MultiPolygon, circa 400 KB e 14.717 vertici.
- **UAT senza le aree**: oc:9001 è su `develop` di `forestas`, non su `main`; il database di UAT ha 12 layer e
  l'overlay punta a un file con gli id di Geohub (217, 220…).
- **Design**: tav. 1 di oc:8783, «Aree territoriali»: mappa SVG con le 39 aree in cinque fasce del blu dei sentieri
  (nessun percorso · 1–10 · 11–30 · 31–60 · oltre 60), area scelta in arancio, card accanto con foto, descrizione,
  numeri e «Vedi i percorsi dell'area», «Vedi le aree come elenco»; nel wireframe il pulsante apriva l'app.
- **Punti emersi dalla challenge**:
  - nomi dei layer non unici fra i tipi (su UAT «Area G (Nord-EST)» per i layer 1 e 7): lo slug va cercato solo fra
    le aree dell'overlay;
  - un nome che cambia (l'import orario riscrive le aree) cambia l'indirizzo senza storico;
  - sulla pagina di un'area, la faccetta delle aree con l'area fissa dà l'intersezione (su dev 117-118 percorsi su 778
    stanno in due o più aree, uno in 8): va tolta o cambiata;
  - un'area senza percorsi (Coros, layer 12 su dev);
  - sul telefono, a 343 px, la mappa è alta circa 650 px e la card dell'area scelta resta sotto, fuori schermo;
    l'area più piccola (Parteolla) è circa 40×38 px;
  - `aria-pressed` su 39 zone a scelta singola non dice allo screen reader che un'altra zona si è spenta;
  - per 4 aree il centro del riquadro cade fuori dalla geometria (Arci-Grighine, Campidano di Oristano-Sinis-Arborea,
    Barbagia di Belví-Gennargentu, Montalbo-Tepilora): il numero va messo dentro la zona;
  - i numeri della mappa devono venire dalla stessa copia della griglia;
  - senza la copia del `config.json`, 503 come gli enti, non 404;
  - una pagina creata da `config/pagine.json` resta pubblicata anche se la si toglie dal file.
- **Redirect al lancio**: corrispondenza fra le 34 zone Drupal e le 39 aree ancora aperta (`decisioni.md:489`); per
  esempio i sentieri e i POI di «Baronie e Montalbo» cadono per lo più in «Montalbo - Tepilora» e in parte in
  «Baronie-Tuttavista-Cedrino».

## Mancanze dello shard (per il ticket di fine lavoro)

1. **Attività fuse**: «Alte vie» (tid 4825) e «Hiking» (4833) sono una sola attività `hiking`, «Trekking» (4827) e
   «Dorsali escursionistiche a tappe» (4831) una sola `trekking`, perché su Drupal hanno lo stesso
   `field_geohub_identifier`. Da valutare: forse la fusione era voluta.
2. **Icone delle attività**: `taxonomy_activities.icon` è valorizzato per 2 attività su 7 (`accessible-trails`,
   `educational-trails`); su Drupal ogni termine ha `field_classe_id_icona`.
3. **Dislivello**: nessuno slider in `MAP.filters`; `ascent` vuoto nell'indice di UAT.
4. **Distanza e tempo mancanti**: su UAT 213 sentieri su 617 senza distanza e 248 senza tempo; nessun itinerario né
   cammino li ha; su dev quasi nessuno. Nell'indice il valore mancante è 0, non null.
5. **Passo dello slider della distanza**: `min 1`, `max 30`, `steps 3`: con un passo che non divide l'intervallo il
   cursore arriva a 28, non a 30.
6. **Nomi delle tappe del Sentiero Italia scritti in modo diverso**: «Tappa Z17 Sentiero Italia: …», «Tappa Z18 …»,
   spazio iniziale in Z23 e Z28, «(Tappa SI Z12)», doppio spazio in «(Tappa  Z13)»; nomi con tab o spazio iniziale in
   testa all'ordine per nome (es. «\tCMSB - TAPPA 04…»).
7. **Ordine per nome dell'API**: `name.keyword` ordina per byte, quindi «Àndala Segura…» e i nomi minuscoli
   («bivio …») finiscono dopo la «Z».
8. **`itinerary-type`**: nomi solo in italiano (Drupal ha l'inglese per 6 tipologie su 9); assente da `MAP.filters`.
9. **Nome dello slider della distanza**: «Lunghezza del sentiero» (`track_distance` di `MAP.filters`) vale anche per
   itinerari e cammini, dove il sito lo mostrerà com'è.
10. **Aree geografiche** (per il ticket delle aree): descrizioni solo in italiano e troncate con «...», una area senza
   descrizione, 4 senza foto, foto in conversione 400×200.

## Informazioni per altri ticket

- **oc:9018**: il 25/11, cambiando branch in `wp-forestas`, nel working tree c'erano modifiche non committate a
  `notes.md` e `overview.md` di 9018 (aggiornamenti del 25/11 sul `config.json`). Sono rimaste nel working tree e non
  entrano nei commit di 9003.

## Divergenze dal piano, task per task

### Task 1 slider nelle faccette della ricerca

Il piano metteva gli slider in `forestas_listing_track_facets()`. Con il Task 3 sono entrati in
`forestas_search_track_facets()` (ricerca unica), da cui le griglie prendono le faccette: averli anche in
`listings.php` li avrebbe doppiati. `forestas_listing_track_facets()` toglie solo «Tipo di percorso» e aggiunge la
«Tipologia itinerario».

### Task 2 template griglia scritto a mano in post.json

Il piano diceva di creare il template nel WordPress locale e di esportarlo. L'esportazione riordina però tutti gli
us_content_template (140 righe di differenza per un template nuovo): il blocco è stato copiato a mano da quello della
testata in `config/post.json` (13 righe), e creato anche nel sito locale con la stessa chiave
(`_wp_forestas_chiave` = `template-pagina-griglia`); `applica` non vede differenze.

### Task 5 prove insieme al plugin

Le prove nel browser e con VoiceOver sono state fatte una volta sola, per plugin e child insieme: esiti e correzioni in
`notes.md` del plugin (`wp-package`, stessa cartella), «Prove di accessibilità». Due correzioni toccano il child: lo
stile di binario e cursore degli slider (`style.css`, Version 1.19.2) e la disposizione di nome, valore e binario.

## Review interna

- **Primo giro, revisore di wp-forestas (25/11)**: nessun bloccante, nove cleanup risolti. Nome del file nell'overview;
  test di `[forestas_listing]` senza lo shortcode del plugin; `forestas_search_type_layers()` in `inc/search.php`, usata
  anche dal ramo Elastic; `forestas_listing_layer()` passa da `forestas_search_layer_for_route_type()` (una sola
  implementazione); `_nota` di `config/pagine.json` (`titolo` su una pagina con nid, template `griglia`); riga in
  `docs/howto/ricreare-wordpress-da-zero.md`; selettori degli slider tutti sotto `.wmwp-search` (Version 1.19.3); docblock
  sui route-type contati; uno spazio in `tests/dettaglio.php`.
- **Secondo giro, stesso revisore (25/11)**: nessun bloccante, ciclo chiuso. Restano le condizioni di rilascio:
  `WP_PACKAGE_REF` al merge di `wp-package` 0.7.0 e il puntatore del submodule in `forestas`.
- **Dal revisore di wp-package (25/11)**: il 301 di `/da-vedere/` (vecchi indirizzi Drupal, `inc/pagine-statiche.php`)
  perdeva i parametri; ora `/da-vedere/?pg=2&poi-type=viewpoint` → `/punti-di-interesse/?pg=2&poi-type=viewpoint`, anche
  per `/node/<nid>` e le tipologie dei «da sapere». Verificato con curl.

## Bug trovati

Nessuno nel child. Quelli trovati nelle prove (link delle card, cursore invisibile in Safari, focus dopo il cambio di
pagina, test del plugin che cambiavano la copia) sono nelle note del plugin.

## Follow-up

- Riallineare titolo e descrizione del ticket alle decisioni (alla fine, con l'overview).
- Messa in opera su dev e UAT: `docs/howto/messa-in-opera-delle-pagine-d-elenco.md`.
