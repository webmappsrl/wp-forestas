# Ricreare WordPress da zero, dal reset al sito completo

> **Pagina viva.** Va aggiornata ogni volta che un lavoro aggiunge un passo, cambia un ordine o
> scopre un comportamento nuovo del ciclo (un avviso atteso, un errore e come si risolve). Chi tocca
> l'installazione, `bin/wordpress-config.sh`, `config/` o la sincronizzazione da Drupal la rilegge e la
> corregge nello stesso lavoro.

Sequenza completa per riportare il WordPress di Forestas da un volume vuoto a un sito con
configurazione, pagine statiche, news, eventi, «da sapere», media e menu collegati. Vale in locale e su UAT; le differenze fra
i due sono indicate passo per passo. **In produzione il reset non si usa**: lì i dati di WordPress
sono permanenti.

Il reset cancella anche lo storico degli indirizzi delle pagine di dettaglio del plugin `wp-package` (oc:8983): sul
sito ricreato i doppioni dello slug si ricalcolano in ordine di nid, quindi un sentiero che teneva il suo indirizzo
grazie allo storico può scambiarlo con un omonimo, e i 301 dagli indirizzi di prima di una rinomina non ci sono più. Su
UAT va bene; è un motivo in più per non usarlo in produzione.

Ultima verifica dell'intero ciclo: 10/11/2026, in locale (oc:8952), con la ricerca unica e il plugin
`wp-package` collegato al clone (`WP_PACKAGE_DIR`), passi 1-5: 46 pagine (23 IT, 23 EN, comprese le due
`/cerca-nel-sito`), 99 news, 14 eventi e 121 «da sapere», come prima del reset anche nei 774 allegati; mancanti
solo i 2 media della news 4606; il giro dopo tutto «invariata» salvo la news 4606; `applica` senza differenze;
ritratto identico a quello di prima del reset; la ricerca dà per «perda liana» 42 percorsi e 4 contenuti in
italiano e in inglese. Il primo `pagine --conferma` è durato 12 minuti. Verifica precedente: 29/10/2026, in locale (oc:8760), dopo le due review, passi 1-5:
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
| Plugin della ricerca `wp-package` (oc:8952) | `WP_PACKAGE_SHARD=forestasdev`, `WP_PACKAGE_APP_ID=1`; per sviluppare il plugin anche `WP_PACKAGE_DIR` con il percorso del clone | `WP_PACKAGE_SHARD` e `WP_PACKAGE_APP_ID` dello shard di UAT; `WP_PACKAGE_DIR` vuoto |
| Pagine di dettaglio di percorsi e POI (oc:8969) | `wp-package` con il lavoro di oc:8969 (`WP_PACKAGE_REF` di `init-wordpress.sh`, o il clone con `WP_PACKAGE_DIR`); sullo shard il set di icone con le `txn-disability-*` caricato da `/icons-upload` e il nome inglese dei tre `route-type` inserito in Nova | lo stesso: il set di icone si carica prima del rilascio aggiungendo le icone a quelle che ci sono (script in `docs/features/8969-…/icone/`), il nome inglese dei `route-type` a mano in Nova |
| Enti (oc:8990) | `WP_API_TOKEN` nel `wp-forestas/.env` con il valore di `WP_API_TOKEN` del `.env` dello shard del dev: con `WP_PACKAGE_SHARD=forestasdev` il sito locale legge il dev, anche dopo il reset. Lo shard locale lo legge solo con il mu-plugin non versionato `wp-content/mu-plugins/wpf-shard-locale.php` (oc:8969), che sta nel volume di WordPress e quindi il reset cancella: rimesso il mu-plugin, il token è quello del `.env` di `forestas` locale, e sullo shard locale serve l'indice degli enti, `docker exec php-forestas php artisan scout:import "App\Models\Ente"` (pagina 8965 di `forestas`) | `WP_API_TOKEN` del `.env` dello shard di UAT, lo stesso valore, nel `wp-forestas/.env`; l'indice degli enti lo crea il primo deploy di oc:8965 (`forestas/docs/howto/deploy-automatico.md`) |
| Indirizzi stabili dei dettagli (`wp-package` 0.3.0, oc:8983) | le tracce nell'Elasticsearch dello shard locale hanno `sardegnasentieri_id` (una richiesta all'API Elasticsearch dello shard); se manca, `docker exec php-forestas php artisan scout:import "App\Models\EcTrack"` | lo stesso sullo shard di UAT, prima del primo giro della 0.3.0 |
| File dei media (`DRUPAL_FILES_URL` nel `.env`) | vuoto: si scaricano dal sito pubblico di Drupal | lo stesso finché Drupal è acceso; dopo lo spegnimento va puntato alla copia della cartella `sites/default/files`, da chiedere prima |

Il tunnel, in locale (`forestas/docs/howto/copia-database-drupal.md`, «Lavorare dal locale sulla copia
di UAT»):

```bash
ssh -f -N -o ExitOnForwardFailure=yes -L 3307:127.0.0.1:3307 uat.forestas
nc -z 127.0.0.1 3307 && echo tunnel aperto
```

Il tunnel cade al riavvio del Mac o al cambio di rete. Se è caduto, la sincronizzazione si ferma con
`copia Drupal non raggiungibile su host.docker.internal:3307`.

Il container legge il `.env` solo quando viene creato: `WP_API_TOKEN` va scritto prima del reset, che ricrea il
container. Se lo si aggiunge dopo, serve `scripts/wordpress-up.sh` di `forestas`; fino ad allora le pagine degli enti
rispondono 503 e il log di PHP dice:
`forestas enti: rinnovo non riuscito, codice 0 (WP_API_TOKEN mancante nell'ambiente del container)`.

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

L'opzione contiene la data dell'apply riuscito. Nei log del plugin della ricerca: «scarico
wp-package al commit …» (in locale con `WP_PACKAGE_DIR`: «collego wp-package al clone di
sviluppo»), «attivo il plugin wp-package», «imposto wmwp_shard dal .env» e «imposto wmwp_app_id dal
.env». Finché il repo GitHub del plugin non esiste, al loro posto c'è l'avviso atteso «AVVISO:
wp-package non ancora pubblicato, nessun download», e `/cerca-nel-sito` mostra il campo di ricerca
semplice. **Avvisi attesi** a questo punto: per ogni voce di
menu che porta a una pagina statica,
`menu …: page «sentieri» non c'è su questo sito, la voce è un link a /sentieri/`. Le pagine arrivano
al passo 3, e il passo 4 collega le voci.

## 3. Pagine statiche da Drupal

Dalla cartella `wp-forestas`:

```bash
bin/wordpress-config.sh pagine              # anteprima: 46 pagine, 99 news, 14 eventi e 121 «da sapere» «creata», i termini, media da scaricare, link
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

Atteso nell'output: `menu: 18 voci rimaste senza menu cancellate` (10/11; il 29/10 erano 17, e `config/menu.json` è
cambiato il 02/11 con oc:8760). È normale. Il 18/11, con oc:9000, il menu di servizio ha perso la voce
«Segnalazioni» («Report an issue» in inglese): il numero può cambiare, il ciclo non è stato rifatto. Dopo il primo giro del
passo 3 è atteso anche `wpml: stato del wizard: 'diverso' → 'come nel repo'` (29/10, oc:8760): lo cambia
la creazione dei contenuti, non un giro che non scrive nulla. Quando l'apply ricrea i
menu italiani, WPML stacca dai menu inglesi le traduzioni delle voci vecchie; i menu inglesi vengono
ricreati subito dopo, e le voci staccate si cancellano (oc:8758).

L'apply ha anche il passo «4. Contenuti di default di WordPress» (oc:8990): sposta nel cestino, con
`wp_trash_post()`, il post 1 «Ciao mondo!», la pagina 2 «Pagina di esempio» e la bozza 3 «Privacy
Policy», se hanno ancora lo slug di default e non sono mai stati modificati, e azzera
`wp_page_for_privacy_policy` se punta a quella bozza. Su un sito appena ricreato lo ha già fatto l'apply
automatico del passo 2, quindi qui l'anteprima non ha righe `default:` (comportamento del codice, non ancora
verificato con un reset completo). Le quattro righe compaiono su un sito installato prima di oc:8990 che non ha
ancora fatto un `applica --conferma` con il passo nuovo, e dopo quel giro non compaiono più:

```
default: post 1 ciao-mondo: 'presente' → 'nel cestino'
default: page 2 pagina-di-esempio: 'presente' → 'nel cestino'
default: page 3 privacy-policy: 'presente' → 'nel cestino'
default: opzione wp_page_for_privacy_policy: 3 → 0
```

In tutti e due i casi si verifica con `wp post list --post_status=trash` (id 1, 2, 3) e con `/news/ciao-mondo/`
che risponde 404.

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
  versione inglese, sei con la nostra traduzione in `config/traduzioni/en/`); CONTATTI porta all'elenco degli enti
  `/enti/`, come su Drupal; AREA RISERVATA porta al login della Regione;
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
- ricerca (oc:8952; parametri, filtri e slug inglese cambiati da oc:9018, verificati il 24/11 in locale ma non ancora
  con un reset completo): `/cerca-nel-sito/?q=perda+liana` ha i tab «Tutti», «Percorsi», «Punti di interesse»,
  «Contenuti del sito» ed «Enti» con i numeri (il 24/11 in locale 42, 13, 4 e 2; in inglese «All», «Trails»…) e i
  gruppi con le prime card; il tab «Percorsi» (`?tab=trails`) ha i filtri a sinistra, presi dal `config.json`
  dell'app come le app (il 24/11, sul `config.json` del dev: Percorribilità, Stato validazione, Tipo percorso,
  Attività, una «Difficoltà …» per scala, le aree degli overlay) e una casella su ogni riga: scegliendo due percorsi
  compare in fondo la barra «2 percorsi selezionati» e «Scarica 2 percorsi in GPX» scarica
  `sardegna-sentieri-percorsi-2.gpx`; «Mappa», accanto al totale, mostra gli stessi percorsi su una mappa con
  «Inquadratura sul riquadro che contiene tutti i risultati» in basso (il 10/11 in locale, senza testo, 769
  percorsi in circa 7 secondi). I filtri del `config.json` compaiono solo dopo il primo giro del cron che lo scarica:
  in locale WP-Cron non parte, si lancia `docker exec wordpress-forestas wp cron event run wmwp_app_config_refresh
  --allow-root`. `/?s=oliena` porta in 302 a `/cerca-nel-sito/?q=oliena`, `/en/?s=trail` a
  `/en/search-in-our-website/?q=trail`, `/en/cerca-nel-sito/` in 301 a `/en/search-in-our-website/`; un vecchio link
  di Drupal `…?f%5B0%5D=cerca_contenuto_nel_sito%3Asentiero` porta in 302 al tab «Percorsi» con il filtro del tipo
  (`?tab=trails&type=<id del layer>`, `type=11` sul dev); fra i «Contenuti del sito» di tipo pagina ci sono le 13
  pagine venute da Drupal (la «Pagina di esempio» dell'installazione è nel cestino, oc:8990);
- dettagli (oc:8969, aggiunti al ciclo il 13/11 ma non ancora verificati con un reset completo): `/da-vedere/` porta
  in 301 a `/punti-di-interesse/`; dalla ricerca una card porta al dettaglio (`/?wmwp_detail=track&wmwp_id=<id>` in
  302 a `/sentiero/<slug>/`, `/itinerario/…`, `/cammino/…`, i POI sotto `/punti-di-interesse/<slug>/`), con testata,
  indicatori, mappa con partenza e arrivo, gruppi dei dati da aprire e correlati; le icone delle tipologie di
  disabilità si vedono solo se il set dello shard le ha. Se dopo il reset un dettaglio dà 404 perché le basi sono
  cambiate (pagina «Punti di interesse» ricreata), l'indice si rifà da solo entro 5 minuti, o subito con
  `docker exec wordpress-forestas wp eval 'wmwp_detail_index( true );' --allow-root`;
- enti (oc:8990, aggiunti al ciclo il 16/11 ma non ancora verificati con un reset completo): la prima visita a
  `/enti/` scarica la copia del JSON, poi la rinnova WP-Cron ogni 15 minuti; in locale WP-Cron non parte, e il
  rinnovo si lancia con `docker exec wordpress-forestas wp --allow-root cron event run forestas_entes_refresh`, o lo fa una
  pagina degli enti dopo un'ora senza rinnovi (la prima lo lascia per 2 minuti a WP-Cron, che parte senza girare).
  `/enti/` ha 9 card per pagina e a sinistra il filtro «Argomento» con i cinque tipi; `/enti/<slug>/` ha «Informazioni»,
  «Sentieri gestiti», la galleria e le date; `/enti/page/99/` e `/enti/non-esiste/` danno 404; in
  `/cerca-nel-sito/` c'è il tab «Enti» con il numero degli enti, e una ricerca con un testo trova gli enti per nome;
  nel gruppo «Enti» di un dettaglio di sentiero il nome dell'ente porta alla sua pagina;
- il titolo della scheda è «Pagina | Sardegna Sentieri», come su Drupal: il nome viene da `blogname`
  di `config/sito.json` (l'apply lo scrive), il separatore dal child. Se resta «Forestas» l'apply non è
  andato a buon fine;
- la scheda del browser ha l'icona del sito, la «S» del logo su un cerchio bianco (`config/media/icona-sito.png`,
  oc:9000): nell'HTML ci sono `<link rel="icon">` da 32 e 192 pixel e `apple-touch-icon`. Se manca, `bin/wordpress-config.sh
  applica` mostra la riga `sito: site_icon` da applicare.

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
| `ERRORE: nessun container WordPress avviato monta …/themes/forestas-child` con il container avviato | `bin/wordpress-config.sh` lanciato da un percorso diverso da quello che il container monta: per esempio da `/Volumes/WebmappDev/Sites/…` quando il compose è partito da `~/Sites/…`, che è un link simbolico alla stessa cartella; lo script confronta i percorsi, non le cartelle (10/11) | lanciarlo dal percorso del mount (`docker inspect wordpress-<APP_NAME>`, mount di `forestas-child`), o con `WPF_CONTAINER=wordpress-<APP_NAME>` |
| `ERRORE sincronizzazione da Drupal spenta in questo ambiente` | `DRUPAL_SYNC` non è `on`, o il container non ha riletto il `.env` | `.env`, poi `scripts/wordpress-up.sh` |
| `ERRORE DRUPAL_DB_USER e DRUPAL_DB_PASSWORD mancano nel .env di wp-forestas` | credenziali della copia non impostate (non hanno valori predefiniti), o container non riavviato | aggiungerle al `wp-forestas/.env`, poi `scripts/wordpress-up.sh` |
| `ERRORE un'altra sincronizzazione delle pagine è in corso` | un giro sta già girando, o ne è rimasto il blocco dopo un container fermato a metà | aspettare la fine del giro; un blocco rimasto lo toglie l'avvio del container (solo con l'init nuovo, dopo `scripts/wordpress-up.sh`) o scade dopo 2 ore |
| `AVVISO … template … non riuscirebbe` in anteprima, uscita 1, «Prima i template di Impreza: … applica --conferma» | i template delle pagine non ci sono: l'apply del passo 2 non è stato fatto o non è riuscito | `bin/wordpress-config.sh applica --conferma`, poi di nuovo il passo 3 |
| `Uscita N: errore imprevisto, il giro non è finito` con la riga `Fatal error` | errore di PHP o di WP-CLI (per esempio `WP_ADMIN_USER` che non c'è più, o una funzione di WPML cambiata) | leggere la causa e il log completo; il comando non suggerisce nulla finché non è capita |
| `MANCANTE news 4606/it galleria … Not Found` o `cURL error 28` (o `MANCANTE pagina …`) o `Service Unavailable` | il sito Drupal non serve il file | passo 6, più tardi |
| `ERRORE copia Drupal sospetta: N news …` (o `N eventi`, `N «da sapere»`) | più contenuti del solito da mettere in bozza (news 5, eventi 2, «da sapere» 5), o nella copia molti meno pubblicati che in WordPress: di solito la copia è in ricarica | riprovare dopo il reload; se su Drupal sono state tolte davvero, `pagine --accetta-bozze --conferma` |
| `/news/attivita-e-comunicazioni/` (o `/news/eventi/`, `/da-sapere/faq/`…) dà 404 | regole degli indirizzi non rigenerate dopo una scheda nuova | un giro di `pagine --conferma` le rigenera (riga `REGOLE`); a mano `wp rewrite flush` |
| `AVVISO: wp-package non ancora pubblicato, nessun download` nei log dell'init; `/cerca-nel-sito` con il solo campo di ricerca | `WP_PACKAGE_REF` vuoto in `init-wordpress.sh`: il repo GitHub del plugin non c'era quando l'immagine è stata costruita | atteso finché il repo non esiste; dopo, l'hash in `WP_PACKAGE_REF` e `scripts/wordpress-up.sh` di forestas (oc:8952) |
| Tab «Percorsi (—)» e «Questi risultati non sono disponibili» in `/cerca-nel-sito` | il plugin non raggiunge l'API Elasticsearch: shard o app id sbagliati o vuoti (Impostazioni → WP Package), o lo shard non risponde | controllare le opzioni `wmwp_shard`/`wmwp_app_id` e l'URL mostrato nella pagina delle impostazioni (oc:8952) |
| `AVVISO aggiornamento automatico dei link di WPML non trovato` | WPML aggiornato: gli hook che rendevano assoluti i link non si chiamano più così | controllare che i link dei testi inglesi restino relativi; aggiornare `wpf_wpml_senza_link_automatici()` |
| `/enti/` risponde 503 «Pagina momentaneamente non disponibile»; nel log di PHP `forestas enti: rinnovo non riuscito, codice 0 (WP_API_TOKEN mancante nell'ambiente del container)` | il token non è nel container: manca nel `wp-forestas/.env`, o il container è stato creato prima. Il 503 c'è solo su un sito che non ha mai avuto una copia degli enti (dopo il reset); con una copia le pagine restano sull'ultima | `.env`, poi `scripts/wordpress-up.sh` di `forestas`; le pagine riprovano dopo 5 minuti, o subito con `wp cron event run forestas_entes_refresh` (oc:8990) |
| nel log `forestas enti: … codice 401` o `codice 403` | 401: token diverso da quello dello shard a cui punta il sito. In locale succede dopo il reset con il token dello shard locale: il reset ha cancellato il mu-plugin `wpf-shard-locale.php`, e il sito legge il dev. 403: lo shard non ha `WP_API_TOKEN` | nel `wp-forestas/.env` il token dello shard a cui punta il sito (in locale quello del dev, o il mu-plugin rimesso con il token dello shard locale), poi `scripts/wordpress-up.sh` di `forestas`; per lo shard i passi del primo deploy di oc:8965 (`forestas/docs/howto/deploy-automatico.md`) |
| tab «Enti (—)» in `/cerca-nel-sito` solo con un testo; nel log `ricerca degli enti non riuscita, codice 503` | sullo shard manca l'indice degli enti | `docker exec php-forestas php artisan scout:import "App\Models\Ente"` (oc:8990) |
| tab «Enti (—)» per mezzo minuto; nel log una riga `ricerca degli enti non riuscita, codice … ; per 30 secondi la ricerca risponde 503 senza chiamare lo shard` | la ricerca dello shard non è disponibile (rete, 5xx, 429 del limite al minuto dello shard, 401/403 del token, risposta senza la lista `data`): per 30 secondi il sito non la richiama, e il log ha una riga per pausa. Un 4xx che dipende dal testo (400, 422…) non fa partire la pausa: il tab dice «nessun risultato», senza riga di log | se torna dopo la pausa, niente; se si ripete, il motivo è nella riga (oc:8990) |
| nel log `forestas enti: rinnovo saltato, ne gira già un altro` | un rinnovo partito mentre ne girava un altro (WP-Cron e una pagina, o `wp cron event run` lanciato in quel momento): ne gira uno alla volta, con il lucchetto `forestas_entes_refresh_lock` | niente; se il rinnovo è stato interrotto a metà, il lucchetto si riprende dopo 2 minuti, o subito con `wp option delete forestas_entes_refresh_lock` (oc:8990) |
| nel log `forestas enti: … token non mandato: lo shard è in http su un host non locale` | l'origine dello shard vista dal server è in http su un dominio: il token viaggerebbe in chiaro | l'indirizzo dello shard in https (`environment.ts` del plugin, o il filtro `wmwp_server_origin`); in http valgono solo `localhost`, `127.0.0.1`, `::1`, `host.docker.internal` e i nomi dei servizi Docker (oc:8990) |
| `/enti/` dà 404 | rewrite rules non rigenerate dopo l'aggiornamento del child | un giro di `pagine --conferma` (riga `REGOLE`) o `wp rewrite flush` |
| nel log `forestas enti: copia nuova scartata, non plausibile: …` | lo shard ha meno della metà degli enti o dei collegamenti ente-traccia della copia: su UAT l'import dopo il reset notturno è in corso | niente: la copia di prima resta e la nuova entra quando l'import finisce. Un calo che dura più di 3 ore dal primo rifiuto si accetta da solo (nel log `calo accettato`); per accettarlo subito, `wp option delete forestas_entes_copy` e poi `wp cron event run forestas_entes_refresh` (oc:8990) |
| PHPStan di `forestas` fallisce con `Unable to create file …/build/phpstan/cache/…` | cache scritta da più processi su un volume montato da macOS | rilanciare con `--debug` (un solo processo) |
