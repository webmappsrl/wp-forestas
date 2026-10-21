> Ticket: oc:8758

# Import delle pagine statiche da Drupal — piano di implementazione

> **Per chi esegue:** sub-skill richiesta `superpowers:subagent-driven-development` (consigliata) o
> `superpowers:executing-plans`. I passi usano le caselle (`- [ ]`). **Nessun `git commit`, `git add`
> o `git push`:** i commit indicati sono istruzioni per l'utente, che committa lui.

**Obiettivo:** un comando sull'host che crea e tiene allineate in WordPress le 14 pagine statiche del
perimetro leggendo la copia SQL di Drupal, con media, traduzioni WPML, link riscritti, template di
Impreza e menu collegati, ricreabile da zero.

**Architettura:** `bin/wordpress-config.sh pagine` esegue nel container WordPress, con `wp eval-file`,
lo script `docker/scripts/config/pagine.php`, che legge Drupal con `mysqli` (host e porta dal `.env`)
e scrive pagine, meta e allegati con le API di WordPress. Il perimetro sta in `config/pagine.json`; la
grafica sta in tre `us_content_template` versionati in `config/post.json` e assegnati alle pagine col
meta `us_content_id`; i menu puntano alle pagine con la destinazione `pagina` già gestita da
`menu.php`. Il comando bash fa da involucro: lock, log sull'host, codici d'uscita.

**Stack:** bash (bash 3.2 di macOS compresa), PHP 8.3 + WP-CLI nel container, `mysqli`, WordPress,
Impreza 9.4 + UpSolution Core, WPML; copia Drupal MySQL 8 in sola lettura (utente `readonly`).

**Spec:** [overview.md](overview.md)

## Vincoli globali

- Tutto il codice in `wp-forestas`; in `forestas` cambia solo il puntatore del submodule, dopo il merge.
- Repo pubblico: nessun valore reale (password, host interni) in file tracciati; solo in `.env`.
- Commenti, messaggi, documentazione in italiano; opzioni del comando in italiano (`--conferma`,
  `--contenuti-mancanti`), come in `wordpress-config.sh`.
- Lettura Drupal solo in `SELECT`, con `MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT` (una query
  rifiutata deve essere un'eccezione, non un risultato vuoto) e `utf8mb4`.
- Default della connessione: `mysql-sardegnasentieri-dump:3306`, database `sardegnasentieri`, utente
  e password `readonly`; sovrascrivibili con `DRUPAL_DB_HOST`, `DRUPAL_DB_PORT`, `DRUPAL_DB_NAME`,
  `DRUPAL_DB_USER`, `DRUPAL_DB_PASSWORD`. In locale col tunnel: `host.docker.internal` / `3307`.
- `DRUPAL_SYNC=on|off`, predefinito `off`: con `off` solo anteprima, messaggio chiaro, codice ≠ 0.
- Nessuna chiamata SSH negli script.
- Lingue: su Drupal `it` (più `und`, trattato come `it`) ed `en`; in WPML `it` ed **`en-us`** (codice dei
  menu inglesi in `config/menu.json`). `_drupal_langcode` conserva il codice Drupal (`en`); le chiavi
  `en` di `config/pagine.json` sono codici Drupal, convertiti in `en-us` solo nelle chiamate WPML.
- Media da `https://www.sardegnasentieri.it/sites/default/files/<percorso>` per `public://<percorso>`.
- Soglia: più di 2 pagine da mettere in bozza nello stesso giro → stop senza scrivere.
- Log in `log/pagine-AAAA-MM-GG-HHMMSS.log` sull'host, ultimi 60 conservati, `/log/` in `.gitignore`.
- Meta delle pagine: `_wpf_pagina` (chiave del perimetro), `_drupal_nid`, `_drupal_langcode`,
  `wpf_sottotitolo`, `wpf_allegati` (array di id allegato), `wpf_galleria` (array di id allegato),
  `us_content_id` (id del template); meta degli allegati: `_drupal_fid`.
- Opzioni di WordPress: `wpf_sync_mancanti` (elenco dei mancanti), `wp_forestas_pagine_in_corso`
  (lock, stesso schema di `WPF_OPZIONE_APPLY_IN_CORSO`).
- Chiavi dei template (`_wp_forestas_chiave`): `template-pagina-testata`, `template-pagina-testo`,
  `template-pagina-mappa`.
- Trappole del `CLAUDE.md` da rispettare: codice che legge o scrive traduzioni dentro
  `wpf_wpml_tutte_le_lingue()`; cancellazioni con `wpf_wpml_cancella_post`; link con
  `wpf_link_relativo`; niente `… | head` o `echo | grep -q` con `pipefail`; niente `chown` sul child.

## Punti da sorvegliare in review

1. **Copia in ricarica:** tabella `node_field_data` presente ma vuota, o `node__body` vuota → lo
   script deve fermarsi prima di scrivere (Task 2, test con un database finto).
2. **Seconda esecuzione:** subito dopo un `--conferma` l'anteprima deve dire «nessuna differenza» per
   tutte le pagine, media e link (Task 4 e Task 9).
3. **Slug inglese uguale all'italiano** (`da-vedere`): la pagina inglese non deve diventare
   `da-vedere-2` (Task 1).
4. **Media non scaricabile** (URL inesistente): la pagina tiene il media precedente, l'elemento va in
   `wpf_sync_mancanti`, le altre pagine proseguono, codice d'uscita ≠ 0 (Task 5).
5. **`DRUPAL_SYNC` assente** dal `.env`: comportamento `off` (Task 7).

---

### Task 1: Perimetro, slug e verifica WPML

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-slug-inglesi-e-wpml)

**File:**
- Crea: `config/pagine.json`

**Interfacce:**
- Produce: `config/pagine.json` con forma
  `{"pagine": [{"chiave": "privacy", "nid": 88, "template": "testo", "slug": {"it": "privacy"}, "menu": ["footer-menu:PRIVACY"]}, …]}`.
  `template` ∈ `testata | testo | mappa`; `nid` è `null` per Cammini, che ha `"testata_da": "itinerari"`
  e `"titolo": {"it": "Cammini", "en": "Walks"}`; `slug` ha la chiave `en` solo dove l'inglese esiste.

- [ ] **Passo 1:** compila le 14 voci. Slug italiani: `contatti`, `faq`, `app`, `note-legali`, `privacy`,
  `crediti`, `dichiarazione-di-accessibilita`, `sentieri`, `itinerari`, `cammini`, `da-vedere`,
  `da-sapere`, `news`, `mappa-interattiva`. Inglesi solo per `note-legali`, `crediti`, `sentieri`,
  `itinerari`, `cammini`, `da-vedere`, `da-sapere`: leggi gli alias inglesi su Drupal
  (`select alias from path_alias where path in ('/node/87','/node/89','/node/1080','/node/1081','/node/1082','/node/1083') and langcode='en'`)
  e usa l'ultimo segmento quando esiste (`legal-notes`, `credits`), altrimenti lo stesso slug italiano.
- [ ] **Passo 2 (verifica WPML):** in locale crea a mano con WP-CLI una pagina `prova-slug` in italiano
  e la sua traduzione inglese con lo stesso `post_name`, collegate con `wpf_wpml_collega`.
  Atteso: `wp post list --post_type=page --name=prova-slug --fields=ID,post_name` restituisce due
  pagine con `post_name` = `prova-slug`. Se l'inglese diventa `prova-slug-2`, usa per l'inglese gli slug
  `trails`, `itineraries`, `walks`, `points-of-interest`, `good-to-know` e annota la divergenza.
  Cancella le pagine di prova con `wpf_wpml_cancella_post`.
- [ ] **Commit (utente):** `feat(oc:8758): perimetro delle pagine statiche`

### Task 2: Lettura di Drupal e controllo di integrità

**File:**
- Crea: `docker/scripts/config/drupal.php`
- Modifica: `compose.yml` (servizio `wordpress`: `extra_hosts: ["host.docker.internal:host-gateway"]`),
  `.env-example` (blocco `DRUPAL_*` e `DRUPAL_SYNC=off`, con commento sul tunnel)

**Interfacce:**
- `wpf_drupal_connessione(): mysqli` — dai `DRUPAL_DB_*` con i default; eccezione con messaggio
  leggibile (host:porta) se non si connette.
- `wpf_drupal_controlla( mysqli $db, array $nid ): void` — `RuntimeException` se manca o è vuota una di
  `node_field_data`, `node__body`, `path_alias`, `media_field_data`, `file_managed`, o se uno dei `$nid`
  non c'è in `node_field_data`.
- `wpf_drupal_pagina( mysqli $db, int $nid, string $lingua ): ?array` — `null` se la traduzione non
  esiste; altrimenti `['titolo', 'status' (bool), 'sottotitolo', 'body', 'formato', 'immagine' => ?file,
  'allegati' => file[], 'galleria' => file[]]`, dove `file = ['fid', 'uri', 'nome', 'alt', 'mime', 'byte']`.
  Per la lingua `it` accetta anche `langcode = 'und'`. Immagini e allegati dai campi `_media` (catena
  media → `media__field_media_image|document` → `file_managed`), mai dai campi legacy.
- `wpf_drupal_alias( mysqli $db ): array` — `['/percorso' => ['nid' => int, 'lingua' => string]]` da
  `path_alias` (solo `/node/N`), lingua `und` resa come `it`.

- [ ] **Passo 1:** scrivi le funzioni.
- [ ] **Passo 2 (verifica dati):** con il tunnel aperto e `DRUPAL_DB_HOST=host.docker.internal`,
  `DRUPAL_DB_PORT=3307` nel `.env`, ricrea il container (`scripts/wordpress-up.sh` di forestas) e lancia
  `docker exec -u www-data wordpress-forestas wp eval 'require "/usr/local/lib/wp-forestas/drupal.php"; print_r(wpf_drupal_pagina(wpf_drupal_connessione(), 87, "en"));'`.
  Atteso: titolo «Legal notes», `status` true, body non vuoto, 2 allegati (media 8721, 8722).
  `wpf_drupal_pagina(…, 1, 'it')` restituisce «Contatti» (riga `und`).
- [ ] **Passo 3 (verifica integrità):** crea nel MariaDB locale di WordPress un database `prova_drupal`
  con `node_field_data` vuota e le altre tabelle assenti, punta `DRUPAL_DB_*` lì con un utente di prova,
  chiama `wpf_drupal_controlla`. Atteso: `RuntimeException` che nomina la tabella. Togli il database.
- [ ] **Commit (utente):** `feat(oc:8758): lettura della copia Drupal con controllo di integrità`

### Task 3: Media nella libreria

**File:**
- Crea: `docker/scripts/config/media.php`

**Interfacce:**
- `wpf_media_importa( array $file, bool $prova ): int|WP_Error` — riusa l'allegato con meta
  `_drupal_fid` = `$file['fid']` se c'è (nessun download); altrimenti scarica con `download_url()` e
  registra con `media_handle_sideload()`, imposta alt (`_wp_attachment_image_alt`) e `_drupal_fid`. In
  prova restituisce `0` per «da scaricare» senza scaricare. Errori di rete o 404 → `WP_Error` con la causa.

- [ ] **Passo 1:** scrivi la funzione.
- [ ] **Passo 2 (verifica):** importa il file del media 8202 (1 MB). Atteso: un allegato con
  `_drupal_fid` 3595; una seconda chiamata restituisce lo stesso id senza nuovo file in `uploads`.
  Con `uri` = `public://non-esiste.jpg` atteso `WP_Error` con codice HTTP 404 nel messaggio.
- [ ] **Commit (utente):** `feat(oc:8758): import dei media da Drupal`

### Task 4: Pagine, traduzioni, bozze, mancanti

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-chiave-delle-pagine-e-link-delle-traduzioni)

**File:**
- Crea: `docker/scripts/config/pagine.php` (eseguito con `wp eval-file`), `docker/scripts/config/pagine-lib.php`
- Modifica: `docker/scripts/config/comune.php` (lock: `wpf_blocca_pagine(): bool`, `wpf_sblocca_pagine(): void`
  sullo schema di `wpf_blocca_apply`, con `WPF_APPLY_SCADENZA` come scadenza)

**Interfacce:**
- Consuma: Task 1 (`config/pagine.json`), Task 2, Task 3, `wpf_wpml_collega`, `wpf_wpml_tutte_le_lingue`,
  `wpf_post_per_chiave` (template per chiave).
- Variabili lette da `pagine.php`: `WPF_PROVA=1` (anteprima), `WPF_SOLO_MANCANTI=1`, `DRUPAL_SYNC`.
- `wpf_pagina_trova( string $chiave, string $lingua ): ?WP_Post` — per `_wpf_pagina` + lingua WPML,
  stati `publish` e `draft`, dentro `wpf_wpml_tutte_le_lingue()`.
- `wpf_pagina_scrivi( array $voce, string $lingua, array $dati, bool $prova ): array` — crea o aggiorna,
  restituisce `['esito' => 'creata|aggiornata|bozza|invariata', 'campi' => string[], 'mancanti' => array[]]`.
- `wpf_mancanti_leggi(): array`, `wpf_mancanti_salva( array $elenco ): void` — opzione `wpf_sync_mancanti`;
  ogni elemento `['cosa', 'pagina', 'lingua', 'causa', 'primo', 'ultimo']`.
- Righe di output per il log, una per evento, prefisso fisso: `PAGINA`, `MEDIA`, `LINK`, `MENU`,
  `MANCANTE`, `AVVISO`, `ERRORE`, `RIASSUNTO`.

- [ ] **Passo 1:** scrivi `pagine-lib.php` e `pagine.php`. Ordine: `DRUPAL_SYNC` (con `off` e senza
  `WPF_PROVA` → `ERRORE` ed `exit(1)`), lock, connessione, `wpf_drupal_controlla` con i nid del perimetro,
  conteggio delle pagine con `status` falso (> 2 → `ERRORE`, nulla scritto), poi per ogni voce: italiano,
  poi inglese collegato con `wpf_wpml_collega( $id_en, 'post_page', 'en-us', $id_it )`. Campi scritti:
  `post_title`, `post_name`, `post_content` (body Drupal, dopo il Task 6), `post_status`, `_thumbnail_id`,
  `wpf_sottotitolo`, `wpf_allegati`, `wpf_galleria`, `us_content_id`, `_wpf_pagina`, `_drupal_nid`,
  `_drupal_langcode`. Cammini: titolo da `pagine.json`, sottotitolo e immagine da Itinerari, niente
  `_drupal_nid`. Un elemento fallito non ferma le altre pagine: va nei mancanti; un elemento riuscito ne
  esce. Con `WPF_SOLO_MANCANTI` lavora solo sugli elementi dell'elenco. `exit(1)` se a fine giro
  l'elenco non è vuoto o c'è stato un `ERRORE`.
- [ ] **Passo 2 (verifica):** `DRUPAL_SYNC=on`, esecuzione con `WPF_PROVA=1`: 14 pagine IT + 7 EN «da
  creare», nulla scritto (`wp post list --post_type=page --meta_key=_wpf_pagina` vuoto). Poi senza
  prova: 21 pagine; `wp post meta get <id-en-note-legali> _drupal_langcode` = `en`; Cammini ha la stessa
  `_thumbnail_id` di Itinerari. Seconda esecuzione con `WPF_PROVA=1`: tutte `invariata`.
- [ ] **Passo 3 (verifica bozza e soglia):** nel database di prova del Task 2 (copia ridotta di
  `node_field_data`) metti `status = 0` su 88: Privacy va in bozza e torna `publish` rimettendo 1, con lo
  stesso `post_name`. Con `status = 0` su tre nid: `ERRORE` sulla soglia, nessuna pagina cambiata.
- [ ] **Commit (utente):** `feat(oc:8758): sincronizzazione delle pagine statiche da Drupal`

### Task 5: Media delle pagine e fallimenti

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-media-non-scaricabili)

**File:**
- Modifica: `docker/scripts/config/pagine-lib.php`

**Interfacce:**
- Consuma: `wpf_media_importa` (Task 3), `wpf_mancanti_*` (Task 4).

- [ ] **Passo 1:** collega immagine in evidenza, allegati e galleria ai media importati. Un `WP_Error`
  lascia il valore precedente del campo, aggiunge `['cosa' => 'allegato|immagine|galleria', …]` ai
  mancanti e scrive `MEDIA … non scaricato: <causa>`.
- [ ] **Passo 2 (verifica):** con un `uri` alterato nel database di prova per l'immagine di Privacy:
  Privacy mantiene la `_thumbnail_id` precedente, `wp option get wpf_sync_mancanti --format=json` contiene
  l'elemento con la causa 404, le altre pagine `invariata`, `exit 1`. Ripristinato l'`uri`,
  `--contenuti-mancanti` (Task 7) lo risolve e l'elenco torna vuoto.
- [ ] **Commit (utente):** `feat(oc:8758): media delle pagine e media mancanti`

### Task 6: Pulizia del body e riscrittura dei link

**File:**
- Crea: `docker/scripts/config/link.php`

**Interfacce:**
- `wpf_link_riscrivi( string $html, array $alias, callable $trova ): array` — `$alias` da
  `wpf_drupal_alias`; `$trova( int $nid, string $lingua ): ?int` cerca un post di qualunque tipo con
  `_drupal_nid` e `_drupal_langcode`. Riconosce `https?://(www\.)?sardegnasentieri\.it` con o senza
  `index.php/`, con o senza `/en/`, alias o `/node/N`. Restituisce
  `['html' => string, 'riscritti' => string[], 'in_attesa' => string[]]`; un link verso una pagina del
  perimetro senza `_drupal_nid` (link a `/` = Home) diventa `/`.
- La pulizia del body si limita a togliere `style` inline e attributi Drupal (`data-entity-*`); il resto
  dell'HTML resta com'è (verificato: niente `<img>`, `<drupal-media>`, `<script>` nei body).

- [ ] **Passo 1:** scrivi la funzione e chiamala in `wpf_pagina_scrivi` prima di confrontare il body.
- [ ] **Passo 2 (verifica):** dopo un `--conferma`, nel `post_content` di App
  `https://www.sardegnasentieri.it/index.php/pagina-base/privacy` è diventato `/privacy/`; i quattro link
  ai «da sapere» e `/form/segnalazione` sono invariati e compaiono come `LINK … in attesa` nell'output.
  In Crediti EN `/en/node/1491` è `in attesa`.
- [ ] **Commit (utente):** `feat(oc:8758): riscrittura dei link interni`

### Task 7: Comando sull'host, log, lock, interruttore

**File:**
- Modifica: `bin/wordpress-config.sh` (nuovo comando `pagine`, `uso()`), `.gitignore` (`/log/`)

**Interfacce:**
- `bin/wordpress-config.sh pagine [--contenuti-mancanti] [--conferma]`. Senza `--conferma` passa
  `WPF_PROVA=1`; con `--contenuti-mancanti` passa `WPF_SOLO_MANCANTI=1`. `DRUPAL_SYNC` arriva al
  container dal `.env` (env_file del compose).
- Log: output di `esegui_script pagine.php …` scritto anche in `log/pagine-<data-ora>.log` (redirezione
  in un file e poi `cat`, non `tee` in pipe: con `pipefail` l'exit dello script va conservato), poi
  cancellazione dei log oltre i 60 più recenti. A schermo: le righe `RIASSUNTO`, `ERRORE`, `MANCANTE` e il
  percorso del log; il comando suggerito per i mancanti se ce ne sono.
- Codice d'uscita = quello dello script.

- [ ] **Passo 1:** scrivi il comando.
- [ ] **Passo 2 (verifica):** senza `DRUPAL_SYNC` nel `.env`, `bin/wordpress-config.sh pagine --conferma`
  esce ≠ 0 con «sincronizzazione da Drupal spenta in questo ambiente» e nessuna pagina modificata;
  `pagine` senza `--conferma` mostra l'anteprima. Due `--conferma` lanciati insieme: il secondo esce
  subito con il messaggio del lock. Il file di log appartiene all'utente dell'host. Con 61 log ne restano 60.
- [ ] **Commit (utente):** `feat(oc:8758): comando pagine con log e interruttore`

### Task 8: Template di Impreza e stili

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-8-grafica-dei-template)

**File:**
- Modifica: `config/post.json` (tre `us_content_template`, IT ed EN se il resto della configurazione lo fa
  per gli oggetti builder), `themes/forestas-child/style.css` (o il file di stili del child), shortcode del
  child per sottotitolo, allegati e galleria (`[forestas_sottotitolo]`, `[forestas_allegati]`,
  `[forestas_galleria]`, leggono i meta del Task 4), `Version` del child

**Interfacce:**
- Consuma: meta `wpf_sottotitolo`, `wpf_allegati`, `wpf_galleria`, immagine in evidenza, `post_content`.
- Produce: template con chiavi `template-pagina-testata`, `template-pagina-testo`,
  `template-pagina-mappa`, che `pagine.php` risolve con `wpf_post_per_chiave` per scrivere `us_content_id`.

- [ ] **Passo 1:** ricava misure, colori e tipografia di testata hero, testo e allegati da due fonti:
  - la copia locale del codice Drupal,
    `/Volumes/Crucial X6 1/Siti Webmapp/Wordpress/Forestas/Drupal sardegnasentieri/sardegnasentieri/docroot/themes/custom/sardegnasentieri/`
    (`scss/`, `layouts/`, `css/`; in alternativa lo zip su UAT, `/root/sardegnasentieri.zip`);
  - l'artifact «Canvas Fase 2» con le modifiche di oc:8783
    (https://claude.ai/artifact/4T3ZRSP1LCrbCuJdNuEGXQ), in particolare la tavola delle sezioni
    redazionali (`.red-hero`, `.red-scrim`, `.fil` nel colore della sezione) per la testata.
  Dove le due fonti divergono vale la regola del `CLAUDE.md`: il sito Drupal, salvo le modifiche
  concordate in oc:8783; le divergenze si chiedono al dev. Il sito in produzione il 20/10 rispondeva 503:
  il confronto nel browser si fa quando torna su, altrimenti con il tema Drupal.
- [ ] **Passo 2:** costruisci i tre template dal pannello in locale (titolo, sottotitolo, immagine come
  sfondo con velo, contenuto, allegati, galleria; mappa con iframe a tutta pagina), assegna le chiavi,
  `bin/wordpress-config.sh esporta`, controlla `git diff config/`.
- [ ] **Passo 3 (verifica):** rilancia `pagine --conferma`; confronta nel browser ogni pagina con la
  produzione (se raggiungibile) e con l'artifact a 390, 768, 1280 e 1440px, IT ed EN. Requisiti [UX] dell'overview: contrasto della testata
  ≥ 4,5:1, ancore della FAQ non coperte dall'header (`scroll-margin-top`), `title` dell'iframe con link
  alternativo, allegati con nome, formato e dimensione, immagini con `loading="lazy"` e dimensioni.
  axe senza violazioni, anche da loggato.
- [ ] **Commit (utente):** `feat(oc:8758): template di Impreza per le pagine statiche`

### Task 9: Un solo scrittore e menu collegati

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-9-menu-inglesi-verso-pagine-solo-italiane)

**File:**
- Modifica: `docker/scripts/config/export.php` (salta i post con `_wpf_pagina` fra le pagine riferite
  dalle opzioni), `docker/scripts/config/post.php` (`wpf_post_applica` salta con `AVVISO` un post di
  `config/` che sul sito è una pagina con `_wpf_pagina`), `config/menu.json`

**Interfacce:**
- Consuma: destinazione `{"tipo": "pagina", "post_type": "page", "percorso": …, "url": …}` già gestita da
  `wpf_menu_args_destinazione` (`menu.php:319`).

- [ ] **Passo 1:** escludi le pagine nei due file.
- [ ] **Passo 2:** collega dal pannello in locale le voci del perimetro alle pagine (menu IT ed EN; le
  voci inglesi senza traduzione alla pagina italiana), `bin/wordpress-config.sh esporta`, controlla che in
  `config/menu.json` le voci abbiano `tipo: pagina` e nessun id numerico.
- [ ] **Passo 3:** in `pagine.php` aggiungi l'avviso `MENU <voce> punta a una pagina in bozza` per le voci
  di menu verso pagine del perimetro in bozza.
- [ ] **Passo 4 (verifica, sito ricreato):** `scripts/wordpress-reset.sh --conferma` in locale, attesa
  dell'apply automatico, `pagine --conferma`, `applica --conferma`: menu collegati alle pagine, anteprima di
  `applica` e di `pagine` senza differenze. Ripeti invertendo l'ordine (pagine prima dell'apply): stesso
  risultato. Imposta temporaneamente `page_for_posts` sulla pagina News ed esegui `esporta`: News non
  compare in `config/post.json`.
- [ ] **Commit (utente):** `feat(oc:8758): menu collegati alle pagine e un solo scrittore`

### Task 10: Documentazione

**File:**
- Modifica: `README.md` (comando `pagine`, variabili `DRUPAL_*`, tunnel in locale, log, mancanti),
  `CLAUDE.md` (trappole), `docs/knowledge/` (pagina sulla sincronizzazione delle pagine: perché Drupal
  vince, `_wpf_pagina` e lingua come chiave, soglia, `DRUPAL_SYNC`, indirizzi senza `pagina-base`),
  `docs/features/8758-…/notes.md` (follow-up: 301 da `/pagina-base/…` con APP indicizzata, sitemap con
  `blog_public`, media allo spegnimento di Drupal, `DRUPAL_SYNC=off` al lancio, cron lontano dalle 10:00,
  vincolo `_drupal_nid` + `_drupal_langcode` per oc:8760)

- [ ] **Passo 1:** scrivi la documentazione; controlla i link fra i file.
- [ ] **Commit (utente):** `docs(oc:8758): sincronizzazione delle pagine statiche`

Dopo il merge (utente): PR verso `develop` di `wp-forestas`, poi in `forestas` puntatore del submodule
con l'hash letto da `git rev-parse`.
