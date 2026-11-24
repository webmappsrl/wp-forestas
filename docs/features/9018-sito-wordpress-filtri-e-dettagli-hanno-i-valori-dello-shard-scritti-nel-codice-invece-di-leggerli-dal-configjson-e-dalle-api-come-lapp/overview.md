> Ticket: oc:9018

# Sito WordPress: filtri e dettagli hanno i valori dello shard scritti nel codice, invece di leggerli dal config.json e dalle API come l'app

Overview del child `forestas-child` (repo `wp-forestas`). La parte del plugin sta in
`wp-package/docs/features/<stesso slug>/overview.md`. Le decisioni del dialogo, con le prove, sono in
`notes.md`.

## Cosa cambia

Il sito smette di avere nel codice i dati dello shard e li legge come fanno le app Webmapp:

- **i valori dei filtri e i loro nomi** arrivano dal `config.json` dell'app (`MAP.filters`,
  `LABELS.taxonomies`) e dai risultati della ricerca. Il child non scrive più nomi, traduzioni né
  identifier dei termini: sceglie solo quali filtri mostrare, in che ordine e con che aspetto;
- **i filtri di luogo** diventano gli overlay del `config.json` le cui aree portano un `layer_id`
  (oggi Aree Catastali e Aree geografiche). Spariscono Provincia, Comune e il filtro «Area» per nome.
  Il plugin li costruisce da solo; il child non dichiara nessuna FeatureCollection;
- **la riga di un percorso nei risultati** mostra l'ultimo luogo di `taxonomyWheres`, un comune, come la card
  delle app, invece di tutti i comuni;
- **la difficoltà** si filtra in tutte le scale (escursionistica, MTB, ippovia, mobilità dolce…), con un
  filtro per scala e il nome dello shard, non solo in quella escursionistica;
- **i parametri dell'indirizzo della ricerca** passano in inglese, come la webapp;
- **le linee dei percorsi sulla mappa** prendono il colore del layer dello shard (primo layer colorato,
  regola del plugin: quella delle app su Forestas darebbe il rosso di default). Il colore del tipo e
  la fascia dei titoli di sezione del dettaglio restano quelli del child, che passano il contrasto AA;
- **le tacche dell'accessibilità** tornano accese (bug: identifier maiuscoli nello shard, minuscoli nel
  child);
- **gli indirizzi dei dettagli** restano stabili anche sul dev, e i vecchi indirizzi di Drupal portano
  con un 301 alla pagina nuova (provato su dev e UAT; vale da solo quando il sito prende il dominio).

## Perché

Un termine, uno stato o un tipo aggiunto, tolto o rinominato in Nova oggi non arriva al sito: compare
senza nome tradotto e in fondo, o non compare affatto (description di oc:9018, verificato il 20/11 su
dev e UAT). Allo scrum del 19/11 si è deciso che lo shard è l'unica fonte dei dati e che nomi e
traduzioni si scrivono solo lì; allo scrum del 23/11 Giuseppe ha indicato che il sito deve funzionare
come la webapp, senza dati nuovi in Elastic, e il 23/11 alle 12:41 che le FeatureCollection non si
dichiarano ma si leggono dal `config.json`. Giuseppe ha chiesto di fare questo ticket prima di oc:9003.

## Requisiti

Spuntati: fatti e verificati in locale, sui dati del dev (test, browser, conteggi contro il DB del dev: `notes.md`).
Aperti: si possono fare o provare solo dopo il deploy su dev e UAT (procedure in `forestas/docs/howto/`).

### Comportamento come le app

- [x] Ogni filtro dei percorsi e dei POI prende valori e nomi dallo shard (`MAP.filters`, risultati
      della ricerca, `LABELS.taxonomies` per i nomi delle famiglie). Il nome si sceglie nella lingua
      della pagina, poi in italiano, poi il primo non vuoto, come `wmtrans` delle app (che usa la lingua di default
      dell'app, l'italiano su Forestas: la regola del plugin è `wmwp_text()`, notes del plugin, C6).
- [x] Un valore che il child non conosce compare con il nome dello shard; uno tolto in Nova sparisce.
      Un nome cambiato arriva quando lo shard riscrive il `config.json` o reindicizza, poi al rinnovo
      della cache del sito: oggi rinominare un'attività non riscrive il `config.json` (lacuna nelle
      note). Nessun valore viene scartato perché manca in un registro del sito; le opzioni di
      `MAP.filters` sono quelle che lo shard pubblica, come nelle app, anche quando ne manca una (su
      UAT il 24/11 manca `mtb`, 18 percorsi: il `config.json` è scritto a metà dell'import notturno;
      correzione nel ticket sullo shard).
- [x] Un'opzione con zero risultati non si mostra, come nelle app.
- [x] Ordine delle opzioni: quello dell'array di `MAP.filters` (oggi l'ordine in cui lo shard legge il
      DB, diverso fra dev e UAT: `sort_order` pubblicato è fra le richieste allo shard). Per le famiglie che `MAP.filters` non
      ha (difficoltà, percorribilità, validazione, tipo e ruolo degli enti) un ordine per famiglia nel
      child, scritto in un solo punto, come ripiego temporaneo: un valore che non vi compare si mostra
      lo stesso, in fondo.
- [x] Attività: le opzioni di `MAP.filters`, come le app, anche se oggi contengono `trail` («Sentiero») e
      non `accessible-trails`: la fonte vince anche quando è sbagliata (Rubens, 24/11). Il child non
      esclude più `trail`, `itinerary`, `walk` per nome; l'errore va nel ticket per lo shard.
- [x] Tipo di percorso: il filtro è `MAP.filters.layers` («Tipo percorso / Path type»: Sentieri,
      Itinerari, Cammini di Sardegna), come le app, e un percorso vi rientra con il suo `layers`. Il
      filtro del child sul `route-type` (`inc/search.php:559-564`, nomi a `:491-493`) si toglie: una sola
      fonte per filtro. Il link della vecchia ricerca di Drupal per tipo si traduce nel layer
      corrispondente. L'etichetta del tipo nelle righe e nel dettaglio resta sul `route-type`, con i
      nomi dello shard (decisione della challenge, 24/11).
- [x] Il child non ha più nomi propri dei valori: attività, difficoltà, tipo di percorso, validazione
      usano i nomi dello shard (la tabella dei nomi di oggi è in `notes.md`, per la decisione sull'import
      dello shard). Se lo shard dà un nome solo in italiano, la pagina inglese lo mostra in italiano con
      `lang="it"`.
- [x] Tipi e ruoli degli enti: l'API degli enti dà solo il valore grezzo. Finché non dà i nomi (lacuna
      da mandare a oc:8916), i nomi di oggi del child restano come ripiego dichiarato; un tipo o un ruolo
      nuovo compare con il valore grezzo invece di sparire.

### Luoghi

- [x] I filtri Provincia, Comune e «Area» (riconoscimento per nome, `FORESTAS_SEARCH_PROVINCES`,
      `^Area `, settori CAI) si tolgono da percorsi e POI.
- [x] I filtri di luogo sono quelli che il plugin costruisce dagli overlay del `config.json` le cui aree
      hanno `layer_id`; il child sceglie solo dove metterli nell'ordine dei filtri. Sul dev e su UAT
      oggi sono Aree Catastali (7) e Aree geografiche (39).
- [x] Un percorso sta in un'area se il suo `layers` contiene il `layer_id` dell'area; un POI se la sua
      geometria cade nel poligono dell'area (indicazione di Giuseppe, scrum del 23/11).
- [x] La riga del percorso nei risultati mostra l'ultimo luogo di `taxonomyWheres`. Un test sui dati
      controlla che l'ultimo luogo sia un comune (oggi 772 su 772 sul dev, 170 su 170 su UAT).
- [x] Il dettaglio continua a dividere comuni, province e regione con `_admin_level`
      (`wmwp_detail_places()`).

### Difficoltà

- [x] Prima di cambiare il filtro, un controllo: scelto un valore, i risultati hanno davvero quel
      valore (non solo «ci sono risultati»).
- [x] Un filtro per scala di difficoltà (`*-difficulty`), con il nome della scala dello shard
      (`LABELS.taxonomies`: «Difficoltà escursionistica», «Difficoltà mountain bike», «Difficoltà
      ippovia»…) e per valore l'identifier intero: nessuna scala si mescola con un'altra. Ogni filtro
      compare solo se ha percorsi. Ordine dentro la scala: `sort_order` dello shard quando lo pubblica,
      intanto l'ordine di ripiego del child (decisione della challenge, 24/11).
- [x] Le scale si riconoscono dal suffisso `-difficulty` delle famiglie, senza un elenco di scale nel
      codice. Esclusa `inclusivity-difficulty`, che è il grado di accessibilità e ha il suo posto.

### Indirizzo della ricerca

- [x] Parametri in inglese, uguali in tutte le lingue: il child non traduce più i parametri del plugin
      (`tab`, `pg`, `view`, `map`, `q`) e le chiavi dei filtri sono in inglese. Il valore è
      l'identifier dello shard; per i filtri di luogo l'id del layer dell'area, in un solo parametro
      `layer` come le app (overview del plugin). Anche i valori scritti dal sito sono in inglese: `tab`
      (`trails`, `points-of-interest`, `content`, `organisations`) e i tipi di `content` (`news`, `event`,
      `good-to-know`, `page`) (deciso dal dev, 24/11).
- [x] Il redirect dei vecchi link di ricerca di Drupal (`f[N]=…`) porta ai nomi nuovi, e così anche
      quelli con il solo testo (`?search_api_fulltext=…`, senza `f`: oggi il redirect scatta solo con
      `f`, `inc/search.php:874`) e il redirect di `?s=` (`:869`), che oggi scrive `search_api_fulltext`. La sua tabella
      «chiave di Drupal → identifier» (`sentiero` → `route-type-trail`…) resta nel codice: traduce
      indirizzi vecchi, che non cambiano più, non dati dello shard.

- [x] Slug inglese della pagina di ricerca tradotto: `/en/search-in-our-website/` (`config/pagine.json`), come le
      altre pagine con lo slug inglese; il vecchio `/en/cerca-nel-sito`, di Drupal e del sito di prima, porta lì con un
      301 tenendo i parametri, così i vecchi link della ricerca inglese finiscono sui parametri nuovi (deciso dal dev,
      24/11).

### Dettagli

- [x] Tacche dell'accessibilità: il confronto degli identifier dei gradi (`inclusivity-difficulty-AT`,
      `-AE`, `-AEE`) funziona con le sigle maiuscole dello shard; il test usa i dati veri. Nomi dei gradi
      dallo shard; nel registro del child (`inc/statuses.php`) resta solo l'ordine, come ripiego.
- [x] Tipologie di disabilità (`inclusivity-disabilities-*`): il blocco dell'accessibilità del
      dettaglio resta com'è (deciso dal dev nella challenge, 24/11: lo stile dell'accessibilità del sito
      non si rompe). Su ogni percorso mostra le tre tipologie, motoria, sensoriale e psichica cognitiva,
      accese o spente (`wp-package/track.php:182-205`), e il JSON porta solo quelle accese: per le spente
      servono l'elenco, i nomi e le icone del child (`inc/dettaglio.php:463-472`, icone `txn-disability-*`
      del set dello shard o il disegno a tratto). L'icona del termine, quando c'è, vale come oggi. Lo
      shard va allineato al sito, perché le app potranno usarli: nei termini gli stessi nomi in italiano
      e inglese e le stesse icone (oggi «Fisica e motoria» `txn-mobility-disability`, «Cognitiva e
      psichica» `txn-cognitive-disability`, «Sensoriale» senza icona, nessun nome inglese). È fra le
      richieste del ticket sullo shard (`notes.md`, da creare a fine lavoro); le icone nei termini erano già la bozza
      di ticket di fine lavori di oc:8969.
- [x] Ruoli degli enti nel dettaglio (`forestas_dettaglio_ruoli()`) e categoria dell'ente nell'elenco
      `/enti` (`forestas_entes_type_category()`, gerarchia dei tipi di Drupal): ripieghi dichiarati come i
      tipi e i ruoli dei filtri, finché l'API degli enti non dà nomi e gerarchia; un ruolo o un tipo nuovo
      compare con il valore grezzo, senza categoria.
- [x] Tipo del dettaglio (sentiero, itinerario, cammino): la tabella `route-type-*` → tipo sta in un
      solo punto del child invece che in quattro. Un tipo che il child non conosce prende il nome dello
      shard, il colore neutro del testo del sito e la breadcrumb generica; non diventa «sentiero».
- [x] Difficoltà per attività nel dettaglio: famiglie e nomi delle famiglie dallo shard
      (`LABELS.taxonomies` e i termini della traccia), nessuna famiglia scritta nel codice. Una famiglia
      nuova compare.
- [x] Download: oltre al GPX, i formati che lo shard dà in `forestas.downloads` (KML, GeoJSON).
- [x] Pulsante «Stampa» nel dettaglio di percorsi e POI (deciso dal dev, 24/11), solo se la stampa dell'app è
      accesa (`OPTIONS.ec_pdf_enabled` del `config.json`): link alla scheda PDF dello shard nella lingua della pagina
      (`wmwp_detail_print_url()` del plugin), `rel="nofollow"`, nuova finestra annunciata allo screen reader. Il
      `robots.txt` dello shard esclude `/print/` (`Print.md` del wm-package).
- [x] Anello: dal campo `roundtrip` dello shard, non dalla distanza fra inizio e fine (nel child
      `inc/dettaglio.php:1323-1342` e nel plugin `track-map.js`).
- [x] Legenda della mappa del POI: nomi dei tipi dai percorsi correlati, non scritti nel child.
- [x] Area sosta attrezzata nel dettaglio del POI: riconosciuta sia come `equipped-rest-area` sia come
      `poi_type_equipped-rest-area`.
- [x] Classi di inclusività («Sentiero per tutti»): nomi, intervalli e tacche restano nel child come
      ripiego dichiarato in un solo punto, perché lo shard non li pubblica (lacuna da mandare a
      oc:8916).

### Colori

- [x] Il colore del tipo e la fascia dei titoli di sezione del dettaglio restano quelli del child
      (`forestas_dettaglio_colori()`), presi da Drupal: passano il contrasto AA con il testo bianco.
- [x] Le linee dei percorsi sulla mappa prendono il colore del layer dal plugin (primo layer colorato
      nell'ordine di `MAP.layers`, vedi l'overview del plugin); il child non passa più colori suoi alla
      mappa.

### Indirizzi stabili e redirect da Drupal

- [ ] Dev: tolte dallo storico dei percorsi le 170 righe con chiave per id (`i:174` … `i:597`), con una
      copia prima, come su UAT il 23/11. Procedura in `forestas/docs/howto/messa-in-opera-wordpress-dev.md`: si fa
      dopo il deploy sul dev.
- [x] Produzione: nella procedura di messa in opera, il controllo che il `.env` dello shard non abbia
      `EC_TRACK_MODEL`.
- [ ] I vecchi indirizzi di Drupal (`/sentieri/…`, `/sentiero/…`, `/itinerario/…`, `/da-vedere/…`,
      `/poi/…`) portano con un 301 alla pagina nuova, con una corrispondenza elemento per elemento sul
      nid di Drupal (`sardegnasentieri_id`) e su `properties.forestas.url`. L'indirizzo Drupal di ogni
      elemento si salva nello storico degli indirizzi prima che serva, e non si cancella: quello dei
      POI dalla `pois.geojson` (976 su 976 con l'indirizzo, UAT 24/11); quello dei percorsi, che
      nell'elenco di Elastic non c'è, leggendo alla ricostruzione dell'indice il dettaglio dei percorsi
      di cui manca (170 letture la prima volta, poi solo i nuovi). Così un WordPress nuovo, come quello
      di produzione, ha tutti gli indirizzi prima del cambio di dominio. Vale anche per le pagine
      inglesi di Drupal: `/en/` più l'indirizzo italiano (stesso nome in 152 percorsi su 153 e in 170
      POI su 412); per gli altri 242 POI serve l'indirizzo inglese dallo shard (note). Gli indirizzi
      con caratteri codificati (39 percorsi e 37 POI su UAT, es. `/sentieri/s%E2%80%99araighinos-t-511b`)
      entrano per nome nella prova (decisioni della challenge, 24/11). Su
      Drupal i sentieri hanno due prefissi, `/sentieri/` (173, i più vecchi) e `/sentiero/` (442), e
      il nome spesso cambia (su UAT il 24/11: 106 percorsi su 169 e 258 POI su 976): il redirect vale
      per ogni indirizzo vecchio diverso da quello nuovo. Provato in locale sui dati del dev (3512 indirizzi su
      3512, italiano e inglese); su dev e UAT si prova dopo il deploy. Il prefisso dei dettagli
      (`/sentiero/` o `/sentieri/` sotto la pagina d'elenco) si decide in oc:9003 (segnalato il 24/11):
      il redirect usa quello che esce da lì. Le schede PDF di oc:8982 non usano più `properties.forestas.url`
      (forestas `1123b46`): il QR code porta a `?wmwp_detail=<tipo>&wmwp_id=<id>`, che il plugin risolve nella pagina
      del dettaglio.

### UX e accessibilità

- [x] [UX] I filtri di luogo usano la faccetta del plugin che c'è già: `fieldset` con `legend`, prime 5
      opzioni e «Mostra altri» (39 aree geografiche), numero dei risultati letto dallo screen reader.
- [x] [UX] I nomi lunghi dello shard («Dorsali escursionistiche a tappe (per esperti)») vanno a capo e
      non si troncano: sono i nomi che distinguono le opzioni.
- [x] [UX] Un nome in una lingua diversa dalla pagina porta l'attributo `lang` (WCAG 3.1.2).
- [x] [UX] Il tipo di percorso non si riconosce solo dal colore: il nome del tipo resta scritto.
- [x] [UX] Controllo WCAG 2.2 AA e screen reader su ricerca e dettaglio a fine lavoro, anche sotto i
      600px.

## Rischi

- **Ordine dei luoghi.** Che l'ultimo luogo sia un comune dipende dall'ordine in cui lo shard scrive
  `properties.taxonomy_where`, non da una regola del codice. Mitigazione: test sui dati; se l'ordine
  cambia, la riga mostra comunque un luogo vero del percorso.
- **Nomi dello shard diversi da quelli di oggi.** «Alte vie» al posto di «Camminate giornaliere», «E»
  al posto di «E — escursionistico». È voluto: il dev valuterà se cambiare l'import dello shard con i
  nomi di oggi (tabella in `notes.md`).
- **Pagina inglese con nomi italiani** dove lo shard non ha l'inglese (validazione, difficoltà, layer).
  Mitigazione: `lang="it"`; le traduzioni si chiedono nel ticket per lo shard.
- **Indice della ricerca e DB dello shard non allineati.** Il 23/11 l'indice del dev aveva 772 percorsi
  e il DB 170; il 24/11 alle 15:30 il DB del dev ne ha 778, dopo un import del pomeriggio. Le verifiche
  sui filtri si fanno contando anche nel DB, con l'ora del conteggio.
- **UAT senza oc:9001.** Le Aree geografiche su UAT non hanno percorsi nei layer: il filtro delle aree
  geografiche per i percorsi resta vuoto e non si mostra finché oc:9001 non arriva su UAT. Per i POI
  funziona già, con le geometrie.
- **Redirect da Drupal.** Prefisso e nome spesso non coincidono (su UAT il 24/11 cambia il nome in 106
  percorsi su 169 e in 258 POI su 976): senza una corrispondenza per nid alcuni indirizzi finirebbero
  sulla pagina sbagliata. Mitigazione: corrispondenza per nid, prova su tutti gli elementi di dev e UAT.
- **`properties.forestas.url` che cambia.** Oggi lo scrive l'import da Drupal (alias italiano del nodo,
  `SardegnaSentieriDatabaseSource.php:752-777`) e la stampa di oc:8982 prevede che, al passaggio a
  WordPress, contenga il nuovo indirizzo (`ForestasPrintSheet.php:424-427`); chi lo scriverà non è
  deciso. Se cambia prima che il sito abbia salvato gli indirizzi vecchi, l'indirizzo Drupal di ogni elemento
  si perde. Mitigazione: il sito salva l'indirizzo Drupal di ogni elemento alla ricostruzione
  dell'indice, nello storico degli indirizzi del plugin, e da lì non si cancella; se il campo cambia, gli indirizzi
  vecchi restano. Proposta ad Alessandro il 24/11 (oc:8982): QR code delle schede con l'indirizzo per
  id del plugin (`?wmwp_detail=track|poi&wmwp_id=<id>`, verificato sul dev) invece di riscrivere
  `properties.forestas.url`.

## Out of scope

- Modifiche allo shard (forestas e wm-package): le lacune vanno nel ticket per lo shard da creare a fine
  lavoro e alla sessione di oc:8916 (sezione «Da chiedere allo shard» di `notes.md`).
- Nuovi filtri per le famiglie che lo shard ha e il sito non filtra (avvertenze, tipologia degli
  itinerari).
- La decisione sui nomi da mostrare (attività, difficoltà): contenuto da concordare con il cliente.
- Il prefisso degli indirizzi dei dettagli (`/sentiero/` o `/sentieri/`): si decide in oc:9003.
- Il QR code delle schede PDF: è di oc:8982 (proposta mandata ad Alessandro il 24/11).

## Moduli toccati

- `themes/forestas-child/inc/search.php`: filtri di percorsi, POI ed enti, parametri dell'indirizzo,
  redirect dei vecchi link di ricerca di Drupal, riga del percorso.
- `themes/forestas-child/inc/statuses.php`: registro degli stati (gradi di accessibilità, validazione).
- `themes/forestas-child/inc/dettaglio.php`: tipo del dettaglio, colori, difficoltà per attività,
  download, anello, legenda della mappa del POI, area sosta attrezzata, ruoli degli enti.
- `themes/forestas-child/wp-package/track.php`, `poi.php`: pulsante «Stampa».
- `forestas/public/robots.txt`: `Disallow: /print/`.
- `themes/forestas-child/inc/entes.php`: tipi e ruoli degli enti.
- `themes/forestas-child/inc/pagine-statiche.php` o un file nuovo per i redirect dei dettagli di Drupal.
- `themes/forestas-child/style.css`: colore neutro del tipo sconosciuto, e `Version` alzata a ogni
  modifica di CSS o JS.
- `themes/forestas-child/tests/`: test di dettaglio, ricerca e redirect.
- `docs/knowledge/`: pagina del ticket.
