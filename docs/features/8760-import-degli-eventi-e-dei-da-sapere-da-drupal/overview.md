> Ticket: oc:8760

# Import degli eventi e dei «da sapere» da Drupal

## Cosa cambia

Il WordPress di Forestas importa dalla copia SQL di Drupal i due tipi di contenuto editoriale che
ancora mancano: gli **eventi** (11, più 3 traduzioni inglesi) e i **«da sapere»** (72, più 49
traduzioni inglesi). Ciascuno diventa un tipo di contenuto (CPT) proprio. La sincronizzazione
quotidiana li tiene allineati come già fa con pagine e news.

L'import delle news (oc:8759) diventa prima **generico**: `pagine.php` scorre un elenco di import
(news, eventi, «da sapere») e il ciclo comune (controlli, termini, lingue, link, log, mancanti,
bozze) sta in una funzione sola. Ogni tipo aggiunge solo ciò che ha di suo.

Lato sito:

- **Eventi**: scheda «Eventi» nella pagina News (`/news/eventi`, pulsante «Scopri tutti gli
  eventi»), pagina d'elenco `/eventi` come su Drupal (dal più recente, 8 per pagina), card con la
  data grande del Canvas, pagina dell'evento con date, luogo, testo, allegati, galleria e
  collegamenti.
- **Da sapere**: la pagina `/da-sapere` diventa a schede come nel wireframe (Canvas Fase 2,
  tavola 8): «Come organizzare l'escursione» (la pagina stessa), «Norme di comportamento»,
  «Pillole di sentieristica» (riquadri verso le altre 6 tipologie) e «FAQ» (elenco di titoli),
  ciascuna al suo indirizzo. Ogni tipologia ha la sua pagina d'elenco. Card unica del sito con il
  colore di sezione `#0F6E6A`.
- **Editor**: la redazione può creare in WordPress un evento o un «da sapere» nuovo con gli stessi
  campi di quelli importati; le tipologie dei «da sapere» hanno immagine e ordine, le pagine colore
  tematico e testata, come nei form di Drupal.

Allineamenti con Drupal emersi dai controlli del 29/10 (dettagli in `notes.md`, «Decisioni»):

- **Filtri** come le faccette di Drupal su `/attivita-e-comunicazioni/`, `/tutte-le-news/` ed `/eventi/`,
  con la disposizione delle liste di Drupal; blocchi «in evidenza» a due colonne uguali.
- **Pagine delle tipologie** con la testata di Drupal (immagine e descrizione della tipologia) e la
  griglia di card con la foto a sinistra.
- **Vecchi indirizzi**: alias delle pagine (`/pagina-base/…`, `/tutti-gli-eventi`) e delle tipologie
  (`/taxonomy/term/<tid>`) portano alla pagina nuova con un 301.

## Perché

La richiesta del cliente è portare nel WordPress i contenuti del sito Drupal, che verrà spento:
«Drupal non esisterà più, anche il container con l'SQL sparirà» (Giuseppe, scrum 27/10). Allo scrum
del 28/10 Giuseppe ha indicato di passare agli eventi dopo le news («switcha sugli eventi»), con una
scheda in più nella pagina News. I «da sapere» completano la parte editoriale: la pagina `/da-sapere`
oggi ha solo la testata, e 4 link delle pagine statiche (FAQ, App, Crediti IT ed EN) restano «in
attesa» finché quei contenuti non esistono.

Il perimetro, deciso il 28/10, esclude carte e zone geografiche: sono la tassonomia del catasto e
arriveranno con il plugin (descrizione del ticket, «Perché carte e zone sono escluse»).

## Requisiti

### Import generico
- [x] `pagine.php` percorre un elenco di import invece di conoscere le news per nome (lettura,
      percorsi dei link, filtro dei mancanti, chiamata, riassunto)
- [x] Il ciclo delle news (`wpf_news_sincronizza`) diventa il ciclo generico `wpf_import_sincronizza` per i tipi con nid Drupal; le news ne usano
      la versione generica con la loro parte specifica (categoria News, riferimenti, `post`)
- [x] I media del contenuto restano nella costante `WPF_CONTENUTO_MEDIA`: eventi e «da sapere» hanno gli
      stessi tre media di news e pagine (notes.md, task 2)
- [x] Il riassunto di `bin/wordpress-config.sh` conta i tipi di riga senza elencarli a mano
- [x] Le tabelle Drupal di eventi e «da sapere» entrano nel controllo di integrità comune
- [x] Non regressione: un giro di `pagine` in prova dopo il refactoring dà tutte le news
      «invariate»; in più, sul WordPress locale e mai su UAT, prima e dopo il refactoring: news
      messa in bozza a mano (il giro la ripubblica), news cancellata (il giro la ricrea), giro con
      `--contenuti-mancanti`, contatore dei mancanti; output confrontato riga per riga
- [x] La soglia delle bozze è un parametro di ciascun tipo nell'elenco di import
- [x] `evento` e `da_sapere` traducibili in `config/wpml.json` (`custom_posts_sync_option`, stesso
      valore di `post`); l'howto verifica che lo siano prima del primo giro di `pagine`
- [x] `FORESTAS_REGOLE_VERSIONE` alzata: le regole dei CPT si rigenerano al primo giro o alla prima
      pagina del pannello dopo il deploy

### Eventi
- [x] CPT evento con indirizzo come su Drupal (alias `evento/<titolo>`), traducibile con WPML,
      `post_tag` associato
- [x] Importati titolo, testo, sottotitolo, data di inizio e di fine, luogo (indirizzo, località,
      due righe di testo), immagine principale, allegati, galleria, collegamenti, «In evidenza», tag,
      traduzioni inglesi
- [x] `field_tag_evento` si importa in `post_tag` (sono termini dei vocabolari `tags` e `tag_eventi`:
      il «5016» dell'evento 5106 è il tag «eventi», non l'itinerario — notes.md, task 5)
- [x] I link dei testi verso contenuti che non esistono in WordPress restano verso Drupal «in
      attesa», con origine propria in `wpf_sync_mancanti`
- [x] Scheda «Eventi» della pagina News (`/news/eventi`) con il pulsante «Scopri tutti gli eventi»
- [x] Pagina d'elenco `/eventi`: ordine per data di inizio dal più recente, 8 per pagina, paginata;
      come su Drupal mostra anche gli eventi passati
- [x] Nell'editor la data di inizio è obbligatoria (senza, l'evento resta in bozza con un avviso) e
      la data di fine non può precedere l'inizio; le query degli elenchi non escludono un evento senza
      data, che va in fondo
- [x] Card con la data grande (variante della card unica) e pagina dell'evento come su Drupal
      (`evento--full`): luogo, date, testo, allegati, collegamenti, galleria

### Da sapere
- [x] CPT «da sapere» con indirizzo come su Drupal (alias `da-sapere/<titolo>`), traducibile con
      WPML, con `tipologia_da_sapere` e `post_tag` associati
- [x] Importati titolo, testo, sottotitolo, tipologie (tutte: un contenuto può averne più d'una), tag,
      data di creazione e «Sempre in cima», immagine principale (dal media: il legacy ha solo doppioni),
      allegati, galleria, traduzioni inglesi; importati i 9 termini di `tipologia_da_sapere`, uno per le
      due lingue (ripiego di WPML: su Drupal solo «F.A.Q.» è tradotta, con lo stesso nome; notes.md,
      task 6), con descrizione, immagine, spunta e peso
- [x] In inglese tipologia e pagina della tipologia si mostrano con il ripiego di WPML; un contenuto
      italiano senza traduzione resta marcato `lang="it"`
- [x] Pagina `/da-sapere` a 4 schede come nel wireframe: la prima scheda è la pagina stessa, le
      altre stanno in `/da-sapere/norme-di-comportamento/`, `/da-sapere/pillole-di-sentieristica/`,
      `/da-sapere/faq/`; ogni scheda ha il pulsante del wireframe verso la pagina della tipologia
- [x] Le schede riprendono i blocchi della pagina Drupal (nid 1083): «Come organizzare
      l'escursione» = tipologia 4709 (`sa_sapere_in_evidenza`); «Norme di comportamento» = tipologia
      4705; «Pillole di sentieristica» = le tipologie con la spunta «Mostra in lista Da sapere»
      (`da_sapere_le_altre_categorie`), in ordine di peso; «FAQ» = tipologia 4710
- [x] La spunta «Mostra in lista Da sapere» (`field_mostra_in_lista_da_sapere_`) si importa come term
      meta di `tipologia_da_sapere`, modificabile dalla schermata del termine: le pillole non sono un
      elenco fisso (oggi la spunta è sulle 6 tipologie del wireframe)
- [x] Pagina d'elenco per ciascuna delle 9 tipologie, come le pagine dei termini di Drupal
- [x] Pagina del «da sapere» come su Drupal (template predefinito del nodo, non ha un `--full` suo)
- [x] I 4 «da sapere» linkati dalle pagine statiche (nid 1418, 1041, 1414, 1491) non sono più «in
      attesa» dopo l'import

### Comune
- [x] Redirect `/node/<nid>` e dei vecchi alias anche verso eventi e «da sapere», solo per gli indirizzi
      che Drupal aveva (meta `_wpf_alias_drupal` di ogni contenuto): un altro 404 resta 404
- [x] Redirect 301 dai vecchi indirizzi Drupal di tutte le pagine pubblicate (`/pagina-base/…`,
      `/node/<nid>`), anche delle schede di News costruite in WordPress senza nodo:
      `/tutti-gli-eventi` → `/news/eventi/`, `/pagina-attivita-e-comunicazioni` →
      `/news/attivita-e-comunicazioni/` (campo `sostituisce` di `config/pagine.json`)
- [x] Filtri come le faccette di Drupal a sinistra della lista, su `/attivita-e-comunicazioni/` (Tipologia,
      Anno; lista a due colonne senza foto), `/tutte-le-news/` (Tipologia = tag, senza numeri; Anno) ed
      `/eventi/` (Tipologia = tag; Anno; lista a una colonna di card orizzontali): campi `filtri` e `colonne`
      di `config/pagine.json`
- [x] Ogni pagina d'elenco o a schede confrontata con Drupal online su filtri, disposizione, contenuti e
      ordine, indirizzi, testata e stile (elenco in `notes.md`, «Confronto con Drupal»)
- [x] Pagina della tipologia come su Drupal: testata con immagine e descrizione della tipologia, percorso
      con la sola «Home», griglia di card con la foto a sinistra su tre colonne; i vecchi indirizzi
      (`/taxonomy/term/<tid>`) portano lì; immagine e ordine si cambiano dalla schermata della tipologia
- [x] Ogni campo dei moduli di Drupal per news, eventi, «da sapere», pagine, media e tipologie si importa e
      si compila nell'editor di WordPress, salvo le eccezioni in `notes.md`
- [x] Le pagine hanno nell'editor «Colore tematico» e «Testata» (riquadro «Colore e testata»), come i campi
      «Colore tematico» e «Layout» del form della pagina di Drupal
- [x] Riquadro dell'editor, percorso, testata e card riconoscono i due CPT: la redazione può creare
      un evento (date, luogo, allegati, galleria, collegamenti) e un «da sapere» (tipologia, tag,
      allegati, galleria) nuovi, salvati negli stessi meta dell'import
- [x] Le protezioni sulla copia valgono anche per i due CPT: con `DRUPAL_SYNC=on` una modifica dal
      pannello a un contenuto importato si perde al giro successivo, come per le news
- [x] Testi dell'interfaccia in italiano e inglese con `forestas_testo()` (il child non ha file di
      traduzione); date nel formato della lingua (`30 giu` / `30 Jun`)
- [x] `docs/howto/ricreare-wordpress-da-zero.md` aggiornato con i passi, gli avvisi attesi e la data
      di verifica
- [x] [UX] La data grande della card è decorativa (`aria-hidden`, il giorno di inizio come nel Canvas);
      la data completa, o l'intervallo di un evento su più giorni, è un `<time datetime>` sotto il titolo,
      ed è quella che legge il lettore di schermo
- [x] [UX] Schede come link in un `<nav>` con `aria-current="page"`, scheda attiva non indicata dal
      solo colore; card con un solo link sul titolo e immagine decorativa; titoli senza salti di
      livello (stesse regole di oc:8759)
- [x] [UX] `#0F6E6A` solo su filetti e elementi grafici (6,07:1 sul bianco); il testo resta
      `#4D4D4D`

## Rischi

- **Regressione sulle news**: il refactoring tocca l'import che gira su UAT, e il giro in prova su
  una copia che non cambia passa solo dal ramo «invariato»: bozze, traduzioni nuove, link risolti e
  news nuove si romperebbero senza segnali fino al primo cambiamento su Drupal. Mitigazione: i casi
  provocati a mano sul WordPress locale, prima e dopo, con l'output confrontato (requisito).
- **Traduzioni scollegate su un sito ricreato**: oggi `config/wpml.json` non rende traducibili i due
  CPT, e le traduzioni inglesi verrebbero create senza legame con l'italiano. Mitigazione: i CPT in
  `custom_posts_sync_option` e il controllo nell'howto.
- **Evento della redazione senza data**: l'ordinamento su meta lo escluderebbe dagli elenchi senza
  avviso. Mitigazione: data obbligatoria nell'editor e query che non escludono chi non ha il meta.
- **Indirizzi che si sovrappongono**: i «da sapere» stanno in `/da-sapere/<titolo>` e le schede in
  `/da-sapere/<scheda>`. Mitigazione: la stessa regola di precedenza delle schede di News, estesa a
  `/da-sapere/`, e l'AVVISO dello script per un contenuto con lo slug di una scheda.
- **Termini e lingue**: le tipologie sono un termine per le due lingue; un «da sapere» inglese mostra la
  tipologia italiana. Mitigazione: il nome del termine ha `lang="it"` nella pagina inglese (notes.md, task 6).
- [UX] **Data in forma abbreviata**: la data grande «30 GIU» è nascosta al lettore di schermo, che legge
  la data completa sotto il titolo.

## Out of scope

- Carte e zone geografiche (`zona_geografica`, `/carte`, `/carte/<zona>`): ticket separato. I link
  verso le zone restano «in attesa».
- Homepage e i suoi blocchi «in evidenza» (eventi, «da sapere»): ticket separato, quando tutti i
  contenuti che mostra sono in WordPress.
- Mostrare i riferimenti a contenuti del catasto e agli enti: li mostrerà il plugin con le API dello
  shard.
- Commenti dei «da sapere»: il form li prevede ma sulla copia Drupal non ce n'è nessuno (l'unico
  commento è un «Commento di prova» del 2013 sull'itinerario nid 75).
- Posizione del percorso: su Drupal sta sopra la testata, qui sotto in tutto il sito (oc:8758).
- Inserire e mostrare i riferimenti delle news: li gestirà il plugin con le API dello shard (follow-up
  in `notes.md`).
- Permessi per tipo come su Drupal (es. «Caporedattore DA SAPERE» che crea solo «da sapere»): in
  WordPress i due CPT usano i permessi standard dei post.

## Moduli toccati

Tutto nel submodule `wp-forestas` (in `forestas` cambia solo il puntatore del submodule, a cura del
dev).

- `docker/scripts/config/pagine.php` — elenco di import, vecchi indirizzi delle pagine
- `docker/scripts/config/import-lib.php` — nuovo: ciclo generico, termini e tag comuni
- `docker/scripts/config/import-tipi.php` — nuovo: elenco dei tipi e controllo dei descrittori
- `docker/scripts/config/contenuti-lib.php` — meta dei vecchi indirizzi, termini nell'ordine per le tassonomie con `sort`
- `docker/scripts/config/news-lib.php` — parte specifica delle news
- `docker/scripts/config/eventi-lib.php`, `docker/scripts/config/da-sapere-lib.php` — nuovi (con
  descrizione, immagine e indirizzi delle tipologie)
- `docker/scripts/config/drupal.php` — elenco dei nodi per bundle, lettura di eventi, «da sapere» e
  tipologie
- `docker/scripts/config/media.php` — file senza estensione e KML
- `docker/scripts/config/pagine-lib.php` — etichetta della scheda, vecchi indirizzi Drupal delle pagine
- `bin/wordpress-config.sh` — riassunto
- `config/pagine.json` — scheda Eventi, pagina `/eventi`, schede di Da sapere, nodi Drupal rimpiazzati
  dalle schede di News (`sostituisce`), filtri e colonne delle liste
- `config/post.json`, `config/wpml.json` — template e traducibilità dei due CPT
- `config/impreza.json` — template di Impreza dei due CPT anche per i contenuti creati dal pannello
- `themes/forestas-child/` — CPT (`inc/contenuti.php`), eventi (`inc/eventi.php`), «da sapere» e
  tipologie (`inc/da-sapere.php`, `taxonomy-tipologia_da_sapere.php`), card e corpo comuni
  (`inc/contenuti.php`), griglie e filtri (`inc/griglie.php`, `assets/js/filtri.js`), schede (`inc/news.php`), testata, percorso e redirect (`inc/pagine-statiche.php`),
  riquadri dell'editor (`inc/pagine-statiche-admin.php`), CSS
- `themes/forestas-child/functions.php` — carica i file nuovi
- `docs/howto/ricreare-wordpress-da-zero.md`, `docs/howto/cron-sincronizzazione-pagine.md`,
  `docs/knowledge/`, `CLAUDE.md`
- `docs/features/8759-…/notes.md` — messa in opera su UAT del 27/10, su richiesta della sessione di oc:8759
