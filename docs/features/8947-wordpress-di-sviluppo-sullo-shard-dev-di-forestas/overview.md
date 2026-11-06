> Ticket: oc:8947

# WordPress di sviluppo sullo shard dev di forestas (wp.forestas.dev.maphub.it)

L'overview completa del lavoro, che è soprattutto di messa in opera sull'host di dev, sta in
`forestas`: `docs/features/8947-wordpress-di-sviluppo-sullo-shard-dev-di-forestas/overview.md`.
Qui solo la parte che tocca questo repo.

## Cosa cambia

`docker/` ha i virtual host Apache di riferimento anche per dev, accanto a quelli di UAT, e
`cron/allinea.crontab` dice dove è installato il cron di `allinea` (UAT alle 11:00, dev alle 05:00
UTC), con la riga per dev commentata. `CLAUDE.md` e la procedura del cron rimandano a quello stato
invece di ripeterlo; il README elenca `docker/dev/`.

## Perché

Il WordPress va in opera anche su `https://wp.forestas.dev.maphub.it`, per provare senza toccare
UAT (scrum del 06/11). Come per UAT, i virtual host si versionano, così chi rifà il server parte da
un file.

## Requisiti

- [x] `docker/dev/wp.forestas.dev.maphub.it.conf`: HTTP, challenge ACME, redirect a HTTPS.
- [x] `docker/dev/wp.forestas.dev.maphub.it-le-ssl.conf`: HTTPS con reverse proxy verso
  `127.0.0.1:8090` e certificato di `wp.forestas.dev.maphub.it`.
- [x] Nessun valore reale nei file: il repo è pubblico (solo dominio, porta, percorsi).
- [x] `cron/allinea.crontab`: stato dell'installazione aggiornato con dev e orario di dev; lo stato
  sta solo lì, `CLAUDE.md` e `docs/howto/cron-sincronizzazione-pagine.md` ci rimandano.

## Rischi

- Due copie quasi identiche dei virtual host (uat e dev): una modifica a una va riportata
  sull'altra. Accettato finché gli ambienti sono due.

## Out of scope

- Codice dell'init, di `bin/` e del child theme: non cambiano.
- Licenze di Impreza e WPML su dev.

## Moduli toccati

- `docker/dev/wp.forestas.dev.maphub.it.conf` — nuovo
- `docker/dev/wp.forestas.dev.maphub.it-le-ssl.conf` — nuovo
- `cron/allinea.crontab` — stato, riga commentata per dev
- `docs/howto/cron-sincronizzazione-pagine.md` — dove va il cron (anche dev), stato per rimando
- `CLAUDE.md` — trappola del cron: stato per rimando
- `README.md` — `docker/dev/` nella tabella dei contenuti
