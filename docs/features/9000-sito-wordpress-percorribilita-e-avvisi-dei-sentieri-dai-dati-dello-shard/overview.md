> Ticket: oc:9000

# Sito WordPress: percorribilità e avvisi dei sentieri, dai dati dello shard

Ciò che legge dati nati in `wm-package` sta nel plugin `wp-package`, ciò che legge dati di forestas sta nel child
(Rubens, 18/11): percorribilità, avviso del dettaglio, registro degli stati e ricerca dei POI nel plugin; validazione,
accessibilità, pagina `/avvisi` (`api/v1/avvisi` è di forestas), filtri e grafica nel child. Overview del plugin
nella stessa cartella (`docs/features/9000-sito-wordpress-percorribilita-e-avvisi-dei-sentieri-dai-dati-dello-shard/`).

## Cosa cambia

Il sito mostra i dati che lo shard espone già dopo oc:8989 (percorribilità) e oc:8981 (avvisi), e cerca anche i
punti di interesse:

- nel dettaglio di sentieri e cammini la fascia della percorribilità e l'indicatore a tacche leggono
  `walkability-status` e `properties.walkability`; la fascia «Avviso» legge `not_accessible*`, anche negli
  itinerari;
- il numero di tacche di ogni indicatore del dettaglio segue gli stati della sua famiglia nello shard;
- nasce la pagina `/avvisi` (`/en/avvisi`) con gli avvisi attivi di `api/v1/avvisi`;
- nella ricerca unica i filtri «Percorribilità» e «Validazione catastale» hanno un'opzione per stato, e i POI
  compaiono nel loro gruppo del tab «Tutti» e nel tab «Punti di interesse»;
- dal dettaglio spariscono segnalazioni e recensioni, che non sono nel primo rilascio.

## Perché

Chi guarda un sentiero deve sapere com'è oggi sul terreno e se c'è un problema. I dati ci sono nello shard, ma il
sito mostra ancora il segnaposto «Dato non ancora disponibile», e il filtro «Percorribilità» legge
`validation-status`: dopo oc:8989 sul dev trova solo validati e certificati, e «Non percorribili» non trova nulla.
I POI mancano dalla ricerca unica dal suo primo rilascio (oc:8952), mentre la tavola 2 di oc:8783 li vuole.

Allo scrum del 18/11 il lavoro è stato definito come allineare il WordPress all'API, con i dati che ci sono adesso.

## Requisiti

### Allineamento di ciò che è già fatto (primo passo del lavoro)

- [x] Prima di tutto il resto (Rubens, 18/11), il child smette di leggere da sé i dati nati in `wm-package` e usa le
      funzioni del plugin: Blocchi Dettaglio (`forestas_dettaglio_blocchi()`), galleria e foto
      (`forestas_dettaglio_galleria()`, `wp-package/track.php`, `wp-package/poi.php`), POI correlati (parte POI di
      `forestas_dettaglio_correlati()`), dati tecnici (`track.php`, `forestas_dettaglio_tabelle()`), luoghi
      (`forestas_dettaglio_luoghi()`, `forestas_dettaglio_poi()`), tipo del POI (`forestas_dettaglio_poi()`,
      `forestas_dettaglio_card()`). Il markup resta nel child.
- [x] Il child indica al plugin `route-type` come famiglia del tipo delle tracce, così la base degli indirizzi
      (`/sentiero/…`, `/cammino/…`, `/itinerario/…`) non cambia.
- [x] Restano nel child, perché nascono in forestas: difficoltà, `validation-status`, `inclusivity-*`,
      `route-type`, `layer_name`, sentieri correlati (`nearby_tracks`), `properties.forestas`.
- [x] Controllo che nulla si sia rotto, prima di passare al resto: HTML di un campione di dettagli e indirizzi
      dell'indice confrontati prima e dopo lo spostamento (overview del plugin); passano i test del child.

### Stati delle famiglie e tacche

- [x] Il numero di tacche di ogni indicatore del dettaglio (validazione, percorribilità, accessibilità) segue gli
      stati della sua famiglia nello shard: se lo shard aggiunge o toglie uno stato, le tacche seguono senza
      cambiare il codice che le disegna (Rubens, 18/11).
- [x] Tante tacche quanti sono gli stati della famiglia nello shard, stato di assenza compreso; ogni stato accende le
      tacche fino alla sua posizione, lo stato di assenza («Sconosciuta», «Non verificato») e il termine mancante
      nessuna (Rubens, 18/11, dopo averlo visto con 3 tacche):
      - percorribilità, 4 tacche: Sconosciuta o nessun termine 0, Non percorribile 2, Parzialmente percorribile 3,
        Percorribile 4;
      - validazione, 5 tacche: Non verificato o nessun termine 0, In revisione-validazione 2, In pre-accatastamento
        o manutenzione 3, Validato 4, Certificato 5;
      - accessibilità, 3 tacche: la famiglia non ha uno stato di assenza; nessun termine 0, AT 1, AE 2, AEE 3, come
        oggi.
- [x] Valore sempre scritto accanto alle tacche («3 su 4 · Parzialmente percorribile», «0 su 4 · Sconosciuta»); la
      legenda dietro la «i» elenca gli stati della famiglia con il loro livello (0, 2, 3, 4 per la percorribilità) e i
      nomi dello shard.
- [x] L'elenco degli stati di ogni famiglia (identifier, nome italiano e inglese come nello shard, ordine, stato di
      assenza) sta nel registro degli stati di `wp-package`, che ha già la percorribilità; il child ci aggiunge
      `validation-status` e `inclusivity-difficulty`, famiglie di forestas, in un solo punto. Lo shard oggi non
      espone l'elenco: non c'è in `config.json`, `taxonomies/{taxonomy_name}.json` risponde «Taxonomy name not
      valid» e le faccette di Elasticsearch contano solo gli stati usati da almeno una traccia. Finché l'elenco è
      scritto nel codice, uno stato nuovo nello shard vuole un rilascio; quando arriva l'API si sostituisce solo la
      fonte del registro, e il livello resta la posizione nell'elenco, non il `sort_order`.
- [x] Nessun riferimento rimasto a `validation-status-walkable` e `validation-status-not-walkable`, tolti da
      oc:8989 (indicatore della validazione in `inc/dettaglio.php`, filtri e `forestas_search_term_labels()` in
      `inc/search.php`).

### Percorribilità nel dettaglio

- [x] Stato, nota e data della percorribilità e dati dell'avviso si leggono con le funzioni di `wp-package`; il
      child ne fa solo la grafica.
- [x] Fascia della percorribilità nel dettaglio di sentieri e cammini, solo per Percorribile, Parzialmente
      percorribile e Non percorribile: titolo con lo stato, riga «Nota: …» da `properties.walkability.description`
      se c'è, colori e contrasti di «Fascia della percorribilità» in `implementazione-wordpress.md` di oc:8783.
      Nessuna riga «Inserito da».
- [x] La data («<stato>, stato aggiornato il <data>», in `<time datetime>`, nell'ora italiana) compare solo se
      `properties.walkability.user` non è `null`, cioè se lo stato l'ha messo una persona da Nova. Lo stato scritto
      dall'import ha `user` `null` e la data dell'import: sul dev la traccia 99 ha `last_update`
      2026-11-18T06:21:16+01:00 (Rubens, 18/11).
- [x] Sentiero con «Sconosciuta» o senza termine: indicatore 0 su 4 con la scritta «Sconosciuta», nessuna fascia.
      Sul dev sono 513 tracce su 773 senza termine (indice di ricerca, 18/11).
- [x] Itinerari: niente fascia della percorribilità e niente indicatori, come oggi.
- [x] [UX] Lo stato è scritto a parole nella fascia e nell'indicatore, mai affidato solo al colore (WCAG 1.4.1);
      le tacche sono decorative e lo screen reader legge il valore («livello 3 su 4, Parzialmente percorribile»).

### Avvisi

- [x] Fascia «Avviso» in alto nel dettaglio di sentieri, cammini e itinerari, al posto del segnaposto, solo se
      `not_accessible` è acceso (letto come lo shard, con `FILTER_VALIDATE_BOOLEAN`) e il messaggio non è vuoto;
      senza avviso la fascia non c'è (scrum 16/11). Sugli itinerari la fascia compare anche se oc:8969 l'aveva
      tolta: `api/v1/avvisi` include gli avvisi degli itinerari, e l'avviso deve vedersi anche nel dettaglio
      (Rubens, 18/11). Testo nella lingua della pagina, con il ripiego sull'italiano marcato `lang="it"` già usato
      dal child (`forestas_dettaglio_testo_lingua()`); data da `not_accessible_updated_at` (UTC, mostrata nell'ora
      italiana) quando c'è.
- [x] Pagina `/avvisi` e `/en/avvisi` (tavola 10 di oc:8783): titolo, una riga di spiegazione, gli avvisi attivi di
      `api/v1/avvisi`, ordinabili per data (più recenti prima, o meno recenti), senza filtri, con la paginazione
      della ricerca. Ogni avviso: triangolo `aria-hidden`, parola «Avviso», `ref` e nome del sentiero, testo, data
      quando c'è; porta al dettaglio. Gli avvisi senza data vanno in fondo con tutti e due gli ordinamenti.
- [x] La data dell'avviso è l'ultima modifica del testo italiano (`published_at`, knowledge 8981 di forestas): si
      scrive «aggiornato il <data>», non «pubblicato il».
- [x] Il link di ogni avviso è l'indirizzo del dettaglio letto dall'indice in cache del plugin (`wmwp_detail_url()`):
      niente `wmwp_detail_url_by_id()` per ogni riga, che scaricherebbe il dettaglio di ogni traccia mentre la pagina
      si genera. Un avviso il cui percorso non è nell'indice (`track_id` assente, 0 o sconosciuto) mostra il nome
      senza link, invece di portare a un 404 (review interna, 18/11).
- [x] La rotta nuova alza `FORESTAS_REGOLE_VERSIONE` (`inc/news.php`), altrimenti dopo il deploy `/avvisi` dà 404
      fino a `wp rewrite flush`; selettore di lingua di WPML e `hreflang` come le pagine degli enti.
- [x] [UX] L'ordinamento è un controllo vero (link o `select`) con lo stato corrente esposto, non un elemento
      cliccabile qualsiasi.
- [x] [UX] Nessun avviso attivo: messaggio «Al momento non ci sono avvisi attivi». Shard che non risponde e nessuna
      copia precedente: messaggio d'errore diverso, mai un falso «nessun avviso».
- [x] La risposta di `api/v1/avvisi` si tiene al massimo 60 secondi, come il dettaglio, con l'ultima copia buona se
      lo shard non risponde: un avviso nuovo compare sul sito entro un minuto.

### Ricerca unica

- [x] Filtro «Percorribilità» su `terms.walkability-status`: un'opzione per stato della famiglia, nell'ordine dello
      shard; le tracce senza termine contano come «Sconosciuta». Come le altre faccette, un'opzione senza risultati
      non si mostra (`computeFacets()` di `wp-package`): oggi sul dev non c'è nessun Parzialmente percorribile.
- [x] Etichetta colorata della riga dei percorsi: la percorribilità con il semaforo del wireframe (Percorribile
      #0E7D2C, Parzialmente percorribile #F59B23 con testo scuro, Non percorribile #B70827), non più la validazione;
      nessuna etichetta per «Sconosciuta» (`badge_exclude`). Nel wireframe la riga mostrava lo stato unico di Drupal,
      e il semaforo riassume la percorribilità (Rubens, 18/11).
- [x] Filtro «Validazione catastale»: un'opzione per stato di `validation-status`, nell'ordine dello shard, senza
      le fasce «Avanzato», «Intermedio» e «Base»; le tracce senza termine (19 sul dev) contano come «Non
      verificato».
- [x] Punti di interesse nel loro gruppo del tab «Tutti» e nel tab «Punti di interesse», con la faccetta per
      `poi-type`; la card ha la grafica della card di Drupal già usata dal child e, come il risultato di ricerca di
      Drupal, non mostra il tipo (`core.entity_view_display.node.poi.search_result.yml`). Entrano tutti i POI,
      waypoint compresi, come nell'indice di Drupal. La sorgente è nel plugin (`wp-package`, overview del plugin).
- [x] Il redirect dei vecchi link di Drupal con `cerca_contenuto_nel_sito:poi` porta al tab «Punti di interesse»
      invece che a «Tutti».

### Segnalazioni e recensioni

- [x] Il primo rilascio non ha segnalazioni né recensioni (Giuseppe, scrum 16/11: «Il primo rilascio non avrà le
      recensioni»; Rubens, 18/11): nel dettaglio non si vedono più la riga «Recensioni: …» della testata
      (`wp-package/track.php:109`), il pulsante «Segnala» (`track.php:133`) e la sezione «Opinioni e recensioni»
      (`track.php:433-435`). Il markup si toglie dal template, non si nasconde con il CSS, così non resta nemmeno
      per gli screen reader; tornano con oc:8980.
- [x] Anche la voce «Segnalazioni» del menu di servizio dell'header («Report an issue» in inglese, link `#`) si toglie
      per ora, da `config/menu.json` (Rubens, 18/11); torna con oc:8980.

### Lingue

- [x] Ogni testo nuovo del child in italiano e in inglese con `forestas_testo()`; il child non ha file di
      traduzione.

## Rischi

- **Allineamento su pagine già verificate.** Lo spostamento tocca i dettagli di oc:8969 e la base degli indirizzi
  stabili di oc:8983. Mitigazione: si fa per primo e si chiude con il confronto prima/dopo di HTML e indirizzi.
- **Elenco degli stati scritto nel codice.** Se in Nova si aggiunge uno stato prima che lo shard esponga l'elenco,
  il sito non lo conosce: la traccia con quello stato mostra lo stato a parole senza tacche accese, e il filtro non
  ha la sua opzione. Mitigazione: la richiesta dell'elenco ad Alessandro parte con questo lavoro; l'elenco sta nel
  registro del plugin e nella registrazione del child, e si sostituisce quando arriva l'API.
- **Riapertura in Drupal.** Se in Drupal un sentiero passa da «Non percorribile» a «Validato», l'import non toglie
  la percorribilità già scritta (`syncWalkability()` esce con il valore `null`,
  `app/Services/Import/SardegnaSentieriImportService.php:1169-1173`): il sito mostra «Non percorribile» finché
  Drupal resta la fonte. È dello shard (oc:8989): segnalato ad Alessandro e Giuseppe.
- **Dati di prova sul dev.** Oggi sul dev nell'indice ci sono solo Percorribile (219) e Non percorribile (41),
  nessun Parzialmente percorribile, e `api/v1/avvisi` non ha avvisi (18/11). Per vedere tutti i casi servono un
  sentiero parzialmente percorribile e alcuni avvisi creati in Nova sul dev, o una simulazione locale.
- **Ordine di rilascio.** Il child usa funzioni che esistono solo dalla 0.5.0 di `wp-package`, e il `WP_PACKAGE_REF`
  di oggi è la 0.4.0. Senza un controllo, il child nuovo con il plugin vecchio manderebbe in errore fatale dettagli,
  `/enti/<slug>`, `/avvisi` e la ricerca. Mitigazione: un controllo unico della versione (`forestas_plugin_ready()`)
  fa degradare il child invece di andare in errore (dettagli con il template del plugin, ricerca senza i filtri
  degli stati, testi senza la lingua da cui vengono); il nuovo `WP_PACKAGE_REF` va comunque nella stessa PR del
  child, e la PR non va in merge prima di quel commit; poi `scripts/wordpress-up.sh` di `forestas` su dev e UAT.
- **UAT senza oc:8989.** Su UAT i percorsi hanno ancora `validation-status-walkable` (219) e `not-walkable` (41) e
  nessun `walkability-status` (18/11): con oc:9000 lì la colonna Validazione mostrerebbe «0 su 5 · Percorribile» e
  la percorribilità sarebbe «Sconosciuta» ovunque. Mitigazione: oc:9000 va su UAT dopo oc:8989 e dopo il suo import;
  sul dev i termini sono già quelli nuovi.

## Out of scope

- Fascia degli avvisi e semaforo nella home: arrivano con la home (oc:9005, ultimo dei lavori secondo lo scrum del
  18/11); fino ad allora `/avvisi` non ha un link nel sito, come nel wireframe, dove l'unico accesso è «Leggi tutti
  gli avvisi» della home.
- Itinerari: niente fascia della percorribilità né indicatori (call del 02/11, `decisioni.md` di oc:8783).
- Riga «Inserito da» nella fascia: l'API dà solo l'id dell'utente e gli utenti non sono legati a un ente; si
  riprende quando lo shard avrà il dato.
- Storico degli avvisi: ticket a parte (scrum 18/11).
- Segnalazioni e recensioni: restano in backlog con oc:8980 (scrum 16/11); qui si tolgono solo dal dettaglio.
- Scala 0–5 e «percorribile ma temporaneamente interrotto»: non esistono in oc:8989.
- Regola di import da Drupal (il valore unico copiato su percorribilità e validazione): è dello shard (oc:8989); il
  sito mostra ciò che lo shard espone, e un sentiero validato senza percorribilità mostra «Sconosciuta».

## Moduli toccati

Repo `wp-forestas`, child theme `themes/forestas-child/`:

- `wp-package/track.php`: fascia della percorribilità, fascia «Avviso» (anche sugli itinerari), indicatori a tacche
  con il numero di tacche dalla famiglia; tolti «Recensioni», «Segnala» e «Opinioni e recensioni»; con un plugin più
  vecchio della 0.5.0 il template del plugin.
- `inc/dettaglio.php`: letture spostate nel plugin (Blocchi Dettaglio, galleria, POI correlati, tabelle, luoghi,
  tipo del POI) sostituite dalle sue funzioni; `forestas_dettaglio_indicatori()` (livelli dal registro degli stati
  del plugin), fascia della percorribilità e avviso dalle funzioni del plugin; famiglia `route-type` indicata al
  plugin.
- `wp-package/poi.php`: galleria e foto dalle funzioni del plugin; con un plugin più vecchio il template del plugin.
- `inc/statuses.php` (nuovo): registrazione nel registro del plugin di `validation-status` e `inclusivity-difficulty`;
  controllo della versione del plugin (`forestas_plugin_ready()`), nomi e legenda degli stati per filtri e tacche.
- `inc/search.php`: filtri «Percorribilità» e «Validazione catastale» generati dall'elenco degli stati,
  `forestas_search_term_labels()`, POI nella ricerca unica, redirect dei vecchi link `poi`.
- `inc/notices.php` (nuovo) e `templates/notices-list.php` (nuovo): rotta `/avvisi`, lettura di `api/v1/avvisi` con
  copia, pagina.
- `inc/news.php`: `FORESTAS_REGOLE_VERSIONE`.
- `assets/css/dettaglio.css`, `assets/css/notices.css` (nuovo), `assets/js/dettaglio.js` (commento); `functions.php`;
  `style.css` (Version, colori dell'etichetta della riga per la percorribilità, etichetta «Avviso» comune a dettaglio
  e `/avvisi`, card dei POI).
- `tests/`: test della pagina degli avvisi, delle tacche, del dettaglio generato e dei filtri.

Repo `wp-forestas`, fuori dal child: `config/menu.json` (voce «Segnalazioni» tolta),
`docs/howto/ricreare-wordpress-da-zero.md` (conteggio delle voci del menu), `docs/knowledge/8969-…md` (itinerari e
segnaposto superati); `docker/scripts/init-wordpress.sh`
(`WP_PACKAGE_REF`) dopo il merge del plugin, in un commit a sé nella stessa PR.

Repo `wp-package`: registro degli stati, lettura di percorribilità e avviso, sorgente dei POI nella ricerca, valore
per i risultati senza campo (vedi l'overview del plugin).

Repo `forestas`: solo il puntatore del submodule `wp-forestas`, su indicazione del dev.
