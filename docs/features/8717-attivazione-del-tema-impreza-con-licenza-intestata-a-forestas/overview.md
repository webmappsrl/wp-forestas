> Ticket: oc:8717

# Attivazione del tema Impreza con licenza intestata a Forestas

## Cosa cambia

Il WordPress dello shard usa il tema Impreza attraverso un child theme, `forestas-child`, che è
codice del repo `wp-forestas`.

- **Impreza su UAT**: installato da `docker/themes/impreza.zip` (lo zip del solo tema, estratto dal
  pacchetto ThemeForest) e attivato, con la licenza di Forestas attivata dal pannello. Operazioni
  manuali sul server, fuori dal repo.
- **Child theme nel repo**: `themes/forestas-child/` contiene `style.css` e `functions.php`. Il
  compose lo monta dentro WordPress, quindi una modifica nel repo è subito visibile sul sito, in
  locale e su UAT dopo il pull. `init-wordpress.sh` lo attiva quando il tema attivo è Impreza o un
  tema di default.
- **Stile del design system Forestas**: colori, tipografia (Work Sans) e bottoni del mockup «Canvas
  Fase 2» impostati nelle Theme Options di Impreza su UAT; le variabili che non hanno un posto in
  Impreza (declinazioni, stati di validazione, colori per tipo di contenuto) e il filo oro sopra il
  footer stanno nello `style.css` del child.
- **Installazione dei plugin dal pannello**: `init-wordpress.sh` assegna a `www-data` tutti i file
  di WordPress, core compreso, e non solo `wp-content`.
- **WordPress ricreabile da zero senza perdere nulla**: dopo un reset o su
  un server nuovo, `init-wordpress.sh` reinstalla temi e plugin con la loro configurazione e le
  licenze.
  - **Codice:** Impreza e child come oggi; UpSolution Core dallo zip che Impreza contiene
    (`common/plugins/us-core.zip`); WPML e WPML String Translation da zip in `docker/plugins/`,
    esclusa da git. Child Theme Configurator e All-in-One WP Migration non fanno parte del sito.
  - **Configurazione:** un export scrive nel repo, in `config/`, le Theme Options di Impreza, i
    `theme_mods` del child, header e footer di Impreza, la Home, i menu, le impostazioni di WPML e
    quelle del sito (titolo, permalink, home statica). Un apply le riscrive in WordPress e ricollega
    i riferimenti per id (`header_id`, `footer_id`, `page_on_front`, posizioni dei menu). L'apply
    parte da solo alla prima installazione, e su un sito esistente solo con un comando esplicito.
  - **WPML** si configura con le sue stesse API, senza scrivere nelle sue tabelle: sincronizzazione
    del catalogo delle lingue (`CatalogueSyncRunner`, funziona anche senza registrazione), lingue
    salvate con l'endpoint della schermata «Lingue» (`SaveLanguages`, con il `presetCode` di ogni
    lingua), impostazioni con `$sitepress->save_settings()`, stato del wizard nell'opzione
    `WPML(setup)`. Provato su un WordPress nuovo: lingue, locale, tag, bandiere, URL `/en/`, setup e
    impostazioni identici a UAT; un secondo apply non duplica nulla.
  - **Licenze e chiavi:** `WPML_SITE_KEY`, `IMPREZA_LICENSE_SECRET` e `IMPREZA_MAINTENANCE_KEY` (il
    link privato che scavalca la modalità manutenzione) stanno in `wp-forestas/.env`. Lo script le
    applica solo se `WP_URL` non è `localhost` o `127.0.0.1`. WPML si registra con la costante
    `OTGS_INSTALLER_SITE_KEY_WPML`; Impreza si riattiva con la stessa chiamata che fa il tema
    (`/envato_auth` con segreto e dominio) e salva ciò che risponde l'API: verificato in sola
    lettura su UAT, risposta `status 1`, `site_type dev`.
  - **Licenza di sviluppo e manutenzione:** con una licenza di sviluppo Impreza tiene sempre accesa
    la modalità manutenzione (oggi su UAT la pagina di manutenzione è la Home). In produzione serve
    un'attivazione come sito di produzione.

## Perché

Il WordPress che sostituisce la parte editoriale del Drupal di Sardegna Sentieri deve avere la
grafica già realizzata, montata su Impreza (scrum del 06/10 e del 07/10, tag `wp-forestas`). Allo
scrum del 07/10 è stato deciso l'ordine: prima l'attivazione di Impreza, poi la grafica. La grafica
va nel repository e si prova in locale, poi commit, puntatore del submodule e pull su UAT: per
questo il child theme deve stare in `wp-forestas` e arrivare a WordPress dal repo.

Allo scrum del 06/10 è stato chiesto che lo script installi tutto quello che serve, perché il sito
si ricrei da zero con un comando e un cambio di server non richieda giorni. Oggi un reset cancella
licenze, Theme Options, WPML e la sua configurazione, che esistono solo nel database di UAT.

Il core scaricato come root impediva l'installazione dei plugin dal pannello in locale: WordPress
confronta il proprietario del proprio codice con quello dei file creati da Apache e, se sono
diversi, chiede le credenziali FTP.

## Requisiti

- [x] Impreza installato e attivo su UAT, licenza di Forestas attivata dal pannello.
- [x] Child theme `forestas-child` in `themes/forestas-child/`, senza file di Impreza (il repo è
      pubblico: lo screenshot generato da Child Theme Configurator è grafica di Impreza e resta fuori).
- [x] `compose.yml` monta `themes/forestas-child` in `wp-content/themes/forestas-child`.
- [x] `init-wordpress.sh` attiva `forestas-child` se il tema attivo è Impreza o un tema di default, e
      non sostituisce un tema scelto a mano.
- [x] `init-wordpress.sh` assegna a `www-data` tutti i file di WordPress tranne il child montato.
- [x] Colori, tipografia e bottoni delle Theme Options di Impreza su UAT allineati al mockup «Canvas
      Fase 2»; le variabili restanti nel child.
- [x] Il child ha una numerazione propria (`Version: 1.0.0`), indipendente da quella di Impreza.
- [x] README e `CLAUDE.md` spiegano come si lavora sul child e cosa resta fuori dal repo.
- [x] `docker/plugins/` esclusa da git (con `.gitkeep`) e montata nel container; gli zip commerciali
      non entrano mai in un commit.
- [x] `init-wordpress.sh` installa e attiva UpSolution Core dallo zip contenuto in Impreza, se manca,
      e lo riattiva se è spento: senza, Impreza non ha Theme Options né builder (a differenza dei
      plugin commerciali, che spenti a mano restano spenti).
- [x] `init-wordpress.sh` installa e attiva i plugin degli zip in `docker/plugins/` che non sono
      installati; un plugin installato e disattivato a mano non viene riattivato. Zip mancante:
      avviso nei log, il sito parte lo stesso.
- [x] Zip di WPML 5.1.0, WPML String Translation 5.1.0 e Impreza 9.4 creati dalle cartelle
      installate, uguali a UAT; lo script controlla che la cartella radice di Impreza sia `Impreza`.
- [x] `docker/plugins/` montata fuori da `/var/www/html`, come `docker/themes/`.
- [x] `bin/wordpress-config.sh zip` rigenera gli zip di Impreza e dei plugin commerciali dalle
      versioni installate, nelle cartelle escluse da git: gli aggiornamenti si fanno dal pannello di
      UAT, dove ci sono le licenze, e gli zip arrivano in locale da lì.
- [x] `DISALLOW_FILE_MODS` solo in produzione, riconosciuta da `WP_AMBIENTE=produzione` nel `.env`.
- [x] Licenze dal `.env`: `OTGS_INSTALLER_SITE_KEY_WPML` in `wp-config.php` e segreto di Impreza
      nelle sue opzioni, solo se `WP_URL` non è locale. `.env-example` con i segnaposto.
- [x] Export: Theme Options di Impreza, `theme_mods` del child, header e footer di Impreza, Home,
      menu con le impostazioni di Impreza sulle voci (mega menu, pulsante), con le loro traduzioni
      WPML voce per voce (Menu Sync) e le voci verso pagine che restano collegate alle pagine, impostazioni di WPML (lingue `it` e `en-us`, formato degli URL), traduzioni dei testi
      delle opzioni fatte con String Translation, titolo, permalink e home statica, in file
      leggibili in `config/`. L'URL del sito diventa
      un segnaposto. I file sono dell'utente dell'host, non di root.
- [x] Nessun segreto nei file esportati, chiudendo per impostazione predefinita: le opzioni con
      `key`, `secret`, `token`, `password` o `pass` nel nome (anche in camelCase; «api» da sola no,
      `apiUrl` è un'impostazione) sono tolte e segnalate (oggi
      `maintenance_private_key`); quelle che servono vanno nel `.env` e l'apply le rimette. Un valore
      con la forma di una chiave nota, sotto qualsiasi nome, ferma l'export senza scrivere.
- [x] Google Fonts serviti in locale (`store_gfonts_locally`), verificato anche dopo il reset.
- [x] Apply: riscrive la configurazione esportata, ricollega i riferimenti per id e le traduzioni
      WPML; idempotente: header, footer e Home si riconoscono dal metadato `_wp_forestas_chiave` del
      post, i menu dallo stesso metadato sul termine, non da titolo o slug. Su un sito che esisteva
      prima di `config/` i post e i menu senza chiave si ricollegano per tipo e slug. Le pagine che
      esistono già, come la Home, sono anche contenuto della redazione: si aggiornano solo con
      `apply --pagine`.
- [x] L'apply automatico parte solo su un sito installato dall'init (opzione
      `wp_forestas_config_da_applicare`, scritta all'installazione), al massimo 3 volte; scrive
      `wp_forestas_config_applicata` solo se finisce senza errori. Su un sito esistente, e dopo i 3
      tentativi, solo con il comando esplicito. Un riavvio di un sito configurato non cambia nulla.
- [x] I passi nuovi dell'init (zip, licenze, catalogo WPML, apply) non bloccano mai l'avvio: in caso
      di errore scrivono un avviso nei log; licenze e plugin si ritentano a ogni riavvio, l'apply
      entro i suoi 3 tentativi. Un apply parziale esce con errore. Se `comune.php` (montato dal repo)
      non si carica, WordPress parte lo stesso con un avviso.
- [x] L'apply ha una modalità che non scrive e restituisce le differenze fra configurazione del repo
      e sito, usata dal comando sull'host prima di chiedere `--conferma`.
- [x] Comando sull'host in questo repo: `bin/wordpress-config.sh export|apply|ritratto`. Trova da
      solo il container WordPress, cioè quello avviato che monta il child da questa stessa cartella
      (non dal nome dell'immagine, che una ricostruzione riassegna), senza leggere il `.env` di
      `forestas`; si ferma con un messaggio chiaro se non lo
      trova o se ne trova più di uno.
- [x] `apply` senza argomenti non scrive nulla e stampa le differenze fra repo e sito; con
      `--conferma` salva prima la configurazione attuale in `backup/<data-ora>/` (esclusa da git) e
      poi applica; con `--da DIR` applica un'altra cartella, per esempio un backup.
- [x] Sintassi di `bin/wordpress-config.sh` verificata con `bash -n` su bash 3.2 (macOS) e bash 5.
- [x] Ritratto del sito (tema attivo, versioni, Theme Options, riferimenti, lingue WPML, impostazioni,
      home in 200 con CSS del child e Work Sans) salvato prima e dopo un reset; i due coincidono.
- [x] Prova in locale: export, `wordpress-reset.sh --conferma`, ritratti uguali, home controllata a
      occhio accanto a UAT.
- [x] README: la configurazione si fa in locale e UAT la riceve con apply (un export su UAT scrive
      fuori dal repo con `--in`); aggiornamenti di temi e plugin dal pannello di UAT e poi `zip`;
      copia di zip e `.env` in un posto condiviso del team; `git diff` su `config/` prima del commit;
      zip necessari e dove metterli, flusso «cambi la configurazione → export → commit»,
      licenze nel `.env` e comportamento in locale, passi di messa in opera su UAT (zip, chiavi,
      `wordpress-up.sh`, Child Theme Configurator da disattivare).
- [x] Il codice di WordPress cambia solo in questo repo. In `forestas` cambiano la documentazione
      (procedura di UAT, `CLAUDE.md`, pagina di conoscenza) e due correzioni a `wordpress-reset.sh` e
      `wordpress-up.sh` (lettura di `APP_NAME`), poi, dopo il merge, il puntatore del submodule, che
      non si committa insieme a questo lavoro.

## Rischi

- **Una modifica fatta dal pannello di UAT finisce nel checkout del repo sul server**, fuori da
  git, e può far fallire il pull successivo: documentato nel README e nel `CLAUDE.md`; Child Theme
  Configurator va disattivato su UAT alla messa in opera.
- **Un `chown` sulla cartella montata cambierebbe il proprietario dei file del repo sull'host**: la
  cartella del child è esclusa dal `chown` di `init-wordpress.sh`.
- **Configurazione esportata e sito possono allontanarsi**: una modifica fatta dal pannello e non
  esportata si perde al reset successivo. Mitigato documentando il flusso export → commit; su un
  sito esistente l'apply mostra prima le differenze, scrive solo con `--conferma` e salva un backup
  della configurazione che sostituisce.
- **UAT resta modificabile dal pannello per temi e plugin**, perché è lì che le licenze permettono
  gli aggiornamenti: un aggiornamento senza `zip` fa divergere gli ambienti. Il ritratto prima e dopo
  un reset lo rende visibile.

## Out of scope

- Azzeramento giornaliero selettivo dei soli contenuti importati da Drupal: ticket dell'import.
- Contenuti editoriali, contenuti di esempio di WordPress e uploads: non fanno parte della
  configurazione.
- Larghezze del layout e raggio generale degli angoli del mockup.

## Moduli toccati

Repo `wp-forestas`:

- `themes/forestas-child/style.css`, `themes/forestas-child/functions.php` — nuovi (`functions.php`
  dà al CSS del child la sua `Version` al posto di quella di Impreza)
- `compose.yml` — mount del child, degli zip dei plugin, di `config/` e degli script di configurazione;
  nome dell'immagine con `APP_NAME`
- `docker/scripts/init-wordpress.sh` — passi 1 (database controllato con una query, uscita se non
  risponde), 3b (costanti d'ambiente), 4b (`.htaccess` per i
  permalink), 4c (blocco di un apply interrotto), 6 (wp-geohub a un commit fisso), 7 (controllo
  dello zip), 7b (UpSolution Core), 7c (plugin commerciali), 8 (child), 8b (licenza Impreza), 8c
  (apply automatico), 9 (proprietario dei file); funzioni `config_php` e `config_valore` (nomi e
  percorsi da `comune.php`), `url_locale`, `wp_script` e `slug_zip`
- `README.md`, `CLAUDE.md`, `docs/knowledge/inizializzazione-wordpress.md`
- `.gitignore`, `.env-example`, `docker/plugins/.gitkeep`,
  `docker/plugins/commerciali.txt` (elenco dei plugin commerciali), `config/` (nuova, con `.gitkeep`),
  `docker/scripts/config/` (nuova: `comune.php`, `export.php`, `apply.php`, `post.php`, `menu.php`,
  `opzioni.php`, `wpml.php`, `ritratto.php`, `licenza-impreza.php`), `docker/configs/php/Dockerfile`
  (copia degli script, WP-CLI a una versione fissa),
  `bin/wordpress-config.sh` (nuovo: export, apply, ritratto, zip),
  `backup/` (esclusa da git)

Repo `forestas`: `docs/howto/messa-in-opera-wordpress-uat.md`, `docs/knowledge/wordpress-nello-shard.md`,
`CLAUDE.md`, `scripts/wordpress-reset.sh` e `scripts/wordpress-up.sh` (commento e lettura di
`APP_NAME` senza `grep | head`, che con `pipefail` faceva uscire lo script senza messaggio); il
puntatore del submodule dopo il merge.

Su UAT, fuori dal repo: zip di Impreza in `docker/themes/`, licenza, Theme Options di Impreza,
child creato con Child Theme Configurator e poi sostituito dal mount, temi di default eliminati.
