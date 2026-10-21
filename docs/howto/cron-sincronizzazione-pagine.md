# Mettere in cron la sincronizzazione delle pagine statiche

Procedura per far girare ogni giorno sul server `bin/wordpress-config.sh pagine --conferma`, che
allinea le pagine statiche alla copia SQL del database Drupal (oc:8758). Cosa fa il comando e come
si legge il log: [README](../../README.md#pagine-statiche-da-drupal); le scelte e i vincoli:
[pagina di conoscenza](../knowledge/8758-import-delle-pagine-editoriali-da-drupal-e-ricostruzione-con-impreza-di-quelle-fatte-con-il-layout-builder.md).

Il cron gira **sull'host**, non dentro WordPress: il comando trova da sé il container WordPress e ci
esegue lo script. Va messo solo dove la copia Drupal c'è e Drupal è ancora la fonte dei contenuti
(UAT; produzione fino al lancio).

## 1. Accendere la sincronizzazione

Nel `wp-forestas/.env` del server:

```bash
DRUPAL_SYNC=on
DRUPAL_DB_USER=<utente in sola lettura della copia>
DRUPAL_DB_PASSWORD=<la sua password>
```

Servono anche `DRUPAL_DB_USER` e `DRUPAL_DB_PASSWORD`, l'utente in sola lettura della copia
(`forestas/docs/howto/copia-database-drupal.md`): non hanno default. Host, porta e database restano
vuoti: su UAT e in produzione vale il default, il container `mysql-sardegnasentieri-dump` sulla stessa
rete Docker. Il container WordPress legge il `.env` solo
all'avvio, quindi dalla root di `forestas`:

```bash
scripts/wordpress-up.sh
```

Controllo:

```bash
docker exec wordpress-<APP_NAME> printenv DRUPAL_SYNC     # on
```

## 2. Prima esecuzione, a mano

(Su un sito appena ricreato: segui invece l'intera sequenza di
[ricreare-wordpress-da-zero.md](ricreare-wordpress-da-zero.md), passi 3-6.)

Dalla cartella `wp-forestas` sull'host:

```bash
bin/wordpress-config.sh applica --conferma    # template delle pagine (e il resto di config/), con backup
bin/wordpress-config.sh pagine                # anteprima: pagine da creare, media da scaricare, link
bin/wordpress-config.sh pagine --conferma     # crea le pagine e scarica i media
bin/wordpress-config.sh applica               # anteprima: le voci di menu da collegare alle pagine
bin/wordpress-config.sh applica --conferma    # collega i menu alle pagine appena create
```

Le pagine hanno bisogno dei template di Impreza, che scrive l'apply; le voci di menu le scrive l'apply,
non il comando delle pagine, e una voce verso una pagina che ancora non c'è resta un link per indirizzo
finché un apply successivo non la collega: per questo l'apply va prima e dopo. Serve solo la prima
volta, o quando cambia `config/`. Una seconda anteprima deve dire «invariata» per tutte le pagine e
«nessuna differenza» per l'apply.

## 3. La riga del crontab

Nel crontab di `root` (`crontab -e`), **lontano dalle 10:00**, ora in cui la copia di UAT si ricarica
(`forestas/docs/howto/copia-database-drupal.md`, «La copia su UAT»): durante il reload il comando si
ferma senza scrivere, ma il giro va perso.

```
0 11 * * * cd /var/www/html/forestas/wp-forestas && PATH=/usr/local/bin:/usr/bin:/bin bin/wordpress-config.sh pagine --conferma >> /var/www/html/forestas/storage/logs/wordpress-pagine-cron.log 2>&1
```

- `cd` nella cartella del repo e `PATH` esplicito: il cron parte con un ambiente ridotto, e il comando
  usa `docker`.
- Il file indicato nella riga raccoglie solo il riassunto di ogni giro; il dettaglio è in
  `wp-forestas/log/pagine-<data-ora>.log` (si tengono gli ultimi 60).

## 4. Leggere l'esito

Sul server di UAT il cron non manda notifiche (nessun `MAILTO`, nessun programma di posta): l'esito si
legge nel file del cron e nei log. Il codice d'uscita conta per chi lancia il comando a mano e per
un eventuale script che lanci più import in fila.

| Codice d'uscita | Significato | Cosa fare |
|---|---|---|
| 0 | tutto sincronizzato | niente |
| 0, `AVVISO … media non scaricati` | il sito Drupal non ha servito qualche file: le pagine sono aggiornate | niente: il giro successivo ritenta; per ritentare subito `bin/wordpress-config.sh pagine --contenuti-mancanti --conferma` |
| 0, `AVVISO … config/traduzioni/en/<chiave>.* va rivista` | il testo italiano di una pagina che Drupal ha solo in italiano è cambiato: la pagina inglese mostra ancora la traduzione vecchia | rifare la traduzione in `config/traduzioni/en/<chiave>.html` (e titolo o sottotitolo nel `.json`), aggiornare «sorgente» con l'impronta nuova che l'avviso riporta, commit |
| 0, `AVVISO … non si usa più e si può cancellare` | Drupal ha ora la sua traduzione inglese, che vince | cancellare i due file in `config/traduzioni/en/`, commit |
| 1, righe `MANCANTE` di pagina, traduzione o template | qualcosa che non si sistema da sé | leggere la causa nella riga `MANCANTE`, correggere, poi `--contenuti-mancanti --conferma` |
| 1, `ERRORE copia Drupal non integra` | la copia era in ricarica o è troncata: nulla scritto | controllare l'orario del cron e il log del sync della copia |
| 1, `ERRORE … DRUPAL_SYNC` (con `--conferma`) o `AVVISO … DRUPAL_SYNC … solo anteprima` (senza) | interruttore spento: nulla scritto | punto 1 |
| 1, `ERRORE … un'altra sincronizzazione` | due esecuzioni sovrapposte: la seconda esce | nessuna, se è un caso isolato |
| 1, `AVVISO … non riuscirebbe` (in anteprima) | il `--conferma` lascerebbe un template fra i mancanti (l'anteprima non scrive, quindi può prevedere solo questo: una pagina o una traduzione che non si salvano si scoprono al `--conferma`) | `applica --conferma`, che scrive i template, poi di nuovo `pagine` |
| più di 1, `Uscita N: errore imprevisto` con la coda del log | errore di PHP o di WP-CLI, non del comando (per esempio una funzione di WPML cambiata dopo un aggiornamento) | leggere il log completo; il comando non ha finito il giro |

Le righe `LINK … in attesa` non sono errori: sono link verso contenuti non ancora importati, che si
sistemano da soli quando arrivano. Nemmeno le righe `LINK … verso Drupal`: sono link a pagine di Drupal
che non sono contenuti (il form `/form/segnalazione`, un file, un vecchio indirizzo servito da un
redirect di Drupal), che restano verso Drupal finché una regola in
`docker/scripts/config/link.php` non dà loro un indirizzo WordPress.

## 5. Al lancio in produzione

Quando WordPress diventa la fonte dei contenuti:

1. togliere la riga dal crontab;
2. `DRUPAL_SYNC=off` nel `wp-forestas/.env` e `scripts/wordpress-up.sh`: anche lanciato a mano, il
   comando non scrive più e non riporta le pagine alla copia Drupal, che resta ferma all'ultimo dump.
