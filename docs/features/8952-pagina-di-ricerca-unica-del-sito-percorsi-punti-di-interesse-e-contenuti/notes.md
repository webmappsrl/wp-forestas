> Ticket: oc:8952

# Notes — Pagina di ricerca unica del sito: percorsi, punti di interesse e contenuti

## Decisioni

- **Il codice si divide fra plugin e child theme, con un registro delle sorgenti** (Rubens, 09/11). Il plugin
  `wp-package` fa l'ossatura della pagina (campo di ricerca, vista «Tutti» con un gruppo per sorgente, tab
  con i conteggi, stato nell'indirizzo, card comune) e le sorgenti dello shard (Percorsi, Punti di interesse,
  Enti, da Elasticsearch). Offre un registro delle sorgenti (filtro PHP e funzione JS) in cui il child
  `forestas-child` registra la sorgente «Contenuti del sito» (`wp-json/wp/v2/search` su `post`, `evento`,
  `da_sapere`, `page`) e sostituisce i template del plugin con la grafica della card di Drupal. È la divisione
  decisa allo scrum del 04/11 (08:34), Giuseppe: «Il plugin fornisce i dati e crea le pagine il c il team è
  come c'è l'HTML CSS per intenderci»; lo schema dei template sostituibili dal tema è quello di WooCommerce.
- **Nome del plugin: `wp-package`** (Rubens, 09/11), dal «VMVP package» dello scrum del 27/10 (12:08).
  Il repository su GitHub non esiste: si parte in locale. Il dettaglio è nelle note di sviluppo del ticket.
- **Il fork parte dalla copia locale del ramo `osm2cai`** di wp-geohub (vedi sotto), non dal commit installato
  in wp-forestas: quello andrà eliminato (Rubens, 09/11).
- **Nel fork entra solo ciò che serve alla ricerca**; il resto non si porta (Rubens, 09/11). La base è nella
  sezione «Base di `wp-package`».
- **Il caricamento delle traduzioni si corregge nel fork** (Rubens, 09/11): percorso relativo alla cartella dei
  plugin e file `wp-package-{locale}.mo` (es. `it_IT`), come chiede la documentazione di
  `load_plugin_textdomain()`.
- **La mappa dei risultati è in standby** (Rubens, 09/11): non è deciso se si userà Leaflet o altro. Allo
  scrum del 29/10 (08:33) Giuseppe: «mappa non serve subito».
- **Le modifiche all'API di Elasticsearch non si fanno qui**: quelle note sono nel ticket; ogni altra lacuna
  si comunica alla sessione di analisi oc:8916, che la segna nell'anteprima del ticket delle API.
- **Tag di Orchestrator**: la fase dei tag è stata saltata su indicazione del dev (09/11).
- **I filtri del tab «Percorsi» sono guidati dai conteggi della risposta** (Rubens, 09/11): si preparano tutti
  quelli del wireframe, e ognuno compare solo quando l'API ne restituisce i conteggi; in WordPress restano
  nomi e ordine. La percorribilità, che la tavola 2 mette visibile ma spenta (nota 1), prima non compariva (review-gate,
  09/11); dall'11/11 si ricava dallo stato di validazione (sezione «Filtro «Percorribilità» dallo stato di
  validazione»). I
  filtri che aspettano l'API sono tracciati in «Filtri in attesa dell'API».
- **Selezione e scarico di più sentieri restano fuori da questo ticket, per ora** (Rubens, 09/11; poi entrati nel
  review-gate, sezione «Selezione e scarico di più percorsi»): caselle,
  «Seleziona tutti», barra fissa con numero, km e dislivello, azioni «Tracce» e «Mappa e schede» (wireframe,
  tav. 2, nota 7). Dipendono da API che non ci sono (`api/ec/track/multiple` restituisce al massimo 10 tracce
  in GeoJSON, `EcTrackController.php:67`; scarico multiplo in GPX e KML assente; GPX e KML della singola
  traccia rispondono 500; km e dislivello a 0 fino a oc:8932) e dalla mappa, in standby. Nel plugin `osm2cai`
  non c'è nulla di selezione o scarico multiplo. Si valuta poi se inserirli in un secondo momento.
- **`wp-package` ha un repo suo fin da subito, in previsione del remoto su GitHub** (Rubens, 09/11): clone
  di `wp-geohub` dal ramo `osm2cai` in `/Volumes/WebmappDev/Sites/Webmapp/Wordpress plugin/wp-package`,
  così la storia del fork resta; il remoto `origin` si aggiunge quando il repo esiste. Nel WordPress locale
  entra con un volume nel `compose.yml` di wp-forestas con il percorso dal `.env` (es.
  `${WP_PACKAGE_DIR:-…}:/var/www/html/wp-content/plugins/wp-package`), come il child
  (`compose.yml:41`) e come la chiave `plugins` di `@wordpress/env`. Sul WordPress di dev arriva solo quando
  il repo GitHub esiste: i server fanno solo `pull`. Il volume resta solo per lo sviluppo in locale: sui
  server il plugin lo scarica l'init (decisione «L'init scarica e attiva `wp-package`…», qui sotto).
- **Il plugin attuale `wm-package` (wp-geohub) si toglie da wp-forestas in questo ticket** (Rubens, 09/11): via
  il passo 6 di `init-wordpress.sh` (righe 235-241) con `GEOHUB_DIR`/`GEOHUB_REF`/`GEOHUB_TARBALL` (righe
  14-18), la trappola sul nome `wm-package` nel `CLAUDE.md`, le righe che ne parlano in
  `docs/knowledge/inizializzazione-wordpress.md`, e il passo in `docs/howto/ricreare-wordpress-da-zero.md` (la
  regola del repo chiede di aggiornarla in ogni lavoro che cambia l'installazione). In locale si disattiva e
  si cancella; su UAT e dev resta finché non arriva `wp-package`, poi si disattiva e si cancella a mano,
  con il passo scritto nelle howto di messa in opera: l'init non disinstalla niente, per regola. Anche
  `docker/scripts/config/ritratto.php:30` elenca `wm-package` fra le versioni riportate: diventa
  `wp-package`. Il 09/11 in locale `wm-package` 2.0 è attivo (`wp plugin list`); nessuna pagina in
  `config/` usa suoi shortcode (cercati `wm_grid`, `wm_single`, `wm-package`, `geohub`).
- **`wp-package` ha un prefisso suo per funzioni, classi e opzioni** (Rubens, 09/11), come chiede il
  Plugin Handbook di WordPress («Prefix everything»): con le stesse funzioni del fork (`wm_get_default_config`,
  `wm_package_load_textdomain`…) i due plugin attivi insieme darebbero «Cannot redeclare function», e le
  opzioni (`wm_shard`…) verrebbero lette e scritte da entrambi.
- **L'indirizzo della ricerca è stabile per costruzione** (Rubens, 09/11; motivo in «Codici QR e stabilità
  degli indirizzi»):
  - i **valori dei filtri sono gli identifier dei termini nello shard**, non i nomi mostrati, quindi non
    cambiano con un nome o una traduzione. Il `tid` di Drupal oggi c'è solo negli identifier della difficoltà
    (es. `sardegnasentieri:tid:4067`, «E» di B 401), non in `route-type` (`route-type-trail`), e le carte non
    sono nello shard: risalire da un termine Drupal al valore del filtro si decide insieme alle carte (punto
    aperto 3 di oc:8932; verifica della sessione di oc:8916, 09/11); più
    valori dello stesso filtro separati da virgola, in OR; comuni e province per nome finché l'API non dà un
    identifier. Questa regola sta nel **plugin** e il tema non la cambia;
  - i **nomi dei parametri** sono valori predefiniti neutri del plugin (es. `q` per il testo), esposti in un
    filtro PHP (es. `wp_package_search_params`); il **child** li adatta a Forestas: `search_api_fulltext`
    per il testo, come su Drupal, così i link alla ricerca di oggi continuano a funzionare, e nomi italiani
    per tab e filtri (`tipo`, `attivita`, `difficolta`, `carta`, `comune`…). Anche l'indirizzo
    `/cerca-nel-sito` è del child (voce di `config/pagine.json`).
  - Riferimento: «Cool URIs don't change» (Tim Berners-Lee, W3C, 1998).
- **Il ticket si chiude con la pagina verificata sul WordPress di dev** (`wp.forestas.dev.maphub.it`) (Rubens,
  09/11): confronto con Drupal sui 7 punti di oc:8760 a ≥ 1440px e sotto 960px, accessibilità con tastiera e
  screen reader, italiano e inglese. Prima va chiesto a Giuseppe di creare il repo GitHub di `wp-package`:
  senza, il plugin non arriva su dev. Né wp-forestas né il plugin hanno test automatici o CI; un controllo
  automatico nel browser (Playwright) si valuterà quando l'API cambierà spesso.
- **Lacune dell'API comunicate alla sessione di oc:8916** (09/11), registrate nell'anteprima del ticket
  delle API (versione 29, sezione «Ricerca: Elasticsearch»): identifier stabile per ogni valore filtrabile
  e ordinamento per pertinenza («aggiungerlo all'API»: su Drupal la View `cerca_nel_sito` ha `sorts: {}`,
  cioè pertinenza); nomi delle chiavi dei conteggi dei 9 filtri («da concordare»); `tid` di Drupal negli
  identifier («da decidere insieme alle carte», punto aperto 3 di oc:8932).
- **Il browser chiama direttamente l'API Elastic, come su sentieroitalia; decisione da rivalutare**
  (Rubens, 09/11, challenge: «ok A ma da valutare»). Motivo e misure in «Peso della risposta di
  Elasticsearch». L'alternativa scartata per ora: la ricerca passa da una rotta REST del plugin, con cache
  in un transient e solo i 12 risultati della pagina al browser. Si riprende se l'API non toglie `searchable`
  dalla risposta e non la comprime. Il testo cercato si ripulisce nel plugin prima dell'invio, per evitare
  gli errori 500. Lacune mandate alla sessione di oc:8916 il 09/11: `searchable` nella risposta, compressione,
  errori 500 sui caratteri speciali, OR dentro un filtro, conteggi di `taxonomyWheres` limitati a 10, nomi
  con lo spazio iniziale. Registrate nell'anteprima del ticket delle API, **versione 30**, sezione «Ricerca:
  Elasticsearch» (https://claude.ai/artifact/KmRTuKX4Kndd5VQYEFXBFS), riverificate sul dev dalla sessione di
  oc:8916: righe nuove, «da correggere», per peso della risposta (`searchable` fuori dalla risposta, `terms`
  da valutare), compressione, errore 500 sul testo e nomi con spazio o tabulazione iniziale; il caso
  `hiking,mtb` aggiunto alla riga dell'OR, che c'era già; i conteggi di `taxonomyWheres` erano già nella riga
  «comune e provincia».

- **Senza il plugin la pagina di ricerca resta usabile** (Rubens, 09/11, challenge). Se il child arriva sul
  server senza il plugin, al giro di `allinea` dopo l'apply nasce `/cerca-nel-sito`, e lo shortcode del plugin
  non registrato resterebbe in pagina come testo, con il campo «Cerca nel sito» dell'header che porta lì (oggi
  funziona con `?s=`). Per questo il template della pagina usa uno shortcode del child (es.
  `[forestas_ricerca]`): con il plugin attivo (`shortcode_exists()`) chiama quello del plugin, senza mostra un
  campo di ricerca semplice verso `?s=`. `pagine` si collega sempre alla copia Drupal (`pagine.php:64`), anche
  per le pagine senza nodo come questa: è lo stesso giro di Cammini ed Eventi.
  - Prima della decisione sull'installazione (qui sotto) child e plugin arrivavano per strade diverse, e la
    howto della messa in opera doveva dire l'ordine: prima il clone del plugin, poi il puntatore. Con il
    plugin scaricato dall'init l'ordine si sistema da solo; il controllo nel child resta, perché se GitHub non
    risponde il sito parte senza plugin e lo scarica al riavvio dopo (README, righe 85-87).
- **L'init scarica e attiva `wp-package` al posto di wp-geohub** (Rubens, 09/11, challenge: «ok best
  practice»; «possiamo ritenerlo realmente operativo con il repo disponibile quando ci sarà e sarà pubblico
  come il vecchio plugin»).
  - Come funziona oggi wp-geohub (verificato nel codice): `scripts/wordpress-reset.sh` di forestas cancella il
    volume `wordpress-<APP_NAME>`, cioè tutto `/var/www/html` compresi i plugin, e lancia
    `scripts/wordpress-up.sh`; al passo 6 `init-wordpress.sh` non trova `wm-package/index.php` (riga 236),
    scarica l'archivio del repo pubblico al commit `GEOHUB_REF` (righe 17-18) dentro il volume e lo attiva
    con `attiva_plugin wm-package` (riga 249).
  - Per `wp-package` vale lo stesso, con un commit fisso suo (es. `WP_PACKAGE_REF`). Il plugin sta nel
    volume, non montato dall'host: il `chown` di `init-wordpress.sh:402` non tocca file del repo. Arriva con
    `scripts/wordpress-up.sh`, che va già lanciato dopo ogni nuovo puntatore di wp-forestas (`CLAUDE.md` di
    forestas), quindi insieme al child.
  - Correzione rispetto a wp-geohub: oggi l'init scarica solo se manca `index.php`, quindi su un sito che non
    si azzera (produzione) un commit nuovo non arriverebbe mai, senza avvisi. L'init scrive il commit
    scaricato in un file della cartella del plugin e riscarica quando il commit dichiarato è diverso.
  - Operativo solo quando il repo GitHub esiste ed è pubblico come wp-geohub: il download non usa credenziali.
    Il 09/11 `git ls-remote https://github.com/webmappsrl/wp-package` risponde «Repository not found».
    Finché non c'è, l'init dà un AVVISO e il sito resta senza plugin, con il campo di ricerca semplice del child.
  - In locale, per sviluppare, il clone si monta sopra la cartella del plugin con un mount facoltativo
    (`WP_PACKAGE_DIR` nel `.env`), solo in locale: un default vuoto nel `compose.yml` dei server
    coprirebbe la cartella scaricata. Con la cartella montata l'init non scarica e il `chown` la salta. Come
    si attiva il mount (file compose a parte o altro) si decide nel piano.
  - Shard e app id vengono dal `.env` (segnaposto in `.env-example`): l'init li scrive nelle opzioni del
    plugin solo se sono vuote, come già fa per URL e amministratore. Cambiano per ambiente (dev:
    `forestasdev`, app 1) e su un sito nuovo resterebbero vuoti.
  - Scartate: il clone del repo fatto a mano sul server (versione non fissata, un passo in più nella
    procedura dei server, che si aggiornano solo con `git submodule update --init --recursive`, e il
    `chown` da escludere); `wp-package` come submodule di wp-forestas (due puntatori da aggiornare a ogni
    rilascio, e lo stesso vincolo del repo pubblico).
  - Riferimento: The Twelve-Factor App, «II. Dependencies» (dipendenze dichiarate a una versione esatta), e
    il principio scritto in testa a `init-wordpress.sh` («Porta WordPress allo stato atteso»).
- **I conteggi dei filtri della tab «Percorsi» li calcola il plugin, dai percorsi che il browser riceve già;
  da rivalutare quando l'API li darà giusti** (Rubens, 09/11, challenge: «ok la A valutando se con C poi la
  cosa si può risolvere»).
  - Perché: i conteggi dell'API (`aggregations`) hanno tre difetti, verificati il 09/11 su
    `https://forestas.dev.maphub.it/api/v2/elasticsearch?app=forestasdev_1`:
    1. `taxonomyWheres` e `taxonomyActivities` non hanno `size` (`ElasticsearchController.php`, righe
       184-188; `layers` ha 150): Elasticsearch restituisce i 10 valori più frequenti. Per «Dove» sono
       Sardegna 769, Nuoro 309, Area D 173 … Cagliari 98, con `sum_other_doc_count` 2531; nei 769 percorsi i
       valori diversi sono 306, quindi 296 non compaiono mai (es. Buggerru). Le attività sono esattamente 10,
       e oggi stanno tutte.
    2. I conteggi si calcolano sulla stessa query con i filtri (niente `post_filter`): con
       `taxonomyActivities=mtb` le attività diventano mtb 169, hiking 143, horse 41, trekking 21,
       nordic-walking 11, educational-trails 1, e «Cammini di Sardegna» (`sardegnasentieri:cammino`, 53 senza
       filtro) scompare. Un secondo valore in OR non si può aggiungere.
    3. Con zero risultati (`query=zzqqxx`) tutte le liste dei conteggi sono vuote: con i filtri guidati dai
       conteggi sparirebbero anche quelli attivi, e chi li ha scelti non potrebbe più toglierli.
  - Riferimento Drupal (`config/sync/facets.facet.*.yml`, copia locale): `hard_limit: 0` (tutti i valori),
    `soft_limit: 5` («Mostra altro»; 10 per `cerca_contenuto_nel_sito`), `min_count: 1` (valori a zero
    nascosti), `query_operator: or` per `difficolta` e `aree_geografiche_sentieri`, `and` per
    `cerca_contenuto_nel_sito` e `stato_di_validazione`. Su `/cerca-nel-sito` di Drupal c'è una sola faccetta,
    `cerca_contenuto_nel_sito` (tipo di contenuto); le altre stanno nelle View dei sentieri.
  - Come: ogni percorso della risposta porta `taxonomyWheres`, `taxonomyActivities`, `terms` (es.
    `soft-mobility-difficulty`, `route-type`, `itinerary-type`, con identifier) e `layers`, e arrivano tutti e
    769 in una risposta (decisione sul browser che chiama l'API). All'API si chiede solo il testo: si
    riscarica quando cambia il testo, non a ogni clic su un filtro. In JS: tutti i valori, OR dentro un filtro
    con i conteggi di quel filtro calcolati senza il suo stesso filtro, AND fra filtri; valori attivi sempre
    visibili e deselezionabili anche a zero; valori a zero nascosti e «Mostra altro» dopo i primi 5, come
    Drupal. Un filtro compare quando i percorsi ne portano il campo.
  - Vincolo: funziona finché l'API restituisce tutti i percorsi (`take(10000)`); con la paginazione i
    conteggi tornerebbero all'API. Se si passa alla rotta REST con cache (alternativa del rischio sul peso),
    lo stesso calcolo gira in PHP.
  - Da rivalutare: quando il ticket delle API darà conteggi con `post_filter` (ogni filtro contato senza sé
    stesso), una `size` che copra tutti i valori e i conteggi anche dei valori attivi, si valuta se tornare
    ai conteggi dell'API. Se basta, il browser potrebbe ricevere solo i risultati della pagina, e questo
    risolverebbe anche il peso della risposta. Lacune e vincolo comunicati alla sessione di oc:8916 il 09/11 e
    registrati nell'anteprima del ticket delle API, **versione 31**, sezione «Ricerca: Elasticsearch»: riga
    «comune e provincia» con la causa (`taxonomyWheres` senza `size`), righe nuove «tutte le attività nei
    conteggi» e «conteggi di ogni faccetta con tutti i filtri tranne il suo» (`post_filter`, insieme all'OR),
    entrambe «aggiungerlo all'API»; paragrafo «Vincolo (oc:8952, 09/11)» sotto la tabella, con «WordPress
    (plugin `wp-package`)» dalla **versione 32**. Nella risposta i
    conteggi sono annidati: `aggregations.<nome>.count.buckets`.
  - Riferimento: Elasticsearch Reference, «Filter search results – Post filter».
- **«Contenuti del sito» passa da una rotta REST del child, non da `wp/v2/search`** (Rubens, 09/11,
  challenge: «ok best practice»). Con `wp/v2/search` una pagina di card costa 17 richieste: 1 per i
  risultati, che danno l'immagine solo come id anche con `_embed` (news 685: `featured_media: 679`), 12 a
  `wp/v2/media/<id>`, 4 per i conteggi per tipo (`X-WP-Total`; «sentiero» in locale: 101, cioè post 64, evento
  9, da_sapere 22, page 6). La rotta (es. `forestas/v1/ricerca`) usa la ricerca del core (`WP_Query` con `s`) e
  restituisce in una richiesta le card pronte e i conteggi per tipo. Scartate: `wp/v2/search` con
  `register_rest_field` per l'immagine (5 richieste) e `wp/v2/search` così com'è (17). Riferimento: manuale
  della REST API di WordPress, «Modifying Responses» e «Adding Custom Endpoints».
  - Verificati il 09/11 e non problemi: in inglese `/en/wp-json/wp/v2/search?search=trail` dà titoli inglesi e
    indirizzi con `/en/`, anche con `?lang=en`; i titoli italiani che escono in `/en` sono news senza
    traduzione che WPML mostra in italiano per configurazione (`custom_posts_sync_option`: post, evento,
    da_sapere = 2), come nel resto del sito. La ricerca del core guarda anche il markup (`href`: 87 contenuti
    su 203), ma Drupal fa lo stesso (in produzione `href` 196 risultati, `strong` 1196; l'indice
    `sardegna_index` non ha il processore `html_filter`): non è un passo indietro e resta così.
- **Undici punti minori del challenge, tutti con la proposta** (Rubens, 09/11: «ok»). Verificati nel plugin di
  sentieroitalia (`/Volumes/WebmappDev/Local Sites/sentieroitalia/app/public/wp-content/plugins/wm-package`) e in
  wp-forestas:
  1. Indietro del browser: in `assets/js/grid-track-filters.js` c'è un `pushState` a ogni pausa di 400 ms mentre
     si scrive (righe 48-56 e 971-978) e manca l'ascoltatore `popstate`. Si usa `replaceState` mentre si scrive,
     `pushState` con Invio e al cambio di filtro, tab o pagina, e `popstate` ripristina la ricerca (MDN,
     «Working with the History API»). *Cambiato in esecuzione: la prima pausa di quello che si scrive fa `pushState`,
     vedi «Divergenze», «Task 5: un passo della cronologia per ogni ricerca scritta».*
  2. Manca `AbortController`: una risposta lenta per «sent» può sostituire quella per «sentiero». Si annulla
     la richiesta precedente.
  3. `searchQuery.replace(/ /g, '%20')` (riga 582) codifica solo gli spazi: una `&` spezza la query. Si usa
     `URLSearchParams`.
  4. Durante il caricamento i risultati diventano «Loading...» (riga 597): si tengono i vecchi con
     `aria-busy`.
  5. La pagina si disegna in PHP e in JS (es. `/track/<slug>/` in `shortcodes/grid_track.php:768` e nel JS,
     riga 736). Il PHP stampa solo lo scheletro, un `<noscript>` verso `?s=` e la card in un `<template>` che
     il JS clona. Un primo disegno PHP dei risultati raddoppierebbe il download (il JS scarica comunque tutti i
     percorsi per contare i filtri). L'indirizzo resta indicizzabile perché Googlebot esegue il JavaScript
     (Google Search Central, «Understand the JavaScript SEO basics»).
  6. `number_format($km, 1, ',', '')` e `number_format($meters, 0, ',', '.')` (`shortcodes/single_track.php`,
     righe 85 e 71) mostrerebbero «179,5 km» anche in inglese: si usa `Intl.NumberFormat` con la lingua della
     pagina.
  7. I testi del JS passano in `cfg.i18n` (`grid_track.php`, righe 870-877) senza plurali: si usano
     `wp_set_script_translations()` e `wp.i18n._n()`.
  8. Un solo messaggio d'errore (riga 647): l'errore resta nel gruppo e nel tab della source che non
     risponde, senza un falso 0, e le altre funzionano.
  9. Il link predefinito `/track/<slug>/` darebbe 404 senza wp-geohub: senza destinazione dal filter hook la
     card non ha link.
  10. Drupal in produzione crea link alla ricerca filtrata per tipo, verificati il 09/11 su
      `/cerca-nel-sito?search_api_fulltext=sentiero`: `&f%5B0%5D=cerca_contenuto_nel_sito%3A<tipo>`, con
      `evento`, `itinerario`, `news`, `page`, `poi`, `sentiero`, tutti `rel="nofollow"`. Il child li porta con un
      302 (non 301, vedi il punto 4 più sotto) al tab corrispondente (`poi` a «Tutti» finché i punti di interesse non
      sono nell'API); dal 10/11 `sentiero` e `itinerario` anche con il filtro «Tipo di percorso».
  11. `pagine` non cancella le pagine tolte da `config/pagine.json` (in `pagine.php` non c'è
      `wp_delete_post`): tornando a un child più vecchio `/cerca-nel-sito` mostrerebbe `[forestas_ricerca]` come
      testo. La howto di messa in opera dice i passi per tornare indietro.
- **Soluzioni ai cinque rischi ipotetici del challenge** (Rubens, 09/11: «hai le soluzioni per questi?», poi
  «ok»):
  1. Crawler sulle combinazioni di filtri: i valori dei filtri sono `<input type="checkbox">` in un
     `<fieldset>` con `<legend>` (WCAG, tecnica H71), non link: Google segue solo `<a href>` (Google Search
     Central, «Make your links crawlable»). Drupal usa link con `rel="nofollow"` (verificato in produzione).
  2. Picco sullo shard: la rotta è pubblica (`Access-Control-Allow-Origin: *`, `Cache-Control: no-cache,
     private`, verificato il 09/11) e senza throttle (`routes/api.php:168`, nessun `throttle` nel gruppo).
     Nel plugin una cache in memoria delle risposte per testo (`Map`). All'API, via sessione di oc:8916: cache
     lato server per testo, `Cache-Control: public, max-age=…`, throttle. Nessuna soglia minima di caratteri:
     Drupal non ce l'ha (`min_chars: 1` in `search_api.server.database_server.yml`). Parte API registrata
     nell'anteprima del ticket delle API, **versione 33**, sezione «Ricerca: Elasticsearch», fonte «oc:8952,
     09/11»: cache lato server con chiave app, testo e filtri («aggiungerlo all'API»; il controller non usa
     `Cache`), `Cache-Control: public, max-age=…` ed `ETag` («da correggere», insieme alla compressione),
     throttle per IP («aggiungerlo all'API»; la rotta è montata due volte da `WmPackageServiceProvider`,
     righe 140-150, con prefisso `api/v2` e `api`, solo con il middleware `api`).
  3. Errore fatale senza plugin: il child parla al plugin solo con i filter hook, `shortcode_exists()` e
     `window.wmWpSearch`; il suo JS si accoda solo nel ramo «plugin presente». WordPress non stampa uno script
     con una dipendenza mancante (`class-wp-dependencies.php`, righe 216-233, WordPress 7.1.4; dalla 6.9.1
     scrive un `_doing_it_wrong`). Verifica a fine lavoro con il plugin disattivato.
  4. Reindirizzamento che non si ritira: i vecchi link `f[N]` portano al tab con un **302**, non un 301 (cambia
     il punto 10 qui sopra): sono `rel="nofollow"`, senza valore SEO da trasferire, e un 301 resta nei browser
     (RFC 9110, § 15.4.2, «heuristically cacheable»).
  5. Il plugin come contratto: versione 0.x (Semantic Versioning 2.0.0, punto 4), `CHANGELOG.md`, costante
     della versione, sezione «Interfaccia pubblica» nel README; la 1.0 quando l'API è stabile, prima che un
     secondo shard lo adotti.
  - Riferimento per il punto 2: MDN, «HTTP caching» (cache condivise).
- **Mount del clone di sviluppo, deciso nel piano** (09/11, da approvare con il piano). L'overview chiedeva un
  mount che non toccasse il compose dei server; il «come» restava al piano. Il clone si monta in sola lettura in
  `/opt/wp-forestas/wp-package-sviluppo` con `wp-forestas/compose.sviluppo.yml`, incluso solo da
  `local.compose.yml` di forestas (`include` con un elenco di file; Docker Compose 2.31 in locale), e l'init collega
  `wp-content/plugins/wp-package` con un link simbolico. Così un default vuoto non copre mai un plugin scaricato,
  `find` del passo 9 non entra nel link e `compose.yml`, usato da UAT e dev, non cambia. `scripts/wordpress-up.sh`
  usa gli stessi file compose con cui gira lo shard (righe 19-34). Unico file di codice in forestas:
  `local.compose.yml`.
- **Pulizia del testo per Elasticsearch, decisa nel piano** (verificata sul dev il 09/11): `a/b`, `(oliena`,
  `oliena:`, `"oliena` danno 500, mentre escapati con `\` danno 200 con 0 risultati; `B-401` e `B\-401` danno 200
  con 2 risultati. Il plugin sostituisce con uno spazio `+ = & | > < ! ( ) { } [ ] ^ " ~ * ? : \ /` (così «(oliena»
  trova Oliena) e scrive `-` come `\-` (così i codici come «B-401» restano interi).

## Peso della risposta di Elasticsearch (misure del 09/11)

**Come funziona su sentieroitalia** (copia locale del plugin, `[wm_grid_track layer_ids="6"
show_filters='true']`, shard osm2cai, app 2):
- al primo caricamento il PHP chiama l'API dal server (`grid_track.php:150-152`, `wp_remote_get`, senza
  cache) e scrive tutte le card;
- a ogni cambio di filtro o testo il browser chiama direttamente lo shard (`grid-track-filters.js:573-599`,
  `fetch`, attesa di 300 ms) e ridisegna tutte le card, senza paginazione; la difficoltà la filtra il browser
  (`hit.cai_scale`).

**Misure con `curl`:**

| | Percorsi | Risposta | `searchable` | Con gzip |
|---|---|---|---|---|
| osm2cai `?app=osm2cai_app_2&layer=6` | 525 | 430 KB | 55 KB (nome e luoghi) | 64 KB (calcolato) |
| forestasdev `?app=forestasdev_app_1` | 769 | 2,5 MB | 1,4 MB (descrizione it/en, fino a 11 KB per percorso) | 560 KB (calcolato) |
| forestasdev senza `searchable` (calcolato) | 769 | 1,1 MB | — | 109 KB |

- Nessuna delle due API comprime la risposta (nessun `Content-Encoding` anche con `Accept-Encoding: gzip`),
  ed entrambe rispondono `Cache-Control: no-cache, private`.
- `searchable` lo compone `EcTrack::getSearchableString()` (`wm-package/src/Models/EcTrack.php:821`) con i
  campi scelti nel tab «searchable» della Resource App (`track_searchables`). Su Forestas contiene la
  descrizione, che serve a cercare nel testo come fa Drupal: va tolta dalla risposta, non dall'indice.
- `terms` pesa 314.358 B su Forestas; con JSON compatto `searchable` è 1.446.528 B (massimo 11.224 B per
  percorso), la risposta senza `searchable` 1.055.456 B, con gzip 108.839 B (misure della sessione di oc:8916).
- Gli errori 500 su `B/401`, `monte (limbara`, `tappa: 04` ci sono anche su osm2cai (6.665 B): l'errore è nel
  controller condiviso del wm-package, che passa il testo a `QueryStringQuery('*'.$search.'*')`, dove `/`, `(`
  e `:` sono sintassi di Lucene (causa trovata dalla sessione di oc:8916).

## Codici QR e stabilità degli indirizzi (ricerca del 09/11)

Ricerca su capitolato, bozze, resoconto e verbale del 12/08, call con il cliente e scrum dal 24/06 al 09/11
(manca il periodo 24-30/07, la cui interrogazione non si era chiusa). Citazioni verificate con la ricerca
full text di Drive, salvo dove indicato.

- **QR già distribuiti esistono, e puntano a schede sentiero e pagine delle carte, non alla ricerca.**
  - Capitolato contrattuale del sito (V3, 14/05/2026, `2026_FORESTAS_STUDIOFLU_CIG_BBD6B108DB_CAPITOLATO.pdf`),
    WEBSITE-3 «Standardizzazione URL dei sentieri»: gestione parallela di `/sentieri` e `/sentiero` «così da
    garantire la continuità di accesso ai contenuti pubblicati anche tramite QR-Code già distribuiti», e
    «mantenimento di tutti gli URL precedentemente pubblicati tramite redirect HTTP 301 permanenti». Piano di
    test: verifica dei 301 «da tutti gli URL legacy censiti […] poiché il capitolato tutela la continuità degli
    URL e dei QR code già diffusi».
  - `ANALISI FORESTAS 2025 2028`: metà dei sentieri ha `/sentieri` (creati prima del 21/11/2023) e metà
    `/sentiero`, «in quanto ci sono QR-Code che potrebbero non funzionare».
  - `20260401_FORESTAS_Riunione_SAL.pdf` (riunione SAL del 01/04/2026; l'agente lo attribuiva alla bozza di
    capitolato website, ma la frase si trova in questo PDF): revisione delle aree territoriali «MANTENENDO LE
    URL GIA USATE IN TUTTI I PANNELLI DELLA SARDEGNA» per raggruppare in un unico QR statico i sentieri di un
    territorio, con l'esempio `https://www.sardegnasentieri.it/carte/supramontes`.
  - `/carte/supramontes` su Drupal (09/11) è la pagina del termine «Supramontes» del vocabolario «carte»
    (alias `[term:vocabulary]/[term:name]`): elenco di sentieri con le faccette e 12 card per pagina.
  - Quali e quanti QR, su quali supporti, chi li ha stampati: non risulta da nessuna fonte.
- **QR futuri.** Capitolato V3, WEBSITE-30 «Luoghi di posa»: un QR univoco per ogni palo segnavia, che apre
  una scheda sentiero ottimizzata per il telefono. L'indirizzo della ricerca «stampabile su QR» compare solo
  nel resoconto e nel verbale del 12/08 (§ 4.1), non nel capitolato.
- **Call tecnica del 14/09 (11:00)**, non verificabile con la ricerca di Drive (il documento, di Alessio
  Piccioli, non entra nell'indice della ricerca); testo dai riferimenti di NotebookLM: per gli URL distribuiti
  su «QR code, cartelloni» «bisogna fare un mapping dei 301»; per il canonico delle schede Piccioli propone il
  codice esteso del sentiero, «Perché quello sei abbastanza sicuro che non cambia».
- **Due formulazioni non riconciliate**: il capitolato chiede il 301 per «tutti gli URL precedentemente
  pubblicati»; il resoconto del 12/08 (C-03) e la call del 14/09 li limitano agli indirizzi più indicizzati e
  a quelli distribuiti, con una soglia che deve indicare il committente.
- **Conseguenza per la ricerca**: il resoconto del 12/08 (§ 5) delega l'elenco dei sentieri delle pagine di
  area alla ricerca «con interrogazione già impostata». Se `/carte/<territorio>` (sui pannelli) diventa un
  reindirizzamento alla ricerca con il filtro della carta, l'indirizzo della ricerca con quel filtro finisce
  di fatto dietro un QR stampato.

## Filtri in attesa dell'API

Situazione verificata sull'API dello shard dev il 09/11 (tabella «Cosa serve dall'API» del ticket). Oggi
l'API filtra solo per testo (`query`), attività (`taxonomyActivities`), luoghi (`taxonomyWheres`), layer,
intervalli (`filters`) e `ids`, e restituisce i conteggi (`aggregations`) solo di `taxonomyActivities`,
`taxonomyWheres` (i primi 10) e `layers`. Per ogni filtro va concordato con chi fa l'API il nome della chiave
dei conteggi, perché il filtro compaia da solo: da comunicare alla sessione di oc:8916.

| Filtro | Oggi | Cosa manca nell'API | Chiave dei conteggi |
|---|---|---|---|
| Attività | filtro e conteggi ci sono | i nomi del sito al posto degli identifier Geohub (`hiking`…): arrivano con oc:8932 | `taxonomyActivities` (esiste) |
| Più valori nello stesso filtro, in OR (vale per tutti) | il secondo valore sostituisce il primo | OR dentro la stessa faccetta | — |
| Difficoltà (T, E, EE, EEA) | il dato c'è in `terms.hiking-difficulty`, ma non si filtra e non ha conteggi | filtro e conteggi sui `terms` | da concordare |
| Validazione catastale | non è nell'indice (nello shard c'è per 750 percorsi) | `stato_validazione` nell'indice, filtrabile e con i conteggi | da concordare |
| Comune, provincia | mescolati con aree e settori CAI in `taxonomyWheres`, conteggi solo dei primi 10 | luoghi distinti per tipo e tutti i conteggi | da concordare |
| Area geografica (34 zone) | non c'è nello shard | arriva con oc:8932, poi nell'indice con i conteggi | da concordare |
| Percorribilità (0–5 e «temporaneamente interrotto») | la scala non esiste; il filtro legge lo stato di validazione (11/11) | dato nuovo, deciso nel wm-package (tag 692): ticket da creare, vedi «Da fare dopo questo ticket» | da concordare |
| Area territoriale | da decidere quale griglia | punto aperto 1 del ticket | da concordare |
| Servizio territoriale | non c'è | da decidere (punto aperto 4 di oc:8932) | da concordare |
| Carta | non c'è | da decidere (punto aperto 3 di oc:8932) | da concordare |

Le altre lacune dell'API che non sono filtri (POI ed enti nell'indice, stato di validazione di ogni
risultato, geometrie dei risultati, scarico multiplo, GPX e KML che rispondono 500, inclusività, lunghezza,
dislivello e tempo a 0) sono nella tabella del ticket e nell'anteprima del ticket delle API.

## Da dove si prende il codice

**Copia di partenza**: `/Volumes/WebmappDev/Local Sites/sentieroitalia/app/public/wp-content/plugins/wm-package`
(`~/Local Sites` è un link alla stessa cartella), repository `webmappsrl/wp-geohub`, ramo `osm2cai` all'ultimo
commit `8255db5` (30/04/2026), allineato a `origin/osm2cai` il 09/11. `osm2cai` contiene tutto `origin/main`
(`92d2ae4`, il commit installato oggi in wp-forestas) più due commit: `26bfd3c` «enhance filter and content
handling» e `8255db5` «white label branding support».

La copia ha **modifiche non committate** in 6 file (+355 −38) e la cartella `docs/` non tracciata. Non si
committano (si parte con un plugin nuovo): quello che serve si recupera da lì. La correzione dello slug è
riportata qui sotto per intero, perché esiste solo in quella copia di lavoro.

## Base di `wp-package`: cosa entra

| Da (copia di partenza) | Cosa si prende | Perché |
|---|---|---|
| `index.php` | intestazione nuova (`WP Package`, Text Domain `wp-package`) e solo gli include necessari | oggi include sync, CPT, wp-cli e pagina completa |
| `admin_page.php`, sezioni «Backend Configuration» (riga 580) e «APIs» (riga 621); salvataggio in `wm_save_options` (riga 2462: `wm_shard` 2473, `app_configuration_id` e URL calcolati 2478-2513, `elastic_api` 2513) | scelta dello shard da wm-types, app id, URL delle API calcolati | con `forestasdev` e app 1 l'URL diventa `https://forestas.dev.maphub.it/api/v2/elasticsearch` (`admin_page.php:468`); wm-types ha `forestas`, `forestasdev`, `forestasuat` (`src/environment.ts:136-141`) |
| `functions/functions.php`: `wm_get_config_api_url` (1039), `wm_fetch_remote_config` (1063), `wm_get_default_config` (1089), `wm_update_config_id` (1139), `wm_package_load_textdomain` (8, da correggere) | configurazione e lingue | il resto del file è sync, osm2cai, iframe, Leaflet, stili. *Entrato solo il caricamento delle lingue, corretto (`includes/i18n.php`); le quattro funzioni della configurazione no, vedi «Divergenze», «Task 1: niente configurazione dell'app»* |
| `config/wm_default_config.json` | configurazione di base | *non entrato (stessa divergenza)* |
| `hooks/transient.php` | avvisi della pagina delle impostazioni | *non entrato (stessa divergenza)* |
| `shortcodes/grid_track.php` e `assets/js/grid-track-filters.js`, **solo la logica generica** | chiamata a Elasticsearch (`app={shard}_app_{id}`, `grid_track.php:150`; `query` JS:582; `filters=[{identifier,min,max}]` JS:593; `taxonomyWheres`), lettura di `hits` e `aggregations` (PHP 240-300), stato nell'indirizzo con `pushState` (JS 28-55), card generate in JS (JS 713-800) | è la «stessa logica» del plugin di Sentiero Italia decisa il 29/10; diventa la base del modulo di ricerca nuovo |
| `shortcodes/single_track.php`, funzioni **non committate** `wm_format_track_duration` (44), `wm_format_track_meters` (66), `wm_format_track_distance_km` (80) | durata «h min», metri con il punto delle migliaia, km con una cifra decimale e la virgola | per lunghezza, dislivello, tempo e km complessivi dei risultati |
| `languages/` | si rifà da zero: `.pot` del codice nuovo e `it_IT` | i 7 `.po` di oggi riguardano gli shortcode che non portiamo |

## Cosa resta fuori, e dove recuperarlo se servirà

| Cosa | Dove sta nella copia di partenza | Perché resta fuori |
|---|---|---|
| Sincronizzazione manuale, automatica, wp-cli, cancellazione | `actions/sync_track_action.php`, `sync_poi_action.php`, `auto_sync.php`, `delete_*_action.php`, `wp-cli/` | la ricerca non la usa: `grid_track.php` legge solo Elasticsearch (152), l'API dei layer (76) e la taxonomy where (113), mai `get_posts` né `get_post_meta` |
| CPT `track` e `poi`, custom field (`wm_track_id`, `wm_poi_id`), restrizioni dell'admin, controllo WPML | `post_types/`, `custom_fields/`, `restrict-admin-capabilities.php`, `functions/controls.php` | servono solo alla sincronizzazione |
| Schede di dettaglio e griglia dei POI | `shortcodes/single_track.php`, `single_poi.php`, `grid_poi.php` | il dettaglio è un altro lavoro (Giuseppe, 09/11) |
| Mappa: Leaflet, markercluster, fullscreen (da unpkg) | `functions/functions.php` 434-862, `single_track.php`, `single_poi.php` | mappa in standby |
| Swiper, Lightbox2, Font Awesome (caricati su ogni pagina), togpx | `functions/imports.php` 14 e seguenti, `single_track.php:357` | la ricerca non li usa |
| CSS del plugin | `assets/css/wm-package-default.css` (1.399 righe) | la grafica va nel child |
| Branding white label, widget della dashboard | `admin_page.php` 65-92, `hooks/dashboard.php`, `wm_is_osm2cai_branding_enabled()` | specifici di osm2cai |
| Parti di osm2cai della ricerca: 20 regioni italiane, ordinamento delle tappe, alias Basilicata/Lucania (non committato), ordinamento su «V<numero>» (non committato) | `grid_track.php` 522, 662, 859; `grid-track-filters.js` 265, 282-299, 705 | specifiche di Sentiero Italia |
| Altri punti specifici di osm2cai già committati | `wm_is_osm2cai_shard_type` (`functions.php:236`), `wm_is_osm2cai_shard` (`admin_page.php:439`), usati in iframe, tile, URL dei POI, branding, `single_poi`, `single_track`; confronti con `'geohub'` in `grid_track.php:57`, `functions.php:1050`, `admin_page.php:463` | |
| Documentazione | `docs/documentazione-tecnica-dev.md`, `docs/guida-utente-wm-settings.md` (non tracciati) | descrivono soprattutto sincronizzazione e shortcode; su 5 affermazioni controllate 2 sono sbagliate (le frequenze dell'auto-sync sono 7, `auto_sync.php:22-32`) |

Il nome della cartella `wm-package` è scritto nel codice in `functions/imports.php:6`, `grid_track.php` 66,
764, 887, `single_track.php:371`, `single_poi.php:72`, `grid_poi.php:24`, `functions.php` 582, 911: nei file
che entrano va tolto (percorso calcolato, non scritto).

### Correzione dello slug delle traduzioni (non committata, da applicare quando entrerà la sincronizzazione)

Prima ogni traduzione WPML sincronizzata riceveva lo slug `<slug>-<lingua>`; la correzione le dà lo stesso
slug dell'originale e lascia a WPML la distinzione per lingua. Le traduzioni si trovano con `wpml_object_id`
(`wm_find_translation_post_id`, `functions.php:167-185`), non per slug, quindi la modifica non rompe
l'aggiornamento. Il motivo non è scritto nel diff; la card in JS costruisce `/track/<slug>/` dallo slug
dell'API, che non ha suffisso (`grid-track-filters.js:734-736`).

```diff
# actions/sync_track_action.php (riga 243) e actions/sync_poi_action.php (riga 228)
-                    'post_name'     => $post_slug . '-' . $lang_code,
+                    'post_name'     => $post_slug,

# actions/auto_sync.php, wm_auto_run_tracks_batch (riga 508) e wm_auto_run_pois_batch (riga 676)
-                    'post_name'    => $tr_post_slug . '-' . $lang_code,
+                    'post_name'    => $tr_post_slug,
```

## Da fare dopo questo ticket

- **Ticket nuovo per la percorribilità** (Rubens, 09/11: «sarà da creare come ticket nuovo»). Nessun ticket
  oggi la sviluppa; dove compare (verificato il 09/11):
  - oc:8932 la mette fuori scope, nella tabella «Dati nuovi chiesti dal wireframe (non esistono su
    Drupal)»: stato 0–5 più «percorribile ma temporaneamente interrotto», data, autore, nota; «deciso: nel
    wm-package sul modello di osm2cai (tag 692)», su `EcTrack` con i campi di `HikingRoute` di osm2cai2
    (`issues_status`, `issues_last_update`, `issues_user_id`, `issues_description`, `issues_chronology`);
  - oc:8489 (wm-package, overview, «Out of scope») lascia «Scindere `stato_validazione` in stato di
    accatastamento e stato di percorribilità», con «ticket da aprire» nelle sue note (Piccioli);
  - oc:8827 (forestas, overview, «Out of scope») lascia la richiesta del cliente del 28/10 di filtri
    separati Validazione catastale / Percorribilità;
  - oc:8783 (`docs/features/8783-sardegna-sentieri-wireframe-v3/decisioni.md`) definisce scala 0–5 e
    semaforo, la mette fra «Deciso, da sviluppare» e fra «Deve fornirlo Forestas», e lascia aperto il
    modello (stato, data, chi, nota).

  Per questa pagina, quando il dato arriva nell'API: il filtro è già primo in `forestas_search_track_facets()`
  del child e legge lo stato di validazione (11/11); cambiano solo `field` e i valori dei `groups`. La tavola 2
  lo vuole preselezionato su «4 · Percorribile», dichiarato e disattivabile: oggi non è preselezionato.
  Va chiarito con Giuseppe se la scala separata chiesta da Forestas resta un requisito, visto lo scrum
  dell'11/11 (sezione «Filtro «Percorribilità» dallo stato di validazione»).

  Altre informazioni per quel ticket (verificate il 09/11):
  - il verbale n. 1 del 12/08 (§4.1, riga «Percorribilità») chiede «fra i filtri anche i sentieri percorribili ma
    temporaneamente interrotti, distinti dai non percorribili; verifiche automatiche di coerenza fra segnalazioni e
    stato in catasto»;
  - oggi lo shard ha solo lo stato di validazione importato da Drupal (`terms.validation-status`), un valore unico che
    mescola percorribilità e accatastamento. Sul dev: Percorribile 215, Non percorribile 41, In pre-accatastamento o
    manutenzione 238, In revisione-validazione 193, Validato 45, Non verificato 18, senza stato 19 (769 percorsi);
  - ripiego valutato e **non fatto** (Rubens, 09/11: «se è una cosa nuova che deve essere fatta allora no, aspettiamo»):
    filtro «Percorribilità» provvisorio dallo stesso campo con le tre voci del semaforo della home di `decisioni.md`
    di oc:8783 (Percorribili = Percorribile + Validato 260; In revisione o parzialmente percorribili = In
    revisione-validazione + In pre-accatastamento o manutenzione 431; Non percorribili 41), come prevede la stessa
    pagina («il valore unico di stato del Drupal si copia su entrambe le scale finché Forestas non fornisce la
    percorribilità separata»). Contro: due filtri sullo stesso campo, e un percorso «Validato» ma interrotto da una
    frana risulterebbe percorribile. Con il dato vero il filtro legge il campo nuovo, senza cambiare la pagina: il
    plugin ha già le fasce (`groups`) e i filtri spenti (`disabled`).

- **Grafica di base della ricerca nel plugin, con le variabili CSS** (Rubens, 10/11: «la grafica base del plugin
  tienila nelle note la facciamo con il prossimo ticket e la passiamo ad un'altro agente»). Richiesta di Giuseppe nello
  scrum del 10/11 (08:24, verso 00:05): «lo scheletro diventa [wp-]package. In realtà potrebbe al momento anche la
  grafica, visto che non ne abbiamo una, ovviamente customizzabile con delle variabili di CSS. Quindi questa roba non
  va in qualcosa di custom, ma siccome Elastic non è custom, va in una situazione dove tu poi lo puoi accendere sugli
  altri WordPress»; e «io voglio che tutti abbiano questa disposizione, che poi il tasto sia giallo, rettangolare»
  (il 04/11 aveva detto il contrario: «il plugin non può avere la grafica di Forestas»; vale il 10/11). Stato oggi:
  il plugin ha solo il CSS della mappa (`map/src/map.css`, compilato in `assets/dist/`); tutto l'aspetto della
  ricerca sta nel `style.css` del child, nel blocco della ricerca unica (circa 970 righe, dalla 2505 alla fine del file
  al 10/11, dai filtri al riquadro della mappa). Per chi lo farà: un foglio di stile del plugin (es. `assets/css/search.css`, registrato con
  la ricerca) con la disposizione e una grafica neutra, tutti i colori, i caratteri e le misure come variabili
  `--wmwp-*`; nel child restano solo i valori delle variabili e le eccezioni di Forestas; poi il confronto a 1440 e
  a 390 px con le schermate di oggi (la pagina di Forestas non deve cambiare). Attenzione alle regole di Impreza che
  il child batte con `!important` (outline del focus) e al «torna su» che copre la barra della selezione. Il foglio
  del plugin deve portare anche `.screen-reader-text`, che oggi viene dal tema (Impreza): senza, un altro tema
  mostrerebbe «, 34 percorsi» accanto al numero dei filtri (review di wp-package del delta di accessibilità, 10/11). Il child
  theme di base «liscio» (Giuseppe, 04/11) è un'altra cosa: «a chiusura dei giochi».
- **Rimando alla scheda completa nel riquadro della mappa** (Rubens, 10/11: «il rimando alla scheda completa tienilo
  in note»). Il riquadro al clic mostra la card del percorso senza link, perché il dettaglio della traccia non ha
  ancora un indirizzo (`link_template` vuoto, fuori da questo ticket). Quando ci sarà, basta impostare `link_template`
  della source `tracks` nel child: la card del riquadro diventa un link alla scheda (`fill()` mette `href` quando c'è
  `url`). Da decidere allora se il link resta la card intera o diventa un pulsante «Apri la scheda».

- **Scarico di un solo percorso dalle rotte dello shard, dopo oc:8953** (Rubens, 10/11, nella sessione di oc:8916:
  «ok mi sta bene per il singolo percorso»; comunicato a questa sessione da quella di oc:8916). Con un solo percorso
  selezionato, «Scarica 1 percorso in GPX» e «in KML» useranno `api/ec/track/download/{id}.gpx` e `.kml` invece di
  convertire la traccia nel browser con `assets/js/track-export.js`. Si fa quando oc:8953 è mergiato nel wm-package
  e verificato sul dev: oggi quelle rotte rispondono 500 (segnalazione 4). Con più percorsi resta `track-export.js`:
  le rotte di oc:8953 danno una traccia per file e non esiste una rotta con più tracce in un file (nel ticket delle
  API c'è la riga «scarico di più sentieri in GPX, KML e GeoJSON → aggiungerlo all'API»).
  Differenze rispetto a oggi, secondo l'overview di oc:8953 (il codice non c'è ancora, quindi non verificate):
  - nome del file: lo dà lo shard con `Content-Disposition`; è il nome del contenuto con gli accenti (`filename*` in
    UTF-8, `filename` in ASCII), sempre in italiano anche sulla pagina inglese, e senza `ref`. Oggi la ricerca usa il
    nome nella lingua della pagina, senza accenti, al massimo 80 caratteri (`fileName` di `track-export.js`);
  - quota: lo shard toglie `<ele>` da ogni punto con Z uguale a 0; `track-export.js` la toglie solo se tutta la
    traccia è a 0;
  - traccia senza geometria: la rotta risponde 404 `{"code": 404, "error": "Geometry not found"}`. Oggi la ricerca
    esclude il percorso e la barra lo dice: con la rotta dello shard quel caso va gestito, altrimenti un link diretto
    apre il JSON d'errore (supposizione, da provare quando la rotta c'è);
  - il GeoJSON resta fuori dalla decisione: `download/{id}.geojson` risponde già 200, ma con la traccia completa,
    descrizioni e gallerie comprese; quello di `track-export.js` ha solo geometria, `id`, `name` e `ref`.

- **Nomi inglesi di tipo e stato di validazione da confermare con Forestas** (review di wp-forestas, 10/11). L'API dà
  i nomi dei termini solo in italiano; il child li traduce con `term_labels` (`forestas_search_term_labels()` in
  `inc/search.php`). I nomi inglesi li abbiamo scritti noi e sono provvisori: Trail, Itinerary, Walk; Validated, Under
  review, Pre-registration or maintenance, Walkable, Not verified, Not walkable (gli stessi delle fasce del filtro
  «Validazione catastale», che erano già in inglese). Se Forestas ne dà altri, si cambiano solo lì. Quando l'API darà i
  nomi in inglese, `term_labels` si può togliere: il plugin usa il nome dell'API se il tema non ne dà uno.
- **«Pagina di esempio» di WordPress da togliere dall'installazione** (review di wp-forestas, 10/11). È la pagina 2
  che WordPress crea all'installazione; c'è in locale, sul dev e su UAT, pubblicata, e con la regola delle pagine
  (sotto, «Review interne del 10/11») esce fra i «Contenuti del sito»: in locale il 10/11 le pagine nei risultati
  sono 14, le 13 venute da Drupal più questa. Va tolta da `docker/scripts/init-wordpress.sh` (e a mano sui server
  già installati), non nascosta dalla ricerca: una regola che la nasconde nasconderebbe anche le pagine che la
  redazione scrive in WordPress.
- **Debito del plugin per un secondo shard** (review di wp-package, 10/11; verificato nel codice lo stesso giorno, solo
  da annotare):
  - la lettura degli shard da wm-types (`wmwp_parse_shards()`, `includes/settings.php:28`) è un'espressione regolare
    che dipende dall'ordine `origin`, `elasticApi`, `graphhopperHost`, `awsApi` di `environment.ts` su `main`: se
    l'ordine cambia, nessuno shard viene letto;
  - valori fissi nel codice: colore e spessore di riserva, contorno dei selezionati e tratteggio degli altri nella
    mappa (`assets/js/track-map.js:26-34`, colore e spessore si cambiano con `map_style`, gli altri no); il tipo del
    percorso letto da `terms['route-type']` e l'italiano come lingua di ripiego dei nomi (`source-tracks.js`,
    `search-logic.js`);
  - `map/src/index.js` importa il componente da un file interno del campo Nova di wm-package
    (`src/Nova/Fields/FeatureCollectionMap/resources/js/components/FeatureCollectionMap.vue`), e nessun controllo
    verifica che `assets/dist/` sia compilato dal commit del submodule;
  - la ricerca dei percorsi si fa tutta nel browser sui risultati dell'API, che ne dà al massimo 10.000, senza un
    avviso se il catalogo è più grande (Forestas: 769 percorsi sul dev).

## Scrum del 10/11: repository e mappa

Trascrizione: «scr-um w-ebmapp - 2026/11/10 08:24 CET - Transcript» (Doc `1_L1mLqeqw-6MpK6Qcsn6wDn-yWAKUiyvCFeH0_53sEg`),
letta con NotebookLM (notebook «scrum 2026-11-10»). Trascrizione automatica con nomi storpiati («Kitab», «VP package»,
«view JS», «aframe»): le attribuzioni sono poco affidabili.

- **Repository.** Rubens: «il Repository non c'è, va creato il repository per il nuovo plugin»; Giuseppe lo crea
  durante la call (verso 00:45), perché Rubens non ha l'accesso per farlo. Su GitHub: `webmappsrl/wp-package`, pubblico,
  creato il 10/11 alle 09:12, vuoto (verificato con `gh`). Il plugin ne prende il nome: sezione «Nome del plugin».
- **Mappa.** Rubens la propone con OpenLayers fatta da zero; Giuseppe: «il problema non è farla visivamente, è farci
  finire i dati a Elastic» e «Elastic non ti può fornire i dati per disegnare le linee» (le geometrie renderebbero
  lento l'indice). Strada indicata da Giuseppe: dagli id dei risultati filtrati, un'API che restituisce una feature
  collection («Mi raccomando una feature collection»); Rubens: è `multiple`, che c'è già. Per disegnarla, il componente
  Vue che Giuseppe ha fatto per Nova, in una build standalone dentro il plugin WordPress (l'iframe «magari non ci piace
  tanto»); con lo stesso codice anche i punti. Rubens: «La mappa si fa così», «me la vedo io». Non detti: ticket,
  priorità, inquadratura, clic su un percorso, cluster.
- **GPX.** Giuseppe chiede se lo scarico usa i GPX di sardegnasentieri.it («questo sito lo butteremo giù»). Verificato
  il 10/11 con Playwright: lo scarico della pagina chiama solo `https://forestas.dev.maphub.it/api/ec/track/multiple`,
  niente da sardegnasentieri.it. Di cosa parlasse esattamente non si capisce dalla trascrizione (supposizione: di
  un altro scarico, per esempio quello della webapp; non verificato).
- **oc:8953**: Carla corregge GPX e KML delle tracce, e nello stesso ticket dei POI.
- **Dev e UAT**: «tutti in dev», UAT non si aggiorna per ora.

## Nome del plugin: `wp-package` come il repository (10/11)

Rubens: «si va cambiato intanto il nome come quello del repository del plugin». Da `wm-wp-package` a `wp-package`:
- plugin: cartella del clone (`Wordpress plugin/wp-package`), `Plugin Name: WP Package`, `Plugin URI`, text domain e
  file delle traduzioni (`wp-package.pot` rigenerato con make-pot su una copia, `wp-package-it_IT.po/.mo` 46/6, i due
  JSON), hook `wp_package_search_params` e `wp_package_search_sources` (usati dal child), cartella dei template nel
  tema (`wp-package/<nome>`), `creator` del GPX, README, CHANGELOG, test;
- wp-forestas: `WP_PACKAGE_REF`, `WP_PACKAGE_DIR`, `WP_PACKAGE_SHARD`, `WP_PACKAGE_APP_ID` (init, `.env-example`, `.env`
  locale), `compose.local.yml` con `/opt/wp-forestas/wp-package-dev` e `docker/wp-package-empty`, `ritratto.php`, README,
  CLAUDE.md, knowledge e howto; child `Version` 1.8.15;
- forestas: `local.compose.yml`, knowledge `wordpress-nello-shard.md`, howto di dev e UAT.

Non cambiati i prefissi interni `wmwp` (funzioni `wmwp_`, costanti `WMWP_`, opzioni `wmwp_shard` e `wmwp_app_id`, handle
`wmwp-*`, classi `.wmwp-` e `data-wmwp`, `window.wmWpSearch`): sono il namespace del codice, non il nome del plugin, e
cambiarli toccherebbe le opzioni già salvate e 192 righe del CSS del child.

In locale l'init ha collegato e attivato `wp-package`; tolti a mano il link `wm-wp-package` rimasto nella cartella dei
plugin e la sua voce in `active_plugins`. Sui server non c'è niente da togliere: il plugin non è mai stato installato
(Task 12, passo 4). Verificato con Playwright: script da `/wp-content/plugins/wp-package/`, `style.css?ver=1.8.15`, testi
in italiano («Seleziona tutti i 769 percorsi della ricerca», «File pronto: 1 percorso in GPX.»), GPX con
`creator="wp-package"`, nessun errore JS. Test `search-logic` 29/29, `track-export` 9/9.

Nello stesso giro: due test di `search-logic` fallivano con Node 22.23 (CLDR 48), non per il rename. In italiano il
CLDR recente non separa le migliaia sotto i 10.000 («1234 m»; CLDR 44 dava «1.234 m»): i test usano 12.345, che si
scrive uguale in tutte le versioni provate.

### Storia del repository: si parte da zero (10/11)

Rubens: «ok per partire con una storia nuova sul repo». Il clone è quello di `wp-geohub` (34 commit), ma del codice di
partenza non resta nessun file identico: restano solo `index.php` (riscritto, 18 righe aggiunte e 27 tolte) e
`.gitignore`, i 21 file del plugin sono nuovi. Il primo commit di `webmappsrl/wp-package` sarà quindi su un branch
orfano (`git checkout --orphan`), con il plugin di oggi; la storia di `wp-geohub` resta nel suo repository, pubblico, e
la sezione «Cosa resta fuori, e dove recuperarlo se servirà» dice dove trovare il codice tolto.

**Fatto in locale il 10/11** (Rubens: «tutto questo puoi iniziarlo a fare nel mio locale e poi quando siamo pronti si
fa il push su origin»), nel clone `Wordpress plugin/wp-package`, senza push:
- tolti i branch locali `main`, `develop` e il vecchio `feature/oc-8952-…`: erano puntatori allo stesso commit di
  `osm2cai` (`8255db5`, verificato prima), senza remote né commit propri; resta `osm2cai`, che segue
  `upstream/osm2cai`, come riferimento a wp-geohub;
- `main` orfano con `77e7c1d chore(oc:8952): avvio del repository wp-package` (licenza GPL-2.0 da gnu.org, `.gitignore`,
  README minimo); `develop` allo stesso commit;
- `feature/oc-8952-…` da `develop` con `72330e1 feat(oc:8952): ricerca unica del sito, scarico dei percorsi e mappa dei
  risultati` (38 file nuovi e il README completo; niente `node_modules`, `vendor`, `.DS_Store`); nessun commit in
  comune con wp-geohub (`git merge-base` vuoto);
- poi, sullo stesso branch, `b343cae chore(oc:8952): wm-package come submodule per la build della mappa` e `5818402
  build(oc:8952): la mappa si compila dal submodule wm-package` (sezione «Mappa dei risultati»);
- remote `origin` → `https://github.com/webmappsrl/wp-package`. Poi (Rubens: «si toglili») tolti anche il remote
  `upstream` (wp-geohub) e il branch `osm2cai`, perché GitKraken mostrava la storia di wp-geohub insieme alla nuova e il
  «Push» di un branch senza remote poteva scegliere `upstream`; prima verificato che `8255db5` sia nel clone
  `Wordpress plugin/wp-geohub` (`origin/osm2cai`) e su GitHub. Nel clone restano solo `main`, `develop`, il ticket e
  `origin`.
Incidente senza danni: il primo tentativo di `git checkout --orphan main` è fallito perché `main` esisteva già, e lo
script è andato avanti mettendo in stage la cancellazione dei file di wp-geohub sul vecchio branch; nessun commit,
indice riportato a HEAD con `git reset`. Restano: push di `main`, `develop` e del ticket su `origin`, la PR verso
`develop` (revisione di Giuseppe), poi `WP_PACKAGE_REF` in wp-forestas.

## Mappa dei risultati (10/11)

Rubens, dopo lo scrum del 10/11: «va fatta ora come discusso, il mio ticket non è in review, se il componente vue
non è fattibile si ricrea la mappa basandoti su quel componente»; poi: «la mappa deve essere un componente
riutilizzabile per più punti del sito oltre alla ricerca unica, sarà usato nei dettagli di track, poi layer etc».

- **Il componente.** È `FeatureCollectionMap` di wm-package (`src/Nova/Fields/FeatureCollectionMap`, Vue 3 e
  OpenLayers 9.2.4): nello scrum Giuseppe parla del codice «presente in Feature Collection e fatto in Vue JS», un
  componente che gira dentro Laravel Nova, da portare in una build standalone dentro il plugin WordPress. È fattibile
  e si usa **com'è, senza modificarlo**: riceve una FeatureCollection già pronta (`inlineGeojson`), lo stile viene
  dalle `properties` di ogni feature, e la parte di Nova (popup con le classi di Nova, screenshot, link alla
  risorsa) si spegne con le props. Non c'entra `wm-layer-map` di `webmappsrl/wm-elements` (Angular, 2,4 MB solo per
  il file principale, mostra un layer dell'app, non una FeatureCollection).
- **Come arriva nel plugin.** `map/` in `wp-package`: Vite 5 compila `src/index.js` più il componente in
  `assets/dist/wp-package-map.js` (805 KB, 243 KB compressi) e `.css`, versionati, così WordPress non ha bisogno di
  Node. Il componente non è copiato. Prima prova come dipendenza npm da GitHub: 113 MB e 9 minuti, perché il
  `package.json` di wm-package porta `chartjs-node-canvas` e `canvas` (modulo nativo). Seconda versione: `npm run build`
  scaricava da GitHub solo la cartella del componente al commit di `package.json`. **Dal 10/11 pomeriggio** viene dal
  submodule `map/wm-package` (al commit `f05142f7`, `main` di wm-package), per la decisione di Giuseppe nello scrum
  delle 14:15 (trascrizione «scr-um w-ebmapp - 2026/11/10 14:15 CET»): «gli dobbiamo inserire il submodule
  wm-package. È da lì che si va a prendere tutto il codice. Non gli mettere un URL», «se io poi faccio una modifica
  alla feature collection, tu per vederla dovrai aggiornare il submodule», «noi buttiamo il compilato dentro il
  repository». Commit separati nel repo del plugin: `b343cae` (solo `.gitmodules` e il submodule) e `5818402` (la
  build). Il compilato rifatto dal submodule ha la stessa lunghezza ma non gli stessi byte, perché Vue e il
  minificatore calcolano alcuni nomi dal percorso dei file: verificato nel browser che mappa, riquadro con selezione,
  colori dei layer (stessi pixel: 184, 904, 3846, 1162) e schermo intero siano come prima. Dettagli in `map/README.md`.
- **Cosa aggiunge la build:** `ol/ol.css` (in Nova lo carica `resources/sass/field.scss`, che non viene scaricato),
  il testo «Caricamento mappa...» nascosto perché è solo in italiano (resta la rotella; l'avanzamento lo scrive la
  pagina), i `console.log` del componente tolti, i nomi dei pulsanti +/− nella lingua della pagina (OpenLayers li ha
  in inglese, «Zoom in»), la mano sulle linee cliccabili (il componente la mette solo sulle feature con `link`, che
  aprirebbe una scheda nuova).
- **Componente del sito, non solo della ricerca.** `window.wmWpMap.mount(element, options)` e shortcode `[wmwp_map]`
  con `tracks="…"` (tracce per id da `api/ec/track/multiple`) o `geojson="…"` (es. `api/ec/poi/<id>.geojson`).
  Provato su una pagina locale temporanea, poi cancellata: le tracce 128, 102, 98 e il GeoJSON della 343, 0 errori JS.
  Nome dello shortcode `wmwp_map`, come `wmwp_search`.
- **Dati.** Le geometrie non vengono da Elasticsearch (Giuseppe nello scrum: «Elastic non ti può fornire i dati per
  disegnare le linee») ma da `api/ec/track/multiple` con gli id dei risultati filtrati, come lo scarico. Il codice di
  rete è passato dallo scarico a `assets/js/track-api.js`, usato da entrambi, con una cache per id che tiene solo
  geometria, id e codice. Misure sul dev, 10/11 (Playwright): tutti i 769 percorsi, 77 richieste, 11,5 MB, 7,3
  secondi, heap della pagina 82 MB; un filtro dopo, 0,3 secondi e nessuna richiesta; tornando all'elenco e di nuovo
  alla mappa, nessuna richiesta. Il 09/11 gli stessi 769 erano 20,5 MB con curl: suppongo che il contenuto delle
  tracce sia cambiato col reset dello shard, non l'ho verificato.
- **Dalla tavola 2:** selettore `.seg` accanto al totale (pulsanti con `aria-pressed`, blu `#16497B` quello scelto),
  mappa di 420px al posto di elenco e pagine (`.res-map`), «Seleziona tutti» e barra della selezione che restano,
  linee rosse `#DE1B0D` da 4px con il codice come etichetta, +/− in alto a destra (`.map-ctl`), nota in basso a
  sinistra (`.cap`) «Inquadratura sul riquadro che contiene tutti i risultati». Il pallino 5 della tavola sta sul
  selettore: non serviva chiedere, la mappa è quella decisa nello scrum. Sotto i 600px la nota va sotto la mappa,
  perché sopra copriva «© OpenStreetMap contributors».
- **Limiti del componente usato com'è** (si cambiano in wm-package, condiviso con Nova): base OpenStreetMap fissa
  (OSM chiede l'attribuzione, che c'è; per un sito con molto traffico va valutata una base nostra), etichetta nel
  colore della linea (rossa con alone bianco, nella tavola è nera), 12px sans-serif invece di 13px Work Sans,
  tastiera solo sui pulsanti +/− (`keyboard: false` nelle interazioni). Per chi usa uno screen reader la vista
  accessibile resta l'elenco.
- **Schermo intero** (Rubens, 10/11: «è possibile aggiungere il controllo a tutto schermo della mappa nei
  risultati?»). Il componente non lo ha: è il controllo `FullScreen` di OpenLayers, aggiunto dal wrapper alla `map`
  del componente dopo il mount (`fullScreen: true`; ricerca e shortcode lo accendono), senza toccare wm-package. Il
  pulsante è `.map-full` della tavola del dettaglio (32px, bordo `#767676`, angoli di 4px, icona delle frecce agli
  angoli), 6px sotto i +/−; nomi «Mappa a schermo intero» / «Esci dallo schermo intero» (in inglese «Show the map full
  screen» / «Exit full screen»). Va a schermo intero solo la mappa, senza la nota. Dove il browser non permette lo
  schermo intero di un elemento della pagina OpenLayers nasconde il pulsante (`ol-unsupported`): suppongo che sia il
  caso di Safari su iPhone, non l'ho provato su un telefono vero. Nello stesso giro: «Ripristina il nord» di
  OpenLayers (invisibile finché la mappa non si ruota, per esempio con due dita sul telefono) stava di serie dove ora
  sono i +/−; spostato sotto lo schermo intero. Verificato con Playwright: Invio sul pulsante, mappa 1440×1000 come la
  finestra, nome «Esci dallo schermo intero»; clic per uscire, mappa di nuovo 874×420; nessun errore JS; child 1.8.19
  servito.
- **Percorsi selezionati in primo piano** (Rubens, 10/11: «se selezioni delle track dall'elenco c'è modo di dare un
  focus a quelle selezionate anche su mappa? il component fa già qualcosa di simile?», poi «si fallo così»). Il
  componente non ha la selezione, ma ha `context: true` (oc:8747, `map-fit/utils.mjs` e `line-style/utils.mjs`): la
  feature si disegna sotto le altre e non entra nell'inquadratura. Se fra i risultati c'è almeno un percorso
  selezionato, `wmWpTrackMap.collection()` lascia i selezionati rossi con un contorno bianco (`casing` 8px) e mette
  gli altri come i «sentieri collegati» della mappa del dettaglio nella tavola (`.map-leg .lg-near`: tratteggio da
  2,5px, `#5B7A99`; qui `strokeDash` [7, 5]), senza etichetta e con `context: true`: la mappa si inquadra sui
  selezionati. Primo piano e inquadratura vanno insieme: separarli vorrebbe dire cambiare il componente in
  wm-package. La nota dice «Inquadratura sulla selezione: 2 percorsi selezionati. Gli altri risultati sono
  tratteggiati.»; con tutti selezionati, o con nessun selezionato fra i risultati, la mappa è quella di sempre.
  **Errore di verifica, corretto.** La prima versione usava il grigio (`#6B7280` da 3px, poi `rgba(107, 114, 128,
  0.5)` da 2,5px), e l'avevo data per verificata guardando una schermata: le linee grigie che vedevo potevano essere
  strade e sentieri della carta OpenStreetMap, e infatti Rubens: «io se seleziono vedo solo quelli selezionati
  […] devi essere sicuro e seguire una strada». Controllato poi bloccando le tile di OpenStreetMap (`page.route`): le
  linee grigie c'erano, ma sulla carta non si distinguevano dalle sue. Da qui lo stile della tavola, e la verifica
  con due schermate della stessa selezione, con e senza carta: B 401 e G 640 rossi, gli altri tratteggiati, ben
  distinti anche sulla carta (intorno a Monte Ortobene). Con due selezionati lontani gran parte degli altri risultati
  resta fuori dall'inquadratura: è l'effetto voluto di `context`.
  La selezione si fa nell'elenco; con la mappa aperta «Seleziona tutti» e «Deseleziona tutto» la ridisegnano, senza
  richieste (tracce in memoria). Verificato con Playwright: B-403 e B-405C selezionati nell'elenco, «Mappa»,
  inquadratura sui due e nota; «Seleziona tutti» e poi «Deseleziona tutto» con la mappa aperta riportano la mappa di
  sempre, con la barra nascosta; nessun errore JS.
- **Carta di base Webmapp** (Rubens, 10/11: «è possibile usare delle tile diverse per la mappa? tipo quelle
  webmapp?», poi «ok» alla proposta). La webapp di Forestas legge le carte da `MAP.tiles` del `config.json` dell'app
  (`https://forestas.dev.maphub.it/api/app/webmapp/1/config.json`): prima la carta Webmapp
  (`https://api.webmapp.it/tiles/{z}/{x}/{y}.png`), poi il satellite di MapTiler; nella webapp l'attribuzione è
  «© Webmapp © OpenStreetMap» (letta con Playwright su `1.forestasdev.webmapp.it`). Il plugin legge la prima carta
  dallo stesso file (`wmwp_map_tiles()`, transient di un giorno, un'ora se lo shard non risponde; filter
  `wp_package_map_tiles`) e il wrapper cambia la sorgente del layer di tile del componente, senza toccarlo. La chiave
  del satellite, che sta nel `config.json`, non finisce nel repo. **Zoom:** l'app dichiara `maxZoom: 17`, ma le tile
  Webmapp arrivano al 16: su 10 punti della Sardegna lo zoom 16 risponde su tutti, il 17 su nessuno (404; il 18 su 4).
  Quindi le tile si chiedono fino al 16 e oltre si ingrandiscono (`WMWP_WEBMAPP_TILES_MAX_ZOOM`), la vista si ferma a
  17. Con la sorgente nuova OpenLayers chiudeva l'attribuzione dietro una «i»: `attributionsCollapsible: false`, come
  nella webapp. Verificato con Playwright: nella ricerca 70 tile da `api.webmapp.it`, nessuna da OpenStreetMap, nessun
  404 fino allo zoom massimo (12 clic su +), attribuzione «© Webmapp © OpenStreetMap» sempre visibile; shortcode su una
  pagina temporanea, poi cancellata, con la stessa carta. Test PHP `map-tiles.php` PASS (RED prima).
- **Colori dei percorsi per layer** (Rubens, 10/11: «per la 1 ok, e questo va inserito come cosa custom nel child o
  tramite plugin?»). Nel plugin: i colori vengono dal `config.json` dell'app, che ogni app Webmapp ha e che il plugin
  legge già per la carta, e Giuseppe il 10/11 ha detto che quello che non è custom va nel plugin; il child li cambia
  con `wp_package_map_layer_colors`. `strokeColor` di Elasticsearch è vuoto su tutti i 769; i colori stanno in
  `MAP.layers[].style.color`. Regola: il primo layer colorato a cui la traccia appartiene, nell'ordine dell'app
  (Cammini 8, Sentieri Accessibili 9, Itinerari 10, Sentieri 11). Effetto: 612 sentieri rossi, 102 itinerari verdi, 53
  cammini blu, e i 2 sentieri anche nel layer «Sentieri Accessibili» in magenta `#e100ff`: C 421 «Fassoni - Su
  Presoni» e C 104 A «Àndala Segura - Sentiero accessibile di Seleni». Per averli rossi basta mettere il layer 11 prima
  del 9 con il filter. Il verde degli itinerari è quello dell'app, `#608d0d`; il filetto delle card usa quello del
  wireframe, `#58810B`. Verificato con le tile bloccate: pixel dei tre colori sulla mappa di «oliena».
- **Riquadro al clic sulla mappa** (Rubens, 10/11: «mostra la preview dei dati e il rimando alla scheda completa tienilo
  in note»). Al clic su un percorso, un riquadro sul punto cliccato con la card della ricerca (template `wmwp-card`:
  immagine, tipo con il filetto del colore, nome, comuni e misure), con «Chiudi», Esc e clic fuori per chiuderlo. È
  un'opzione generica del wrapper (`popup`), riutilizzabile nei dettagli. Il clic non apre più il percorso: prima le
  informazioni essenziali (resoconto del 12/08, § 4.2). Su un telefono il «+» copriva «Chiudi»: il riquadro ora sta
  sopra i controlli. Verificato con Playwright: card di G 122 «Sa 'e Pride Ledda - Jacu Piu», «Chiudi» ed Esc la
  chiudono, a 390px niente scorrimento orizzontale; nessun errore JS.
- **Mappa con la ricerca di prima mentre carica** (Rubens, 10/11: «hai notato cosa succede se cancelli la ricerca
  digitata e vai su mappa?», poi «si puoi risolvere?»). Riprodotto con Playwright: con il campo vuoto i risultati sono
  769, e la mappa deve scaricare tutte le tracce (77 richieste, circa 6 secondi). La mappa si aggiornava una volta sola,
  alla fine: per quei secondi, con la mappa già aperta su «oliena», il totale diceva «769 percorsi» e la mappa
  disegnava ancora i 37 di «oliena»; con la mappa mai aperta, l'area restava vuota. Ora la mappa disegna le tracce già
  arrivate a ogni gruppo (al massimo ogni 600 ms, la prima volta subito) senza cambiare inquadratura
  (`preserveViewOnReload` del componente, `update(…, { keepView: true })`), e alla fine inquadra tutto una volta.
  Misurato contando i pixel colorati con le tile bloccate: da «oliena» (6220 px) a «buggerru», dopo 0,7 s 0 px (i
  percorsi di «oliena» spariti, «Carico i tracciati: 0 di 18»); campo svuotato, da 2873 px (i 55 già in memoria, che
  fanno parte dei 769) a 18065 px in 5,5 s, poi l'inquadratura su tutta la Sardegna; mappa aperta da zero con il campo
  vuoto, creata dopo 1,1 s e con le prime tracce a 1,6 s, inquadratura finale a 7,1 s. Ripassate le prove di elenco e
  mappa, selezione, riquadro e scarico: tutto come prima, nessun errore JS. Test JS 53/53.
- **«Seleziona tutti» solo con una ricerca** (Rubens, 10/11: «quando non ci sono risultati inseriti e quindi è in
  /cerca-nel-sito/?tipo=percorsi nascondi il box di selezione […] questo si mostra solo se inseriscono delle
  ricerche»). Scelta dichiarata a Rubens: conta come ricerca un testo (non fatto di soli caratteri speciali,
  `cleanQuery`) o almeno un filtro, perché il resoconto del 12/08 (§ 4.3) ha il caso «selezionare un comune e
  scaricare in un'unica operazione tutti i sentieri», cioè filtro senza testo e poi «Seleziona tutti». Le caselle
  delle righe e la barra restano anche senza ricerca. Verificato con Playwright: `?tipo=percorsi` senza «Seleziona
  tutti» (con la barra se si sceglie una riga), solo il filtro «Cammino» «Seleziona tutti i 53 percorsi della
  ricerca», testo «oliena» «… 37 …», campo svuotato di nuovo senza, vista mappa senza ricerca senza; nessun errore JS.
- **Selezione dalla mappa** (Rubens, 10/11: «dalla mappa se clicco su una track si apre l'anteprima e va bene, che ne
  dici se al click si seleziona pure per il download?»; alla proposta del pulsante, «ok»). Non al semplice clic: si
  clicca per guardare, e ogni selezione ridisegnava e reinquadrava la mappa chiudendo il riquadro. Nel riquadro c'è il
  pulsante «Aggiungi alla selezione» / «Togli dalla selezione» (`aria-pressed`); una scelta fatta lì cambia solo i
  colori (`setSelected(…, quiet)` → `update(…, { keepView: true, keepPopup: true })`): la vista non si muove, il
  riquadro resta aperto, la nota dice «1 percorso selezionato. Gli altri risultati sono tratteggiati.» senza parlare di
  inquadratura. La barra si aggiorna come dall'elenco. Verificato con Playwright: riquadro fermo nello stesso punto
  prima e dopo (798, 552), pulsante e barra aggiornati, togliendo la scelta tutto torna com'era, nell'elenco la scelta
  resta («Seleziona tutti» a metà); nessun errore JS. Corretto nello stesso giro: la card nel riquadro aveva il puntino
  dell'elenco (regola CSS che non trovava più la lista). Child 1.8.20.
  Poi Rubens, con una schermata di C100T «Via del Martirio»: «il tasto viene un pò tagliato non deve tagliarsi».
  Misurato: il riquadro finiva a filo del pulsante (881 px tutti e due), perché il margine sotto il pulsante usciva
  dal riquadro (margin collapsing); corretto con `display: flow-root` sul contenuto del riquadro (plugin). In più il
  riquadro di un cammino con 12 comuni arrivava quasi all'altezza della mappa (420 px) e rischiava di essere tagliato
  dal suo bordo: nel child immagine 2:1 e comuni su al massimo 3 righe con i puntini. Verificato su C100T a 1440 e a
  390 px: 14 px sotto il pulsante, riquadro alto 316 px e tutto dentro la mappa. Child 1.8.21.
- **Il comando dei test JS** del README del plugin, `node --test tests/js/`, con Node 22 non trova i file («Cannot
  find module …/tests/js»): ora è `node --test tests/js/*.test.js`, 47/47 con Node 20 e 22. Corretto anche nel piano.
- **Verificato** nel browser (Playwright, locale): «oliena» con 37 percorsi, selettore «Elenco | Mappa», indirizzo
  con `vista=mappa`, mappa 874×420, nota finale, elenco e pagine nascosti, «Seleziona tutti» presente; filtro
  «Itinerario» con la mappa aperta (2 percorsi, mappa aggiornata, `vista=mappa` resta); Invio su «Elenco» dalla
  tastiera; «Indietro» torna alla mappa; indirizzo aperto direttamente con `vista=mappa`; 390px senza scorrimento
  orizzontale; pagina inglese («Results view: List Map», «Framed on the area that contains all the results», «Zoom
  in on the map»); scarico KML di 2 percorsi dopo la modifica, e poi la mappa chiede solo i 35 percorsi che mancano;
  nessun errore JS (le sole richieste fallite sono gli annullamenti voluti, `ERR_ABORTED`). Test: `search-logic`
  30/30, `track-api` 6/6, `track-export` 9/9, `track-map` 2/2, `shards-fallback` PASS. Child 1.8.17 servito.

## Richieste di Giuseppe negli scrum e fonti rilette (10/11)

Rubens: «manca qualcosa che magari ha chiesto peppe durante gli scrum o altro?». Lette con NotebookLM le call dal
27/10 al 10/11 (notebook «scrum 2026-10-27» … «scrum 2026-11-10»; aggiunte al notebook del 28/10 le call delle 13:00 e
13:54). Attribuzione a Giuseppe affidabile (Alessio lo chiama «Peppe», il 29/10 e il 10/11). Le richieste che
riguardano la ricerca e non sono ancora fatte:
- **Dev** (10/11, verso 00:35): «c'hai ancora qua la ricerca. Non so cosa ti manca, ma buttala in dev. E dopo che
  l'hai buttato in dev puoi metterlo rilasciato»; «tutti in dev», «il wat non si tocca» (UAT si aggiorna solo quando
  «ci torna tutto»). È il Task 12, passo 4.
- **Revisione con Giuseppe** (04/11, verso 00:35): «inizializza per come ci siam detti e chiamami per una revisione,
  così siamo sicuri che siamo d'accordo».
- **Scheletro e grafica di base nel plugin** (10/11, verso 00:05): «lo scheletro diventa [wp-]package. In realtà
  potrebbe al momento anche la grafica, visto che non ne abbiamo una, ovviamente customizzabile con delle variabili di
  CSS. Quindi questa roba non va in qualcosa di custom, ma siccome Elastic non è custom, va in una situazione dove tu
  poi lo puoi accendere sugli altri WordPress»; e «io voglio che tutti abbiano questa disposizione, che poi il tasto sia
  giallo, rettangolare». Oggi il plugin non ha CSS: tutto l'aspetto della ricerca sta nel `style.css` del child
  (`.wmwp-*`). Il 04/11 Giuseppe aveva detto il contrario («il plugin VM package non può avere la grafica di
  Forestas»; grafica nel child): la frase del 10/11 è la più recente.
- Già coperte: ricerca prima della scheda del sentiero (29/10); mappa da una FeatureCollection con il componente Vue
  di wm-package (29/10, 10/11); niente geometrie in Elastic (10/11); WordPress che parla con lo shard solo via API
  (27/10); GPX non da sardegnasentieri.it (10/11, verificato). Il child theme di base «liscio» (04/11) è «a chiusura
  dei giochi», non di questo ticket.

Rilette anche le fonti del ticket (10/11). Resoconto del 12/08: il «modello Booking» è nel § 4.1 (passaggio
lista/mappa); il clic che mostra «prima le informazioni essenziali e solo su richiesta la scheda completa» è nel § 4.2
«Mappa interattiva», dopo la distinzione fra mappa di ricerca e mappa interattiva, e nel § 5 alla riga «Scheda
sentiero»; non dice che valga per la mappa della ricerca. «Peso visivo inferiore» degli itinerari e livello «non
acceso in modo predefinito» (§ 3.2) riguardano una mappa a livelli, non la ricerca. Nota 5 della tavola 2: «i
tracciati accesi nel colore del sentiero»; non dice un colore per tipo. La descrizione del ticket su Orchestrator
mette insieme Booking e il clic sotto § 4.1: da correggere se Rubens lo chiede.

**Colori per tipo e dati per un riquadro al clic (verificati sul dev, 10/11).** `strokeColor` c'è in ogni risultato
di Elasticsearch ma è vuoto su tutti i 769; `api/ec/track/multiple` non ha colori. I colori stanno nel `config.json`
dell'app, nei layer (`MAP.layers[].style.color`), gli stessi della webapp: Sentieri `#de1b0d`, Itinerari `#608d0d`,
Cammini di Sardegna `#1a416f`, Sentieri Accessibili `#e100ff`. Ogni risultato ha `layers`: 612 sentieri solo nel layer
Sentieri, 102 itinerari solo in Itinerari, 53 cammini solo in Cammini, 2 sentieri in Sentieri e Sentieri Accessibili.
Il verde degli itinerari del wireframe è `#58810B`, quello dell'app `#608d0d`. Dati già nel browser per un riquadro, su
769: immagine 769, stato di validazione 750, difficoltà 697, codice 589, da/a 343, avvisi 230 (es. B 401: «Scarsita
d'acqua», «Cinghiali», «Rischio nebbia»…), lunghezza, dislivello e tempo solo 3 (manca il calcolo dal DEM). La scheda
completa non ha ancora un indirizzo (`link_template` vuoto, dettaglio fuori da questo ticket).


## Review interne del 10/11 (wp-package e wp-forestas)

Due sessioni di review separate, una per repo, su richiesta di Rubens. Esito del primo giro: wp-package «approvato con
riserve», un bloccante; wp-forestas «da correggere», un bloccante. Tutto corretto nella stessa giornata, senza commit.
Secondo giro (10/11, pomeriggio): **approvati tutti e due**, nessun bloccante e nessun difetto nuovo. Restavano da
decidere con Rubens i filtri che restano attivi cambiando tab («Vedi tutti: 37 percorsi» nel tab «Tutti» apriva il tab
con 12, perché `validazione=avanzato` restava nell'indirizzo; provato con Playwright il 10/11) e il numero del tab, che
non tiene conto dei filtri. **Decisione** (Rubens, 10/11, «ok best practice» sulla proposta di togliere i filtri
cambiando tab): `goToTab()` azzera i filtri, per clic, frecce e «Vedi tutti»; «Indietro» ritrova la ricerca filtrata.
Provato con Playwright: «oliena» con «Avanzato» (12) → «Tutti» → «Vedi tutti: 37 percorsi» apre 37, senza chip; due
volte «Indietro» → di nuovo 12 con il chip «Avanzato». Il numero sul tab resta quello del testo cercato: dopo la
decisione è quello che il tab mostra appena aperto, e coincide con «Vedi tutti». Nota minore lasciata così: dopo «Deseleziona tutto» lo scarico annullato libera il pulsante
al giro successivo, quindi un clic su «Scarica» nello stesso istante va perso (azzerarlo subito farebbe sì che la
risposta dello scarico annullato cancelli quello nuovo).

**wp-package.**
- Bloccante: `?page=2` sulla pagina di ricerca dava 404, perché `page` è una query var pubblica di WordPress (su una
  pagina senza `<!--nextpage-->` `WP::handle_404()` risponde 404; Forestas non ne era toccato, usa `pagina`). Il
  predefinito ora è `pg`; `wmwp_search_params()` riporta al predefinito, con un avviso `_doing_it_wrong`, un nome
  vuoto, già usato o che è in `$wp->public_query_vars`; `wmwp_safe_facet_keys()` dà il prefisso `f_` alle chiavi dei
  filtri nella stessa situazione (anche in `meta_facet`). Test: `tests/php/search-params.php`.
- Cronologia e configurazione dell'app: scritte in «Divergenze» (Task 5 e Task 1), il codice resta.
- `popstate` ferma l'attesa dei 400 ms e rimette `typingEntry` a `false`: scrivere, tornare indietro e riscrivere
  crea un passo nuovo invece di sovrascrivere quello ripristinato.
- `selectable()` vuole anche `selection.summary`; `setStatus` fa leggere di nuovo allo screen reader una frase
  uguale alla precedente (un carattere invisibile alterno in coda); nel tab «Tutti» una source in errore con le altre a
  0 mostra l'errore invece di una riga vuota; una source in errore toglie anche i chip dei filtri attivi; «Deseleziona
  tutto» annulla lo scarico in corso.
- Memoria e richieste: le risposte di Elasticsearch restano in memoria per al massimo 6 testi (si toglie quello usato
  meno di recente; una risposta con tutti i percorsi è circa 2,5 MB), le ricerche del tab «Tutti» per al massimo 40;
  la mappa e lo scarico che chiedono le stesse tracce insieme fanno una richiesta sola, e se quella viene annullata chi
  aspettava la rifà. L'elenco `known` di `source-tracks.js` (un percorso per id) resta senza limite: è grande quanto il
  catalogo e serve a nome, km e dislivello dei percorsi selezionati anche dopo che la ricerca è cambiata.
- Pulizia: `localized` solo in `search-logic.js`; `chunk` tolto da `track-export.js` (lo usa solo `track-api.js`); il
  ripiego di `sprintf` tratta anche `%1$s`; il codice del percorso in `link_template` senza spazi; intestazioni dei test
  con `node --test tests/js/*.test.js`; guardia `ABSPATH` nei test PHP e `.gitattributes` che lascia `tests/` e
  `map/` fuori dall'archivio di GitHub da cui si installa il plugin; README («Interfaccia pubblica», mappa, test) e
  CHANGELOG allineati.
- Test nuovo `tests/js/source-tracks.test.js`: la source gira in una finestra finta con l'API finta (titolo e codice
  senza tab e spazi, indirizzo da `link_template`, `term_labels`, nome del file di un percorso, limite delle risposte).

**wp-forestas.**
- Bloccante: sulla pagina inglese tipo e stato di validazione dei percorsi restavano in italiano. Il plugin legge i
  nomi da `term_labels` della source (`L.termName()`), il child li dà in italiano e in inglese (vedi «Da fare dopo
  questo ticket» per i nomi inglesi provvisori).
- I link di Drupal `f[N]=cerca_contenuto_nel_sito:sentiero` e `:itinerario` aprono «Percorsi» con il filtro «Tipo di
  percorso»; più tipi si sommano nello stesso filtro.
- Pagine nei «Contenuti del sito»: prima 24, con le pagine di struttura senza testo (Home, News, Tutte le news,
  Eventi, Attività e comunicazioni…). Ora restano le pagine venute da Drupal (`_drupal_nid`) e quelle scritte in
  WordPress (senza `_wpf_pagina`); fuori le pagine che lo script `pagine` crea senza un nodo Drupal e la home: in
  locale 14 (curl di `forestas/v1/search?type=pagina`, 10/11). Drupal ne cerca 16 «pagine base» secondo la review.
- forestas, howto UAT «Tornare indietro»: l'header manda sempre a `?s=`, ed è il 302 del child a portarlo alla
  ricerca; overview allineata. Il «301» rimasto in queste note è diventato 302.

## Messa in opera su dev del 10/11

Rubens, 10/11: «puoi fare tutto e allineare i branch develop di wp-forestas e forestas, il plugin puoi mettere già su
main», poi «chiaramente segui la regola del team con pr e merge».

**Repo e PR:**
- `wp-package`: push di `main`, `develop` e del branch del ticket sul repo vuoto (`main` branch predefinito,
  pubblico); PR #1 verso `develop` con squash (`d4b643f`), PR #2 da `develop` a `main` con merge commit («Merge
  branch 'develop'», `9537a61`), `develop` allineato a `main` con un fast-forward. Il submodule `map/wm-package` è
  entrato nello squash insieme al lavoro: era nato con il plugin in questo ticket, in un commit suo del branch;
- `WP_PACKAGE_REF=9537a61d9beedaa1ee7c51b9c5d4e785775a7698` in `init-wordpress.sh`, provato in locale senza
  `WP_PACKAGE_DIR` (piano, Task 12 passo 3);
- `wp-forestas`: PR #18 verso `develop` con squash (`8eb2aa0`);
- `forestas`: PR #47 (documenti e `local.compose.yml`) e PR #48 (solo il puntatore di `wp-forestas`, come #40 di
  oc:8760), tutte e due con squash; `develop` a `121d167`. La CI `laravel-tests` di #47 è fallita prima dei test,
  all'avvio del container Postgres (`postgis/postgis:17-3.5-alpine`, «invalid character in extension owner»):
  è un errore dell'ambiente della CI, lo stesso delle PR #46 e #44 mergiate prima.

**Sul server dev** (`ssh dev.forestas`, `/var/www/html/forestas`, solo pull):
1. `git pull --ff-only origin develop` e `git submodule update --init --recursive`: si è spostato solo
   `wp-forestas` (`wm-package` era già al puntatore di `develop`);
2. copia del `.env` di wp-forestas (`.env.bak-oc8952-<data>`) e del database (`backup/prima-oc8952-<data>.sql`,
   `chmod 600`), poi nel `.env` `WP_PACKAGE_SHARD=forestasdev`, `WP_PACKAGE_APP_ID=1`, `WP_PACKAGE_DIR=`;
3. `scripts/wordpress-up.sh`: «scarico wp-package al commit 9537a61…», «attivo il plugin wp-package», «imposto
   wmwp_shard dal .env», «imposto wmwp_app_id dal .env»; cartella del plugin `755`, `.wpf-ref` giusto;
4. `applica --conferma`: una sola differenza, `template-pagina-ricerca` creato; dopo, «nessuna differenza»;
5. `pagine --conferma`: 2 pagine create (`/cerca-nel-sito` in italiano e in inglese), il resto invariato;
6. `wm-package` (wp-geohub) disattivato e cancellato, dopo aver controllato che nessun contenuto usa i suoi
   shortcode (`wm_grid_poi`, `wm_grid_track`, `wm_single_poi`, `wm_single_track`).

**Verifica su `https://wp.forestas.dev.maphub.it`** (Playwright e curl, 10/11):
- `/cerca-nel-sito/` e `/en/cerca-nel-sito/` 200; `/?s=oliena` e `/en/?s=trail` in 302 alla ricerca; il vecchio
  link di Drupal dei sentieri in 302 a `?tipo=percorsi&percorso=route-type-trail`; `?tipo=percorsi&pagina=2` 200;
- «perda liana»: 42 percorsi e 4 contenuti, in inglese «All», «Trails (42)», «Site content (4)»; stati in inglese;
- mappa e scarico insieme: 37 tracce, nessuna doppia; annullamento; errori dell'API; 14 pagine fra i contenuti;
- mappa da tastiera e tooltip; barra della selezione a 1440, 1280, 1100, 768, 390 e 320px, con il «torna su» mai
  sopra la barra;
- servite le versioni giuste: child `1.8.25`, plugin `0.1.0.<data del file>`.

Restano da fare a mano, da Rubens: il confronto con la tavola 2 del wireframe e la prova con uno screen reader vero
(VoiceOver o NVDA). La «Pagina di esempio» c'è ancora anche su dev («Da fare dopo questo ticket»).

## Ciclo da zero del 10/11

Rubens, 10/11: «hai provato a fare un test dove si riparte da zero con la crezione del wordpress inclusa questa
nuova sezione?». Non era stato fatto: l'ultimo ciclo completo era del 29/10 (oc:8760), prima di questo ticket. Il
commit del plugin non serve in locale: con `WP_PACKAGE_DIR` l'init collega il clone di sviluppo, modifiche non
committate comprese; la strada dell'archivio di GitHub (`WP_PACKAGE_REF`) si prova su dev dopo il push.

Fatto alle 17:36-18:00, passi 1-5 dell'howto, con copia del database e ritratto presi prima:
- init: «collego wp-package al clone di sviluppo», «attivo il plugin wp-package», «imposto wmwp_shard dal .env»,
  «imposto wmwp_app_id dal .env»; apply riuscito; avvisi solo quelli attesi (voci di menu senza pagina, `HTTP_HOST`
  del core);
- `pagine --conferma`: 46 pagine (le 44 di prima più `/cerca-nel-sito` in italiano e in inglese), 99 news, 14
  eventi, 121 «da sapere», mancanti i 2 media noti della news 4606; 12 minuti;
- `applica --conferma`: menu collegati, 18 voci senza menu cancellate (l'howto ne diceva 17: `config/menu.json` è
  cambiato il 02/11 con oc:8760, non con questo ticket); il giro dopo «nessuna differenza»;
- `pagine` dopo: tutto «invariata» salvo la news 4606; ritratto identico a quello di prima; conteggi per tipo e
  lingua e 774 allegati identici;
- ricerca: «perda liana» 42 percorsi e 4 contenuti in italiano e in inglese; redirect da `?s=` e dai link di Drupal;
  pagina 2, mappa e scarico insieme (37 tracce, nessuna doppia), annullamento, errori dell'API come prima.

Trovato nel ciclo e scritto nell'howto, «Problemi incontrati»: `bin/wordpress-config.sh` lanciato da
`/Volumes/WebmappDev/Sites/…` dice «nessun container WordPress avviato monta …» quando il compose è partito da
`~/Sites/…`, un link simbolico alla stessa cartella. Lo script confronta i percorsi e non le cartelle. Si lancia dal
percorso del mount, o con `WPF_CONTAINER=wordpress-forestas` (provato). Correggere lo script, cioè confrontare le
cartelle con `pwd -P`, è un lavoro a parte: lo script non fa parte di questo ticket.

## Audit di accessibilità del 10/11

Chiesto da Rubens il 10/11: «hai controllato lo stato dell'accessibilità di tutta la pagina cerca-nel-sito?
accessibilità e screen reader devono essere in linea con le linee guida ufficiali». Prima di allora c'erano solo le
prove dei singoli requisiti [UX]; la vista mappa, il riquadro e la barra della selezione, arrivati dopo, non erano
stati provati per intero.

**Riferimento.** Per un ente pubblico valgono le linee guida AgID sull'accessibilità degli strumenti informatici, che
rimandano alla norma EN 301 549 v3.2.1, e questa a WCAG 2.1 livello AA (ricerca del 10/11; le linee guida AgID del
marzo 2026 sui servizi usano lo stesso riferimento). Una versione nuova della norma con WCAG 2.2 è annunciata, ma non
ho trovato conferma che sia in vigore: i criteri nuovi di WCAG 2.2 AA si sono controllati a parte, come margine.

**Come.** Sul WordPress locale, con Playwright (script nello scratchpad della sessione):
- axe-core 4.10.2 (regole WCAG 2.0/2.1 A e AA, e 2.2 AA a parte) in 12 stati: «Tutti» senza testo e con «oliena», tab
  «Percorsi» con filtri, «Contenuti del sito», due percorsi selezionati, mappa con il riquadro aperto, nessun
  risultato, API che non risponde, filtri a schermo intero a 390px, mappa a 390px, inglese «Percorsi» e «Tutti»;
- tastiera: 75 Tab dalla cima del tab «Percorsi» con la barra della selezione aperta, frecce/Home/Fine sui tab, focus
  dopo «Deseleziona tutto», «Pagina 2», «Vedi tutti», «Mappa», dialog dei filtri (giro del Tab, Esc);
- contrasti calcolati sui colori e sugli sfondi effettivi di 50 elementi, compresi bordi dei controlli (1.4.11) e
  anelli del focus;
- reflow a 320px (1.4.10), spaziatura del testo dell'utente a 1440 e a 390px (1.4.12), tooltip della mappa (1.4.13);
- screen reader: albero di accessibilità di Chromium (ruoli, nomi e stati di tab, filtri, righe, barra, landmark),
  non VoiceOver o NVDA veri: il browser delle prove non ha finestra. La prova con uno screen reader vero resta alla
  verifica su dev.

**Trovato e corretto** (dopo le correzioni axe non trova più violazioni WCAG 2.1 in nessuno dei 12 stati, né WCAG 2.2
sulla mappa a 390px senza il riquadro aperto):
| Problema | Criterio | Correzione |
|---|---|---|
| Durante il caricamento i risultati erano a opacità 0,55: testo grigio a 2,71:1 | 1.4.3 | testo pieno e una linea animata in cima ai risultati (ferma con `prefers-reduced-motion`); `aria-busy` resta |
| Testo grigio delle righe selezionate (#767676 su #F6FAFF) a 4,33:1 | 1.4.3 | `--wmwp-ink-3` a #6B6B6B: 5,33:1 su bianco, 5,08 sulla riga selezionata, 4,89 su `--wmwp-surface` |
| Con Tab il focus finiva sotto la barra della selezione fissa (righe in fondo, pagine, filtri in basso) | 2.4.7 (2.4.11 in WCAG 2.2) | il plugin dà a `<html>` uno `scroll-padding-bottom` alto quanto la barra (tecnica C43), anche al ridimensionamento |
| Focus quasi invisibile sul formato già scelto (GPX su fondo bianco) | 2.4.7 | contorno blu dentro la voce |
| La mappa non si spostava da tastiera: il componente spegne le interazioni da tastiera | 2.1.1 | il wrapper (`map/src/index.js`) dà alla mappa `tabindex="0"`, `role="application"`, un nome con i tasti, e aggiunge KeyboardPan e KeyboardZoom |
| La tooltip al passaggio del mouse non si chiudeva con Esc e spariva portandoci sopra il puntatore | 1.4.13 | il wrapper la nasconde con Esc finché non si passa su un'altra linea, la rende raggiungibile (eventi fermati sulla tooltip, distanza dal puntatore da 10 a 4px) |
| «GeoJSON» tagliato nella barra con la spaziatura del testo aumentata | 1.4.12 | i formati vanno a capo |
| Lo screen reader leggeva il numero del filtro attaccato al nome («Sentiero34») | 1.3.1, buona pratica | testo nascosto: «Sentiero, 34 percorsi» |
| Due landmark «search» senza nome (header e pagina) | buona pratica ARIA | il form della pagina prende il nome dalla sua etichetta (`aria-labelledby`) |

La mappa si attiva con Tab ma i percorsi della mappa non si scelgono da tastiera (il riquadro si apre al clic): la
stessa informazione e la stessa selezione sono nella vista «Elenco», a un clic dal selettore, ed è su questo che si
regge il criterio 2.1.1 per la mappa (giudizio mio, da confermare se Forestas fa fare un audit esterno). Allo stesso
modo il tipo del percorso sulla mappa si distingue solo dal colore (1.4.1), ma è scritto nel riquadro, nelle card e
nel filtro «Tipo di percorso»; una legenda aiuterebbe tutti, ed è da decidere con il dettaglio della traccia.

**Trovato fuori dalla ricerca, non corretto qui** (header di tutto il sito, oc:8757, identico a Drupal): con la
spaziatura del testo dell'utente (1.4.12) a 1440px il menu principale esce dalla pagina di 89px («NEWS»).

**Verificato e a posto:** lingua della pagina (`it-IT`, `en-US`), titolo, link «Vai al contenuto», landmark con nome,
`h1` unico, tab con `aria-selected` e Tab che entra solo sul tab scelto, filtri in `fieldset` con `legend`, un solo
`role="status"` per i risultati e uno per la barra, dialog dei filtri con `aria-modal`, focus che resta dentro ed Esc
che lo riporta al pulsante «Filtri», focus spostato sui risultati dopo «Pagina 2» e sul tab dopo «Vedi tutti», nessun
id doppio, nessuno scorrimento orizzontale a 320px, contrasti di testi, badge, controlli e anelli del focus sopra le
soglie.

## Info per altri ticket

- **Reindirizzamenti del lancio: i QR già stampati.** Il capitolato (WEBSITE-3 e piano di test) chiede i 301
  da tutti gli URL legacy, compresi `/sentieri/…` e `/sentiero/…` dei QR già distribuiti; la riunione SAL del
  01/04/2026 chiede di mantenere le URL dei pannelli, come `/carte/supramontes`. Con l'indirizzo stabile di
  questo ticket, `/carte/<nome>` può diventare un 301 verso
  `/cerca-nel-sito?tipo=percorsi&carta=<identifier del termine>`; come ricavare l'identifier dal termine
  Drupal si decide insieme alle carte (punto aperto 3 di oc:8932). Da chiedere a Forestas: l'elenco degli URL stampati e la soglia dei 301 (impegno del committente,
  resoconto del 12/08). Fonti nella sezione «Codici QR e stabilità degli indirizzi».
- **Il link delle card dei percorsi dipende dal dettaglio della traccia.** In wp-geohub la card porta a
  `/track/<slug>/` (`grid-track-filters.js:736`), che esiste solo se la sincronizzazione ha creato il post
  `track`; senza, risponde 404. Dove porterà nel sito nuovo lo decide il lavoro sul dettaglio della traccia,
  non questo ticket.
- **Le traduzioni di wp-geohub non si caricano.** `load_plugin_textdomain()` riceve un percorso assoluto
  (`functions/functions.php:13`), che WordPress antepone a `WP_PLUGIN_DIR` (`wp-includes/l10n.php:1009`), e i
  file si chiamano `wm-package-it.mo` invece di `wm-package-it_IT.mo`.
- **L'OR dentro la stessa faccetta in osm2cai è stato aggirato nel browser**: più chiamate a Elasticsearch,
  una per valore, unite in JS (`grid-track-filters.js` 265-299, non committato). È la stessa lacuna dell'API
  segnata nel ticket.
- **Percorribilità: stato di validazione o scala separata?** (11/11) Nello scrum dell'11/11 Giuseppe ricava la
  percorribilità dallo stato di validazione del catasto (livello 4 «Percorribile» e superiori), senza toccare
  Elastic. `docs/features/8783-sardegna-sentieri-wireframe-v3/decisioni.md` invece la definisce come scala 0–5
  separata, chiesta da Forestas e pianificata in oc:8932 sul modello `HikingRoute` di osm2cai2. Le due cose
  convivono: il filtro di questa pagina legge oggi `terms.validation-status`, e con la scala separata cambia solo
  il campo letto. Comunicato all'agente di OC:8916 l'11/11.
- **La mappa nei dettagli di track, POI e layer** (oc:8952, 10/11): il plugin ha già il componente. In una pagina
  basta lo shortcode `[wmwp_map tracks="<id>"]` (tracce dello shard per id) o `[wmwp_map geojson="<url>"]`; da JS,
  `window.wmWpMap.mount()` dopo aver accodato `wmwp-map`. Il profilo altimetrico del componente si accende con
  `slopeChart: true` (serve la quota, che oggi sul dev è 0 dove il DEM non è calcolato). README del plugin, «Mappa».

## Divergenze dal piano, task per task

### Task 1: niente configurazione dell'app (in esecuzione, scritto nei documenti il 10/11)

Il piano portava nel plugin `includes/config.php` (da `wm_get_config_api_url`, `wm_fetch_remote_config`,
`wm_get_default_config` e `wm_update_config_id`), `config/wm_default_config.json` e `hooks/transient.php`. Nel
plugin non ci sono: la ricerca usa solo shard e app id (`wmwp_elastic_url()` e `wmwp_app_param()` in
`includes/settings.php`), e la pagina delle impostazioni non ha avvisi. Quando la mappa ha avuto bisogno del
`config.json` dell'app (carta di base e colori dei layer, Task 10b), si è scritto codice nuovo, più piccolo:
`wmwp_app_config_url()` e una sola lettura in cache in `includes/map.php`. Le parti del plugin di partenza restano
recuperabili dalla copia indicata in «Cosa resta fuori». Trovato dalla review di wp-package del 10/11.

### Barra della selezione riordinata, oltre la tavola 2 (10/11)

Rubens, 10/11: «ora il box per i download lo so che hai seguito il design del wirefrime, però possiamo migliorarlo in
questa fase mi sembra tutto distribuito in maniera disordinata»; sulla proposta, con le schermate prima e dopo: «si
applicala». Com'era (tavola 2, `.selbar2`), a 1440px:
- due righe con allineamenti diversi: conteggio a sinistra, «Deseleziona tutto» a destra ma a 90px dal bordo;
- sotto, l'etichetta «TRACCE» in una colonna di 130px, i formati e il pulsante, con circa 270px vuoti a destra;
- i 90px erano lo spazio lasciato al «torna su» di Impreza, che copriva la barra;
- su telefono «GeoJSON» andava a capo e il pulsante non prendeva la larghezza.

Com'è ora (solo `style.css` del child, colori e misure della tavola restano):
- desktop: una riga alta 64px invece di 106. A sinistra il conteggio con «Deseleziona tutto», che agisce sulla
  selezione; a destra formati e pulsante, alti 40px, l'azione principale per ultima. L'avanzamento dello scarico sta
  sotto, su tutta la larghezza. Fino a circa 1000px di barra formati e pulsante vanno a capo restando a destra (a
  768px);
- telefono (fino a 600px): conteggio e «Deseleziona tutto» sulla stessa riga, i tre formati in tre colonne uguali, il
  pulsante su tutta la larghezza; formati e pulsante alti 44px. A 320px i formati vanno su due righe;
- «Tracce» non si vede più, ma resta il nome del gruppo per lo screen reader: nella tavola separava le tracce da
  «Mappa e schede», che qui non c'è;
- il «torna su» sale sopra la barra (`bottom` del child con `--wmwp-selection-height`, una variabile che il plugin
  scrive su `<html>` e tiene aggiornata con un ResizeObserver). Nascosto, Impreza lo sposta del 150% in giù: alzato
  sarebbe finito sopra la barra, quindi nello stato nascosto scende anche dell'altezza della barra;
- con la barra più bassa il Tab arrivava con l'elemento a filo del suo bordo e il contorno del focus (5px) restava
  coperto: lo `scroll-padding-bottom` del plugin ora lascia 16px in più.

Provato con Playwright a 1440, 1280, 1100, 768, 390 e 320px:
- nessuno scorrimento orizzontale;
- «torna su» mai sopra la barra, né nascosto né visibile;
- formati non tagliati con la spaziatura del testo (WCAG 1.4.12);
- 75 Tab senza focus coperto;
- axe senza violazioni nello stato con la selezione;
- scarico, annullamento e selezione dal riquadro della mappa come prima.

### Task 10b: mappa più alta dei 420px della tavola 2 (10/11)

Rubens, 10/11: «la mappa può prendere più spazio in altezza? vedo che c'è molto spazio in basso». Nella vista mappa la
colonna dei filtri è alta 1033px (5 valori per filtro e «Mostra altro», misurato con «oliena»), la mappa 420px come la
tavola 2: a 1440×900 restavano circa 350px vuoti sotto la mappa. Ora su desktop (da 1025px) la mappa è alta
`clamp(420px, 100vh − 47px − 24px, 760px)` (47px è la riga compatta dell'header, `style.css` del child): 760px a 1440×900
e a 1920×1080, dove finisce con la colonna dei filtri, 649px a 1280×720, dove si vede intera nella finestra. Sotto
1025px resta 420px: una mappa alta quanto lo schermo prenderebbe ogni trascinamento del dito e la pagina non
scorrerebbe più.

Provato prima e scartato: la mappa che riempie lo spazio della colonna (colonna principale flex). Comparendo con la
prima scelta dal riquadro, la barra della selezione prendeva parte di quello spazio e la mappa si accorciava di 122px:
il riquadro e il pulsante «Togli dalla selezione» salivano di 61px sotto il puntatore (Playwright, 10/11). Con
un'altezza propria il riquadro resta fermo. Il componente riceve sempre `height` 420: l'altezza vera la dà il CSS del
child al suo contenitore (`.map-container`), e OpenLayers si ridimensiona da solo. Schermo intero, mappa da tastiera e
tooltip provati di nuovo dopo la modifica.

### Task 5: un passo della cronologia per ogni ricerca scritta (in esecuzione, scritto nei documenti il 10/11)

Il piano diceva `replaceState` mentre si scrive e `pushState` solo con Invio. Così chi scrive e aspetta i risultati,
senza Invio (la ricerca parte da sola dopo 400 ms), non ha nessun passo nella cronologia: «Indietro» lo porta fuori
dalla pagina invece che alla ricerca di prima. Il codice (`setText` in `assets/js/search.js` del plugin) fa invece:
la prima pausa crea il passo (`pushState`), le pause successive e l'Invio lo aggiornano (`replaceState`); dopo
l'Invio, o dopo un cambio di filtro, tab, pagina o vista, il testo successivo crea un passo nuovo. Quello che si
scrive di seguito senza Invio resta un passo solo: con «oliena», una pausa e poi «aggius», «Indietro» torna alla
pagina senza testo (provato con Playwright il 10/11). «Indietro» torna alla ricerca precedente, non a ogni parola
scritta a metà, che è lo scopo del punto 1 del challenge. Filtro, tab, pagina e vista fanno `pushState`
come da piano. Un «Indietro» durante l'attesa dei 400 ms annulla la ricerca in attesa (`popstate` ferma il timer),
così la pagina non torna avanti da sola. La scelta era nel codice dal Task 5 e la review di wp-package del 10/11
l'ha trovata non scritta: overview e piano sono allineati.

### Commit del plugin: uno solo invece di uno per Task (10/11)

Il piano chiede un commit per Task nel repo `wp-package` (Task 1, 3, 4, 5, 6, 7). Il codice è entrato con la storia
nuova del repo (sezione «Storia del repository»), quando era già finito: un commit solo, `72330e1 feat(oc:8952): ricerca
unica del sito, scarico dei percorsi e mappa dei risultati`, poi `b343cae` (submodule) e `5818402` (build dal
submodule). Con lo squash and merge della PR verso `develop` il risultato su `develop` è comunque un commit. I passi
di commit del plugin nel piano sono spuntati con questo rimando; quelli di wp-forestas e forestas restano aperti.

### Nomi nel codice (prima dell'esecuzione, 09/11)

La fase di esecuzione di wm-plan vuole gli identificatori nuovi in inglese. Nel piano e nell'overview sono stati
rinominati prima di cominciare: `[forestas_ricerca]` → `[forestas_search]`, `forestas/v1/ricerca` →
`forestas/v1/search` (argomenti `q`, `type`, `page`, `lang`; chiave `by_type`), `inc/ricerca.php` e
`assets/js/ricerca.js` → `inc/search.php` e `assets/js/search.js`, `forestas_search_url()`,
`forestas_search_from_drupal()`, source `content`, `compose.sviluppo.yml` → `compose.local.yml`,
`docker/wp-package-empty`, `/opt/wp-forestas/wp-package-dev`, `WMWP_DEV_DIR`. Restano in italiano i valori
pubblici dell'indirizzo decisi da Rubens (`search_api_fulltext`, `tipo`, `pagina`, `attivita`, `difficolta`, `dove`,
`contenuto`, `percorsi`, `contenuti`), i valori di configurazione di `config/pagine.json` (`template: ricerca`, come
`testata`, `mappa`…) e gli identificatori italiani già presenti nel child, che non si rinominano.

### Design della pagina: la tavola 2 del wireframe (prima dell'esecuzione, 09/11)

Rubens, 09/11, alla proposta di prendere Drupal come riferimento: «no il design è questo
https://claude.ai/artifact/4T3ZRSP1LCrbCuJdNuEGXQ intanto parti da questo poi vediamo». Il riferimento è la tavola
2 «Pagina di ricerca unica» (sezione `s1` della pagina, «Desktop 1440»), non `/cerca-nel-sito` di Drupal. La
pagina pesa 12,5 MB: la lettura normale del tool Artifact si ferma al CSS, l'HTML intero si scarica con
`path: "index.html"`. Cambiano: il requisito dell'aspetto nell'overview (colonna dei filtri di 238px, righe dei
percorsi, riquadro «Indirizzo di questa ricerca», riga del totale «28 sentieri · 179,5 km complessivi», token e
colori dei tipi), la verifica finale (confronto con la tavola invece che con Drupal), i Task 4-6 (template
`row.php`, `layout` per source, campi `code` e `badge` della card, testi del campo per tab) e il Task 10. Restano
fuori, come già deciso, il selettore Elenco/Mappa, le caselle di selezione e la barra di scarico. La tavola mostra
solo il desktop: sotto 1024px i filtri a schermo intero restano come su Drupal.

### Task 10: barra dei tab senza scorrimento verticale (review-gate, 09/11)

Rubens: «la tab dove vedo tutti percorsi e contenuti del sito hanno uno scroll verticale non deve esserci». La
barra dei tab aveva `overflow-x: auto` (per scorrere in orizzontale su schermi stretti) e il tab attivo
`margin-bottom: -1px` per sovrapporre la sottolineatura alla linea grigia, come le schede della tavola 9: con un
overflow non visibile su un asse anche l'altro diventa scorrevole, e quel pixel in più (contenuto 45px in una barra
di 44px) mostrava una barra di scorrimento verticale. Ora la linea grigia è un'ombra interna della barra, il tab non
ha margini negativi e `overflow-y` è `hidden`: stessa resa, nessuno scorrimento verticale (verificato a 1440 e
390px).

### Cache del browser: Version del child e versione degli script del plugin (review-gate, 09/11)

Dopo la correzione della barra dei tab Rubens vedeva ancora lo scorrimento (screenshot): il suo browser usava
`style.css?ver=1.8.0` dalla cache, perché la `Version` del child non era stata rialzata dopo le modifiche al CSS
(regola del child, `forestas_versione()`), e la verifica era stata fatta con un browser senza cache. La `Version`
ora è 1.8.1. Nel plugin la versione degli script non è più solo `WMWP_VERSION`, ma `WMWP_VERSION` più la data di
modifica del file (`wmwp_asset_version()`): un JS cambiato cambia indirizzo anche senza una versione nuova del
plugin.

### Task 5 e 10: niente riquadro «Indirizzo di questa ricerca» (review-gate, 09/11)

Il riquadro della tavola 2 con l'indirizzo della ricerca (nota 4) era stato preso per un componente della pagina.
Rubens, 09/11: «ma quello era un esempio per far capire al cliente come si mostra l'url della ricerca». Tolto da
`templates/search.php` e da `search.js` del plugin e dal CSS del child (`Version` 1.8.2); la stringa «Address of
this search» è uscita da `.pot` e `.po`. L'indirizzo della ricerca resta quello della barra del browser, con il
canonical che ne porta i parametri. Il requisito dell'aspetto nell'overview non lo nomina più.

### Task 10: righe dei percorsi senza codice (review-gate, 09/11)

Rubens (screenshot): alcune righe dell'elenco avevano il nome «tutto schiacciato a sinistra», per esempio
«'Untanas de Su Tassu - Su Tassu (G 111B)». Sono i percorsi con `ref` vuoto nell'API: sul dev 180 su 769. Il JS non
mette l'elemento del codice, e nella griglia della riga (96px, nome, stato) il nome finiva nella prima colonna. Ora
codice, nome e stato hanno la loro colonna nel CSS del child, anche sotto 600px (`Version` 1.8.3). I codici dell'API
hanno anche formati diversi (418 «Z-NU-…», 57 solo numero, come «335»): segnalato alla sessione di oc:8916.

### Segnalazioni per il ticket delle API senza conferma (09/11, sera)

La sessione di oc:8916 non risponde più (chiusa). Restano da far registrare nell'anteprima del ticket delle API,
sezione «Ricerca: Elasticsearch»; le prime due sono state mandate, senza conferma della versione:
1. un testo più corto trova meno percorsi: `query=sent` 68, `query=sentiero` 687 (QueryStringQuery con `*testo*`);
2. le parole AND, OR, NOT in maiuscolo danno 500 (`sentiero AND`; `sentiero and` → 352), e un trattino isolato azzera
   i risultati (`Jacca - Genna` → 0, `Jacca Genna` → 1); lato plugin sono già aggirati in `cleanQuery`;
3. non mandata: `ref` vuoto per 180 percorsi su 769 e formati diversi (418 «Z-NU-…», 57 solo numero come «335»,
   altri «G602C», «B-401»); da decidere quale codice mostrare e se l'API lo dà normalizzato.
4. non mandata: GPX e KML di una traccia rispondono ancora 500 sul dev (09/11, traccia 343: `api/ec/track/download/343.gpx`
   e `.kml`, `api/ec/track/343.gpx` e `.kml`; il GeoJSON risponde 200). Nel log della pagina d'errore:
   `Class "Symm\Gisconverter\Geometry\Linestring" not found`, dalla libreria `ndamiens/gisconverter`. La webapp
   (`1.forestasdev.webmapp.it`) li scarica lo stesso perché non usa quelle rotte: converte nel browser il GeoJSON della
   traccia, in GPX con `@dwayneparton/geojson-to-gpx` e in KML con un'altra libreria (letto nel codice compilato
   `main.442ec1500d68a237.js`, funzione `export`).
   Aggiornamento del 10/11: la sessione di oc:8916 l'ha portata in oc:8953 (wm-package, PR draft #324,
   `feature/oc-8953-wm-package-download-gpx-kml-tracce-errore`; al 10/11 un solo commit, `e729ac0f`, con
   l'overview). Cosa cambia per questa pagina: «Da fare dopo questo ticket».

### Stato di validazione nell'API del dev: etichetta e filtro a fasce (09/11, sera)

Rubens: «il dato che vedo nell'esempio del wireframe "validato" "percorribile" etc al momento non si può mostrare
perchè non c'è?», poi «procedi». Il dato c'è dal 09/11 nell'API del dev: `terms["validation-status"]` su 750
percorsi su 769 (In pre-accatastamento o manutenzione 238, Percorribile 215, In revisione-validazione 193, Validato
45, Non percorribile 41, Non verificato 18), nomi solo in italiano. Fatto:
- etichetta dello stato in ogni riga: chiave `badge_field` della source `tracks` (`terms.validation-status` nel
  child), `firstTerm()` in `search-logic.js`, colori di Drupal indicati nel wireframe (verde `#0E7D2C`, arancio
  `#F59B23` con testo `#333`, rosso `#B70827`, grigio `#595959` per Non verificato; contrasti da 5,27 a 7,00);
- filtro «Validazione catastale» a fasce con la nuova opzione `groups` dei filtri (test in `search-logic.test.js`):
  Avanzato 45, Intermedio 646, Base 59 sul dev; «Non percorribile» in Base, supposto livello 1.
Nello stesso giro: sul dev il percorso 473 ha già lunghezza, dislivello e tempo dal DEM (1,1 km, +2 m, 19 min) e la
somma dava «769 sentieri · 1,1 km complessivi»: ora i km complessivi compaiono solo se tutti i percorsi della ricerca
hanno la lunghezza. Restano uguali nell'API: nessuna compressione, `searchable` nella risposta, conteggi limitati a
10, errore 500 sui caratteri speciali, nessun OR dentro un filtro.

### Tipo di percorso, «Dove» diviso, comuni nella riga, pertinenza (09/11, sera)

Rubens ha scelto tutte e quattro le aggiunte proposte («c'è altro che possiamo aggiungere?»):
- **Tipo di percorso** da `terms.route-type`: Sentiero 614, Itinerario 102, Cammino 53; chiave `percorso`.
- **«Dove» diviso e ripulito.** `taxonomyWheres` sul dev ha 306 voci: «Sardegna» (su tutti i 769 percorsi), 60 codici
  dei settori CAI («Z» + tre lettere + numero, es. «ZNUB4»), 7 macro aree «Area …», 8 province (Cagliari, Gallura
  Nord-Est Sardegna, Medio Campidano, Nuoro, Ogliastra, Oristano, Sassari, Sulcis Iglesiente) e 230 comuni (controllati
  a mano i nomi composti). Tre filtri con le nuove opzioni `include`, `include_pattern`, `exclude`, `exclude_pattern`;
  province in `FORESTAS_SEARCH_PROVINCES` del child. Limiti: le aree della tavola sono 39 (punto aperto 1), qui 7;
  Cagliari, Nuoro, Oristano e Sassari, nomi sia di provincia sia di comune, stanno solo fra le province; un nome nuovo
  di provincia nell'API finirebbe fra i comuni. La soluzione vera resta l'API con i luoghi per tipo (già chiesta).
- **Comuni nella riga** con la chiave `meta_facet` della source (`comune`): «Seui, Gairo · …», come nella tavola.
- **Pertinenza** con `rankByText()`: prima il testo intero nel nome, poi tutte le parole, poi gli altri, a parità
  l'ordine dell'API; senza accenti né maiuscole. «perda liana»: i primi quattro hanno «Perda Liana» nel nome.
Test 28/28 (3 nuovi RED→GREEN), child `Version` 1.8.5, canonical con le chiavi nuove.

### Niente riquadro della regola OR/AND (review-gate, 09/11)

Rubens: «togli anche il box con scritto "OR dentro lo stesso filtro, AND fra filtri diversi." anche questo era un
esempio per il cliente». Come il riquadro dell'indirizzo, la nota 3 della tavola 2 spiegava la regola al cliente: la
regola resta nel comportamento dei filtri, non scritta in pagina. Tolto da `templates/search.php` del plugin (la
stringa esce da `.pot` e `.po`) e dal CSS del child (`Version` 1.8.6). Nel messaggio precedente l'avevo data per
richiesta dal ticket, che dice «La regola dei filtri scritta in pagina»: anche quella frase della descrizione viene
dalla nota del wireframe.

### Filtri attivi sopra i risultati (review-gate, 09/11)

Rubens: «il tasto che toglie i filtri al momento è poco visibile», poi «ok procedi con la A best practice». Drupal
non ha un comando per togliere tutti i filtri (`show_reset_link: false` nelle faccette), e nemmeno le liste di news
ed eventi del child. Fatto: una riga «Filtri attivi:» sotto il totale con un'etichetta per ogni valore scelto (bordo
blu, altezza minima 32px, nome accessibile «Togli il filtro Attività: Mountain Bike») e «Togli tutti i filtri»; il
link in fondo alla colonna dei filtri è tolto; sotto 1024px la riga si vede con il pannello chiuso e il pulsante dice
«Filtri (2)». Riferimento: Baymard Institute, ricerche sull'usabilità dei filtri (filtri applicati sopra i risultati).
Nella verifica sono venuti fuori due difetti, corretti nello stesso giro:
- spuntando una casella con la tastiera il focus finiva sulla pagina, perché i filtri si ridisegnano: ora torna sulla
  stessa casella (e dopo un clic su un'etichetta va su quella che ne prende il posto);
- sotto 600px l'etichetta lunga dello stato («In pre-accatastamento o manutenzione») schiacciava il nome: ora codice,
  nome e stato stanno uno sotto l'altro.
Child `Version` 1.8.8; traduzioni «Filtri attivi:» e «Togli il filtro %1$s: %2$s».

### Spazi fra campo, totale, filtri attivi ed elenco (review-gate, 09/11)

Rubens: «darei un po' più spazio tra filtri, ricerca ed elenco, mi sembrano troppo vicini». Misure a 1440px, prima
e dopo: campo → totale 14 → 28px, totale → filtri attivi 6 → 10px, filtri attivi → primo risultato 12 → 28px, totale
→ primo risultato senza filtri attivi 6 → 20px. CSS del child, `Version` 1.8.9.

### Totale sopra l'elenco, sotto i filtri attivi (review-gate, 09/11)

Rubens: «ok procedi best practice». Ordine nel tab di una source: campo → filtri attivi → totale → elenco, con il
numero del totale in grassetto come nella tavola 2 («**4** sentieri»; il testo letto dagli screen reader resta la
frase intera). Spazi misurati a 1440px: campo → filtri attivi 28px, filtri attivi → totale 10px, totale → elenco 20px;
senza filtri attivi campo → totale 28px. Child `Version` 1.8.10.

### «percorsi» invece di «sentieri» nel totale e in «Vedi tutti» (review-gate, 09/11)

Rubens: «la label la teniamo sentieri o la allineamo con percorsi? cosa dice la best practice», poi «ok procedi best
practice». La pagina dice «Percorsi» nel tab, «Cerca un percorso» nel campo e «Tipo di percorso» nel filtro, mentre il
totale e «Vedi tutti» dicevano «sentieri»; con «Tipo di percorso: Cammino» il totale diceva «53 sentieri». Ora `nouns`
della source `tracks` nel child è «%s percorso / %s percorsi» (inglese «trail / trails»). Riferimento: Jakob Nielsen,
10 euristiche di usabilità, «Consistency and standards». La tavola 2 scrive «28 sentieri» in un esempio in cui i
risultati erano tutti sentieri.

### Filtro «Percorribilità» dallo stato di validazione (scrum, 11/11)

Supera la sezione «Niente filtro «Percorribilità»» qui sotto. Nello scrum dell'11/11 (09:46) Giuseppe ha segnalato la
mancanza del filtro come difetto: «mettiti nei panni di uno che deve andarsi a fare un'escursione», da mettere «tra i
primi… se non il primo», anche se il cliente non l'ha chiesto. Il dato: «quel percorribile lo prendi da qua… te lo dà
elastic»; «non devi modificare elastic, devi semplicemente aggiungere questo filtro a quello che già fai filtrare per
validation status». Riferimento: osm2cai, stati 1–4, il 4 è percorribile.

Fatto nel child (`forestas_search_track_facets()`), primo filtro, `field` `terms.validation-status` con le voci del
semaforo della home di oc:8783: Percorribili («Percorribile» + «Validato» + «Certificato»), In revisione o parzialmente percorribili
(«In revisione-validazione» + «In pre-accatastamento o manutenzione»), Non percorribili («Non percorribile»); «Non
verificato» (livello 0) fuori, come nel semaforo. Nessuna modifica al plugin né a Elastic. Non preselezionato: la
tavola 2 lo vuole su «4 · Percorribile», ma cambierebbe il totale di partenza della pagina; da decidere a parte.

Perché «Validato» sta fra i percorribili (verificato l'11/11 sul documento originale): è una regola di Forestas.
Nelle «Osservazioni finali a CANVAS CLAUDE fase 2» (Google Doc di Alessio Saba,
`1wbCgmHo8Z2RmDelUsLfCZlhFHHdwk9fL_ip073ei0BE`), «SOLUZIONE FORESTAS»: il valore di Drupal «sarà riversato sia su
percorribilità che su validazione», «tal quale (valore 3)»; la barra «diventa verde quando supera la soglia minima
(4 tacchette per la percorribilità, 4 per la validazione)». Nello shard i livelli sono quelli di
`ConfigureTaxonomyTermsCommand`: 4 Percorribile, 5 Validato, 6 Certificato; quindi verde = 4–6. Mancava
«Certificato» (livello 6, nessun percorso sul dev): aggiunto a «Percorribili», alla fascia «Avanzato» della
validazione (che lo dichiarava 5–6 ma elencava solo «Validato») e ai nomi dei termini (`term_labels`).

**Da riportare nelle note di sviluppo del ticket** (Rubens, 11/11), all'aggiornamento finale su Orchestrator: il
filtro e la sua origine (scrum dell'11/11), la regola di Forestas con la fonte qui sopra, «Certificato» aggiunto, e
che la scala di percorribilità separata resta un requisito di Forestas, rimandato (oc:8783, oc:8932).

Le etichette restano scritte nel child: quando Ale chiude il lavoro sulle tassonomie nella configurazione dell'app
(`taxonomy label`), si prendono da lì per tutti i filtri insieme (scrum dell'11/11; per Giuseppe quelle a mano «possono
anche andar bene»).

Verificato in locale: 769 percorsi senza filtro; Percorribili 260, In revisione o parzialmente percorribili 432, Non
percorribili 41; con «Percorribili» il totale è 260, l'indirizzo diventa `percorribilita=percorribili`, «Validazione
catastale» mostra Avanzato 45 e Intermedio 215, i badge delle card sono solo «Percorribile» e «Validato»; pagina inglese
«Walkability» con gli stessi numeri; nessun errore JS. Nessuna modifica di CSS o JS, quindi `Version` del child
invariata.

### Niente filtro «Percorribilità» (review-gate, 09/11)

> Superata l'11/11: vedi la sezione precedente.

Rubens: «intanto nascondi il filtro se non fa niente per adesso». La tavola 2 lo mette primo, visibile e spento con
«Dato in arrivo» (nota 1); il dato non esiste e nessun ticket lo sviluppa (sezione «Da fare dopo questo ticket»). Tolto
dalla configurazione del child (`forestas_search_track_facets()`); nel plugin resta l'opzione `disabled` dei filtri,
generica e coperta dai test. Verificato: la configurazione della pagina non ha più la chiave `percorribilita`, il tab
«Percorsi» parte da «Validazione catastale», nessun errore JS. Nessuna modifica di CSS o JS, quindi `Version` del child
invariata.

### Selezione e scarico di più percorsi (review-gate, 09/11)

Rubens ha visto lo scarico di GPX, KML e GeoJSON nella webapp (`1.forestasdev.webmapp.it/?track=343`) e ha chiesto
se la selezione multipla della tavola 2 fosse già fattibile; poi: «se si può fare e funziona mettilo ora».

- **Perché si può.** La webapp non usa le rotte GPX e KML dell'API, che sul dev rispondono 500 (segnalazione 4 qui
  sopra): converte nel browser il GeoJSON della traccia. Il plugin fa lo stesso con `assets/js/track-export.js`
  (UMD, provato con `node --test`), senza le librerie della webapp, perché non ha una build: per linee con nome e
  quota bastano poche righe.
- **Da dove vengono le tracce.** `api/ec/track/multiple?ids=` risponde con un FeatureCollection, ma tiene solo i primi
  10 id (`EcTrackController.php:67`, `array_slice($ids, 0, 10)`): le tracce si chiedono a gruppi di 10, quattro
  richieste alla volta. Misurato il 09/11 sul dev: tutti i 769 percorsi sono 77 richieste, 20,5 MB, 12 secondi con
  curl; dalla pagina, GPX di 769 tracce da 25,1 MB in 7,1 secondi, ben formato (`xmllint`). La risposta non ha il
  nome della traccia: si prende dai risultati della ricerca già in memoria.
- **Quota.** Lo shard scrive 0 dove il DEM non è calcolato (la 343 ha tutti i punti a 0, la 250 la quota vera):
  `<ele>` nel GPX e la terza coordinata in KML e GeoJSON solo se la traccia ha almeno un punto diverso da 0.
- **GeoJSON leggero.** Solo geometria, `id`, `name`, `ref`: la risposta dell'API porta anche descrizioni e gallerie.
- **Nome del file.** Con più percorsi `sardegna-sentieri-percorsi-<numero>.<formato>`; con uno solo il nome del percorso,
  senza accenti né simboli e al massimo 80 caratteri (Rubens, 09/11: «se scarica un solo file può avere il nome del
  percorso?»), per esempio `pedras-rujas-sa-pala-e-sa-trae-sentiero-di-san-francesco-g-602-c.gpx`; anche il documento
  KML prende quel nome. Test `fileName con un solo percorso…` RED→GREEN, 9/9; verificato nel browser con GPX e KML.
- **Riepilogo della barra.** «3 percorsi selezionati · km · +dislivello»: km e dislivello solo se tutti i percorsi
  scelti li hanno (`measuredSum`, la stessa regola dei km complessivi del totale); oggi sul dev li ha 1 su 769.
- **La selezione** resta cambiando testo, filtri, tab e pagina; non sta nell'indirizzo, che resta quello della ricerca.
- **Aspetto.** La prima versione era fatta dalla descrizione scritta (`implementazione-wordpress.md` di oc:8783), con
  radio e pulsante diversi dalla tavola. Rubens: «il tipo di file da selezionare gpx kml o geojson ha un design più
  figo sul wireframe perchè lo hai fatto così diverso?». Rifatta con il markup e il CSS della tavola 2, letti dal file
  del wireframe (`.chk` 18px, `.selall`, `.selbar2`, gruppo «Tracce» con `.sb-gl`, formato `.sb-fmt` a tre voci unite
  con quella scelta su fondo bianco, `.sb-dl`, `.sb-note` per avanzamento ed esito). I 90px liberi a destra della barra
  sono quelli della tavola: lì cade il «torna su» di Impreza, che nella prima versione copriva il pulsante «Scarica» a
  390px.
- **Fuori:** il gruppo «Mappa e schede» (stampa della mappa d'insieme, schede PDF: non c'è nulla dietro), la «Guida
  all'uso del download» (pagina redazionale che non esiste) e la preparazione asincrona oltre una soglia, che con i
  tempi misurati non serve.
- **File unico o un file per percorso: nessuna fonte lo dice** (ricerca del 09/11 su verbale del 12/08 e call del
  17/09, 23/09 e 16/10). Il verbale n. 1 del 12/08 (§4.3 «Scaricamento dei tracciati») dice: «Formati — Scelta al
  momento dello scaricamento: GPX, KML, GeoJSON»; «Selezione multipla — Confermata, con soglia massima da definire»;
  «Per area — Es. selezionare un comune e scaricarne tutti i sentieri in un'unica operazione»; §10, questione 4:
  «Soglia di scaricamento massivo — Da definire». Nelle call si dice solo «decidere quale file scaricare di queste più
  tracce» e «te li scarichi nel formato che vuoi» (16/10, fra 00:40 e 00:45), «scarica n tracce di quelli che hai
  selezionato» (17/09, verso 00:50). Il file unico è quindi una scelta di questo ticket; lo zip con un file per
  percorso è fattibile (Rubens, 09/11: «è solo una domanda», non si fa).
- **Soglia.** Il 16/10 (fra 00:40 e 00:45) alla domanda sul limite la risposta è «questo qua lo vediamo noi. Tecnico.»,
  attribuita nel documento a Gianluca Schirru; l'attribuzione di quella call è poco affidabile (quasi tutte le battute
  risultano di Schirru, Piccioli ne ha 15). `decisioni.md` di oc:8783: «la soglia è tecnica e la fissa Webmapp». Qui
  nessuna soglia: tutti i 769 percorsi si scaricano in circa 7 secondi.
- **Guida all'uso del download**: confermata nella call del 16/10 (verso 00:50 e fra 01:20 e 01:25, «Aggiungi la riga
  sotto che rimanda alla guida all'uso del download»): una riga sotto «Seleziona tutti» verso una pagina statica con i
  consigli della redazione di Forestas. Non c'è perché la pagina non esiste.
- **Verificato** nel browser (Playwright): caselle su 12 righe, Spazio sulla casella lascia il focus lì, «Seleziona
  tutti» con lo stato a metà, KML di 2 percorsi, GPX di 2 e di 769, GeoJSON dei 53 cammini con 53 feature, selezione
  mantenuta passando a pagina 2, «Deseleziona tutto» porta il focus su «Seleziona tutti», frecce sui formati con il
  contorno bianco, testi in italiano e in inglese, nessuno scorrimento orizzontale a 390px, nessun errore JS. Test:
  `search-logic` 29/29, `track-export` 8/8. Child `Version` 1.8.14, servita.
- **Vincolo registrato nel ticket delle API** (sessione di oc:8916, 10/11): l'anteprima del ticket
  (https://claude.ai/artifact/KmRTuKX4Kndd5VQYEFXBFS, versione 37) ha un «Vincolo sullo scarico (oc:8952, 09/11)»:
  lo scarico multiplo legge da `api/ec/track/multiple` la geometria (`LineString`/`MultiLineString`),
  `properties.id` e `properties.ref`; un cambio di quei campi va concordato prima, alzare il limite oltre 10 non
  rompe nulla.
- **Un solo percorso, dopo oc:8953** (decisione del 10/11): GPX e KML dalle rotte dello shard, vedi «Da fare dopo
  questo ticket».
