> Ticket: oc:8757

# Header e footer di WordPress Forestas secondo il mockup

## Cosa cambia

Il sito WordPress di Sardegna Sentieri ha header e footer **identici a quelli del sito attuale**,
sardegnasentieri.it (Drupal), al posto di quelli demo di Impreza, in italiano e in inglese. Le sole
differenze dal sito attuale sono quelle concordate col cliente e disegnate nel wireframe di oc:8783
(call del 16/10/2026, commenti fino al 19/10): logo senza «Beta»; voce CAMMINI e «DA VEDERE» che
diventa «PUNTI DI INTERESSE»; barre del menu nei colori delle entità trasmessi dal committente;
«Open Data» in fondo ai link di servizio; su desktop una riga compatta con logo e menu che resta in
alto scorrendo; nel footer sette loghi (i quattro del blocco istituzionale FESR 2021-2027 e i tre
enti della rete: Forestas Sentieristica, CAI, Sardegna Sentieri per tutti) e «Preferenze cookie»
nella striscia del copyright. Nell'header non ci sono loghi: nel wireframe sono stati tolti da tutte
le tavole, in attesa della conferma della referente UE. Anche le Theme Options di Impreza su colori e
tipografia sono allineate al tema Drupal.

Nella prima versione header e footer seguivano il prototipo «Canvas Fase 2»; allo scrum del
14/10/2026 è emerso che il capitolato chiede la stessa UI del sito Drupal, salvo modifiche concordate,
e che il prototipo se ne discostava (voce del menu al passaggio del mouse, pesi e colori dei testi).
Le misure e i colori vengono dal tema Drupal «sardegnasentieri» (sorgente SCSS nella copia del
repository caricata su UAT, `/root/sardegnasentieri.zip`, e CSS in produzione) e sono verificati
elemento per elemento sul sito in produzione.

Header e footer restano oggetti nativi di Impreza: l'header è il template `us_header` «Header»
(Templates → Headers), il footer è il blocco riutilizzabile `us_page_block` «Footer» (Templates →
Reusable Blocks). Non si costruiscono dal pannello ma in codice: il loro contenuto è scritto in
`config/post.json`, i menu in `config/menu.json`, e `bin/wordpress-config.sh apply` li porta nel
sito.

Dal pannello sono **visibili**; si **modificano dal pannello solo in locale**, seguiti da `export` e
commit. Sui server (UAT, produzione) appartengono al repo: l'apply sovrascrive sempre gli oggetti dei
builder, quindi una modifica fatta lì va persa al primo apply.

**Header, desktop e laptop (da 1025px)** — contenuto largo al massimo 1294px, con 20px ai lati:

- riga di servizio (36px più 5px sopra e sotto): a sinistra «Seguici su» con sei icone social e i
  link CONTATTI, SEGNALAZIONI, FAQ, APP, OPEN DATA (con l'icona provvisoria di un database); a destra
  IT EN e il campo «Cerca nel sito» (144×36px, grigio, con la lente del sito attuale);
- riga principale (1rem-2.5rem sopra e sotto): logo a sinistra, largo un quarto della finestra fra
  231 e 382px; menu principale a destra in Montserrat maiuscolo (SENTIERI, ITINERARI, CAMMINI, PUNTI
  DI INTERESSE, DA SAPERE, MAPPA INTERATTIVA, NEWS). Le voci restano grigie; al passaggio del mouse, al
  focus e sulla voce attiva compare una barra di 3,3px sul bordo inferiore della riga, nel colore
  dell'entità (sentieri `#DE1B0D`, itinerari `#58810B`, cammini `#1A416F`, punti di interesse
  `#B7127F`, da sapere `#0F6E6A`, mappa `#F59F1A`, news `#C2410C`). Fra 1025 e circa 1180px, e fra
  1295 e 1340px dove il carattere cresce, spazi e padding delle voci si riducono con la finestra
  perché «PUNTI DI INTERESSE» ci stia.

**Riga compatta su desktop** (da 1025px, finestra alta più di 500px): scorrendo, quando l'header
intero è uscito dallo schermo, resta fisso in alto ridotto a una riga alta 47px con il logo largo
170px e il menu a 12px (voce attiva più scura, barra di 2px sotto il testo), con un'ombra sotto;
tornando su, l'header torna intero. Il contenuto non si sposta, e `scroll-padding-top` riserva in
cima lo spazio della riga, così l'elemento che riceve il focus non ci finisce sotto (WCAG 2.4.11).
Con la finestra bassa (zoom alto) la riga non resta fissa (WCAG 1.4.10).

**Header, tablet e mobile (fino a 1024px)**: la riga del sito attuale: logo, IT EN, pulsante ricerca (riquadro grigio con la lente, scuro al passaggio e quando è
aperto) e pulsante menu (riquadro arancio di 45px con la scritta «menu», che a menu aperto diventa
«chiudi» su fondo arancio scuro). Come sul sito attuale l'header resta fermo in cima allo schermo
mentre la pagina scorre (non sugli schermi alti meno di 480px né sui dispositivi con lo schermo fra
576 e 1024px tenuti in orizzontale). Il menu è un pannello che entra da destra sotto l'header, largo quanto il suo contenuto (tutta la larghezza sui
telefoni) e alto fino al fondo dello schermo: voci principali, poi in fondo i link di servizio e
«Seguici su» con i social. Il resto della pagina è coperto da un velo scuro, e non scorre: un tocco
sul velo chiude il menu senza attivare il link che sta sotto.

**Footer**: in cima la riga dei loghi del sito attuale, con sette loghi: i quattro del blocco
istituzionale PR FESR 2021-2027 (Coesione Italia 21-27 Sardegna, «Cofinanziato dall'Unione europea»,
Repubblica Italiana, Regione Autonoma della Sardegna), separati uno per uno, al posto dei quattro
vecchi del sito attuale; poi il nuovo logo di Forestas Sentieristica, CAI e «Sardegna Sentieri per
tutti». Caselle larghe fino a 170px, su una riga da 768px; sotto, due righe su una griglia di 12
colonne: i quattro finanziatori, più piccoli, e i tre enti della rete. Poi
il filo arancio di 5px; a sinistra logo e «Contattaci» con `tavolo.tecnico.res@forestas.it`, a destra
«Seguici su» con cinque social (Facebook, Telegram, WhatsApp, Instagram, YouTube, come sul sito
attuale) e i link istituzionali AREA RISERVATA, NOTE LEGALI, PRIVACY, CREDITI, DICHIARAZIONE DI
ACCESSIBILITÀ; in fondo la striscia grigio scuro con «© <anno corrente> Regione Autonoma della
Sardegna» e, a destra, il link «Preferenze cookie», con sotto lo spazio per i pulsanti fissi
nell'angolo dello schermo. Sotto i 768px le colonne si impilano.

**Font**: Montserrat (menu, titoli, «Seguici su», link istituzionali) e Work Sans, con gli stessi file
e gli stessi due pesi (400 e 600) del sito attuale, nel child (`assets/fonts/`, licenza OFL).

**Logo**: il PNG del wireframe (1442×120, trasparente, senza «Beta») sta nel child in `assets/img/logo.png`, non negli
uploads. Lo stampa uno shortcode `[forestas_logo]` registrato nel `functions.php` del child, usato
sia nell'header sia nel footer.

**Blocco istituzionale FESR** (Coesione Italia 21-27 Sardegna, emblema UE «Cofinanziato dall'Unione
europea», Repubblica Italiana, Regione Autonoma della Sardegna): i file ufficiali di
sardegnaprogrammazione.it. Nel footer i quattro loghi separati, ritagliati dalla «versione 2» (testi
sotto i loghi) con la stessa scala e la stessa fascia verticale, così le proporzioni fra i loghi
restano quelle del file ufficiale; li stampa, insieme ai tre enti della rete, lo shortcode
`[forestas_loghi_footer]`.

## Perché

Header e footer attuali sono quelli demo di Impreza (il footer mostra ancora «Impreza», «First
Office», «Get Started»). Il cliente non distingue fra Drupal e WordPress: il capitolato chiede che il
portale nuovo abbia la stessa UI del sito attuale, salvo modifiche concordate, e ogni differenza va
giustificata (scrum del 14/10/2026).

Il portale è cofinanziato dal PR FESR Sardegna 2021-2027: il cliente ha chiesto di rispettare le
regole di visibilità dei loghi UE. Le linee guida (Kit di strumenti per la visibilità dell'UE § 6.1;
Linee guida beneficiari PR FESR Sardegna, «Blocco istituzionale») chiedono l'emblema UE «in maniera
prominente» e mai più piccolo degli altri loghi; esporlo senza dover scorrere è una buona prassi.
Nel wireframe oc:8783 i loghi dei finanziatori stanno solo nel footer, perché nell'intestazione erano
un doppione che appesantiva la pagina; la scelta aspetta la conferma della referente UE.

Costruirli in codice ma come oggetti di Impreza tiene insieme due esigenze: restano versionati e
sopravvivono alla ricreazione del sito (regola del repo: la configurazione si cambia in locale e sta
in `config/`), e chi lavora sul sito li ritrova dove Impreza li mette.

## Requisiti

**Header e footer**

- [ ] Header e footer sono identici a quelli di sardegnasentieri.it: posizioni, dimensioni, font,
      colori e stati (passaggio del mouse, focus, voce attiva, menu aperto) coincidono con il sito in
      produzione a 1100, 1280 e 1440px, a 800 e 1024px col mouse, a 390 e 768px su touch, a menu
      chiuso e aperto. Le sole differenze sono quelle del wireframe oc:8783 elencate in «Cosa cambia»
- [ ] L'header «Header» (`us_header`) ha il layout desktop descritto sopra, su due righe, senza loghi
- [ ] Su desktop la riga compatta descritta sopra: compare quando l'header intero esce dallo
      schermo, il contenuto non si sposta, non c'è con la finestra alta 500px o meno
- [ ] Il menu principale sta su una riga accanto al logo a ogni larghezza da 1025px, con almeno 17px
      di margine per la barra di scorrimento di Windows
- [ ] L'header ha i layout tablet e mobile descritti sopra; l'hamburger compare fino a 1024px
      (`mobile_width` del menu 1025px); fino a 1024px l'header resta fermo in cima allo schermo con le
      condizioni del sito attuale (alto almeno 480px, non sui tablet in orizzontale)
- [ ] Il footer «Footer» (`us_page_block`) ha la struttura descritta sopra e sostituisce interamente
      il contenuto demo
- [ ] Con i filtri di WPML attivi (primo passo del wizard completato, come su UAT) l'apply crea e
      aggiorna i menu tradotti con le loro voci, e l'export li legge senza mescolarli agli originali;
      l'apply completa quel passo anche su un sito configurato dagli script, così il locale si
      comporta come UAT
- [ ] Header e footer sono scritti in `config/post.json` e arrivano nel sito con
      `bin/wordpress-config.sh apply --conferma`. Il contenuto scritto a mano si apre e si salva una
      volta dal pannello in locale, poi si esporta: in `config/` va la forma normalizzata dal builder,
      così un salvataggio successivo dal pannello non produce un diff enorme. Dopo l'apply, un
      `export` non produce differenze in `config/`. Fatto per header e footer IT; le copie EN restano
      nella forma scritta in codice, perché in locale WPML non è registrato e il pannello non salva
      le traduzioni (rischio accettato dal dev, `notes.md`, «Follow-up»)
- [ ] Header e footer sono visibili in Templates → Headers e Templates → Reusable Blocks

**Logo e anno**

- [ ] Il logo è servito dal child (`assets/img/logo.png`) tramite `[forestas_logo]`: `<img>` con
      `alt="Sardegna Sentieri"`, `width`/`height` espliciti, link alla home nella lingua corrente, e
      `?ver=` con la `Version` del child nell'URL, così una sostituzione del file non resta in cache
- [ ] L'anno del copyright è stampato da uno shortcode `[forestas_anno]` del child, non scritto a mano

**Menu e link**

- [ ] Il menu `main-menu` in `config/menu.json` contiene sette voci: le sei del sito attuale più
      CAMMINI, con «PUNTI DI INTERESSE» («POINTS OF INTEREST») al posto di «DA VEDERE», tutte con URL `#`
      (le pagine di destinazione non esistono ancora); le voci demo (Home, Chi siamo, Blog, Contatti)
      spariscono — alla home si arriva dal logo
- [ ] Ogni voce del menu principale porta una classe CSS del suo tipo (`tipo-sentiero`,
      `tipo-itinerario`, `tipo-cammino`, `tipo-punti-di-interesse`, `tipo-da-sapere`, `tipo-mappa`,
      `tipo-news`); il child dà alla barra della voce il colore dell'entità del wireframe
      (`--sase-sezione-*`, valori in «Cosa cambia»), almeno 3:1 sul bianco (WCAG 1.4.11)
- [ ] Lo stile della voce attiva si prova in locale collegando temporaneamente una voce a una pagina
      esistente; il collegamento di prova non va esportato
- [ ] I link di servizio (Contatti, Segnalazioni, FAQ, App, Open Data) e quelli istituzionali del footer sono due
      menu versionati in `config/menu.json` (`servizio-menu` e `footer-menu`), con voci a `#`. Il
      footer non usa più `main-menu`
- [ ] «Open Data» è l'ultima voce di `servizio-menu` (anche in inglese), con la classe
      `forestas-open-data` che dà l'icona provvisoria, nella riga di servizio e nel pannello mobile;
      l'icona è decorativa e prende il colore del testo
- [ ] Nella striscia del copyright, a destra, il link «Preferenze cookie» / «Cookie preferences»
      (shortcode `[forestas_preferenze_cookie]`), oggi a `#`; sotto la striscia lo spazio per i
      pulsanti fissi nell'angolo (88px, 80px sotto i 600px), così in fondo alla pagina il «torna su»
      di Impreza non copre il link
- [ ] Il menu mobile è un pannello che entra ed esce da destra (`mobile_layout="panel"`), sotto
      l'header, largo quanto il suo contenuto (al massimo tutta la larghezza) e alto dal bordo
      dell'header al fondo dello schermo, con le misure del sito attuale; se il contenuto non ci sta,
      il pannello scorre. Il resto della pagina è coperto da un velo grigio scuro all'80%: un tocco
      sul velo chiude il menu e non attiva il link che sta sotto (provato a 800 e 1024px su touch,
      con un link sotto il punto toccato), e la barra di amministrazione resta cliccabile
- [ ] Nel pannello compaiono, dopo le voci principali, i link di servizio e «Seguici su» con i social,
      senza voci nascoste in `main-menu` e senza ripetere gli URL dei social fuori da `config/`
- [ ] A menu aperto la pagina non scorre; il pulsante del menu diventa «chiudi» («close» in inglese)
- [ ] Su tablet e mobile menu e ricerca sono riquadri come sul sito attuale, a ogni larghezza fino a
      1024px: ricerca grigia `#F5F5F5` di 3em (45px sui touch screen), scura `#333333` al passaggio e
      quando è aperta; menu arancio `#F59F1A` di 45px con la scritta «menu», arancio scuro `hsl(17deg
      76% 45%)` con testo bianco al passaggio e quando è aperto

**Social, lingua, ricerca**

- [ ] Social con l'elemento nativo di Impreza e gli URL dati dal cliente, usati così come sono:
      Facebook `https://www.facebook.com/SardegnaSentieri`,
      Instagram `https://www.instagram.com/agenziaforestas`,
      X `https://x.com/SardegnSentieri`,
      Telegram `https://telegram.me/forestas`,
      WhatsApp `https://api.whatsapp.com/send?phone=3351785162`,
      YouTube `https://www.youtube.com/channel/UCq0HJqMdbZA8ThGm7S6dwmQ`
- [ ] Icone e ordine dei social come sul sito attuale: nell'header Facebook, Instagram, X, Telegram,
      WhatsApp, YouTube; nel footer Facebook, Telegram, WhatsApp, Instagram, YouTube. X ha l'icona
      dell'uccellino, come in produzione, ma il link resta quello dato dal cliente
- [ ] Il selettore di lingua legge le lingue da WPML (shortcode `[forestas_lingue]` del child): mostra
      IT EN in quest'ordine, codice breve in maiuscolo; la lingua attiva è in nero con una barra
      arancio scuro di 2px sul bordo inferiore della riga, come sul sito attuale.
      `languages_order` in `config/wpml.json` diventa `[ "it", "en-us" ]`
- [ ] Il campo di ricerca ha l'aspetto del sito attuale e il segnaposto «Cerca nel sito» / «Search
      the site»; usa la ricerca standard di WordPress

**Blocco istituzionale FESR**

- [ ] I quattro loghi ritagliati dalla «versione 2» e i tre enti della rete sono nel child
      (`assets/img/loghi-footer/`, PNG larghi 342px), non negli uploads, senza alterare l'ordine dei
      loghi né le proporzioni fra loro; ogni logo ha il suo testo alternativo, in italiano e in
      inglese
- [ ] Nell'header non ci sono loghi, a nessuna larghezza
- [ ] Footer: i quattro loghi sono i primi quattro della riga dei loghi, nell'ordine del blocco
      ufficiale, seguiti da Forestas Sentieristica, CAI e Sardegna Sentieri per tutti; da 768px su una
      riga, sotto in due righe (quattro più tre); sopra il filo arancio e separata dal contenuto da
      una riga sottile

**Inglese**

- [ ] Versione inglese di header, footer e dei tre menu, versionata in `config/` come traduzione WPML
      dell'originale italiano, con le chiavi che l'export assegna (`<chiave>-en-us`, es.
      `us_header-header-en-us`); etichette da far validare al cliente (in particolare «Cammini»)

**Stile e accessibilità**

- [ ] I colori di header e footer vengono dalle variabili `--sase-*` del child, con i valori del
      tema Drupal, o dalle Theme Options; mai ripetuti come valori esadecimali nel contenuto di header
      e footer
- [ ] La `Version` di `style.css` del child è alzata, così i browser scaricano il CSS nuovo
- [ ] [UX] Ogni icona social ha un nome accessibile che dice il social e non attribuisce l'account a
      un titolare sbagliato (es. «Facebook», «Instagram»); le icone accanto a testo visibile sono
      `aria-hidden`
- [ ] [UX] I target cliccabili rispettano WCAG 2.2 2.5.8 con le misure del sito attuale (nessuna
      violazione di axe): i link testuali più bassi di 24px rientrano nell'eccezione della spaziatura
- [ ] [UX] Il focus da tastiera resta visibile su tutti i link di header e footer, come sul sito
      attuale: contorno `#0089C7` di 3px staccato di 3px, con un anello bianco
- [ ] [UX] Il logo ha dimensioni riservate (`width`/`height` o `aspect-ratio`) per non spostare il
      layout al caricamento

**Accessibilità** (Linee guida AgID, cioè EN 301 549 e WCAG 2.1 AA; verificato con axe-core)

- [ ] Nessuna violazione WCAG 2.0/2.1/2.2 A e AA di axe-core in header e footer
- [ ] Per chi non è loggato il primo elemento della pagina è il link «Vai al contenuto» / «Skip to
      content», visibile al focus, che porta al `<main id="page-content">` (WCAG 2.4.1). Per chi è
      loggato viene dopo il link di WordPress «Vai alla barra degli strumenti» (comportamento del
      core, non si tocca) e compare sotto la barra di amministrazione, a ogni larghezza e anche con
      la pagina scesa (WCAG 2.4.11); col mouse sopra mantiene il contrasto
- [ ] Il nome del pulsante del menu segue la scritta visibile: «Menu» chiuso, «Chiudi» / «Close»
      aperto (WCAG 2.5.3)
- [ ] A pannello aperto Esc lo chiude e riporta il focus sul pulsante; Tab e Maiusc+Tab restano fra il
      pulsante e i link del pannello (WCAG 2.4.3, 2.4.7); se la finestra passa al desktop col pannello
      aperto, la tastiera non resta bloccata
- [ ] Testo bianco del pulsante «chiudi» con contrasto di almeno 4,5:1 (fondo `hsl(17deg 76% 45%)`
      del sito attuale, 4,58:1; WCAG 1.4.3), anche con il mouse sopra
- [ ] Aprendo il menu, anche con la pagina scesa, il pulsante «chiudi» è tutto nello schermo, e per
      chi è loggato sotto la barra di amministrazione; il pannello parte subito sotto l'header, senza
      coprirlo (WCAG 2.4.11)
- [ ] Le tre `nav` hanno un nome distinto nella lingua della pagina («Menu principale», «Link di
      servizio», «Link istituzionali»); l'etichetta del selettore è «Lingua» / «Language»

**Theme Options di Impreza: colori e tipografia** (`config/impreza.json`, dal tema Drupal)

- [ ] Colori del contenuto e del contenuto alternativo: testo e titoli `#4D4D4D`, testo attenuato
      `#595959`, link `#CA4D1C` (`hsl(17deg 76% 45%)`) e al passaggio `#D63D00` (`hsl(17deg 100%
      42%)`), primario `#F59F1A`, secondario `#16497B`, bordi `#F0F0F0`, fondo `#FFFFFF` e alternativo
      `#F5F5F5`, velo `rgba(51,51,51,0.8)`; fondo della pagina bianco
- [ ] Header: fondi bianchi, testo `#4D4D4D` anche al passaggio del mouse; footer bianco con testo e
      link `#4D4D4D`; sotto-footer `#333333` con testo bianco; barra del browser mobile `#F59F1A`
- [ ] Focus da tastiera `#0089C7`, 3px, staccato di 3px
- [ ] Testo: Work Sans 400, `1rem`, interlinea 1.4, grassetto 700 (che cade sul 600, l'unico peso
      pesante del sito attuale); radice a 14px sotto i 960px, come sul sito attuale (nel child)
- [ ] Titoli in Montserrat 600 con le misure fluide del tema Drupal: h1 come `.sase-h1`, h2-h6 come i
      titoli nel corpo delle pagine (`.sase-page-body`), con il loro spazio sotto
- [ ] Pulsanti: «Pulsante predefinito» come il primario del sito attuale (arancio `#F59F1A`, testo
      `#333333`, al passaggio `#D63D00` con testo bianco), «Contorno» come il pulsante chiaro (bianco con
      bordo e testo `#4D4D4D`, al passaggio arancio); Work Sans 600 `1rem`, bordo 1px, raggio 0.25em
- [ ] I font sono quelli del child: nelle Theme Options la famiglia è scritta con la virgola
      (`"Montserrat", sans-serif`), così Impreza non la scarica da Google; «Store Google Fonts locally»
      è spento, perché non c'è più nessun Google Font da scaricare
- [ ] Dopo l'apply un `export` non produce differenze e un secondo apply dice «nessuna differenza»

**Documentazione**

- [ ] Regola nel `CLAUDE.md` (Regole del repo): per ogni lavoro su aspetto e comportamento del sito la
      fonte di design è il frontend Drupal di sardegnasentieri.it, con la copia del repository sul
      server UAT (`/root/sardegnasentieri.zip`: come scaricarla, MD5, cartelle del tema da usare)

- [ ] Trappola nel `CLAUDE.md` di `wp-forestas`: header, footer e gli altri oggetti dei builder si
      modificano solo in locale; sui server l'apply li sovrascrive sempre
- [ ] Trappola nel `CLAUDE.md`: header e footer esistono in due copie, IT ed EN, da cambiare insieme
- [ ] Trappola nel `CLAUDE.md`: la soglia 1024/1025px sta in tre posti da cambiare insieme
- [ ] Trappola nel `CLAUDE.md`: i colori al passaggio del mouse nell'header perdono contro
      `.no-touch .l-subheader.at_middle a:hover` di Impreza senza `.l-subheader` nel selettore
- [ ] Pagina di conoscenza `docs/knowledge/8757-…` e sua riga nella tabella «Conoscenza» del `CLAUDE.md`
- [ ] Trappole nel `CLAUDE.md` emerse allineando header e footer al sito attuale: lo sticky richiede
      `overflow: clip` su `.l-canvas`; le famiglie dei font nelle Theme Options vanno scritte con la
      virgola; le regole di Impreza da battere (margini logici delle celle, `--hwrapper-gap` in linea,
      `background: none !important` del pulsante di ricerca, menu verticale responsive del footer)

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
- **Rollback**: l'apply non cancella né i post né i menu assenti da `config/`, quindi header e
  footer inglesi e cinque menu restano nel sito e vanno tolti a mano. Mitigazione: procedura a passi
  nella pagina di conoscenza `docs/knowledge/8757-…`, «Come funziona oggi».
- **Apply fermato dalle versioni**: se `config/versioni.json` non corrisponde alle versioni installate,
  l'apply si ferma e header e footer restano quelli demo. Mitigazione: si controlla l'esito
  dell'apply.
- **Sette voci di menu invece di sei, e più lunghe**: CAMMINI e «PUNTI DI INTERESSE» allargano il menu
  rispetto al sito attuale, che fra 1025 e 1160px e fra 1295 e 1325px non entrerebbe più accanto al
  logo. Mitigazione: in quelle fasce spazi e padding delle voci si riducono con la finestra (da 6px,
  come nel wireframe); verificato ogni 15px da 1025 a 1440px, almeno 20px di margine.
- **Uguale al sito attuale prima dei ritocchi di accessibilità**: la prima versione aveva colori più
  scuri e target più grandi del sito attuale. Ora valgono i valori di produzione; i contrasti restano
  sopra 4,5:1 (link e pulsante «chiudi» 4,58:1) e axe non segnala violazioni.
- **Font delle Theme Options fuori elenco**: la famiglia scritta con la virgola non è fra le scelte
  del pannello. Salvare la tipografia dalle Theme Options in locale può sostituirla: prima
  dell'export si controlla `git diff config/impreza.json`.
- **Primario e secondario cambiati**: arancio e blu del sito attuale sostituiscono blu e verde del
  design system in tutti gli elementi di Impreza che li usano (pulsanti, campi, contenuti demo). Le
  variabili del prototipo nel child (`--sar-*`, `--tipo-*` e le altre), che vi rimandavano o non
  erano più usate, sono state tolte.
- **Emblema UE solo nel footer**: senza i loghi nell'header l'emblema non si vede senza scorrere, e
  nella riga del footer è più piccolo dei loghi degli enti che gli stanno accanto. Le linee guida
  chiedono l'emblema «in maniera prominente» e «mai più piccolo degli altri loghi»: è la scelta del
  wireframe, in attesa della conferma della referente UE.
- **Riga compatta fatta dal child, non dallo sticky di Impreza**: lo sticky di Impreza calcola lo
  spazio in cima dalle altezze delle opzioni dell'header, mentre le righe del child sono alte quanto
  il loro contenuto. Lo script `header-compatto.js` misura l'header vero e lascia in cima lo stesso
  spazio; se non gira, l'header resta intero e scorre con la pagina come oggi.
- **«Preferenze cookie» porta a `#`**: il gestore dei cookie non è ancora scelto. Quando ci sarà, il
  link va collegato (con iubenda basta la classe `iubenda-cs-preferences-link`).
- **`overflow: clip` su `.l-canvas`** fino a 1024px, al posto dell'`hidden` di Impreza, per lo
  sticky: taglia il contenuto allo stesso modo ma non è un contenitore di scorrimento.
- **Contrasti delle Theme Options, fedeli al sito attuale ma sotto le soglie WCAG** fuori da header e
  footer (trovati in review): testo `#4D4D4D` sull'arancio del pulsante «Contorno» al passaggio
  3,97:1; link `#CA4D1C` sul fondo alternativo `#F5F5F5` 4,20:1, al passaggio `#D63D00` 4,26:1;
  icona bianca del pulsante «torna su» sull'arancio 2,1:1 (sotto 3:1). Sono i colori di produzione:
  vanno fatti sapere al cliente, che decide se correggerli.
- **Posizione del pannello mobile** — l'header è fermo in cima, ma non sugli schermi bassi né sui
  tablet in orizzontale, e sopra può esserci la barra di amministrazione, fissa in cima da 601px e
  che scorre con la pagina sotto i 600px: il pannello si apre dal bordo inferiore dell'header come si
  vede in quel momento, calcolato da `menu-mobile.js` all'apertura, mai sopra l'header; la riga di
  «chiudi» viene prima portata in vista sotto la barra.
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
- Il gestore dei cookie (banner e preferenze) e il widget di accessibilità fissi nell'angolo dello
  schermo, che il wireframe mostra come esempi: qui c'è solo il link «Preferenze cookie» e lo spazio
  sotto la striscia del copyright.
- La pagina «Open Data» e l'icona ufficiale dei dati aperti: la voce porta a `#` e l'icona è
  provvisoria.

## Moduli toccati

Tutto nel submodule `wp-forestas`, tranne l'howto di UAT in `forestas` (in fondo); nessuna modifica in
`wm-package`.

- `docker/scripts/config/wpml.php` — primo passo del wizard di WPML completato dall'apply;
  `wpf_wpml_tutte_le_lingue()` per leggere e scrivere i menu tradotti con i filtri di WPML attivi
- `docker/scripts/config/apply.php`, `export.php`, `ritratto.php` — menu letti e scritti con
  `wpf_wpml_tutte_le_lingue()`
- `docker/configs/php/wordpress.ini` — errori PHP nei log del container, mai in pagina

- `config/post.json` — `us_header-header` (header IT), `us_page_block-footer` (footer IT) e le
  traduzioni `-en-us`
- `config/menu.json` — `main-menu` con le sette voci, `servizio-menu` con «Open Data», `footer-menu`,
  e le traduzioni EN
- `config/wpml.json` — `languages_order`, ed eventuali impostazioni del selettore di lingua
- `config/impreza.json` — colori, tipografia, pulsanti e focus dal tema Drupal;
  `store_gfonts_locally` spento
- `themes/forestas-child/assets/fonts/` — nuovo: Montserrat e Work Sans del sito attuale (400 e 600,
  woff2) con le due licenze OFL
- `themes/forestas-child/assets/img/cerca.svg` — nuovo: la lente del campo di ricerca del sito attuale
- `themes/forestas-child/assets/img/logo.png` — nuovo, senza «Beta» (1442×120)
- `themes/forestas-child/assets/img/open-data.svg` — nuovo: l'icona provvisoria di Open Data
- `themes/forestas-child/assets/img/loghi-footer/` — nuovo: i sette loghi del footer (quattro
  ritagliati dal file ufficiale «versione 2», CAI dal sito attuale, Forestas Sentieristica e Sardegna
  Sentieri per tutti dal wireframe oc:8783)
- `themes/forestas-child/functions.php` — shortcode `[forestas_logo]`, `[forestas_anno]`,
  `[forestas_lingue]`, `[forestas_loghi_footer]`, `[forestas_preferenze_cookie]`, helper
  `forestas_immagine()`; filtro che aggiunge servizio e social al pannello mobile
  (`forestas_pannello_servizio()`, `forestas_pannello_social()`, `forestas_voce_pannello()`); link
  «Vai al contenuto»; caricamento di CSS, `menu-mobile.js` e `header-compatto.js` con la Version del
  child; classi delle voci di servizio nel pannello (icona di Open Data); funzioni
  `forestas_testo()`, `forestas_versione()`, `forestas_asset_url()`
- `themes/forestas-child/functions.php` (filtro del pannello) — menu della pagina e `main-menu`
  confrontati nella lingua predefinita, per i filtri di WPML attivi
- `themes/forestas-child/assets/js/menu-mobile.js` — nuovo: scritta e nome del pulsante, focus nel
  pannello, posizione del pannello sotto l'header, tocco sul velo che non attiva il link sotto
- `themes/forestas-child/assets/js/header-compatto.js` — nuovo: riga compatta su desktop
- `themes/forestas-child/style.css` — font, variabili `--sase-*` del sito attuale, stili di header,
  pannello mobile e footer, radice a 14px sotto i 960px, `Version` alzata
- `docker/scripts/config/comune.php` — `us_og_image`, `_us_page_block_ids` e
  `_us_posts_per_archive_page` fra i meta calcolati che l'export esclude
- `CLAUDE.md` — regola sulla fonte di design (il frontend Drupal, con la copia del repository su
  UAT); trappole sugli oggetti dei builder sovrascritti dall'apply, sulla soglia 1024/1025px in tre
  posti, sulle due copie IT/EN di header e footer, sull'hover di Impreza nell'header, sull'identità
  col sito attuale, sullo sticky e sui font delle Theme Options; riga dell'indice «Conoscenza»
- `README.md` — la `Version` del child si alza anche per JavaScript e immagini; `wordpress.ini` nella tabella e gli errori PHP in `docker logs`
- `docs/knowledge/inizializzazione-wordpress.md` — dipendenze dal codice interno di Impreza usate dal
  child; errori PHP nei log del container
- `docs/knowledge/8757-header-e-footer-di-wordpress-forestas-secondo-il-mockup.md` — nuova pagina di
  conoscenza
- Fuori da questo repo, in `forestas`: `docs/howto/messa-in-opera-wordpress-uat.md` — child e
  configurazione da rilasciare insieme, con rimando alla pagina di conoscenza (commit deciso dal dev)
