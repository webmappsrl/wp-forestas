> Ticket: oc:8757

# Header e footer di WordPress Forestas — piano di implementazione

> **Per chi esegue:** sub-skill richiesta: superpowers:subagent-driven-development (consigliata) o
> superpowers:executing-plans. I passi usano le checkbox (`- [ ]`).
>
> **Nessun `git commit`, `git add`, `git push` o branch automatico.** I passi «Commit» sono
> istruzioni testuali per l'utente, che committa lui.

**Obiettivo:** header e footer del mockup «Canvas Fase 2», in italiano e in inglese, come oggetti
nativi di Impreza versionati in `config/`.

**Architettura:** il contenuto di header (`us_header`, JSON dell'Header Builder) e footer
(`us_page_block`, shortcode WPBakery/Impreza) e dei tre menu si scrive in `config/post.json` e
`config/menu.json`; `bin/wordpress-config.sh apply --conferma` lo porta nel sito locale; un
salvataggio dal pannello normalizza il JSON; `export` lo riporta in `config/`. Logo, anno e stili che
Impreza non copre stanno nel child theme.

**Stack:** WordPress, Impreza 9.4 + UpSolution Core 9.4, WPML (it, en-us), WP-CLI 2.12 nel container
`wordpress-forestas`, sito locale `http://localhost:8090`.

**Spec:** [overview.md](overview.md)

## Vincoli globali

- Tutto il lavoro è nel repo `wp-forestas`, branch `feature/oc-8757-header-e-footer-di-wordpress-forestas-secondo-il-mockup`.
- Il repo è pubblico: `git diff config/` prima di ogni commit; mai committare zip di Impreza/WPML né
  valori del `.env`.
- Colori solo da variabili (`--sar-*`, `--tipo-*`, `--focus`) o dai colori delle Theme Options
  (`_header_*`, `_footer_*`); nessun esadecimale dentro `config/post.json`.
- Chiavi delle traduzioni: `<chiave originale>-en-us`, con `"lingua": "en-us"` e
  `"originale": "<chiave originale>"`.
- Ogni modifica di `style.css` alza `Version` nell'intestazione del child.
- Gli URL social si usano esattamente come in overview, WhatsApp compreso.
- Tutti i link dei menu e di servizio sono `#`.
- Comandi WP-CLI: `docker exec wordpress-forestas wp --allow-root <…>`.

## Punti da guardare in review

1. **Voce attiva su un link `#`**: WordPress non mette `current-menu-item`; lo stile si prova solo con
   un collegamento temporaneo a una pagina vera (Task 5, passo 3), che non deve finire in `config/`.
2. **Versione inglese che mostra l'header italiano**: aprendo `/en/` header, footer e menu devono
   essere in inglese (Task 6, passo 4).
3. **Shortcode stampato come testo**: in nessuna pagina deve comparire `[forestas_logo]` o
   `[forestas_anno]` letterale (Task 3 passo 4, Task 4 passo 3).
4. **Diff dopo l'export**: dopo apply + salvataggio dal pannello + export, un secondo export non deve
   cambiare `config/` (Task 7, passo 1).
5. **Menu che va a capo** fra 900 e 1440 px (Task 7, passo 2).

---

### Task 1: Logo e anno nel child theme

**File:**
- Crea: `themes/forestas-child/assets/img/logo.png` (copia di
  `/Volumes/Crucial X6 1/Siti Webmapp/Wordpress/Forestas/Assets/logo.png`, 1155×90)
- Modifica: `themes/forestas-child/functions.php`

**Interfacce:**
- Produce: shortcode `[forestas_logo]` → `<a class="forestas-logo" href="<home nella lingua corrente>"><img src="<child>/assets/img/logo.png?ver=<Version child>" alt="Sardegna Sentieri" width="1155" height="90"></a>`;
  shortcode `[forestas_anno]` → anno corrente (`wp_date( 'Y' )`).

- [x] **Passo 1: verifica che gli shortcode non esistano ancora**

  Run: `docker exec wordpress-forestas wp --allow-root eval 'echo do_shortcode("[forestas_logo]|[forestas_anno]");'`
  Atteso: `[forestas_logo]|[forestas_anno]` (testo letterale).

- [x] **Passo 2: copia il logo e registra i due shortcode in `functions.php`**

  Home nella lingua corrente: `apply_filters( 'wpml_home_url', home_url( '/' ) )`. Versione:
  `wp_get_theme()->get( 'Version' )`. URL: `get_stylesheet_directory_uri()`. Commenti in italiano
  come quelli già presenti.

- [x] **Passo 3: verifica**

  Run: `docker exec wordpress-forestas wp --allow-root eval 'echo do_shortcode("[forestas_logo]|[forestas_anno]");'`
  Atteso: un `<a class="forestas-logo" href="http://localhost:8090/">` con `<img … logo.png?ver=1.0.0 … alt="Sardegna Sentieri" width="1155" height="90">`, poi `|2026`.
  Run: `curl -sI http://localhost:8090/wp-content/themes/forestas-child/assets/img/logo.png | head -1`
  Atteso: `HTTP/1.1 200 OK`.

- [x] **Passo 4: commit (istruzione per l'utente)**

  `git add themes/forestas-child/assets/img/logo.png themes/forestas-child/functions.php`
  `git commit -m "feat(oc:8757): logo e anno del footer come shortcode del child theme"`

---

### Task 2: Menu e ordine delle lingue

**File:**
- Modifica: `config/menu.json`, `config/wpml.json`

**Interfacce:**
- Produce: menu `main-menu` (chiave `menu-main-menu`), `servizio-menu` (chiave `menu-servizio-menu`),
  `footer-menu` (chiave `menu-footer-menu`), usati per slug dagli elementi di header e footer.
- Classi delle voci di `main-menu`, nell'ordine: SENTIERI `tipo-sentiero`, ITINERARI
  `tipo-itinerario`, CAMMINI `tipo-cammino`, DA VEDERE `tipo-da-vedere`, DA SAPERE `tipo-da-sapere`,
  MAPPA INTERATTIVA `tipo-mappa`, NEWS `tipo-news` (campo `classi`).

- [x] **Passo 1: riscrivi `main-menu`** con le sette voci (`destinazione: {"tipo":"custom","url":"#"}`)
  e le classi sopra; togli Home, Chi siamo, Blog, Contatti.
- [x] **Passo 2: aggiungi `servizio-menu`** (Contatti, Segnalazioni, FAQ, App) e **`footer-menu`**
  (AREA RISERVATA, NOTE LEGALI, PRIVACY, CREDITI, DICHIARAZIONE DI ACCESSIBILITÀ), stessa forma delle
  voci esistenti, `"lingua": "it"`, `"originale": null`.
- [x] **Passo 3: in `config/wpml.json` imposta `"languages_order": [ "it", "en-us" ]`.**
- [x] **Passo 4: applica e verifica**

  Run: `bin/wordpress-config.sh apply` (anteprima), poi `bin/wordpress-config.sh apply --conferma`.
  Run: `docker exec wordpress-forestas wp --allow-root menu item list main-menu --fields=title,url,classes --format=csv`
  Atteso: le sette voci nell'ordine, `url` `#`, ciascuna con la sua classe `tipo-*`.
  Run: `docker exec wordpress-forestas wp --allow-root menu list --fields=slug --format=csv`
  Atteso: contiene `main-menu`, `servizio-menu`, `footer-menu`.

- [x] **Passo 5: commit (istruzione per l'utente)** — dopo `git diff config/`:
  `git commit -m "feat(oc:8757): menu principale, di servizio e del footer; IT prima di EN"`

---

### Task 3: Header italiano

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-3-header-italiano)

**File:**
- Modifica: `config/post.json`, voce `us_header-header` (`post_content`, JSON dell'Header Builder)

**Interfacce:**
- Consuma: `[forestas_logo]` (Task 1), menu `main-menu` e `servizio-menu` (Task 2).
- Produce: classi CSS sugli elementi, usate dal Task 5: `forestas-servizio` (riga top),
  `forestas-nav` (elemento menu principale), `forestas-lingua` (selettore), `forestas-social`.

Elementi (tipi di UpSolution Core già presenti in `plugins/us-core/config/elements/`):

| Posizione desktop | Elemento |
|---|---|
| `top_left` | `text` «Seguici su» + `socials` (6 profili, URL dell'overview, colore `--sar-terra-testo`, nome accessibile = nome del social, es. «Instagram») |
| `top_right` | `additional_menu` `source=servizio-menu`, `dropdown` `source=wpml` (codice breve, IT · EN), `search` (testo «Cerca nel sito») |
| `middle_left` | `html` con `[forestas_logo]` (l'elemento `html` esegue `do_shortcode`, `templates/elements/html.php:26`) |
| `middle_right` | `menu` `source=main-menu` |

Tablet e mobile (`tablets`, `mobiles`): top con il solo `dropdown` lingua, middle con logo, `search`
in modalità icona, `menu` (hamburger); `additional_menu` di servizio e `socials` nel layout mobile
come elementi del pannello a scomparsa, se l'Header Builder lo consente, altrimenti sotto il menu.
Tutti gli elementi demo (`text:1…7`, `cart:1`, `image:1`, `hwrapper:1`) si tolgono.

- [x] **Passo 1: verifica lo stato attuale**

  Run: `curl -s http://localhost:8090/ | grep -c "I'm shopping for"`
  Atteso: `≥1` (header demo).

- [x] **Passo 2: scrivi il nuovo `post_content` di `us_header-header`** con layout `default`,
  `laptops`, `tablets`, `mobiles` e `data` come in tabella; breakpoint invariato (`900px`).
- [ ] **Passo 3: applica, poi apri l'header in Templates → Headers, salva senza cambiare nulla,
  esporta**

  Run: `bin/wordpress-config.sh apply --conferma`, salvataggio dal pannello, poi `bin/wordpress-config.sh export`.

- [x] **Passo 4: verifica**

  Run: `curl -s http://localhost:8090/ | grep -o -E "I'm shopping for|\[forestas_logo\]|forestas-logo|Seguici su|Cerca nel sito|tipo-sentiero" | sort | uniq -c`
  Atteso: `forestas-logo`, `Seguici su`, `Cerca nel sito`, `tipo-sentiero` presenti; `I'm shopping for`
  e `[forestas_logo]` assenti.
  Run: `curl -s http://localhost:8090/ | grep -o -E 'href="https://(www\.facebook|www\.instagram|x\.com|telegram\.me|api\.whatsapp|www\.youtube)[^"]*"' | sort -u | wc -l`
  Atteso: `6`.
  Run: `curl -s http://localhost:8090/ | grep -o -E 'aria-label="(Facebook|Instagram|X|Telegram|WhatsApp|YouTube)"' | sort -u | wc -l`
  Atteso: `6` (se Impreza usa `title` invece di `aria-label`, adattare il pattern: conta che il nome ci sia).

- [x] **Passo 5: commit (istruzione per l'utente)** — dopo `git diff config/`:
  `git commit -m "feat(oc:8757): header del design system nell'Header Builder"`

---

### Task 4: Footer italiano

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-footer-italiano)

**File:**
- Modifica: `config/post.json`, voce `us_page_block-footer` (`post_content`, shortcode)

**Interfacce:**
- Consuma: `[forestas_logo]`, `[forestas_anno]` (Task 1), `footer-menu` (Task 2).
- Produce: classi `forestas-footer-r1`, `forestas-footer-r2`, `forestas-footer-bar` sulle righe, per il
  Task 5.

Struttura (`vc_row`): riga 1 logo (`us_html` con `[forestas_logo]`) + «Seguici su» e `us_socials`;
riga 2 «Contattaci» con `mailto:tavolo.tecnico.res@forestas.it` + `us_additional_menu
source="footer-menu"`; riga 3 (fondo `#1A1A1A` dato dal CSS del child, non dallo shortcode) con «©
[forestas_anno] Regione Autonoma della Sardegna» e «Rete Escursionistica della Sardegna · Agenzia
Fo.Re.S.T.A.S.». Colonne impilate su tablet e mobile (`tablets_columns="1"`).

- [x] **Passo 1: scrivi il nuovo `post_content`**, sostituendo tutto il contenuto demo.
- [ ] **Passo 2: apply, salvataggio da Templates → Reusable Blocks, export** (come Task 3, passo 3).
- [x] **Passo 3: verifica**

  Run: `curl -s http://localhost:8090/ | grep -o -E "First Office|Get Started|\[forestas_anno\]|© 2026 Regione Autonoma della Sardegna|tavolo\.tecnico\.res@forestas\.it|DICHIARAZIONE DI ACCESSIBILIT" | sort | uniq -c`
  Atteso: `© 2026 …`, l'email e `DICHIARAZIONE DI ACCESSIBILIT` presenti; `First Office`, `Get Started`,
  `[forestas_anno]` assenti.

- [x] **Passo 4: commit (istruzione per l'utente)** — dopo `git diff config/`:
  `git commit -m "feat(oc:8757): footer del design system come blocco riutilizzabile"`

---

### Task 5: Stili del child

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-stili-del-child)

**File:**
- Modifica: `themes/forestas-child/style.css` (`Version: 1.0.0` → `1.1.0`)

**Interfacce:**
- Consuma: classi dei Task 2, 3, 4.

Regole da coprire, con le misure del mockup (`.ds-serv`, `.ds-head`, `.ds-nav`, `.ds-foot`):
- riga di servizio 12px, link `--ink-2`; social 18px in `--sar-terra-testo`; lingua attiva 600 con
  bordo inferiore 2px `--sar-terra-testo`;
- menu principale 12px, 600, maiuscolo, `letter-spacing: .02em`; voce attiva
  (`.current-menu-item`, `.current-menu-ancestor`) con `color: var(--tipo-<tipo>)` e `::after` 2px
  `currentColor`; `.tipo-mappa` usa `--sar-blu`;
- footer: righe 1 e 2 come `.ds-foot .r1/.r2`, link istituzionali 11.5px 600, striscia nera `#1A1A1A`
  testo bianco 12px (valore già nel mockup, nessuna variabile esistente: va dichiarato come
  `--striscia-footer` nel blocco `:root`);
- target cliccabili ≥ 24×24px (social, voci di servizio, hamburger); focus con `outline: 3px solid
  var(--focus)`;
- `.forestas-logo img`: `height` fissa per breakpoint (desktop 26px, mobile 20px), `width: auto`.

- [x] **Passo 1: scrivi le regole e alza `Version`.**
- [x] **Passo 2: verifica il caricamento**

  Run: `curl -s http://localhost:8090/ | grep -o "forestas-child/style.css?ver=[0-9.]*"`
  Atteso: `forestas-child/style.css?ver=1.1.0`.

- [x] **Passo 3: prova della voce attiva** — collega temporaneamente la voce SENTIERI alla pagina Home
  dal pannello, apri la Home, controlla che SENTIERI sia rosso `#DE1B0D` con la sottolineatura;
  ripristina l'URL `#`. Non esportare durante la prova.

  Run (dopo il ripristino): `docker exec wordpress-forestas wp --allow-root menu item list main-menu --fields=title,url --format=csv | grep -c ',#$'`
  Atteso: `7`.

- [x] **Passo 4: commit (istruzione per l'utente)**
  `git commit -m "feat(oc:8757): stili di header e footer nel child theme"`

---

### Task 6: Versione inglese

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6-versione-inglese)

**File:**
- Modifica: `config/menu.json` (`menu-main-menu-en-us`, `menu-servizio-menu-en-us`,
  `menu-footer-menu-en-us`), `config/post.json` (`us_header-header-en-us`,
  `us_page_block-footer-en-us`); `config/impreza.json` solo se il passo 4 fallisce.

Etichette: TRAILS, ITINERARIES, WALKS, SIGHTS, GOOD TO KNOW, INTERACTIVE MAP, NEWS; Contact, Report
an issue, FAQ, App; RESERVED AREA, LEGAL NOTES, PRIVACY, CREDITS, ACCESSIBILITY STATEMENT; «Follow
us», «Search the site», «Contact us», «© [forestas_anno] Autonomous Region of Sardinia», «Sardinia
Hiking Network · Fo.Re.S.T.A.S. Agency». Gli elementi menu dell'header EN puntano agli slug dei menu
EN se WPML non li risolve da sé.

- [x] **Passo 1: scrivi le cinque traduzioni** con `"lingua": "en-us"` e `"originale"` alla chiave
  italiana.
- [ ] **Passo 2: apply, salvataggio dal pannello di header e footer EN, export.**
- [x] **Passo 3: verifica che l'export non abbia rinominato le chiavi**

  Run: `grep -c -E '"chiave": "(us_header-header|us_page_block-footer|menu-main-menu|menu-servizio-menu|menu-footer-menu)-en-us"' config/post.json config/menu.json`
  Atteso: `config/post.json:2`, `config/menu.json:3`.

- [x] **Passo 4: verifica la pagina inglese**

  Run: `curl -s http://localhost:8090/en/ | grep -o -E "TRAILS|Follow us|Search the site|ACCESSIBILITY STATEMENT|SENTIERI|Seguici su" | sort | uniq -c`
  Atteso: termini inglesi presenti, `SENTIERI` e `Seguici su` assenti. Se compaiono quelli italiani,
  impostare in `config/impreza.json` header e footer per lingua o puntare gli elementi agli slug EN.

- [x] **Passo 5: commit (istruzione per l'utente)** — dopo `git diff config/`:
  `git commit -m "feat(oc:8757): header, footer e menu in inglese"`

---

### Task 7: Verifica d'insieme e documentazione

**File:**
- Modifica: `CLAUDE.md` di `wp-forestas` (sezione «Trappole»)
- Crea: `docs/features/8757-…/notes.md`

- [x] **Passo 1: round-trip della configurazione**

  Run: `bin/wordpress-config.sh export && git status --short config/`
  Atteso: nessun file di `config/` modificato rispetto all'ultimo export dei task precedenti.

- [x] **Passo 2: prova visiva nel browser** a 1440, 1100, 900 e 375 px, IT ed EN: confronto con le
  tavole del mockup, menu su una riga a desktop, hamburger e pannello mobile come da overview,
  navigazione da tastiera con focus visibile.
- [x] **Passo 3: trappola nel `CLAUDE.md`** (una riga, forma delle altre):
  «`us_header`, `us_page_block`, `us_content_template` e `us_grid_layout` si modificano solo in locale
  e poi si esportano: l'apply li sovrascrive sempre (`WPF_TIPI_BUILDER`), anche senza `--pagine`, e una
  modifica fatta dal pannello di UAT o produzione va persa (oc:8757)».
- [x] **Passo 4: `notes.md`** con le decisioni (tag ignorati su richiesta del dev, WhatsApp e handle X
  come forniti, ricerca standard) e i follow-up senza ticket (ricerca del catasto, pagine di
  destinazione, logo senza «BETA», layout mobile definitivo).
- [x] **Passo 5: commit (istruzione per l'utente)**
  `git commit -m "docs(oc:8757): trappola sugli oggetti dei builder e note del lavoro"`

---

### Task 8: Header e footer identici al sito attuale, Theme Options dal tema Drupal

Dopo lo scrum del 14/10/2026 (overview, «Cosa cambia»), branch
`feature/oc-8757-header-e-footer-identici-al-sito-attuale` da `develop`. Il vincolo «colori solo da
`--sar-*`/`--tipo-*`» qui sopra non vale più: quelle variabili sono state tolte dal child, e per header
e footer valgono le variabili `--sase-*`, con i valori del tema Drupal o i colori delle Theme Options.

**File:**
- Modifica: `config/post.json` (header e footer IT/EN), `config/impreza.json`
- Modifica: `themes/forestas-child/style.css`, `functions.php`, `assets/js/menu-mobile.js`,
  `assets/img/blocco-istituzionale-fesr.png` (ridotto a 1100×77)
- Crea: `themes/forestas-child/assets/fonts/` (font e licenze OFL), `assets/img/cerca.svg`,
  `assets/img/loghi-header/`, `assets/img/loghi-footer/`
- Cancella: `themes/forestas-child/assets/img/blocco-istituzionale-fesr-2.png`
- Modifica: `README.md`, `docs/knowledge/inizializzazione-wordpress.md`
- Modifica: `CLAUDE.md`, `docs/knowledge/8757-…`, `overview.md`, `notes.md`

- [x] **Passo 1: misure del sito attuale** — CSS di produzione e SCSS del tema Drupal (copia su UAT):
  posizione, misure, font e colori di ogni elemento di header, footer e pannello alle larghezze
  dell'overview, menu chiuso e aperto.
- [x] **Passo 2: struttura** in `config/post.json`: celle dell'header come in produzione (servizio a
  sinistra, IT EN e ricerca a destra; su tablet e mobile FESR da solo in alto, IT EN, ricerca e menu
  nella riga del logo), icona del menu a icona personalizzata, footer a due colonne con i cinque social.
- [x] **Passo 3: stili** in `style.css`, poi confronto elemento per elemento con la produzione fino a
  nessuna differenza oltre mezzo pixel, salvo FESR e CAMMINI.
- [x] **Passo 4: Theme Options** in `config/impreza.json`: colori, tipografia, pulsanti, focus.
  Run: `bin/wordpress-config.sh apply --conferma && bin/wordpress-config.sh export && bin/wordpress-config.sh apply`
  Atteso: l'ultimo apply dice «nessuna differenza».
- [x] **Passo 5: verifiche** — confronto delle misure, axe a 1440 e 390px con pannello chiuso e aperto,
  bandiera UE più grande del logo vicino da 320 a 1920px.
- [x] **Passo 6: documentazione** — overview, notes, knowledge, trappole nel `CLAUDE.md`, regola sulla
  fonte di design (frontend Drupal, copia su UAT) nel `CLAUDE.md`.
- [x] **Passo 7: riga dei loghi del footer** — i quattro loghi del blocco istituzionale ritagliati dal
  file ufficiale «versione 2» con scala comune, più Forestas e CAI del sito attuale, in
  `assets/img/loghi-footer/`; shortcode `[forestas_loghi_footer]`; disposizione confrontata con la
  produzione a 390, 768, 800, 1100 e 1440px.
- [x] **Passo 8: loghi dell'header** — `[forestas_fesr]` (poi `[forestas_loghi_header]`, passo 9) stampa il blocco «versione 1» intero, poi
  Forestas e CAI con il separatore del blocco, alti quanto la bandiera; verificati da 320 a 1920px
  su poche larghezze (la review ha trovato la fascia 430-500px fuori regola: rifatto ogni 5px nel
  passo 9).
- [x] **Passo 9: correzioni della review di 5d2a098** — tocco sul velo, bandiera UE sotto i 600px,
  cleanup (notes, «Review del commit 5d2a098»); confronti con la produzione e axe ripetuti.
- [x] **Passo 10: commit (istruzione per l'utente)** — dopo `git diff config/`:
  `git commit -m "feat(oc:8757): header e footer identici al sito attuale, Theme Options dal tema Drupal"`

---

### Task 9: Header e footer secondo il wireframe oc:8783

Dopo la call del 16/10/2026 e i commenti fino al 19/10 sul wireframe di oc:8783 (overview, «Cosa
cambia»), branch `feature/oc-8757-header-e-footer-dal-wireframe` da `develop`.

**File:**
- Modifica: `config/menu.json` (PUNTI DI INTERESSE, Open Data, IT ed EN), `config/post.json` (header
  senza la riga dei loghi, footer con «Preferenze cookie», IT ed EN)
- Modifica: `themes/forestas-child/style.css` (Version 1.3.1), `functions.php`,
  `assets/img/logo.png` (1442×120)
- Crea: `assets/js/header-compatto.js`, `assets/img/open-data.svg`,
  `assets/img/loghi-footer/forestas-sentieristica.png`, `sardegna-sentieri-per-tutti.png`
- Cancella: `assets/img/blocco-istituzionale-fesr.png`, `assets/img/loghi-header/`,
  `assets/img/loghi-footer/forestas-sardegna-foreste.png`
- Modifica: `CLAUDE.md`, `docs/knowledge/8757-…`, `overview.md`, `notes.md`

- [x] **Passo 1: confronto col wireframe** — HTML, CSS, immagini e note della tavola 1 e del
  piedipagina; immagini confrontate per hash con quelle del child (nuovi solo logo, Forestas
  Sentieristica, Sardegna Sentieri per tutti).
- [x] **Passo 2: configurazione** — menu e header/footer in `config/`.
  Run: `bin/wordpress-config.sh apply --conferma && bin/wordpress-config.sh export && bin/wordpress-config.sh apply`
  Atteso: l'ultimo apply dice «nessuna differenza».
- [x] **Passo 3: child** — shortcode, loghi, colori del menu, icona Open Data, griglia dei loghi del
  footer, «Preferenze cookie», riga compatta.
- [x] **Passo 4: verifiche** — menu su una riga ogni 15px da 1025 a 1440px; riga compatta a 1025,
  1280, 1440px, nessuno spostamento del contenuto, spenta con la finestra alta 480px; mobile a 390,
  700 e 800px con il pannello aperto; footer a 390 e 1280px con il «torna su»; axe IT ed EN a 1440 e
  390px; da loggato a 390, 700, 1025 e 1280px.
- [x] **Passo 5: documentazione** — overview, notes, knowledge, `CLAUDE.md`.
- [ ] **Passo 6: commit (istruzione per l'utente)** — dopo `git diff config/`:
  `git commit -m "feat(oc:8757): header e footer secondo il wireframe oc:8783"`
