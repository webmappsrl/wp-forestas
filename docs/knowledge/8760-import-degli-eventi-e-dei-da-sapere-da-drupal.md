# Eventi e «da sapere» da Drupal

## Come funziona oggi

Eventi (11 nodi `it`/`und`, 3 traduzioni inglesi) e «da sapere» (72 nodi, 49 traduzioni) sono due tipi di
contenuto di WordPress, `evento` e `da_sapere`, registrati dal child (`inc/contenuti.php`). Li scrive lo
stesso giro delle pagine e delle news, `bin/wordpress-config.sh pagine` (quindi anche `allinea` e il suo
cron), dopo le news. Le regole delle pagine e delle news valgono anche qui (pagine di conoscenza di
oc:8758 e oc:8759): copia Drupal in sola lettura, Drupal vince sempre, `DRUPAL_SYNC`, mancanti da
ritentare, termini ritrovati da `_drupal_chiave`.

- **Un ciclo per tutti i tipi.** `docker/scripts/config/import-lib.php` ha il giro comune dei contenuti con
  un nodo Drupal (`wpf_import_sincronizza`: controlli sulla copia, lingue, link, log, mancanti, bozze).
  Ogni tipo è un descrittore, documentato in testa a `import-lib.php`, con la sua lettura dalla copia, i
  suoi termini e la sua scrittura (`news-lib.php`, `eventi-lib.php`, `da-sapere-lib.php`). L'elenco dei
  tipi, nell'ordine del giro, è `import-tipi.php`: `wpf_import_tipi` ferma lo script se un descrittore non
  ha tutte le chiavi. Un tipo nuovo ha il suo `*-lib.php`, si aggiunge a `import-tipi.php` e, se ha un
  indirizzo suo, si registra nel child. Il riassunto di `bin/wordpress-config.sh` conta i tipi dalle
  righe `TIPO` che `pagine.php` stampa: non c'è un elenco da tenere a mano.
- **Riconoscimento.** Come le news: `_drupal_nid` e `_wpf_lingua`, mai l'id di WordPress.
- **Eventi.** Titolo, testo, sottotitolo nel Riassunto, immagine in evidenza, allegati, galleria,
  collegamenti, «in evidenza», tag (`field_tag_evento` → `post_tag`). Le date in `wpf_evento_inizio` e
  `wpf_evento_fine` (AAAA-MM-GG, la fine solo se c'è), il luogo in `wpf_evento_luogo` con le chiavi del
  modulo address di Drupal. La data del post resta quella dell'import: un evento futuro datato al suo
  giorno sarebbe «programmato» e WordPress non lo mostrerebbe. Gli elenchi ordinano per data di inizio,
  dal più recente; un evento senza data va in fondo (join su `wpf_evento_inizio` con la chiave nella
  condizione, `forestas_griglia_query` in `inc/griglie.php`).
- **«Da sapere».** Titolo, testo, sottotitolo, immagine, allegati, galleria, tag e tutte le tipologie di
  `field_tipo_da_sapere` (`tipologia_da_sapere`; 4 contenuti ne hanno più d'una). La data del post è la
  creazione su Drupal e «Sempre in cima» è `menu_order` 1, presi dalla lingua originale anche per le
  traduzioni: le View di Drupal ordinano per sticky e created. La pagina di una tipologia ordina per
  `menu_order`, data e ID (10 per pagina), le schede di Da sapere per data. La tassonomia ha `sort`:
  l'import scrive le tipologie nell'ordine di Drupal (`term_order`) e card e testata le mostrano tutte in
  quell'ordine, separate da uno spazio, come i template di Drupal.
- **Tipologie dei «da sapere».** Le 9 tipologie si importano tutte, anche quelle senza contenuti, con
  descrizione, immagine (term meta `wpf_immagine`), la spunta «Mostra in lista Da sapere»
  (`wpf_mostra_in_lista`, le pillole) e il peso (`wpf_peso`, il loro ordine). Una tipologia è un termine
  per le due lingue, mostrato in inglese col ripiego di WPML, come le categorie delle news: su Drupal solo
  «F.A.Q.» è tradotta, e con lo stesso nome. La pagina della tipologia
  (`/tipologia-da-sapere-argomenti/<tipologia>/`, `taxonomy-tipologia_da_sapere.php`) è come quella di
  Drupal: testata con immagine e descrizione, percorso con la sola «Home», card con la foto a sinistra.
- **Pagine.** In `config/pagine.json`: la scheda Eventi di News (`/news/eventi/`) e l'elenco `/eventi/`
  (8 per pagina, una colonna di card orizzontali); `/da-sapere/` a 4 schede come nel wireframe di oc:8783
  (la pagina stessa, «Norme di comportamento», «Pillole di sentieristica», «FAQ»). Le schede figlie di Da
  sapere seguono la regola di precedenza di quelle di News (pagina di conoscenza di oc:8759).
- **Filtri delle liste.** `/attivita-e-comunicazioni/`, `/tutte-le-news/` ed `/eventi/` hanno a sinistra
  i filtri come le faccette di Drupal (`facets.facet.*.yml`): un gruppo per voce del campo `filtri` della
  griglia in `config/pagine.json` (`campo` tipologia, tag o anno, titolo, numeri, ordine). L'anno è
  quello della data mostrata per le news e quello dell'inizio per gli eventi. Markup e conteggi in
  `inc/griglie.php`, aperture e «Mostra di più» in `assets/js/filtri.js`; sotto 960px i filtri stanno
  dietro il pulsante «Filtri».
- **Codice del child per tutti i tipi.** Card unica del Canvas (`forestas_card`), corpo della pagina
  (`forestas_corpo_contenuto`) e collegamenti (`forestas_collegamenti`) in `inc/contenuti.php`; griglie,
  query e filtri in `inc/griglie.php`; schede e pagine d'elenco in `inc/news.php`; data e luogo degli
  eventi in `inc/eventi.php`; pagina, pillole e tipologie dei «da sapere» in `inc/da-sapere.php`.
- **Pagina del contenuto.** Template di Impreza `template-evento` (`[forestas_evento]`: testata con
  «Eventi», «Dove» e «Quando», testo, allegati, collegamenti, galleria) e `template-da-sapere`
  (`[forestas_da_sapere]`: testata con le tipologie, testo, allegati, galleria; i tag no, come nel layout
  di Drupal). Sono i predefiniti dei due tipi (`content_evento_id` e `content_da_sapere_id` in
  `config/impreza.json`): anche un contenuto creato dal pannello ha quel layout.
- **Vecchi indirizzi.** Ogni pagina, news, evento, «da sapere» e tipologia porta nel meta
  `_wpf_alias_drupal` i suoi vecchi percorsi Drupal (alias e `node/<nid>` o `taxonomy/term/<tid>`,
  senza `/` iniziale né lingua); le pagine anche quelli dei nodi che rimpiazzano (`sostituisce` in
  `config/pagine.json`). Un solo gestore del child (`inc/pagine-statiche.php`) cerca sul 404 il percorso
  in quel meta e manda in 301 alla pagina nuova: solo gli indirizzi che Drupal aveva, un altro 404 resta
  404. Un percorso con un'estensione o che comincia con `wp-` non fa la ricerca.
- **Editor.** Evento: data di inizio obbligatoria (senza, l'evento resta in bozza con un avviso, anche
  dalla Modifica rapida e di massa) e fine non prima dell'inizio, luogo; riquadro «Allegati, collegamenti e
  galleria» e «In evidenza» come le news (riquadro «Date e luogo»). «Da sapere»: tipologie, tag, riquadro
  dei media. La schermata di una tipologia ha «Immagine», «Pillole di sentieristica» (la spunta «Mostra in
  lista Da sapere» di Drupal) e «Ordine». Le pagine hanno in più il riquadro
  «Colore e testata» (`_wpf_colore`, `_wpf_testata`), come i campi «Colore tematico» e «Layout» della
  Pagina base di Drupal. Con `DRUPAL_SYNC=on` ogni schermata di un contenuto o di una tipologia
  sincronizzati avvisa che le modifiche si perdono al giro dopo.
- **Lingue.** I due tipi sono traducibili con ripiego in `config/wpml.json`: le traduzioni inglesi sono
  collegate all'italiano, e sul sito inglese gli altri contenuti si vedono in italiano, con `lang="it"`
  sul contenuto e su ogni nome di tipologia italiano (`forestas_nomi_termini`, WCAG 3.1.2).
- **Protezioni.** Come le news, con soglie proprie nel descrittore: eventi 2 (sono 11), «da sapere» 5.
  `WPF_EVENTO_PROVA_CONTEGGIO` e `WPF_DA_SAPERE_PROVA_CONTEGGIO` simulano in anteprima una copia con meno
  contenuti, come `WPF_NEWS_PROVA_CONTEGGIO`.

## Perché così

- **Tipi di contenuto propri e non post:** su Drupal sono tipi diversi dalle news, con indirizzi propri
  (`/evento/<titolo>`, `/da-sapere/<titolo>`) e campi propri. Con i permalink `/news/%postname%/` un post
  starebbe sotto `/news/`; i tipi non nativi non ci finiscono (`forestas_senza_front`, oc:8759).
- **Un ciclo generico prima dei tipi nuovi:** il ciclo delle news era già il giro di un tipo con nid
  Drupal. Copiarlo due volte avrebbe dato tre copie da correggere insieme; il refactoring è stato
  verificato sulle news prima di aggiungere i tipi (log identico, salvo l'avviso finale).
- **Soglie per tipo:** uno scarto di 5 non segnalerebbe una copia a metà con 11 eventi.
- **Date di Drupal per i «da sapere», non per gli eventi:** le View dei «da sapere» ordinano per created e
  sticky, quindi servono; quelle degli eventi per data di inizio, e un evento datato nel futuro sarebbe
  «programmato».
- **Data e «Sempre in cima» della lingua originale:** WPML allinea la data di una traduzione a quella
  dell'originale; con quella della traduzione ogni giro riscriveva le 49 traduzioni.
- **Vecchi indirizzi in un meta e un solo gestore:** gli indirizzi di Drupal non si ricavano dallo slug
  (accenti, prefissi diversi, pagine costruite senza nodo). Un meta per contenuto e una sola ricerca
  valgono per tutti i tipi; reindirizzare ogni `/<slug>` mandava in 301 anche indirizzi che Drupal non
  aveva.
- **Redirect a priorità 9:** `redirect_canonical` del core gira a priorità 10 e, su un alias che finisce
  con una punteggiatura (`/da-sapere/vestiti-“-cipolla”`), toglie l'ultimo carattere e manda a un altro
  404 prima che il gestore lo veda.
- **Tipologie con `sort`:** WordPress restituisce i termini in ordine di nome; l'ordine di Drupal
  (`2336`: «Norme di comportamento», «Come organizzare l'escursione», «Il fai-da-te…») serve un ordine
  salvato.

## Come ci siamo arrivati

- Il piano prevedeva griglie di card su `/eventi/` e il percorso «Home › Da sapere › tipologia» sulle
  pagine delle tipologie. Il confronto con Drupal del 29/10 ha portato filtri, lista a una colonna,
  testata della tipologia e percorso con la sola «Home». Da lì la regola: ogni pagina d'elenco si
  confronta con Drupal su filtri, disposizione, contenuti e ordine, indirizzi, testata e stile
  (sezione «Confronto con Drupal» delle note del ticket).
- Le tipologie e la data di creazione dei «da sapere» sono arrivate dalla review del 29/10: si
  importava solo la prima tipologia e la data dell'import, e F.A.Q. aveva 10 contenuti invece di 12.
- Carte e zone geografiche sono rimaste fuori (descrizione del ticket): servono a sentieri, itinerari e
  POI, che arriveranno con il plugin che legge lo shard.
