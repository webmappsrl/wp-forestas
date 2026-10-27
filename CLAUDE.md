# wp-forestas — CLAUDE.md

Ambiente Docker del WordPress dello shard Forestas: immagine PHP 8.3 + Apache con WP-CLI, MariaDB
11.4, inizializzazione a passi. È un submodule di `forestas`, che include `compose.yml` nei suoi
compose: come entra nello shard è spiegato in `forestas/docs/knowledge/wordpress-nello-shard.md`.
Uso e avvio: [README.md](README.md).

## Regole che precedono tutte le altre

**Il repo è pubblico.** Nessun valore reale in un file tracciato: password, chiavi, URL interni
stanno solo in `.env` (escluso da git). `.env-example` contiene solo segnaposto.

**Impreza e WPML sono commerciali e non si ridistribuiscono.** Gli zip stanno in `docker/themes/` e
`docker/plugins/`, escluse da git: non vanno mai committati, nemmeno per una prova. Lo stesso vale
per le chiavi di licenza, che stanno solo nel `.env`.

**Non eseguire mai `git commit` senza istruzione esplicita dell'utente.** Vale anche per i
subagent.

## Regole del repo

- **Fonte di design: il sito attuale, sardegnasentieri.it (Drupal).** Il capitolato chiede la stessa
  UI, salvo modifiche concordate col cliente: per ogni lavoro su aspetto e comportamento del sito
  (header, footer, pagine, colori, tipografia, Theme Options) il riferimento è il frontend Drupal,
  non un prototipo. La copia del suo repository sta sul server UAT, `/root/sardegnasentieri.zip`
  (`scp uat.forestas:/root/sardegnasentieri.zip .`, MD5 `23215ab201b6d7bf994378222c523a69`; si
  estrae con `unzip sardegnasentieri.zip -x '__MACOSX/*'`). Il tema è in
  `docroot/themes/custom/sardegnasentieri`: `scss/` per misure e colori, `js/` per i comportamenti,
  `fonts/`. I valori si confrontano anche con il sito in produzione, misurando nel browser (oc:8757).
- Documentazione, commenti e messaggi di commit in italiano; i termini tecnici restano in inglese.
- `compose.yml` non deve mai far fallire il parsing del compose di `forestas`: niente
  `${VAR:?…}`, `env_file` con `required: false`. Le variabili mancanti le segnala lo script.
- `init-wordpress.sh` controlla ogni passo prima di eseguirlo e non distrugge mai
  un'installazione esistente: in produzione i dati di WordPress saranno permanenti.
- Dopo un merge su `develop`/`main`, in `forestas` il puntatore del submodule si aggiorna con
  l'hash letto da `git rev-parse`, mai scritto a mano.
- **La sequenza dal reset al sito completo è in `docs/howto/ricreare-wordpress-da-zero.md`:** ogni
  lavoro che cambia installazione, `config/`, `bin/wordpress-config.sh` o la sincronizzazione da
  Drupal aggiorna quella pagina nello stesso lavoro (passi, avvisi attesi, problemi incontrati), e ne
  aggiorna la data dell'ultima verifica se ha rifatto il ciclo (oc:8758).

## Trappole

- La cartella del plugin `wp-geohub` deve chiamarsi `wm-package`: il plugin ha il percorso
  `wp-content/plugins/wm-package/shortcodes` scritto nel codice e con un altro nome l'attivazione
  lancia un'eccezione (oc:8711)
- MariaDB crea utente e database solo al primo avvio su un volume vuoto: avviata senza password
  inizializzerebbe l'utente senza password e ignorerebbe il `.env` creato dopo. Per questo esce se
  manca `WP_DB_PASSWORD` — non togliere quel controllo (oc:8711)
- I valori del `.env` (URL, amministratore) valgono solo alla prima installazione: dopo si
  cambiano dal pannello o con WP-CLI, non dal `.env` (oc:8711)
- `blog_public = 0` viene impostato all'installazione e non più toccato: al lancio in produzione va
  tolto a mano (Impostazioni → Lettura) (oc:8711)
- Il child theme e la configurazione del sito si cambiano in locale, mai dal pannello di UAT né con
  Child Theme Configurator: lì finirebbero nel checkout del server, fuori da git (README, «Lavorare
  sul child theme» e «Configurazione versionata») (oc:8717)
- `init-wordpress.sh` sta nell'immagine, non è montato come gli script di `docker/scripts/config/`:
  una sua modifica gira solo dopo `scripts/wordpress-up.sh` di `forestas`, che ricostruisce
  l'immagine; un semplice `docker restart` esegue ancora quello vecchio (oc:8717)
- `init-wordpress.sh` legge nomi e percorsi da `docker/scripts/config/costanti.php`, che è montato dal
  repo (gli script PHP non sono più copiati nell'immagine): rinominare una costante o una funzione di
  `costanti.php` che l'init usa (`config_php`) vale anche per un init vecchio ancora nell'immagine.
  `costanti.php` resta piccolo e senza dipendenze; se non si carica l'init usa i valori di riserva e
  WordPress parte senza `.htaccess`, licenza e apply: un AVVISO nei log, non un errore (oc:8717)
- `us_header`, `us_page_block`, `us_content_template` e `us_grid_layout` (header, footer e altri
  oggetti dei builder di Impreza) si modificano solo in locale e poi si esportano: l'apply li
  sovrascrive sempre (`WPF_TIPI_BUILDER`), anche senza `--pagine`, e una modifica fatta dal pannello
  di UAT o produzione va persa al primo apply (oc:8757)
- La soglia tablet/desktop è una sola ma sta in tre posti: `tablets_breakpoint` (1024px) in
  `config/impreza.json`, `mobile_width` (1025px) del menu nei due header IT/EN in `config/post.json`,
  le media query `max-width: 1024px` / `min-width: 1025px` del child. Cambiandone una sola, fra le due
  soglie l'header desktop convive col pannello mobile o viceversa. Il `breakpoint: 900px` che si legge
  nel JSON degli header è ignorato (`custom_breakpoint` 0): non è quello da cambiare (oc:8757)
- Header e footer esistono in due copie, italiana e inglese (`config/post.json`): URL social, contatti
  e opzioni dell'header vanno cambiati in tutte e due, e il pannello mobile inglese legge i social
  dall'header inglese. Dopo l'export, `git diff config/` mostra se le copie sono allineate (knowledge
  8757, «Perché così») (oc:8757)
- Nell'header, i colori al passaggio del mouse del child perdono contro `.no-touch .l-subheader.at_middle
  a:hover` di Impreza, che dà a ogni link e pulsante della riga centrale il blu dei link: serve
  `.l-subheader` nel selettore. Succede solo col mouse, quindi su un telefono non si vede; il pulsante
  «chiudi» era sceso a 1,66:1 (oc:8757)
- Header e footer sono identici a sardegnasentieri.it, salvo le differenze concordate nel wireframe di
  oc:8783 (elenco nella knowledge 8757): un ritocco si confronta con la produzione e con quel
  wireframe, non con il prototipo «Canvas Fase 2», e ogni altra differenza va concordata col cliente
  (oc:8757)
- Su desktop la riga compatta che resta in alto scorrendo è del child (`header-compatto.js`), non lo
  sticky di Impreza: accendere `sticky` nelle opzioni dell'header riserverebbe in cima lo spazio
  calcolato dalle altezze delle opzioni, non quello vero, e il contenuto salterebbe. Una voce del menu
  principale più lunga va rimisurata da 1025px: deve stare su una riga accanto al logo (oc:8757)
- L'header è sticky fino a 1024px (finestra alta almeno 480px, non sui dispositivi con lo schermo fra
  576 e 1024px in orizzontale, come sul sito attuale) solo perché `.l-canvas` ha `overflow: clip` e il
  blocco dello scorrimento a menu aperto sta sul body: un `overflow: hidden` su `.l-canvas` o su
  `<html>` ferma lo sticky, e aprire il menu dopo uno scorrimento riporta la pagina in cima (oc:8757)
- Per avere le misure del sito attuale il child batte regole di Impreza che non si vedono dal
  pannello: `--hwrapper-gap` scritto in linea sul gruppo «Seguici su» del footer (si batte col margine
  del testo), `background: none !important` sul pulsante di ricerca (fondo e lente stanno sull'icona),
  `text-decoration: none !important` sui social, layout verticale responsive dei menu aggiuntivi
  (`responsive_width="0px"` nello shortcode del footer). Toccando quegli elementi si controlla il
  risultato nel browser, non solo nel pannello (oc:8757)
- Nelle Theme Options le famiglie dei font sono scritte con la virgola (`"Montserrat", sans-serif`)
  perché Impreza non le scarichi da Google: i font sono nel child. Non sono fra le scelte del pannello,
  e salvare la tipografia dalle Theme Options può sostituirle; «Store Google Fonts locally» resta
  spento, se no l'apply segnala sempre una differenza (oc:8757)
- L'apply automatico vale solo nei 3 giorni dopo l'installazione (`WPF_APPLY_FINESTRA`) e aspetta,
  senza consumare tentativi, che tema e plugin di `config/versioni.json` siano installati: dopo, un
  sito cambiato dal pannello non viene più sovrascritto senza anteprima (oc:8717)
- Export, apply e ritratto girano come l'utente `WP_ADMIN_USER` del `.env` (`--user`): se
  quell'utente si rinomina o si cancella dal pannello, falliscono finché il `.env` non lo segue (oc:8717)
- L'apply automatico si ferma se `config/versioni.json` ha una versione di Impreza o di un plugin
  diversa da quella del sito, anche solo minore («9.4» contro «9.5»): dopo un aggiornamento degli
  zip va rifatto l'export, o si applica a mano guardando le differenze (oc:8717)
- `root_page` di WPML non sta in `config/` (è un id di pagina): su un sito ricreato si reimposta a
  mano. L'immagine Docker si chiama `wm-wordpress-<APP_NAME>`: un altro shard sulla stessa macchina
  non la sovrascrive (oc:8717)
- `init-wordpress.sh` assegna a `www-data` tutti i file di WordPress tranne il child montato: un
  `chown` sulla cartella montata cambierebbe il proprietario dei file del repo sull'host (oc:8717)
- `git diff config/` prima di ogni commit: l'export toglie i segreti per nome e si ferma sui valori
  con la forma di una chiave nota, ma un segreto con un nome insolito e una forma qualsiasi sfugge,
  in un repo pubblico (oc:8717)
- WPML si configura solo con le sue API (`CatalogueSyncRunner`, `SaveLanguages` con `presetCode`,
  `save_settings`): su un sito nuovo, senza sincronizzare il catalogo, l'API risponde
  `missing_preset` (oc:8717)
- I filtri per lingua di WPML si accendono solo con il primo passo del wizard completato
  (`existing_content_language_verified`): l'apply lo completa, così il locale si comporta come UAT.
  Con i filtri attivi, da WP-CLI la lingua corrente è quella predefinita: `get_terms()` non vede i
  menu (e i termini) tradotti e `get_term()` ne converte l'id in quello dell'originale. Il codice
  degli script che legge o scrive menu tradotti va dentro `wpf_wpml_tutte_le_lingue()`; nel child si
  confrontano i menu nella lingua predefinita (oc:8757)
- Da WP-CLI WPML non toglie la riga di traduzione di un post cancellato (lo fa solo dal pannello o
  dal sito) e converte il link di una traduzione in quello della lingua predefinita: negli script di
  `docker/scripts/config/` si cancella con `wpf_wpml_cancella_post` e si prende il link con
  `wpf_link_relativo` (oc:8717)
- Dopo un aggiornamento di Impreza o WPML dal pannello di UAT: `bin/wordpress-config.sh zip`, poi
  zip in locale e nella cartella condivisa (README), e un controllo delle funzioni interne che export
  e apply usano (`docs/knowledge/inizializzazione-wordpress.md`, «Dipendenze») (oc:8717)
- In `bin/` e negli script con `set -o pipefail`, niente `… | head` o `echo … | grep -q` per
  decidere: il SIGPIPE fa fallire la pipeline anche quando il testo c'è (oc:8717)
- `wp option get WPLANG` fallisce su un'installazione appena fatta: la lingua attiva si legge con
  `wp language core list --status=active` (oc:8711)
- Le pagine con `_wpf_pagina` e le news con `_drupal_nid` le scrive solo `bin/wordpress-config.sh
  pagine`. Per le pagine `esporta` e `applica` le saltano (le news, che sono post normali, non le
  toccano proprio); con `DRUPAL_SYNC=on` una modifica fatta dal pannello (titolo, testo, riassunto,
  stato, immagine, allegati, galleria, e per le news anche data, collegamenti, categorie, tag e «In
  evidenza») si perde alla sincronizzazione successiva (oc:8758, oc:8759)
- Il testo di una pagina sincronizzata si mostra senza `the_content` finché coincide con l'impronta
  `_wpf_testo_drupal`: un filtro aggiunto a `the_content` non cambia quelle pagine, e passa solo dopo
  una modifica fatta in WordPress (oc:8758)
- `DRUPAL_DB_USER` e `DRUPAL_DB_PASSWORD` non hanno valori predefiniti (repo pubblico): senza nel
  `.env` il comando `pagine` si ferma; su un server nuovo vanno aggiunte prima del primo giro, poi
  `scripts/wordpress-up.sh` di `forestas`, perché il container legge il `.env` solo all'avvio (oc:8758)
- Le traduzioni inglesi nostre (`config/traduzioni/en/`) portano l'impronta del testo italiano da cui
  sono state fatte (`sorgente`): se Drupal cambia l'italiano il log dà un AVVISO con l'impronta nuova,
  e la traduzione va rifatta prima di aggiornare `sorgente`; se Drupal aggiunge la sua traduzione,
  vince quella (oc:8758)
- Il cron usa `bin/wordpress-config.sh allinea`, con la riga versionata in `cron/allinea.crontab`
  (lì anche lo stato: oggi non installata su nessun server, da aggiornare quando la si installa). Applica
  da solo solo le differenze dei menu: un rilascio di `config/` (header, footer, Theme Options…) va
  applicato a mano con `applica --conferma`, altrimenti ogni giro chiude con `STATO non allineato` (oc:8758)
- La sincronizzazione non va lanciata durante il reload giornaliero della copia Drupal su UAT (alle
  10:00, crontab di `root`, circa un quarto d'ora: il 20/10 è finito alle 10:14): il controllo di integrità la ferma senza scrivere, ma il giro
  va perso; il cron va messo lontano da quell'ora (`docs/howto/cron-sincronizzazione-pagine.md`) (oc:8758)
- Una scheda figlia della pagina News aggiunta o rinominata dal pannello dà 404 finché non gira
  `pagine --conferma` o `wp rewrite flush`: le regole di rewrite (`inc/news.php`) si rigenerano solo
  quando cambia l'elenco delle schede o `FORESTAS_REGOLE_VERSIONE` (giro di `pagine`, riga `REGOLE`, o il
  pannello): chi cambia le regole del child alza quella costante (`inc/news.php`). Per la stessa precedenza una
  news con lo slug di una scheda non si raggiunge, e lo script lo segnala con un AVVISO. Il perché sta in
  `docs/knowledge/8759-import-delle-news-da-drupal.md` (oc:8759)

## Conoscenza

| Argomento | Cosa copre | Pagina |
|---|---|---|
| Inizializzazione di WordPress | perché lo script lavora a passi, perché il core non sta nell'immagine, cosa succede senza GitHub o senza Impreza | [docs/knowledge/inizializzazione-wordpress.md](docs/knowledge/inizializzazione-wordpress.md) |
| Header e footer del sito | identici al sito attuale, oggetti di Impreza scritti in codice e copiati per lingua, shortcode e font del child, menu mobile, riga compatta su desktop, loghi del footer, accessibilità | [docs/knowledge/8757-header-e-footer-di-wordpress-forestas-secondo-il-mockup.md](docs/knowledge/8757-header-e-footer-di-wordpress-forestas-secondo-il-mockup.md) |
| Pagine editoriali da Drupal | perimetro, sincronizzazione dalla copia Drupal, media, link, traduzioni nostre, template e shortcode del child; il cron in [docs/howto/cron-sincronizzazione-pagine.md](docs/howto/cron-sincronizzazione-pagine.md) | [docs/knowledge/8758-import-delle-pagine-editoriali-da-drupal-e-ricostruzione-con-impreza-di-quelle-fatte-con-il-layout-builder.md](docs/knowledge/8758-import-delle-pagine-editoriali-da-drupal-e-ricostruzione-con-impreza-di-quelle-fatte-con-il-layout-builder.md) |
| News da Drupal | news come post della categoria News, termini con `_drupal_chiave`, pagina News a schede, editor e protezioni sulla copia | [docs/knowledge/8759-import-delle-news-da-drupal.md](docs/knowledge/8759-import-delle-news-da-drupal.md) |
