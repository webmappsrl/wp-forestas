> Ticket: oc:8757

# Notes — Header e footer di WordPress Forestas secondo il mockup

## Divergenze dal piano, task per task

### Task 3: header italiano

- **Selettore di lingua**: non è l'elemento `dropdown` di Impreza con `source=wpml`, che sa mostrare
  solo bandiere o nomi lunghi delle lingue. È uno shortcode del child, `[forestas_lingue]`, dentro un
  elemento `html`: legge le lingue da WPML (`wpml_active_languages`) e stampa «IT EN» in linea come
  nel mockup, con la lingua corrente in grassetto.
- **Link di servizio su tablet e mobile**: il pannello dell'hamburger mostra solo il menu
  dell'elemento. Un filtro `wp_nav_menu_items` del child aggiunge in coda al menu principale le voci
  di `servizio-menu` (nella lingua corrente), con la classe `forestas-solo-mobile` che le nasconde su
  desktop. In `main-menu` non ci sono voci nascoste.
- **Social su mobile**: in un primo momento solo nel footer, per non ripetere gli URL in PHP fuori da
  `config/`. Superato dopo la review: ora sono anche nel pannello del menu, letti dall'header (vedi
  «Decisioni»).

### Task 5: stili del child

- **Hamburger fino a 1024px** (`mobile_width` del menu `1025px`, non 900px): fra 900 e 1024px il
  menu desktop più il campo di ricerca usciva dallo schermo. Impreza passa all'hamburger con
  `innerWidth < mobile_width`, quindi per coprire 1024 il valore è 1025.
- **Fra 1025 e 1199px** logo a 22px e meno spazio fra le voci: altrimenti NEWS usciva dallo schermo.
- **Ricerca su tablet** (601–1024px): resta il campo intero, non la sola icona chiesta dall'overview;
  su mobile è l'icona.
- **Colore dei link di servizio:** il piano chiedeva `--ink-2`, variabile del mockup che nel child non
  esiste. I link prendono il colore del testo della riga in alto dalle Theme Options (contrasto 8,9:1).
- **Misure del logo:** quelle del piano (26px desktop, 20px mobile) sono state cambiate su richiesta
  del dev: vedi la tabella delle altezze in `style.css` e «Decisioni».
- **Media query al posto delle classi `type_desktop` / `type_mobile`**: nella pagina caricata in un
  iframe stretto Impreza mostra l'hamburger ma lascia `type_desktop` sull'elemento menu; il CSS che
  dipende dalla larghezza usa quindi `@media (min-width: 1025px)` / `(max-width: 1024px)`, allineate
  a `mobile_width`.

### Task 4: footer italiano

- Il logo sta in un `vc_column_text` invece che in un `us_html`: esegue lo shortcode allo stesso modo
  e non richiede il contenuto codificato in base64.
- Dopo l'implementazione il footer ha avuto una prima riga con il blocco istituzionale FESR e il filo
  oro è passato dal bordo del footer a quella riga (vedi «Decisioni»).
- Il salvataggio dal pannello (Templates → Reusable Blocks) non è stato fatto: resta da fare prima del
  rilascio (vedi «Decisioni»).

### Task 6: versione inglese

- Il salvataggio dal pannello di header e footer inglesi non è stato fatto, come per l'italiano.
- Passo 4 («verifica la pagina inglese» con `curl /en/`): così com'è scritto non può passare, perché la
  Home non ha la traduzione inglese e `/en/` rimanda con un 301 alla Home italiana. La verifica è stata
  fatta su una 404 inglese (`/en/prova-8757/`, `lang="en-US"`): TRAILS, Follow us, Search the site,
  ACCESSIBILITY STATEMENT, «Autonomous Region» presenti; SENTIERI, «Seguici su», «Cerca nel sito»
  assenti.

## Bug trovati

- Il logo, in un elemento `html` dentro una cella flex, veniva schiacciato in larghezza a 960px:
  risolto con `flex-shrink: 0` sull'elemento e `max-width: none` sull'immagine.
- A 320px il logo rigido spingeva l'hamburger fuori schermo: sotto 600px il logo si restringe con la
  cella.
- Il campo di ricerca era più alto della riga di servizio (48px contro 36): fissato a 28px.
- Il colore delle icone social veniva sovrascritto dalla regola `color_text` di Impreza: serve il
  selettore `.w-socials.forestas-social`.

- **Review `wm-review-ticket` (commit e462804), due bloccanti corretti:**
  - sotto i 375px la bandiera UE dell'header era più bassa del logo (a 360px 14,7 contro 15px, a 320px
    12,4 contro 14,8): il logo dell'header ora scala con `max-width: min(193px, 48vw)`; rimisurato da
    320 a 600px, bandiera sempre più alta;
  - col pannello mobile aperto e la finestra passata al desktop (rotazione, finestra allargata) Impreza
    non chiude il pannello e `aria-expanded` resta `true`: `menu-mobile.js` tratteneva ancora il focus,
    portandolo sul pulsante invisibile. Ora il pannello conta come aperto solo se il pulsante è visibile;
    riprovato: su mobile il ciclo del focus funziona, su desktop Tab non viene più intercettato.
  `Version` del child 1.1.23.

## Decisioni

- Tag di Orchestrator: non associati né creati, su indicazione del dev.
- Dopo la review il dev ha chiesto il logo un po' più piccolo in header e footer: altezza 22px su
  desktop (era 26), 20px fra 1025 e 1199px (era 22), 18px fino a 1024px (era 20), al massimo 231px di
  larghezza sotto 600px (era 257). `Version` del child 1.1.1.
- Poi ha chiesto la riga del menu principale un po' più alta: `middle_height` 84px su desktop e
  laptop (era 72), tablet e mobile invariati a 60px. La sottolineatura della voce attiva ora è legata
  al testo (`.w-nav-title::after`, 7px sotto) invece che a una distanza fissa dal fondo della riga,
  così non si sposta quando cambia l'altezza. `Version` del child 1.1.2.
- Infine ha chiesto, solo per l'header con l'hamburger (fino a 1024px), i pulsanti di ricerca e menu
  come sul sito attuale in produzione: riquadri di 45px, ricerca su fondo `#333333` con icona
  bianca, menu su arancio `#F59F1A` con l'etichetta «menu» sotto l'icona, `hsl(17deg 76% 45%)` (poi
  scurito al 40% per il contrasto, vedi «Accessibilità») con
  testo bianco a menu aperto o al passaggio. Valori presi dal CSS di produzione
  (`--custom-theme-brand-color`, `--custom-theme-color-bg-04`, `--header-navigation-toggle-mobile-size`),
  dichiarati come variabili `--pulsante-*` nel child. L'etichetta è l'opzione «testo accanto
  all'icona» del menu di Impreza (`mobile_icon_text`), quindi è in `config/post.json`. Il pulsante di
  ricerca a icona compare solo sotto i 600px: fra 601 e 1024 resta il campo. `Version` del child
  1.1.3.
- Spazio fra il pulsante di ricerca e l'hamburger ridotto da circa 22px (lo spazio di Impreza fra gli
  elementi dell'header) a 8px, solo fino a 1024px. `Version` del child 1.1.4.
- **Blocco istituzionale FESR nell'header**, richiesto dal cliente via mail (loghi UE del PR FESR
  Sardegna 2021-2027) e dal dev nella riga di servizio, accanto alle lingue. Le linee guida
  (Kit UE § 6.1; Linee guida beneficiari PR FESR Sardegna, «Blocco istituzionale») chiedono
  l'emblema UE «in maniera prominente» e mai più piccolo degli altri loghi; la visibilità senza
  scorrere è indicata come buona prassi, non come obbligo. File: «versione 1» ufficiale, ridotto a
  1400×98 px e versionato in `themes/forestas-child/assets/img/blocco-istituzionale-fesr.png`,
  stampato dallo shortcode `[forestas_fesr]` (elemento `html:4` dell'header, IT ed EN). Dopo una
  prima prova nella riga di servizio, il dev l'ha voluto **su desktop e laptop in una terza riga
  dell'header sotto il menu** (44px, centrato) e **su tablet e mobile nella
  riga in alto, a sinistra delle lingue**, dove si restringe con lo spazio libero. Altezze: 28px
  (bandiera UE ~23px, logo del sito 22px), 25px fra 1025 e 1199px (logo 20px). Su mobile a 375px il
  blocco è alto 20px (bandiera ~16px) contro i 18px del logo: risolto più avanti, rimpicciolendo il
  logo dell'header su mobile.
  `Version` del child 1.1.10.
- Pulsante di ricerca su tablet e mobile: a riposo ha il fondo dei campi del sito
  (`--inputs-background`, lo stesso del campo di ricerca desktop) e l'icona scura; il fondo scuro
  `#333333` con icona bianca vale solo al tocco, al passaggio e al focus. Aperta la ricerca, Impreza
  mostra il campo a tutta larghezza al posto del pulsante. `Version` del child 1.1.11.
- **Menu a pannello su tablet e mobile**, come sul sito attuale (screenshot del dev): l'hamburger apre
  un pannello che entra ed esce da destra (`mobile_layout="panel"`, `mobile_effect_p="afr"`), a tutta
  larghezza sotto l'header, che resta visibile; il pulsante diventa «chiudi» («close» in inglese) sul
  fondo arancio scuro (oggi `hsl(17deg 76% 40%)`, vedi «Accessibilità»). Voci principali a 20px; in fondo, fra due filetti, i link di servizio in
  maiuscolo, poi «Seguici su» con i sei social a 32px in color terra. Questo **supera la decisione
  presa nel Task 3** di tenere i social solo nel footer: il filtro `wp_nav_menu_items` del child li
  legge dall'header della lingua corrente (`socials:1`, `html:1`), quindi gli URL restano solo in
  `config/post.json`. `Version` del child 1.1.15.
- Due correzioni al pannello chieste dal dev, allineate al sito attuale (`--header-position: fixed` e
  `.has-mobile-menu-open { overflow: hidden }` nel suo CSS): su tablet e mobile **l'header è fisso** in
  cima (`sticky` dell'Header Builder solo per `tablets` e `mobiles`), e **a menu aperto la pagina non
  scorre** (`html:has(... [aria-expanded="true"])` con `overflow: hidden`). Il pannello ha un'altezza
  esplicita `100dvh - 96px` e `min-height: 0`: Impreza gli dava come minimo l'altezza dello schermo
  intero, e il fondo con social e link di servizio finiva fuori. Spazi, caratteri e icone si riducono
  con l'altezza (`clamp` in `dvh`): provato a 568, 667 e 812px di altezza, tutto visibile.
  `Version` del child 1.1.18.
- Logo dell'header più piccolo su tablet e mobile: 15px di altezza fino a 1024px (era 18) e al
  massimo 193px di larghezza sotto 600px (era 231); il logo del footer non cambia. A 375px la bandiera
  UE del blocco istituzionale (~16px) è ora più alta del logo del sito (15px): il punto sulle
  dimensioni dell'emblema su mobile è risolto. `Version` del child 1.1.19.
- **Blocco istituzionale FESR nel footer**, richiesto dal dev: il file ufficiale «versione 2» (testi
  sotto i loghi), ridotto a 1400×161 px e versionato in
  `themes/forestas-child/assets/img/blocco-istituzionale-fesr-2.png`, stampato da
  `[forestas_fesr versione="2"]` (lo shortcode ora sceglie la versione). È la prima riga del blocco
  riutilizzabile «Footer» (IT ed EN), centrata, alta 80px su desktop e a tutta larghezza sotto 600px
  (bandiera UE ~21px contro i 18px del logo del footer a 375px). Sopra ha una riga sottile come quella
  dell'header che la separa dal contenuto; il filo oro, che prima era il bordo del footer
  (`.l-footer`, oc:8717), ora sta sopra la riga del logo e dei social, cioè sotto i loghi. Gli stili
  del blocco nell'header sono stati limitati a `.l-header`. `Version` del child 1.1.21.
- **Accessibilità**, verifica chiesta dal dev rispetto alle Linee guida AgID (EN 301 549, WCAG 2.1 AA).
  axe-core su header e footer: nessuna violazione A/AA. Controlli manuali e correzioni:
  - nome del pulsante del menu allineato alla scritta visibile («Chiudi»/«Close» a menu aperto,
    WCAG 2.5.3) e, nello stesso script `assets/js/menu-mobile.js`, Esc che chiude il pannello (ramo poi
    tolto perché lo fa già Impreza, vedi «Pulizia dopo la review») e focus
    che resta nel pannello (WCAG 2.4.3, 2.4.7);
  - fondo del pulsante aperto scurito da `hsl(17deg 76% 45%)` (4,58:1 col bianco, al limite) a
    `hsl(17deg 76% 40%)` (5,55:1): è l'unico punto in cui ci si allontana dal sito attuale;
  - link «Vai al contenuto» / «Skip to content» con `wp_body_open` (WCAG 2.4.1);
  - nomi distinti alle tre `nav` con gli attributi HTML personalizzati di Impreza
    (`custom_html_atts`), IT ed EN; etichetta del selettore «Lingua»/«Language» e tolto
    `aria-current` dal `<b>` della lingua attiva;
  - `scroll-padding-top` sotto i 1024px per l'header fisso (WCAG 2.2 2.4.11).
  Verificati nel browser: primo Tab sul link di salto, Esc, ciclo del focus, contrasto, axe pulito
  (resta solo `heading-order` nel contenuto demo della Home, fuori da header e footer).
  `Version` del child 1.1.22.
- URL social usati esattamente come forniti dal cliente: WhatsApp senza prefisso internazionale
  (`phone=3351785162`), handle X `SardegnSentieri`.
- Il campo di ricerca usa la ricerca standard di WordPress, che oggi non trova sentieri, itinerari e
  POI (stanno nel backend Laravel).
- Header e footer si costruiscono con due generatori usati solo in sessione (non nel repo): quello che
  conta è il risultato in `config/post.json`, verificato con un secondo export identico al primo.
- Il salvataggio dell'header dal pannello (Templates → Headers) non è stato provato durante
  l'implementazione: il browser non era autenticato nel pannello. Va fatto dal dev, seguito da
  `bin/wordpress-config.sh export` e `git diff config/`.
- La voce attiva del menu è stata provata collegando per un momento SENTIERI alla Home con WP-CLI;
  il collegamento è stato annullato con un apply da `config/`, mai esportato.

- Il meta `us_og_image` lo scrive Impreza da sé a ogni salvataggio (prima `<img>` del contenuto,
  con `?ver=` della Version del child: nel footer oggi è il blocco FESR): esportato, cambiava a ogni `Version` del child e rompeva il round-trip.
  È stato aggiunto a `WPF_META_CALCOLATI` in `docker/scripts/config/comune.php`, come gli altri meta
  che UpSolution Core ricalcola.

- Documentazione finale: pagina di conoscenza `docs/knowledge/8757-header-e-footer-di-wordpress-forestas-secondo-il-mockup.md`
  (controllata con `wm-context-guard`: tolta una ripetizione del vincolo dei 96px, che resta solo come
  trappola), riga nella tabella «Conoscenza» del `CLAUDE.md` e seconda trappola oc:8757 sul pannello
  mobile legato all'altezza dell'header. Quella trappola è stata tolta con la pulizia della prima
  review, quando il CSS è passato a `--header-sticky-height`.

- **Pulizia dopo la review** (cleanup di `wm-review-ticket`), `Version` del child 1.1.26:
  - CSS riordinato per argomento (target, riga di servizio, logo e blocco FESR con una tabella delle
    altezze, menu, tablet e mobile, footer, link di salto); l'altezza dell'header mobile non è più
    scritta a mano in 5 punti ma presa da `--header-sticky-height` di Impreza; barra di
    amministrazione esclusa sotto i 600px, dove WordPress non la tiene fissa; `#fff` sostituito da
    `--testo-su-scuro`; tolti `position: relative` residuo, `max-height`, `width` e `overflow` doppi;
    social del footer a sinistra fino a 1024px (era 900, mentre le colonne si impilano a 1024);
  - voce attiva del menu: considera anche `current-menu-parent`, `current_page_parent`,
    `current-page-ancestor`, e nel pannello mobile tiene il colore del tipo (prima la regola del
    pannello, più specifica, lo copriva);
  - campo di ricerca a 28px solo su desktop: su tablet e mobile la ricerca aperta resta alta quanto
    vuole Impreza;
  - `functions.php`: helper `forestas_asset_url()`; testo alternativo del blocco FESR con
    `forestas_testo()`, quindi in inglese sulle pagine inglesi (quello del logo resta «Sardegna
    Sentieri», fisso: è un nome proprio); il filtro del pannello diviso in
    `forestas_pannello_servizio()` e `forestas_pannello_social()`, che cerca social e «Seguici su» per
    tipo e classe invece che per id; risolve `servizio-menu` solo dopo aver riconosciuto il menu
    principale;
  - `menu-mobile.js`: cambia anche la scritta visibile «menu»/«chiudi» (prima la metteva il CSS con
    `::after`, in un secondo posto con le stesse parole); ascolta in fase di cattura, così Maiusc+Tab
    sulla prima voce torna al pulsante invece di chiudere il pannello; tolto il ramo di Esc, che fa già
    Impreza;
  - classe del contenitore delle lingue rinominata `forestas-lingua-wrap` (si confondeva con il `<nav>`
    `forestas-lingue`);
  - trappola dei 96px tolta dal `CLAUDE.md`: non scatta più;
  - lasciato il link «Vai al contenuto» del child invece dell'opzione nativa di Impreza, motivato nel
    commento di `style.css`.
  Durante la verifica nel browser due regressioni introdotte dalla pulizia, corrette: senza
  `aspect-ratio` il blocco FESR dell'header sotto i 600px si annullava (largo 0px nella cella flex),
  e il logo dell'header aveva perso `height: auto` sotto i 600px. Rimisurato: 360px bandiera 14,3
  contro logo 13,5; 1025px e 1440px uguali a prima.

- **Seconda review** (sessione di review, commit b8b42ce: 12 cleanup), `Version` del child 1.1.27:
  - selettore di lingua: il mockup mostra «IT EN» senza separatore (`<b>IT</b><span>EN</span>`, con
    9px di spazio); il codice è già così, erano i documenti a dire «IT · EN»: corretti overview, note,
    pagina di conoscenza e commento di `functions.php`. `plan.md` resta com'era, perché è il piano
    approvato;
  - filtro del pannello limitato all'elemento menu di Impreza (walker `US_Walker_Nav_Menu`): un widget
    o un «menu aggiuntivo» che mostra `main-menu` non riceve più servizio e social;
  - link di servizio nel pannello con `target`, `rel` e `title` delle voci di menu;
  - `functions.php`: `forestas_versione()` al posto delle tre chiamate a `wp_get_theme()`, un solo
    callback su `wp_enqueue_scripts` per CSS e JS, `forestas_voce_pannello()` per il markup delle due
    voci aggiunte. Lo script resta caricato con `get_stylesheet_directory_uri()` e non con
    `forestas_asset_url()`, perché `wp_enqueue_script` aggiunge da sé `?ver=`;
  - CSS: un'unica regola di focus anche per il link di salto, che ora scende sotto la barra di
    amministrazione; `--grigio-scuro` condiviso dai due token che usavano `#333333`; convenzione dei
    nomi delle variabili scritta in `:root`; commento unico sulla soglia 1024/1025px;
  - trappola nel `CLAUDE.md` sulla soglia 1024/1025px, che sta in tre posti;
  - `_us_posts_per_archive_page` e `_us_page_block_ids` fra i meta calcolati (`comune.php`): li scrive
    `us-core/functions/list.php` a ogni salvataggio; export rifatto, il meta è sparito dai footer;
  - dipendenze dal codice interno di Impreza usate dal child aggiunte all'elenco «Dipendenze» di
    `docs/knowledge/inizializzazione-wordpress.md`;
  - checkbox di `plan.md` spuntate per i passi eseguiti; restano aperti i tre passi con il salvataggio
    dal pannello (Task 3 passo 3, Task 4 passo 2, Task 6 passo 2). I passi «commit» dei singoli task
    sono spuntati ma sono stati fatti come due commit unici (e462804, b8b42ce), su richiesta del dev.
  Non applicati, con il motivo:
  - la lista dei cinque stati «corrente» del menu resta in tre regole (colore su desktop, sottolineatura,
    colore nel pannello): unirle richiederebbe di sovrascrivere il colore delle voci di Impreza con una
    variabile senza un valore di ripiego affidabile;
  - le misure del blocco FESR restano sia negli attributi dell'`<img>` sia nell'`aspect-ratio` del CSS:
    senza quest'ultimo il blocco si annulla nella cella flex (regressione già vista);
  - header e footer restano copiati per lingua: vedi la pagina di conoscenza, «Perché così»;
  - prefissi: `forestas_` è il child, `wpf_` gli script di configurazione; scritto nella pagina di
    conoscenza, senza rinominare nulla.

- **Terza review** (sessione di review, commit e0777a8: 14 cleanup), `Version` del child 1.1.28:
  - link di salto sotto la barra di amministrazione anche sotto i 600px (usa direttamente
    `--wp-admin--admin-bar--height`: lì la barra è assoluta in cima alla pagina, come il link);
  - commento di `comune.php` corretto: `_us_page_block_ids` e `_us_posts_per_archive_page` us-core li
    calcola su `post_updated`, che non scatta su un post appena creato dall'apply;
  - commento sulle soglie in `style.css`: anche 600px è di Impreza (`mobiles_breakpoint`), e
    `breakpoint: 900px` nel JSON dell'header è ignorato (`custom_breakpoint` 0);
  - variabili rinominate secondo la convenzione: `--tipo-voce` → `--voce-attiva` (si confondeva con i
    token `--tipo-*`), `--forestas-voce-menu` → `--carattere-voce-menu`; nuove `--carattere-piccolo`
    (12px, quattro usi) e `--carattere-servizio` (12.5px, due usi); `11.5px` resta letterale, un solo
    uso. Il motivo scritto nella seconda review («ciascuna in un solo contesto») era sbagliato;
  - escape dei link di servizio spostato alla stampa, con valori grezzi nella mappa degli attributi;
  - README: la `Version` si alza anche per JS e immagini; pagina «Inizializzazione»: aggiunto
    `_us_page_block_ids`, `US_Walker_Nav_Menu` attribuita a Impreza, percorso completo di `menu.js`;
  - `CLAUDE.md`: header e footer copiati per lingua (nella riga dell'indice e in una trappola) e
    mezza riga sul `breakpoint: 900px` da non toccare;
  - follow-up superato del filtro `wp_nav_menu_items` tolto; nota sulla «seconda trappola» corretta;
    deviazione del Task 6, passo 4, registrata.
- **Modifiche a `overview.md` dopo l'approvazione, chieste dalle review:** il selettore «IT · EN»
  diventato «IT EN» (righe di «Cosa cambia» e requisito del selettore), il requisito della trappola
  sulla soglia 1024/1025px fra quelli di documentazione, e i «Moduli toccati» (`comune.php` con i nuovi
  meta calcolati, `CLAUDE.md`, `inizializzazione-wordpress.md`). Dopo la terza review: requisito del
  link di salto sotto la barra di amministrazione, requisito della trappola sulle due copie IT/EN,
  `README.md` e le nuove funzioni di `functions.php` fra i «Moduli toccati».

## Correzione dopo le review: header mobile non sticky e pannello sotto l'header (2026-10-13)

Problemi segnalati dal dev provando il sito in locale da loggato, a ≤600px; nessuna delle tre review
li aveva visti, perché le prove erano fatte da anonimo o sopra i 600px:

- **L'header di tablet e mobile era sticky** e il dev lo vuole fermo in cima alla pagina, come su
  desktop. Da loggato era anche rotto: sotto i 600px la barra di amministrazione scorre via con la
  pagina, ma Impreza teneva l'header fisso a 46px dal bordo, lasciando vedere il contenuto sopra.
  Correzione: `sticky` 0 per `tablets` e `mobiles` nei due header IT/EN (`config/post.json`,
  applicato ed esportato: il diff è solo quei quattro valori).
- **Il pannello del menu copriva la riga del logo** e restava visibile solo «chiudi». Partiva da
  `--header-sticky-height` + barra di amministrazione, ma la barra sotto i 600px vale 0 nel CSS
  mentre occupa 46px sopra l'header: il pannello partiva 46px troppo in alto. Con l'header che scorre
  il valore dipende anche da quanto la pagina è scesa. Correzione: `menu-mobile.js` all'apertura (e al
  ridimensionamento a menu aperto) imposta `--forestas-sotto-header` al bordo inferiore dell'header
  nello schermo; il valore del CSS resta come riserva senza JS.
- Tolto lo `scroll-padding-top` sotto i 1024px e il relativo requisito dell'overview (WCAG 2.4.11):
  serviva solo con l'header fisso. Child alla Version 1.1.29.
- Verifica in headless (Chrome) a 390 e 800px, da anonimo e da loggato, con la pagina in cima e scesa
  di 40px: header `pos_static` che esce dallo schermo scorrendo; pannello che parte esattamente dal
  bordo inferiore dell'header (390 loggato: header 46–142, pannello da 142) e arriva al fondo.
- **Modifiche a `overview.md`:** «Cosa cambia» e requisito dei layout tablet e mobile (header non
  sticky), requisito dello `scroll-padding-top` tolto, rischio sul pannello riscritto.

Verifica di accessibilità rifatta dopo la correzione (axe-core 4.10.2, regole WCAG 2.0/2.1/2.2 A e
AA, su header, footer e pannello aperto; anonimo e loggato a 390, 800 e 1280px). Ha trovato due
problemi, corretti:

- **Contrasto di «chiudi» col mouse sopra: 1,66:1.** `.no-touch .l-subheader.at_middle a:hover` di
  Impreza è più specifica della regola del child e colorava la scritta del blu dei link dell'header
  (`#254877`) sul fondo arancio scuro. Succede solo con il mouse (classe `no-touch`): tablet con mouse,
  finestra stretta del desktop. Le verifiche precedenti misuravano il pulsante col mouse altrove.
  Correzione: `.l-subheader` aggiunto al selettore del child; Version 1.1.30.
- **«chiudi» in parte fuori dallo schermo** aprendo il menu con la pagina scesa di qualche decina di
  pixel: a menu aperto la pagina è bloccata e non si può risalire (WCAG 2.4.11). Correzione:
  all'apertura `menu-mobile.js` porta la riga del pulsante tutta nello schermo.

Modifiche a `overview.md` per queste due correzioni: requisito del contrasto di «chiudi» esteso al
mouse sopra, requisito nuovo sul pulsante tutto visibile e sul pannello sotto l'header, «Moduli
toccati» di `menu-mobile.js`. Nella pagina di conoscenza il paragrafo sull'accessibilità e l'alternativa
superata dell'header sticky; nel `CLAUDE.md` la trappola dell'hover di Impreza.

Esito finale: nessuna violazione con il pannello chiuso o aperto. Passando il mouse su ognuno dei 20-22
link e pulsanti dell'header, dei 13 del footer e dei 17 del pannello, nessun problema di contrasto.
Tab e Maiusc+Tab restano nel pannello aperto, Esc lo chiude e riporta il focus sul pulsante. Dopo uno
scroll di 80px «chiudi» è tutto visibile e il pannello parte subito sotto.

## Follow-up

Rilievi minori della code review finale (prima review), non corretti:

- le voci di servizio aggiunte dal filtro non hanno `w-nav-item level_1` né `current-menu-item`;
- `--striscia-footer` e il bianco del testo duplicano i colori del footer delle Theme Options;

Nessun ticket aperto: si apriranno se e quando il dev lo chiede.

- Collegare il campo di ricerca alla ricerca del catasto (Elasticsearch del backend Laravel).
- Collegare voci di menu e link (oggi `#`) alle pagine, quando esisteranno.
- Prima della messa online: il link «Dichiarazione di accessibilità» del footer deve portare alla
  dichiarazione dell'ente sul portale AgID (Legge 4/2004); l'indirizzo lo fornisce il cliente.
- La Home non ha la traduzione inglese: `/en/` rimanda alla Home italiana, quindi dalla Home il link
  EN del selettore non porta a una pagina inglese. Header e footer inglesi si vedono sulle pagine
  inglesi (provati su una 404 sotto `/en/`).
- Sostituire il logo con una versione senza «BETA», quando arriva.
- Rivedere il layout tablet e mobile quando il mockup lo definirà.
- Far validare al cliente le etichette inglesi, in particolare «WALKS» per «Cammini».
