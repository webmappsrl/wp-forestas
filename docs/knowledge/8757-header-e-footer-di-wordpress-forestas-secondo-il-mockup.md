# Header e footer del sito

## Come funziona oggi

Header e footer sono oggetti nativi di Impreza scritti in codice: l'header è il template `us_header`
«Header» (Templates → Headers), il footer è il blocco riutilizzabile `us_page_block` «Footer»
(Templates → Reusable Blocks), ciascuno con la sua traduzione inglese WPML. Il contenuto sta in
`config/post.json`, i tre menu (`main-menu`, `servizio-menu`, `footer-menu` e i loro `-en`) in
`config/menu.json`, e arrivano nel sito con `bin/wordpress-config.sh apply` (README, «Configurazione
versionata»). Ciò che Impreza non sa fare sta nel child theme.

Header e footer sono **identici a quelli di sardegnasentieri.it** (tema Drupal «sardegnasentieri»,
copia del repository su UAT: `CLAUDE.md`, «Regole del repo»),
tranne le aggiunte concordate: blocco istituzionale FESR e voce CAMMINI. Misure, colori e font sono
nelle variabili `--sase-*` di `style.css`, con i valori del tema Drupal; anche colori e tipografia
delle Theme Options (`config/impreza.json`) vengono da lì. Un ritocco va confrontato col sito in
produzione: ogni differenza va concordata col cliente (capitolato, scrum del 14/10/2026).

- **Header JSON dentro JSON:** `post_content` dell'header è il JSON dell'Header Builder, salvato come
  stringa in `config/post.json`. Gli elementi `html` hanno il contenuto in `base64(rawurlencode(…))`
  (`us-core/templates/elements/html.php`). Per cambiare l'header conviene farlo dal pannello in
  locale e poi `export`, invece di scrivere a mano nel JSON. Su questi header e footer, scritti in
  codice, il giro è stato provato sulle copie IT: il pannello non cambia nessun valore, aggiunge solo
  i campi vuoti del suo modulo (header) e trasforma i `<br>` in a capo (footer). Le copie EN non si
  possono salvare in locale senza registrare WPML (oc:8757, `notes.md`).
- **Shortcode del child** (`functions.php`), usati dentro header e footer:
  `[forestas_logo]`, `[forestas_anno]`, `[forestas_lingue]` (IT EN da WPML, codice breve),
  `[forestas_loghi_header]` (i sei loghi dell'header: blocco istituzionale FESR «versione 1»
  intero, poi Forestas e CAI), `[forestas_loghi_footer]` (i sei loghi del footer). Le immagini stanno
  in `themes/forestas-child/assets/img/`, non negli uploads.
- **Font:** Montserrat e Work Sans del sito attuale, in `themes/forestas-child/assets/fonts/` con
  le licenze OFL, solo 400 e 600 come in produzione, usati anche dalle Theme Options per tutto il
  sito. Work Sans si chiama «Work Sans Sardegna Sentieri», per non mescolarsi con una Work Sans
  installata sul computer o caricata da un plugin, che potrebbe avere il peso 500. Sotto i 960px la
  radice è a 14px, come sul sito attuale (sezione «Base» di `style.css`): vale per tutto il sito, e
  le misure in rem di header e footer coincidono con quelle di produzione.
- **Desktop (da 1025px):** tre righe, contenuto largo al massimo 1294px. Servizio (36px più 5px sopra
  e sotto), logo e menu (fra 1 e 2,5rem sopra e sotto), sei loghi centrati (44px). Al passaggio del
  mouse, al focus e sulla voce attiva compare sotto la voce una barra di 3,3px sul bordo della riga,
  nel colore della sezione del sito attuale, scelto dalla classe della voce (`tipo-sentiero`, …).
- **Tablet e mobile (fino a 1024px):** riga dei loghi in alto, poi la riga del sito attuale (logo, IT EN,
  ricerca, menu). L'header resta fermo in cima con le condizioni del sito attuale: `position: sticky`
  con `top` negativo quanto la riga FESR, che quindi esce dallo schermo; non sugli schermi alti meno
  di 480px né sui dispositivi con lo schermo fra 576 e 1024px tenuti in orizzontale (`device-width`:
  conta lo schermo, non la finestra). Lo sticky funziona solo perché
  `.l-canvas` ha `overflow: clip` invece dell'`hidden` di Impreza. Il menu è un pannello che entra da
  destra (`mobile_layout="panel"`), largo quanto il contenuto, alto dal bordo inferiore dell'header al
  fondo dello schermo, con un velo scuro sulla pagina. Un tocco sul velo chiude il menu: lo fa
  Impreza al `touchend`, e `menu-mobile.js` annulla in fase di cattura il click che il browser
  genererebbe subito dopo, che cadrebbe sul link sotto il velo appena sparito. Quel
  bordo lo calcola `menu-mobile.js` all'apertura (`--forestas-sotto-header`): dipende da quanto la
  pagina è scesa e dalla barra di amministrazione, fissa in cima da 601px (46px fino a 782px, 32px
  oltre) e che scorre con la pagina sotto i 600px: il solo CSS non può conoscerlo; in coda alle voci un filtro
  `wp_nav_menu_items` del child aggiunge i link di servizio e i social. Social e «Seguici su» si
  leggono dall'header della lingua corrente, cercando gli elementi per tipo e classe (non per id, che
  il builder rinumera), così il PHP non ripete gli URL; gli URL stanno comunque in ognuna delle
  quattro copie di header e footer (IT, EN) di `config/post.json`. A menu aperto la pagina non scorre
  (`overflow: hidden` sul body quando `<html>` ha `w-nav-open`, classe che mette e toglie Impreza in
  `menu.js`). Il blocco sta sul body come in produzione: su `<html>` fermerebbe lo sticky.
  Limite noto: il pannello si posiziona solo all'apertura e al ridimensionamento, e presuppone la
  pagina bloccata.
- **Footer:** in cima la riga dei sei loghi come sul sito attuale: i primi quattro sono i loghi del
  blocco istituzionale FESR, ritagliati uno per uno dal file ufficiale «versione 2» con la stessa
  scala (`assets/img/loghi-footer/`), gli ultimi due Forestas e CAI. La disposizione copia anche il
  `clearfix` del campo Drupal, il cui `::after` vuoto è un settimo elemento della riga flex: dà le
  caselle di 165,7px a 1440px e, sotto i 768px, una terza riga vuota; poi
  il filo arancio di 5px, logo e contatti a sinistra, social e link istituzionali a destra, e la
  striscia grigio scuro con l'anno corrente.
- **Accessibilità** (Linee guida AgID: EN 301 549, WCAG 2.1 AA): link «Vai al contenuto» da
  `wp_body_open`, primo elemento raggiungibile da tastiera per chi non è loggato (per chi lo è viene
  dopo «Vai alla barra degli strumenti» di WordPress); per chi è loggato compare sotto la barra di
  amministrazione, anche su una pagina già
  scesa: lo garantisce lo `scroll-padding-top` che WordPress stesso mette su `html` per chi è
  loggato (`admin-bar.css`), non il child; col mouse sopra il link tiene il bianco grazie a
  `.no-touch .forestas-salta:hover`, che batte `.no-touch a:hover` di Impreza; `assets/js/menu-mobile.js` cambia insieme scritta e nome del pulsante («menu» /
  «chiudi», WCAG 2.5.3) e tiene il focus nel pannello finché il pulsante si vede (Impreza non chiude il
  pannello quando la finestra passa al desktop); ascolta in fase di cattura, perché Impreza chiude il
  pannello con Maiusc+Tab sulla prima voce. Esc lo gestisce Impreza, che riporta anche il focus sul
  pulsante. All'apertura, se la riga del pulsante è tagliata in alto o finisce sotto la barra di
  amministrazione fissa, la porta tutta in vista:
  a menu aperto la pagina non scorre e «chiudi» resterebbe in parte nascosto (WCAG 2.4.11). Il colore
  di «chiudi» col mouse sopra lo decide il child con `.l-subheader` nel selettore, per battere
  `.no-touch .l-subheader.at_middle a:hover` di Impreza (trappola nel `CLAUDE.md`). Le `nav` hanno nomi distinti con gli attributi HTML personalizzati di Impreza
  (`custom_html_atts`).

Vincoli:

- l'emblema UE del blocco FESR non può essere più piccolo degli altri loghi (Linee guida beneficiari
  PR FESR Sardegna): nell'header Forestas e CAI sono alti quanto la bandiera (82% del blocco, misure
  in `--loghi-h` di `style.css`), e la bandiera non è più piccola nemmeno del logo del sito. Sul
  desktop il blocco è alto un decimo della larghezza del logo, che cambia con la finestra; fino a
  1024px è alto quanto entra nella riga, e sotto i 600px, dove il logo del sito si restringe accanto a
  lingue, ricerca e menu, i margini della riga e gli spazi fra i loghi si riducono per tenere la
  bandiera più grande. Verificato ogni 5px da 320 a 1024px e a 1025, 1100, 1199, 1200, 1440 e 1920px
  nell'header, da 320 a 1920px nel footer. Chi cambia logo, loghi o spazi deve rimisurare;
- le famiglie dei font nelle Theme Options sono scritte con la virgola (`"Montserrat", sans-serif`):
  Impreza salta questi nomi quando raccoglie i Google Fonts, così valgono i font del child. Non sono
  fra le scelte del pannello: salvare la tipografia dalle Theme Options può sostituirle;
- Impreza dà al pannello mobile un `min-height` pari allo schermo intero: il child lo annulla, se no
  il fondo con servizio e social finisce fuori dallo schermo;
- `us_og_image` è fra i meta calcolati che l'export esclude (`WPF_META_CALCOLATI`): Impreza lo
  ricalcola a ogni salvataggio dalla prima immagine del contenuto (oggi il primo logo del footer),
  che porta `?ver=` della `Version` del child, e cambierebbe a ogni nuova versione;
- **rilascio e rollback toccano sempre insieme child e `config/`:** il child arriva con il pull,
  header e footer solo con l'apply. Con il solo child il sito mostra header e footer demo con il CSS
  nuovo; con la sola configurazione (o tornando indietro solo sul child) mostra gli shortcode
  `[forestas_logo]`, `[forestas_loghi_header]`, `[forestas_loghi_footer]`, `[forestas_lingue]`,
  `[forestas_anno]` come testo. Il
  rollback, a passi:
  1. riporta il sito alla configurazione precedente con `bin/wordpress-config.sh apply --da
     <backup> --conferma` (README, «Configurazione versionata»). Il backup è il **primo** fatto nel
     rilascio, quello creato dal primo apply di oc:8757: ogni `apply --conferma` ne crea uno nuovo,
     e i successivi contengono già header e footer nuovi. Il percorso di `--da` si legge rispetto
     alla cartella da cui si lancia lo script, mentre i backup stanno in `wp-forestas/backup/`: da
     `forestas`, come nella procedura per UAT, si scrive `--da wp-forestas/backup/<data-ora>`;
  2. subito dopo torna indietro sul child, così gli shortcode non restano a lungo senza la loro
     definizione. Su UAT il child è il submodule di `forestas`: `git revert` in `wp-forestas`,
     puntatore aggiornato in `forestas` con l'hash letto da `git rev-parse` (`CLAUDE.md`), poi
     `scripts/wordpress-up.sh`. Un checkout fatto a mano sul server non dura;
  3. cancella a mano ciò che l'apply non toglie, perché non cancella né i post né i menu assenti da
     `config/` (`menu.php` toglie solo voci; il suo `wp_delete_nav_menu` annulla una creazione
     fallita): in Templates → Headers e in Reusable Blocks, con la lingua EN, header e footer
     inglesi, poi svuota il cestino; in Aspetto → Menu `servizio-menu`, `footer-menu`, `main-menu-en`,
     `servizio-menu-en` e `footer-menu-en`. `main-menu-en`, traduzione WPML di `main-menu`, darebbe
     altrimenti le 7 voci nuove alle pagine inglesi.
- **prima della messa in produzione:** il link «Dichiarazione di accessibilità» del footer (IT ed EN)
  è `#` e deve portare alla dichiarazione dell'ente pubblicata sul portale AgID: è un obbligo di
  legge (L. 4/2004). L'indirizzo lo fornisce il cliente; si cambia in `config/menu.json`
  (`footer-menu` e `footer-menu-en`) e si applica.

## Perché così

- **Oggetti di Impreza scritti in codice, non template PHP nel child** (oc:8757): restano visibili e
  modificabili dove Impreza li mette, e sono versionati come il resto della configurazione. I template
  PHP avrebbero legato il child alla struttura interna di `us-core`.
- **Logo, anno, lingue e loghi FESR come shortcode del child** (oc:8757): un file in `assets/` e un
  URL calcolato da `get_stylesheet_directory_uri()` valgono in locale, UAT e produzione; un percorso
  scritto nel JSON o un'immagine negli uploads no.
- **Selettore di lingua da shortcode** (oc:8757): il `dropdown` di Impreza con `source=wpml` mostra
  solo bandiere o nomi lunghi, non «IT EN» in linea come sul sito attuale.
- **Identici al sito attuale** (oc:8757): il capitolato chiede la stessa UI del sito Drupal, salvo
  modifiche concordate; il cliente non distingue fra Drupal e WordPress.
- **Font nel child e non da Google** (oc:8757): gli stessi file del sito attuale danno la stessa resa;
  la Work Sans variabile di Google, che Impreza caricava, avrebbe disegnato un peso 500 che in
  produzione non c'è.
- **Menu letti e scritti su tutte le lingue** (oc:8757): con i filtri di WPML attivi, che si accendono
  completando il primo passo del wizard («lingua dei contenuti esistenti»), la lingua corrente di
  WP-CLI è quella predefinita: `get_terms()` non vede i menu tradotti e `get_term()` converte l'id di
  un menu tradotto in quello del suo originale. Apply, export e ritratto lavorano sui menu dentro
  `wpf_wpml_tutte_le_lingue()` (`wpml.php`), e l'apply completa quel passo anche sui siti configurati
  dagli script: senza, in locale i filtri restavano spenti e i difetti si vedevano solo su UAT. Lo
  stesso vale per il child: il filtro del pannello confronta i menu nella lingua predefinita.
- **Header e footer copiati per lingua** (oc:8757): WPML traduce gli oggetti dei builder di Impreza
  come post separati, uno per lingua. Ridurre la copia vorrebbe dire tenere un solo header e tradurne
  i testi con WPML String Translation dentro il JSON dell'Header Builder: non provato. Rischio
  accettato: un URL social sta in quattro posti (header e footer, IT ed EN), e `mobile_width` o
  un'altra opzione dell'header va cambiata in due. Chi cambia uno dei due header cambia anche l'altro,
  poi `export` e `git diff config/` mostrano se le copie sono allineate.
- **Prefissi** (oc:8757): `forestas_` per funzioni e shortcode del child, `wpf_` per gli script di
  configurazione in `docker/scripts/config/`.
- **Loghi FESR in header e footer** (oc:8757): richiesti dal cliente per le regole di visibilità del
  PR FESR 2021-2027; in footer sarebbe bastato, l'header segue la buona prassi di mostrarli senza
  scorrere. In tutti e due i posti ci sono gli stessi sei loghi (i quattro del blocco, Forestas e
  CAI): nel footer separati, nella disposizione del sito attuale; nell'header il blocco «versione 1»
  intero, perché la riga è bassa e il blocco ufficiale non va scomposto dove non serve.

## Come ci siamo arrivati

- **Header e footer del prototipo «Canvas Fase 2»** (oc:8757, superata il 14/10/2026): il prototipo
  si discostava dal sito attuale (voce del menu al passaggio del mouse, pesi e colori dei testi) e il
  capitolato chiede la stessa UI. Con lui sono caduti anche i ritocchi di accessibilità che cambiavano
  l'aspetto: target di 24px sui link testuali, arancio più scuro del pulsante «chiudi», focus blu.
- **Header non sticky su tablet e mobile** (oc:8757, superata il 14/10/2026): era la scelta del dev,
  ma il sito attuale tiene fermo l'header. Ora è sticky come in produzione.
- **Social solo nel footer su mobile** (oc:8757, superata): evitava di ripetere gli URL in PHP. Il dev
  li ha voluti nel pannello come sul sito attuale; ora il filtro li legge dall'header.
- **Blocco FESR nella riga di servizio su desktop** (oc:8757, superata): per farlo stare servivano
  spazi ridotti, «Seguici su» nascosto e ricerca stretta fino a 1439px. Spostato in una terza riga.
- **Header fisso (sticky) di Impreza su tablet e mobile** (oc:8757, superata): da loggato era
  rotto: sotto i 600px la barra di amministrazione scorre via e Impreza teneva l'header a 46px dal
  bordo, e il pannello, posizionato con il solo CSS, copriva la riga del logo. Lo sticky di oggi è
  del child (`position: sticky`), non l'opzione di Impreza.
- **Pagina bloccata con `overflow: hidden` su `<html>`** (oc:8757, superata): con l'header sticky,
  aprire il menu dopo uno scorrimento faceva tornare la pagina in cima.
- **Altezza dell'header mobile scritta a mano (96px) nel CSS** (oc:8757, superata): andava
  aggiornata a ogni cambio delle righe nell'Header Builder. Ora il bordo dell'header lo calcola
  `menu-mobile.js` (`--forestas-sotto-header`); nel CSS resta solo una riserva fissa, la riga dei
  loghi più 92px, se lo script non gira.
- **Social e «Seguici su» letti per id (`socials:1`, `html:1`)** (oc:8757, superata): ricreando
  l'elemento dal pannello l'id cambia e la riga sparisce senza errori.
- **Esc gestito anche dallo script del child** (oc:8757, superata): lo fa già Impreza.
- **Pagina bloccata con `html:has(… [aria-expanded="true"])`** (oc:8757, superata): non funziona sui
  browser senza `:has()`, come Safari iOS precedente alla 16. Impreza mette già `w-nav-open` su
  `<html>` a menu aperto.
- **`scroll-padding-top` della barra di amministrazione nel child** (oc:8757, superata): era un
  doppione di quello che WordPress mette già per chi è loggato.
- **Opzione nativa «skip_to_content_btn» di Impreza** (oc:8757, scartata): ha un'etichetta sola per
  tutte le lingue nelle Theme Options e mette `role="button"` su un link.
- **Pannello con `bottom: 0`** (oc:8757, superata): Impreza dà al pannello `min-height` pari allo
  schermo intero, e il fondo con social e servizio finiva fuori. Ora altezza esplicita e
  `min-height: 0`.
