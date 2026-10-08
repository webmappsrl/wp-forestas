# Inizializzazione di WordPress

## Come funziona oggi

L'entrypoint del container lancia `docker/scripts/init-wordpress.sh` prima di Apache, a ogni
avvio. Cosa controlla e in che ordine è descritto nel [README](../../README.md#cosa-fa-linizializzazione);
qui conta come si comporta e perché.

- **Ogni passo controlla prima di agire** e fa solo ciò che manca: un riavvio non tocca
  un'installazione esistente, e un pezzo mancante (uno zip di Impreza aggiunto dopo, un plugin non
  scaricato) viene rimesso al riavvio successivo.
- **Il core non sta nell'immagine:** lo scarica WP-CLI nel volume `wordpress-<APP_NAME>`, che
  contiene l'intera cartella di WordPress (core, `wp-config.php`, plugin, temi, lingue, uploads).
  Ricostruire l'immagine o ricreare il container non perde nulla.
- **GitHub non raggiungibile:** il sito parte lo stesso con un avviso nei log; `wp-geohub` si scarica
  al riavvio successivo.
- **Zip di Impreza assente:** avviso nei log, tema di default. Il tema si riconosce dallo slug della
  cartella (`impreza`, senza distinzione di maiuscole). Uno zip la cui cartella radice non è
  `Impreza/` non si installa: il child, che dichiara `Template: Impreza`, resterebbe senza padre.
- **Passi aggiunti con oc:8717 non bloccanti:** plugin commerciali, licenze e configurazione
  scrivono un avviso se falliscono e il sito parte comunque. Licenze e plugin si ritentano a ogni
  riavvio; l'apply automatico solo su un sito installato dall'init, al massimo 3 volte.
- **Variabili mancanti:** lo script esce elencandole; Docker riavvia il container a intervalli
  crescenti finché il `.env` non c'è.
- **Dietro un proxy HTTPS** `wp-config.php` imposta `HTTPS=on` quando arriva
  `X-Forwarded-Proto: https`: senza, WordPress crede di essere in http e va in un ciclo di redirect.

Vincolo: `wp-geohub` non ha tag né release; si scarica a un commit fisso (`GEOHUB_REF` in
`init-wordpress.sh`), che si aggiorna a mano (oc:8717). Un sito già installato non lo riscarica.

## Perché così

- **Passi indipendenti invece di un solo controllo «WordPress installato?»** (oc:8711): con un
  controllo unico, un pezzo perso o aggiunto dopo l'installazione non tornerebbe mai.
- **Chiavi di sicurezza generate da `wp config create`** (oc:8711): non passano da nessun `.env`,
  quindi niente caratteri speciali da proteggere e nulla da incollare.
- **Plugin scaricato come tarball nella cartella `wm-package`** (oc:8711): lo zip che GitHub genera
  per un branch crea una cartella con il nome del branch, e il plugin vuole il nome `wm-package`.
- **Child theme montato dal compose e non copiato dallo script** (oc:8717): una modifica nel repo è
  subito visibile sul sito, e il codice del child ha una sola copia.
- **`chown` su tutti i file di WordPress, core compreso** (oc:8717): WordPress confronta il
  proprietario del proprio codice (`wp-admin/includes/file.php`) con quello dei file creati da
  Apache; se sono diversi non scrive direttamente e chiede le credenziali FTP per installare plugin
  e temi dal pannello. La cartella montata del child resta esclusa (trappola nel `CLAUDE.md`).
- **Configurazione di `config/` applicata da sola solo su un sito appena installato, al massimo 3
  volte** (oc:8717): un sito ricreato nasce configurato, ma un sito esistente (UAT alla prima messa in
  opera, la produzione) non viene mai riscritto senza anteprima, e un apply che non può riuscire, per
  esempio senza WPML, non riscrive la configurazione a ogni riavvio. Il segno
  `wp_forestas_config_applicata` si scrive solo a fine senza errori.
- **Stato dei plugin escluso da `config/`** (oc:8717): i segni di migrazione di WPML (`…_has_run`) e
  la modalità manutenzione di Impreza descrivono il sito da cui si esporta, non la configurazione.
  Versionati, un sito nuovo saltava migrazioni di WPML mai eseguite e nasceva in manutenzione.
- **WPML configurato con le sue API** (oc:8717): la configurazione è sparsa fra opzioni e tabelle
  proprie, e l'endpoint della schermata «Lingue» crea le lingue con paese (`en-us`) come le crea il
  pannello. Su un WordPress nuovo il risultato è identico a UAT (prova dell'08/10 nelle note di
  oc:8717).
- **Licenze solo fuori da `localhost`** (oc:8717): un locale con la site key di WPML si registrerebbe
  su wpml.org e si collegherebbe ai servizi cloud con l'identità di UAT.
- **Aggiornamenti di Impreza e WPML dal pannello di UAT, poi `zip`** (oc:8717): le licenze sono
  attive solo lì, e in locale senza licenza il pannello non offre aggiornamenti.
- **Configurazione fatta in locale** (oc:8717): è lo stesso flusso del codice del child, con il repo
  come fonte di verità e UAT che la riceve.
- **Post e menu riconosciuti dal metadato `_wp_forestas_chiave` e non da titolo o slug** (oc:8717):
  gli id cambiano da un sito all'altro, nomi e slug si cambiano dal pannello e WPML modifica gli slug
  delle traduzioni. Il backup di `apply --conferma` è un export in sola lettura, per non assegnare
  chiavi fra l'anteprima e l'esecuzione.
- **Script di configurazione montati dal repo** (oc:8717): copiati solo nell'immagine, una loro
  modifica non valeva finché non si ricostruiva l'immagine, e su UAT `bin/` e `config/` nuovi potevano
  girare con script vecchi. `init-wordpress.sh` invece sta solo nell'immagine: una sua modifica vale
  dopo `scripts/wordpress-up.sh` di `forestas`, che la ricostruisce.
- **`.htaccess` scritto dall'init e dall'apply, non da WP-CLI** (oc:8717): `wp rewrite structure
  --hard` lo scrive solo se WP-CLI sa che `mod_rewrite` c'è, e da riga di comando non lo sa. Senza il
  file un sito ricreato risponde 404 su tutte le pagine tranne la home. Lo scrive
  `wpf_scrivi_htaccess()` con la funzione di WordPress che usa il pannello, dichiarando che
  `mod_rewrite` c'è (l'immagine lo abilita): l'init solo se il file manca, l'apply quando cambia i
  permalink. La funzione tocca solo la sezione «WordPress» del file.
- **wp-geohub a un commit fisso** (oc:8717): dal ramo `main` due siti ricreati in giorni diversi
  avevano codice diverso, contro l'obiettivo di un sito ricreabile.
- **Un apply alla volta, e l'automatico solo con le stesse versioni principali** (oc:8717): l'apply
  dell'avvio e uno lanciato dall'host nello stesso momento creerebbero post e menu doppi. All'avvio
  del container (passo 4c) si tolgono i blocchi presi prima dell'avvio, perché un apply gira dentro il
  container e muore con lui; quello di un apply lanciato durante l'avvio resta. Un `config/` di un
  Impreza o un WPML di versione principale diversa può avere uno schema diverso, che l'apply
  automatico applicherebbe senza che nessuno guardi le differenze.
- **Nomi, percorsi ed elenco dei plugin commerciali solo in `comune.php`** (oc:8717): bash e PHP non
  possono condividere codice, ma l'init li legge con `php -r` (`config_php`) e `bin/` con `php -r`
  dentro il container. Una copia in bash rinominata a metà avrebbe fatto ripartire l'apply automatico
  all'infinito, o mai.
- **Database che non risponde: l'init si ferma** (oc:8717): proseguendo, `core is-installed` fallisce
  come su un sito vuoto e il passo 4 tratterebbe un sito esistente come nuovo, con l'apply automatico.
- **Le pagine esistenti si aggiornano solo con `apply --pagine`** (oc:8717): la Home è in `config/`
  perché un sito ricreato deve averla, ma è anche contenuto della redazione; un apply fatto per un
  colore non deve riportarla alla versione del repo.
- **Voci di menu lette dal database, non da `wp_get_nav_menu_items()`** (oc:8717): da WP-CLI il suo
  filtro lascia a WPML aggiungere il selettore di lingua e togliere la «root page», che finirebbero
  in `config/` come voci vere o andrebbero perse.
- **WP-CLI a una versione fissa** (oc:8717): export e apply usano WP-CLI e sono provati con quella
  versione; prima l'immagine scaricava l'ultima.

## Dipendenze da codice interno di Impreza e WPML

Export e apply usano funzioni e classi che Impreza e WPML non documentano come API. Dopo un
aggiornamento dei due (README, «Aggiornamenti di Impreza e WPML») vanno controllati questi punti,
con un apply di prova su un WordPress usa e getta prima di quello su UAT:

- **Impreza / UpSolution Core:** `usof_save_options`, `usof_backup`, `us_config('theme-options')`
  (campi `upload`), `us_get_local_google_fonts_state`, `us_download_local_google_fonts`, `us_api` con
  `/envato_auth` (`licenza-impreza.php` riproduce `us_check_and_activate_theme`, che legge `$_GET` e
  fa un redirect e quindi non si può richiamare), il salvataggio dei metadati in `us_save_post`.
- **WPML:** `\WPML\LanguageEditor\PageData`, `Endpoint\SaveLanguages`, `Presets\CatalogueSyncRunner`,
  `\WPML\FP\Right`, `WPML_Package_Translation_Schema::run_update`, `WPML_Config::load_config_run`,
  `$sitepress->delete_element_translation` (vuole il trid come stringa), le tabelle
  `icl_strings`/`icl_string_translations` lette dall'export.

La versione principale diversa ferma l'apply automatico; un apply a mano avvisa e mostra le
differenze prima di `--conferma`.

## Come ci siamo arrivati

- **Script di copia da UAT al locale** (oc:8717, superata): progettato, poi sostituito da una copia
  una tantum con All-in-One WP Migration; la riproducibilità è passata alla configurazione nel repo.
- **Zip scaricati dagli account WPML e ThemeForest** (oc:8717, superata): puliti ma con versioni da
  allineare a mano a quelle di UAT; scelti invece gli zip ricavati dalle versioni installate.
- **Configurazione applicata a ogni avvio** (oc:8717, superata): su un sito esistente senza chiavi,
  come UAT alla prima messa in opera, avrebbe duplicato header, footer e Home senza anteprima (lo ha
  fatto, provato su un WordPress usa e getta); e a ogni riavvio avrebbe cancellato senza avviso le
  modifiche fatte dal pannello.
- **Post cercati con `post_type => any`** (oc:8717, superata): escludeva `us_header` e
  `us_page_block`; poi, con UpSolution Core spento, l'apply duplicava header e footer. Ora la ricerca
  va diretta sui metadati e un tipo non registrato si salta.
- **Plugin nella cartella `wp-geohub`** (oc:8711, superata): l'attivazione falliva perché il plugin
  cerca i suoi shortcode in `wp-content/plugins/wm-package/` (`functions/imports.php`).
- **`chown` solo su `wp-content`** (oc:8711, superata da oc:8717): il core scaricato da
  `wp --allow-root` restava di root e l'installazione dei plugin dal pannello falliva con
  `unable_to_connect_to_filesystem`.
- **Lingua letta da `wp option get WPLANG`** (oc:8711, superata): l'opzione non esiste subito dopo
  l'installazione e il comando fallisce.
