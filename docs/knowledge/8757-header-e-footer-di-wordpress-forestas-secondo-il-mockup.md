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
  `[forestas_logo]`, `[forestas_anno]`, `[forestas_lingue]` (IT · EN da WPML, codice breve),
  `[forestas_fesr]` / `[forestas_fesr versione="2"]` (blocco istituzionale FESR). Le immagini stanno
  in `themes/forestas-child/assets/img/`, non negli uploads.
- **Desktop (da 1025px):** tre righe. Servizio (36px), logo e menu (84px), blocco FESR centrato
  (44px). La voce attiva del menu prende il colore del tipo di contenuto dalla classe della voce
  (`tipo-sentiero`, …), con una sottolineatura legata al testo.
- **Tablet e mobile (fino a 1024px):** l'header è fisso (`sticky` solo per `tablets` e `mobiles`) e
  alto **96px** (riga in alto 36 + riga del logo 60). Il menu è un pannello che entra da destra
  (`mobile_layout="panel"`), alto `100dvh − 96px`; in coda alle voci un filtro `wp_nav_menu_items` del
  child aggiunge i link di servizio e i social, letti dall'header della lingua corrente così gli URL
  restano solo in `config/`. A menu aperto la pagina non scorre (`html:has(…)` con
  `overflow: hidden`).
- **Footer:** blocco FESR («versione 2») in cima, poi il filo oro, poi logo, social, contatti, link
  istituzionali e la striscia scura con l'anno corrente.
- **Accessibilità** (Linee guida AgID: EN 301 549, WCAG 2.1 AA): link «Vai al contenuto» da
  `wp_body_open`; `assets/js/menu-mobile.js` allinea il nome del pulsante alla scritta («Menu» /
  «Chiudi»), chiude il pannello con Esc e tiene il focus nel pannello; le `nav` hanno nomi distinti
  con gli attributi HTML personalizzati di Impreza (`custom_html_atts`).

Vincoli:

- l'emblema UE del blocco FESR non può essere più piccolo degli altri loghi (Linee guida beneficiari
  PR FESR Sardegna): le altezze del blocco e del logo del sito sono scelte insieme, a ogni larghezza;
- il pannello mobile è legato ai 96px dell'header (vedi la trappola nel `CLAUDE.md`);
- `us_og_image` è fra i meta calcolati che l'export esclude (`WPF_META_CALCOLATI`): Impreza lo
  ricalcola dal logo a ogni salvataggio e cambierebbe a ogni `Version` del child.

## Perché così

- **Oggetti di Impreza scritti in codice, non template PHP nel child** (oc:8757): restano visibili e
  modificabili dove Impreza li mette, e sono versionati come il resto della configurazione. I template
  PHP avrebbero legato il child alla struttura interna di `us-core`.
- **Logo, anno, lingue e loghi FESR come shortcode del child** (oc:8757): un file in `assets/` e un
  URL calcolato da `get_stylesheet_directory_uri()` valgono in locale, UAT e produzione; un percorso
  scritto nel JSON o un'immagine negli uploads no.
- **Selettore di lingua da shortcode** (oc:8757): il `dropdown` di Impreza con `source=wpml` mostra
  solo bandiere o nomi lunghi, non «IT · EN» in linea come nel mockup.
- **Menu mobile come sul sito attuale** (oc:8757): pannello da destra, header fisso, pagina bloccata,
  pulsanti a riquadro. Il fondo del pulsante aperto è più scuro di quello di produzione
  (`hsl(17deg 76% 40%)` contro 45%) per arrivare a 4,5:1 col testo bianco.
- **Blocco FESR in header e footer** (oc:8757): richiesto dal cliente per le regole di visibilità del
  PR FESR 2021-2027; in footer sarebbe bastato, l'header segue la buona prassi di mostrarlo senza
  scorrere.

## Come ci siamo arrivati

- **Social solo nel footer su mobile** (oc:8757, superata): evitava di ripetere gli URL in PHP. Il dev
  li ha voluti nel pannello come sul sito attuale; ora il filtro li legge dall'header.
- **Blocco FESR nella riga di servizio su desktop** (oc:8757, superata): per farlo stare servivano
  spazi ridotti, «Seguici su» nascosto e ricerca stretta fino a 1439px. Spostato in una terza riga.
- **Pannello con `bottom: 0`** (oc:8757, superata): Impreza dà al pannello `min-height` pari allo
  schermo intero, e il fondo con social e servizio finiva fuori. Ora altezza esplicita e
  `min-height: 0`.
