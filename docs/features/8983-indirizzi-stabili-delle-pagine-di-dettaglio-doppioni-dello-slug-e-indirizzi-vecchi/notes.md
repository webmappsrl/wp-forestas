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

## Rilascio (16/11)

- Sul dev le tracce nell'Elasticsearch hanno `sardegnasentieri_id` (769 su 769): la precondizione della 0.3.0 c'è.
- `wp-package`: PR #5 chiusa con squash and merge in `develop` (`fe8569f`), poi `develop` → `main` con merge commit
  (`d820602`). Il piano e le note del plugin restano com'erano al merge: l'avanzamento del rilascio sta qui.
- `wp-forestas`: `WP_PACKAGE_REF` a `d820602`, in un commit a sé.

## Follow-up

- Dopo il merge di `wp-forestas`: puntatore di `wp-forestas` in `forestas` (il merge in `develop` fa partire il deploy
  sul dev), `scripts/wordpress-up.sh` di `forestas` sul dev e la verifica, anche il giorno dopo il reset notturno
  (Task 9 del piano del plugin). Su UAT e in produzione, prima, il controllo di `sardegnasentieri_id`
  nell'Elasticsearch delle tracce (pagina di conoscenza 8969).
