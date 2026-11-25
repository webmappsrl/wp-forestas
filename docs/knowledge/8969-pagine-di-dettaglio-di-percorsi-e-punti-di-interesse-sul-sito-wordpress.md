# Pagine di dettaglio di percorsi e punti di interesse

> Lavoro d'origine: oc:8969. Artefatti in `docs/features/8969-pagine-di-dettaglio-di-percorsi-e-punti-di-interesse-sul-sito-wordpress/`.
> Percorribilità, avviso, breadcrumb e POI come la Tavola 7: oc:9000, artefatti in
> `docs/features/9000-sito-wordpress-percorribilita-e-avvisi-dei-sentieri-dai-dati-dello-shard/`.
> La parte generica (indirizzi, indice, lettura del JSON, SEO, mappa) è del plugin `wp-package`: il suo `README.md` e la
> sua pagina `docs/knowledge/8969-pagine-di-dettaglio-di-percorsi-e-punti-di-interesse-sul-sito-wordpress.md`.

## Come funziona oggi

Sentieri, itinerari, cammini e punti di interesse dello shard hanno una pagina sul sito senza post in WordPress:
`/sentiero/<slug>/`, `/itinerario/…`, `/cammino/…` (base dal `route-type`) e `/punti-di-interesse/<slug>/` (sotto la
pagina «Punti di interesse», chiave `da-vedere` di `config/pagine.json`, che ha preso il posto di «Da vedere»: `/da-vedere/`
porta lì con un 301). Il plugin risolve l'indirizzo e legge il JSON; il child disegna la pagina secondo le Tavole 3, 4, 5
e 7 del wireframe di oc:8783.

- **Codice.** `themes/forestas-child/wp-package/track.php` e `poi.php` sostituiscono i template del plugin;
  `inc/dettaglio.php` trasforma il JSON nei dati di ogni sezione (tipo e colori, indicatori, «Sentiero per tutti»,
  gruppi dei dati, enti, correlati, mappa, allegati) e passa al plugin la chiave stabile (`forestas_dettaglio_nid()`
  sul filtro `wmwp_detail_source`: il nid di Drupal, `sardegnasentieri_id`). CSS e JS in `assets/css/dettaglio.css` e
  `assets/js/dettaglio.js`, caricati solo sui dettagli; `wp-package/card.php` dà alla ricerca la stessa card dei post.
- **Indirizzi stabili** (oc:8983, plugin 0.3.0): per Forestas la chiave con cui il plugin riconosce gli elementi, e
  con cui decide fra gli omonimi chi tiene l'indirizzo senza suffisso, è il nid di Drupal; i contenuti creati in Nova,
  senza nid, li riconosce dall'id dello shard. Come funzionano doppioni, storico dei percorsi e 301: README e pagina di
  conoscenza 8969 del plugin. Le correzioni a mano si fanno con `wp wmwp detail-paths`.
- **Dati letti dal plugin** (oc:9000, plugin 0.5.0): Blocchi Dettaglio, galleria, POI correlati, dati tecnici,
  luoghi, tipo del POI, percorribilità (`wmwp_walkability()`) e avviso (`wmwp_track_notice()`); il child li disegna e
  con un plugin più vecchio degrada (`forestas_plugin_ready()`). Fascia della percorribilità e indicatori a tacche
  seguono il registro degli stati del plugin: pagina di conoscenza di oc:9000.
- **File v1 della traccia** (oc:9000, scrum del 19/11): il child sceglie `v1` con `wmwp_track_json_version`, e il
  plugin legge `tracks/v1/<id>.json`, con ripiego sul file senza versione dove lo shard non la scrive.
- **Breadcrumb** «Home › <elenco del tipo> › nome» sotto la testata (`forestas_dettaglio_percorso()`): la voce di mezzo
  ha il nome della voce del menu principale che porta all'elenco (`forestas_voce_menu_principale()`).
- **POI** (Tavola 7): etichetta del tipo magenta con il testo bianco come le altre; la tipologia è il primo tipo che
  non sia l'area sosta; «Il punto in breve» con Quota (`properties.ele` del DEM, solo quando c'è) e «Area sosta
  attrezzata» (tipo `equipped-rest-area`); poi descrizione, galleria, «Percorsi correlati» (i percorsi entro 1 km
  dallo shard) e «Punti di interesse correlati» (`forestas.related_pois`, i correlati di Drupal, senza tipo).
- **Niente segnalazioni né recensioni** nel primo rilascio: tolti dal template, tornano con oc:8980. «Stampa» è
  tornato con oc:9018, quando la stampa dell'app è accesa (knowledge 9018).
- **Pagina «Mappa interattiva»** (`inc/pagine-statiche.php`, `forestas_mappa_url()`): iframe della webapp dello shard
  dell'ambiente (`wmwp_webapp_url()` del plugin), con il focus sull'elemento da «Vedi sulla mappa interattiva».
- **Icone delle tipologie di disabilità** dal set IcoMoon dello shard (`wmwp_icon_svg()`), che le prende dai termini
  `inclusivity-disabilities-*` (`txn-disability-motor`, `-sensory`, `-cognitive`).
- **Test.** `themes/forestas-child/tests/dettaglio.php`, con `wp eval-file` nel container.

## Perché così

- **Le tavole decidono, Drupal dove le tavole tacciono.** Ordine delle sezioni, gruppi dei dati (sentiero Percorso,
  Misure, Catasto; itinerario Percorso e Misure; cammino Misure e Stato), indicatori: dalle tavole. Dove il wireframe
  non dice, il sito attuale: partenza e arrivo sulla mappa come la mappa di GeoHub (cerchio verde, bandiera a scacchi,
  negli anelli solo la partenza), «Maggiori info» accanto al grado di difficoltà e alla validazione verso le pagine che
  Drupal linka (nodo 1102, «gradi di difficoltà», e nodo 87, Note legali, che spiegano i livelli 0-6), frecce della
  lightbox.
- **Itinerario senza Segnala e recensioni**: l'itinerario non è nel catasto (call del 02/11); la Tavola 4 ha
  «Segnala» e le recensioni, ma su Drupal le segnalazioni sono solo dei sentieri. Con oc:9000 segnalazioni e
  recensioni sono tolte da tutti i dettagli per il primo rilascio (tornano con oc:8980), e la fascia «Avviso» compare
  anche sugli itinerari, perché `api/v1/avvisi` include i loro avvisi.
- **Segnaposto dichiarati** («Dato non ancora disponibile») per i dati che lo shard non ha ancora, come la data di
  accatastamento: occupano lo spazio del componente finito, così il layout non cambia quando il dato arriva. Avviso e
  percorribilità vengono dal dato vero da oc:9000.
- **Gruppi dei dati chiusi da aprire** (pattern «disclosure», come il roadbook): titolo `h3` con il pulsante, tabella
  con il nome dal titolo, tutto aperto senza JavaScript; gli Enti aperti se ce n'è uno solo.
- **Su mobile la mappa sale dopo i pulsanti, spostata nel DOM** e non solo a vista: Tab e screen reader seguono
  l'ordine che si vede.
- **Pulsanti senza funzione tolti dal markup, non nascosti con il CSS** (oc:9000): lo screen reader non trova un
  pulsante che non fa niente; un commento nel template dice dove rimetterli.
- **Tacche nascoste allo screen reader** (oc:9000): il testo sotto dice già livello e stato («3 su 4, Parzialmente
  percorribile»), e l'immagine con la stessa frase lo faceva leggere due volte; il «·» visibile si legge come virgola.
- **Nome della voce del menu nella breadcrumb, non il titolo della pagina** (oc:9000): allora la pagina dei POI si
  intitolava «Da vedere», perché il titolo arrivava dalla sincronizzazione con Drupal, mentre il menu diceva «Punti di
  interesse». Da oc:9003 le pagine si intitolano come le voci del menu («Punti di interesse», «Itinerari»: `titolo` in
  `config/pagine.json`); la voce del menu resta la fonte della breadcrumb.
- **«Percorsi correlati» anche nel POI** (oc:9000): percorsi vicini al POI e percorsi correlati della traccia si
  calcolano tutti e due per vicinanza, quindi un'etichetta sola.
- **Pagina «Mappa interattiva» più alta di Drupal**: «Espandi la mappa» sta nella riga del percorso invece che in una
  barra di 88px; differenza voluta (Rubens).
- **Icone dal set dello shard, non dal package**: il set si carica da `/icons-upload` dello shard e i termini lo usano
  per nome; il package non si tocca per i dati di uno shard.
- **Il nid resta la chiave anche dopo lo spegnimento di Drupal** (oc:8983): l'import lo salva nello shard (per le
  tracce in `properties->sardegnasentieri_id`, per i POI in `properties->forestas->sardegnasentieri_id`), quindi gli
  elementi importati lo tengono per sempre. L'id dei contenuti creati dopo non si rinumera, perché il reset notturno
  con `RESTART IDENTITY` gira solo dove `SARDEGNASENTIERI_DAILY_RESET` è acceso, e nell'ordine degli omonimi i nuovi
  vengono dopo i nid, quindi non tolgono l'indirizzo a chi c'era.

## Messa in opera

L'elenco dei passi è nella howto `docs/howto/ricreare-wordpress-da-zero.md` («Prima di cominciare» e «Verifiche») e
nel Passo 5 del Task 6 del piano. Tre cose da sapere:

- con `WP_PACKAGE_REF` a un commit del plugin senza oc:8969 i dettagli si spengono in silenzio (i template controllano
  che le funzioni del plugin esistano), mentre «Punti di interesse» è già rinominata;
- le icone `txn-disability-*` si **aggiungono** al set che lo shard dell'ambiente ha già, mai al posto suo: il set è lo
  stesso che usano Nova e l'app (script in
  `docs/features/8969-pagine-di-dettaglio-di-percorsi-e-punti-di-interesse-sul-sito-wordpress/icone/`, con il
  controllo che non se ne perda nessuna);
- prima che un ambiente passi a `wp-package` 0.3.0, le tracce nell'Elasticsearch dello shard devono avere
  `sardegnasentieri_id`: altrimenti al primo giro il plugin le registra con l'id e, quando il nid arriva, gli omonimi
  possono scambiarsi l'indirizzo (oc:8983). Controllo e comando nella howto, «Prima di cominciare».

## Come ci siamo arrivati

- **Etichetta del tipo del POI bianca con il testo magenta** (oc:8969, superata da oc:9000): la guida del wireframe
  diceva «tipologia in etichetta bianca su #B7127F (6,16:1)», cioè testo bianco su magenta; il CSS della Tavola 7
  (`.tipo.pt`) lo conferma.
- **Tipologia del POI = primo tipo** (oc:8969, superata da oc:9000): su Perda Liana il primo tipo è «Area sosta
  attrezzata», che ora ha la sua riga nel riquadro.
- **Breadcrumb con il titolo della pagina d'elenco** (oc:9000, superata lo stesso giorno): dava «Da vedere».

- Le API del dettaglio sono cresciute insieme a queste pagine (oc:8959): enti per ruolo in `organizations`, percorsi
  vicini dei POI calcolati per vicinanza, `layer_name` per il nome del cammino, il nid di Drupal per la chiave stabile.
  La distanza fra due percorsi correlati è stata scartata: né il sito attuale né il wireframe la mostrano.
