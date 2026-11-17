# Mettere in opera gli enti su dev e UAT

Procedura per portare su un sito WordPress già installato (dev, UAT) il lavoro di oc:8990: elenco e dettaglio degli
enti, tab «Enti» della ricerca, link dai dettagli, contenuti di default nel cestino. Si fa una volta sola, dopo il
merge. Il perché delle scelte è in
[8990-enti-sul-sito-wordpress-…](../knowledge/8990-enti-sul-sito-wordpress-elenco-enti-dettaglio-enti-slug-e-ricerca-dallapi-di-oc8965.md);
per un sito ricreato da zero vale [ricreare-wordpress-da-zero.md](ricreare-wordpress-da-zero.md).

1. **Token.** `WP_API_TOKEN` nel `.env` dello shard, se manca: è il primo passo del primo deploy di oc:8965, in
   `forestas/docs/howto/deploy-automatico.md`. Lo stesso valore va nel `wp-forestas/.env`, poi si lancia
   `scripts/wordpress-up.sh` di forestas, perché il container legge il `.env` solo quando viene creato.
2. **Indice degli enti sullo shard**, se manca:
   `docker exec php-forestas php artisan scout:import "App\Models\Ente"` (pagina 8965 di forestas). Senza, il tab «Enti»
   mostra l'errore a ogni ricerca con un testo.
3. **Apply.** Un giro di `bin/wordpress-config.sh applica`, per controllare le righe `default:` (l'anteprima ne mostra una per ogni contenuto di default ancora presente) ed
   eventuali altre differenze del rilascio, poi `applica --conferma`.
   - Va fatto a mano: il cron `allinea` applica da solo solo le differenze dei menu.
   - Senza, `allinea` vede le righe `default:`, chiude ogni giorno con `STATO non allineato` e non applica più nemmeno
     i menu.
4. **Rewrite rules.** Si rigenerano al primo giro di `pagine` o `allinea` (riga `REGOLE`), o alla prima pagina del
   pannello, e fino ad allora `/enti/` dà 404. Per averle subito:
   `docker exec wordpress-<APP_NAME> wp --allow-root rewrite flush`.
5. **WP-Cron.** Si controlla che giri: `checked_at` dello stato dei rinnovi deve cambiare ogni 15 minuti
   (`docker exec wordpress-<APP_NAME> wp --allow-root option pluck forestas_entes_state checked_at`; `fetched_at` è
   l'ultimo rinnovo riuscito). Se resta fermo, il motivo lo dice `wp cron test`. Con WP-Cron fermo le pagine degli enti
   rinnovano da sé la copia dopo un'ora, ma i link dai dettagli e la ricerca restano sulla copia vecchia fino a quella
   visita.
6. **Cestino.** `EMPTY_TRASH_DAYS` non deve essere 0 nel `wp-config.php` del server: con 0, `wp_trash_post()` cancella
   per sempre. `wp config get EMPTY_TRASH_DAYS` dà un errore se la costante non c'è, e allora vale il default di 30
   giorni.
7. **Plugin.** Dopo il merge di `wp-package` 0.4.0 su `main`: il nuovo hash in `WP_PACKAGE_REF` di
   `docker/scripts/init-wordpress.sh`, poi `scripts/wordpress-up.sh`. Il controllo è `.wpf-ref` del plugin nel
   container. Le pagine degli enti funzionano anche con il plugin 0.3.0; la 0.4.0 serve al ripiego sulla copia nei
   dettagli di tracce e POI.

Il controllo finale: `/enti/` e `/enti/<slug>/` rispondono 200, il tab «Enti» di `/cerca-nel-sito/` ha il numero degli
enti, e nel log di PHP non ci sono righe `forestas enti:`.
