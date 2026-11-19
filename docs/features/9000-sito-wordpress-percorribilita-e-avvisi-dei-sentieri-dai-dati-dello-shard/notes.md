> Ticket: oc:9000

# Notes — Sito WordPress: percorribilità e avvisi dei sentieri, dai dati dello shard

## Divergenze dal piano, task per task

### Task 13 icona del sito (aggiunto il 19/11)

- Chiesto da Rubens il 19/11, sul ticket attuale: l'icona del sito come quella di Drupal, messa dallo script come il
  resto della configurazione.
- La favicon di Drupal (`public://favicon_2.ico`, da `sardegnasentieri.settings.yml`) è un ICO da 16×16: la «S» del
  logo, alta 12 pixel, su un cerchio bianco con gli angoli trasparenti. WordPress vuole per `site_icon` un'immagine
  grande e ne genera le misure da 32, 180, 192 e 270 pixel: da 16 pixel sarebbero sfocate. `config/media/icona-sito.png`
  è la stessa icona rifatta a 512×512 dalla «S» del logo del child, con le proporzioni della favicon; a 32 pixel
  coincide con quella di Drupal.
- Nuovo segnaposto `@file:<percorso>` in `sito.json` (`allegati.php`): l'apply crea l'allegato con le misure di
  `WP_Site_Icon` e il contesto `site-icon` e lo ritrova dal percorso e dall'impronta; l'export copia il file
  dell'icona in `media/icona-sito.<estensione>` (anche caricata dal pannello) e `export_in` sostituisce tutta la
  cartella `media/`. La copia di Impreza (`site_icon` delle Theme Options, che al salvataggio Impreza riscrive in
  WordPress) è in `WPF_IMPREZA_COPIE`: non si esporta né si applica, così un apply delle Theme Options non azzera
  l'icona. Il ritratto mostra file e impronta, non l'id.
- Provato sul locale: `applica` una differenza, `--conferma` l'allegato e i tag `icon`/`apple-touch-icon` in Home e
  `/avvisi`, secondo giro nessuna differenza; `esporta` riscrive lo stesso PNG, toglie `site_icon` da `impreza.json`
  e rimescola solo l'ordine di `post.json` (rimesso com'era).
- Bug trovato dopo: `tests/icona-sito.php` lanciato dopo l'apply cancellava l'icona vera, perché al passo 7 raccoglieva
  tutti gli allegati con percorso `media/icona-sito.png` (e cancellare l'allegato dell'icona toglie anche `site_icon`).
  Ora il test annota gli allegati veri all'inizio e non li tocca; rilanciato dopo `applica --conferma`, l'icona resta.

### Task 14 filtri del tab «Enti» (aggiunto il 19/11)

- Chiesto da Rubens il 19/11: fra le possibili faccette degli enti ha scelto tipo di ente e ruolo sui sentieri. La
  terza proposta, provincia o area, non c'è: la copia degli enti non ha la posizione e servirebbe una modifica
  all'API dello shard.
- I valori arrivano già contati dalla rotta, come per il filtro dei contenuti: il tab a testo vuoto legge la copia, con
  un testo lo shard, e solo la rotta ha tutti e due.
- «Complesso forestale» compare in tutti e due i filtri: è un tipo di ente e anche un ruolo sulle tracce (`roles`
  dell'API), con lo stesso nome usato da Drupal nei due posti.

### Task 15 filtri dei luoghi dei POI (aggiunto il 19/11)

- Chiesti da Rubens il 19/11. Gli altri filtri possibili non ci sono nei dati: `pois.geojson` non ha i percorsi
  vicini né gli enti, e `forestas.zona_geografica` è un termine Drupal che ripete l'area.
- Il lavoro è nel plugin (la copia dei POI e i conteggi stanno sul server); il child dichiara solo le tre faccette,
  prese da `forestas_search_track_facets()` così le regole restano in un posto solo.
- Da girare ad Alessandro: 269 POI su 976 non hanno il tipo (`poi-type`), e scelto un tipo spariscono tutti senza una
  voce per ritrovarli. Va sistemato nei dati, non nel sito.

### Task 16 stile dei filtri (aggiunto il 19/11)

- Riferimento: lo stile finale della ricerca unica; si comincia dal numero nel riquadro arancione dei filtri dei post.
  Come chiesto da Rubens, la dimensione del testo non cambia: il riquadro dei post usa .8em del testo della colonna,
  quello della ricerca resta a 11,5px.
- Il fondo pieno va a tutto il filtro con una voce scelta, come il gruppo attivo dei filtri dei post (`.facet-active`
  di Drupal), non alla sola voce.

### Task 17 filtri apribili (aggiunto il 19/11)

- La funzione è nel plugin, spenta di base: il disegno delle faccette è di `search.js`. Il child la accende e dà lo
  stile, con la freccia del Font Awesome di Impreza come i filtri dei post.
- Il `legend` va allargato a tutta la colonna (`:has(.wmwp-facet__toggle)`), altrimenti la freccia resta attaccata al
  titolo.

### Controllo di accessibilità dei task 13–17 (19/11)

- Contrasti (WCAG 1.4.3): numero arancione #CA4D1C su #FFFDFA 4,51:1, bianco su #CA4D1C 4,58:1, titoli dei filtri
  #6B6B6B su bianco 5,33:1, «Mostra di più» #333 12,63:1. Il primo è appena sopra 4,5:1: non schiarire il fondo né
  il colore senza ricalcolarlo.
- Corretti: il pulsante che apre e chiude un filtro era alto 22px, ora `min-height: 24px` (2.5.8); le frecce del
  Font Awesome in `::after`/`::before` entravano nel nome letto («Mostra di più» con il glifo in fondo), ora con il
  testo alternativo vuoto (`content: "\f077" / ""`), anche nel «Mostra di più» dei filtri dei post.
- Albero di accessibilità (Playwright, IT ed EN): ogni filtro è un `group` con il nome del titolo e un `button` con
  `expanded` quando è aperto; le voci sono `checkbox` con nome e numero («Nuoro, 308 percorsi»: il numero visibile è
  `aria-hidden`); a filtro chiuso le voci non ci sono per lo screen reader. Focus visibile (3px #0089C7), Invio apre e
  chiude e il focus resta sul pulsante; su telefono (375px) il dialogo «Filtri» riceve il focus, Esc lo chiude e
  riporta il focus su «Filtri», niente scorrimento orizzontale.
- Restano com'erano: il nome della casella ha uno spazio prima della virgola («Nuoro , 308», tre elementi in una
  `label` flex del plugin), che gli screen reader leggono come una pausa; l'icona del sito è decorativa.

### Controllo di accessibilità di avvisi e percorribilità (19/11)

- Provato con dati finti scritti per un'ora nella cache locale dei dettagli (sentiero 99 parzialmente percorribile con
  nota, data e avviso; itinerario 613 con avviso senza data; cammino 646 non percorribile) e degli avvisi
  (`forestas_notices`), poi cancellati: sul dev non ci sono avvisi attivi né sentieri parzialmente percorribili.
- Contrasti: etichetta «Avviso» bianco su #B70827 6,82:1; testo dell'avviso 15,24:1, data 6,13:1; fascia
  «Parzialmente percorribile» 7,38:1, nota 11,56:1; «Non percorribile» 7,52:1; etichette della riga della ricerca:
  bianco su #0E7D2C 5,27:1, #333 su #F59B23 5,77:1, bianco su #B70827 6,82:1. Lo stato è sempre scritto, il colore non
  è l'unico segnale (1.4.1).
- Lingua (3.1.2): nella pagina inglese il testo dell'avviso solo italiano ha `lang="it"`, nel dettaglio e in
  `/avvisi`. Ordinamento di `/avvisi`: `select` con etichetta e pulsante «Ordina», nessun cambio di pagina alla sola
  scelta (3.2.2). Su telefono nessuno scorrimento orizzontale.
- Corretti:
  - gli indicatori della scheda facevano leggere due volte livello e stato (l'immagine delle tacche con `aria-label`
    e il testo sotto): ora le tacche sono `aria-hidden`, anche nella tabella dei punteggi di inclusività;
  - il «·» fra livello e stato, fra codice e nome nella lista degli avvisi e nel punteggio di inclusività si leggeva
    «punto» o niente: ora è nascosto e lo screen reader legge una virgola (`forestas_separatore_html()`, la stessa
    soluzione che c'era già nella fascia della percorribilità);
  - «Avviso Chiuso il ponte…» si leggeva senza pausa: dopo l'etichetta c'è un «:» solo per lo screen reader.

### Task 18 intestazione della ricerca (aggiunto il 19/11)

- Riusati il markup di `[forestas_testata]` con la testata «Solo titolo centrato» e `forestas_percorso()`, così la
  ricerca segue da sola ogni ritocco di intestazione e breadcrumb delle altre pagine.
- Il filo è rosso perché la pagina della ricerca ha il colore tematico «sentieri» nelle sue impostazioni
  (`forestas_sezione_pagina()`): si cambia dal pannello della pagina, senza codice.
- Niente «Condividi», come le pagine d'elenco: l'indirizzo della ricerca si condivide dal browser.

### Commit

- Un commit per repo invece di uno per task, come chiedeva il piano: molti file sono stati toccati da più task e dalle
  due review, e il merge in `develop` è comunque uno squash. I messaggi dei commit elencano il contenuto per task.

### Task 2 letture del dettaglio nel plugin

- `wmwp_detail_blocks()` restituisce `{title, title_lang, content}` con `content` = valore per lingua dello shard, non
  il testo già risolto: il child prepara l'HTML con `forestas_dettaglio_ricco()`, che ha bisogno del valore per lingua
  per marcare `lang`.
- `wmwp_detail_places()` ha un terzo parametro, `$source` (`taxonomies` o `taxonomy_where`), e restituisce anche
  `identifier`: il POI leggeva `taxonomy_where`, in un ordine diverso da `taxonomies.where`.
- `wmwp_text()` accetta il codice con la regione (`en-us` → `en`), come `wmwp_detail_text()`; dopo la review interna
  `wmwp_detail_text()` è un suo involucro (indirizzi invariati, confronto prima/dopo).
- `wmwp_detail_gallery()` ha un secondo parametro, `$lang`: didascalie e crediti si leggono nella lingua della pagina.
- I test PHP del plugin si lanciano con `--path=/var/www/html`: dalla cartella del plugin wp-cli non trova WordPress.

### Task 3 tipo delle tracce dal tema e child sulle letture del plugin

- Due letture in più, che il piano non elencava: `wmwp_detail_feature_image()` (foto della testata, `track.php` e
  `poi.php`) e la posizione dei POI correlati in `wmwp_detail_related_pois()` (mappa del dettaglio).
- `kind_field` ha la forma `terms.<famiglia>`, come `badge_field`.
- Nuova funzione del child `forestas_detail_visible_places()`: la regola di Forestas su quali zone mostrare.
- `tests/php/detail-kind.php` toglie i filtri del tema attivo su `wmwp_track_kind_taxonomy` e li rimette alla fine.

### Task 4 registro degli stati

- Modifica chiesta dopo l'esecuzione (Rubens, 18/11): il totale delle tacche è il numero degli stati della famiglia,
  stato di assenza compreso (percorribilità 4, validazione 5), non il numero degli stati che non sono di assenza; il
  livello è la posizione dello stato. Vedi «Decisioni».

### Task 6 dettaglio del child

- La fascia della percorribilità c'è solo per gli stati che il registro conosce e che non sono di assenza: uno stato
  nuovo dello shard si vede a parole nell'indicatore, senza fascia.
- Validazione senza termine: l'indicatore mostra «0 su 5 · Non verificato» invece del segnaposto; tabella e riga
  compatta restano vuote come prima.
- Le tacche della percorribilità restano rosse (#FF4B4D), come nel wireframe e nel child: è il rosso
  dell'infografica di Forestas; il verde #0E7D2C è della fascia.
- Su mobile la data dell'avviso va a capo sotto il testo (prima prova: il testo finiva in una colonna stretta).

### Task 7 pagina avvisi

Fatto da un agente, rivisto nella review finale. Scelte sue:
- markup in `forestas_notices_html()` dentro `inc/notices.php`, template sottile;
- con `ok` false la pagina esce con 503 e `Retry-After: 60` dal template proprio;
- paginazione `paginate_links` in `nav.forestas-paginazione`, come `/enti`: quella della ricerca unica la disegna il
  browser (`renderPager` in `search.js`) e non si riproduce lato server;
- richiesta con `wp_remote_get` all'indirizzo di `wmwp_server_url()`, timeout 5 secondi; dopo un errore con la copia
  disponibile, la copia torna nel transient per 60 secondi, così uno shard fermo non si richiama a ogni visita;
- valore `piu-recenti` per l'ordinamento predefinito; una pagina oltre l'ultima risponde 404;
- ordinamento con una `select` e il pulsante «Ordina» in un form GET, invece dei due link con `aria-current` del
  piano: la pagina non cambia al solo cambio della scelta (WCAG 3.2.2) e lo stato corrente è l'opzione `selected`.
- Dopo la review interna: link di ogni avviso dall'indice dei dettagli (`wmwp_detail_url()`), senza link per un
  percorso che l'indice non ha; etichetta «Avviso» e data uguali al dettaglio (`forestas_notice_tag_html()`,
  `forestas_detail_time_html()`, `datetime` con la sola data).

### Task 9 filtri dal registro

- Le opzioni dei filtri hanno per id l'identifier dello stato (`percorribilita=walkability-status-walkable`
  nell'indirizzo): i vecchi id dei gruppi (`percorribili`, `parziali`…) non valgono più. Il sito non è pubblico.

### Task 10 sorgente dei POI nel plugin

Fatto da un agente, rivisto nella review finale. Scelte sue:
- la copia passa da `wmwp_poi_search_pending()` (statica) e si scrive in `wmwp_detail_index_rebuild()` dopo
  l'accettazione dell'indice; dopo la review interna un'app senza POI ha una copia vuota;
- `searchable` per lingua (dopo la review interna: lo shard lo dà con tutte le lingue e i marcatori `{it:…,en:…}`), in
  minuscolo, senza accenti, con il nome in coda; la ricerca ignora gli accenti;
- «testo nel nome» = tutte le parole nel nome della lingua della pagina; a parità ordine per nome e id.

### Task 11 POI nella ricerca di Forestas

- Nome della faccetta e tipo della card dei POI vengono dalla configurazione della source (`source-pois.js`, nuova
  chiave `type_label`): la rotta REST non conosce la lingua della pagina che la chiama.

## Bug trovati

- **Test che sporcano lo stato del sito.** La sandbox dei test del plugin (`tests/php/history-sandbox.php`) rimetteva a
  posto le opzioni dell'indice dei dettagli ma non la copia nuova dei POI: dopo `detail-cache.php` la ricerca dei POI
  trovava un solo POI finto. Corretto (la sandbox salva anche `wmwp_poi_search_copy`).
- **Test lanciati in parallelo.** I test di plugin e child scrivono sullo stesso WordPress: lanciati insieme (io e un
  agente) hanno lasciato regole di rewrite con basi finte, e `/en/…` e i POI davano 404. Ripristinato cancellando
  l'opzione della firma delle regole (`WMWP_DETAIL_RULES_SIGNATURE`) e ricaricando una pagina. Nessun test, da solo,
  lascia le regole sbagliate.

- **Nomi dello shard con spazi iniziali** (dello shard): alcuni nomi cominciano con una tabulazione o uno spazio
  (`"\tCMSB - TAPPA 04…"`, `" 'Untanas de Su Tassu…"`) e l'API, che ordina su `name.keyword`, li metteva in testa
  all'elenco della ricerca. Ripiego nel plugin (ordine sul nome ripulito senza testo cercato); segnalato il 18/11 alla
  sessione di oc:8916 per Alessandro. Sul dev sono 35 tracce su 773 con spazi o tabulazioni ai lati del nome
  italiano (18/11).
- **Riapertura in Drupal non arriva allo shard** (dello shard, oc:8989): se in Drupal un sentiero passa da «Non
  percorribile» a «Validato», `syncWalkability()` esce con il valore `null` e la percorribilità già scritta resta
  (`app/Services/Import/SardegnaSentieriImportService.php:1169-1173`). Segnalato il 18/11 alla sessione di oc:8916
  (anteprima delle API v78, bozza del tag 692 v57) e da girare ad Alessandro.

- **Review finale (opus), quattro importanti corretti, ognuno con un test che falliva prima:**
  - `/avvisi` senza copia richiamava lo shard 6 o più volte per visita: ora l'errore resta per 60 secondi;
  - un `page` enorme mandava in errore fatale la rotta pubblica `wmwp/v1/pois`: ora ha un tetto (10000);
  - dopo il deploy del plugin il tab dei POI restava in errore fino al giro orario del cron: ora, senza copia, la
    ricostruzione dell'indice parte subito;
  - i testi dei POI che ripiegano sull'italiano non erano marcati: ora la rotta dà la lingua di titolo, estratto e
    tipi, e `search.js` mette `lang`.
  Alzata a importante e corretta anche la chiave `tipo-poi` nel plugin (identificatori in inglese): il plugin usa
  `poi-type`, il child tiene `tipo-poi` nell'indirizzo come gli altri parametri italiani della ricerca.
- **Un test interrotto lascia lo stato sporco.** Un giro del test dei POI fermato da un errore fatale (era il rosso di
  I2) non ha fatto la pulizia finale e ha lasciato la copia dei POI finta; i giri dopo l'hanno salvata e rimessa così.
  Ripristinata forzando la ricostruzione dell'indice (togliendo `wmwp_detail_index_rebuilt_at`, perché il limite di
  frequenza la saltava).

- **Review interna, giro 1 (18/11).** Dalla review di `wp-forestas`, applicati: controllo unico della versione del
  plugin (`forestas_plugin_ready()`, `inc/statuses.php`): con un `wp-package` più vecchio della 0.5.0 i dettagli usano
  il template del plugin, `/enti/<slug>` e `/avvisi` i testi senza lingua, la ricerca non ha i filtri degli stati
  (provato alzando per un momento la versione minima: sentiero, itinerario, POI, `/avvisi`, ente e ricerca a 200,
  nessun errore); commenti e test con la regola vecchia delle tacche (fascia dai dati di `wmwp_walkability()`, stato
  sconosciuto dal registro); tolte `forestas_dettaglio_livello_validazione()` e le chiavi morte degli indicatori; una
  sola chiamata per l'accessibilità e una sola fonte dei gradi (il registro per ordine e tacche, il child per i nomi
  estesi); `forestas_status_names()` e `forestas_status_legend()` al posto dei tre cicli sul registro; etichetta
  «Avviso» e data uguali in dettaglio e `/avvisi` (CSS dell'etichetta in `style.css`); CSS morto e commento di
  `dettaglio.js`; misure della riga dei fatti da `wmwp_detail_stats()`; `forestas_walkability_family()` al posto di
  `'walkability-status'` scritto a mano; costante per `piu-recenti`, riga ridondante, `forestas_notices_item_html()`;
  test degli avvisi che potevano non fallire; render vero di un itinerario e di un sentiero con avviso; link degli
  avvisi solo per percorsi nell'indice. Scartati, con il motivo: la chiave `validazione_tacche` e le classi `av-*`
  restano in italiano come le chiavi e le classi vicine del child (`validazione`, `percorribilita`, `wm-tac`,
  `wm-segnaposto`): rinominarle solo qui farebbe convivere due lingue nello stesso array e nello stesso CSS.
- **Pulizia (18/11).** Tolti il mu-plugin di simulazione `zz-prova-9000.php`, la cache dei dettagli 99 e 613 e la
  copia degli avvisi che ne avevano i dati finti, la cartella `/Volumes/WebmappDev/tmp/oc9000/` e lo spazio di lavoro
  del piano in `.superpowers/`. In locale la traccia 99 torna percorribile senza avviso e `/avvisi` non ha avvisi,
  come il dev. `zz-prova-config-detail.php` resta: è nel container dal 12/11, non è di questo lavoro.
- **Review interna, giro 2 (18/11).** Nessun bloccante in tutti e due i repo; applicati anche i cleanup facoltativi
  (copia dei POI nel formato di prima trattata come assente, liste saltate negli slug, parsing di `searchable`, un
  solo evento di ricostruzione durante l'attesa: note del plugin).

## Decisioni

Dialogo del 18/11 (Rubens):

- Il tab «Punti di interesse» resta in oc:9000; la fonte è `pois.geojson`, non un indice dei POI nello shard.
  Faccetta: `poi-type`.
- Le tacche di ogni indicatore sono tante quanti sono gli stati della famiglia nello shard, stato di assenza
  compreso, e ogni stato accende fino alla sua posizione: percorribilità 4 tacche (Sconosciuta 0, Non percorribile 2,
  Parzialmente 3, Percorribile 4), validazione 5, accessibilità 3. Storia: scelta così nel dialogo; cambiata dopo la
  challenge in «lo stato di assenza non ha una tacca» (3 tacche), perché «Non percorribile» accendeva mezza barra su
  un sentiero chiuso; rimessa a 4 tacche da Rubens il 18/11, dopo averla vista nel browser: «possiamo avere 0 su 4?
  come gli stati dello shard?». Nella legenda i livelli sono 0, 2, 3, 4: l'1 è il posto di «Sconosciuta».
- Sentiero senza termine o «Sconosciuta»: indicatore 0 con la scritta, nessuna fascia.
- Nessuna riga «Inserito da»: l'API dà solo l'id dell'utente, per scelta (privacy), e gli utenti non sono legati a
  un ente.
- La data della percorribilità si mostra solo se `properties.walkability.user` non è `null`: lo stato scritto
  dall'import ha la data dell'import (sul dev la traccia 99, 2026-11-18T06:21:16+01:00).
- Filtri «Percorribilità» e «Validazione catastale»: un'opzione per stato, i sentieri senza termine contano come
  stato di assenza.
- Elenco degli stati scritto nel codice finché lo shard non lo espone (richiesta ad Alessandro).
- La fascia «Avviso» compare anche sugli itinerari, a differenza di quanto deciso in oc:8969, perché
  `api/v1/avvisi` include gli avvisi degli itinerari.
- Lingua degli avvisi: vale il ripiego sull'italiano con `lang="it"` già del child.
- Tolta per ora anche la voce «Segnalazioni» del menu di servizio dell'header, italiano e inglese (Rubens, 18/11):
  cancellata dal pannello locale, poi `esporta`; nel diff solo `config/menu.json` (l'export aveva solo rimescolato
  l'ordine di `config/post.json`, tenuto com'era). Sui server la applica il cron `allinea`, che applica da solo le
  differenze dei menu.
- Filtro «Percorribilità»: mostra solo gli stati che hanno risultati, come le altre faccette (Rubens, 18/11).
- Etichetta della riga dei percorsi: la percorribilità con il semaforo, non più la validazione, e nessuna etichetta per
  «Sconosciuta» (Rubens, 18/11). Nel wireframe la riga mostrava lo stato unico di Drupal; dopo oc:8989 leggeva solo la
  validazione, e i 260 sentieri percorribili o non percorribili apparivano come «Non verificato» grigio.
- Regola generale: ciò che legge dati nati in `wm-package` sta in `wp-package`, ciò che legge dati di forestas sta
  nel child. Percorribilità, avviso del dettaglio, registro degli stati e ricerca dei POI nel plugin.
- L'allineamento delle letture già fatte (Blocchi Dettaglio, galleria, POI correlati, dati tecnici, luoghi, tipo del
  POI, `route-type` nel plugin) si fa in oc:9000, come primo passo, con il controllo prima/dopo.
- Segnalazioni e recensioni tolte dal dettaglio: il primo rilascio non le ha (Giuseppe, scrum 16/11).
- Dalla challenge, senza una scelta del dev: card del POI senza tipo e tutti i POI nella ricerca, come Drupal
  (`core.entity_view_display.node.poi.search_result.yml`, `search_api.index.sardegna_index.yml`); una sola copia di
  `pois.geojson`, quella dell'indice dei dettagli; ricerca su `searchable`; link di `/avvisi` per id, risolti al
  clic; `FORESTAS_REGOLE_VERSIONE` per la rotta nuova.
- Ordine di `wm-plan` invertito: piano scritto prima della stima, perché `wm-estimate` stima su overview e piano e
  rispondeva «STIMA NON POSSIBILE» senza il piano.
- Tag del ticket: non toccati, su indicazione di Rubens. Stato del ticket: lo cambia Rubens.

- Stima: 2,3 ore misurate più 8,3 stimate da `wm-estimate`; sul ticket Rubens ha messo 11 ore.

## Follow-up

- **Condizione di merge:** la PR di `wp-forestas` non va in merge prima del commit con il nuovo `WP_PACKAGE_REF` in
  `docker/scripts/init-wordpress.sh` (hash di `git rev-parse` dopo il merge del plugin, commit a sé nella stessa PR).
  Oggi è `827af5e`, la 0.4.0: senza il commit il child degrada (`forestas_plugin_ready()`), ma il sito resta senza
  percorribilità, avvisi nel dettaglio, filtri degli stati e tab dei POI. Poi `scripts/wordpress-up.sh` di `forestas`
  su dev e UAT.
- **Dopo l'aggiornamento del plugin su dev e UAT** il tab «Punti di interesse» può rispondere 503 («I punti di
  interesse non sono disponibili») finché la prima ricostruzione dell'indice non scrive la copia dei POI: la rotta la
  programma subito, al massimo 5 minuti dopo l'ultima ricostruzione. È atteso, non un guasto: la 0.4.0 non scriveva la
  copia.
- **Icona del sito su dev e UAT:** è in `config/`, quindi la mette `bin/wordpress-config.sh applica --conferma` (il cron
  `allinea` non applica `sito.json`); su un sito nuovo la mette l'apply automatico dell'init.
- **UAT dopo oc:8989:** su UAT i percorsi hanno ancora `validation-status-walkable` (219) e `not-walkable` (41) e
  nessun `walkability-status` (18/11). oc:9000 va su UAT dopo oc:8989 e il suo import, altrimenti la Validazione
  mostra «0 su 5 · Percorribile» e la percorribilità è «Sconosciuta» ovunque.
- Verifica con dati veri su dev e UAT: un sentiero parzialmente percorribile e un avviso creati in Nova.
- Minori della review finale ancora rimandati: nessun `Cache-Control` e opzione da 1,57 MB letta a ogni richiesta;
  `wmwp_track_kind_taxonomy()` fuori dall'impronta delle basi; nomi della validazione in due punti del child (registro
  e `forestas_search_term_labels()`); copia dei POI che ignora il filtro `wmwp_detail_items`; `firstTerm` in
  `source-tracks.js`; link di un avviso senza testo se `ref` e nome sono vuoti. Corretti con la review interna:
  parametri array della rotta dei POI, `'walkability-status'` scritto a mano, `WMWP_POI_SEARCH_OPTION` in
  `index.php`, docblock di `detail-base.php`.
- Con oc:8980 rimettere nel menu di servizio la voce «Segnalazioni» / «Report an issue», tolta qui.

- Quando lo shard espone l'elenco degli stati di una famiglia, il registro di `wp-package` passa a leggere l'API.
- Riga «Inserito da» quando lo shard avrà il dato.
- Link a `/avvisi` dalla home (oc:9005).
