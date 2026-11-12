> Ticket: oc:8969

# Pagine di dettaglio di percorsi e punti di interesse sul sito WordPress

La parte generica (rewrite, risoluzione dello slug, lettura del JSON, template sostituibili) sta nel
plugin: `wp-package/docs/features/8969-pagine-di-dettaglio-di-percorsi-e-punti-di-interesse-sul-sito-wordpress/overview.md`.
Qui c'è la parte di Forestas: i template del child theme che seguono il wireframe e le modifiche alla
configurazione del sito.

## Cosa cambia

Oggi dalla ricerca unica e dalla mappa si trovano percorsi e punti di interesse, ma una card non apre
niente: il `link_template` della source `tracks` è vuoto (`wp-package/includes/search.php:115`) e non
esiste nessuna pagina di dettaglio.

Dopo questo lavoro ogni EcTrack e ogni EcPoi dello shard ha una pagina sul sito, generata al momento
della richiesta dai dati delle API dello shard, senza post salvati in WordPress:

| Tipo | `route-type` | Indirizzo | Tavola del wireframe oc:8783 | Colore |
|---|---|---|---|---|
| Sentiero | `route-type-trail` (614 sul dev) | `/sentiero/<slug>/` | 3 «Scheda sentiero» (la base) | #DE1B0D |
| Itinerario | `route-type-itinerary` (102) | `/itinerario/<slug>/` | 4 «Scheda itinerario» | #58810B |
| Cammino | `route-type-walk` (53) | `/cammino/<slug>/` | 5 «Scheda cammino» (la singola traccia) | #1A416F |
| Punto di interesse | EcPoi (976) | `/punti-di-interesse/<slug>/` | 7 «Scheda punto di interesse» | #B7127F |

La base di una traccia è il nome del suo `route-type` («Sentiero», «Itinerario», «Cammino»), come
negli indirizzi del sito Drupal (`/sentiero/villanova-monteleone-galoppatoio-romana-e-203`); una
traccia senza `route-type` va sotto `/track/` (sul dev oggi nessuna). I POI stanno sotto la pagina
«Punti di interesse», scelta nelle impostazioni del plugin. Lo slug è il nome della traccia o del POI
passato in `sanitize_title`; i doppioni prendono `-<id>`. Le pagine inglesi stanno sotto `/en/` e usano
i nomi inglesi della traccia (o del POI) e del suo `route-type`, e quelli italiani dove l'inglese manca:
`/en/sentiero/salita-sul-monte-corrasi-b-401/` senza traduzioni, con il nome inglese del termine e
della traccia lo slug segue l'inglese. Quando arriva una traduzione l'indirizzo cambia da solo, e
quello vecchio porta al nuovo con un 301. Sul dev 281 tracce su 778 hanno il nome inglese diverso
dall'italiano; i tre `route-type` oggi hanno solo l'italiano (l'inglese lo inserisce Rubens in Nova,
`notes.md`).

La pagina «Da vedere» cambia indirizzo: diventa `/punti-di-interesse/` (italiano e inglese), così il
dettaglio di un POI sta sotto la pagina del suo elenco.

## Perché

Il giro «cerco → seleziono → apro il dettaglio» è il pezzo che manca per mostrare il sito al cliente:
allo scrum dell'11/11 (13:04) Giuseppe ha fissato le priorità — «abbiamo finito la ricerca, facciamo il
dettaglio» — e la regola per i dati: «iniziati a montare quello che già il plugin ti torna di base»,
con gli spazi vuoti dove il dato manca, «così ti mantiene la grandezza». Il wireframe oc:8783 è
approvato (call del 02/11) ed è il riferimento di design; il tema Drupal resta il riferimento per ciò
che il wireframe non disegna.

Le pagine sono dinamiche, sul modello del vecchio sito di Sentiero Italia (`template-tappa-details.php`
di sicai), ma leggono le API dello shard e non il database: gran parte del dettaglio
(`properties.forestas`, `properties.taxonomies`) è costruita in uscita dal backend e nel DB non c'è
(`forestas/docs/knowledge/dettaglio-della-traccia-dati-forestas.md`).

## Requisiti

### Indirizzi e pagine del sito

- [ ] Impostazioni del plugin: tracce con la base dal tipo (`route-type`); POI con la base dalla
      pagina «Punti di interesse» (chiave `da-vedere`). Gli indirizzi sono quelli della tabella.
- [ ] La pagina con chiave `da-vedere` di `config/pagine.json` ha slug `punti-di-interesse` in italiano
      e in inglese; le voci di `config/menu.json` puntano al nuovo indirizzo; `/da-vedere/` e
      `/en/da-vedere/` rispondono con un 301 verso il nuovo. La chiave `da-vedere` non cambia (il file
      avverte che rinominarla scollega la pagina dalla sincronizzazione).
- [ ] Le card e le righe della ricerca unica portano al dettaglio (`link_template` della source
      `tracks`, e dei POI se il tema ne registra la source).
- [ ] La card che si apre al clic sulla mappa della ricerca porta al dettaglio.

### Dettaglio del percorso (Tavola 3, base comune ai tre tipi)

- [ ] Testata con foto (`feature_image`), fascia scura al 60%, riga del tipo e dell'area, nome,
      bandierina con il codice (`ref`), voto e numero di recensioni come segnaposto.
- [ ] Riga di azioni: **Scarica GPX** funzionante (`forestas.downloads`), **Condividi** con
      `forestas_condividi()`, **Stampa** e **Segnala** visibili ma non attivi, con l'indicazione che
      non sono ancora disponibili.
- [ ] Avviso del sentiero e fascia della percorribilità come segnaposto (i dati non esistono ancora).
- [ ] Tre indicatori a tacche: validazione (dal termine `validation-status`), percorribilità
      (segnaposto), accessibilità (da `forestas.inclusivity`), ciascuno con la «i» della legenda.
- [ ] «Il sentiero in breve», «Descrizione tappe (roadbook)» (`forestas.roadbook`), «Attività e
      avvertenze» (tassonomie di difficoltà per attività e `warnings`), informazioni utili
      (`forestas.useful_info`), galleria con lightbox (`image_gallery` con i crediti di
      `forestas.image_credits`), allegati e video (`forestas.attachments`, `forestas.videos`).
- [ ] Dati catastali e altimetrici in tre tabelle (Percorso, Misure, Catasto) dai campi che esistono
      (`properties` DEM, `forestas.registry_code`, `forestas.survey_date`, validazione); quelli che
      non esistono (data di accatastamento) con un trattino, come nel wireframe.
- [ ] Gli enti compaiono con nome e ruolo, senza link: la pagina dell'ente non esiste ancora
      (oc:8965). Dopo lo scrum del 12/11 Alessandro riorganizza la chiave per ruolo e la rinomina
      `organizations`: ruolo → elenco di enti con `id` e `name`, così il nome arriva già nel
      dettaglio. Il template legge gli enti attraverso una sola funzione.
- [ ] Mappa agganciata allo scorrimento nella colonna destra (`[wmwp_map tracks="<id>"]`), con i
      pulsanti di zoom ed «Estendi la mappa».
- [ ] Correlati in card a tre colonne: punti di interesse (`related_pois`) e sentieri
      (`forestas.nearby_tracks`, che sono i «sentieri correlati»: scrum 11/11 13:54). «Percorsi
      vicini» e «sentieri correlati» sono la stessa cosa, calcolata dallo shard per prossimità; la
      distanza la aggiunge Giuseppe al calcolo e si mostra nella card quando arriva (scrum 12/11).
- [ ] «Opinioni e recensioni» visibile come segnaposto, senza modulo.
- [ ] Testata compatta che resta in alto scorrendo, con tipo, nome e bandierina.
- [ ] Titoli di sezione su fascia chiara con la barra del colore del tipo
      (`implementazione-wordpress.md` di oc:8783, «Colori delle entità e delle sezioni»).

### Differenze per tipo

- [ ] **Itinerario**: senza il blocco validazione, percorribilità e accessibilità (call del 02/11,
      Alessio Saba: «tutto il blocco va tolto per l'itinerario perché l'itinerario non è
      classificato nel catastro»); tabella «Dati dell'itinerario» al posto dei dati catastali;
      correlati «Percorsi correlati»; colore verde.
- [ ] **Cammino**: struttura del sentiero; accanto all'etichetta il nome del cammino; blocco «Il
      cammino, in sintesi» sotto «in breve» con i soli dati che esistono (tematismo = tipologie della
      traccia, gestore = ente col ruolo, iscrizione al Registro); tabella «Dati del cammino»; colore
      blu. Il blocco l'ha chiesto Forestas (Alessio Saba, call del 17/09: «informazioni che chi guarda
      un cammino deve ricevere subito»).

### Dettaglio del punto di interesse (Tavola 7)

- [ ] Tipologia in testata su etichetta bianca su #B7127F; area geografica, descrizione, come arrivare,
      galleria, allegati.
- [ ] Mappa con il punto (`[wmwp_map geojson=…]`).
- [x] «Percorsi vicini» in card con la distanza dal POI, le prime 3 e «Mostra tutti»: li calcola lo shard
      (entro 1 km, oc:8959 del 12/11), il frontend non calcola niente.
- [ ] Niente «Segnala», niente recensioni, niente accessibilità (sito attuale; Giuseppe allo scrum
      del 02/11 sui dati di accessibilità dei punti).

### Generali

- [ ] La pagina mostra sempre i dati attuali del backend: una traccia o un POI aggiunto, modificato o
      cancellato (dal reset notturno di dev e collaudo o dalla redazione in produzione) si vede nel
      dettaglio entro un minuto (cache breve del JSON nel plugin), senza interventi, e un indirizzo non mostra mai un elemento diverso da quello
      richiesto.
- [ ] Testi dell'interfaccia in italiano e inglese con `forestas_testo()`, come il resto del child.
- [ ] Ogni segnaposto è riconoscibile come tale (testo «Dato non ancora disponibile» o lorem ipsum
      dichiarato) e occupa lo spazio del componente finito.
- [ ] [UX] Spazio riservato a foto, mappa e segnaposto (`aspect-ratio`, altezze fisse): la pagina non
      salta quando arrivano i dati.
- [ ] [UX] La testata compatta e la mappa agganciata non coprono il focus da tastiera
      (`scroll-padding-top` aggiornato), e sotto 1024px la mappa non si aggancia.
- [ ] [UX] Le sezioni senza dati non lasciano titoli vuoti: o segnaposto dichiarato, o la sezione
      non compare (regola per sezione nel piano).
- [ ] WCAG 2.1 AA come da `implementazione-wordpress.md` di oc:8783 (sezione «Schede»).

### Cosa manca nel backend

Da segnalare ad Alessandro e Giuseppe, ciascuna come richiesta a sé; finché non arriva, il frontend
mostra il segnaposto:

- [x] Enti nel dettaglio di tracce e POI come `organizations` (proposta di Alessandro, 12/11): ruoli
      `manager`, `maintainer`, `surveyor`, più `operator` e il complesso forestale, che le tracce del
      dev hanno (176 e 96 collegamenti); nei POI solo `operator` (35).
- [ ] Distanza nei sentieri correlati (`nearby_tracks`): la aggiunge Giuseppe (scrum 12/11).
- [ ] Nome inglese dei tre termini `route-type`: lo inserisce Rubens a mano in Nova su ogni ambiente
      (resta dopo il reset notturno, che rigenera anche file delle tracce e indice; in produzione
      serve `wm:resync-taxonomy-where --app=1 --outputs-only`: dettagli e promemoria in `notes.md`).
- [x] Percorsi vicini di un POI nel dettaglio del POI (`api/ec/poi/<id>`), con i dati della card come
      `nearby_tracks` delle tracce e la distanza (oggi nessun POI li ha, `related` è `false` in tutti
      i 976; la pagina di Alessandro lo dice: «I POI vicini non ci sono»).
- [ ] PDF per la Stampa, generato dallo shard.
- [ ] Salvataggio di segnalazioni e recensioni (`decisioni.md` di oc:8783: «Da decidere»).
- [x] `properties.forestas` dei POI ripulito come quello delle tracce (oggi ha ancora `allegati`,
      `come_arrivare`, `poi_correlati`, `zona_geografica`, `source_relations`).
- [ ] Avvisi e percorribilità del sentiero (aree 4 e 5 del tag 700).

## Rischi

- **Contratto dei POI non stabile**: il `forestas` dei POI cambierà chiavi quando verrà ripulito. Il
  template del POI legge le chiavi attraverso una sola funzione, così il cambio si fa in un punto.
- **Lorem ipsum davanti al cliente** [UX]: su UAT un segnaposto non dichiarato sembra un errore. I
  segnaposto sono riconoscibili come tali.
- **Download del GPX**: l'endpoint di download allo scrum del 12/11 era rotto (Giuseppe: «ora è
  rotto»), e Alessandro lo stava controllando. Il pulsante «Scarica GPX» dipende da quel fix.
- **Nome cambiato**: lo slug segue il nome, quindi rinominare una traccia cambia l'indirizzo. Accettato
  in questo ciclo.

Rischi della parte generica: overview del plugin.

## Out of scope

- La pagina dell'itinerario a tappe e quella del cammino intero: saranno pagine dei layer.
- La pagina dell'ente e il link dal nome dell'ente (oc:8965 e ticket della pagina).
- I 301 dai vecchi indirizzi Drupal dei singoli percorsi e POI: su un campione di 120 tracce circa
  4 indirizzi su 10 sono diversi da quelli nuovi. Se la nuova app avrà ancora il link «scheda
  completa», gli URL si correggono con una query sullo shard a partire dall'id (`notes.md`).
- I moduli di segnalazione e recensione, il PDF della Stampa, gli avvisi e la percorribilità (vedi «Cosa
  manca nel backend»).
- Le tassonomie scritte male che Giuseppe ha chiesto di non considerare: si usano quelle che il JSON
  dà ora.

## Moduli toccati

Repo `wp-forestas` (questo):

- `themes/forestas-child/wp-package/` — template del dettaglio di percorso e POI che sostituiscono
  quelli del plugin.
- `themes/forestas-child/inc/` — un file nuovo per il dettaglio (dati per sezione, differenze per tipo,
  basi degli indirizzi, segnaposto) e `inc/search.php` per il `link_template`.
- `themes/forestas-child/` CSS e JS del dettaglio (testata compatta, galleria, mappa agganciata).
- `config/pagine.json`, `config/menu.json` — slug della pagina «Punti di interesse».
- `inc/pagine-statiche.php` — 301 da `/da-vedere/`.
- `docs/howto/ricreare-wordpress-da-zero.md` — se cambia la configurazione da applicare.

Repo `wp-package`: vedi la sua overview.
