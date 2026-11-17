> Ticket: oc:8990

# Notes — Enti sul sito WordPress

## Divergenze dal piano, task per task

### Task 1: helper delle API, intestazione Accept e timeout

- **`forestas_entes_api_get( string $path, array $query = [], int $timeout = FORESTAS_ENTES_TIMEOUT ): array`** è
  l'unico punto che costruisce l'indirizzo e mette il token. Lo usano `forestas_entes_fetch()` e la ricerca del task 6,
  così il codice del token non si duplica (ruling del preflight). Il terzo parametro serve alla ricerca, che aspetta 8
  secondi invece di 20.
- **Intestazione `Accept: application/json` oltre al token**: senza, Laravel risponde al 422 di validazione di
  `api/v1/entes/search` con un redirect invece che con il codice.
- **Costanti e funzioni in più**: `FORESTAS_ENTES_SCHEDULE`, `FORESTAS_ENTES_TIMEOUT`, `forestas_entes_token()`,
  `forestas_entes_copy()`, `forestas_entes_counts()`, `forestas_entes_refresh_failed()`, `forestas_entes_schedule()`.
- **Dalla review del task 1, fatte nel task 2**:
  - il transient di attesa si scrive prima della prima richiesta sincrona, così le visite che arrivano mentre è in
    corso non la ripetono (superato nel secondo giro della review interna: le visite le ferma il lucchetto del rinnovo,
    e l'attesa si scrive solo dopo un fallimento senza copia);
  - la copia si legge dal database una volta per richiesta (`forestas_entes_copy()` con una variabile `static`, che si
    dimentica quando l'option si scrive o si cancella).
- **Passo 1 verificato con `WP_PACKAGE_SHARD`**, che viaggia nello stesso `env_file` del token, invece che con il token
  vero: in locale il token non c'era. La conferma con il token vero resta per la verifica finale.
- **Passo 2, test dell'evento**: il test non chiama `do_action( 'init' )`, che da `eval-file` si ferma con un errore
  fatale nell'init di Impreza. Controlla `has_action( 'init', 'forestas_entes_schedule' )` e chiama la funzione
  direttamente; il collegamento vero è provato con `wp cron event run forestas_entes_refresh`.
- **In locale il sito usava lo shard locale** (superato): nel task 1 il sito locale leggeva lo shard locale con il
  mu-plugin non versionato `wpf-shard-locale.php`, e overview e piano erano stati corretti in quel senso. La decisione
  finale è l'opposto: di default il sito locale legge il dev (`WP_PACKAGE_SHARD=forestasdev`, token del dev nel `.env`
  di `wp-forestas`), e lo shard locale solo con il mu-plugin (vedi «Info per altri ticket»).

### Task 2 e 3: fatti insieme, nomi dei tipi e chiavi aggiunte

- **Fatti insieme**: lo stesso file e la stessa review.
- **Nomi italiani dei tipi dai termini Drupal** (`structure_sync.data.yml`, tassonomia `tipo_ente_istituzione_societa`),
  come li mostra la produzione: «Altre Pubbliche Istituzioni» e «Privato/associazione», diversi da quelli del piano.
  Drupal non ha l'inglese dei termini, quindi l'inglese resta quello del piano.
- **Chiavi in più**:
  - `id` negli elementi dell'elenco, per le card della ricerca;
  - `didascalia` nell'immagine, per l'`alt` della testata;
  - `description_lang` e `contacts_lang`, come `name_lang`;
  - dal task 5, `category`.
- **`forestas_entes_url()` parte da `get_option( 'home' )`, non da `home_url()`**: sul sito WPML filtra `home_url()` con
  la lingua della pagina. È lo stesso motivo per cui `wmwp_detail_url()` del plugin usa l'option.
- **`forestas_entes_list()` legge `forestas_entes_cached()`, non `forestas_entes_all()`**: la prima richiesta allo shard
  e il 503 spettano alla rotta, e il tab della ricerca a testo vuoto non fa partire una richiesta sincrona.
- **`forestas_entes_type_label()` usa la lingua corrente di WPML**: la firma del piano non ha la lingua. Sulle pagine
  le due lingue coincidono, ed è scritto nei docblock.
- **Titoli di descrizione e contatti da h3**, sotto i titoli di sezione h2; i contatti, sotto l'h3 «Contatti», si
  rinumerano da h4 nel template.
- **Nome dei sentieri gestiti, dalla review**: il controllo accetta anche il nome italiano della traccia, così una
  traduzione scritta dopo l'ultima ricostruzione dell'indice non toglie la card. Spostato nel task 4, che lavorava
  sullo stesso file.

### Task 4: hook aggiuntivi e cache delle lingue di WPML

- **Hook in più: `parse_query`, `posts_pre_query` e `pre_handle_404`.** Senza, una pagina degli enti è per WordPress la
  home del blog: legge le news, e `WP::handle_404()` dà 404 a `/enti/page/<n>/` quando `<n>` supera le pagine delle
  news, prima che la rotta decida.
- **Selettore di lingua**: il filtro `icl_ls_languages` legge le lingue del child prima di accendere il suo guard. Il
  motivo è in «Bug trovati».
- **Il 503 usa la pagina «non disponibile» del plugin** (`wmwp_locate_template( 'detail-unavailable.php' )`), la stessa
  dei dettagli di percorsi e POI, invece di un terzo template.
- **`hreflang` con `wmwp_detail_hreflangs()` del plugin**, invece di una copia nel child.
- **Titolo inglese dell'elenco tradotto** («Organisations, institutions and companies»), anche se Drupal su `/en/enti`
  lo lascia in italiano: le altre etichette inglesi del child sono tradotte (ruling).
- **Passo 4 con una copia temporanea**: i controlli con curl sono stati fatti con il JSON dello shard locale scritto
  nell'option, poi cancellato. Con il token vero si ripetono nella verifica finale.

### Task 5: filtro Argomento, ordine della galleria e card con il tipo padre

- **Filtro «Argomento» aggiunto** (ruling, regola «Drupal 1:1»: i filtri delle liste si allineano senza chiedere).
  - Le voci sono i cinque tipi, come la faccetta di Drupal (`field_tipo_ente`, senza gerarchia), non le categorie
    della card.
  - Funzioni nuove: `FORESTAS_ENTES_TYPE_PARAM`, `forestas_entes_type()`, `forestas_entes_listed()` e
    `forestas_entes_type_facet()`; rotta, indirizzi, selettore e `hreflang` tengono il filtro.
  - La colonna dei filtri di news ed eventi è stata estratta in `forestas_filtri_colonna()` di `inc/griglie.php`. L'HTML
    delle liste delle news è rimasto identico.
- **Galleria dopo «Sentieri gestiti»**, come su Drupal (`content_infos_managed` prima di `content_infos_gallery`):
  l'overview la metteva prima ed è stata corretta.
- **Card con il tipo padre**: la teaser di Drupal stampa il padre del termine, o il termine stesso se è una radice
  (`forestas_entes_type_category()`). Comune e Complesso forestale sono figli di Altre Pubbliche Istituzioni nella
  tassonomia di Drupal (gerarchia letta dalla copia locale del database; `structure_sync.data.yml` è vecchio).
- **Modifiche fuori dai due template**: una riga di `assets/js/dettaglio.js` (la radice comprende `.forestas-ente`,
  altrimenti «Mostra tutti (N)» resta nascosto) e il commento di `assets/css/dettaglio.css`.
- **Passo 4 con la copia dello shard locale**: confronto con la produzione a 1512, 800 e 390px, in italiano e in
  inglese. Restano da guardare con il token vero, su dev, le descrizioni con l'HTML e le immagini, che nel MinIO locale
  rispondono 404.

### Task 6: testo vuoto dalla copia e al massimo 50 risultati

- **Testo vuoto senza copia: 503.** Il piano chiedeva solo il caso con la copia. Senza copia il dato non è disponibile,
  e la prima richiesta allo shard spetta alle pagine degli enti, non a una chiamata REST fatta mentre si scrive.
- **Al massimo 50 risultati**: `api/v1/entes/search` restituisce al massimo 50 enti (`EnteSearchQuery::SIZE` dello
  shard) senza il totale, quindi `total` non supera 50.
- **Link solo verso gli enti della copia**: un ente trovato dalla ricerca ma non ancora nella copia ha la card senza
  `url`, come prevede la card del plugin.
- **Attesa di 8 secondi** (`FORESTAS_ENTES_SEARCH_TIMEOUT`), meno dei 15 dopo cui il plugin abbandona una source.
- **In più**: `search_label` della source («Cerca un ente» / «Search for an organisation»); gli argomenti delle due
  rotte REST in `forestas_search_rest_args()`.
- **Messaggio d'errore**: il plugin mostra nel tab il suo messaggio fisso, non quello della rotta. Un messaggio proprio
  richiederebbe una modifica a `wp-package`.
- **Passo 4 con uno shard finto**: la ricerca riuscita è stata vista con un mu-plugin temporaneo che fingeva
  `api/v1/entes/search` (in locale il token non c'era); l'errore anche con lo shard spento davvero. La ricerca vera
  resta per la verifica finale con il token.

### Task 8: cestino, opzione della privacy e lingue di WPML

- **`wp_trash_post()`**, come nel piano, e non `wpf_wpml_cancella_post`, che cancella per sempre.
- **Option della privacy**: `wp_page_for_privacy_policy` si azzera solo se punta alla pagina 3 e quella pagina è la
  bozza di default, cioè la manda nel cestino questo giro o è già nel cestino con lo slug di default (anche
  `privacy-policy__trashed`). Il piano la azzerava sempre quando valeva 3 (vedi «Bug trovati»).
- **Lingue di WPML**: la chiamata sta dentro `wpf_wpml_tutte_le_lingue()`, come il passo dei menu, così `get_post()` non
  dipende dai filtri per lingua.
- **Test automatico del passo**: il piano non lo prevedeva; lo ha chiesto la review finale.

### Review interna: freni della ricerca, copia snella e pulizie

Il primo giro della review interna (16/11) ha portato queste modifiche, oltre ai bug e alle decisioni qui sotto.

- **Freni della ricerca degli enti** (`inc/search.php`): limite di 30 ricerche con un testo al minuto per visitatore,
  oltre 429 con `Retry-After`; pausa di 30 secondi dopo un errore della ricerca, con 503 senza chiamare lo shard e una
  riga di log per pausa (nel secondo giro solo per i guasti dello shard, e contatori in un'option sola). L'IP del
  visitatore viene da X-Forwarded-For solo dietro un REMOTE_ADDR privato (il proxy dell'host), l'ultimo indirizzo
  pubblico da destra.
- **Copia snella** (`forestas_entes_slim()`): solo le chiavi lette dal child, riscritta solo quando i dati cambiano. Lo
  stato dei rinnovi (`forestas_entes_state`) e gli slug (`forestas_entes_slugs`) in due option piccole; dettagli di
  percorsi e POI e ricerca con un testo leggono solo gli slug (`forestas_entes_known_slugs()`), mentre la ricerca a
  testo vuoto elenca gli enti della copia. Misure sul dev: da 2.185.719 a
  858.963 byte serializzati, lettura da circa 3,2 a 0,7 ms e da 5 a 2,5 MB.
- **Rinnovo**: un calo sotto la metà si accetta se dura più di 3 ore dal primo rifiuto; con WP-Cron fermo da più di
  un'ora la prima pagina degli enti rinnova da sé, con l'attesa come lucchetto (nel secondo giro con il lucchetto
  atomico); una riga di log per gli slug doppi di una copia nuova; evento tolto quando si cambia tema.
- **Token** solo in https o verso un host locale (`forestas_entes_token_allowed()`); il motivo degli errori HTTP in un
  solo punto (`forestas_entes_http_reason()`, con il 429), e il token non si controlla più due volte.
- **Pulizie**: card dell'elenco e della ricerca da `forestas_entes_list_item()` con le misure dell'immagine in
  `FORESTAS_ENTES_CARD_IMAGE`; data della `<time>` da `forestas_date_iso()` (anche in `forestas_dettaglio_data()`);
  testo della pagina web tagliato da `forestas_trim_link_text()` (anche nei collegamenti delle news); query var e
  regola degli enti in costanti e in `forestas_entes_rule_matched()`; nome dell'intervallo WP-Cron dalla costante.
- **CSS**: la barretta dei titoli degli enti nel gruppo della barretta di `style.css`, e `.forestas-griglia-vuota` in
  `style.css` (vedi «Decisioni»); `style.css` alla Version 1.16.16.

### Review interna, secondo giro: pausa della ricerca, lucchetto del rinnovo e contatori

Il secondo giro della review interna (16/11) ha portato queste modifiche.

- **Pausa della ricerca solo quando lo shard non è disponibile** (`inc/search.php`): rete (codice 0), 401, 403, 429,
  5xx o risposta 200 senza la lista `data`. Un 4xx che dipende dal testo (400, 422 e gli altri 4xx,
  `forestas_entes_search_refused()`) risponde «nessun risultato» (200, `total` 0), senza pausa né log (vedi «Bug
  trovati»).
- **Testo normalizzato prima di decidere se è vuoto** (`forestas_entes_search_text()`): via in testa e in coda spazi
  Unicode, caratteri di controllo e di formato e gli altri di `Str::INVISIBLE_CHARACTERS` di Laravel (verificato: i 56
  caratteri di Laravel e quelli di `trim()` si tolgono tutti). Un testo fatto solo di quelli prende la strada del testo
  vuoto, senza chiamare lo shard.
- **Contatori del limite in un'option sola** (`forestas_entes_search_counters`, non autoload): impronta dell'IP →
  `[ ricerche, inizio della finestra ]`, senza le finestre chiuse a ogni scrittura e con un tetto di 2000 visitatori
  (`FORESTAS_ENTES_SEARCH_VISITORS`, 144 KB serializzati al massimo). Prima erano un transient per visitatore (vedi
  «Decisioni»).
- **Un rinnovo alla volta** (`inc/entes.php`): `forestas_entes_refresh()` prende il lucchetto
  `forestas_entes_refresh_lock` con `forestas_entes_lock()`, una sola istruzione SQL atomica (INSERT con `ON DUPLICATE
  KEY UPDATE … IF(…)`), e lo lascia alla fine; un lucchetto di più di 120 secondi si riprende. Chi lo trova preso non
  chiama lo shard: WP-Cron con una riga di log `rinnovo saltato`, una visita senza. Le visite provano a rinnovare una
  volta per richiesta (`forestas_entes_refresh_tried()`), e l'attesa di 5 minuti non si scrive più prima del rinnovo.
- **Rinnovo scaduto lasciato a WP-Cron** (`forestas_entes_leave_to_cron()`): con la copia, se WP-Cron è appena partito
  (`doing_cron` di meno di un minuto) e l'evento del rinnovo è da eseguire, la pagina esce con la copia e segna
  `left_to_cron` nello stato; dopo 120 secondi senza rinnovi (`FORESTAS_ENTES_CRON_WAIT`) rinnova da sé.
- **Opzione degli slug riallineata a ogni rinnovo**: anche con gli stessi dati il rinnovo confronta
  `forestas_entes_slugs` con `forestas_entes_slug_set()` e la riscrive se manca o è diversa, senza riscrivere la copia.
- **Documenti**: righe di log citate per intero nella howto, `::1` fra gli host locali, il plugin che ripiega sulla
  copia per ogni codice diverso da 200, 304 e 404 (knowledge 8969 del plugin), la ricerca a testo vuoto che legge la
  copia, il log dei tipi sconosciuti solo per una copia nuova.

## Bug trovati

- **Selettore di lingua dalla cache di WPML** (task 4).
  - Sul dettaglio di un ente, «EN» dell'header portava a `/en/`, la home.
  - WPML tiene in cache per la richiesta ogni lettura delle lingue (`WPML_LS_Languages_Cache`), filtro
    `icl_ls_languages` compreso. La prima lettura del child (`forestas_entes_wpml_code()`, chiamata da
    `forestas_entes_url()`) avveniva dentro il filtro, con il guard acceso, e finiva in cache con gli indirizzi della
    home; il selettore dell'header li leggeva da lì.
  - Correzione: il filtro legge le lingue prima di accendere il guard. Un test fallisce senza la correzione.
- **Variabile del test sovrascritta** in `tests/entes-view.php`: una variabile riusata da un ciclo faceva controllare a
  un caso un valore diverso da quello che il caso dichiarava, e il caso passava lo stesso. L'ha trovata la review
  finale, ed è corretta nel giro finale del codice.
- **Option della privacy azzerata anche per una privacy vera** (task 8, review): il primo giro azzerava
  `wp_page_for_privacy_policy` ogni volta che valeva 3, anche con una pagina 3 modificata o con un altro slug, che può
  essere la privacy vera del sito. Ora la condizione è la stessa della pagina.

- **Tab «Contenuti del sito» in italiano su `/en/cerca-nel-sito`** (review interna, codice di oc:8952):
  `forestas_search_rest()` passava a WPML `en`, che WPML non conosce (il suo codice è `en-us`): cambiavano i testi ma
  non gli indirizzi. Ora usa `forestas_entes_wpml_code()`, come la rotta degli enti; con `lang=en` le pagine hanno
  `/en/` (13 su 13 il 16/11).
- **Avvisi PHP con le query var del core sugli indirizzi degli enti** (review interna): `/enti/?p=1` e
  `/enti/<slug>/?page_id=2` rispondevano 200 ma scrivevano 5 avvisi «Attempt to read property … on null»
  (`post-template.php`, `get_body_class()`), perché `p` e `page_id` accendevano is_single e is_page. Ora sulle regole
  degli enti valgono solo le query var della regola.
- **Slug degli enti non puliti nei dettagli e nella ricerca** (review interna): `forestas_dettaglio_enti()` e la ricerca
  costruivano l'insieme degli slug senza `trim` e senza controllare il tipo, diversamente da `forestas_entes_slug()`.
  Ora tutti passano da `forestas_entes_known_slugs()` e `forestas_entes_slug()`.

- **Ricerca degli enti ferma per tutti con un testo invisibile** (secondo giro della review interna): ogni risposta
  diversa da 200 faceva partire la pausa di 30 secondi per tutto il sito. Un testo di soli U+200B, U+00A0 o U+FEFF
  passa `sanitize_text_field()` e `trim()`, arriva allo shard, che con TrimStrings e ConvertEmptyStringsToNull lo
  rende null e risponde 422: due richieste al minuto tenevano spento il tab «Enti» per tutti. Ora quel testo è vuoto
  già nel sito, e un 4xx del testo non fa partire la pausa. I test falliscono con il codice di prima.
- **Due rinnovi insieme dopo un'ora senza visite** (secondo giro della review interna): la prima visita a `/enti/` faceva
  partire WP-Cron su `init`, che rinnovava senza guardare l'attesa, e poi rinnovava anche la pagina; l'attesa letta e
  scritta con due chiamate non era comunque un lucchetto. Ora il lucchetto è atomico e condiviso, e la pagina lascia il
  rinnovo a WP-Cron appena partito. Verificato dal vivo: prima visita 0,62 s con il rinnovo lasciato a WP-Cron, visita
  dopo 120 secondi 3,6 s con il rinnovo, lucchetto tolto.

- **Indice dei dettagli rifiutato passando dallo shard locale al dev** (solo locale, 16/11): disattivato il mu-plugin
  `wpf-shard-locale.php`, il plugin ha scartato l'indice del dev con «la chiave della fonte è crollata
  ({"track":0, …})», perché l'indice locale aveva i nid e quello del dev no (vedi Follow-up). Il sito restava sugli id
  locali: dettagli dei percorsi in 404 e sentieri gestiti nascosti dal controllo del nome. Si risolve cancellando la
  cache dell'indice locale (il transient `wmwp_detail_index`, le option `wmwp_detail_index_last` e
  `wmwp_detail_index_rebuilt_at`) e ricostruendolo; i comandi sono nelle trappole del `CLAUDE.md`. Succede solo dal
  locale al dev, e sul dev e su UAT non succede: lì l'indice viene sempre dallo stesso shard.

## Decisioni

- **Tag di Orchestrator**: nessun tag associato né creato; il dev ha detto che quelli presenti (26Q4 e tag 692)
  bastano (16/11).
- **Design**: contenuti, ordine e comportamento come Drupal; stile delle pagine e dei post già presenti nel
  child. Per questo «Sentieri gestiti» usa la griglia con «Mostra tutti (N)» dei «Percorsi correlati» invece
  del carosello di Drupal: il child non ha caroselli (16/11).
- **API degli enti**: la card dei sentieri gestiti (`route_type`, `feature_image`, `distance`,
  `duration_forward`) e `created_at` delle tracce li ha aggiunti Alessandro il 16/11 (commit `2fb929f` di
  forestas), su richiesta di questo ticket. Resta da chiedergli che le date di enti e tracce siano giuste
  anche per ciò che nasce o cambia in Nova dopo lo spegnimento di Drupal: oggi `created_at` dell'ente è
  `drupal_created_at`, `updated_at` è `properties.forestas.updated_at`, `created_at` delle tracce è
  `properties.forestas.created_at`. La richiesta la manda il dev (16/11).
- **Lo shard è la fonte definitiva, Drupal è temporaneo**: nessuna logica del sito dipende da un dato che
  esiste solo per i contenuti importati da Drupal; `lang="it"` solo quando il testo `en` coincide con `it`.
- **Ricerca degli enti senza cache per testo cercato, con un limite per visitatore**: con la ricerca mentre si scrive
  quasi ogni testo è nuovo, e la cache lascerebbe solo righe in `wp_options` senza risparmiare richieste (challenge,
  16/11). Lo shard si protegge invece con un limite per visitatore e una pausa dopo un errore (review interna, 16/11):
  il limite al minuto dello shard conta per IP, e il sito è un IP solo con la stessa chiave per la ricerca e per il
  JSON della copia; la pagina 8965 di forestas assegna il limite per visitatore a WordPress.
- **Contatori del limite in un'option sola, con un tetto** (secondo giro della review interna, 16/11): nel primo giro
  erano un transient per visitatore, ma senza object cache persistente un transient scaduto resta in `wp_options`
  finché lo stesso IP non lo rilegge o non passa `delete_expired_transients` (una volta al giorno, con WP-Cron; in
  locale mai), e una raffica da molti IP lasciava migliaia di righe. Fra le due strade proposte dalla review (option
  sola con potatura e tetto, o transient con una pulizia nel rinnovo) l'option sola: la misura massima è certa (2000
  visitatori, 144 KB serializzati, misurati il 16/11) e non dipende da WP-Cron. Il prezzo: ogni ricerca con un testo riscrive
  l'option intera, e due ricerche nello stesso istante possono contare una volta sola, cosa accettabile per un freno.
- **Pausa della ricerca solo per i guasti dello shard** (16/11): la review del primo giro chiedeva la pausa dopo un
  errore di rete, un 5xx o un 429, e la pausa valeva per ogni errore, anche 401, 403 e una risposta non valida, che non
  si risolvono da soli in mezzo minuto e scriverebbero una riga di log per tasto; restano nella pausa. Dal secondo giro
  i 4xx della validazione (400, 422) rispondono «nessun risultato» senza pausa né log: con la pausa un visitatore
  fermava la ricerca per tutti (vedi «Bug trovati»). Dal terzo giro il 404 e gli altri 4xx sono di nuovo guasti, con
  pausa e messaggio nel tab: un 404 non dipende dal testo, vuol dire che allo shard manca la rotta (oggi su UAT).
- **Lucchetto del rinnovo con una sola istruzione SQL** (secondo giro, 16/11): la review proponeva `add_option()`, ma
  add_option() prima legge e poi scrive con `INSERT … ON DUPLICATE KEY UPDATE`, e due processi che leggono la riga
  assente nello stesso momento la scrivono tutti e due con successo. L'INSERT di `forestas_entes_lock()` ha `IF(…)`
  nell'`ON DUPLICATE KEY UPDATE`: 1 riga toccata se la crea, 2 se riprende un lucchetto abbandonato, 0 se è di un altro.
- **`doing_cron` solo se recente, rinnovo lasciato a WP-Cron per 2 minuti al massimo** (secondo giro, 16/11): la review
  chiedeva di saltare il rinnovo della pagina quando `doing_cron` c'è. Il transient non scade da solo, e dove il
  loopback di WP-Cron non risponde (in locale, il caso per cui le pagine rinnovano da sé) nessuno lo cancella: ogni
  visita fa ripartire WP-Cron e lo riscrive, e la pagina non avrebbe rinnovato mai. Per questo conta solo se ha meno di
  `WP_CRON_LOCK_TIMEOUT` secondi, solo con l'evento del rinnovo da eseguire e con una copia da mostrare, e per 120
  secondi dal primo rinvio (`left_to_cron`).
- **Visitatore dall'IP, con X-Forwarded-For solo dietro un proxy privato**: su dev e UAT REMOTE_ADDR è il gateway di
  Docker, e l'IP vero lo aggiunge in coda l'Apache dell'host; l'ultimo indirizzo pubblico da destra non lo può scrivere
  il visitatore (16/11).
- **Copia del JSON ogni 15 minuti con WP-Cron** invece di un'ora (il massimo della pagina di conoscenza di
  oc:8965), con l'ultima copia buona se lo shard non risponde; lo stesso ripiego nei dettagli di tracce e POI
  del plugin, su richiesta del dev.
- **Contenuti di default nel cestino** con `wp_trash_post()`, non con `wpf_wpml_cancella_post`, che cancella
  per sempre (`wp_delete_post( $id, true )`).
- **Controllo di plausibilità con un calo accettato dopo 3 ore** (review interna, 16/11): prima non scadeva, e un calo
  vero degli enti sotto la metà si sbloccava solo cancellando a mano l'option `forestas_entes_copy` (ruling, 16/11).
  Una lista vuota non si accetta mai.
- **Filtro «Argomento» in questo ticket, sui cinque tipi**: regola «Drupal 1:1» per i filtri delle liste; le voci sono
  i tipi come nella faccetta di Drupal, mentre la card mostra la categoria come la teaser (ruling, 16/11).
- **Etichette inglesi tradotte**, compreso il titolo dell'elenco, anche dove Drupal lascia l'italiano (ruling, 16/11).
- **In inglese tutti i sentieri gestiti**: Drupal mostra solo quelli tradotti, ma sul sito ogni percorso ha la pagina
  inglese (scelta del plugin e di oc:8969), e il sito resta coerente con sé stesso (16/11).
- **Stima**: Misurato 2,0h + Stimato 8,2h = Totale 10,2h, approvata dal dev e scritta su Orchestrator.
- **Review finale, rotta**: `forestas_entes_requested()` legge la richiesta dalla `matched_rule` e il filtro `request`
  `forestas_entes_request_vars()` imposta le variabili. Le query var sono pubbliche, perché le regole funzionino; per
  questo il filtro le toglie dalle richieste che non vengono dalle regole degli enti, altrimenti `/?forestas_entes[]=x`
  farebbe mostrare a WordPress la home del blog al posto della pagina iniziale. Sulle regole degli enti tiene solo le
  query var della regola (review interna: anche senza `p`, `page_id`, `s` della query string).
- **Review finale, contenuti di default**: `wpf_default_content_apply()` riceve la mappa dei contenuti e l'id della
  privacy, con i valori predefiniti `WPF_DEFAULT_CONTENT` e `WPF_DEFAULT_PRIVACY_ID`. Test:
  `docker/scripts/config/tests/default-content.php`, che crea post temporanei in bozza; si lancia con
  `docker exec wordpress-forestas wp --allow-root eval-file /usr/local/lib/wp-forestas/tests/default-content.php`
  (`./docker/scripts/config` è montato in `/usr/local/lib/wp-forestas` da `compose.yml`).
- **Review finale, tipi e link**: i tipi sconosciuti si scrivono nel log in `forestas_entes_refresh()`, una volta per
  copia nuova: dopo la review interna solo quando i dati cambiano, non a ogni rinnovo. Un sito web o un `related_url`
  con un protocollo non ammesso da `esc_url()` si scarta. `style.css` è alla Version 1.16.16 dopo la review interna.
- **`.forestas-griglia-vuota` in `style.css`** (review interna, 16/11): la classe la usano anche le griglie delle news e
  dei «da sapere» (`inc/griglie.php`, `taxonomy-tipologia_da_sapere.php`), ma lo stile stava solo in `entes.css`.
  Ora il messaggio «Nessun contenuto.» di quelle griglie è centrato e con il colore e la dimensione del testo, come
  quello degli enti.

## Follow-up

### Miglioramenti da chiedere ad Alessandro (non bloccano la chiusura)

Il dev li manda in un solo messaggio; il sito funziona già senza (17/11). Il dettaglio di ciascuno è nelle voci qui
sotto.

1. **Date giuste per ciò che nasce o cambia in Nova**: oggi `created_at` dell'ente è `drupal_created_at`,
   `updated_at` è `properties.forestas.updated_at` e `created_at` delle tracce è `properties.forestas.created_at`;
   dopo lo spegnimento di Drupal un ente o una traccia creati in Nova arriverebbero senza data, e una modifica in Nova
   non cambierebbe `updated_at`. Intanto il sito mette in fondo un elemento senza data e non la mostra.
2. **Più tipi per ente**: su Drupal un ente può averne più d'uno (Comune 30 nella faccetta), lo shard e l'API ne
   tengono uno (Comune 27, anche sul dev).
3. **`sardegnasentieri_id` nelle tracce dell'Elastic del dev**: c'è nel dettaglio della traccia ma non nell'indice, e
   sul WordPress del dev gli indirizzi stabili dei percorsi (oc:8983) funzionano senza nid.
4. **Limiter con nome per le due rotte dello shard** (`api/v1/entes` e `api/v1/entes/search`): oggi condividono la
   chiave `dominio|ip`, e le ricerche consumano il budget del rinnovo; il sito ha già il suo limite per visitatore.
5. **Totale della ricerca oltre i 50 risultati**: `api/v1/entes/search` si ferma a 50 e non dà il totale.
6. **Chiave stabile nelle tracce degli enti e negli enti** (nid di Drupal o un'altra): oggi i sentieri gestiti si
   confrontano per nome con l'indice del plugin e gli enti si riconoscono per slug.

### Altre voci

- **Ordine dell'elenco `/enti`**: su Drupal la view `enti` non ha un ordinamento; si usa `created_at` crescente. Se il
  cliente nota differenze, si concorda un ordine (per esempio alfabetico).
- **Totale della ricerca degli enti al massimo 50** (API di oc:8965): `api/v1/entes/search` non dà il totale e si ferma
  a 50 risultati (`EnteSearchQuery::SIZE`). Oggi gli enti sono 93; da chiedere ad Alessandro se il tab deve contarli
  tutti.
- **Un tipo solo per ente** (da chiedere ad Alessandro): su Drupal un ente può avere più tipi, e la faccetta conta
  Comune 30 ed Ente partner 19; lo shard e l'API ne tengono uno solo, e anche sul dev il filtro conta Comune 27
  (verificato il 16/11 con i dati del dev). Lo shard sarà la fonte definitiva: se un ente ha davvero più tipi, lo shard
  deve poterli tenere.
- **`sardegnasentieri_id` assente dalle tracce nell'Elastic del dev** (da chiedere ad Alessandro): il dettaglio della
  traccia sul dev ce l'ha (`properties.forestas.sardegnasentieri_id`), ma l'indice che il plugin costruisce dall'Elastic
  del dev ha la chiave della fonte vuota per tutte le tracce (`"track":0` nel log). Sul WordPress del dev gli indirizzi
  stabili dei percorsi (oc:8983) funzionano quindi senza nid.
- **UAT senza l'API degli enti** (16/11): `forestas.uat.maphub.it/api/v1/entes` risponde 404, perché oc:8965 e la card
  dei sentieri gestiti (`2fb929f`) non sono ancora su `main` di forestas. Le pagine degli enti su UAT funzionano solo
  dopo il merge `develop` → `main` (Giuseppe) e la messa in opera di `docs/howto/messa-in-opera-degli-enti.md`.
- **Prefisso o limiter distinto per le due rotte dello shard** (da chiedere ad Alessandro, API di oc:8965): `throttle:60,1`
  sul JSON e `throttle:600,1` sulla ricerca usano la stessa chiave `domain|ip`, e il sito è un IP solo: le ricerche
  consumano il margine del rinnovo. Con un limiter con nome per ciascuna rotta le ricerche non farebbero più fallire il
  rinnovo con un 429; intanto il sito ha il limite per visitatore e la pausa.
- **Ipotesi del secondo giro della review interna** (16/11, non fatte):
  - token mandato di nuovo dopo un redirect verso http: `wp_remote_get()` segue i redirect, e `wmwp_http_json()` del
    plugin non permette di passare `redirection => 0`. `forestas_entes_token_allowed()` controlla solo l'indirizzo di
    partenza; serve un parametro nel plugin, che è condiviso fra shard;
  - limite per visitatore approssimato: visitatori dietro lo stesso NAT lo condividono, in IPv6 si aggira cambiando
    indirizzo nella stessa /64, la finestra fissa lascia passare fino a 60 ricerche a cavallo di due finestre, e anche
    le ricerche che durante la pausa rispondono 503 contano nel limite;
  - un WAF davanti allo shard che rispondesse 403 a certi testi farebbe partire la pausa, perché il 403 si legge come
    token mancante: oggi davanti agli shard non ce n'è uno noto.
- **Lasciati fuori dalla review interna del 16/11** (opzionali, con il motivo):
  - markup della galleria ripetuto in `ente-detail.php`, `wp-package/track.php` e `poi.php`, con il «Mostra tutto»
    scritto a mano: un componente comune tocca i template di oc:8969;
  - nomi inglesi in `docker/scripts/config/default-content.php` fra nomi di script italiani: la regola globale vuole
    gli identificatori nuovi in inglese, e rinominare gli script vecchi è un lavoro a sé;
  - shard finto ripetuto nei test (`tests/entes-*.php`): un helper comune dei test è un lavoro a sé, che cambierebbe
    tutti i file;
  - lettura di `wmwp_detail_index()['items']['track'][$id]['names']`, non documentata: serve un accessor documentato nel
    plugin, che è condiviso fra shard;
  - identità degli enti per nome e slug invece che per una chiave stabile: serve il nid di Drupal in `tracks[]` e negli
    enti dell'API (richiesta ad Alessandro);
  - SEO delle pagine degli enti (sitemap, canonical, meta, Open Graph): va fatta per il lancio, con quella delle altre
    pagine dinamiche;
  - `lang="it"` nelle card della ricerca: serve un campo nella card del plugin.

## Info per altri ticket

- **Verifica in locale con i dati del dev** (16/11): token del dev nel `.env` di `wp-forestas` (copiato dal server senza
  mostrarlo), mu-plugin `wpf-shard-locale.php` rinominato in `.disattivato`, copia degli enti e cache dell'indice
  cancellate, `scripts/wordpress-up.sh`. Risultato: 93 enti, sentieri gestiti 4 (Oasi Tepilora), 13 (Stintino), 138
  (Agenzia Forestas), link agli enti da G 203A e D 701, ricerca «tepilora» con 2 risultati, descrizioni con l'HTML (84
  su 86). Per tornare allo shard locale: rinominare il mu-plugin e cancellare di nuovo la cache dell'indice.

- Su Drupal il carosello «Sentieri gestiti» mostra tutti i sentieri pubblicati di cui l'ente è gestore (138
  per Agenzia Forestas, 60 in inglese, e 9 per il Comune di Stintino il 16/11), non i 5 più recenti: la configurazione
  locale di Drupal (`views.view.sentieri_gestiti.yml`, `items_per_page: 5`) non è quella della produzione.
- Su Drupal la voce di menu «Contatti» porta a `/enti`; in WordPress la stessa voce punta già a `/enti/`
  (`config/menu.json`).
- **WPML e il filtro `icl_ls_languages`**: chi lo aggancia non deve leggere le lingue di WPML (per esempio con
  `wpml_active_languages` o un `wpml_permalink` che le legge) per la prima volta dentro il filtro con un guard acceso,
  perché la lettura annidata resta nella cache della richiesta e il selettore dell'header la riusa (vedi «Bug
  trovati»).
- **API Laravel chiamate da WordPress**: con `Accept: application/json` un errore di validazione arriva come 422; senza,
  come un redirect.
- **IP del visitatore nel container WordPress**: REMOTE_ADDR è il gateway della rete di Docker (`172.27.0.1` in locale,
  privato anche su dev e UAT, dove l'Apache dell'host fa `ProxyPass` verso `127.0.0.1:8090`), e l'IP vero arriva in coda
  a X-Forwarded-For. Il container non ha `mod_remoteip`: chi ha bisogno dell'IP lo prende come
  `forestas_entes_visitor_ip()`.
- **Connessioni verso il dev che vanno in timeout dopo una raffica** (16/11, in locale): con 31 ricerche di fila dal sito
  verso `forestas.dev.maphub.it`, una richiesta Apache ciascuna, dopo 3-14 risposte la connessione è andata in timeout
  (cURL 28, «Connection timed out after 8000 milliseconds») per tre volte su tre. 15 ricerche di fila dalla stessa
  richiesta PHP e 15 richieste curl senza token dal container non hanno avuto il problema. Causa non trovata; la pausa
  della ricerca ha fermato le chiamate con una riga di log per pausa.
- **Lucchetti in `wp_options`** (16/11): `add_option()` non è atomico (legge, poi `INSERT … ON DUPLICATE KEY UPDATE`), e
  nemmeno un transient letto e scritto. Un lucchetto vero è un INSERT con `ON DUPLICATE KEY UPDATE option_value = IF(…)`
  e il conto delle righe toccate, come `forestas_entes_lock()` in `inc/entes.php`.
- **`doing_cron` del core non scade** (16/11): `spawn_cron()` lo scrive senza scadenza e lo cancella solo `wp-cron.php`
  alla fine. Dove il loopback non risponde (in locale) resta con l'istante dell'ultima partenza: chi lo usa per sapere
  se WP-Cron sta girando deve guardare che abbia meno di `WP_CRON_LOCK_TIMEOUT` secondi.
- **Testi invisibili verso un'API Laravel** (16/11): `sanitize_text_field()` e `trim()` lasciano U+200B, U+00A0,
  U+FEFF, che TrimStrings di Laravel toglie, e un campo `required` risponde 422. Il taglio come Laravel è
  `forestas_entes_search_text()` in `inc/search.php`.
- **Teaser degli enti su Drupal**: l'etichetta della card è il padre del tipo, perché in Twig `parent.entity` di un
  termine radice ricade sul termine stesso.
