> Ticket: oc:8990

# Enti sul sito WordPress — piano di implementazione

> **Per chi esegue:** sub-skill richiesta: superpowers:subagent-driven-development (consigliata) o
> superpowers:executing-plans, task per task. I passi usano le caselle (`- [ ]`). **Nessun commit**: i comandi
> `git commit` sono istruzioni per il dev, che li esegue dopo la review.

**Obiettivo:** elenco `/enti`, dettaglio `/enti/<slug>`, tab «Enti» della ricerca unica e link agli enti dai
dettagli di percorsi e POI, dall'API degli enti dello shard; in più, via i contenuti di default di WordPress.

**Architettura:** un modulo del child, `inc/entes.php`, è l'unico punto che parla con l'API: tiene una copia
del JSON in un'opzione, la rinnova ogni 15 minuti con WP-Cron e la espone con funzioni pure che i template, la
ricerca e i dettagli dei percorsi usano. Elenco e dettaglio sono pagine dinamiche del child (rewrite propria,
`template_include`); la ricerca aggiunge una source al plugin con il filtro `wp_package_search_sources`.

**Stack:** WordPress con Impreza e WPML, child theme PHP 8.3, plugin `wp-package` (helper HTTP, indice dei
dettagli, ricerca), test `wp eval-file`.

**Spec:** [overview.md](overview.md). La parte del plugin ha il suo piano:
`wp-package/docs/features/<slug>/plan.md` (stesso slug, nel clone del plugin).

## Vincoli globali

- Identificatori in inglese (`forestas_entes_*`, come l'API `api/v1/entes`); commenti, docblock e
  documentazione in italiano. Testi del sito con `forestas_testo( it, en )`.
- Token: `WP_API_TOKEN` nel `.env` di `wp-forestas`, letto con `getenv()`; mai nel browser, mai in un file
  tracciato, mai nei log.
- Copia rinnovata ogni 900 secondi; attesa dopo un fallimento senza copia: 300 secondi; soglia di
  plausibilità: la metà della copia precedente, con il calo accettato dopo 3 ore dal primo rifiuto (review interna).
- Ordine dell'elenco: `created_at` crescente, senza data in fondo. «Sentieri gestiti»: `created_at`
  decrescente, senza data in fondo. 9 enti per pagina.
- I dettagli di percorsi e POI leggono solo la copia salvata: mai una chiamata allo shard, mai un 503 per gli
  enti.
- Lo shard sarà la fonte definitiva: nessuna logica che dia per scontato un dato che esiste solo per i
  contenuti importati da Drupal.
- Test del child: `docker exec wordpress-forestas wp --allow-root eval-file wp-content/themes/forestas-child/tests/<file>.php`,
  stampa `PASS` o `FAIL: …`. Lo shard si finge con `pre_http_request`; i test rimettono opzioni, transient e
  post com'erano.
- In locale il WordPress legge il dev (`WP_PACKAGE_SHARD=forestasdev`, token del dev nel `.env` di `wp-forestas`). Lo
  shard locale solo con il mu-plugin non versionato `wpf-shard-locale.php` (oc:8969), che il reset cancella: allora lo
  stesso `WP_API_TOKEN` nel `.env` di `forestas` e di `wp-forestas` e l'indice Elastic degli enti creato in locale, poi
  `scripts/wordpress-up.sh` di `forestas`. WP-Cron in locale non parte: il rinnovo si lancia con
  `wp cron event run forestas_entes_refresh`.

## Punti da guardare in review

1. Copia con `data: []` o con metà degli enti (import dopo il reset di UAT): la copia di prima resta e il log lo
   dice.
2. Token assente sul sito appena installato: una chiamata ogni 5 minuti al massimo, non una per visita; le
   pagine degli enti rispondono 503, i dettagli dei percorsi mostrano gli enti senza link.
3. Id di una traccia passato a un altro sentiero (reset di UAT) prima che l'indice del plugin se ne accorga: la
   card non compare.
4. Slug che non esiste, pagina dell'elenco oltre l'ultima, `/en/enti/<slug>`: 404, 404, pagina in inglese.
5. Testo `en` uguale a `it`: `lang="it"`; testo `en` diverso: nessun `lang`.

---

### Task 1: copia del JSON degli enti

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-1-helper-delle-api-intestazione-accept-e-timeout)

**File:**
- Crea: `themes/forestas-child/inc/entes.php`
- Modifica: `themes/forestas-child/functions.php:317` (`require_once __DIR__ . '/inc/entes.php';` dopo
  `dettaglio.php`)
- Modifica: `.env-example` (`WP_API_TOKEN=` con un commento: lo stesso valore di `WP_API_TOKEN` dello shard;
  dopo una modifica `scripts/wordpress-up.sh` di `forestas`)
- Test: `themes/forestas-child/tests/entes-copy.php`

**Interfacce:**
- Produce:
  - costanti `FORESTAS_ENTES_OPTION = 'forestas_entes_copy'` (non autoload), `FORESTAS_ENTES_RETRY = 'forestas_entes_retry'`
    (transient), `FORESTAS_ENTES_REFRESH_HOOK = 'forestas_entes_refresh'`, `FORESTAS_ENTES_INTERVAL = 900`,
    `FORESTAS_ENTES_RETRY_AFTER = 300`;
  - `forestas_entes_fetch(): array|WP_Error` — `wmwp_http_json( wmwp_server_url( <origin> . '/api/v1/entes' ), 20, [ 'Authorization' => 'Bearer ' . <token> ] )`;
    `WP_Error` senza token, con codice diverso da 200 o con `data` che non è una lista;
  - `forestas_entes_is_plausible( array $new, ?array $old ): bool` — falso se `$new` è vuoto, o se enti o
    collegamenti ente-traccia sono meno della metà di `$old`;
  - `forestas_entes_refresh(): bool` — legge, controlla, salva `[ 'generated_at', 'fetched_at', 'data' ]`; su
    errore scrive nel log `forestas enti: …` (codice, mai il token) e, se non c'è copia, il transient di attesa
    (dopo la review interna la copia ha solo `data`, snella, e l'ora del rinnovo sta in `forestas_entes_state`: Task 11;
    dopo il secondo giro gira un rinnovo alla volta, con il lucchetto `forestas_entes_lock()`, e gli slug si
    riallineano anche con gli stessi dati: Task 12);
  - `forestas_entes_all(): ?array` — la lista dalla copia; senza copia e senza attesa in corso chiama
    `forestas_entes_refresh()` una volta; null se ancora niente (dopo la review interna anche con WP-Cron fermo da più di
    un'ora: Task 11; dopo il secondo giro senza scrivere l'attesa prima, con un tentativo per richiesta e il rinnovo
    scaduto lasciato a WP-Cron appena partito: Task 12);
  - `forestas_entes_cached(): array` — la lista dalla copia, `[]` se manca: non chiama mai lo shard (per i
    dettagli di percorsi e POI; dopo la review interna i dettagli leggono `forestas_entes_known_slugs()`: Task 11);
  - `forestas_entes_by_slug( string $slug ): ?array`;
  - evento WP-Cron `forestas_entes_refresh` con l'intervallo `forestas_quarter_hour` (900 s), registrato su
    `init` se manca, come fa il plugin in `includes/detail-index.php:563`.

- [x] **Passo 1: verificare che il token arrivi a PHP.** (16/11, token del dev: `1` in `/proc/1/environ`, API raggiunta) Con `WP_API_TOKEN` nel `.env` e il container ricreato,
  `docker exec wordpress-forestas sh -c 'cat /proc/1/environ | tr "\0" "\n" | grep -c ^WP_API_TOKEN='` → `1`, e
  una pagina di prova che stampa `getenv('WP_API_TOKEN') !== false` → `true`. Se fosse `false`, ripiego dell'overview:
  l'init scrive il valore in una costante di `wp-config.php`; annotarlo in `notes.md`.
- [x] **Passo 2: test che falliscono** in `tests/entes-copy.php`, shard finto:
  - risposta 200 con 3 enti → `forestas_entes_refresh() === true`, `forestas_entes_all()` ne ha 3, l'header
    mandato contiene `Bearer <token di prova>`;
  - copia da 3 enti, risposta 200 con `data: []` → `false`, la copia ha ancora 3 enti;
  - copia da 4 enti con 10 collegamenti, risposta con 4 enti e 4 collegamenti → copia invariata;
  - copia presente, risposta 401 → `false`, copia invariata, una riga di log senza il token;
  - nessuna copia, risposta 503 → `forestas_entes_all() === null`, transient di attesa scritto; una seconda
    `forestas_entes_all()` non fa una seconda richiesta (contatore nel finto);
  - `forestas_entes_cached()` senza copia → `[]` e nessuna richiesta;
  - `forestas_entes_by_slug( 'comune-di-stintino' )` → l'ente; slug inesistente → null;
  - `wp_next_scheduled( 'forestas_entes_refresh' )` non è falso dopo `do_action( 'init' )`.
- [x] **Passo 3:** lanciare il test → `FAIL`.
- [x] **Passo 4:** implementare le funzioni dell'interfaccia in `inc/entes.php`.
- [x] **Passo 5:** test → `PASS`; `tests/dettaglio.php` → `PASS`.
- [x] **Passo 6 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "feat(oc:8990): copia del JSON degli enti con token e WP-Cron"`

### Task 2: dati dell'ente per le pagine

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-e-3-fatti-insieme-nomi-dei-tipi-e-chiavi-aggiunte)

**File:**
- Modifica: `themes/forestas-child/inc/entes.php`
- Test: `themes/forestas-child/tests/entes-view.php`

**Interfacce:**
- Consuma: `forestas_entes_all()`, `forestas_entes_by_slug()` (Task 1); `forestas_dettaglio_galleria()`,
  `forestas_dettaglio_data()`, `forestas_dettaglio_lang()` di `inc/dettaglio.php`.
- Produce:
  - `forestas_entes_type_label( string $type ): ?string` — i cinque valori in IT/EN (`complesso-forestale`
    «Complesso forestale» / «Forest complex», `ente-partner` «Ente partner» / «Partner organisation»,
    `altre-pubbliche-istituzioni` «Altre pubbliche istituzioni» / «Other public institutions»,
    `privato-associazione` «Privato o associazione» / «Private body or association», `comune` «Comune» /
    «Municipality»; i nomi italiani vanno presi dal termine Drupal, tassonomia del tipo ente, se diversi);
    null per un valore sconosciuto (la riga di log la scrive `forestas_entes_refresh()` con
    `forestas_entes_unknown_types()`, una volta per copia nuova, cioè solo quando i dati cambiano);
  - `forestas_entes_text( $value, string $lang ): array{text: string, lang: ?string}` — la chiave `$lang`; se
    è `en` e coincide con `it`, `lang` è `'it'`; se manca, ripiego su `it` con `lang` `'it'`;
  - `forestas_entes_url( string $slug, string $lang ): string` — `home_url( '/enti/<slug>/' )` passato da
    `wpml_permalink` nella lingua;
  - `forestas_entes_list( string $lang ): list<array{slug, name, name_lang, type, image, url}>` — ordinato per
    `created_at` crescente, senza data in fondo;
  - `forestas_entes_view( array $ente, string $lang ): array` — chiavi `name`, `name_lang`, `type`, `image`
    (url, autore, credits come l'immagine principale del dettaglio traccia), `description` e `contacts` (HTML
    con i titoli abbassati da `forestas_dettaglio_titoli()` e la loro `lang`, `null` se `[]`), `website`
    (`url`, `title`), `links` (da `related_url`), `gallery` (da `forestas_dettaglio_galleria( $ente )`),
    `updated` e `created` (da `forestas_dettaglio_data()`, `null` se assenti).

- [x] **Passo 1: test che falliscono** in `tests/entes-view.php`, con una copia di prova scritta nell'opzione:
  tipo `comune` in EN → «Municipality»; tipo `boh` → null; `en` uguale a `it` → `lang === 'it'`; `en` diverso →
  `lang === null`; elenco di tre enti con `created_at` 2015, null, 2013 → ordine 2013, 2015, null;
  `description: []` → `description === null`; `updated_at` `2024-02-21 10:37:26` → data nel formato del sito;
  `forestas_entes_url( 'comune-di-stintino', 'en' )` finisce con `/en/enti/comune-di-stintino/`.
- [x] **Passo 2:** test → `FAIL`.
- [x] **Passo 3:** implementare.
- [x] **Passo 4:** test → `PASS`.
- [x] **Passo 5 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "feat(oc:8990): dati dell'ente per elenco e dettaglio"`

### Task 3: «Sentieri gestiti»

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-2-e-3-fatti-insieme-nomi-dei-tipi-e-chiavi-aggiunte)

**File:**
- Modifica: `themes/forestas-child/inc/entes.php`
- Test: `themes/forestas-child/tests/entes-view.php` (casi nuovi)

**Interfacce:**
- Consuma: `forestas_dettaglio_card_percorso()` (`inc/dettaglio.php:695`), `wmwp_detail_index()` del plugin
  (`items['track'][<id>]['names']`).
- Produce: `forestas_entes_managed_tracks( array $ente ): list<array>` — le card di
  `forestas_dettaglio_card_percorso()` per le tracce con `gestore` in `roles`, ordinate per `created_at`
  decrescente (senza data in fondo), tenendo solo quelle con un `url` e il cui nome (`name` nella lingua della
  pagina, o `it`) è fra i `names` dell'indice per lo stesso id.

- [x] **Passo 1: test che falliscono** (indice finto con il filtro o l'opzione del plugin, come in
  `tests/dettaglio.php`): una traccia `manutentore` non c'è; tre `gestore` con date 2020, 2024, null → 2024,
  2020, null; una `gestore` con l'id nell'indice ma un altro nome → assente; una senza pagina nell'indice →
  assente; la card ha foto, nome con codice, tempo e lunghezza dai campi della card.
- [x] **Passo 2:** test → `FAIL`.
- [x] **Passo 3:** implementare.
- [x] **Passo 4:** test → `PASS`.
- [x] **Passo 5 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "feat(oc:8990): sentieri gestiti dell'ente"`

### Task 4: pagine dinamiche `/enti` e `/enti/<slug>`

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-4-hook-aggiuntivi-e-cache-delle-lingue-di-wpml)

**File:**
- Modifica: `themes/forestas-child/inc/entes.php`
- Modifica: `themes/forestas-child/inc/news.php:123` (`FORESTAS_REGOLE_VERSIONE` da 3 a 4)
- Crea: `themes/forestas-child/templates/entes-list.php`, `themes/forestas-child/templates/ente-detail.php`
  (scheletro: titolo e un elemento con `data-test` per i controlli; il markup vero è nel Task 5)
- Test: `themes/forestas-child/tests/entes-route.php`

**Interfacce:**
- Consuma: Task 1-3.
- Produce:
  - `forestas_entes_rewrite_rules(): array` — `^enti/?$`, `^enti/page/([0-9]+)/?$`, `^enti/([^/]+)/?$`
    (dopo quella della paginazione) verso le query var `forestas_entes=list|detail`, `paged`,
    `forestas_ente_slug`; aggiunte con il filtro `rewrite_rules_array` come `inc/news.php:114` (WPML toglie
    `/en/` prima delle regole);
  - `forestas_entes_route( array $vars ): array{status: int, view: string, ente?: array, page?: int, pages?: int}`
    — pura: `list` con pagina valida 200, oltre l'ultima 404; `detail` con slug nella copia 200, altrimenti 404;
    nessuna copia 503;
  - `template_include` verso i due template, `status_header()` e `Retry-After` per il 503, come il plugin
    in `includes/detail-render.php:339`;
  - titolo del documento (`pre_get_document_title`, come `includes/detail-seo.php:56` del plugin), `hreflang` e
    selettore di lingua (`icl_ls_languages`, come `includes/detail-wpml.php:87`) verso la stessa pagina nell'altra
    lingua.

- [x] **Passo 1: test che falliscono** in `tests/entes-route.php`: le tre regole sono nell'array di
  `rewrite_rules_array` e `enti/page/2` cade sulla paginazione, non sul dettaglio; `forestas_entes_route()` con
  20 enti: pagina 3 → 200 con `pages === 3`, pagina 4 → 404; slug esistente → 200 con l'ente; slug inesistente
  → 404; copia vuota e attesa in corso → 503.
- [x] **Passo 2:** test → `FAIL`.
- [x] **Passo 3:** implementare; alzare `FORESTAS_REGOLE_VERSIONE` a 4.
- [x] **Passo 4:** test → `PASS`; rigenerare le regole (`wp rewrite flush`) e controllare con `curl -s -o /dev/null -w '%{http_code}'`:
  `http://localhost:8090/enti/` 200, `/enti/page/2/` 200, `/enti/comune-di-stintino/` 200, `/en/enti/comune-di-stintino/` 200,
  `/enti/non-esiste/` 404, `/enti/page/99/` 404.
- [x] **Passo 5 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "feat(oc:8990): pagine dinamiche dell'elenco e del dettaglio degli enti"`

### Task 5: template e stile di elenco e dettaglio

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-5-filtro-argomento-ordine-della-galleria-e-card-con-il-tipo-padre)

**File:**
- Modifica: `themes/forestas-child/templates/entes-list.php`, `themes/forestas-child/templates/ente-detail.php`
- Crea: `themes/forestas-child/assets/css/entes.css` (caricato solo su queste pagine, versione da
  `forestas_versione()`; alzare `Version` di `style.css`)

**Interfacce:**
- Consuma: `forestas_entes_list()`, `forestas_entes_view()`, `forestas_entes_managed_tracks()`,
  `forestas_entes_type_label()`, `forestas_dettaglio_griglia()`, `forestas_dettaglio_mostra_tutti()`,
  `forestas_galleria_lightbox()`, `forestas_card()` / `forestas-card`.

- [x] **Passo 1: riferimento Drupal.** Dal codice Drupal locale (`docroot/themes/custom/sardegnasentieri`,
  `config/sync/views.view.enti.yml`, `templates/content/node--ente-istituzione-societa--full.html.twig`) e dalla
  produzione: ordine dei campi, testi delle etichette («Informazioni», «Tipo», «Contatti», «Pagina web»,
  «Collegamenti», «Sentieri gestiti», «Aggiornato il», «Creato il» e i loro equivalenti inglesi su `/en/`),
  contenuto delle card dell'elenco, testata di `/enti` («Enti, istituzioni e società»).
- [x] **Passo 2: elenco.** Testata come le altre pagine del child, griglia di card `forestas-card` (immagine,
  nome, tipo), paginazione con `nav` e `aria-label`, `aria-current` sulla pagina corrente, messaggio quando non
  ci sono enti.
- [x] **Passo 3: dettaglio.** Nell'ordine del template Drupal; testi con la loro `lang`
  (`forestas_dettaglio_lang()`); galleria con la lightbox come `wp-package/track.php:375-385`; «Sentieri gestiti»
  con `forestas_dettaglio_griglia()` e «Mostra tutti (N)» come `track.php:417-421`; sezioni vuote non mostrate.
- [ ] **Passo 4: verifica nel browser**, schermo largo e sotto 960px, IT ed EN, confrontata con la produzione
  di Drupal: Oasi Tepilora, Comune di Stintino, Agenzia Forestas, GAL BMG, elenco con la paginazione. Ogni
  differenza non concordata si corregge (regola «Drupal 1:1» per contenuti e ordine, stile del child).
- [x] **Passo 5 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "feat(oc:8990): template e stile delle pagine degli enti"`

### Task 6: tab «Enti» nella ricerca unica

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-6-testo-vuoto-dalla-copia-e-al-massimo-50-risultati)

**File:**
- Modifica: `themes/forestas-child/inc/search.php` (rotta REST e source nel filtro di riga 356, configurazione
  dello script a riga ~435)
- Modifica: `themes/forestas-child/assets/js/search.js` (seconda `registerSource`)
- Test: `themes/forestas-child/tests/entes-search.php`

**Interfacce:**
- Consuma: `forestas_entes_list()`, `forestas_entes_type_label()`, `forestas_entes_url()`, token e origine del
  Task 1.
- Produce:
  - rotta `GET forestas/v1/entes-search` con `q`, `page`, `per_page`, `lang` (stessi argomenti di
    `forestas/v1/search`), handler `forestas_entes_search_rest( WP_REST_Request $request ): WP_REST_Response` che
    risponde `{ total, items }` con gli item nella forma di quelli dei contenuti (`id`, `title`, `url`,
    `typeKey: 'ente'`, `typeLabel`, `image`); testo vuoto → gli enti di `forestas_entes_list()`, senza chiamare lo
    shard; testo → `api/v1/entes/search?query=<testo>` con il token, senza cache; errore o 503 → risposta 503
    con un messaggio;
  - source `entes` nel filtro `wp_package_search_sources`: `slug` `enti`, `label` «Enti» / «Organisations»,
    `nouns` «%s ente» / «%s enti», «%s organisation» / «%s organisations», `script` `forestas-search`,
    `layout` `cards`, nessun filtro;
  - in `search.js` `api.registerSource('entes', …)` che chiama `config.entesEndpoint` e su errore lancia
    un'eccezione con il messaggio, che il plugin mostra nel tab.

- [x] **Passo 1: test che falliscono** in `tests/entes-search.php`, shard finto: testo vuoto → `total` uguale al
  numero di enti della copia e nessuna richiesta; testo `tepilora` → una richiesta con `query=tepilora` e
  `Authorization: Bearer …`, item con `url` verso `/enti/<slug>/`; shard 503 → risposta 503; testo di 201
  caratteri → troncato a 200 prima della richiesta (l'API risponde 422 oltre i 200).
- [x] **Passo 2:** test → `FAIL`.
- [x] **Passo 3:** implementare rotta, source e script.
- [x] **Passo 4:** (16/11, ricerca «tepilora» con il token del dev: 2 risultati) test → `PASS`; nel browser `/cerca-nel-sito` e `/en/cerca-nel-sito`: tab «Enti» con i
  conteggi, ricerca «tepilora», tab con lo shard finto giù (gli altri tab funzionano).
- [x] **Passo 5 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "feat(oc:8990): enti nella ricerca unica"`

### Task 7: link agli enti dai dettagli di percorsi e POI

**File:**
- Modifica: `themes/forestas-child/inc/dettaglio.php:308-343` (`forestas_dettaglio_enti()`: chiave `url`) e
  `:550-570` (`forestas_dettaglio_gruppo()`: indice 5 della riga, l'indirizzo del valore)
- Modifica: `themes/forestas-child/wp-package/track.php:405` e `themes/forestas-child/wp-package/poi.php:78`
- Test: `themes/forestas-child/tests/dettaglio.php` (casi nuovi)

**Interfacce:**
- Consuma: `forestas_entes_cached()`, `forestas_entes_url()` (dopo la review interna `forestas_entes_known_slugs()` al
  posto di `forestas_entes_cached()`: Task 11).
- Produce: `forestas_dettaglio_enti()` restituisce anche `url: ?string` — l'indirizzo dell'ente nella lingua
  della pagina se lo `slug` è fra gli enti della copia, altrimenti null.

- [x] **Passo 1: test che falliscono** in `tests/dettaglio.php`: con una copia che contiene lo slug
  dell'ente della traccia completa → `url` valorizzato e il gruppo «Enti» ha un `<a href=…>`; senza copia →
  `url` null, nessun link e nessuna richiesta allo shard; slug non nella copia → `url` null.
- [x] **Passo 2:** test → `FAIL`.
- [x] **Passo 3:** implementare; in `poi.php` il nome nella cella diventa un link quando `url` c'è.
- [x] **Passo 4:** (16/11, dati del dev: G 203A con 2 link, D 701 con 1) test → `PASS`; nel browser un sentiero (Su Lisandru - Vigneto, G 203A) e un POI (Bellarosa
  Minore, D 701) portano all'ente, in IT ed EN.
- [x] **Passo 5 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "feat(oc:8990): link agli enti dai dettagli di percorsi e POI"`

### Task 8: contenuti di default nel cestino

> ⚠️ L'implementazione ha deviato da questo task: [notes.md](notes.md#task-8-cestino-opzione-della-privacy-e-lingue-di-wpml)

**File:**
- Crea: `docker/scripts/config/default-content.php` con
  `wpf_default_content_apply( bool $dry_run, callable $diff ): bool`
- Modifica: `docker/scripts/config/apply.php` (require e passo «4. Contenuti di default di WordPress» prima
  dell'esito)
- Modifica: `docs/howto/ricreare-wordpress-da-zero.md` (passo e riga attesa nell'output dell'apply)

**Interfacce:**
- Produce: `wpf_default_content_apply()` — per id 1 (`post`, `ciao-mondo`), 2 (`page`, `pagina-di-esempio`),
  3 (`page`, `privacy-policy`): se il post esiste, ha quello slug, non è nel cestino e `post_modified_gmt` è
  uguale a `post_date_gmt`, `$diff( "default: <tipo> <id> <slug>", 'presente', 'nel cestino' )` e, se non è una
  prova, `wp_trash_post()`; se `wp_page_for_privacy_policy` vale 3, `$diff` e `update_option( …, 0 )`. Vero se
  tutto è riuscito.

- [x] **Passo 1:** in locale `bin/wordpress-config.sh applica` (prova) → le righe `default: post 1 ciao-mondo`,
  `default: page 2 pagina-di-esempio`, `default: page 3 privacy-policy` e quella dell'opzione, prima di
  implementare: nessuna (verifica che manchino).
- [x] **Passo 2:** implementare.
- [x] **Passo 3:** `bin/wordpress-config.sh applica` → le quattro righe; `applica --conferma` → riuscito;
  `applica` di nuovo → nessuna riga `default:`; `wp post list --post_status=trash` ha 1, 2 e 3;
  `/news/ciao-mondo/` e `/pagina-di-esempio/` → 404; la ricerca `ciao mondo` non trova nulla.
- [x] **Passo 4:** aggiornare `ricreare-wordpress-da-zero.md`.
- [x] **Passo 5 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "feat(oc:8990): contenuti di default di WordPress nel cestino"`

### Task 9: plugin aggiornato

Dopo il piano del plugin, con il suo branch mergiato su `develop` e poi su `main` di `wp-package`.

**File:**
- Modifica: `docker/scripts/init-wordpress.sh:16` (`WP_PACKAGE_REF` con l'hash di `main` del plugin, letto con
  `git rev-parse`)

- [x] **Passo 1 (17/11, `WP_PACKAGE_REF=827af5e…`, `main` del plugin con la 0.4.0; il controllo di `.wpf-ref` si fa su dev e UAT, in locale il plugin è il link al clone):** nuovo hash; `scripts/wordpress-up.sh` di `forestas` in locale; il plugin nel container ha
  `.wpf-ref` uguale al nuovo hash (in locale è il link simbolico, il controllo vale per dev e UAT).
- [x] **Passo 2 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "chore(oc:8990): wp-package con la copia dei dettagli"`

### Task 10: documentazione e chiusura

**File:**
- Crea: `docs/knowledge/8990-enti-sul-sito-wordpress-elenco-enti-dettaglio-enti-slug-e-ricerca-dallapi-di-oc8965.md`
  (come funziona oggi, perché così, come ci siamo arrivati), con il rimando nella tabella `## Conoscenza` di
  `CLAUDE.md`
- Modifica: `docs/howto/ricreare-wordpress-da-zero.md` (token, `wordpress-up.sh` dopo il `.env`, indice Elastic
  degli enti)
- Crea: `docs/features/<slug>/notes.md`
- Proposta al dev: trappole nel `CLAUDE.md` (per esempio «il token entra nel container solo con
  `wordpress-up.sh`», «in locale WP-Cron non parte»)

- [x] **Passo 1:** (16/11, tutti PASS) tutti i test del child (`tests/*.php`) e del plugin (`tests/php/*.php`) → `PASS`.
- [x] **Passo 2:** scrivere pagina di conoscenza, howto e note.
- [x] **Passo 3 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "docs(oc:8990): enti sul sito WordPress"`

### Task 11: review interna, primo giro

> ⚠️ Modifiche chieste dalla review interna dopo i task 1-10: [notes.md](notes.md#review-interna-freni-della-ricerca-copia-snella-e-pulizie)

**File:**
- Modifica: `themes/forestas-child/inc/entes.php`, `inc/search.php`, `inc/dettaglio.php`, `inc/contenuti.php`,
  `templates/ente-detail.php`, `assets/css/entes.css`, `style.css`
- Modifica: i test `tests/entes-*.php` e `tests/dettaglio.php`
- Modifica: `.env-example`, `docs/howto/ricreare-wordpress-da-zero.md`, `docs/howto/messa-in-opera-degli-enti.md`, la
  pagina di conoscenza; nel plugin `README.md` e il suo `plan.md`

- [x] **Passo 1:** freni della ricerca degli enti: limite per visitatore (429 con `Retry-After`), pausa dopo un errore
  (503 senza chiamare lo shard, una riga di log per pausa), IP da X-Forwarded-For solo dietro un REMOTE_ADDR privato;
  test in `tests/entes-search.php`.
- [x] **Passo 2:** copia snella, riscritta solo quando cambia, con le option piccole dello stato e degli slug; dettagli e
  ricerca solo sugli slug (`forestas_entes_known_slugs()`); misure prima e dopo.
- [x] **Passo 3:** calo accettato dopo 3 ore, rinnovo da una pagina con WP-Cron fermo, slug doppi nel log, token solo in
  https o verso un host locale, evento tolto cambiando tema; test in `tests/entes-copy.php`.
- [x] **Passo 4:** query var del core tolte sulle regole degli enti (test in `tests/entes-route.php`, log pulito con
  curl), codice `en-us` nella rotta dei contenuti, pulizie (card, motivi HTTP, date, testo dei link, costanti, CSS).
- [x] **Passo 5:** tutti i test del child, del passo dei contenuti di default e del plugin → `PASS`; `bin/wordpress-config.sh
  applica` → nessuna differenza; documenti aggiornati.
- [x] **Passo 6 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "fix(oc:8990): freni della ricerca degli enti, copia snella e pulizie della review"`

### Task 12: review interna, secondo giro

> ⚠️ Modifiche chieste dal secondo giro della review interna: [notes.md](notes.md#review-interna-secondo-giro-pausa-della-ricerca-lucchetto-del-rinnovo-e-contatori)

**File:**
- Modifica: `themes/forestas-child/inc/search.php`, `inc/entes.php`
- Modifica: i test `tests/entes-search.php` e `tests/entes-copy.php`
- Modifica: `docs/howto/ricreare-wordpress-da-zero.md`, la pagina di conoscenza, `overview.md`, `notes.md`; nel plugin
  `docs/knowledge/8969-…md`

- [x] **Passo 1:** pausa della ricerca solo quando lo shard non è disponibile (rete, 401, 403, 429, 5xx, 200 senza
  `data`); un 4xx del testo dà «nessun risultato» senza pausa né log; testo di soli caratteri invisibili trattato come
  vuoto (`forestas_entes_search_text()`); test in `tests/entes-search.php`.
- [x] **Passo 2:** contatori del limite in un'option sola, non autoload, senza le finestre chiuse e con un tetto di 2000
  visitatori; test in `tests/entes-search.php`.
- [x] **Passo 3:** un rinnovo alla volta con il lucchetto atomico `forestas_entes_lock()` (anche abbandonato), un
  tentativo per richiesta, rinnovo scaduto lasciato a WP-Cron appena partito per 2 minuti al massimo, opzione degli
  slug riallineata anche con gli stessi dati; test in `tests/entes-copy.php`.
- [x] **Passo 4:** tutti i test del child, del passo dei contenuti di default e del plugin → `PASS`; curl della ricerca
  con `?q=%E2%80%8B` e delle pagine degli enti; documenti aggiornati.
- [x] **Passo 5 (per il dev, 17/11, riunito nei tre commit del ticket):** `git commit -m "fix(oc:8990): pausa della ricerca solo per i guasti dello shard, lucchetto del rinnovo e contatori del limite"`
