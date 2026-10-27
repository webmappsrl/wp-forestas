> Ticket: oc:8759

> **Piano eseguito il 27/10/2026.** Le differenze fra questo piano e il lavoro fatto (schede invertite,
> niente `/news/news`, shortcode al posto delle griglie di Impreza, ordine per data, riquadro
> dell'editor delle news, lettura delle news in memoria) sono in `notes.md`, «Divergenze dal piano».

# Import delle news da Drupal: piano di implementazione

> **Per chi esegue:** si lavora un task per volta, con le caselle (`- [ ]`) per segnare i passi.
> **Nessun commit automatico:** i comandi `git commit` sono istruzioni per il dev, che li lancia lui
> dopo aver controllato il codice. Prima di ogni commit: `git diff config/` (repo pubblico: nessun
> valore reale, niente file di Impreza o WPML).

**Obiettivo:** le 95 news italiane e le 4 traduzioni inglesi di Drupal diventano post di WordPress
con la categoria «news», dentro lo stesso giro di `bin/wordpress-config.sh pagine`, con la pagina
News a schede e le pagine d'elenco come su Drupal.

**Architettura:** il motore di oc:8758 (`docker/scripts/config/`) si divide in una parte comune
(scrittura di un contenuto, media, link, WPML, mancanti) e due parti per tipo: pagine (perimetro fisso
in `config/pagine.json`) e news (elenco letto dalla copia con una query). `pagine.php` resta il punto
d'ingresso e lancia le news dopo le pagine, con lo stesso lock e lo stesso log. Il child theme porta la
regola di rewrite di `/news/`, lo shortcode delle schede, la card e la tassonomia
`tipologia_da_sapere`; la pagina della news e le griglie sono template di Impreza in `config/post.json`.

**Stack:** WordPress + WP-CLI (`wp eval-file`), PHP 8.3, mysqli verso la copia MySQL di Drupal,
WPML, Impreza (template `us_content_template`, griglie).

**Spec:** `docs/features/8759-import-delle-news-da-drupal-copia-sql-con-media-e-traduzioni-wpml/overview.md`
(con `notes.md` per i dati verificati).

## Vincoli globali

- Il repo è pubblico: credenziali e URL interni solo nel `.env`; `.env-example` ha solo segnaposto.
- Impreza, WPML e le loro chiavi non si committano mai.
- Comandi e opzioni dei nostri script in italiano (`--conferma`, `--accetta-bozze`, `--contenuti-mancanti`).
- Allineamento 1 a 1 con Drupal per aspetto e comportamento; il codice Drupal di riferimento è in
  `/Volumes/Crucial X6 1/Siti Webmapp/Wordpress/Forestas/Drupal sardegnasentieri/sardegnasentieri`.
- Nessuna sincronizzazione su UAT intorno alle 10:00 (reload della copia).
- Codice che legge o scrive post e termini da WP-CLI dentro `wpf_wpml_tutte_le_lingue()`.
- Verifica dell'header e del menu anche da loggati sotto 600px, dopo lo scroll.
- Non esiste una suite di test nel repo: ogni task si verifica con l'anteprima e il `--conferma` dello
  script, con `wp eval` e `curl` sul sito locale, e nel browser. `php -l` su ogni file PHP toccato.

## Punti da controllare in review

1. **Secondo giro senza cambi:** dopo `pagine --conferma`, un secondo `pagine` deve dare solo
   «invariata» per pagine e news e nessun media da scaricare (Task 5, passo «idempotenza»).
2. **Copia caricata a metà:** con il conteggio delle news della copia artificialmente basso, lo script
   si ferma senza scrivere (Task 5, prova con `WPF_NEWS_PROVA_CONTEGGIO`).
3. **Precedenza a `/news/`:** `/news/news`, `/news/attivita-e-comunicazioni` e le versioni `/en/`
   danno le pagine scheda, `/news/<titolo>` dà il post (Task 6, `curl`).
4. **Sito inglese:** una news senza traduzione si vede in italiano a `/en/news/<slug>`; le 4 tradotte
   hanno il loro testo inglese e i termini giusti (Task 5 e Task 7).
5. **Regressione delle pagine di oc:8758:** dopo il Task 1 l'anteprima delle pagine su un sito già
   allineato dà 14+14 «invariata» (Task 1).

---

## Struttura dei file

| File | Responsabilità |
|---|---|
| `docker/scripts/config/drupal.php` (modifica) | `DRUPAL_FILES_URL`; tabelle nuove in `WPF_DRUPAL_TABELLE`; query di news, termini, riferimenti, collegamenti |
| `docker/scripts/config/contenuti-lib.php` (nuovo) | parte comune estratta da `pagine-lib.php`: differenze, scrittura di un contenuto (pagina o post), media, mancanti con provenienza |
| `docker/scripts/config/pagine-lib.php` (modifica) | solo ciò che è delle pagine: perimetro, percorsi, traduzioni nostre, `post_parent`, meta delle schede |
| `docker/scripts/config/news-lib.php` (nuovo) | news: lettura dalla copia, termini, soglie, scrittura dei post |
| `docker/scripts/config/pagine.php` (modifica) | punto d'ingresso: pagine, poi news, mancanti, riassunto |
| `config/pagine.json` (modifica) | schede figlie di News, pagine d'elenco, `genitore`, `scheda`, `pulsante` |
| `config/sito.json` (modifica) | `permalink_structure` `/news/%postname%/` |
| `config/post.json` (modifica, export) | template della news, delle schede, delle pagine d'elenco, griglie e card |
| `config/wpml.json` (modifica, export) | `post`, `category`, `post_tag`, `tipologia_da_sapere` traducibili; ripiego sui post |
| `themes/forestas-child/inc/news.php` (nuovo) | rewrite di `/news/`, redirect `/node/1094`, shortcode schede e collegamenti, tassonomia `tipologia_da_sapere` |
| `themes/forestas-child/functions.php` (modifica) | `require` di `inc/news.php` |
| `themes/forestas-child/assets/css/news.css`, `assets/js/schede.js` (nuovi) | schede, card, scorrimento sotto 860px |
| `bin/wordpress-config.sh` (modifica) | testo d'aiuto: le news nel giro di `pagine`, `--accetta-bozze` per pagine e news |
| `.env-example` (modifica) | `DRUPAL_FILES_URL` |
| `docs/howto/ricreare-wordpress-da-zero.md`, `docs/knowledge/8759-….md`, `CLAUDE.md` | documentazione |

---

### Task 1: Parte comune del motore, indirizzo dei file nel `.env`, mancanti con provenienza

**File:**
- Crea: `docker/scripts/config/contenuti-lib.php`
- Modifica: `docker/scripts/config/pagine-lib.php` (funzioni `wpf_pagina_differenze`, `wpf_pagina_media`,
  `wpf_pagina_scrivi`, `wpf_mancanti_*`), `docker/scripts/config/pagine.php:244-262` (filtro dei
  mancanti), `docker/scripts/config/drupal.php:22,309-314`, `.env-example`

**Interfacce prodotte:**
- `wpf_drupal_files_base(): string` — `getenv('DRUPAL_FILES_URL')` con `/` finale garantito, oppure
  `'https://www.sardegnasentieri.it/sites/default/files/'`. `wpf_drupal_url_file()` la usa al posto
  della costante, che si toglie.
- `wpf_contenuto_differenze( ?WP_Post $post, array $voluti, array $meta ): array` — rinomina di
  `wpf_pagina_differenze`, stesso comportamento.
- `wpf_contenuto_media( array $file, bool $prova ): array` — rinomina di `wpf_pagina_media`.
- `wpf_contenuto_scrivi( array $c, bool $prova, ?array $solo = null ): array` — il corpo di
  `wpf_pagina_scrivi` reso generico. `$c` ha: `tipo` (`page`|`post`), `trova` (`callable(): ?WP_Post`),
  `lingua`, `slug`, `parent` (int, 0 per i post), `id_it`, `voluti` (campi del post senza `post_name`),
  `meta`, `media` (`[cosa => file[]]`, con le chiavi di `WPF_PAGINA_MEDIA`), `template` (id o 0, e
  `nome_template` per il messaggio), `termini` (`[tassonomia => term_id[]]`, vuoto per le pagine).
  Restituisce la stessa forma di oggi: `id`, `esito` (`creata|aggiornata|invariata|non creata|non aggiornata`),
  `campi`, `mancanti`, `media`. Il tipo WPML è `post_{$tipo}`. I termini si confrontano e si scrivono
  con `wp_set_object_terms` e contano come campo `termini` nelle differenze.
- `wpf_pagina_scrivi(...)` resta con la firma di oggi e costruisce `$c` (con `parent` da Task 2).
- Elenco dei mancanti: ogni voce ha `origine` (`pagina`|`news`) e `pagina` diventa `elemento` (la
  chiave per le pagine, il nid per le news). `wpf_mancante_chiave( string $origine, string $cosa, string $elemento, string $lingua, string $dettaglio = '' )`.
  `wpf_mancanti_leggi()` converte le voci vecchie (senza `origine`) in `origine = pagina`.
  Il filtro di `pagine.php` toglie solo le voci `pagina` fuori da `config/pagine.json`; quelle `news`
  le filtra il Task 5.

- [ ] **Passo 1: anteprima di partenza.** Sul sito locale allineato:
  `bin/wordpress-config.sh pagine > /tmp/prima.txt`. Atteso: 28 «invariata».
- [ ] **Passo 2:** estrai le funzioni in `contenuti-lib.php` (richiesto da `pagine-lib.php`), introduci
  `wpf_drupal_files_base()`, l'`origine` nei mancanti, `DRUPAL_FILES_URL=` con commento in `.env-example`.
- [ ] **Passo 3:** `php -l` su ogni file toccato.
- [ ] **Passo 4: nessuna regressione.** `bin/wordpress-config.sh pagine`: stesse righe di `/tmp/prima.txt`
  (28 «invariata», stessi LINK e MANCANTE).
- [ ] **Passo 5: variabile letta.** Con `DRUPAL_FILES_URL=http://esempio.invalid/files` nel `.env` e
  `scripts/wordpress-up.sh` di forestas:
  `docker exec wordpress-forestas wp eval 'require "/usr/local/lib/wp-forestas/drupal.php"; echo wpf_drupal_url_file("public://a b.jpg");' --allow-root`
  → `http://esempio.invalid/files/a%20b.jpg`. Poi togli la riga dal `.env`.
- [ ] **Passo 6: elenco vecchio convertito.** `wp option get wpf_sync_mancanti --format=json` prima e
  dopo un giro: le voci restano, con `origine: pagina`.
- [ ] **Passo 7 (dev):** `git commit -m "refactor(oc:8759): parte comune del motore, DRUPAL_FILES_URL e mancanti con provenienza"`

### Task 2: Pagine figlie, schede e pagine d'elenco in `config/pagine.json`

**File:**
- Modifica: `config/pagine.json`, `docker/scripts/config/pagine-lib.php` (`wpf_pagina_scrivi`,
  `wpf_pagina_percorso`), `docker/scripts/config/pagine.php` (validazione del genitore)

**Interfacce:**
- Campi nuovi di una voce: `genitore` (chiave di un'altra voce), `scheda` (int, ordine della scheda),
  `pulsante` (`{ "it": "testo", "en": "testo", "verso": "<chiave>" }`). Meta delle pagine:
  `_wpf_scheda` (ordine) e `_wpf_pulsante` (json del pulsante con l'id della pagina di destinazione
  risolto al momento della lettura, non salvato).
- `post_parent` = id della pagina del genitore nella stessa lingua; le voci con genitore si scrivono
  dopo il genitore (ordinamento topologico semplice: prima le voci senza genitore).
- `wpf_pagina_percorso()` restituisce `/news/attivita-e-comunicazioni/` per una figlia.

Voci da aggiungere (senza nid: su Drupal sono View; testata da `news`, come fa Cammini con `testata_da`):

| chiave | genitore | slug it / en | scheda | pulsante | titolo it / en |
|---|---|---|---|---|---|
| `news-attivita-e-comunicazioni` | `news` | `attivita-e-comunicazioni` / `activities-and-communications` | 1 | «Scopri tutte le attività» → `attivita-e-comunicazioni` | Attività e comunicazioni / Activities and communications |
| `news-news` | `news` | `news` / `news` | 2 | «Scopri tutte le news» → `tutte-le-news` | News / News |
| `attivita-e-comunicazioni` | — | `attivita-e-comunicazioni` / `activities-and-communications` | — | — | Attività e comunicazioni / Activities and communications |
| `tutte-le-news` | — | `tutte-le-news` / `all-news` | — | — | Tutte le news / All news |

I testi dei pulsanti si prendono 1 a 1 dalle View/blocchi di Drupal (`config/sync`, blocchi della
pagina nid 1084) prima di scriverli: quelli in tabella sono segnaposto da sostituire.

- [ ] **Passo 1:** aggiungi i campi e le voci; `pagine.php` si ferma con un errore chiaro se un
  `genitore` non esiste o forma un ciclo.
- [ ] **Passo 2:** `php -l`; `bin/wordpress-config.sh pagine`: 8 «creata» nuove, le 28 di prima
  «invariata».
- [ ] **Passo 3:** `pagine --conferma`, poi
  `wp post list --post_type=page --fields=ID,post_name,post_parent --allow-root | grep -E 'news|tutte|attivita'`:
  le due schede hanno `post_parent` = id di News nella loro lingua.
- [ ] **Passo 4:** secondo `pagine`: tutto «invariata».
- [ ] **Passo 5 (dev):** `git diff config/`, poi `git commit -m "feat(oc:8759): schede della pagina News e pagine d'elenco"`

### Task 3: Lettura delle news dalla copia Drupal

**File:**
- Modifica: `docker/scripts/config/drupal.php`

**Interfacce prodotte:**
- `WPF_DRUPAL_TABELLE` += `node__field_data`, `node__field_date`, `node__field_top_image_media`,
  `node__field_file_media`, `node__field_tipologia`, `node__field_tag_news`, `node__field_in_evidenza`,
  `node__field_collegamenti`, `node__field_references`, `taxonomy_term_field_data`.
- `wpf_drupal_news_elenco( mysqli $db ): array` — `[ nid => [ 'langcode' => 'it'|'und', 'status' => bool, 'lingue' => ['it', 'en'?] ] ]`
  per tutti i nodi `news` con `default_langcode = 1`.
- `wpf_drupal_news( mysqli $db, int $nid, string $lingua ): ?array` — stessa forma di
  `wpf_drupal_pagina` (`titolo`, `body`, `formato`, `sottotitolo`, `status`, `immagine`, `allegati`,
  `galleria`) più: `data` (`AAAA-MM-GG` da `field_data`), `data_ordine` (int, `field_date`, 0 se
  manca), `tipologia` (tid|null), `tag` (`[ [tid, vid] ]`), `in_evidenza` (bool),
  `collegamenti` (`[ [titolo, url] ]`, `uri` Drupal com'è), `riferimenti` (`[ [bundle, nid] ]`),
  `alias` (ultimo pezzo dell'alias, null se non c'è).
- `wpf_drupal_termini( mysqli $db, array $tid ): array` — `[ tid => [ 'vid', 'nome' => [it, en?], 'peso' ] ]`;
  un tid che non esiste più (es. 3905 su 2 news) non c'è nell'elenco.

- [ ] **Passo 1:** scrivi le funzioni.
- [ ] **Passo 2: verifica sui dati** (tunnel aperto):
  `wp eval '... $db=wpf_drupal_connessione(); $e=wpf_drupal_news_elenco($db); echo count($e), " ", count(array_filter($e, fn($n)=>in_array("en",$n["lingue"])));'`
  → `95 4` (95 nodi italiani o `und`, 4 con l'inglese).
- [ ] **Passo 3:** `wpf_drupal_news($db, 1040, 'it')`: titolo «R.E.S.: inaugurazione dei nuovi sentieri di Oliena»,
  `riferimenti` = `[['ente_istituzione_societa', 1044]]`; `wpf_drupal_news($db, 1094, 'it')['alias']` = null;
  le news 388, 389, 575 hanno `immagine` null.
- [ ] **Passo 4 (dev):** `git commit -m "feat(oc:8759): lettura delle news dalla copia Drupal"`

### Task 4: Termini: categoria «news», tipologie, tag, `tipologia_da_sapere`

**File:**
- Crea: `docker/scripts/config/news-lib.php` (prima parte)
- Crea: `themes/forestas-child/inc/news.php` (prima parte), modifica `themes/forestas-child/functions.php`

**Interfacce prodotte:**
- Term meta: `_drupal_tid` e `_drupal_vocabolario`, **non univoci** (`add_term_meta` senza `$unique`):
  un termine fuso porta tutte le coppie. `wpf_termine_per_drupal( int $tid, string $vid, string $tassonomia ): int`
  cerca per la coppia (meta_query su entrambe le chiavi della stessa riga non è possibile: si salva
  anche `_drupal_chiave` = `"{$vid}:{$tid}"`, non univoco, ed è quella la chiave di ricerca).
- `wpf_news_categoria( bool $prova ): int` — categoria `news` (slug `news`, nome «News»), creata se manca.
- `wpf_news_termine( array $t, string $tassonomia, int $parent, string $lingua, bool $prova ): int` —
  ritrova per `_drupal_chiave`, poi per nome nella stessa tassonomia (fusione: confronto con
  `sanitize_title` del nome), altrimenti crea; collega la traduzione inglese in WPML (`tax_{$tassonomia}`)
  solo se Drupal ha il nome inglese.
- Tipologie (`tipologia_news`) → sottocategorie di `news`; `tag_news` e `tags` → `post_tag`, creati solo
  se usati.
- Nel child: `register_taxonomy( 'tipologia_da_sapere', [], [ 'hierarchical' => false, 'public' => true, 'show_in_rest' => true, 'rewrite' => [ 'slug' => 'tipologia-da-sapere' ] ] )`;
  si collega al CPT «Da sapere» con `register_taxonomy_for_object_type` quando oc:8760 lo registra.

- [ ] **Passo 1:** scrivi termini e tassonomia; aggiungi `tipologia_da_sapere` a `config/wpml.json`
  come traducibile (dal pannello WPML, poi `bin/wordpress-config.sh esporta`).
- [ ] **Passo 2:** `wp term list category --fields=term_id,name,parent --allow-root`: dopo il Task 5,
  «News» con 6 figlie; `wp term meta list <id> --allow-root` mostra `_drupal_chiave` `tipologia_news:4734`.
- [ ] **Passo 3:** `wp taxonomy get tipologia_da_sapere --allow-root` risponde.
- [ ] **Passo 4 (dev):** `git commit -m "feat(oc:8759): termini delle news e tassonomia tipologia_da_sapere"` (dopo il Task 5, che li usa)

### Task 5: Scrittura delle news, soglie, giro in `pagine.php`

**File:**
- Modifica: `docker/scripts/config/news-lib.php`, `docker/scripts/config/pagine.php`,
  `docker/scripts/config/pagine-lib.php` (`$trova` dei link), `bin/wordpress-config.sh` (aiuto)

**Interfacce:**
- Costanti: `WPF_NEWS_SOGLIA_BOZZE = 5`; meta `WPF_META_IN_EVIDENZA = 'wpf_in_evidenza'` ('1' o assente),
  `WPF_META_COLLEGAMENTI = 'wpf_collegamenti'` (`[ ['titolo'=>, 'url'=>] ]`, url già riscritto come i link
  del testo), `WPF_META_RIFERIMENTI = '_wpf_riferimenti'` (`[ ['tipo'=>, 'nid'=>] ]`),
  `WPF_META_DATA_ORDINE = '_wpf_data_ordine'` (timestamp di `field_date`, per la scheda Attività
  ordinata come la View `attivita_e_comunicazioni`, block_2), più `_drupal_nid`, `_drupal_langcode`,
  `_wpf_lingua`, `_wpf_testo_drupal`, `wpf_galleria`, `wpf_allegati`, `_thumbnail_id` come le pagine.
- Tipi dei riferimenti: `ente_istituzione_societa` → `ente`, `sentiero` → `sentiero`, `itinerario` →
  `itinerario`, `poi` → `poi`; un altro bundle → riga `AVVISO` e non si salva.
- `post_date` = `field_data` alle 12:00 ora di Roma (`post_date_gmt` di conseguenza): mai una data
  futura, quindi niente stato `future`.
- `post_name` = `alias` di Drupal; con alias null (nid 1094) `sanitize_title( titolo )`. Se WordPress
  cambia lo slug: `MANCANTE news <nid>/<lingua> slug`.
- `wpf_news_sincronizza( mysqli $db, array $alias, callable $trova, bool $prova, bool $solo_mancanti, bool $accetta_bozze, array $solo, callable $riga ): array`
  — restituisce `[ 'conteggi' => [], 'mancanti' => [] ]` nella forma del Task 1.
- Righe di log: `NEWS <nid>/<lingua>: <esito> (campi)` come `PAGINA`.
- Protezioni, prima di scrivere qualsiasi news:
  1. news da mettere in bozza = nodi spubblicati + post `_drupal_nid` pubblicati assenti dall'elenco;
     se sono più di 5 e manca `--accetta-bozze`: `ERRORE copia Drupal sospetta: N news da mettere in bozza …` e
     uscita senza scrivere news (le pagine del giro restano scritte: è un altro perimetro).
  2. news pubblicate nella copia < news pubblicate in WordPress − 5 → stesso errore, con il
     messaggio «se la copia è in ricarica riprova più tardi; se su Drupal sono state tolte davvero:
     pagine --accetta-bozze». Anche questo si supera solo con `--accetta-bozze`. Per provarlo,
     `WPF_NEWS_PROVA_CONTEGGIO=<n>` forza il numero letto dalla copia (solo in anteprima).
- `$trova` dei link conosce anche le news del giro: un link verso `/news/<x>` o `/node/<nid>` di una
  news riscrive al percorso `/news/<slug>/`.
- Inglese: le 4 news con `en` → post inglese collegato con WPML (`post_post`), termini nella versione
  inglese se esiste, altrimenti nessun termine (il ripiego mostra l'italiano). Le altre: solo il post
  italiano; `config/wpml.json` con `post` in modalità «traducibile, usa la traduzione se c'è» (ripiego).

- [ ] **Passo 1:** scrivi `wpf_news_sincronizza` e chiamala in `pagine.php` dopo le pagine, dentro lo
  stesso lock; mancanti con `origine = news` (le voci di nid non più nella copia escono dall'elenco).
- [ ] **Passo 2:** anteprima: `bin/wordpress-config.sh pagine` → 99 righe `NEWS … creata`, nessun `ERRORE`;
  2 `AVVISO` per la tipologia 3905 che non esiste più.
- [ ] **Passo 3:** `pagine --conferma` (tunnel aperto, lontano dalle 10:00 se su UAT). Poi:
  `wp post list --post_type=post --lang=all --format=count --allow-root` → **99**;
  `wp post meta get <id 1040> _wpf_riferimenti --format=json` → `[{"tipo":"ente","nid":1044}]`.
- [ ] **Passo 4: idempotenza.** Secondo `pagine`: tutte «invariata», nessun `MEDIA … da scaricare`.
- [ ] **Passo 5: copia sospetta.** `WPF_PROVA=1 WPF_NEWS_PROVA_CONTEGGIO=10` (via `esegui_script`):
  `ERRORE copia Drupal sospetta`, uscita 1.
- [ ] **Passo 6: news assente.** In locale, metti un post news in `publish` con `_drupal_nid` 999999:
  l'anteprima dice `NEWS 999999/it: in bozza — non più nella copia Drupal`. Poi cancella il post di prova.
- [ ] **Passo 7:** `bin/wordpress-config.sh` aiuto: le news fanno parte di `pagine` e di `allinea`;
  `--accetta-bozze` vale per pagine (oltre 2) e news (oltre 5).
- [ ] **Passo 8 (dev):** `git commit -m "feat(oc:8759): import delle news nel giro delle pagine"`

### Task 6: Indirizzi: permalink, precedenza delle schede, redirect

**File:**
- Modifica: `config/sito.json` (`permalink_structure` → `/news/%postname%/`), `themes/forestas-child/inc/news.php`

**Interfacce:**
- Filtro `rewrite_rules_array`: per ogni pagina figlia di News (le pagine con `_wpf_scheda`, lette
  dal DB, non scritte nel codice) aggiunge in testa `^news/<slug>/?$ → index.php?page_id=<id>` e la
  versione `en/`; si rigenera con `flush_rewrite_rules()` a fine `pagine --conferma` se l'elenco
  delle schede è cambiato (confronto con l'opzione `wpf_schede_regole`).
- `pagine.php`: `AVVISO` se una news ha lo slug di una scheda.
- `template_redirect`: `/node/1094` (e `/en/node/1094`) → 301 al post con `_drupal_nid` 1094.
  In generale `/node/<nid>` di una news → 301 al suo post.
- Le categorie e i tag non usano `/news/` come base (`category_base` resta `category`).

- [ ] **Passo 1:** `bin/wordpress-config.sh applica --conferma` per il permalink; scrivi il filtro e il redirect.
- [ ] **Passo 2:** `curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n'` su:
  `/news/`, `/news/news/`, `/news/attivita-e-comunicazioni/`, `/en/news/activities-and-communications/`,
  `/news/res-inaugurazione-dei-nuovi-sentieri-di-oliena/`, `/en/news/res-inaugurazione-dei-nuovi-sentieri-di-oliena/`,
  `/node/1094` → tutte 200 tranne l'ultima, 301 al post.
- [ ] **Passo 3:** le pagine di oc:8758 non cambiano indirizzo: `curl` su `/sentieri/`, `/contatti/`,
  `/en/contacts/` → 200; `wp menu item list` invariati.
- [ ] **Passo 4 (dev):** `git diff config/`, `git commit -m "feat(oc:8759): indirizzi delle news e precedenza delle schede"`

### Task 7: Pagina della news, schede, pagine d'elenco (Impreza e child)

**File:**
- Modifica: `config/post.json`, `config/wpml.json` (export), `themes/forestas-child/inc/news.php`
- Crea: `themes/forestas-child/assets/css/news.css`, `themes/forestas-child/assets/js/schede.js`;
  alza `Version` in `style.css`

**Interfacce:**
- Shortcode `[forestas_schede]`: barra delle schede delle pagine sorelle della pagina corrente (o
  figlie, se la pagina è il genitore), ordinate per `_wpf_scheda`; la scheda corrente con
  `aria-current="page"`; la pagina News mostra la prima scheda. Scorrimento orizzontale sotto 860px.
- Shortcode `[forestas_collegamenti]` (meta `wpf_collegamenti`) e `[forestas_data]` (data come su
  Drupal, `field--node--field-data--news--full.html.twig`).
- Template di Impreza, costruiti dal pannello e poi esportati con `bin/wordpress-config.sh esporta`:
  `template-news` (tipo post, layout di `core.entity_view_display.node.news.full.yml`: prima sezione
  tipologia, titolo, data, sottotitolo, immagine; seconda tag, testo, allegati, collegamenti, galleria;
  niente «Riferimenti»); `template-pagina-scheda` (testata di News, `[forestas_schede]`, griglia, pulsante);
  `template-pagina-elenco` (griglia paginata).
- Griglie, 1 a 1 con le View di Drupal:
  - scheda Attività e comunicazioni = blocco `attivita_e_comunicazioni:block_2`: tipologia 4734,
    9 elementi, ordine `_wpf_data_ordine` decrescente;
  - scheda News = `news_in_evidenza:block_1`: `wpf_in_evidenza` = 1, 4 elementi, ordine per data decrescente;
  - `/tutte-le-news` = `tutte_le_news`: tutte le news, 9 per pagina, data decrescente;
  - `/attivita-e-comunicazioni` = `vista_attivita_e_comunicazioni`: tipologia 4734, 8 per pagina;
    la View non ha ordinamento esplicito: si confronta l'ordine con la pagina live e si sceglie quello
    che coincide (data o `_wpf_data_ordine`), scrivendolo nelle note.
- Card unica con tre varianti (in evidenza con foto, compatta, senza foto), dai template
  `node--news--teaser*.html.twig`; colore della sezione #C2410C. Gli slug inglesi delle schede sono
  quelli del Task 2.

- [ ] **Passo 1:** costruisci template, griglie e card; assegna `template-news` ai post (meta
  `us_content_id` scritto dallo script, come per le pagine, con il nome in `news-lib.php`).
- [ ] **Passo 2:** `bin/wordpress-config.sh esporta`, poi `git diff config/`: solo template, griglie e
  impostazioni WPML attese, nessuna chiave di licenza.
- [ ] **Passo 3: confronto nel browser** con `www.sardegnasentieri.it`, desktop e sotto 600px, anche da
  loggato e dopo lo scroll: news 1040 (riferimento ente non mostrato, collegamenti, allegati), una news
  con galleria, una delle 3 senza immagine, una delle 4 inglesi a `/en/news/…`; le due schede, le due
  pagine d'elenco con la paginazione.
- [ ] **Passo 4:** secondo `pagine`: tutto «invariata» (il template non deve cambiare a ogni giro).
- [ ] **Passo 5 (dev):** `git diff config/`, `git commit -m "feat(oc:8759): pagina della news, schede e pagine d'elenco"`

### Task 8: Ciclo completo da zero e documentazione

**File:**
- Modifica: `docs/howto/ricreare-wordpress-da-zero.md`, `CLAUDE.md` del repo
- Crea: `docs/knowledge/8759-import-delle-news-da-drupal.md`
- Modifica: `docs/features/8759-…/notes.md` (divergenze, bug)

- [ ] **Passo 1:** ciclo completo in locale come da howto (reset, init, applica, pagine con news,
  allinea): `allinea` esce con 0 e dice «allineato».
- [ ] **Passo 2:** aggiorna l'howto: le news nel giro, i conteggi attesi (99 post), `DRUPAL_FILES_URL`
  e il prerequisito della cartella dei file allo spegnimento di Drupal, la soglia di 5 e `--accetta-bozze`.
- [ ] **Passo 3:** pagina di conoscenza (come funziona, perché così, come ci siamo arrivati) e voce
  nell'indice del `CLAUDE.md`, passando da `wm-context-guard`.
- [ ] **Passo 4 (dev):** `git commit -m "docs(oc:8759): import delle news, howto e conoscenza"`
