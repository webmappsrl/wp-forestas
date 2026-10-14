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
  rilascio (vedi «Decisioni»). **⚠️ Superato (2026-10-13):** fatto per le copie IT, vedi «Follow-up».

### Task 6: versione inglese

- Il salvataggio dal pannello di header e footer inglesi non è stato fatto, come per l'italiano.
  **⚠️ Aggiornato (2026-10-13):** resta non fatto per le copie EN, perché in locale WPML non è
  registrato; rischio accettato dal dev, vedi «Follow-up».
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
  stampato dallo shortcode `[forestas_fesr]` (elemento `html:4` dell'header, IT ed EN). **Cambiato il
  14/10/2026:** il file è ora 1100×77 e lo shortcode `[forestas_loghi_header]`, che stampa anche
  Forestas e CAI (sezioni «Loghi dell'header» e «Review del commit 5d2a098»). Dopo una
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
  legge dall'header della lingua corrente (`socials:1`, `html:1`; **⚠️ Superato:** la lettura per id è
  stata sostituita da quella per tipo e classe, vedi «Pulizia dopo la review»), quindi gli URL restano solo in
  `config/post.json`. `Version` del child 1.1.15.
- **⚠️ Superato (2026-10-13), vedi «Correzione dopo le review»:** l'header non è più fisso e il
  pannello non è più alto `100dvh - 96px` ma parte dal bordo dell'header calcolato da
  `menu-mobile.js`. Resta valida la pagina bloccata a menu aperto. Testo originale:
  Due correzioni al pannello chieste dal dev, allineate al sito attuale (`--header-position: fixed` e
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
- **Blocco istituzionale FESR nel footer** (superata il 14/10/2026: nel footer ora c'è la riga dei
  sei loghi, vedi «Riga dei loghi del footer»; file e variante `versione="2"` tolti), richiesto dal dev: il file ufficiale «versione 2» (testi
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
  - `scroll-padding-top` sotto i 1024px per l'header fisso (WCAG 2.2 2.4.11). **⚠️ Superato
    (2026-10-13):** tolto con l'header fisso; ora `scroll-padding-top` vale l'altezza della barra di
    amministrazione da 601px, vedi «Quarta review».
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
  `bin/wordpress-config.sh export` e `git diff config/`. **⚠️ Superato (2026-10-13):** fatto per le copie IT, vedi «Follow-up».
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
    amministrazione esclusa sotto i 600px, dove WordPress non la tiene fissa (**⚠️ Superato
    (2026-10-13):** il bordo dell'header lo calcola `menu-mobile.js`, vedi «Correzione dopo le
    review» e «Quarta review»); `#fff` sostituito da
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

## Quarta review (2026-10-13, su 9751df0)

Un bloccante e 11 cleanup mandati dalla sessione di review; tutti applicati. Child alla Version 1.1.31.

- **Bloccante, riprodotto prima della correzione:** da loggato fra 601 e 1024px la barra di
  amministrazione è fissa in cima (46px fino a 782px, 32px oltre) e l'header, non più sticky, le
  scorre sotto. `menu-mobile.js` riportava la riga del pulsante in vista solo se usciva dallo schermo:
  a 700px con la pagina scesa di 60 e 85px «chiudi» stava a 30–75 e 8–53px, sotto la barra (fino a
  46). Ora il limite è il fondo della barra quando è `position: fixed`, altrimenti 0. Dopo: «chiudi»
  a 54–99px a 700px e a 40–85px a 900px; pannello sempre dal bordo dell'header.
- **⚠️ Superato (quinta review):** rilievo errato, regola tolta. **1. Link di salto coperto dopo uno
  scroll** (da loggato, da 601px): `scroll-padding-top` pari
  all'altezza della barra di amministrazione da 601px, a ogni larghezza. Verificato da loggato a 700,
  900 e 1280px con la pagina scesa di 500px: il link sta a 54 e 40px, sotto la barra.
- **2. Link di salto col mouse sopra:** `.no-touch a:hover` di Impreza gli dava il colore dei link sul
  fondo blu; regola `.no-touch .forestas-salta:hover` nel child. axe, col link a fuoco e il mouse
  sopra: nessun problema di contrasto.
- **3.** Tolta `--forestas-barra-admin`: la riserva di `--forestas-sotto-header` usa
  `--wp-admin--admin-bar--height`, che a pagina in cima è giusta a ogni larghezza; commenti accorciati.
- **4. Doppia rotazione:** il ricalcolo al ridimensionamento parte 50ms dopo l'ultimo evento, dopo
  quello di Impreza (5ms). **⚠️ Motivo corretto nella quinta review:** l'attesa serve all'header, non
  alla visibilità del pulsante. Verificato: menu aperto a 390px, finestra a 1280, scroll di 300px, ritorno
  a 390: «chiudi» a 8px e pannello dal bordo dell'header.
- **5.** `header` e riga del pulsante calcolati una volta all'avvio, tolti i controlli che non
  scattavano mai e il `Math.max`; commento di `functions.php` aggiornato.
- **6–8.** Segnati come superati i punti di queste note sull'header fisso e sullo
  `scroll-padding-top`; quarta trappola fra i requisiti di documentazione e i «Moduli toccati»
  dell'overview; nella pagina di conoscenza `--header-sticky-height` descritta come sola riserva.
- **9.** Commento di `comune.php` corretto: la prima lettura in front-end ricalcola tutti e due i meta
  (verificato in `us-core/functions/list.php`).
- **10.** Rollback scritto come procedura a passi nella pagina di conoscenza (verificato che
  `menu.php` non cancella menu).
- **11.** `.l-subheader` e `no-touch` fra le «Dipendenze» di `inizializzazione-wordpress.md`; limite
  noto del pannello (solo all'apertura e al ridimensionamento, `:has()` assente prima di Safari iOS 16)
  nella pagina di conoscenza.
- **Modifiche a `overview.md`:** requisito della trappola sull'hover di Impreza fra quelli di
  documentazione, e la stessa trappola fra i «Moduli toccati» del `CLAUDE.md`; requisito del link di
  salto esteso alla pagina scesa e al mouse sopra; requisito di «chiudi» esteso alla barra di
  amministrazione fissa; rischio sulla posizione del pannello con la barra fissa da 601px.
- **Pagina di conoscenza:** barra di amministrazione fissa da 601px (46/32px), `scroll-padding-top` e
  hover del link di salto, riga del pulsante portata in vista anche sotto la barra.

## Quinta review (2026-10-13, su b9b43e7)

Nessun bloccante; 17 cleanup mandati dalla sessione di review. Applicati tutti; il 17 dopo la
decisione del dev. Child alla Version 1.1.32.

- **Correzione di due rilievi errati della quarta review:**
  - **1.** Lo `scroll-padding-top` del child era un doppione: WordPress 7.1.3 lo mette già su `html`
    per chi è loggato (`admin-bar.min.css`: `scroll-padding-top: var(--wp-admin--admin-bar--height)`,
    32px e 46px sotto 782px), anche sotto i 600px. Regola e commento tolti, frase della pagina di
    conoscenza corretta. Verifica rifatta da loggato a 700 e 900px, pagina scesa di 500px, Tab fino
    al link di salto: a 54 e 40px, sotto la barra (46 e 32).
  - **2.** La visibilità del pulsante del menu (e quindi `aperto()`) la decide subito una media query
    del CSS dell'header di Impreza, non il debounce di `menu.js`. L'attesa di 50ms al
    ridimensionamento resta, ma per dare all'header di Impreza (`header.js`) la geometria finale prima
    di misurarlo: commento corretto.
- **3.** `min-width: 24px` e `text-align: center` per i link testuali di header, footer e pannello:
  «FAQ» e «App» misuravano 23×24px fra 1025 e 1440px. Dopo: nessun link sotto 24×24 a 1025, 1100 e
  1440px.
- **4.** Requisito del pannello precisato nell'overview: sugli schermi bassi e sui telefoni in
  orizzontale il pannello scorre.
- **5.** Pagina bloccata con `html.w-nav-open` (classe messa e tolta da Impreza in `menu.js`) invece
  di `html:has(…)`: tolto il limite noto sui Safari iOS senza `:has()`. Verificato da anonimo e da
  loggato a 390px: a menu aperto la rotella non fa scorrere, alla chiusura sì; passando a 1280px col
  pannello aperto la classe resta ma la regola, limitata a 1024px, non blocca più la pagina.
- **6.** Commento di `functions.php` ridotto a un rimando al commento di testa di `menu-mobile.js`,
  più la nota su `wp_localize_script`; commento di `style.css` andato a capo.
- **7.** Commento di `comune.php` precisato: il salvataggio vale solo per `page`, `us_page_block` e
  `us_content_template`, la lettura in front-end solo per pagina di ricerca, pagina degli articoli e
  template d'archivio (verificato in `us-core/functions/list.php`).
- **8.** `espanso()` estratta in `menu-mobile.js`; commento del token `--pulsante-cerca-fondo` esteso
  a passaggio, tocco e focus.
- **9–10.** Rollback nella pagina di conoscenza: `--da wp-forestas/backup/<data-ora>` lanciando da
  `forestas` (lo script legge `--da` rispetto alla cartella corrente; i backup stanno in
  `${REPO}/backup`), il backup giusto è il primo del rilascio, l'apply ripristina il sito e non la
  cartella; il child si riporta indietro con `git revert` in `wp-forestas`, puntatore in `forestas` e
  `scripts/wordpress-up.sh`.
- **11.** Rischio «Rollback» dell'overview sostituito con un rimando alla procedura a passi.
- **12.** `forestas/docs/howto/messa-in-opera-wordpress-uat.md`: una riga su child e `config/` da
  rilasciare insieme, con rimando alla pagina di conoscenza. È nel repo `forestas`, non committata.
- **13.** Dichiarazione di accessibilità (L. 4/2004) nella pagina di conoscenza, come passo da fare
  prima della messa in produzione.
- **14.** «Dipendenze» di `inizializzazione-wordpress.md`: `#wpadminbar`, il suo `position`,
  `--wp-admin--admin-bar--height` con lo `scroll-padding-top` del core, e la classe `w-nav-open`.
- **15.** Etichette «Superato» sulle frasi delle note ancora valide in apparenza (social letti per id,
  barra di amministrazione esclusa sotto i 600px) e sui punti 1 e 4 della quarta review.
- **16.** Registrate qui sotto le modifiche all'overview della prima pulizia (b8b42ce).
- **17. Deciso dal dev:** per chi è loggato il primo elemento raggiungibile da tastiera è il link di
  WordPress «Vai alla barra degli strumenti» (`tabindex=1`), non «Vai al contenuto». Il comportamento
  del core non si tocca; il requisito dell'overview ora dice che il link di salto è il primo elemento
  per chi non è loggato, e che per chi è loggato viene dopo quello di WordPress.
- Regressione: axe (WCAG 2.0/2.1/2.2 A e AA) da anonimo e da loggato a 390, 800 e 1280px, pannello
  chiuso e aperto, hover su ogni link e pulsante: nessuna violazione; Tab, Maiusc+Tab, Esc, apertura
  dopo uno scroll e prova della barra di amministrazione (12 casi) corretti.
- **Modifiche a `overview.md`:** requisito del pannello (schermi bassi e orizzontale: scorre),
  rischio «Rollback», requisito del link di salto (primo elemento per chi non è loggato) e l'howto
  di `forestas` fra i «Moduli toccati». Nella pagina di conoscenza, fra le alternative superate, il
  blocco della pagina con `:has()` e lo `scroll-padding-top` del child. Modifiche fatte nella prima pulizia (b8b42ce) e non registrate allora: ricerca
  a campo intero fra 601 e 1024px («Cosa cambia» e requisito dei pulsanti), bandiera UE con il logo
  dell'header a `min(193px, 48vw)` sotto i 600px (rischio sull'emblema), tastiera non bloccata quando
  la finestra passa al desktop col pannello aperto (requisito del focus), requisito della «trappola
  dei 96px» tolto.

## Rilascio su UAT e correzione per WPML con i filtri attivi (2026-10-13)

**Rilascio su UAT** (dev in remoto, eseguito da Claude via SSH): `forestas` su `2340c96` (PR #25),
`wp-forestas` su `7dfe79c` (PR #4), `scripts/wordpress-up.sh` (immagine dalla cache, container non
ricreati), `apply` in anteprima con le 11 differenze attese, poi `apply --conferma`. **Backup per il
rollback: `wp-forestas/backup/2026-10-13-123738`** su UAT, l'unico del rilascio.

**Difetto 1, menu tradotti.** Dopo l'apply UAT aveva 13 voci inglesi nei tre menu italiani: per ogni
menu inglese la prima voce era nel menu giusto, le successive in quello italiano, e l'anteprima
segnalava ancora 6 differenze. Causa: su UAT WPML ha i suoi filtri attivi, in locale no. I filtri si
accendono quando è completato il primo passo del wizard («lingua dei contenuti esistenti»,
`existing_content_language_verified`): su UAT lo è (wizard fatto dal pannello), in locale no, perché
le lingue impostate con l'API non lo completano e l'export lo scarta come stato (`_verified$`). Con i
filtri attivi la lingua corrente è l'italiano: `get_terms()` non vede i menu inglesi e `get_term()`
converte l'id di un menu inglese in quello del suo originale. Non dipende dalla registrazione di WPML:
completato quel passo, il locale ha riprodotto le stesse 6 differenze e lo stesso export sbagliato
(`menu.json`, 123 righe).

- **Correzione dei dati su UAT**, autorizzata dal dev: le 13 voci in lingua `en-us` spostate dai menu
  italiani a quelli inglesi (6→10, 7→9, 8→11), conteggi ricalcolati. Nel database ogni menu ha le
  voci di `config/`.
- **Correzione del codice** (branch `fix/oc-8757-menu-e-pannello-con-wpml-attivo`):
  - `wpml.php`: l'apply completa il primo passo del wizard con `WPML_Installation::finish_step1()`
    (`wpml_get_setup_instance()`), come il wizard; assegna la lingua ai contenuti esistenti solo se il
    sito non ha ancora traduzioni. Su UAT non scatta, perché il passo è già completato;
  - `wpml.php`: nuova `wpf_wpml_tutte_le_lingue()`, che esegue un blocco con WPML su tutte le lingue
    e senza conversione degli id (`\WPML\Utils\AutoAdjustIds::runWithout`, l'API con cui WPML stesso
    la spegne); la usano l'apply dei menu, l'export dei menu e il ritratto.
- **Verifica in locale, con i filtri attivi:** stato di UAT prima del rilascio ricreato (backup di UAT
  applicato, menu e header/footer inglesi tolti), poi apply del repo: 11 modifiche, poi «nessuna
  differenza»; sei menu con chiave, lingua e voci giuste, nessuna voce orfana; export identico a
  `config/`. **Su UAT in sola anteprima** (script corretti copiati in una cartella temporanea del
  container, poi cancellata, `WPF_PROVA=1`): «nessuna differenza», contro le 6 di prima.

**Difetto 2, pannello mobile inglese senza servizio e social.** Il filtro `wp_nav_menu_items` del child
confrontava il menu della pagina con `main-menu` cercato per slug; con i filtri di WPML attivi quella
ricerca restituisce già il menu nella lingua della pagina, e in inglese il confronto falliva.
Correzione: si confrontano tutti e due convertiti nella lingua predefinita. Verificato in locale, IT
ed EN; Version del child 1.1.33.

**Verifiche su UAT prima del merge, in sola lettura** (chieste dal dev; script corretti in una cartella
temporanea del container, poi cancellata, checkout del server non toccato):

- export con gli script corretti (`WPF_SOLA_LETTURA=1`): i 7 file identici byte per byte a `config/`;
- logica del filtro del pannello nel contesto inglese: `main-menu` cercato per slug arriva come
  `main-menu-en`; condizione vecchia «no» (servizio e social assenti), nuova «sì»; in italiano «sì»;
- PHP di UAT legge già `wordpress.ini` da `conf.d` (`memory_limit` 256M, `upload_max_filesize` 64M):
  le tre righe nuove arrivano con la ricostruzione dell'immagine.

Non provati su UAT fino al rilascio: la resa del child 1.1.33, la ricostruzione dell'immagine (il
container di WordPress viene ricreato, pochi secondi di sito irraggiungibile), la scrittura dei menu
(l'apply non avrà differenze), il primo passo del wizard (su UAT è già completato). Dopo il rilascio:
confronto di resa locale/UAT su IT ed EN.

**Rilascio della correzione su UAT (2026-10-13)**, eseguito da Claude via SSH dopo il merge del dev
(`wp-forestas` PR #5 e #6, `forestas` PR #26): `forestas` su `3c66c87`, `wp-forestas` su `82293fe`;
`scripts/wordpress-up.sh` ha ricostruito l'immagine e ricreato il container di WordPress («WordPress
pronto», nessun avviso all'avvio); `display_errors` spento e `log_errors` acceso; `apply` in anteprima:
«nessuna differenza», quindi nessun `apply --conferma`. Verifiche, confrontando con il locale:

- pagina italiana, a 1280 e 390px: HTML di header e footer e geometria di ogni elemento identici;
- pagina inglese (404 sotto `/en/`): HTML di header e footer identico, pannello mobile con servizio e
  social; geometria dell'header identica, il footer è solo più in basso (1316px a 1280, 2555px a 390)
  perché su UAT la manutenzione mostra la Home demo al posto di «Page not found»: contenuti, fuori da
  questo ticket;
- child servito alla Version 1.1.33; l'avviso «Undefined array key 0» non compare più in pagina ed è
  in `docker logs wordpress-forestasuat`;
- axe da anonimo, IT ed EN, a 390, 800 e 1280px, pannello chiuso e aperto, hover su ogni link e
  pulsante: nessuna violazione; apertura del menu dopo uno scroll: «chiudi» visibile, pannello subito
  sotto l'header.

Regressione in locale con i filtri attivi: axe senza violazioni da anonimo e da loggato a 390, 800 e
1280px, hover, tastiera, 12 casi su 12 della barra di amministrazione, target, pagina bloccata.

**Fino al rilascio di questa correzione su UAT non va lanciato `apply --conferma` lì:** con gli
script vecchi rinominerebbe i menu italiani come quelli inglesi.

**Avviso PHP sulla 404 inglese di UAT** («Undefined array key 0 in class-wp-query.php line 3754»),
chiesto dal dev. Causa, dal codice di us-core (`us_display_maintenance_page`): su UAT la licenza di
sviluppo di Impreza tiene accesa la modalità manutenzione, con la Home (id 7) come pagina di
manutenzione, quindi chi non è loggato vede la Home al posto di ogni pagina, 404 compresa. In inglese
us-core cerca la Home tradotta, che non esiste, e la richiede con una `WP_Query` che i filtri di WPML
limitano all'inglese: la query è vuota e `the_post()` dà l'avviso. In locale la manutenzione è spenta
(nessuna licenza). L'avviso sparisce quando la Home avrà la traduzione inglese (pagine inglesi: altro
ticket, deciso dal dev).

Si vedeva in pagina perché l'immagine non ha un `php.ini`: PHP usava `display_errors` acceso e
`log_errors` spento, cioè errori mostrati ai visitatori, con i percorsi del server, e non registrati.
**Correzione** in `docker/configs/php/wordpress.ini`: `display_errors = Off`, `log_errors = On`,
`error_log = /dev/stderr`. Verificata in locale dopo `scripts/wordpress-up.sh` (immagine ricostruita,
container ricreato) con una pagina di prova temporanea, poi cancellata: l'avviso non compare in pagina
e finisce in `docker logs`. Su UAT arriva con `wordpress-up.sh`, che stavolta ricostruisce l'immagine;
README aggiornato (tabella e «Comandi utili»).

**Modifiche a `overview.md`:** requisito sull'apply e l'export con WPML attivo; «Moduli toccati» con
`wpml.php`, `apply.php`, `export.php`, `ritratto.php`, il filtro del pannello, `wordpress.ini`, il
README e `inizializzazione-wordpress.md` (errori nei log), e la frase «nessuna modifica in `forestas`» corretta (c'è l'howto di UAT).

## Header e footer identici al sito attuale, Theme Options dal tema Drupal (2026-10-14)

Allo scrum del 14/10/2026 è emerso che il capitolato chiede la stessa UI del sito Drupal, salvo
modifiche concordate: il prototipo «Canvas Fase 2» se ne discostava (voce del menu al passaggio del
mouse, pesi e colori dei testi), e il cliente non lo considera un riferimento. Header e footer sono
stati rifatti identici a sardegnasentieri.it, tenendo le aggiunte concordate (blocco FESR, CAMMINI);
poi, su richiesta del dev, anche le Theme Options di Impreza su colori e tipografia. Branch
`feature/oc-8757-header-e-footer-identici-al-sito-attuale`, da `develop`.

**Fonti.** Il CSS in produzione e il tema Drupal «sardegnasentieri» della copia del repository
caricata su UAT (`/root/sardegnasentieri.zip`, MD5 `23215ab201b6d7bf994378222c523a69`; SCSS in
`docroot/themes/custom/sardegnasentieri/scss`, comportamento del menu in `js/header-navigation.js`).

**Metodo.** Per ogni elemento di header, footer e pannello, posizione, misure, font, colori e
spaziature letti dal browser sul sito in produzione e sul WordPress locale, e confrontati: a 1100,
1280 e 1440px; a 800 e 1024px col mouse; a 390 e 768px su touch; menu chiuso e aperto; passaggio del
mouse su voci, link e social. Esito: nessuna differenza oltre mezzo pixel, tranne quelle dovute alla
riga FESR (header più alto di 36px su tablet e mobile, 44px su desktop) e alla voce CAMMINI. axe senza
violazioni a 1440 e 390px, pannello chiuso e aperto.

**Decisioni**

- **Blocco FESR**: resta dov'era. Nell'header desktop la terza riga; su tablet e mobile la riga in
  alto, ora da solo e centrato, perché IT EN passa nella riga del logo come sul sito attuale. Nel
  footer prende il posto della riga dei loghi del sito attuale (POR FESR 2014-2020, Intense, Interreg
  e gli altri), che non si riporta. **Superata lo stesso giorno:** nel footer la riga dei sei loghi
  del sito attuale con i primi quattro aggiornati, nell'header gli stessi sei loghi (vedi «Riga dei
  loghi del footer» e «Loghi dell'header», qui sotto).
- **Bandiera UE e logo**: col logo largo come in produzione (fino a 382px, 28px di altezza a 1440px)
  il blocco dell'header alto 28px avrebbe avuto la bandiera più piccola del logo. Ora è alto un
  decimo della larghezza del logo (da 25,6 a 38,2px, nella riga di 44px); verificato da 320 a 1920px
  in header e footer.
- **CAMMINI**: il sito attuale non ha la voce. Prende la barra arancio del marchio, come le voci senza
  sezione (MAPPA INTERATTIVA).
- **X**: icona dell'uccellino come in produzione (`\f099` di Font Awesome 5 Brands sull'icona, perché
  Impreza per «twitter» stampa il logo X in SVG); link e nome accessibile restano quelli dati dal
  cliente (`x.com`, «X»).
- **URL dei social**: restano quelli forniti dal cliente. In produzione l'Instagram dell'header è
  `sardegna_sentieri` e X è `twitter.com`: non si copiano, sono contenuti decisi col cliente.
- **Ordine dei social**: header e footer come in produzione (il footer ne ha cinque, senza X).
- **Footer**: tolta «Rete Escursionistica della Sardegna · Agenzia Fo.Re.S.T.A.S.» dalla striscia,
  che in produzione ha solo il copyright. L'anno resta quello corrente (`[forestas_anno]`): in
  produzione è scritto a mano e fermo al 2024.
- **Header fermo su tablet e mobile**: come in produzione (`--header-position: fixed` sotto i 1025px
  con altezza almeno 480px, non sui tablet in orizzontale), con `position: sticky` e `top` negativo
  pari alla riga FESR, così la riga FESR esce dallo schermo e resta ferma solo la riga del sito
  attuale. Due ostacoli di Impreza: `.l-header { position: relative }` (battuto con `.header_hor`) e
  `.l-canvas { overflow: hidden }`, che impedisce lo sticky: diventa `overflow: clip` fino a 1024px.
- **Ricerca su tablet**: in produzione è un pulsante fino a 1024px; Impreza mostra il campo intero
  sopra i 600px. Le regole che Impreza applica sotto i 600px sono estese fino a 1024px. Differenza di
  comportamento rimasta: in produzione il pulsante porta alla pagina «Cerca nel sito», qui apre il
  campo sopra la riga del logo, come già sotto i 600px.
- **Pannello**: largo quanto il contenuto (`width: max-content`), con il velo grigio all'80% del sito
  attuale; un tocco sul velo chiude il menu (`menu-mobile.js`), come in produzione. Restano i
  comportamenti di accessibilità della prima versione (nome del pulsante, focus nel pannello, Esc).
- **Font**: gli stessi file del sito attuale (woff2, 400 e 600), nel child con le licenze OFL. Work
  Sans ha un nome proprio («Work Sans Sardegna Sentieri») perché la Work Sans variabile che Impreza
  caricava avrebbe disegnato un vero 500 dove il sito attuale, senza quel peso, mostra il 400. Per lo
  stesso motivo i pesi 500 e 700 del CSS del sito attuale sono stati tenuti: cadono su 400 e 600 come
  in produzione.
- **Radice a 14px sotto i 960px**, come in produzione: il tema Drupal misura in rem. Nel child,
  `html { font-size: 14px }` e la variabile `--sase-rem` per le misure di header e footer (poi
  sostituita da `rem`, che con quella radice coincide: «Review del commit 5d2a098»).
- **Accessibilità della prima versione rimossa dove cambiava l'aspetto**: target minimi di 24px sui
  link testuali (axe non segnala 2.5.8: i link rientrano nell'eccezione della spaziatura), arancio del
  pulsante «chiudi» più scuro (ora `hsl(17deg 76% 45%)` come in produzione, 4,58:1), focus `#0B63C5`
  (ora `#0089C7` con l'anello bianco del sito attuale). Restano link «Vai al contenuto», nomi delle
  `nav`, nome del pulsante del menu e gestione del focus nel pannello, che non si vedono.

**Riga dei loghi del footer** (richiesta del dev, stesso giorno): non più il blocco FESR «versione 2»
intero, ma la riga dei sei loghi del sito attuale con la sua disposizione. I primi quattro (POR FESR
2014-2020, Intense, Interreg Marittimo, Regione Sardegna) sono sostituiti dai quattro loghi del blocco
istituzionale 2021-2027, separati; Forestas e CAI restano i file del sito attuale.

- I quattro loghi sono ritagliati dal file ufficiale ad alta risoluzione (`blocco istituzionale
  sardegna 21-27_ridotto_versione 2.png`, 6148×710, cartella `Assets/sardegnaprogrammazione` del
  progetto), separatori esclusi, con la **stessa fascia verticale e la stessa scala**: le proporzioni
  fra i quattro sono quelle del blocco. Ognuno sta, centrato, su una tela trasparente nel formato dei
  loghi del sito attuale (342×178), al doppio (684×356) per gli schermi ad alta densità; poi ridotti a
  342×178, che è già il doppio delle caselle da 170px («Review del commit 5d2a098»).
- Disposizione come in produzione: caselle larghe il 25% fino a 170px, da 768px su una riga con
  `space-between`, sotto su più righe centrate; immagini in linea. Il campo Drupal ha la classe
  `clearfix`, il cui `::after` vuoto è un settimo elemento della riga flex: aggiunge uno spazio (caselle
  di 165,7px a 1440px) e sotto i 768px finisce da solo in una terza riga. Riprodotto con lo stesso
  `::after`, altrimenti caselle e altezza della riga non coincidono.
- Verifica: posizioni e misure dei sei loghi uguali alla produzione a 390, 768, 800, 1100 e 1440px
  (scarti sotto il mezzo pixel, dalle proporzioni dei vecchi file); axe senza violazioni.
- **Da confermare col cliente:** la bandiera UE resta più grande del logo del sito (da 320 a 1920px),
  ma nella riga è più piccola dei loghi di Forestas e CAI, alti quanto la casella; le linee guida
  chiedono l'emblema «mai più piccolo degli altri loghi».
- `[forestas_fesr]` serve ora solo all'header: tolti la variante `versione="2"` e il suo file
  `blocco-istituzionale-fesr-2.png`. Poi rinominato `[forestas_loghi_header]` («Review del commit
  5d2a098»).
- Nel sostituire il CSS del footer era sparita la regola che azzera il padding laterale delle sezioni
  di Impreza (contenuto rientrato di 40px e link istituzionali a capo): ripristinata, e ripetuti tutti
  i confronti.

**Loghi dell'header** (richiesta del dev, stesso giorno): sei, come nel footer. La riga dell'header è
bassa (44px su desktop, 36px sotto), quindi non i loghi impilati del footer ma il blocco «versione 1»
(testi accanto ai loghi), **intero**: ordine e proporzioni dei quattro loghi restano quelli del file
ufficiale, come chiedono le linee guida. Dopo il blocco, Forestas e CAI (gli stessi file del footer,
senza i margini trasparenti), ciascuno preceduto da un separatore con le misure di quelli del blocco
(grigio `#919292`, alto il 65%, a 0,85 altezze dai loghi) e alto quanto la bandiera UE (82% del
blocco): l'emblema non è mai più piccolo degli altri loghi. Verificato da 320 a 1920px: i sei loghi
stanno nella riga, la bandiera è alta quanto Forestas e CAI e più del logo del sito. Sotto i 600px
diventano piccoli (bandiera di 14,4px a 390px, 11,8px a 320px): è il prezzo di sei loghi su una riga.
Confronti col sito attuale e axe invariati.

**Fonte di design per i lavori futuri** (richiesta del dev): nel `CLAUDE.md`, prima voce di «Regole
del repo», il riferimento per aspetto e comportamento del sito è il frontend Drupal, con la copia del
repository su UAT (`/root/sardegnasentieri.zip`, come scaricarla ed estrarla, MD5, cartelle del tema).
La copia estratta usata per questo lavoro sta sul disco del dev, nella cartella del progetto Forestas,
non nel repo: il repo è pubblico e quel percorso vale solo sulla sua macchina.

**Regole di Impreza da battere** (dettagli nei commenti di `style.css`): margini logici fra gli
elementi delle celle dell'header; `--hwrapper-gap` di 16px scritto in linea sul gruppo «Seguici su»
del footer; `background: none !important` sul pulsante di ricerca (fondo e lente stanno sull'icona) e
`text-decoration: none !important` sui social (la sottolineatura al passaggio sta sull'icona);
`display: inline-flex` del menu del footer e il suo layout verticale responsive
(`responsive_width="0px"` nello shortcode); `margin-inline-end: -0.8rem` sull'ultimo elemento della
riga; `min-height: 50px` e padding delle voci del menu (`--menu-items-gap`).

**Theme Options** (`config/impreza.json`), dal tema Drupal:

- colori di contenuto e contenuto alternativo, header, footer e sotto-footer, barra del browser
  mobile, focus (valori nell'overview);
- testo Work Sans 400 `1rem` interlinea 1.4, grassetto 700; titoli Montserrat 600 con le misure
  fluide di `.sase-h1` e dei titoli nel corpo delle pagine (`.sase-page-body`). Lo spazio sotto i
  titoli è quello che il tema Drupal mette prima dell'elemento seguente; h1 non ne ha uno (`margin:
  unset`): 0.67em, il valore predefinito dei browser;
- famiglie scritte con la virgola (`"Montserrat", sans-serif`): Impreza salta i nomi con la virgola
  quando raccoglie i Google Fonts da scaricare (`us-core/functions/helpers.php`), così vale l'
  `@font-face` del child. Senza più Google Fonts, «Store Google Fonts locally» va spento: acceso,
  Impreza non arriva mai allo stato «scaricati» e l'apply segnalerebbe sempre una differenza;
- pulsanti: primario e chiaro del sito attuale. Impreza non ha l'altezza fissa (3em) né la larghezza
  minima (165px) del pulsante Drupal: il padding verticale 0.95em dà circa la stessa altezza, la
  larghezza minima non c'è;
- primario e secondario diventano arancio e blu del sito attuale: `--sar-blu` e `--sar-verde` del child,
  che vi rimandavano, ora hanno i valori del design system scritti (`#254877`, `#566D27`). Poi tolte, con le altre
  variabili del prototipo, perché non le usava più nessuno («Review del commit 5d2a098»).

Apply, export e secondo apply: «nessuna differenza».

**Bug trovato e corretto: menu aperto dopo uno scorrimento.** Con l'header sticky, il blocco dello
scorrimento su `<html>` (`overflow: hidden`) fermava lo sticky: la riga del pulsante usciva dallo
schermo e `menu-mobile.js`, per riportarla in vista, faceva tornare la pagina in cima. Il blocco ora
sta sul body, come sul sito attuale. Verificato da anonimo a 390 e 768px su touch e da loggato a 390px
(barra di amministrazione che scorre) e 700px (barra fissa): la pagina resta dov'era, la riga del
pulsante resta in vista (sotto la barra, se è fissa) e il pannello parte subito sotto; a 768px un
tocco sul velo chiude il menu. Con una finestra fra 576 e 1024px più larga che alta (per esempio
900×800 col mouse) l'header non è fermo, come in produzione, e la pagina risale fino all'header per
mostrare «chiudi», come nella versione precedente.

## Review del commit 5d2a098 (2026-10-14)

Review da un'altra sessione sul diff `develop...HEAD`: da correggere, 2 bloccanti e diversi cleanup.
Il ticket resta com'è (released, decisione del dev).

**Bloccante 1: il tocco sul velo attivava il link sotto.** Impreza chiude il menu al `touchend`
(`closeOnClickOutside` in `common/js/elements/menu.js`): quando arrivava il `click`, il menu risultava
già chiuso e il listener del child, che doveva annullarlo, non interveniva mai; il velo, pseudo-elemento
del body, era sparito e il click sintetico cadeva sul link sotto. Ora `menu-mobile.js` ascolta il
`touchend` in fase di cattura e, solo se il bersaglio è il velo (body o html) a menu aperto, annulla il
click sintetico; la chiusura resta a Impreza. Tolto il vecchio listener sul `click`. Provato con un link
sotto il punto toccato, a 800 e 1024px su touch: il menu si chiude e il link non si attiva (da anonimo
e da loggato); da loggato la barra di amministrazione resta cliccabile a menu aperto. Col mouse non
serve: il click va all'antenato comune di mousedown e mouseup, il body.

**Bloccante 2: bandiera UE più piccola del logo del sito fra circa 430 e 500px.** Sotto i 600px il
logo del sito si restringe accanto a lingue, ricerca e menu più lentamente dei sei loghi, che
dipendono dalla larghezza intera. Sotto i 600px la riga ha margini di 10px invece di 5vmin e gli spazi
intorno ai separatori scendono da 0,85 a 0,5 altezze (`--loghi-spazio`): blocco alto 20px a 390px.
Verificato ogni 5px da 320 a 1024px e a 1025, 1100, 1199, 1200, 1440 e 1920px: bandiera sempre più
grande del logo del sito (margine minimo 1px a 1025px), Forestas e CAI mai più alti della bandiera,
loghi sempre dentro la riga. La verifica «da 320 a 1920px» scritta prima era fatta su poche larghezze.

**Cleanup fatti**

- Tolte dal child le variabili del prototipo (`--sar-*`, tinte, `--st-*`, `--tipo-*`, `--focus`),
  inutilizzate o in conflitto con le `--sase-*`.
- Le `--sase-*` dei colori che stanno anche nelle Theme Options rimandano alle variabili di Impreza
  (`--color-content-*`, `--color-subfooter-*`); i pulsanti in `impreza.json` tornano a riferimenti
  (`_content_primary`, `_content_link_hover`, `_content_text`, `_content_bg`).
- `rem` al posto di `--sase-rem`, che lo replicava: con la radice a 14px sotto i 960px coincidono. La
  radice sta in una sezione «Base» del CSS, che dice che vale per tutto il sito.
- Variabili per i valori ripetuti (`--sase-larghezza-logo`, `--sase-spazio-riga-logo`,
  `--sase-carattere-piccolo`, `--sase-larghezza-contenuto`, `--sase-separatore-loghi`, `--sase-fondo`).
- Pulsante ricerca su touch a 45px (2.858em faceva 51px fra 960 e 1024px); social del pannello: 45px
  solo sotto i 768px, senza riscrivere i valori di base; blocco dei loghi mobile dentro il blocco
  `@media (max-width: 1024px)` principale; una sola forma di selettore per l'hover dei social.
- «Vai al contenuto» con l'aspetto dei link di salto del sito attuale (`.sase-skip-links`): fondo
  bianco, colore dei link, peso 500, contorno del colore del testo.
- Shortcode `[forestas_fesr]` rinominato `[forestas_loghi_header]` (stampa sei loghi), con la classe
  del contenitore `forestas-loghi-header-wrap`; helper `forestas_immagine()` per tutte le `<img>` del
  child; tolte dal footer le classi `forestas-footer-sx`/`-dx`, senza CSS.
- Immagini alle misure utili: loghi nuovi del footer 342×178 (il doppio delle caselle da 170px), blocco
  dell'header 1100×77, Forestas e CAI dell'header alti 64px. L'header carica 81 KB di loghi invece di
  157. Forestas e CAI restano in due copie: il footer ha bisogno dei margini trasparenti del sito
  attuale per avere le stesse caselle, l'header no.
- Documenti: commenti e knowledge sul font Work Sans, sul `device-width` e sulla riserva di
  `--forestas-sotto-header`; trappola dello sticky con le eccezioni; trappola sulle regole di Impreza
  da battere; selettori interni di Impreza nelle «Dipendenze» di `inizializzazione-wordpress.md`;
  contrasti delle Theme Options sotto WCAG nei Rischi dell'overview, da far sapere al cliente.

**Non fatto, facoltativo:** `scroll-padding-top` fino a 1024px per lo skip link sotto l'header
sticky. Il focus sul `<main>` resta visibile, quindi non è una violazione di 2.4.11; l'altezza
dell'header cambia con la larghezza e un valore fisso sarebbe sbagliato a molte larghezze.

Dopo le correzioni: confronto con la produzione invariato (desktop 1100, 1280, 1440; touch 390 e 768;
mouse 800 e 1024; pannello aperto; riga dei loghi del footer), axe senza violazioni, apply ed export
senza differenze. Version del child 1.2.1.

## Follow-up

**Requisito di rilascio: prova del salvataggio dal pannello (2026-10-13).** Il dev ha salvato dal
pannello in locale, senza modifiche di contenuto, l'header IT (Header Builder) e il footer IT (Live
Builder); poi `export` e `git diff config/`:

- **header IT:** nessun valore cambiato né tolto (confronto campo per campo del JSON); l'Header
  Builder aggiunge solo i campi vuoti (`""`) del suo modulo che il JSON scritto in codice non aveva
  (`wrapper_start_*`/`wrapper_end_*`, `css`, `conditions_copy_paste`, `custom_html_atts` sugli
  elementi che non li usano, intestazioni di sezione del menu) e sposta `custom_html_atts` più in alto;
- **footer IT:** il `<br>` dopo «Contattaci» diventa un a capo (`\r\n`); la pagina stampa comunque
  `Contattaci<br />`, quindi a schermo non cambia nulla;
- **header e footer EN non provati:** in locale WPML non è registrato (la site key di UAT non si usa
  in locale, README) e il pannello non salva le traduzioni; il dev non vuole per ora registrare il
  sito locale. Stessa struttura degli IT, generata dallo stesso script: rischio basso. Resta non
  provato solo ciò che fa WPML salvando una traduzione (campi copiati o sincronizzati fra le lingue).
  Rischio accettato dal dev; la prova si fa se il sito locale verrà registrato su WPML.

Confronto della resa (2026-10-13): stessa pagina con la versione scritta in codice e con quella salvata
dal pannello, applicate una dopo l'altra in locale; HTML di header e footer, CSS inline e posizione e
misure di ogni elemento visibile, a 1280 e a 390px. Tutto identico, tranne un a capo dopo il `<br>` di
«Contattaci» nell'HTML del footer, che non cambia la resa. Poi il sito è stato riportato alla versione
salvata, e l'export coincide con quella.

Esito: modificarli dal pannello è sicuro, almeno per le copie IT.

Modifica a `overview.md`: il requisito sul salvataggio una tantum dal pannello ora dice che è fatto
per le copie IT e non per le EN, con il motivo.

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
- Ricerca su tablet e mobile: in produzione il pulsante porta alla pagina «Cerca nel sito», qui apre
  il campo nell'header; da decidere quando ci sarà la pagina dei risultati.
- Far validare al cliente le etichette inglesi, in particolare «WALKS» per «Cammini».
