# Mettere in cron la sincronizzazione delle pagine statiche e delle news

Procedura per far girare ogni giorno sul server `bin/wordpress-config.sh allinea`, che porta il
WordPress allo stato di riferimento senza interventi: pagine statiche (oc:8758) e news (oc:8759)
allineate alla copia SQL del database Drupal e voci dei menu collegate alle pagine. Le news fanno parte
dello stesso giro del comando delle pagine: la riga del cron è la stessa. Vale anche per un sito appena
ricreato da zero: dopo il reset l'init applica `config/`, e il primo giro di `allinea` crea pagine e
news e collega i menu (verificato in locale il 27/10 lanciando solo `allinea` dopo il reset: 36 pagine
e 99 news, `STATO allineato`, ritratto identico a quello di prima del reset; il primo giro dura una
ventina di minuti, perché scarica dal sito Drupal circa 300 file delle news). Cosa fa il comando delle pagine e
come si legge il log: [README](../../README.md#pagine-statiche-da-drupal); le scelte e i vincoli:
[pagine](../knowledge/8758-import-delle-pagine-editoriali-da-drupal-e-ricostruzione-con-impreza-di-quelle-fatte-con-il-layout-builder.md)
e [news](../knowledge/8759-import-delle-news-da-drupal.md).

Il cron gira **sull'host**, non dentro WordPress: il comando trova da sé il container WordPress e ci
esegue gli script. Va messo solo dove la copia Drupal c'è e Drupal è ancora la fonte dei contenuti
(UAT; produzione fino al lancio).

**Cosa fa `allinea`, in ordine:**
1. controlla che il sito sia pronto (WordPress installato e `config/` applicata dall'init); se non lo
   è, esce con 1 e il giro dopo riprova;
2. `pagine --conferma`: pagine, poi news;
3. guarda l'anteprima di `applica`: se le sole differenze sono nei menu (voci da collegare a pagine
   appena create o ripubblicate) le applica, con il backup in `backup/<data-ora>`; se `config/` è
   diverso dal sito anche in altro (un rilascio non ancora applicato, una modifica dal pannello) non
   tocca nulla e lo segnala: quello si applica a mano guardando l'anteprima;
4. verifica: `applica` deve dire «nessuna differenza» e l'anteprima delle pagine uscire con 0.

L'ultima riga è `STATO allineato (…)` con uscita 0, o `STATO non allineato: <motivo>` con uscita 1.
Fanno eccezione i casi in cui il comando non trova il WordPress: nessun container avviato (un reset o
un `wordpress-up.sh` in corso) o più di uno; allora esce con 1 e una riga `ERRORE: …`, senza `STATO`.
Un giro su un sito già allineato non scrive nulla e non crea backup.

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

Dalla cartella `wp-forestas` sull'host, la stessa riga del cron, a mano, per vederne l'esito:

```bash
bin/wordpress-config.sh allinea      # ultima riga: STATO allineato (…)
```

Va bene anche su un sito appena ricreato, dove l'init ha già applicato `config/`. Se si preferisce
guardare un passo alla volta, sono questi. `allinea` fa da solo gli ultimi quattro, e l'ultimo solo se
le differenze sono tutte di menu; il primo, l'`applica --conferma` di tutto `config/`, non lo fa mai:
su un sito nuovo lo fa l'init, su un sito in uso è un rilascio:

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

La riga è versionata in [`cron/allinea.crontab`](../../cron/allinea.crontab), l'unico posto in cui è
scritta: si copia da lì nel crontab di `root` (`crontab -e`). Il file dice anche lo stato
dell'installazione (oggi: non installata su nessun server); quando la si mette in un crontab, si
aggiorna quello stato nello stesso commit.

L'orario è **lontano dalle 10:00**, ora in cui la copia di UAT si ricarica
(`forestas/docs/howto/copia-database-drupal.md`, «La copia su UAT»): durante il reload il comando si
ferma senza scrivere, ma il giro va perso.

- `cd` nella cartella del repo e `PATH` esplicito: il cron parte con un ambiente ridotto, e il comando
  usa `docker`.
- Il file indicato nella riga raccoglie di ogni giro il riassunto di pagine e news (con le righe che
  chiedono un intervento: `ERRORE`, `MANCANTE`, `MENU`, ogni `AVVISO`) e lo `STATO`; in più l'output
  intero dell'apply quando `allinea` collega i menu, e quello della verifica quando fallisce. Il
  dettaglio delle pagine è in `wp-forestas/log/pagine-<data-ora>.log`: ogni giro di `allinea` ne scrive
  due (il giro e la verifica), quindi i 60 tenuti coprono circa un mese di cron.

## 4. Leggere l'esito

Sul server di UAT il cron non manda notifiche (nessun `MAILTO`, nessun programma di posta): l'esito si
legge nel file del cron e nei log. Il codice d'uscita conta per chi lancia il comando a mano e per
un eventuale script che lanci più import in fila. La riga da cercare nel file del cron è l'ultima di
ogni giro, `STATO …`:

| `STATO` | Significato | Cosa fare |
|---|---|---|
| `allineato (pagine: … ; news: …)` | sito uguale a `config/` e alla copia Drupal | niente |
| `non allineato: config/ non ancora applicata dall'init` | sito appena ricreato, l'init non ha finito (o non è riuscito) l'apply automatico | niente se il giro dopo passa; altrimenti i log dell'init (`docker logs wordpress-<APP_NAME>`) |
| `non allineato: config/ è diverso dal sito anche fuori dai menu` | un rilascio di `config/` non ancora applicato, o una modifica fatta dal pannello | `bin/wordpress-config.sh applica`, guardare le differenze (elencate sopra la riga), poi `applica --conferma` a mano |
| `non allineato: il giro delle pagine è uscito con 1` o `la verifica delle pagine esce con 1` | righe `ERRORE` o `MANCANTE` del comando delle pagine, sopra nel file | la tabella qui sotto |
| `non allineato: errore imprevisto delle pagine` o `applica dei menu non riuscito` | errore di PHP, di WP-CLI o dell'apply | le righe sopra, e il log completo |
| `non allineato: anteprima di applica non riuscita` | l'anteprima dell'apply è uscita con errore (il suo output è sopra) | le righe sopra |
| `non allineato: dopo il giro applica mostra ancora differenze` | dopo pagine e menu `config/` e il sito differiscono ancora (le differenze sono sopra) | `bin/wordpress-config.sh applica` a mano, guardando le differenze |
| nessuno `STATO`, `ERRORE: nessun container WordPress avviato monta …` o `più container montano questo child` | WordPress fermo (reset o riavvio in corso) o più container con lo stesso child | niente se il giro dopo passa; altrimenti `docker ps` e `scripts/wordpress-up.sh` |

Le righe del comando delle pagine, sopra lo `STATO`:

| Codice d'uscita | Significato | Cosa fare |
|---|---|---|
| 0 | tutto sincronizzato | niente |
| 0, `AVVISO … media non scaricati` | il sito Drupal non ha servito qualche file: le pagine sono aggiornate | niente: il giro successivo ritenta; per ritentare subito `bin/wordpress-config.sh pagine --contenuti-mancanti --conferma` |
| 0, `AVVISO … config/traduzioni/en/<chiave>.* va rivista` | il testo italiano di una pagina che Drupal ha solo in italiano è cambiato: la pagina inglese mostra ancora la traduzione vecchia | rifare la traduzione in `config/traduzioni/en/<chiave>.html` (e titolo o sottotitolo nel `.json`), aggiornare «sorgente» con l'impronta nuova che l'avviso riporta, commit |
| 0, `AVVISO … non si usa più e si può cancellare` | Drupal ha ora la sua traduzione inglese, che vince | cancellare i due file in `config/traduzioni/en/`, commit |
| 1, righe `MANCANTE` di pagina, traduzione o template | qualcosa che non si sistema da sé | leggere la causa nella riga `MANCANTE`, correggere, poi `--contenuti-mancanti --conferma` |
| 1, `ERRORE copia Drupal non integra` | la copia era in ricarica o è troncata: nulla scritto | controllare l'orario del cron e il log del sync della copia |
| 1, `ERRORE copia Drupal sospetta: N pagine da mettere in bozza` | più di 2 pagine spubblicate nello stesso giro: di solito una copia in ricarica | se la copia è a posto e su Drupal le pagine sono state spubblicate davvero, `bin/wordpress-config.sh pagine --accetta-bozze --conferma` (una volta, a mano) |
| 1, `ERRORE copia Drupal sospetta: N news da mettere in bozza` o `… news pubblicate nella copia contro … in WordPress` | più di 5 news spubblicate o sparite nello stesso giro, o nella copia più di 5 news pubblicate in meno che in WordPress: di solito una copia in ricarica. Le pagine sono scritte, le news no | se la copia è a posto e su Drupal le news sono state tolte davvero, `bin/wordpress-config.sh pagine --accetta-bozze --conferma` (una volta, a mano) |
| 0, `AVVISO news N/it: la tipologia … non esiste più su Drupal` o `il tag …` | la news usa un termine cancellato su Drupal (oggi le news 839 e 842) | niente: la news resta senza quel termine |
| 1, `ERRORE nodi di config/pagine.json non trovati nella copia Drupal` | la copia è in ricarica, o su Drupal il nodo è stato cancellato | se il reload è finito e il nodo manca ancora, togliere la sua voce da `config/pagine.json`, commit; la pagina WordPress resta e il giro dopo la segnala come fuori dal perimetro |
| 1, `ERRORE il nodo N (<chiave>) non ha la lingua «en»` | la copia è in ricarica, o su Drupal la traduzione inglese è stata tolta | se il reload è finito, scrivere la traduzione nostra in `config/traduzioni/en/<chiave>.json` e `.html`, o togliere `slug.en` da `config/pagine.json`; commit |
| 0, `AVVISO la pagina … non è più in config/pagine.json` | voce tolta o chiave rinominata: la pagina resta com'era e nessuno la aggiorna | se va tolta, nel cestino dal pannello; una chiave non si rinomina |
| 0, `AVVISO nella copia Drupal l'ultimo contenuto è stato modificato il …` | nessuna modifica da più di 30 giorni: forse il dump non arriva più | controllare il reload della copia (`forestas/docs/howto/copia-database-drupal.md`) |
| 1, `ERRORE … DRUPAL_SYNC` (con `--conferma`) o `AVVISO … DRUPAL_SYNC … solo anteprima` (senza) | interruttore spento: nulla scritto | punto 1 |
| 1, `ERRORE … un'altra sincronizzazione` | due esecuzioni sovrapposte (la seconda esce), o il blocco rimasto da un container fermato a metà giro | nessuna, se è un caso isolato: il blocco rimasto lo toglie l'avvio del container (`scripts/wordpress-up.sh`) o scade dopo 2 ore |
| 1, `ERRORE DRUPAL_DB_USER e DRUPAL_DB_PASSWORD mancano nel .env di wp-forestas` | credenziali della copia non impostate (non hanno valori predefiniti), o container non riavviato | aggiungerle al `wp-forestas/.env`, poi `scripts/wordpress-up.sh` |
| 1, `AVVISO … non riuscirebbe` (in anteprima) | il `--conferma` lascerebbe un template fra i mancanti (l'anteprima non scrive, quindi può prevedere solo questo: una pagina o una traduzione che non si salvano si scoprono al `--conferma`) | `applica --conferma`, che scrive i template, poi di nuovo `pagine` |
| diverso da 0, `Uscita N: errore imprevisto, il giro non è finito` con la riga `Fatal error` del log (o le sue ultime righe) | errore di PHP o di WP-CLI, non del comando (per esempio una funzione di WPML cambiata dopo un aggiornamento) | leggere il log completo; il comando non ha finito il giro |

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
3. sapere che il testo delle pagine si mostra così com'è finché resta quello importato (impronta
   `_wpf_testo_drupal`): il primo salvataggio dall'editor che lo cambia, anche solo per aggiungere un
   allegato, lo fa passare per la formattazione di WordPress (paragrafi, virgolette). Dopo ogni primo
   salvataggio di una pagina va controllato l'aspetto; l'editor lo ricorda con un avviso in cima al
   riquadro «Allegati e galleria», finché il testo è quello importato.
