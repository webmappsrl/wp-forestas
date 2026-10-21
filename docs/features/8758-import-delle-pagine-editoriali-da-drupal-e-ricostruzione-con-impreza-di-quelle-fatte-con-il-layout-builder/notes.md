> Ticket: oc:8758

# Notes — Import delle pagine statiche da Drupal

## Divergenze dal piano, task per task

### Task 1: slug inglesi e WPML

Verificato in locale (pagine di prova poi cancellate): WPML ammette per la traduzione inglese lo
stesso slug dell'italiana, ma solo se lo slug si scrive **dopo** aver assegnato la lingua con
`wpf_wpml_collega`. Per questo `wpf_pagina_scrivi` crea la pagina, la collega e poi riscrive
`post_name`; se WordPress lo cambia lo stesso, l'elemento finisce fra i mancanti. Gli slug inglesi
restano quelli di Drupal (`sentieri`, `itinerari`, `da-vedere`, `da-sapere`, `legal-notes`, `credits`;
Cammini `cammini`). `config/pagine.json` non lo produce l'export: `bin/wordpress-config.sh esporta` lo
salta invece di avvisare che manca.

### Task 4: chiave delle pagine e link delle traduzioni

- La pagina si trova con `_wpf_pagina` **e** `_wpf_lingua` (meta in più rispetto al piano): così non
  si dipende dai filtri per lingua di WPML, che da WP-CLI mostrano solo la lingua predefinita.
- La funzione che risolve i link restituisce direttamente il link e conosce i percorsi delle pagine
  del perimetro non ancora create: i link interni sono giusti anche alla prima esecuzione e in
  anteprima.
- **WPML riscrive i link delle traduzioni.** `ItemUpdateEvent` di WPML (closure su `save_post`,
  `post_updated` e `shutdown`) a fine esecuzione trasforma i link interni di una traduzione in
  indirizzi assoluti del sito (`/app/` → `http://localhost:8090/app/`): link diversi in ogni ambiente
  e Crediti EN sempre «aggiornata». `wpf_wpml_senza_link_automatici()` (in `wpml.php`) stacca quegli
  hook durante la sincronizzazione; se a un aggiornamento di WPML non li trova più, il comando lo
  segnala con un AVVISO.
- Le costanti dei meta allegati e galleria si chiamano `WPF_META_PAGINA_ALLEGATI` e
  `WPF_META_PAGINA_GALLERIA`: `WPF_META_ALLEGATI` esiste già in `comune.php`.

### Task 5: media non scaricabili

Un file che non si scarica non si ritenta nella stessa esecuzione (italiano e inglese della stessa
pagina usano gli stessi file, e con il sito Drupal lento ogni tentativo costa 60 secondi). Le
anteprime dei PDF sono spente durante l'import: richiederebbero Ghostscript, che l'immagine non ha.

### Task 5: allegati scaricati solo in parte

Prima versione: se anche un solo file di un elenco (allegati, galleria) non si scaricava, l'elenco
restava quello di prima, e su un sito appena ricreato quindi vuoto: Note legali non mostrava nessun
allegato perché uno dei due PDF non era stato servito da Drupal. Su richiesta del dev (20/10) allegati
e galleria mostrano i file scaricati e il mancante si aggiunge quando arriva; per l'immagine in
evidenza, che è una sola, resta quella che c'era.

### Task 8: grafica dei template

- **Testata dall'artifact, non dal tema Drupal** (decisione del dev, 20/10): l'artifact «Canvas Fase
  2» segue gli aggiornamenti chiesti dal cliente. Tavola 8 (sezioni redazionali): immagine desaturata
  e scurita (`grayscale(.35) brightness(.55)`), barretta, titolo e sottotitolo centrati, poi il
  percorso «Home › pagina». **Misure dal tema Drupal** (richiesta del dev dopo averla vista, 20/10:
  l'immagine era più bassa che su Drupal): altezza 66% della finestra con minimo 286px, al massimo 50%
  su tablet, fra 500 e 600px su desktop; titolo 30–40px su telefono e 40–55px da tablet; barretta
  57×7px; margini e larghezza del contenuto di `_hero.scss`. La prima versione riportava in scala le
  misure dell'artifact (testata 268px). Il testo resta centrato anche in verticale, come
  nell'artifact. La barretta ha il colore dell'entità della voce di menu (`--sase-sezione-*`, oc:8783);
  le pagine senza entità hanno il terra `#CA5C30` dell'artifact.
- **Impaginazione della testata come il sito, sfumatura come le tavole del catasto** (richiesta del dev,
  20/10): testo in basso e centrato come la testata hero di Drupal, contenuto largo fino a 1294px, margini
  di 30–40px; al posto dell'immagine desaturata e scurita della tavola 8, la fascia `.sh-scrim` delle
  tavole del catasto dell'artifact: immagine a colori pieni e, dal basso, `rgba(26,26,26,.6)` dove c'è
  testo, che sfuma nei 64px in alto. axe senza violazioni anche su Note legali, la foto più chiara.
  Child alla Version 1.4.6.
- **«Credits foto» nella testata, come su Drupal** (richiesta del dev, 20/10). Su Drupal i media
  immagine hanno due campi distinti, «Autore» (`field_autore`) e «Credits» (`field_credits`), testo
  libero fino a 255 caratteri, compilati in due caselle separate; la testata li mostra dietro un
  pulsante «Credits foto» (`media--image--testata-pagina.html.twig`). In WordPress: due campi
  personalizzati dell'immagine, `wpf_autore` e `wpf_credits`, nella scheda della libreria media
  (`attachment_fields_to_edit`), importati e allineati a ogni sincronizzazione. Scartata la
  Didascalia nativa: un campo solo al posto di due, e WordPress la mostra da sé sotto le immagini nel
  testo, nelle gallerie e nella lightbox, dove su Drupal i credits non compaiono. Pulsante e testo con
  gli stili di `sase-image-credits` (13px, pulsante scuro a credits nascosti, bianco aperti), chiusi
  all'apertura; testo con il markup di `field--media--field-autore` e `field--media--field-credits`:
  etichetta in grassetto «Autore foto» / «Credits immagine» (EN «Photo author» / «Image credits») prima
  di ogni valore, e il «•» di Drupal attaccato al secondo campo. Su Drupal 1898 immagini su 3823 hanno
  l'autore: gli import di news, da sapere ed eventi devono usare gli stessi due campi.
- **Spazi e caratteri della testata identici a Drupal** (richiesta del dev, 20/10). Confrontati col CSS
  compilato del tema Drupal locale, su una pagina di prova con il markup della testata del Layout
  Builder, a 375, 500, 800, 1100 e 1440px: posizione di filo, titolo, sottotitolo e pulsante dei credits
  dal fondo uguali al pixel. Valori come su Drupal, in rem perché sotto 960px la radice è 14px (anche in
  Impreza): margini della sezione e della regione `clamp(1.875rem, 4vw, 2.5rem)`, filo + spazio
  `1.875rem`, 1,3rem fra titolo e sottotitolo e sotto il sottotitolo; titolo `clamp(1.875rem, …, 2.5rem)`
  e da 768px `clamp(2.5rem, …, 3.438rem)`, sottotitolo `clamp(0.875rem, …, 1.125rem)` e da 768px
  `clamp(1rem, …, 1.125rem)`; altezza minima 17,86rem; sotto 768px testata alta quanto il contenuto
  (`--hero-height: auto` di Drupal); fino a 1024px pulsante «Credits foto» alto 2,5em. Non ripresa la
  sfumatura mobile di Drupal: resta quella dell'artifact. Child alla Version 1.4.14.
- **Pagine senza immagine in evidenza come su Drupal** (richiesta del dev, 20/10; prima restava uno
  sfondo grigio pieno). Su Drupal non hanno immagine Contatti, Crediti, News e Mappa. Contatti e Crediti
  usano il layout predefinito: stessa testata, con la sfumatura nera del tema
  (`--custom-theme-hero-gradient`, sotto 768px la variante di `_hero.scss`) sul bianco della pagina, e
  sotto il sottotitolo solo i margini di sezione e regione (senza il blocco dell'immagine manca la riga
  da 1,3rem). News e Mappa hanno un layout proprio (`node__layout_builder__layout`) senza la sezione
  hero: News mostra solo il titolo scuro con il filo centrato nel colore del marchio
  (`sase-container sase-heading-has-decoration`, `h1.sase-h1`), sotto il percorso come su Drupal. Lo
  script lo ricava dal layout (`wpf_drupal_testata`: nessuna sezione con lo stile
  `section_page_header_hero`) e lo scrive nel meta `_wpf_testata` = `titolo`: se la redazione cambia il
  layout su Drupal, la pagina segue. La Mappa ha già il titolo nascosto, il meta non cambia nulla.
  Child alla Version 1.4.17.
- **«Condividi» dell'artifact sotto il percorso, percorso sempre sotto la testata** (richiesta del dev,
  21/10). Su Drupal le pagine hanno la condivisione AddToAny (`block.block.addtoanysharebuttons`), nascosta
  su `/sentieri`, `/itinerari`, `/da-vedere`, `/da-sapere`, `/mappa-interattiva`, `/news`: al suo posto il
  «Condividi» della tavola del sentiero dell'artifact (`.sh-share`), nella riga dei pulsanti (`.sh-actions`)
  subito sotto il percorso, sulle stesse pagine (le sette di testo; Cammini segue Itinerari). Funziona
  come pensato nell'artifact, con collegamenti veri: su telefono il menu di condivisione del sistema
  (`navigator.share`), altrove il pannello con WhatsApp, Facebook, X, Telegram, Email (indirizzi di
  condivisione scritti, nessuno script né cookie di terzi) e «Copia link», che conferma «Link copiato»;
  Esc o clic fuori chiudono. Il pulsante è il `sase-btn-light` di Drupal, ora classe comune con quelli
  della Mappa (fuoco corretto: azzurro `#0089c7` di Drupal, prima avevo messo il colore del testo). Il
  percorso è sotto la testata in tutte le pagine: in News, che non ha testata, viene dopo il titolo
  (prima stava sopra, come su Drupal). Corretto anche il filo del titolo di News: su Drupal ha il colore
  tematico «news», non quello del marchio. Child alla Version 1.4.20. Poi (segnalazione del dev, 21/10)
  testo del pulsante centrato come in `sase-btn`: lo spazio fra testo e icona (`sase-btn--has-icon`)
  solo nei pulsanti con icona, quelli della Mappa (`forestas-pulsante--icona`); Version 1.4.22.
- **Credenziali della copia Drupal solo nel `.env`** (controllo prima del commit, 21/10): `drupal.php` aveva
  come default utente e password dell'utente in sola lettura della copia, cioè un valore reale in un repo
  pubblico, per una copia con dati personali. Ora `DRUPAL_DB_USER` e `DRUPAL_DB_PASSWORD` sono
  obbligatori nel `.env` e senza la sincronizzazione si ferma con un messaggio che lo dice; host, porta e
  database tengono i default. **Su UAT, prima della prima sincronizzazione dopo il merge:** aggiungere
  le due variabili al `.env` di wp-forestas e lanciare `scripts/wordpress-up.sh`.
- **Riprova del ciclo dal reset** (21/10), dopo backup completo (database, file, media) in
  `Backup/Local/forestas-wordpress-20261021-173954` sul disco esterno: passi 1-5 della guida senza
  interventi a mano, 28 pagine, ritratto identico a prima. Due imprevisti, ora nella guida: pecl.php.net
  in errore 500 durante la build (si rilancia `scripts/wordpress-up.sh`) e un avviso `HTTP_HOST` atteso
  di WP-CLI.
- **Verifica di accessibilità WCAG 2.1 AA** (richiesta del dev, 21/10), su tutte le 28 pagine (IT ed EN),
  header e footer compresi. Automatica con axe-core 4.10.2 a 1440 e 375px; a mano: contrasto dei testi
  delle testate (sulla foto almeno 4,54:1 col velo; senza foto, con la sfumatura di Drupal, minimo 4,65:1
  per il titolo di Crediti su telefono, che è testo grande), lingua della pagina, un solo h1, landmark e
  nomi delle navigazioni tradotti, nessuno scorrimento orizzontale a 320px, id unici, tastiera e nomi
  di Credits foto, Condividi, lightbox e Mappa, fuoco visibile, link «Vai al contenuto». Header e footer:
  nessuna violazione. Corretti: link del testo sottolineati anche negli elenchi (su Drupal solo nei
  paragrafi: 1,84:1 col testo, WCAG 1.4.1); lightbox accessibile (dialogo con nome, testo alternativo
  delle immagini, fuoco su «Chiudi» e trattenuto, ritorno alla miniatura; la libreria non lo fa, su
  Drupal manca); link annidati creati dall'import (vedi sotto). Restano, nei testi di Drupal: titoli
  che saltano livelli (App con un secondo h1, Note legali, Privacy, Crediti, Accessibilità) e le 35 ancore
  della FAQ senza destinazione, da segnalare al cliente (punto 10 della sequenza). Non sostituisce una
  prova con un lettore di schermo vero (VoiceOver, NVDA). Version 1.4.26. Dopo la sincronizzazione
  FAQ, Crediti e Privacy (IT ed EN) non hanno più violazioni; resta solo l'icona di WPML nella barra di
  amministrazione, che vede solo chi è collegato.
- **Controllo d'integrità della copia con tutte le tabelle lette** (21/10): durante il reload delle 10:00
  UTC una sincronizzazione si è fermata su `taxonomy_term__field_colore_tematico`, aggiunta in questo
  ticket ma non in `WPF_DRUPAL_TABELLE`: nulla scritto, ma con un errore di tabella mancante invece del
  messaggio «copia in ricarica». Ora l'elenco ha tutte le tabelle lette.
- **Tutte le pagine in inglese** (richieste del dev, 21/10). Mappa interattiva: pagina inglese di sola
  interfaccia («Interactive map», `/en/interactive-map/`; su Drupal `/en/node/1093` mostra il contenuto
  italiano). FAQ, App, Privacy, Dichiarazione di accessibilità, Contatti e News: tradotte da noi
  (decisione del dev: traduzione dei testi), in `config/traduzioni/en/`, versionate così il reset le
  ricrea; testo con la stessa struttura HTML dell'originale (tag verificati uno per uno: FAQ 1336, App
  398, Privacy 204). La sincronizzazione le usa finché Drupal non ha una sua traduzione (allora avvisa che
  la nostra non serve più) e avvisa quando il testo italiano cambia (impronta «sorgente» nel JSON).
  «Cammini» reso con «Walks», come nel menu. Indirizzi inglesi dove Drupal non dà un alias: `contacts`,
  `accessibility-statement`, `interactive-map`. Voci dei menu inglesi collegate alle pagine inglesi.
  Nelle pagine inglesi i link portano alla versione inglese, se c'è (anche nel testo inglese di Drupal
  di Crediti, che linkava l'App italiana), e la home a `/en/`, che oggi rimanda alla home italiana finché
  la home non ha la versione inglese. **Da far verificare al cliente:** Privacy e Dichiarazione di
  accessibilità sono testi legali tradotti da noi; nella Privacy, sezione b), la frase italiana è
  sgrammaticata ed è stata interpretata (dettaglio in `privacy.json`, «da_verificare»).
- **Comandi degli script in italiano** (decisione del dev, 21/10): `bin/wordpress-config.sh` mescolava
  comandi inglesi di oc:8717 (`export`, `apply`) e opzioni italiane (`--conferma`, `--da`, `--in`,
  `--pagine`, `--contenuti-mancanti`, come `scripts/wordpress-reset.sh --conferma` in forestas). Ora
  `esporta` e `applica`; `zip` resta, è il nome del formato. I nomi vecchi funzionano ancora con un
  avviso, per cron e abitudini in uso. Aggiornati i riferimenti nel codice e nei documenti vivi (README,
  howto, conoscenza, documenti di questo ticket, `forestas/docs/howto/messa-in-opera-wordpress-uat.md`);
  non i documenti dei ticket chiusi. I messaggi di `init-wordpress.sh` cambiano solo dopo
  `scripts/wordpress-up.sh` (l'init sta nell'immagine).
- **Verifica mobile sulla produzione** (richiesta del dev, 21/10). HTML e CSS di sardegnasentieri.it
  scaricati e serviti in locale (font dal tema locale), confrontati elemento per elemento con WordPress a
  375 e 1440px su tutte le pagine del perimetro (IT, e Note legali e Crediti in EN). Trovate e corrette:
  il pulsante «Credits foto» eredita carattere e interlinea del blocco (0,8125rem, 1,4) per il
  normalize del sito (`button { font: inherit }`), che la prova del 20/10 col solo CSS del tema non
  caricava (prima 13,33px, su telefono più alto); il blocco dei credits non centra gli elementi, così su
  telefono, col testo su più righe, il pulsante resta in alto; senza immagine né sottotitolo (Contatti)
  sotto il titolo non c'è lo spazio di 1,3rem. Dopo le correzioni testate, testo, allegati, galleria,
  titolo di News e barra della Mappa coincidono. Differenze volute: colore del filo di News (artifact),
  spazi di News col percorso sotto il titolo, «Condividi» al posto di AddToAny. Non misurabile nella
  prova l'altezza della mappa (su Drupal la calcola uno script); la formula è la stessa. Version 1.4.25.
- **Titolo della galleria `sase-h3`, non `sase-h2`** (segnalazione del dev, 21/10, sul telefono): il template
  attivo sulle pagine è `field--node--field-galleria-media---custom.html.twig` (titolo `sase-h3`, 21px
  sotto 960px e 24px su desktop), non `field--field-galleria-media.html.twig` che avevo seguito; lo
  conferma l'HTML della produzione (classe `blazy--field-galleria-media---custom`). Confrontati con la
  produzione anche testata, credits, testo, allegati e percorso: coincidono. Version 1.4.23.
- **Etichette d'interfaccia tradotte e uguali a Drupal** (richiesta del dev, 21/10: l'interfaccia ha
  sempre le traduzioni). Confrontate con le traduzioni di Drupal (`locales_target`) per ogni etichetta
  dei template riprodotti: «Photogallery» → «Galleria fotografica» in italiano (era fisso in inglese),
  etichetta del percorso «Briciole di pane» / «Breadcrumb» (era «Percorso di navigazione»). Tradotte
  anche quelle che su Drupal restano in una lingua sola: titolo dell'iframe della Mappa, «Foto di:» /
  «Photo by:» nella didascalia della lightbox (su Drupal fisso in italiano nel CSS), frecce
  «Precedente» / «Successiva» e pulsante «Chiudi» della lightbox (le librerie li hanno solo in inglese,
  e la chiusura non aveva nome). Le altre (allegati, credits, mappa, Condividi) erano già tradotte.
  Child alla Version 1.4.21.
- **Titolo della scheda come su Drupal** (decisione del dev, 21/10): «Pagina | Sardegna Sentieri»
  (`html.html.twig` del tema, nome del sito «Sardegna Sentieri» in `system.site`). Il nome sta in
  `config/sito.json` (`blogname`, versionato: l'apply lo scrive a ogni ricreazione), non nell'init, che
  usa `WP_TITLE` del `.env` solo alla prima installazione (aggiornato anche `.env-example`). Il separatore
  «|» non è un'opzione di WordPress: filtro `document_title_separator` nel `functions.php` del child.
  Vale per tutto il sito.
- **Percorso di navigazione «Home › pagina» come l'artifact** (scelta del dev, 20/10): sotto la
  testata, come nella tavola 8 (`.crumb`), e non sopra come su Drupal (blocco
  `sardegnasentieri_breadcrumbs` fra header e contenuto). Valori dell'artifact così come sono, non in
  scala: testo 12,5px grigio `#767676`, link «Home» blu `#254877` sottolineato, pagina corrente
  `#1A1A1A` semibold, spazio 8px, 11px sopra e sotto, filo `#EEEBE5` a tutta larghezza; il testo parte
  dove parte il logo dell'header (22px dal bordo sui telefoni).
- **Allegati identici a Drupal** (richiesta del dev, 20/10), da `field--field-allegati-media.html.twig`
  e `file-link.html.twig` del tema: titolo «Allegati» (`sase-h5`, Montserrat 600, 18–24px), voci senza
  pallini a 10px, testo del link nel colore del testo e semibold 16–18px, sottolineato al passaggio del
  mouse; sotto, l'icona `fa-file` (stile regular) nel colore del marchio e «PDF - 86.74 KB» in 14–16px,
  con la dimensione scritta come la scrive Drupal (`format_size`). Il testo del link è la descrizione
  del documento su Drupal (`field_media_document_description`), o il nome del file se manca: lo script
  la importa nel titolo dell'allegato e lo aggiorna se cambia.
- **Testo, allegati e galleria identici a Drupal** (richiesta del dev, 20/10), confrontati col CSS
  compilato del tema e con il layout predefinito delle pagine (sezione «Pagina contenuto» del Layout
  Builder, letta da `core.entity_view_display.node.page.full`: `sase-padding-v-12`, blocchi
  `sase-container--xs`, 3,125rem fra testo, allegati e galleria). Pagina di prova con il body vero delle
  7 pagine di testo, filtrato come `filtered_html`: posizione, dimensioni e stile di ogni elemento
  uguali al pixel a 1440, 800 e 375px. Per arrivarci: regole di `sase-page-body` copiate così come
  sono (prima erano approssimate: formule dei titoli diverse, carattere fisso sul contenitore), reset
  del tema Drupal al posto degli stili di Impreza, titoli annidati con lo stile del browser come su
  Drupal, body filtrato all'import come `filtered_html` e mostrato senza `the_content` finché è quello di
  Drupal (impronta `_wpf_testo_drupal`: `wpautop`, `wptexturize` e `paragraph_fix` di Impreza
  cambiavano paragrafi, virgolette e un `<br>` delle FAQ). Theme Options di Impreza non toccate: body e
  h1–h6 erano già quelli di Drupal. Galleria: titolo «Photogallery» `sase-h2` (superato: è `sase-h3`,
  vedi sotto) con il filo a sinistra,
  sezione con 2,5rem sopra e sotto, griglia con 0,9375rem fra le immagini, lente di Blazy al passaggio
  del mouse; resta su Drupal una riga 6px più alta, dallo spazio sotto l'immagine nel markup di Blazy,
  ricostruito a mano dai template e non copiato. Child alla Version 1.4.16.
- **Lightbox della galleria come su Drupal** (richiesta del dev, 20/10: «se su Drupal è così devi
  seguire 1 a 1»). Stesse librerie (Slick 1.8.1 e slick-lightbox 0.2.12, MIT) e stessi CSS dei moduli
  `slick` 8.x-2.10 e `slick_lightbox` 8.x-1.7, copiati nel child (`assets/vendor/`, con provenienza e
  licenze) e caricati solo nelle pagine con una galleria. `assets/js/galleria-lightbox.js` replica
  `slick-lightbox.load.js` per le immagini, con le impostazioni dell'optionset `slick_lightbox` (nessuna
  configurazione salvata su Drupal: valgono i default del modulo), e lo script del tema che segna autore
  e credits presenti. Didascalia del formatter della galleria: testo alternativo, poi «Foto di: autore •
  Credits: credits», con le etichette in italiano anche in inglese come nel CSS del tema. Si apre il file
  originale, non la copia ridotta di WordPress. Child alla Version 1.4.18.
- **Mappa interattiva come il blocco di Drupal** (verifica 1 a 1 del dev, 20/10). Sul sito attuale la
  pagina ha un layout proprio: titolo nascosto alla vista e il blocco `mappa_interattiva` del tema
  (`block--mappa-interattiva.html.twig`, `MappaInterattivaBlock`), non l'iframe del body, che Drupal
  non mostra. Ripresi: percorso visibile (su Drupal il blocco del percorso non ha restrizioni), h2
  nascosto «Mappa interattiva: cerca sentieri, itinerari, punti d'interesse», barra bianca con i
  pulsanti `sase-btn-light` «Espandi la mappa» / «Esci dalla modalità mappa a schermo intero»
  (traduzioni italiane di Drupal), iframe `trailMap` con l'indirizzo del blocco, altezze di
  `sase-map-wrapper has-iframe-fullpage` (finestra meno header, percorso e barra di WordPress; iframe 5rem
  sotto), schermo intero con Esc e Tab fuori dalla mappa per chiuderlo. Tolto il link «Apri la mappa in
  una nuova finestra», che su Drupal non c'è. Unica differenza voluta: aperta a schermo intero, il fuoco
  va sul pulsante che la richiude (su Drupal si perde e il Tab successivo la richiude, con la tastiera
  la mappa allargata non si usa). Da fare con le pagine di sentieri, itinerari e punti di interesse: il
  cambio di mappa con `?id=` del blocco Drupal (URL di geohub per traccia o POI dal nid).
- **Colore tematico delle pagine** (verifica 1 a 1 del dev, 20/10): su Drupal App e Privacy hanno il
  colore tematico «Da sapere» (`field_colore_tematico`), e il filo della loro testata è di quel colore.
  Lo script lo scrive nel meta `_wpf_colore`; il child usa la sezione del menu e, per le pagine che non
  ne hanno, quella del colore tematico (con i colori dell'artifact, come le altre).
- **Testo alternativo dell'immagine della testata** (verifica 1 a 1 del dev, 20/10): su Drupal
  l'immagine ha il testo alternativo del media (formatter `image` del core); prima era vuoto. Resta il
  caricamento immediato invece di quello differito di Drupal: è il primo elemento grande della pagina.
  Child alla Version 1.4.19.
- **Riquadro «Allegati e galleria» nell'editor delle pagine** (richiesta del dev, 20/10; nato come
  «Testata e allegati», con anche il sottotitolo): su Drupal la
  redazione gestisce allegati e galleria dai campi della pagina, con la libreria media; in WordPress i
  dati stavano già in campi personalizzati (`wpf_allegati`, `wpf_galleria`, `wpf_sottotitolo`), ma il
  pannello non dava modo di modificarli (il riquadro «Campi personalizzati» standard mostra un elenco
  come testo serializzato). Il riquadro (`inc/pagine-statiche-admin.php`, `assets/js/riquadro-pagina.js`)
  apre la libreria media di WordPress, riordina trascinando e salva negli stessi meta. Provato in locale
  su Note legali: allegato aggiunto, salvato, visibile nella pagina; la sincronizzazione successiva lo ha
  tolto (Drupal vince) e ha riportato anche testo IT e immagine EN, che il salvataggio dal pannello
  (editor e WPML) aveva toccato. L'avviso «questa pagina viene da Drupal…» compare solo con
  `DRUPAL_SYNC=on`: Apache vede la variabile del `.env` (verificato con `getenv`); il testo non nomina
  variabili, perché la redazione non è tecnica.
- **Sottotitolo nel Riassunto nativo** (richiesta del dev, 20/10): WordPress non ha un sottotitolo per
  le pagine, ma ha il Riassunto (`post_excerpt`), spento sulle pagine e acceso dal child. Lo script ci
  scrive la «Descrizione testata» di Drupal e cancella il vecchio meta `wpf_sottotitolo`; la testata
  legge il Riassunto scritto (non quello ricavato dal testo). Verificato: 21 pagine aggiornate, 0 meta
  vecchi rimasti, anteprima successiva «invariata». Gli altri campi passati in rassegna: allegati e
  galleria restano personalizzati (un file ha una sola pagina «madre» e nessun ordine; la galleria
  nativa sta nel testo, che Drupal riscrive); chiavi e provenienza sono campi tecnici nascosti. Child
  alla Version 1.4.5.
- I template sono post `us_content_template` con chiave (`template-pagina-testata|testo|mappa`),
  creati in locale con uno script una tantum ed esportati in `config/post.json`. Gli shortcode stanno in
  `themes/forestas-child/inc/pagine-statiche.php`; child alla Version 1.4.2.

### Task 9: menu inglesi verso pagine solo italiane

> Superato il 21/10: ora ogni pagina ha la versione inglese (nostra traduzione dove Drupal non ce l'ha) e
> le voci inglesi di `config/menu.json` sono di nuovo di tipo pagina, verso le pagine inglesi. Resta
> valido il meccanismo descritto qui (`wpf_menu_args_destinazione`, `wpf_menu_togli_orfane`), che
> interviene se una pagina inglese manca.

Emerso dalla prova sul sito ricreato da zero. Una voce del menu inglese con destinazione «pagina»
verso una pagina che esiste solo in italiano (Privacy, Contatti, FAQ, App, News, Mappa,
Accessibilità) veniva creata dall'apply e subito staccata dal menu da WPML, perché voce e pagina
hanno lingue diverse: la voce spariva dal menu e restava orfana, una in più a ogni apply. Prima del
reset non si vedeva perché le voci erano state collegate a mano. Ora `wpf_menu_args_destinazione`
(`menu.php`) trasforma in link all'indirizzo della pagina (`/privacy/`) una destinazione la cui
pagina non è nella lingua del menu, e `config/menu.json` ha queste voci come `custom`; quelle con
traduzione (Legal notes, Credits, Trails…) restano collegate alle pagine.

Sempre dalla prova sul sito ricreato: quando l'apply ricrea un menu **italiano** (qui perché le voci
passano da link a pagine) cancella le voci vecchie, e WPML stacca dai menu inglesi le loro traduzioni.
L'apply trova poi i menu inglesi vuoti e li ricrea, ma le 17 voci staccate restavano nel database,
una serie in più a ogni cambio di un menu italiano (con i menu di `develop` non succedeva perché i
menu italiani non cambiavano). `wpf_menu_togli_orfane()` (`menu.php`), alla fine dell'apply dei menu,
cancella le voci pubblicate rimaste senza menu. Ciclo completo verificato su un WordPress locale
ricreato da zero, in tutti e due gli ordini: pagine e menu collegati, nessuna voce orfana, «nessuna
differenza» all'apply successivo.

### Task 9: voci di menu verso link esterni

Su richiesta del dev (20/10) anche le voci di header e footer che su Drupal portano fuori dal sito
vanno collegate. Dalla copia Drupal (`menu_link_content_data`) l'unica voce dei nostri menu con un
link esterno è AREA RISERVATA (`menu-footer`): su Drupal punta a `/amr/oauth/login`, che rimanda al
login della Regione. In `config/menu.json` AREA RISERVATA e RESERVED AREA portano a
`https://login.regione.sardegna.it/saml/discovery?returnIDParam=idp&entityID=accessmanager.spid.regione.sardegna.it`
(indirizzo dato dal dev; il sito Drupal quel giorno non rispondeva, il rimando non si è potuto
seguire). I social dell'header e del footer sono già collegati dagli shortcode del child (oc:8757).

### Task 9: CONTATTI porta a /enti, come su Drupal

Decisione del dev (20/10): su Drupal la voce CONTATTI del menu di servizio (`links-utili---testata`)
porta a `/enti`, l'elenco degli enti, e non alla pagina «Contatti» (nid 1). Si segue la struttura di
Drupal: in `config/menu.json` Contatti porta a `/enti/` e Contact a `/en/enti/`. In WordPress
l'elenco degli enti non esiste ancora, quindi oggi la voce porta a una pagina che non c'è: arriverà
con i contenuti degli enti (shard, modello `Ente`). La pagina «Contatti» resta nel perimetro e si
sincronizza, ma nessuna voce di menu ci porta (`menu: []` in `config/pagine.json`). Se servirà, il
collegamento si cambierà.

## Sequenza consigliata per popolare i contenuti: da riportare nel tag `wp-forestas`

Proposta del 20/10, approvata dal dev come nota per il tag (da riportare alla chiusura del ticket
insieme alle sezioni su enti, altro da Drupal e form). Criterio: prima ciò da cui dipende il resto.

0. **Base comune: il motore di oc:8758.** Lettura della copia Drupal con controllo di integrità,
   media, link tramite `_drupal_nid` + `_drupal_langcode`, elenco dei mancanti, lock, log,
   `DRUPAL_SYNC`. oc:8759 e oc:8760 lo estendono invece di riscriverlo; il comando resta uno, con le
   sue parti (pagine, news, da sapere…), così il cron è uno e ha un ordine fisso.
1. **Tassonomie prima dei contenuti.** `tags` (480), `tipologia_news`, `tag_news`,
   `tipologia_da_sapere`, `zona_geografica`: si decide una volta sola come diventano in WordPress
   (categorie, tag o tassonomie proprie), così gli import successivi non creano termini al volo né
   doppioni. Ticket breve a sé o prima parte di oc:8759; se resta dentro oc:8759, oc:8760 deve
   aspettarlo.
2. **News (oc:8759) con i loro elenchi:** `/tutte-le-news`, `/attivita-e-comunicazioni`. La testata
   della pagina News c'è già (oc:8758).
3. **Da sapere, eventi e carte (oc:8760) con i loro elenchi:** `/eventi`, `/carte`, `/carte/<zona>` e
   le sezioni della pagina Da sapere (norme, altre categorie, FAQ). Dopo, i link «in attesa» delle
   pagine statiche si sistemano da soli alla sincronizzazione successiva, purché l'import scriva
   `_drupal_nid` e `_drupal_langcode`. Oggi (21/10) sono 4 articoli Da sapere linkati dalle pagine
   statiche: «Cos'è un territorio montano?» (nid 1418, dalla FAQ), «F.A.Q. sulle linee guida per la
   gestione della RES» (nid 1041, da App), «I toponimi sardi accompagnano l'escursionista…» (nid 1414,
   da App), «Le norme che definiscono la R.E.S.» (nid 1491, da Crediti IT ed EN). A import fatto,
   `bin/wordpress-config.sh pagine` non deve più elencarli come «in attesa» nel log.
4. **Homepage:** dopo 2 e 3, perché mostra slide, banner, news, eventi e da sapere in evidenza.
5. **Catasto in WordPress, in parallelo a 1-4:** sentieri, itinerari, punti di interesse, cammini e
   le pagine per categoria (`/sentieri-per-attivita`, `/itinerari-per-tipologia`,
   `/poi-per-tipologia`); dipende dall'import nello shard e dal plugin wp-geohub (scelta aperta fra
   script esterno e fork del plugin).
6. **Enti (`/enti`):** dopo 5, perché gli enti stanno nello shard (modello `Ente`); fino ad allora la
   voce CONTATTI dà 404.
7. **Form:** la segnalazione generica anche presto (la voce SEGNALAZIONI oggi è `#`); la valutazione
   del sentiero dopo 5, perché vive nella scheda del sentiero. Prima va deciso dove finiscono gli
   invii e se portare quelli vecchi. La pagina App linka `/form/segnalazione` due volte (con e senza
   `/index.php`): oggi restano verso Drupal, perché il form non è un nodo e la sincronizzazione non lo
   riscrive. Il ticket del form deve dare a quei link un indirizzo WordPress (per esempio una regola in
   `docker/scripts/config/link.php` per `/form/segnalazione`) e verificare il testo di App.
8. **Ricerca (`/cerca-nel-sito`):** per ultima, perché cerca in tutti i contenuti.
9. **Prima del lancio:** 301 dai vecchi indirizzi e sitemap; cron unico con l'ordine 1 → 2 → 3;
   `DRUPAL_SYNC=off` in produzione; decisione su dove tenere i media quando Drupal sarà spento.
10. **Da segnalare al cliente, da correggere su Drupal:** le 35 ancore interne della FAQ non hanno
   destinazione; titoli che saltano livelli nei testi di App (anche un secondo h1), Note legali, Privacy,
   Crediti e Dichiarazione di accessibilità (WCAG 1.3.1, verifica del 21/10). Corretti su Drupal, la
   sincronizzazione li porta in WordPress.

## Elenco degli enti (`/enti`): da riportare nel tag `wp-forestas`

Su Drupal `/enti` è la View `views.view.enti` («Enti, istituzioni e società»), elenco automatico dei
92 nodi `ente_istituzione_societa` dall'indice di ricerca; non è una `page` e non è stata importata.
La voce CONTATTI del menu di servizio porta lì (decisione del dev: seguire Drupal), quindi in
WordPress oggi dà 404. Nel tag gli enti compaiono solo nella tabella dei tipi di contenuto, con
destinazione «shard (modello `Ente`)»; nessun ticket del tag (oc:8711, 8716, 8717, 8737, 8757, 8758,
8759, 8760) prevede un elenco degli enti in WordPress. Da riportare nel tag alla chiusura insieme ai
form.

## Altro da Drupal che il tag `wp-forestas` non nomina

Verificato sulla copia di UAT e sulla configurazione Drupal locale il 20/10; da riportare nel tag alla
chiusura insieme a enti e form.

- **Tipi di contenuto:** tutti gli 11 con nodi sono già nella tabella del tag. I 4 tipi restanti
  (`article`, `entity_browser_test`, `liberatoria`, `openlayers_example_content`) hanno 0 nodi: nulla
  da importare.
- **Pagine generate da View** (elenchi, non nodi: nessun import le porta da sole):

  | Indirizzo | View | Che cosa elenca | Ticket che ci si avvicina |
  |---|---|---|---|
  | `/tutte-le-news` | `tutte_le_news` | news | oc:8759 importa le news, non dice nulla dell'elenco |
  | `/attivita-e-comunicazioni` | `vista_attivita_e_comunicazioni` | news di tipo attività e comunicazioni | oc:8759, come sopra |
  | `/eventi` | `lista_eventi` | eventi | oc:8760 importa gli eventi, non l'elenco |
  | `/carte`, `/carte/<zona>` | `territori_della_sardegna`, `carte` | carte per zona geografica | oc:8760, come sopra |
  | `/enti` | `enti` | enti, istituzioni e società | nessuno (sezione sopra) |
  | `/sentieri-per-attivita`, `/itinerari-per-tipologia`, `/poi-per-tipologia` | omonime | contenuti del catasto per categoria | nessuno; i dati arrivano dallo shard e dal plugin wp-geohub |
  | `/cerca-nel-sito` | `cerca_nel_sito` | ricerca su tutto il sito | nessuno; in oc:8757 la ricerca è segnalata «da collegare» |

- **Tassonomie** (termini nella lingua originale): `tags` 480, `zona_geografica` 34,
  `tipologia_poi` 21, `tipologia_da_sapere` 9, `tipologia_news` 6, `tag_news` 6,
  `categorie_slides_homepage` 6, più vocabolari del catasto (tipologie, icone, servizi, stato di
  validazione, fruibilità). Il tag cita tag e tipologie come campi di news, da sapere ed eventi, ma non
  dice se e come si importano i vocabolari in WordPress (categorie, tag o tassonomie proprie).

## Form di Drupal: informazioni per il tag `wp-forestas`

Da riportare nel tag alla chiusura del ticket (richiesta del dev, 20/10). Verificato sulla copia
Drupal di UAT il 20/10; gli invii sono stati contati, non letti, perché contengono dati personali.

| Form Drupal | Indirizzo | Da dove si raggiunge | Campi | Invii (ultimo) |
|---|---|---|---|---|
| `segnalazione` | `/form/segnalazione` | voce SEGNALAZIONI del menu di servizio; pulsante nella scheda di ogni sentiero con il sentiero già scelto (`node--sentiero--full.html.twig:224` del tema Drupal); link nel testo della pagina App | sentiero (scelta da elenco), comune o zona, nome o nickname, email, testo della segnalazione, file allegato, id del sentiero nascosto, accettazione della privacy | 79 (08/10/2026) |
| `valutazione_sentiero` | `/form/valutazione-sentiero` | pulsante nella scheda di ogni sentiero (`node--sentiero--full.html.twig:198`); le valutazioni si mostrano nella scheda con la View `valutazioni_sentiero` | comune o zona, nome o nickname, email, testo, quattro voti a stelle (facile da seguire, impegnativo ma gratificante, pericoli da considerare, valutazione generale), sentiero, id nascosto, privacy | 515 (12/10/2026) |
| `contact` | `/form/contact` | nessuna voce di menu né pagina: è il form predefinito di Drupal, non usato | — | 0 |

- Nessuno dei due form usati manda email (nessun gestore di invio configurato): gli invii restano
  solo nell'amministrazione di Drupal.
- In WordPress le voci SEGNALAZIONI / REPORT AN ISSUE portano oggi a `#`; il link al form nella pagina
  App resta verso Drupal ed è «in attesa» nel log della sincronizzazione.
- Da decidere: plugin di form o form scritto per il sito; dove finiscono gli invii e chi li legge; se e
  come portare in WordPress gli invii esistenti (dati personali); come si mostrano le valutazioni
  nella scheda del sentiero, che arriva dal plugin wp-geohub o dai ticket del catasto.
- Come si sono trovati: `config` (`webform.webform.*`), `path_alias` (`/form/…`), conteggio di
  `webform_submission` per `webform_id`, ricerca nel tema e nella configurazione Drupal locali.

## Bug trovati

- Regex dei link con `#` sia come delimitatore sia dentro il pattern: corretta con `~`.
- Voci orfane nei menu inglesi a ogni ricreazione di un menu, in due modi diversi (vedi Task 9): corretto in `menu.php`.
- **Link annidati creati dall'import** (verifica di accessibilità del 21/10): `make_clickable`,
  usato come `filter_url` di Drupal, trasformava in link anche il testo di un link che è un indirizzo
  («<a href="…">https://…</a>»), e il browser lasciava vuoto il primo. Succedeva nella FAQ (email del
  tavolo tecnico) e in Crediti (link all'App): su Drupal sono link singoli e funzionanti. Ora gli
  indirizzi diventano link solo fuori dai link (`wpf_pagina_link_dagli_indirizzi`). Smentita quindi una
  nota precedente: il link all'App in Crediti non è rotto su Drupal, lo era da noi.

## Decisioni

- **Codice d'uscita** (decisione del dev, 20/10): 1 solo se manca qualcosa che non si sistema da sé
  (pagina, traduzione, template) o per gli errori che fermano il comando; se mancano solo media
  (sito Drupal lento) esce con 0 e una riga `AVVISO … media non scaricati`. Sul server di UAT il cron
  non manda notifiche (nessun `MAILTO`, nessun programma di posta): il codice conta per chi lancia a
  mano e per un futuro script che lanci gli import in fila, che non deve fermarsi per un'immagine.

- **Procedura del cron** (richiesta del dev a posteriori, 20/10): il cron resta fuori dal ticket, ma
  i passi per configurarlo sono in `docs/howto/cron-sincronizzazione-pagine.md`, linkata dal README.
  Il comando non è ancora stato provato su UAT né dentro un cron vero.

- Il 20/10/2026 il sito Drupal in produzione rispondeva 503 alle pagine e in parte anche ai file:
  la prima sincronizzazione in locale ha lasciato 11 media fra i mancanti, che è esattamente il caso
  per cui esistono l'elenco e `--contenuti-mancanti`.

- Il 20/10/2026, alle 10:05 UTC, una sincronizzazione lanciata durante il reload giornaliero della
  copia di UAT si è fermata da sola: «copia Drupal non integra: tabella path_alias non leggibile»,
  nulla scritto. È il caso per cui esiste il controllo di integrità; il reload è finito alle 10:14.

## Follow-up

- **Al lancio, redirect 301** dai vecchi indirizzi `/pagina-base/…` (faq, privacy, note-legali e
  legal-notes, crediti e credits, app-sardegna-sentieri, dichiarazione-di-accessibilita) ai nuovi.
  `/pagina-base/app-sardegna-sentieri` è indicizzata da Google e compare fra i link sotto la Home: con
  il 301 Google sposta posizione e link sul nuovo indirizzo. «pagina-base» non è una scelta di nessuno:
  è il nome predefinito del tipo `page` di Drupal usato dal pattern pathauto
  `[node:content-type]/[node:title]`.
- **Sitemap al lancio**: quella di WordPress (`/wp-sitemap.xml`) oggi è spenta da `blog_public = 0`;
  si accende togliendo quel blocco, e va passata a Google insieme ai 301 (la sitemap da sola non
  trasferisce posizione e link dai vecchi indirizzi).
- **`DRUPAL_SYNC=off` in produzione al lancio**: dopo lo spegnimento di Drupal la copia resta ferma
  all'ultimo dump, e una sincronizzazione riscriverebbe il lavoro fatto in WordPress.
- **Media allo spegnimento di Drupal**: i file si scaricano da `www.sardegnasentieri.it`; ricreare il
  sito da zero dopo lo spegnimento non troverà più i file. Da decidere insieme agli altri import.
- **Cron della sincronizzazione** sul server: lontano dalle 10:00, ora in cui la copia di UAT si
  ricarica.
- **oc:8760 e gli altri import** devono scrivere `_drupal_nid` e `_drupal_langcode` sui contenuti che
  creano: è così che i link «in attesa» di FAQ, Crediti e App si sistemano da soli.
- Il form delle segnalazioni (`/form/segnalazione`) non ha un ticket: i link restano verso Drupal. I
  form da ricreare sono descritti più sopra, «Form di Drupal: informazioni per il tag `wp-forestas`».
- **Link «in attesa» allo spegnimento di Drupal**: se un contenuto collegato non arriva in WordPress
  prima, il link resta verso `www.sardegnasentieri.it` e smette di funzionare. Oggi sono i 4 articoli Da
  sapere (FAQ, App, Crediti IT ed EN) e il form `/form/segnalazione` (App, due indirizzi).
- **Ancore della FAQ rotte già su Drupal**: il testo ha 35 link interni (`#FAQ 1`, `#1 bis`…) ma
  nessun elemento con quell'`id` o `name` (li ha tolti il filtro `filtered_html` di Drupal). Su
  WordPress restano rotti allo stesso modo; da segnalare al cliente, che può correggere il testo su
  Drupal.
- **Design delle pagine dopo il lancio**: le pagine riproducono Drupal 1 a 1 (salvo testata e
  «Condividi» dell'artifact); un ridisegno di testo, allegati e galleria secondo l'artifact, che oggi non
  li ridisegna, va concordato col cliente e fatto con un ticket a sé.

## Review del 21/10

Review con 5 finder sul branch (commit `ab192a1`), poi correzioni nello stesso branch.

- **Soglia delle bozze per pagina, non per lingua** (bloccante): la soglia contava le coppie
  pagina/lingua, così una sola pagina tolta su Drupal in due lingue valeva due; ora conta i nid
  distinti. Cammini, che non ha un nodo proprio, non va mai in bozza (`'status' => true`). Verificato
  spubblicando Itinerari nella copia di prova: in bozza solo itinerari it/en, Cammini invariata; con 3
  pagine tolte il comando si ferma.
- **Sincronizzazione spenta, anteprima con uscita 0** (bloccante): con `DRUPAL_SYNC=off` l'anteprima
  usciva con 0 e un cron non si accorgeva che non avrebbe scritto; ora esce con 1 e
  `bin/wordpress-config.sh` stampa come accenderla.
- Credenziali della copia Drupal: tolte le predefinite dal codice (repo pubblico), obbligatorie nel
  `.env`. Su UAT vanno aggiunte prima del primo `pagine`.
- Link: un indirizzo Drupal che non è un nodo (il form delle segnalazioni) è contato a parte
  («non è un contenuto») invece che «in attesa»; un alias scritto con l'accento («cosè») si trova anche
  se l'alias di Drupal è senza (normalizzazione come il confronto di MySQL); `/en/<alias>` cade sulla
  pagina inglese.
- Log: media scaricati e riusati distinti; menu letti in tutte le lingue; mancanti fuori perimetro
  scartati; `--contenuti-mancanti` ricollega anche le traduzioni.
- Child (Version 1.4.27): `FORESTAS_META_PAGINA` e `forestas_meta_ids` condivisi fra template e
  riquadro; tolta la sezione «mappa» non usata; galleria che salta le immagini tolte dalla libreria e
  carica la lightbox solo se ha immagini; `credits-foto.js` solo dove ci sono credits; guardia contro la
  ricorsione di `[forestas_pagina_testo]`; classi e opzioni non lette tolte da galleria e lightbox;
  misure del filo e dello spazio di sezione in variabili. Nessuna differenza visibile: anteprima
  «invariata: 28», lightbox, didascalia inglese e filo verificati nel browser.
- I tre template di Impreza esistono solo in italiano e le pagine inglesi usano quelli (`us_content_id`
  verso il template italiano): il testo dei template è solo shortcode, le etichette le traduce il child.
  Se un template avrà testo fisso, servirà la sua copia inglese.
