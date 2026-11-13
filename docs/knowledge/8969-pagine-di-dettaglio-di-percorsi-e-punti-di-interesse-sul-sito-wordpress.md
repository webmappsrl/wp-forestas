# Pagine di dettaglio di percorsi e punti di interesse

> Lavoro d'origine: oc:8969. Artefatti in `docs/features/8969-pagine-di-dettaglio-di-percorsi-e-punti-di-interesse-sul-sito-wordpress/`.
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
- **Itinerario senza Avviso, Segnala e recensioni**: l'itinerario non è nel catasto (call del 02/11); la Tavola 4 ha
  «Segnala» e le recensioni, ma su Drupal le segnalazioni sono solo dei sentieri. Si decide nel ticket di segnalazioni e
  recensioni.
- **Segnaposto dichiarati** («Dato non ancora disponibile») per avvisi, percorribilità, recensioni, data di
  accatastamento: occupano lo spazio del componente finito, così il layout non cambia quando il dato arriva.
- **Gruppi dei dati chiusi da aprire** (pattern «disclosure», come il roadbook): titolo `h3` con il pulsante, tabella
  con il nome dal titolo, tutto aperto senza JavaScript; gli Enti aperti se ce n'è uno solo.
- **Su mobile la mappa sale dopo i pulsanti, spostata nel DOM** e non solo a vista: Tab e screen reader seguono
  l'ordine che si vede.
- **Pagina «Mappa interattiva» più alta di Drupal**: «Espandi la mappa» sta nella riga del percorso invece che in una
  barra di 88px; differenza voluta (Rubens).
- **Icone dal set dello shard, non dal package**: il set si carica da `/icons-upload` dello shard e i termini lo usano
  per nome; il package non si tocca per i dati di uno shard.

## Messa in opera

L'elenco dei passi è nella howto `docs/howto/ricreare-wordpress-da-zero.md` («Prima di cominciare» e «Verifiche») e
nel Passo 5 del Task 6 del piano. Due cose da sapere:

- con `WP_PACKAGE_REF` a un commit del plugin senza oc:8969 i dettagli si spengono in silenzio (i template controllano
  che le funzioni del plugin esistano), mentre «Punti di interesse» è già rinominata;
- le icone `txn-disability-*` si **aggiungono** al set che lo shard dell'ambiente ha già, mai al posto suo: il set è lo
  stesso che usano Nova e l'app (script in
  `docs/features/8969-pagine-di-dettaglio-di-percorsi-e-punti-di-interesse-sul-sito-wordpress/icone/`, con il
  controllo che non se ne perda nessuna).

## Come ci siamo arrivati

- Le API del dettaglio sono cresciute insieme a queste pagine (oc:8959): enti per ruolo in `organizations`, percorsi
  vicini dei POI calcolati per vicinanza, `layer_name` per il nome del cammino, il nid di Drupal per la chiave stabile.
  La distanza fra due percorsi correlati è stata scartata: né il sito attuale né il wireframe la mostrano.
