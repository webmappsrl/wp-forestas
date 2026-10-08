> Ticket: oc:8717

# Notes — Attivazione del tema Impreza con licenza intestata a Forestas

> Gli artefatti di questa cartella sono stati scritti a lavoro concluso, ricostruendo la sessione:
> il lavoro è nato operativo (attivare il tema su UAT) ed è cresciuto strada facendo.

## Divergenze dal piano, task per task

### Task 3 functions.php del child

Il piano prevedeva di copiare `functions.php` dal child generato da Child Theme Configurator. Il file
portava i marcatori «AUTO GENERATED - Do not modify» del plugin, che contraddicono la regola di non
gestire il child con quel plugin, e un filtro per il `rtl.css` del padre che a un sito in italiano e
inglese non serve: è stato riscritto con la sola guardia `ABSPATH`. Lo `style.css` del child lo
carica Impreza da sé (verificato: la home carica `forestas-child/style.css?ver=1.0.0`).

### Task 9 apply automatico

Il piano faceva partire l'apply a ogni avvio finché mancava `wp_forestas_config_applicata`. La review
finale ha mostrato che alla prima messa in opera su UAT, sito esistente, avrebbe duplicato header,
footer e Home: ora l'apply automatico parte solo su un sito installato dall'init, al massimo 3 volte
(dettagli nei «Bug trovati»).

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
  prima `WPML_Package_Translation_Schema::run_update()`. Resta un solo messaggio, una volta, su
  `wp_icl_mo_files_domains` al primo caricamento: non si ripete e non viene dal nostro codice.
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
  Il WordPress locale, ricreato prima della correzione, ha ancora i segni e non ha
  `wp_icl_mo_files_domains`: si sistema con un nuovo reset.

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

## Decisioni

- **Chiavi stabili `<post_type>-<post_name>`** (`us_header-header`, `us_page_block-footer`,
  `page-home`), menu `menu-<slug>` salvato come metadato del termine, traduzioni con `-<lingua>`: si
  ricavano senza un elenco a mano e non si scontrano fra tipi diversi. Una chiave assegnata non cambia
  più, anche se lo slug cambia; una chiave copiata da «Duplica» di Impreza viene rigenerata
  sull'export, con un avviso.
- **Script di configurazione montati dal compose**, oltre che copiati nell'immagine: una modifica vale
  subito, senza ricostruire l'immagine, e su UAT `bin/`, `config/` e script hanno sempre la stessa
  versione.
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
- **Licenza di Impreza che segue il `.env`**: si riattiva quando il segreto del `.env` è diverso da
  quello salvato, come la site key di WPML.
- **`config/versioni.json`**: versioni di Impreza, UpSolution Core e plugin commerciali dell'export;
  l'apply avvisa se il sito ne ha altre, perché opzioni di versioni diverse possono avere uno schema
  diverso.
- **Segreti riconosciuti dalle parole separate da `_` del nome** (`…_key`, `secret`, `token`,
  `password`, `api`) e non da qualsiasi sottostringa: «key» da solo prendeva opzioni come
  `h_keyboard_accessibility`. Un segreto con un nome insolito (`apiKey`) sfugge: l'ultimo controllo è
  `git diff config/` prima del commit.

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
- **Esito del reset del locale (08/10)**: ritratto prima e dopo identico tranne
  l'impronta delle Theme Options, dovuta a `text_styles: []` che Impreza aggiunge a ogni
  installazione. Gli export successivi l'hanno portata in `config/` (è un'opzione vera, vuota), quindi
  un sito ricreato da questo `config/` ha la stessa impronta.
- **Dati generati esclusi dall'export WPML**: `st.was_frontend_visited_key` e
  `custom_fields_translation`/`custom_term_fields_translation`, che WPML calcola da sé.
- **`WPF_CONTAINER`**: sulla stessa macchina più WordPress possono montare lo stesso
  child (è successo con l'ambiente di prova).
- Docker Desktop si è fermato durante una prova, con il Mac molto carico; riavviato dal dev, nessun
  dato perso.

## Follow-up

- **Zip su UAT**: sull'host di UAT c'è ancora `impreza.zip` 9.3.1 e mancano gli zip di WPML; si sostituiscono alla
  messa in opera (README, «Messa in opera su UAT»).
- **Azzeramento giornaliero selettivo**: il ciclo giornaliero deve toccare solo i contenuti
  importati da Drupal (riconoscibili da un id Drupal salvato sul post) e lasciare intatta la
  configurazione; un import che aggiorna invece di cancellare e ricreare tiene stabili id, URL e
  menu. Da decidere nel ticket dell'import.
- **Documentazione di `forestas` allineata** sul branch `feature/oc-8717-wordpress-ricreabile`:
  la procedura di UAT rimanda al README di `wp-forestas` per zip, chiavi e configurazione, e il
  `CLAUDE.md` non descrive più `wordpress-reset.sh` come azzeramento quotidiano già attivo.
- **`--tipo-cammino`**: nel testo dato dall'agente del design system il valore era troncato
  (`#1A41`); il valore giusto, `#1A416F`, viene dal mockup.
