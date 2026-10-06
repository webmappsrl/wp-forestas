# wp-forestas — CLAUDE.md

Ambiente Docker del WordPress dello shard Forestas: immagine PHP 8.3 + Apache con WP-CLI, MariaDB
11.4, inizializzazione a passi. È un submodule di `forestas`, che include `compose.yml` nei suoi
compose: come entra nello shard è spiegato in `forestas/docs/knowledge/wordpress-nello-shard.md`.
Uso e avvio: [README.md](README.md).

## Regole che precedono tutte le altre

**Il repo è pubblico.** Nessun valore reale in un file tracciato: password, chiavi, URL interni
stanno solo in `.env` (escluso da git). `.env-example` contiene solo segnaposto.

**Il tema Impreza è commerciale e non si ridistribuisce.** Lo zip sta in `docker/themes/`, esclusa
da git: non va mai committato, nemmeno per una prova.

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
- `wp option get WPLANG` fallisce su un'installazione appena fatta: la lingua attiva si legge con
  `wp language core list --status=active` (oc:8711)

## Conoscenza

| Argomento | Cosa copre | Pagina |
|---|---|---|
| Inizializzazione di WordPress | perché lo script lavora a passi, perché il core non sta nell'immagine, cosa succede senza GitHub o senza Impreza | [docs/knowledge/inizializzazione-wordpress.md](docs/knowledge/inizializzazione-wordpress.md) |
