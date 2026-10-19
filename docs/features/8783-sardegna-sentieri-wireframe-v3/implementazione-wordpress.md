> Ticket: oc:8783

# Implementazione in WordPress delle pagine del wireframe v3

Indicazioni tecniche per costruire le pagine nel tema Impreza con il child `forestas-child`. Stato al
19/10/2026. Il perché delle scelte e le fonti sono in [decisioni.md](decisioni.md).

Regole generali:

- **Misure reali, non quelle del prototipo.** Il prototipo misura in proporzione (`cqw`) su una tavola
  di ~1130px che rappresenta 1440px. In WordPress valgono i valori del sito attuale (SCSS del tema
  Drupal `sardegnasentieri`, CSS in produzione) e i breakpoint del child.
- **Accessibilità**: obiettivo WCAG 2.1 AA (EN 301 549, linee guida AgID). Per ogni componente sono
  indicati i criteri e i rapporti di contrasto calcolati. Il widget di accessibilità è un aiuto in
  più, non sostituisce la conformità del sito.
- **`prefers-reduced-motion`**: ogni transizione, ingrandimento, sollevamento, scorrimento automatico
  e animazione di apertura si spegne (WCAG 2.3.3).
- **Ordine visivo = ordine nel codice**: niente riordino con `order` o `flex-direction: reverse`
  (1.3.2, 2.4.3).
- **`<a>` per ciò che porta altrove o scarica, `<button>` per le azioni in pagina** (4.1.2).

## Breakpoint

| Soglia | Dove | Cosa cambia |
|---|---|---|
| 1025px e oltre | `tablets_breakpoint` di Impreza, media query del child | intestazione desktop, riga compatta sticky |
| fino a 1024px | idem | intestazione mobile con hamburger, sticky |
| fino a 767px | tema Drupal | piedipagina a una colonna, loghi su griglia a 12 colonne, carosello a 3 card |
| fino a 600px | `mobiles_breakpoint` di Impreza | regole mobili del child |
| fino a 560px | tema Drupal | card in una colonna, carosello a 2 card |
| altezza fino a 500px | intestazione e testata compatta | nulla resta fisso (come il Drupal sotto 480px) |

Le soglie di 860px e 760px che si leggono nel CSS del prototipo sono soglie della tavola: in WordPress
si usano quelle della tabella.

## Identità visiva

Valori del tema Drupal, già nelle Theme Options di Impreza e nel child (oc:8757).

- **Caratteri**: titoli Montserrat 600, testo Work Sans 400 #4D4D4D, interlinea 1.4; solo pesi 400 e
  600 (un 500 rende 400, un 700 rende 600).
- **Link** #CA4D1C, al passaggio #D63D00 sottolineato. Sui fondi grigi #F5F5F5 il link scende a
  4,20:1: nelle tabelle e negli elenchi su fondo grigio usare #B83F14 (5,59:1 sul bianco, 5,12:1 sul
  grigio), al passaggio #8F2F0D con sottolineatura più spessa (1.4.3). Link nel testo sempre
  sottolineati (1.4.1).
- **Primario** #F59F1A, **secondario** #16497B.
- **Fondi** #FFFFFF, #FCFCFC, #F5F5F5, #FFF7EB, #F6FAFF (celeste delle sezioni), #333333.
- **Bordi** #F0F0F0, #D0D5DD.
- **Testo attenuato** #666666, non #767676 (su #F5F5F5 scende a 4,17:1).
- **Focus** 3px #0089C7 con anello bianco, solo con `:focus-visible` (2.4.7); visibile su fondo chiaro
  e scuro. Verificare che Impreza non lo tolga.

### Colori delle entità e delle sezioni

Da definire come variabili CSS del child e usare ovunque per la stessa sezione (3.2.4).

| Uso | Colore | Contrasto |
|---|---|---|
| Sentiero | #DE1B0D | bianco 4,92:1 |
| Itinerario | #58810B | bianco 4,61:1 (il #608D0D del Drupal si ferma a 3,95:1) |
| Cammino | #1A416F | bianco 10,35:1 |
| Punto di interesse | #B7127F | bianco 6,16:1 |
| News | #C2410C | bianco 5,18:1 (il #F67745 del Drupal 2,75:1) |
| Da sapere | #0F6E6A | 6,07:1 sul bianco |
| Eventi | #16497B | bianco 9,24:1 |
| Mappa interattiva (menu) | #F59F1A | 2,13:1 sul bianco: sotto il 3:1 di 1.4.11, come nel sito attuale; punto aperto |

Fasce chiare dei titoli di sezione nelle schede (fondo / barra sinistra 4px): sentiero #FDEEED /
#DE1B0D, itinerario e itinerario a tappe #EFF4E6 / #58810B, cammino #E8ECF1 / #1A416F, punto di
interesse #F8E9F3 / #B7127F. Testo #1A1A1A Montserrat 600 16px, oltre 14:1 su tutti i fondi.

### Semaforo e stati

Variabili CSS del child, usate da home, ricerca e schede.

| Stato | Colore | Testo |
|---|---|---|
| Percorribile (livelli 4–5) | #0E7D2C | bianco 5,27:1 |
| In revisione o parzialmente percorribile (2–3) | #F59B23 | #333333 5,77:1; mai #F59B23 come testo (2,19:1 sul bianco) |
| Non percorribile (1) | #B70827 | bianco 6,82:1 |

Lo stato è sempre scritto accanto al colore (1.4.1).

## Intestazione e piedipagina

Già realizzati in WordPress con oc:8757 (vedi la pagina di conoscenza). Differenze decise sul
wireframe:

- **Logo** definitivo senza «Beta» (file vettoriali di Forestas del 16/10).
- **«OPEN DATA»** ultima voce del menu di servizio, IT ed EN, verso la pagina dei dati aperti; icona
  ufficiale dei dati aperti, decorativa (`aria-hidden`), il testo resta il nome.
- **Loghi dei finanziatori solo nel piedipagina**: la riga dei loghi dell'header (`[forestas_loghi_header]`)
  si toglie quando arriva la conferma della referente UE.
- **Voce «Punti di interesse»** al posto di «Da vedere»: è circa 85px più lunga. Verificare il menu
  alle larghezze intermedie (1025–1199px) prima di ridurre gli spazi.
- **Voce attiva del menu**: barretta nel colore della sezione con almeno 3:1 sul bianco (1.4.11) e
  `aria-current="page"` (1.3.1, 4.1.2); la voce si distingue anche dal nome.
- **Riga compatta sticky su desktop** (da 1025px): quando l'intestazione esce dalla vista compare una
  riga fissa con logo e menu, alta quanto la riga di servizio (47px), logo largo 170px, menu 12px,
  voce attiva sottolineata. È la stessa intestazione ridotta, non una copia: resta navigabile con lo
  screen reader. Nel sito attuale la versione compatta su desktop non è attiva: è un comportamento
  nuovo.
- **Intestazione mobile sticky** fino a 1024px, come oggi (`.l-canvas` con `overflow: clip`, vedi le
  trappole del `CLAUDE.md`).
- **Altezza fino a 500px**: nessuna intestazione fissa (1.4.10).
- **«Salta al contenuto principale»** come primo elemento di ogni pagina, nascosto finché non riceve
  il focus con il Tab, come «Inizio contenuto principale» del sito attuale (2.4.1).
- **Spazio per il focus**: `scroll-padding-top` pari all'altezza di ciò che resta fisso (120px nel
  prototipo, 170px nelle schede con la fascia del nome), così l'elemento con il focus non finisce
  sotto (2.4.11 di WCAG 2.2, richiesto da AgID).

**Piedipagina**:

- sette loghi: Coesione, UE, Repubblica, Regione (PR FESR 2021-2027), Forestas Sentieristica (nuovo,
  al posto di «Sardegna Foreste»), CAI, «Sardegna Sentieri per tutti»;
- fino a 767px loghi su griglia a 12 colonne: i quattro finanziatori larghi 3/12 sulla prima riga, i
  tre enti larghi 4/12 sulla seconda;
- link «Preferenze cookie» a destra nella striscia del copyright, bianco su #333 (12,6:1), in tutte le
  pagine nella stessa posizione (3.2.3);
- striscia del copyright con 88px di spazio in più in fondo (80px sotto 600px), per i pulsanti fissi.

## Pulsanti

Un solo sistema, uguale in tutte le pagine: principale e secondario, stessa forma.

- **Principale** (`sase-btn` del sito): fondo e bordo #F59F1A, testo #333 (5,93:1), Work Sans 600,
  altezza 3em (48px), larghezza minima 165px, padding laterale 1.25rem, raggio .25em, transizione
  .3s. Passaggio #D63D00 testo bianco (4,64:1); premuto #CA4D1C testo bianco (4,58:1). Usi: Scarica
  GPX, Scarica il GPX dell'itinerario/del cammino, Vedi sulla mappa interattiva (icona a destra,
  decorativa), Cerca, Scarica N sentieri, Invia.
- **Secondario** (`sase-btn-light`): fondo bianco, testo #4D4D4D (8,45:1), bordo 1px del colore del
  testo con destro e inferiore 2px (oltre 3:1, 1.4.11). Passaggio fondo #F59F1A con testo **#333**
  (5,93:1; con #4D4D4D sarebbe 3,97:1): da impostare nelle Theme Options. Premuto #16497B testo
  bianco (9,24:1). Resta bianco anche sui fondi scuri. Usi: Scopri tutti…, Tutte le news, Tutti i Da
  sapere, Tutti gli eventi, Apri la mappa interattiva, Vedi i percorsi dell'area, pulsanti dello
  slider, FAQ, Stampa, Condividi, Segnala, Scrivi una recensione, Stampa la mappa di insieme, Scarica
  le schede PDF.
- Al massimo un principale per blocco: la gerarchia non dipende solo dal colore (1.4.1, 2.4.6).
- Nessun pulsante si sottolinea al passaggio, anche se fatto con `<a>`.
- Non usare la variante `.sase-btn-cta` (bianco su arancio, 2,13:1).
- Pulsante disabilitato con `aria-disabled`.
- Testo che dice cosa fa e su cosa, anche letto da solo (2.4.4, 2.4.6); il testo visibile è contenuto
  nel nome accessibile (2.5.3). Dove possibile «Scarica GPX» con formato e peso del file.

## Card unica

Una sola card per tutte le anteprime (in Impreza: un layout di Grid Layout più uno «in evidenza»).

- Foto 3/2 in alto, etichetta del tipo in #4D4D4D con filetto del colore della sezione, titolo
  Montserrat 600 #333 (12,63:1), riga meta #4D4D4D (8,45:1) con data in `<time datetime>`, rubrica o
  km/tempo.
- Card cliccabile per intero con **un solo link**, sul titolo, esteso con `::after` (2.4.4, 2.5.3);
  titolo come heading con link (1.3.1); focus visibile su tutta la card (2.4.7).
- Angoli 6–8px, ombra leggera che cresce al passaggio, foto che si ingrandisce appena; tutto spento
  con `prefers-reduced-motion`.
- Foto con `alt=""`: il titolo descrive la card (1.1.1).
- Titoli lunghi a capo, mai troncati con i puntini.
- Altezze minime, mai fisse (1.4.4, 1.4.12); fino a 560px una colonna (1.4.10).

Varianti:

- **in evidenza**: foto a tutta card, testo bianco su sfumatura nera all'85% in basso (oltre 10:1 nella
  zona del testo; verificare con foto molto chiare);
- **compatta**: foto a lato, per le card accanto a quella in evidenza (`--has-side-thumb-left` del
  Drupal);
- **evento**: data grande (giorno e mese) sulla foto con `aria-hidden`, la data vera resta in
  `<time>`;
- **senza foto**: filetto in alto.

Dove: home (punti di interesse: una in evidenza più quattro; ultimi contenuti: quattro base), Da
sapere e News (una in evidenza 3/5 più tre compatte 2/5; elenchi senza foto in tre colonne; eventi
con data), correlati delle schede (griglia a tre colonne), caroselli del catasto e del catalogo in
home.

## Pulsanti fissi e cookie

Strumento di accessibilità e gestore dei cookie non ancora scelti. Disposizione decisa:

- **Strumenti di accessibilità** nell'angolo in basso a sinistra, a 16px dal bordo e dal fondo, 38×38px,
  raggio 5px, icona bianca su #16497B (9,24:1). Non si sposta mai (3.2.3).
- **Preferenze cookie** subito a destra, a 66px dal bordo (16 + 38 + 12), stessa misura. Con iubenda:
  `floatingPreferencesButtonDisplay: "bottom-left"` più un override CSS del margine sinistro (iubenda ha
  un margine fisso di 16px e nessuna opzione per spostarlo; usa `!important`, da ricontrollare a ogni
  aggiornamento). Alternativa senza override: iubenda nell'angolo e il widget di accessibilità
  spostato di 66px.
- **«Torna su»** di Impreza in basso a destra (`.w-toplink`, 50×50px, raggio .3rem, 15px dai bordi,
  10px sotto 600px, passaggio #F59F1A, focus #0089C7). Il suo contrasto (bianco su grigio ≈ 2:1, su
  arancio 2,1:1) è sotto il 3:1 di 1.4.11: da correggere.
- `html { scroll-padding-bottom: 70px }`: l'elemento con il focus da tastiera viene portato sopra i
  pulsanti (2.4.11).
- Distanza fra i due pulsanti a sinistra 12px (≥ 8px, 2.5.8). 38px rispetta 2.5.8 (AA), non 2.5.5
  (44px, AAA): limite di iubenda, da dichiarare.
- Il pannello di accessibilità si apre sopra la riga dei pulsanti, senza coprire il pulsante che lo
  chiude.

Requisiti per scegliere lo strumento di accessibilità:

- posizione in basso a sinistra, dimensione regolabile vicino a 38px;
- pulsante `<button>` con nome («Apri gli strumenti di accessibilità»), `aria-expanded`,
  `aria-controls`; all'apertura il focus va alla prima funzione, Esc o × chiudono e riportano il focus
  al pulsante; funzioni come interruttori con `aria-pressed` (4.1.2, 2.1.1, 2.4.3);
- meglio se apribile anche da un link fra i primi elementi raggiungibili con Tab;
- link alla Dichiarazione di accessibilità, che resta anche nel piedipagina.

Banner dei cookie (linee guida del Garante 2021): Rifiuta e Accetta dello stesso peso, «Scopri di più e
personalizza», X che chiude senza accettare; pannello delle preferenze come dialog modale con focus
all'apertura, Tab che resta dentro, Esc che chiude e riporta il focus; categorie non preselezionate
tranne i necessari. Pulsante delle preferenze di iubenda: verificare contorno ≥ 3:1 (o impostare
`floatingPreferencesButtonColor` scuro, es. #16497B con testo bianco 9,24:1) e nome accessibile.

## Home

Sezioni a tutta larghezza, stessa spaziatura verticale (50px del sito), nessun filo di separazione.
Sfondi alternati: ricerca e stato della rete bianco · aree territoriali celeste #F6FAFF · mappa
interattiva bianco · catasto celeste · catalogo bianco · punti di interesse celeste · ultimi contenuti
bianco. Testi sul celeste: #333 12,06:1, #4D4D4D circa 8:1. Ogni sezione ha un titolo vero con filetto
sopra e testo centrato (1.3.1, 2.4.6).

### Fascia degli avvisi

- In cima, sopra lo slider; si stampa solo se c'è almeno un avviso attivo.
- Etichetta «Avviso» bianco su #B70827 (6,82:1) con triangolo SVG bianco `aria-hidden` (la parola
  resta, 1.1.1, 1.4.1); riga su #FDECEC, testo #1A1A1A (15,24:1), data #595959 (6,13:1), «Leggi tutti
  gli avvisi» #8F2F0D (7,13:1).
- Con più avvisi: cambio ogni 5 s, contatore «1 di 3», pulsante pausa/riprendi con `aria-pressed` e
  nome che cambia («Ferma» / «Riprendi lo scorrimento degli avvisi») (2.2.2). Con
  `prefers-reduced-motion` partono fermi.
- Ogni avviso è un link alla scheda del sentiero; «Leggi tutti gli avvisi» apre `/avvisi`.

### Slider

Identico al sito attuale (template `block--views-block--slider-homepage-block-1.html.twig`,
`_slider-main.scss`, `_hero.scss`, `_slick-slider.scss`, `js/scripts.js`):

- slick con `fade`, `speed: 600`, `autoplaySpeed: 3000` (un cambio ogni ~3,6 s), `infinite: false`
  (all'ultima slide torna indietro), niente frecce, dots numerati (cerchi 2,5em, numero bianco,
  attivo con bordo #F59F1A, nome «Vai alla slide N», `aria-current`);
- pulsante pausa/riprendi in alto a sinistra (3em, cerchio, fondo nero al 50%, bordo bianco 2px), con
  etichetta che cambia; scorrimento fermo al passaggio e al focus; parte in pausa con
  `prefers-reduced-motion` (2.2.2, 2.3.3);
- link nascosto «Salta lo slider dei contenuti in evidenza…» verso l'ancora della sezione successiva
  (2.4.1); slide non attive nascoste agli screen reader e fuori dal Tab (4.1.2, 2.4.3);
- altezza `clamp(37.25em, 66vh, 40.5em)`; contenuto centrato in altezza e allineato a sinistra, in
  basso solo i numeri; velo nero uniforme dal 45% al 60%; padding a destra 20%; titolo Montserrat
  `clamp(2.5rem, …, 3.438rem)`; categoria maiuscola con filetto 3px del colore di sezione;
  sottotitolo 1–1.125rem, interlinea 1.6; pulsante secondario;
- testo bianco ≥ 4,5:1 sul velo: verificare foto per foto (1.4.3).

### Ricerca

- `<form role="search" aria-label="Cerca un percorso">` con `action` verso la ricerca unica.
- Etichetta visibile «Cerca un percorso — sentieri, itinerari, cammini, aree e comuni», segnaposto
  «Dove vuoi andare?» (il segnaposto non è un'etichetta, 1.3.1, 3.3.2); pulsante «Cerca» principale.
- Campo vero (`<input type="search">`) legato all'etichetta, pulsante `<button>`; segnaposto
  #595959 su bianco (7:1). Le parole «area territoriale, difficoltà, attività» sotto il campo sono
  link (in un prototipo precedente sembravano link senza esserlo).
- Stesso nome nella home e nella ricerca unica (3.2.4); nome diverso da «Cerca nel sito»
  dell'intestazione (2.4.6).
- Nella pagina dei risultati di «Cerca nel sito» (ricerca di WordPress) un rimando in cima «Cerchi un
  percorso? Usa la ricerca dei sentieri» che porta il testo cercato alla ricerca unica.

### Stato della rete

- Totale dei sentieri a catasto e tre colonne a semaforo con filo superiore 6px e pallino del colore
  di stato. Testi scuri, il colore solo su filo e pallino.
- Data dei dati sotto i numeri.
- Conteggi calcolati dal catasto Forestas. Finché la percorribilità non c'è si usa lo stato di
  validazione attuale: verde «Percorribile» + «Validato», giallo «In revisione-validazione» + «In
  pre-accatastamento o manutenzione», rosso «Non percorribile» (etichette «Percorribili o validati»,
  «In revisione o in pre-accatastamento», «Non percorribili»). Con la percorribilità: livelli 4-5,
  2-3, 1.

### Aree territoriali

- **Mappa SVG inline** con le 39 aree; ogni zona è un pulsante (`role="button"`,
  `aria-pressed`) raggiungibile da tastiera, con nome «<area>, N percorsi» (2.1.1, 4.1.2). Clic,
  Invio o Spazio selezionano l'area e aggiornano la card; il passaggio del mouse e il solo focus non
  selezionano (altrimenti la card cambia mentre il mouse va verso il pulsante e lo screen reader
  annuncia ogni zona attraversata): al passaggio del mouse si evidenzia solo il bordo.
- Fasce per numero di percorsi (sentieri del catasto più itinerari), calcolate lato server: nessuno
  #F5F5F5, 1–10 #DCEAF6, 11–30 #A9CBEA, 31–60 #5E9BD3, oltre 60 #1271B7; legenda scritta sotto
  (1.4.1).
- Bordi delle zone #16497B sulle fasce chiare (da 5,46:1 a 8,47:1), bianchi sulle scure; zona scelta
  #F59F1A con contorno #1A1A1A 2px (1.4.11); numeri neri con alone bianco (17:1); focus da tastiera
  con contorno #1A1A1A spesso 4,5 unità: almeno 3,37:1 su tutte le fasce (un contorno azzurro
  #0089C7 scende a 1,33:1 sul blu #1271B7); nessun rettangolo dopo il clic del mouse
  (`:focus-visible`) (2.4.7, 1.4.11).
- Al cambio d'area una sola frase in `aria-live="polite"`: «Area selezionata: <area>, N percorsi»
  (4.1.3).
- **Card dell'area**: foto in evidenza del layer dell'area in qualità originale, `alt=""` (1.1.1);
  categoria «Area territoriale» con filetto; nome; «N percorsi · di cui N sentieri del catasto»;
  descrizione al massimo 4 righe a vista, intera per lo screen reader; pulsante secondario «Vedi i
  percorsi dell'area». Senza foto: riquadro «Immagine non disponibile» #5B6B7D su #E9F1F9 (4,79:1).
  Se il pulsante apre una nuova scheda: icona di link esterno e testo nascosto «(apre … in una nuova
  scheda)» (3.2.5).
- Mappa e card affiancate con la stessa altezza; la card ha altezza minima, non massima: la foto si
  riduce per prima e la card cresce se il testo non ci sta (1.4.4, 1.4.12). Su schermi stretti una
  sotto l'altra.
- **Elenco delle aree** in `<details>`/`<summary>` nativi (Invio e Spazio, stato annunciato; 2.1.1,
  4.1.2): riepilogo centrato «Vedi le aree come elenco» #16497B su #F6FAFF (8,81:1) con la freccia del
  browser. Aperto: riquadro bianco con bordo #D0D5DD e margini laterali, frase introduttiva, tre
  colonne (due fino a 900px, una fino a 560px) separate da un filo, testo 16px interlinea 1.4, righe
  alte almeno 40px, nome dell'area sottolineato #B83F14, numero in etichetta #16497B su #E9F1F9
  (8,1:1) con la parola «percorsi» letta (1.3.1); niente parole spezzate.

### Mappa interattiva

- Titolo di sezione con filetto #F59F1A.
- Anteprima della rete a tutta larghezza (immagine ad alta risoluzione o mappa vera), `alt=""`
  perché il riquadro la descrive; riquadro bianco a destra con «Tutta la rete sulla mappa
  interattiva», una riga di testo e il pulsante secondario «Apri la mappa interattiva» verso
  `/mappa-interattiva`, nella stessa scheda. Su schermi stretti il riquadro scende sotto l'immagine.
- La mappa vera deve mostrare l'attribuzione © OpenStreetMap (licenza ODbL).

### Caroselli del catasto e del catalogo

- Modello del sito (`.sase-slider--card-carousel.has-bg-image`, viste `tipologie_sentieri_slider` e
  `tipologie_itinerari_homepage`): slick con nove card, 5 visibili (4 fino a 1024px, 3 fino a 767px,
  2 fino a 560px), `infinite: false`, una card per clic; testata con foto di sfondo del tema e velo
  nero al 50% (verificare il testo bianco sulle zone chiare, 1.4.3).
- Card nello stile della card unica: foto, etichetta «Sentieri» o «Itinerari» con filetto
  dell'entità, nome con icona dell'attività del tema (`icons/trekking.svg`, `hiking.svg`,
  `per-tutti.svg`, `mountain-bike.svg`, `sentiero-equestre.svg`: cerchio in `currentColor`, figura
  bianca; decorative), numero e km con la parola «sentieri/itinerari» nascosta alla vista ma letta.
  Numeri con la virgola decimale (818,9).
- Prime cinque card del catasto nell'ordine: Sentieri lunghi, Camminate giornaliere, Sentieri per tutti
  (con il marchio RES dell'Allegato H al posto della foto, `alt=""`, chiedere a Forestas il
  vettoriale), Mountain Bike, Turismo equestre; poi le altre quattro tipologie del sito. Rinominare
  «Trekking» e «Hiking» anche in filtri e schede (3.2.4).
- Frecce come `<button>` con nome («Tipologie precedenti», «Tipologie successive») e `aria-disabled`
  agli estremi invece di sparire (4.1.2); card fuori vista con `inert` (2.4.3); link «Salta lo
  slider…» visibile al focus (2.4.1); 6px di spazio interno nel riquadro del carosello perché ombra e
  focus non vengano tagliati (2.4.7). Nessun movimento automatico.

### Punti di interesse e ultimi contenuti

- Punti di interesse: card unica, una in evidenza (senza etichetta «In evidenza») più quattro; link
  secondario «Scopri tutti i punti di interesse».
- Ultimi contenuti in evidenza: quattro card base miste (news, Da sapere, eventi). Serve una query su
  più tipi di contenuto, ordinata per data o per un campo «in evidenza» (criterio da decidere). Tre
  link secondari: Tutte le news, Tutti i Da sapere, Tutti gli eventi.

## Ricerca unica

- **Filtri** in `<fieldset>` con `<legend>`, caselle vere con il numero di risultati: Percorribilità
  (preselezionata su 4, dichiarata e disattivabile), Validazione catastale per fasce, Attività,
  Difficoltà, Dove. Ogni voce con livello e nome scritti (1.3.1, 1.4.1).
- Frase «OR dentro lo stesso filtro, AND fra filtri diversi» scritta sopra i risultati.
- **Indirizzo** aggiornato a ogni filtro (`/cerca?attivita=…&percorribilita=4&…`), condivisibile e
  indicizzabile; mostrato in pagina.
- **Elenco | Mappa**: due `<button>` con `aria-pressed` (o schede vere); la voce scelta cambia anche il
  fondo, non solo il colore (4.1.2, 1.4.1). La mappa sostituisce l'elenco nella stessa pagina,
  inquadrata sui risultati, con i tracciati nel colore del sentiero (#DE1B0D sulla carta 4,05:1) e il
  codice scritto accanto; l'elenco resta l'alternativa testuale (1.1.1). Cambiando vista restano
  filtri, posizione e selezione.
- **Riga del risultato**: casella `<input type="checkbox">` con `aria-label` «Seleziona <codice>
  <nome>»; codice, nome, comuni, km, dislivello, tempo, stato scritto; riga selezionata su #F6FAFF.
- «Seleziona tutti i N sentieri della ricerca» seleziona l'intera ricerca, non solo la pagina.
- Sotto, link «Guida all'uso del download: consigli per scaricare e stampare più sentieri» verso una
  pagina redazionale (2.4.4).
- **Barra della selezione** `role="region"` con nome, fissa in fondo mentre si scorre (sticky), fondo
  #16497B:
  - riepilogo in `aria-live="polite"` (numero, km, dislivello) e «Deseleziona tutto» (4.1.3);
  - gruppo «Tracce»: formato GPX/KML/GeoJSON come radio in `<fieldset>` con `<legend>`, pulsante
    principale «Scarica N sentieri in GPX» che riporta il formato scelto;
  - gruppo «Mappa e schede»: «Stampa la mappa di insieme» e «Scarica le schede PDF», secondari, con
    testo nascosto «dei N sentieri selezionati» (2.4.6, 2.5.3);
  - frase «Con molti sentieri i file richiedono più tempo: li prepariamo e ti avvisiamo quando sono
    pronti.»: oltre la soglia tecnica i file si preparano in modo asincrono;
  - bianco su #16497B 9,24:1; la barra non deve coprire i pulsanti fissi.
- La pagina ha il piedipagina come le altre; i pulsanti fissi si fermano sopra di esso.

## Schede (sentiero, itinerario, cammino, itinerario a tappe, punto di interesse)

Struttura comune; le differenze per tipo sono più sotto.

### Avviso

Se c'è un avviso attivo sul contenuto: subito sotto l'intestazione e prima della foto, stesso
componente della fascia della home (triangolo, «Avviso», testo, scadenza).

### Testata con foto

- Altezza come `.sase-hero--has-image-background` del sito: da 1025px `clamp(31.25em, 66vh, 37.5em)`
  (500–600px), fra 768 e 1024px `clamp(17.86em, 66vh, 50vh)`, fino a 767px automatica con minimo
  17.86em. Immagine di testata con `alt` descrittivo (1.1.1).
- **Fascia scura** sul fondo della foto: `rgba(26, 26, 26, .60)` piena dalla prima riga di testo in
  giù; sopra, 64px di sfumatura da trasparente al 60%. Il 60% è il minimo: sulla foto più chiara il
  bianco sta a 4,54:1 (al 59% scenderebbe a 4,42:1). Tenerlo esatto (1.4.3).
- Nella fascia: riga del tipo e dell'area («Sentiero · Area territoriale del …»), titolo `<h1>` con la
  bandierina, comuni e provincia, stelle e recensioni.
- **Bandierina** dentro il titolo, dopo il nome: bande verticali rosso #C8102E, bianco, rosso, codice
  in #1A1A1A 600 (17,4:1); un separatore nascosto fa leggere «S'Arena — Arcu Gennargentu,
  sentiero T 721» (1.3.1). Codice dal `full_code` del registro, non dal `ref`. Classe riusabile anche
  nella testata compatta.
- **Stelle**: decorative, voto scritto («4,2 su 5 · 38 recensioni») con link alle opinioni; giallo
  #FFD873 (3,31:1 sulla fascia, 1.4.11).

### Riga di azioni

Subito sotto la testata: «Scarica GPX» principale (`<a>` di download), «Stampa», «Condividi»,
«Segnala» secondari (`<button>`, `aria-expanded` e `aria-controls` dove aprono qualcosa). «Segnala»
ha nome accessibile «Segnala un problema». Stessa riga per sentiero, itinerario e cammino; il punto di
interesse ha solo Stampa e Condividi (niente segnalazione, come nel sito attuale). Nessuna azione è un
`<span>`: devono essere raggiungibili con il Tab e annunciate come pulsanti o link (2.1.1, 4.1.2), come
«Vedi sulla mappa interattiva» e i rimandi «Scopri…» delle sezioni.

**Condividi** — componente unico per tutte le schede, senza servizi esterni (niente AddToAny, script o
cookie di terzi):

- dove disponibile `navigator.share` (menu di condivisione del sistema);
- altrimenti un pannello «Condividi questa pagina» con WhatsApp, Facebook, X, Telegram, Email (indirizzi
  di condivisione semplici) e «Copia link» (Clipboard API) con conferma «Link copiato» in
  `role="status"` (4.1.3);
- ogni voce con icona e nome scritto (1.1.1); `aria-expanded` sul pulsante; Esc e clic fuori chiudono,
  il focus va alla prima voce e torna al pulsante (2.1.2, 2.4.3).

### Fascia della percorribilità

- Titolo «Percorribile, stato aggiornato il 4 giugno 2026» con la data in `<time datetime>`; il «·»
  visibile è `aria-hidden` e sostituito da una virgola letta.
- Righe «Inserito da <ente>.» e «Nota: …».
- Colori per stato, testo sempre scuro (1.4.3):

| Stato | Fondo | Titolo |
|---|---|---|
| Percorribile | #EEF6F0, accento #0E7D2C | #0B6624 (6,49:1) |
| Parzialmente percorribile | #FFF4DB | #6B4A00 (7,38:1) |
| Non percorribile | #FDECEC | #9B0720 (7,52:1) |

Nota sempre #333 (oltre 11:1). Lo stato è scritto, non affidato al colore (1.4.1).

### Tre indicatori a tacche

- Validazione catastale 6 tacche, percorribilità 5 tacche, accessibilità 3 tacche AT · AE · AEE con
  le etichette sotto e le stelle delle dotazioni.
- Tacche piene: validazione #8A6A1E (5,05:1), percorribilità #0E7D2C (5,27:1), accessibilità #254877
  (9,24:1); vuote con bordo #8C8C8C (3,36:1) (1.4.11).
- Valore sempre scritto («4 su 6 · Percorribile») con una riga di spiegazione; tacche con nome per lo
  screen reader («livello 4 su 6») (1.1.1, 1.4.1).
- «i» come `<button>` con `aria-expanded` che apre la legenda con tutti i livelli e il link
  all'articolo di approfondimento; Esc chiude (4.1.2).

### Corpo

- **Titoli di sezione** su fascia chiara con barra sinistra 4px del colore dell'entità; restano
  heading veri nella gerarchia della pagina (1.3.1, 2.4.6).
- **Il sentiero in breve**: lunghezza, dislivello positivo, tempo a piedi, quote minima e massima.
- **Attività e avvertenze**: `<table>` con intestazioni di colonna (Attività, Difficoltà, Tempo).
- **Galleria** (`section.sase-gallery`, `.sase-grid--4`): 3 colonne su mobile, 4 da tablet, spazio
  15px, miniature quadrate `object-fit: cover`; al passaggio e al focus un tondo 3em
  `rgba(0,0,0,.8)` con la lente «+» bianca (2.4.7, 1.4.13). Ogni miniatura è un link con nome = alt
  (2.4.4); testo alternativo vero obbligatorio al caricamento (1.1.1). Foto grande in finestra modale:
  focus sul pulsante di chiusura, Esc e frecce da tastiera, focus che torna alla miniatura, «foto N di
  M» annunciato (`aria-live`), pulsanti «Foto precedente», «Foto successiva», «Chiudi la galleria»
  (2.1.1, 2.1.2, 2.4.3, 4.1.2, 4.1.3).
- **Dati catastali e altimetrici** (solo sentiero), in fondo:
  - tre `<table>` (Percorso, Misure, Catasto), ciascuna con `<caption>` resa come testata della
    tabella: fascia #FDEEED dentro il riquadro, senza barra, maiuscoletto 12px, #1A1A1A (15,4:1);
  - righe `<th scope="row">` + `<td>` (1.3.1); righe alterne #F5F5F5, bordo #D0D5DD; su schermi
    larghi le tre tabelle affiancate, su stretti in colonna;
  - Catasto: Validazione catastale, Codice CAI, Data di accatastamento (riga a sé), Soggetto
    rilevatore;
  - Partenza e Arrivo testo semplice;
  - Soggetto rilevatore: tutto il nome dell'ente è un link alla sua pagina contatti;
  - «Maggiori info» accanto a difficoltà e validazione, con testo nascosto «sui gradi di difficoltà»
    / «sui livelli di validazione» (2.4.4, 2.5.3). Serve una pagina che spieghi i livelli 0–6 e la
    percorribilità;
  - link #B83F14, passaggio #8F2F0D; niente ↗ sui link interni; ↗ e testo nascosto «(apre in una
    nuova scheda)» solo per link esterni o in nuova scheda (3.2.5). La destinazione sta nel testo del
    link, non nel `title`.
- **Mappa agganciata** nella colonna laterale fino alle opinioni (in colonna singola l'aggancio
  decade): sentiero linea piena nel colore dell'entità con partenza e arrivo, sentieri vicini
  tratteggiati #5B7A99 con il codice accanto, punti di interesse correlati come segnaposti #B7127F
  numerati come nella legenda testuale sotto la mappa (1.4.1). `role="img"` con `aria-label` che
  descrive sentiero, vicini e punti, o legenda equivalente (1.1.1). Pulsanti di zoom (+/−) ed «Estendi
  la mappa» con nome (4.1.2), bordo #767676 (3,74:1, 1.4.11). Sotto, «Vedi sulla mappa interattiva»
  principale con icona a destra decorativa.
- **Correlati** (punti di interesse, itinerari e cammini, sentieri): card unica in griglia a tre
  colonne.
- **Opinioni e recensioni**: media e numero, «Vai alle opinioni» e «Scrivi la tua»; pulsante
  secondario «Scrivi una recensione» che apre il modulo (vedi «Moduli»); premuto #CA4D1C o #16497B
  testo bianco.

### Testata compatta

- Quando la foto esce dalla vista resta in alto, sotto la riga compatta dell'intestazione, la fascia
  scura con tipo, nome, bandierina e stato; sparisce a fine scheda.
- Fondo `rgba(26, 26, 26, .60)` con `backdrop-filter: blur(10px)` sul contenuto sotto; senza supporto
  della sfocatura #1A1A1A pieno. Nome bianco ≥ 4,54:1 anche su fondo bianco (1.4.3). La riga del logo
  resta bianca piena.
- Fino a 1024px la fascia si aggancia sotto l'intestazione mobile sticky (altezza misurata da JS in
  una variabile CSS).
- Altezza fino a 500px: resta solo la fascia scura; fino a 360px nulla resta fisso e la mappa non si
  aggancia (1.4.10).
- `scroll-padding-top` aumentato quando la fascia è visibile (2.4.11).

### Differenze per tipo

- **Itinerario**: stessa struttura del sentiero (testata con voto, testata compatta, azioni, fascia
  della percorribilità, tre indicatori, corpo, mappa, recensioni). Al posto dei dati catastali la
  tabella «Dati dell'itinerario» (arrivo, lunghezza, tempo, dislivello totale, difficoltà,
  accatastamento, stato di validazione), con «Itinerario in progetto» alla riga Accatastamento. Le
  quote, che il Drupal non ha per gli itinerari, vengono dalla traccia. Correlati: punti di interesse
  e percorsi (sentieri e itinerari).
- **Cammino**: la pagina è quella di una sua traccia, con la stessa struttura del sentiero. Accanto
  all'etichetta, il nome del cammino di cui la traccia fa parte. Blocco «Il cammino, in sintesi» sotto
  «in breve»: tematismo (tipologie della traccia), gestore (ente collegato con tipo e contatti),
  iscrizione al Registro dei cammini; referenti e punti di sosta solo quando ci saranno i dati.
  Posizione da decidere (traccia o pagina che raccoglie le tracce del cammino). Tabella «Dati del
  cammino» (lunghezza, tempo, dislivello positivo, negativo e totale, difficoltà, stato di
  validazione); correlati: punti di interesse e sentieri.
- **Itinerario a tappe**: due indicatori derivati (percorribilità e validazione d'insieme per fasce);
  quadro d'insieme (km, tappe, giorni, dislivello); tabella delle tappe con `<th>`: numero, nome, km,
  dislivello, stato scritto, codici dei sentieri, etichetta «Fuori catasto · N km su strada» #4D4D4D su
  #F5F5F5 (7,75:1) scritta in parole (1.4.1); ogni riga porta alla scheda del sentiero; mappa con
  tappe distinguibili e tappa sospesa tratteggiata in rosso (non solo colorata); un solo GPX
  d'insieme.
- **Punto di interesse**: tipologia in etichetta bianca su #B7127F (6,16:1), una tinta per tutte le
  tipologie; mappa con il punto nero dentro la rete e percorsi nei colori dell'entità, legenda che li
  nomina; accanto, elenco dei percorsi vicini ordinato per distanza crescente (codice, nome, tipo,
  distanza, stato scritto) e criterio scritto («distanza dal tratto più vicino, raggio 5 km, massimo
  10 percorsi», da confermare). Distanza calcolata sulla geometria delle tracce (PostGIS), non sui
  punti di partenza e arrivo. Niente «Segnala un problema».

## Moduli: recensione e segnalazione

Stesso componente per i due moduli, con i campi dei Webform del sito attuale (`valutazione_sentiero`,
`segnalazione`).

- Si apre in pagina sotto il pulsante (animazione 0,25 s, spenta con `prefers-reduced-motion`); il
  focus va al primo campo modificabile; «Annulla» o Esc chiudono e il focus torna al pulsante (2.4.3).
- Campo nascosto con l'identificativo del contenuto: il sentiero non si chiede.
- Etichette visibili, ognuna su una riga con «(facoltativo)» accanto; campi della stessa riga allineati
  in alto con stessa larghezza e altezza; suggerimento dell'email sotto il campo, collegato con
  `aria-describedby` (3.3.2).
- `autocomplete`: `address-level2` (provenienza), `nickname`, `email` (1.3.5).
- **Voti** (solo recensione): quattro `<fieldset>` con `<legend>` (Facile da seguire; Impegnativo, ma
  gratificante; Pericoli da considerare; Valutazione generale), radio con etichetta «N stelle»;
  stelle non scelte bianche con contorno #767676 (4,5:1), scelte #8A5A00 (1.3.1, 1.4.11).
- **File** (solo segnalazione): `multiple`, formati accettati scritti, bordo pieno come gli altri
  campi.
- **Privacy**: casella obbligatoria con «(obbligatorio)» per lo screen reader; riga «* campo
  obbligatorio».
- **Errori**: `novalidate` e controllo del sito; senza privacy compare accanto alla casella
  «Errore: devi accettare la nostra politica sulla privacy prima di inviare …» in #B70827 (6,82:1),
  con `aria-describedby`, `aria-invalid` e focus sulla casella (3.3.1, 3.3.3).
- **Conferma** dopo l'invio in `role="status"` («Grazie, la tua valutazione è stata inviata.» /
  «Grazie, la tua segnalazione è stata inviata.»), #1A1A1A su #E8F3EC, senza spostare il focus
  (4.1.3).
- Lato server: protezione antispam; gli invii non sono pubblici; delle recensioni si mostrano solo
  voto e testo; email e nomi mai esposti; testo della privacy e conservazione da definire con
  Forestas.

## Da sapere e News

- Fila di schede come **link di navigazione**, non componente a schede: centrata, attiva in grassetto
  con filetto sotto del colore della sezione (Da sapere #0F6E6A, News #C2410C) e `aria-current="page"`
  (1.4.1, 2.4.8). Testo #4D4D4D.
- Ogni scheda ha un indirizzo proprio (`/da-sapere/faq`) e serve solo la sua sezione; percorso di
  navigazione a tre livelli (Home › Da sapere › FAQ).
- Su schermi stretti la fila scorre in orizzontale da sola (`overflow-x: auto`), senza trascinare la
  pagina, già posizionata sulla scheda attiva.
- Testate alla grandezza del sito (variante layout builder di `.sase-hero`).
- Card unica (vedi sopra); i contenuti restano quelli attuali.

## Elenco degli avvisi

- Pagina `/avvisi`: titolo, una riga di spiegazione, elenco degli avvisi attivi dal catasto.
- Ordinamento con `<select>` ed etichetta visibile «Ordina per data» («Più recenti prima», «Meno
  recenti prima») (1.3.1, 3.3.2); niente filtri.
- Ogni avviso: componente della fascia della home, codice e nome del sentiero, testo, «Pubblicato il
  …» in `<time>` quando la data c'è; nessuna scadenza (non fra i dati decisi il 16/10); tutto
  l'avviso porta alla scheda del sentiero (2.4.4).

## Controllo di accessibilità del wireframe

Al 19/10/2026 il wireframe è stato controllato con axe-core 4.9.1 (regole WCAG 2.0, 2.1 e 2.2, livelli
A e AA) e a mano. Nessuna violazione automatica dopo le correzioni. Restano da verificare a mano, e
vanno rifatti sul sito vero: i contrasti dei testi su foto e sfumature (testo bianco sulla fascia al
60%: 4,54:1 sul caso peggiore; stelle #FFD873 3,31:1), la lettura con uno screen reader vero
(VoiceOver, NVDA) e la navigazione con la sola tastiera. Le annotazioni numerate del prototipo sono
nascoste agli screen reader: non sono contenuto del sito.
