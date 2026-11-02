> Ticket: oc:8760

# Import degli eventi e dei «da sapere» da Drupal — piano di implementazione

> **Per chi esegue:** sotto-skill `superpowers:subagent-driven-development` o
> `superpowers:executing-plans`, task per task. **Nessun `git commit`, `git add` o `git push`**: i
> commit indicati sono istruzioni per il dev, che committa lui.

**Obiettivo:** importare dalla copia Drupal eventi e «da sapere» come due CPT di WordPress, con le
loro pagine come su Drupal e sul wireframe, dopo aver reso generico l'import delle news.

**Architettura:** `pagine.php` percorre un elenco di tipi di import; ogni tipo è un descrittore
(array) con la sua lettura da Drupal, i suoi termini e la sua scrittura, e il ciclo comune
(controlli, lingue, link, log, mancanti, bozze) sta in `import-lib.php`. Nel child i due CPT si
registrano in un file proprio; card, elenchi, schede e riquadro dell'editor si estendono invece di
essere copiati.

**Stack:** WordPress + Impreza + WPML, WP-CLI, PHP 8.3, copia SQL di Drupal (MariaDB) in sola lettura.

**Spec:** [overview.md](overview.md)

## Vincoli globali

- Tutto nel submodule `wp-forestas`, branch `feature/oc-8760-import-degli-eventi-e-dei-da-sapere-da-drupal`.
- Verifiche **solo sul WordPress locale** (container `wordpress-forestas`), con la copia Drupal di UAT
  raggiunta dal tunnel sulla 3307 (`DRUPAL_DB_HOST=host.docker.internal`): mai giri con `--conferma`
  su UAT. Il tunnel si chiude a fine lavoro: `pkill -f "3307:127.0.0.1:3307"`.
- Non esiste una suite di test PHP: il «test» di ogni task è un giro di
  `bin/wordpress-config.sh pagine` (anteprima, poi `--conferma`), WP-CLI (`wp eval`, `wp post list`,
  `wp rewrite list`) e la pagina nel browser.
- Riferimento di design: Drupal 1:1 (codice in `/Volumes/Crucial X6 1/Siti Webmapp/Wordpress/Forestas/Drupal sardegnasentieri/sardegnasentieri`,
  template in `docroot/themes/custom/sardegnasentieri/templates/content/`) e, per Da sapere, il
  wireframe Canvas Fase 2 tavola 8. Colore di sezione Da sapere `#0F6E6A`, testo `#4D4D4D`.
- Testi dell'interfaccia con `forestas_testo( $italiano, $inglese )`; nessun file di traduzione.
- Commenti, documentazione e messaggi di commit in italiano. Commit: `refactor(oc:8760): …`,
  `feat(oc:8760): …`, `fix(oc:8760): …`.
- Repo pubblico: `git diff config/` prima di ogni commit (trappola del `CLAUDE.md`).
- Regole del child cambiate → `FORESTAS_REGOLE_VERSIONE` alzata.

## Punti da guardare in review

1. **News dopo il refactoring**: un giro deve dare per le news esattamente le righe di prima, in tutti
   i casi del task 1 (non solo «invariato»). Coperto dal task 3.
2. **`/da-sapere/<scheda>/` contro `/da-sapere/<contenuto>/`**: le quattro schede devono aprirsi e un
   «da sapere» con lo slug di una scheda dà un AVVISO. Coperto dal task 4 e dal task 8.
3. **Inglese**: un «da sapere» inglese mostra la tipologia inglese (l'unica tradotta), e un contenuto
   senza traduzione non compare nelle liste inglesi come se fosse inglese. Coperto dal task 6 e dal task 8.
4. **Evento senza data creato dalla redazione**: non si pubblica, e se esiste non sparisce dagli elenchi.
   Coperto dal task 9.
5. **Seconda pagina di `/eventi`** (`/eventi/page/2/` o il parametro di paginazione usato da
   `/tutte-le-news`): mostra gli eventi 9-11. Coperto dal task 7.

---

### Task 1: fotografia delle news prima del refactoring

**File:** nessuno nel repo; i log vanno nello scratchpad della sessione, fuori dal repo.

- [x] **Passo 1:** controllare che il locale sia sincronizzabile: `DRUPAL_SYNC=on` nel `.env` di
  `wp-forestas`, tunnel aperto (`nc -z 127.0.0.1 3307`), un giro `bin/wordpress-config.sh pagine
  --conferma` che chiude con `RIASSUNTO` senza `ERRORE`.
- [x] **Passo 2:** registrare cinque giri, salvando ogni log (il percorso lo stampa lo script):
  - A. anteprima pulita: `bin/wordpress-config.sh pagine` → atteso: news tutte «invariato»;
  - B. una news (scegliere un nid con traduzione inglese) messa in bozza dal pannello o con
    `wp post update <id> --post_status=draft`, poi `pagine --conferma` → atteso: torna pubblicata;
  - C. la stessa news cancellata con `wpf_wpml_cancella_post` (via `wp eval`), poi `pagine --conferma`
    → atteso: ricreata, con la traduzione collegata;
  - D. `pagine --contenuti-mancanti` (anteprima) → atteso: l'elenco dei mancanti delle news;
  - E. `WPF_NEWS_PROVA_CONTEGGIO=1` in anteprima → atteso: `ERRORE` copia sospetta, nulla scritto.
- [x] **Passo 3:** salvare anche `wp option get wpf_sync_mancanti --format=json`.

### Task 2: ciclo di import generico e news come primo tipo

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-descrittore-diverso-da-quello-del-piano), [nomi delle funzioni](notes.md#task-2-nomi-delle-funzioni-e-dei-file), [media](notes.md#task-2-media-e-avviso)

> Si fa solo il refactoring: nessun tipo nuovo finché il task 3 non è verde.

**File:**
- Create: `docker/scripts/config/import-lib.php`
- Modify: `docker/scripts/config/news-lib.php` (il ciclo esce da `wpf_news_sincronizza`, che resta come
  descrittore + funzioni proprie)
- Modify: `docker/scripts/config/pagine.php` (righe 116-120, 186-190, 203-209, 274, 291-297)
- Modify: `docker/scripts/config/contenuti-lib.php` (`wpf_contenuto_scrivi`, `WPF_CONTENUTO_MEDIA`)
- Modify: `docker/scripts/config/drupal.php` (`wpf_drupal_news_elenco`)
- Modify: `bin/wordpress-config.sh` (riga 210)

**Interfacce prodotte:**
- `wpf_import_tipi(): array` in `import-lib.php` — elenco ordinato dei descrittori. Ogni descrittore:
  `origine` (string: `news`, `evento`, `da_sapere`, chiave di `wpf_sync_mancanti`), `riga` (string:
  tipo di riga nel log, `NEWS`, `EVENTO`, `DA_SAPERE`), `bundle` (string Drupal), `post_type`,
  `soglia_bozze` (int; news 5), `template` (chiave del content template Impreza), `leggi`
  (callable `( mysqli, array $elenco ): array $dati[nid][lingua]`), `esistenti` (callable `(): array
  [nid][lingua] => WP_Post`, che filtra per `_drupal_nid` e per `post_type`), `termini` (callable
  `( mysqli, array $dati, bool $prova ): array` con `righe`, `mancanti` e la mappa dei termini),
  `prepara` (callable `( array $d, array $alias, callable $trova, string $lingua ): array` con `d` e
  `link` aggiuntivi, per le news i collegamenti), `scrivi` (callable con la firma attuale di
  `wpf_news_scrivi`), `slug` (callable `( array $d ): string`).
- `wpf_import_sincronizza( array $tipo, mysqli $db, array $elenco, array $dati, array $alias, callable $trova, array $mancanti, ?array $solo, bool $prova, bool $accetta_bozze, callable $riga ): array`
  — stesso ritorno di oggi: `conteggi`, `mancanti`, `previsti`, `errore`. Corpo = l'attuale
  `wpf_news_sincronizza` con `'news'`, `NEWS`, la soglia e le funzioni sostituite dal descrittore.
  L'AVVISO sullo slug di una scheda usa l'elenco delle schede del genitore del tipo.
- `wpf_import_percorsi( array $tipo, array $dati ): array` — generalizza `wpf_news_percorsi`.
- `wpf_drupal_bundle_elenco( mysqli $db, string $bundle ): array` — generalizza
  `wpf_drupal_news_elenco` (che resta come chiamata a `wpf_drupal_bundle_elenco( $db, 'news' )` o sparisce).
- `wpf_contenuto_scrivi( $c, … )`: i media si leggono da `$c['media']` (`[ chiave => meta ]`), con
  `WPF_CONTENUTO_MEDIA` come valore predefinito se manca.

- [x] **Passo 1:** spostare il ciclo in `wpf_import_sincronizza` e la variabile d'ambiente di prova:
  `WPF_NEWS_PROVA_CONTEGGIO` resta valida per le news; per gli altri tipi
  `WPF_<ORIGINE>_PROVA_CONTEGGIO` (in maiuscolo).
- [x] **Passo 2:** in `pagine.php` sostituire i cinque punti che nominano le news con un ciclo su
  `wpf_import_tipi()`: lettura di tutti i tipi prima delle pagine; percorsi di tutti i tipi in
  `$percorsi`/`$in_bozza_nodi`; filtro dei mancanti `! in_array( origine, [ 'pagina', ...origini ] )`;
  una chiamata per tipo; nel `RIASSUNTO` una parte per tipo con la stessa forma di oggi
  (`news: invariato: 95`), «non sincronizzate (copia sospetta)» per tipo in errore.
- [x] **Passo 3:** `bin/wordpress-config.sh` riga 210: contare anche le righe dei tipi nuovi senza
  elencarli a mano (per esempio tutte le righe il cui primo campo non è fra quelle di servizio),
  mantenendo `pagine:` e `news:` nell'ordine di oggi.
- [x] **Passo 4:** `php -l` su ogni file toccato, nel container.

### Task 3: non regressione delle news

- [x] **Passo 1:** rifare i cinque giri A-E del task 1, nelle stesse condizioni.
- [x] **Passo 2:** confrontare i log con `diff` dopo aver tolto data e ora delle righe → atteso: nessuna
  differenza sulle righe `NEWS`, `TERMINE`, `MEDIA`, `LINK`, `MANCANTE`, `ERRORE`, e il `RIASSUNTO`
  identico (salvo l'ordine delle parti se cambia, da segnalare nelle note).
- [x] **Passo 3:** confrontare `wpf_sync_mancanti` con quello del task 1 → atteso: identico.
- [x] **Passo 4 (commit del dev):** `refactor(oc:8760): import generico per tipi, news come primo tipo`.

### Task 4: CPT, WPML e regole di rewrite

**File:**
- Create: `themes/forestas-child/inc/contenuti.php` (incluso da `functions.php` accanto a `inc/news.php`)
- Modify: `themes/forestas-child/inc/news.php` (registrazione di `tipologia_da_sapere` alle righe
  35-55, `forestas_regole_schede`, `FORESTAS_REGOLE_VERSIONE` → 3, redirect `/node/<nid>` righe 128-156)
- Modify: `config/wpml.json`

**Interfacce prodotte:** post type `evento` (rewrite `evento`, `has_archive` false, `supports` titolo,
editor, riassunto, immagine in evidenza) e `da_sapere` (rewrite `da-sapere`, `has_archive` false);
`post_tag` su entrambi; `tipologia_da_sapere` su `da_sapere`; costante
`FORESTAS_TIPI_IMPORTATI = [ 'post', 'evento', 'da_sapere' ]` usata da redirect, riquadro, card.

- [x] **Passo 1:** registrare i due CPT su `init` e associare le tassonomie.
- [x] **Passo 2:** le regole delle schede valgono per ogni pagina scheda, non solo per le figlie di
  News: `/da-sapere/<scheda>/` vince su `da_sapere` come `/news/<scheda>/` vince sui post.
- [x] **Passo 3:** il redirect di `/node/<nid>` e dei vecchi alias cerca in `FORESTAS_TIPI_IMPORTATI`
  (per il «da sapere» con alias `gradi_difficoltà/…` vale il redirect dei vecchi alias).
- [x] **Passo 4:** `config/wpml.json`, `impostazioni.custom_posts_sync_option`: `"evento": 2,
  "da_sapere": 2` (come `post`); `bin/wordpress-config.sh applica` in anteprima → atteso: differenza
  solo su quelle due chiavi; poi `applica --conferma`.
- [x] **Passo 5:** verificare `wp post-type list` (i due tipi), `wp rewrite list | grep -E 'evento|da-sapere'`
  (le regole delle schede di Da sapere prima di quella del CPT, dopo il task 8 che crea le schede),
  e in WPML i due tipi «traducibili».

### Task 5: lettura degli eventi da Drupal e import

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-field_tag_evento-punta-a-termini)

**File:**
- Create: `docker/scripts/config/eventi-lib.php`
- Modify: `docker/scripts/config/drupal.php` (`WPF_DRUPAL_TABELLE`, lettura di un evento)
- Modify: `docker/scripts/config/import-lib.php` (descrittore `evento` in `wpf_import_tipi()`, dopo le news)

**Interfacce prodotte:** `wpf_drupal_evento( mysqli $db, int $nid, string $lingua ): ?array` con i
campi di `wpf_drupal_pagina` più `inizio` (AAAA-MM-GG, `field_date_event_single_value`), `fine`
(`field_data_evento_fine_value` o `''`), `luogo` (`[ organizzazione, indirizzo1, indirizzo2,
cap, località, provincia ]` da `field_luogo_evento_*`: `organization`, `address_line1`,
`address_line2`, `postal_code`, `locality`, `administrative_area`), `in_evidenza`, `collegamenti`,
`tag` (tid), `riferimenti` (`[ bundle, nid ]` dei nodi in `field_tag_evento`), `alias`.
Meta WordPress: `wpf_evento_inizio`, `wpf_evento_fine`, `wpf_evento_luogo` (array), più quelli già
esistenti per «in evidenza», collegamenti, riferimenti, allegati, galleria.

- [x] **Passo 1:** aggiungere a `WPF_DRUPAL_TABELLE` `node__field_date_event_single`,
  `node__field_data_evento_fine`, `node__field_luogo_evento`, `node__field_tag_evento`.
- [x] **Passo 2:** descrittore `evento`: `soglia_bozze` 2, `template` `template-evento`, slug
  dall'alias Drupal senza `evento/`. `field_tag_evento` che punta a un nodo (5106 → 5016) va in
  `_wpf_riferimenti`, uno che punta a un termine in `post_tag`.
- [x] **Passo 3:** anteprima `pagine` → atteso: 14 righe `EVENTO … nuovo` (11 it + 3 en), nessun
  `ERRORE`; poi `--conferma` e una seconda anteprima → atteso: 14 «invariato».
- [x] **Passo 4:** controllare l'evento 5106: `wp post meta get <id> _wpf_riferimenti` contiene
  `itinerario`/5016; i 6 link agli itinerari nel testo sono «in attesa» con origine `evento`.
- [x] **Passo 5 (commit del dev):** `feat(oc:8760): import degli eventi da Drupal`.

### Task 6: lettura dei «da sapere», tipologie e termini per lingua

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6-un-termine-per-le-due-lingue), [tipologie multiple e data](notes.md#task-6-tipologie-multiple-data-di-creazione-e-sempre-in-cima)

**File:**
- Create: `docker/scripts/config/da-sapere-lib.php`
- Modify: `docker/scripts/config/drupal.php`, `docker/scripts/config/contenuti-lib.php`,
  `docker/scripts/config/import-lib.php`
- Modify: `themes/forestas-child/inc/news.php` (`forestas_termine_drupal`)

**Interfacce prodotte:** `wpf_drupal_da_sapere( mysqli $db, int $nid, string $lingua ): ?array` con
i campi di `wpf_drupal_pagina` più `tipologia` (tid), `tag` (tid), immagine principale dal media o,
se manca, dal legacy `field_immagine_principale`; term meta `wpf_mostra_in_lista` («1»/assente) da
`field_mostra_in_lista_da_sapere__value`; il peso del termine Drupal in `term_order` o term meta
`wpf_peso`; `forestas_termine_drupal( string $chiave, string $tassonomia, ?string $lingua = null ): ?WP_Term`.

- [x] **Passo 1:** tabelle `node__field_tipo_da_sapere`, `node__field_tag`,
  `node__field_immagine_principale`, `taxonomy_term__field_mostra_in_lista_da_sapere_` in
  `WPF_DRUPAL_TABELLE`.
- [x] **Passo 2:** i 9 termini di `tipologia_da_sapere` con `wpf_termine_ritrova_o_crea`; il termine
  4710 «F.A.Q.» anche in inglese, collegato al termine italiano in WPML.
- [x] **Passo 3:** descrittore `da_sapere`: `soglia_bozze` 5, `template` `template-da-sapere`, slug
  dall'alias senza `da-sapere/`.
- [x] **Passo 4:** anteprima `pagine` → atteso: 121 righe `DA_SAPERE … nuovo` (72 + 49), 9 `TERMINE`;
  `--conferma`, poi anteprima → tutto «invariato».
- [x] **Passo 5:** i 4 «da sapere» linkati dalle pagine statiche (nid 1418, 1041, 1414, 1491): dopo un
  secondo `--conferma` i link di FAQ, App e Crediti IT/EN sono relativi e non più nei `MANCANTE`.
- [x] **Passo 6:** `wp eval` con `forestas_termine_drupal( 'tipologia_da_sapere:4710', 'tipologia_da_sapere', 'en' )`
  → il termine inglese; con `'it'` → quello italiano.
- [x] **Passo 7 (commit del dev):** `feat(oc:8760): import dei «da sapere» e delle tipologie da Drupal`.

### Task 7: eventi nel sito — card, scheda, elenco, pagina

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-7-e-8-pagine-allineate-a-drupal-dopo-il-piano), [ordine con un join](notes.md#task-7-ordine-degli-eventi-con-un-join)

**File:**
- Modify: `themes/forestas-child/inc/news.php` (`forestas_news_card`, `forestas_news_query`)
- Create: `themes/forestas-child/inc/eventi.php` (data, luogo, intervallo)
- Modify: `config/pagine.json`, `config/post.json` (content template `template-evento`), CSS del child

**Interfacce:** `forestas_evento_data( int $id ): string` — markup della data grande con
`<time datetime="AAAA-MM-GG">`, giorno e mese abbreviato visibili (`30 giu` / `30 Jun`) con
`aria-hidden`, data completa (e intervallo se c'è la fine) in testo per i lettori di schermo;
variante `evento` di `forestas_news_card`; in `forestas_news_query` con `tipo` `evento` l'ordine è
per `wpf_evento_inizio` decrescente con una `meta_query` `OR` (`EXISTS` / `NOT EXISTS`) così un evento
senza data non sparisce e va in fondo.

- [x] **Passo 1:** voce `news-eventi` in `config/pagine.json`: `genitore: news`, `testata_da: news`,
  `scheda: 3`, `titolo` «Eventi»/«Events», `pulsante` «Scopri tutti gli eventi»/«See all events» →
  `eventi`, `griglia` `{ "tipo": "evento", "quanti": 3, "variante": "evento" }`, slug `eventi`.
- [x] **Passo 2:** voce `eventi`: `template: elenco`, `testata_da: news`, titolo «Eventi»/«Events»,
  `griglia { "tipo": "evento", "quanti": 8, "paginata": true, "variante": "evento" }`, slug `eventi`
  (Drupal: `lista_eventi`, 8 per pagina, `field_data` decrescente, nessun filtro sulla data).
- [x] **Passo 3:** pagina dell'evento come `evento--full.html.twig`: luogo, date, testo, allegati,
  collegamenti, galleria (riusare `forestas_allegati`, `forestas_galleria`, `forestas_news_collegamenti`).
- [x] **Passo 4:** `pagine --conferma`, poi nel browser: `/news/eventi/`, `/eventi/`, la seconda pagina
  di `/eventi/` (eventi 9-11), un evento con fine (intervallo), le stesse in `/en/`; confronto delle
  misure con il CSS compilato di Drupal.
- [x] **Passo 5 (commit del dev):** `feat(oc:8760): pagine e card degli eventi`.

### Task 8: Da sapere a schede, tipologie, pagina del contenuto

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-7-e-8-pagine-allineate-a-drupal-dopo-il-piano)

**File:**
- Modify: `config/pagine.json`, `config/post.json` (content template `template-da-sapere`)
- Modify: `themes/forestas-child/inc/news.php` (`forestas_news_query`: `tipo` `da_sapere`, tassonomia
  `tipologia_da_sapere`), CSS del child (colore `#0F6E6A` su filetti e scheda attiva)
- Create: `themes/forestas-child/inc/da-sapere.php` (shortcode delle pillole e dell'elenco FAQ, pagina
  della tipologia)

- [x] **Passo 1:** la voce `da-sapere` (nid 1083) diventa `template: scheda`, `scheda: 1`, titolo della
  scheda «Come organizzare l'escursione», `griglia { "tipo": "da_sapere", "tassonomia":
  "tipologia_da_sapere", "tipologia": 4709, … }`, pulsante «Scopri altro su come organizzare
  l'escursione» verso la pagina della tipologia 4709.
- [x] **Passo 2:** voci figlie `genitore: da-sapere`, `testata_da: da-sapere`: `norme-di-comportamento`
  (`scheda 2`, tipologia 4705, «Scopri tutte le norme»), `pillole-di-sentieristica` (`scheda 3`,
  riquadri delle tipologie con `wpf_mostra_in_lista`, in ordine di peso, ciascuno verso la sua
  pagina), `faq` (`scheda 4`, tipologia 4710, elenco di titoli, «Scopri la sezione delle FAQ»).
  Testi e numero di elementi come nel wireframe; numero di elementi della griglia come nelle viste
  Drupal (`sa_sapere_in_evidenza`: 4).
- [x] **Passo 3:** pagina della tipologia come `termine_tassonomia_da_sapere` (10 per pagina,
  `sticky` poi `created` decrescenti), all'indirizzo del termine Drupal
  (`/tipologia-da-sapere-argomenti/<termine>`); in inglese solo contenuti inglesi.
- [x] **Passo 4:** pagina del «da sapere» con il template predefinito del nodo di Drupal (testata,
  testo, allegati, galleria): nessun `node--da-sapere--full` su Drupal.
- [x] **Passo 5:** l'AVVISO dello slug di una scheda (task 2) vale anche per Da sapere.
- [x] **Passo 6:** `pagine --conferma`, `wp rewrite list` (regole delle 3 schede prima di
  `da-sapere/([^/]+)`), poi nel browser le 4 schede, una tipologia con più pagine, un «da sapere» it ed
  en, la tipologia F.A.Q. in inglese; schede in `<nav>` con `aria-current="page"`.
- [x] **Passo 7 (commit del dev):** `feat(oc:8760): pagina Da sapere a schede e pagine delle tipologie`.

### Task 9: editor dei due CPT

**File:**
- Modify: `themes/forestas-child/inc/pagine-statiche-admin.php` (riquadro e salvataggio: righe 19-53,
  112-225), `assets/js/riquadro-pagina.js`
- Modify: `themes/forestas-child/inc/pagine-statiche.php` (`forestas_percorso`, `forestas_sezione_pagina`)

- [x] **Passo 1:** il riquadro compare su `evento` e `da_sapere` (`save_post_evento`,
  `save_post_da_sapere`) con allegati, galleria e, per gli eventi, «in evidenza» e collegamenti.
- [x] **Passo 2:** campi dell'evento: data di inizio, data di fine, luogo (organizzazione, indirizzo,
  seconda riga, CAP, località, provincia) negli stessi meta del task 5. Senza data di inizio il
  salvataggio come «pubblicato» diventa bozza con un avviso
  («Un evento senza data di inizio non si pubblica»); fine prima dell'inizio → stesso trattamento.
- [x] **Passo 3:** con `DRUPAL_SYNC=on` il riquadro avvisa che le modifiche a un contenuto importato si
  perdono al giro successivo, come per le news.
- [x] **Passo 4:** dal pannello: creare un evento senza data → resta bozza con l'avviso; con data →
  compare in `/news/eventi/` e `/eventi/`; creare un «da sapere» con tipologia «Come vestirsi» →
  compare nella pagina della tipologia; un evento creato a mano senza meta (con `wp post create`) →
  in fondo a `/eventi/`, non sparito.
- [x] **Passo 5 (commit del dev):** `feat(oc:8760): editor di eventi e «da sapere»`.

### Task 10: documentazione e giro completo

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-10-ciclo-da-zero-rifatto-dopo-le-review)

**File:** `docs/howto/ricreare-wordpress-da-zero.md` (righe 9, 14, 84-85, 114, 126-129, 165),
`docs/knowledge/` (pagina di conoscenza), `CLAUDE.md` (trappole e indice), `notes.md`.

- [x] **Passo 1:** howto: passi, controllo «i due CPT sono traducibili in WPML» prima del primo giro,
  avvisi attesi, data della verifica.
- [x] **Passo 2:** giro completo dal reset seguendo l'howto in locale; anteprima finale → tutto
  «invariato», nessun `ERRORE`, mancanti attesi solo verso carte, zone e catasto.
- [x] **Passo 3:** chiudere il tunnel verso UAT.
