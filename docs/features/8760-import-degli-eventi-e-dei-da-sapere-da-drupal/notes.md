> Ticket: oc:8760

# Notes — Import degli eventi e dei «da sapere» da Drupal

## Confronto con Drupal: cosa controllare in ogni pagina (regola del dev, 29/10)

Filtri, disposizione e indirizzi sono sfuggiti al primo confronto, fatto solo sullo stile delle card. Ogni
pagina d'elenco o a schede si confronta con la stessa pagina di Drupal online, a schermo largo (≥ 1440px) e
sotto 960px, su questi punti, e ogni differenza si allinea senza aspettare che la trovi il dev:

1. **Filtri (faccette)**: la colonna a sinistra, i gruppi, i valori, i numeri, l'ordine, «Mostra di più»;
   l'elenco completo sta in `config/sync/facets.facet.*.yml` del codice Drupal (`facet_source_id` = la View).
2. **Disposizione**: colonne della griglia, larghezze, distanze, altezze delle card, posizione della foto.
3. **Contenuti mostrati**: quali e in che ordine (filtri e ordinamenti della View in `views.view.*.yml`).
4. **Indirizzi**: i vecchi alias Drupal della pagina portano alla pagina nuova (301).
5. **Testata**: etichetta, barretta, titolo, misure.
6. **Stile**: colori, caratteri, icone, misurati nel browser.
7. **Campi di inserimento**: ogni campo del modulo di Drupal (contenuto, termine, media) si importa e si
   può compilare anche nell'editor di WordPress; i moduli si leggono sullo staging Acquia
   (`/node/add/<tipo>`, `/admin/structure/taxonomy/manage/<vocabolario>/add`, `/media/add/<tipo>`).

### Controllo dei campi di inserimento (29/10, richiesta del dev)

Moduli di Drupal letti sullo staging Acquia, confrontati con l'import e con l'editor di WordPress:

| Drupal | Campi | Import | Editor WordPress |
|---|---|---|---|
| News | titolo, data, immagine, testo, riferimenti, allegati, collegamenti, descrizione testata, galleria, tipologia, tag, in evidenza, alias, stato | tutti | tutti tranne **riferimenti** (meta `_wpf_riferimenti`, nessun campo) |
| Evento | titolo, inizio, fine, descrizione testata, testo, immagine, galleria, luogo (via, seconda riga, località, paese), allegati, collegamenti, in evidenza, tag | tutti | tutti (il luogo ha anche organizzazione, CAP e provincia) |
| Da sapere | titolo, immagine, descrizione testata, testo, allegati, tag, galleria, argomento | tutti | tutti |
| Pagina base | titolo, immagine, testo, galleria, descrizione testata, allegati, **colore tematico**, **layout** | tutti | tutti (colore e testata nel riquadro «Colore e testata», aggiunto qui) |
| Immagine | file, testo alternativo, autore, credits | tutti | tutti (libreria media) |
| Documento | file con descrizione | sì (titolo dell'allegato) | sì (titolo nella libreria media) |
| Tipologia da sapere | nome, descrizione, **immagine**, mostra in lista, peso | tutti (descrizione e immagine aggiunte qui) | tutti (immagine e ordine aggiunti qui) |
| Tipologia news, tag | nome, descrizione | nome | nome e descrizione (nativi) |

Non si importano, perché non servono: autore, «Authored on» degli eventi (hanno la data dell'import: gli
elenchi ordinano per data di inizio), «Promosso», «Sempre in cima» di news ed eventi, layout dei «da sapere»
(nessuno lo usa) e:
- dei «da sapere» invece «Authored on» e «Sempre in cima» si importano (data del post e `menu_order`, dalla
  review del 29/10): le loro View ordinano per sticky e created;
- **commenti**: in tutta la copia ce n'è uno, «Commento di prova» del 2013 su un itinerario (tipo non
  importato); su news, eventi, «da sapere» e pagine zero, e il sito Drupal non mostra il modulo dei commenti;
- **messaggio di revisione**: nota interna dello storico delle versioni, non si vede sul sito; c'è su 535
  revisioni ed è quasi sempre automatico («Edited by …», «Creato da …»). WordPress ha il suo storico delle
  revisioni;
- **descrizioni di tag e tipologie news**: una sola per vocabolario, e Drupal non le mostra.

**Pagine delle tipologie allineate a Drupal (corretto qui):**
- l'import scrive nella tipologia descrizione, immagine (`wpf_immagine`) e vecchi indirizzi
  (`_wpf_alias_drupal`: `/taxonomy/term/<tid>` e l'alias di «In caso di pericolo»), che davano 404; la
  descrizione si confronta con l'impronta `_wpf_testo_drupal`, perché WordPress ripulisce l'HTML;
- la pagina della tipologia ha la testata di Drupal (immagine, titolo, descrizione, a tutta larghezza),
  il percorso con la sola «Home» e la griglia di card con la foto a sinistra su tre colonne: a 1920px
  testata 1905×600, griglia 1294 con colonne da 421 a 15px, card 421×113 con foto 134×113, come su Drupal;
- nella schermata della tipologia ci sono «Immagine» (libreria media) e «Ordine» (il peso delle
  pillole), oltre alla spunta; la tassonomia ha le sue etichette («Modifica tipologia», non «Modifica
  tag»). Child 1.7.3.

**Colore tematico e layout delle pagine (aggiunti qui, 29/10, approvato dal dev):** riquadro laterale «Colore
e testata» nell'editor delle pagine. «Colore tematico» con le voci del vocabolario `colore_content_type` di
Drupal (Sentieri, Punti di interesse, Itinerari, News, Da sapere, Eventi; `is-event` ora dà la sezione
Eventi); «Testata» con immagine o solo titolo centrato (il layout di Drupal che il child sa mostrare). Su una
pagina sincronizzata li riscrive la sincronizzazione, e l'avviso del riquadro lo dice. Provato su una pagina
temporanea: salvataggio, valore inventato scartato, sezione della barretta.

**Resta senza un campo in WordPress: i riferimenti delle news** (sentieri, itinerari, POI, enti; 12 news su
Drupal li hanno). Si importano in `_wpf_riferimenti` ma non si mostrano né si inseriscono: li gestirà il
plugin con le API dello shard appena il backend è pronto (decisione di oc:8759, confermata dal dev il 29/10).
Vedi «Follow-up».

**Percorso sopra o sotto la testata:** su Drupal il percorso sta sopra la testata, in tutte le pagine; qui
sta sotto, come nelle pagine di oc:8758. Non cambiato in questo ticket.

## Divergenze dal piano, task per task

### Task 5 field_tag_evento punta a termini

Il piano (e l'overview, dalla descrizione del ticket) diceva che nell'evento 5106 `field_tag_evento`
punta all'itinerario 5016, da salvare in `_wpf_riferimenti`. Non è così: il campo ha
`target_type: taxonomy_term` (`field.storage.node.field_tag_evento.yml`) e il 5016 è il termine «eventi»
del vocabolario `tags`. Il nid dell'itinerario «L'Ogliastra da Sud a Nord - Tappa 3» è 5016 per caso: la
verifica del 28/10 aveva fatto il join su `node`. L'evento 5106 ha quindi i tag «sentieri» ed «eventi»,
importati come `post_tag`, e gli eventi non hanno riferimenti al catasto (`_wpf_riferimenti` resta delle
sole news). I legami dell'evento con il catasto sono i 6 link agli itinerari nel testo, «in attesa».

### Task 6 un termine per le due lingue

Il piano voleva il termine «F.A.Q.» anche in inglese, collegato in WPML, e `forestas_termine_drupal` per
lingua. Su Drupal solo «F.A.Q.» è tradotta, e con lo stesso nome: le tipologie sono un termine per le due
lingue, mostrato in inglese col ripiego di WPML (`tipologia_da_sapere` traducibile con ripiego in
`config/wpml.json`), come le categorie delle news. In inglese le pagine delle tipologie mostrano anche i
«da sapere» italiani senza traduzione, marcati `lang="it"` (verificato il 29/10: 5 su 10 in F.A.Q.); il
piano diceva «in inglese solo contenuti inglesi».

### Task 6 tipologie multiple, data di creazione e «Sempre in cima»

Il piano leggeva una sola `tipologia` (tid) per «da sapere» e lasciava la data dell'import. Dalla review
del 29/10 `wpf_drupal_da_sapere` restituisce `tipologie` (tutte quelle di `field_tipo_da_sapere`: 4
contenuti ne hanno più d'una, F.A.Q. ne ha 12 come su Drupal), e `wpf_drupal_nodo` restituisce `creato`
e `in_cima` (created e sticky della lingua originale, perché WPML allinea la data della traduzione a
quella dell'originale). Il «da sapere» ha `post_date` = creazione su Drupal e `menu_order` = sticky:
la pagina della tipologia ordina per `menu_order`, data e ID, le schede per data, come le View. Dettagli
in «Bug trovati».

### Task 7 ordine degli eventi con un join

Il piano (interfacce del task 7) ordinava gli eventi con una `meta_query` `OR` (`EXISTS` / `NOT
EXISTS`). Nella review del 29/10 si è visto che così un evento senza data veniva ordinato sul valore di un
meta qualsiasi e finiva in cima: `forestas_griglia_query` (`inc/griglie.php`) mette la query var `forestas_ordine_eventi` e un
filtro `posts_clauses` (oggi in `inc/griglie.php`) fa un `LEFT JOIN` su `wpf_evento_inizio` con la chiave nella
condizione, così l'evento senza data va in fondo.

### Task 7 e 8 pagine allineate a Drupal dopo il piano

Dopo i controlli del 29/10 con il dev, `/eventi/` ha i filtri e la lista a una colonna di card
orizzontali, e la pagina della tipologia ha la testata con immagine e descrizione, il percorso con la sola
«Home» e card con la foto a sinistra: il piano (task 7 passo «elenco», task 8 passo 3) prevedeva la griglia
di card e il percorso «Home › Da sapere › tipologia». Dettagli in «Decisioni». Il percorso a tre livelli
del wireframe di oc:8783 («Home › Da sapere › FAQ», `decisioni.md`, tavola 8) riguarda le schede
(`/da-sapere/faq/`), che lo hanno; le pagine delle tipologie non sono nel wireframe e seguono Drupal.

### Task 2 descrittore diverso da quello del piano

Il piano prevedeva un descrittore con le callable `leggi`, `esistenti`, `prepara`, `slug`. Il codice
(`import-lib.php`, commento in testa) ha `nodo`, `termini`, `scrivi` più i testi dei messaggi (`nome`,
`plurale`, `questo`, `genere`), `prefisso`, `schede`, `pagina`, `scarto_copia`, `conteggio` e
`tassonomie`: esistenti, slug e collegamenti si sono rivelati uguali per tutti i tipi e stanno nel ciclo.
`scarto_copia` (news 5, eventi 2, «da sapere» 5) è il controllo «copia a metà» che le news avevano già,
reso un parametro: gli eventi sono 11, e uno scarto di 5 non segnalerebbe una copia a metà. Chi aggiunge un
tipo segue il commento di `import-lib.php` e lo aggiunge a `import-tipi.php`, non il piano (pagina di
conoscenza 8760).

### Task 2 nomi delle funzioni e dei file

Il piano nomina `wpf_news_sincronizza`, `wpf_news_percorsi` e `wpf_drupal_news_elenco`, che non esistono
più: il ciclo è `wpf_import_sincronizza` con `wpf_import_percorsi` (`import-lib.php`), l'elenco dei nodi
`wpf_drupal_bundle_elenco` (`drupal.php`). Dalla seconda review (29/10) l'elenco dei tipi sta in
`import-tipi.php` (pagine.php lo include, ogni `*-lib.php` include `import-lib.php`), e nel child le funzioni
comuni a tutti i tipi hanno un nome neutro: `forestas_card`, `forestas_corpo_contenuto` e
`forestas_collegamenti` in `inc/contenuti.php`, `forestas_griglia`, `forestas_griglie_pagina`,
`forestas_griglia_query` e `forestas_filtri*` in `inc/griglie.php` (erano `forestas_news_*` in
`inc/news.php`); la classe del corpo è `forestas-corpo-contenuto` (era `forestas-news-corpo`).

### Commit in uno solo

Il piano chiude ogni task con un commit del dev; il lavoro è stato committato in uno solo (`7c5ba28`,
29/10), su istruzione del dev.

### Riquadro «Colore e testata» delle pagine

Non era nel piano: è stato aggiunto il 29/10 dopo il controllo dei campi dei moduli Drupal (sezione
«Controllo dei campi di inserimento»), su richiesta del dev, ed è fra i requisiti dell'overview. Vale per
tutte le pagine (`inc/pagine-statiche-admin.php`), come su Drupal i campi «Colore tematico» e «Layout»
della Pagina base.

### Task 6 immagine legacy

Il piano voleva anche `node__field_immagine_principale` (legacy) come ripiego. Sulla copia i 53 nodi con
il legacy hanno tutti anche il media, con lo stesso file: si legge solo il media (commento in
`wpf_drupal_da_sapere`).

### Task 10 ciclo da zero rifatto dopo le review

Il ciclo dal reset (task 10, passo 2) non era stato rifatto dopo le modifiche del 29/10. Rifatto il 29/10
dopo la seconda review, su richiesta del dev, seguendo `ricreare-wordpress-da-zero.md` passi 1-5: nessun
intervento a mano, conteggi e avvisi attesi scritti nell'howto («Ultima verifica»). L'unico scostamento
dall'howto: al passo 4 `applica --conferma` cambia anche lo stato del wizard di WPML dopo il primo giro
che crea i contenuti; un giro che non scrive nulla non lo cambia. Ora è fra gli avvisi attesi.

### Task 2 media e avviso

- `WPF_CONTENUTO_MEDIA` resta una costante: eventi e «da sapere» hanno gli stessi tre media (immagine,
  allegati, galleria) delle news e delle pagine.
- L'avviso finale «pagine e news sono aggiornate» diventa «pagine e contenuti sono aggiornati»: con più
  tipi il testo era falso. È l'unica differenza nei log delle news fra prima e dopo il refactoring.
- Oltre al ciclo, sono diventate comuni anche la lettura di un nodo (`wpf_drupal_nodo`, con
  `wpf_drupal_news` che la usa) e la creazione dei termini (`wpf_import_termini`, `wpf_import_tag`);
  l'anteprima delle news resta identica.

## Bug trovati

Dalla review del 29/10 (`wm-review-ticket`, 5 finder), tutti corretti e verificati in locale:
- **Tipologie multiple**: si importava solo la prima di `field_tipo_da_sapere`; 4 «da sapere» ne hanno più
  d'una (1102, 2336, 3691, 3996) e F.A.Q. aveva 10 contenuti invece di 12. Ora `tipologie` è un elenco.
- **Ordine dei «da sapere»**: per data dell'import. Ora la data del post è la creazione su Drupal (della
  versione originale anche per le traduzioni: WPML la allinea, e con quella della traduzione ogni giro la
  riscriveva) e «Sempre in cima» è `menu_order` 1 (12 contenuti); la pagina della tipologia ordina per
  `menu_order` e data (View `termine_tassonomia_da_sapere`: sticky, created), le schede per data
  (`sa_sapere_in_evidenza`: created). Ordine della tipologia 4705 identico alla copia (1413, 5236, 2336,
  1417, 1412, 569); Drupal online differisce dal quarto in poi perché i suoi dati non sono quelli della copia.
- **Eventi senza data**: la meta_query OR EXISTS/NOT EXISTS li ordinava sul valore di un meta qualsiasi
  (in cima alla lista). Ora un join sul meta con la chiave nella condizione (`forestas_ordine_eventi`) li
  manda in fondo; provato togliendo per un momento la data a un evento.
- **Immagine della tipologia cancellata da un download fallito**: ora un errore lascia l'immagine scritta.
- **Data obbligatoria aggirabile** dalla Modifica rapida o di massa: ora vale la data già salvata.
- **Redirect da `/<slug>` per tutti i «da sapere»**: era per il solo `/gradi_difficoltà`. Ora ogni
  contenuto porta nel meta `_wpf_alias_drupal` i suoi vecchi indirizzi (alias e `/node/<nid>`), come le
  pagine, e il child reindirizza solo quelli, con un solo meccanismo (`inc/pagine-statiche.php`). Il primo
  giro dopo questa modifica aggiorna tutte le news, gli eventi e i «da sapere» una volta (meta nuovo).
  Riprovati i 256 alias e i `/node/<nid>`: tutti 301, tranne `/node/52`, la Home, che non si importa.
- **`?anno[]=`** dava un PHP Warning a ogni richiesta; ora un valore non testuale non filtra.
- Minori: «Dove» vuoto con il solo paese (evento 1476); tipologia italiana senza `lang="it"` in inglese
  (card e testata); avviso della sincronizzazione mancante su immagini di eventi, «da sapere» e tipologie e
  nella schermata della tipologia; import senza controllo dei tipi registrati (ora si ferma);
  riassunto di `bin/wordpress-config.sh` che contava come tipo anche un «PHP Warning».
- Classi dei filtri rinominate (`__in-piu`, `__mostra` al posto di `__altro`, `__altri`) in CSS e
  `assets/js/filtri.js`. Child 1.7.4.

Dalla seconda review (29/10, sessione di review sul commit `7c5ba28`), tutti corretti e verificati in
locale:
- **Template di Impreza assente su un evento o un «da sapere» creato dal pannello**: il template lo
  scriveva solo l'import (`us_content_id`). Ora `config/impreza.json` ha `content_evento_id` e
  `content_da_sapere_id` (`@chiave:template-evento`, `@chiave:template-da-sapere`), applicati in locale.
  Provato creando un evento e un «da sapere» senza import: testata, Dove/Quando e corpo ci sono; poi
  cancellati, nessuna riga orfana in `icl_translations`.
- **Vecchio indirizzo di «Vestiti "a cipolla"» a 404**: `redirect_canonical` del core girava prima e
  toglieva la virgoletta finale. Il redirect ora è a priorità 9. Riprovati tutti i 406 vecchi indirizzi
  (alias e `/node/<nid>` di pagine, contenuti e tipologie pubblicati): tutti 301 verso una pagina 200.
- **Una sola tipologia mostrata**: card e testata ne mostravano la prima in ordine alfabetico. Ora tutte,
  separate da uno spazio come in `field--node--field-tipo-da-sapere--teaser` e `--full`, nell'ordine di
  Drupal (delta): la tassonomia ha `sort`, l'import confronta i termini nell'ordine e li scrive in
  `term_order`. Primo giro: 4 «da sapere» aggiornati (1102, 2336, 3996 it ed en), il giro dopo tutto
  «invariato». Il 2336 mostra «Norme di comportamento Come organizzare l'escursione Il fai-da-te
  dell'escursionista»; in inglese ogni nome italiano ha il suo `lang="it"`.
- Cleanup: card dell'evento senza immagine con il riquadro vuoto e la data (come il `thumb-placeholder` di
  `node--evento--teaser`); corpo delle pagine in un helper (`forestas_corpo_contenuto`); nomi neutri e file
  nuovi (sezione «Task 2 nomi delle funzioni e dei file»); termini degli eventi in una funzione con nome
  (`wpf_eventi_termini`); `genere` del descrittore a chiavi con nome e descrittore controllato da
  `wpf_import_tipi`; prefisso di eventi e «da sapere» letto dal rewrite del tipo; riassunto di
  `bin/wordpress-config.sh` dai tipi che `pagine.php` elenca (righe `TIPO`); un 404 con estensione o con
  `wp-` non cerca nei meta degli alias; token `--sase-bordo` al posto di `#F0F0F0`; commenti corretti in
  `style.css` e `inc/da-sapere.php`. Child 1.7.5.
- Verifiche: `pagine` in anteprima dopo il `--conferma` tutto «invariato» (news 97 + 2 della 4606, media
  mancante); prova dei filtri 0 errori; pagine principali 200, `?anno[]=x` 200; nessun avviso di PHP nel
  log del container.

Prima delle review:

- **Media senza estensione e KML** (28/10): 13 «da sapere» non avevano tutti i media, perché alcuni file
  Drupal hanno come nome una descrizione senza estensione e due allegati sono KML, che WordPress rifiuta.
  `media.php` prende il nome dall'uri quando manca l'estensione e accetta i KML solo durante l'import.
- **Card compatta allungata da una foto verticale** (29/10): la terza card di `/da-sapere/` era alta 276px
  invece di 180; ora la foto riempie la card senza cambiarne l'altezza.
- **Vecchi indirizzi Drupal a 404** (29/10): alias delle pagine con slug diverso, schede di News senza nodo,
  pagine delle tipologie (`/taxonomy/term/<tid>`). Ora portano alla pagina nuova con un 301.
- **Su Drupal**, da non copiare: la card dell'evento in `/eventi` mostra la data di oggi su tutti gli
  eventi; la pagina dell'evento scrive «Da … to …» con «to» non tradotto.

## Decisioni

- **Disposizione dei blocchi «in evidenza» come su Drupal (29/10, su richiesta del dev: la disposizione, non lo
  stile).** News, Attività e comunicazioni e Da sapere usano su Drupal `sase-grid--left-highlight-column`:
  due colonne uguali a 15px, la prima card su tre righe a sinistra, le altre tre a destra alte 180px con la
  foto larga 207px. Prima qui era 3fr 2fr a 20px con card compatte da 120px e foto al 38%. A 1920px le
  posizioni di `/attivita-e-comunicazioni/` coincidono con Drupal al pixel (griglia 1294×570, card 640×570 e
  640×180). Lo stile delle card resta quello del Canvas. Corretto anche un difetto: una foto verticale
  allungava la card compatta (terza card di `/da-sapere/`, 276px). Child 1.6.8.
- **Ordine del blocco «in evidenza» di Attività: resta per data mostrata (29/10, il dev conferma l'opzione B
  di oc:8759 «per adesso»).** Drupal ordina per il vecchio `field_date`, che le 4 news più recenti in evidenza
  (nid 5306, 4016, 3436, 3371) non hanno: lì in cima c'è «Oliena» (2022), qui «Attenzione sul sentiero 301».
- **Filtri e lista di `/attivita-e-comunicazioni/` come su Drupal (29/10, richiesta del dev).** Su Drupal la
  View `vista_attivita_e_comunicazioni` ha due faccette, «Tipologia attività» e «Anno attività» (link con il
  numero, ordine per numero e poi per valore, i primi 5 e «mostra altri»), in una colonna a sinistra
  (`sase-grid--3-9`), e la lista è a due colonne senza immagine. Qui:
  - campo `filtri` di `config/pagine.json`: la colonna dei filtri, con `?tipologia=<slug>` e `?anno=<AAAA>`
    nell'indirizzo; la paginazione tiene il filtro, una pagina oltre l'ultima della lista filtrata dà 404;
  - l'anno è quello della data mostrata, non `field_date`: su Drupal mancano 2024, 2025 e 2026 e quelle news
    non si raggiungono dal filtro (stessa scelta dell'ordinamento);
  - lista con le card senza foto di `/news/attivita-e-comunicazioni/` (variante `testo`, scelta del dev) a
    due colonne (campo `colonne`);
  - sotto 960px i filtri stanno dietro il pulsante «Filtri», come `sase-sidebar-mobile` di Drupal (lì è un
    pannello che si apre sopra la pagina, qui si apre sopra la lista);
  - a 945px lista e colonne coincidono con Drupal (837px, due colonne da 411,9px); la colonna a sinistra
    sopra i 960px, misurata poi a 1920px su `/eventi/` e `/tutte-le-news/`, coincide con Drupal (filtri a
    x 306 larghi 315, lista a x 655 larga 944). Child 1.6.9.
  - **stile dei filtri identico a Drupal (29/10, richiesta del dev)**, misurato a 1512px su Drupal e qui:
    gruppi chiusi all'apertura (aperto solo quello con un valore scelto), freccia in su a gruppo chiuso e in
    giù ad aperto come su Drupal, titolo Montserrat 18px, pulsante della freccia 45×45, voci alte 30,4px a
    8px, numero in un riquadro `#CA4D1C` su `#FFFDFA` (pieno nel gruppo con un valore scelto, voci in
    grassetto), valore scelto in fondo al gruppo, «Mostra di più»/«Mostra meno» centrato sotto un filo
    `#F0F0F0`. Altezze dei gruppi, posizioni di titolo, freccia e numeri coincidono al pixel. Diverso solo
    sotto 960px: su Drupal i filtri si aprono in un pannello sopra la pagina, qui sopra la lista. Child 1.7.1.
- **Filtri anche su Tutte le news ed Eventi (29/10, richiesta del dev: «allinea come su Drupal»).** Le faccette
  di Drupal sono su tre pagine: `/attivita-e-comunicazioni`, `/tutte-le-news` (Tipologia = i tag delle news,
  senza numeri; Anno in ordine di anno decrescente) ed `/eventi` (Tipologia = i tag degli eventi; Anno vuoto,
  perché nessun evento ha `field_date`). Nessuna sulle schede di News, su Da sapere e sulle pagine delle
  tipologie (verificato su Drupal online). Il campo `filtri` di `config/pagine.json` è ora l'elenco dei
  gruppi (`campo` tipologia | tag | anno, `titolo`, `numeri`, `ordine`), come `facets.facet.*.yml`; l'anno è
  quello della data mostrata (news) o dell'inizio (eventi), così il gruppo Anno degli eventi non è vuoto. A
  parità di numero i valori vanno in ordine alfabetico senza maiuscole («alert» prima di «App»).
- **Lista di `/eventi/` a una colonna come su Drupal**: card orizzontali con la foto a sinistra (308×224 su
  944, campo `colonne: 1`), filtri a sinistra; a 1920px posizioni identiche a Drupal. `/tutte-le-news/`: tre
  colonne da 304,75px a 15px, identiche. Lo stile della card dell'evento resta quello del Canvas. Su Drupal
  la teaser dell'evento mostra la data di oggi (29.10) su tutti gli eventi: non si copia. Child 1.7.2.

- **Etichetta sopra il titolo come su Drupal (29/10, su segnalazione del dev).** Su news, eventi e «da sapere»
  la tipologia (o «Eventi») ha la sua barretta come `sase-hero__category-text-decorated`: larga quanto il
  testo, alta 5px, 1em sotto il testo, al posto del filo 57×7 attaccato all'etichetta. Misure a 1440px
  identiche a Drupal (nid 5416, 5686, 5701): carattere 12,7px, barretta a 37px dall'inizio del testo, titolo
  a 70px. Il colore resta quello della sezione concordato (news `#C2410C`, Da sapere `#0F6E6A`), non quello
  di Drupal (`#F67745`, `#EA1F1F`). Child 1.6.6.

- **Vecchi indirizzi Drupal delle pagine → 301 verso i nuovi (29/10, su richiesta del dev).** Le differenze di
  indirizzo con Drupal si unificano con un redirect verso la versione nuova; se un giorno servirà, si
  ripristinano i vecchi slug. Verificati tutti gli alias pubblicati di pagine, news, eventi e «da sapere»
  della copia: 256, nessuno dà 404.

- **Tag di Orchestrator non proposti**: il dev ha chiesto di saltare la ricerca dei tag (28/10). Il
  ticket resta con `26Q4` e `wp-forestas`.
- **Piano scritto prima della stima**: `wm-estimate` non stima senza `plan.md`, quindi l'ordine
  overview → challenge → piano → stima.
- **Stima**: proposta di `wm-estimate` Misurato 0,94h + Stimato 7,8h = 8,7h; il dev ha fissato
  **10h**, scritte su Orchestrator il 28/10.
- **Struttura di Da sapere verificata su Drupal** (copia di UAT, 28/10): le schede del wireframe
  riprendono i blocchi della pagina nid 1083; le «Pillole» sono le tipologie con la spunta «Mostra in
  lista Da sapere» (oggi 6), che si importa come term meta per lasciarla modificabile.
- **Commenti**: il form Drupal dei «da sapere» li prevede ma non ce n'è nessuno; l'unico commento della
  copia è un «Commento di prova» del 2013 sull'itinerario nid 75.

- **Confronto visivo** (28/10, browser a 1440px, Drupal in produzione e Canvas Fase 2 tavola 8), dopo la
  richiesta del dev:
  - card dell'evento come nel Canvas (`.uc--evento`): data in un riquadro bianco sulla foto, etichetta
    «Evento», blu `#16497B`, sotto il titolo la data per intero;
  - pagina dell'evento come su Drupal (`is-event`): «Eventi» sopra il titolo con la barretta `#1271B7`,
    «Dove» e «Quando» in due colonne con il filo `#F0F0F0`, frecce e galleria in blu, icone dei file grigie;
    il paese del luogo è nel markup ma nascosto, come su Drupal;
  - «Quando» resta «Dal … al …» e, con inizio e fine uguali, una data sola: Drupal scrive «Da 16 Maggio
    2026 to 16 Maggio 2026» («to» non tradotto);
  - Da sapere come il Canvas: prima scheda con la prima card in evidenza e tre compatte, Norme con card
    senza foto, pillole su fondo `#F7F5F1`, FAQ in un pannello blu `#254877` con «›» e pulsante bianco;
  - le pillole seguono l'ordine dei pesi di Drupal, non quello del wireframe.

## Follow-up

- **Debito di architettura (review del 29/10, non bloccante):** card, griglie, query e filtri di tutti i
  tipi non sono più in `inc/news.php` (seconda review: `inc/contenuti.php` e `inc/griglie.php`), ma
  hanno ancora rami per tipo; la tassonomia delle tipologie è
  registrata in `inc/news.php` e agganciata al tipo in `inc/contenuti.php`; le costanti dei meta sono
  scritte due volte (script e child, con il rimando nei commenti); i filtri caricano tutta la lista a ogni
  richiesta, senza cache. Da riprendere quando arrivano homepage e plugin del catasto.

- **Riferimenti delle news da inserire e mostrare in WordPress** (campo «Riferimenti» del form della news di
  Drupal): quando il backend dello shard espone le API, il plugin dà il campo nell'editor e il blocco nella
  pagina della news; i dati importati sono già in `_wpf_riferimenti` (tipo e nid Drupal). Nessun ticket
  aperto: il dev lo sa (29/10).

- Homepage con i blocchi «in evidenza» di eventi e «da sapere»: ticket separato.
- Carte e zone geografiche: ticket separato (descrizione del ticket).

## Info per altri ticket

- **Campi dei moduli Drupal (staging Acquia, 29/10).** I moduli «Aggiungi contenuto» di News, Evento, Da Sapere
  e Pagina base hanno gli stessi campi della copia UAT, e l'import li legge tutti tranne autore, «Promosso»,
  commenti (vuoti) e messaggio di revisione; «Authored on» e «Sempre in cima» solo per i «da sapere», dove
  ordinano gli elenchi.
- **Campo «Layout» delle pagine (`layout_selection`).** L'import legge solo il layout personalizzato
  (`layout_builder__layout`), non la tendina dei layout predefiniti. La usano 4 pagine: Home (nid 52,
  `homepage`: per il ticket della homepage), News (1084) e Attività e comunicazioni (1086), entrambe
  `pagina_con_solo_titolo_centrato`, e Mappa interattiva (1093, `pagina_solo_body`, che in WordPress ha già il
  template `mappa`).
- **Vecchi indirizzi delle pagine (risolto qui, 29/10).** I vecchi alias Drupal delle pagine pubblicate
  davano 404 su WordPress quando lo slug era diverso (`/pagina-base/privacy`, `/pagina-base/faq`…) o la
  pagina era costruita senza nodo (`/tutti-gli-eventi`, nodo 1085; `/pagina-attivita-e-comunicazioni`,
  nodo 1086). Ora ogni pagina porta nel meta `_wpf_alias_drupal` i vecchi indirizzi suoi e dei nodi del
  campo `sostituisce` di `config/pagine.json`, e il child ci manda con un 301. Il ticket della homepage
  può usare lo stesso campo per il nodo 52.
- **`/node/52` (la Home di Drupal) dà 404**: la Home non si importa; il ticket della homepage può dare alla
  pagina il campo `nid` o `sostituisce` di `config/pagine.json` per avere il redirect.
