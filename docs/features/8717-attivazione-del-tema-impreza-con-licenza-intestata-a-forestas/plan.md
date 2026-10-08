> Ticket: oc:8717

# Impreza, child theme Forestas e WordPress ricreabile da zero — piano di implementazione (repo `wp-forestas`)

> **Per chi esegue:** task in ordine. **Nessun `git commit`, `git add`, `git push`** in autonomia:
> i passi di commit sono istruzioni per lo sviluppatore, e i puntatori dei submodule non entrano mai
> nel commit del lavoro. Le operazioni su UAT si fanno sull'host (`ssh uat.forestas`, cartella
> `/var/www/html/forestas`); le prove distruttive solo in locale.

**Obiettivo:** Impreza attivo con licenza su UAT, child theme `forestas-child` versionato in questo
repo, stile allineato al design system Forestas, e un WordPress che dopo un reset o su un server
nuovo torna identico: temi, plugin, configurazione di Impreza e WPML, header, footer, Home, menu con
le traduzioni, licenze.

**Architettura:** il child è montato dal compose. `init-wordpress.sh` installa il codice (zip esclusi
da git) e, su un sito che ha appena installato, applica la configurazione versionata in `config/`.
Export, apply e ritratto sono script PHP eseguiti con `wp eval-file` dentro il container;
`bin/wordpress-config.sh` li lancia dall'host e trova il container dal mount del child.

**Stack:** bash (compatibile 3.2 e 5), PHP 8.3 con WP-CLI, API di WPML 5.1.0 e di Impreza/UpSolution
Core 9.4.

**Spec:** [overview.md](overview.md).

## Vincoli globali

- Repo **pubblico**: nessun file di Impreza o WPML (zip, screenshot, codice), nessun dato di licenza,
  né `.env` o `backup/` in file tracciati.
- `init-wordpress.sh` non distrugge mai un'installazione esistente e non sostituisce un tema scelto
  a mano; i passi che aggiunge non bloccano mai l'avvio: errore → `log "AVVISO: …"` e si prosegue.
- Licenze e chiavi solo nel `.env`: `WPML_SITE_KEY`, `IMPREZA_LICENSE_SECRET`,
  `IMPREZA_MAINTENANCE_KEY`; applicate solo se l'host di `WP_URL` non è `localhost` o `127.0.0.1`.
- `DISALLOW_FILE_MODS` solo con `WP_AMBIENTE=produzione`.
- WPML si configura solo con le sue API (`CatalogueSyncRunner`, `SaveLanguages` con `presetCode`,
  `$sitepress->save_settings()`, opzione `WPML(setup)`), mai con SQL sulle tabelle `wp_icl_*`.
- Comandi WP-CLI che scrivono file: come `www-data`; con `WP_URL` in `https`, `$_SERVER['HTTPS']`
  impostato con `--exec` prima del caricamento.
- Segnaposti nei file di `config/`: `@url_sito` per l'URL, `@segreto` per un valore tolto,
  `@chiave:<nome>` per un riferimento a un post esportato.

## Attenzione in review

- Zip mancante o con cartella radice sbagliata → avviso, sito avviato, nessun tema rotto attivato.
- `apply --conferma` lanciato due volte → nessun duplicato di header, footer, Home o menu.
- Header rinominato dal pannello → l'apply lo ritrova dalla chiave, non ne crea un altro.
- Opzione segreta valorizzata (oggi `maintenance_private_key`) → mai nei file di `config/`.
- Errore di rete (API UpSolution, catalogo WPML) all'avvio → il sito parte e l'init lo segnala.

---

### Task 1: Impreza su UAT

1. Estrarre dal pacchetto ThemeForest il solo tema: `Product/Impreza.zip` (contiene
   `Impreza/style.css`). Il pacchetto completo non è installabile.
2. Copiarlo in `wp-forestas/docker/themes/impreza.zip` sull'host e riavviare
   `wordpress-forestasuat`: il passo 7 di `init-wordpress.sh` installa e attiva il tema.
3. Verifica: log `installo e attivo Impreza`, `wp theme list` con Impreza attivo, home in `200` con
   risorse da `wp-content/themes/Impreza`.
4. Licenza: Impreza → Attivazione dal pannello (manuale, sviluppatore).

### Task 2: Stile del design system nelle Theme Options (UAT)

1. Salvare le opzioni attuali (`wp option get usof_options_Impreza --format=json`).
2. Applicare colori, tipografia e bottoni con `usof_save_options()` di Impreza, preceduta da
   `usof_backup()`, via `wp eval-file` come `www-data` e con `--exec='$_SERVER["HTTPS"]="on";'`:
   la funzione rigenera il CSS del tema, e senza HTTPS impostato prima del caricamento gli URL
   generati sarebbero `http://`.
3. Verifica: le variabili `--color-*` e `--h1-font-family` nella home hanno i valori nuovi, nessun
   URL `http://` nel CSS generato.

### Task 3: Child theme nel repo

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-functionsphp-del-child)

1. Copiare `style.css` e `functions.php` del child dal WordPress locale (importato da UAT) in
   `themes/forestas-child/`. Lo screenshot resta fuori.
2. Intestazione di `style.css`: descrizione propria, `Version: 1.0.0`, nessuna riga `Updated`.
3. `compose.yml`: montare `./themes/forestas-child` in `/var/www/html/wp-content/themes/forestas-child`.

### Task 4: Inizializzazione del child e proprietario dei file

1. `init-wordpress.sh`, passo 8: se il child c'è e Impreza è installato, attivare `forestas-child`
   quando il tema attivo è Impreza o `twentytwenty*`; altrimenti solo un avviso.
2. Passo 9: `chown` a `www-data` di tutto `WP_PATH`, escluso il child montato
   (`find … -path "$CHILD_DIR" -prune -o ! -user www-data -exec chown …`).
3. Verifica in locale: `scripts/wordpress-up.sh` di `forestas`, child attivo, nessun file di root
   fuori dal child, installazione di un plugin dal pannello riuscita; con Impreza attivato a mano, un
   riavvio del container riattiva il child.

### Task 5: Struttura per zip e configurazione

**File:** `.gitignore`, `docker/plugins/.gitkeep` (nuovo), `compose.yml`,
`docker/configs/php/Dockerfile`, `.env-example`, `config/.gitkeep` (nuovo)

**Interfacce prodotte:** nel container `/opt/wp-forestas/plugins` (sola lettura, fuori da
`/var/www/html`), `/opt/wp-forestas/config` (sola lettura), script PHP in
`/usr/local/lib/wp-forestas/` (copiati da `docker/scripts/config/`).

- [x] `.gitignore`: `/docker/plugins/*`, `!/docker/plugins/.gitkeep`, `/backup/`.
- [x] `compose.yml`: mount `./docker/plugins:/opt/wp-forestas/plugins:ro` e
      `./config:/opt/wp-forestas/config:ro`.
- [x] Dockerfile: `COPY docker/scripts/config/ /usr/local/lib/wp-forestas/`.
- [x] `.env-example`: `WPML_SITE_KEY=`, `IMPREZA_LICENSE_SECRET=`, `IMPREZA_MAINTENANCE_KEY=`,
      `WP_AMBIENTE=` con commento («vuoto, oppure `produzione`»).
- [x] Verifica: `git check-ignore docker/plugins/x.zip backup/x` li riporta entrambi;
      `docker compose -f ../compose.yml config --quiet` dalla cartella di `forestas` esce 0.

### Task 6: Codice installato dall'init

**File:** `docker/scripts/init-wordpress.sh`

**Interfacce prodotte:** funzione bash `url_locale` (0 se l'host di `WP_URL` è `localhost` o
`127.0.0.1`), usata anche dal Task 9.

- [x] Passo 3b, dopo `wp-config.php`: con `WP_AMBIENTE=produzione`, `wp config set
      DISALLOW_FILE_MODS true --raw`; se non `url_locale` e `WPML_SITE_KEY` non vuota,
      `wp config set OTGS_INSTALLER_SITE_KEY_WPML "$WPML_SITE_KEY"` (solo se diverso).
- [x] Passo 7: prima di installare Impreza, `unzip -l` dello zip deve mostrare la cartella radice
      `Impreza/`; altrimenti avviso e niente installazione.
- [x] Passo 7b: se `us-core` non è installato, installarlo e attivarlo da
      `wp-content/themes/Impreza/common/plugins/us-core.zip`.
- [x] Passo 7c: per ogni `/opt/wp-forestas/plugins/*.zip` il cui slug (nome del file senza `.zip`)
      non è installato, `wp plugin install <zip> --activate`; un plugin già installato non si tocca.
- [x] Ogni passo nuovo con `|| log "AVVISO: …"`.
- [x] Verifica (attenzione: zip mancante, cartella sbagliata, errore): WordPress usa e getta
      `APP_NAME=prova WP_PORT=8091 docker compose -p wpprova up -d --build` con gli zip di WPML e
      Impreza 9.4 in `docker/plugins/` e `docker/themes/` → log con Impreza, UpSolution Core, WPML e
      String Translation installati; con uno zip di Impreza a radice `impreza/` → avviso e sito
      avviato; riavvio → nessuna reinstallazione. In locale `scripts/wordpress-up.sh` di `forestas`
      non cambia nulla. Poi `docker compose -p wpprova down -v`.

### Task 7: Export della configurazione

**File:** `docker/scripts/config/comune.php`, `docker/scripts/config/export.php` (nuovi)

**Interfacce prodotte (`comune.php`):**
- `wpf_valore_segreto( string $nome ): bool` — vero se il nome contiene `key`, `secret`, `token`,
  `password` o `api` (senza distinzione di maiuscole);
- `wpf_togli_segreti( array $dati, array &$tolti ): array` — ricorsiva, sostituisce i valori segreti
  non vuoti con `@segreto` e ne raccoglie i nomi;
- `wpf_url_in_segnaposto( mixed $dati ): mixed` e `wpf_segnaposto_in_url( mixed $dati ): mixed` —
  `home_url()` ↔ `@url_sito`, anche nelle forme con `\/`;
- `wpf_post_per_chiave( string $chiave ): ?WP_Post`.

**Formato dei file** (scritti da `export.php` in una cartella del container, poi copiati dall'host):
`impreza.json` (Theme Options), `child.json` (`theme_mods_forestas-child`), `sito.json`
(`blogname`, `blogdescription`, `permalink_structure`, `show_on_front`, `page_on_front`),
`wpml.json` (lingue da `PageData::active()`, `defaultCode`, impostazioni, `WPML(setup)`),
`post.json` (header, footer, Home: `chiave`, `post_type`, `post_title`, `post_name`, `post_content`,
meta senza `_edit_*`, `lingua`, `originale`), `menu.json` (menu con voci, posizioni, traduzioni).
Riferimenti fra post e opzioni (`header_id`, `footer_id`, `maintenance_page`, `page_on_front`, voci
di menu verso pagine esportate) diventano `@chiave:<nome>`; una voce verso un post non esportato
diventa un link con URL relativo.

- [x] Export sul locale: assegna `_wp_forestas_chiave` ai post che non l'hanno, scrive i 6 file.
- [x] Verifica (attenzione: segreto valorizzato): nessun file contiene il valore di
      `maintenance_private_key` né `localhost:8090`; `impreza.json` contiene `@segreto` e l'export
      stampa `escluso: maintenance_private_key`; `header_id` è un riferimento `@chiave:`.

### Task 8: Apply della configurazione

**File:** `docker/scripts/config/apply.php`, `docker/scripts/config/wpml.php` (nuovi)

**Interfacce:** legge la cartella in `WPF_CONFIG_DIR` (default `/opt/wp-forestas/config`); con
`WPF_PROVA=1` non scrive e stampa una riga per differenza (`<file>: <voce> <prima> → <dopo>`), con
uscita 0. Scrive `wp_forestas_config_applicata` (data ISO) solo a fine senza errori.

- [x] Ordine: WPML (catalogo, lingue con `presetCode = language`, impostazioni, setup), post per
      chiave (crea o aggiorna, poi traduzioni con `wpml_set_element_language_details` sul `trid`
      dell'originale), menu, opzioni con `@chiave:*` risolti, `@url_sito` sostituito, valori
      `@segreto` lasciati come sono sul sito tranne `maintenance_private_key` ←
      `IMPREZA_MAINTENANCE_KEY` se valorizzata; Theme Options con `usof_save_options()`.
- [x] Verifica (attenzione: due apply, header rinominato): sul WordPress usa e getta del Task 6,
      apply → home con header, footer e menu; secondo apply → nessun post nuovo (conteggio uguale);
      header rinominato a mano e terzo apply → nessun duplicato; una traduzione inglese finta
      dell'header esportata e riapplicata → collegata all'originale in WPML.

### Task 9: Licenza Impreza e apply automatico all'avvio

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-9-apply-automatico)

**File:** `docker/scripts/config/licenza-impreza.php` (nuovo), `docker/scripts/init-wordpress.sh`

- [x] `licenza-impreza.php`: se manca `us_license_secret`, chiama `us_api( '/envato_auth', …,
      US_API_RETURN_ARRAY )` con `IMPREZA_LICENSE_SECRET` e il dominio del sito e salva le opzioni
      come fa `us_check_and_activate_theme()` (`site_type` `dev` → `us_license_dev_activated`,
      altrimenti `us_license_activated`; poi `us_license_secret`); risposta negativa → avviso.
- [x] Init, passo 8b: se non `url_locale` e `IMPREZA_LICENSE_SECRET` non vuota → `licenza-impreza.php`.
- [x] Init, passo 8c: se `wp_forestas_config_applicata` manca e `config/` contiene file →
      `apply.php`; errore → avviso, si ritenta al riavvio.
- [x] Verifica (attenzione: errore di rete): in locale il log dice che le licenze non si applicano
      a un indirizzo locale; nel WordPress usa e getta, con `config/` del Task 7, il primo avvio
      applica e scrive il segno, il riavvio non riapplica; con un passo che fallisce il sito parte
      comunque.

### Task 10: Comando sull'host

**File:** `bin/wordpress-config.sh` (nuovo)

**Interfacce:** `bin/wordpress-config.sh export [--in DIR] | apply [--conferma] | ritratto | zip`.

- [x] Container: fra i container avviati, l'unico con mount su
      `/var/www/html/wp-content/themes/forestas-child` la cui sorgente è `themes/forestas-child` di
      questo repo; nessuno o più di uno → errore chiaro.
- [x] `export`: lancia `export.php`, copia con `docker cp` in `config/` (o in `--in DIR`).
- [x] `apply`: senza argomenti `WPF_PROVA=1` e stampa le differenze; con `--conferma` prima un
      export in `backup/<AAAA-MM-GG-HHMMSS>/`, poi apply.
- [x] `ritratto`: lancia `ritratto.php` (Task 11) e `curl` della home.
- [x] `zip`: crea con `ZipArchive` nel container `impreza.zip` (cartella `Impreza`) e uno zip per
      ogni plugin commerciale, poi `docker cp` in `docker/themes/` e `docker/plugins/`.
- [x] Verifica: `/bin/bash -n bin/wordpress-config.sh` e `docker run --rm -v "$PWD/bin:/s:ro" bash:5
      bash -n /s/wordpress-config.sh`; in locale `zip` produce i tre zip con le versioni installate;
      `apply` senza argomenti dopo un `export` stampa «nessuna differenza».

### Task 11: Ritratto e prova completa

**File:** `docker/scripts/config/ritratto.php` (nuovo)

**Interfacce:** stampa righe ordinate `voce: valore`: tema attivo; versioni di Impreza, child,
`us-core`, plugin commerciali, `wm-package`, core (solo `maggiore.minore`); hash delle Theme Options
senza i valori segreti; `header_id`, `footer_id`, `page_on_front`, `maintenance_page` e posizioni dei
menu risolti nella chiave; lingue WPML e predefinita; impostazioni di `sito.json`;
`store_gfonts_locally`.

- [x] In locale: Theme Options → «salva i Google Fonts in locale» attivo; `zip`; `export`.
- [x] Backup del locale con All-in-One WP Migration (lo fa lo sviluppatore), poi `ritratto > prima.txt`.
- [x] `scripts/wordpress-reset.sh --conferma` di `forestas` **solo dopo l'ok esplicito dello
      sviluppatore**; poi `ritratto > dopo.txt`; `diff prima.txt dopo.txt` vuoto.
- [x] Home in 200 con `forestas-child/style.css` e senza `fonts.googleapis.com`; confronto a occhio
      con UAT.

### Task 12: Documentazione

**File:** `README.md`, `CLAUDE.md`, `docs/knowledge/inizializzazione-wordpress.md`, `notes.md`,
`overview.md` (requisiti spuntati); in `forestas` la procedura di UAT, `CLAUDE.md` e la pagina di
conoscenza di WordPress.

- [x] README: riga `themes/forestas-child/` nella tabella, passi dell'inizializzazione, sezione
      «Lavorare sul child theme»; zip necessari e dove metterli (con copia nel posto condiviso del
      team insieme al `.env`); la configurazione si fa in locale, UAT la riceve con
      `apply --conferma`; export su UAT solo con `--in`; aggiornamenti di temi e plugin dal pannello
      di UAT e poi `zip`; `git diff config/` prima del commit; licenze nel `.env` e perché non si
      applicano in locale; licenza di sviluppo e manutenzione; messa in opera su UAT.
- [x] `CLAUDE.md`: trappole sul child montato (niente pannello né Child Theme Configurator, niente
      `chown` sulla cartella montata), segreti nelle Theme Options, configurazione fatta solo in
      locale e portata su UAT con l'apply, WPML solo con le sue API.
- [x] Pagina di conoscenza: perché il child è montato, perché il `chown` copre tutto il core, perché
      l'apply automatico solo sui siti nuovi, perché le API di WPML, perché gli aggiornamenti da UAT.

### Task 13: Commit e messa in opera (sviluppatore)

- [ ] Commit su `feature/oc-8717-child-theme-forestas` di `wp-forestas`, per esempio
      `feat(oc:8717): Impreza, child theme forestas-child e WordPress ricreabile da zero`; PR verso
      `develop`. In `forestas`, commit della documentazione e del commento di
      `scripts/wordpress-reset.sh` su `feature/oc-8717-wordpress-ricreabile`; il puntatore del submodule lo aggiorna lo sviluppatore
      a parte, mai nel commit del lavoro.
- [ ] Su UAT: zip in `docker/themes/` e `docker/plugins/`, chiavi nel `wp-forestas/.env`,
      aggiornamento abituale, `scripts/wordpress-up.sh`, Child Theme Configurator disattivato,
      `bin/wordpress-config.sh apply` (differenze) e, se attese, `--conferma`.
