> Ticket: oc:8983

# Indirizzi stabili delle pagine di dettaglio — piano (parte di wp-forestas)

Il piano completo sta nel plugin:
`wp-package/docs/features/8983-indirizzi-stabili-delle-pagine-di-dettaglio-doppioni-dello-slug-e-indirizzi-vecchi/plan.md`.
In questo repo si eseguono i suoi task 8 e 9:

- [x] **Task 8:** docblock di `forestas_dettaglio_nid()` (`themes/forestas-child/inc/dettaglio.php:233-237`) e pagina
      di conoscenza 8969; verifica in locale con i dati veri (doppioni ricalcolati con i 301 dai `-<nid>`, rinomina con
      il 301 dopo la ricostruzione, `wp wmwp detail-paths`).
- [ ] **Task 9, passo 3:** `WP_PACKAGE_REF` (`docker/scripts/init-wordpress.sh:16`) all'hash di `main` di
      `wp-package` con la 0.3.0, in un commit a sé, poi PR verso `develop`.
- [ ] **Task 9, passi 5-6:** `scripts/wordpress-up.sh` di `forestas` sul dev e verifica, anche dopo il reset notturno.
