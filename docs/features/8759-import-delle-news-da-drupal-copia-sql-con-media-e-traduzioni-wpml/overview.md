> Ticket: oc:8759

# Import delle news da Drupal (copia SQL), con media e traduzioni WPML

## Cosa cambia

Le 95 news italiane di sardegnasentieri.it (93 `it` e 2 `und`, trattate come italiane) e le 4
traduzioni inglesi diventano **post di WordPress** con la categoria «news». Le sincronizza lo stesso
motore delle pagine di oc:8758, dentro il giro di `bin/wordpress-config.sh pagine` e quindi di
`allinea` e del suo cron: niente comando nuovo. Valgono le stesse regole: legge la copia Drupal con
il controllo di integrità, Drupal vince sempre, una news spubblicata va in bozza invece di essere
cancellata, si aggiorna senza svuotare e ripopolare, c'è un log e un lock, e tutto è attivo solo con
`DRUPAL_SYNC`.

Ogni news porta con sé quello che ha su Drupal:
- titolo, testo filtrato come `filtered_html` e sottotitolo;
- la data mostrata (`field_data`);
- immagine principale, galleria e allegati nella libreria media, con autore e credits;
- tipologia, tag e «in evidenza»;
- i link «Collegamenti».

La pagina della singola news si ricostruisce con un template di Impreza, 1 a 1 col layout di Drupal.

La pagina **News** diventa una pagina a schede, come nel Canvas Fase 2, Tavola 9:
- **News**, la prima scheda, aperta all'arrivo: è la pagina News stessa, a `/news/` (decisione del dev,
  27/10, al posto di `/news/news`);
- **Attività e comunicazioni**, a `/news/attivita-e-comunicazioni`.

I pulsanti delle schede portano alle pagine d'elenco complete, come su Drupal: `/attivita-e-comunicazioni`
(8 per pagina) e `/tutte-le-news` (9 per pagina). Le news restano all'indirizzo che hanno su Drupal,
`/news/<titolo>`.

Il motore della sincronizzazione impara a distinguere pagine, post e tipi di contenuto propri (CPT),
e a scrivere pagine figlie. Così oc:8760 aggiunge eventi, «Da sapere» e carte senza riscrivere la
parte comune.

I **riferimenti** delle news (`field_references`, il blocco «Riferimenti» verso enti, sentieri e
itinerari) **non si mostrano in questo ticket**: sono contenuti del catasto, vivono nello shard, e
WordPress li leggerà con le API dello shard attraverso un plugin che nasce in ticket successivi
(call con Giuseppe del 27/10). Il dato però si salva già, come meta nascosto del post, perché la
copia SQL di Drupal sparirà.

## Perché

Il nuovo sito WordPress deve sostituire Drupal con gli stessi contenuti. Dopo le pagine
editoriali (oc:8758) le news sono il contenuto redazionale più consistente, e sono anche il primo
import che usa i vocabolari di Drupal. Per questo qui si fissano le regole che oc:8760 riuserà, cioè
dove vanno i termini e come si ritrovano, così che quel ticket non crei termini doppi. Il cliente
vuole la pagina News a schede del Canvas Fase 2. Pulsanti e pagine d'elenco restano invece quelli
di Drupal.

## Requisiti

**Contenuti**
- [ ] Ogni news `it`/`und` pubblicata diventa un post pubblicato, con `_drupal_nid` e `_drupal_langcode`;
      una news spubblicata su Drupal va in bozza; un secondo giro senza cambi non scrive nulla.
- [ ] Una news che non c'è più nella copia (cancellata su Drupal) va in bozza, non si cancella.
- [ ] Protezione da una copia caricata a metà (il reload delle 10:00 ricarica una tabella alla
      volta, e `wpf_drupal_controlla()` verifica solo tabelle non vuote e nid di `pagine.json`):
      - soglia propria delle news: con più di 5 news da mettere in bozza nello stesso giro lo script
        si ferma senza scrivere e lo segnala nel log; si prosegue solo con `--accetta-bozze`;
      - prima di scrivere, le news pubblicate trovate nella copia devono essere almeno quante quelle
        pubblicate in WordPress meno la soglia; altrimenti la copia è sospetta e lo script si ferma.
- [ ] La news senza alias su Drupal (nid 1094, «Cos'è Sardegna-SENTIERI?», a `/node/1094`) prende lo
      slug dal titolo, con un redirect da `/node/1094`.
- [ ] Le 4 news con traduzione inglese su Drupal hanno il post inglese collegato con WPML; le altre
      si vedono in italiano anche sul sito inglese (ripiego di WPML sui post).
- [ ] Slug uguale all'ultimo pezzo dell'alias Drupal, quindi l'indirizzo resta `/news/<titolo>`
      senza bisogno di redirect. Lo slug passa da `sanitize_title` come lo salverebbe WordPress (3
      alias con accenti o apostrofi tipografici): il vecchio indirizzo porta alla news con un 301.
- [ ] Data del post e data mostrata da `field_data` (presente in tutte le 95 news italiane), che resta
      anche su una news in bozza (data GMT ed `edit_date` nella scrittura).
- [ ] Testo filtrato come `filtered_html` (lo stesso filtro di oc:8758). I link verso altri
      contenuti di Drupal si riscrivono con `_drupal_nid`, compresi quelli che altri contenuti
      fanno verso una news.
- [ ] Il sottotitolo (`field_descrizione_testata_pagina`) va nel Riassunto, come per le pagine.
- [ ] L'immagine principale (`field_top_image_media`) diventa l'immagine in evidenza; la galleria va in
      `wpf_galleria` e gli allegati (`field_file_media`) in `wpf_allegati`, nell'ordine del campo su Drupal; i media sono
      deduplicati per `_drupal_fid`, con `wpf_autore` e `wpf_credits`. Le 3 news con l'immagine solo
      nel campo legacy restano senza, come su Drupal (i file non esistono più). Gli allegati stanno
      nella libreria media di WordPress finché Alessio non conferma dove devono vivere (vedi Rischi).
- [ ] «In evidenza» (`field_in_evidenza`) è salvato come meta del post, non come sticky.
- [ ] «Collegamenti» (`field_collegamenti`, link con titolo) è salvato come meta. I collegamenti verso
      il sito stesso si riscrivono come i link nel testo.

- [ ] I riferimenti (`field_references`) sono salvati in un meta del post come elenco di tipo e nid
      Drupal (es. `ente` 1044), tutti e 18, senza filtri e senza mostrarli. Lo shard ritrova ogni
      contenuto dal nid Drupal (`sardegnasentieri_id`).

- [ ] L'indirizzo da cui si scaricano i file di Drupal non è più fisso nel codice
      (`WPF_DRUPAL_FILES`, `drupal.php:22`) ma viene dal `.env` (`DRUPAL_FILES_URL`), con il sito
      pubblico come valore predefinito. Vale per le news e per le pagine di oc:8758, che usano la
      stessa funzione (`wpf_drupal_url_file()`, `media.php:83`): un solo punto per tutti i media.
      `docs/howto/ricreare-wordpress-da-zero.md` e `.env-example` spiegano la variabile.

- [ ] Nell'editor della news la redazione trova gli stessi campi del form della news di Drupal: data,
      immagine principale, Riassunto (sottotitolo), categorie (tipologia) e tag nativi; nel riquadro
      «Allegati, collegamenti e galleria», come quello delle pagine, allegati e galleria dalla libreria
      media, i collegamenti (righe URL e testo, da aggiungere, togliere e riordinare; un collegamento al
      sito stesso è un percorso relativo come `/news/x/`, che il campo accetta) e «In evidenza».
      Con la sincronizzazione accesa il riquadro avvisa che le modifiche a una news del vecchio sito si
      perdono. Una news nuova parte nella categoria News.

**Termini**
- [ ] Una categoria «news» fa da contenitore; le 6 tipologie di `tipologia_news` sono sue
      sottocategorie.
- [ ] I tag sono `post_tag`, alimentati da `tag_news` e `tags`, e si creano solo se una news li usa.
      Due termini con lo stesso nome diventano uno solo.
- [ ] Ogni termine importato ha nei term meta l'id e il vocabolario Drupal, così un import successivo
      lo ritrova senza ricrearlo.
- [ ] È registrata la tassonomia `tipologia_da_sapere` (nome, slug, traducibile con WPML), senza tipo
      di contenuto: oc:8760 la collega a «Da sapere» e porta i termini.

**Pagine e indirizzi**
- [ ] In `config/pagine.json` ci sono: la scheda `news-attivita-e-comunicazioni`, figlia di News, che
      è a sua volta la prima scheda; le pagine d'elenco `/attivita-e-comunicazioni` e `/tutte-le-news`, senza nid Drupal perché su
      Drupal sono View. Il motore delle pagine scrive `post_parent`.
- [ ] I post hanno indirizzo `/news/<slug>`; una regola di rewrite del child dà la precedenza alle
      pagine figlie di News, e lo script segnala un post il cui slug coincide con quello di una
      scheda. `/news/` vale solo per i post: i tipi e le tassonomie non nativi (i tracciati di
      `wp-geohub`, `tipologia_da_sapere`, i tipi di oc:8760) non ci finiscono sotto (`with_front`
      falso). Le regole si rigenerano quando cambiano le schede o la loro versione (`WPF_REGOLE_VERSIONE`).
- [ ] La pagina News mostra le schede come nel Canvas: percorso di navigazione a tre livelli con
      `aria-current="page"`, un indirizzo per scheda, card unica con le varianti (in evidenza con
      foto, compatta, senza foto), colore della sezione #C2410C, scorrimento orizzontale delle schede
      sotto 860px. Le schede sono definite da una configurazione generica: aggiungerne una (Eventi,
      oc:8760) non richiede codice nuovo.
- [ ] Contenuto delle schede e delle pagine d'elenco come le View di Drupal: filtri per tipologia e
      «in evidenza», quantità e paginazione; ordine per `field_data` decrescente in tutte (la scheda
      Attività su Drupal ordinava per `field_date`, campo nascosto: decisione del dev, 27/10). Niente
      «Condividi» su schede e pagine d'elenco.
- [ ] La pagina della singola news segue il layout Drupal (`core.entity_view_display.node.news.full.yml`).
      Prima sezione: la testata con immagine di sfondo, tipologia, titolo, data, sottotitolo. Seconda
      sezione: tag, testo, allegati, collegamenti («Collegamenti» / «Links», testo tagliato a 80
      caratteri), galleria. Il blocco «Riferimenti» non c'è finché non arriva il plugin.

**Motore**
- [ ] Pagine, post e CPT hanno ciascuno la propria parte nel motore; le funzioni comuni (media,
      link, testo, WPML, log) restano condivise.
- [ ] L'elenco dei mancanti (`wpf_sync_mancanti`) registra la provenienza di ogni voce e non è più
      legato alle sole chiavi di `config/pagine.json`; `--contenuti-mancanti` ritenta anche le news.
- [ ] `docs/howto/ricreare-wordpress-da-zero.md` aggiornato con i passi e gli avvisi delle news, e
      ciclo completo rifatto in locale.

## Rischi

- **Precedenza fra pagine e post sotto `/news/`.** Con i post a `/news/%postname%/`, WordPress cerca un
  post prima di una pagina figlia, e le schede darebbero 404. Si mitiga con la regola di rewrite e
  con il controllo sugli slug in conflitto; va provato anche sul sito inglese (`/en/news/…`).
- **Cambiare la struttura dei permalink** (`config/sito.json`, da `/%postname%/` a
  `/news/%postname%/`) tocca tutto il sito: verificato il 27/10 che le pagine di oc:8758 e i menu
  non cambiano indirizzo. La review ha trovato i tracciati di `wp-geohub` e `tipologia_da_sapere` finiti
  sotto `/news/`: risolto con `with_front` falso per tutto ciò che non è nativo.
- **Slug con caratteri accentati** (es. la news Co.R.E.M., `…réseau…`): WordPress li normalizza in
  modo diverso da Drupal. Risolto: lo script li normalizza come WordPress e il child porta il vecchio
  indirizzo alla news con un 301.
- **WPML e i post da WP-CLI**: le trappole note di oc:8757-8758 (lingua corrente, link riscritti,
  cancellazioni) valgono anche per post e termini. Il codice che li legge o li scrive va dentro
  `wpf_wpml_tutte_le_lingue()`.
- **Allegati in WordPress, forse da spostare su Laravel.** Giuseppe ha sollevato il dubbio
  (27/10): se gli allegati servono anche sullo shard, devono vivere lì e WordPress li legge, per
  non dividere l'informazione. Per ora si importano nella libreria media; va chiesto ad Alessio. Se
  la risposta è Laravel, l'import degli allegati cambia: deciderlo presto costa poco, fra due mesi
  molto.
- **I file dei media arrivano dal sito Drupal pubblico.** La copia di UAT contiene solo il
  database: foto e allegati si scaricano da `www.sardegnasentieri.it` (verificato il 27/10, su UAT
  non c'è nessuna copia di `sites/default/files`). Spento Drupal, una ricostruzione di WordPress da
  zero resterebbe senza media. Prima dello spegnimento va chiesta e copiata sul server la cartella
  dei file, e `DRUPAL_FILES_URL` va puntato lì.
- **Reload della copia Drupal alle 10:00 su UAT**: le prove su UAT vanno fatte lontano da quell'ora.

## Out of scope

- La scheda Eventi e gli eventi, i contenuti «Da sapere» e le carte: oc:8760 (le informazioni da
  passare sono in `notes.md`).
- Il vocabolario `zona_geografica`: si fa in oc:8760, insieme alle carte.
- Il blocco «Riferimenti» della pagina della news (il dato si salva, non si mostra).
- Il plugin WordPress che legge lo shard: fork di `wp-geohub` in un repo nuovo (`wp-package`, nome da
  confermare), con le personalizzazioni per shard; per Forestas mai più `wp-geohub`.
- Le API dello shard pensate per WordPress (prefisso `wp`) e l'import degli enti dalla copia SQL di
  Drupal al posto dello scraping del JSON: ticket da creare, con overview assegnata a Giuseppe.
- Lo shortcode che mostra e fa selezionare i riferimenti, generico rispetto al modello (enti,
  sentieri, itinerari, altri).
- La pagina `/enti` e le schede degli enti.

## Moduli toccati

- `docker/scripts/config/`:
  - `contenuti-lib.php` (nuovo): parte comune a pagine e news, estratta da `pagine-lib.php`;
  - `pagine.php` e `pagine-lib.php`: pagine figlie, schede, giro delle news, elenco dei mancanti con
    la provenienza, regole degli indirizzi;
  - `news-lib.php` (nuovo): lettura, termini, controlli sulla copia, scrittura delle news;
  - `drupal.php`: `DRUPAL_FILES_URL`, lettura per bundle con le tabelle in memoria, query delle news,
    dei termini e dei riferimenti;
  - `media.php`: nome del file senza spazi in fondo;
  - `link.php`: indirizzo del sito Drupal in una costante (`WPF_SITO_DRUPAL`).
- `config/`:
  - `pagine.json`: News a schede, scheda Attività, pagine d'elenco;
  - `post.json`: template di Impreza della news, della pagina a schede e della pagina d'elenco;
  - `sito.json`: permalink;
  - `wpml.json`: post e tassonomie traducibili con ripiego, `tipologia_da_sapere`.
- `bin/wordpress-config.sh`, `cron/allinea.crontab`: testo d'aiuto, conteggi, `STATO` di `allinea`.
- `.env-example`: `DRUPAL_FILES_URL`.
- `themes/forestas-child/`:
  - `functions.php`;
  - `inc/news.php` (nuovo): tassonomia `tipologia_da_sapere`, `with_front` falso per i tipi non
    nativi, regole delle schede, redirect, pagina della news, schede, card, griglie, collegamenti, voce
    NEWS attiva;
  - `inc/pagine-statiche.php`: percorso con le pagine madri, testata della news, «Condividi», testo
    importato in una funzione comune a pagine e news;
  - `inc/pagine-statiche-admin.php` e `assets/js/riquadro-pagina.js`: riquadro anche sulle news;
  - `assets/js/schede.js`, `style.css`.
- `docs/`: questa feature, `README.md`, `howto/ricreare-wordpress-da-zero.md`,
  `howto/cron-sincronizzazione-pagine.md`, le pagine di conoscenza 8758 e 8759, le indicazioni del
  wireframe (oc:8783) e il `CLAUDE.md` del repo.
