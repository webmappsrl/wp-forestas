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
prefisso `wm-` (Rubens). Le note numerate del wireframe non si riportano (Rubens). «Sentiero per tutti» (nota 11,
valori d'esempio) non c'è: nessun sentiero ha il punteggio; l'indicatore di accessibilità usa la classe di inclusività
quando c'è. Il nome perde il codice tra parentesi quando c'è la bandierina; i nomi dello shard uguali all'identifier
(es. provincia «R19621461» in inglese) si scartano.

## Da verificare

- **Ripubblicazione del file dopo un salvataggio** (osservato in locale il 12/11): rinominata la traccia 520 da tinker,
  `UpdateEcTrackAwsJob` è girato ma il file su MinIO è rimasto con il nome vecchio; rilanciato a mano
  (`dispatchSync`) il file si è aggiornato. Finché il file è vecchio il dettaglio con l'indirizzo nuovo risponde 404 (il
  controllo nome ↔ slug non mostra un elemento che non torna). Da riprovare sul dev salvando da Nova prima di
  segnalarlo come bug.

## Messa in opera

- Dopo il merge del plugin, il nuovo commit di `wp-package` va in `WP_PACKAGE_REF` di `docker/scripts/init-wordpress.sh`,
  poi `scripts/wordpress-up.sh` di forestas (CLAUDE.md di wp-forestas, «Trappole»).
- Su ogni ambiente: `bin/wordpress-config.sh pagine --conferma` (slug «punti-di-interesse») e `applica --conferma`.

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

## Bug trovati dopo la review

- **Selettore di lingua della testata verso la home** (Rubens, 12/11): su un dettaglio, «EN» portava a `/en/`. WPML
  calcola il selettore una prima volta prima che il plugin carichi il dettaglio (template_redirect), e la testata usa
  quel calcolo. Il filtro `icl_ls_languages` ora ricava gli indirizzi dal percorso della richiesta e dall'indice
  (`wmwp_detail_request_urls()`), senza leggere il JSON; una guardia evita il rientro, perché `wpml_permalink` richiama
  il selettore (la prima versione esauriva la memoria). Test in `tests/php/detail-sitemap.php`.

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
