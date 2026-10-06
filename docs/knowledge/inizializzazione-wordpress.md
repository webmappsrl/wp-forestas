# Inizializzazione di WordPress

## Come funziona oggi

L'entrypoint del container lancia `docker/scripts/init-wordpress.sh` prima di Apache, a ogni
avvio. Cosa controlla e in che ordine è descritto nel [README](../../README.md#cosa-fa-linizializzazione);
qui conta come si comporta e perché.

- **Ogni passo controlla prima di agire** e fa solo ciò che manca: un riavvio non tocca
  un'installazione esistente, e un pezzo mancante (uno zip di Impreza aggiunto dopo, un plugin non
  scaricato) viene rimesso al riavvio successivo.
- **Il core non sta nell'immagine:** lo scarica WP-CLI nel volume `wordpress-<APP_NAME>`, che
  contiene l'intera cartella di WordPress (core, `wp-config.php`, plugin, temi, lingue, uploads).
  Ricostruire l'immagine o ricreare il container non perde nulla.
- **GitHub non raggiungibile:** il sito parte lo stesso con un avviso nei log; `wp-geohub` si scarica
  al riavvio successivo.
- **Zip di Impreza assente:** avviso nei log, tema di default. Il tema si riconosce dallo slug della
  cartella (`impreza`, senza distinzione di maiuscole).
- **Variabili mancanti:** lo script esce elencandole; Docker riavvia il container a intervalli
  crescenti finché il `.env` non c'è.
- **Dietro un proxy HTTPS** `wp-config.php` imposta `HTTPS=on` quando arriva
  `X-Forwarded-Proto: https`: senza, WordPress crede di essere in http e va in un ciclo di redirect.

Vincolo: `wp-geohub` si scarica dal `main` del repo pubblico, che non ha tag né release; ogni
installazione prende il codice del momento. Rischio accettato (oc:8711).

## Perché così

- **Passi indipendenti invece di un solo controllo «WordPress installato?»** (oc:8711): con un
  controllo unico, un pezzo perso o aggiunto dopo l'installazione non tornerebbe mai.
- **Chiavi di sicurezza generate da `wp config create`** (oc:8711): non passano da nessun `.env`,
  quindi niente caratteri speciali da proteggere e nulla da incollare.
- **Plugin scaricato come tarball nella cartella `wm-package`** (oc:8711): lo zip che GitHub genera
  per un branch crea una cartella con il nome del branch, e il plugin vuole il nome `wm-package`.

## Come ci siamo arrivati

- **Plugin nella cartella `wp-geohub`** (oc:8711, superata): l'attivazione falliva perché il plugin
  cerca i suoi shortcode in `wp-content/plugins/wm-package/` (`functions/imports.php`).
- **Lingua letta da `wp option get WPLANG`** (oc:8711, superata): l'opzione non esiste subito dopo
  l'installazione e il comando fallisce.
