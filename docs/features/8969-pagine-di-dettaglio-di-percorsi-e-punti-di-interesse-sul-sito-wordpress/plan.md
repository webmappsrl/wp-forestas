> Ticket: oc:8969

# Pagine di dettaglio di percorsi e punti di interesse — piano del child theme

> **Per chi esegue:** REQUIRED SUB-SKILL: superpowers:subagent-driven-development (consigliato) o
> superpowers:executing-plans. **Nessun `git commit`, `git push` o branch senza istruzione esplicita di
> Rubens**: i commit qui sotto sono istruzioni per lui. Si parte dopo i Task 1-5 del piano di
> `wp-package` (stesso slug di cartella), che danno indirizzi, `$detail` e template sostituibili.

**Obiettivo:** le pagine di dettaglio di sentiero, itinerario, cammino e punto di interesse di Sardegna
Sentieri secondo le Tavole 3, 4, 5 e 7 del wireframe di oc:8783. La pagina «Da vedere» diventa
«Punti di interesse».

**Architettura:** il child sostituisce i template del plugin (`wp-package/track.php`,
`wp-package/poi.php`). Un file `inc/dettaglio.php` trasforma il JSON dello shard nei dati di ogni
sezione: tipo, colore, enti, indicatori, tabelle, correlati. Così i template restano solo markup, e un
cambio di chiave dello shard si corregge in un punto. CSS e JS del dettaglio sono file a parte, caricati
solo sulle pagine di dettaglio.

**Tecnologie:** child di Impreza 9.4, WPML, `forestas_testo()` per i testi, test con `wp eval-file`,
verifica nel browser (Playwright o Chrome) in locale.

**Stato al 13/11:** Task 1-5 fatti; i loro commit sono stati raggruppati in due (d9ca844, 2cd79fb) invece di uno per
task. Task 6 e Task 7 (lavoro aggiunto dopo il piano) da chiudere: il lavoro dopo 2cd79fb non è ancora committato.

**Spec:** `docs/features/8969-pagine-di-dettaglio-di-percorsi-e-punti-di-interesse-sul-sito-wordpress/overview.md`
e `notes.md` di questo repo; la parte generica è nell'overview e nel piano di `wp-package`.

## Vincoli globali

- **Riferimenti di design:**
  - il wireframe di oc:8783 (Tavole 3, 4, 5, 7). I componenti si prendono dal markup e dal CSS della
    tavola (`Artifact` read di `https://claude.ai/artifact/4T3ZRSP1LCrbCuJdNuEGXQ`, path `index.html`,
    sezioni `s2`, `s2i`, `s2k`, `s2p`), non dalla descrizione scritta;
  - un elemento con una nota numerata può essere una spiegazione per il cliente: si chiede a Rubens
    prima di costruirlo;
  - per ciò che il wireframe non disegna, il tema Drupal locale
    (`/Volumes/WebmappDev/Archivio/Siti Webmapp/Wordpress/Forestas/Drupal sardegnasentieri/sardegnasentieri/docroot/themes/custom/sardegnasentieri`).
- **Colori per tipo:**

  | Tipo | Colore | Fascia |
  |---|---|---|
  | sentiero | `#DE1B0D` | `#FDEEED` |
  | itinerario | `#58810B` | `#EFF4E6` |
  | cammino | `#1A416F` | `#E8ECF1` |
  | POI | `#B7127F` | `#F8E9F3` |
- **Tipo dal `route-type`:** `route-type-trail` → sentiero, `route-type-itinerary` → itinerario,
  `route-type-walk` → cammino. Senza `route-type` si usa il template del sentiero.
- **Chiavi dello shard lette in un solo punto** (`inc/dettaglio.php`):
  - per le tracce: `properties.forestas.{useful_info, roadbook (divergenza: ora `properties.config_detail`, vedi notes), videos, attachments, registry_code, survey_date, inclusivity, organizations, nearby_tracks, downloads}`,
    `properties.taxonomies` e `related_pois`;
  - gli enti stanno in `organizations` (ruolo → `[{id, name, slug}]`) e, finché arriva, in
    `entities` (`[{id, roles}]`, senza nome).
- **Segnaposto:** riconoscibile (testo «Dato non ancora disponibile» / «Not yet available»), occupa lo
  spazio del componente finito, niente titolo di sezione vuoto.
- **Elementi presenti ma non attivi:** Stampa e Segnala visibili e disattivati con
  `aria-disabled="true"` e la spiegazione. Restano segnaposto anche recensioni, avviso e percorribilità.
- **Testi** con `forestas_testo( it, en )`.
- **WCAG 2.1 AA:** contrasto 4,5:1, focus visibile, mappa e testata compatta che non coprono il focus
  (`scroll-padding-top`).
- **Versione del child:** dopo ogni modifica di CSS o JS si alza `Version` in `style.css` e si controlla
  il `?ver=` servito.
- **Il child non deve rompersi se il plugin è vecchio:** ogni chiamata a `wmwp_*` è protetta da
  `function_exists`.
- **Verifiche in locale:** `http://localhost:8080`, da non loggato e da loggato, a 1440 px e a 390 px,
  e dopo uno scorrimento (testata compatta).

## Da controllare in review

1. **Traccia con quasi tutti i campi vuoti** (dettaglio di ripiego del package, o traccia nuova): la
   pagina è completa con i segnaposto, senza avvisi PHP. Test nel Task 2.
2. **Ente senza nome** (vecchio formato `entities`): compare il ruolo e il segnaposto del nome, mai «0»
   o un id. Test nel Task 2.
3. **Nome lunghissimo o con il codice in coda** («… (G 210)»): la testata compatta va su una riga con
   i puntini, la testata grande va a capo senza coprire la bandierina. Verifica nel Task 3 con la
   traccia dal nome più lungo del dev.
4. **Sotto 1024 px:** la mappa non è agganciata e sta dopo la descrizione; la testata compatta non
   copre l'header sticky del sito (fino a 1024 px). Verifica nel Task 3.
5. **Pagina inglese di una traccia senza nome inglese:** nome e testi delle sezioni in italiano dove
   manca l'inglese, etichette dell'interfaccia in inglese. Verifica nel Task 3.

---

### Task 1: indirizzi, «Punti di interesse» e impostazioni del plugin

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#divergenze-dal-piano-task-per-task).

**File:**
- Modify: `config/pagine.json` (riga `da-vedere`: slug `punti-di-interesse` in `it` e `en`; la chiave
  resta `da-vedere`)
- Modify: `config/menu.json` (le voci con `/da-vedere/` e `/en/da-vedere/` puntano a `/punti-di-interesse/`
  e `/en/punti-di-interesse/`)
- Create: `themes/forestas-child/inc/dettaglio.php`, con le impostazioni del plugin
- Modify: `themes/forestas-child/functions.php` (require di `inc/dettaglio.php`)
- Modify: `docs/howto/ricreare-wordpress-da-zero.md`, se cambia un passo

**Interfacce:**
- `pre_option_wmwp_detail_tracks_use_page` → `0` (la base viene dal `route-type`).
- `pre_option_wmwp_detail_pois_use_page` → `1`.
- `pre_option_wmwp_detail_pois_page` → l'id della pagina con meta `_wpf_pagina` uguale a `da-vedere`,
  letto con `get_posts`, così nel database non resta un id scritto a mano.
- `forestas_dettaglio_pagina_poi(): int` → quell'id, oppure 0.

- [x] **Passo 1:** applica slug e menu in locale con `bin/wordpress-config.sh pagine --conferma` e
  `applica --conferma`. Verifica:
  - `curl -sI localhost:8080/punti-di-interesse/` → 200;
  - `curl -sI localhost:8080/en/punti-di-interesse/` → 200;
  - `curl -sI localhost:8080/da-vedere/` → 301 verso `/punti-di-interesse/`, grazie al redirect degli
    alias di Drupal in `inc/pagine-statiche.php:503`. Se non funziona, aggiungi l'alias `da-vedere` alla
    pagina nello stesso meta (`FORESTAS_META_ALIAS_DRUPAL`), non un secondo redirect.
- [x] **Passo 2:** aggiungi i filtri `pre_option_*`. Verifica che
  `curl -sI localhost:8080/?wmwp_detail=poi\&wmwp_id=9` dia 301 verso `/punti-di-interesse/<slug>/`, e
  che `?wmwp_detail=track&wmwp_id=311` dia 301 verso `/cammino/<slug>/`.
- [x] **Passo 3:** controlla i menu nelle due lingue nel browser: la voce «Da vedere» porta a
  `/punti-di-interesse/`.
- [x] **Passo 4 (commit):** `feat(oc:8969): pagina «Punti di interesse» e basi dei dettagli`.

### Task 2: dati del dettaglio in un solo punto

**File:**
- Modify: `themes/forestas-child/inc/dettaglio.php`
- Modify: `themes/forestas-child/inc/pagine-statiche.php` (`forestas_condividi()`)
- Test: `themes/forestas-child/tests/dettaglio.php`, che si lancia con
  `docker exec -w /var/www/html wordpress-forestas wp eval-file wp-content/themes/forestas-child/tests/dettaglio.php --allow-root`

**Interfacce:**
- `forestas_dettaglio_tipo( array $feature ): string` → `sentiero`, `itinerario`, `cammino` o `poi`.
- `forestas_dettaglio_colori( string $tipo ): array` → `[ 'colore' => '#…', 'fascia' => '#…' ]`.
- `forestas_dettaglio_enti( array $properties ): array` → lista di
  `[ 'ruolo' => etichetta, 'nome' => string|null, 'slug' => string|null ]`, nell'ordine gestore,
  manutentore, rilevatore, operatore, complesso forestale.
  - Le etichette inglesi sono quelle di Drupal: «Managing authority», «Maintainer». Per gli altri ruoli
    si usa l'inglese più vicino, da far confermare a Rubens.
  - Legge `organizations`; se manca, `entities` con `nome = null`.
- `forestas_dettaglio_indicatori( array $properties ): array` → `validazione` (dal termine
  `validation-status`), `percorribilita` (sempre `null`), `accessibilita` (da `forestas.inclusivity`),
  ognuno con `livello` 0-3 e `etichetta`.
- `forestas_dettaglio_tabelle( array $properties, string $tipo ): array` → le tabelle Percorso, Misure
  e Catasto, oppure «Dati dell'itinerario» / «Dati del cammino»; ogni valore assente è `'—'`.
- `forestas_dettaglio_correlati( array $properties ): array` → `[ 'poi' => […], 'percorsi' => […] ]`.
  - Ogni card ha `url` (da `wmwp_detail_url()`), `nome`, `tipo`, `immagine`, `lunghezza`, `tempo`,
    `distanza` (`null` finché non arriva).
- `forestas_dettaglio_testo( $valore ): string` → testo nella lingua corrente con ripiego italiano.
- `forestas_condividi( int|array $origine ): string`: con un id si comporta come oggi; con
  `[ 'url' => …, 'titolo' => … ]` usa quei valori. Così vale anche per le pagine senza post.

- [x] **Passo 1: test che fallisce**, con due Feature costruite nel test:
  - **completa:** route-type walk, `organizations` con gestore e rilevatore, `nearby_tracks` con 2 card;
  - **vuota:** solo `id`, `name` e `geometry`.

  Controlli:
  - `forestas_dettaglio_tipo( completa ) === 'cammino'`;
  - `forestas_dettaglio_tipo( vuota ) === 'sentiero'`;
  - `forestas_dettaglio_enti( completa )[0]['ruolo']` è l'etichetta del gestore;
  - con `entities = [ [ 'id' => 3, 'roles' => [ 'gestore' ] ] ]` il nome è `null`;
  - `forestas_dettaglio_tabelle( vuota, 'sentiero' )` ha tutti i valori `'—'`;
  - `forestas_dettaglio_correlati( vuota ) === [ 'poi' => [], 'percorsi' => [] ]`;
  - nessun avviso PHP (`set_error_handler` che fa fallire il test).
- [x] **Passo 2:** verifica che dia `FAIL`.
- [x] **Passo 3:** implementa.
- [x] **Passo 4:** verifica che dia `PASS`.
- [x] **Passo 5 (commit):** `feat(oc:8969): dati del dettaglio di percorsi e POI`.

### Task 3: dettaglio del sentiero (Tavola 3)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#divergenze-dal-piano-task-per-task).

**File:**
- Create: `themes/forestas-child/wp-package/track.php`, `themes/forestas-child/template-parts/dettaglio/*.php`
  (una parte per sezione: testata, azioni, indicatori, sezioni del corpo, enti, mappa, correlati,
  recensioni, testata compatta)
- Create: `themes/forestas-child/assets/css/dettaglio.css`, `themes/forestas-child/assets/js/dettaglio.js`
- Modify: `themes/forestas-child/style.css` (`Version`), `themes/forestas-child/inc/dettaglio.php`
  (enqueue solo con `body.wmwp-detail`)

**Interfacce:**
- Consuma `$detail` del plugin, `[wmwp_map tracks="<id>"]` e le funzioni del Task 2.
- Il colore arriva dal `body` come variabili CSS: `--dettaglio-colore` e `--dettaglio-fascia`.
- La galleria riusa `assets/js/galleria-lightbox.js` e `assets/css/galleria-lightbox.css`.

- [x] **Passo 1:** leggi la Tavola 3 dal markup del wireframe e annota in `notes.md` gli elementi con
  nota numerata da chiarire con Rubens prima di costruirli.
- [x] **Passo 2:** costruisci le parti nell'ordine della tavola:
  - testata con foto e fascia scura al 60%;
  - riga di azioni: GPX da `forestas.downloads`; Condividi; Stampa e Segnala disattivati;
  - avviso e percorribilità come segnaposto;
  - tre indicatori con la «i» della legenda;
  - «Il sentiero in breve», roadbook, «Attività e avvertenze», informazioni utili;
  - galleria con i crediti presi dalle foto (`caption`, `author`, `credits`);
  - allegati e video;
  - tre tabelle;
  - enti senza link;
  - correlati in card a tre colonne;
  - «Opinioni e recensioni» come segnaposto;
  - testata compatta.
- [x] **Passo 3:** CSS e JS:
  - mappa agganciata nella colonna destra da 1025 px;
  - testata compatta che compare dopo la testata grande;
  - `scroll-padding-top` aggiornato all'altezza vera.

  Alza `Version` in `style.css`.
- [x] **Passo 4:** verifica nel browser `localhost:8080/sentiero/<slug>/`, scegliendo una traccia del
  dev con foto, enti e correlati. A 1440 e a 390 px, da loggato e non, prima e dopo uno scorrimento,
  controlla:
  - contrasto dei testi sulla foto e sulle fasce (≥ 4,5:1);
  - focus da tastiera visibile e mai sotto la testata compatta;
  - nessun salto della pagina al caricamento;
  - il nome più lungo del dev nella testata compatta;
  - la pagina inglese della stessa traccia;
  - una traccia con pochi dati (segnaposto e nessun titolo vuoto).

  Salva le schermate nello scratchpad e confrontale con la tavola.
- [x] **Passo 5 (commit):** `feat(oc:8969): dettaglio del sentiero secondo la tavola 3`.

### Task 4: differenze di itinerario e cammino (Tavole 4 e 5)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#divergenze-dal-piano-task-per-task).

**File:**
- Modify: le parti di `template-parts/dettaglio/`, `assets/css/dettaglio.css`, `inc/dettaglio.php`

**Interfacce:**
- **Itinerario:**
  - senza il blocco dei tre indicatori;
  - tabella «Dati dell'itinerario»;
  - correlati «Percorsi correlati»;
  - verde.
- **Cammino:**
  - il nome del cammino accanto all'etichetta del tipo;
  - il blocco «Il cammino, in sintesi» sotto «in breve», con i soli dati che esistono: tematismo (le
    tipologie della traccia), gestore (l'ente con il ruolo gestore), iscrizione al Registro;
  - tabella «Dati del cammino»;
  - blu.

- [x] **Passo 1:** leggi le Tavole 4 e 5 dal markup e annota le differenze rispetto alla 3. Le note
  numerate vanno chiarite con Rubens.
- [x] **Passo 2:** implementa le differenze, usando `forestas_dettaglio_tipo()` nei template e non le
  classi CSS da sole.
- [x] **Passo 3:** verifica nel browser:
  - un itinerario (`/itinerario/<slug>/`) e un cammino (`/cammino/<slug>/`) del dev;
  - le stesse pagine in inglese (`/en/itinerary/…`, `/en/walk/…`);
  - a 1440 e a 390 px.
- [x] **Passo 4 (commit):** `feat(oc:8969): dettaglio di itinerario e cammino`.

### Task 5: dettaglio del punto di interesse (Tavola 7)

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#divergenze-dal-piano-task-per-task).

**File:**
- Create: `themes/forestas-child/wp-package/poi.php` e le parti del POI in `template-parts/dettaglio/`
- Modify: `assets/css/dettaglio.css`, `inc/dettaglio.php`

**Interfacce:**
- Dati del POI:
  - tipologia in testata, su etichetta bianca e fondo `#B7127F`;
  - area geografica, descrizione, come arrivare, galleria, allegati;
  - mappa `[wmwp_map geojson=…]`.
- **«Percorsi vicini»:** segnaposto finché il dettaglio del POI non porta il dato; poi stesse card dei
  correlati.
- Niente Segnala, niente recensioni, niente accessibilità.
- Il `forestas` dei POI ha ancora le chiavi vecchie (`allegati`, `come_arrivare`, `zona_geografica`):
  le legge solo `inc/dettaglio.php`.

- [x] **Passo 1:** leggi la Tavola 7 dal markup; note numerate a Rubens.
- [x] **Passo 2:** implementa.
- [x] **Passo 3:** verifica nel browser:
  - `localhost:8080/punti-di-interesse/<slug>/` e la pagina inglese;
  - a 1440 e a 390 px;
  - un POI senza foto.
- [x] **Passo 4 (commit):** `feat(oc:8969): dettaglio del punto di interesse secondo la tavola 7`.

### Task 6: giro completo e documentazione

**File:**
- Modify: `docs/howto/ricreare-wordpress-da-zero.md` (se serve), `notes.md` del ticket

- [ ] **Passo 1:** giro completo in locale:
  - **dalla ricerca:** dalla pagina di ricerca alla card, al dettaglio, indietro, in italiano e in
    inglese;
  - **dalla mappa:** dalla card della mappa della ricerca al dettaglio;
  - **indirizzi:** `/da-vedere/` porta a `/punti-di-interesse/`;
  - **SEO:** il sorgente HTML di un dettaglio ha un solo `canonical`, una `description`, i tag `og:` e
    i due `hreflang`.
- [ ] **Passo 2:** prova con dati cambiati, in locale:
  - rinomina in Nova una traccia del database locale (`localhost:8000/nova`) e rigenera il suo file;
  - il vecchio indirizzo dà 404 o 301, quello nuovo funziona, senza toccare WordPress.
- [ ] **Passo 3:** aggiorna `notes.md` (deviazioni, decisioni) e la howto, se la sequenza di messa in
  opera è cambiata (nuovo commit del plugin in `WP_PACKAGE_REF` di `init-wordpress.sh`).
- [ ] **Passo 4 (commit):** `docs(oc:8969): note e procedura di messa in opera`.

### Task 7: lavoro aggiunto dopo il piano (Rubens, 12-13/11)

Fatto e verificato in locale, con i test di `tests/dettaglio.php`; decisioni e misure in `notes.md`.

- [x] Icone delle tipologie di disabilità dal set IcoMoon dello shard (`wmwp_icon_svg()` del plugin), indicatori e
      «Sentiero per tutti» identici alla Tavola 3, accessibilità e screen reader rivisti.
- [x] Card uniche (dettaglio, ricerca, post) con filetto nel colore del tipo e segnaposto di Drupal; «Percorsi
      correlati».
- [x] Mappa del dettaglio come le tavole (collegati, POI numerati, legenda), partenza e arrivo come GeoHub, su mobile
      subito dopo i pulsanti.
- [x] «Vedi sulla mappa interattiva» con il focus sull'elemento e la webapp dell'ambiente; pagina della mappa più alta.
- [x] Frecce della lightbox su tutto il sito.
- [x] Nome del cammino (`layer_name`) e nid di Drupal come chiave stabile (filtro `wmwp_detail_source`).
- [x] Gruppi dei dati chiusi da aprire uno per uno, come le tavole, con grado di difficoltà e «Maggiori info».
- [ ] **Commit (istruzione per Rubens):** `feat(oc:8969): …` dei file cambiati dopo 2cd79fb, nello stesso branch.
