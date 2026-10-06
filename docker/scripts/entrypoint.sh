#!/bin/bash
# Porta WordPress allo stato atteso, poi avvia Apache.
# Se l'inizializzazione fallisce il container esce: il motivo è nei log
# (docker logs wordpress-<APP_NAME>).
set -e

init-wordpress.sh

exec docker-php-entrypoint "$@"
