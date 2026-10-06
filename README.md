# wp-forestas

WordPress dello shard Forestas: sostituisce la parte editoriale (news, post) del sito Drupal di
Sardegna Sentieri e riceve sentieri e POI dallo shard tramite il plugin
[`wp-geohub`](https://github.com/webmappsrl/wp-geohub).

Il repo è un **submodule di [`forestas`](https://github.com/webmappsrl/forestas)**: i servizi di
`compose.yml` sono inclusi dai compose dello shard e partono con lui. Da solo serve solo per
provarlo.

> Il repo è **pubblico**: nessun valore reale nei file tracciati. La configurazione sta in `.env`,
> escluso da git.

## Cosa contiene

| Percorso | Cosa |
|---|---|
| `compose.yml` | servizi `wordpress` (PHP 8.3 + Apache) e `mariadb` (11.4), volumi `wordpress-<APP_NAME>` e `mariadb-<APP_NAME>` |
| `docker/configs/php/` | immagine con le estensioni di WordPress e WP-CLI |
| `docker/scripts/init-wordpress.sh` | a ogni avvio porta WordPress allo stato atteso, un passo alla volta |
| `docker/themes/` | qui va messo `impreza.zip` (escluso da git) |
| `docker/uat/` | virtual host di riferimento per l'Apache dell'host di UAT |

## Avvio dentro forestas

1. In `forestas`: `git submodule update --init --recursive`.
2. `cp wp-forestas/.env-example wp-forestas/.env` e compila i valori.
3. Facoltativo: copia lo zip del tema Impreza in `wp-forestas/docker/themes/impreza.zip`.
4. Avvia lo shard come al solito, oppure solo WordPress con `scripts/wordpress-up.sh` di `forestas`.
5. Il sito risponde su `WP_URL`; il pannello su `WP_URL/wp-admin` con `WP_ADMIN_USER` e
   `WP_ADMIN_PASSWORD`.

## Avvio da solo, per provarlo

```bash
cp .env-example .env
APP_NAME=prova docker compose up -d --build
docker compose logs -f wordpress          # attendi «WordPress pronto»
APP_NAME=prova docker compose down -v     # per buttare via tutto
```

## Cosa fa l'inizializzazione

A ogni avvio del container `init-wordpress.sh` controlla, nell'ordine: variabili presenti,
database raggiungibile, core, `wp-config.php`, installazione, lingua `it_IT`, plugin `wp-geohub`,
tema Impreza. Esegue solo i passi che mancano e non tocca mai un'installazione esistente.

- Le **chiavi di sicurezza** le genera WP-CLI in `wp-config.php`, che sta nel volume: non vanno nel
  `.env`.
- I valori del `.env` (URL, utente amministratore) contano **solo alla prima installazione**: per
  cambiarli dopo si usa il pannello o WP-CLI.
- `wp-geohub` si installa nella cartella `wp-content/plugins/wm-package`: il plugin si chiama «WM
  Package» e cerca i propri file in quel percorso, quindi la cartella non va rinominata.
- `wp-geohub` si scarica dal `main` del repo pubblico; se GitHub non risponde il sito parte lo
  stesso e il plugin arriva al riavvio successivo.
- **Impreza** è un tema commerciale: lo zip si scarica dall'account Webmapp su ThemeForest e non va
  mai committato. Se lo zip manca il sito usa il tema di default; se lo aggiungi dopo, si installa
  al riavvio successivo. La **licenza** si attiva dal pannello, in Impreza → Attivazione.

## Indicizzazione

All'installazione è attiva l'opzione «Scoraggia i motori di ricerca dall'indicizzare questo sito».
**Al lancio in produzione va tolta** da Impostazioni → Lettura: lo script non la reimposta più,
perché l'installazione c'è già.

## Comandi utili

```bash
docker exec -it wordpress-<APP_NAME> wp --allow-root --path=/var/www/html <comando>
docker logs wordpress-<APP_NAME>
```
