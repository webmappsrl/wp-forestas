# News sincronizzate da Drupal

## Come funziona oggi

Le news di Drupal (95 nodi `it`/`und`, 4 con la traduzione inglese) sono **post di WordPress** con la
categoria «News». Le scrive lo stesso giro delle pagine statiche: `bin/wordpress-config.sh pagine`, e
quindi `allinea` e il suo cron. Prima le pagine, poi le news, con lo stesso lock e lo stesso log. Le
regole delle pagine valgono anche qui (pagina di conoscenza di oc:8758): copia Drupal in sola lettura
con il controllo di integrità, Drupal vince sempre, `DRUPAL_SYNC`, mancanti da ritentare.

- **Codice.** `docker/scripts/config/contenuti-lib.php` è la parte comune (scrittura di un contenuto,
  pagina o post, con media, termini e traduzione WPML; pulizia del testo; elenco dei mancanti e righe
  di log; regole dei termini importati, `wpf_termine_per_drupal` e `wpf_termine_ritrova_o_crea`; schede).
  `pagine-lib.php` ha solo il perimetro delle pagine, `news-lib.php` solo le news. `drupal.php` legge
  la copia: per le news carica ogni tabella una volta sola, per bundle (`wpf_drupal_bundle_tabella`,
  `wpf_drupal_bundle_campo`), e riusa la stessa lettura per gli altri bundle di oc:8760.
- **Riconoscimento.** Una news si ritrova da `_drupal_nid` e `_wpf_lingua`, mai dall'id di WordPress.
- **Campi.** Titolo, testo filtrato come `filtered_html`, sottotitolo nel Riassunto, data del post da
  `field_data` (all'inizio del giorno, nel fuso del sito, versionato in `config/sito.json`: con un'ora
  del giorno una news con la data di oggi sincronizzata prima di quell'ora sarebbe «programmata»), immagine
  in evidenza, `wpf_galleria`, `wpf_allegati`. In più:
  `wpf_in_evidenza` («1»), `wpf_collegamenti` (titolo e url, link interni riscritti come quelli del
  testo) e `_wpf_riferimenti`. `field_date` non si importa: vedi «Come ci siamo arrivati».
- **Riferimenti salvati, non mostrati.** I 18 riferimenti di `field_references` (enti, sentieri,
  itinerari) stanno in `_wpf_riferimenti` come tipo neutro (`ente`, `sentiero`, `itinerario`, `poi`) e
  nid Drupal. Nessuna pagina li mostra: li userà il plugin che legge lo shard con le API, dove ogni
  contenuto ha il suo nid Drupal (`sardegnasentieri_id`).
- **Termini.** Categoria «News» (slug `news`) con le 6 tipologie di `tipologia_news` come figlie; i tag
  di `tag_news` e `tags` sono `post_tag`, creati solo se una news li usa. Ogni termine ha il term meta
  `_drupal_chiave` = `<vocabolario>:<tid>`, **non univoco**: un termine che ne fonde due porta tutte le
  coppie. Un import successivo (oc:8760) ritrova i termini da lì. La tassonomia `tipologia_da_sapere` è
  registrata nel child, senza termini e senza tipo di contenuto: li porta oc:8760.
- **Lingue.** Le 4 news tradotte su Drupal hanno il post inglese collegato in WPML. Post, categorie e
  tag sono in WPML «traducibili, con ripiego»: sul sito inglese le altre news si vedono in italiano, e
  i termini, che su Drupal non hanno traduzioni, restano quelli italiani.
- **Indirizzi.** Permalink `/news/%postname%/` (`config/sito.json`): la news sta a `/news/<slug>/`, come
  su Drupal. Lo slug è l'alias di Drupal passato da `sanitize_title`, che toglie accenti e apostrofi
  tipografici (3 news). Il child porta in 301 i vecchi indirizzi: `/news/<alias con accenti>` e
  `/node/<nid>` (la news 1094 non ha alias e su Drupal sta solo lì).
- **Pagina News a schede** (Canvas Fase 2, tavola 9). La pagina News è la prima scheda, «News», con
  le 4 news in evidenza; la seconda, «Attività e comunicazioni», è la pagina figlia
  `/news/attivita-e-comunicazioni/`. Tutto in `config/pagine.json` (`genitore`, `scheda`, `pulsante`,
  `griglia`). Le pagine d'elenco sono `/attivita-e-comunicazioni/` e `/tutte-le-news/`, senza
  «Condividi» come la pagina News. Su Drupal schede ed elenchi sono View, quindi senza nid; la testata
  viene da News. Ogni pagina dice nel campo `griglia` quali news mostra, con i valori della View di
  Drupal (tipologia, in evidenza, quantità, ordine, paginazione, variante della card): il child non
  conosce nomi di View.
- **Card unica del Canvas** con tre varianti: in evidenza (foto piena), compatta (foto a lato), senza
  foto (filetto in alto); nelle pagine d'elenco la card con la foto in alto. Etichetta: la tipologia
  nelle card in evidenza, i tag nelle altre, come le anteprime di Drupal.
- **Pagina della news.** Template `template-news` (`[forestas_news]`), il predefinito di Impreza per tutti
  i post (`content_post_id` in `config/impreza.json`): anche una news scritta dalla redazione ha questo
  layout. Contiene la testata delle pagine con tipologia e data, poi tag, testo, allegati, collegamenti e galleria, nell'ordine del layout di Drupal.
  Le icone della news (data, segnalibro dei tag, file degli allegati, freccia dei collegamenti) hanno il
  colore della sezione News, come su Drupal (`--section-color`): `--sase-sezione-news`, `#C2410C`
  concordato al posto del `#F67745` del sito attuale. I tag sono il blocco `sase-tag`: segnalibro
  «light», maiuscolo, voci separate da «-». L'icona della data è `fa-calendar-edit`, l'equivalente
  in Font Awesome 5 Pro (quello di Impreza) del `fa-calendar-lines-pen` di Drupal, che è di FA 6.
- **Editor della news.** Gli stessi campi del form della news di Drupal: data, immagine in evidenza,
  Riassunto, categorie e tag nativi, più il riquadro delle pagine con il titolo «Allegati, collegamenti
  e galleria» (`inc/pagine-statiche-admin.php`): allegati, collegamenti (righe URL e testo) e galleria,
  nell'ordine del form di Drupal. «In evidenza» ha un riquadro suo in cima alla colonna laterale
  («Mostra nella pagina News»): la pagina News mostra solo le 4 news in evidenza più recenti, e una news
  senza la spunta si vede solo negli elenchi; in fondo al riquadro grande la redazione non lo notava
  (27/10). La categoria predefinita dei post è News: la imposta il giro delle news.
- **Accessibilità (WCAG 2.1 AA).** Schede come link in un `<nav>` con `aria-current="page"`; card con
  un solo link, sul titolo, e immagine decorativa (`alt=""`); titoli delle card `h3` nelle schede (sotto
  l'`h2` della scheda) e `h2` nelle pagine d'elenco, senza saltare livelli; frecce della paginazione con
  un nome per lo screen reader. Una news italiana mostrata sul sito inglese (ripiego di WPML) ha
  `lang="it"` su titolo, testo, percorso e card (`forestas_attributo_lingua`, WCAG 3.1.2), così lo
  screen reader la legge con la pronuncia italiana. Controllo axe-core 4.9.1 del 27/10 (A e AA): nessuna violazione;
  restano da verificare a mano i contrasti del testo bianco su immagini e sfumature (testata, card in
  evidenza).
- **Protezioni.** Una news spubblicata o non più nella copia va in bozza, mai cancellata; così anche la
  sola traduzione inglese tolta su Drupal. Con più di 5
  news da mettere in bozza nello stesso giro, o con le news pubblicate della copia sotto quelle di
  WordPress di più di 5, lo script non scrive nessuna news (le pagine sì) e si va avanti solo con
  `--accetta-bozze`. Per provare il controllo, `WPF_NEWS_PROVA_CONTEGGIO=<n>` simula una copia con `<n>`
  news, solo in anteprima. `bin/wordpress-config.sh` non la passa al container: va data a mano, per
  esempio `docker exec -u www-data -e WPF_PROVA=1 -e WPF_NEWS_PROVA_CONTEGGIO=10 wordpress-<APP_NAME> wp
  --path=/var/www/html --user=<admin> eval-file /usr/local/lib/wp-forestas/pagine.php`; con un sito in
  https serve anche `--exec='$_SERVER["HTTPS"]="on";'`, come aggiunge lo script. Le soglie sono due
  costanti di `news-lib.php`: `WPF_NEWS_SOGLIA_BOZZE` (news o traduzioni da mettere in bozza) e
  `WPF_NEWS_SCARTO_COPIA` (news pubblicate in meno nella copia).
- **File dei media.** Si scaricano dal sito pubblico di Drupal: la copia ha solo il database.
  `DRUPAL_FILES_URL` nel `.env` cambia l'indirizzo, per pagine e news. Esempio, l'immagine della news
  1040: nella copia `node__field_top_image_media` porta al media 6263, `media__field_media_image` al
  file 3052, e `file_managed` dice `uri` = `public://WhatsApp Image 2022-05-26 at 09.48.04.jpeg`. Lo
  script sostituisce `public://` con l'indirizzo dei file e scarica
  `https://www.sardegnasentieri.it/sites/default/files/WhatsApp%20Image%202022-05-26%20at%2009.48.04.jpeg`
  nella libreria media (allegato con `_drupal_fid` 3052, `uploads/…/locandina-oliena.jpeg`). Ai giri
  dopo lo ritrova dal `_drupal_fid` e non lo riscarica.

## Perché così

- **Post e non un tipo di contenuto proprio:** le news sono il blog del sito, e WordPress dà ai post
  categorie, tag, archivi e WPML senza codice. I tipi propri arrivano con oc:8760 (eventi, Da sapere).
- **Riferimenti non mostrati:** sono contenuti del catasto che vivono nello shard. WordPress non girerà
  sulla stessa macchina di Laravel e li leggerà con le API (call con Giuseppe del 27/10); il dato si
  salva ora perché la copia SQL di Drupal sparirà.
- **Un giro solo per pagine e news:** i link si riscrivono in tutte e due le direzioni, e un cron solo
  tiene allineato tutto. Le news si leggono prima di scrivere le pagine, così un link di una pagina
  verso una news si risolve subito.
- **Le tabelle delle news in memoria:** dal Mac, col tunnel verso UAT, ogni query costa quasi mezzo
  secondo; una per campo e per news voleva dire 13 minuti a giro, una per tabella 9 secondi.
- **Soglia propria delle news (5) e controllo sul numero:** il controllo di integrità guarda solo che
  le tabelle non siano vuote, e una copia caricata a metà restituisce meno news. Senza controllo le
  news mancanti andrebbero in bozza a decine.
- **Schede come pagine con il loro indirizzo:** il Canvas vuole un indirizzo per scheda. Con i
  permalink `/news/%postname%/` WordPress prova `/news/<x>/` come post prima che come pagina: il child
  aggiunge in testa una regola per ogni pagina scheda, letta dal database, e lo script rigenera le
  regole quando l'elenco delle schede cambia o quando cambia la versione delle regole del child
  (`FORESTAS_REGOLE_VERSIONE` in `inc/news.php`): lo controlla `forestas_regole_aggiorna()`, chiamata dal
  giro di `pagine` (riga `REGOLE` nel log) e dal pannello, quindi anche dopo il lancio, con la
  sincronizzazione spenta.
- **`/news/` solo per i post:** con i permalink `/news/%postname%/` WordPress mette `/news/` davanti a
  ogni tipo e tassonomia registrati con `with_front`. Il child lo toglie a tutti quelli non nativi
  (`forestas_senza_front`): i tracciati di `wp-geohub` restano a `/track/`, e così i tipi di oc:8760.
  Categorie e tag, nativi, stanno a `/news/category/` e `/news/tag/`, che nessun link usa.
- **Slug normalizzati nello script:** WordPress salverebbe comunque lo slug senza accenti, e lo script
  vedrebbe lo slug «cambiato» a ogni giro (mancante perpetuo, uscita 1).

## Come ci siamo arrivati

- Nel Canvas la prima scheda era «Attività e comunicazioni», a un indirizzo suo, e News aveva
  `/news/news/`. Il dev ha scelto il contrario (27/10): `/news/` è già la scheda News, senza un
  indirizzo doppio.
- Il ticket nasceva con il plugin di Forestas per gli enti (lettura diretta del DB dello shard, pagine
  dinamiche `/enti/<slug>`). La call del 27/10 con Giuseppe l'ha tolto: il plugin nasce da un fork di
  `wp-geohub`, generico con personalizzazioni per shard, e legge lo shard con API dedicate a
  WordPress. Le note del ticket hanno il dettaglio.
- `/attivita-e-comunicazioni` su Drupal non ha un ordinamento (View `vista_attivita_e_comunicazioni`,
  senza criterio di ordine): qui le news sono in ordine di data, le più recenti prima. Senza ordine la
  paginazione di Drupal non è stabile e una news (nid 3436) non compare in nessuna pagina; qui ci sono
  tutte. Come su Drupal, le news in evidenza ricompaiono nella lista e il blocco «In evidenza» si ripete
  su ogni pagina (verificato il 27/10).
- Su Drupal la scheda Attività ordinava per `field_date`, un campo nascosto nel form che le news
  recenti non hanno: finivano in fondo. Il dev ha scelto l'ordine per data, come le altre griglie (27/10).
- Allegati nella libreria media di WordPress **in attesa di conferma**: se servono anche allo shard
  devono vivere su Laravel (dubbio di Giuseppe, da chiudere con Alessio).
