> Ticket: oc:8983

# Indirizzi stabili delle pagine di dettaglio: doppioni dello slug e indirizzi vecchi

Il lavoro vero sta nel plugin `wp-package`: overview completa in
`wp-package/docs/features/8983-indirizzi-stabili-delle-pagine-di-dettaglio-doppioni-dello-slug-e-indirizzi-vecchi/overview.md`.
Qui solo la parte di questo repo.

## Cosa cambia

Il sito usa la versione 0.3.0 di `wp-package`: i doppioni dello slug prendono `-2`, `-3`… invece di `-<nid>`, chi ha
già un indirizzo lo tiene, e gli indirizzi vecchi (rinomina, traduzione, cambio di `route-type`) portano sempre con un
301 a quello attuale, grazie a uno storico che il plugin tiene in una tabella sua. Il nid di Drupal resta la chiave
degli elementi importati; quelli creati in Nova sono riconosciuti dall'id dello shard.

## Perché

Vedi l'overview del plugin. In questo repo il commento del filtro del child dice ancora che il nid serve «per il
suffisso degli slug doppi», e il sito scarica il plugin a un commit fisso: senza il nuovo hash la versione nuova non
arriva su nessun server.

## Requisiti

- [x] Commento di `forestas_dettaglio_nid()` (`themes/forestas-child/inc/dettaglio.php:233-237`) allineato: il nid è
      la chiave degli elementi importati, gli altri sono riconosciuti dall'id, il suffisso non lo usa più. Il codice
      del filtro non cambia (restituisce il nid o la chiave del plugin; l'id lo aggiunge il plugin), quindi nemmeno i
      suoi test (`themes/forestas-child/tests/dettaglio.php:92-98`).
- [x] `WP_PACKAGE_REF` in `docker/scripts/init-wordpress.sh` al commit di `main` di `wp-package` con la 0.3.0
      (`d820602`).
- [x] Pagina di conoscenza 8969: la chiave degli elementi è il nid finché c'è, l'id per quelli creati in Nova, anche
      dopo lo spegnimento di Drupal (il nid resta salvato nello shard, `properties->sardegnasentieri_id`); la
      precondizione della 0.3.0 su ogni ambiente (`sardegnasentieri_id` nell'Elasticsearch delle tracce).
- [x] Howto `ricreare-wordpress-da-zero.md`: il reset di WordPress cancella anche lo storico degli indirizzi.
- [ ] Sul dev, dopo il deploy, `scripts/wordpress-up.sh` di `forestas` e la verifica descritta nell'overview del plugin.

## Rischi

Vedi l'overview del plugin.

## Out of scope

Vedi l'overview del plugin.

## Moduli toccati

- `themes/forestas-child/inc/dettaglio.php` (commento)
- `docker/scripts/init-wordpress.sh` (`WP_PACKAGE_REF`)
- `docs/knowledge/8969-pagine-di-dettaglio-di-percorsi-e-punti-di-interesse-sul-sito-wordpress.md`
- `docs/howto/ricreare-wordpress-da-zero.md` (il reset cancella anche lo storico degli indirizzi)
- `CLAUDE.md` (trappola sulla chiave di `forestas_dettaglio_nid()` e riga 8969 della tabella «Conoscenza»)
