#!/bin/bash
# Porta WordPress allo stato atteso, un passo alla volta: ogni passo controlla se è già fatto
# e agisce solo se manca. Non distrugge mai un'installazione esistente, quindi è sicuro a ogni
# avvio del container, anche in produzione.
set -euo pipefail

WP_PATH=/var/www/html
WP="wp --allow-root --path=${WP_PATH}"
IMPREZA_ZIP=/opt/wp-forestas/themes/impreza.zip
# Il plugin si chiama «WM Package» e cerca i propri file in wp-content/plugins/wm-package
# (percorso scritto fisso in functions/imports.php): la cartella deve avere questo nome.
GEOHUB_DIR="${WP_PATH}/wp-content/plugins/wm-package"
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
if [ "$($WP language core list --status=active --field=language)" != "it_IT" ]; then
    $WP site switch-language it_IT
fi

# 6. Plugin wp-geohub (dal main del repo pubblico, nella cartella wm-package)
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
if [ -f "${GEOHUB_DIR}/index.php" ] && ! $WP plugin is-active wm-package; then
    log "attivo wp-geohub"
    $WP plugin activate wm-package
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
