# Header e footer del sito

## Come funziona oggi

Header e footer sono oggetti nativi di Impreza scritti in codice: l'header è il template `us_header`
«Header» (Templates → Headers), il footer è il blocco riutilizzabile `us_page_block` «Footer»
(Templates → Reusable Blocks), ciascuno con la sua traduzione inglese WPML. Il contenuto sta in
`config/post.json`, i tre menu (`main-menu`, `servizio-menu`, `footer-menu` e i loro `-en`) in
`config/menu.json`, e arrivano nel sito con `bin/wordpress-config.sh apply` (README, «Configurazione
versionata»). Ciò che Impreza non sa fare sta nel child theme.

- **Header JSON dentro JSON:** `post_content` dell'header è il JSON dell'Header Builder, salvato come
  stringa in `config/post.json`. Gli elementi `html` hanno il contenuto in `base64(rawurlencode(…))`
  (`us-core/templates/elements/html.php`). Per cambiare l'header conviene farlo dal pannello in
  locale e poi `export`, invece di scrivere a mano nel JSON.
- **Shortcode del child** (`functions.php`), usati dentro header e footer:
  `[forestas_logo]`, `[forestas_anno]`, `[forestas_lingue]` (IT EN da WPML, codice breve),
  `[forestas_fesr]` / `[forestas_fesr versione="2"]` (blocco istituzionale FESR). Le immagini stanno
  in `themes/forestas-child/assets/img/`, non negli uploads.
- **Desktop (da 1025px):** tre righe. Servizio (36px), logo e menu (84px), blocco FESR centrato
  (44px). La voce attiva del menu prende il colore del tipo di contenuto dalla classe della voce
  (`tipo-sentiero`, …), con una sottolineatura legata al testo.
- **Tablet e mobile (fino a 1024px):** l'header non è sticky (`sticky` 0 a ogni larghezza):
  sta in cima alla pagina e scorre con essa, come su desktop. È alto 96px (riga in alto 36 + riga del
  logo 60), che Impreza pubblica in `--header-sticky-height`. Il menu è un pannello che entra da
  destra (`mobile_layout="panel"`), alto dal bordo inferiore dell'header al fondo dello schermo. Quel
  bordo lo calcola `menu-mobile.js` all'apertura (`--forestas-sotto-header`): dipende da quanto la
  pagina è scesa e dalla barra di amministrazione, che sotto i 600px scorre con la pagina e che il
  solo CSS non può conoscere; in coda alle voci un filtro
  `wp_nav_menu_items` del child aggiunge i link di servizio e i social. Social e «Seguici su» si
  leggono dall'header della lingua corrente, cercando gli elementi per tipo e classe (non per id, che
  il builder rinumera), così il PHP non ripete gli URL; gli URL stanno comunque in ognuna delle
  quattro copie di header e footer (IT, EN) di `config/post.json`. A menu aperto la pagina non scorre
  (`html:has(…)` con `overflow: hidden`).
- **Footer:** blocco FESR («versione 2») in cima, poi il filo oro, poi logo, social, contatti, link
  istituzionali e la striscia scura con l'anno corrente.
- **Accessibilità** (Linee guida AgID: EN 301 549, WCAG 2.1 AA): link «Vai al contenuto» da
  `wp_body_open`, che per chi è loggato compare sotto la barra di amministrazione; `assets/js/menu-mobile.js` cambia insieme scritta e nome del pulsante («menu» /
  «chiudi», WCAG 2.5.3) e tiene il focus nel pannello finché il pulsante si vede (Impreza non chiude il
  pannello quando la finestra passa al desktop); ascolta in fase di cattura, perché Impreza chiude il
  pannello con Maiusc+Tab sulla prima voce. Esc lo gestisce Impreza, che riporta anche il focus sul
  pulsante. All'apertura, se la riga del pulsante è tagliata in alto, la porta tutta nello schermo:
  a menu aperto la pagina non scorre e «chiudi» resterebbe in parte nascosto (WCAG 2.4.11). Il colore
  di «chiudi» col mouse sopra lo decide il child con `.l-subheader` nel selettore, per battere
  `.no-touch .l-subheader.at_middle a:hover` di Impreza (trappola nel `CLAUDE.md`). Le `nav` hanno nomi distinti con gli attributi HTML personalizzati di Impreza
  (`custom_html_atts`).

Vincoli:

- l'emblema UE del blocco FESR non può essere più piccolo degli altri loghi (Linee guida beneficiari
  PR FESR Sardegna): le altezze del blocco e del logo del sito sono scelte insieme, a ogni larghezza;
  sotto i 600px il logo dell'header scala con la larghezza (`min(193px, 48vw)`) perché il blocco si
  restringe accanto alle lingue;
- Impreza dà al pannello mobile un `min-height` pari allo schermo intero: il child lo annulla, se no
  il fondo con servizio e social finisce fuori dallo schermo;
- `us_og_image` è fra i meta calcolati che l'export esclude (`WPF_META_CALCOLATI`): Impreza lo
  ricalcola a ogni salvataggio dalla prima immagine del contenuto (oggi il blocco FESR nel footer),
  che porta `?ver=` della `Version` del child, e cambierebbe a ogni nuova versione;
- **rilascio e rollback toccano sempre insieme child e `config/`:** il child arriva con il pull,
  header e footer solo con l'apply. Con il solo child il sito mostra header e footer demo con il CSS
  nuovo; con la sola configurazione (o tornando indietro solo sul child) mostra gli shortcode
  `[forestas_logo]`, `[forestas_fesr]`, `[forestas_lingue]`, `[forestas_anno]` come testo. Tornando indietro, i post
  inglesi di header e footer restano nel sito e vanno cancellati a mano dal pannello.

## Perché così

- **Oggetti di Impreza scritti in codice, non template PHP nel child** (oc:8757): restano visibili e
  modificabili dove Impreza li mette, e sono versionati come il resto della configurazione. I template
  PHP avrebbero legato il child alla struttura interna di `us-core`.
- **Logo, anno, lingue e loghi FESR come shortcode del child** (oc:8757): un file in `assets/` e un
  URL calcolato da `get_stylesheet_directory_uri()` valgono in locale, UAT e produzione; un percorso
  scritto nel JSON o un'immagine negli uploads no.
- **Selettore di lingua da shortcode** (oc:8757): il `dropdown` di Impreza con `source=wpml` mostra
  solo bandiere o nomi lunghi, non «IT EN» in linea come nel mockup.
- **Menu mobile come sul sito attuale** (oc:8757): pannello da destra, pagina bloccata,
  pulsanti a riquadro. Il fondo del pulsante aperto è più scuro di quello di produzione
  (`hsl(17deg 76% 40%)` contro 45%) per arrivare a 4,5:1 col testo bianco.
- **Header e footer copiati per lingua** (oc:8757): WPML traduce gli oggetti dei builder di Impreza
  come post separati, uno per lingua. Ridurre la copia vorrebbe dire tenere un solo header e tradurne
  i testi con WPML String Translation dentro il JSON dell'Header Builder: non provato. Rischio
  accettato: un URL social sta in quattro posti (header e footer, IT ed EN), e `mobile_width` o
  un'altra opzione dell'header va cambiata in due. Chi cambia uno dei due header cambia anche l'altro,
  poi `export` e `git diff config/` mostrano se le copie sono allineate.
- **Prefissi** (oc:8757): `forestas_` per funzioni e shortcode del child, `wpf_` per gli script di
  configurazione in `docker/scripts/config/`.
- **Blocco FESR in header e footer** (oc:8757): richiesto dal cliente per le regole di visibilità del
  PR FESR 2021-2027; in footer sarebbe bastato, l'header segue la buona prassi di mostrarlo senza
  scorrere.

## Come ci siamo arrivati

- **Social solo nel footer su mobile** (oc:8757, superata): evitava di ripetere gli URL in PHP. Il dev
  li ha voluti nel pannello come sul sito attuale; ora il filtro li legge dall'header.
- **Blocco FESR nella riga di servizio su desktop** (oc:8757, superata): per farlo stare servivano
  spazi ridotti, «Seguici su» nascosto e ricerca stretta fino a 1439px. Spostato in una terza riga.
- **Header fisso (sticky) su tablet e mobile** (oc:8757, superata): copiava il sito attuale, ma il
  dev vuole l'header fermo in cima come su desktop. Da loggato era anche rotto: sotto i 600px la barra
  di amministrazione scorre via e Impreza teneva l'header a 46px dal bordo, e il pannello, posizionato
  con il solo CSS, copriva la riga del logo.
- **Altezza dell'header mobile scritta a mano (96px) nel CSS** (oc:8757, superata): andava
  aggiornata a ogni cambio delle righe nell'Header Builder; ora il CSS usa `--header-sticky-height`.
- **Social e «Seguici su» letti per id (`socials:1`, `html:1`)** (oc:8757, superata): ricreando
  l'elemento dal pannello l'id cambia e la riga sparisce senza errori.
- **Esc gestito anche dallo script del child** (oc:8757, superata): lo fa già Impreza.
- **Opzione nativa «skip_to_content_btn» di Impreza** (oc:8757, scartata): ha un'etichetta sola per
  tutte le lingue nelle Theme Options e mette `role="button"` su un link.
- **Pannello con `bottom: 0`** (oc:8757, superata): Impreza dà al pannello `min-height` pari allo
  schermo intero, e il fondo con social e servizio finiva fuori. Ora altezza esplicita e
  `min-height: 0`.
