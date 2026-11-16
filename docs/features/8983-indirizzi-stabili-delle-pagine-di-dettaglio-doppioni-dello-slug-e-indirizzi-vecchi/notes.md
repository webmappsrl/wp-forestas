> Ticket: oc:8983

# Notes — Indirizzi stabili delle pagine di dettaglio (parte di wp-forestas)

Le note complete (divergenze, bug trovati in review, decisioni, verifica in locale) stanno nel plugin:
`wp-package/docs/features/8983-indirizzi-stabili-delle-pagine-di-dettaglio-doppioni-dello-slug-e-indirizzi-vecchi/notes.md`.

## Divergenze dal piano

- La howto `docs/howto/ricreare-wordpress-da-zero.md` non era nel piano: le review interne hanno chiesto di scrivere
  che il reset di WordPress cancella anche lo storico degli indirizzi, con i suoi effetti (su UAT va bene).
- La pagina di conoscenza 8969 ha anche la precondizione della 0.3.0 su ogni ambiente (`sardegnasentieri_id`
  nell'Elasticsearch delle tracce), emersa dalla verifica in locale e dalla review formale.

## Decisioni

- Il codice di `forestas_dettaglio_nid()` non cambia: restituisce il nid o la chiave del plugin, e il passaggio
  all'id per gli elementi creati in Nova lo fa il plugin. Cambia solo il docblock; i test del child restano uguali.

## Follow-up

- `WP_PACKAGE_REF` (`docker/scripts/init-wordpress.sh:16`) all'hash di `main` di `wp-package` con la 0.3.0, poi
  `scripts/wordpress-up.sh` di `forestas` sul dev e la verifica (Task 9 del piano del plugin). Prima, su ogni
  ambiente, il controllo di `sardegnasentieri_id` nell'Elasticsearch delle tracce (pagina di conoscenza 8969).
