> Ticket: oc:8990

# Enti sul sito WordPress: elenco /enti, dettaglio /enti/<slug> e ricerca, dall'API di oc:8965

## Cosa cambia

Il sito WordPress mostra gli enti che oggi stanno solo su Drupal, leggendoli dall'API degli enti dello
shard (`GET api/v1/entes` e `GET api/v1/entes/search`, oc:8965), chiamata dal server del sito con il
token. Gli enti sono un dato solo di Forestas: tutto il codice degli enti sta nel child theme
`forestas-child`, e il plugin `wp-package` non li conosce (oc:8990 tocca il plugin solo per i dettagli di
tracce e POI, vedi la sua `overview.md`).

- **Elenco `/enti`** (e `/en/enti`): le card degli enti attivi con immagine, nome e tipo, 9 per pagina con
  la paginazione e il filtro «Argomento» per tipo, come la view `enti` e le sue faccette su Drupal. Nessuna
  ricerca a testo libero propria: su Drupal l'elenco non ne ha, si cerca dalla ricerca del sito. Ci si arriva dalla voce «Contatti» del menu, che punta già a `/enti/`
  (`config/menu.json`), come su Drupal.
- **Dettaglio `/enti/<slug>`** (e `/en/enti/<slug>`): i contenuti del template Drupal
  `node--ente-istituzione-societa--full` nel suo ordine: nome, immagine principale con autore e credits,
  descrizione («Informazioni»), tipo, contatti, pagina web, collegamenti, «Sentieri gestiti», galleria con
  lightbox, date di aggiornamento e di creazione. Niente mappa né POI, come su Drupal. Uno slug che non
  è nel JSON risponde 404.
- **«Sentieri gestiti»**: le tracce dell'ente con ruolo `gestore` (le «Track gestiti» della Resource Nova
  `Ente`), presentate come i «Percorsi correlati» del dettaglio traccia: la stessa card, la griglia a tre
  colonne (`forestas_dettaglio_griglia()`) e il pulsante «Mostra tutti (N)» oltre le prime tre. Su Drupal
  è un carosello, ma il child non ne ha nessuno e lo stile segue le pagine già presenti. Ordinate dalla più
  recente per data di creazione su Drupal. La sezione non c'è se l'ente non gestisce sentieri.
- **Tab «Enti» nella ricerca unica** (`/cerca-nel-sito`): una source aggiunta dal child con il filtro
  `wp_package_search_sources`, che interroga `api/v1/entes/search` attraverso una rotta REST del child.
- **Link agli enti dai dettagli di percorsi e POI**: nel gruppo «Enti» di sentieri, itinerari e cammini e
  negli enti del POI, il nome di ogni ente, per tutti i ruoli come su Drupal, porta al suo dettaglio.
- **Contenuti di default di WordPress tolti**: il post «Ciao mondo!» (id 1, oggi esce nella ricerca come
  news), la «Pagina di esempio» (id 2) e la bozza «Privacy Policy» (id 3), con un passo di
  `bin/wordpress-config.sh`.
- **Stile**: quello delle pagine e dei post già presenti nel child (testata, `forestas-card`,
  tipografia); da Drupal si prendono contenuti, ordine e comportamento.

## Perché

Drupal verrà spento e gli enti hanno oggi sul sito pubblico una loro pagina di elenco e di dettaglio, che
il nuovo sito deve avere con gli stessi dati (decisioni del tag 692: enti come pagine dinamiche, 27/10;
stessi dati del dettaglio di Drupal, 08/11). La ricerca di Drupal trova gli enti, quindi anche la ricerca
unica deve trovarli (oc:8965). Il wireframe di oc:8783 (tav. 3) vuole che dal dettaglio di un percorso
il nome del soggetto rilevatore porti alla pagina dell'ente, «come nel sito attuale»; su Drupal il link
c'è per tutti i ruoli, nei sentieri e nei POI. L'API non è pubblica perché contiene nomi e cognomi di
persone (Giuseppe, scrum 11/11): la chiama solo il server del sito, con il suo token.

## Requisiti

### Dati e configurazione

- [x] Il token è `WP_API_TOKEN` nel `.env` di `wp-forestas`, lo stesso nome dello shard, con un
  segnaposto in `.env-example`; il child lo legge dall'ambiente del container e lo manda come
  `Authorization: Bearer <token>`. Il token non arriva mai nel browser né in un file tracciato, e parte solo in https
  o verso un host locale (`localhost`, `127.0.0.1`, `::1`, `host.docker.internal`, un servizio Docker): in http verso
  un dominio la richiesta non parte e il log lo dice.
- [x] Il JSON degli enti si legge con `wmwp_http_json()` dall'origine dello shard
  (`wmwp_configured_api_urls()`, con `wmwp_server_url()` per le chiamate dal server), con l'header del
  token.
- [x] Il JSON si rinnova ogni 15 minuti con un evento WP-Cron, fuori dalle richieste dei visitatori; le
  pagine degli enti leggono sempre la copia salvata. Solo se non esiste nessuna copia (sito appena
  installato), o se da più di un'ora non è partito nessun rinnovo (WP-Cron fermo, o nessuna visita), la pagina la
  richiede subito, con un tentativo per richiesta. Gira un rinnovo alla volta: WP-Cron e le pagine passano da un
  lucchetto atomico, e una visita che lo trova preso legge la copia che c'è. Con la copia, se WP-Cron è appena
  partito (di solito dalla stessa visita) la pagina gli lascia il rinnovo, per 2 minuti al massimo.
- [x] La copia tiene solo le chiavi che il child legge e si riscrive solo quando i dati cambiano; l'ora del rinnovo e
  gli slug degli enti stanno in due option piccole. Dettagli di percorsi e POI e ricerca con un testo leggono solo gli
  slug; la ricerca a testo vuoto elenca gli enti della copia. Il rinnovo riallinea gli slug anche quando la copia non
  cambia.
- [x] Se il rinnovo fallisce (shard spento, 401/403/429, risposta non valida) resta l'ultima copia buona e il
  problema finisce nel log con il codice ricevuto; dopo un fallimento senza copia non si riprova prima di 5
  minuti, così un token mancante non fa partire una chiamata per ogni visita. Le pagine degli enti
  rispondono 503 solo se una copia buona non c'è mai stata.
- [x] Una copia nuova non sostituisce quella buona se il numero di enti, o quello dei collegamenti
  ente-traccia, scende sotto la metà della copia precedente: succede su UAT durante l'import dopo il reset
  notturno, quando `enteables` è vuota o parziale. Il caso finisce nel log. Un calo che dura più di 3 ore dal primo
  rifiuto si accetta, con una riga di log; una lista vuota mai.
- [x] Sulle pagine inglesi nome, descrizione e contatti vengono dalla chiave `en`. Quando coincide con
  quella `it` è il testo italiano copiato dall'API, come oggi per tutti gli enti importati da Drupal, e si
  marca `lang="it"`; una traduzione inglese vera, scritta in Nova dopo il lancio, si mostra come inglese.
  Le etichette del child sono tradotte con `forestas_testo()`.
- [x] Il sito usa `created_at` e `updated_at` così come li dà l'API, per l'ente e per le tracce: oggi
  sono le date di Drupal, e dopo lo spegnimento di Drupal la fonte sarà lo shard. Che restino giuste per
  un ente o una traccia creati o modificati in Nova è una richiesta ad Alessandro (lista delle richieste di
  oc:8990); il sito non dipende da un dato che esiste solo per i contenuti importati da Drupal.
- [x] Il nome del tipo di ente è tradotto in italiano e in inglese per i cinque valori di `type`
  (`complesso-forestale`, `ente-partner`, `altre-pubbliche-istituzioni`, `privato-associazione`,
  `comune`); un valore fuori dai cinque non si mostra e finisce nel log.

### Elenco `/enti`

- [x] Rewrite del child per `/enti/` e `/en/enti/`, con la paginazione `/enti/page/N/` come le altre
  griglie; si alza `FORESTAS_REGOLE_VERSIONE` perché le regole si rigenerino.
- [x] Card con immagine, nome e tipo, 9 per pagina, nell'ordine di creazione su Drupal (`created_at`
  crescente, gli enti senza data in fondo): la view `enti` di Drupal non ha un ordinamento, e quello di
  creazione è il più vicino a quello che si vede.
- [x] Filtro «Argomento» come la faccetta di Drupal (`field_tipo_ente`, senza gerarchia): i cinque tipi con il
  numero di enti, parametro `?argomento=<type>`, la paginazione che tiene il filtro. La card invece mostra il
  tipo padre, o il tipo stesso se non ha padre, come la teaser di Drupal.
- [x] Una pagina oltre l'ultima risponde 404.

### Dettaglio `/enti/<slug>`

- [x] Rewrite del child per `/enti/<slug>/` e `/en/enti/<slug>/`, senza conflitto con la paginazione
  dell'elenco; uno slug che non è nel JSON risponde 404.
- [x] I contenuti nell'ordine del template Drupal, con descrizione e contatti come HTML filtrato
  (`description` e `contacts` vuoti escono come `[]` e non mostrano la sezione).
- [x] Galleria con la lightbox del child (`forestas_galleria_lightbox()`), come nel dettaglio traccia: le
  prime tre foto e «Mostra tutti (N)», anche con le 14 di GAL BMG.
- [x] «Sentieri gestiti»: le tracce con ruolo `gestore`, con la card di
  `forestas_dettaglio_card_percorso()` (foto, tipo, nome con codice, tempo e lunghezza quando ci sono:
  campi della card che il JSON ha dal commit `2fb929f` di forestas) e il link di `wmwp_detail_url()`,
  dalla più recente per `created_at`. Compare solo una traccia che ha una pagina di dettaglio e il cui
  nome nel JSON, quello della pagina o quello italiano, è fra i nomi dell'indice del plugin per lo stesso id
  (`'names'` dell'indice): dopo il reset di UAT un id può passare a un altro sentiero prima che l'indice, ricostruito
  ogni ora, se ne accorga, e la card porterebbe al sentiero sbagliato; il nome italiano basta perché una traduzione
  scritta dopo l'ultima ricostruzione dell'indice non tolga la card.
- [x] Date di aggiornamento e di creazione nel formato data del sito (`updated_at` è
  `Y-m-d H:i:s` senza fuso).
- [x] Titolo della pagina, `<html lang>` e lingue di WPML corretti per la pagina dinamica, come per i
  dettagli di tracce e POI.

### Ricerca unica e link

- [x] Source «Enti» nella ricerca unica, con le card degli enti (immagine, nome, tipo) e il link al
  dettaglio; la rotta REST del child passa ogni ricerca a `api/v1/entes/search` con il token, senza cache
  per testo cercato: con la ricerca mentre si scrive quasi ogni testo è nuovo, e la cache lascerebbe solo
  righe in `wp_options`.
- [x] Freni verso lo shard, che conta un limite al minuto per IP e vede il sito come un IP solo: al massimo 30 ricerche
  con un testo al minuto per visitatore, oltre 429 con `Retry-After`, con i contatori in un'option sola che tiene solo
  le finestre aperte e al massimo 2000 visitatori; quando la ricerca dello shard non è disponibile (rete, 5xx, 429,
  token, rotta mancante con 404, risposta non valida), 30 secondi di 503 senza chiamare lo shard e una riga di log per
  pausa. Un 4xx della validazione (400, 422) dà «nessun risultato», senza pausa: un visitatore non può fermare la
  ricerca per tutti. L'IP del visitatore viene da X-Forwarded-For solo dietro il proxy dell'host (REMOTE_ADDR privato).
- [x] Un testo fatto solo di spazi o di caratteri invisibili (U+200B, U+00A0, U+FEFF…) è un testo vuoto, come per lo
  shard dopo il suo TrimStrings: il tab elenca gli enti della copia senza chiamare lo shard.
- [x] Con il testo vuoto (apertura di `/cerca-nel-sito`) il tab «Enti» elenca gli enti dalla copia, come
  gli altri tab elencano i loro contenuti, senza chiamare lo shard (l'API risponde 422 a un testo vuoto).
- [x] Se la ricerca degli enti non risponde (indice mancante, 503) il tab «Enti» mostra un messaggio e
  gli altri tab funzionano.
- [x] Nel gruppo «Enti» del dettaglio dei percorsi (`wp-package/track.php`) e negli enti del POI
  (`wp-package/poi.php`), il nome di ogni ente porta a `/enti/<slug>` (o `/en/enti/<slug>`) se lo slug c'è
  ed è fra gli enti della copia; altrimenti resta testo. I dettagli di percorsi e POI leggono solo gli slug della
  copia già salvata: non chiamano mai lo shard per gli enti e non vanno mai in 503 per colpa loro.

### Contenuti di default

- [x] `bin/wordpress-config.sh applica` sposta nel cestino (`wp_trash_post()`) il post 1, la pagina 2 e la
  bozza 3, se esistono ancora con lo slug di default e non sono stati modificati dopo l'installazione; azzera
  `wp_page_for_privacy_policy` se punta alla bozza 3 di default (una pagina 3 modificata può essere la privacy
  vera). Il cestino e non la cancellazione
  definitiva: `wpf_wpml_cancella_post` cancella per sempre (`wp_delete_post( $id, true )`), mentre dal
  cestino un contenuto si recupera per 30 giorni, e la riga di traduzione di WPML resta finché il post
  esiste. Sui siti nuovi lo fa l'apply automatico dei primi 3 giorni; in locale, su dev e su UAT un
  `applica --conferma` a mano (howto `docs/howto/messa-in-opera-degli-enti.md`). Un secondo giro non fa nulla.

### [UX] Accessibilità e usabilità

- [x] [UX] Card con un solo link e un nome accessibile (il nome dell'ente o del sentiero), immagini con
  `alt` vuoto quando sono decorative e con dimensioni riservate, così la pagina non salta mentre carica.
- [x] [UX] Paginazione con `nav` e `aria-label`, pagina corrente con `aria-current`, controlli usabili da
  tastiera con il focus visibile.
- [x] [UX] Galleria: la lightbox tiene il focus dentro e lo riporta alla foto da cui si è aperta, si
  chiude con Esc, i pulsanti hanno un'etichetta in italiano e in inglese (già così nel dettaglio
  traccia).
- [x] [UX] Sezioni vuote (nessun sentiero gestito, nessuna foto, nessun contatto) non compaiono, come su
  Drupal; l'elenco senza enti dice «Non ci sono enti da mostrare.», il tab di ricerca senza risultati mostra
  «Enti (0)» e il messaggio del plugin «Nessun risultato per “…”» (un testo proprio richiederebbe `wp-package`).

### Verifica

- [x] Test `wp eval-file` del child per lettura con token, cache, ultima copia buona, controllo di
  plausibilità, attesa dopo un fallimento, 404, «Sentieri gestiti» (ruolo, ordine, nome che non torna con
  l'indice), link dai dettagli di percorsi e POI, passo dei contenuti di default. I test che scrivono
  opzioni o post rimettono il database com'era. Fatti tutti, compreso quello del passo dei contenuti di default
  (`docker/scripts/config/tests/default-content.php`), e dopo la review interna anche copia snella, calo accettato
  dopo 3 ore, rinnovo con WP-Cron fermo, token solo in https, limite per visitatore, pausa della ricerca, IP da
  X-Forwarded-For e query var del core sulle pagine degli enti; dopo il secondo giro lucchetto del rinnovo (anche
  abbandonato), rinnovo lasciato a WP-Cron appena partito, slug riallineati con gli stessi dati, 4xx del testo senza
  pausa, testo di soli caratteri invisibili e contatori del limite con il tetto.
- [ ] Confronto con Drupal su schermo largo e sotto 960px, in italiano e in inglese, per Oasi Tepilora (4
  sentieri gestiti), Comune di Stintino (molti sentieri gestiti), Agenzia Forestas (138 card su Drupal, 137
  tracce gestite nello shard locale) e GAL BMG (14 foto, nessun sentiero gestito), per l'elenco `/enti` con la
  paginazione e per il tab «Enti» della ricerca.
- [x] Verifica in locale con i dati del dev (16/11): token del dev nel `.env` di `wp-forestas`, mu-plugin non versionato
  `wpf-shard-locale.php` (oc:8969) disattivato, cache dell'indice dei dettagli ricostruita dal dev. Elenco, filtro,
  dettagli, sentieri gestiti, link da percorsi e POI e ricerca con il token funzionano (dettaglio in `notes.md`, «Info
  per altri ticket»). In locale WP-Cron non parte (`wp cron test` fallisce sulla porta 8090): il rinnovo si lancia a
  mano con `wp cron event run`.
- [ ] Verifica sul dev dopo il merge su `develop` e la messa in opera (`docs/howto/messa-in-opera-degli-enti.md`).
- [ ] Verifica su UAT dopo il merge `develop` → `main` di forestas e di `wp-forestas` e la messa in opera: oggi su UAT
  l'API degli enti non c'è (404). Il lavoro è chiuso solo quando funziona su dev e su UAT.

## Rischi

- **Reset notturno di UAT alle 06:00**: per ore `enteables` è vuota o parziale e gli id delle tracce
  passano ad altri sentieri. Mitigazione: il controllo di plausibilità tiene la copia di prima; il
  confronto del nome con l'indice toglie le card che porterebbero al sentiero sbagliato. In produzione,
  dopo lo spegnimento di Drupal, il reset non c'è.
- **Token mancante, sbagliato o non ancora nel container** (403 se lo shard non ha il token, 401 se è
  sbagliato): il token entra nel container solo quando lo si ricrea, quindi dopo il `.env` serve
  `scripts/wordpress-up.sh` di `forestas`. Mitigazione: il log dice il codice ricevuto, l'attesa di 5
  minuti evita una chiamata per ogni visita, e la procedura di messa in opera elenca il token e
  `wordpress-up.sh`.
- **Limite al minuto dello shard condiviso da ricerca e rinnovo** (la stessa chiave `domain|ip`, e il sito è un IP
  solo): molte ricerche farebbero fallire il rinnovo con un 429. Mitigazione: limite per visitatore e pausa dopo un
  errore; il rinnovo fallito lascia la copia. Richiesta ad Alessandro: un limiter distinto per le due rotte.
- **Indice Elastic degli enti assente** (in locale va creato a mano): la ricerca degli enti risponde 503.
  Mitigazione: il tab «Enti» mostra un messaggio senza rompere gli altri tab.
- **Date dell'API ancora prese da Drupal**: finché l'API non le prende dallo shard, un ente o una
  traccia creati in Nova arrivano senza `created_at`. Mitigazione: richiesta ad Alessandro; intanto un
  elemento senza data va in fondo all'elenco e ai «Sentieri gestiti», e la data non si mostra.
- **Uno slug di ente che cambia** (ente rinominato su Drupal): il vecchio indirizzo risponde 404. È lo
  stesso comportamento di Drupal senza alias; vedi Out of scope.
- **Cancellare un contenuto di default che qualcuno ha riusato**: il passo tocca solo contenuti non
  modificati dopo l'installazione, e li mette nel cestino, da cui si recuperano per 30 giorni.

## Out of scope

- Calcolo di tempo e lunghezza dei sentieri gestiti: si mostrano quando lo shard li ha, il calcolo in
  blocco dal DEM viene dopo il go-live (oc:8932).
- Blocco «Il cammino, in sintesi» con il gestore del cammino: la posizione è da decidere (`decisioni.md`
  di oc:8783, tav. 5).
- Redirect dagli indirizzi Drupal degli enti (`/contatti/…`, `/enteistituzionesocieta/…`): con gli altri
  redirect del lancio (tag 692).
- Indirizzi stabili degli enti (storico degli slug e 301 dopo una rinomina, come oc:8983 per tracce e
  POI).

## Moduli toccati

Tutti in `wp-forestas`, salvo dove indicato.

- `themes/forestas-child/inc/entes.php` (nuovo): lettura del JSON con token, cache, evento WP-Cron,
  controllo di plausibilità, rewrite e query var, risoluzione dello slug, tipi di ente, filtro «Argomento», card.
- `themes/forestas-child/templates/entes-list.php` e `templates/ente-detail.php` (nuovi), con lo stile di pagine
  e post.
- `themes/forestas-child/inc/search.php` e `assets/js/search.js` (o uno script nuovo): source «Enti» e
  rotta REST verso `api/v1/entes/search`, con il limite per visitatore e la pausa dopo un errore; la rotta dei
  contenuti passa a WPML il codice `en-us`.
- `themes/forestas-child/inc/dettaglio.php`, `wp-package/track.php` e `wp-package/poi.php`: link agli
  enti; in `inc/dettaglio.php` anche `forestas_date_iso()`.
- `themes/forestas-child/inc/contenuti.php`: `forestas_trim_link_text()`, il taglio del testo dei link, comune ai
  collegamenti delle news e alla pagina web degli enti.
- `themes/forestas-child/style.css`: barretta dei titoli delle sezioni degli enti nel gruppo della barretta,
  `.forestas-griglia-vuota`, Version.
- `themes/forestas-child/inc/news.php`: `FORESTAS_REGOLE_VERSIONE`.
- `themes/forestas-child/functions.php`: caricamento di `inc/entes.php`.
- `themes/forestas-child/inc/griglie.php`: colonna dei filtri estratta in `forestas_filtri_colonna()`, usata
  anche dal filtro «Argomento».
- `themes/forestas-child/assets/css/entes.css` (nuovo); `assets/js/dettaglio.js` parte anche dal dettaglio
  dell'ente.
- `themes/forestas-child/tests/` (nuovi test `wp eval-file`).
- `docker/scripts/config/default-content.php` (nuovo) e `apply.php`: passo che toglie i contenuti di default.
- `.env-example`: `WP_API_TOKEN`.
- Documentazione: questa cartella, una pagina di conoscenza sugli enti nel sito,
  `docs/howto/ricreare-wordpress-da-zero.md` (token, `wordpress-up.sh` e passo dei contenuti di default),
  `docs/howto/messa-in-opera-degli-enti.md` (messa in opera su dev e UAT) e `docs/knowledge/inizializzazione-wordpress.md`
  (passo dei contenuti di default).
- Plugin `wp-package` (repo a sé): ripiego sulla copia nei dettagli di tracce e POI, con la sua
  `overview.md`; poi il nuovo hash in `WP_PACKAGE_REF` di `docker/scripts/init-wordpress.sh`.
