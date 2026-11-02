# Ricreare WordPress da zero, dal reset al sito completo

> **Pagina viva.** Va aggiornata ogni volta che un lavoro aggiunge un passo, cambia un ordine o
> scopre un comportamento nuovo del ciclo (un avviso atteso, un errore e come si risolve). Chi tocca
> l'installazione, `bin/wordpress-config.sh`, `config/` o la sincronizzazione da Drupal la rilegge e la
> corregge nello stesso lavoro.

Sequenza completa per riportare il WordPress di Forestas da un volume vuoto a un sito con
configurazione, pagine statiche, news, eventi, «da sapere», media e menu collegati. Vale in locale e su UAT; le differenze fra
i due sono indicate passo per passo. **In produzione il reset non si usa**: lì i dati di WordPress
sono permanenti.

Ultima verifica dell'intero ciclo: 29/10/2026, in locale (oc:8760), dopo le due review, passi 1-5:
44 pagine (22 IT, 22 EN), 99 news (95 IT, 4 EN), 14 eventi (11 IT, 3 EN) e 121 «da sapere» (72 IT, 49 EN),
tutte le traduzioni inglesi collegate in WPML, nessuna riga orfana; il giro dopo tutto «invariato» salvo la
news 4606 (2 media mancanti, sotto); ritratto identico a quello di prima del reset; i 406 vecchi
indirizzi Drupal dei contenuti pubblicati (alias e `/node/<nid>`) danno 301 verso una pagina 200. Il
primo `pagine --conferma` dura circa 11 minuti (500 media), l'anteprima 2. Verifica precedente: 27/10/2026, in locale (oc:8759), dopo le tre review, con `allinea`
lanciato subito dopo il reset con l'ambiente ridotto del cron, due volte di fila: 34 pagine (17 IT, 17 EN) e 99 news (95 IT, 4 EN), `STATO allineato`, ritratto identico a quello
di prima del reset; restano 2 media mancanti, una foto di galleria che dà 404 anche sul sito Drupal
(news 4606, in italiano e in inglese). Il giro dopo il reset dura una ventina di minuti: scarica dal
sito Drupal circa 300 file delle news. Verifica precedente: 22/10/2026, in locale (oc:8758, dopo le review), con le 28 pagine
(14 IT, 14 EN, sei con la nostra traduzione): passi 1-5 senza interventi a mano, nessun mancante né
avviso, `applica` senza differenze, ritratto identico a quello di prima del reset. Il 21/10 lo stesso;
il 20/10 cinque volte, in tutti e due gli ordini fra pagine e apply.

Su un sito che aveva già il lavoro di oc:8759, il primo `pagine --conferma` dopo
oc:8760 aggiorna una volta tutte le news, gli eventi e i «da sapere» (meta `_wpf_alias_drupal` dei vecchi
indirizzi; per i «da sapere» anche data di creazione, «Sempre in cima» e tipologie multiple): è atteso.

In alternativa ai passi 3-5, dopo il reset basta `bin/wordpress-config.sh allinea`, il comando del
cron: crea le pagine, collega i menu e verifica (`STATO allineato`). Verificato il 22/10 in locale
lanciandolo con l'ambiente ridotto del cron: ritratto identico a quello di prima del reset, e un
secondo giro senza nulla da fare. I passi qui sotto restano per guardare un passo alla volta.

## Prima di cominciare

| Cosa | Locale | UAT |
|---|---|---|
| Zip commerciali (`docker/themes/impreza.zip`, `docker/plugins/*.zip`) | sì, dalla cartella condivisa del team | sì, sull'host |
| Chiavi di licenza nel `wp-forestas/.env` | non servono: su `localhost` non si applicano | `WPML_SITE_KEY`, `IMPREZA_LICENSE_SECRET`, `IMPREZA_MAINTENANCE_KEY` |
| `DRUPAL_SYNC=on` nel `wp-forestas/.env` | sì, per sincronizzare le pagine | sì, finché Drupal è la fonte |
| Copia del database Drupal | tunnel SSH verso quella di UAT; nel `.env` `DRUPAL_DB_HOST=host.docker.internal`, `DRUPAL_DB_PORT=3307`, `DRUPAL_DB_USER` e `DRUPAL_DB_PASSWORD` | container `mysql-sardegnasentieri-dump` sulla stessa macchina; nel `.env` solo `DRUPAL_DB_USER` e `DRUPAL_DB_PASSWORD` |
| Orario | qualsiasi, ma non durante il reload della copia di UAT (dalle 10:00 per circa 15 minuti) | lo stesso |
| File dei media (`DRUPAL_FILES_URL` nel `.env`) | vuoto: si scaricano dal sito pubblico di Drupal | lo stesso finché Drupal è acceso; dopo lo spegnimento va puntato alla copia della cartella `sites/default/files`, da chiedere prima |

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
bin/wordpress-config.sh pagine              # anteprima: 44 pagine, 99 news, 14 eventi e 121 «da sapere» «creata», i termini, media da scaricare, link
bin/wordpress-config.sh pagine --conferma   # crea pagine, news, eventi, «da sapere» e termini, scarica media, assegna i template
```

Prima del primo giro i tipi `evento` e `da_sapere` devono essere traducibili in WPML (li rende tali
l'apply del passo 2, `custom_posts_sync_option` in `config/wpml.json`): senza, le traduzioni inglesi
nascerebbero scollegate dall'italiano. Si controlla in WPML → Impostazioni → «Traduzione dei tipi di
post», o con `bin/wordpress-config.sh applica` che non deve mostrare differenze su `wpml`.

Il riassunto finale dice quante pagine sono state create, quanti elementi mancano e la data dell'ultima
modifica nella copia Drupal (un AVVISO se è più vecchia di 30 giorni). Il dettaglio è in `log/pagine-<data-ora>.log`. Cosa fare se qualcosa
manca: passo 6.

Con `DRUPAL_SYNC` spenta l'anteprima funziona ma comincia con `AVVISO sincronizzazione da Drupal spenta
in questo ambiente (DRUPAL_SYNC): solo anteprima`, esce con 1 e chiude con «Per scrivere serve
DRUPAL_SYNC=on…»; il `--conferma` si ferma subito con un `ERRORE`. Vedi la tabella dei problemi.

## 4. Collegare i menu alle pagine

```bash
bin/wordpress-config.sh applica               # anteprima: i 6 menu, con voci da ricollegare
bin/wordpress-config.sh applica --conferma    # backup in backup/<data-ora>, poi applica
```

Atteso nell'output: `menu: 17 voci rimaste senza menu cancellate`. È normale. Dopo il primo giro del
passo 3 è atteso anche `wpml: stato del wizard: 'diverso' → 'come nel repo'` (29/10, oc:8760): lo cambia
la creazione dei contenuti, non un giro che non scrive nulla. Quando l'apply ricrea i
menu italiani, WPML stacca dai menu inglesi le traduzioni delle voci vecchie; i menu inglesi vengono
ricreati subito dopo, e le voci staccate si cancellano (oc:8758).

L'ordine fra i passi 3 e 4 si può invertire: se l'apply viene prima delle pagine, le voci restano link
per indirizzo e si collegano alle pagine con un secondo `applica --conferma` dopo il passo 3.

## 5. Verifiche

```bash
bin/wordpress-config.sh applica      # → «nessuna differenza»
bin/wordpress-config.sh pagine     # → tutte «invariata», salvo i media del passo 6 (la news 4606 «aggiornata (wpf_galleria)»: la sua foto manca anche su Drupal)
# AVVISO attesi a ogni giro, dati di Drupal: news 839 e 842 (tipologia 3905 e tag 3361 che non esistono più),
# «da sapere» 326 (tipologia 372), 327 (tipologia 371, tag 4284), 368 (tag 4399)
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
- News: `/news/` è la scheda News (le 4 news in evidenza), `/news/attivita-e-comunicazioni/` la
  seconda scheda; i pulsanti portano a `/attivita-e-comunicazioni/` e `/tutte-le-news/` (paginate); una news
  sta a `/news/<titolo>/` con testata, tag, testo, allegati, collegamenti e galleria; `/node/1094` porta
  in 301 alla news «Cos'è Sardegna-SENTIERI?»; `/tutte-le-news/page/99/` e `/news/page/2/` danno 404;
- Eventi (oc:8760): `/news/eventi/` è la terza scheda di News, con la data grande nelle card; il
  pulsante porta a `/eventi/` (8 per pagina, `/eventi/page/2/` ha gli ultimi 3); un evento sta a
  `/evento/<titolo>/` con «Dove», «Quando», testo, allegati, collegamenti e galleria; `/node/5106` porta in
  301 a «Boschi Aperti 2025…»;
- Da sapere (oc:8760): `/da-sapere/` è la scheda «Come organizzare l'escursione», poi
  `/da-sapere/norme-di-comportamento/`, `/da-sapere/pillole-di-sentieristica/` (6 riquadri) e
  `/da-sapere/faq/` (elenco di titoli); i pulsanti e le pillole portano alle pagine delle tipologie
  (`/tipologia-da-sapere-argomenti/<tipologia>/`); `/gradi_difficoltà` porta in 301 a
  `/da-sapere/gradi_difficolta/`;
- la pagina di una tipologia (`/tipologia-da-sapere-argomenti/in-caso-di-pericolo/`) ha la testata con
  l'immagine e la descrizione; `/taxonomy/term/4709` e `/tipologia-da-sapere-argomenti/caso-di-pericolo`
  portano in 301 alla tipologia; il giro dopo il primo non riscrive più nessuna tipologia (righe
  `TERMINE tipologia` assenti nel log);
- i vecchi indirizzi Drupal delle pagine portano in 301 alla pagina nuova: `/pagina-base/privacy` a
  `/privacy/`, `/tutti-gli-eventi` a `/news/eventi/`, `/pagina-attivita-e-comunicazioni` a
  `/news/attivita-e-comunicazioni/`;
- `/attivita-e-comunicazioni/`, `/tutte-le-news/` ed `/eventi/` hanno a sinistra i filtri «Tipologia» e «Anno»
  (sotto 960px dietro il pulsante «Filtri»); `/attivita-e-comunicazioni/?anno=2022` lascia 3 news,
  `/eventi/?anno=2025` 4 eventi; la lista degli eventi è a una colonna di card orizzontali;
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
| `ERRORE DRUPAL_DB_USER e DRUPAL_DB_PASSWORD mancano nel .env di wp-forestas` | credenziali della copia non impostate (non hanno valori predefiniti), o container non riavviato | aggiungerle al `wp-forestas/.env`, poi `scripts/wordpress-up.sh` |
| `ERRORE un'altra sincronizzazione delle pagine è in corso` | un giro sta già girando, o ne è rimasto il blocco dopo un container fermato a metà | aspettare la fine del giro; un blocco rimasto lo toglie l'avvio del container (solo con l'init nuovo, dopo `scripts/wordpress-up.sh`) o scade dopo 2 ore |
| `AVVISO … template … non riuscirebbe` in anteprima, uscita 1, «Prima i template di Impreza: … applica --conferma» | i template delle pagine non ci sono: l'apply del passo 2 non è stato fatto o non è riuscito | `bin/wordpress-config.sh applica --conferma`, poi di nuovo il passo 3 |
| `Uscita N: errore imprevisto, il giro non è finito` con la riga `Fatal error` | errore di PHP o di WP-CLI (per esempio `WP_ADMIN_USER` che non c'è più, o una funzione di WPML cambiata) | leggere la causa e il log completo; il comando non suggerisce nulla finché non è capita |
| `MANCANTE news 4606/it galleria … Not Found` o `cURL error 28` (o `MANCANTE pagina …`) o `Service Unavailable` | il sito Drupal non serve il file | passo 6, più tardi |
| `ERRORE copia Drupal sospetta: N news …` (o `N eventi`, `N «da sapere»`) | più contenuti del solito da mettere in bozza (news 5, eventi 2, «da sapere» 5), o nella copia molti meno pubblicati che in WordPress: di solito la copia è in ricarica | riprovare dopo il reload; se su Drupal sono state tolte davvero, `pagine --accetta-bozze --conferma` |
| `/news/attivita-e-comunicazioni/` (o `/news/eventi/`, `/da-sapere/faq/`…) dà 404 | regole degli indirizzi non rigenerate dopo una scheda nuova | un giro di `pagine --conferma` le rigenera (riga `REGOLE`); a mano `wp rewrite flush` |
| `AVVISO aggiornamento automatico dei link di WPML non trovato` | WPML aggiornato: gli hook che rendevano assoluti i link non si chiamano più così | controllare che i link dei testi inglesi restino relativi; aggiornare `wpf_wpml_senza_link_automatici()` |
| PHPStan di `forestas` fallisce con `Unable to create file …/build/phpstan/cache/…` | cache scritta da più processi su un volume montato da macOS | rilanciare con `--debug` (un solo processo) |
