#!/usr/bin/env bash
# Configurazione del WordPress di Forestas, dall'host (oc:8717). Uso: vedi uso() qui sotto.
#
# Il container è quello avviato che monta il child theme da questa cartella; con più WordPress che la
# montano si sceglie con WPF_CONTAINER=<nome>. La configurazione si fa in locale: su UAT un export va
# fatto con --in, fuori dal repo, per non lasciare file fuori da git nel checkout del server.
set -euo pipefail

REPO=$(cd "$(dirname "$0")/.." && pwd -P)
# Stessi percorsi di docker/scripts/init-wordpress.sh e del compose
WP_PATH=/var/www/html
CHILD_MOUNT="${WP_PATH}/wp-content/themes/forestas-child"
CONFIG_LIB=/usr/local/lib/wp-forestas
# Plugin commerciali: elenco unico in docker/plugins/commerciali.txt
# (stesse regole di init-wordpress.sh: «#» apre un commento, spazi e righe vuote si ignorano)
PLUGIN_COMMERCIALI=$(awk '{ sub(/#.*/, ""); gsub(/[ \t\r]/, "") } NF' "${REPO}/docker/plugins/commerciali.txt" 2>/dev/null || true)
# Attesa massima della home nel ritratto, in secondi
CURL_ATTESA=60

fail() { echo "ERRORE: $*" >&2; exit 1; }

uso() {
    cat <<'TESTO'
Configurazione del WordPress di Forestas, dall'host.

  bin/wordpress-config.sh export [--in DIR]   configurazione del sito → config/ (o DIR)
  bin/wordpress-config.sh apply               mostra cosa cambierebbe, senza scrivere
  bin/wordpress-config.sh apply --conferma    salva un backup in backup/<data-ora>/ e applica config/
  bin/wordpress-config.sh ritratto            stato del sito, da confrontare prima e dopo un reset
  bin/wordpress-config.sh zip                 zip di Impreza e dei plugin commerciali installati

Con più WordPress avviati che montano questo child: WPF_CONTAINER=<nome>.
TESTO
}

trova_container() {
    if [ -n "${WPF_CONTAINER:-}" ]; then
        echo "$WPF_CONTAINER"
        return
    fi
    # Il mount può riportare il percorso con o senza i link simbolici risolti: si accettano entrambi
    local child="${REPO}/themes/forestas-child" logico trovati="" quanti=0 c sorgente
    logico="$(cd "$(dirname "$0")/.." && pwd -L)/themes/forestas-child"
    for c in $(docker ps -q); do
        sorgente=$(docker inspect -f "{{range .Mounts}}{{if eq .Destination \"${CHILD_MOUNT}\"}}{{.Source}}{{end}}{{end}}" "$c")
        if [ -n "$sorgente" ] && { [ "$sorgente" = "$child" ] || [ "$sorgente" = "$logico" ]; }; then
            trovati="$trovati $(docker inspect -f '{{.Name}}' "$c" | tr -d /)"
            quanti=$((quanti + 1))
        fi
    done
    [ "$quanti" -eq 0 ] && fail "nessun container WordPress avviato monta ${child}: avvialo con scripts/wordpress-up.sh di forestas"
    [ "$quanti" -gt 1 ] && fail "più container montano questo child:${trovati}. Scegli con WPF_CONTAINER=<nome>"
    echo "${trovati# }"
}

case "${1:-}" in
    export|apply|ritratto|zip) ;;
    *)
        uso
        exit 1
        ;;
esac

C=$(trova_container)
URL=$(docker exec "$C" printenv WP_URL || true)
ADMIN=$(docker exec "$C" printenv WP_ADMIN_USER || true)
# Con un sito in https, HTTPS va impostato prima che WordPress si carichi (CSS rigenerato da Impreza)
# Con un sito in https, HTTPS va impostato prima che WordPress si carichi (CSS rigenerato da Impreza):
# stesso criterio di wp_script() in init-wordpress.sh
HTTPS_EXTRA=()
if [[ "$URL" == https://* ]]; then HTTPS_EXTRA=(--exec='$_SERVER["HTTPS"]="on";'); fi

# esegui_script <script.php> [VAR=valore…]: esegue uno script di configurazione come www-data
# (wp_script di init-wordpress.sh fa lo stesso come root, dentro il container, all'avvio)
esegui_script() {
    local script=$1
    shift
    local env_args=""
    for v in "$@"; do env_args="$env_args -e $v"; done
    # shellcheck disable=SC2086
    # ${A[@]+"${A[@]}"}: un array vuoto con set -u, anche con la bash 3.2 di macOS
    docker exec -u www-data $env_args "$C" wp --path="$WP_PATH" ${HTTPS_EXTRA[@]+"${HTTPS_EXTRA[@]}"} --user="$ADMIN" eval-file "${CONFIG_LIB}/${script}"
}

# export_in <cartella> [sola-lettura]: in sola lettura non assegna chiavi al sito (backup dell'apply)
export_in() {
    local dest=$1 tmp=/tmp/wp-forestas-export-$$ prodotti f
    mkdir -p "$dest"
    if [ "${2:-}" = "sola-lettura" ]; then
        esegui_script export.php "WPF_EXPORT_DIR=${tmp}" "WPF_SOLA_LETTURA=1"
    else
        esegui_script export.php "WPF_EXPORT_DIR=${tmp}"
    fi
    # Si sostituiscono solo i file prodotti: un export con WPML spento non cancella wpml.json
    prodotti=$(docker exec "$C" sh -c "cd ${tmp} && ls *.json")
    for f in impreza.json child.json sito.json post.json menu.json wpml.json versioni.json; do
        if [[ $'\n'"$prodotti"$'\n' != *$'\n'"$f"$'\n'* ]] && [ -f "${dest}/${f}" ]; then
            echo "AVVISO: ${f} non prodotto (per wpml.json: WPML non è attivo), lasciato com'è in ${dest}"
        fi
    done
    for f in $prodotti; do rm -f "${dest}/${f}"; done
    # docker cp crea i file con l'utente dell'host, non con quello del container
    docker cp "${C}:${tmp}/." "$dest/" >/dev/null
    docker exec "$C" rm -rf "$tmp"
    echo "configurazione esportata in ${dest}"
}

case "${1:-}" in
    export)
        if [ "${2:-}" = "--in" ]; then
            [ -n "${3:-}" ] || fail "--in vuole una cartella"
            export_in "$3"
        else
            export_in "${REPO}/config"
            echo "Controlla le differenze con «git diff config/» prima del commit."
        fi
        ;;
    apply)
        if [ "${2:-}" = "--conferma" ]; then
            backup="${REPO}/backup/$(date +%Y-%m-%d-%H%M%S)"
            echo "backup della configurazione attuale in ${backup}"
            export_in "$backup" sola-lettura
            esegui_script apply.php || fail "configurazione applicata solo in parte: vedi gli avvisi sopra (backup in ${backup})"
        else
            echo "Differenze fra config/ e il sito ${URL} (nulla viene scritto):"
            esegui_script apply.php WPF_PROVA=1
            echo "Per applicarle: $0 apply --conferma"
        fi
        ;;
    ritratto)
        esegui_script ritratto.php
        # una sola richiesta: l'ultima riga è il codice HTTP
        risposta=$(curl -s -L -m "$CURL_ATTESA" -w '\n%{http_code}' "$URL/" || true)
        codice=${risposta##*$'\n'}
        home=${risposta%$'\n'*}
        echo "home: ${codice}"
        # confronto di bash e non «echo | grep -q»: con pipefail il SIGPIPE di echo darebbe sempre «no»
        if [[ "$home" == *forestas-child/style.css* ]]; then echo "home carica forestas-child/style.css: sì"; else echo "home carica forestas-child/style.css: no"; fi
        if [[ "$home" == *fonts.googleapis.com* ]]; then echo "home carica font da Google: sì"; else echo "home carica font da Google: no"; fi
        ;;
    zip)
        mkdir -p "${REPO}/docker/themes" "${REPO}/docker/plugins"
        docker exec "$C" rm -rf /tmp/wpf-zip
        docker exec "$C" mkdir -p /tmp/wpf-zip
        # zip <cartella dentro wp-content> <nome dello zip>: la cartella resta la radice dello zip
        crea_zip() {
            docker exec -w "${WP_PATH}/wp-content/$(dirname "$1")" "$C" php -r '
                $z = new ZipArchive();
                if ($z->open($argv[2], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) { exit(1); }
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1], FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) { $z->addFile($f->getPathname(), $f->getPathname()); }
                exit($z->close() ? 0 : 1);' "$(basename "$1")" "/tmp/wpf-zip/$2"
        }
        crea_zip themes/Impreza impreza.zip
        docker cp "${C}:/tmp/wpf-zip/impreza.zip" "${REPO}/docker/themes/impreza.zip" >/dev/null
        echo "docker/themes/impreza.zip: Impreza $(docker exec "$C" wp --allow-root --path="$WP_PATH" theme get Impreza --field=version)"
        for slug in $PLUGIN_COMMERCIALI; do
            if ! docker exec "$C" test -d "${WP_PATH}/wp-content/plugins/${slug}"; then
                echo "AVVISO: ${slug} non è installato su questo sito: zip non rigenerato"
                continue
            fi
            crea_zip "plugins/${slug}" "${slug}.zip"
            docker cp "${C}:/tmp/wpf-zip/${slug}.zip" "${REPO}/docker/plugins/${slug}.zip" >/dev/null
            echo "docker/plugins/${slug}.zip: $(docker exec "$C" wp --allow-root --path="$WP_PATH" plugin get "$slug" --field=version)"
        done
        docker exec "$C" rm -rf /tmp/wpf-zip
        echo "Copia gli zip anche nella cartella condivisa del team: non stanno in git."
        ;;
esac
