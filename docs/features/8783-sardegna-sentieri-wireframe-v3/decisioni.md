> Ticket: oc:8783

# Decisioni sul wireframe v3 (stato al 19/10/2026)

Ogni voce riporta lo stato attuale e la fonte. Sigle delle fonti:

- **call 17/09**, **call 16/10**: riunioni di revisione con Forestas (minuto indicativo);
- **commento sul wireframe 12/10** (o 13/10): commenti di Forestas sulle tavole;
- **osservazioni di Forestas**: documento «Osservazioni finali al prototipo, fase 2»;
- **resoconto 12/08 § N**: resoconto della riunione del 12/08/2026;
- **infografica RES 2026**: infografica Forestas «Riforma RES 2026»
  (https://www.sardegnasentieri.it/node/5701);
- **Allegato H**: DGR 40/40, Allegato H, linee guida RES;
- **sito attuale**: comportamento di sardegnasentieri.it verificato su pagine, template o config del
  tema Drupal.

## Scale ufficiali

Fonte: infografica RES 2026, Allegato H, osservazioni di Forestas, call 16/10.

Due classificazioni distinte per sentieri e cammini, mai mescolate in un solo valore.

**Validazione catastale, 0–6** (quanto è completo e affidabile il dato):

| Livello | Nome | Fascia |
|---|---|---|
| 0 | Non catalogato | base |
| 1 | In accreditamento | base |
| 2 | In validazione o revisione | intermedio |
| 3 | Pre-accatastamento | intermedio |
| 4 | Percorribile | intermedio |
| 5 | Validato tappa per tappa | avanzato |
| 6 | Tracciato completo e gestito | avanzato |

**Percorribilità, 0–5** (com'è il sentiero oggi sul terreno):

| Livello | Nome |
|---|---|
| 0 | Sconosciuta o non verificata |
| 1 | Non percorribile |
| 2–3 | In revisione o semplicemente percorribile |
| 4 | Percorribile |
| 5 | Controllato con roadbook e punti tappa |

**Accessibilità, provvisoria**: i tre gradi dell'Allegato H — AT Accessibile Turistico, AE
Accessibile Escursionistico, AEE Accessibile Escursionistico per Esperti — con «plus» da una a tre
stelle (dotazioni in più) e la tipologia di disabilità. Forestas manderà una semplificazione delle
classi: fino ad allora è provvisoria.

**Semaforo**: riassume la percorribilità. Verde livelli 4–5 «Percorribili», giallo 2–3 «In revisione
o parzialmente percorribili», rosso 1 «Non percorribili». Nessun livello 0 nei conteggi. Etichetta
sempre scritta accanto al colore. Fonte: commento sul wireframe 12/10, osservazioni di Forestas
(Forestas vuole il semaforo rosso/giallo/verde).

**Import**: il valore unico di stato del Drupal si copia su entrambe le scale finché Forestas non
fornisce la percorribilità separata. Fonte: osservazioni di Forestas.

## Elementi comuni a tutte le pagine

- **Intestazione**: quella del sito attuale già realizzata in WordPress (oc:8757), con le differenze
  qui sotto. Fonte: sito attuale, call 16/10.
- **Logo** definitivo senza «Beta», dai file vettoriali ricevuti da Forestas il 16/10. Fonte: call
  16/10.
- **«OPEN DATA»** ultima voce dell'intestazione di servizio: porta a una pagina con i collegamenti ai
  dati aperti (API e scarichi). Icona provvisoria, da sostituire con quella ufficiale dei dati aperti.
  Fonte: call 16/10.
- **Loghi dei finanziatori solo nel piedipagina**: tolti dall'intestazione in tutte le pagine. Sette
  loghi nel piedipagina: i quattro del PR FESR 2021-2027 (Coesione, UE, Repubblica, Regione),
  Forestas Sentieristica (nuovo), CAI, «Sardegna Sentieri per tutti». Si attende la conferma della
  referente UE. Fonte: call 16/10, min. 15.
- **Intestazione sticky**: su desktop, scorrendo, si riduce a una riga con logo e menu e resta in
  alto in tutte le pagine; su telefono e tablet (fino a 1024px) resta fissa l'intestazione mobile con
  il menu ad hamburger, come nel sito attuale. Con poca altezza dello schermo non resta fisso nulla.
  Fonte: call 16/10, min. 15; sito attuale.
- **Menu principale**: SENTIERI, ITINERARI, CAMMINI, PUNTI DI INTERESSE, DA SAPERE, MAPPA
  INTERATTIVA, NEWS. «Da vedere» si chiama «Punti di interesse» ovunque. Fonte: commento sul
  wireframe 12/10.
- **Colori delle entità** (trasmessi da Forestas): sentiero #DE1B0D, itinerario #58810B, cammino
  #1A416F, punto di interesse #B7127F; sezioni: Da sapere #0F6E6A, News #C2410C, eventi #16497B,
  mappa interattiva #F59F1A. Decisione presa: non si cambiano. Fonte: call 17/09, 01:20; versione del
  wireframe con i commenti.
- **Due ricerche distinte**: «Cerca nel sito» nell'intestazione (tutti i contenuti) e «Cerca un
  percorso» (sentieri, itinerari, cammini, aree e comuni) che porta alla ricerca unica. Proposta
  Webmapp, da confermare con Forestas. Fonte: commento sul wireframe 13/10.
- **Pulsanti**: un solo sistema, principale (arancio, l'azione per cui esiste il blocco) e secondario
  (chiaro, tutto ciò che porta altrove o è accessorio). Fonte: sito attuale.
- **Card unica con varianti** per tutte le anteprime (catasto, punti di interesse, editoriali,
  correlati): base, in evidenza a foto piena, compatta con foto a lato, evento, senza foto. Fonte:
  commento sul wireframe 12/10 («svecchiare»).
- **Pulsanti fissi**: strumenti di accessibilità in basso a sinistra, preferenze cookie accanto, «torna
  su» in basso a destra; link «Preferenze cookie» anche nella striscia del copyright. Lo strumento di
  accessibilità e il gestore dei cookie non sono ancora scelti. Il widget non sostituisce la
  conformità WCAG 2.1 AA del sito. Fonte: commento sul wireframe 12/10.

## Tavola 1 — Home

Ordine dall'alto: avvisi, slider, ricerca, stato della rete, aree territoriali, mappa interattiva,
catasto, catalogo, punti di interesse, ultimi contenuti, piedipagina.

- **Fascia degli avvisi** in cima, sopra lo slider; c'è solo se esiste almeno un avviso. Più avvisi
  scorrono ogni 5 secondi con pulsante pausa/riprendi e contatore «1 di 3»; ogni avviso porta alla
  scheda del sentiero; «Leggi tutti gli avvisi» apre la Tavola 10. Gli avvisi della tavola sono
  reali: la news del 30/04/2026 sull'interruzione del G-502, l'avvertenza in testa alla scheda del
  T 721, il T 521 «Non percorribile» nel catasto; gli ultimi due senza data perché il dato non c'è.
  Fonte: commento sul wireframe 12/10, call 16/10.
- **Slider** identico al sito attuale (contenuti, tempi, pausa, link di salto); contenuto centrato in
  altezza, in basso solo i numeri delle slide. Fonte: sito attuale, call 16/10.
- **Ricerca** subito dopo lo slider: etichetta «Cerca un percorso», segnaposto «Dove vuoi andare?».
  È un vero campo di ricerca legato all'etichetta, con pulsante «Cerca»; «area territoriale,
  difficoltà, attività» sotto il campo sono link. Fonte: commento sul wireframe 13/10.
- **Stato della rete**: totale dei sentieri a catasto e tre numeri a semaforo con etichetta, data
  dei dati. Finché la percorribilità non esiste, i numeri vengono dallo stato di validazione attuale
  (Geohub app 32, 19/10/2026): 625 sentieri; verde 160 («Percorribile» 152 + «Validato» 8), giallo
  439 («In revisione-validazione» 168 + «In pre-accatastamento o manutenzione» 271), rosso 19
  («Non percorribile»); restano fuori 6 «Non verificato» e uno senza stato. Con la percorribilità il
  semaforo userà la scala 0–5. Fonte: commento sul wireframe 12/10, osservazioni di Forestas.
- **Aree territoriali**: mappa SVG con le 39 aree, colorate in cinque fasce per numero di percorsi
  (sentieri del catasto più itinerari), con legenda scritta; il clic su una zona seleziona l'area e
  aggiorna la card accanto (foto e descrizione del layer dell'area, numeri, pulsante «Vedi i percorsi
  dell'area»); sotto, «Vedi le aree come elenco» come alternativa testuale. Le zone sono pulsanti
  con lo stato «premuto»; l'area si sceglie con clic, Invio o Spazio, non al passaggio del mouse né
  al solo focus. Fonte: commento sul wireframe 12/10.
- **Mappa interattiva**: la mappa è una pagina del sito (`/mappa-interattiva`), non un widget della
  home; in home un'anteprima a tutta larghezza con un riquadro e il pulsante. Fonte: call 17/09.
- **Catasto per attività**: carosello delle nove tipologie con numero e km. Prime cinque, in
  quest'ordine: Sentieri lunghi (ex Trekking), Camminate giornaliere (ex Hiking), Sentieri per tutti,
  Mountain Bike, Turismo equestre. Le rinomine valgono anche in filtri e schede. «Sentieri per tutti»
  mostra il marchio RES dell'Allegato H e porterà ai sentieri con grado di accessibilità sopra una
  soglia, non più a quelli marcati accessibili. Icone delle attività: quelle del tema Drupal. Fonte:
  commenti sul wireframe 12/10 e 13/10, call 16/10.
- **Catalogo** di itinerari, destinazioni e cammini: stesso schema, nove categorie del sito. Fonte:
  sito attuale.
- **Punti di interesse**: una card a foto piena più quattro, senza etichetta «In evidenza» (difficile
  da gestire nel backend). Fonte: commento sul wireframe 12/10.
- **Ultimi contenuti in evidenza** al posto di «Le ultime notizie»: quattro card miste (news, Da
  sapere, eventi) e tre link «Tutte le news», «Tutti i Da sapere», «Tutti gli eventi». Fonte: commento
  sul wireframe 12/10.
- **Sfondi alternati** bianco e celeste a tutta larghezza fra le sezioni.

## Tavola 2 — Ricerca unica

Fonte generale: resoconto 12/08 § 4.1 e § 4.3, call 16/10.

- Una sola pagina per indice sentieri, area, comune e mappa, con filtri a sinistra.
- **Filtri**: Percorribilità (prima, scala 0–5 più «Percorribile ma temporaneamente interrotto»
  chiesta dal resoconto § 4.1; sarà preselezionata su «4 · Percorribile», dichiarata e
  disattivabile), Validazione catastale per fasce (avanzato 5–6, intermedio 2–4, base 0–1), Attività
  (le tipologie del sito con le rinomine della home), Difficoltà (T, E, EE, EEA), Dove (area
  territoriale, area geografica, comune, provincia, servizio territoriale, Carta). Ogni voce con
  numero di livello e nome scritti.
- **Conteggi della tavola** veri, Geohub app 32 al 19/10/2026: validazione 8 / 591 / 6 (stato attuale
  riportato alle fasce: «Validato» → 5, «Percorribile» → 4, «In pre-accatastamento» → 3, «In
  revisione-validazione» → 2, «Non verificato» → 0); attività come in home; difficoltà 154 / 411 / 49 /
  1; aree territoriali 39 (layer dell'app), aree geografiche 34 (zone del Drupal), comuni 134,
  province 5. Percorribilità, servizi territoriali e Carte senza numero: il dato non c'è. La ricerca
  d'esempio («Camminate giornaliere», E, Gennargentu Ogliastrino) dà 28 sentieri e 179,5 km, con le
  righe vere (T 721, T 702, T 710, T 711, T 712).
- **Regola dei filtri scritta in pagina**: OR dentro lo stesso filtro, AND fra filtri diversi.
- **Ogni ricerca ha un indirizzo** canonico (`/cerca?attivita=…&percorribilita=4&…`).
- **Elenco | Mappa**: due viste della stessa ricerca nella stessa pagina; la mappa è inquadrata sul
  riquadro che contiene tutti i risultati, con i codici scritti accanto ai tracciati; cambiando vista
  restano filtri, posizione e selezione.
- **Selezione multipla**: casella su ogni riga, «Seleziona tutti i N sentieri della ricerca» (tutta la
  ricerca, non solo la pagina), barra fissa in fondo con riepilogo e due gruppi di azioni: Tracce
  (formato GPX/KML/GeoJSON e «Scarica N sentieri in GPX») e Mappa e schede («Stampa la mappa di
  insieme», «Scarica le schede PDF»).
- **Soglia**: con molti sentieri i file si preparano e si notificano; la soglia è tecnica e la fissa
  Webmapp. Fonte: call 16/10.
- **Guida all'uso del download** sotto «Seleziona tutti»: pagina redazionale con i consigli (per
  esempio non più di due sentieri, vicini, per un PDF della mappa in buona scala). Richiesta dal
  capitolato. Fonte: commento sul wireframe 16/10, call 16/10.
- **«Carte»**: nuova classe di poligoni perimetrati sui progetti di sentieristica dei comuni; nome
  provvisorio. Fonte: resoconto 12/08 § 3.3 e § 10.3.

Punti del resoconto 12/08 non ancora nel wireframe:

- la pagina vale per tutti i tipi di contenuto (sentieri, punti di interesse, rifugi, toponimi): la
  tavola mostra solo sentieri e non ha un filtro per tipo (§ 4.1);
- servizio cartografico interrogabile dai GIS per l'uso professionale (§ 4.3);
- distinzione fra aree territoriali (settori catastali) e aree geografiche (§ 3.3): la tavola usa le
  39 aree dei layer dell'app e le 34 zone del Drupal.

## Tavola 3 — Scheda sentiero

Fonte generale: call 16/10 (impianto A scelto, opzione B tolta).

- **Avviso del sentiero** sotto l'intestazione e prima della foto, con lo stile della home. Fonte:
  call 16/10, min. 15.
- **Esempio reale**: il T 721 «S'Arena – Arcu Gennargentu» com'è oggi sul sito (misure, catasto,
  testi, foto, attività e avvertenze, correlati). Sono esempi dichiarati il voto e le recensioni (il
  T 721 non ne ha) e la fascia della percorribilità (dato nuovo).
- **Riga del tipo e dell'area** sopra il titolo («Sentiero · Area geografica Gennargentu»).
  Fonte: call 17/09, 00:55.
- **Bandierina** rosso-bianco-rosso a bande verticali con il codice, dopo il nome del sentiero. Fonte:
  commento sul wireframe 12/10, call 16/10. Aperte: posizione e codice breve o completo.
- **Fascia scura sulla foto al 60%** (il minimo che tiene il testo bianco a 4,5:1), con una sfumatura
  verso la foto sopra il testo. Fonte: call 16/10 (Forestas chiede la massima trasparenza compatibile
  con il contrasto).
- **Stelle e numero di recensioni sulla foto**, come nel sito attuale. Niente contatore di
  visualizzazioni: il dato non esiste. Fonte: call 16/10.
- **Riga di azioni** subito sotto la testata: Scarica GPX (principale), Stampa, Condividi, Segnala;
  tutti pulsanti veri.
  Stampa, condivisione e segnalazione sono migliorie accettate. Fonte: call 17/09, 01:10; call 16/10.
  - **Condividi**: senza servizi esterni (niente AddToAny né script o cookie di terzi): su telefono il
    menu di condivisione del sistema, altrove un pannello con WhatsApp, Facebook, X, Telegram, Email
    e «Copia link». Proposta Webmapp approvata.
  - **Segnala**: apre sotto la riga il modulo «Segnalazione» con i campi del Webform `segnalazione`
    del sito attuale; il sentiero non si chiede, la segnalazione resta collegata alla scheda.
  - **Stampa**: da decidere se produce una versione di stampa della pagina o una scheda PDF. Fonte:
    call 17/09.
- **Fascia della percorribilità**: stato (percorribile, parzialmente percorribile, non percorribile),
  data, chi l'ha inserito, nota descrittiva. Modello del catasto CAI, da confermare. Fonte: call
  16/10.
- **Tre indicatori a tacche**: validazione catastale 0–6, percorribilità 0–5, accessibilità AT · AE ·
  AEE (al posto dell'accatastamento); la «i» apre la legenda con tutti i livelli e il collegamento
  all'articolo. Fonte: call 16/10, infografica RES 2026, Allegato H.
- **Titoli di sezione su fascia colorata** con barra del colore dell'entità. Fonte: commenti sul
  wireframe 12/10 e 13/10.
- **Attività e avvertenze**: tabella con difficoltà per attività (il catasto ce l'ha già, per esempio
  27 sentieri «Turistico» a piedi e «MC» in mountain bike; il T 721 ne ha una sola), poi le attività
  del sentiero e le avvertenze come nel sito attuale. Fonte: call 17/09, 01:05.
- **Galleria** come il sito attuale (griglia di foto quadrate, foto grande in finestra).
- **Dati catastali e altimetrici** in fondo, in tre tabelle: Percorso (Partenza, Arrivo), Misure
  (Lunghezza, Tempo di percorrenza, Dislivello totale, Quota minima, Quota massima, Grado di
  difficoltà), Catasto (Validazione catastale, Codice CAI, Data di accatastamento, Soggetto
  rilevatore). La data di accatastamento non esiste in nessuna banca dati: la deve fornire Forestas
  (nella tavola un trattino). Partenza e arrivo sono testo semplice. Il soggetto rilevatore è un ente e tutto il nome
  porta alla pagina contatti dell'ente, come nel sito attuale. Accanto a difficoltà e validazione
  resta «Maggiori info». Fonte: commenti sul wireframe 12/10, call 16/10.
- **Mappa agganciata allo scorrimento** con sentiero, sentieri vicini tratteggiati con il codice,
  punti di interesse correlati numerati come nella legenda; pulsanti di zoom ed «Estendi la mappa».
  Fonte: commento sul wireframe 13/10, call 17/09, commento sul wireframe 16/10.
- **Card dei correlati** (punti di interesse, itinerari e cammini) uguali a quelle della home.
- **Opinioni e recensioni**: pulsante «Scrivi una recensione» e link «Scrivi la tua» che aprono il
  modulo «Valuta la tua esperienza» con i campi del Webform `valutazione_sentiero` del sito attuale.
  Fonte: commento sul wireframe 12/10.
- **Testata compatta**: scorrendo resta in alto l'intestazione ridotta con la fascia scura (tipo, nome,
  bandierina, percorribilità), così il numero del sentiero è sempre visibile; stessa trasparenza della
  fascia sulla foto, con il contenuto sotto sfocato. Su telefono e tablet la fascia si aggancia sotto
  l'intestazione mobile. Fonte: call 16/10, min. 15.

## Tavola 4 — Scheda itinerario

Fonte generale: call 17/09, 01:15–01:20; call 16/10; richiesta del 19/10 di avere per itinerario e
cammino la stessa struttura del sentiero.

- **Stessa struttura della scheda sentiero**: voto e recensioni sulla foto, testata compatta, azioni
  (Scarica GPX, Stampa, Condividi, Segnala), fascia della percorribilità, tre indicatori a tacche, in
  breve, attività e avvertenze, galleria, tabella dei dati, correlati, mappa, recensioni.
- **Tabella «Dati dell'itinerario»** al posto dei dati catastali, con i campi che l'itinerario ha
  nel Drupal: arrivo, lunghezza, tempo, dislivello totale, difficoltà, accatastamento, stato di
  validazione. Alla riga Accatastamento «Itinerario in progetto», al posto di «accatastato»
  (call 16/10).
- **Esempio reale**: «Monte Borborisaghe – Teti» (nessun itinerario semplice in Ogliastra: sono tutti
  tappe). Quote da Geohub, perché l'itinerario nel Drupal non ha questi campi. Esempi dichiarati:
  voto e recensioni, stato e data della percorribilità.
- Da chiarire: nel Drupal l'itinerario ha «Stato di validazione: Percorribile», mentre la sua
  avvertenza dice che non è nella rete escursionistica.

## Tavola 5 — Scheda cammino

Fonte generale: call 17/09, 01:10–01:30; richiesta del 19/10.

- **La scheda di un cammino è la scheda di una sua traccia**, con la stessa struttura della scheda
  sentiero. Nel Drupal il cammino non è un contenuto a sé: è un itinerario con tipologia «Cammini di
  Sardegna», una scheda per tappa (il Cammino Minerario di Santa Barbara ne ha 30 più 5 varianti);
  alcuni cammini sono una scheda unica con la traccia intera (Cammino dei Beati, Cammino Francescano).
- **Esempio reale**: la tappa 1 del Cammino Minerario di Santa Barbara, da Iglesias a Nebida (21 km,
  901 m di dislivello, 6 ore, «In revisione-validazione», livello 2 su 6). Esempi dichiarati: voto e
  recensioni, stato e data della percorribilità.
- **Etichetta e colore del cammino** e, accanto, il nome del cammino di cui la traccia fa parte.
- **Fascia della percorribilità e tre indicatori** come nel sentiero; «Inserito da» è il gestore.
- **«Il cammino, in sintesi»**, l'unico blocco in più (chiesto il 17/09 per tematismo, lato
  costitutivo, gestore, referenti, punti di sosta), solo con i dati che esistono: tematismo (le
  tipologie della traccia), gestore (l'ente collegato, «Ente partner», con i contatti della sua
  scheda; che sia il gestore lo dice il testo delle informazioni utili), iscrizione al Registro dei
  cammini (DGR 48/9 del 2012, dalle informazioni utili). Referenti e punti di sosta non ci sono.
  Da decidere se il blocco va nella scheda della singola traccia o nella pagina che mostra insieme
  tutte le tracce del cammino, come per gli itinerari a tappe.
- **Tabella «Dati del cammino»**: lunghezza, tempo, dislivello positivo, negativo e totale,
  difficoltà, stato di validazione.

## Tavola 6 — Itinerario a tappe

Fonte generale: call 17/09, 01:20–01:30; commento sul wireframe 12/10; call 16/10.

- Tipo di contenuto a sé (es. Sentiero Italia CAI), entità distinta dal cammino.
- **Una tappa** è uno o più sentieri del catasto, più eventuali tratti fuori catasto (lunghezza e
  tipo, es. «su strada»). In tabella i codici dei sentieri e l'etichetta «Fuori catasto · N km su
  strada».
- **Due indicatori derivati**: percorribilità e validazione d'insieme (per fasce), calcolata solo sui
  sentieri a catasto.
- Quadro d'insieme (km, tappe, giorni, dislivello) e griglia delle tappe con link alle schede dei
  sentieri.
- GPX dell'intero itinerario; quello della singola tappa dalla scheda del sentiero. Proposta da
  verificare in sviluppo.
- Mappa d'insieme con tappe distinguibili; la tappa sospesa è tratteggiata in rosso.

## Tavola 7 — Punto di interesse

Fonte generale: call 17/09, 01:25.

- **Esempio reale**: «Monumento Naturale di Perda Liana (Gairo)», con le sue tipologie («Meraviglie
  della Geologia e del Paesaggio», «Area sosta attrezzata»), area geografica, descrizione, come
  arrivare, servizi e foto. La quota viene dalla descrizione: il punto non ha un campo quota.
- La tipologia sale in testata, su etichetta magenta: un solo colore per tutte le tipologie.
- La mappa mostra il punto dentro la rete (sentieri e itinerari vicini), non un punto isolato.
- Accanto, l'elenco dei percorsi vicini ordinato per distanza: codice, nome, tipo, distanza, stato.
  Nella tavola i percorsi sono veri, con la distanza calcolata sulle tracce di Geohub: entro 5 km ce
  ne sono 15, l'elenco mostra i primi 10.
- Criterio di prossimità scritto in pagina: raggio 5 km, massimo 10 percorsi. Ipotesi da confermare.
- Niente «Segnala un problema»: nel sito attuale il punto di interesse non ce l'ha.

## Tavola 8 — Da sapere e News

Fonte generale: call 17/09, 01:30–01:40.

- Ogni pagina è divisa in schede; ogni scheda ha un indirizzo proprio (es. `/da-sapere/faq`) ed è la
  sola sezione servita.
- Nessun menu di secondo livello; percorso di navigazione a tre livelli (Home › Da sapere › FAQ).
- In «Da sapere» si apre sulle FAQ; in «News» la prima scheda è «Attività e comunicazioni».
- Etichette solo dove il sito le ha: «In evidenza» sulla news che il sito mette in evidenza, nessuna
  sul primo Da sapere (la vista mostra il più recente della categoria).
- Contenuti e blocchi restano quelli attuali; cambiano la fila di schede e le card (card unica).

## Tavola 9 — Cammini, aree territoriali, rifugi

- **Cammino**: oggi è un itinerario con tipologia «Cammini di Sardegna», colorato a mano in blu sulla
  mappa; le tappe sono schede separate o riferimenti ai sentieri che le compongono. Proposta:
  contenuto con tappe in ordine, ogni tappa rimanda ai sentieri; scaricabile per tappa (il file del
  cammino intero è da costruire); regola dello stato del cammino da decidere con Forestas (proposta:
  lo stato peggiore fra le tappe).
- **Area territoriale**: pagina di sintesi (racconto, carta d'inquadramento, numeri) con un solo
  invito, «Vedi i percorsi dell'area», che porta alla ricerca già filtrata; nessun elenco proprio.
- **Rifugio**: servizio dentro «Da sapere», scheda breve (contatti, servizi come «Fonti», gestito o
  no — quest'ultimo dato oggi non c'è), compare nelle schede dei sentieri che lo toccano; nessuna voce
  di menu di primo livello. Oggi nel menu non c'è una sezione dei rifugi: sono punti di interesse.
- **Un solo elenco di sentieri** in tutto il sito: ogni sottoinsieme rimanda alla ricerca filtrata
  (evita i duplicati).

## Tavola 10 — Elenco degli avvisi

Fonte generale: call 16/10.

- Pagina `/avvisi`: titolo, una riga di spiegazione, elenco degli avvisi attivi.
- Ordinabile per data (più recenti prima, o meno recenti), senza filtri.
- Ogni avviso: triangolo, parola «Avviso», codice e nome del sentiero, testo, data di pubblicazione
  quando c'è; porta alla scheda del sentiero. Nessuna scadenza: fra i dati dell'avviso decisi il
  16/10 (stato, data, chi lo inserisce, nota) non è prevista.
- Gli avvisi della tavola sono gli stessi tre, reali, della home.

## Moduli recensioni e segnalazione

Fonte: commento sul wireframe 12/10, call 16/10, sito attuale (Webform `valutazione_sentiero` e
`segnalazione`).

- Stessi campi dei Webform del sito attuale, stesso funzionamento nei due moduli.
- **Recensione** («Valuta la tua esperienza»): comune o zona di provenienza, nome o nickname, email,
  testo, quattro voti a stelle (Facile da seguire; Impegnativo, ma gratificante; Pericoli da
  considerare; Valutazione generale), consenso alla privacy obbligatorio, «Invia la valutazione».
- **Segnalazione**: provenienza, nome ed email facoltativi (l'email serve solo per un riscontro),
  testo, foto o file anche multipli con i formati scritti, privacy obbligatoria, «Invia la
  segnalazione».
- Il contenuto (sentiero, itinerario, cammino) non si chiede: è un campo nascosto della scheda.
- Nel sito attuale recensioni e «Segnala un problema» ci sono solo per i sentieri; nel wireframe
  anche per itinerari e cammini, che hanno la stessa struttura del sentiero (non per i punti di
  interesse).

Da definire con Forestas per il nuovo sito:

- chi può scrivere (oggi i visitatori non hanno account) e se le recensioni si moderano;
- chi riceve e legge le segnalazioni, se partono email, se e come diventano avvisi;
- gli invii non sono pubblici: si mostrano solo voti e testi delle recensioni approvate, mai email o
  dati personali;
- testo della privacy e tempi di conservazione;
- dove si salvano (WordPress o catasto Forestas, vedi «Stato dei dati»).

## Decisioni ancora aperte

Dalla sezione «Che cosa manca per chiudere» del wireframe, al 19/10:

- **Carte**: nome definitivo, chi fornisce i poligoni e quante sono (tav. 2). Resoconto 12/08 § 10.3.
- **Regola editoriale per le attività**: quando e chi redige le linee guida, quali attività restano,
  se un sentiero può averne più di una. Resoconto 12/08 § 10.5.
- **Servizi territoriali**: elenco, quanti sono e chi li fornisce (tav. 2). Oggi il servizio territoriale non è un
  dato a sé: da rivedere il filtro della ricerca, i referenti del cammino e il rifugio «filtrabile fra
  i servizi territoriali».
- **Bandierina**: posizione (prima o dopo il nome) e codice breve («T 721») o completo
  («Z-NU-T-721») (tav. 3).
- **Classi di accessibilità e soglia di «Sentieri per tutti»**: conferma dei gradi AT, AE, AEE
  (tav. 1 e 3).
- **Logo «Sardegna Sentieri per tutti» nell'intestazione** (tav. 1).
- **Un template o due** per cammino e itinerario a tappe: le entità restano distinte, si decide solo
  il template (tav. 5–6). Call 17/09.
- **«Il cammino, in sintesi»**: nella scheda della singola traccia o nella pagina che raccoglie le
  tracce del cammino (tav. 5).
- **Stato di un cammino a partire dalle sue tappe**: con quale regola (tav. 9).
- **Itinerari «Percorribile» nel Drupal ma fuori dalla rete escursionistica**: quale stato mostrare
  (tav. 4).
- **Criterio di prossimità**: raggio e numero di percorsi (tav. 7).
- **Rifugi «non gestiti»**: quali sono (tav. 8–9). Resoconto 12/08 § 5.1. Nel Drupal esiste solo la
  tipologia di punto di interesse «Rifugio montano incustodito», senza un campo di gestione.
- **Tratti fuori catasto nelle tappe**: come si definiscono e chi li fornisce; da dove arriva la loro
  percorribilità (tav. 6).
- **Modello della percorribilità** (stato, data, chi, nota): da confermare (tav. 3).
- **Stampa**: versione di stampa della pagina o scheda PDF (tav. 3).
- **Destinazione dei link delle aree** («Vedi i percorsi dell'area» ed elenco delle aree): oggi
  aprono l'app; per coerenza con la pagina «Mappa interattiva» potrebbero aprire la mappa del sito con
  l'area scelta.
- **Galleria**: il sito attuale mostra tutte le foto; da decidere se serve «Mostra tutte».
- **Ordine di «Ultimi contenuti in evidenza»**: per data o per un campo «in evidenza».

In attesa da Forestas: esempi di sentieri con inglese primario; classi semplificate di «Sentieri per
tutti»; conferma della referente UE sui loghi solo nel piedipagina; soglia per i reindirizzamenti
301; una foto con testo alternativo per ciascuna area; data di accatastamento dei sentieri.

## Stato dei dati

Ricognizione del 16/10 sul catasto Forestas, sull'app Geohub 32 e sul Drupal (API dell'import di
sardegnasentieri.it, REST pubblico `/node/{id}?_format=json` e `/taxonomy/term/{tid}?_format=json`,
config esportata del tema), più le verifiche del 17-19/10. Regola: un dato entra nel sito solo se
esiste o se è stato deciso di produrlo.

### Esiste nel catasto Forestas

- Codice completo nel `ref`, foto e galleria, descrizione, punti di interesse correlati.
- Area territoriale con foto e descrizione (layer).
- Tipologia, foto e descrizione dei punti di interesse.
- Data dell'ultimo rilievo (`properties.data_rilievo`, incompleta).
- Ente rilevatore collegato alla traccia (ruolo «rilevatore»), senza data né nota.
- GPX, KML, GeoJSON.
- Eventi sul codice catastale (cambi di stato con utente, motivo e data).

Limite trasversale: il GeoJSON delle tracce espone solo `properties` e tassonomie; stato di
validazione, enti, avvertenze e registro catastale oggi non arrivano in API.

### Esiste nel Drupal, basta importarlo

- Codice breve (`field_codice`) e codice CAI (`field_codice_cai`, diversi in 11 casi su 29
  verificati).
- Partenza e arrivo (parziali).
- Tipo di cammino e itinerario a tappe (`field_tipologia_itinerario`), tappe ordinate
  (`field_sentieri_collegati` per delta e `field_sentiero`).
- Nomi, foto e descrizioni delle 34 zone.
- Enti gestore, manutentore, rilevatore, complesso forestale (il servizio territoriale compare solo
  dentro il nome del complesso).
- Stato di validazione con valore 0–6 e colore (dal termine).
- Data del rilievo (`field_data_rilievo`, `field_data`); il template attuale non la mostra.
- Avvertenze (senza date) e informazioni utili (testo libero).
- Lunghezza, tempo, dislivello totale, quote (già nell'API dell'import, oggi non scritti nel catasto,
  vedi oc:8641).
- Difficoltà con MC/BC (`field_frubilita`), anche per attività.
- Icona delle attività (classe dello sprite del tema).
- Sentieri collegati, tag, referenti e punti dei cammini, PDF delle Carte, tipologie degli itinerari.
- Tutti gli editoriali: banner, slide, news, Da sapere, eventi, pagine.
- Recensioni (invii del Webform `valutazione_sentiero`): da importare collegandole al sentiero,
  senza esporre dati personali.

### Da calcolare

- Dislivello positivo e negativo (presente in pochi sentieri).
- Comuni e province per intersezione geometrica.
- Quota e comune dei punti di interesse.
- Conteggi della home (stato della rete, catasto, catalogo, aree) dal catasto Forestas, non da
  Geohub.
- Sentieri vicini e punti di interesse entro un raggio (PostGIS).
- Codice breve dal `full_code` del registro, indipendente dalla forma del `ref` (oggi misto: «T-114»,
  «Z-NU-T-114», «D 700»).
- Poligoni delle aree per la mappa SVG (nel Drupal la mappa è un'immagine con zone cliccabili).
- Stato derivato di cammini e itinerari a tappe (regola da decidere; validazione d'insieme sui soli
  sentieri a catasto).
- Tematismo di itinerari e cammini dai theme: su Geohub esistono già i theme delle tipologie del
  Drupal («Passeggiate Geo-Minerarie», «Vie della Transumanza», «Itinerari dello Spirito e altre
  Destinazioni», «Cammino Minerario di Santa Barbara»…), ma le tracce dell'app 32 hanno solo
  «Itinerari» e «Cammini di Sardegna». Nel wm-package i theme si importano (`ImportEcTrackJob`) ma in
  `EcTrack` la loro uscita nelle API è commentata: va riattivata.

### Deve fornirlo Forestas

- Percorribilità separata dalla validazione.
- Interruzioni temporanee come dato con date (oggi sono news).
- Poligoni delle Carte e loro nome.
- Elenco dei servizi territoriali.
- Cammini: tipologia, tappe ordinate, promotore, referenti.
- Tratti fuori catasto delle tappe.
- Rifugi gestiti o non gestiti.
- Colore delle attività.
- Stato di accatastamento e data di iscrizione (oggi nel registro su foglio Google).
- Mappatura MC/BC, codice CAI distinto o no, corrispondenza fra le 34 zone del Drupal e le 39 aree
  dei layer dell'app.
- Classi di accessibilità e soglia di «Sentieri per tutti».
- Linee guida delle attività.
- Foto delle aree senza immagine (4) e descrizione mancante (1).

### Deciso, da sviluppare

- **Avvisi** pubblicati dal catasto, con stato, data, chi li inserisce e nota, letti dal sito in home,
  nella scheda e nella pagina `/avvisi` (call 16/10). Oggi gli avvisi sono news con il tag
  «segnalazione».
- **Percorribilità con storico** sul modello osm2cai: stato, data, autore, nota, cronologia
  (call 16/10). Alimenta la fascia della scheda e il semaforo.
- **Accessibilità** come terzo indicatore, sulle classi che Forestas confermerà.
- **Open Data**: pagina con API e scarichi.
- **Scarico multiplo** con preparazione asincrona oltre la soglia; stampa della mappa d'insieme e
  schede PDF (la stampa della mappa richiede una cartografia adatta alla stampa).
- **Moduli recensioni e segnalazione** nel nuovo sito. Salvarli nel catasto Forestas e leggerli da
  WordPress via API è fattibile ma è sviluppo nuovo, frontend e backend: modello delle recensioni
  legato alla traccia, endpoint di scrittura per il sito con limiti antispam, media ed elenco senza
  dati personali in API, moderazione in Nova, consenso e conservazione, import delle recensioni del
  Drupal. In alternativa restano in WordPress. Da decidere.
- **Editoriali** (avvisi, slider, news, Da sapere, eventi, pagine, catalogo, recensioni): esistono solo
  nel Drupal; né il catasto Forestas né Geohub né l'app li hanno. Servono custom post type in
  WordPress e un import.

### Non esiste e non si mostra

- Numero di visualizzazioni delle schede: il sito attuale non lo conta.
- Tempo di percorrenza per attività: il Drupal ha un solo tempo per sentiero (la difficoltà per
  attività invece esiste). Nel wireframe la colonna mostra solo il tempo reale.
- Referenti dei cammini e punti di sosta collegati ai cammini.
- Il servizio territoriale come dato a sé.
