# wp-forestas — CLAUDE.md

Ambiente Docker del WordPress dello shard Forestas: immagine PHP 8.3 + Apache con WP-CLI, MariaDB
11.4, inizializzazione a passi. È un submodule di `forestas`, che include `compose.yml` nei suoi
compose: come entra nello shard è spiegato in `forestas/docs/knowledge/wordpress-nello-shard.md`.
Uso e avvio: [README.md](README.md).

## Regole che precedono tutte le altre

**Il repo è pubblico.** Nessun valore reale in un file tracciato: password, chiavi, URL interni
stanno solo in `.env` (escluso da git). `.env-example` contiene solo segnaposto.

**Impreza e WPML sono commerciali e non si ridistribuiscono.** Gli zip stanno in `docker/themes/` e
`docker/plugins/`, escluse da git: non vanno mai committati, nemmeno per una prova. Lo stesso vale
per le chiavi di licenza, che stanno solo nel `.env`.

**Non eseguire mai `git commit` senza istruzione esplicita dell'utente.** Vale anche per i
subagent.

## Regole del repo

- Documentazione, commenti e messaggi di commit in italiano; i termini tecnici restano in inglese.
- `compose.yml` non deve mai far fallire il parsing del compose di `forestas`: niente
  `${VAR:?…}`, `env_file` con `required: false`. Le variabili mancanti le segnala lo script.
- `init-wordpress.sh` controlla ogni passo prima di eseguirlo e non distrugge mai
  un'installazione esistente: in produzione i dati di WordPress saranno permanenti.
- Dopo un merge su `develop`/`main`, in `forestas` il puntatore del submodule si aggiorna con
  l'hash letto da `git rev-parse`, mai scritto a mano.

## Trappole

- La cartella del plugin `wp-geohub` deve chiamarsi `wm-package`: il plugin ha il percorso
  `wp-content/plugins/wm-package/shortcodes` scritto nel codice e con un altro nome l'attivazione
  lancia un'eccezione (oc:8711)
- MariaDB crea utente e database solo al primo avvio su un volume vuoto: avviata senza password
  inizializzerebbe l'utente senza password e ignorerebbe il `.env` creato dopo. Per questo esce se
  manca `WP_DB_PASSWORD` — non togliere quel controllo (oc:8711)
- I valori del `.env` (URL, amministratore) valgono solo alla prima installazione: dopo si
  cambiano dal pannello o con WP-CLI, non dal `.env` (oc:8711)
- `blog_public = 0` viene impostato all'installazione e non più toccato: al lancio in produzione va
  tolto a mano (Impostazioni → Lettura) (oc:8711)
- Il child theme e la configurazione del sito si cambiano in locale, mai dal pannello di UAT né con
  Child Theme Configurator: lì finirebbero nel checkout del server, fuori da git (README, «Lavorare
  sul child theme» e «Configurazione versionata») (oc:8717)
- `init-wordpress.sh` sta nell'immagine, non è montato come gli script di `docker/scripts/config/`:
  una sua modifica gira solo dopo `scripts/wordpress-up.sh` di `forestas`, che ricostruisce
  l'immagine; un semplice `docker restart` esegue ancora quello vecchio (oc:8717)
- `init-wordpress.sh` assegna a `www-data` tutti i file di WordPress tranne il child montato: un
  `chown` sulla cartella montata cambierebbe il proprietario dei file del repo sull'host (oc:8717)
- `git diff config/` prima di ogni commit: l'export toglie i segreti per nome e si ferma sui valori
  con la forma di una chiave nota, ma un segreto con un nome insolito e una forma qualsiasi sfugge,
  in un repo pubblico (oc:8717)
- WPML si configura solo con le sue API (`CatalogueSyncRunner`, `SaveLanguages` con `presetCode`,
  `save_settings`): su un sito nuovo, senza sincronizzare il catalogo, l'API risponde
  `missing_preset` (oc:8717)
- Da WP-CLI WPML non toglie la riga di traduzione di un post cancellato (lo fa solo dal pannello o
  dal sito) e converte il link di una traduzione in quello della lingua predefinita: negli script di
  `docker/scripts/config/` si cancella con `wpf_cancella_post` e si prende il link con
  `wpf_link_relativo` (oc:8717)
- Dopo un aggiornamento di Impreza o WPML dal pannello di UAT: `bin/wordpress-config.sh zip`, poi
  zip in locale e nella cartella condivisa (README), e un controllo delle funzioni interne che export
  e apply usano (`docs/knowledge/inizializzazione-wordpress.md`, «Dipendenze») (oc:8717)
- In `bin/` e negli script con `set -o pipefail`, niente `… | head` o `echo … | grep -q` per
  decidere: il SIGPIPE fa fallire la pipeline anche quando il testo c'è (oc:8717)
- `wp option get WPLANG` fallisce su un'installazione appena fatta: la lingua attiva si legge con
  `wp language core list --status=active` (oc:8711)

## Conoscenza

| Argomento | Cosa copre | Pagina |
|---|---|---|
| Inizializzazione di WordPress | perché lo script lavora a passi, perché il core non sta nell'immagine, cosa succede senza GitHub o senza Impreza | [docs/knowledge/inizializzazione-wordpress.md](docs/knowledge/inizializzazione-wordpress.md) |
