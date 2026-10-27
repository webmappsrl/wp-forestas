> Ticket: oc:8759

# Notes — Import delle news da Drupal

## Decisioni prese con il dev (pianificazione del 26/10)

1. **Le news sono post nativi di WordPress** con la categoria «news», non un CPT.
2. **Le tipologie Drupal (`tipologia_news`) sono sottocategorie di «news»**: la categoria «news» fa
   da contenitore.
3. **Tag nativi (`post_tag`)** alimentati dai vocabolari `tag_news` e `tags`, creati solo quando una
   news li usa, con term meta dell'id Drupal e del vocabolario di provenienza; termini con lo stesso
   nome fusi in uno.
4. **Sito inglese:** le 4 news tradotte su Drupal diventano traduzioni WPML; le altre si mostrano in
   italiano con il ripiego di WPML.
5. **«In evidenza» (`field_in_evidenza`) si importa come meta**, non come sticky di WordPress.
6. **La pagina News è a schede come nel Canvas Fase 2, Tavola 8** (sezione `s2r`): prima scheda
   Attività e comunicazioni, aperta, poi News. Le schede sono pagine figlie di News
   (`/news/attivita-e-comunicazioni`, `/news/news`), le pagine nuove si aggiungono in
   `config/pagine.json`. I post stanno a `/news/<titolo>`: serve una regola di rewrite che dia la
   precedenza alle pagine figlie di News, più un controllo nello script sugli slug in conflitto, e
   il supporto di `post_parent` nel motore delle pagine. Nel motore si distinguono pagine, post e CPT.
7. **I pulsanti delle schede portano a pagine d'elenco separate, come su Drupal**: `/tutte-le-news`
   (9 per pagina, senza nid Drupal) e `/attivita-e-comunicazioni` (8 per pagina, senza nid Drupal: su Drupal è una View).
8. **La scheda Eventi nasce con oc:8760**; il meccanismo delle schede è generico e pronto per la
   terza.
9. ~~Superata dalla call del 27/10, vedi sotto.~~ **Riferimenti (`field_references`): si importano quelli il cui contenuto esiste nello shard;
   gli altri si tralasciano**; i link portano per ora a Drupal, segnati «in attesa» (vedi sotto).
10. **Le 3 news con l'immagine solo nel campo legacy si importano senza immagine** (file cancellati
    su Drupal, vedi sotto).
11. **`tipologia_da_sapere` diventa una tassonomia propria di WordPress**, collegata solo al tipo di
    contenuto «Da sapere»: qui si scrive la regola (nome, slug, traducibile con WPML, term meta con
    l'id Drupal), i termini si importano con oc:8760 insieme ai contenuti, creati solo se usati.
    Motivo: le categorie appartengono ai post; un CPT dentro le categorie comparirebbe negli
    archivi dei post e nelle query delle schede News.
12. **`zona_geografica` non si fa in questo ticket ma in oc:8760**: le news non la usano, le carte
    di oc:8760 sono il primo contenuto WordPress che la usa (il tag `wp-forestas` la elenca già fra i
    campi di `carta`); il plugin di Forestas, che arriva dopo, ritrova i termini dall'id Drupal. Il ticket chiedeva di
    decidere qui tutti i vocabolari: per questo è una deviazione concordata col dev (27/10).
13. ~~Superata dalla call del 27/10, vedi sotto.~~ **Plugin di Forestas in questo ticket, solo base più enti** (decisione del dev, 27/10): connessione
    in sola lettura al DB dello shard, pagine dinamiche `/enti` e `/enti/<slug>` (anche in inglese,
    404, redirect dai vecchi indirizzi); i riferimenti agli enti delle news portano lì. Sentieri e
    itinerari restano verso Drupal «in attesa» fino a un ticket loro.

## Call con Giuseppe del 27/10: cosa cambia

Fonte: trascrizione «SCRUM WEBMAPP» del 27/10 (Google Doc `1ZaTokTGzZ6QJavkNgaBOKdfoXDk_uvBJBut2moBRx40`).
Le decisioni 9 e 13 qui sopra sono superate.

1. **oc:8759 importa solo la parte editoriale delle news**: testo, media, termini, collegamenti.
   I riferimenti (`field_references`) restano fuori, insieme a tutto ciò che legge lo shard.
   Obiettivo: chiudere presto il ticket, così Alessio e il cliente vedono le news.
2. **Allegati: per ora come WordPress**, nella libreria media, presi dalla copia SQL. **Dubbio da
   chiudere con Alessio**: se gli allegati servono anche sullo shard, devono vivere su Laravel e
   WordPress li legge («WordPress è slave rispetto a Laravel», per non dividere l'informazione).
   Vale anche per gli allegati dei tracciati, che su Drupal esistono. Giuseppe se lo segna e lo
   chiede anche lui.
3. **Niente lettura diretta del DB dello shard, niente tunnel**: WordPress **non** girerà sulla
   stessa macchina di Laravel (Giuseppe sta chiedendo le macchine; come per osmfeatures/CAI): se
   Laravel si blocca non deve trascinare giù WordPress. WordPress legge lo shard **con le API**. Il
   tunnel serve solo fra macchine diverse, e va bene solo per leggere la copia MySQL di Drupal.
4. **API pensate per WordPress, sotto un prefisso `wp`** nelle rotte di Laravel: ricevono quello che
   WordPress ha salvato (per esempio un elenco di coppie modello + id) e restituiscono la risposta
   già pronta da mostrare, in una sola chiamata invece di una per ente. Sono in sola lettura e non
   toccano il modello.
5. **WordPress salva solo gli id**: il contenuto di un ente (telefono, descrizione) si legge a ogni
   visita dallo shard, così una modifica in Nova si vede subito senza sincronizzare.
6. **Plugin: `wp-geohub` non deve esistere su Forestas.** Si fa un fork di `wp-geohub` in un repo
   nuovo, che Giuseppe ha chiamato «wp-package» (il nome esatto va confermato), generico per tutti
   gli shard; `wp-geohub` non si tocca più. Giuseppe fa il fork insieme a Rubens.
7. **Personalizzazioni per shard dentro lo stesso plugin** (proposta di Rubens, approvata): il
   plugin sceglie lo shard e accende la sua parte. Regole di Giuseppe:
   - ogni shard ha i suoi file con lo stesso nome, tipo `<shard>_customization`;
   - uno script controlla per ogni shard che i file ci siano e crea quelli mancanti, vuoti;
   - un pezzo di codice copiato da uno shard a un altro deve funzionare senza modifiche.
8. **Lo shortcode dei riferimenti non è legato agli enti**: il modello è un parametro, così domani
   si aggiungono validatori o altro senza cambiare codice. Su Drupal i riferimenti ammettono enti,
   itinerari, sentieri, POI.
9. **Import degli enti dalla copia SQL di Drupal**, al posto di quello attuale che legge il JSON
   pubblico di Drupal (fatto da Giuseppe quando la copia non c'era, e che non ha tutti i campi,
   es. la sede). Una colonna per dato invece di `properties` va bene, il modello può cambiare
   (nessuna produzione da preservare). Le tabelle che Drupal usa solo per organizzarsi (es. i
   contatti) non diventano modelli: il loro contenuto entra nell'ente (es. `contatti` come testo
   formattato o jsonb). Le relazioni ente-tracciati esistono già, cambia solo la fonte.
10. **Ticket successivi** (li crea Rubens; overview delle API assegnata a Giuseppe, per condividere
    le scelte): allineamento import enti + API; poi shortcode di selezione dei riferimenti.
11. **Idea da valutare durante il lavoro**: un child theme di Impreza «base», versionato con le
    modifiche che si rifanno a ogni sito, da riusare per esempio per Cammini d'Italia.
12. **I riferimenti si salvano comunque** (decisione del dev dopo la call, 27/10): meta del post
    con tipo e nid Drupal, tutti e 18, non mostrati. Motivo: la copia SQL di Drupal sparirà, e il
    ticket dello shortcode li passerà così all'API `wp`.
13. **Verificare a occhio come la redazione inserisce i riferimenti su Drupal** (richiesta di
    Giuseppe: non fidarsi solo dell'analisi). Prova già raccolta: `field_references` è un campo
    del form della news (`config/sync`), compilato a mano in 12 news su 95.
14. **Challenge (27/10), decisioni del dev:** indirizzo dei file Drupal nel `.env`
    (`DRUPAL_FILES_URL`), valido per pagine e news; news assenti dalla copia in bozza, soglia
    propria di 5 e controllo sul numero di news prima di scrivere. Smentiti dai dati di UAT: slug
    oltre 200 caratteri (massimo 94), date con ora o future (solo date, nessuna futura), tag con
    lo stesso nome in `tag_news` e `tags` (nessuno).

## Riferimenti delle news (`field_references`)

> Dopo la call del 27/10 i riferimenti si salvano come meta (tipo e nid Drupal, tutti) ma non si
> mostrano in oc:8759. I dati qui sotto restano come base per il ticket dello shortcode.

Su Drupal sono il blocco «Riferimenti» in fondo all'articolo: un elenco di link col titolo del
contenuto collegato (`core.entity_view_display.node.news.full.yml:543`, formatter
`entity_reference_label`). Esempio: la news «R.E.S.: inaugurazione dei nuovi sentieri di Oliena»
(nid 1040) ha un solo riferimento, «Comune di Oliena» → `/contatti/comune-di-oliena`.

**Tutti i riferimenti puntano a contenuti del catasto, nessuno a contenuti editoriali.** 12 news
hanno riferimenti, 18 link in tutto (12 verso enti, 11 enti distinti perché O.d.V. Villaggio Normann
è citato da due news; 5 verso itinerari; 1 verso un sentiero); 15 link puntano a contenuti presenti
nello shard. Verificato il 26/10 sulla copia Drupal e, in sola lettura, sul
DB dello shard UAT (`ssh uat.forestas`, container `postgres-forestasuat`; il DB locale di forestas
non ha la tabella `entes` perché è indietro con le migrazioni).

Sentieri e itinerari, tutti presenti nello shard UAT in `ec_tracks`:

| Drupal nid | Tipo Drupal | ec_track UAT | Titolo |
|---|---|---|---|
| 580 | sentiero | 184 | Anello Buggerru - Galleria Henry - Cala Domestica (D-301) |
| 75 | itinerario | 451 | Carta 1. Baronie e Montalbo |
| 5006 | itinerario | 244 | L'Ogliastra da Sud a Nord - Dal mare ai Tacchi (TAPPA 1 di 4) |
| 5026 | itinerario | 239 | L'Ogliastra da Sud a Nord - Dai Tacchi al Gennargentu (TAPPA 1 di 4) |
| 5046 | itinerario | 235 | L'Ogliastra da Sud a Nord - Dal Gennargentu al Supramonte (TAPPA 1 di 5) |
| 5086 | itinerario | 231 | L'Ogliastra da Sud a Nord - Dal Supramonte al mare (TAPPA 1 di 4) |

Enti (`ente_istituzione_societa`), 8 su 11 presenti nello shard UAT in `entes` (71 enti in tutto):

| Drupal nid | Ente UAT | Nome |
|---|---|---|
| 1044 | 17 | Comune di Oliena |
| 158 | 14 | Comune di Sassari |
| 2441 | 11 | Comune di Orroli |
| 3701 | 56 | Comune di Guspini |
| 967 | 16 | Parco Naturale Regionale Molentargius-Le Saline |
| 1410 | 5 | O.d.V. Villaggio Normann |
| 3991 | 39 | GAL Ogliastra |
| 125 | 70 | Complesso Forestale Anglona Meilogu |

**Perché 3 enti mancano.** L'import dello shard prende un ente solo quando lo richiama un tracciato
(gestore, manutentore, rilevatore, operatore, complesso forestale:
`forestas/app/Services/Import/SardegnaSentieriImportService.php`, `resolveOrImportEnte`). Questi 3
sono pubblicati su Drupal ma nessun sentiero, itinerario o POI li cita, né con un campo di
riferimento né con un link nel testo: l'unico legame che hanno è con le news.

| Ente mancante | Pagina Drupal | Unica news che lo cita |
|---|---|---|
| Département de la Haute-Corse (nid 275) | `/contatti/département-de-la-haute-corse-corsica-del-nord` | «Co.R.E.M. - Sousprojet B…» (nid 277) |
| Département de la Corse-du-Sud (nid 276) | `/contatti/département-de-la-corse-du-sud-corsica-del-sud` | «Co.R.E.M. - Sousprojet B…» (nid 277) |
| Sportello regionale del Sentiero (nid 1406) | `/enteistituzionesocieta/sportello-regionale-del-sentiero-tavolo-tecnico-regionale-la-res` | «le associazioni» (nid 1447) |

Le due news sono vecchie: la Co.R.E.M. ha data 25/11/2013 (fra le 5 più vecchie delle 95 italiane),
«le associazioni» ha data 27/01/2023 (33ª più recente; titolo tutto minuscolo, modificata 2 minuti
dopo la creazione: sembra una scheda informativa più che una notizia, impressione non verificata).

**Decisione del dev:** si usano i riferimenti presenti nello shard; la news Co.R.E.M. e «le
associazioni» restano senza enti associati. La Co.R.E.M. conserva il riferimento al Comune di
Sassari, che nello shard c'è.

**Destinazione dei link (decisione del dev, opzione A):** per ogni riferimento la news salva tipo,
id Drupal e id dello shard; il blocco «Riferimenti» mostra il link all'indirizzo attuale su Drupal,
segnato «in attesa» come i link nel testo di oc:8758. L'indirizzo lo costruisce una sola funzione:
quando il plugin di Forestas avrà le pagine di enti, sentieri e itinerari, si cambia lì e i link si
aggiornano al primo `allinea`.

**Come si decide «presente nello shard» (overview, 27/10).** Lo script di WordPress non ha accesso al
DB dello shard: si usa la stessa regola dell'import dello shard calcolata sulla copia Drupal
(sentieri e itinerari sempre; enti solo se richiamati da un tracciato come gestore, manutentore,
rilevatore, operatore o complesso forestale). Non si salva l'id dello shard ma solo tipo e nid
Drupal: lo shard tiene il nid Drupal di ogni contenuto (`sardegnasentieri_id`,
`out_source_feature_id`), quindi il plugin lo ritrova da lì.

**Punto aperto per il lancio:** i link dei riferimenti verso `www.sardegnasentieri.it` vanno
sostituiti prima che Drupal si spenga. Dipende dal ticket del plugin di Forestas (consiglio dato al
dev il 26/10: plugin dedicato nel repo `wp-forestas`, non copia di `wp-geohub` né codice nel child
theme; prefisso unico per tipo appoggiato alla pagina d'elenco, con redirect dai prefissi vecchi).

### Indirizzi dei contenuti del catasto, su Drupal e in WordPress (verificato il 26/10)

Prefissi degli alias Drupal in italiano (`path_alias`): Pathauto non ha pattern per questi tipi, gli
alias sono stati scritti in tempi diversi e il prefisso non è uniforme.

| Tipo Drupal | Prefissi | Elenco su Drupal | Elenco in WordPress oggi |
|---|---|---|---|
| sentiero | `/sentiero/` (435), `/sentieri/` (173) | `/sentieri`, pagina nid 1080 | pagina `sentieri` (oc:8758) |
| itinerario | `/itinerario/` (126), `/itinerari/` (1) | `/itinerari`, pagina nid 1081 | pagina `itinerari` (oc:8758) |
| ente_istituzione_societa | `/contatti/` (49), `/enteistituzionesocieta/` (43) | `/enti`, View `views.view.enti` | nessuno (404, vedi notes di oc:8758) |
| poi | `/da-vedere/` (794), `/poi/` (45) | `/da-vedere`, pagina nid 1082 | pagina `da-vedere` (oc:8758) |

`/contatti` su Drupal non è l'elenco degli enti: è la pagina «Contatti» (nid 1), già in WordPress
con slug `contatti`/`contacts`. Il prefisso `/contatti/` era la regola vecchia degli enti: quelli
creati dal 2013 al 2022 (49) stanno a `/contatti/<nome>`, quelli creati da luglio 2023 (43) a
`/enteistituzionesocieta/<nome>`, il prefisso automatico di Drupal. Il percorso di navigazione Drupal
nasce dall'indirizzo: sulla scheda del Comune di Oliena dice «Home › Contatti», su quella dello
Sportello regionale del Sentiero solo «Home». Gli enti quindi non sono figli della pagina Contatti.

**Plugin `wp-geohub`** (cartella `wm-package`, commit `GEOHUB_REF` in `docker/scripts/init-wordpress.sh`,
attivo): registra i CPT `track` e `poi` (in locale 0 contenuti, opzione `track_url` non impostata),
gli shortcode `wm_grid_track`, `wm_single_track`, `wm_grid_poi`, `wm_single_poi`, e costruisce gli
indirizzi dei tracciati come `/track/<slug>/`. Non gestisce gli enti.

## Relazioni delle news su Drupal (verificato il 27/10)

Campi da `config/sync/field.field.node.news.*.yml` (la configurazione attiva; `sync-dessi` e le
altre cartelle sono copie degli sviluppatori), conteggi dalla copia Drupal di UAT.

| Campo | Punta a | News | Righe |
|---|---|---|---|
| `field_top_image_media` | media `image` | 92 | 96 |
| `field_galleria_media` | media `image` | 25 | 124 |
| `field_file_media` | media `document` | 33 | 51 |
| `field_tipologia` | termini `tipologia_news` | 53 | 53 |
| `field_tag_news` | termini `tag_news` (33 news, 37 righe) e `tags` (13 news, 18 righe) | 44 | 57 |
| `field_references` | nodi `ente_istituzione_societa`, `itinerario`, `sentiero` (ammessi anche `poi` e `liberatoria`, mai usati) | 12 | 18 |
| `field_collegamenti` | link con titolo, 23 righe in 14 news verso il sito stesso, il resto esterni | 45 | 88 |
| `body` | link nel testo verso altre pagine del sito | 9 | — |

`field_top_image` e `field_file` sono i campi legacy (file diretti). In senso inverso nessun campo
di altri contenuti punta a una news; solo il testo di 2 contenuti `da_sapere` contiene un link
`/news/…` (si riscrive da solo con `_drupal_nid`, come in oc:8758).

## Immagini solo nel campo legacy `field_top_image` (verificato il 26/10)

Le 3 news che il ticket indica (nid 388 «Foresteria e Museo nel bosco di Montes», 389 «I edizione
Ultramarathon Lakes Trail Sardegna», 575 «Rafforzamento delle partnership per la R.E.S.», del
2014-2018) hanno in `field_top_image` i fid 1272, 1273 e 1840, che **non esistono più in
`file_managed`**: il file è stato cancellato. Inoltre la pagina completa nasconde `field_top_image`
(`core.entity_view_display.node.news.full.yml`, sezione `hidden`) e sul sito pubblico quelle 3 news
non hanno immagine principale. Non c'è niente da recuperare: si importano senza immagine, 1 a 1 con
Drupal. Tutte le altre news hanno `field_top_image_media`.

## Informazioni per altri ticket

Tutto ciò che emerge qui e serve ad altri ticket si raccoglie in questa sezione; a chiusura di
oc:8759 si condivide nei ticket interessati (richiesta del dev, 26/10).

### oc:8760 (eventi, da sapere, carte)

- **Scheda Eventi della pagina News** (`/news/eventi`, pulsante «Scopri tutti gli eventi»): una voce in
  `config/pagine.json` con `genitore: news`, `testata_da: news`, `scheda: 3`, `pulsante` e `griglia`.
  Lo shortcode `[forestas_schede]` la mostra da solo; la `griglia` oggi cerca solo post della categoria
  «news» (`forestas_news_query` in `inc/news.php`): per gli eventi serve un parametro per il tipo di
  contenuto, e una card con la data grande del Canvas (`.uc-date`).
- **Regola di rewrite delle pagine figlie di News**: copre già `/news/eventi` se la scheda è una
  pagina figlia di News con il meta `_wpf_scheda`; lo script rigenera le regole da sé (riga `REGOLE`).
- **Pagina d'elenco degli eventi come su Drupal**, sul modello di `/tutte-le-news` e
  `/attivita-e-comunicazioni`.
- **Tag nativi riusabili:** `post_tag` va associato al CPT evento; il vocabolario `tags` è usato
  anche da evento. I termini Drupal si ritrovano dal term meta `_drupal_chiave` = `<vocabolario>:<tid>`
  (non univoco), con `wpf_termine_per_drupal()` e `wpf_news_termine()` di `news-lib.php`.
- **`tipologia_da_sapere`:** tassonomia propria registrata nel child (`inc/news.php`) senza tipo di
  contenuto: oc:8760 la collega al CPT «Da sapere» con `register_taxonomy_for_object_type` e importa i
  termini; in `config/wpml.json` è già traducibile. Su Drupal: 9 termini senza gerarchia, 1 tradotto in inglese,
  usato solo da `da_sapere` nel campo `field_tipo_da_sapere` (71 contenuti su 72); ogni termine ha
  una pagina d'elenco, es. `/tipologia-da-sapere-argomenti/caso-di-pericolo`.
- **`zona_geografica`: si fa in oc:8760** (decisione del dev, 27/10), insieme alle carte: una sola
  tassonomia `zona_geografica` con term meta dell'id Drupal, che il plugin di Forestas ritroverà.
  Cosa sapere:
  - su Drupal 34 termini senza gerarchia, tutti tradotti in inglese; la usano 16 `carta`, 610
    sentieri, 151 itinerari, 976 POI; le news no;
  - sulla pagina del POI si vede come «Area geografica: Sarcidano» (testo, senza link); su sentieri
    e itinerari è salvata ma nascosta;
  - la View `carte` (`/carte/<id zona>`, es. `/carte/4835`) elenca sentieri e itinerari di una zona,
    con filtro per tipo: è il collegamento fra una carta e i suoi percorsi, e funziona solo se carta
    e tracciati hanno lo stesso termine;
  - lo shard la tiene già per ogni tracciato e POI come elenco id Drupal → nome in
    `properties->forestas->zona_geografica` (`forestas/app/Dto/Import/ForestasTrackData.php:55`);
  - **rischio:** se oc:8760 e il plugin la creano ciascuno per conto proprio nascono due «Barbagia»;
    se il plugin dovesse partire prima di oc:8760, la tassonomia la crea lui con la stessa regola.
- **Card unica del Canvas** (varianti in evidenza con foto, compatta, senza foto): la stessa per le
  news e per gli eventi.
- **Motore:** `wpf_contenuto_scrivi()` di `contenuti-lib.php` scrive pagine e post (`tipo`), con media,
  termini e WPML; un CPT si aggiunge con un suo `*-lib.php` sul modello di `news-lib.php`, chiamato da
  `pagine.php` dopo le news.
- **Riferimenti a contenuti del catasto:** se gli eventi hanno riferimenti a enti, sentieri o
  itinerari, si salvano come le news (meta `_wpf_riferimenti`, tipo neutro e nid Drupal, tutti) e non si
  mostrano: li mostrerà il plugin con le API dello shard (call del 27/10).
- **`wpf_sync_mancanti` con la provenienza:** ogni voce ha `origine` (`pagina`, `news`) ed `elemento`;
  gli eventi useranno la loro origine e il loro filtro, come le news in `pagine.php`.

### Plugin WordPress, API `wp` e import degli enti (ticket da creare)

**Le decisioni valide sono quelle della «Call con Giuseppe del 27/10»** (sopra): fork di
`wp-geohub` in un repo nuovo generico con personalizzazioni per shard, lettura dello shard solo con
API sotto il prefisso `wp`, WordPress su una macchina diversa da Laravel, solo id salvati in
WordPress, shortcode generico sul modello, import degli enti dalla copia SQL.

Superato dalla call: lettura diretta del DB con utente Postgres in sola lettura e `pdo_pgsql`
(WordPress e shard non staranno sulla stessa macchina); plugin dedicato solo a Forestas.

Ancora utili:
- **Pagine dinamiche, non post** (idea del dev): `/enti/<slug>` generata a ogni visita, ora con i
  dati presi dall'API. Precedente: il vecchio tema `seodo` di sicai.webmapp.it (server `php8`):
  una pagina per tipo con un template che legge `?id=` e interroga Postgres a ogni richiesta, link
  `/tappa?id=…`, nessuna cache. Lo stesso tema mette i post sotto `/news/` con
  `add_rewrite_rule('^news/([^/]+)/?$', 'index.php?name=$matches[1]', 'top')` e il filtro
  `post_link`. Da migliorare: rewrite `/enti/<slug>` invece di `?id=`, cache breve, 404 vero,
  titolo e meta della pagina, `/en/enti/<slug>`, redirect dai vecchi `/contatti/<x>` e
  `/enteistituzionesocieta/<x>`.
- La scheda ente su Drupal mostra nome, «Informazioni», «Tipo di ente», sito web e date; l'elenco
  è la View `/enti` (92 enti). Il modello `Ente` dello shard ha `sardegnasentieri_id`, `name`,
  `description`, `contatti`, `pagina_web`, `tipo_ente`, `geometry`, `properties`; manca per
  esempio la sede.
- Indirizzi: prefisso unico per tipo appoggiato alla pagina d'elenco, con redirect dai prefissi
  vecchi di Drupal (tabella in «Indirizzi dei contenuti del catasto»).
- 3 enti pubblicati su Drupal non arrivano nello shard perché nessun tracciato li richiama
  (nid 275, 276, 1406): con l'import dalla copia SQL si può decidere di prenderli tutti.
- I riferimenti delle news: 12 news, 18 link, tabelle con gli id dello shard UAT in «Riferimenti
  delle news». Quando Drupal si spegne, la copia SQL sparisce.
- `zona_geografica`: se il plugin arriva prima di oc:8760, crea lui la tassonomia unica (dettagli
  nella sezione di oc:8760, sopra).

### Tag `wp-forestas`

- Elenco degli enti `/enti`: oggi 404, nessun ticket lo prevede (già segnato in oc:8758).
- **Prima dello spegnimento di Drupal va chiesta la cartella dei file** (`sites/default/files`
  del loro server). Su UAT ci sono solo il dump del database (`storage/drupal-dump`) e il codice
  (`/root/sardegnasentieri.zip`, in `sites/default/files` solo icone di sistema e traduzioni):
  verificato il 27/10. Pagine e news scaricano i media da `www.sardegnasentieri.it`; copiata la
  cartella sul server, si punta lì `DRUPAL_FILES_URL` nel `.env` (requisito di oc:8759).

## Divergenze dal piano, task per task

- **Task 1.** `WPF_DRUPAL_FILES` resta come valore predefinito di `wpf_drupal_files_base()` invece di
  sparire. Le funzioni della pulizia del testo (`wpf_pagina_pulisci`, `wpf_pagina_link_dagli_indirizzi`)
  sono passate in `contenuti-lib.php` con il nome di prima.
- **Task 2.** Slug inglesi delle schede e degli elenchi uguali a quelli italiani: su Drupal le View hanno
  lo stesso percorso nelle due lingue (`/en/attivita-e-comunicazioni`). Testi dei pulsanti presi dalle
  View: «Vedi tutte le attività» / «View all activities» e «Tutte le news» / «All news». Le pagine senza
  nid prendono la testata da News anche in inglese, dove Drupal non ha la traduzione di News: dati
  dell'italiano, sottotitolo dalla nostra traduzione. Il pulsante è un array nei meta: in JSON
  `update_post_meta` toglieva la «\» degli accenti e la pagina risultava cambiata a ogni giro.
- **Task 3.** Lettura riscritta con le tabelle delle news in memoria (`wpf_drupal_news_righe`,
  `wpf_drupal_file_media_tutti`, termini in un colpo solo): dal Mac col tunnel ogni query costa 0,4 s,
  e una query per campo voleva dire 13 minuti a giro; ora 9 secondi.
- **Task 4-5.** Categorie e tag non si traducono: su Drupal `tipologia_news`, `tag_news` e `tags` non
  hanno traduzioni, quindi in WPML post, categorie e tag sono «traducibili, con ripiego» e le news
  inglesi usano i termini italiani. Titoli senza spazi in fondo (7 news li hanno su Drupal) e slug passati
  da `sanitize_title` nello script, se no ogni giro li vedeva «cambiati». Nome dei file dei media senza
  spazi in fondo (fid 1768, «….pdf »).
- **Task 6.** Il redirect del child porta anche i vecchi `/news/<alias con accenti>` (3 news) oltre a
  `/node/<nid>`. Riga `REGOLE` nel log quando le regole delle schede si rigenerano.
- **Task 7.** Niente griglie di Impreza: schede, griglie e card sono shortcode del child
  (`[forestas_schede]`, `[forestas_elenco]`, `[forestas_news]`), come il resto delle pagine di oc:8758;
  i template di `config/post.json` li richiamano. Le news da mostrare stanno nel campo `griglia` di
  `config/pagine.json`, che può essere anche un elenco di griglie: `/attivita-e-comunicazioni` ha sopra
  le 4 in evidenza, come su Drupal. La testata della news è quella delle pagine (immagine di sfondo) con
  tipologia e data, come la sase-hero di Drupal. Etichetta delle card: tipologia nelle card in evidenza
  (Canvas), tag nelle altre (anteprime di Drupal). A parità di data le news vanno in ordine di nid, come
  su Drupal. «Condividi» non c'è sulle schede, come sulla pagina News di Drupal. Sui post la voce NEWS
  del menu è attiva.
- **Ordine di `/attivita-e-comunicazioni`.** Su Drupal la View non ha un ordinamento (Search API,
  pertinenza) e l'ordine online non segue né `field_data` né `field_date`: qui data decrescente.
- **Categoria «News» all'indirizzo `/news/category/news/`**, non `/category/news/`: con i permalink
  `/news/%postname%/` WordPress mette la base delle categorie sotto `/news/`. Nessun link porta lì.

- **Schede invertite (decisione del dev, 27/10, controllo in locale).** `/news/` è la scheda News, con
  le 4 in evidenza e il pulsante «Tutte le news»; «Attività e comunicazioni» è la seconda, a
  `/news/attivita-e-comunicazioni/`. Niente più `/news/news/` (voce `news-news` tolta da
  `config/pagine.json`, le due pagine locali nel cestino). Nel Canvas la prima era Attività.
- **«Condividi» tolto anche da `/attivita-e-comunicazioni/` e `/tutte-le-news/`** e dalle loro pagine
  successive (decisione del dev, 27/10).

- **Collegamenti come su Drupal (controllo del dev, 27/10).** Titolo «Collegamenti» in italiano e
  «Links» in inglese: è la traduzione di Drupal (`locales_target`, lid 155) della stringa «Links» del
  template. Freccia davanti al testo, link in grassetto senza sottolineatura, testo tagliato a 80
  caratteri con «…» (formatter `link`, `trim_length: 80`). La freccia usa il colore della sezione News
  del sito nuovo (#C2410C), non il #F67745 di Drupal.
- **Fila delle schede senza scroll.** Su desktop niente overflow; sotto 860px solo orizzontale
  (`overflow: auto hidden`): con `overflow-x: auto` il browser metteva auto anche in verticale.

- **Editor della news allineato al form di Drupal (decisione del dev, 27/10: va fatto come per le
  pagine).** Su Drupal la redazione inserisce i collegamenti nel campo `field_collegamenti` del form
  (widget `link_default`: URL e testo, voci illimitate, interni ed esterni). In WordPress il riquadro
  delle pagine (`inc/pagine-statiche-admin.php`) vale anche per le news, con il titolo «Allegati,
  collegamenti e galleria», nell'ordine del form di Drupal: allegati, collegamenti, galleria, «In
  evidenza». Il Riassunto è visibile anche sulle news. Data, immagine, tipologia e tag sono i campi nativi.
  La categoria predefinita dei post diventa News (la imposta il giro delle news, riga `TERMINE`), così
  una news scritta in WordPress compare nelle schede e negli elenchi. Verificato con WP-CLI: riquadro
  con le quattro sezioni e l'avviso, salvataggio, rimozione, un indirizzo `javascript:` scartato.
- **Scheda Attività ordinata per data (decisione del dev, 27/10, opzione B).** Su Drupal il blocco
  ordina per `field_date`, campo nascosto nel form che le news recenti non hanno: finivano in fondo, e
  una news scritta in WordPress ci sarebbe finita sempre. Ora tutte le griglie ordinano per data, le più
  recenti prima; `field_date` e il meta `_wpf_data_ordine` sono tolti.

## Bug trovati

Nessuno nel codice esistente. Dati di Drupal da sapere:
- la foto di galleria fid 86891 della news 4606 dà 404 anche sul sito Drupal: resta fra i media
  mancanti (non ferma il giro) e l'anteprima segnala la news 4606 «aggiornata (wpf_galleria)»;
- le news 839 e 842 hanno la tipologia 3905 e il tag 3361, che su Drupal non esistono più: AVVISO nel
  log, restano senza.
