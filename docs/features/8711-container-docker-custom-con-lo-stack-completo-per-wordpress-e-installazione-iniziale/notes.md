> Ticket: oc:8711

# Notes — Container Docker custom con lo stack completo per WordPress e installazione iniziale

## Divergenze dal piano, task per task

### Task 3 cartella del plugin

Il piano (e l'overview) mettevano il plugin in `wp-content/plugins/wp-geohub`. All'attivazione il
plugin lancia un'eccezione: in `functions/imports.php:6` cerca i propri shortcode nel percorso
scritto fisso `wp-content/plugins/wm-package/shortcodes`. Il plugin si chiama «WM Package» e la
cartella **deve** chiamarsi `wm-package`: lo script lo scarica lì e attiva `wm-package`.

Nello stesso task, `wp option get WPLANG` falliva su un'installazione appena fatta (l'opzione non
esiste ancora): la lingua attiva si legge con `wp language core list --status=active`.

### Task 4 verifica dietro proxy

Il piano si aspettava, senza `X-Forwarded-Proto`, un `301` della home verso `https://`. WordPress
sulla home non forza lo schema e risponde `200`: l'attesa era sbagliata. La prova che conta l'ha
data `wp-admin` con l'header: `302` verso `https://wp.esempio.test/wp-login.php…`, nessun ciclo.

## Bug trovati

- **MariaDB inizializzata senza password.** Avviando lo shard prima di aver creato
  `wp-forestas/.env` (il caso del primo aggiornamento dopo il merge, su UAT e sulle macchine dei
  dev), MariaDB inizializzava il volume con l'utente `wordpress` senza password; il `.env` creato
  dopo veniva ignorato e WordPress riceveva `Access denied`. Trovato provando l'include nello shard
  locale. Correzione in `compose.yml`: senza `WP_DB_PASSWORD` MariaDB esce senza toccare il volume,
  e WordPress dipende da MariaDB come `service_started` (non `service_healthy`), così
  `docker compose up` dello shard non fallisce.

## Decisioni

- Il ticket oc:8711 è di tipo Feature, ma la stima su Orchestrator non è stata scritta: il dev ha
  scelto di saltarla (stima di `wm-estimate`: Misurato 1,0h + Stimato 3,3h = Totale 4,3h).
- Il compose incluso non usa variabili obbligatorie e carica `.env` con `required: false`: un
  `wp-forestas/.env` mancante non deve bloccare l'avvio dell'intero shard (scelta fatta nel piano).
- Il comportamento con lo zip di Impreza è stato verificato con un tema finto di nome «Impreza»:
  lo zip vero non era disponibile. Il riconoscimento usa lo slug della cartella (`impreza`,
  senza distinzione di maiuscole).

## Follow-up

- Ciclo giornaliero di azzeramento e import dei contenuti da Drupal o dalle API di `forestas`.
- Chiamate del plugin allo shard locale (oggi legge gli indirizzi pubblici da `wm-types`).
