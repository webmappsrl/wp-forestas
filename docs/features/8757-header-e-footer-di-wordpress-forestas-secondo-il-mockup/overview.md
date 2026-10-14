> Ticket: oc:8757

# Header e footer di WordPress Forestas secondo il mockup

## Cosa cambia

Il sito WordPress di Sardegna Sentieri ha header e footer **identici a quelli del sito attuale**,
sardegnasentieri.it (Drupal), al posto di quelli demo di Impreza, in italiano e in inglese. Le sole
differenze dal sito attuale sono le aggiunte concordate col cliente: nell'header una riga con sei
loghi (blocco istituzionale FESR, Forestas e CAI), nel footer i quattro loghi del blocco al posto dei
primi quattro della riga dei loghi, e la voce CAMMINI del menu. Anche le Theme
Options di Impreza su colori e tipografia sono allineate al tema Drupal.

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
  link CONTATTI, SEGNALAZIONI, FAQ, APP; a destra IT EN e il campo «Cerca nel sito» (144×36px, grigio,
  con la lente del sito attuale);
- riga principale (1rem-2.5rem sopra e sotto): logo a sinistra, largo un quarto della finestra fra
  231 e 382px; menu principale a destra in Montserrat maiuscolo (SENTIERI, ITINERARI, CAMMINI, DA
  VEDERE, DA SAPERE, MAPPA INTERATTIVA, NEWS). Le voci restano grigie; al passaggio del mouse, al focus
  e sulla voce attiva compare una barra di 3,3px sul bordo inferiore della riga, nel colore della
  sezione del sito attuale (arancio del marchio per MAPPA INTERATTIVA e CAMMINI, che non hanno una
  sezione);
- terza riga (44px), che il sito attuale non ha: i sei loghi del footer, centrati. Il blocco
  istituzionale FESR «versione 1» intero, poi Forestas e CAI con lo stesso separatore grigio del
  blocco e alti quanto la bandiera UE.

**Header, tablet e mobile (fino a 1024px)**: in alto la riga dei sei loghi, poi la riga del sito
attuale: logo, IT EN, pulsante ricerca (riquadro grigio con la lente, scuro al passaggio e quando è
aperto) e pulsante menu (riquadro arancio di 45px con la scritta «menu», che a menu aperto diventa
«chiudi» su fondo arancio scuro). Come sul sito attuale l'header resta fermo in cima allo schermo
mentre la pagina scorre (non sugli schermi alti meno di 480px né sui tablet in orizzontale); la riga
FESR esce dallo schermo con lo scorrimento, così la parte ferma è alta come in produzione. Il menu è
un pannello che entra da destra sotto l'header, largo quanto il suo contenuto (tutta la larghezza sui
telefoni) e alto fino al fondo dello schermo: voci principali, poi in fondo i link di servizio e
«Seguici su» con i social. Il resto della pagina è coperto da un velo scuro che chiude il menu al
tocco, e non scorre.

**Footer**: in cima la riga dei sei loghi del sito attuale, con la stessa disposizione (caselle
uguali larghe fino a 170px, su una riga da 768px, su due righe centrate sotto). I primi quattro
(POR FESR 2014-2020, Intense, Interreg Marittimo, Regione Sardegna) sono sostituiti dai quattro loghi
attuali del blocco istituzionale PR FESR 2021-2027 (Coesione Italia 21-27 Sardegna, «Cofinanziato
dall'Unione europea», Repubblica Italiana, Regione Autonoma della Sardegna), separati uno per uno; gli
ultimi due, Forestas e CAI, restano quelli del sito attuale. Poi
il filo arancio di 5px; a sinistra logo e «Contattaci» con `tavolo.tecnico.res@forestas.it`, a destra
«Seguici su» con cinque social (Facebook, Telegram, WhatsApp, Instagram, YouTube, come sul sito
attuale) e i link istituzionali AREA RISERVATA, NOTE LEGALI, PRIVACY, CREDITI, DICHIARAZIONE DI
ACCESSIBILITÀ; in fondo la striscia grigio scuro con «© <anno corrente> Regione Autonoma della
Sardegna». Sotto i 768px le colonne si impilano.

**Font**: Montserrat (menu, titoli, «Seguici su», link istituzionali) e Work Sans, con gli stessi file
e gli stessi due pesi (400 e 600) del sito attuale, nel child (`assets/fonts/`, licenza OFL).

**Logo**: il PNG ufficiale (1155×90, trasparente) sta nel child in `assets/img/logo.png`, non negli
uploads. Lo stampa uno shortcode `[forestas_logo]` registrato nel `functions.php` del child, usato
sia nell'header sia nel footer.

**Blocco istituzionale FESR** (Coesione Italia 21-27 Sardegna, emblema UE «Cofinanziato dall'Unione
europea», Repubblica Italiana, Regione Autonoma della Sardegna): i file ufficiali di
sardegnaprogrammazione.it. Nell'header la «versione 1» (loghi su una riga), ridotta a 1400px di
larghezza e versionata nel child, intera, seguita da Forestas e CAI: lo shortcode `[forestas_fesr]`
stampa i sei loghi. Nel footer i quattro
loghi separati, ritagliati dalla «versione 2» (testi sotto i loghi) con la stessa scala e la stessa
fascia verticale, così le proporzioni fra i loghi restano quelle del file ufficiale; li stampa,
insieme a Forestas e CAI, lo shortcode `[forestas_loghi_footer]`.

## Perché

Header e footer attuali sono quelli demo di Impreza (il footer mostra ancora «Impreza», «First
Office», «Get Started»). Il cliente non distingue fra Drupal e WordPress: il capitolato chiede che il
portale nuovo abbia la stessa UI del sito attuale, salvo modifiche concordate, e ogni differenza va
giustificata (scrum del 14/10/2026).

Il portale è cofinanziato dal PR FESR Sardegna 2021-2027: il cliente ha chiesto di rispettare le
regole di visibilità dei loghi UE. Le linee guida (Kit di strumenti per la visibilità dell'UE § 6.1;
Linee guida beneficiari PR FESR Sardegna, «Blocco istituzionale») chiedono l'emblema UE «in maniera
prominente» e mai più piccolo degli altri loghi; esporlo senza dover scorrere è una buona prassi.

Costruirli in codice ma come oggetti di Impreza tiene insieme due esigenze: restano versionati e
sopravvivono alla ricreazione del sito (regola del repo: la configurazione si cambia in locale e sta
in `config/`), e chi lavora sul sito li ritrova dove Impreza li mette.

## Requisiti

**Header e footer**

- [ ] Header e footer sono identici a quelli di sardegnasentieri.it: posizioni, dimensioni, font,
      colori e stati (passaggio del mouse, focus, voce attiva, menu aperto) coincidono con il sito in
      produzione a 1100, 1280 e 1440px, a 800 e 1024px col mouse, a 390 e 768px su touch, a menu
      chiuso e aperto. Le sole differenze sono il blocco FESR e la voce CAMMINI
- [ ] L'header «Header» (`us_header`) ha il layout desktop descritto sopra, più la terza riga con il
      blocco FESR
- [ ] L'header ha i layout tablet e mobile descritti sopra; l'hamburger compare fino a 1024px
      (`mobile_width` del menu 1025px); fino a 1024px l'header resta fermo in cima allo schermo con le
      condizioni del sito attuale (alto almeno 480px, non sui tablet in orizzontale) e la riga FESR
      esce dallo schermo con lo scorrimento
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

- [ ] Il menu `main-menu` in `config/menu.json` contiene sette voci: le sei del sito attuale più CAMMINI, tutte con URL `#`
      (le pagine di destinazione non esistono ancora); le voci demo (Home, Chi siamo, Blog, Contatti)
      spariscono — alla home si arriva dal logo
- [ ] Ogni voce del menu principale porta una classe CSS del suo tipo (`tipo-sentiero`,
      `tipo-itinerario`, `tipo-cammino`, `tipo-da-vedere`, `tipo-da-sapere`, `tipo-mappa`,
      `tipo-news`); il child dà alla barra della voce il colore della sezione del sito attuale
      (`--sase-sezione-*`: sentieri `#1271B7`, itinerari `#608D0D`, da vedere `#B7127F`, da sapere
      `#EA1F1F`, news `#F67745`). MAPPA INTERATTIVA e CAMMINI non hanno una sezione e usano l'arancio
      del marchio `#F59F1A`, come le voci senza sezione del sito attuale
- [ ] Lo stile della voce attiva si prova in locale collegando temporaneamente una voce a una pagina
      esistente; il collegamento di prova non va esportato
- [ ] I link di servizio (Contatti, Segnalazioni, FAQ, App) e quelli istituzionali del footer sono due
      menu versionati in `config/menu.json` (`servizio-menu` e `footer-menu`), con voci a `#`. Il
      footer non usa più `main-menu`
- [ ] Il menu mobile è un pannello che entra ed esce da destra (`mobile_layout="panel"`), sotto
      l'header, largo quanto il suo contenuto (al massimo tutta la larghezza) e alto dal bordo
      dell'header al fondo dello schermo, con le misure del sito attuale; se il contenuto non ci sta,
      il pannello scorre. Il resto della pagina è coperto da un velo grigio scuro all'80%, che chiude
      il menu al tocco
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

- [ ] Il file ufficiale «versione 1», i quattro loghi ritagliati dalla «versione 2» e Forestas e CAI
      sono nel child (`assets/img/`, `assets/img/loghi-header/`, `assets/img/loghi-footer/`), non
      negli uploads, senza alterare l'ordine dei loghi né le proporzioni fra loro; testo alternativo
      che nomina i quattro enti del blocco, e quello di ogni altro logo
- [ ] Header: sei loghi come nel footer. Il blocco «versione 1» intero, poi Forestas e CAI, ciascuno
      preceduto dal separatore grigio del blocco (alto il 65% del blocco, a 0,85 altezze dai loghi) e
      alto quanto la bandiera UE (82% del blocco), così l'emblema non è più piccolo degli altri loghi
- [ ] Header desktop: terza riga sotto il menu, centrata, blocco alto un decimo della larghezza del
      logo (da 25,6 a 38,2px)
- [ ] Header tablet e mobile: da soli nella riga in alto, centrati, blocco alto 28px finché la riga
      ci sta, poi più piccoli con la larghezza (14,4px a 390px, 11,8px a 320px)
- [ ] Footer: i quattro loghi sono i primi quattro della riga dei loghi, nell'ordine del blocco
      ufficiale, seguiti da Forestas e CAI; la riga ha la disposizione del sito attuale a ogni
      larghezza (verificata a 390, 768, 800, 1100 e 1440px), sopra il filo arancio e separata dal
      contenuto da una riga sottile
- [ ] In ogni posizione la bandiera UE non è più piccola del logo del sito che le sta vicino,
      verificato da 320 a 1920px in header e footer

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
- **Sette voci di menu invece di sei**: CAMMINI allarga il menu rispetto al sito attuale. Verificato:
  a 1025px le voci stanno ancora su una riga, con le misure fluide del sito attuale.
- **Uguale al sito attuale prima dei ritocchi di accessibilità**: la prima versione aveva colori più
  scuri e target più grandi del sito attuale. Ora valgono i valori di produzione; i contrasti restano
  sopra 4,5:1 (link e pulsante «chiudi» 4,58:1) e axe non segnala violazioni.
- **Font delle Theme Options fuori elenco**: la famiglia scritta con la virgola non è fra le scelte
  del pannello. Salvare la tipografia dalle Theme Options in locale può sostituirla: prima
  dell'export si controlla `git diff config/impreza.json`.
- **Primario e secondario cambiati**: arancio e blu del sito attuale sostituiscono blu e verde del
  design system in tutti gli elementi di Impreza che li usano (pulsanti, campi, contenuti demo).
  `--sar-blu` e `--sar-verde` del child ora hanno i valori scritti, non più le variabili di Impreza.
- **Emblema UE nella riga dei loghi del footer**: con le caselle uguali del sito attuale la bandiera
  UE resta più grande del logo del sito (verificato da 320 a 1920px), ma è più piccola dei loghi di
  Forestas e CAI che le stanno accanto, alti quanto la casella. Le linee guida chiedono l'emblema
  «mai più piccolo degli altri loghi»: va confermato col cliente.
- **`overflow: clip` su `.l-canvas`** fino a 1024px, al posto dell'`hidden` di Impreza, per lo
  sticky: taglia il contenuto allo stesso modo ma non è un contenitore di scorrimento.
- **Dimensione dell'emblema UE** — le linee guida vietano che sia più piccolo degli altri loghi.
  Mitigazione: altezze del blocco scelte perché la bandiera resti più alta del logo del sito a ogni
  larghezza (logo dell'header ridotto a 15px su mobile).
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
- `config/menu.json` — `main-menu` con le sette voci, `servizio-menu`, `footer-menu`, e le traduzioni
  EN
- `config/wpml.json` — `languages_order`, ed eventuali impostazioni del selettore di lingua
- `config/impreza.json` — colori, tipografia, pulsanti e focus dal tema Drupal;
  `store_gfonts_locally` spento
- `themes/forestas-child/assets/fonts/` — nuovo: Montserrat e Work Sans del sito attuale (400 e 600,
  woff2) con le due licenze OFL
- `themes/forestas-child/assets/img/cerca.svg` — nuovo: la lente del campo di ricerca del sito attuale
- `themes/forestas-child/assets/img/logo.png` — nuovo
- `themes/forestas-child/assets/img/blocco-istituzionale-fesr.png` — nuovo; il
  `blocco-istituzionale-fesr-2.png` della prima versione è tolto
- `themes/forestas-child/assets/img/loghi-header/` — nuovo: Forestas e CAI senza margini, per la riga
  dell'header
- `themes/forestas-child/assets/img/loghi-footer/` — nuovo: i sei loghi del footer (quattro ritagliati
  dal file ufficiale «versione 2», Forestas e CAI dal sito attuale)
- `themes/forestas-child/functions.php` — shortcode `[forestas_logo]`, `[forestas_anno]`,
  `[forestas_lingue]`, `[forestas_fesr]`, `[forestas_loghi_footer]`; filtro che aggiunge servizio e social al pannello mobile
  (`forestas_pannello_servizio()`, `forestas_pannello_social()`, `forestas_voce_pannello()`); link
  «Vai al contenuto»; caricamento di CSS e `menu-mobile.js` con la Version del child; funzioni
  `forestas_testo()`, `forestas_versione()`, `forestas_asset_url()`
- `themes/forestas-child/functions.php` (filtro del pannello) — menu della pagina e `main-menu`
  confrontati nella lingua predefinita, per i filtri di WPML attivi
- `themes/forestas-child/assets/js/menu-mobile.js` — nuovo: scritta e nome del pulsante, focus nel
  pannello, posizione del pannello sotto l'header, chiusura al tocco sul velo
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
