> Ticket: oc:8711

# WordPress in Docker per lo shard Forestas — piano di implementazione (repo `wp-forestas`)

> **Per chi esegue:** usare superpowers:executing-plans (o subagent-driven-development) task per
> task. **Nessun `git commit`, `git add`, `git push` né creazione di branch in autonomia**: i passi
> di commit sono istruzioni per lo sviluppatore. Le modifiche a `forestas` sono nel piano gemello
> `forestas/docs/features/8711-…/plan.md`, che parte dopo il Task 5 di questo piano.

**Obiettivo:** un ambiente Docker (PHP 8.3 + Apache, MariaDB 11.4) che porta su un WordPress
completo un passo alla volta, pensato per essere incluso dal compose di `forestas`.

**Architettura:** un `compose.yml` con due servizi (`wordpress`, `mariadb`) e due volumi nominati.
L'immagine `wordpress` contiene WP-CLI e uno script di inizializzazione lanciato dall'entrypoint
prima di Apache: lo script controlla ogni passo (core, `wp-config.php`, installazione, lingua,
plugin, tema) ed esegue solo quelli mancanti. La configurazione arriva da `wp-forestas/.env`
(caricato con `env_file`), `APP_NAME` dal `.env` di chi include il compose.

**Stack:** Docker Compose ≥ 2.24 (UAT ha 2.26.1), `php:8.3-apache`, `mariadb:11.4`, WP-CLI, bash.

**Spec:** [overview.md](overview.md) di questa cartella e l'overview gemella in `forestas`.

## Vincoli globali

- Repo **pubblico**: nessun valore reale in file tracciati. Solo `.env-example` con segnaposto.
- Container: `wordpress-${APP_NAME}` e `mariadb-${APP_NAME}`; volumi: `wordpress-${APP_NAME}` e
  `mariadb-${APP_NAME}` (nomi espliciti, servono a `scripts/wordpress-reset.sh` di `forestas`).
- Porta di WordPress pubblicata solo su `127.0.0.1`.
- Le chiavi di sicurezza di WordPress le genera `wp config create`; non stanno in nessun `.env`.
- Il compose incluso **non deve mai far fallire il parsing** del compose di `forestas`: niente
  `${VAR:?…}`, `env_file` con `required: false`. Le variabili mancanti le segnala lo script dentro
  il container.
- `blog_public = 0` all'installazione; lingua `it_IT`.
- `wp-geohub` dal `main` di `https://github.com/webmappsrl/wp-geohub`, nella cartella
  `wp-content/plugins/wp-geohub`.
- Impreza solo da `docker/themes/impreza.zip`, mai tracciato da git.
- Documentazione e commenti in italiano.

## Attenzione in review

1. **`wp-forestas/.env` mancante** (un dev che aggiorna `forestas` senza aver creato il file): il
   compose dello shard deve partire comunque; il solo container `wordpress` stampa quali variabili
   mancano ed esce. Verifica nel Task 2, passo 4.
2. **Riavvio con installazione già fatta**: nessun passo distruttivo, nessuna riscrittura di
   `wp-config.php` né della password dell'amministratore. Verifica nel Task 3, passo 5.
3. **Zip di Impreza aggiunto dopo il primo avvio**: al riavvio successivo il tema viene installato.
   Verifica nel Task 3, passo 6.
4. **GitHub non raggiungibile al primo avvio**: WordPress parte lo stesso, con un avviso; il plugin
   si installa al riavvio successivo. Verifica nel Task 3, passo 7.
5. **WordPress dietro proxy HTTPS**: con `X-Forwarded-Proto: https` nessun redirect verso `http://`.
   Verifica nel Task 4, passo 3.

---

### Task 1: Immagine PHP + Apache con WP-CLI

**File:**
- Crea: `docker/configs/php/Dockerfile`
- Crea: `docker/configs/php/vhost.conf`
- Crea: `docker/configs/php/wordpress.ini`
- Crea: `docker/scripts/entrypoint.sh`
- Crea: `docker/scripts/init-wordpress.sh` (in questo task solo lo scheletro, completato nel Task 3)

**Interfacce:**
- Produce: immagine `wm-wordpress:php8.3`, build context = root del repo; entrypoint
  `/usr/local/bin/wp-forestas-entrypoint` che lancia `/usr/local/bin/init-wordpress.sh` e poi
  `apache2-foreground`.

- [ ] **Passo 1: `docker/configs/php/Dockerfile`**

```dockerfile
# Immagine WordPress di Forestas: PHP 8.3 + Apache, estensioni richieste da WordPress e WP-CLI.
# Il core di WordPress NON sta nell'immagine: lo scarica init-wordpress.sh nel volume.
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libicu-dev libzip-dev \
        libmagickwand-dev mariadb-client curl ca-certificates unzip less \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" mysqli gd intl zip exif opcache \
    && pecl install imagick \
    && docker-php-ext-enable imagick

RUN a2enmod rewrite headers

RUN curl -fsSL -o /usr/local/bin/wp \
        https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    && chmod +x /usr/local/bin/wp

COPY docker/configs/php/vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/configs/php/wordpress.ini /usr/local/etc/php/conf.d/wordpress.ini
COPY docker/scripts/init-wordpress.sh /usr/local/bin/init-wordpress.sh
COPY docker/scripts/entrypoint.sh /usr/local/bin/wp-forestas-entrypoint
RUN chmod +x /usr/local/bin/init-wordpress.sh /usr/local/bin/wp-forestas-entrypoint

ENTRYPOINT ["wp-forestas-entrypoint"]
CMD ["apache2-foreground"]
```

- [ ] **Passo 2: `docker/configs/php/vhost.conf`**

```apache
# Virtual host interno al container. HTTPS e dominio pubblico li gestisce l'Apache dell'host
# (vedi docker/uat/), che inoltra qui con X-Forwarded-Proto.
<VirtualHost *:80>
    DocumentRoot /var/www/html

    <Directory /var/www/html>
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

- [ ] **Passo 3: `docker/configs/php/wordpress.ini`**

```ini
; Limiti adatti a un sito editoriale con immagini e a un tema pesante come Impreza
memory_limit = 256M
upload_max_filesize = 64M
post_max_size = 64M
max_execution_time = 120
```

- [ ] **Passo 4: `docker/scripts/entrypoint.sh`**

```bash
#!/bin/bash
# Porta WordPress allo stato atteso, poi avvia Apache.
# Se l'inizializzazione fallisce il container esce: il motivo è nei log
# (docker logs wordpress-<APP_NAME>).
set -e

init-wordpress.sh

exec docker-php-entrypoint "$@"
```

- [ ] **Passo 5: scheletro di `docker/scripts/init-wordpress.sh`**

```bash
#!/bin/bash
# Inizializzazione di WordPress: completata nel Task 3.
set -euo pipefail
echo "init-wordpress: scheletro"
```

- [ ] **Passo 6: build e verifica delle estensioni**

Esegui dalla root di `wp-forestas`:

```bash
docker build -f docker/configs/php/Dockerfile -t wm-wordpress:php8.3 .
docker run --rm --entrypoint php wm-wordpress:php8.3 -m | grep -E -i '^(mysqli|gd|intl|zip|imagick|exif|Zend OPcache)$'
docker run --rm --entrypoint wp wm-wordpress:php8.3 --allow-root --version
docker run --rm --entrypoint apache2ctl wm-wordpress:php8.3 -M 2>/dev/null | grep -E 'rewrite|headers'
```

Atteso: le 7 estensioni elencate, `WP-CLI 2.x`, `rewrite_module` e `headers_module`.

- [ ] **Passo 7: commit (lo esegue lo sviluppatore)**

```bash
git add docker/configs/php docker/scripts
git commit -m "feat(oc:8711): immagine PHP 8.3 + Apache con WP-CLI per WordPress"
```

---

### Task 2: compose, `.env-example`, `.gitignore`

**File:**
- Crea: `compose.yml`
- Crea: `.env-example`
- Crea: `.gitignore`
- Crea: `docker/themes/.gitkeep`

**Interfacce:**
- Consuma: l'immagine del Task 1.
- Produce: servizi `wordpress` e `mariadb`; volumi `wordpress-${APP_NAME}`, `mariadb-${APP_NAME}`;
  variabili `WP_PORT`, `WP_URL`, `WP_TITLE`, `WP_DB_NAME`, `WP_DB_USER`, `WP_DB_PASSWORD`,
  `WP_ADMIN_USER`, `WP_ADMIN_PASSWORD`, `WP_ADMIN_EMAIL`. Lo zip di Impreza è montato in
  `/opt/wp-forestas/themes/impreza.zip`.

- [ ] **Passo 1: `compose.yml`**

```yaml
# Servizi WordPress dello shard Forestas.
# Pensato per essere incluso dal compose di forestas (`include: - wp-forestas/compose.yml`):
# APP_NAME arriva dal .env di forestas, la configurazione di WordPress da wp-forestas/.env.
# Nessuna variabile è obbligatoria a livello di compose: un .env mancante non deve impedire
# l'avvio dello shard. Le variabili mancanti le segnala init-wordpress.sh.
services:
  wordpress:
    build:
      context: .
      dockerfile: docker/configs/php/Dockerfile
    image: wm-wordpress:php8.3
    container_name: "wordpress-${APP_NAME}"
    restart: unless-stopped
    env_file:
      - path: .env
        required: false
    environment:
      WP_DB_HOST: mariadb
    ports:
      - "127.0.0.1:${WP_PORT:-8090}:80"
    volumes:
      - wordpress-data:/var/www/html
      - ./docker/themes:/opt/wp-forestas/themes:ro
    depends_on:
      mariadb:
        condition: service_healthy

  mariadb:
    image: mariadb:11.4
    container_name: "mariadb-${APP_NAME}"
    restart: unless-stopped
    environment:
      MARIADB_DATABASE: ${WP_DB_NAME:-wordpress}
      MARIADB_USER: ${WP_DB_USER:-wordpress}
      MARIADB_PASSWORD: ${WP_DB_PASSWORD:-}
      MARIADB_RANDOM_ROOT_PASSWORD: "1"
    volumes:
      - mariadb-data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "healthcheck.sh", "--connect", "--innodb_initialized"]
      interval: 5s
      timeout: 5s
      retries: 30

volumes:
  wordpress-data:
    name: "wordpress-${APP_NAME}"
  mariadb-data:
    name: "mariadb-${APP_NAME}"
```

- [ ] **Passo 2: `.env-example`**

```dotenv
# Configurazione di WordPress. Copiare in .env (escluso da git) e compilare.
# Questo file lo legge solo Docker Compose: Laravel non lo vede.
# Le chiavi di sicurezza di WordPress NON vanno qui: le genera WP-CLI in wp-config.php.

# Porta su cui WordPress risponde, solo su 127.0.0.1
WP_PORT=8090
# Indirizzo pubblico del sito. In locale http://localhost:8090, su UAT https://wp.forestas.uat.maphub.it
WP_URL=http://localhost:8090
WP_TITLE=Forestas

WP_DB_NAME=wordpress
WP_DB_USER=wordpress
WP_DB_PASSWORD=cambiami

WP_ADMIN_USER=cambiami
WP_ADMIN_PASSWORD=cambiami
WP_ADMIN_EMAIL=cambiami@example.org
```

- [ ] **Passo 3: `.gitignore` e cartella dei temi**

```gitignore
# Configurazione con valori reali: mai nel repo (il repo è pubblico)
.env

# Temi commerciali (Impreza): non ridistribuibili
/docker/themes/*
!/docker/themes/.gitkeep
```

Crea `docker/themes/.gitkeep` vuoto.

- [ ] **Passo 4: verifica del compose, con e senza `.env`**

Dalla root di `wp-forestas`:

```bash
APP_NAME=prova docker compose config --quiet && echo "OK senza .env"
cp .env-example .env
APP_NAME=prova docker compose config | grep -E 'container_name|host_ip|published|name: (wordpress|mariadb)-prova'
git check-ignore .env docker/themes/impreza.zip && echo "ignorati"
git check-ignore docker/themes/.gitkeep || echo ".gitkeep tracciato"
```

Atteso: `OK senza .env`; `wordpress-prova`, `mariadb-prova`, `host_ip: 127.0.0.1`,
`published: "8090"`, volumi `wordpress-prova` e `mariadb-prova`; `.env` e lo zip ignorati,
`.gitkeep` tracciato.

- [ ] **Passo 5: commit (lo esegue lo sviluppatore)**

```bash
git add compose.yml .env-example .gitignore docker/themes/.gitkeep
git commit -m "feat(oc:8711): compose con WordPress e MariaDB su volumi nominati"
```

---

### Task 3: inizializzazione un passo alla volta

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-cartella-del-plugin)

**File:**
- Modifica: `docker/scripts/init-wordpress.sh` (sostituisce lo scheletro del Task 1)

**Interfacce:**
- Consuma: le variabili del Task 2, `WP_DB_HOST`, `/opt/wp-forestas/themes/impreza.zip`.
- Produce: WordPress installato in `/var/www/html` con `wp-geohub` attivo e, se c'era lo zip,
  Impreza attivo.

- [ ] **Passo 1: `docker/scripts/init-wordpress.sh`**

```bash
#!/bin/bash
# Porta WordPress allo stato atteso, un passo alla volta: ogni passo controlla se è già fatto
# e agisce solo se manca. Non distrugge mai un'installazione esistente, quindi è sicuro a ogni
# avvio del container, anche in produzione.
set -euo pipefail

WP_PATH=/var/www/html
WP="wp --allow-root --path=${WP_PATH}"
IMPREZA_ZIP=/opt/wp-forestas/themes/impreza.zip
GEOHUB_DIR="${WP_PATH}/wp-content/plugins/wp-geohub"
GEOHUB_TARBALL=https://github.com/webmappsrl/wp-geohub/archive/refs/heads/main.tar.gz

log() { echo "[init-wordpress] $*"; }

# 0. Configurazione: senza queste variabili non si può installare nulla
mancanti=()
for v in WP_URL WP_TITLE WP_DB_NAME WP_DB_USER WP_DB_PASSWORD WP_DB_HOST \
         WP_ADMIN_USER WP_ADMIN_PASSWORD WP_ADMIN_EMAIL; do
    [ -n "${!v:-}" ] || mancanti+=("$v")
done
if [ ${#mancanti[@]} -gt 0 ]; then
    log "ERRORE: variabili mancanti: ${mancanti[*]}"
    log "Crea wp-forestas/.env partendo da wp-forestas/.env-example e riavvia il container."
    exit 1
fi

# 1. Database raggiungibile (il healthcheck di MariaDB lo garantisce quasi sempre)
for i in $(seq 1 30); do
    mariadb-admin ping -h "$WP_DB_HOST" -u "$WP_DB_USER" -p"$WP_DB_PASSWORD" --silent && break
    log "attendo MariaDB ($i/30)"
    sleep 2
done

# 2. Core
if [ ! -f "${WP_PATH}/wp-includes/version.php" ]; then
    log "scarico il core di WordPress (it_IT)"
    $WP core download --locale=it_IT
fi

# 3. wp-config.php: le chiavi di sicurezza le genera WP-CLI
if [ ! -f "${WP_PATH}/wp-config.php" ]; then
    log "creo wp-config.php"
    $WP config create \
        --dbname="$WP_DB_NAME" --dbuser="$WP_DB_USER" --dbpass="$WP_DB_PASSWORD" \
        --dbhost="$WP_DB_HOST" --locale=it_IT --skip-check \
        --extra-php <<'PHP'
// Dietro l'Apache dell'host che termina l'HTTPS: senza questo WordPress crede di essere in
// http e va in un ciclo di redirect.
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
PHP
fi

# 4. Installazione
if ! $WP core is-installed; then
    log "installo WordPress su ${WP_URL}"
    $WP core install --url="$WP_URL" --title="$WP_TITLE" \
        --admin_user="$WP_ADMIN_USER" --admin_password="$WP_ADMIN_PASSWORD" \
        --admin_email="$WP_ADMIN_EMAIL" --skip-email
    # Sito di prova: i motori di ricerca non devono indicizzarlo. Al lancio in produzione si
    # toglie dal pannello (Impostazioni → Lettura).
    $WP option update blog_public 0
    $WP rewrite structure '/%postname%/' --hard
fi

# 5. Lingua
if ! $WP language core is-installed it_IT; then
    log "installo la lingua it_IT"
    $WP language core install it_IT
fi
if [ "$($WP option get WPLANG)" != "it_IT" ]; then
    $WP site switch-language it_IT
fi

# 6. Plugin wp-geohub (dal main del repo pubblico, nella cartella wp-geohub)
if [ ! -f "${GEOHUB_DIR}/index.php" ]; then
    log "scarico wp-geohub"
    tmp=$(mktemp -d)
    if curl -fsSL "$GEOHUB_TARBALL" | tar xz --strip-components=1 -C "$tmp"; then
        mkdir -p "$GEOHUB_DIR"
        cp -a "$tmp"/. "$GEOHUB_DIR"/
    else
        log "AVVISO: impossibile scaricare wp-geohub, riprovo al prossimo avvio"
    fi
    rm -rf "$tmp"
fi
if [ -f "${GEOHUB_DIR}/index.php" ] && ! $WP plugin is-active wp-geohub; then
    log "attivo wp-geohub"
    $WP plugin activate wp-geohub
fi

# 7. Tema Impreza (commerciale: solo se lo zip è stato messo a mano in docker/themes/)
if $WP theme list --field=name | grep -qi '^impreza$'; then
    :
elif [ -f "$IMPREZA_ZIP" ]; then
    log "installo e attivo Impreza"
    $WP theme install "$IMPREZA_ZIP" --activate
else
    log "AVVISO: docker/themes/impreza.zip non trovato, il sito usa il tema di default"
fi

# 8. Permessi: Apache gira come www-data
chown -R www-data:www-data "${WP_PATH}/wp-content"

log "WordPress pronto su ${WP_URL}"
```

- [ ] **Passo 2: ricostruisci l'immagine e avvia da zero**

Dalla root di `wp-forestas`, con `.env` copiato da `.env-example` (Task 2):

```bash
export APP_NAME=prova
docker compose up -d --build
docker compose logs -f wordpress   # attendi «WordPress pronto», poi Ctrl+C
```

- [ ] **Passo 3: verifica dello stato installato**

```bash
W="docker exec wordpress-prova wp --allow-root --path=/var/www/html"
$W core is-installed && echo installato
$W option get WPLANG          # → it_IT
$W option get blog_public     # → 0
$W plugin is-active wp-geohub && echo geohub-attivo
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8090/         # → 200
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8090/wp-login.php  # → 200
```

Atteso: avviso «impreza.zip non trovato» nei log, tutto il resto come indicato.

- [ ] **Passo 4: ricreazione del container senza perdita**

```bash
docker compose up -d --force-recreate wordpress
docker compose logs wordpress | tail -5    # nessun «installo», «scarico», «creo»
$W plugin is-active wp-geohub && echo geohub-attivo
```

- [ ] **Passo 5: il riavvio non tocca l'installazione esistente**

```bash
$W option update blogname "Titolo cambiato a mano"
docker compose restart wordpress
$W option get blogname     # → Titolo cambiato a mano
```

- [ ] **Passo 6: zip di Impreza aggiunto dopo**

Copia lo zip di Impreza in `docker/themes/impreza.zip`, poi:

```bash
docker compose restart wordpress
$W theme list --status=active --field=name   # → Impreza
```

Se lo zip non è disponibile, segnalalo allo sviluppatore e salta il passo.

- [ ] **Passo 7: GitHub non raggiungibile**

Cambia temporaneamente `GEOHUB_TARBALL` nello script con un URL inesistente
(`https://github.com/webmappsrl/wp-geohub/archive/refs/heads/non-esiste.tar.gz`), poi:

```bash
$W plugin deactivate wp-geohub
docker exec wordpress-prova rm -rf /var/www/html/wp-content/plugins/wp-geohub
docker compose up -d --build wordpress
docker compose logs wordpress | grep "AVVISO: impossibile scaricare wp-geohub"
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8090/     # → 200
```

Ripristina l'URL nello script, `docker compose up -d --build wordpress`, e verifica che il plugin
venga scaricato e attivato (`$W plugin is-active wp-geohub && echo geohub-attivo`).

- [ ] **Passo 8: pulizia**

```bash
docker compose down -v      # rimuove container e volumi di prova (wordpress-prova, mariadb-prova)
unset APP_NAME
```

- [ ] **Passo 9: commit (lo esegue lo sviluppatore)**

```bash
git add docker/scripts/init-wordpress.sh
git commit -m "feat(oc:8711): inizializzazione di WordPress un passo alla volta con WP-CLI"
```

---

### Task 4: virtual host di riferimento per UAT

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-verifica-dietro-proxy)

**File:**
- Crea: `docker/uat/wp.forestas.uat.maphub.it.conf`
- Crea: `docker/uat/wp.forestas.uat.maphub.it-le-ssl.conf`

Due file come per `forestas.uat.maphub.it` sull'host: il primo (HTTP) serve anche a ottenere il
certificato, il secondo (HTTPS) si abilita solo dopo, quando il certificato esiste.

- [ ] **Passo 1: `docker/uat/wp.forestas.uat.maphub.it.conf`**

```apache
# Riferimento per l'Apache dell'host di UAT: /etc/apache2/sites-available/
# HTTP: risponde alla challenge di Let's Encrypt, tutto il resto va in HTTPS.
<VirtualHost *:80>
    ServerName wp.forestas.uat.maphub.it

    Alias /.well-known/acme-challenge/ /var/www/letsencrypt/.well-known/acme-challenge/
    <Directory /var/www/letsencrypt/.well-known/acme-challenge/>
        Options None
        AllowOverride None
        Require all granted
    </Directory>

    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\.well-known/acme-challenge/ [NC]
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]

    ErrorLog ${APACHE_LOG_DIR}/wp-forestas.error.log
    CustomLog ${APACHE_LOG_DIR}/wp-forestas.access.log combined
</VirtualHost>
```

- [ ] **Passo 2: `docker/uat/wp.forestas.uat.maphub.it-le-ssl.conf`**

```apache
# Riferimento per l'Apache dell'host di UAT: /etc/apache2/sites-available/
# HTTPS: reverse proxy verso il container wordpress-<APP_NAME>, pubblicato su 127.0.0.1:8090
# (WP_PORT in wp-forestas/.env). Abilitare solo dopo aver ottenuto il certificato.
<IfModule mod_ssl.c>
<VirtualHost *:443>
    ServerName wp.forestas.uat.maphub.it

    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"
    ProxyPass / http://127.0.0.1:8090/
    ProxyPassReverse / http://127.0.0.1:8090/

    LimitRequestBody 0
    ProxyTimeout 300

    ErrorLog ${APACHE_LOG_DIR}/wp-forestas.error.log
    CustomLog ${APACHE_LOG_DIR}/wp-forestas.access.log combined

    SSLEngine on
    Include /etc/letsencrypt/options-ssl-apache.conf
    SSLCertificateFile /etc/letsencrypt/live/wp.forestas.uat.maphub.it/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/wp.forestas.uat.maphub.it/privkey.pem
</VirtualHost>
</IfModule>
```

- [ ] **Passo 3: verifica del comportamento dietro proxy, in locale**

Con WordPress avviato come nel Task 3 (passo 2) ma con `WP_URL=https://wp.esempio.test` nel
`.env` **prima** del primo avvio (volumi azzerati):

```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' \
  -H 'Host: wp.esempio.test' -H 'X-Forwarded-Proto: https' http://127.0.0.1:8090/
```

Atteso: `200` senza redirect. Senza l'header `X-Forwarded-Proto` atteso invece un `301` verso
`https://wp.esempio.test/`: è la prova che il passo 3 dello script funziona. Poi
`docker compose down -v` e ripristina `WP_URL` nel `.env`.

- [ ] **Passo 4: commit (lo esegue lo sviluppatore)**

```bash
git add docker/uat
git commit -m "feat(oc:8711): virtual host di riferimento per pubblicare WordPress su UAT"
```

---

### Task 5: README

**File:**
- Crea: `README.md`

- [ ] **Passo 1: `README.md`**

````markdown
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
````

- [ ] **Passo 2: commit (lo esegue lo sviluppatore)**

```bash
git add README.md
git commit -m "docs(oc:8711): README dell'ambiente WordPress"
```

- [ ] **Passo 3: checkpoint — push del repo (lo esegue lo sviluppatore)**

Il piano di `forestas` aggiunge `wp-forestas` come submodule, e un submodule richiede un commit
raggiungibile sul remoto. Prima di passare al piano di `forestas`, lo sviluppatore pubblica il
lavoro su GitHub (il repo è vuoto: va deciso il branch iniziale, `main` e `develop`, secondo le
regole del team). Annota l'hash del commit da usare come puntatore con `git rev-parse HEAD`.
