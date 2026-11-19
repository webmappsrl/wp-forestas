# Enti sul sito WordPress

> Lavoro d'origine: oc:8990. Artefatti in `docs/features/8990-enti-sul-sito-wordpress-elenco-enti-dettaglio-enti-slug-e-ricerca-dallapi-di-oc8965/`.
> L'API dello shard (rotte, token, forma del JSON, indice della ricerca, primo deploy) è nella pagina di conoscenza di
> forestas `forestas/docs/knowledge/8965-api-degli-enti-per-il-nuovo-sito-wordpress.md`: qui c'è solo il lato del sito.
> La procedura di messa in opera su dev e UAT è nella howto `docs/howto/messa-in-opera-degli-enti.md`.
> Il ripiego sulla copia nei dettagli di tracce e POI è del plugin `wp-package` 0.4.0: README del plugin, «Copia quando
> lo shard non risponde».

## Come funziona oggi

Gli enti dello shard hanno sul sito un elenco, `/enti/`, e una pagina per ente, `/enti/<slug>/` (in inglese `/en/…`),
senza post in WordPress. In più compaiono in un tab della ricerca unica, e i loro nomi diventano link nei dettagli di
percorsi e POI. Gli enti sono un dato solo di Forestas: tutto il codice sta nel child, e il plugin non li conosce.

- **Codice.**
  - `themes/forestas-child/inc/entes.php`: copia del JSON, rotta, dati di elenco, dettaglio e «Sentieri gestiti».
  - `templates/entes-list.php`, `templates/ente-detail.php` e `assets/css/entes.css`.
  - Il tab della ricerca in `inc/search.php` e `assets/js/search.js`, i link in `inc/dettaglio.php`.
  - Test `wp eval-file`: `tests/entes-copy.php`, `entes-view.php`, `entes-route.php`, `entes-search.php`, più i casi
    degli enti in `tests/dettaglio.php`.

### La copia del JSON

- **Un solo punto parla con l'API**: `forestas_entes_api_get()`.
  - Prende l'origine dello shard dal plugin (`wmwp_configured_api_urls()`), vista dal server con `wmwp_server_url()`.
  - Manda `Authorization: Bearer <token>` e `Accept: application/json`.
  - Senza token non chiama lo shard.
  - Il token parte solo in https, o in http verso un host che non esce dalla macchina: `localhost`, `127.0.0.1`, `::1`,
    `host.docker.internal` o il nome di un servizio Docker, senza punti (`forestas_entes_token_allowed()`). In http
    verso un dominio la richiesta non parte, e il log dice `token non mandato: lo shard è in http su un host non
    locale`.
  - Il motivo di un errore, per il log, lo dà `forestas_entes_http_reason()`, lo stesso per il rinnovo e per la ricerca
    (401, 403, 429, 503, rete).
- **Il token** è `WP_API_TOKEN` nel `.env` di wp-forestas, con lo stesso valore del `.env` dello shard.
  - Il child lo legge con `getenv()`; non arriva mai nel browser né nei log.
  - Entra nel container solo quando il container viene creato (cosa fare dopo una modifica del `.env`: howto
    [ricreare-wordpress-da-zero.md](../howto/ricreare-wordpress-da-zero.md), «Prima di cominciare»).
- **La copia** è il JSON di `api/v1/entes` salvato nell'option `forestas_entes_copy`, non autoload, con la sola chiave
  `data`.
  - Tiene solo le chiavi che il child legge (`forestas_entes_slim()`, `FORESTAS_ENTES_KEYS` e
    `FORESTAS_ENTES_TRACK_KEYS`): fuori `pois`, `geometry` e le misure delle foto non usate; delle tracce senza il ruolo
    `gestore` restano solo id e ruoli. Chi legge una chiave nuova la aggiunge alle costanti, altrimenti non c'è.
  - Si riscrive solo quando i dati cambiano.
  - Il 16/11/2026, con i 93 enti del dev, la copia serializzata è passata da 2.185.719 a 858.963 byte, e la sua lettura
    da circa 3,2 a 0,7 ms e da 5 a 2,5 MB di memoria.
- **Due option piccole accanto alla copia**, non autoload.
  - `forestas_entes_slugs`: gli slug degli enti (slug → true), riscritti a ogni scrittura della copia e cancellati
    con lei (hook dell'option). Il rinnovo li riallinea anche quando la copia non cambia, se l'option manca o è
    diversa. Li leggono i link dei dettagli e la ricerca con un testo (`forestas_entes_known_slugs()`), senza
    deserializzare la copia; la ricerca a testo vuoto invece elenca gli enti della copia. Senza l'option (copia scritta
    prima che esistesse, option cancellata a mano) gli slug vengono dalla copia, fino al rinnovo successivo.
  - `forestas_entes_state`: lo stato dei rinnovi (`forestas_entes_state()`). `generated_at` dello shard e `fetched_at`
    (UTC) dell'ultimo rinnovo riuscito, `checked_at` (UTC) dell'ultimo rinnovo partito, riuscito o no,
    `refused_since`, l'inizio di un calo rifiutato, e `left_to_cron`, la visita che ha lasciato il rinnovo a WP-Cron
    (sotto).
- **Il rinnovo.**
  - Lo fa ogni 15 minuti l'evento WP-Cron `forestas_entes_refresh` (intervallo `forestas_quarter_hour`), registrato su
    `init` se manca e tolto quando si cambia tema (`forestas_entes_unschedule()` su `switch_theme`).
  - Le pagine degli enti leggono la copia, una volta per richiesta, e non chiamano lo shard; le eccezioni sono il sito
    senza copia e il rinnovo scaduto, qui sotto.
  - Una copia nuova (dati cambiati) dà una riga di log se ha tipi di ente sconosciuti e una se ha slug doppi (lo slug
    nello shard può mancare e non è unico): pagina e link portano al primo ente con quello slug.
- **Un rinnovo alla volta** (`forestas_entes_refresh()`). L'evento WP-Cron, le pagine degli enti e `wp cron event run`
  prendono prima il lucchetto, la riga `forestas_entes_refresh_lock` di `wp_options` (`forestas_entes_lock()`).
  - Lo prende uno solo: una sola istruzione SQL inserisce la riga se manca e non la tocca se c'è.
  - Chi lo trova preso non chiama lo shard. WP-Cron e `wp cron event run` scrivono nel log `forestas enti: rinnovo
    saltato, ne gira già un altro …`; una visita legge la copia che c'è, senza log.
  - Il lucchetto si toglie alla fine, anche quando il rinnovo fallisce. Uno di più di 2 minuti
    (`FORESTAS_ENTES_LOCK_STALE`) è di un rinnovo interrotto a metà, e si riprende.
- **Un rinnovo fallito lascia l'ultima copia buona.** Fallisce con lo shard spento, con un 401 (token diverso da quello
  dello shard), con un 403 (lo shard non ha il token), con un 429 (limite al minuto dello shard, sotto) o con una
  risposta non valida. Ogni fallimento scrive una riga nel log di PHP che comincia con `forestas enti:`, con il codice
  ricevuto e il motivo.
- **Controllo di plausibilità** (`forestas_entes_is_plausible()`).
  - Una copia nuova si scarta se è vuota, o se enti o collegamenti ente-traccia (le `tracks` di tutti gli enti) scendono
    sotto la metà della copia precedente. Il log dice `copia nuova scartata, non plausibile: …`.
  - Il primo rifiuto scrive `refused_since` nello stato. Un calo che dura più di 3 ore dal primo rifiuto
    (`FORESTAS_ENTES_DROP_GRACE`) si accetta, con la riga di log `calo accettato`; un rinnovo plausibile chiude il
    rifiuto. Una lista vuota non si accetta mai.
  - Per accettare subito un calo vero: `wp option delete forestas_entes_copy`, poi `wp cron event run
    forestas_entes_refresh` (tabella dei problemi della howto
    [ricreare-wordpress-da-zero.md](../howto/ricreare-wordpress-da-zero.md)).
- **Sito senza copia** (appena installato).
  - La prima pagina degli enti chiede subito il JSON (`forestas_entes_all()`); le visite che arrivano mentre la
    richiesta è in corso trovano il lucchetto preso e rispondono 503.
  - Se la richiesta fallisce, il rinnovo scrive il transient `forestas_entes_retry` di 5 minuti, e le pagine degli enti
    rispondono 503 con `Retry-After: 300` e la pagina «non disponibile» del plugin. Nessuna visita richiama lo shard
    prima che il transient scada; l'evento WP-Cron invece tiene il suo passo di 15 minuti, perché
    `forestas_entes_refresh()` non guarda il transient, e un suo rinnovo riuscito porta la copia anche alle pagine.
- **Rinnovo scaduto.** Se da più di un'ora non è partito nessun rinnovo (`checked_at` dello stato,
  `forestas_entes_overdue()`), una pagina degli enti rinnova da sé, con un tentativo per richiesta. Succede con WP-Cron
  fermo, ma anche con WP-Cron che gira dopo un'ora senza visite, perché WP-Cron parte solo con una visita.
  - Se WP-Cron è appena partito (transient `doing_cron` del core scritto da meno di un minuto, di solito dalla stessa
    visita, su `init`) e l'evento del rinnovo è da eseguire, la pagina con la copia lascia il rinnovo a WP-Cron ed
    esce subito (`forestas_entes_leave_to_cron()`). L'istante va in `left_to_cron` dello stato: se dopo 2 minuti
    (`FORESTAS_ENTES_CRON_WAIT`) nessun rinnovo è partito, WP-Cron è partito ma non gira, e la pagina rinnova da sé.
  - `doing_cron` non scade da solo: dove il loopback non risponde (in locale) nessuno lo cancella, e resta con
    l'istante dell'ultima partenza. Per questo conta solo se è recente.
  - Le visite che arrivano durante il rinnovo trovano il lucchetto preso e leggono la copia di prima. Un rinnovo
    fallito lascia la copia, e `checked_at` fa aspettare un'altra ora.
  - I dettagli di percorsi e POI e la ricerca non rinnovano mai.
  - Con WP-Cron che gira e visite continue, `checked_at` avanza ogni 15 minuti anche quando il rinnovo fallisce o
    viene scartato, e le pagine non rinnovano mai.
- **In locale WP-Cron non parte**: il loopback sulla porta 8090 non risponde (`wp cron test` fallisce). Il rinnovo si
  lancia a mano (comando nella howto [ricreare-wordpress-da-zero.md](../howto/ricreare-wordpress-da-zero.md), passo
  5), o lo fa una pagina degli enti dopo un'ora: la prima lo lascia a WP-Cron, che ogni visita fa partire senza che
  giri, e una visita di almeno 2 minuti dopo rinnova.
- **In locale il sito legge il dev**, con `WP_PACKAGE_SHARD=forestasdev` e il token del dev nel `.env` di `wp-forestas`.
  - Lo shard locale lo legge solo con il mu-plugin non versionato `wp-content/mu-plugins/wpf-shard-locale.php`
    (oc:8969), e allora il token è quello del `.env` di forestas locale.
  - Il mu-plugin sta nel volume di WordPress, che `scripts/wordpress-reset.sh` cancella: dopo un reset il sito torna
    sul dev, e con il token dello shard locale il rinnovo riceve 401.
  - Passando dallo shard locale al dev il plugin scarta l'indice del dev, perché nel suo Elastic le tracce non hanno il
    nid di Drupal, e il sito resta sugli id dello shard locale. La cache dell'indice va cancellata e ricostruita: il
    transient `wmwp_detail_index` e le option `wmwp_detail_index_last` e `wmwp_detail_index_rebuilt_at`; i comandi
    esatti sono nelle trappole del `CLAUDE.md`. Nel verso opposto non serve: il plugin scarta un indice solo se perde
    chiavi che l'indice precedente aveva.

### Indirizzi

- **Rewrite rules del child**, in testa con il filtro `rewrite_rules_array`:
  - `^enti/?$`, `^enti/page/([0-9]+)/?$`, `^enti/([^/]+)/?$`, nell'ordine, così `enti/page/2` è la pagina 2 e non
    l'ente «page»;
  - verso le query var `forestas_entes` (`list` o `detail`), `paged` e `forestas_ente_slug`;
  - il prefisso `/en/` lo toglie WPML prima delle regole.
- **Regole nuove**: `FORESTAS_REGOLE_VERSIONE` di `inc/news.php` è passata a 4. Le regole si rigenerano al primo giro
  di `bin/wordpress-config.sh pagine` (riga `REGOLE`, anche dentro `allinea`) o alla prima pagina del pannello; fino ad
  allora `/enti/` dà 404.
- **La rotta** (`forestas_entes_route()`) decide lo stato dalla copia.
  - 200 per l'elenco e per uno slug che è nella copia.
  - 404 per uno slug che non c'è, per una pagina oltre l'ultima e per una vista sconosciuta.
  - 503 se una copia non c'è.
  - Tre hook, `parse_query`, `posts_pre_query` e `pre_handle_404`, impediscono a WordPress di trattare la richiesta
    come la home del blog: senza, leggerebbe le news e darebbe 404 a `/enti/page/<n>/` oltre l'ultima pagina delle
    news.
- **Query var** (`forestas_entes_request_vars()`, filtro `request`).
  - Sono pubbliche, perché le regole funzionino, e valgono solo dalle regole degli enti: `/news/?forestas_entes=list`
    resta la pagina delle news.
  - Sulle regole degli enti valgono solo le query var della regola (vista, slug, `paged`). Le query var pubbliche di
    WordPress prese dalla query string (`?p=1`, `?page_id=2`, `?name=…`, `?s=…`, `?feed=…`) si tolgono: accenderebbero
    is_single, is_page, la ricerca o il feed su una pagina senza post, con avvisi PHP di `post-template.php`.
- **Titolo, `hreflang` e selettore di lingua** portano alla stessa pagina nell'altra lingua, filtro compreso.
  - Il titolo è «Comune di Stintino | Sardegna Sentieri» (`pre_get_document_title`).
  - Gli `hreflang` li stampa `wmwp_detail_hreflangs()` del plugin, come per i dettagli di percorsi e POI.
  - Il selettore passa dal filtro `icl_ls_languages`.

### Elenco

- **Card.**
  - 9 per pagina, in ordine di `created_at` crescente; gli enti senza data vanno in fondo.
  - Ogni card ha immagine, nome (l'unico link) e un'etichetta.
  - L'etichetta è la categoria della teaser di Drupal: il tipo padre, o il tipo stesso se non ha padre
    (`forestas_entes_type_category()`). Per questo Comune e Complesso forestale mostrano «Altre Pubbliche Istituzioni».
- **Filtro «Argomento»**, come la faccetta di Drupal.
  - Parametro `?argomento=<type>` (`FORESTAS_ENTES_TYPE_PARAM`).
  - Le voci sono i cinque tipi, senza gerarchia, con il numero di enti, in ordine di numero e poi di nome.
  - La paginazione tiene il filtro e conta le pagine dell'elenco filtrato.
  - La colonna è quella dei filtri di news ed eventi (`forestas_filtri_colonna()` di `inc/griglie.php`).
  - Un tipo che nessun ente ha dà la prima pagina vuota, con «Non ci sono enti da mostrare.», e 404 dalla seconda.
- **Nomi dei tipi** (`forestas_entes_type_label()`).
  - In italiano sono quelli dei termini Drupal, maiuscole e barra comprese («Altre Pubbliche Istituzioni»,
    «Privato/associazione»).
  - L'inglese è nostro: Drupal non traduce i termini.
  - Un `type` fuori dai cinque non si mostra e finisce nel log.

### Dettaglio

- **Contenuti nell'ordine del template Drupal** `node--ente-istituzione-societa--full`:
  1. testata con nome, immagine e credits;
  2. «Informazioni»: la descrizione, chiusa oltre i 30em con «Mostra tutto», e i campi «Tipo di ente», «Contatti»,
     «Pagina web» e «Collegamenti»;
  3. «Sentieri gestiti»;
  4. «Galleria fotografica» con la lightbox del child;
  5. date di aggiornamento e di creazione, nel formato data del sito.
- **Sezioni vuote** non compaiono.
- **Stile** delle pagine e delle news del child. Griglia, galleria e «Mostra tutti (N)» sono i componenti del dettaglio
  traccia: `dettaglio.css` e `dettaglio.js` partono anche da `.forestas-ente`.
- **Lingua dei testi.** Un testo `en` uguale a quello `it` è l'italiano che l'API copia nella chiave `en`, oggi per tutti
  gli enti importati da Drupal: esce con `lang="it"`. Una traduzione scritta in Nova si mostra come inglese, senza
  `lang`.

### Sentieri gestiti

- **Quali tracce** (`forestas_entes_managed_tracks()`).
  - Le tracce dell'ente con ruolo `gestore`, dalla più recente per `created_at`.
  - Hanno la card di `forestas_dettaglio_card_percorso()`, nella griglia a tre colonne, con «Mostra tutti (N)» oltre
    le prime tre.
  - Compare solo una traccia che ha una pagina di dettaglio nell'indice del plugin e il cui nome, quello della card o
    quello italiano, è fra i `names` dell'indice per lo stesso id.
- **In inglese** compaiono tutte, con gli indirizzi `/en/…`; Drupal mostra solo le tracce tradotte.

### Link dai dettagli di percorsi e POI

- **Dove**: nel gruppo «Enti» di `wp-package/track.php` e negli enti di `wp-package/poi.php`, per tutti i ruoli.
- **Come**: `forestas_dettaglio_enti()` dà a ogni ente un `url` se il suo slug è fra gli enti della copia
  (`forestas_entes_known_slugs()`, l'option piccola degli slug: il dettaglio non deserializza la copia).
- **Solo la copia**: questi dettagli non chiamano mai lo shard per gli enti e non rispondono 503 per colpa loro. Senza
  copia, i nomi restano testo.

### Tab «Enti» della ricerca unica

- **Source e rotta.** La source `entes` si aggiunge con il filtro `wp_package_search_sources`, dopo «Contenuti del
  sito». Il tab chiama la rotta REST `forestas/v1/entes-search` (`forestas_entes_search_rest()`).
- **Testo vuoto** (apertura di `/cerca-nel-sito/`).
  - Il tab elenca gli enti della copia nell'ordine dell'elenco, senza chiamare lo shard: l'API risponde 422 a un testo
    vuoto.
  - Se una copia non c'è mai stata, la rotta risponde 503.
- **Testo.**
  - Ogni ricerca chiama `api/v1/entes/search?query=<testo>` con il token, senza cache, salvo i due freni qui sotto.
  - Il testo si taglia a 200 caratteri, il massimo dell'API.
  - Il testo perde in testa e in coda spazi e caratteri invisibili (`forestas_entes_search_text()`): spazi Unicode
    (anche U+00A0), caratteri di controllo e di formato (U+200B, U+FEFF…) e gli altri di `Str::INVISIBLE_CHARACTERS` di
    Laravel. È il taglio che lo shard fa con TrimStrings, con qualche carattere in più: un testo fatto solo di quelli è
    vuoto, e il tab elenca gli enti della copia senza chiamare lo shard.
  - L'attesa è di 8 secondi, meno dei 15 dopo cui il plugin abbandona una source.
  - L'API restituisce al massimo 50 enti (`EnteSearchQuery::SIZE` dello shard) e non dice il totale: il numero del tab
    non supera 50.
  - Il link al dettaglio c'è solo per gli enti della copia: un ente appena creato ha la sua pagina al rinnovo
    successivo.
- **Limite per visitatore.** 30 ricerche con un testo al minuto per IP (`FORESTAS_ENTES_SEARCH_LIMIT`, finestra fissa
  di `FORESTAS_ENTES_SEARCH_WINDOW` secondi). Oltre, 429 con `Retry-After` e senza chiamare lo shard. Il testo vuoto
  non conta.
  - I contatori stanno nell'option `forestas_entes_search_counters`, non autoload: impronta dell'IP (HMAC con il salt
    del sito, l'IP non va nel database) → ricerche e inizio della finestra.
  - A ogni scrittura escono le finestre chiuse, e oltre 2000 visitatori (`FORESTAS_ENTES_SEARCH_VISITORS`) quelli con
    la finestra più vecchia: al massimo l'option pesa 144 KB serializzati. Oltre il limite non si scrive.
  - Due ricerche nello stesso istante possono contare una volta sola: il limite può sforare di poco.
- **IP del visitatore** (`forestas_entes_visitor_ip()`).
  - Su dev e UAT il container sta dietro l'Apache dell'host (`ProxyPass` verso `127.0.0.1:8090`): REMOTE_ADDR è il
    gateway della rete di Docker (privato, in locale `172.27.0.1`), e l'IP vero arriva in coda a X-Forwarded-For,
    dove lo mette mod_proxy.
  - Per questo X-Forwarded-For vale solo con un REMOTE_ADDR privato o di loopback, e se ne prende l'ultimo indirizzo
    pubblico da destra: quelli a sinistra li può scrivere il visitatore. Con un REMOTE_ADDR pubblico vale quello.
- **Pausa quando lo shard non è disponibile.** Con un errore di rete, un 5xx, un 429, un 401 o 403 (token) o una
  risposta 200 senza la lista `data`, per 30 secondi (`FORESTAS_ENTES_SEARCH_PAUSE`) la rotta risponde 503 con
  `Retry-After` senza chiamare lo shard, e nel log va una riga per pausa, non una per tasto.
- **Testo rifiutato dallo shard** (400 e 422, i 4xx della validazione, `forestas_entes_search_refused()`): il tab
  risponde «nessun risultato» (200 con `total` 0), senza pausa e senza log.
- **Filtri** (oc:9000): «Tipo di ente» (`tipo-ente`, i `type` con un nome) e «Ruolo sui sentieri» (`ruolo`, i
  `roles` delle tracce con i nomi di `forestas_dettaglio_ruoli()`), in `forestas_entes_search_filter()`.
  - Valori dello stesso filtro in OR, i due filtri in AND; valori sconosciuti nell'indirizzo ignorati.
  - La rotta restituisce le voci già contate (`facets`): ogni filtro conta sugli enti filtrati dall'altro, non dal
    proprio; si vedono le voci con almeno un ente e quelle scelte, per numero e poi per nome.
  - I ruoli vengono sempre dalla copia, per slug: la ricerca dello shard non dà le tracce, e un ente non ancora nella
    copia non ha ruoli.
- **Errore** (indice degli enti assente, token mancante o rifiutato, rotta mancante con 404, shard spento o al limite).
  - La rotta risponde 503 e scrive nel log `forestas enti: ricerca degli enti non riuscita, codice …; per 30 secondi la
    ricerca risponde 503 senza chiamare lo shard`.
  - Il plugin mostra nel tab il suo messaggio fisso e «Enti (—)»; gli altri tab vanno avanti.

## Perché così

- **Copia rinnovata da WP-Cron ogni 15 minuti, non una cache letta durante le visite** (oc:8990):
  - la pagina 8965 di forestas chiede al sito una cache di un'ora al massimo; con 15 minuti una modifica fatta in Nova
    arriva presto;
  - nessun visitatore aspetta una richiesta del JSON, che il 16/11/2026 sullo shard locale con 93 enti era di circa 1,8 MB;
    con WP-Cron fermo aspetta una visita all'ora, quella che rinnova (sopra, «Rinnovo scaduto»);
  - dopo il reset notturno di UAT il sito si riallinea da solo.
- **Ultima copia buona invece del 503** (oc:8990): le pagine degli enti non si spengono quando lo shard si ferma, e il
  503 resta solo dove una copia non c'è mai stata. Su richiesta del dev, lo stesso ripiego vale per i dettagli di tracce
  e POI, dal plugin 0.4.0.
- **Controllo di plausibilità, con un calo accettato dopo 3 ore** (oc:8990):
  - su UAT, durante l'import dopo il reset notturno, `enteables` resta vuota o parziale per ore, e una copia con metà
    dei collegamenti toglierebbe sentieri gestiti e link;
  - senza scadenza, un calo vero lasciava la copia ferma finché qualcuno non la cancellava a mano;
  - se l'import dura più di 3 ore entra la copia parziale, ma il rinnovo dopo la fine dell'import la sostituisce (una
    crescita è sempre plausibile); una lista vuota non entra mai.
- **Limite per visitatore e pausa invece di una cache per testo** (oc:8990): il limite al minuto dello shard conta per IP,
  e il sito è un IP solo, con la stessa chiave per la ricerca (600 al minuto) e per il JSON della copia (60 al minuto).
  Senza freni, molti visitatori che scrivono esaurirebbero il margine e farebbero fallire anche il rinnovo con un 429.
  La pagina 8965 di forestas assegna il limite per visitatore a WordPress. La cache per testo resta scartata (sotto).
- **Copia snella, riscritta solo quando cambia, e slug a parte** (oc:8990): la copia intera pesava circa 2,1 MB, si
  riscriveva ogni 15 minuti solo perché cambiava `fetched_at`, e ogni dettaglio di percorso o POI e ogni ricerca la
  deserializzavano per leggere gli slug.
- **Token solo in https o verso un host locale** (oc:8990): il token apre dati con nomi e cognomi, e in http verso un
  dominio viaggerebbe in chiaro in rete.
- **Attesa di 5 minuti dopo un fallimento senza copia** (oc:8990): senza, un token mancante su un sito nuovo farebbe
  una chiamata allo shard a ogni visita.
- **Lucchetto con una sola istruzione SQL** (oc:8990): dopo un'ora senza visite la prima visita a `/enti/` faceva partire
  WP-Cron su `init` e poi rinnovava anche lei, e i due rinnovi giravano insieme. Un transient letto e poi scritto non
  ferma due processi che leggono nello stesso momento, e nemmeno add_option(): legge, poi scrive con `ON DUPLICATE KEY
  UPDATE`, e riesce per tutti e due. L'INSERT con `ON DUPLICATE KEY UPDATE … IF(…)` di `forestas_entes_lock()` decide
  sulla chiave unica di `option_name`, in un passo solo.
- **Rinnovo scaduto lasciato a WP-Cron, per 2 minuti al massimo** (oc:8990): il visitatore non aspetta un rinnovo che
  WP-Cron fa in background. Il limite serve dove WP-Cron parte senza girare (loopback che non risponde), il caso per
  cui le pagine rinnovano da sé.
- **Pausa della ricerca solo per i guasti dello shard** (oc:8990): il testo lo sceglie il visitatore. Con la pausa anche
  per i 4xx, un testo che l'API rifiuta (fatto di soli caratteri invisibili, che TrimStrings rende null: 422) fermava
  la ricerca per tutti per 30 secondi, e due richieste al minuto la tenevano ferma.
- **Contatori del limite in un'option sola, con un tetto** (oc:8990): senza object cache persistente un transient
  scaduto resta in `wp_options` finché lo stesso IP non lo rilegge o non passa `delete_expired_transients` (una volta
  al giorno, con WP-Cron), e una raffica da molti IP lascerebbe migliaia di righe. L'option sola tiene solo le finestre
  aperte e ha una misura massima; il prezzo è una scrittura di tutta l'option per ricerca.
- **Dettagli di percorsi e POI solo sulla copia** (oc:8990): per quelle pagine gli enti sono un dato secondario, e una
  chiamata allo shard o un 503 per colpa degli enti le renderebbe fragili.
- **Nome dei sentieri gestiti controllato con l'indice del plugin** (oc:8990):
  - dopo il reset di UAT un id può passare a un altro sentiero prima che l'indice, ricostruito ogni ora, se ne accorga,
    e la card porterebbe al sentiero sbagliato;
  - vale anche il nome italiano, così una traduzione scritta dopo l'ultima ricostruzione non toglie la card.
- **`lang="it"` solo quando `en` coincide con `it`** (oc:8990):
  - oggi l'API copia l'italiano nella chiave `en`, ma lo shard diventerà la fonte, e una traduzione scritta in Nova è
    inglese vero;
  - nessuna logica del sito dipende da un dato che esiste solo per i contenuti importati da Drupal;
  - lo stesso vale per le date: il sito usa `created_at` e `updated_at` come li dà l'API.
- **Ricerca senza cache per testo cercato** (oc:8990): con la ricerca mentre si scrive quasi ogni testo è nuovo, e la
  cache lascerebbe solo righe in `wp_options`.
- **Tab a testo vuoto dalla copia, 503 senza copia** (oc:8990): all'apertura gli altri tab elencano i loro contenuti, e
  l'API rifiuta un testo vuoto. La prima richiesta allo shard spetta alle pagine degli enti, non a una chiamata REST
  fatta mentre si scrive.
- **Link solo verso gli enti della copia** (oc:8990), nella ricerca come nei dettagli: un ente che la copia non ha
  porterebbe a un 404.
- **Contenuti, ordine e filtri da Drupal, stile del child** (oc:8990): per la regola «Drupal 1:1» si allineano contenuti,
  ordine e filtri, mentre lo stile resta quello delle pagine già presenti. Da qui:
  - la galleria dopo «Sentieri gestiti», come su Drupal;
  - l'etichetta della card con la categoria;
  - il filtro «Argomento» sui tipi, perché la faccetta di Drupal filtra `field_tipo_ente` senza gerarchia;
  - «Sentieri gestiti» con la griglia dei «Percorsi correlati» e non con un carosello, che il child non ha.
- **Etichette inglesi tradotte** (oc:8990): su `/en/` Drupal lascia in italiano titolo, tipi e campi. Le altre etichette
  inglesi del child sono tradotte, quindi anche queste («Organisations, institutions and companies»).
- **Tutto nel child** (oc:8990): gli enti sono un dato solo di Forestas, e il plugin è condiviso fra shard.

## Come ci siamo arrivati

- **Carosello per i «Sentieri gestiti»** (oc:8990, scartato): Drupal ha un carosello, ma il child non ne ha nessuno. Il
  dev ha scelto il componente dei «Percorsi correlati» (16/11).
- **Cache di un'ora letta durante le visite** (oc:8990, superata): era il massimo indicato dalla pagina 8965 di forestas.
  Su richiesta del dev è diventata il rinnovo con WP-Cron ogni 15 minuti, con l'ultima copia buona.
- **Cache per testo cercato nella ricerca degli enti** (oc:8990, scartata nella review del piano del 16/11): lasciava righe in
  `wp_options` senza far risparmiare richieste. Nella review interna del 16/11 lo shard si è protetto invece con il
  limite per visitatore e la pausa dopo un errore.
- **Controllo di plausibilità senza scadenza** (oc:8990, superato nella review interna del 16/11): un calo vero degli
  enti restava fermo finché qualcuno non cancellava la copia a mano; oggi si accetta dopo 3 ore.
- **Attesa di 5 minuti come lucchetto del rinnovo da una pagina** (oc:8990, superata nel secondo giro della review
  interna del 16/11): get_transient e set_transient non sono atomici, e il rinnovo di WP-Cron non la guardava. Oggi c'è
  il lucchetto, e l'attesa vale solo dopo un fallimento senza copia.
- **Contatori del limite in un transient per visitatore** (oc:8990, superati nello stesso giro): i transient scaduti
  restavano in `wp_options` (sopra, «Perché così»).
- **Pausa della ricerca per ogni errore** (oc:8990, superata nello stesso giro): comprendeva i 4xx del testo, che un
  visitatore può provocare.
- **Copia intera con `fetched_at` dentro** (oc:8990, superata nella review interna del 16/11): era riscritta a ogni
  rinnovo e letta per intero anche dai dettagli di percorsi e POI.
- **Verifica in locale solo con lo shard locale** (oc:8990, superata): durante il lavoro i test e il confronto con
  Drupal hanno usato lo shard locale, con una copia del JSON scritta a mano nell'option. Lo shard locale però aveva
  descrizioni senza HTML e dati fermi al giorno della copia del database: la verifica finale in locale si è fatta con i
  dati del dev, disattivando il mu-plugin (vedi «Come funziona oggi»).
- **Elenco senza filtri, poi filtro sulle categorie della card** (oc:8990, superati durante il lavoro): l'elenco di
  Drupal ha la faccetta «Argomento», e la faccetta mostra i tipi, non le categorie della teaser. Il confronto con la
  produzione del 16/11 ha portato il filtro sui cinque tipi.
- **Galleria prima dei «Sentieri gestiti»** (oc:8990, corretto): su Drupal i sentieri gestiti vengono prima
  (`content_infos_managed` prima di `content_infos_gallery` in `node--base--full`), come si vede sulla pagina di
  Stintino.

## Messa in opera su dev e UAT

I passi, da fare una volta sola dopo il merge, sono nella howto [messa-in-opera-degli-enti.md](../howto/messa-in-opera-degli-enti.md).
