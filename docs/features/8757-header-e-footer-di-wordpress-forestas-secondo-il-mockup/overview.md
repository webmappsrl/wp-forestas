> Ticket: oc:8757

# Header e footer di WordPress Forestas secondo il mockup

## Cosa cambia

Il sito WordPress di Sardegna Sentieri ha header e footer del design system «Canvas Fase 2» al posto
di quelli demo di Impreza, in italiano e in inglese.

Header e footer restano oggetti nativi di Impreza: l'header è il template `us_header` «Header»
(Templates → Headers), il footer è il blocco riutilizzabile `us_page_block` «Footer» (Templates →
Reusable Blocks). Non si costruiscono dal pannello ma in codice: il loro contenuto è scritto in
`config/post.json`, i menu in `config/menu.json`, e `bin/wordpress-config.sh apply` li porta nel
sito.

Dal pannello sono **visibili**; si **modificano dal pannello solo in locale**, seguiti da `export` e
commit. Sui server (UAT, produzione) appartengono al repo: l'apply sovrascrive sempre gli oggetti dei
builder, quindi una modifica fatta lì va persa al primo apply.

**Header, desktop e laptop (da 1025px)** — tre righe:

- riga di servizio (36px): «Seguici su» con sei icone social, i link Contatti, Segnalazioni, FAQ,
  App, il selettore di lingua IT EN, il campo «Cerca nel sito»;
- riga principale (84px): logo a sinistra, menu principale in maiuscolo a destra (SENTIERI,
  ITINERARI, CAMMINI, DA VEDERE, DA SAPERE, MAPPA INTERATTIVA, NEWS). La voce attiva prende il colore
  del tipo di contenuto con una sottolineatura di 2px sotto il testo;
- terza riga (44px): il blocco istituzionale FESR, centrato.

**Header, tablet e mobile (fino a 1024px)**, non nel mockup: definito dal dev sul modello del sito
attuale in produzione. L'header sta in cima alla pagina e scorre con essa, come su desktop (non è
sticky). Riga in alto con il blocco istituzionale FESR a
sinistra e IT EN a destra; riga del logo con la ricerca (sotto i 600px un riquadro di 45px sul fondo
dei campi, scuro al tocco; fra 601 e 1024px il campo intero) e l'hamburger (riquadro arancio di 45px con la scritta «menu», che a menu aperto
diventa «chiudi»). Il menu è un pannello che entra ed esce da destra, a tutta larghezza sotto l'header
e alto quanto lo schermo: voci principali, poi i link di servizio, poi «Seguici su» con i social. A
menu aperto la pagina non scorre.

**Footer**: in cima il blocco istituzionale FESR centrato, separato dal contenuto della pagina da una
riga sottile; sotto, il filo oro; poi logo e social, «Contattaci» con
`tavolo.tecnico.res@forestas.it`, link istituzionali AREA RISERVATA, NOTE LEGALI, PRIVACY, CREDITI,
DICHIARAZIONE DI ACCESSIBILITÀ, striscia nera con «© <anno corrente> Regione Autonoma della
Sardegna» e «Rete Escursionistica della Sardegna · Agenzia Fo.Re.S.T.A.S.». Su mobile le colonne si
impilano.

**Logo**: il PNG ufficiale (1155×90, trasparente) sta nel child in `assets/img/logo.png`, non negli
uploads. Lo stampa uno shortcode `[forestas_logo]` registrato nel `functions.php` del child, usato
sia nell'header sia nel footer.

**Blocco istituzionale FESR** (Coesione Italia 21-27 Sardegna, emblema UE «Cofinanziato dall'Unione
europea», Repubblica Italiana, Regione Autonoma della Sardegna): i file ufficiali di
sardegnaprogrammazione.it, «versione 1» (loghi su una riga) nell'header e «versione 2» (testi sotto i
loghi) nel footer, ridotti a 1400px di larghezza e versionati nel child. Li stampa lo shortcode
`[forestas_fesr]` (`versione="2"` per il footer).

## Perché

Header e footer attuali sono quelli demo di Impreza (il footer mostra ancora «Impreza», «First
Office», «Get Started»). Il mockup «Canvas Fase 2» fissa l'identità del portale: marchio SARDEGNA
Sentieri, Forestas nella striscia di chiusura del footer, colori dal logotipo.

Il portale è cofinanziato dal PR FESR Sardegna 2021-2027: il cliente ha chiesto di rispettare le
regole di visibilità dei loghi UE. Le linee guida (Kit di strumenti per la visibilità dell'UE § 6.1;
Linee guida beneficiari PR FESR Sardegna, «Blocco istituzionale») chiedono l'emblema UE «in maniera
prominente» e mai più piccolo degli altri loghi; esporlo senza dover scorrere è una buona prassi.

Costruirli in codice ma come oggetti di Impreza tiene insieme due esigenze: restano versionati e
sopravvivono alla ricreazione del sito (regola del repo: la configurazione si cambia in locale e sta
in `config/`), e chi lavora sul sito li ritrova dove Impreza li mette.

## Requisiti

**Header e footer**

- [ ] L'header «Header» (`us_header`) ha il layout desktop del mockup: riga di servizio e riga
      principale, con gli elementi nell'ordine del mockup, più la terza riga con il blocco FESR
- [ ] L'header ha i layout tablet e mobile descritti sopra; l'hamburger compare fino a 1024px
      (`mobile_width` del menu 1025px) e su tablet e mobile l'header non è sticky: scorre con la pagina come su desktop
- [ ] Il footer «Footer» (`us_page_block`) ha la struttura del mockup e sostituisce interamente il
      contenuto demo
- [ ] Header e footer sono scritti in `config/post.json` e arrivano nel sito con
      `bin/wordpress-config.sh apply --conferma`. Il contenuto scritto a mano si apre e si salva una
      volta dal pannello in locale, poi si esporta: in `config/` va la forma normalizzata dal builder,
      così un salvataggio successivo dal pannello non produce un diff enorme. Dopo l'apply, un
      `export` non produce differenze in `config/`
- [ ] Header e footer sono visibili in Templates → Headers e Templates → Reusable Blocks

**Logo e anno**

- [ ] Il logo è servito dal child (`assets/img/logo.png`) tramite `[forestas_logo]`: `<img>` con
      `alt="Sardegna Sentieri"`, `width`/`height` espliciti, link alla home nella lingua corrente, e
      `?ver=` con la `Version` del child nell'URL, così una sostituzione del file non resta in cache
- [ ] L'anno del copyright è stampato da uno shortcode `[forestas_anno]` del child, non scritto a mano

**Menu e link**

- [ ] Il menu `main-menu` in `config/menu.json` contiene le sette voci del mockup, tutte con URL `#`
      (le pagine di destinazione non esistono ancora); le voci demo (Home, Chi siamo, Blog, Contatti)
      spariscono — alla home si arriva dal logo
- [ ] Ogni voce del menu principale porta una classe CSS del suo tipo (`tipo-sentiero`,
      `tipo-itinerario`, `tipo-cammino`, `tipo-da-vedere`, `tipo-da-sapere`, `tipo-mappa`,
      `tipo-news`); il child colora la voce attiva con il `--tipo-*` corrispondente e la
      sottolineatura di 2px. MAPPA INTERATTIVA non ha un colore di tipo e usa `--sar-blu`
- [ ] Lo stile della voce attiva si prova in locale collegando temporaneamente una voce a una pagina
      esistente; il collegamento di prova non va esportato
- [ ] I link di servizio (Contatti, Segnalazioni, FAQ, App) e quelli istituzionali del footer sono due
      menu versionati in `config/menu.json` (`servizio-menu` e `footer-menu`), con voci a `#`. Il
      footer non usa più `main-menu`
- [ ] Il menu mobile è un pannello che entra ed esce da destra (`mobile_layout="panel"`), a tutta
      larghezza sotto l'header e alto quanto lo schermo (`100dvh` meno l'header): tutto il contenuto
      resta visibile, con spazi e caratteri che si riducono con l'altezza disponibile
- [ ] Nel pannello compaiono, dopo le voci principali, i link di servizio e «Seguici su» con i social,
      senza voci nascoste in `main-menu` e senza ripetere gli URL dei social fuori da `config/`
- [ ] A menu aperto la pagina non scorre; il pulsante del menu diventa «chiudi» («close» in inglese)
- [ ] Su tablet e mobile il menu è un riquadro di 45px come sul sito attuale; la ricerca lo è sotto i
      600px (fra 601 e 1024px resta il campo intero): ricerca sul fondo
      dei campi (`--inputs-background`), scura al tocco; menu arancio `#F59F1A` con la scritta «menu»

**Social, lingua, ricerca**

- [ ] Social con l'elemento nativo di Impreza e gli URL dati dal cliente, usati così come sono:
      Facebook `https://www.facebook.com/SardegnaSentieri`,
      Instagram `https://www.instagram.com/agenziaforestas`,
      X `https://x.com/SardegnSentieri`,
      Telegram `https://telegram.me/forestas`,
      WhatsApp `https://api.whatsapp.com/send?phone=3351785162`,
      YouTube `https://www.youtube.com/channel/UCq0HJqMdbZA8ThGm7S6dwmQ`
- [ ] Il selettore di lingua legge le lingue da WPML (shortcode `[forestas_lingue]` del child): mostra
      IT EN in quest'ordine, codice breve in maiuscolo, lingua attiva in grassetto e sottolineata in `--sar-terra-testo`.
      `languages_order` in `config/wpml.json` diventa `[ "it", "en-us" ]`
- [ ] Il campo di ricerca ha l'aspetto del mockup e il segnaposto «Cerca nel sito» / «Search the
      site»; usa la ricerca standard di WordPress

**Blocco istituzionale FESR**

- [ ] I file ufficiali «versione 1» e «versione 2» sono nel child (`assets/img/`), non negli uploads,
      senza alterare ordine e proporzioni dei loghi, con un testo alternativo che nomina i quattro enti
- [ ] Header desktop: terza riga sotto il menu, centrato, alto 28px (25px fra 1025 e 1199px)
- [ ] Header tablet e mobile: nella riga in alto, a sinistra delle lingue, si restringe con lo spazio
- [ ] Footer: prima riga, centrato, alto 80px su desktop e a tutta larghezza su mobile, sopra il filo
      oro e separato dal contenuto da una riga sottile
- [ ] In ogni posizione la bandiera UE non è più piccola del logo del sito che le sta vicino: sotto i
      600px il logo dell'header scala con la larghezza (`max-width: min(193px, 48vw)`), verificato da
      320 a 600px

**Inglese**

- [ ] Versione inglese di header, footer e dei tre menu, versionata in `config/` come traduzione WPML
      dell'originale italiano, con le chiavi che l'export assegna (`<chiave>-en-us`, es.
      `us_header-header-en-us`); etichette da far validare al cliente (in particolare «Cammini»)

**Stile e accessibilità**

- [ ] I colori vengono dalle variabili già definite nel child (`--sar-*`, `--tipo-*`) o dalle Theme
      Options, mai ripetuti come valori esadecimali nel contenuto di header e footer
- [ ] La `Version` di `style.css` del child è alzata, così i browser scaricano il CSS nuovo
- [ ] [UX] Ogni icona social ha un nome accessibile che dice il social e non attribuisce l'account a
      un titolare sbagliato (es. «Facebook», «Instagram»); le icone accanto a testo visibile sono
      `aria-hidden`
- [ ] [UX] I target cliccabili (icone social, voci di servizio, hamburger) misurano almeno 24×24 px
      (WCAG 2.2 AA)
- [ ] [UX] Il focus da tastiera resta visibile su tutti i link di header e footer (`--focus` del child)
- [ ] [UX] Il logo ha dimensioni riservate (`width`/`height` o `aspect-ratio`) per non spostare il
      layout al caricamento

**Accessibilità** (Linee guida AgID, cioè EN 301 549 e WCAG 2.1 AA; verificato con axe-core)

- [ ] Nessuna violazione WCAG 2.0/2.1/2.2 A e AA di axe-core in header e footer
- [ ] Il primo elemento della pagina è il link «Vai al contenuto» / «Skip to content», visibile al
      focus, che porta al `<main id="page-content">` (WCAG 2.4.1); per chi è loggato compare sotto
      la barra di amministrazione, a ogni larghezza e anche con la pagina scesa (WCAG 2.4.11); col
      mouse sopra mantiene il contrasto
- [ ] Il nome del pulsante del menu segue la scritta visibile: «Menu» chiuso, «Chiudi» / «Close»
      aperto (WCAG 2.5.3)
- [ ] A pannello aperto Esc lo chiude e riporta il focus sul pulsante; Tab e Maiusc+Tab restano fra il
      pulsante e i link del pannello (WCAG 2.4.3, 2.4.7); se la finestra passa al desktop col pannello
      aperto, la tastiera non resta bloccata
- [ ] Testo bianco del pulsante «chiudi» con contrasto di almeno 4,5:1 (fondo `hsl(17deg 76% 40%)`,
      5,55:1; WCAG 1.4.3), anche con il mouse sopra
- [ ] Aprendo il menu, anche con la pagina scesa, il pulsante «chiudi» è tutto nello schermo, e per
      chi è loggato sotto la barra di amministrazione; il pannello parte subito sotto l'header, senza
      coprirlo (WCAG 2.4.11)
- [ ] Le tre `nav` hanno un nome distinto nella lingua della pagina («Menu principale», «Link di
      servizio», «Link istituzionali»); l'etichetta del selettore è «Lingua» / «Language»

**Documentazione**

- [ ] Trappola nel `CLAUDE.md` di `wp-forestas`: header, footer e gli altri oggetti dei builder si
      modificano solo in locale; sui server l'apply li sovrascrive sempre
- [ ] Trappola nel `CLAUDE.md`: header e footer esistono in due copie, IT ed EN, da cambiare insieme
- [ ] Trappola nel `CLAUDE.md`: la soglia 1024/1025px sta in tre posti da cambiare insieme
- [ ] Trappola nel `CLAUDE.md`: i colori al passaggio del mouse nell'header perdono contro
      `.no-touch .l-subheader.at_middle a:hover` di Impreza senza `.l-subheader` nel selettore
- [ ] Pagina di conoscenza `docs/knowledge/8757-…` e sua riga nella tabella «Conoscenza» del `CLAUDE.md`

## Rischi

- **Modifiche dal pannello dei server sovrascritte** — l'apply riscrive sempre `us_header` e
  `us_page_block` (`WPF_TIPI_BUILDER`, `docker/scripts/config/post.php:78`). Mitigazione: sui server
  header e footer appartengono al repo; documentato nell'overview e come trappola nel `CLAUDE.md`.
- **Ricerca che non trova i sentieri** — sentieri, itinerari e POI stanno nel backend Laravel, non in
  WordPress (0 `track`/`poi` e 0 articoli nel WordPress locale). Mitigazione: il campo resta con la
  ricerca standard; il collegamento al catasto è fuori da questo ticket.
- **Ordine delle lingue invertito** — `config/wpml.json` ha `en-us` prima di `it`. Mitigazione:
  l'ordine si inverte in `config/`.
- **Anno del copyright che invecchia** — mitigato con `[forestas_anno]`.
- **IT ed EN sono due copie** di header e footer: un ritocco all'italiano va rifatto sull'inglese.
  Accettato: è come WPML gestisce gli oggetti dei builder.
- **Due canali di rilascio**: il child cambia al `git pull`, `config/` solo con un apply. Se il child
  arriva prima, header e footer restano quelli demo con il CSS nuovo; se si torna indietro solo sul
  child, header e footer mostrano `[forestas_logo]` come testo. Mitigazione: rilascio e rollback
  toccano sempre entrambi, child e apply.
- **Rollback**: un `git revert` più un apply riporta header e footer italiani, ma i post inglesi
  creati restano nel sito e vanno cancellati a mano dal pannello (WP-CLI non toglie la riga di
  traduzione WPML).
- **Apply fermato dalle versioni**: se `config/versioni.json` non corrisponde alle versioni installate,
  l'apply si ferma e header e footer restano quelli demo. Mitigazione: si controlla l'esito
  dell'apply.
- [UX] Il menu maiuscolo a 12px con sette voci, alcune lunghe («MAPPA INTERATTIVA»), può andare a
  capo fra il breakpoint mobile e i 1440 px del mockup: va provato alle larghezze intermedie.
- [UX] Il terra `#CA5C30` è sotto il contrasto 4,5:1 su bianco: per icone e sottolineature della
  lingua si usa `--sar-terra-testo` come nel mockup.
- [UX] Il logo contiene la scritta «BETA», che nel mockup non c'è.
- **Dimensione dell'emblema UE** — le linee guida vietano che sia più piccolo degli altri loghi.
  Mitigazione: altezze del blocco scelte perché la bandiera resti più alta del logo del sito a ogni
  larghezza (logo dell'header ridotto a 15px su mobile).
- **Posizione del pannello mobile** — l'header scorre con la pagina e sopra può esserci la barra di
  amministrazione, fissa in cima da 601px e che scorre con la pagina sotto i 600px: il pannello si
  apre dal bordo inferiore dell'header come si vede in quel momento, calcolato da `menu-mobile.js`
  all'apertura, mai sopra l'header; la riga di «chiudi» viene prima portata in vista sotto la barra.
- Gli URL social sono quelli forniti dal cliente: il numero WhatsApp è senza prefisso internazionale
  e l'handle X è `SardegnSentieri`. Si usano così come sono.

## Out of scope

- Le pagine di destinazione dei menu e dei link (Sentieri, Itinerari, Contatti, Privacy, …): le voci
  restano `#` e si collegano quando le pagine esisteranno.
- Il collegamento del campo di ricerca alla ricerca del catasto (Elasticsearch del backend Laravel).
- Gli avvisi in cima alla home, lo slider e il resto del corpo pagina del mockup.
- La logica che decide quale voce è attiva sulle schede di sentieri, itinerari e punti di interesse:
  qui c'è solo lo stile della voce attiva, legato alla classe del tipo.
- Il link della «Dichiarazione di accessibilità» nel footer, oggi `#`: prima della messa online deve
  portare alla dichiarazione pubblicata dall'ente sul portale AgID (obbligo della Legge 4/2004);
  l'indirizzo lo fornisce il cliente.
- L'avviso di apertura in una nuova scheda sui link social (tecnica consigliata G201, non requisito).
- La pagina con descrizione, obiettivi e finanziamento del progetto che le linee guida FESR chiedono
  sul sito: è contenuto, non header o footer.
- Il blocco FESR e i loghi UE nell'app.

## Moduli toccati

Tutto nel submodule `wp-forestas`; nessuna modifica in `forestas` né in `wm-package`.

- `config/post.json` — `us_header-header` (header IT), `us_page_block-footer` (footer IT) e le
  traduzioni `-en-us`
- `config/menu.json` — `main-menu` con le sette voci, `servizio-menu`, `footer-menu`, e le traduzioni
  EN
- `config/wpml.json` — `languages_order`, ed eventuali impostazioni del selettore di lingua
- `themes/forestas-child/assets/img/logo.png` — nuovo
- `themes/forestas-child/assets/img/blocco-istituzionale-fesr.png` e
  `blocco-istituzionale-fesr-2.png` — nuovi
- `themes/forestas-child/functions.php` — shortcode `[forestas_logo]`, `[forestas_anno]`,
  `[forestas_lingue]`, `[forestas_fesr]`; filtro che aggiunge servizio e social al pannello mobile
  (`forestas_pannello_servizio()`, `forestas_pannello_social()`, `forestas_voce_pannello()`); link
  «Vai al contenuto»; caricamento di CSS e `menu-mobile.js` con la Version del child; funzioni
  `forestas_testo()`, `forestas_versione()`, `forestas_asset_url()`
- `themes/forestas-child/assets/js/menu-mobile.js` — nuovo: scritta e nome del pulsante, focus nel
  pannello, posizione del pannello sotto l'header
- `themes/forestas-child/style.css` — stili di header, pannello mobile e footer, `Version` alzata
- `docker/scripts/config/comune.php` — `us_og_image`, `_us_page_block_ids` e
  `_us_posts_per_archive_page` fra i meta calcolati che l'export esclude
- `CLAUDE.md` — trappole sugli oggetti dei builder sovrascritti dall'apply, sulla soglia
  1024/1025px in tre posti, sulle due copie IT/EN di header e footer e sull'hover di Impreza
  nell'header; riga dell'indice «Conoscenza»
- `README.md` — la `Version` del child si alza anche per JavaScript e immagini
- `docs/knowledge/inizializzazione-wordpress.md` — dipendenze dal codice interno di Impreza usate dal
  child
- `docs/knowledge/8757-header-e-footer-di-wordpress-forestas-secondo-il-mockup.md` — nuova pagina di
  conoscenza
