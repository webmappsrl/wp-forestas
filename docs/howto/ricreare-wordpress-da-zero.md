# Ricreare WordPress da zero, dal reset al sito completo

> **Pagina viva.** Va aggiornata ogni volta che un lavoro aggiunge un passo, cambia un ordine o
> scopre un comportamento nuovo del ciclo (un avviso atteso, un errore e come si risolve). Chi tocca
> l'installazione, `bin/wordpress-config.sh`, `config/` o la sincronizzazione da Drupal la rilegge e la
> corregge nello stesso lavoro.

Sequenza completa per riportare il WordPress di Forestas da un volume vuoto a un sito con
configurazione, pagine statiche, media e menu collegati. Vale in locale e su UAT; le differenze fra
i due sono indicate passo per passo. **In produzione il reset non si usa**: lì i dati di WordPress
sono permanenti.

Ultima verifica dell'intero ciclo: 21/10/2026, in locale (oc:8758), con le 28 pagine (14 IT, 14 EN,
sei con la nostra traduzione): passi 1-5 senza interventi a mano, ritratto identico a quello di prima
del reset. Il 20/10 cinque volte, in tutti e due gli ordini fra pagine e apply.

## Prima di cominciare

| Cosa | Locale | UAT |
|---|---|---|
| Zip commerciali (`docker/themes/impreza.zip`, `docker/plugins/*.zip`) | sì, dalla cartella condivisa del team | sì, sull'host |
| Chiavi di licenza nel `wp-forestas/.env` | non servono: su `localhost` non si applicano | `WPML_SITE_KEY`, `IMPREZA_LICENSE_SECRET`, `IMPREZA_MAINTENANCE_KEY` |
| `DRUPAL_SYNC=on` nel `wp-forestas/.env` | sì, per sincronizzare le pagine | sì, finché Drupal è la fonte |
| Copia del database Drupal | tunnel SSH verso quella di UAT; nel `.env` `DRUPAL_DB_HOST=host.docker.internal`, `DRUPAL_DB_PORT=3307`, `DRUPAL_DB_USER` e `DRUPAL_DB_PASSWORD` | container `mysql-sardegnasentieri-dump` sulla stessa macchina; nel `.env` solo `DRUPAL_DB_USER` e `DRUPAL_DB_PASSWORD` |
| Orario | qualsiasi, ma non durante il reload della copia di UAT (dalle 10:00 per circa 15 minuti) | lo stesso |

Il tunnel, in locale (`forestas/docs/howto/copia-database-drupal.md`, «Lavorare dal locale sulla copia
di UAT»):

```bash
ssh -f -N -o ExitOnForwardFailure=yes -L 3307:127.0.0.1:3307 uat.forestas
nc -z 127.0.0.1 3307 && echo tunnel aperto
```

Il tunnel cade al riavvio del Mac o al cambio di rete. Se è caduto, la sincronizzazione si ferma con
`copia Drupal non raggiungibile su host.docker.internal:3307`.

Prima del reset, se il sito ha contenuti da conservare, fai un backup, per esempio con All-in-One WP
Migration: il reset cancella database e uploads.

## 1. Reset

Dalla root di `forestas`, sull'host:

```bash
scripts/wordpress-reset.sh --conferma
```

Rimuove i container `wordpress-<APP_NAME>` e `mariadb-<APP_NAME>`, cancella **solo** i volumi
omonimi e rilancia `scripts/wordpress-up.sh`. Gli altri volumi dello shard (Postgres, Elasticsearch,
la copia Drupal) non li tocca.

## 2. Installazione e apply automatico

L'inizializzazione (`docker/scripts/init-wordpress.sh`) installa core, plugin, Impreza, WPML e child
dagli zip, poi applica da sola `config/`. Si segue con:

```bash
docker logs -f wordpress-<APP_NAME>     # fino a «[init-wordpress] WordPress pronto su …»
docker exec -u www-data wordpress-<APP_NAME> wp --path=/var/www/html option get wp_forestas_config_applicata
```

L'opzione contiene la data dell'apply riuscito. **Avvisi attesi** a questo punto: per ogni voce di
menu che porta a una pagina statica,
`menu …: page «sentieri» non c'è su questo sito, la voce è un link a /sentieri/`. Le pagine arrivano
al passo 3, e il passo 4 collega le voci.

## 3. Pagine statiche da Drupal

Dalla cartella `wp-forestas`:

```bash
bin/wordpress-config.sh pagine              # anteprima: 28 pagine «creata» (14 IT, 14 EN), media da scaricare, link
bin/wordpress-config.sh pagine --conferma   # crea pagine, scarica media, assegna i template
```

Il riassunto finale dice quante pagine sono state create e quanti elementi mancano. Il dettaglio è in
`log/pagine-<data-ora>.log`. Cosa fare se qualcosa manca: passo 6.

## 4. Collegare i menu alle pagine

```bash
bin/wordpress-config.sh applica               # anteprima: i 6 menu, con voci da ricollegare
bin/wordpress-config.sh applica --conferma    # backup in backup/<data-ora>, poi applica
```

Atteso nell'output: `menu: 17 voci rimaste senza menu cancellate`. È normale. Quando l'apply ricrea i
menu italiani, WPML stacca dai menu inglesi le traduzioni delle voci vecchie; i menu inglesi vengono
ricreati subito dopo, e le voci staccate si cancellano (oc:8758).

L'ordine fra i passi 3 e 4 si può invertire: se l'apply viene prima delle pagine, le voci restano link
per indirizzo e si collegano alle pagine con un secondo `applica --conferma` dopo il passo 3.

## 5. Verifiche

```bash
bin/wordpress-config.sh applica      # → «nessuna differenza»
bin/wordpress-config.sh pagine     # → tutte «invariata», salvo i media del passo 6
bin/wordpress-config.sh ritratto   # stato del sito, da confrontare con un ritratto precedente
```

Nel browser, in italiano e inglese:
- header e footer di Sardegna Sentieri, non quelli demo di Impreza;
- le voci dei menu portano alle pagine, in inglese a quelle inglesi (tutte le 14 pagine hanno la
  versione inglese, sei con la nostra traduzione in `config/traduzioni/en/`); CONTATTI porta a `/enti`, come su Drupal, e
  oggi dà 404 finché l'elenco degli enti non esiste in WordPress; AREA RISERVATA porta al login della
  Regione;
- le pagine hanno testata, percorso «Home › pagina» subito sotto, «Condividi» sulle pagine di testo,
  testo e allegati; la Mappa interattiva mostra la mappa a tutta pagina con «Espandi la mappa»;
- il titolo della scheda è «Pagina | Sardegna Sentieri», come su Drupal: il nome viene da `blogname`
  di `config/sito.json` (l'apply lo scrive), il separatore dal child. Se resta «Forestas» l'apply non è
  andato a buon fine.

## 6. Elementi mancanti

Se il riassunto del passo 3 dice `mancanti: N`, le righe `MANCANTE` spiegano cosa e perché. Il caso
tipico è un media che il sito Drupal non serve (timeout, 503): la pagina c'è, manca il file.

```bash
bin/wordpress-config.sh pagine --contenuti-mancanti --conferma   # ritenta solo quegli elementi
```

L'elenco sta sul sito (opzione `wpf_sync_mancanti`) e si svuota da sé quando gli elementi riescono;
ogni esecuzione normale li ritenta comunque.

## 7. Sincronizzazione quotidiana

Da qui in poi le pagine si tengono allineate a Drupal con un cron sull'host:
[cron-sincronizzazione-pagine.md](cron-sincronizzazione-pagine.md).

## Problemi incontrati e come si riconoscono

| Sintomo | Causa | Cosa fare |
|---|---|---|
| il reset si ferma nella ricostruzione dell'immagine con `cannot download "pecl/imagick"` (HTTP 500) | pecl.php.net non risponde, a intermittenza (21/10); il reset ha già tolto container e volumi | rilanciare solo `scripts/wordpress-up.sh` dalla root di `forestas` finché la build riesce; non serve rifare il reset |
| nei log dell'init, dopo «installo WordPress»: `Warning: Undefined array key "HTTP_HOST" in …/wp-includes/functions.php` | `wp core install` gira da WP-CLI, senza una richiesta web | niente: l'installazione riesce; è un avviso atteso |
| `ERRORE copia Drupal non integra: tabella … non leggibile` o `… vuota` | la copia di UAT si sta ricaricando (cron delle 10:00, circa 15 minuti) | aspettare la fine (`ssh uat.forestas 'tail -n 5 /var/www/html/forestas/storage/logs/drupal-dump-sync.log'` mostra «fine (uscita 0)») e rilanciare |
| `ERRORE copia Drupal non raggiungibile su host.docker.internal:3307` | tunnel caduto (in locale) | riaprire il tunnel |
| `ERRORE sincronizzazione da Drupal spenta in questo ambiente` | `DRUPAL_SYNC` non è `on`, o il container non ha riletto il `.env` | `.env`, poi `scripts/wordpress-up.sh` |
| `MANCANTE immagine/allegato … cURL error 28` o `Service Unavailable` | il sito Drupal non serve il file | passo 6, più tardi |
| `AVVISO aggiornamento automatico dei link di WPML non trovato` | WPML aggiornato: gli hook che rendevano assoluti i link non si chiamano più così | controllare che i link dei testi inglesi restino relativi; aggiornare `wpf_wpml_senza_link_automatici()` |
| PHPStan di `forestas` fallisce con `Unable to create file …/build/phpstan/cache/…` | cache scritta da più processi su un volume montato da macOS | rilanciare con `--debug` (un solo processo) |
