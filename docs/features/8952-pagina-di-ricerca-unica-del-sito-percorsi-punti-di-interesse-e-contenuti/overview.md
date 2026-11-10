> Ticket: oc:8952

# Pagina di ricerca unica del sito: percorsi, punti di interesse e contenuti

## Cosa cambia

Il nuovo sito ha una pagina di ricerca unica, `/cerca-nel-sito`, come oggi su Drupal. Chi cerca per esempio
«perda liana» vede il tab «Tutti» con i risultati raggruppati per tipo (Percorsi, poi Contenuti del sito:
news, eventi, «da sapere», pagine) e un tab per ogni tipo con il numero dei risultati. Il tab «Percorsi» ha
i filtri (faccette) a sinistra, calcolati sui percorsi che restituisce l'Elasticsearch dello shard: compaiono
da soli man mano che i percorsi ne portano il dato, e un selettore «Elenco | Mappa» che mostra gli stessi risultati
su una mappa. Ogni ricerca ha un indirizzo stabile, che si può condividere, indicizzare e stampare su un codice QR.

Il codice nasce in un plugin nuovo, `wp-package`, fork di wp-geohub e unico per tutti gli shard, e nel
child theme `forestas-child`. Il plugin attuale `wm-package` (wp-geohub) esce da wp-forestas.

Il codice è in tre repo:

| Repo | Cosa |
|---|---|
| `wp-package` (nuovo, `webmappsrl/wp-package`) | struttura della pagina di ricerca, source dello shard, mappa (componente `FeatureCollectionMap` di wm-package compilato), settings page, traduzioni |
| `wp-forestas` (submodule di forestas) | child theme: source «Contenuti del sito», template e CSS come Drupal, parametri di Forestas, pagina `/cerca-nel-sito`; installazione: `init-wordpress.sh` scarica e attiva `wp-package` al posto di wp-geohub |
| `forestas` | `local.compose.yml` (mount di sviluppo del plugin, solo in locale) e documentazione |

## Perché

- Il resoconto della riunione del 12/08 (§ 4.1) chiede «una sola pagina di ricerca, con faccette, valida per
  tutti i tipi di contenuto», con un indirizzo «canonico, stabile e interrogabile, così da poter essere
  indicizzato e stampato su codice QR»; il capitolato (WEBSITE-13) chiede ricerca avanzata con filtri
  combinabili.
- Drupal va dismesso a fine anno (scrum del 29/10): la ricerca va fatta.
- Allo scrum del 09/11 Rubens parte dalla ricerca su Elasticsearch, mentre Giuseppe fa il dettaglio della
  traccia; Giuseppe: «Elastic Search a regola già c'ha tutto. Se ti manca qualcosa mi scrivi».
- Il 27/10 e il 04/11 si è deciso un plugin nuovo, fork di wp-geohub, unico per tutti gli shard: «Il plugin
  fornisce i dati e crea le pagine», il tema fa HTML e CSS (Giuseppe, 04/11).

Le decisioni prese nel dialogo, con le fonti, sono in [notes.md](notes.md), sezione «Decisioni».

## Requisiti

### Plugin `wp-package`

- [x] Il repo nasce da wp-geohub, ramo `osm2cai` (`8255db5`), ridotto alla base, in
  `/Volumes/WebmappDev/Sites/Webmapp/Wordpress plugin/wp-package`, con una storia nuova su un branch orfano: della
  storia di wp-geohub non restava nessun file identico (decisione del 10/11, notes.md, «Storia del repository»). Il
  repo `webmappsrl/wp-package` l'ha creato Giuseppe il 10/11; `origin` è aggiunto, il push si fa con la chiusura.
- [x] Contiene solo la base elencata in [notes.md](notes.md), «Base di `wp-package`: cosa entra»; il resto
  di wp-geohub non entra (tabella «Cosa resta fuori»).
- [x] Ha un prefisso suo per funzioni, classi, opzioni e handle (es. `wmwp_`), il text domain
  `wp-package`, e nessun percorso con il nome della cartella scritto nel codice: attivo insieme a
  `wm-package` non dà errori (verificato il 10/11 sul codice: nessuna funzione in comune con wp-geohub, tutte con
  `wmwp_`, costanti `WMWP_*`; wp-geohub in locale non è più installato).
- [x] La settings page permette di scegliere lo shard da wm-types e l'app id, e calcola gli URL delle API;
  con `forestasdev` e app 1 l'URL di Elasticsearch è `https://forestas.dev.maphub.it/api/v2/elasticsearch`.
- [x] Le traduzioni si caricano: `load_plugin_textdomain()` con percorso relativo alla cartella dei plugin,
  `.pot` del codice nuovo e `wp-package-it_IT.po`/`.mo`. I testi di base sono in inglese. I testi del JS
  passano da `wp_set_script_translations()` e `wp.i18n`, con i plurali («1 percorso», «28 percorsi»).
- [x] Il PHP stampa solo lo scheletro della pagina (campo, tab, colonna dei filtri, un `<noscript>` con il
  campo verso `?s=`) e la card dentro un `<template>`; il JS disegna risultati e filtri clonando quel
  `<template>`, così il markup sta in un solo posto. Nessun primo disegno PHP dei risultati: raddoppierebbe il
  download della risposta.
- [x] Uno shortcode di ricerca disegna:
  - il campo di ricerca;
  - il tab «Tutti», con un gruppo per source: titolo vero (heading) con il numero dei risultati, le prime card
    e «Vedi tutti i N …», che apre il tab della source; un gruppo senza risultati non compare;
  - un tab per source, con il numero dei risultati letto insieme al nome (`role="tablist"`,
    `aria-selected`);
  - la stessa card per tutte le source: immagine, tipo con il suo colore, titolo, link.
- [x] Registro delle source: un filter hook PHP (es. `wp_package_search_sources`) e una funzione JS (es.
  `wmWpSearch.registerSource()`) con cui il tema aggiunge source; ogni source restituisce i dati della card
  nello stesso formato.
- [x] Source «Percorsi», da `api/v2/elasticsearch`, partendo dalla logica generica di `grid_track` e
  `grid-track-filters.js`:
  - il browser chiama direttamente l'API, come su sentieroitalia (decisione da rivalutare, vedi
    [notes.md](notes.md), «Peso della risposta di Elasticsearch»);
  - testo cercato (`query`), ripulito prima dell'invio dai caratteri speciali della sintassi di Lucene (`/`,
    `(`, `:`…), che oggi fanno rispondere l'API con un errore 500: il controller passa il testo a una
    `QueryStringQuery`;
  - all'API si chiede solo il testo: filtri e conteggi li calcola il plugin in JS dai percorsi della
    risposta (`taxonomyWheres`, `taxonomyActivities`, `terms`, `layers`), quindi un clic su un filtro non
    riscarica niente (decisione da rivalutare quando l'API darà i conteggi giusti, vedi [notes.md](notes.md));
  - filtri guidati dai dati: ognuno compare solo quando i percorsi ne portano il campo; in WordPress restano
    nomi e ordine; la percorribilità, primo filtro della tavola 2, non compare finché il dato non c'è:
    è un dato nuovo, da sviluppare con un ticket a parte (review-gate, 09/11);
  - tutti i valori di un filtro, non solo i 10 più frequenti; i valori a zero nascosti e «Mostra altro» dopo i
    primi 5, come Drupal (`min_count: 1`, `soft_limit: 5`); i valori attivi sempre visibili e deselezionabili,
    anche a zero risultati;
  - OR dentro lo stesso filtro, con i conteggi di quel filtro calcolati senza il suo stesso filtro, AND fra
    filtri diversi;
  - i filtri valgono nel tab in cui si scelgono: cambiando tab (clic, frecce, «Vedi tutti») si tolgono, così «Vedi
    tutti: 37 percorsi» apre 37 percorsi; «Indietro» ritrova la ricerca filtrata. Il numero sul tab resta quello del
    testo cercato, cioè quello che il tab mostra appena aperto. *Aggiunto il 10/11: decisione di Rubens («ok best
    practice») sul punto 13a della review di wp-package;*
  - i valori dei filtri sono `<input type="checkbox">` in un `<fieldset>` con `<legend>`, non link `<a href>`:
    nessun crawler apre da solo le combinazioni; l'aspetto resta quello dei link di Drupal;
  - le risposte dell'API restano in una cache in memoria per testo (`Map`): tornare indietro, cambiare tab o
    riscrivere lo stesso testo non riscarica;
  - numero dei percorsi e km complessivi (i km solo se tutti i percorsi della ricerca hanno la lunghezza); per
    riga codice, nome, comuni, lunghezza, dislivello, tempo e stato di validazione quando l'API li dà (lo stato da
    `terms.validation-status`, nell'API del dev dal 09/11, come badge colorato con il nome scritto, `wmwp-badge--<key>`) (formattazione da `single_track.php`, ma con `Intl.NumberFormat` nella
    lingua della pagina al posto dei separatori italiani fissi);
  - 12 risultati per pagina, come su Drupal: l'API li restituisce tutti in una volta, fino a 10.000.
- [x] Lo stato della ricerca (testo, tab, filtri, pagina, vista) sta nell'indirizzo. Quello che si scrive di
  seguito è un passo solo della cronologia: la prima pausa (400 ms) lo crea con `pushState`, le pause successive e
  l'Invio lo aggiornano con `replaceState`; dopo l'Invio, o dopo un cambio di filtro, tab, pagina o vista (che fanno
  `pushState`), il testo successivo crea un passo nuovo; un ascoltatore `popstate`
  ripristina la ricerca quando si torna indietro. L'indirizzo si compone con `URLSearchParams`. *Riscritto il 10/11
  (review di wp-package): il requisito approvato diceva `pushState` solo con Invio, ma chi cerca senza premere Invio
  con «Indietro» usciva dalla pagina; vedi notes, «Divergenze».*
- [x] Filtri attivi sopra i risultati, sotto il totale: un chip (`wmwp-chip`) per ogni valore scelto («Mountain Bike ✕»), che
  con un clic lo toglie, e «Togli tutti i filtri»; si vedono anche con la colonna dei filtri (`wmwp-search__filters`) chiusa, dove il pulsante
  dice quanti sono («Filtri (2)»). Dopo un clic su una checkbox o su un chip il focus resta al suo posto.
- [x] Una nuova richiesta annulla quella precedente (`AbortController`): una risposta lenta non sostituisce
  quella più recente.
- [x] Una source che non risponde mostra l'errore nel suo gruppo e nel suo tab, senza un falso 0; le altre
  source funzionano.
- [x] I valori dei filtri nell'indirizzo sono gli identifier dei termini dello shard, non i nomi mostrati (per
  comuni e province il nome, finché l'API non dà un identifier); più valori separati da virgola. Questa regola
  sta nel plugin e il tema non la cambia.
- [x] I nomi dei parametri sono valori predefiniti neutri (es. `q` per il testo), cambiabili dal tema con un
  filter hook (es. `wp_package_search_params`).
- [x] Il link della card dei percorsi passa da un filter hook: senza una destinazione la card non ha link
  (niente `/track/<slug>/` di wp-geohub, che darebbe 404). La destinazione la decide il lavoro sul dettaglio
  della traccia.
- [x] I template sono sostituibili dal tema copiandoli in una cartella `wp-package/` del tema (schema di
  WooCommerce), compreso quello della card che il JS clona.
- [x] Il plugin parte dalla versione 0.x (Semantic Versioning), con `CHANGELOG.md` e una costante della
  versione (es. `WMWP_VERSION`); il README ha una sezione «Interfaccia pubblica» con quello su cui un tema può
  contare (filter hook, `wmWpSearch.registerSource()`, file dei template, classi CSS della card) e dice che
  fino alla 1.0 può cambiare; tutto il resto è interno.

### Child theme `forestas-child` (wp-forestas)

- [x] Source «Contenuti del sito», registrata nel registro del plugin: una rotta REST del child (es.
  `forestas/v1/search`) sui tipi `post`, `evento`, `da_sapere`, `page`, con la ricerca del core (`WP_Query`
  con `s`). Con una sola richiesta restituisce le card della pagina pronte (titolo, link nella lingua corrente,
  tipo, URL dell'immagine nella misura della card con il testo alternativo, estratto) e i conteggi per tipo
  del filtro «tipo»; 12 risultati per pagina. Le pagine sono quelle con un testo: venute da Drupal (`_drupal_nid`) o
  scritte in WordPress dalla redazione (senza `_wpf_pagina`); restano fuori le pagine di struttura che lo script
  `pagine` crea senza un nodo Drupal (schede ed elenchi, la stessa pagina di ricerca) e la home. *Aggiunto il 10/11
  (review di wp-forestas): prima usciva solo la pagina di ricerca, e «Contenuti del sito» mostrava elenchi senza
  testo.*
- [x] Parametri di Forestas: `search_api_fulltext` per il testo, come su Drupal, e nomi italiani per tab, pagina,
  vista e filtri (`tipo`, `pagina`, `vista=mappa`, `attivita`, `difficolta`, `carta`, `comune`…). Il plugin non
  accetta nomi che sono query var di WordPress: con il suo predefinito di allora, `page`, il redirect canonico
  toglieva il parametro e `?page=2` tornava alla pagina 1; ora il predefinito è `pg` (review di wp-package del
  10/11; Forestas usa `pagina` e non ne era toccato).
- [x] Filtro «Validazione catastale» per fasce, primo filtro finché la percorribilità non c'è (dopo di lei quando
  arriverà), da `terms.validation-status`: Avanzato 5–6
  («Validato»), Intermedio 2–4 («In revisione-validazione», «In pre-accatastamento o manutenzione», «Percorribile»),
  Base 0–1 («Non verificato» e «Non percorribile», supposto livello 1); nell'indirizzo `validazione=avanzato`…
- [x] Filtro «Tipo di percorso» prima di «Attività», da `terms.route-type` (Sentiero, Itinerario, Cammino;
  nell'indirizzo `percorso=route-type-walk`…).
- [x] Tipo e stato di validazione di ogni percorso (riga dei risultati e riquadro della mappa) nella lingua della
  pagina: l'API dà i nomi dei termini solo in italiano, e il child passa al plugin i nomi in inglese con
  `term_labels` («Trail», «Validated»…). I nomi inglesi sono provvisori, scritti da noi: vanno confermati con
  Forestas (vedi notes). *Aggiunto il 10/11, review di wp-forestas.*
- [x] «Dove» diviso in tre filtri riconoscendo i luoghi dal nome, ripiego finché l'API non li dà per tipo: «Area»
  (le 7 macro aree «Area …» di Geohub), «Provincia» (8 nomi, comprese le province storiche; Cagliari, Nuoro,
  Oristano e Sassari solo qui), «Comune» (il resto, senza «Sardegna» e senza i codici dei settori CAI come «ZNUB4»);
  i comuni anche all'inizio di ogni riga, come nella tavola 2.
- [x] Con un testo cercato, prima i percorsi che lo hanno nel nome, poi quelli con tutte le parole, poi gli altri
  (punto aperto 4: ordine per pertinenza come su Drupal; l'API ordina per nome).
- [x] Il child parla al plugin solo con i filter hook, con `shortcode_exists()` e, in JS, controllando che
  `window.wmWpSearch` esista; il suo JS si accoda solo nel ramo «plugin presente» di `[forestas_search]`.
  Nessuna chiamata diretta a funzioni del plugin: senza plugin il sito non va in errore.
- [x] I link di Drupal alla ricerca filtrata per tipo (`f[N]=cerca_contenuto_nel_sito:<tipo>`) portano con un
  302 al tab corrispondente: `sentiero` e `itinerario` a «Percorsi» con il filtro «Tipo di percorso»
  (`percorso=route-type-trail`, `percorso=route-type-itinerary`); `news`, `evento` e `page` a «Contenuti del
  sito» con il tipo; `poi` a «Tutti» finché i punti di interesse non sono nell'API. Più tipi dello stesso tab si
  sommano nello stesso filtro (`percorso=route-type-trail,route-type-itinerary`); con tipi di tab diversi vince il
  primo. *Il filtro sul tipo di percorso è aggiunto il 10/11 (review di wp-forestas): prima un link a «solo
  itinerari» apriva tutti i percorsi.*
- [x] Aspetto dalla tavola 2 del [wireframe](https://claude.ai/artifact/4T3ZRSP1LCrbCuJdNuEGXQ) («Pagina di
  ricerca unica», sezione `s1`, «Desktop 1440»; decisione di Rubens del 09/11: «intanto parti da questo poi
  vediamo»): colonna dei filtri di 238px a sinistra con titoli, caselle e numeri a destra; nel tab «Percorsi»
  campo «Cerca un percorso», riga del totale («28 percorsi · 179,5 km
  complessivi», «percorsi» e non «sentieri» come nella tavola: i risultati sono anche itinerari e cammini) e risultati in righe
  (codice, nome, comuni · km · dislivello · tempo, stato di validazione); colori e misure dai token del wireframe
  (`--sar-blu` `#254877`, colori dei tipi `#DE1B0D` sentiero, `#58810B` itinerario, `#1A416F` cammino, `#0F6E6A`
  da sapere, `#C2410C` news). La tavola mostra solo il desktop: sotto 1024px i filtri si aprono a schermo
  intero con un pulsante, come su Drupal (`sidebar-mobile`).
- [x] Selezione e scarico di più percorsi nel tab «Percorsi» (entrata nel review-gate, 09/11: «se si può fare e
  funziona mettilo ora»), con misure e colori della tavola 2 (`.chk`, `.selall`, `.selbar2`): checkbox su ogni riga (`wmwp-row__select`),
  «Seleziona tutti i N percorsi della ricerca» (tutta la ricerca, non la pagina; solo con un testo o un filtro, non
  sull'intero catalogo), barra in fondo con riepilogo
  («3 percorsi selezionati · km · dislivello», km e dislivello solo se tutti i percorsi scelti li hanno),
  «Deseleziona tutto», formato GPX, KML o GeoJSON e «Scarica N percorsi in GPX». Le tracce vengono da
  `api/ec/track/multiple` a gruppi di 10 e si convertono nel browser, perché GPX e KML dell'API rispondono 500;
  i percorsi senza tracciato restano fuori e la barra lo dice. Disposizione della barra (Rubens, 10/11: «mi sembra
  tutto distribuito in maniera disordinata»): su desktop una riga, a sinistra il conteggio con «Deseleziona tutto»,
  a destra formato e pulsante di scarico; su telefono tre righe (conteggio e «Deseleziona tutto», i tre formati in
  colonne uguali, il pulsante su tutta la larghezza, alti 44px); il «torna su» di Impreza sale sopra la barra.
- [x] Mappa dei risultati nel tab «Percorsi» (entrata il 10/11 dopo lo scrum: «va fatta ora come discusso»), con il
  selettore «Elenco | Mappa» della tavola 2 (`.seg`, `.res-map`): la mappa prende il posto di elenco e pagine e
  mostra tutti i percorsi della ricerca con i filtri scelti, inquadrata sul riquadro che li contiene; su desktop è
  alta quanto la finestra meno l'header, da 420 a 760px (circa il fondo della colonna dei filtri), sotto 1025px 420px
  (Rubens, 10/11: «la mappa può prendere più spazio in altezza? vedo che c'è molto spazio in basso»); «Seleziona
  tutti» e la barra della selezione restano; la vista sta nell'indirizzo (`vista=mappa`). Geometrie da
  `api/ec/track/multiple` con gli id dei risultati, non da Elasticsearch (scrum del 10/11). La mappa è il componente
  `FeatureCollectionMap` di wm-package (Vue 3 e OpenLayers, quello di Nova), usato com'è e compilato in `wp-package`
  in un file che la pagina carica solo alla prima apertura della mappa. È un componente del plugin riutilizzabile in
  tutto il sito (Rubens, 10/11: «sarà usato nei dettagli di track, poi layer etc»): `window.wmWpMap.mount()` e
  shortcode `[wmwp_map]` con tracce per id o un GeoJSON. Linee da 4px nel colore del layer (sotto) con il codice, +/− in alto a
  destra e sotto il pulsante dello schermo intero (Rubens, 10/11: «è possibile aggiungere il controllo a tutto
  schermo della mappa nei risultati?»), testi in italiano e inglese. Con percorsi selezionati, quelli stanno in primo
  piano (nel loro colore con un contorno bianco) e la mappa si inquadra su di loro; gli altri risultati restano sotto,
  tratteggiati in blu ardesia come i «sentieri collegati» della tavola del dettaglio (`.lg-near`) (Rubens, 10/11: «si
  fallo così», poi «devi essere sicuro e seguire una strada»). Carta di base: quella dell'app, come la webapp (per
  Forestas la carta Webmapp), letta dal `config.json` dell'app; senza risposta resta OpenStreetMap (Rubens, 10/11).
  Ogni percorso nel colore del suo layer nell'app (Sentieri rosso, Itinerari verde, Cammini blu), come la webapp; al clic
  su un percorso un popup (`popup` di `wmWpMap.mount()`, `.wmwp-map-popup`) con la sua card (immagine, tipo, nome, comuni e
  misure), senza aprire il dettaglio della traccia (Rubens,
  10/11: «mostra la preview dei dati e il rimando alla scheda completa tienilo in note»), e il pulsante «Aggiungi alla
  selezione» / «Togli dalla selezione» per scegliere i percorsi da scaricare dalla mappa, senza che la vista si muova.
- [x] Pagina `/cerca-nel-sito` come voce di `config/pagine.json`, in italiano e in inglese.
- [x] Il template della pagina usa uno shortcode del child (es. `[forestas_search]`): con `wp-package`
  attivo (`shortcode_exists()`) disegna la ricerca del plugin, senza plugin un campo di ricerca semplice verso
  `?s=`, come oggi. La pagina non mostra mai uno shortcode come testo.
- [x] I testi visibili sono in italiano e inglese (WPML `it` e `en-us`; stringhe del child con
  `forestas_testo()`).
- [x] Il campo «Cerca nel sito» dell'header, italiano e inglese, porta a `/cerca-nel-sito` come su Drupal: il campo
  manda a `?s=` di WordPress, e con il plugin attivo il child risponde con un 302 verso la pagina di ricerca
  (`/?s=oliena` → `/cerca-nel-sito/?search_api_fulltext=oliena`); `config/post.json` non cambia l'header.

### Installazione (wp-forestas)

- [ ] Il passo 6 di `init-wordpress.sh` scarica `wp-package` dal repo GitHub pubblico a un commit fisso (codice
  pronto; si prova con il primo hash pubblicato, Task 12 passo 3)
  (es. `WP_PACKAGE_REF`, come oggi `GEOHUB_REF`) in `wp-content/plugins/wp-package`, dentro il volume, e
  lo attiva con `attiva_plugin`. Scrive il commit scaricato in un file della cartella e riscarica quando il
  commit dichiarato cambia: l'aggiornamento arriva con `scripts/wordpress-up.sh` anche su un sito che non si
  azzera. Se GitHub non risponde, il sito parte senza plugin con un AVVISO e riprova al riavvio successivo.
- [ ] Il passo è operativo quando il repo GitHub ha il codice: `webmappsrl/wp-package` esiste dal 10/11 (pubblico,
  creato da Giuseppe), resta il push e l'hash in `WP_PACKAGE_REF` (Task 12, passi 2 e 3).
- [x] In locale il clone di sviluppo si monta in sola lettura fuori da `/var/www/html`, percorso dal `.env`
  (`WP_PACKAGE_DIR`, segnaposto in `.env-example`), con un file compose a parte (`compose.local.yml`) incluso
  solo da `local.compose.yml` di forestas: il compose dei server non cambia. L'init collega la cartella del plugin
  al clone con un link simbolico, non scarica e il `chown` non ci entra. Il compose di forestas non smette di
  funzionare se la variabile manca.
- [x] Shard e app id vengono dal `.env` (segnaposto in `.env-example`): l'init li scrive nelle opzioni del
  plugin solo se sono vuote, come per URL e amministratore.
- [x] wp-geohub esce: `GEOHUB_*` e il suo passo 6, `docker/scripts/config/ritratto.php:30` (riporta
  `wp-package`), trappola nel `CLAUDE.md`, righe del `README.md`, di
  `docs/knowledge/inizializzazione-wordpress.md` e di `docs/howto/ricreare-wordpress-da-zero.md`. In locale
  `wm-package` si disattiva e si cancella; su UAT e dev si disattiva e si cancella a mano quando arriva
  `wp-package`, con il passo scritto nelle howto di messa in opera di forestas (l'init non disinstalla).
- [x] Le howto di messa in opera dicono come tornare indietro: `pagine` non cancella le pagine tolte da
  `config/pagine.json`, quindi con un child più vecchio `/cerca-nel-sito` mostrerebbe lo shortcode come testo;
  si cancella la pagina nelle due lingue, poi `applica --conferma` per il link dell'header.

### Accessibilità [UX]

- [x] [UX] Il numero dei risultati, dopo ogni cambio di filtro, si legge con un solo `role="status"` e una frase
  intera («28 percorsi · 179,5 km complessivi»), non con numeri sparsi.
- [x] [UX] Durante il caricamento la zona dei risultati ha `aria-busy` e non sfarfalla.
- [x] [UX] Senza risultati compare un messaggio con cosa provare (controllare il testo, togliere un filtro) e
  il comando per togliere tutti i filtri.
- [x] [UX] Focus sempre visibile e ordine di tabulazione uguale all'ordine visivo, anche quando i filtri
  sono a schermo intero (sotto 1024px; su Drupal è la `sidebar-mobile`).
- [x] La pagina rispetta WCAG 2.1 livello AA, il riferimento delle linee guida AgID per la pubblica amministrazione
  (tramite la norma EN 301 549 v3.2.1), in tutti gli stati: «Tutti», tab con filtri, filtri a schermo intero, vista
  mappa con il riquadro, barra della selezione, nessun risultato, errore dell'API, italiano e inglese. Compresi la
  mappa da tastiera (frecce e +/−), la tooltip della mappa che si chiude con Esc e resta sotto il puntatore, il
  focus mai sotto la barra della selezione e il testo che resta leggibile durante il caricamento. *Aggiunto il 10/11
  dopo l'audit chiesto da Rubens («accessibilità e screen reader devono essere in linea con le linee guida
  ufficiali»); esito e limiti in notes, «Audit di accessibilità del 10/11».*

### Fine del lavoro

- [ ] La pagina è verificata sul WordPress di dev (`wp.forestas.dev.maphub.it`) con lo shard dev: confronto
  con la tavola 2 del wireframe, tastiera e screen reader (VoiceOver o NVDA veri: l'audit del 10/11 ha usato l'albero
  di accessibilità di Chromium), italiano e inglese. *10/11: messa in opera fatta e prove automatiche su dev passate
  (Playwright: ricerca, filtri, mappa da tastiera, scarico, barra, redirect, inglese; notes, «Messa in opera su dev
  del 10/11»). Restano da fare a mano il confronto con la tavola 2 e la prova con uno screen reader vero.* Il repo GitHub c'è dal 10/11; si fa
  dopo il push e il merge (Task 12, passo 4), come chiesto da Giuseppe il 10/11 («buttala in dev»).
- [x] In locale, con il plugin disattivato, `/cerca-nel-sito` mostra il campo di ricerca semplice e il log non
  ha errori PHP.
- [x] Il ciclo da zero in locale (reset, installazione, pagine da Drupal, menu, verifiche di
  `docs/howto/ricreare-wordpress-da-zero.md`) porta un sito con la ricerca funzionante senza interventi a mano:
  `wp-package` collegato e configurato dall'init, `/cerca-nel-sito` creata in italiano e in inglese. *Fatto il
  10/11, su richiesta di Rubens; esito in notes, «Ciclo da zero del 10/11».*

## Rischi

- **L'API oggi copre poco della pagina.** Filtra solo testo, attività, luoghi, layer e intervalli; mancano i
  conteggi di difficoltà, validazione, comuni distinti, aree, percorribilità, carte; POI ed enti non sono
  nell'indice; l'ordine è sempre per nome. Mitigazione: filtri guidati dai dati dei percorsi, lacune tracciate in
  [notes.md](notes.md) («Filtri in attesa dell'API») e comunicate alla sessione di oc:8916.
- **La risposta dell'API pesa 2,5 MB.** Il browser la riscarica a ogni ricerca: 769 percorsi, non compressi,
  1,4 MB dei quali sono il campo `searchable`, che il client non usa. Mitigazione: chiesto alla sessione di
  oc:8916 di togliere `searchable` dalla risposta e di comprimerla (109 KB, calcolato); se non arriva, la
  ricerca passa da una rotta REST del plugin con cache (alternativa in [notes.md](notes.md)).
- **Un picco di traffico sulla ricerca carica lo shard.** La rotta è pubblica, senza cache e senza throttle
  (`routes/api.php:168`). Mitigazione: una richiesta solo quando cambia il testo e cache in memoria nel
  browser; cache lato server, `Cache-Control: public, max-age` e throttle chiesti per il ticket delle API.
- **Il plugin conta i filtri sui percorsi ricevuti.** Funziona finché l'API li restituisce tutti
  (`take(10000)`); con una paginazione i conteggi tornerebbero all'API. Mitigazione: vincolo comunicato alla
  sessione di oc:8916; i conteggi giusti dell'API (`post_filter`, `size`) sono chiesti per il ticket delle API.
- **Il repo GitHub è vuoto fino al push.** `webmappsrl/wp-package` esiste dal 10/11 (pubblico, creato da
  Giuseppe), ma finché non c'è il codice e l'hash in `WP_PACKAGE_REF` l'init non scarica il plugin, che non arriva su
  dev. Mitigazione: il push e la PR verso `develop` sono il primo passo della chiusura; nel frattempo il sito resta
  usabile con il campo di ricerca semplice del child.
- **La mappa scarica le tracce di tutti i risultati.** `api/ec/track/multiple` ne dà 10 per richiesta: tutti i 769
  percorsi sono 77 richieste, 11,5 MB, circa 7 secondi (dev, 10/11). Mitigazione: tracce in memoria per id (un
  filtro dopo non riscarica), disegno man mano che arrivano; chiesti alla sessione di oc:8916 un limite più alto e
  una risposta con la sola geometria.
- **La mappa dipende dalla struttura interna del componente.** Carta di base, schermo intero e popup al clic si
  aggiungono da fuori alla `Map` di OpenLayers del componente (un solo layer di tile, `preserveViewOnReload`).
  Mitigazione: il componente è fissato al commit del submodule `map/wm-package`; a ogni aggiornamento del submodule
  si riprova la mappa nel browser (`map/README.md`).
- **Conflitti con wp-geohub.** Il fork parte dallo stesso codice. Mitigazione: prefisso nuovo, e wp-geohub
  esce da wp-forestas.

## Out of scope

- La mappa nelle pagine di dettaglio di track, POI e layer: il componente e lo shortcode `[wmwp_map]` ci sono, le
  pagine le fanno i loro ticket.
- Della selezione multipla della tavola 2: le azioni «Mappa e schede» («Stampa la mappa di insieme», «Scarica le
  schede PDF»), che non hanno nulla dietro; la «Guida all'uso del download», pagina redazionale che non esiste; la
  preparazione asincrona dei file oltre una soglia (tutti i 769 percorsi si scaricano in circa 7 secondi).
- Lo scarico GPX e KML di un solo percorso dalle rotte dello shard (`api/ec/track/download/{id}.gpx` e `.kml`):
  deciso il 10/11, si fa dopo oc:8953, che corregge quelle rotte (oggi rispondono 500). Fino ad allora anche un
  solo percorso si converte nel browser (notes.md, «Da fare dopo questo ticket»).
- Le source «Punti di interesse» ed «Enti»: si aggiungono quando l'API li indicizza.
- La destinazione del link delle card dei percorsi (il lavoro sul dettaglio della traccia).
- I reindirizzamenti del lancio: `/sentieri/…`, `/sentiero/…` dei QR già distribuiti, `/carte/<nome>` dei
  pannelli, le pagine `/sentieri`, `/itinerari`, `/da-vedere` (vedi «Info per altri ticket» in notes.md).
- Il blocco «Cerca un percorso» della home (la home si fa per ultima, scrum del 09/11).
- Le modifiche all'API dello shard (ticket delle API, in preparazione nella sessione di oc:8916).
- Sincronizzazione, tipi di post `track`/`poi` e shortcode `single_track`, `single_poi`, `grid_poi` di
  wp-geohub nel plugin nuovo.
- La grafica di base della ricerca nel plugin, con le variabili CSS (Giuseppe, 10/11): prossimo ticket (Rubens,
  10/11); oggi l'aspetto sta nel child (notes.md, «Da fare dopo questo ticket»).
- Il link al dettaglio della traccia nel popup della mappa: arriva con il dettaglio (`link_template`).
- La percorribilità come filtro: dato nuovo, ticket a parte (notes.md, «Da fare dopo questo ticket»).

## Moduli toccati

**`wp-package`** (repo nuovo): `index.php`, settings page (da `admin_page.php`), funzioni di
configurazione e lingue (da `functions/functions.php`), `config/wm_default_config.json`,
`hooks/transient.php`, shortcode di ricerca, JS e template della ricerca (dalla logica generica di
`grid_track.php` e `grid-track-filters.js`), funzioni di formattazione, mappa (`map/` con la build, `assets/dist/`,
`includes/map.php`, `assets/js/track-api.js`, `track-map.js`, `map-embed.js`), `languages/`, README.

**`wp-forestas`**: `themes/forestas-child/` (source «Contenuti del sito», template e CSS della ricerca,
parametri, reindirizzamento della ricerca dell'header), `config/pagine.json`, `config/post.json` (header IT
e EN, se serve), `compose.local.yml`, `docker/wp-package-empty/`, `.env-example`, `docker/scripts/init-wordpress.sh`,
`docker/scripts/config/ritratto.php`, `CLAUDE.md`, `README.md`, `docs/knowledge/inizializzazione-wordpress.md`,
`docs/howto/ricreare-wordpress-da-zero.md`.

**`forestas`**: `local.compose.yml`, `docs/knowledge/wordpress-nello-shard.md`, `docs/howto/messa-in-opera-wordpress-uat.md`,
`docs/howto/messa-in-opera-wordpress-dev.md` (togliere `wm-package` dai siti esistenti).
