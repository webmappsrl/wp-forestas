> Ticket: oc:8758

# Import delle pagine statiche da Drupal e ricostruzione con Impreza di quelle fatte con il Layout Builder

## Cosa cambia

Il WordPress di Forestas ha le **pagine statiche della struttura del sito**, cioè quelle raggiungibili
dai menu di header e footer, create e tenute allineate da uno script a partire dalla copia SQL del
database Drupal di Sardegna Sentieri. Come header e footer (oc:8717, oc:8757), tutto si ricrea da
zero con un comando: pagine, testi, immagini, allegati, traduzioni inglesi e collegamenti dei menu.

**Perimetro: 14 pagine**, elenco fisso versionato nel repo (non si cercano pagine nuove su Drupal):

| Menu | Voce | Pagina Drupal (nid) | Cosa crea lo script |
|---|---|---|---|
| principale | Sentieri | 1080 (+EN) | solo testata |
| principale | Itinerari | 1081 (+EN) | solo testata |
| principale | Cammini | — (pagina nuova chiesta dal cliente, non esiste in Drupal) | solo testata, con titolo «Cammini» e, per ora, sottotitolo e immagine di Itinerari (1081), anche in inglese |
| principale | Punti di interesse | 1082 «Da vedere» (+EN) | solo testata |
| principale | Da sapere | 1083 (+EN) | solo testata |
| principale | News | 1084 | solo testata |
| principale | Mappa interattiva | 1093 | il blocco mappa del tema Drupal (iframe dell'app, pulsante per lo schermo intero), titolo nascosto alla vista come sul sito attuale |
| servizio | Contatti | 1 | pagina completa; la voce di menu però porta a `/enti`, come su Drupal |
| servizio | FAQ | 86 | pagina completa |
| servizio | App | 1486 | pagina completa |
| footer | Note legali | 87 (+EN) | pagina completa |
| footer | Privacy | 88 | pagina completa |
| footer | Crediti | 89 (+EN) | pagina completa |
| footer | Dichiarazione di accessibilità | 1496 | pagina completa |

- **Solo testata**: titolo, sottotitolo (`field_descrizione_testata_pagina`) e immagine in evidenza
  (`field_immagine_principale_media`). Su Drupal queste pagine sono fatte col Layout Builder e sotto
  la testata hanno solo View (elenchi di sentieri, POI, da sapere, news…): gli elenchi arrivano con
  ticket dedicati, insieme ai contenuti che li riempiono.
- **Pagina completa**: testata, testo (`body`), allegati (`field_allegati_media`) e galleria
  (`field_galleria_media`), dove Drupal li ha.

**Il design sta nei template di Impreza, le pagine contengono solo dati.** Lo script scrive in ogni
pagina titolo, sottotitolo (nel Riassunto nativo, `post_excerpt`), immagine in evidenza, testo HTML pulito, allegati e galleria,
e le assegna un template di contenuto di Impreza (`us_content_template`): uno per le pagine con sola
testata, uno per le pagine di testo, uno per la mappa. I template leggono i dati della pagina, sono
versionati in `config/post.json` come header e footer: cambiando un template cambiano insieme tutte
le pagine che lo usano. La testata ha altezze, misure e impaginazione (testo in basso, centrato) del
tema Drupal, la sfumatura dal basso delle tavole del catasto dell'artifact «Canvas Fase 2» e la
barretta della tavola 8 nel colore dell'entità della voce
di menu (oc:8783); testo, allegati e galleria sono identici al tema Drupal `sardegnasentieri`, che
l'artifact non ridisegna: il testo è filtrato all'import come il formato `filtered_html` di Drupal e
mostrato così com'è finché non viene modificato in WordPress.

**Drupal vince sempre.** È una sincronizzazione per la fase di migrazione: finché è attiva la
redazione lavora su Drupal e le pagine in WordPress non si modificano; al passaggio in produzione la
sincronizzazione si spegne e WordPress diventa la fonte. A ogni esecuzione lo script riscrive le
pagine con i dati di Drupal. Ogni pagina si identifica con la sua chiave nell'elenco del perimetro
(meta `_wpf_pagina`, per esempio `privacy` o `cammini`) e la lingua: viene trovata e aggiornata, mai
ricreata, così id, indirizzi, media e collegamenti WPML restano stabili. Le pagine che vengono da
Drupal portano anche il nid e la lingua del nodo (`_drupal_nid`, `_drupal_langcode`), usati per
risolvere i link. Una pagina spubblicata su Drupal (`status = 0`) va **in bozza** su WordPress e
torna pubblicata, con lo stesso indirizzo, se su Drupal ritorna.

**Sicurezze.** Lo script scrive solo se nel `wp-forestas/.env` trova `DRUPAL_SYNC=on` (predefinito
`off`: l'anteprima resta sempre possibile); al passaggio in produzione si mette `off`. Prima di
scrivere controlla che la copia Drupal sia integra (tabelle presenti e non vuote, pagine del
perimetro trovate) e si ferma senza toccare nulla se un nid del perimetro non c'è o se più di 2
pagine dovrebbero andare in bozza nello stesso giro: è il segno di una copia in ricarica o
troncata (una spubblicazione vera passa a mano con `pagine --accetta-bozze`). Un lock impedisce due
esecuzioni insieme. A ogni giro un AVVISO segnala le pagine sincronizzate che `config/pagine.json` non
elenca più e una copia senza modifiche da più di 30 giorni, di cui il riassunto dà la data.

**Ciò che manca si ritenta.** Ogni elemento non riuscito (pagina, traduzione e il suo collegamento
WPML, immagine della testata, allegato, galleria, template) finisce in un elenco salvato su WordPress con
pagina, lingua, causa e date del primo e dell'ultimo tentativo. L'opzione `--contenuti-mancanti`
rilegge da Drupal e riscrive solo quegli elementi; l'esecuzione normale riscrive tutto e toglie
dall'elenco ciò che è andato a buon fine. Un media che non si scarica non nasconde gli altri
allegati della pagina, e non toglie l'immagine in evidenza che c'era.

**Indirizzi.** Le pagine che su Drupal hanno un alias scritto a mano lo mantengono (`/contatti`,
`/sentieri`, `/itinerari`, `/da-vedere`, `/da-sapere`, `/news`, `/mappa-interattiva`). Le 6 che su
Drupal stanno sotto `/pagina-base/…` (pattern automatico di pathauto, «Pagina base» è il nome
predefinito del tipo `page`) hanno indirizzi puliti: `/faq`, `/privacy`, `/note-legali`,
`/crediti`, `/app`, `/dichiarazione-di-accessibilita` e gli equivalenti inglesi. Cammini ha un
indirizzo suo (`/cammini`). I link interni nei testi verso `www.sardegnasentieri.it` diventano link
relativi al contenuto WordPress che porta lo stesso nid Drupal; se quel contenuto non esiste ancora,
il link resta verso Drupal e si sistema da solo a una sincronizzazione successiva.

**Log.** Ogni esecuzione scrive un file di log sull'host che racconta cosa è stato fatto pagina per
pagina, quali media e link sono stati trattati, cosa è rimasto aperto e perché.

**Inglese.** Ogni pagina ha la versione inglese, collegata all'italiano in WPML. Dove Drupal ha
l'inglese (Sentieri, Itinerari, Da vedere, Da sapere, Note legali, Crediti, più Cammini che usa la
testata inglese di Itinerari) si importa quello, che vince sempre. Dove non c'è, la nostra traduzione
versionata in `config/traduzioni/en/` (FAQ, App, Privacy, Dichiarazione di accessibilità, Contatti,
News) o, per la Mappa, la sola interfaccia in inglese. Le voci inglesi dei menu portano alle pagine
inglesi (decisione del dev, 21/10).

**Media nella libreria di WordPress.** Immagini e PDF delle pagine (22 file in tutto) vengono
scaricati da Drupal e registrati come allegati WordPress; a una nuova esecuzione un file già
scaricato non si riscarica.

**Menu collegati.** Le voci di `config/menu.json` che oggi puntano a `#` e corrispondono a una pagina
del perimetro diventano collegamenti a quella pagina, con un riferimento che non dipende dall'id di
WordPress (lo stesso in locale, su UAT e in produzione). I menu restano scritti solo dall'apply.

**Come si lancia.** Un comando sull'host in `wp-forestas` (come `bin/wordpress-config.sh`) che senza
opzioni mostra le differenze e con `--conferma` scrive. Gira dentro il container WordPress con
WP-CLI e legge la copia Drupal da un indirizzo preso da `wp-forestas/.env`: per default
`mysql-sardegnasentieri-dump:3306`, il container della copia sulla rete Docker dello shard (UAT e
produzione, nessuna configurazione); in locale col tunnel `host.docker.internal:3307`. Niente SSH
negli script. Il comando è pronto per essere chiamato da un cron sul server (nessuna domanda, codice
d'uscita diverso da 0 in caso di errore), ma il cron non fa parte di questo ticket.

## Perché

Il WordPress sostituisce la parte editoriale del sito Drupal, destinato a essere spento: le pagine
che i menu già elencano (oggi tutte a `#`, oc:8757) devono esistere, con la grafica del sito attuale
che il capitolato chiede, e con i loro contenuti e media copiati in WordPress. Fino al lancio la
fonte resta Drupal, che la redazione continua a usare: per questo la sincronizzazione è ripetibile
e Drupal vince sempre. Come per header e footer, il sito deve potersi ricreare da zero con uno
script, senza lavoro a mano dal pannello, perché un cambio di server non richieda giorni (scrum del
06/10, descrizione del tag `wp-forestas`).

## Requisiti

- [x] Elenco versionato delle 14 pagine del perimetro con, per ognuna, chiave (`_wpf_pagina`), nid
      Drupal (o la regola di Cammini), slug WordPress italiano e inglese scritti espliciti e
      template da usare.
- [x] Comando sull'host che senza opzioni mostra le differenze (pagine da creare, da aggiornare, da
      mettere in bozza, media da scaricare) e con `--conferma` scrive; esce con codice diverso da 0
      in caso di errore o se manca una pagina, una traduzione o un template; se mancano solo media
      esce con 0 e un avviso (si ritentano al giro dopo); nessuna domanda interattiva.
- [x] Interruttore `DRUPAL_SYNC=on|off` nel `wp-forestas/.env`, predefinito `off`: con `off` il
      comando mostra l'anteprima ma non scrive, ed esce con un messaggio chiaro e codice ≠ 0
      (con `--conferma` si ferma subito con un `ERRORE`, senza anteprima).
- [x] Controllo di integrità della copia prima di scrivere: tabelle lette presenti e non vuote;
      nid del perimetro non trovato → stop con errore (non bozza); più di 2 pagine da mettere in
      bozza nello stesso giro → stop con errore, nulla scritto, salvo `--accetta-bozze` a mano
      (review interna del 22/10).
- [x] Lock contro due esecuzioni contemporanee.
- [x] Elenco degli elementi mancanti salvato su WordPress (un'opzione, non i meta delle pagine):
      cosa, pagina, lingua, causa, primo e ultimo tentativo. `--contenuti-mancanti` ritenta solo
      quegli elementi (anteprima, poi `--conferma`); l'esecuzione normale ritenta tutto e ripulisce
      l'elenco. I link in attesa di contenuti non ancora importati non sono mancanti: il log li
      riporta a parte.
- [x] Un media che non si scarica finisce fra i mancanti e non ferma le altre pagine: allegati e
      galleria mostrano intanto quelli scaricati; l'immagine in evidenza resta quella che c'era.
- [x] `esporta` e `applica` di `bin/wordpress-config.sh` saltano le pagine con `_wpf_pagina`, anche se
      un'opzione di Impreza o del sito punta a loro: le pagine hanno un solo scrittore.
- [x] Lettura della copia Drupal in sola lettura da host e porta presi da `wp-forestas/.env`
      (`DRUPAL_DB_*`), con default `mysql-sardegnasentieri-dump:3306`; utente e password solo dal
      `.env`, senza default; `extra_hosts: host.docker.internal:host-gateway` nel compose; valori di
      esempio in `.env-example`, nessun valore reale nel repo.
- [x] Per ogni pagina: titolo, sottotitolo nel Riassunto nativo (`post_excerpt`), immagine in evidenza, testo HTML pulito
      (senza shortcode) nel `post_content`, allegati e galleria; meta `_drupal_nid`; template di
      contenuto di Impreza assegnato.
- [x] Percorso sempre subito sotto la testata; sotto, «Condividi» dell'artifact sulle pagine dove Drupal
      ha la condivisione: menu del sistema su telefono, pannello con i servizi e «Copia link» altrove.
- [x] Testo, allegati e galleria delle pagine di testo uguali a Drupal (posizione, dimensioni e stile
      di ogni elemento) a 1440, 800 e 375px, confrontati col CSS compilato del tema Drupal.
- [x] Le righe Drupal con `langcode = und` (Contatti) si trattano come italiano.
- [x] Pagine esistenti trovate tramite `_wpf_pagina` e lingua e aggiornate, mai ricreate; pagina con
      `status = 0` su Drupal → bozza; di nuovo pubblicata → pubblicata con lo stesso indirizzo.
- [x] Slug inglesi uguali a quelli italiani (per esempio `da-vedere`) verificati con WPML: la pagina
      inglese non deve diventare `…-2` (in `config/post.json` l'header inglese è `header-2`).
- [x] Le voci di menu verso pagine in bozza restano visibili e portano a un 404 (WordPress invalida
      solo le voci verso il cestino, `wp-includes/nav-menu.php:888`): il log le segnala come avviso.
- [x] Versione inglese per ogni pagina: di Drupal dove c'è, altrimenti la nostra traduzione (avviso nel
      log quando il testo italiano cambia) o la sola interfaccia (Mappa); collegate all'italiano in WPML con le
      funzioni esistenti (`wpf_wpml_collega`, `wpf_wpml_tutte_le_lingue`, `wpf_wpml_cancella_post`).
- [x] Cammini: pagina propria, titolo «Cammini» / inglese, sottotitolo e immagine presi da
      Itinerari (1081), aggiornati quando cambiano su Drupal.
- [x] Media scaricati da `https://www.sardegnasentieri.it/sites/default/files/…` (`public://`) e
      registrati nella libreria, riconosciuti a ogni esecuzione (per esempio dal fid Drupal) senza
      riscaricarli; alt delle immagini dal media Drupal.
- [x] Link assoluti a `www.sardegnasentieri.it` (anche con `index.php/` e `/en/`) risolti in modo
      generico: dall'indirizzo Drupal (alias o `/node/<nid>`) si ricava il nid, si cerca in WordPress
      un contenuto di qualunque tipo con quel `_drupal_nid` e quella lingua (`_drupal_langcode`) e,
      se c'è, il link diventa il suo
      indirizzo WordPress relativo; se non c'è, il link resta verso Drupal ed è elencato. Così i link
      verso contenuti importati più avanti (i «da sapere» di oc:8760) si sistemano da soli alla
      sincronizzazione successiva.
- [x] File di log per ogni esecuzione (anteprima e `--conferma`), scritto dallo script sull'host
      (non dal container, perché i file non diventino di root), escluso da git, con data e ora nel
      nome, conservati gli ultimi 60: per ogni pagina cosa è stato fatto (creata, aggiornata, messa in bozza,
      nessuna differenza) e quali campi sono cambiati; media scaricati, riusati o non scaricabili;
      link riscritti e link rimasti verso Drupal, con la pagina in cui stanno; avvisi ed errori con
      la causa. L'output a schermo ne è un riassunto e indica il percorso del log.
- [x] Tre template di contenuto di Impreza versionati in `config/post.json` («pagina con testata»,
      «pagina di testo», «mappa»): testata con colori dell'artifact «Canvas Fase 2» (tavola 8) e misure del tema Drupal, testo, allegati e
      galleria come il tema Drupal; verificati desktop e mobile, IT ed EN.
- [x] Voci di `config/menu.json` del perimetro collegate alle pagine con un riferimento indipendente
      dall'id WordPress; voci inglesi verso le pagine inglesi; su un sito ricreato
      da zero, apply e comando delle pagine portano allo stesso risultato in qualunque ordine li si
      lanci, o l'ordine è documentato e imposto.
- [x] Una seconda esecuzione subito dopo la prima non mostra differenze.
- [x] Un comando per il cron (`bin/wordpress-config.sh allinea`, richiesta del dev del 22/10) che, da un
      sito appena ricreato o già in uso, porta allo stato di riferimento senza interventi: pagine,
      menu collegati alle pagine (solo le differenze dei menu si applicano da sole), verifica finale
      con `STATO` e codice d'uscita.
- [x] [UX] Testo della testata leggibile sopra l'immagine (contrasto almeno 4,5:1, con velo scuro o
      fondo come su Drupal), anche con immagini chiare.
- [x] [UX] Le ancore interne della FAQ (35) portano alla domanda senza che l'header fisso la copra
      (`scroll-margin-top` pari all'altezza dell'header compatto e di quello mobile). Predisposto: oggi
      le ancore non hanno destinazione già su Drupal (il filtro del testo ha tolto `id` e `name`), e
      funzionano quando il cliente corregge il testo su Drupal (notes.md, «Sequenza», punto 10).
- [x] [UX] L'iframe della Mappa interattiva ha un `title` descrittivo; pulsanti per lo schermo intero
      usabili da tastiera (Esc lo chiude, finché il fuoco è nella pagina: dentro l'iframe dell'app, che
      sta su un altro dominio, i tasti vanno all'app, come su Drupal). Niente link alternativo: su Drupal non c'è e le pagine
      seguono Drupal 1 a 1 (decisione del dev, 20/10).
- [x] [UX] Allegati PDF con nome, formato e dimensione nel testo del link; immagini della galleria
      con alt; immagini sotto la piega con `loading="lazy"` e dimensioni dichiarate, per evitare
      spostamenti della pagina durante il caricamento.
- [x] Riquadro «Allegati e galleria» nell'editor delle pagine (child): allegati e galleria dalla
      libreria media, riordinabili, sugli stessi campi personalizzati (`wpf_allegati`, `wpf_galleria`)
      che legge il template; il sottotitolo si modifica nel riquadro nativo «Riassunto», acceso anche
      sulle pagine; sulle pagine che vengono da Drupal avvisa
      che la sincronizzazione sovrascrive le modifiche. Serve alla redazione dopo il lancio, come i campi
      della pagina di Drupal (richiesta del dev, 20/10).
- [x] Autore e credits delle immagini (campi «Autore» e «Credits» dei media di Drupal) importati in due
      campi dell'immagine (`wpf_autore`, `wpf_credits`), modificabili nella libreria media, e mostrati
      nella testata dietro il pulsante «Credits foto», come su Drupal (richiesta del dev, 20/10).
- [x] axe senza violazioni sulle pagine create, IT ed EN, desktop e mobile.
- [x] Documentazione: README di `wp-forestas` (uso del comando, variabili del `.env`), pagina di
      conoscenza, trappole nel `CLAUDE.md`.

## Rischi

- **Spegnimento di Drupal e ricreazione da zero.** I media si scaricano da
  `www.sardegnasentieri.it`: quando Drupal sarà spento, uno script che ricrea il sito da zero non
  troverà più i file. Il problema riguarda tutti gli import (news, da sapere…) e va affrontato con
  quelli prima del lancio (copia dei file accanto alla copia del database, o import spento con
  WordPress che diventa la fonte); qui si registra come follow-up.
- **Indirizzi cambiati.** Le 6 pagine `/pagina-base/…` (8 indirizzi, con le versioni inglesi di Note
  legali e Crediti) cambiano indirizzo; APP
  (`/pagina-base/app-sardegna-sentieri`) è indicizzata da Google e compare fra i link sotto la Home.
  Al lancio servono i redirect 301 dai vecchi indirizzi e la sitemap di WordPress, che oggi è spenta
  da `blog_public = 0`. Registrato come follow-up obbligatorio prima del lancio.
- **Link verso contenuti non ancora in WordPress.** Nei testi di FAQ, Crediti (IT ed EN) e App ci
  sono 6 link verso contenuti fuori perimetro: quattro «da sapere» (uno è `/en/node/1491`) e il form
  `/form/segnalazione`. Finché Drupal è acceso funzionano; si sistemano da soli quando oc:8760
  importa i «da sapere» **purché metta anche lui `_drupal_nid` e `_drupal_langcode`** sui contenuti
che crea. Il form
  delle segnalazioni non ha un ticket: resta verso Drupal ed è elencato nel log.
- **Copia Drupal in ricarica.** Su UAT la copia si ricarica ogni giorno alle 10:00 con `DROP
  DATABASE` e caricamento tabella per tabella (`forestas/scripts/drupal-dump-reload.sh:54-59`): in
  quei minuti le tabelle mancano o sono vuote. Senza controlli lo script metterebbe in bozza tutte le
  pagine o salverebbe testi vuoti. Mitigato dal controllo di integrità e dalla soglia sulle bozze; un
  futuro cron va messo lontano dalle 10:00.
- **Sincronizzazione dopo il lancio.** Dopo lo spegnimento di Drupal la copia resta, ferma
  all'ultimo dump (`forestas/docs/knowledge/copia-database-drupal.md:8`): rilanciare lo script
  riscriverebbe il lavoro fatto in WordPress. Mitigato da `DRUPAL_SYNC`, predefinito `off`; metterlo
  a `off` in produzione è un passo della procedura di lancio.
- [UX] **Testata diversa dal sito attuale, per scelta.** Segue l'artifact concordato col cliente e non
  la testata hero di Drupal nella sfumatura sull'immagine e nella barretta col colore della sezione:
  chi confronta con il sito attuale vede una differenza voluta nei colori e nella resa dell'immagine;
  altezze, misure e posizione del testo (in basso, centrato) sono quelle del tema Drupal.
- **Traduzioni nostre dei testi legali.** Privacy e Dichiarazione di accessibilità in inglese non
  esistono su Drupal e le abbiamo tradotte noi (`config/traduzioni/en/`): non sono testi rivisti dal
  cliente né da un legale. Vanno fatte verificare a Forestas prima del lancio; il campo `da_verificare`
  dei due file JSON lo ricorda e dice quali passaggi sono stati interpretati.

## Out of scope

- La homepage: avrà un ticket dedicato.
- Gli elenchi sotto la testata delle pagine di Sentieri, Itinerari, Cammini, Punti di interesse, Da
  sapere e News (View di Drupal): ticket dedicati, insieme ai contenuti che li riempiono.
- Eventi (1085) e Attività e comunicazioni (1086), che non sono nei menu; le 4 pagine non pubblicate.
- Segnalazioni (su Drupal è un form), Open Data e Area riservata, che su Drupal non hanno una pagina.
- La riga del crontab sul server: il comando c'è (`bin/wordpress-config.sh allinea`, aggiunto su richiesta del dev il 22/10), metterlo in cron è un passo della messa in opera.
- I redirect 301 dai vecchi indirizzi e la sitemap, da fare al lancio.
- La revisione delle nostre traduzioni inglesi (quelle che su Drupal non esistono): spetta al cliente.

## Moduli toccati

Tutto in `wp-forestas` (il codice è specifico di Forestas; `wm-package` non è coinvolto). In
`forestas` cambia solo il puntatore del submodule, dopo il merge.

- `wp-forestas/docker/scripts/config/` — nuovo script delle pagine (lettura Drupal, pagine, media,
  link, WPML), eseguito con `wp eval-file`; funzioni comuni in `comune.php` (blocco) e `wpml.php`;
  `menu.php` (voci di lingua diversa dalla pagina come link, pulizia delle voci senza menu: cambia
  l'apply di tutti i menu, header e footer compresi).
- `wp-forestas/bin/` — comando `pagine` di `wordpress-config.sh`, con lock e log; i comandi
  `export`/`apply` rinominati `esporta`/`applica` (i vecchi nomi restano come alias, con un avviso),
  anche nei messaggi di `docker/scripts/init-wordpress.sh`.
- `wp-forestas/docker/scripts/config/export.php`, `apply.php` (o `post.php`) — esclusione delle
  pagine con `_wpf_pagina`.
- `wp-forestas/.gitignore` — cartella dei log.
- `wp-forestas/cron/allinea.crontab` — riga del crontab per `bin/wordpress-config.sh allinea`, con lo
  stato dell'installazione (non installata da nessuno script).
- `wp-forestas/config/` — elenco delle pagine del perimetro (nuovo file), template di contenuto in
  `post.json`, collegamenti in `menu.json`, traduzioni nostre in `traduzioni/en/`, nome del sito
  «Sardegna Sentieri» in `sito.json`.
- `wp-forestas/themes/forestas-child/` — stili dei template e, se servono, shortcode per sottotitolo,
  allegati o galleria.
- `wp-forestas/compose.yml`, `.env-example` — `extra_hosts` e variabili `DRUPAL_DB_*`.
- `wp-forestas/README.md`, `wp-forestas/CLAUDE.md`, `wp-forestas/docs/knowledge/`, `wp-forestas/docs/howto/`
  (cron della sincronizzazione, ricreare WordPress da zero).
- `forestas/docs/howto/messa-in-opera-wordpress-uat.md` (repo padre): passo delle pagine nella messa in
  opera su UAT.
