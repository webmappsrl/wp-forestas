> Ticket: oc:8717

# Notes — Attivazione del tema Impreza con licenza intestata a Forestas

> Gli artefatti di questa cartella sono stati scritti a lavoro concluso, ricostruendo la sessione:
> il lavoro è nato operativo (attivare il tema su UAT) ed è cresciuto strada facendo.

## Divergenze dal piano, task per task

### Task 3 functions.php del child

Il piano prevedeva di copiare `functions.php` dal child generato da Child Theme Configurator. Il file
portava i marcatori «AUTO GENERATED - Do not modify» del plugin, che contraddicono la regola di non
gestire il child con quel plugin, e un filtro per il `rtl.css` del padre che a un sito in italiano e
inglese non serve: è stato riscritto. Lo `style.css` del child lo carica UpSolution Core da sé
(handle `theme-style`), ma con la versione di Impreza (`?ver=9.4`): alzare la `Version` del child
non avrebbe cambiato l'URL e i browser avrebbero tenuto il CSS vecchio. `functions.php` contiene
quindi la guardia `ABSPATH` e un filtro che dà a `theme-style` la `Version` del child (verificato:
la home carica `forestas-child/style.css?ver=1.0.0`).

### Task 5 struttura per zip e configurazione

- **Script PHP montati dal compose** (`./docker/scripts/config:/usr/local/lib/wp-forestas:ro`), oltre
  alla copia nell'immagine prevista dal piano: una loro modifica vale subito (vedi le Decisioni).
  `init-wordpress.sh` invece resta solo nell'immagine.
- **`docker/plugins/commerciali.txt`**, versionato con un'eccezione in `.gitignore`: l'elenco dei
  plugin commerciali in un punto solo (vedi le Decisioni).

### Task 6 codice installato dall'init

- **Slug dei plugin commerciali letto dalla cartella dentro lo zip**, non dal nome del file come
  diceva il piano: uno zip scaricato da WPML si chiama `sitepress-multilingual-cms.5.1.0.zip`.
  `slug_zip` prende la prima cartella, saltando i file in radice e la `__MACOSX` aggiunta dal Finder;
  lo stesso controllo vale per la cartella `Impreza/` del tema. Uno zip illeggibile produce un avviso
  e si salta (`slug_zip … || true`: con `pipefail` l'init sarebbe uscito e il container sarebbe
  ripartito all'infinito).
- **UpSolution Core riattivato se è spento**, oltre che installato se manca: senza, Impreza non ha
  Theme Options né builder.
- **Passo 4b, `.htaccess`**, non previsto: `wp rewrite structure --hard` da WP-CLI non lo scrive
  (WP-CLI non sa che `mod_rewrite` c'è), e su un sito ricreato tutte le pagine tranne la home
  rispondevano 404. L'init lo crea solo se manca, con `wpf_scrivi_htaccess()`: la funzione di
  WordPress che usa il pannello (`save_mod_rewrite_rules`), dichiarando che `mod_rewrite` c'è.
- **wp-geohub a un commit fisso** (`GEOHUB_REF`) invece del ramo `main`: due siti ricreati in giorni
  diversi avrebbero avuto codice diverso. Per lo stesso motivo WP-CLI è a una versione fissa nel
  Dockerfile (`WP_CLI_VERSION`).
- **Database che non risponde: l'init si ferma** invece di proseguire: `core is-installed` sarebbe
  fallito come su un sito vuoto e il passo 4 avrebbe trattato un sito esistente come nuovo, con
  l'apply automatico.
- **Tempi massimi** per i passi che vanno in rete (`curl` di wp-geohub, licenza, apply automatico,
  con `timeout`): Apache parte solo alla fine dell'init.
- **Plugin commerciale la cui attivazione automatica fallisce**: l'init lo segna
  (`wp_forestas_plugin_da_attivare_<slug>`) e lo riattiva agli avvii successivi; uno spento dal
  pannello resta spento.
- **Blocco dell'apply al passo 4c**: si tolgono solo i blocchi presi prima dell'avvio dell'init.
- **Site key di WPML scritta con l'output di WP-CLI scartato**: il messaggio di `wp config set`
  riporta il valore della costante, che sarebbe finito nei log del container.

### Task 7 export della configurazione

- **7 file invece di 6**: si è aggiunto `versioni.json` (vedi le Decisioni).
- **`sito.json` comprende anche `page_for_posts`**: è un riferimento a una pagina come
  `page_on_front`, e senza un sito ricreato avrebbe perso la pagina degli articoli.
- **`post.json` più ampio di header, footer e Home**: contiene tutti i post del builder di Impreza
  (header, Page Block, Content Template, Grid Layout), anche in bozza, e le pagine a cui puntano
  Theme Options e impostazioni del sito. Un Page Block usato in una pagina è configurazione quanto il
  footer.
- **Segreti riconosciuti dalle parole del nome** e non da qualsiasi sottostringa (vedi le Decisioni).
- **Esclusi i metadati che i plugin ricalcolano salvando il post** (`WPF_META_CALCOLATI`):
  `_us_jsoncss_data`, `_us_faceted_filter_items` e `_us_schema_markup_faq` di UpSolution Core
  (`us_save_post`), `copied_media_ids` e `referenced_media_ids` di WPML Media (id di allegati). Il
  salvataggio fatto dall'apply li ricalcola.
- **Voci di menu con xfn e metadati**: le impostazioni di Impreza sulle voci (`us_mega_menu_settings`,
  `_menu_item_btn_style`, `_menu_item_remove_rows`) si scrivono dal pannello dei menu; senza, un sito
  ricreato perdeva mega menu e pulsanti, e un apply che ricreava le voci li cancellava.
- **Traduzioni dei testi delle opzioni** (String Translation, contesti `admin_texts_…`) in
  `wpml.json`, chiave `stringhe`: solo quelle in una lingua diversa da quella della stringa, perché
  WPML crea da sé «traduzioni» italiane dei formati di data. Lette dalle tabelle di String
  Translation, applicate con `icl_add_string_translation`.
- **Voci di menu lette dal database** (`wpf_voci_db`) e non con `wp_get_nav_menu_items()`, il cui
  filtro da WP-CLI lascia a WPML aggiungere il selettore di lingua e togliere la «root page».
- **Voci verso pagine non esportate** con destinazione «pagina» (tipo e percorso), non più un link:
  l'apply ritrova la pagina nella lingua del menu. Il link di riserva si calcola nella lingua della
  pagina (`wpf_link_relativo`), perché da WP-CLI WPML lo convertirebbe in quello della lingua
  predefinita.
- **Voci dei menu tradotti con la posizione della voce originale** (`originale`): è il legame di
  Menu Sync di WPML, che gli id non possono portare da un sito all'altro.
- **`_thumbnail_id` e i metadati `_icl_…`** di WPML non si esportano: id del sito d'origine.
- **Segreti**: il nome si spezza anche sulle maiuscole (`consumerSecret`) e comprende `pass`; un
  segreto con un elenco come valore si toglie per intero.
- **Valori con la forma di una chiave** (`WPF_FORME_SEGRETE`: Google, Stripe, GitHub, AWS, Slack,
  chiave privata) cercati in tutti i file prima di scriverli: se ce n'è uno l'export si ferma senza
  scrivere e senza stampare il valore. Copre ciò che il filtro sui nomi non vede, come una chiave
  dentro il contenuto di un post.
- **Terza forma dell'URL**, `@url_sito_urlenc`, per l'indirizzo codificato dentro un link
  (`http%3A%2F%2F…`).
- **Riferimenti nelle Theme Options riconosciuti per famiglia** (`header_…_id`, `footer_…_id`,
  `content_…`, `sidebar_…`, `titlebar_…`, `…_page`): un id di un servizio esterno come
  `facebook_app_id` resta un valore. Gli allegati della Libreria media non si esportano: le Theme
  Options di tipo `upload` si leggono dalla definizione di Impreza (`us_config('theme-options')`),
  in tutti i formati che Impreza salva (`12`, `12|full`, `12,13`); l'export avvisa, anche per i post
  del builder che usano immagini, e l'apply tiene il valore del sito.

### Task 8 apply della configurazione

- **Post, menu e opzioni in file propri** (`post.php`, `menu.php`, `opzioni.php`), come WPML:
  `apply.php` resta l'ordine dei passi e l'esito.
- **Pagine esistenti aggiornate solo con `--pagine`**: la Home è anche contenuto della redazione.
- **Voci di menu create prima di cancellare le vecchie**: se una non si crea il menu resta com'era.
  Le voci tradotte entrano nel gruppo della voce originale; cancellando le vecchie si toglie anche la
  loro riga di WPML (`wpf_cancella_post`), che da WP-CLI WPML non toglie da sé.
- **Google Fonts non scaricati: apply parziale**, così l'apply si rilancia; il ritratto mostra se i
  font sul sito sono aggiornati e se la home li carica.
- **Menu ritrovati dalla chiave e riallineati anche nello slug**: l'header di Impreza richiama il menu
  per slug (`"source":"main-menu"`). Se cambiano solo nome o slug le voci non si ricreano.
- **`source_language_code` solo per una traduzione**, con la lingua del suo originale: un originale
  non ha lingua di partenza. Anche una riga già esistente con la lingua di partenza sbagliata viene
  corretta.
- **Traduzioni dei testi delle opzioni dopo le Theme Options**: String Translation registra le
  stringhe originali leggendo i `wpml-config.xml` e le opzioni; l'apply lo fa fare con
  `WPML_Config::load_config_run()`, la procedura del wizard di WPML.
- **`.htaccess` scritto anche dall'apply** quando cambia i permalink.
- **Un apply alla volta**: la riga `wp_forestas_apply_in_corso` di `wp_options`, inserita con
  `INSERT IGNORE`, la ottiene un solo apply (`add_option` non basta: scrive con
  `ON DUPLICATE KEY UPDATE` e si fida della cache). Scade dopo 40 minuti (`WPF_APPLY_SCADENZA`, più lungo del tempo massimo dell'apply automatico), e l'init la toglie a ogni
  avvio (passo 4c): gli apply girano nel container e muoiono con lui, e un blocco rimasto da un
  `docker stop` avrebbe consumato i tentativi dell'apply automatico.
- **Segreti nei metadati delle voci di menu** confrontati come `@segreto` e conservati dal sito alla
  ricreazione delle voci, come per i post: altrimenti ogni apply avrebbe ricreato le voci perdendoli.
- **`WPML_Config::load_config_run()`** fa anche la pulizia degli admin texts non più configurati,
  come quando un amministratore apre il pannello dei temi: effetto accettato.
- **Apply automatico e versioni**: con `WPF_AUTOMATICO=1` (l'init) e una versione principale diversa
  da `versioni.json` l'apply non applica, perché nessuno guarda le differenze.
- **Un valore `null` di `sito.json` si salta**: indica un'opzione che sul sito dell'export non c'era.
- **Nelle differenze stampate, anche un segreto dentro un'impostazione composta** (WPML, theme_mods)
  diventa `@segreto`.

### Task 9 apply automatico

Il piano faceva partire l'apply a ogni avvio finché mancava `wp_forestas_config_applicata`. La review
finale ha mostrato che alla prima messa in opera su UAT, sito esistente, avrebbe duplicato header,
footer e Home: ora l'apply automatico parte solo su un sito installato dall'init, al massimo 3 volte
(dettagli nei «Bug trovati»).

Anche la licenza devia: il piano la attivava solo se mancava `us_license_secret`, il codice la
riattiva anche quando il segreto del `.env` è diverso da quello salvato, come la site key di WPML.
Con un segreto cambiato nel `.env` il sito resterebbe altrimenti con quello vecchio. Il confronto lo
fa l'init, che lancia `licenza-impreza.php` solo quando serve, con un tempo massimo.

### Task 10 comando sull'host

- **`WPF_CONTAINER`**: sulla stessa macchina più WordPress possono montare lo stesso child (è successo
  con l'ambiente di prova); in quel caso il comando chiede di scegliere.
- **Backup in sola lettura** (`WPF_SOLA_LETTURA=1`): il backup di `apply --conferma` non assegna
  chiavi al sito, così non cambia ciò che l'apply ricollega subito dopo.
- **L'export sostituisce solo i file prodotti**: un export con WPML spento non cancella `wpml.json`, e
  lo segnala.
- **`apply --pagine`**: aggiorna anche le pagine che esistono già (vedi il Task 8).
- **Elenco dei plugin commerciali letto da `comune.php` nel container** per `zip`, invece di una
  seconda lettura di `commerciali.txt` sull'host.
- **`apply --da DIR`**: applica un'altra cartella, per rimettere un backup senza toccare `config/`
  (montata in sola lettura, e nel checkout del server non va sporcata).
- **Il backup non passa dal controllo delle chiavi**: va in `backup/`, esclusa da git, e il controllo
  avrebbe solo impedito apply e ripristino. Un export fallito cancella la cartella temporanea nel
  container.

### Task 11 ritratto e prova completa

- La prova prevedeva `diff prima.txt dopo.txt` vuoto: dopo il reset del locale differiva l'impronta
  delle Theme Options, per `text_styles: []` che Impreza aggiunge a ogni installazione (vedi «Esito
  del reset del locale»). Dopo l'export successivo i ritratti coincidono.
- Il ritratto lascia fuori dall'impronta delle Theme Options anche lo stato di Impreza
  (`WPF_IMPREZA_STATO`) e i campi immagine (l'id di un allegato cambia da un sito all'altro), e mostra
  le traduzioni dei testi delle opzioni e la presenza di `.htaccess`.

### Vincolo www-data

Il piano chiedeva di eseguire come `www-data` i comandi WP-CLI che scrivono file. Il comando sull'host
(`bin/wordpress-config.sh`) lo fa; l'init invece gira come root e lancia licenza e apply come root
(`--allow-root`), perché è root a fare tutti gli altri passi. I file scritti (CSS di Impreza, Google
Fonts) passano a `www-data` al passo 9 dello stesso avvio.

Le altre scelte prese durante l'implementazione sono nelle «Decisioni» qui sotto.

## Bug trovati

- **Installazione dei plugin dal pannello impossibile in locale** (`unable_to_connect_to_filesystem`).
  `init-wordpress.sh` scarica il core come root e assegnava a `www-data` solo `wp-content`.
  `get_filesystem_method()` confronta il proprietario di `wp-admin/includes/file.php` (root) con
  quello di un file creato da Apache (`www-data`) e, diversi, sceglie `ftpsockets`. Da riga di
  comando il metodo risultava `direct`, per questo l'errore si è visto solo riproducendo la chiamata
  del pannello via HTTP. Su UAT il core era già di `www-data`. Corretto nel passo 9.
- **Pacchetto ThemeForest caricato al posto del tema**: «Nel tema manca il foglio di stile
  style.css». Il pacchetto contiene documentazione e altri zip; il tema è `Product/Impreza.zip`.
- **CSS di Impreza con URL `http://`** dopo il salvataggio delle Theme Options da WP-CLI:
  `$us_template_directory_uri` si calcola al caricamento di WordPress, quindi `HTTPS` va impostato
  con `--exec` prima del caricamento, non dentro `eval`.

- **Header e footer duplicati dall'apply** con UpSolution Core spento: la ricerca per
  chiave filtrava sui tipi di post registrati. Trovato nella prova di fallimento sul WordPress usa e
  getta; ora la ricerca va sui metadati e un tipo non registrato si salta senza scrivere il segno.
- **URL nel JSON del builder rimesso nella forma sbagliata**: un solo segnaposto per
  le due forme dell'URL (`http://` e `http:\/\/`). Ora `@url_sito` e `@url_sito_json`.
- **`… | head` e `echo … | grep -q` con `pipefail`**: il SIGPIPE faceva fallire il
  controllo della cartella di Impreza e il ritratto diceva «no» anche quando il testo c'era.
- **Errori SQL di WPML** al primo salvataggio di un post del builder su un sito nuovo
  (tabella `wp_icl_string_packages` creata da WPML solo alla prima visita del pannello): l'apply lancia
  prima `WPML_Package_Translation_Schema::run_update()`. Restava anche un messaggio su
  `wp_icl_mo_files_domains`, che veniva invece da `config/`: vedi la prima review qui sotto.
- **Google Fonts locali mai scaricati** su un sito ricreato: Impreza li scarica solo
  aprendo la pagina delle Theme Options; ora lo fa l'apply con `us_download_local_google_fonts()`.

- **Review finale (revisore indipendente)**: l'apply automatico partiva su qualsiasi
  sito senza il segno `wp_forestas_config_applicata`. Alla prima messa in opera su UAT, sito esistente
  e senza chiavi, avrebbe duplicato header, footer e Home senza anteprima né backup (riprodotto sul
  WordPress usa e getta: 2 header, 2 footer). Corretto: l'apply automatico parte solo su un sito
  installato dall'init (opzione `wp_forestas_config_da_applicare`, al massimo 3 tentativi), e l'apply
  manuale ricollega i post esistenti senza chiave con stesso tipo e slug. Nella stessa review: menu
  confrontati solo per titolo, nessun avviso per uno zip commerciale mancante, chiave di manutenzione
  stampata nei log; tutti corretti e verificati.

- **Review wm-review-ticket (08/10), due bloccanti corretti**: `config/` conteneva stato di UAT che
  non è configurazione.
  - I segni con cui WPML ricorda le migrazioni già eseguite (`…_has_run`, `…migration_complete…`,
    25 voci): applicati su un sito nuovo facevano saltare le migrazioni, e sul locale ricreato
    mancava `wp_icl_mo_files_domains`. Ora export e apply li tolgono (`wpf_togli_stato_wpml`); un
    WordPress nuovo da `config/` ha la tabella.
  - `maintenance_mode: 1`, forzata su UAT dalla licenza di sviluppo: un sito di produzione nuovo
    sarebbe nato in manutenzione. Ora non si esporta né si applica (`WPF_IMPREZA_STATO`).
  Il WordPress locale, ricreato prima della correzione, aveva ancora i segni: il reset successivo
  (08/10, vedi «Esito del reset del locale») lo ha ricreato con la tabella.

- **Seconda review wm-review-ticket (08/10), tre bloccanti corretti**:
  - `wpf_wpml_collega()` passava `trid => false` anche per un originale: WPML ne cancellava la riga e
    creava un gruppo nuovo, staccando l'originale dalle traduzioni a ogni apply che lo aggiornava
    (`WPML_Set_Language::set`). Ora un originale resta nel suo gruppo; verificato con una traduzione
    inglese dell'header ancora collegata dopo l'apply.
  - `default_categories` di WPML (id di termini) e gli altri valori che WPML calcola sul sito erano in
    `config/`: sul locale ricreato la categoria predefinita inglese puntava al termine del menu. Ora
    sono esclusi da export e apply.
  - «Duplica» di Impreza copia la chiave stabile: due post con la stessa chiave si sovrascrivevano
    all'apply. L'export ora rigenera la chiave del duplicato, con un avviso, e l'apply rifiuta due
    voci con la stessa chiave. Nella prova è emerso anche un doppio passaggio sull'originale
    raggiunto dalla sua traduzione, corretto.

- **Terza review wm-review-ticket (08/10), due bloccanti corretti**: `.htaccess` mancante su un sito
  ricreato (404 su `/en/` e `/wp-json/`) e uno zip illeggibile in `docker/plugins/` che fermava l'init
  (dettagli nel Task 6). Verificati sul locale (zip di prova rovinato: solo un avviso, container
  avviato; `/wp-json/` 200) e su un WordPress usa e getta installato da zero: `.htaccess` creato,
  apply «nessuna differenza», ritratto identico a quello del locale.

- **Quarta review wm-review-ticket (08/10), un bloccante corretto**: le impostazioni di Impreza sulle
  voci di menu (mega menu, pulsante) non venivano esportate, e l'apply che ricreava le voci le
  cancellava. Corretti nella stessa occasione i cleanup: traduzioni di String Translation, campi
  immagine letti da Impreza, metadati ricalcolati dai plugin, `.htaccess` dall'apply, lingua di
  partenza delle righe WPML esistenti, un apply alla volta, versioni nell'apply automatico,
  `apply --da`, forme di chiave cercate nei valori, wp-geohub a un commit fisso. L'apply delle
  traduzioni di String Translation, alla prima prova su un sito nuovo, non trovava le stringhe ancora
  registrate (corretto con `WPML_Config::load_config_run()`), e l'apply dei permalink non scriveva
  `.htaccess` perché WordPress teneva la struttura vecchia (corretto rileggendola).
  Verifica su un WordPress usa e getta: header e menu tradotti in inglese, mega menu, voce a pulsante,
  messaggio dei cookie tradotto e icona del sito; export, sito ricreato da zero, `apply --da` → tutto
  presente, `source_language_code` `it` sulle traduzioni e NULL sugli originali, secondo apply
  «nessuna differenza», ritratti uguali tranne l'icona (allegato, resta quella del sito). Provati
  anche: export fermato da una chiave Google finta in un Page Block (nessun file scritto, valore non
  stampato), secondo apply rifiutato mentre un altro è in corso, apply automatico rifiutato con
  Impreza 8 in `versioni.json`, `.htaccess` riscritto dall'apply dopo un cambio di permalink, zip con
  `__MACOSX/` e file in radice. Un revisore indipendente sulle correzioni non ha trovato bloccanti;
  i suoi cleanup (blocco non atomico, blocco rimasto dopo un `docker stop`, segreti nei metadati
  delle voci, backup fermato dal controllo delle chiavi, README e un commento) sono corretti.

- **Quinta review wm-review-ticket (08/10), un bloccante corretto**: le voci dei menu tradotti con
  Menu Sync di WPML perdevano il legame con le voci originali, all'export e a ogni ricreazione delle
  voci. Corretti anche i cleanup (pagine della redazione, voci verso pagine, selettore di lingua,
  segreti, init, plugin, rete, font, WP-CLI, struttura dell'apply, documentazione). Nella prova sono
  emersi due bug, corretti: il link di riserva di una pagina tradotta era quello della pagina
  italiana, e le voci cancellate lasciavano righe di traduzione orfane (WPML vuole il trid come
  stringa). Confutato `us_template_preview="8745"` nel footer: è l'id di un modello della libreria di
  UpSolution (`footer-templates.php`), non del sito d'origine.
  Verifica su un WordPress usa e getta: pagine «Chi siamo»/«About us» e menu inglese tradotto voce per
  voce come Menu Sync; export, sito ricreato, apply senza le pagine (avviso e link di riserva), pagine
  create, apply (voci ricollegate alle pagine), secondo apply «nessuna differenza», ritratti uguali;
  voce del menu italiano cambiata → voci italiane e inglesi ricreate e ricollegate, nessuna riga
  orfana nuova; Home modificata non riscritta (riscritta solo con `--pagine`); plugin riattivato dopo
  un'attivazione fallita e lasciato spento se spento a mano; blocco di un apply lanciato durante
  l'avvio conservato; MariaDB fermo → init fermo e ripartito senza reinstallare.

## Decisioni


- **Chiavi stabili `<post_type>-<post_name>`** (`us_header-header`, `us_page_block-footer`,
  `page-home`), menu `menu-<slug>` salvato come metadato del termine, traduzioni con `-<lingua>`: si
  ricavano senza un elenco a mano e non si scontrano fra tipi diversi. Una chiave assegnata non cambia
  più, anche se lo slug cambia; una chiave copiata da «Duplica» di Impreza viene rigenerata
  sull'export, con un avviso.
- **Script di configurazione montati dal compose**, oltre che copiati nell'immagine: una modifica vale
  subito, senza ricostruire l'immagine, e su UAT `bin/`, `config/` e script PHP hanno sempre la
  stessa versione. `init-wordpress.sh` invece sta solo nell'immagine e cambia dopo
  `scripts/wordpress-up.sh`; legge però nomi e percorsi da `comune.php` montato, quindi un rename lì
  vale anche per l'init vecchio.
- **Elenco dei plugin commerciali in `docker/plugins/commerciali.txt`**, versionato: lo leggono init,
  `bin/wordpress-config.sh zip` e il ritratto, così un plugin si aggiunge in un punto solo.
- **Costanti di `wp-config.php` che seguono il `.env`**: `DISALLOW_FILE_MODS` e
  `OTGS_INSTALLER_SITE_KEY_WPML` si scrivono quando il `.env` le chiede e si tolgono quando non le
  chiede più (o l'indirizzo è locale), così un ambiente non resta bloccato da un valore di prova.
- **Apply più severo sugli errori**: un JSON non valido in `config/`, un riferimento `@chiave:` che non
  trova il post o una voce di menu che non si crea fanno uscire l'apply con errore e senza scrivere il
  segno; un riferimento non risolto lascia il valore del sito invece di azzerarlo.
- **Posizioni dei menu e liste di WPML**: un `config/` senza posizioni non azzera quelle del sito; un
  elenco di WPML del repo (`hidden_languages`, `languages_order`) sostituisce quello del sito invece di
  essere unito voce per voce, che non lo accorcerebbe mai.
- **Plugin spenti**: UpSolution Core si riattiva sempre (senza, Impreza non ha Theme Options); un plugin
  commerciale spento si segnala nei log ma non si riattiva, perché può essere stato spento apposta. Lo
  slug di un plugin si legge dalla cartella dentro lo zip.
- **`config/versioni.json`**: versioni di Impreza, UpSolution Core e plugin commerciali dell'export;
  l'apply avvisa se il sito ne ha altre, perché opzioni di versioni diverse possono avere uno schema
  diverso.
- **Segreti riconosciuti dalle parole separate da `_` del nome** (`…_key`, `secret`, `token`,
  `password`, `api`) più le forme attaccate comuni (`apikey`, `licensekey`, `secretkey`,
  `accesstoken`), e non da qualsiasi sottostringa: «key» da solo prendeva opzioni come
  `h_keyboard_accessibility`. Ciò che il nome non rivela lo prende il controllo sui valori
  (`WPF_FORME_SEGRETE`); l'ultimo controllo resta `git diff config/` prima del commit.

- **Child theme montato dal compose, non copiato da `init-wordpress.sh`**: con il mount una
  modifica nel repo è subito visibile, e il codice del child ha una sola copia.
- **Child attivato solo al posto di Impreza o di un tema di default**: un tema scelto a mano dal
  pannello non viene sostituito, coerente con la regola che l'inizializzazione non distrugge
  un'installazione esistente.
- **Numerazione propria del child** (`1.0.0`): Child Theme Configurator aveva scritto
  `9.3.1.1791459801`, cioè la versione di Impreza più un timestamp; il legame con il padre lo fa
  solo `Template: Impreza`.
- **Colori, font e bottoni nelle Theme Options di Impreza, non nel child**: Impreza genera da quelle
  opzioni le variabili CSS usate da tutti i suoi elementi; ridefinirle nel child farebbe divergere
  pannello e sito. Nel child solo le variabili senza un posto in Impreza.
- **Stile allineato al mockup «Canvas Fase 2» e non al Drupal attuale**: il mockup sostituisce
  volutamente l'identità del sito attuale (palette dal logotipo, riunione del 12/08/2026; colori dei
  tipi trasmessi dal committente il 22/09/2026). Una prima corrispondenza fatta solo sui token del
  design system aveva titoli blu, barra alta dell'header blu-velo e footer invertito (in Impreza il
  *subfooter* è la parte principale, il *footer* la barra finale): corretta sul mockup.
- **Screenshot del child fuori dal repo**: è grafica di Impreza, copiata da Child Theme
  Configurator; il repo è pubblico.
- **Copia di UAT in locale con All-in-One WP Migration** invece di uno script: il dev ha scelto la
  copia una tantum. Dopo l'import sono state cancellate in locale le opzioni di licenza e di
  collegamento all'Advanced Translation Editor (`us_license_secret`, `us_license_dev_activated`,
  `wp_installer_settings`, `WPML_SITE_ID:ate`, `WPML_TM_AMS`, `wpml_tm_ams_site_name`), perché il
  locale non si presenti come UAT a UpSolution e WPML.
- **Temi di default eliminati su UAT** su richiesta del dev; tornano con una nuova installazione del
  core.
- Tag del ticket: il dev ha scelto di non associarne altri.
- **Stima** scritta su Orchestrator: 14,3h (Misurato 4,3h + Stimato 10,0h). La quota misurata
  comprende la sessione dell'08/10 dalle 10:04 UTC (3,8h) e le 0,48h già registrate; la quota stimata
  è di `wm-estimate` per il lavoro ancora da fare in quel momento, con confidenza bassa.
- **Rischi di WPML e Impreza tolti con una prova prima del piano** (08/10): WPML configurato su un
  WordPress usa e getta (`APP_NAME=prova`, poi rimosso) con le sue API, risultato identico a UAT;
  Impreza verificato con una sola chiamata di lettura all'API di UpSolution da UAT, senza modificare
  opzioni. Su un sito nuovo il catalogo dei preset di WPML è incompleto (`missing_preset`) finché non
  lo si sincronizza; l'endpoint `SaveLanguages` vuole il `presetCode` per le lingue da aggiungere.
- **Chiavi di licenza nel `.env` locale**, prese dal database di UAT su richiesta del dev; lo script
  non le applica in locale.

- **Prove distruttive solo in locale**: un WordPress usa e getta (`APP_NAME=prova`,
  porta 8091, rimosso alla fine) e, con l'ok del dev, il reset del WordPress locale. Su UAT solo
  letture (la verifica della licenza Impreza).
- **Dati generati esclusi dall'export WPML**: `st.was_frontend_visited_key` e
  `custom_fields_translation`/`custom_term_fields_translation`, che WPML calcola da sé.
- **Nomi e percorsi solo in `comune.php`**: le opzioni `wp_forestas_*`, i percorsi montati, la lettura
  di `commerciali.txt` e il criterio dell'indirizzo locale stanno solo lì; `init-wordpress.sh` li
  legge con `php -r` (`config_php`), `bin/wordpress-config.sh` con `php -r` nel container. Resta
  ripetuto in `bin/` solo l'`--exec` per HTTPS, che si costruisce sull'host prima di entrare nel
  container.
- **Esito del reset del locale (08/10)**: ritratto prima e dopo identico tranne
  l'impronta delle Theme Options, dovuta a `text_styles: []` che Impreza aggiunge a ogni
  installazione. Gli export successivi l'hanno portata in `config/` (è un'opzione vera, vuota), quindi
  un sito ricreato da questo `config/` ha la stessa impronta.
- Docker Desktop si è fermato durante una prova, con il Mac molto carico; riavviato dal dev, nessun
  dato perso.

## Follow-up

- **Zip su UAT**: sull'host di UAT c'è ancora `impreza.zip` 9.3.1 e mancano gli zip di WPML; si sostituiscono alla
  messa in opera (README, «Messa in opera su UAT»).
- **Azzeramento giornaliero selettivo**: il ciclo giornaliero deve toccare solo i contenuti
  importati da Drupal (riconoscibili da un id Drupal salvato sul post) e lasciare intatta la
  configurazione; un import che aggiorna invece di cancellare e ricreare tiene stabili id, URL e
  menu. Da decidere nel ticket dell'import.
- **`--tipo-cammino`**: nel testo dato dall'agente del design system il valore era troncato
  (`#1A41`); il valore giusto, `#1A416F`, viene dal mockup.
