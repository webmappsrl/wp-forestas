> Ticket: oc:8711

# Container Docker custom con lo stack completo per WordPress e installazione iniziale

## Cosa cambia

Nasce il repo `wp-forestas`, che contiene l'ambiente Docker di un WordPress che fa parte dello
shard `forestas`: gira sulla stessa macchina, parte con lo stesso `docker compose up` e si aggiorna
insieme allo shard. Il repo contiene:

- un'immagine custom PHP + Apache con le estensioni richieste da WordPress e WP-CLI;
- MariaDB in un container separato;
- un file compose con questi due servizi, pensato per essere incluso dal compose di `forestas`
  (vedi l'overview gemella in `forestas`);
- uno script di inizializzazione che porta WordPress allo stato atteso un passo alla volta: core,
  configurazione, installazione in italiano, utente amministratore, plugin `wp-geohub` e, se il
  pacchetto è presente, tema Impreza;
- la configurazione che serve per pubblicare WordPress su UAT, all'indirizzo
  `wp.forestas.uat.maphub.it`, dietro l'Apache dell'host.

`wp-forestas` entra in `forestas` come submodule, come già `wm-package`.

Oggi, su UAT, WordPress verrà cancellato e ricreato ogni giorno a partire dai dati di Drupal o
dalle API di `forestas`, come il resto dello shard; in produzione diventerà permanente. L'ambiente
regge entrambi i modi: i dati stanno su volumi, e un comando li azzera e ricrea WordPress da zero.

## Perché

Il sito Drupal di Sardegna Sentieri verrà dismesso. La parte editoriale che WordPress gestisce
bene — news e post — passa a un WordPress dedicato, che riceve sentieri e POI dallo shard
`forestas` tramite il plugin `wp-geohub` (header: «Plugin to sync tracks and POIs from external
APIs to WordPress and display them via shortcodes»). WordPress diventa parte dello shard: la
macchina che tiene su `forestas` tiene su anche lui, in locale come su UAT, dove il sito va reso
visibile per essere visionato.

## Requisiti

- [ ] Immagine custom basata su `php:8.3-apache`, con `mod_rewrite` attivo (permalink di WordPress
      tramite `.htaccess`), le estensioni `mysqli`, `gd`, `intl`, `zip`, `imagick`, `exif`, `opcache`
      e WP-CLI installato
- [ ] MariaDB 11 LTS in un container separato
- [ ] Due volumi nominati: l'intera cartella di WordPress nel container (`/var/www/html`: core,
      `wp-config.php`, plugin, temi, lingue, uploads) e i dati di MariaDB. Una ricostruzione del
      container non perde nulla
- [ ] Container nominati `wordpress-${APP_NAME}` e `mariadb-${APP_NAME}`, con `APP_NAME` dello shard:
      nessuna collisione con i container di `forestas` (`php-${APP_NAME}` e gli altri), e su UAT
      diventano `wordpress-forestasuat` e `mariadb-forestasuat`
- [ ] Il compose di `wp-forestas` funziona incluso da quello di `forestas`: i container condividono
      la rete dello shard e le API di `forestas` rispondono dal container WordPress per nome di
      container
- [ ] La configurazione di WordPress (URL del sito, porta, credenziali del database, utente
      amministratore) sta in un `.env` di `wp-forestas`, escluso da git e caricato dal servizio con
      `env_file`; `APP_NAME` arriva dal `.env` di `forestas`. Laravel non legge mai il `.env` di
      `wp-forestas`
- [ ] `.env-example` di `wp-forestas` con soli valori segnaposto
- [ ] Le chiavi di sicurezza di WordPress non passano da nessun `.env`: le genera WP-CLI
      (`wp config create`) in `wp-config.php`, che sta sul volume
- [ ] Script di inizializzazione che controlla un passo alla volta — core presente, `wp-config.php`
      presente, WordPress installato, lingua `it_IT`, `wp-geohub` presente e attivo, Impreza
      installato — ed esegue solo i passi mancanti. Non distrugge mai un'installazione esistente
- [ ] All'installazione WordPress è in lingua `it_IT`, con utente amministratore dal `.env`, e con
      «Scoraggia i motori di ricerca dall'indicizzare questo sito» attivo (`blog_public = 0`)
- [ ] Lo script installa e attiva il plugin `wp-geohub` dal `main` del repo pubblico
      `webmappsrl/wp-geohub`, nella cartella `wp-content/plugins/wm-package` (nome imposto dal plugin, vedi [notes.md](notes.md#task-3-cartella-del-plugin))
- [ ] Lo script installa e attiva il tema Impreza da `docker/themes/impreza.zip` se il file c'è e il
      tema non è ancora installato; se manca stampa un avviso chiaro e prosegue senza errori.
      Uno zip aggiunto dopo viene installato al riavvio successivo
- [ ] `docker/themes/*.zip` escluso da git: il tema commerciale non entra nel repo
- [ ] Il container WordPress espone la sua porta solo su `127.0.0.1`: dall'esterno si raggiunge solo
      attraverso l'Apache dell'host
- [ ] WordPress funziona dietro un reverse proxy HTTPS: riconosce `X-Forwarded-Proto` e non entra in
      un ciclo di redirect
- [ ] Virtual host Apache per l'host di UAT (`wp.forestas.uat.maphub.it`, HTTP con redirect a HTTPS e
      HTTPS con certificato Let's Encrypt) versionato nel repo come file di riferimento
- [ ] README con i passi per avviare l'ambiente da zero, dove mettere lo zip di Impreza, come
      attivarne la licenza dal pannello, e il promemoria di togliere `blog_public = 0` al lancio in
      produzione
- [ ] Verifica in locale: da `forestas` appena aggiornato col submodule e i due `.env` compilati, un
      solo `docker compose up` avvia shard e WordPress; il sito risponde all'URL configurato, il
      pannello è accessibile con l'utente configurato, `wp-geohub` è attivo, Impreza è attivo se lo
      zip era presente, dal container WordPress le API di `forestas` rispondono; dopo il comando
      «azzera e ricrea» si ottiene di nuovo lo stesso stato
- [ ] Verifica su UAT: `https://wp.forestas.uat.maphub.it` risponde con certificato valido e mostra il
      sito, il pannello è accessibile

## Rischi

- **Il repo `wp-forestas` è pubblico.** Una credenziale finita in un file tracciato è esposta a
  chiunque. Mitigazione: valori reali solo nel `.env` di `wp-forestas`, escluso da git;
  `.env-example` con soli segnaposto; chiavi di WordPress solo in `wp-config.php`, sul volume;
  controllo del diff prima di ogni commit.
- **Impreza è un tema commerciale** (UpSolution, ThemeForest): non è su wordpress.org e non si può
  ridistribuire. Mitigazione: lo zip resta fuori dal repo, in una cartella esclusa da git, e lo
  script non fallisce se manca; la licenza si attiva a mano dal pannello. Su UAT lo zip va copiato
  a mano sulla macchina.
- **Ricreazione dei container** (oc:8711, challenge): con il solo `uploads` su volume, una
  ricostruzione avrebbe perso core, plugin, tema e lingua, e uno script che controlla solo «WordPress
  installato» non li avrebbe rimessi. Mitigazione: volume sull'intera cartella di WordPress e script
  che controlla un passo alla volta.
- **Caratteri speciali nelle chiavi** (oc:8711, challenge): le chiavi del generatore di WordPress
  contengono `$`, `#` e apici, che Compose e Laravel interpretano. Mitigazione: chiavi generate da
  WP-CLI, configurazione di WordPress in un `.env` che Laravel non legge.
- **Indicizzazione di UAT** (oc:8711, challenge): un WordPress pubblico di prova finirebbe nei motori
  di ricerca. Mitigazione: `blog_public = 0` all'installazione.
- **`wp-geohub` non ha versioni**: si installa dal `main`, quindi ogni ricreazione prende il codice
  del momento. Rischio accettato.
- **Su UAT si scrive solo seguendo la procedura del team.** DNS, virtual host, certificato e `.env`
  di UAT non si applicano dal codice: il repo li descrive, il team li applica a mano (vedi l'overview
  gemella in `forestas`).

## Out of scope

- Ciclo giornaliero di cancellazione e ricreazione e import dei contenuti (news, post) da Drupal o
  dalle API di `forestas`: ticket successivo, che si appoggerà al comando «azzera e ricrea».
- Chiamate del plugin `wp-geohub` allo shard locale: oggi il plugin legge gli indirizzi degli shard
  dalla lista pubblica di `wm-types`.
- Produzione: oggi `forestas` ha solo UAT.
- Sviluppo o modifica del plugin `wp-geohub` e configurazione della sincronizzazione con lo shard
  (endpoint, frequenza, mappatura dei campi).
- Personalizzazione del tema Impreza e dei contenuti del sito.

## Moduli toccati

Repo `wp-forestas` (le modifiche a `forestas` sono nell'overview gemella):

- `compose.yml` — servizi `wordpress` (PHP + Apache) e `mariadb`, volumi, pensati per l'`include` da
  `forestas`
- `docker/configs/php/Dockerfile` — immagine PHP + Apache custom con WP-CLI
- `docker/configs/php/vhost.conf` — virtual host Apache dentro il container (`AllowOverride All`)
- `docker/scripts/init-wordpress.sh` — inizializzazione un passo alla volta con WP-CLI
- `docker/themes/` — cartella per lo zip di Impreza, con il contenuto escluso da git
- `docker/uat/wp.forestas.uat.maphub.it.conf` — virtual host di riferimento per l'Apache dell'host di UAT
- `.env-example`, `.gitignore`
- `README.md`
