#!/usr/bin/env bash
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
# Commit fisso di wp-geohub: un sito ricreato deve avere lo stesso codice di quello di partenza. Per
# aggiornarlo: git ls-remote https://github.com/webmappsrl/wp-geohub refs/heads/main, poi l'hash qui
GEOHUB_REF=92d2ae43b7b4569f1f257bcc38d809266d4bdddd
GEOHUB_TARBALL="https://github.com/webmappsrl/wp-geohub/archive/${GEOHUB_REF}.tar.gz"
# Child theme versionato in themes/forestas-child, montato qui dal compose
CHILD_DIR="${WP_PATH}/wp-content/themes/forestas-child"
# Zip dei plugin commerciali (WPML) in docker/plugins/, esclusa da git: si copiano a mano o li crea
# bin/wordpress-config.sh zip
PLUGIN_ZIP_DIR=/opt/wp-forestas/plugins
# Configurazione versionata (config/ del repo) e script che la applicano
CONFIG_DIR=/opt/wp-forestas/config
CONFIG_LIB=/usr/local/lib/wp-forestas
# Plugin commerciali attesi: elenco unico in docker/plugins/commerciali.txt. Regole di lettura (le
# stesse di bin/wordpress-config.sh e ritratto.php): «#» apre un commento, spazi e righe vuote si ignorano
PLUGIN_COMMERCIALI=$(awk '{ sub(/#.*/, ""); gsub(/[ \t\r]/, "") } NF' "${PLUGIN_ZIP_DIR}/commerciali.txt" 2>/dev/null || true)
# UpSolution Core: Theme Options e builder di Impreza, contenuto nel tema nella sua stessa versione
USCORE_ZIP="${WP_PATH}/wp-content/themes/Impreza/common/plugins/us-core.zip"
# Attesa del database al passo 1
DB_TENTATIVI=30
DB_PAUSA=2
# Opzioni di controllo dell'apply: stessi nomi delle costanti WPF_OPZIONE_* in config/comune.php
OPZIONE_DA_FARE=wp_forestas_config_da_applicare
OPZIONE_FATTO=wp_forestas_config_applicata
# Tentativi automatici dell'apply su un sito appena installato, poi serve il comando a mano
APPLY_TENTATIVI=3

log() { echo "[init-wordpress] $*"; }

# Esegue uno degli script PHP di configurazione come amministratore. Con un sito in https imposta
# HTTPS prima che WordPress si carichi: il CSS che Impreza rigenera altrimenti avrebbe URL http://.
wp_script() {
    local extra=()
    [[ "$WP_URL" == https://* ]] && extra=(--exec='$_SERVER["HTTPS"]="on";')
    $WP "${extra[@]}" --user="$WP_ADMIN_USER" eval-file "${CONFIG_LIB}/$1"
}

# Cartella radice del contenuto di uno zip (lo slug di un tema o di un plugin), ignorando i file in radice
# e la cartella __MACOSX che aggiunge il Finder. awk legge tutto l'elenco: niente SIGPIPE con pipefail.
slug_zip() {
    unzip -Z1 "$1" 2>/dev/null | awk -F/ 'NF > 1 && $1 != "__MACOSX" && s == "" { s = $1 } END { print s }'
}

# Vero se WP_URL punta a questa macchina: lì licenze e chiavi dei servizi esterni non si applicano,
# perché il sito si registrerebbe presso i fornitori con un indirizzo locale.
url_locale() {
    local host="${WP_URL#*://}"
    host="${host%%/*}"
    host="${host%%:*}"
    [ "$host" = "localhost" ] || [ "$host" = "127.0.0.1" ]
}

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
for i in $(seq 1 "$DB_TENTATIVI"); do
    mariadb-admin ping -h "$WP_DB_HOST" -u "$WP_DB_USER" -p"$WP_DB_PASSWORD" --silent && break
    log "attendo MariaDB ($i/${DB_TENTATIVI})"
    sleep "$DB_PAUSA"
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

# 3b. Costanti che dipendono dall'ambiente (passi che non bloccano mai l'avvio)
# Le costanti seguono il .env: si scrivono quando servono e si tolgono quando il .env non le chiede più
if [ "${WP_AMBIENTE:-}" = "produzione" ]; then
    # In produzione core, temi e plugin cambiano solo dal repo e dagli zip, mai dal pannello
    $WP config set DISALLOW_FILE_MODS true --raw --type=constant \
        || log "AVVISO: impossibile impostare DISALLOW_FILE_MODS"
elif $WP config has DISALLOW_FILE_MODS 2>/dev/null; then
    log "WP_AMBIENTE non è produzione: tolgo DISALLOW_FILE_MODS"
    $WP config delete DISALLOW_FILE_MODS || log "AVVISO: impossibile togliere DISALLOW_FILE_MODS"
fi
if ! url_locale && [ -n "${WPML_SITE_KEY:-}" ]; then
    # WPML registra il sito su wpml.org con la chiave scritta come costante
    if [ "$($WP config get OTGS_INSTALLER_SITE_KEY_WPML 2>/dev/null || true)" != "$WPML_SITE_KEY" ]; then
        log "imposto la site key di WPML"
        # Il messaggio di WP-CLI riporta il valore della costante: non deve finire nei log del container
        $WP config set OTGS_INSTALLER_SITE_KEY_WPML "$WPML_SITE_KEY" --type=constant >/dev/null \
            || log "AVVISO: impossibile impostare la site key di WPML"
    fi
elif $WP config has OTGS_INSTALLER_SITE_KEY_WPML 2>/dev/null; then
    log "site key di WPML non richiesta dal .env (o indirizzo locale): la tolgo"
    $WP config delete OTGS_INSTALLER_SITE_KEY_WPML || log "AVVISO: impossibile togliere la site key di WPML"
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
    $WP rewrite structure '/%postname%/'
    # Solo un sito nato qui riceve l'apply automatico della configurazione (passo 8c): su un sito
    # esistente cancellerebbe ciò che è stato fatto dal pannello
    $WP option add "$OPZIONE_DA_FARE" 0 >/dev/null || log "AVVISO: impossibile segnare il sito come da configurare"
fi

# 4b. .htaccess: con i permalink «belli» Apache deve girare ogni indirizzo a index.php. Da WP-CLI
#     «rewrite structure --hard» non lo scrive (non sa che mod_rewrite c'è) e senza il file tutte le
#     pagine tranne la home rispondono 404. Lo scrive la stessa funzione di WordPress che usa il
#     pannello, solo se il file manca: uno esistente può avere regole aggiunte da un plugin.
if [ ! -f "${WP_PATH}/.htaccess" ] && [ -n "$($WP option get permalink_structure 2>/dev/null || true)" ]; then
    log "creo .htaccess per i permalink"
    $WP eval "require '${CONFIG_LIB}/comune.php'; exit( wpf_scrivi_htaccess() ? 0 : 1 );" \
        || log "AVVISO: impossibile creare .htaccess, riprovo al prossimo avvio"
fi

# 4c. Blocco dell'apply rimasto da un apply interrotto: gli apply girano dentro il container e muoiono
#     con lui, quindi all'avvio nessuno può essere ancora in corso. Senza, un blocco rimasto da un
#     «docker stop» farebbe fallire i tentativi dell'apply automatico per 15 minuti.
$WP eval "require '${CONFIG_LIB}/comune.php'; wpf_sblocca_apply();" \
    || log "AVVISO: impossibile togliere il blocco dell'apply"

# 5. Lingua
if ! $WP language core is-installed it_IT; then
    log "installo la lingua it_IT"
    $WP language core install it_IT
fi
if [ "$($WP language core list --status=active --field=language)" != "it_IT" ]; then
    $WP site switch-language it_IT
fi

# 6. Plugin wp-geohub (dal repo pubblico al commit GEOHUB_REF, nella cartella wm-package)
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

# 7. Tema Impreza (commerciale: solo se c'è lo zip in docker/themes/, copiato a mano o creato con
#    bin/wordpress-config.sh zip)
# Elenco letto per intero (tr consuma tutto): con pipefail un «| grep -q» può fallire per SIGPIPE
temi=$'\n'"$($WP theme list --field=name | tr '[:upper:]' '[:lower:]' || true)"$'\n'
if [[ "$temi" == *$'\n'impreza$'\n'* ]]; then
    :
elif [ -f "$IMPREZA_ZIP" ]; then
    # Il child dichiara «Template: Impreza»: con una cartella radice diversa resterebbe senza padre
    if [ "$(slug_zip "$IMPREZA_ZIP" || true)" = "Impreza" ]; then
        log "installo e attivo Impreza"
        $WP theme install "$IMPREZA_ZIP" --activate || log "AVVISO: installazione di Impreza non riuscita"
    else
        log "AVVISO: impreza.zip non ha la cartella radice «Impreza/»: non lo installo"
    fi
else
    log "AVVISO: docker/themes/impreza.zip non trovato, il sito usa il tema di default"
fi

# 7b. UpSolution Core: senza, Impreza non ha Theme Options né builder. Si installa se manca e si
#     riattiva se è spento (anche se un'attivazione precedente non era riuscita)
if [ -f "$USCORE_ZIP" ] && ! $WP plugin is-installed us-core; then
    log "installo e attivo UpSolution Core"
    $WP plugin install "$USCORE_ZIP" --activate || log "AVVISO: installazione di UpSolution Core non riuscita"
elif $WP plugin is-installed us-core && ! $WP plugin is-active us-core; then
    log "UpSolution Core è installato ma spento: lo attivo"
    $WP plugin activate us-core || log "AVVISO: attivazione di UpSolution Core non riuscita"
fi

# 7c. Plugin commerciali dagli zip in docker/plugins/. Lo slug è la cartella contenuta nello zip, non
#     il nome del file (uno zip scaricato può chiamarsi sitepress-multilingual-cms.5.1.0.zip). Un plugin
#     già installato non si reinstalla; se è spento lo si segnala senza riattivarlo, perché può essere
#     stato disattivato apposta dal pannello.
trovati=" "
for zip in "$PLUGIN_ZIP_DIR"/*.zip; do
    [ -f "$zip" ] || continue
    # «|| true»: con pipefail uno zip rovinato farebbe uscire lo script, e il container ripartirebbe
    # all'infinito; così cade nel controllo qui sotto
    slug=$(slug_zip "$zip" || true)
    [ -n "$slug" ] || { log "AVVISO: $(basename "$zip") non è uno zip leggibile"; continue; }
    trovati="${trovati}${slug} "
    if ! $WP plugin is-installed "$slug"; then
        log "installo e attivo il plugin ${slug}"
        $WP plugin install "$zip" --activate || log "AVVISO: installazione del plugin ${slug} non riuscita"
    elif ! $WP plugin is-active "$slug"; then
        log "AVVISO: il plugin ${slug} è installato ma spento: se non è voluto, attivalo dal pannello"
    fi
done
for slug in $PLUGIN_COMMERCIALI; do
    if [[ "$trovati" != *" ${slug} "* ]] && ! $WP plugin is-installed "$slug"; then
        log "AVVISO: nessuno zip di ${slug} in docker/plugins/, il plugin non è installato"
    fi
done

# 8. Child theme forestas-child (montato dal repo): si attiva solo al posto di Impreza o di un
#    tema di default, così un tema scelto a mano dal pannello non viene mai sostituito
if [ -f "${CHILD_DIR}/style.css" ] && $WP theme is-installed Impreza; then
    attivo=$($WP theme list --status=active --field=name || true)
    case "$attivo" in
        forestas-child) ;;
        Impreza|twentytwenty*)
            log "attivo il child theme forestas-child"
            $WP theme activate forestas-child || log "AVVISO: attivazione di forestas-child non riuscita"
            ;;
        *)
            log "AVVISO: il tema attivo è «${attivo}», non lo sostituisco con forestas-child"
            ;;
    esac
fi

# 8b. Licenza di Impreza dal segreto del .env, solo su un indirizzo non locale. Si riattiva se manca o
#     se il segreto del .env è cambiato (come la site key di WPML al passo 3b, che segue il .env)
if url_locale; then
    log "indirizzo locale: licenza di Impreza non applicata"
elif [ -z "${IMPREZA_LICENSE_SECRET:-}" ]; then
    log "IMPREZA_LICENSE_SECRET vuoto: licenza di Impreza non gestita dallo script"
elif [ "$($WP option get us_license_secret 2>/dev/null || true)" != "$IMPREZA_LICENSE_SECRET" ]; then
    log "attivo la licenza di Impreza con il segreto del .env"
    wp_script licenza-impreza.php || log "AVVISO: licenza di Impreza non attivata, riprovo al prossimo avvio"
fi

# 8c. Configurazione versionata: si applica da sola solo su un sito installato da questo script
#     (opzione wp_forestas_config_da_applicare, scritta al passo 4), al massimo APPLY_TENTATIVI volte.
#     Su un sito esistente, e dopo, solo con bin/wordpress-config.sh apply.
tentativi=$($WP option get "$OPZIONE_DA_FARE" 2>/dev/null || true)
# Un valore non numerico (opzione cambiata a mano) farebbe fallire il confronto e uscire lo script
if [ -n "$tentativi" ] && [[ ! "$tentativi" =~ ^[0-9]+$ ]]; then
    log "AVVISO: ${OPZIONE_DA_FARE} vale «${tentativi}», non è un numero: riparto da 0 tentativi"
    tentativi=0
fi
config_presente=false
ls "$CONFIG_DIR"/*.json >/dev/null 2>&1 && config_presente=true
if [ -n "$tentativi" ] && $config_presente; then
    if [ "$tentativi" -ge "$APPLY_TENTATIVI" ]; then
        log "AVVISO: configurazione non applicata dopo ${APPLY_TENTATIVI} tentativi: lanciala a mano con bin/wordpress-config.sh apply"
        $WP option delete "$OPZIONE_DA_FARE" >/dev/null || true
    else
        $WP option update "$OPZIONE_DA_FARE" $((tentativi + 1)) >/dev/null || true
        log "applico la configurazione di config/ (tentativo $((tentativi + 1)) di ${APPLY_TENTATIVI})"
        WPF_AUTOMATICO=1 wp_script apply.php || log "AVVISO: configurazione non applicata del tutto, riprovo al prossimo avvio"
    fi
elif $config_presente && [ -z "$($WP option get "$OPZIONE_FATTO" 2>/dev/null || true)" ]; then
    log "config/ non applicata a questo sito: non si applica da sola, vedi bin/wordpress-config.sh apply"
fi

# 9. Permessi: Apache gira come www-data, e anche il core deve essere suo, altrimenti WordPress
#    non riconosce di poter scrivere e chiede le credenziali FTP per installare plugin e temi.
#    Il child theme resta escluso: è una cartella del repo montata dall'host.
#    -h cambia il proprietario di un link simbolico e non del file a cui punta. Un errore qui non
#    blocca l'avvio: Apache parte e il proprietario si ritenta al riavvio.
find "$WP_PATH" -path "$CHILD_DIR" -prune -o ! -user www-data -exec chown -h www-data:www-data {} + \
    || log "AVVISO: non tutti i file di WordPress sono passati a www-data, riprovo al prossimo avvio"

log "WordPress pronto su ${WP_URL}"
