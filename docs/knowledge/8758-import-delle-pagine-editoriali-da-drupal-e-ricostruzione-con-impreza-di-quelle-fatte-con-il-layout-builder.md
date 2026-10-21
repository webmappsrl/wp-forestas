# Pagine statiche sincronizzate da Drupal

## Come funziona oggi

Uso del comando, opzioni, log e variabili: [README](../../README.md#pagine-statiche-da-drupal). Qui
contano le scelte e i vincoli.

- **Perimetro fisso:** le 14 pagine di `config/pagine.json`, quelle raggiungibili dai menu (Contatti
  è nel perimetro anche se la voce CONTATTI porta a `/enti`, come su Drupal). Lo script
  non cerca pagine nuove su Drupal. La homepage, gli elenchi sotto le testate (View di Drupal) e le
  pagine fuori dai menu (Eventi, Attività e comunicazioni, le 4 non pubblicate) non ne fanno parte.
- **Chiave:** una pagina si riconosce da `_wpf_pagina` (la chiave del perimetro) e `_wpf_lingua`,
  mai dall'id di WordPress. `_drupal_nid` e `_drupal_langcode` dicono da quale nodo viene e servono
  ai link. Cammini non ha un nodo: è una pagina nuova chiesta dal cliente e prende, per ora, la
  testata di Itinerari.
- **Campi nativi dove WordPress li ha, personalizzati dove no.** Il sottotitolo («Descrizione testata»
  di Drupal) è il Riassunto nativo della pagina, acceso con `add_post_type_support`. Allegati e galleria
  sono meta della pagina, come su Drupal sono campi della pagina, perché i campi nativi non bastano: il
  legame «file caricato in questa pagina» ammette una sola pagina per file e nessun ordine, e la
  galleria nativa sta dentro il testo, che Drupal riscrive. Nell'editor il riquadro «Allegati e
  galleria» del child li gestisce con la libreria media. Finché la sincronizzazione è attiva le modifiche fatte lì
  vengono riscritte, e così testo alternativo, autore e credits delle immagini che una pagina sincronizzata
  usa ancora (e il titolo dei suoi PDF): la scheda dell'immagine lo dice sotto i campi, e il log lo
  segnala con una riga `MEDIA …, credits riallineati a Drupal`.
- **Autore e credits delle immagini in due campi dell'immagine** (`wpf_autore`, `wpf_credits`), come i
  due campi dei media di Drupal; la testata li mostra dietro «Credits foto». Non nella Didascalia, che
  WordPress mostra da sé sotto le immagini nel testo e nelle gallerie. Gli altri import usano gli
  stessi campi.
- **Dati nella pagina, grafica nei template.** Lo script scrive titolo, slug, testo HTML pulito,
  stato, Riassunto, immagine in evidenza e i meta `wpf_allegati`, `wpf_galleria`, più
  `us_content_id`, l'id del template di Impreza risolto dalla sua chiave. Template e shortcode del
  child non li tocca mai.
- **Il testo è quello che mostra Drupal.** Un body in `filtered_html` passa all'import per lo stesso
  filtro di Drupal (tag e attributi ammessi, letti da `filter.format.filtered_html`, con `wp_kses`; URL
  nel testo resi link solo fuori dai link già presenti, con `wpf_pagina_link_dagli_indirizzi`: un
  `make_clickable` sull'HTML intero crea link annidati). Gli altri formati (`full_html`, oggi solo la
  Mappa) passano per `wp_kses_post`: lo script scrive come amministratore e `wp_insert_post` non
  filtrerebbe script né attributi `on*`. Il child lo mostra così com'è finché coincide con
  l'impronta `_wpf_testo_drupal` scritta dallo script; un testo modificato in WordPress passa invece
  per `the_content`. Lo stile del testo ricalca le regole compilate di `sase-page-body` (solo figli
  diretti senza classe, spazio sul margine superiore del secondo elemento) e il reset del tema Drupal,
  in rem come lì.
- **Lingue:** su Drupal `it` (più `und`, trattato come italiano) ed `en`; in WPML `it` ed `en-us`.
  Ogni pagina ha la versione inglese: quella di Drupal, dove c'è (vince sempre); altrimenti la nostra
  traduzione in `config/traduzioni/en/<chiave>.json` e `.html` (FAQ, App, Privacy, Dichiarazione di
  accessibilità, Contatti, News), che il log segnala da rivedere quando il testo italiano su Drupal
  cambia (impronta «sorgente»); la Mappa inglese è di sola interfaccia (`titolo.en`). Nelle pagine
  inglesi i link portano alla versione inglese, se c'è, e la home è `/en/`.
- **Un solo scrittore:** `esporta` e `applica` di `bin/wordpress-config.sh` saltano le pagine con
  `_wpf_pagina`, anche se un'opzione punta a loro: in quel caso nemmeno l'opzione si esporta (resta fuori
  da `sito.json` e dalle Theme Options), l'apply lascia il valore di ogni sito e l'export avvisa che lì va
  impostata dal pannello dopo il comando pagine (oggi nessuna opzione lo fa; con oc:8759 la
  pagina degli articoli potrebbe essere News). I menu invece li scrive solo l'apply: le voci del
  perimetro in `config/menu.json` hanno `tipo: pagina` e il percorso, cioè lo slug, che va cambiato lì
  insieme a `config/pagine.json`.
- **Meta in comune fra script e child:** gli script li hanno come costanti `WPF_META_*`, il child come
  `FORESTAS_META_*` (`inc/pagine-statiche.php`), perché il tema non carica gli script. Rinominare un
  meta da una parte sola non dà errori: la pagina perde colore, testata o allegati.
- **Testata:** altezze, misure e impaginazione dal tema Drupal (`_hero.scss`: 66% della finestra,
  500–600px su desktop, testo in basso e centrato); sfumatura dal basso delle tavole del catasto
  dell'artifact «Canvas Fase 2» (`.sh-scrim`); la barretta ha il colore dell'entità della voce di menu
  (`--sase-sezione-*`). Testo, allegati e galleria seguono il tema Drupal, che l'artifact non
  ridisegna. La mappa è il blocco mappa del tema Drupal (iframe dell'app, schermo intero), con il
  titolo nascosto alla vista come sul sito attuale. Il percorso sta sempre subito sotto la testata (o
  sotto il titolo, dove la testata non c'è), seguito dalla riga dei pulsanti: per le pagine solo
  «Condividi» dell'artifact, dove Drupal mostra la sua condivisione (`FORESTAS_PAGINE_SENZA_CONDIVIDI`). Una pagina fuori dal menu principale prende il colore
  del filo dal suo colore tematico di Drupal (`_wpf_colore`). Senza immagine in
  evidenza la testata ha la sfumatura nera del tema Drupal sul bianco; una pagina che su Drupal ha un
  layout proprio senza hero (News) ha solo il titolo (meta `_wpf_testata`, ricavato dallo script). La galleria apre la
  lightbox di Drupal con le sue stesse librerie e i CSS dei moduli Slick (`assets/vendor/`), caricati
  solo dove c'è una galleria perché hanno regole globali.

## Perché così

- **Drupal vince sempre** (oc:8758): finché la sincronizzazione è attiva la redazione lavora su
  Drupal e in WordPress le pagine non si modificano. Confrontare versioni pagina per pagina per
  rispettare modifiche fatte in WordPress non vale la pena per un sistema che vive fino al lancio.
- **Aggiornare invece di svuotare e ripopolare** (oc:8758): id, indirizzi, menu, media e
  collegamenti WPML restano stabili, e i media non si riscaricano ogni giorno.
- **Bozza invece di cancellazione** (oc:8758): una spubblicazione temporanea su Drupal si recupera
  alla sincronizzazione successiva senza id nuovi né media orfani.
- **Niente `the_content` sul testo di Drupal** (oc:8758): i filtri cambiano un HTML già finale.
  `wpautop` fa di due `<br>` di fila un nuovo paragrafo e mette in un paragrafo il testo dei
  blockquote; `wptexturize` cambia apostrofi e virgolette; `paragraph_fix` di Impreza toglie il `<br>`
  che segue una `]` (nelle FAQ, dopo «[...]»). L'impronta serve perché l'editor classico salva i
  paragrafi come righe vuote: un testo scritto lì senza `wpautop` perderebbe i paragrafi.
- **Theme Options di Impreza non toccate per il testo** (oc:8758): tipografia del body e di h1–h6 sono
  già quelle di Drupal (oc:8757). Le differenze venivano dagli stili di Impreza sugli elementi
  (margini di paragrafi e liste, padding sopra i titoli, stile dei titoli annidati), che su Drupal non
  esistono: si annullano dentro `.forestas-pagina-testo`, non nelle opzioni, che valgono per tutto il
  sito.
- **Utente e password della copia solo nel `.env`, senza default** (oc:8758): il repo è pubblico e la
  copia contiene dati personali (utenti, invii dei form). Host, porta e database hanno i default di UAT e
  produzione, che non sono segreti.
- **Interruttore `DRUPAL_SYNC`, spento per default** (oc:8758): dopo lo spegnimento di Drupal la copia
  resta, ferma all'ultimo dump, e una sincronizzazione lanciata per sbaglio riscriverebbe il lavoro
  fatto in WordPress.
- **Controllo di integrità e soglia di 2 bozze** (oc:8758): il reload della copia fa `DROP DATABASE`
  e carica le tabelle una per volta (`forestas/scripts/drupal-dump-reload.sh`); in quei minuti, senza
  controlli, tutte le pagine andrebbero in bozza o si salverebbero testi vuoti.
- **Elenco dei mancanti sul sito, non nel log** (oc:8758): i log ruotano, e un file di testo non è un
  elenco affidabile da cui ripartire. L'opzione (`wpf_sync_mancanti`) è delle pagine: a ogni giro si
  tolgono le voci che non sono chiavi di `config/pagine.json`. Un altro import (oc:8759, oc:8760) che
  volesse un elenco così deve averne uno suo, o dare alle voci la parte di provenienza.
- **Un blocco per comando** (oc:8758): `wpf_blocca( $opzione, $scadenza )` in `comune.php`, usato
  dall'apply (40 minuti) e dalle pagine (`WPF_PAGINE_SCADENZA`, 2 ore: con il sito Drupal lento ogni
  file può costare un minuto, e un blocco tolto mentre la prima esecuzione scrive ancora lascerebbe
  partire la seconda, con allegati doppi). L'init toglie all'avvio del container i blocchi di tutti e
  due presi prima dell'avvio: i comandi girano nel container e muoiono con lui.
- **Indirizzi senza `/pagina-base/`** (oc:8758): su Drupal è il nome predefinito del tipo `page` nel
  pattern automatico di pathauto, non una scelta. Al lancio servono i 301 dai vecchi indirizzi
  (`/pagina-base/app-sardegna-sentieri` è indicizzata da Google) e la sitemap.
- **Lettura dal container, host dal `.env`** (oc:8758): sull'host di UAT non ci sono né `mysql` né
  `php`, mentre il container WordPress e la copia stanno sulla stessa rete Docker. Il tunnel serve
  solo in locale, e gli script non contengono SSH.
- **Voci dei menu inglesi verso pagine solo italiane come link** (oc:8758): collegate alla pagina,
  WPML le staccava dal menu perché di lingua diversa dalla pagina. Una destinazione la cui pagina non è
  nella lingua del menu diventa link all'indirizzo (`/privacy/`, `wpf_menu_args_destinazione`). Oggi
  ogni pagina ha la versione inglese e tutte le voci inglesi del perimetro sono collegate alle pagine
  inglesi: il ripiego scatta solo se una pagina inglese manca.
- **Pulizia delle voci senza menu a fine apply** (oc:8758): quando l'apply ricrea un menu italiano,
  WPML stacca dai menu inglesi le traduzioni delle voci cancellate; i menu inglesi vengono ricreati, e
  `wpf_menu_togli_orfane()` cancella le voci rimaste senza menu, che altrimenti si accumulerebbero.
- **Link verso contenuti non ancora in WordPress** (oc:8758): un link del testo a un nodo Drupal che in
  WordPress non c'è resta verso `www.sardegnasentieri.it` ed è segnato «in attesa» nel log. Si riscrive
  da solo alla sincronizzazione dopo che l'import di quel contenuto ha scritto `_drupal_nid` e
  `_drupal_langcode`: ogni nuovo import deve scriverli. I link a pagine che non sono nodi (i form, come
  `/form/segnalazione`) non si riscrivono mai da soli: sono segnati `LINK … verso Drupal` nel log e
  serve una regola in `link.php`. Elenco aggiornato
  dei link in attesa: le righe `LINK … in attesa` dell'ultimo log in `log/pagine-*.log`.
- **Link interni relativi, hook di WPML staccati** (oc:8758): al salvataggio di una traduzione WPML
  (`ItemUpdateEvent`) riscrive allo shutdown i link interni come indirizzi assoluti dell'ambiente;
  `wpf_wpml_senza_link_automatici()` stacca quegli hook durante la sincronizzazione.

## Come ci siamo arrivati

- **Sottotitolo in un campo personalizzato** (oc:8758, superata): `wpf_sottotitolo`; sostituito dal
  Riassunto nativo, che WordPress, WPML e Impreza già conoscono. Una versione intermedia dello script
  cancellava il vecchio meta, poi tolta: in locale non ne resta nessuno; su un sito che avesse girato
  quella prima versione si cancella con `wp post meta delete`.

- **Svuotare e ripopolare ogni giorno** (scrum 06/10, superata): avrebbe dato id e indirizzi nuovi a
  ogni giro, rompendo menu e collegamenti; vedi «Aggiornare invece di svuotare».
- **Testata identica a quella di Drupal** (oc:8758, superata): sfumatura nera dal basso, testo in
  basso, colori tematici del sito attuale. Il dev ha scelto l'artifact, che segue gli aggiornamenti
  chiesti dal cliente, per colori e resa.
- **Testata con immagine desaturata e scurita e testo al centro** (oc:8758, superata): tavola 8
  dell'artifact; il dev ha preferito l'impaginazione del sito e la sfumatura delle tavole del catasto.
- **Testata con le misure dell'artifact in scala** (oc:8758, superata): 268px di altezza, immagine
  troppo bassa rispetto al sito attuale; altezze e misure sono tornate quelle del tema Drupal.
- **Correggere il testo nel database dopo il salvataggio** (oc:8758, superata): WPML riscrive i link
  allo shutdown, cioè dopo; staccare i suoi hook è l'unico modo che regge.
