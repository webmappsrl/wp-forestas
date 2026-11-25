# Mettere in opera le pagine d'elenco su dev e UAT

Procedura per portare su un sito WordPress già installato (dev, UAT) il lavoro di oc:9003: griglie di `/sentieri/`,
`/itinerari/`, `/cammini/` e `/punti-di-interesse/`, slider nella ricerca unica, titolo «Punti di interesse». Si fa una
volta sola, dopo il merge. Il perché delle scelte è in
[9003-pagine-sentieri-itinerari-cammini-e-punti-di-interesse.md](../knowledge/9003-pagine-sentieri-itinerari-cammini-e-punti-di-interesse.md);
per un sito ricreato da zero vale [ricreare-wordpress-da-zero.md](ricreare-wordpress-da-zero.md).

1. **Plugin.** `WP_PACKAGE_REF` di `docker/scripts/init-wordpress.sh` è già al commit di `wp-package` 0.7.0
   (`9bb0552`, `main` del plugin): basta `scripts/wordpress-up.sh` di forestas, che il deploy automatico lancia da solo
   quando cambia il puntatore di `wp-forestas` (dev con `develop` di forestas, UAT con `main`). Il controllo è
   `.wpf-ref` del plugin nel container. Fino a questo passo le quattro pagine mostrano la sola testata, come oggi.
2. **Copia delle tracce.** La scrive il rebuild orario dell'indice dei dettagli; per averla subito:
   `docker exec wordpress-<APP_NAME> wp --allow-root wmwp tracks-copy refresh` (stampa quante tracce, e quante con
   distanza e tempo). Senza copia le griglie dei percorsi mostrano «I percorsi non sono disponibili in questo momento».
3. **Apply.** `bin/wordpress-config.sh applica` per vedere le differenze del rilascio (il template nuovo
   `template-pagina-griglia`), poi `applica --conferma`. Va fatto a mano: il cron `allinea` applica da solo solo i menu.
4. **Pagine.** `bin/wordpress-config.sh pagine`: cambiano il template delle quattro pagine in italiano e in inglese, il
   titolo di `da-vedere` e di `itinerari` e il sottotitolo di `cammini`; poi `pagine --conferma`. Su UAT lontano dal reload della copia Drupal delle 10:00. Senza
   questo passo la sincronizzazione notturna lo farebbe comunque al giro successivo.
5. **Controllo.**
   - `/sentieri/`, `/itinerari/`, `/cammini/`, `/punti-di-interesse/` e le `/en/…` rispondono 200 con la testata e la
     griglia;
   - `/punti-di-interesse/` si intitola «Punti di interesse», `/itinerari/` «Itinerari», e `/cammini/` ha il suo
     sottotitolo («Scopri i cammini più belli…»);
   - la voce «Sentieri per tutti» del menu porta a `/sentieri/?activity=accessible-trails` (in inglese
     `/en/sentieri/?activity=accessible-trails`): il menu lo applica il cron `allinea` o `applica --conferma`;
   - gli slider compaiono solo dove i percorsi hanno distanza e tempo (su UAT al 25/11: `/sentieri/` e il tab dei
     percorsi della ricerca unica);
   - nel log di PHP non ci sono righe `wp-package` nuove.

## Da sapere

- **Il dislivello** non ha uno slider: compare da solo quando lo shard lo aggiunge a `MAP.filters`.
- **Su dev** quasi nessun percorso ha distanza e tempo (7 su 778 al 25/11): gli slider si vedono poco, ed è giusto così.
  Per provarli in locale si usano i dati di UAT (pagina di conoscenza, «Prova in locale»).
