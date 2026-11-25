<?php
/**
 * Ricerca unica del sito (oc:8952), parte del child. La pagina e i percorsi li fa il plugin wp-package;
 * qui le source «Contenuti del sito» (news, eventi, «da sapere», pagine) ed «Enti» (oc:8990), ognuna con la sua
 * rotta REST.
 *
 * Il child parla al plugin solo con i filter hook, con shortcode_exists() e, in JS, controllando
 * window.wmWpSearch: senza plugin il sito non va in errore.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Tipi cercati: valore nell'indirizzo (parametro `content`, in inglese in tutte le lingue, oc:9018) => post type
const FORESTAS_SEARCH_TYPES = [
	'news'         => 'post',
	'event'        => 'evento',
	'good-to-know' => 'da_sapere',
	'page'         => 'page',
];
const FORESTAS_SEARCH_PER_PAGE = 12;
// Pagina di ricerca in config/pagine.json (meta _wpf_pagina): la trovano i redirect da `?s=` e dai link di Drupal e
// lo shortcode; fuori dai risultati la tiene la regola delle pagine di struttura in forestas_search_rest()
const FORESTAS_SEARCH_PAGE_KEY = 'cerca-nel-sito';
// Slug della ricerca inglese prima di oc:9018: uguale all'italiano, come la view di Drupal (`/en/cerca-nel-sito`). Lo slug
// nuovo sta in config/pagine.json (`search-in-our-website`); il vecchio porta lì con un 301 (forestas_search_old_slug_target())
const FORESTAS_SEARCH_OLD_SLUGS = [ 'cerca-nel-sito' ];
// Ordine di ripiego dei valori di una scala di difficoltà, finché lo shard non pubblica il suo (`sort_order`, ticket
// sullo shard, oc:9018): un punto solo; un valore che non vi compare si mostra lo stesso, in fondo
const FORESTAS_DIFFICULTY_ORDER = [
	'hiking-difficulty' => [ 'hiking-difficulty-T', 'hiking-difficulty-E', 'hiking-difficulty-EE', 'hiking-difficulty-EEA' ],
];
// Scale di difficoltà: le famiglie dello shard con questo suffisso, tranne l'accessibilità, che ha il suo indicatore
// (regola unica per i filtri della ricerca e per il dettaglio, forestas_famiglia_difficolta())
const FORESTAS_DIFFICULTY_SUFFIX  = '-difficulty';
const FORESTAS_DIFFICULTY_EXCLUDE = [ 'inclusivity-difficulty' ];

/** Vero se la famiglia dello shard è una scala di difficoltà (FORESTAS_DIFFICULTY_SUFFIX, FORESTAS_DIFFICULTY_EXCLUDE). */
function forestas_famiglia_difficolta( string $famiglia ): bool {
	return str_ends_with( $famiglia, FORESTAS_DIFFICULTY_SUFFIX ) && ! in_array( $famiglia, FORESTAS_DIFFICULTY_EXCLUDE, true );
}

/** Nome di ogni tipo nella lingua corrente. */
function forestas_search_type_labels(): array {
	return [
		'news'         => forestas_testo( 'News', 'News' ),
		'event'        => forestas_testo( 'Evento', 'Event' ),
		'good-to-know' => forestas_testo( 'Da sapere', 'Good to know' ),
		'page'         => forestas_testo( 'Pagina', 'Page' ),
	];
}

/** Argomenti comuni delle rotte REST della ricerca: testo, pagina, risultati per pagina, lingua della pagina. */
function forestas_search_rest_args(): array {
	return [
		'q'        => [
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_text_field',
		],
		'page'     => [
			'type'    => 'integer',
			'default' => 1,
			'minimum' => 1,
		],
		'per_page' => [
			'type'    => 'integer',
			'default' => FORESTAS_SEARCH_PER_PAGE,
			'minimum' => 1,
			'maximum' => 50,
		],
		'lang'     => [
			'type'    => 'string',
			'default' => '',
			'enum'    => [ '', 'it', 'en' ],
		],
	];
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			'forestas/v1',
			'/search',
			[
				'methods'             => 'GET',
				'callback'            => 'forestas_search_rest',
				'permission_callback' => '__return_true',
				'args'                => forestas_search_rest_args() + [
					'type' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			]
		);
		register_rest_route(
			'forestas/v1',
			'/entes-search',
			[
				'methods'             => 'GET',
				'callback'            => 'forestas_entes_search_rest',
				'permission_callback' => '__return_true',
				// Filtri «Tipo di ente» e «Ruolo sui sentieri» (oc:9000): valori separati da virgole
				'args'                => forestas_search_rest_args() + array_fill_keys(
					[ 'entity-type', 'role' ],
					[
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					]
				),
			]
		);
	}
);

/**
 * Risultati della ricerca dei contenuti: una pagina di card pronte e i conteggi per tipo, che non dipendono
 * dal tipo scelto. Stessa ricerca del core (WP_Query con «s»), nella lingua chiesta.
 */
function forestas_search_rest( WP_REST_Request $request ): WP_REST_Response {
	$lang = (string) $request['lang'];
	if ( '' !== $lang ) {
		// Con il codice di WPML (`en` → `en-us`): con `en` WPML cambia i testi di forestas_testo() ma non gli indirizzi
		do_action( 'wpml_switch_language', function_exists( 'forestas_entes_wpml_code' ) ? forestas_entes_wpml_code( $lang ) : $lang );
	}
	$text   = trim( (string) $request['q'] );
	$chosen = array_values( array_intersect( array_map( 'trim', explode( ',', (string) $request['type'] ) ), array_keys( FORESTAS_SEARCH_TYPES ) ) );

	$args = [
		'post_type'        => array_values( FORESTAS_SEARCH_TYPES ),
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'fields'           => 'ids',
		'no_found_rows'    => true,
		'suppress_filters' => false,
	];
	if ( '' !== $text ) {
		$args['s'] = $text;
	} else {
		$args['orderby'] = 'date';
		$args['order']   = 'DESC';
	}
	// Fuori le pagine di struttura del sito: quelle che lo script `pagine` crea senza un nodo Drupal (`_wpf_pagina` senza
	// `_drupal_nid`: schede ed elenchi di News e Da sapere, Eventi, Cammini, questa pagina di ricerca), che sono elenchi
	// senza testo; e la home. Restano le pagine venute da Drupal e quelle scritte in WordPress dalla redazione (senza
	// `_wpf_pagina`), come le «pagine base» che cerca oggi Drupal (review interna del 10/11)
	$args['meta_query'] = [
		'relation' => 'OR',
		[
			'key'     => '_wpf_pagina',
			'compare' => 'NOT EXISTS',
		],
		[
			'key'     => '_drupal_nid',
			'compare' => 'EXISTS',
		],
	];
	$front = (int) get_option( 'page_on_front' );
	if ( $front ) {
		$args['post__not_in'] = [ $front ];
	}
	$ids = array_map( 'intval', ( new WP_Query( $args ) )->posts );
	_prime_post_caches( $ids, false, false );

	$keys    = array_flip( FORESTAS_SEARCH_TYPES );
	$by_type = array_fill_keys( array_keys( FORESTAS_SEARCH_TYPES ), 0 );
	$matches = [];
	foreach ( $ids as $id ) {
		$key = $keys[ get_post_type( $id ) ] ?? null;
		if ( null === $key ) {
			continue;
		}
		++$by_type[ $key ];
		if ( ! $chosen || in_array( $key, $chosen, true ) ) {
			$matches[] = [ $id, $key ];
		}
	}

	$per_page = (int) $request['per_page'];
	$pages    = max( 1, (int) ceil( count( $matches ) / $per_page ) );
	$page     = min( (int) $request['page'], $pages );
	$labels   = forestas_search_type_labels();
	$items    = [];
	foreach ( array_slice( $matches, ( $page - 1 ) * $per_page, $per_page ) as [ $id, $key ] ) {
		$items[] = forestas_search_card( $id, $key, $labels[ $key ] );
	}

	return new WP_REST_Response(
		[
			'total'   => count( $matches ),
			'by_type' => $by_type,
			'items'   => $items,
		]
	);
}

/** Card di un contenuto, nel formato delle card del plugin (README di wp-package, «Interfaccia pubblica»). */
function forestas_search_card( int $id, string $key, string $label ): array {
	$image_id = (int) get_post_thumbnail_id( $id );
	$image    = $image_id ? wp_get_attachment_image_src( $image_id, 'medium_large' ) : false;
	return [
		'id'        => $id,
		'title'     => html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' ),
		'url'       => get_permalink( $id ),
		'typeKey'   => $key,
		'typeLabel' => $label,
		'image'     => $image ? [
			'src' => $image[0],
			'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
		] : null,
		'code'      => null,
		'meta'      => [],
		'badge'     => null,
		'excerpt'   => html_entity_decode( wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_the_excerpt( $id ) ) ), 30 ), ENT_QUOTES, 'UTF-8' ),
	];
}

/*
 * Tab «Enti» (oc:8990): gli enti dello shard, letti con le funzioni di inc/entes.php. A testo vuoto (apertura della
 * pagina) l'elenco della copia, come gli altri tab elencano i loro contenuti; con un testo la ricerca dello shard
 * (api/v1/entes/search), che chiama solo il server, con il token del sito.
 *
 * Nessuna cache per testo: con la ricerca mentre si scrive quasi ogni testo è nuovo, e la cache lascerebbe solo righe in
 * wp_options. Lo shard si protegge invece con due freni, perché il suo limite al minuto (throttle di Laravel) conta per
 * IP, e il sito è un IP solo: la stessa chiave per la ricerca (600 al minuto) e per il JSON della copia (60 al minuto),
 * così le ricerche consumano anche il margine del rinnovo. Il limite per visitatore spetta al sito (pagina 8965 di
 * forestas):
 * - un limite per visitatore: FORESTAS_ENTES_SEARCH_LIMIT ricerche con un testo in una finestra fissa di
 *   FORESTAS_ENTES_SEARCH_WINDOW secondi, contate per impronta dell'IP in un'opzione sola, che tiene solo le finestre
 *   aperte e al massimo FORESTAS_ENTES_SEARCH_VISITORS visitatori; oltre, 429 con `Retry-After`;
 * - una pausa per tutto il sito quando la ricerca dello shard non è disponibile (rete, 5xx, 429, token mancante o
 *   rifiutato, risposta 200 non valida): per FORESTAS_ENTES_SEARCH_PAUSE secondi la rotta risponde 503 senza chiamare
 *   lo shard, e nel log va una riga per pausa, non una per tasto. Un 4xx che dipende dal testo (400, 422…) non è un
 *   guasto dello shard e non la fa partire (forestas_entes_search_refused()): altrimenti un testo fatto apposta
 *   fermerebbe la ricerca per tutti.
 */
const FORESTAS_ENTES_SEARCH_MAX = 200; // caratteri del testo accettati da api/v1/entes/search: oltre risponde 422
// Secondi di attesa della ricerca dello shard: meno dei 15 del plugin (timeout_ms di assets/js/search.js), così la
// risposta 503 arriva prima che la pagina rinunci alla source
const FORESTAS_ENTES_SEARCH_TIMEOUT = 8;
// Ricerche con un testo per visitatore in una finestra: con la ricerca mentre si scrive (una richiesta ogni 400 ms al
// massimo, DEBOUNCE_MS del plugin) una persona ne fa poche per parola
const FORESTAS_ENTES_SEARCH_LIMIT     = 30;
const FORESTAS_ENTES_SEARCH_WINDOW    = 60;                            // secondi della finestra fissa del limite
const FORESTAS_ENTES_SEARCH_PAUSE     = 30;                            // secondi senza chiamare lo shard dopo un errore
const FORESTAS_ENTES_SEARCH_PAUSE_KEY = 'forestas_entes_search_pause'; // transient della pausa: l'istante in cui finisce
// Contatori del limite per visitatore: impronta dell'IP → [ ricerche, inizio della finestra ], non autoload
const FORESTAS_ENTES_SEARCH_COUNTERS = 'forestas_entes_search_counters';
// Visitatori al massimo nei contatori: oltre restano quelli con la finestra più recente (144 KB serializzati)
const FORESTAS_ENTES_SEARCH_VISITORS = 2000;

/** L'indirizzo è un IP pubblico (né privato, né di loopback o riservato)? */
function forestas_entes_public_ip( string $ip ): bool {
	return false !== filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
}

/**
 * IP del visitatore, per il limite della ricerca degli enti. Su dev e UAT il container WordPress sta dietro l'Apache
 * dell'host (docker/dev/, docker/uat/: `ProxyPass` verso 127.0.0.1:8090), quindi REMOTE_ADDR è il gateway della rete
 * di Docker (privato, es. 172.27.0.1) e l'IP vero arriva in X-Forwarded-For, che mod_proxy aggiunge in coda. Per questo
 * X-Forwarded-For vale solo quando REMOTE_ADDR è privato o di loopback (un proxy di fiducia), e se ne prende l'ultimo
 * indirizzo pubblico da destra: quelli più a sinistra li può scrivere il visitatore. Con un REMOTE_ADDR pubblico (sito
 * senza proxy) vale quello. '' se REMOTE_ADDR non è un IP (WP-CLI).
 */
function forestas_entes_visitor_ip(): string {
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
	if ( false === filter_var( $remote, FILTER_VALIDATE_IP ) ) {
		return '';
	}
	if ( forestas_entes_public_ip( $remote ) ) {
		return $remote;
	}
	$forwarded = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : '';
	foreach ( array_reverse( explode( ',', $forwarded ) ) as $candidate ) {
		$candidate = trim( $candidate );
		if ( forestas_entes_public_ip( $candidate ) ) {
			return $candidate;
		}
	}
	return $remote;
}

/** Chiave di un IP nei contatori del limite: l'impronta (HMAC con il salt del sito), così l'IP non va nel database. */
function forestas_entes_search_visitor_key( string $ip ): string {
	return substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
}

/**
 * Conta una ricerca con un testo dell'IP nella sua finestra fissa (FORESTAS_ENTES_SEARCH_WINDOW secondi dalla prima).
 * 0 se la ricerca può partire; oltre il limite i secondi che mancano alla fine della finestra (per `Retry-After`), senza
 * scrivere nel database.
 *
 * I contatori stanno in un'opzione sola (FORESTAS_ENTES_SEARCH_COUNTERS), non in un transient per visitatore: senza una
 * object cache persistente un transient scaduto resta in wp_options finché lo stesso IP non lo rilegge o non passa
 * `delete_expired_transients` (una volta al giorno, con WP-Cron), e una raffica da molti IP lascerebbe migliaia di
 * righe. A ogni scrittura escono le finestre chiuse, e oltre FORESTAS_ENTES_SEARCH_VISITORS visitatori quelle più
 * vecchie: l'opzione ha al massimo quella misura. Due ricerche nello stesso istante possono contare una volta sola (la
 * seconda scrittura copre la prima): il limite può sforare di poco, e va bene per un freno.
 */
function forestas_entes_search_throttle( string $ip ): int {
	$key      = forestas_entes_search_visitor_key( $ip );
	$now      = time();
	$counters = get_option( FORESTAS_ENTES_SEARCH_COUNTERS, [] );
	$counters = array_filter(
		is_array( $counters ) ? $counters : [],
		fn( $entry ) => is_array( $entry ) && $now - (int) ( $entry[1] ?? 0 ) < FORESTAS_ENTES_SEARCH_WINDOW
	);
	$count = (int) ( $counters[ $key ][0] ?? 0 );
	$start = (int) ( $counters[ $key ][1] ?? $now );
	if ( $count >= FORESTAS_ENTES_SEARCH_LIMIT ) {
		return FORESTAS_ENTES_SEARCH_WINDOW - ( $now - $start );
	}
	unset( $counters[ $key ] );
	if ( count( $counters ) >= FORESTAS_ENTES_SEARCH_VISITORS ) {
		uasort( $counters, fn( array $a, array $b ) => (int) ( $b[1] ?? 0 ) <=> (int) ( $a[1] ?? 0 ) );
		$counters = array_slice( $counters, 0, FORESTAS_ENTES_SEARCH_VISITORS - 1, true );
	}
	$counters[ $key ] = [ $count + 1, $start ];
	update_option( FORESTAS_ENTES_SEARCH_COUNTERS, $counters, false );
	return 0;
}

/**
 * Testo della ricerca senza i caratteri invisibili o di spaziatura in testa e in coda: spazi Unicode (\p{Z}, compreso
 * U+00A0), caratteri di controllo (\p{Cc}) e di formato (\p{Cf}: U+200B-U+200F, U+2060, U+FEFF…), e gli altri di
 * `Str::INVISIBLE_CHARACTERS` di Laravel. È il taglio di `Str::trim()`, che lo shard fa con il middleware TrimStrings,
 * con qualche carattere in più: sanitize_text_field() e trim() di PHP quei caratteri li lasciano, e un testo fatto solo
 * di quelli arriverebbe allo shard, che lo renderebbe null e risponderebbe 422. Così è vuoto anche qui.
 */
function forestas_entes_search_text( string $text ): string {
	$invisible = '[\s\p{Z}\p{Cc}\p{Cf}\x{034F}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{2065}\x{2800}\x{3164}\x{FFA0}\x{1D159}\x{1D173}-\x{1D17A}]';
	return preg_replace( '/^' . $invisible . '+|' . $invisible . '+$/u', '', $text ) ?? trim( $text );
}

/**
 * Lo shard ha rifiutato la ricerca per il testo? Sì solo per i 4xx della validazione (400, 422), e allora la risposta è
 * «nessun risultato», senza pausa e senza log: il testo lo sceglie il visitatore. Ogni altro codice è un guasto, con la
 * pausa e il messaggio nel tab: la rete (0), 401 e 403 (token), 404 (rotta mancante, come su uno shard senza oc:8965),
 * 429 (limite al minuto dello shard), gli altri 4xx e i 5xx.
 */
function forestas_entes_search_refused( int $code ): bool {
	return in_array( $code, [ 400, 422 ], true );
}

/** Secondi che restano alla pausa della ricerca dopo un errore dello shard, 0 se non c'è. */
function forestas_entes_search_paused(): int {
	return max( 0, (int) get_transient( FORESTAS_ENTES_SEARCH_PAUSE_KEY ) - time() );
}

/**
 * Risultati del tab «Enti»: `{ total, items }`, con una pagina di card nella forma di quelle dei contenuti.
 * - Testo vuoto: gli enti della copia nell'ordine dell'elenco /enti, senza chiamare lo shard (l'API risponde 422 a un
 *   testo vuoto) e senza contare per il limite. Se una copia non c'è mai stata (sito appena installato) 503: la prima
 *   richiesta allo shard è delle pagine degli enti (forestas_entes_all()), non di una chiamata REST fatta mentre si
 *   scrive.
 * - Testo: le card della ricerca dello shard (al massimo 50, in ordine di rilevanza), divise in pagine qui; il testo si
 *   tronca ai 200 caratteri che l'API accetta. Il link al dettaglio c'è solo per gli enti della copia: un ente appena
 *   creato nello shard ha la sua pagina al rinnovo successivo, e fino ad allora la card resta senza link. Un testo fatto
 *   solo di spazi o di caratteri invisibili è vuoto (forestas_entes_search_text()).
 * - Oltre il limite del visitatore: 429 con `Retry-After`. Durante la pausa dopo un errore: 503 con `Retry-After`, senza
 *   chiamare lo shard e senza log.
 * - Testo rifiutato dallo shard (400, 422…, forestas_entes_search_refused()): nessun risultato, senza pausa né log.
 * - Ricerca dello shard che non risponde (indice degli enti assente, shard spento, troppe richieste, token mancante o
 *   rifiutato, risposta non valida): 503 con un messaggio, una riga di log e la pausa. Il plugin mostra l'errore nel tab
 *   «Enti», gli altri tab vanno avanti.
 * Nomi, tipi e indirizzi sono nella lingua chiesta: WPML passa a `lang` prima di costruire le card.
 */
function forestas_entes_search_rest( WP_REST_Request $request ): WP_REST_Response {
	$lang = (string) $request['lang'];
	if ( '' !== $lang ) {
		// Con il codice di WPML (`en` → `en-us`): con `en` WPML cambia i testi di forestas_testo() ma non gli indirizzi
		do_action( 'wpml_switch_language', forestas_entes_wpml_code( $lang ) );
	}
	$lang = forestas_entes_lang( '' !== $lang ? $lang : (string) apply_filters( 'wpml_current_language', 'it' ) );
	$text = forestas_entes_search_text( mb_substr( forestas_entes_search_text( (string) $request['q'] ), 0, FORESTAS_ENTES_SEARCH_MAX ) );

	// Slug degli enti della copia: hanno una pagina /enti/<slug>/. Una copia vuota non esiste (il rinnovo la scarta)
	$known = forestas_entes_known_slugs();
	if ( '' === $text ) {
		if ( ! $known ) {
			return forestas_entes_search_unavailable();
		}
		$entes = forestas_entes_sort_by_date( forestas_entes_listed( forestas_entes_cached() ), 'created_at', false );
		$known = null;
	} else {
		$wait = forestas_entes_search_throttle( forestas_entes_visitor_ip() );
		if ( $wait > 0 ) {
			return forestas_entes_search_rate_limited( $wait );
		}
		$paused = forestas_entes_search_paused();
		if ( $paused > 0 ) {
			return forestas_entes_search_unavailable( $paused );
		}
		$response = forestas_entes_api_get( 'api/v1/entes/search', [ 'query' => $text ], FORESTAS_ENTES_SEARCH_TIMEOUT );
		$code     = (int) $response['code'];
		$found    = $response['data']['data'] ?? null;
		if ( forestas_entes_search_refused( $code ) ) {
			// Testo rifiutato (400, 422…): nessun risultato, senza pausa né log
			$found = [];
		} elseif ( 200 !== $code || ! is_array( $found ) || ! array_is_list( $found ) ) {
			$reason = 200 === $code ? 'risposta senza la lista `data`' : forestas_entes_http_reason( $code, (string) $response['error'] );
			set_transient( FORESTAS_ENTES_SEARCH_PAUSE_KEY, time() + FORESTAS_ENTES_SEARCH_PAUSE, FORESTAS_ENTES_SEARCH_PAUSE );
			error_log( sprintf( 'forestas enti: ricerca degli enti non riuscita, codice %d (%s); per %d secondi la ricerca risponde 503 senza chiamare lo shard', $code, $reason, FORESTAS_ENTES_SEARCH_PAUSE ) );
			return forestas_entes_search_unavailable( FORESTAS_ENTES_SEARCH_PAUSE );
		}
		// Senza slug un ente non ha una pagina
		$entes = array_values( array_filter( $found, fn( $ente ) => is_array( $ente ) && '' !== forestas_entes_slug( $ente ) ) );
	}
	$filtered = forestas_entes_search_filter( $entes, forestas_entes_roles_by_slug(), (string) $request['entity-type'], (string) $request['role'] );
	$entes    = array_map( fn( array $ente ): array => forestas_entes_list_item( $ente, $lang, $known ), $filtered['entes'] );

	$per_page = (int) $request['per_page'];
	$pages    = max( 1, (int) ceil( count( $entes ) / $per_page ) );
	$page     = min( (int) $request['page'], $pages );
	return new WP_REST_Response(
		[
			'total'  => count( $entes ),
			'items'  => array_map( 'forestas_entes_search_card', array_slice( $entes, ( $page - 1 ) * $per_page, $per_page ) ),
			'facets' => $filtered['facets'],
		]
	);
}

/**
 * Risposta del tab «Enti» quando gli enti non si possono mostrare: 503 con il messaggio nella lingua corrente e, durante
 * la pausa dopo un errore, `Retry-After` con i secondi che restano.
 */
function forestas_entes_search_unavailable( int $retry_after = 0 ): WP_REST_Response {
	$response = new WP_REST_Response(
		[
			'code'    => 'forestas_entes_unavailable',
			'message' => forestas_testo( 'Gli enti non sono disponibili in questo momento. Riprova più tardi.', 'Organisations are not available right now. Please try again later.' ),
			'data'    => [ 'status' => 503 ],
		],
		503
	);
	if ( $retry_after > 0 ) {
		$response->header( 'Retry-After', (string) $retry_after );
	}
	return $response;
}

/** Risposta del tab «Enti» oltre il limite del visitatore: 429 con `Retry-After` e il messaggio nella lingua corrente. */
function forestas_entes_search_rate_limited( int $retry_after ): WP_REST_Response {
	$response = new WP_REST_Response(
		[
			'code'    => 'forestas_entes_rate_limited',
			'message' => forestas_testo( 'Troppe ricerche in poco tempo. Riprova fra qualche secondo.', 'Too many searches in a short time. Please try again in a few seconds.' ),
			'data'    => [ 'status' => 429 ],
		],
		429
	);
	$response->header( 'Retry-After', (string) $retry_after );
	return $response;
}

/**
 * Card di un ente nella ricerca, nella forma di quelle dei contenuti (forestas_search_card()). L'etichetta è il tipo
 * dell'ente (Comune, Complesso forestale…), come il tipo di percorso nelle card dei percorsi; «Ente» se il tipo manca o
 * è sconosciuto. L'immagine è decorativa (alt vuoto): il nome dell'ente è il testo del link.
 *
 * @param array{id: int, name: string, type: ?string, image: ?string, url: ?string} $ente Una card di forestas_entes_list_item().
 */
function forestas_entes_search_card( array $ente ): array {
	return [
		'id'        => (int) $ente['id'],
		'title'     => (string) $ente['name'],
		'url'       => $ente['url'],
		'typeKey'   => 'ente',
		'typeLabel' => $ente['type'] ?? forestas_testo( 'Ente', 'Organisation' ),
		'image'     => $ente['image'] ? [
			'src' => $ente['image'],
			'alt' => '',
		] : null,
		'code'      => null,
		'meta'      => [],
		'badge'     => null,
	];
}

// Parametri dell'indirizzo: quelli del plugin, in inglese e uguali in tutte le lingue, come la webapp (oc:9018): `q`,
// `tab`, `pg`, `view`, `map`. I vecchi link di Drupal (search_api_fulltext, f[N]) li porta qui il redirect più sotto

/**
 * Nome di una famiglia di termini dal config.json dell'app (`LABELS.taxonomies`, oc:9018), con la lingua se è un'altra;
 * il nome del child solo senza config.json (sito appena installato, prima del giro del cron).
 *
 * @return array{text: string, lang: ?string}
 */
function forestas_search_family_label( string $family, string $fallback ): array {
	$config = forestas_plugin_ready() ? wmwp_app_config() : null;
	$label  = null !== $config ? wmwp_app_label( $config['LABELS']['taxonomies'][ $family ]['labels'] ?? [], forestas_dettaglio_lingua() ) : [
		'text' => '',
		'lang' => null,
	];
	return '' !== $label['text'] ? $label : [
		'text' => $fallback,
		'lang' => null,
	];
}

/**
 * Faccetta di una famiglia del registro degli stati del plugin (oc:9000): ordine del registro, nomi dei termini dello
 * shard (oc:9018); `missing` è lo stato di assenza, che vale anche per i percorsi senza termine (search-logic.js), con il
 * suo nome dal registro, perché nessun dato lo porta. Null se il registro non conosce la famiglia o il plugin è vecchio.
 *
 * @return array<string, mixed>|null
 */
function forestas_search_status_facet( string $family, string $key, string $fallback ): ?array {
	$registry = forestas_plugin_ready() ? ( wmwp_status_families()[ $family ] ?? null ) : null;
	if ( ! is_array( $registry ) ) {
		return null;
	}
	$label = forestas_search_family_label( $family, $fallback );
	$facet = [
		'key'        => $key,
		'label'      => $label['text'],
		'label_lang' => $label['lang'],
		'type'       => 'terms',
		'field'      => 'terms.' . $family,
		'order'      => array_values( array_filter( array_map( fn( $state ) => (string) ( $state['identifier'] ?? '' ), (array) ( $registry['states'] ?? [] ) ) ) ),
	];
	$absent = $registry['absent'] ?? null;
	if ( null !== $absent ) {
		$facet['missing'] = (string) $absent;
		$name             = wmwp_app_label( array_values( array_filter( (array) ( $registry['states'] ?? [] ), fn( $state ) => ( $state['identifier'] ?? '' ) === $absent ) )[0]['name'] ?? [], forestas_dettaglio_lingua() );
		if ( '' !== $name['text'] ) {
			$facet['labels'] = [ (string) $absent => $name['text'] ];
			if ( null !== $name['lang'] ) {
				$facet['lang_labels'] = [ (string) $absent => $name['lang'] ];
			}
		}
	}
	return $facet;
}

/**
 * Filtri dei percorsi di Forestas, nell'ordine della tavola 2 del wireframe di oc:8952. Valori, nomi e ordine li dà lo
 * shard (oc:9018): stati dal registro, tipo, attività, scale di difficoltà e aree dal config.json dell'app, come le app.
 * Il child sceglie solo quali filtri e in che ordine.
 */
function forestas_search_track_facets(): array {
	return array_values(
		array_filter(
			[
				forestas_search_status_facet( forestas_walkability_family(), 'walkability', forestas_testo( 'Percorribilità', 'Walkability' ) ),
				forestas_search_status_facet( 'validation-status', 'validation', forestas_testo( 'Validazione catastale', 'Trail registry validation' ) ),
				[
					'key'         => 'type',
					'from_config' => 'layers',
				],
				[
					'key'         => 'activity',
					'from_config' => 'activities',
				],
				[
					'from_families' => FORESTAS_DIFFICULTY_SUFFIX,
					'exclude'       => FORESTAS_DIFFICULTY_EXCLUDE,
					'order'         => FORESTAS_DIFFICULTY_ORDER,
				],
				// Slider di distanza e tempo di MAP.filters, come le app (oc:9003): limiti, passo e nomi dal config.json;
				// senza dati non si mostrano
				[ 'from_config' => 'track_distance' ],
				[ 'from_config' => 'track_duration' ],
				[ 'from_overlays' => true ],
			]
		)
	);
}

// Filtri della ricerca che si aprono e chiudono dal titolo, come i gruppi dei filtri dei post (oc:9000)
add_filter( 'wmwp_search_collapsible_facets', '__return_true' );

/** Source della ricerca: i percorsi del plugin con i nomi e i filtri di Forestas, più i contenuti del sito e gli enti. */
add_filter(
	'wp_package_search_sources',
	function ( array $sources ): array {
		if ( isset( $sources['tracks'] ) ) {
			$sources['tracks'] = array_merge(
				$sources['tracks'],
				[
					'slug'            => 'trails',
					'label'           => forestas_testo( 'Percorsi', 'Trails' ),
					'nouns'           => [
						// «percorsi» come il tab e il filtro «Tipo di percorso»: i risultati sono anche itinerari e cammini
						'one'   => forestas_testo( '%s percorso', '%s trail' ),
						'other' => forestas_testo( '%s percorsi', '%s trails' ),
					],
					'search_label'    => forestas_testo( 'Cerca un percorso', 'Search for a trail' ),
					'placeholder'     => forestas_testo( 'Nome, codice o luogo — per esempio «721» o «Punta La Marmora»', 'Name, code or place — for example “721” or “Punta La Marmora”' ),
					'facets'          => forestas_search_track_facets(),
					// Percorribilità nella riga, con il semaforo della tavola 2 (che riassume la percorribilità, decisioni.md
					// di oc:8783); niente etichetta per lo stato «non lo sappiamo» (Rubens, 18/11, oc:9000)
					'badge_field'     => forestas_plugin_ready() ? 'terms.' . forestas_walkability_family() : '',
					'badge_exclude'   => forestas_plugin_ready() ? array_values( array_filter( [ wmwp_status_families()[ forestas_walkability_family() ]['absent'] ?? null ] ) ) : [],
					// All'inizio della riga l'ultimo luogo dell'API, il comune, come la card delle app (oc:9018)
					'meta_last_field' => 'taxonomyWheres',
					// Selezione e scarico di più percorsi (tavola 2, nota 7): nome del file e riepilogo della barra
					'download_name'   => 'sardegna-sentieri-percorsi',
					'selected'        => [
						'one'   => forestas_testo( '%s percorso selezionato', '%s trail selected' ),
						'other' => forestas_testo( '%s percorsi selezionati', '%s trails selected' ),
					],
					'selection_label' => forestas_testo( 'Percorsi selezionati', 'Selected trails' ),
				]
			);
		}
		// Punti di interesse (oc:9000): la sorgente è del plugin (pois.geojson), qui nomi e indirizzo di Forestas
		if ( isset( $sources['pois'] ) ) {
			$sources['pois'] = array_merge(
				$sources['pois'],
				[
					'slug'         => 'points-of-interest',
					'label'        => forestas_testo( 'Punti di interesse', 'Points of interest' ),
					'nouns'        => [
						'one'   => forestas_testo( '%s punto di interesse', '%s point of interest' ),
						'other' => forestas_testo( '%s punti di interesse', '%s points of interest' ),
					],
					'search_label' => forestas_testo( 'Cerca un punto di interesse', 'Search for a point of interest' ),
					// Tipo sulla card, nella lingua della pagina
					'type_label'   => forestas_testo( 'Punto di interesse', 'Point of interest' ),
					// Tipi dal config.json, poi le aree degli overlay come per i percorsi (oc:9018): il plugin calcola sul server
					// in quali aree cade ogni POI
					'facets'       => [
						[
							'key'         => 'poi-type',
							'from_config' => 'poi_types',
							'type'        => 'terms',
						],
						[ 'from_overlays' => true ],
					],
				]
			);
		}
		$sources['content'] = [
			'slug'         => 'content',
			'label'        => forestas_testo( 'Contenuti del sito', 'Site content' ),
			'nouns'        => [
				'one'   => forestas_testo( '%s contenuto', '%s item' ),
				'other' => forestas_testo( '%s contenuti', '%s items' ),
			],
			'script'       => 'forestas-search',
			'layout'       => 'cards',
			'search_label' => forestas_testo( 'Cerca nel sito', 'Search the site' ),
			// I valori del filtro li dà la rotta REST (by_type): qui solo la chiave nell'indirizzo e il nome
			'facets'       => [
				[
					'key'   => 'content',
					'label' => forestas_testo( 'Tipo di contenuto', 'Content type' ),
				],
			],
		];
		// Enti dello shard (oc:8990): card con immagine, nome e tipo, dalla rotta forestas/v1/entes-search. Filtri del tipo
		// e del ruolo sui sentieri (oc:9000): i valori li dà la rotta, qui solo la chiave nell'indirizzo e il nome
		$sources['entes'] = [
			'slug'         => 'organisations',
			'label'        => forestas_testo( 'Enti', 'Organisations' ),
			'nouns'        => [
				'one'   => forestas_testo( '%s ente', '%s organisation' ),
				'other' => forestas_testo( '%s enti', '%s organisations' ),
			],
			'script'       => 'forestas-search',
			'layout'       => 'cards',
			'search_label' => forestas_testo( 'Cerca un ente', 'Search for an organisation' ),
			'facets'       => [
				[
					'key'   => 'entity-type',
					'label' => forestas_testo( 'Tipo di ente', 'Type of organisation' ),
				],
				[
					'key'   => 'role',
					'label' => forestas_testo( 'Ruolo sui sentieri', 'Role on trails' ),
				],
			],
		];
		return $sources;
	}
);

/** Campo di ricerca di WordPress (?s=): la pagina di ricerca senza il plugin, come oggi. */
function forestas_search_fallback_form(): string {
	return sprintf(
		'<form class="forestas-search-fallback" role="search" method="get" action="%1$s">'
		. '<label for="forestas-search-s">%2$s</label> <input type="search" id="forestas-search-s" name="s" value="%3$s"> '
		. '<button type="submit">%4$s</button></form>',
		esc_url( home_url( '/' ) ),
		esc_html( forestas_testo( 'Cerca nel sito', 'Search the site' ) ),
		esc_attr( get_search_query() ),
		esc_html( forestas_testo( 'Cerca', 'Search' ) )
	);
}

/*
 * [forestas_search]: il template della pagina /cerca-nel-sito. Con wp-package attivo la ricerca del plugin
 * con le source del child, senza il campo di ricerca di WordPress: la pagina non mostra mai uno shortcode come
 * testo. Il titolo è visibile, come su Drupal, nell'intestazione delle altre pagine del sito (titolo centrato con il
 * filo sopra nel colore della sezione) e seguito dalla breadcrumb «Home › Cerca nel sito» (oc:9000).
 */
add_shortcode(
	'forestas_search',
	function (): string {
		$id    = (int) get_the_ID();
		$title = sprintf(
			'<div class="forestas-intestazione forestas-sezione-%1$s"><h1 class="forestas-intestazione-titolo">%2$s</h1></div>',
			esc_attr( forestas_sezione_pagina( $id ) ),
			esc_html( get_the_title( $id ) )
		) . forestas_percorso( $id );
		if ( ! shortcode_exists( 'wmwp_search' ) ) {
			return $title . forestas_search_fallback_form();
		}
		wp_register_script( 'forestas-search', get_stylesheet_directory_uri() . '/assets/js/search.js', [ 'wmwp-search' ], forestas_versione(), [ 'in_footer' => true ] );
		wp_add_inline_script(
			'forestas-search',
			'window.forestasSearch = ' . wp_json_encode(
				[
					'endpoint'      => rest_url( 'forestas/v1/search' ),
					'entesEndpoint' => rest_url( 'forestas/v1/entes-search' ),
					'types'         => forestas_search_type_labels(),
				]
			) . ';',
			'before'
		);
		return $title . do_shortcode( '[wmwp_search]' );
	}
);

/** Indirizzo della pagina di ricerca nella lingua corrente, '' se la pagina non c'è. */
function forestas_search_url(): string {
	$ids = get_posts(
		[
			'post_type'        => 'page',
			'post_status'      => 'publish',
			'posts_per_page'   => 1,
			'fields'           => 'ids',
			'meta_key'         => '_wpf_pagina',
			'meta_value'       => FORESTAS_SEARCH_PAGE_KEY,
			'suppress_filters' => false,
		]
	);
	return $ids ? (string) get_permalink( $ids[0] ) : '';
}

/**
 * Indirizzo nuovo della ricerca per un suo vecchio indirizzo (`cerca-nel-sito` nella pagina inglese), con la query così
 * com'è: poi il redirect dei vecchi link della ricerca di Drupal ne traduce i parametri. Null per ogni altro indirizzo o
 * se l'indirizzo nuovo è lo stesso (la pagina italiana).
 *
 * @param string $path  Percorso della richiesta, senza prefisso della lingua (`$wp->request`).
 * @param string $query Query della richiesta, già codificata.
 * @param string $url   Indirizzo della ricerca nella lingua della richiesta (forestas_search_url()).
 */
function forestas_search_old_slug_target( string $path, string $query, string $url ): ?string {
	$path = trim( strtolower( $path ), '/' );
	if ( '' === $url || ! in_array( $path, FORESTAS_SEARCH_OLD_SLUGS, true ) ) {
		return null;
	}
	// La ricerca di questa lingua ha ancora lo slug vecchio (la pagina italiana): niente redirect
	$current = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	if ( $current === $path || str_ends_with( $current, '/' . $path ) ) {
		return null;
	}
	return '' !== $query ? $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $query : $url;
}

// Vecchio indirizzo inglese della ricerca → indirizzo nuovo, con i parametri (301). Prima del redirect delle pagine
// (inc/pagine-statiche.php, priorità 9), che perderebbe la query
add_action(
	'template_redirect',
	function () {
		global $wp;
		if ( ! is_404() ) {
			return;
		}
		$target = forestas_search_old_slug_target( (string) $wp->request, (string) ( $_SERVER['QUERY_STRING'] ?? '' ), forestas_search_url() ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( null !== $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	},
	8
);

// Cache della corrispondenza route-type → layer per i vecchi link di Drupal (forestas_search_layer_for_route_type())
const FORESTAS_SEARCH_LAYERS_TRANSIENT = 'forestas_search_route_type_layers';
// Shard che non ha risposto: per 10 minuti un vecchio link non lo richiama, così con lo shard giù il visitatore non
// aspetta il timeout a ogni visita (review interna di oc:9018)
const FORESTAS_SEARCH_LAYERS_FAILED = 'forestas_search_route_type_layers_failed';

/** Id dei layer di «Tipo percorso» nel config.json (`MAP.filters.layers`, oc:9018): fra questi si cerca il layer di un route-type. */
function forestas_search_type_layers(): array {
	$config = function_exists( 'wmwp_app_config' ) ? wmwp_app_config() : null;
	return array_map( 'intval', array_column( (array) ( $config['MAP']['filters']['layers']['options'] ?? [] ), 'id' ) );
}

/**
 * Layer di `MAP.filters.layers` (il filtro «Tipo di percorso», oc:9018) che ha più percorsi di un route-type nei
 * risultati di Elastic: traduce il filtro per tipo dei vecchi link di Drupal e dà il colore delle linee dei percorsi
 * vicini sulla mappa del POI. La corrispondenza resta in cache due ore e la rinnova il cron del config.json; con
 * `$fetch` falso (in una pagina) si legge solo la cache; con `$refresh` (il cron) si riscarica anche se la cache c'è, e
 * la si sostituisce solo se lo shard risponde. Null se nessun percorso di quel tipo sta in un layer del filtro, se lo
 * shard non risponde o se la cache manca e `$fetch` è falso.
 * Con il plugin 0.7.0 e la copia delle tracce la corrispondenza viene dalla copia (wmwp_tracks_layer_for_term(), oc:9003),
 * che conta tutti i route-type di una traccia; il download di Elastic, senza copia, conta solo il primo. Oggi una traccia
 * ha un route-type solo, quindi il risultato è lo stesso.
 */
function forestas_search_layer_for_route_type( string $route_type, bool $fetch = true, bool $refresh = false ): ?int {
	// Con il plugin 0.7.0 la corrispondenza viene dalla copia delle tracce (oc:9003): niente download a parte, e con lo
	// shard giù o a metà di un import resta l'ultima copia buona
	if ( function_exists( 'wmwp_tracks_layer_for_term' ) && null !== wmwp_tracks_copy() ) {
		return wmwp_tracks_layer_for_term( wmwp_track_kind_taxonomy(), $route_type, forestas_search_type_layers() );
	}
	$map = $refresh ? null : get_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT );
	if ( ! is_array( $map ) ) {
		if ( ! $fetch || ! forestas_plugin_ready() || '' === wmwp_elastic_url() || ( ! $refresh && get_transient( FORESTAS_SEARCH_LAYERS_FAILED ) ) ) {
			return null;
		}
		$allowed = forestas_search_type_layers();
		$data    = wmwp_detail_get_json( add_query_arg( 'app', wmwp_app_param(), wmwp_elastic_url() ), WMWP_DETAIL_LIST_TIMEOUT );
		if ( null === $data ) {
			set_transient( FORESTAS_SEARCH_LAYERS_FAILED, 1, 10 * MINUTE_IN_SECONDS );
			// Il rinnovo fallito lascia la corrispondenza di prima, se c'è
			$prima = get_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT );
			return is_array( $prima ) && isset( $prima[ $route_type ] ) ? (int) $prima[ $route_type ] : null;
		}
		$hits   = $data['hits'] ?? [];
		$hits   = isset( $hits['hits'] ) && is_array( $hits['hits'] ) ? $hits['hits'] : $hits;
		$family = wmwp_track_kind_taxonomy();
		$counts = [];
		foreach ( (array) $hits as $hit ) {
			$hit  = isset( $hit['_source'] ) && is_array( $hit['_source'] ) ? $hit['_source'] : (array) $hit;
			$type = (string) ( $hit['terms'][ $family ][0]['identifier'] ?? '' );
			foreach ( (array) ( $hit['layers'] ?? [] ) as $layer ) {
				if ( '' !== $type && in_array( (int) $layer, $allowed, true ) ) {
					$counts[ $type ][ (int) $layer ] = ( $counts[ $type ][ (int) $layer ] ?? 0 ) + 1;
				}
			}
		}
		$map = [];
		foreach ( $counts as $type => $layers ) {
			arsort( $layers );
			$map[ $type ] = (int) array_key_first( $layers );
		}
		set_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT, $map, 2 * HOUR_IN_SECONDS );
		delete_transient( FORESTAS_SEARCH_LAYERS_FAILED );
	}
	return isset( $map[ $route_type ] ) ? (int) $map[ $route_type ] : null;
}

/**
 * Rinnovo della corrispondenza route-type → layer, con il giro orario del config.json, fuori dalle pagine (oc:9018): si
 * riscarica e si sostituisce solo se lo shard risponde, altrimenti resta quella di prima.
 */
function forestas_search_layers_refresh(): void {
	forestas_search_layer_for_route_type( '', true, true );
}
add_action( 'wmwp_app_config_refresh', 'forestas_search_layers_refresh', 30 );

/**
 * Parametri della ricerca nuova da un vecchio link della ricerca di Drupal: testo (`search_api_fulltext`, anche senza
 * filtri) e tipi (`f[N]=cerca_contenuto_nel_sito:<tipo>`). La tabella «tipo di Drupal → tab, filtro, valore» resta nel
 * codice: traduce indirizzi vecchi, che non cambiano più, non dati dello shard. Il tipo di percorso diventa il layer
 * del filtro «Tipo di percorso» (oc:9018). Null se il link non ha né testo né tipi conosciuti.
 *
 * @param array<string, mixed> $query Parametri dell'indirizzo.
 * @return array<string, string>|null
 */
function forestas_search_from_drupal( array $query ): ?array {
	$text    = isset( $query['search_api_fulltext'] ) && is_scalar( $query['search_api_fulltext'] ) ? trim( (string) $query['search_api_fulltext'] ) : '';
	$filters = ! empty( $query['f'] ) && is_array( $query['f'] ) ? $query['f'] : [];
	// tipo di Drupal → [ tab, filtro, valore ]
	$map    = [
		'sentiero'   => [ 'trails', 'type', 'route-type-trail' ],
		'itinerario' => [ 'trails', 'type', 'route-type-itinerary' ],
		'news'       => [ 'content', 'content', 'news' ],
		'evento'     => [ 'content', 'content', 'event' ],
		'page'       => [ 'content', 'content', 'page' ],
		// I punti di interesse hanno il loro tab (oc:9000), senza filtro
		'poi'        => [ 'points-of-interest', null, null ],
	];
	$out    = [];
	$values = [];
	foreach ( $filters as $filter ) {
		if ( ! is_scalar( $filter ) || ! preg_match( '/^cerca_contenuto_nel_sito:(\w+)$/', (string) $filter, $m ) || ! isset( $map[ $m[1] ] ) ) {
			continue;
		}
		[ $tab, $key, $value ] = $map[ $m[1] ];
		if ( ! isset( $out['tab'] ) ) {
			$out['tab'] = $tab;
		}
		// Il tab è quello del primo tipo: i tipi di un altro tab non hanno un filtro lì
		if ( $tab === $out['tab'] && null !== $key ) {
			$values[ $key ][] = 'type' === $key ? (string) forestas_search_layer_for_route_type( $value ) : $value;
		}
	}
	foreach ( $values as $key => $list ) {
		$list = array_values( array_unique( array_filter( $list, 'strlen' ) ) );
		if ( $list ) {
			$out[ $key ] = implode( ',', $list );
		}
	}
	if ( '' !== $text ) {
		$out = [ 'q' => $text ] + $out;
	}
	return $out ? $out : null;
}

/*
 * Con il plugin attivo: la ricerca di WordPress (?s=, anche dal campo «Cerca nel sito» dell'header) va alla pagina
 * di ricerca; sulla pagina di ricerca un vecchio link di Drupal con `f[N]` va ai parametri nuovi. Redirect 302:
 * quei link sono nofollow, e un 301 resterebbe salvato nei browser.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! shortcode_exists( 'wmwp_search' ) ) {
			return;
		}
		// nojs: il form del <noscript> del plugin (senza JavaScript la pagina di ricerca non mostra risultati)
		if ( is_search() && empty( $_GET['nojs'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$url = forestas_search_url();
			if ( '' !== $url ) {
				wp_safe_redirect( add_query_arg( 'q', rawurlencode( get_search_query( false ) ), $url ), 302 );
				exit;
			}
			return;
		}
		// Anche i link con il solo testo (`?search_api_fulltext=…`, senza `f`), oc:9018
		if ( ( isset( $_GET['f'] ) || isset( $_GET['search_api_fulltext'] ) ) && is_page() && FORESTAS_SEARCH_PAGE_KEY === get_post_meta( get_queried_object_id(), '_wpf_pagina', true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$target = forestas_search_from_drupal( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( null !== $target ) {
				wp_safe_redirect( add_query_arg( array_map( 'rawurlencode', $target ), get_permalink( get_queried_object_id() ) ), 302 );
				exit;
			}
		}
	}
);

/**
 * Parametri della ricerca per il canonical, in un ordine fisso e con i valori dei filtri ordinati: la stessa
 * ricerca ha un solo indirizzo anche se i filtri sono stati scelti in un altro ordine, e un parametro estraneo
 * non crea un indirizzo diverso. Valori già codificati per l'indirizzo.
 */
function forestas_search_canonical_args( array $query ): array {
	// Parametri del plugin (oc:9018: in inglese, uguali in tutte le lingue) e chiavi dei filtri di tutte le source; le
	// aree degli overlay stanno nel loro parametro condiviso (`layer`)
	$params = wmwp_search_params();
	$single = [ $params['q'], $params['tab'], $params['page'] ];
	$facets = [];
	foreach ( wmwp_search_sources() as $source ) {
		foreach ( (array) ( $source['facets'] ?? [] ) as $facet ) {
			$key = is_array( $facet ) ? (string) ( $facet['param'] ?? $facet['key'] ?? '' ) : '';
			if ( '' !== $key && ! in_array( $key, $facets, true ) ) {
				$facets[] = $key;
			}
		}
	}
	$keys = array_merge( [ $params['q'], $params['tab'] ], $facets, [ $params['page'] ] );
	$args = [];
	foreach ( $keys as $key ) {
		if ( ! isset( $query[ $key ] ) || ! is_string( $query[ $key ] ) || '' === trim( $query[ $key ] ) ) {
			continue;
		}
		$value = trim( $query[ $key ] );
		if ( $params['page'] === $key && (int) $value <= 1 ) {
			continue;
		}
		if ( ! in_array( $key, $single, true ) ) {
			$values = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $value ) ) ) ) );
			sort( $values, SORT_NATURAL );
			$value = implode( ',', $values );
		}
		$args[ $key ] = rawurlencode( $value );
	}
	return $args;
}

/*
 * Canonical della pagina di ricerca con i parametri della ricerca: ogni ricerca ha un indirizzo che si può
 * indicizzare e stampare su un QR (resoconto del 12/08, § 4.1). Senza, WordPress dichiarerebbe canonica la
 * pagina senza parametri per ogni ricerca.
 */
add_filter(
	'get_canonical_url',
	function ( $url, $post ) {
		if ( ! $post || FORESTAS_SEARCH_PAGE_KEY !== get_post_meta( $post->ID, '_wpf_pagina', true ) ) {
			return $url;
		}
		return add_query_arg( forestas_search_canonical_args( wp_unslash( $_GET ) ), $url ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	},
	// Dopo WPML, che sullo stesso filtro riscrive il canonical nella lingua della pagina senza i parametri
	100,
	2
);

// UpSolution Core toglie rel_canonical di WordPress e stampa il suo canonical (us-core, functions/meta-tags.php,
// filtro us_rel_canonical) con l'indirizzo senza parametri: sulla pagina di ricerca vale quello qui sopra
add_filter(
	'us_rel_canonical',
	function ( $html ) {
		$id = get_queried_object_id();
		if ( ! is_page() || FORESTAS_SEARCH_PAGE_KEY !== get_post_meta( $id, '_wpf_pagina', true ) ) {
			return $html;
		}
		$url = wp_get_canonical_url( $id );
		return $url ? '<link rel="canonical" href="' . esc_url( $url ) . '" />' . "\n" : $html;
	}
);
