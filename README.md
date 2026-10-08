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
| `docker/plugins/` | zip dei plugin commerciali (esclusi da git); l'elenco è in `commerciali.txt`, versionato |
| `config/` | configurazione versionata del sito: Theme Options, header, footer, Home, menu, WPML |
| `docker/scripts/config/` | export, apply, ritratto e licenza Impreza, eseguiti con `wp eval-file`; montati dal compose |
| `bin/wordpress-config.sh` | comando sull'host per export, apply, ritratto e zip |
| `themes/forestas-child/` | child theme di Impreza, montato in `wp-content/themes/forestas-child` |
| `docker/uat/` | virtual host di riferimento per l'Apache dell'host di UAT |

## Avvio dentro forestas

1. In `forestas`: `git submodule update --init --recursive`.
2. `cp wp-forestas/.env-example wp-forestas/.env` e compila i valori.
3. Copia gli zip commerciali, che non stanno in git: `impreza.zip` in `docker/themes/`,
   `sitepress-multilingual-cms.zip` e `wpml-string-translation.zip` in `docker/plugins/`. La copia di
   riferimento, insieme al `.env` con le chiavi, sta nella cartella condivisa del team
   (`Siti Webmapp/Wordpress/Forestas/`): se il server si perde, sono le uniche cose che il repo non
   può ricreare.
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
database raggiungibile, core, `wp-config.php` e costanti d'ambiente, installazione, `.htaccess` dei
permalink, lingua `it_IT`, plugin `wp-geohub` (a un commit fisso), tema Impreza, UpSolution Core,
plugin commerciali, child theme `forestas-child`, licenza Impreza, configurazione di `config/`,
proprietario dei file. Esegue solo i passi che mancano e non cancella mai contenuti né
configurazione di un'installazione esistente. Su un sito esistente agisce solo così: allinea alle
voci del `.env` le costanti di `wp-config.php` (`DISALLOW_FILE_MODS`, site key di WPML) e la licenza
di Impreza, crea `.htaccess` se manca, toglie il blocco lasciato da un apply interrotto, riattiva
UpSolution Core se è spento e installa ciò che manca.

`init-wordpress.sh` sta nell'immagine: dopo una sua modifica va ricostruita (`scripts/wordpress-up.sh`
di `forestas`). Gli script PHP della configurazione invece sono montati dal repo e valgono subito.

- Le **chiavi di sicurezza** le genera WP-CLI in `wp-config.php`, che sta nel volume: non vanno nel
  `.env`.
- I valori del `.env` (URL, utente amministratore) contano **solo alla prima installazione**: per
  cambiarli dopo si usa il pannello o WP-CLI.
- `wp-geohub` si installa nella cartella `wp-content/plugins/wm-package`: il plugin si chiama «WM
  Package» e cerca i propri file in quel percorso, quindi la cartella non va rinominata.
- `wp-geohub` si scarica dal repo pubblico a un commit fisso (`GEOHUB_REF` in `init-wordpress.sh`,
  da aggiornare a mano); se GitHub non risponde il sito parte lo stesso e il plugin arriva al
  riavvio successivo.
- **Impreza** è un tema commerciale: lo zip si scarica dall'account Webmapp su ThemeForest e non va
  mai committato. Se lo zip manca il sito usa il tema di default; se lo aggiungi dopo, si installa
  al riavvio successivo. La **licenza** si riattiva da sola all'avvio con `IMPREZA_LICENSE_SECRET`
  del `.env` (vedi sotto, licenze e chiavi); il segreto si ottiene la prima volta attivando il tema dal
  pannello, in Impreza → Attivazione, e si legge poi dall'opzione `us_license_secret`.
- Il **child theme** `forestas-child` è codice del repo, in `themes/forestas-child/`: il compose lo
  monta dentro WordPress, quindi una modifica nel repo è subito visibile sul sito. Lo script lo
  attiva solo se Impreza è installato e il tema attivo è Impreza o un tema di default: un tema
  scelto a mano dal pannello non viene sostituito. Come ci si lavora: [Lavorare sul child theme](#lavorare-sul-child-theme).
- Lo slug di un plugin si prende dalla cartella contenuta nel suo zip in `docker/plugins/`, non dal
  nome del file. Un plugin già installato non si reinstalla; se è spento lo si segnala nei log senza
  riattivarlo, perché può essere stato disattivato apposta. UpSolution Core invece si riattiva sempre:
  senza, Impreza non ha Theme Options.
- **Licenze e chiavi** stanno nel `.env` (`WPML_SITE_KEY`, `IMPREZA_LICENSE_SECRET`,
  `IMPREZA_MAINTENANCE_KEY`) e si applicano **solo se `WP_URL` non è `localhost` o `127.0.0.1`**
  (le costanti in `wp-config.php` si tolgono quando il `.env` non le chiede più): un
  WordPress locale non deve registrarsi presso WPML e UpSolution con l'identità di UAT. In locale
  Impreza e WPML funzionano lo stesso, senza aggiornamenti e servizi cloud. WPML si registra con la
  costante `OTGS_INSTALLER_SITE_KEY_WPML`; Impreza ripete la chiamata di attivazione che fa il tema
  e salva ciò che risponde UpSolution.
- Con una licenza Impreza di **sviluppo** il tema tiene sempre accesa la **modalità manutenzione**
  (su UAT la pagina di manutenzione è la Home): in produzione serve un'attivazione come sito di
  produzione.
- Con `WP_AMBIENTE=produzione` lo script scrive `DISALLOW_FILE_MODS`: in produzione core, temi e
  plugin non si modificano dal pannello.
- Tutti i file di WordPress, core compreso, appartengono a `www-data`: se il core restasse di root,
  WordPress chiederebbe le credenziali FTP per installare plugin e temi dal pannello.

## Lavorare sul child theme

La cartella `themes/forestas-child/` del repo e `wp-content/themes/forestas-child` dentro WordPress
sono la stessa cartella: ogni file modificato, aggiunto o creato lì, sottocartelle comprese, compare
in `git status` di questo repo.

- **Si lavora solo in locale**, poi commit, push, aggiornamento del puntatore in `forestas` e pull
  sul server. Una modifica fatta dal pannello di UAT finisce nel checkout del repo sul server, fuori
  da git, e il pull successivo può andare in conflitto.
- **Anche ciò che scrive WordPress finisce nel repo**: l'editor dei temi, Child Theme Configurator o
  una funzione di Impreza che salva file nel tema attivo scrivono nella cartella del repo. Prima del
  commit controlla `git status` per non includere file generati.
- **CSS e PHP della grafica** vanno nei file del child: sono già nel repo e arrivano su UAT con il pull,
  senza passare dall'export di `config/`, che riguarda solo ciò che sta nel database.
- **Le immagini della grafica** (logo, icone, sfondi del tema) vanno dentro la cartella del child e si
  richiamano dal CSS o dal PHP. Ciò che si carica dalla Libreria media va in `wp-content/uploads`,
  che sta nel volume e non nel repo.
- **Restano fuori dal repo** contenuti e uploads. Theme Options, header, footer, Home, menu e WPML
  non stanno nel child ma in `config/`: vedi [Configurazione versionata](#configurazione-versionata).
- A ogni modifica del CSS alza la `Version` nell'intestazione di `style.css` (1.0.1 per un ritocco,
  1.1.0 per una sezione nuova): finisce nell'URL del file (`style.css?ver=1.0.1`) e i browser
  scaricano quello nuovo. Impreza userebbe la propria versione: è `functions.php` del child a
  sostituirla.

## Configurazione versionata

Theme Options di Impreza, header e footer, Home, menu (con le traduzioni WPML), lingue e impostazioni
di WPML, titolo, permalink e home statica stanno in `config/`, così un sito ricreato da zero torna
identico. Restano fuori contenuti editoriali, contenuti di esempio e uploads.

```bash
bin/wordpress-config.sh export                  # dal sito a config/
bin/wordpress-config.sh apply                   # cosa cambierebbe, senza scrivere
bin/wordpress-config.sh apply --conferma        # backup in backup/<data-ora>/, poi applica config/
bin/wordpress-config.sh apply --da DIR [--conferma]   # come sopra, da un'altra cartella (un backup)
bin/wordpress-config.sh ritratto                # stato del sito, da confrontare prima e dopo un reset
bin/wordpress-config.sh zip                     # zip di Impreza e dei plugin commerciali installati
```

Il comando trova da solo il container che monta il child da questa cartella; se ce n'è più di uno,
`WPF_CONTAINER=<nome>`.

- **La configurazione si fa in locale**, come il codice del child: cambi dal pannello di
  `localhost`, `export`, controlli `git diff config/`, commit, push, puntatore del submodule in
  `forestas`, pull su UAT, poi `apply` (che mostra le differenze) e `apply --conferma`. Se qualcosa si
  cambia comunque dal pannello di UAT, l'export lì va fatto fuori dal repo
  (`export --in /tmp/config`) e portato in locale: altrimenti il successivo `apply --conferma` riporta
  header, footer, menu e Theme Options a quelli del repo e la modifica si perde (resta solo nel backup
  dell'apply).
- **Nessun segreto nei file**: le opzioni con `key`, `secret`, `token`, `password` o `api` fra le
  parole del nome (o forme attaccate come `apikey`, `licensekey`) diventano `@segreto` e l'export le
  elenca; quelle che servono vanno nel `.env` (oggi `IMPREZA_MAINTENANCE_KEY`). In più l'export si
  ferma senza scrivere nulla se un valore ha la forma di una chiave nota (Google, Stripe, GitHub,
  AWS, Slack, chiave privata), sotto qualsiasi nome e anche dentro il contenuto di un post. Prima del
  commit, `git diff config/` resta l'ultimo controllo: il repo è pubblico.
- Nei file l'URL del sito è `@url_sito` (`@url_sito_json` dentro il JSON del builder,
  `@url_sito_urlenc` dove è codificato dentro un link) e i riferimenti
  ai post sono `@chiave:<nome>`: header, footer, Home e menu si riconoscono dal metadato
  `_wp_forestas_chiave`, non da titolo o slug, quindi rinominarli dal pannello non crea duplicati.
- **Cosa finisce in `config/`**: tutti gli header, i Page Block, i Content Template e i Grid Layout di
  Impreza, **anche in bozza** (una prova va cancellata o cestinata prima dell'export); le pagine a cui
  puntano le Theme Options e le impostazioni del sito, come la Home; tutti i menu con le loro
  traduzioni e le impostazioni di Impreza sulle singole voci (mega menu, voce come pulsante); le
  traduzioni dei testi delle opzioni fatte con String Translation (per esempio il messaggio dei
  cookie di Impreza in inglese). Le altre pagine e gli articoli sono contenuti, non configurazione.
- Una **voce di menu verso una pagina non esportata** (una pagina normale come «Chi siamo») si salva
  come link relativo (`/chi-siamo/`): sul sito di destinazione funziona solo se lì c'è una pagina con
  lo stesso indirizzo.
- **«Duplica» di Impreza** copia anche la chiave stabile: l'export assegna alla copia una chiave sua e
  lo segnala; l'originale tiene la sua.
- Restano fuori da `config/` anche lo stato dei plugin: i segni di migrazione di WPML e la modalità
  manutenzione di Impreza, che su UAT accende la licenza di sviluppo. Restano fuori anche gli stili
  i metadati che UpSolution Core e WPML ricavano da sé salvando un post (stili degli elementi del
  builder, filtri, allegati usati): l'apply salva il post e li ricalcolano loro.
- **Le immagini della Libreria media non stanno in `config/`**: l'export segnala le Theme Options di
  tipo immagine che puntano a un allegato (icona del sito, sfondi…) e i post del builder che usano
  immagini; l'apply per le Theme Options tiene il valore del sito di destinazione. L'immagine va
  caricata dal pannello, oppure messa nel child (vedi «Lavorare sul child theme»). Lo stesso vale
  per un'immagine di sfondo di un mega menu.
- **Il titolo del sito è quello di `config/sito.json`**: `WP_TITLE` del `.env` vale solo
  all'installazione, poi l'apply lo sostituisce con `blogname` del repo.
- `config/versioni.json` registra le versioni di Impreza, UpSolution Core e dei plugin commerciali
  da cui è stato fatto l'export; l'apply avvisa se il sito ne ha altre. L'apply automatico di un sito
  appena installato, che nessuno guarda, con una versione principale diversa (Impreza 9 contro 10)
  non applica: si guardano le differenze e si lancia a mano.
- Il backup di `apply --conferma` è un export in sola lettura in `backup/<data-ora>/`, senza segreti;
  si rimette con `apply --da backup/<data-ora> --conferma`. Impreza conserva anche un suo backup delle
  Theme Options, ripristinabile dal pannello. Un apply che riesce solo in parte termina con errore e
  dice quali passi mancano.
- Due apply sullo stesso sito non partono insieme (per esempio quello automatico dell'avvio e uno
  lanciato a mano): il secondo si ferma e va rilanciato.
- Se l'apply cambia i permalink scrive anche `.htaccess`, come il pannello.
- Per aggiungere un plugin commerciale: il suo slug in `docker/plugins/commerciali.txt`, lo zip
  `<slug>.zip` nella stessa cartella. L'init lo installa, `zip` lo rigenera e il ritratto ne mostra la
  versione.
- **Aggiornamenti di Impreza e WPML**: dal pannello di UAT, dove ci sono le licenze. Subito dopo, su
  UAT, `bin/wordpress-config.sh zip` rigenera gli zip nelle cartelle escluse da git; copiali in locale
  e nella cartella condivisa. Altrimenti un sito ricreato tornerebbe alle versioni vecchie.
- Con «salva i Google Fonts in locale» attivo nelle Theme Options, l'apply scarica i font sul sito:
  Impreza lo fa da sé solo aprendo la pagina delle Theme Options.

## Messa in opera su UAT

1. Copia sull'host gli zip in `docker/themes/` e `docker/plugins/` e aggiungi al `wp-forestas/.env`
   `WPML_SITE_KEY`, `IMPREZA_LICENSE_SECRET` e, se serve, `IMPREZA_MAINTENANCE_KEY`.
2. Aggiornamento abituale di `forestas`, poi `scripts/wordpress-up.sh` sull'host.
3. Disattiva Child Theme Configurator: il child ora arriva dal repo.
4. `bin/wordpress-config.sh apply` per vedere le differenze, poi `apply --conferma` se sono quelle
   attese. Su un sito che esisteva prima di `config/` l'init non applica nulla da solo, e header,
   footer e Home già presenti vengono **ricollegati** («senza chiave → ricollegato»), non duplicati.
   In quella prima anteprima possono comparire anche «voci diverse» nel menu, perché le voci puntano
   a post non ancora ricollegati, e qualche avviso «riferimento non risolto»: con `--conferma` i post
   si ricollegano prima, e un riferimento che resta senza post lascia il valore del sito.

## Indicizzazione

All'installazione è attiva l'opzione «Scoraggia i motori di ricerca dall'indicizzare questo sito».
**Al lancio in produzione va tolta** da Impostazioni → Lettura: lo script non la reimposta più,
perché l'installazione c'è già.

## Comandi utili

```bash
docker exec -it wordpress-<APP_NAME> wp --allow-root --path=/var/www/html <comando>
docker logs wordpress-<APP_NAME>
```
