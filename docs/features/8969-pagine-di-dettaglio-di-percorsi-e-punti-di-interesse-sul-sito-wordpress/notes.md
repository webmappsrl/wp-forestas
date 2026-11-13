> Ticket: oc:8969

# Note — Pagine di dettaglio di percorsi e punti di interesse sul sito WordPress

## Decisioni prese durante la pianificazione

- **Nomi inglesi dei termini `route-type` inseriti a mano in Nova** (Rubens, 12/11). «Sentiero»,
  «Itinerario» e «Cammino» hanno solo `name.it`, perché `ConfigureTaxonomyTermsCommand` scrive solo
  l'italiano. L'inglese si scrive a mano nel campo traducibile del termine, nella Resource
  `TaxonomyTerm` di Nova (`/nova/resources/taxonomy-terms`), invece di aggiungerlo nel comando.
  - Resta dopo il sync notturno di dev e UAT (`sardegnasentieri:import --reset`), verificato sul
    codice: il reset non svuota `taxonomy_terms` (`ImportSardegnaSentieriCommand.php:883-888`); lancia
    `configure-taxonomy-terms` senza `--fresh` (riga 158), che riallinea solo il nome `it`
    (`ConfigureTaxonomyTermsCommand.php:385-390`); l'import collega i termini per identifier senza
    toccare il nome.
  - **Da rifare in produzione** e su ogni installazione nuova: il database è un altro, e il comando
    crea i termini solo in italiano. Si perde anche se qualcuno lancia il comando con `--fresh`.
  - Salvare il termine in Nova **non** aggiorna i file delle tracce, l'indice Elasticsearch né
    `pois.geojson`, che copiano i nomi dei termini: «Rinominare o cancellare un termine | niente:
    dettagli e `pois.geojson` restano indietro finché non si rigenerano»
    (`wm-package/docs/knowledge/tassonomie-nel-dettaglio-di-tracce-e-poi.md`, «Quando si rigenera»;
    `TaxonomyTerm::booted()` riscrive solo il `config.json` delle tassonomie, non dei termini).
  - Sul dev e su UAT ci pensa il reset notturno: ogni import finisce con `CalculateNearbyTracksJob`,
    che a fine batch accoda `RegenerateTaxonomyWhereOutputsJob` (file delle tracce, indice e
    `pois.geojson` dell'app 1). L'inglese si vede dal mattino dopo.
  - Per vederlo subito, o in produzione: `php artisan wm:resync-taxonomy-where --app=1
    --outputs-only`, che rigenera solo le uscite pubbliche senza ricalcolare niente. L'Action Nova
    «Reindex Scout» dell'App non basta: rifà solo l'indice, non i file delle tracce.
  - Gli altri termini creati dal comando (difficoltà per attività, inclusività) hanno lo stesso
    problema; per ora si fanno solo i tre `route-type`.

- **Niente 301 dai vecchi indirizzi Drupal** (Rubens, 12/11). Su 120 tracce del dev prese a caso,
  69 hanno l'indirizzo Drupal (`forestas.url`) uguale a quello nuovo (`/<route-type>/<slug>/`), 13
  cambiano solo la base (`/sentieri/`, `/node/`, `/itinerario/` per i cammini) e 38 hanno lo slug
  diverso, perché in Drupal l'alias era scritto a mano (es. `/sentieri/medau-monte-nieddu-allineddus-d-210`
  contro `/sentiero/medau-monte-nieddu-is-allineddus-d-210`). Non si sa ancora se la nuova app avrà
  il link «scheda completa», che oggi punta a quegli indirizzi
  (`https://32.app.geohub.webmapp.it/?track=80288`). **Se servirà**, non si fa un redirect in
  WordPress: con l'id della traccia si lancia una query sullo shard che sostituisce gli URL vecchi
  con quelli corretti del nuovo sito. Da portare nella pagina di conoscenza del dettaglio in
  `wp-forestas` alla chiusura del ticket.

- **Base e slug per lingua** (Rubens, 12/11): nelle pagine inglesi il nome inglese della traccia o del
  POI e del suo `route-type`, e quello italiano dove l'inglese manca. Dinamico: una traduzione
  aggiunta dopo cambia l'indirizzo inglese di quell'elemento senza interventi, e l'indirizzo vecchio
  porta al nuovo con un 301.

## Da ricordare

- [x] Inglese dei tre `route-type` inserito in Nova sul dev (12/11): Trail, Itinerary, Walk, come il menu
      inglese del sito (TRAILS, ITINERARIES, WALKS). La base di `/en/` usa il nome inglese del termine.
- [ ] Inglese dei tre `route-type` inserito in Nova su UAT.
- [ ] Inglese dei tre `route-type` inserito in Nova in produzione, alla messa in opera.
- [ ] Dopo l'inserimento in produzione: `wm:resync-taxonomy-where --app=1 --outputs-only`.
- [ ] Prima del rilascio in produzione: caricare il set di icone con le tre icone delle disabilità da `/icons-upload` su
      dev e su UAT, e associarle ai termini in Nova (Rubens, 13/11; passi in «Messa in opera»).
- [ ] Creare il ticket dell'**unicità stabile degli slug** dei doppioni (anteprima già pronta, da approvare; vedi
      «Consulenza SEO»).
- [ ] A fine lavori di oc:8969: creare il ticket della **percorribilità** (dato nuovo nel wm-package sul modello
      `HikingRoute` di osm2cai2: scala 0–5 e «temporaneamente interrotto», data, autore, nota, storico; poi indicatore e
      fascia del dettaglio e filtro della ricerca sul dato vero), con quanto raccolto in «Percorribilità» (Rubens, 13/11).
- [ ] A fine lavori di oc:8969: creare il ticket della **stampa** (PDF della scheda di sentiero e POI generato dallo shard
      nel wm-package, asincrono, versione standard; poi il pulsante «Stampa» del dettaglio e, nella ricerca, «Stampa la
      mappa di insieme» e «Scarica le schede PDF»), con quanto raccolto in «Stampa» (Rubens, 13/11).
- [ ] A fine lavori di oc:8969: creare il ticket unico **segnalazioni e recensioni** (strada WordPress decisa il 29/10:
      modulo nella scheda dinamica, contenuti dedicati in WordPress agganciati al `nid` di Drupal, email a Forestas,
      approvazione delle recensioni, allegati protetti, pagina «Segnalazioni», import da Drupal da decidere per i dati
      personali; lo shard come evoluzione): l'analisi completa è in «Ticket da creare: segnalazioni e recensioni»
      (Rubens, 13/11).
- [ ] A fine lavori di oc:8969: creare il ticket degli **avvisi del sentiero** (modello del dato nello shard, inserimento
      dal backend, API degli avvisi attivi, fascia della home e pagina elenco), con quanto raccolto in «Avviso del
      sentiero» (Rubens, 13/11).

## Richieste al backend che restano nostre

Non vanno nel messaggio ad Alessandro (decisione di Rubens, 12/11): restano segnaposto nel
dettaglio, elencate anche in «Cosa manca nel backend» dell'overview.

- Download GPX: l'endpoint era rotto allo scrum del 12/11; Alessandro avvisa Rubens quando è a posto.
- PDF per la Stampa, generato dallo shard.
- Salvataggio di segnalazioni e recensioni.
- `properties.forestas` dei POI ripulito come quello delle tracce.
- Avvisi e percorribilità del sentiero.

## Ambiente locale allineato al dev (12/11)

Il DB locale aveva 29 migrazioni indietro e nessun dato di enti, termini e `route-type`; senza la
copia Drupal in locale l'import non si può rifare.

- Backup del DB locale prima: `/Volumes/WebmappDev/Archivio/backup-db-forestas-locale/forestas-locale-20261112-1106.dump`.
- Migrazioni applicate, poi DB sostituito con il dump del dev
  (`…/forestas-dev-20261112-1107.dump`): 769 tracce, 976 POI, 93 enti, 70 termini.
- `.env` locale: commentata `EC_TRACK_MODEL=Wm\WmPackage\Models\EcTrack` (regola di oc:8959), così i
  file delle tracce escono con il `forestas` di Forestas come sul dev.
- `scout:import "App\Models\EcTrack"` (l'alias locale scriveva su un indice vuoto, come annotato in
  oc:8965) e `wm:resync-taxonomy-where --app=1 --outputs-only`.
- Verificato: dettaglio `api/ec/track/311` e lista Elasticsearch (769 tracce) hanno `route-type` con
  `en` (Trail 614, Itinerary 102, Walk 53).
- I due dump si cancellano a fine lavori.

## Info per altri ticket

- **oc:8965 (API degli enti)**: gli enti collegati nel dettaglio di tracce e POI li sta facendo
  Alessandro adesso, non più «per ultimi» in oc:8965 (punto aperto 1 e le ultime due righe della
  tabella «Cosa serve» della sua descrizione). Struttura concordata il 12/11: `organizations`, per
  ruolo (`manager`, `maintainer`, `surveyor`, più operatore e complesso forestale), ogni ente con
  `id`, `name` e `slug`; nei POI solo l'operatore. A oc:8965 restano l'elenco `/enti`, la pagina
  `/enti/<slug>` e gli enti nella ricerca unica, con l'API non pubblica (scrum 11/11). Da riportare
  nella descrizione di oc:8965 alla chiusura di questo ticket.

## Rimandato

- **Ripiego di `api/ec/track/{id}` senza i dati di Forestas** (Rubens, 12/11: caso raro, per ora non
  si chiede ad Alessandro). Se il file della traccia su AWS manca (traccia appena creata finché il
  suo job non scrive il file, storage che non risponde, ambiente senza file generati),
  `EcTrackController::getGeojson` (`wm-package/src/Http/Controllers/Api/EcTrackController.php:22-35`)
  costruisce il JSON con `Wm\WmPackage\Models\EcTrack` e non con `App\Models\EcTrack`: escono senza
  `organizations`, `nearby_tracks`, `downloads`, e con `allegati`, `video`, `info_utili`. Correzione
  proposta: usare `config('wm-package.ec_track_model')`. Il reset notturno non lo provoca: non
  cancella i file. Il template intanto tratta le chiavi mancanti come dato assente.

## Divergenze dal piano, task per task

### Plugin, Task 2 — filtri per lo shard locale
Il WordPress locale leggeva le API del dev (`forestasdev` in `environment.ts`). Aggiunti al plugin i filtri generici
`wmwp_api_urls` e `wmwp_server_origin`; in locale li imposta un mu-plugin non versionato
(`wp-content/mu-plugins/wpf-shard-locale.php` nel volume di WordPress), che riscrive anche le foto da `localhost:9000`
al dev, perché il MinIO locale non ha i file.

### Plugin, Task 3 — lingue e impronta delle basi
Le lingue dell'indice sono i codici di WPML (`it`, `en-us`), i nomi dell'API stanno sotto `en`: `wmwp_detail_text()`
prova anche le due lettere. L'indice ricorda l'impronta delle basi (`wmwp_detail_base_signature()`) e si ricostruisce
se cambia: senza, una base cambiata dal tema restava vecchia fino a un'ora.

### Plugin, Task 4 — redirect
301 anche per lo slug di un'altra lingua sotto qualsiasi base (indirizzo di prima che arrivasse una traduzione), se un
solo elemento lo ha; il 404 della rotta non diventa più 503 (trovato provando la pagina inglese).

### Plugin, Task 5 — POI senza nome nell'API
`api/ec/poi/{id}` non ha `name` né `taxonomies` (lacuna del package, segnalata alla sessione OC:8916 e da girare ad
Alessandro). L'indice conserva nomi e `out_source_feature_id`: il nome della pagina del POI viene dall'indice (al
massimo un'ora indietro) e il controllo «è l'elemento giusto» usa l'id della fonte. Aggiunte
`wmwp_template_header()`/`wmwp_template_footer()` per i temi a blocchi.

### Plugin, Task 6 e 7 — SEO e WPML
Con Impreza il plugin passa i dati a `us_meta_tags`/`us_rel_canonical` e non stampa i suoi meta. WPML non stampa gli
`hreflang` sulle pagine senza post: li stampa il plugin.

### Child, Task 1 — /da-vedere/
`/da-vedere/` finiva sull'allegato `da-vedere.jpg` (WordPress trova l'allegato con lo stesso slug): il redirect degli
alias Drupal (`inc/pagine-statiche.php`) ora parte anche su `is_attachment()`.

### Child, Task 3-5 — template
Un template per tipo (`wp-package/track.php`, `wp-package/poi.php`) invece di una parte per sezione; classi con il
prefisso `wm-` (Rubens). Le note numerate del wireframe non si riportano (Rubens). «Sentiero per tutti» (nota 11)
all'inizio non c'era perché nessun sentiero aveva il punteggio: aggiunto il 13/11, vedi «Accessibilità come la Tavola 3». Il nome perde il codice tra parentesi quando c'è la bandierina; i nomi dello shard uguali all'identifier
(es. provincia «R19621461» in inglese) si scartano.

## Da verificare

- **Ripubblicazione del file dopo un salvataggio** (osservato in locale il 12/11): rinominata la traccia 520 da tinker,
  `UpdateEcTrackAwsJob` è girato ma il file su MinIO è rimasto con il nome vecchio; rilanciato a mano
  (`dispatchSync`) il file si è aggiornato. Finché il file è vecchio il dettaglio con l'indirizzo nuovo risponde 404 (il
  controllo nome ↔ slug non mostra un elemento che non torna). Da riprovare sul dev salvando da Nova prima di
  segnalarlo come bug.

## Messa in opera

- Dopo il merge del plugin, il nuovo commit di `wp-package` va in `WP_PACKAGE_REF` di `docker/scripts/init-wordpress.sh`
  (CLAUDE.md di wp-forestas, «Trappole»). `scripts/wordpress-up.sh` non va più lanciato a mano: dal 13/11 dev e UAT si
  aggiornano da soli con GitHub Actions a ogni push su `develop` (dev) e su `main` (UAT), senza approvazione, e il
  workflow lancia `wordpress-up.sh` sull'host quando cambia il puntatore di `wp-forestas` (`docs/howto/deploy-automatico.md`
  di forestas). Restano a mano i passi qui sotto.
- **UAT parte con il merge su `main`**: i passi a mano di UAT (icone, `pagine`, `applica`) vanno fatti subito dopo, o
  prima dove possibile (il set di icone si può caricare prima del merge).
- Su ogni ambiente: `bin/wordpress-config.sh pagine --conferma` (slug «punti-di-interesse») e `applica --conferma`.
- **Prima di andare in produzione, il set di icone va caricato su dev e su UAT** (Rubens, 13/11): senza, la colonna
  Accessibilità ripiega sui disegni del template e i termini associati in Nova puntano a icone che il set non ha.
- Icone delle tipologie di disabilità, su ogni ambiente (dev, UAT, produzione): scaricare il set attuale
  (`<awsApi>/json/icons.json`), aggiungere le tre icone con gli script di `icone/` (passi nel commento di
  `aggiungi-al-set.py`), caricare il file da `/icons-upload` dello shard, poi associare in Nova le tre icone ai termini di
  «Disabilità accolte» (vedi «Icone dal set dello shard»). Il set di partenza è sempre quello dell'ambiente su cui si
  carica, appena scaricato (dev e UAT possono avere icone diverse): le tre icone si aggiungono e basta, e lo script si
  ferma senza scrivere se un'icona che c'era manca o è cambiata (Rubens, 13/11).

## Review finale (12/11)

Revisore con contesto nuovo sui due repo. Corretti, ciascuno con il suo test prima: collisione del suffisso dei
doppioni, indice salvato senza tracce con Elasticsearch vuoto, shard giù richiamato a ogni richiesta, pagina base
rinominata non vista dall'impronta, basi uguali a slug di tipi di contenuto, indice intero caricato su `init` (ora le
basi stanno in un'opzione piccola), skip link senza destinazione (`main#page-content`), «Il itinerario in breve».

Da decidere con Rubens: **il nome del cammino accanto all'etichetta del tipo** (overview, «Differenze per tipo»): il
JSON della traccia ha solo gli id dei layer, il nome non c'è.

Rimandati (Minor): query principale della home sui dettagli (`posts_pre_query`); `wmwp_detail_path` accettato anche
fuori dalla regola; lock dell'indice non atomico; POI senza `out_source_feature_id` non controllati; `$ordine` non
inizializzata nel template; `title` di allegati e video senza `??`; roadbook nascosto senza JavaScript; testo chiuso da
«Mostra tutto» raggiungibile da tastiera; testata compatta letta dagli screen reader; lettura della posizione a ogni
evento di scroll; `<main>` annidato nei template del plugin su alcuni temi; basi nuove raggiungibili solo dopo la
ricostruzione dell'indice (da scrivere nel README).

## Aggiornamenti dal backend

- **12/11, `organizations`** (Alessandro, branch `feature/oc-8959-poi-nel-dettaglio`, e8a56db): enti per ruolo
  (`manager`, `maintainer`, `surveyor`, `operator`, `forest_complex`) con `id`, `slug` e `name`; `entities` non esce più.
  Verificato in locale dopo `wm:resync-taxonomy-where --app=1 --outputs-only`: il child mostra i nomi in italiano e in
  inglese senza modifiche (le chiavi erano già quelle). La lettura di `entities` in `forestas_dettaglio_enti()` resta
  come ripiego finché il cambio non è su tutti gli ambienti. Nome del cammino e dettaglio del POI ancora da arrivare.
- **12/11, roadbook nei Blocchi Dettaglio** (Alessandro, oc:8959, 198fdae): l'import scrive il roadbook in
  `properties.config_detail`, un blocco `info` con una riga per tappa, titolo «Tappa N» («Stage N» in inglese) e il
  paragrafo di Drupal; titolo e testo solo nelle lingue in cui la tappa ha un testo. `forestas.roadbook` non esce più.
  Il child legge le righe di tutti i blocchi `info` in un solo elenco (`forestas_dettaglio_blocchi()`, come la
  webapp), con i titoli dei dati al posto di «Tratto N»; testo nella lingua con ripiego sull'italiano, come il resto
  della pagina. Provato in locale aggiungendo il blocco al file della traccia 680 con un mu-plugin temporaneo, poi
  tolto: i dati locali non hanno ancora `config_detail`, che arriva con l'import nuovo (su dev e UAT al reset
  notturno dopo il deploy di 8959). Restano da Alessandro il nome del cammino e la distanza fra i sentieri correlati.
- **13/11, `sardegnasentieri_id` e nome del cammino** (Alessandro, oc:8959, f29b4c9): `forestas.sardegnasentieri_id` nel
  dettaglio della traccia e del POI, `sardegnasentieri_id` piatto nel `_source` dell'indice Elasticsearch delle tracce,
  e nel `forestas` di `pois.geojson` dopo che l'import ha riscritto il POI; `properties.layer_name`: nome (in tutte le
  lingue) del layer di tipologia della traccia, che il sito userà come nome del cammino (oggi «Cammini di Sardegna» per
  tutti i cammini, un layer per cammino in futuro). Verificato il 13/11 dal codice dello shard (Seleni 1330, CMSB 18
  1289, POI 9 5646); nei file locali non c'è ancora finché le tracce non vengono ripubblicate e l'indice rifatto (su dev
  e UAT con il reset dopo il deploy). La distanza fra i sentieri correlati è stata scartata (7e61a3f): né il sito
  attuale né il wireframe la mostrano, e i correlati stanno entro 50 m.
- **13/11, in uso nel sito** (Rubens: «usalo già per rendere più consistenti i post dinamici anche quando vengono
  reimportati»):
  - *Plugin*: `wmwp_detail_source( $type, $props )` e filtro `wmwp_detail_source` (di base `out_source_feature_id`),
    chiamato sulle tre forme dei dati (dettaglio, `pois.geojson`, `_source` dell'indice). La chiave serve a due cose:
    il controllo dell'elemento (se dettaglio e indice ce l'hanno decide lei, anche per le tracce: una traccia rinominata
    resta lei, una omonima che dopo il reset ha preso il suo id dà 404) e il suffisso dei doppioni dello slug,
    `-<chiave>` invece di `-<id>`, così l'indirizzo di due sentieri con lo stesso nome non cambia a ogni reset notturno.
    Test in `tests/php/detail-index.php` e `detail-render.php`.
  - *Child*: `forestas_dettaglio_nid()` sul filtro, con il nid da `forestas.sardegnasentieri_id` o da
    `sardegnasentieri_id` piatto; senza, resta `out_source_feature_id`, che in `pois.geojson` è lo stesso nid (serve
    finché l'import non riscrive i POI). Senza questo, nel dettaglio del POI il controllo non controllava più niente:
    `out_source_feature_id` lì non esce più (f29b4c9).
  - *Nome del cammino*: `forestas_dettaglio_nome_cammino()`; nella riga sopra il titolo il cammino porta il nome del suo
    layer al posto delle aree, come la Tavola 5 («Cammino · Cammino Minerario di Santa Barbara»); oggi «Cammini di
    Sardegna» per tutti. Sentieri e itinerari non lo mostrano: il loro layer è solo il nome del tipo.
  - *In locale*: indice e file AWS rigenerati con `RegenerateTaxonomyWhereOutputsJob(1)` il 13/11.

## Bug trovati dopo la review

- **Selettore di lingua della testata verso la home** (Rubens, 12/11): su un dettaglio, «EN» portava a `/en/`. WPML
  calcola il selettore una prima volta prima che il plugin carichi il dettaglio (template_redirect), e la testata usa
  quel calcolo. Il filtro `icl_ls_languages` ora ricava gli indirizzi dal percorso della richiesta e dall'indice
  (`wmwp_detail_request_urls()`), senza leggere il JSON; una guardia evita il rientro, perché `wpml_permalink` richiama
  il selettore (la prima versione esauriva la memoria). Test in `tests/php/detail-sitemap.php`.

- **Mappe senza le tile Webmapp in locale** (Rubens, 12/11), nel dettaglio e nella ricerca unica: il config.json
  dell'app (da cui vengono carta e colori dei layer) il plugin lo chiedeva all'indirizzo del browser
  (`localhost:8000`), che dal container WordPress non risponde; la mappa ripiegava su OpenStreetMap e la risposta
  vuota restava in cache un'ora. Ora passa da `wmwp_server_url()` come indice e dettagli. Su dev, UAT e produzione il
  server raggiunge lo shard con lo stesso indirizzo del browser, quindi lì il problema non c'era. Test in
  `tests/php/map-tiles.php`.

## Allineamento alle pagine statiche (Rubens, 12/11)

Il dettaglio usa la larghezza intera e il rientro delle pagine (`--sase-rientro-contenuto`), la testata ha le altezze
di `.forestas-testata` (dal tema Drupal: fra 500 e 600px su desktop, 50% della finestra su tablet, alta quanto il
contenuto sotto 768px) e la riga dei pulsanti è `.forestas-azioni` con i `.forestas-pulsante` del sito, chiari con il
bordo; Stampa e Segnala grigi perché non attivi. Il primario blu del wireframe per «Scarica GPX» non si usa.
- **Credits della foto in testata** (Rubens, 12/11): lo shard li dà in `feature_image.author` e `credits` (oggi
  compilati su poche foto, es. Cammino dei Beati). `forestas_credits_immagine()` accetta ora anche
  `[ 'autore', 'credits', 'chiave' ]` oltre all'id di un allegato: la testata del dettaglio mostra lo stesso pulsante
  «Credits foto» delle pagine statiche, e niente pulsante se la foto non ha né autore né credits.
- **Correlati con «Mostra tutti»** (Rubens, 12/11, al posto del carosello chiesto nella richiesta del cliente: più
  semplice da rendere accessibile, schema *disclosure* delle WAI-ARIA APG): le prime 3 card di POI e percorsi correlati,
  poi «Mostra tutti (N)» / «Mostra meno» con `aria-expanded`; aprendo, il focus va sulla prima card nuova; senza
  JavaScript si vedono tutte.
- **Galleria con «Mostra tutti»** (Rubens, 12/11): stesse regole dei correlati, prime 3 foto; la lightbox sfoglia
  comunque tutte le foto, anche quelle nascoste. Il pulsante è `forestas_dettaglio_mostra_tutti()` (inc/dettaglio.php),
  lo stesso dei correlati.
- **12/11, dettaglio del POI** (Alessandro, oc:8959, fc1fe4d): `api/ec/poi/{id}` ha `name`, `taxonomies` e
  `forestas` con `code`, `directions`, `attachments`, `videos`, `organizations`, `related_pois` e `nearby_tracks` (card
  con `distance_from_poi` in metri, al massimo 10, e `nearby_tracks_count`). Il child mostra i percorsi vicini in card
  («a 49 m»), le prime 3 e «Mostra tutti», con la nota «I 10 percorsi più vicini su 11 entro 1 km»; «Come arrivare» da
  `directions`; gli enti del POI in «Il punto in breve». Card e griglia sono ora `forestas_dettaglio_card()` e
  `forestas_dettaglio_griglia()`, comuni a tracce e POI. I POI correlati delle tracce si calcolano per vicinanza (entro
  1 km). Il fix fac57345 del package (download GPX/KML) fa rispondere 200 a `…/download/<id>.gpx`.

- **Niente nota sotto le azioni** (Rubens, 12/11): tolta la riga «Stampa e segnalazioni non sono ancora
  disponibili.» (nel POI «La stampa non è ancora disponibile.»), che si capisce già dai pulsanti disattivati. I
  pulsanti restano con `aria-disabled="true"`, che il lettore di schermo annuncia come non disponibili.

## Consulenza SEO (Fable, 12/11)

Un agente sul modello Fable ha controllato la SEO delle pagine dinamiche. Decisioni di Rubens:

- **Pagine inglesi con il testo italiano** (traccia senza traduzione): si lascia così. È un problema dei dati, c'era
  anche con il sito attuale.
- **Slug dei doppioni con l'id** (`-<id>`): l'id cambia con il reset dello shard e l'indirizzo con lui. Va in un
  ticket a parte, sull'unicità stabile degli slug (per esempio con `out_source_feature_id`).
- **Lettura del JSON a ogni richiesta**: ora c'è una cache breve di 60 secondi nel plugin (`wmwp_detail_cache_ttl`, 0
  la spegne). Una modifica nello shard si vede entro un minuto; gli errori non vanno in cache; un JSON in cache che
  non torna più con lo slug (rinomina) si rilegge subito dallo shard.
- **Il dettaglio della traccia si legge dal file su AWS** (Rubens: in produzione deve leggere i file AWS, come il
  frontend). `<awsApi>/tracks/<id>.json` è lo stesso JSON di `api/ec/track/<id>`, che lo shard prende da lì: verificato
  sul dev, 227.013 byte tutti e due per la traccia 680, con `properties.forestas` dentro. Il file ha `ETag` e
  `Last-Modified` su S3 (produzione, esempio `wmfe.s3.eu-central-1.amazonaws.com/camminiditalia/tracks/702.json`) e su
  MinIO (dev e UAT): con `If-None-Match` o `If-Modified-Since` risponde 304 con 0 byte, senza passare da Laravel. Il
  plugin tiene JSON ed ETag in cache: per 60 secondi non chiama niente, poi fa la richiesta condizionale. Se il file
  manca (traccia appena creata) legge `api/ec/track/<id>`. I POI restano sull'API dello shard: un file per POI non
  esiste (`wmfe/forestasdev/pois/1.json` dà 404), il dettaglio lo costruisce `EcPoiDetailController` a ogni chiamata.
  Il plugin non distingue gli ambienti (Rubens): usa l'`awsApi` che `environment.ts` dà per lo shard, che sia S3 o il
  MinIO dietro il dominio dello shard (dev e UAT), con la stessa logica.
- **Peso della cache nel database**: il JSON resta un giorno (transient in `wp_options`, il sito non ha una cache a
  oggetti), una riga per elemento visitato. Il massimo è la somma dei dettagli: i file delle tracce vanno da 11 a
  222 KB (campione di 10 tracce), quindi qualche decina di MB se un crawler apre tutte le pagine in un giorno. Le righe
  scadute le toglie il cron giornaliero di WordPress.
- Restano proposte, non chieste: `Cache-Control` sui 404 delle pagine dinamiche, parole attaccate nella descrizione
  («metri.Inizia»), separatore del titolo («–» contro il «|» del sito), base maiuscola che dà 404, card Twitter e
  `og:image:alt`, JSON-LD, `lastmod` nella sitemap, parametri `?wmwp_detail` accettati su ogni indirizzo.

## Accessibilità come la Tavola 3 (Rubens, 13/11)

Rubens: «deve essere identico» al wireframe. Prima la colonna «Accessibilità» degli indicatori mostrava la classe di
inclusività A1–A6, e il blocco «Sentiero per tutti» mancava. Ora, dal markup e dal CSS della Tavola 3 (note 10, 11, 12):

- **Colonna Accessibilità**: il grado AT · AE · AEE (`inclusivity-difficulty`, tre tacche con le sigle sotto) e, a
  destra, le icone motoria, sensoriale e psichica cognitiva (`inclusivity-disabilities`): verdi con bordo continuo se
  sì, grigie e tratteggiate se no, «: sì» / «: non certificato» per lo screen reader. Senza grado: «Non classificato».
- **«Sentiero per tutti»**: compare quando il sentiero ha la classe (`forestas.inclusivity.class`, che lo shard calcola
  solo con tutti e tre i punteggi), unito alla colonna a forma di L rovesciata. Badge della classe, tacche della classe
  (A1 sei, A6 una), nome e frase; tabella con «Fruibilità in autonomia», «Disponibilità di assistenza» e i tre
  punteggi riproporzionati su sei tacche (un sesto dell'intervallo raggiunto per intero, il numero non si mostra).
  Funzioni `forestas_dettaglio_accessibilita()` e `forestas_dettaglio_inclusivita()`, test in `tests/dettaglio.php`.
- **Legende** come popover sopra la pagina, una aperta per volta, Esc chiude e torna al pulsante; in fondo «Leggi
  l'articolo di approfondimento», il «da sapere» importato dal nodo Drupal 5701. Percorribilità con la sua legenda e
  le tacche rosse #FF4B4D dell'infografica (prima verdi).
- Provato su Seleni (C 104 A, valori inseriti in Nova in locale: 30 + 15 + 12, classe A2) e su Fassoni (C 421, AE e
  sensoriale, senza punteggi: niente blocco), in italiano e in inglese, a 1440 px e a schermo stretto.

**Aperti:**
- La frase della classe c'è nel wireframe solo per A2 («Percorsi facili/moderati, servizi buoni, digitale buono.»):
  per le altre classi la riga non compare finché Forestas non dà le frasi.
- Le soglie della legenda (Allegato H nel wireframe: A3 41–56, A4 25–40, A5 15–24, A6 12–14) non coincidono con quelle
  provvisorie dello shard (`config/forestas.php`: A3 43–56, A4 29–42, A5 15–28, A6 0–14): un sentiero con 42 punti
  esce A4 ma la legenda lo mette in A3. Rubens, 13/11: si lascia così, se servirà si modificherà; nessun messaggio ad
  Alessandro.
- Le stelle delle dotazioni (★ – ★★★) sono solo nella legenda: nello shard il dato non c'è.
- In locale un salvataggio da Nova non riscrive il file della traccia: la catena si ferma al grafico altimetrico
  (`UpdateEcTrackGenerateElevationChartImage`, `ERR_MODULE_NOT_FOUND` di Node) prima di `UpdateEcTrackAwsJob`; per
  provare si lancia a mano `UpdateEcTrackAwsJob::dispatchSync()` sulla traccia.

## Icone dal set dello shard (Rubens, 13/11)

Le icone motoria, sensoriale e psichica cognitiva della colonna Accessibilità non sono più disegnate nel template:
vengono dal set IcoMoon dello shard (`<awsApi>/json/icons.json`, lo stesso di Nova e dell'app), come le altre icone
dei termini. Rubens: si usano i disegni del WordPress (quelli del wireframe), adattati e caricati nel set da
`/icons-upload`, senza modificare `wm-package`.

- **Le tre icone nuove**: `txn-disability-motor`, `txn-disability-sensory`, `txn-disability-cognitive`. I disegni a
  tratto (viewBox 24, tratto 1,8) sono convertiti in forme piene sulla griglia 1024 da `icone/contorni.py`: ogni
  segmento un rettangolo, cerchi alle estremità e agli angoli, anelli per i cerchi a tratto, tutto in un solo path con
  la regola nonzero. Confrontate con gli originali a 200 e a 28 px: uguali. `icone/aggiungi-al-set.py` le aggiunge al
  set attuale (595 icone): il file caricato sostituisce il set, quindi si parte sempre da quello dello shard. Nomi
  nuovi e non `txn-mobility-disability`/`txn-cognitive-disability`, che sono del set condiviso e cambierebbero negli
  altri progetti.
- **Associazione ai termini** di «Disabilità accolte» (tassonomia `inclusivity-disabilities`): `physical` →
  `txn-disability-motor`, `sensory` → `txn-disability-sensory` (prima nessuna), `cognitive` →
  `txn-disability-cognitive`. In locale fatta nel database (equivale al campo icona in Nova); i termini li crea anche
  `ConfigureTaxonomyTermsCommand`, che ha ancora le icone vecchie: da dire ad Alessandro.
- **Plugin**: `wmwp_icon_svg( $nome )` (`includes/icons.php`) restituisce l'SVG dal set; il set si scarica solo per un
  nome mai chiesto, gli SVG dei nomi già chiesti restano in cache un giorno. Test in `tests/php/icons.php`.
- **Child**: il nome dell'icona viene da `icon_name` del termine nel JSON della traccia; le tipologie spente nel JSON
  non ci sono, e per loro vale il nome della nuova icona di Forestas. Se il set non ha l'icona, resta il disegno a
  tratto di prima. Le icone del set sono piene (`fill`), verdi se sì e grigie se no.
- **In locale** il set non c'era sul MinIO: caricato il set del dev (identico a `wm-package/resources/defaults/icons.json`)
  con le tre icone, nello stesso percorso di `/icons-upload` (`forestas/json/icons.json`). File riscritti di Seleni e
  Fassoni per avere `icon_name` nuovo.

## Avviso del sentiero (Rubens, 13/11)

- **Colore come il wireframe**: la fascia in cima al dettaglio era grigia (#F5F5F5, etichetta #666, «Avvisi»). Ora è
  il componente della fascia avvisi della home (Tavola 3, avviso in cima; `implementazione-wordpress.md` di oc:8783,
  «Fascia degli avvisi» e «Avviso»): riga #FDECEC, etichetta «Avviso» bianca su #B70827 (6,82:1) con il triangolo
  SVG `aria-hidden`, testo #1A1A1A, posto per la data in #595959 (`.wm-avviso__dt`). Child 1.14.1.
- **Il dato non esiste ancora**: nello shard non c'è un modello per gli avvisi. Il wireframe dice che la fascia c'è solo
  se il sentiero ha un avviso attivo; oggi resta il segnaposto «Dato non ancora disponibile».
- **Cosa dicono le call** (ricerca nelle trascrizioni del 13/11, con NotebookLM):
  - *Call con il cliente del 16/10* (Schirru, Forestas): se un sentiero ha un problema segnalato, la scheda lo mostra in
    alto «con lo stesso stile degli avvisi» della home (deciso). Un avviso riguarda un solo sentiero; se tocca più
    sentieri si duplica con un'interfaccia apposta (proposto, non contraddetto). L'operatore lo inserisce una volta sola
    nel backend del catasto, e da lì va su app e sito (deciso). Serve una pagina con l'elenco di tutti gli avvisi,
    semplice e ordinabile per data (deciso). Schirru proponeva un oggetto «interruzione» con quattro dati sul modello
    del catasto CAI: stato, data, chi lo inserisce, nota (proposto). Le news WordPress con il tag «avvisi» sono state
    scartate nella stessa call.
  - *Scrum interno del 29/10* (Piccioli, Bonfanti, senza il cliente): un nuovo campo testuale «avviso» sulle EC
    (`EcTrack`) nel wm-package, testo libero; se è valorizzato la scheda mostra la riga rossa «Avviso» con il testo
    (deciso). Separato da `not_accessible` / `not_accessible_message` (il flag e il messaggio che esistono già in
    `wm-package/src/Nova/EcTrack.php`) e dalla percorribilità, che ha grado, data, chi l'ha inserita e testo.
  - *Scrum del 06/11*: gli avvisi su Drupal non esistono, sono un dato nuovo, «da vedere a parte».
  - **Mai discussi**: scadenza o data di fine (la specifica `implementazione-wordpress.md` di oc:8783 parla di
    «scadenza», la nota della Tavola 10 dice che non è prevista), livelli di gravità, avvisi per area o per tutto il sito,
    approvazione.
  - **Contraddizioni aperte**: il 16/10 un oggetto con quattro dati, il 29/10 un solo campo di testo; ma la fascia del
    wireframe e l'elenco «ordinabile per data» chiedono almeno la data di inserimento, che il campo del 29/10 non ha.
    La fascia della home e l'elenco hanno bisogno di un'API che restituisca tutti gli avvisi attivi, non solo il
    dettaglio di una traccia. Non c'è una call con il cliente che approvi la versione del 29/10.
  - **Non coperti dalla ricerca**: gli scrum dal 18/09 al 28/10, salvo quelli citati.
- **Diventa un ticket a parte**, da creare a fine lavori di questo (Rubens, 13/11): qui resta il segnaposto con lo stile
  del wireframe.

## Percorribilità (Rubens, 13/11)

Nel dettaglio indicatore e fascia della percorribilità restano «Dato non ancora disponibile». **Diventa un ticket a parte,
da creare a fine lavori di questo** (Rubens, 13/11). Non si ricava dallo stato di validazione come il filtro della
ricerca (Rubens, 13/11: «no»).

Quello che sappiamo, per il ticket (fonti in `docs/features/8952-…/notes.md`, «Filtro «Percorribilità» dallo stato di
validazione» e «Da fare dopo questo ticket»):
- **Filtro della ricerca** (oc:8952, scrum dell'11/11, Giuseppe): ricavato da `terms.validation-status` con le voci del
  semaforo della home (Percorribili 4–6, In revisione o parzialmente percorribili 2–3, Non percorribili 1; «Non
  verificato» fuori). Regola di Forestas nelle «Osservazioni finali a CANVAS CLAUDE fase 2» (Saba): il valore di Drupal
  «sarà riversato sia su percorribilità che su validazione», «tal quale».
- **Dato vero, deciso e non sviluppato**: nel wm-package sul modello `HikingRoute` di osm2cai2 (tag 692): scala 0–5 più
  «percorribile ma temporaneamente interrotto», data, autore, nota (`issues_status`, `issues_last_update`,
  `issues_user_id`, `issues_description`, `issues_chronology`). Scrum del 29/10 (Piccioli): «grado di percorribilità
  che è un numero e un label, la data, chi l'ha inserita, il testo»; inserimento multitenant, l'autore è chi è loggato.
  Ne parlano oc:8932 (fuori scope), oc:8489 («ticket da aprire»), oc:8827 (filtri separati chiesti dal cliente il 28/10)
  e `decisioni.md` di oc:8783 (scala 0–5 e semaforo). Call tecnica del 14/09 (Saba): campo percorribilità «che sarà
  vuoto e noi ci riempiremo a mano»; call del 16/10 (Schirru): stato, data, chi, nota sul modello del catasto CAI.
- **Nel dettaglio, quando il dato arriva**: indicatore a tacche 0–5 rosse con la legenda già pronta, e la fascia in alto
  della Tavola 3 (nota 7): stato, «stato aggiornato il …», «Inserito da …», nota; colori per stato (percorribile verde
  #EEF6F0 con titolo #0B6624, parzialmente su #FFF4DB con #6B4A00, non percorribile su #FDECEC con #9B0720). Nella
  ricerca il filtro cambia solo campo e valori.

## Stampa (ricerca nelle call e nei documenti, 13/11)

Nel dettaglio «Stampa» è un pulsante visibile e disattivato (`aria-disabled`). **Diventa un ticket a parte, da creare a
fine lavori di questo** (Rubens, 13/11). Cosa sappiamo:

- **Wireframe** (`decisioni.md` di oc:8783): pulsante nella riga di azioni, «miglioria accettata» (call 17/09 e 16/10);
  cosa produca è aperto: «versione di stampa della pagina o scheda PDF». Nella ricerca (Tavola 2): «Stampa la mappa di
  insieme» e «Scarica le schede PDF» per più sentieri, con la nota che la stampa della mappa richiede una cartografia
  adatta.
- **Call 17/09** (con il cliente): Saba chiede cosa produca la stampa; Piccioli propone «un downgrade dell'HTML» oppure
  «una scheda PDF del sentiero collegata magari alla web app» (proposto).
- **Call 16/10** (Schirru): il requisito originale era «stampami un PDF in buona risoluzione, una mappina»; il PDF «sulla
  scheda singola e basta», scaricarli tutti insieme pesa troppo (obiezione); poi i testi dei pulsanti della ricerca
  diventano «Stampa la mappa di insieme» e «Scarica le schede PDF».
- **Scrum interno del 29/10** (Piccioli, Bonfanti, Rubens; cliente assente), **deciso**: «Stampa» è il PDF della scheda,
  per il sentiero e anche per il POI («non esiste proprio, va fatto»); generato lato server nel wm-package, asincrono
  («li produce già tutti»), partendo da una versione standard; design di Piccioli, validazione di Bonfanti, poi commenti
  del cliente. Priorità bassa: «se ce la facciamo alla fine dell'anno»; «il PDF possiamo spengerlo». Bonfanti: la
  stampa PDF c'è già nell'app (Rubens: «ce l'hai nell'app»).
- **Capitolato** (CIG BBD6B108DB): WEBSITE-18, versione stampabile o PDF della scheda con nome, difficoltà, lunghezza,
  dislivello, descrizione, traccia, immagini, più un «carrello» di sentieri da stampare o esportare in blocco;
  WEBSITE-19, versione sintetica con mappa; test minimo n. 8 «funzioni PDF/stampa/carrello». Bozze: scheda con roadbook
  e mappa a colori ad alta risoluzione con scala selezionabile (WEBSITE-23), mappe a 300 dpi (WEBSITE-21); esempio
  citato dal cliente: le schede della SAT (`sentieri.sat.tn.it/webapps/schede-sentieri`).
- **Codice**: nel package non c'è un PDF della scheda. Resta la rotta `api/ec/track/pdf/{id}`
  (`getFeatureCollectionForTrackPdf`), la geometria per una mappa da stampare, residuo del flusso GeoHub (il link
  «Print PDF» nel modello è commentato). Nel JSON della traccia nessun campo punta a un PDF.
- **Mai discussi**: formato (A4, una pagina), lingue, QR code, contenuto preciso; cosa faccia il pulsante del sito (si
  presume che apra il PDF dello shard). Il «carrello» del capitolato non è mai ripreso nelle call.
- **Copertura della ricerca**: non lette le call prima del 24/06 e alcune di settembre e ottobre (elenco nel resoconto
  dell'agente); l'agente ha creato i notebook «scrum 2026-10-14» e «scrum 2026-10-09» e aggiunto una call al notebook
  del 21/10.

## Segnala e pagina «Segnalazioni» (ricerca nelle call e nei documenti, 13/11)

Nel dettaglio «Segnala» è un pulsante visibile e disattivato; la voce «Segnalazioni» della barra in alto
(`config/menu.json`) punta a `#`. Cosa sappiamo:

- **Wireframe** (`decisioni.md` e `implementazione-wordpress.md` di oc:8783): «Segnala» apre sotto la riga il modulo
  «Segnalazione» con i campi del Webform `segnalazione` di Drupal (provenienza, nome ed email facoltativi, testo, foto o
  file multipli, privacy obbligatoria), il sentiero è un campo nascosto; stesso componente del modulo delle recensioni;
  solo per sentiero, itinerario e cammino, non per il POI. Da definire con Forestas: chi riceve e legge, email, se
  diventano avvisi, privacy e conservazione, dove si salvano.
- **Sito attuale**: «Segnalazioni» apre `/form/segnalazione`, un modulo generico non legato a un sentiero.
- **Call 17/09** (Saba): «stampa, condividi, segnala sul sito com'erano», migliorie accolte «in un contesto più
  conservativo». **Call 16/10**: Schirru elenca la segnalazione fra le azioni della scheda; la voce «segnalazioni» della
  barra in alto è solo nominata. Il «triangolino d'allarme sulla mappa» del 16/10 è l'avviso dell'operatore, non la
  segnalazione dell'utente (vedi «Avviso del sentiero»).
- **Scrum interno del 29/10**, **deciso**: segnalazioni e recensioni «in prima istanza su WordPress» (Bonfanti: «perché
  ce l'ha in mano Rubens ed è autonomo»; Piccioli: «Sì, anche le segnalazioni»), con un tag in backlog per portarle poi
  su Laravel («ricordiamoci che l'abbiamo implementata così su WordPress, ma ci piacerebbe espandere»). «Segnala» usa il
  form copia di quello di Drupal; Bonfanti: «Rubens, ogni segnalazione tracciala nel server», oltre all'email. Criterio di
  Bonfanti: su Laravel solo se le segnalazioni dovranno arrivare anche da app e web app. Recensioni: form uguale a
  Drupal, stelle in alto e dettaglio in fondo (approvato il 02/11), dati da importare da Drupal.
- **Come funziona oggi il form di Drupal** (verificato il 13/11 sul Drupal locale `sardegnasentieri-locale`, config
  `config/sync/webform.webform.segnalazione.yml` e conteggi sul database, senza leggere i contenuti):
  - campi: «Nome del sentiero» (`webform_entity_select` sui nodi di tipo `sentiero`, ordinati per titolo, precompilabile),
    comune o zona di provenienza, nome o nickname, email, testo, «ID sentiero» nascosto, file multipli (immagini,
    documenti, GPX/KML, video, archivi), privacy obbligatoria;
  - nella scheda del sentiero il pulsante «Segnala un problema» è solo un link a `/form/segnalazione?nome_del_sentiero=<nid>`
    (`node--sentiero--full.html.twig`), che precompila la select;
  - **nessuna email**: `handlers: {}`, nessun gestore di invio. **Le segnalazioni si salvano nel database di Drupal**
    (`results_disabled: false`, `purge: none`, conservate per sempre): tabelle `webform_submission` e
    `webform_submission_data`, allegati in `private://webform/segnalazione/`;
  - le legge solo chi ha il ruolo `administrator` (nessun altro ruolo ha permessi sui webform), dai risultati del webform
    nel pannello; nessuno viene avvisato di una segnalazione nuova;
  - quante: 79 segnalazioni dal 22/03/2023 all'08/10/2026 (6 nel 2023, 38 nel 2024, 21 nel 2025, 14 nel 2026), 62
    con il sentiero scelto, 35 file allegati. Il form delle recensioni (`valutazione_sentiero`) funziona allo stesso
    modo: nessuna email, 520 valutazioni salvate dal 27/03/2023 al 02/11/2026;
  - il sentiero scelto è il `nid` del nodo Drupal, lo stesso che lo shard tiene in `out_source_feature_id` delle tracce:
    l'elenco dei sentieri per la select c'è già nello shard.
- **Contraddizioni aperte**: il 29/10 Rubens diceva «manda solo la mail», Piccioli che salvava anche: aveva ragione
  Piccioli, e il capitolato («non produce report né invia mail») sull'email; il 29/10 si chiede di analizzare il DB Drupal e importare le segnalazioni se ci
  sono, ma `docs/knowledge/copia-database-drupal.md` vieta di importare `webform_submission*` perché contiene dati
  personali.
- **Capitolato**: WEBSITE-8 (e), form-mail con invio a una casella del back office ed esportazione tabellare, evoluzione di
  `/form/segnalazione`; WEBSITE-14, segnalazioni di interruzioni su mappa con notifica agli operatori e stati (segnalato,
  in verifica, confermato, risolto), flusso da definire in progettazione esecutiva. Bozze: CATASTO-29 (gestione con presa
  in carico e risposta), FRONTEND-28 (titolo, foto, allegati, tipologia: danno a segnaletica, sentiero interrotto, frana,
  pericolo, GPX diverso dal campo; riepilogo in back office).
- **Mai discussi nelle call**: campi del form, cosa diventa la pagina «Segnalazioni», chi riceve l'email, moderazione e
  ruoli (Validator, enti), ritorno sul sito o sulla mappa, tempi.
- **Ticket unico con le recensioni** (Rubens, 13/11): stesso flusso e stesso componente; la differenza è che le
  recensioni approvate si mostrano, le segnalazioni no. Strada WordPress (29/10) fattibile con le pagine dinamiche: il
  modulo sta nel template e invia a WordPress, le recensioni si leggono dal database di WordPress per chiave della
  traccia. Strada shard: modelli nuovi in forestas senza chiave esterna su `ec_tracks` (il reset notturno la svuota a
  cascata), API pubblica di scrittura con antispam, gestione in Nova (ruolo Validator), legame con gli avvisi.
- **Chiave stabile della traccia** (verificato il 13/11): il sito conosce solo l'id dello shard, che cambia a ogni reset
  di dev e UAT. Il `nid` di Drupal (`out_source_feature_id`) c'è nello shard ma non esce né nel JSON della traccia né
  nella lista Elasticsearch (0 tracce su 769 nell'indice; i POI sì, da `pois.geojson`). Serve per agganciare
  segnalazioni e recensioni e per importare quelle di Drupal, che indicano il sentiero con il `nid`; serve anche al
  ticket dell'unicità degli slug. Richiesta ad Alessandro il 13/11.
- **Nome del `nid` di Drupal** (13/11): Alessandro uniforma tracce e POI su `sardegnasentieri_id` (le tracce lo avevano
  già, i POI usavano `out_source_feature_id`, che nel package è l'id della tabella delle sorgenti esterne OSF). Uscirà
  nel dettaglio della traccia, nella lista Elasticsearch, in `pois.geojson` e nel dettaglio del POI. Quando arriva: il
  plugin legge il nome della chiave da un filtro (oggi `out_source_feature_id` fisso in `detail-index.php`, per i POI
  senza nome) e il child gli passa `sardegnasentieri_id`; il child aggancia segnalazioni e recensioni a quel campo.
  Arrivato e in uso dal 13/11 (filtro `wmwp_detail_source`, vedi «in uso nel sito» sopra); il suffisso stabile dei
  doppioni copre una parte del ticket dell'unicità degli slug.

## Ticket da creare: segnalazioni e recensioni (Rubens, 13/11)

Da creare a fine lavori di oc:8969, un ticket solo per tutte e due (Rubens, 13/11). Raccoglie l'analisi del 13/11: le
fonti di dettaglio (call, capitolato, wireframe, Drupal) sono in «Segnala e pagina «Segnalazioni»».

**Perché un ticket solo.** Stesso flusso (modulo nella scheda, salvataggio, notifica, gestione, import da Drupal), stesso
componente nel wireframe (`implementazione-wordpress.md` di oc:8783, «Moduli: recensione e segnalazione»), decise insieme
nello scrum del 29/10. Differenza: le recensioni approvate si mostrano sulla scheda (stelle in alto e dettaglio in fondo,
approvato dal cliente il 02/11), le segnalazioni restano private.

**Com'è oggi su Drupal** (verificato sul Drupal locale):
- `/form/segnalazione`: select «Nome del sentiero» (nodi `sentiero`), provenienza, nome, email, testo, file multipli,
  privacy; dalla scheda «Segnala un problema» apre lo stesso form con il sentiero già scelto
  (`?nome_del_sentiero=<nid>`). `valutazione_sentiero`: provenienza, nome, email, testo, quattro voti a stelle, privacy.
- Nessuna email (`handlers: {}`), tutto salvato nel database di Drupal e mai cancellato; lo legge solo il ruolo
  `administrator`. 79 segnalazioni (2023–2026, 62 con il sentiero, 35 allegati) e 520 recensioni.
- La voce «Segnalazioni» della barra in alto apre lo stesso form, senza sentiero; nel nuovo WordPress punta a `#`.

**Strada 1, WordPress** (decisa il 29/10 «in prima istanza»; consigliata):
- Due tipi di contenuto non pubblici, segnalazione e recensione, con i campi dei Webform di Drupal.
- Agganciati alla traccia con il `nid` di Drupal (`out_source_feature_id`), non con l'id dello shard, che su dev e UAT
  cambia a ogni reset notturno.
- Funziona con le pagine dinamiche: il modulo sta nel template della scheda e invia a WordPress; le recensioni approvate
  e la media si leggono dal database di WordPress per chiave della traccia; la pagina «Segnalazioni» generica ha la select
  dei sentieri dall'indice dei dettagli del plugin; dal pannello ogni segnalazione linka la sua scheda
  (`wmwp_detail_url()`).
- Email a una casella di Forestas (da decidere); le recensioni in attesa finché non sono approvate; allegati in una
  cartella protetta (in WordPress i file caricati sono pubblici per default); antispam; conferma dopo l'invio come da
  wireframe.
- Pro: veloce, autonomo sul sito, nessuna API di scrittura pubblica sullo shard.
- Contro: app e ricerca non le vedono (né le stelle); una segnalazione non diventa un avviso dal backend; andrà migrata
  (tag in backlog deciso il 29/10). Per la migrazione: chiave = `nid`, campi uguali ai Webform, tutto in due tipi di
  contenuto dedicati, così il passaggio allo shard è un import.

**Strada 2, shard** (evoluzione):
- Modelli nuovi in forestas (non gli UGC del package, che sono per gli utenti registrati dell'app), agganciati alla
  traccia con un dato stabile e senza chiave esterna su `ec_tracks`: il reset notturno di dev e UAT la svuota a cascata.
- Endpoint pubblico di scrittura per il sito con antispam e caricamento dei file; recensioni approvate e media nel
  dettaglio della traccia senza dati personali, visibili anche da app e ricerca.
- Gestione in Nova (ruolo Validator, «validazione UGC»), email agli operatori, stati del capitolato (segnalato, in
  verifica, confermato, risolto; WEBSITE-14), azione che trasforma una segnalazione in avviso (ticket degli avvisi).
- Pro: una sola fonte per sito e app, in linea con il catasto (CATASTO-29, WEBSITE-14). Contro: molto più lavoro su
  backend e sito, a ridosso della consegna di dicembre; un'API pubblica di scrittura da proteggere.

**Dato che serve a tutte e due le strade:** il `nid` di Drupal delle tracce (`out_source_feature_id`) nel JSON della
traccia e nella lista Elasticsearch: oggi non arriva al sito (0 tracce su 769; i POI sì). Richiesto ad Alessandro il
13/11; serve anche per importare le segnalazioni e le recensioni di Drupal e al ticket dell'unicità degli slug.

**Da decidere nel ticket:**
- import delle 79 segnalazioni e 520 recensioni di Drupal: contengono dati personali, e
  `docs/knowledge/copia-database-drupal.md` vieta di importare `webform_submission*`; il 29/10 si è detto di importarle
  se ci sono. Servono una decisione su privacy e conservazione e il testo della privacy;
- chi riceve le email e chi gestisce e approva (ruoli, enti);
- cosa diventa la pagina «Segnalazioni» (form generico con la select, come oggi, o altro);
- se e come una segnalazione diventa un avviso;
- recensioni anche per itinerari e cammini (wireframe sì, Drupal solo sentieri), non per i POI;
- campi della segnalazione: wireframe e Drupal coincidono; la bozza del capitolato (FRONTEND-28) chiede anche titolo e
  tipologia (danno a segnaletica, sentiero interrotto, frana, pericolo, GPX diverso dal campo) e un riepilogo nel back
  office.

## Mappa del dettaglio come le tavole (Rubens, 13/11)

Rubens: si fa in questo ticket, fa parte del dettaglio. Prima la mappa mostrava solo la traccia (o il solo punto del
POI); il wireframe (Tavola 3, `.sh-aside` e `.map-leg`; Tavola 7) vuole anche i vicini:

- **Sentiero, itinerario, cammino**: la traccia della scheda nel colore del suo tipo, 4 px; i percorsi collegati
  (`forestas.nearby_tracks`) tratteggiati #5B7A99, 2,5 px, sotto e fuori dall'inquadratura; i primi tre punti di
  interesse correlati (`related_pois`, con la posizione) come cerchi magenta #B7127F numerati. Sotto la mappa la
  legenda «C 104 A · sentiero della scheda», «Sentieri collegati: …» (o «Percorsi collegati» se non sono tutti
  sentieri), i tre POI numerati, e «Vedi sulla mappa interattiva» (pagina `mappa-interattiva`).
- **POI**: il punto nero e i percorsi vicini nel colore del loro tipo (sentieri #DE1B0D, itinerari #58810B, cammini
  #1A416F), 3 px; legenda dei tipi presenti. Il wireframe dice «entro 5 km»: l'API dà quelli entro 1 km, al massimo 10,
  gli stessi della sezione «Percorsi vicini».
- Al clic su un percorso collegato o su un POI si apre la sua scheda; al passaggio del mouse il nome.
- **Plugin**: `wmwp_map()` (`includes/map.php`), `wmWpTrackMap.detail()` (`assets/js/track-map.js`), il caso `detail`
  in `assets/js/map-embed.js`, e i numeri sui punti in `map/src/index.js` (un layer sopra quello del componente, che
  scrive testi solo lungo le linee): `wm-package` non è toccato, `assets/dist` ricompilato con Node 20. Test:
  `tests/js/track-map.test.js`, `tests/php/map-detail.php`.
- **Child**: `forestas_dettaglio_mappa()` (tracce, punti, legenda dal JSON) e `forestas_dettaglio_mappa_html()`
  (mappa, legenda, pulsante), test in `tests/dettaglio.php`; child 1.15.0.
- Da sapere: la legenda dei collegati usa il codice del percorso e, se manca, il nome: su Seleni un itinerario senza
  codice allunga la riga («L'Ogliastra da Sud a Nord - …»); alcuni `ref` dello shard sono già nella forma del registro
  (`Z-NU-C-105`).
- **Su mobile, subito dopo i pulsanti** (Rubens, 13/11: «solo per il mobile»). Sotto 768px la mappa, con legenda e «Vedi
  sulla mappa interattiva», va subito dopo Scarica GPX, Stampa, Condividi e Segnala, non in fondo alla pagina. Da 768px
  in su resta dov'era: sticky a destra da 1025px, dopo il testo fra 768 e 1024px. La sposta `assets/js/dettaglio.js`
  nel DOM, non solo a vista, così l'ordine del Tab e dello screen reader resta quello che si vede. Il template non
  cambia: senza JavaScript la mappa resta in fondo, e allargando la finestra torna nella colonna. Classe
  `.wm-aside--su` per il rientro, child 1.16.5. Verificato su sentiero 344 e POI 401 a 390px e a 1440px.

## Data di accatastamento (verificato il 13/11)

Nella tabella «Catasto» della scheda la riga «Data di accatastamento» è sempre «—»: il dato non esiste da nessuna parte.
- **Wireframe** (Tavola 3, nota sui dati catastali): «la data di accatastamento oggi non esiste in nessuna banca dati e
  la deve fornire Forestas, per questo c'è un trattino».
- **Drupal**: il sentiero ha solo «Data rilievo» (`field_data_rilievo`, che nello shard è `forestas.survey_date`, la riga
  «Data del rilievo»); nessun campo di accatastamento.
- **Shard**: `trail_registry_codes` ed eventi (`trail_registry_code_events`) hanno solo `created_at`, cioè quando il
  codice è entrato nel nostro registro (con l'import, e su dev e UAT di nuovo a ogni reset notturno), non la data
  dell'accatastamento; `trail_applications` non ha una data di approvazione a sé.
- **Registro catastale** (foglio Google): fra le colonne che l'import legge non c'è una data (`docs/knowledge/
  registro-catastale.md` di forestas); il mirror in locale è vuoto, quindi le intestazioni complete non sono verificate.
- Per i sentieri che verranno accatastati con le istanze del SUS, la data potrà venire dall'evento di conferma del
  codice; per i sentieri esistenti la deve dare Forestas (per esempio una colonna nel registro).
- **Nessuna predisposizione nelle API** (verificato il 13/11 sul lavoro del SUS): il dettaglio della traccia
  (`ForestasTrackDetail`) ha `registry_code` e `survey_date`, nessun campo per l'accatastamento; la lettura dell'istanza
  del SUS (`ShowApplicationController` del wm-package) restituisce id, dati dichiarati, allegati, stato, DEM, codice e
  motivo del rifiuto, senza data di approvazione; l'approvazione di prova (`SusTestDecisionController`) solo id e stato;
  `trail_applications` non ha una data di approvazione. La data esiste solo implicita: l'approvazione scrive un evento
  di cambio di stato del codice (`trail_registry_code_events.created_at`).
- **Punto aperto, solo nelle note** (Rubens, 13/11, nessun messaggio ad Alessandro per ora): servirebbe un campo nel
  dettaglio della traccia (per esempio `forestas.registration_date`) con la data dell'approvazione per i sentieri
  accatastati dal SUS e il dato di Forestas per quelli esistenti; il sito lo mostrerebbe al posto del trattino.

## Accessibilità del dettaglio (Rubens, 13/11: sempre in linea con linee guida e screen reader)

Analisi del 13/11 su sentiero (Seleni), cammino (CMSB tappa 18, inglese), sentiero inglese (Monte Ferru) e POI
(Pantaleo), con un controllo eseguito nella pagina (titoli, landmark, nomi accessibili di link, pulsanti e immagini,
riferimenti ARIA, id duplicati, focalizzabili nascosti allo screen reader, contrasto di ogni testo, bersagli, lingua) e
con la tastiera (focus visibile su ogni elemento del contenuto). axe-core in locale non c'è e non è stato scaricato.

Corretti:
- **Titoli dentro i testi di Drupal** (WCAG 1.3.1): descrizione e informazioni utili portano titoli di livello qualsiasi
  (un h3 dentro «Descrizione», che è già un h3; salti da h4 a h6). `forestas_dettaglio_ricco()` li rinumera da h4,
  nell'ordine relativo e senza salti (`forestas_dettaglio_titoli()`); vale anche per «Come arrivare» e le tappe.
- **Lingua delle parti** (WCAG 3.1.2): sulla pagina inglese i testi che ripiegano sull'italiano erano letti con la
  pronuncia inglese. Ora il testo ricco che viene da un'altra lingua sta in un `div lang`, e così i titoli delle tappe
  (`span lang`). `forestas_dettaglio_testo_lingua()` dice da quale lingua viene il testo.
- **Testo alternativo della testata**: `alt` dalla didascalia della foto quando c'è, come chiede la specifica di
  oc:8783 («Testata con foto»); senza didascalia resta decorativa.
- **Mappa annunciata due volte**: la colonna (`aside`) e la mappa che contiene (`role="region"`) avevano lo stesso nome;
  ora il nome lo porta solo la mappa.
- **Pulsanti della mappa** (zoom, schermo intero) da 22 a 32 px (WCAG 2.5.8 e uso col dito), in `map/src/map.css` del
  plugin, quindi anche nella ricerca.

Verificato e già a posto: un solo `h1` e un solo `main`; landmark con nome; indicatori a tacche con `role="img"` e nome;
legende dietro la «i» con `aria-expanded`/`aria-controls` ed Esc; fisarmoniche del roadbook e «Mostra tutto» con
`aria-expanded`; tabelle con intestazioni di riga e un nome per lo screen reader (didascalia, o il titolo del gruppo
con `aria-labelledby` dal 13/11); galleria con testo alternativo;
icone delle tipologie con «: sì»/«: non certificato»; focus visibile su tutti gli elementi del contenuto; animazioni
spente con `prefers-reduced-motion`; nessun contrasto sotto soglia fuori dalla testata con foto. Il pulsante di
rotazione della mappa è `visibility: hidden` finché la mappa non si ruota, quindi Tab lo salta (il suo nome «Reset
rotation» resta in inglese: si vede solo ruotando la mappa).

Fuori da questo ticket (header di oc:8757): nel controllo con Tab, alcuni link della barra in alto risultano senza
contorno di focus al primo passaggio, da verificare in quel ticket.

## Card uniche e «Percorsi correlati» (Rubens, 13/11)

- **«Percorsi correlati»** anche sulla scheda del sentiero (prima «Sentieri correlati»): fra i correlati di un sentiero
  ci sono anche itinerari (236 schede in locale) e cammini (159). Anche la legenda della mappa dice «Percorsi
  correlati: …».
- **Una card sola nel sito**: le card di percorsi e POI del dettaglio (prima `wm-uc`, fatte a parte) e quelle della
  ricerca (template `card.php` del plugin, con un altro stile) usano ora la card dei post, `forestas-card` (la `.uc` del
  wireframe): foto in alto, etichetta con il filetto, titolo, dati sotto, ombra e passaggio del mouse uguali. Il
  filetto ha il colore del menu del tipo: sentieri, itinerari, cammini (`--sase-sezione-*`) per i percorsi, punti di
  interesse per i POI; nella ricerca anche news, eventi e da sapere con i colori delle loro card.
- Dettaglio: `forestas_dettaglio_card()` scrive `forestas-card forestas-card--immagine forestas-card--<tipo>` con il
  titolo h3 e il link che copre la card; un POI senza tipologia ha l'etichetta «Punto di interesse». Ricerca:
  `wp-package/card.php` nel child sostituisce il template del plugin con lo stesso markup e le classi `wmwp-card*` che
  il JS cerca; la card resta tutta un link (anche nel riquadro della mappa). Regole in `style.css` (colori per tipo,
  `.forestas-card[hidden]` per «Mostra tutti», card-link della ricerca), tolte quelle vecchie di `wm-uc` e
  `wmwp-card`. Child 1.16.0.
- Da sapere: l'etichetta dice il tipo al singolare (Sentiero, Itinerario, Cammino; la tipologia del POI), come nella
  ricerca; il wireframe nelle card dei correlati scrive il nome della sezione al plurale («Sentieri», «Punti di
  interesse»). In locale le foto delle card della ricerca non si vedono perché puntano al MinIO locale senza file
  (solo ambiente).
- **Segnaposto delle card senza foto, come Drupal** (13/11): Drupal mette `images/thumb-placeholder.png` del tema
  (832×536, grigio con il logo «SARDEGNA Sentieri BETA» in basso a destra) in tutti i teaser senza immagine
  (`node--sentiero--teaser.html.twig`, che POI e itinerari estendono, `node--search-result.html.twig`, news, eventi,
  da sapere). Copiato in `assets/img/thumb-placeholder.png` del child e usato come sfondo del riquadro della foto
  vuoto (`.forestas-card__media--segnaposto`, `.forestas-card__media:not(:has(img))`), allineato in basso a destra
  perché il riquadro 3:2 non tagli il logo. Nella ricerca il riquadro non ha più `wmwp-card__media`, così il JS toglie
  solo l'immagine e il segnaposto resta. Child 1.16.2.

## Mappa interattiva con il focus (13/11, fatto in questo ticket)

**Su Drupal.** Il pulsante «Vedi sulla mappa interattiva» di sentiero, itinerario e POI è un link a
`/mappa-interattiva?id=<nid>` (`node--sentiero--full.html.twig`, `node--poi--full.html.twig`). Il blocco
`MappaInterattivaBlock` (modulo custom `mappa_interattiva`) legge `id`, carica il nodo e sceglie l'iframe: per sentiero
e itinerario `https://geohub.webmapp.it/api/webapp/track/sardegna-sentieri-track/<nid>`, per il POI
`…/api/webapp/poi/sardegna-sentieri-poi/<nid>`, senza `id` `https://32.app.webmapp.it/#/map`. GeoHub risponde 302 verso la
webapp con l'elemento a fuoco: verificato il 13/11, nid 1330 → `https://32.app.webmapp.it/#/map?track=80308`, nid 4076 →
`#/map?track=88777`, POI nid 5646 → `#/map?poi=106269` (id di GeoHub, non dello shard).

**Da noi oggi.** La pagina `mappa-interattiva` ha l'iframe fisso `https://32.app.webmapp.it/#/map` (`FORESTAS_MAPPA_URL` in
`inc/pagine-statiche.php`, già con la nota «il cambio di mappa con ?id= arriverà con le loro pagine»); il pulsante del
dettaglio porta alla pagina senza parametri, quindi la mappa si apre sull'isola.

**Due strade per il ticket:**
1. *Come Drupal*: il pulsante porta a `/mappa-interattiva/?id=<nid>` (il nid arriva ora come `sardegnasentieri_id`,
   f29b4c9 di Alessandro) e lo shortcode `forestas_mappa` mette nell'iframe l'indirizzo di GeoHub per tracce o POI. Si
   fa solo nel child, ma dipende da GeoHub e dalla webapp 32, che mostrano i dati di GeoHub, non quelli dello shard.
2. *Webapp dello shard*: iframe della webapp di Forestas su maphub con `#/map?track=<id dello shard>` o `?poi=<id>` (stessi
   parametri della webapp 32, da confermare). Il sito ha già l'id dello shard nella pagina di dettaglio. Da chiarire
   prima: l'indirizzo della webapp dello shard (nel `config.json` dell'app non c'è) e se si lascia mettere in un iframe.
   È la strada coerente con il resto del sito, che legge tutto dallo shard.

Da decidere nel ticket anche quale webapp mostra la pagina «Mappa interattiva» senza parametri (oggi la 32 di GeoHub).

**Webapp per ambiente** (Rubens, 13/11): la mappa interattiva deve usare `https://1.forestasdev.webmapp.it/` su dev e
`https://1.forestasuat.webmapp.it/` su UAT; quella di produzione non c'è ancora. Verificato il 13/11: dev e UAT
rispondono 200 e non hanno `X-Frame-Options` né `Content-Security-Policy`, quindi stanno in un iframe;
`1.forestas.webmapp.it` non risponde. La regola `https://<appId>.<shard>.webmapp.it/` vale anche per altri shard
(`1.camminiditalia.webmapp.it` risponde). `environment.ts` di wm-types non ha l'indirizzo della webapp, ma ha
`redirects`: domini propri → `{ shardName, appId }`, cioè le webapp con un dominio loro.

**Va nel plugin, non nel child**: è un dato di ogni shard, e il plugin sa già shard e app dalle sue impostazioni (su dev
`forestasdev`, su UAT `forestasuat`, in produzione `forestas`), quindi sceglie da solo la webapp giusta. Proposta:
`wmwp_webapp_url()` restituisce, nell'ordine, l'indirizzo scritto nelle impostazioni del plugin o dal filtro
`wmwp_webapp_url` (per una produzione con un indirizzo diverso), il dominio proprio da `redirects` di `environment.ts` se
lo shard e l'app ne hanno uno, altrimenti `https://<appId>.<shard>.webmapp.it/`; `wmwp_webapp_url( 'track', $id )` e
`( 'poi', $id )` aggiungono `#/map?track=<id>` o `?poi=<id>` con l'id dello shard (parametri verificati sulla webapp 32
di GeoHub, da confermare su quelle dello shard). Il child usa la funzione nello shortcode della pagina «Mappa
interattiva» (al posto di `FORESTAS_MAPPA_URL`) e nel pulsante del dettaglio (`/mappa-interattiva/?track=<id>` o
`?poi=<id>`).

**Fatto in oc:8969** (Rubens, 13/11: «si fallo in questo ticket»). Verificato prima sulla webapp di dev che
`https://1.forestasdev.webmapp.it/#/map?track=312` apre il percorso con la sua scheda e `#/map?poi=7` il POI.
- **Plugin**: `wmwp_webapp_url( $tipo = '', $id = 0 )` in `includes/settings.php`, con il campo «Webapp» nelle impostazioni
  (opzione `wmwp_webapp_url`) e il filtro omonimo; i `redirects` di `environment.ts` si leggono insieme agli shard
  (`wmwp_parse_webapp_redirects()`, cache `wmwp_webapp_redirects` di un giorno e ultima lettura riuscita). Oggi gli 11
  domini propri di `environment.ts` sono tutti di GeoHub: per Forestas vale `https://1.<shard>.webmapp.it/`. Test
  `tests/php/webapp-url.php`; traduzioni del campo aggiornate.
- **Child**: `forestas_mappa_url()` in `inc/pagine-statiche.php` dà l'iframe della pagina «Mappa interattiva» (la webapp
  dello shard, con il focus se l'indirizzo ha `?track=<id>` o `?poi=<id>`; `FORESTAS_MAPPA_URL` della webapp 32 resta solo
  di ripiego se il plugin non dà la webapp); «Vedi sulla mappa interattiva» del dettaglio porta a
  `/mappa-interattiva/?track=<id>` o `?poi=<id>` (anche `/en/interactive-map/`). Test in `tests/dettaglio.php`.
- **Produzione**: finché `1.forestas.webmapp.it` non c'è, la pagina avrebbe un iframe vuoto; se si va online prima, si
  scrive l'indirizzo giusto nel campo «Webapp» del plugin. **In locale** il WordPress è configurato come `forestasdev`,
  quindi apre la webapp di dev, ma gli id del database locale (copia del dev del 12/11) non sono più quelli del dev dopo
  il reset: il focus può cadere su un altro elemento (solo ambiente).
- Al primo accesso la webapp mostra la sua finestra dei crediti: è un comportamento della webapp.
- **Altezza della pagina** (Rubens, 13/11: «troppo corta in altezza»). Su Drupal la mappa è alta quanto la finestra meno
  header, percorso e una barra bianca di 88px che contiene solo «Espandi la mappa»: a 1440×723 l'iframe è di 416px, da noi
  era di 429px. Differenza voluta da Drupal: il pulsante sta nella riga del percorso (`.forestas-mappa-testa`, alto 2,5em
  invece di 3em) e la barra resta solo a schermo intero, con «Esci»; la mappa ha almeno 30rem. Risultato a 1440×723:
  iframe di 501px, fino al fondo della finestra; a 500×667 di 540px, senza scorrimento orizzontale. `mappa.js` misura la
  riga del percorso al posto del solo percorso. Child 1.16.4.

## Frecce della lightbox (Rubens, 13/11)

Nella lightbox delle gallerie, su tutto il sito (pagine di oc:8758 e dettaglio), le frecce erano cerchi grigi vuoti e
la chiusura una × di Slick senza cerchio. Su Drupal icone e cerchi li mette il tema (`components/_slick-slider.scss`,
«SLICK GALLERY»): cerchi trasparenti con bordo bianco di 2px, 44px, chevron e × di Font Awesome (`\f053`, `\f054`,
`\f00d`), arancio sotto il mouse solo sulla chiusura. Regole copiate in `assets/css/galleria-lightbox.css`, con il Font
Awesome Pro regular di Impreza (`fontawesome` 400) al posto di «Font Awesome 6 Pro». Child 1.16.6.

## Gruppi dei dati chiusi (Rubens, 13/11)

«Dati catastali e altimetrici» (sentiero) e «Dati del percorso» (itinerario, cammino) prendevano molto spazio. Ogni
gruppo (Percorso, Misure, Catasto, Dati dell'itinerario, Dati del cammino, Enti) ora è un titolo `h3` con il pulsante che
apre e chiude la sua tabella, come il roadbook (`aria-expanded`, `aria-controls`); tutti chiusi, gli Enti aperti se ce
n'è uno solo. La tabella prende il nome dal titolo (`aria-labelledby`) al posto della didascalia, che lo screen reader
leggeva due volte. Senza JavaScript tutto aperto; con Ctrl+F un testo dentro un gruppo chiuso lo apre dove il browser
lo sa fare (`hidden="until-found"`, Chrome ed Edge); in stampa tutto aperto. Verificato prima con Rubens che fosse in
linea con WCAG (pattern «disclosure» della guida ARIA). `forestas_dettaglio_gruppo()` in `inc/dettaglio.php`, pulsante
in `assets/js/dettaglio.js`, test in `tests/dettaglio.php`; child 1.16.7. Il POI non ha gruppi: la sua tabella «Il punto
in breve» resta com'è.

## Partenza e arrivo sulla mappa del dettaglio (Rubens, 13/11)

Su Drupal la mappa del dettaglio è l'iframe di GeoHub (`geohub.webmapp.it/w/osf/simple/sardegna-sentieri-track/<nid>`),
che mette `start-point.png` (cerchio verde) all'inizio e `end-point.png` (bandiera a scacchi) alla fine; in un anello
(nid 1330, Seleni) solo l'inizio. Da noi: opzione `ends` della traccia in `wmwp_map()` del plugin (`track-map.js` mette
i due punti, `map/src/index.js` disegna la bandiera con `getAdditionalPointStyles` del componente), anello = fine entro
50 m dall'inizio. Verde #1F8F35, più scuro di quello di GeoHub perché stacchi dal bordo bianco (4,2:1, WCAG 1.4.11).
Raggio 12 contro 9 dei POI numerati: su Seleni la partenza è a 5 m dal POI 3 e resta visibile come anello verde
intorno. Il child passa le etichette «Partenza» / «Arrivo» e le mette in legenda (l'arrivo solo se non è un anello,
`forestas_dettaglio_anello()`); child 1.16.8.

## Gruppi dei dati come le tavole e «Maggiori info» (Rubens, 13/11)

Itinerario e cammino avevano una tabella sola («Dati dell'itinerario», «Dati del cammino»), scelta mia senza una
decisione dietro. Ora come le tavole: itinerario Percorso e Misure (Tavola 4), cammino Misure e Stato (Tavola 5); il
codice e, per il cammino, partenza e arrivo non sono più nei dati (il codice resta nella testata). In Misure la riga
«Grado di difficoltà» (tassonomie `*-difficulty`, scritte «Escursionismo: E», perché i nomi dei termini sono solo il
codice); accanto al grado e allo stato di validazione «Maggiori info», come su Drupal: `field--field-frubilita.html.twig`
porta al nodo 1102 («Come sono classificati i gradi di difficoltà», qui `/da-sapere/gradi_difficolta/`),
`taxonomy-term--stato-di-validazione--token.html.twig` al nodo 87 (Note legali, che hanno la sezione «Cosa si intende per
stato di validazione» con i livelli 0-6). Le pagine si trovano dal `_drupal_nid` (`forestas_dettaglio_pagina_drupal()`):
senza la pagina niente link. Lo screen reader legge «Maggiori info sui gradi di difficoltà» / «sui livelli di
validazione», colore #B83F14 del wireframe. Restano come prima, d'accordo con Rubens: salita e discesa separate invece
del dislivello totale, soggetto rilevatore negli Enti. Child 1.16.9.

## Ordine delle sezioni del dettaglio: migliorie possibili (Rubens, 13/11, da tenere per il futuro)

L'ordine segue le tavole approvate (3, 4, 5, 7), con lo stesso schema per tutti i tipi: testata, avviso e pulsanti;
indicatori e «Sentiero per tutti»; «in breve»; roadbook, sintesi, attività e avvertenze; galleria e allegati; dati;
correlati; recensioni. Mappa a destra su desktop, subito dopo i pulsanti su mobile. Verificato il 13/11 su sentiero
344, itinerario 589, cammino 311 e POI 401. Rubens ha deciso di non toccare niente per ora; se servisse in futuro:

- **Recensioni sull'itinerario**: la Tavola 4 ha «Opinioni e recensioni» e «Recensioni» in testata, il child le toglie
  per l'itinerario (`'itinerario' !== $tipo` in `wp-package/track.php`) perché su Drupal ci sono solo sui sentieri. Da
  decidere nel ticket di segnalazioni e recensioni.
- **Allegati prima della galleria**: il posto degli allegati non è nelle tavole, l'ha scelto il child (dopo la galleria);
  spesso sono il roadbook in PDF (Seleni: «Roadbook_ANDALA SEGURA.pdf») e starebbero meglio vicino al «come si fa».
- **Indice della pagina** («Vai a: Dati · Correlati · Recensioni…»): utile soprattutto su mobile, dove con la mappa dopo
  i pulsanti indicatori e dati scendono di circa 500px. Non è nel wireframe: va concordato con il cliente.
