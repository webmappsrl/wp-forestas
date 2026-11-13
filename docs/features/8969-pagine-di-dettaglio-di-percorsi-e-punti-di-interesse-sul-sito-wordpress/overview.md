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
passato in `sanitize_title`; i doppioni prendono `-<nid di Drupal>` (`sardegnasentieri_id`, la chiave stabile che il
child passa al plugin con il filtro `wmwp_detail_source`), così l'indirizzo non cambia con il reset notturno degli id;
senza nid prendono `-<id>`. Le pagine inglesi stanno sotto `/en/` e usano
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

- [x] Impostazioni del plugin: tracce con la base dal tipo (`route-type`); POI con la base dalla
      pagina «Punti di interesse» (chiave `da-vedere`). Gli indirizzi sono quelli della tabella.
- [x] La pagina con chiave `da-vedere` di `config/pagine.json` ha slug `punti-di-interesse` in italiano
      e in inglese; le voci di `config/menu.json` puntano al nuovo indirizzo; `/da-vedere/` e
      `/en/da-vedere/` rispondono con un 301 verso il nuovo. La chiave `da-vedere` non cambia (il file
      avverte che rinominarla scollega la pagina dalla sincronizzazione).
- [x] Le card e le righe della ricerca unica portano al dettaglio (`link_template` della source
      `tracks`, e dei POI se il tema ne registra la source).
- [x] La card che si apre al clic sulla mappa della ricerca porta al dettaglio.

### Dettaglio del percorso (Tavola 3, base comune ai tre tipi)

- [x] Testata con foto (`feature_image`), fascia scura al 60%, riga del tipo e dell'area, nome,
      bandierina con il codice (`ref`), voto e numero di recensioni come segnaposto.
- [x] Riga di azioni: **Scarica GPX** funzionante (`forestas.downloads`), **Condividi** con
      `forestas_condividi()`, **Stampa** e **Segnala** visibili ma disattivati (`aria-disabled`), senza
      una nota che lo ripeta: si capisce dai pulsanti (Rubens, 12/11). Sull'itinerario niente «Segnala»: la Tavola 4
      lo ha, ma su Drupal le segnalazioni sono solo dei sentieri; si decide nel ticket di segnalazioni e recensioni,
      come le recensioni dell'itinerario (`notes.md`).
- [x] Avviso del sentiero e fascia della percorribilità come segnaposto (i dati non esistono ancora); non
      sull'itinerario, che non è nel catasto e non li ha nella Tavola 4.
- [x] Tre indicatori a tacche: validazione (dal termine `validation-status`), percorribilità
      (segnaposto), accessibilità (grado AT · AE · AEE da `inclusivity-difficulty` e le tre icone delle
      tipologie di disabilità da `inclusivity-disabilities`), ciascuno con la «i» della legenda e il link
      all'articolo di approfondimento (nodo Drupal 5701), come la Tavola 3 del wireframe (note 10 e 12).
- [x] Blocco «Sentiero per tutti» sotto gli indicatori, unito alla colonna Accessibilità a forma di L
      rovesciata, quando il sentiero ha la classe di inclusività (`forestas.inclusivity`): classe con le
      tacche e la frase, legenda delle sei classi, tabella con i due testi del redattore e i tre punteggi
      riproporzionati su sei tacche (Tavola 3, nota 11).
- [x] «Il sentiero in breve», «Descrizione tappe (roadbook)» (dai Blocchi Dettaglio, `config_detail`: una riga per
      tappa con il titolo «Tappa N» dei dati, oc:8959), «Attività e
      avvertenze» (tassonomie di difficoltà per attività e `warnings`), informazioni utili
      (`forestas.useful_info`), galleria con lightbox (`image_gallery` con i crediti di
      `forestas.image_credits`), allegati e video (`forestas.attachments`, `forestas.videos`).
- [x] Dati catastali e altimetrici in tre gruppi (Percorso, Misure, Catasto) dai campi che esistono
      (`properties` DEM, `forestas.registry_code`, `forestas.survey_date`, validazione); quelli che
      non esistono (data di accatastamento) con un trattino, come nel wireframe. Gruppi chiusi da aprire
      uno per uno; in Misure il grado di difficoltà con «Maggiori info» (il «da sapere» dei gradi, nodo
      Drupal 1102), accanto alla validazione «Maggiori info» verso le Note legali (nodo 87), come su Drupal.
- [x] Gli enti compaiono con nome e ruolo, senza link: la pagina dell'ente non esiste ancora
      (oc:8965). Dopo lo scrum del 12/11 Alessandro riorganizza la chiave per ruolo e la rinomina
      `organizations`: ruolo → elenco di enti con `id` e `name`, così il nome arriva già nel
      dettaglio. Il template legge gli enti attraverso una sola funzione.
- [x] Mappa agganciata allo scorrimento nella colonna destra (`wmwp_map()` del plugin), con i
      pulsanti di zoom ed «Estendi la mappa»: la traccia della scheda, i percorsi collegati tratteggiati e i
      primi tre punti di interesse correlati numerati, con la legenda sotto e «Vedi sulla mappa interattiva»
      (Tavola 3; Rubens, 13/11). Partenza (cerchio verde) e arrivo (bandiera a scacchi) della traccia, come la
      mappa di GeoHub di Drupal; in un anello solo la partenza. Sotto 768px la mappa sta subito dopo i pulsanti
      (Rubens, 13/11).
- [x] Correlati in card a tre colonne: punti di interesse (`related_pois`) e «Percorsi correlati»
      (`forestas.nearby_tracks`: scrum 11/11 13:54; titolo scelto da Rubens il 13/11 perché fra i correlati ci
      sono anche itinerari e cammini). «Percorsi vicini» e percorsi correlati sono la stessa cosa, calcolata
      dallo shard per prossimità. La distanza fra due correlati è stata scartata (Alessandro, 7e61a3f): la card
      mostra lunghezza e tempo del correlato, come il sito attuale. Card uniche con quelle dei post e della
      ricerca, filetto nel colore del tipo, segnaposto di Drupal senza foto.
- [x] «Opinioni e recensioni» visibile come segnaposto, senza modulo (non sull'itinerario: vedi `notes.md`,
      «Ordine delle sezioni del dettaglio»).
- [x] Testata compatta che resta in alto scorrendo, con tipo, nome e bandierina.
- [x] Titoli di sezione su fascia chiara con la barra del colore del tipo
      (`implementazione-wordpress.md` di oc:8783, «Colori delle entità e delle sezioni»).

### Differenze per tipo

- [x] **Itinerario**: senza il blocco validazione, percorribilità e accessibilità (call del 02/11,
      Alessio Saba: «tutto il blocco va tolto per l'itinerario perché l'itinerario non è
      classificato nel catastro»); «Dati dell'itinerario» con i gruppi Percorso e Misure al posto dei dati catastali (Tavola 4);
      correlati «Percorsi correlati»; colore verde.
- [x] **Cammino**: struttura del sentiero; accanto all'etichetta il nome del cammino (`layer_name`, oggi «Cammini
      di Sardegna» per tutti, oc:8959); blocco «Il
      cammino, in sintesi» sotto «in breve» con i soli dati che esistono (tematismo = tipologie della
      traccia, gestore = ente col ruolo, iscrizione al Registro); «Dati del cammino» con i gruppi Misure e
      Stato (Tavola 5); colore
      blu. Il blocco l'ha chiesto Forestas (Alessio Saba, call del 17/09: «informazioni che chi guarda
      un cammino deve ricevere subito»).

### Dettaglio del punto di interesse (Tavola 7)

- [x] Tipologia in testata su etichetta bianca su #B7127F; area geografica, descrizione, come arrivare,
      galleria, allegati.
- [x] Mappa con il punto nero e i percorsi vicini nel colore del loro tipo, con la legenda dei tipi e «Vedi
      sulla mappa interattiva» (Tavola 7; Rubens, 13/11).
- [x] «Percorsi vicini» in card con la distanza dal POI, le prime 3 e «Mostra tutti»: li calcola lo shard
      (entro 1 km, oc:8959 del 12/11), il frontend non calcola niente.
- [x] Niente «Segnala», niente recensioni, niente accessibilità (sito attuale; Giuseppe allo scrum
      del 02/11 sui dati di accessibilità dei punti).

### Generali

- [x] La pagina mostra sempre i dati attuali del backend: una traccia o un POI aggiunto, modificato o
      cancellato (dal reset notturno di dev e collaudo o dalla redazione in produzione) si vede nel
      dettaglio entro un minuto (cache breve del JSON nel plugin), senza interventi, e un indirizzo non mostra mai un elemento diverso da quello
      richiesto.
- [x] Testi dell'interfaccia in italiano e inglese con `forestas_testo()`, come il resto del child.
- [x] Ogni segnaposto è riconoscibile come tale (testo «Dato non ancora disponibile» o lorem ipsum
      dichiarato) e occupa lo spazio del componente finito.
- [x] [UX] Spazio riservato a foto, mappa e segnaposto (`aspect-ratio`, altezze fisse): la pagina non
      salta quando arrivano i dati.
- [x] [UX] La testata compatta e la mappa agganciata non coprono il focus da tastiera
      (`scroll-padding-top` aggiornato), e sotto 1024px la mappa non si aggancia (sotto 768px sale dopo i
      pulsanti, spostata nel DOM perché Tab e screen reader seguano l'ordine visibile).
- [x] [UX] Le sezioni senza dati non lasciano titoli vuoti: o segnaposto dichiarato, o la sezione
      non compare (regola per sezione nel piano).
- [x] WCAG 2.1 AA come da `implementazione-wordpress.md` di oc:8783 (sezione «Schede»).

### Pagina «Mappa interattiva» e lightbox (aggiunti il 13/11)

- [x] «Vedi sulla mappa interattiva» apre la pagina `mappa-interattiva` con il focus sull'elemento
      (`?track=<id>`, `?poi=<id>`), come `/mappa-interattiva?id=<nid>` di Drupal; l'iframe è la webapp dello
      shard dell'ambiente (`wmwp_webapp_url()` del plugin, `https://<app ID>.<shard>.webmapp.it/`; in produzione il
      campo «Webapp» finché il dominio non c'è), su `#/map` senza focus.
- [x] La mappa della pagina è più alta: «Espandi la mappa» sta nella riga del percorso invece che in una barra
      di 88px, e la mappa ha almeno 30rem (differenza voluta da Drupal, Rubens 13/11).
- [x] Frecce e chiusura della lightbox delle gallerie come su Drupal (cerchi con bordo bianco e icone di Font
      Awesome), su tutto il sito.

### Cosa manca nel backend

Da segnalare ad Alessandro e Giuseppe, ciascuna come richiesta a sé; finché non arriva, il frontend
mostra il segnaposto:

- [x] Enti nel dettaglio di tracce e POI come `organizations` (proposta di Alessandro, 12/11): ruoli
      `manager`, `maintainer`, `surveyor`, più `operator` e il complesso forestale, che le tracce del
      dev hanno (176 e 96 collegamenti); nei POI solo `operator` (35).
- [x] Distanza nei sentieri correlati (`nearby_tracks`): scartata con Alessandro il 13/11 (7e61a3f), non serve.
- [x] Nome del cammino (`properties.layer_name`) e nid di Drupal (`forestas.sardegnasentieri_id`, e
      `sardegnasentieri_id` nell'indice Elasticsearch) nel dettaglio di tracce e POI (oc:8959, f29b4c9).
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
  rotto»); sistemato in wm-package (oc:8953, #324: download GPX e KML di tracce e POI).
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
- `themes/forestas-child/inc/dettaglio.php` (nuovo, caricato da `functions.php`) — dati per sezione, differenze per
  tipo, basi degli indirizzi, chiave stabile per il plugin, segnaposto. Il `link_template` della ricerca è del plugin.
- `themes/forestas-child/wp-package/card.php` (nuovo) — card della ricerca uguale a quella dei post e del dettaglio.
- `themes/forestas-child/tests/dettaglio.php` (nuovo) — test del dettaglio con `wp eval-file`.
- `themes/forestas-child/` CSS e JS del dettaglio (testata compatta, galleria, mappa agganciata e spostata su
  mobile, gruppi dei dati, legende), `assets/css/galleria-lightbox.css` (frecce della lightbox, tutto il sito).
- `config/pagine.json`, `config/menu.json` — slug della pagina «Punti di interesse».
- `inc/pagine-statiche.php` — 301 da `/da-vedere/`; pagina «Mappa interattiva» (webapp dell'ambiente, focus
  sull'elemento, altezza) con `assets/js/mappa.js`; `forestas_condividi()` e `forestas_credits_immagine()` accettano
  anche i dati di un dettaglio dinamico (non solo l'id di un post).
- `docs/howto/ricreare-wordpress-da-zero.md` — se cambia la configurazione da applicare.

Repo `wp-package`: vedi la sua overview.
