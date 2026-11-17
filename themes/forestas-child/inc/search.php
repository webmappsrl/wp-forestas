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

// Tipi cercati: valore nell'indirizzo (parametro «contenuto») => post type
const FORESTAS_SEARCH_TYPES = [
	'news'      => 'post',
	'evento'    => 'evento',
	'da-sapere' => 'da_sapere',
	'pagina'    => 'page',
];
const FORESTAS_SEARCH_PER_PAGE = 12;
// Pagina di ricerca in config/pagine.json (meta _wpf_pagina): la trovano i redirect da `?s=` e dai link di Drupal e
// lo shortcode; fuori dai risultati la tiene la regola delle pagine di struttura in forestas_search_rest()
const FORESTAS_SEARCH_PAGE_KEY = 'cerca-nel-sito';
// Province fra i luoghi dell'API (taxonomyWheres), comprese quelle storiche: sul dev il 09/11
const FORESTAS_SEARCH_PROVINCES = [ 'Cagliari', 'Gallura Nord-Est Sardegna', 'Medio Campidano', 'Nuoro', 'Ogliastra', 'Oristano', 'Sassari', 'Sulcis Iglesiente' ];

/** Nome di ogni tipo nella lingua corrente. */
function forestas_search_type_labels(): array {
	return [
		'news'      => forestas_testo( 'News', 'News' ),
		'evento'    => forestas_testo( 'Evento', 'Event' ),
		'da-sapere' => forestas_testo( 'Da sapere', 'Good to know' ),
		'pagina'    => forestas_testo( 'Pagina', 'Page' ),
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
				'args'                => forestas_search_rest_args(),
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
		$entes = forestas_entes_list( $lang );
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
		$entes = [];
		foreach ( $found as $ente ) {
			// Senza slug un ente non ha una pagina
			if ( is_array( $ente ) && '' !== forestas_entes_slug( $ente ) ) {
				$entes[] = forestas_entes_list_item( $ente, $lang, $known );
			}
		}
	}

	$per_page = (int) $request['per_page'];
	$pages    = max( 1, (int) ceil( count( $entes ) / $per_page ) );
	$page     = min( (int) $request['page'], $pages );
	return new WP_REST_Response(
		[
			'total' => count( $entes ),
			'items' => array_map( 'forestas_entes_search_card', array_slice( $entes, ( $page - 1 ) * $per_page, $per_page ) ),
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

/*
 * Parametri dell'indirizzo come su Drupal: il testo in search_api_fulltext, così i link alla ricerca di oggi
 * continuano a funzionare; tab, pagina e vista con nomi italiani (oc:8952): «?tipo=percorsi&vista=mappa».
 */
add_filter(
	'wp_package_search_params',
	function () {
		return [
			'q'    => 'search_api_fulltext',
			'tab'  => 'tipo',
			'page' => 'pagina',
			'view' => 'vista',
			'map'  => 'mappa',
		];
	}
);

/** Nomi dei tipi di percorso (terms.route-type), per il filtro e per l'etichetta del tipo nelle righe e nelle card. */
function forestas_search_route_type_labels(): array {
	return [
		'route-type-trail'     => forestas_testo( 'Sentiero', 'Trail' ),
		'route-type-itinerary' => forestas_testo( 'Itinerario', 'Itinerary' ),
		'route-type-walk'      => forestas_testo( 'Cammino', 'Walk' ),
	];
}

/**
 * Nomi dei termini dello shard mostrati nelle righe e nelle card (`term_labels` della source del plugin): tipo di
 * percorso e stato di validazione. L'API li dà solo in italiano (10/11), quindi sulla pagina inglese senza questi
 * nomi tipo e stato restavano in italiano. In italiano sono i nomi dell'API; in inglese provvisori, scritti da noi.
 */
function forestas_search_term_labels(): array {
	return forestas_search_route_type_labels() + [
		'validation-status-validated'        => forestas_testo( 'Validato', 'Validated' ),
		'validation-status-certified'        => forestas_testo( 'Certificato', 'Certified' ),
		'validation-status-under-review'     => forestas_testo( 'In revisione-validazione', 'Under review' ),
		'validation-status-pre-registration' => forestas_testo( 'In pre-accatastamento o manutenzione', 'Pre-registration or maintenance' ),
		'validation-status-walkable'         => forestas_testo( 'Percorribile', 'Walkable' ),
		'validation-status-unverified'       => forestas_testo( 'Non verificato', 'Not verified' ),
		'validation-status-not-walkable'     => forestas_testo( 'Non percorribile', 'Not walkable' ),
	];
}

/**
 * Filtri dei percorsi di Forestas, nell'ordine della tavola 2 del wireframe di oc:8952.
 */
function forestas_search_track_facets(): array {
	return [
		[
			'key'    => 'percorribilita',
			'label'  => forestas_testo( 'Percorribilità', 'Walkability' ),
			'type'   => 'terms',
			// Dallo stato di validazione del catasto (scrum dell'11/11, Giuseppe). Regola di Forestas («Osservazioni
			// finali a CANVAS CLAUDE fase 2»): il valore unico di Drupal si copia tal quale anche sulla
			// percorribilità, verde da 4 in su; i livelli sono quelli di ConfigureTaxonomyTermsCommand dello shard
			// (4 Percorribile, 5 Validato, 6 Certificato). Voci del semaforo della home (oc:8783, decisioni.md):
			// verde 4–6, giallo 2–3, rosso 1; «Non verificato» (0) fuori. Se arriva la scala di percorribilità
			// separata (oc:8783, oc:8932) cambiano solo campo e valori
			'field'  => 'terms.validation-status',
			'groups' => [
				[
					'id'     => 'percorribili',
					'label'  => forestas_testo( 'Percorribili', 'Walkable' ),
					'values' => [ 'validation-status-walkable', 'validation-status-validated', 'validation-status-certified' ],
				],
				[
					'id'     => 'parziali',
					'label'  => forestas_testo( 'In revisione o parzialmente percorribili', 'Under review or partly walkable' ),
					'values' => [ 'validation-status-under-review', 'validation-status-pre-registration' ],
				],
				[
					'id'     => 'non-percorribili',
					'label'  => forestas_testo( 'Non percorribili', 'Not walkable' ),
					'values' => [ 'validation-status-not-walkable' ],
				],
			],
			'order'  => [ 'percorribili', 'parziali', 'non-percorribili' ],
		],
		[
			'key'    => 'validazione',
			'label'  => forestas_testo( 'Validazione catastale', 'Trail registry validation' ),
			'type'   => 'terms',
			'field'  => 'terms.validation-status',
			// Fasce della tavola 2 del wireframe e della descrizione di oc:8952. «Non percorribile» non vi compare:
			// sta in Base, supposto livello 1 (Rubens, 09/11: «procedi»)
			'groups' => [
				[
					'id'     => 'avanzato',
					'label'  => forestas_testo( 'Avanzato · 5–6', 'Advanced · 5–6' ),
					'values' => [ 'validation-status-validated', 'validation-status-certified' ],
				],
				[
					'id'     => 'intermedio',
					'label'  => forestas_testo( 'Intermedio · 2–4', 'Intermediate · 2–4' ),
					'values' => [ 'validation-status-under-review', 'validation-status-pre-registration', 'validation-status-walkable' ],
				],
				[
					'id'     => 'base',
					'label'  => forestas_testo( 'Base · 0–1', 'Basic · 0–1' ),
					'values' => [ 'validation-status-unverified', 'validation-status-not-walkable' ],
				],
			],
			'order'  => [ 'avanzato', 'intermedio', 'base' ],
		],
		[
			'key'    => 'percorso',
			'label'  => forestas_testo( 'Tipo di percorso', 'Route type' ),
			'type'   => 'terms',
			'field'  => 'terms.route-type',
			'labels' => forestas_search_route_type_labels(),
			'order'  => [ 'route-type-trail', 'route-type-itinerary', 'route-type-walk' ],
		],
		[
			'key'            => 'attivita',
			'label'          => forestas_testo( 'Attività', 'Activity' ),
			'type'           => 'list',
			'field'          => 'taxonomyActivities',
			// Tipi di percorso (sentiero, itinerario) e cammini stanno nello stesso campo: non sono attività
			'exclude_prefix' => 'sardegnasentieri:',
			// Nomi del sito per gli identifier di Geohub (wireframe, tav. 2): provvisori finché non arrivano
			// con oc:8932
			'labels'         => [
				'hiking'         => forestas_testo( 'Camminate giornaliere', 'Day hikes' ),
				'trekking'       => forestas_testo( 'Sentieri lunghi', 'Long-distance trails' ),
				'mtb'            => forestas_testo( 'Mountain Bike', 'Mountain biking' ),
				'horse'          => forestas_testo( 'Turismo equestre', 'Horse riding' ),
				'nordic-walking' => forestas_testo( 'Passeggiate e Nordic Walk', 'Walks and Nordic walking' ),
			],
			'order'          => [ 'hiking', 'trekking', 'mtb', 'horse', 'nordic-walking' ],
		],
		[
			'key'    => 'difficolta',
			'label'  => forestas_testo( 'Difficoltà', 'Difficulty' ),
			'type'   => 'terms',
			'field'  => 'terms.hiking-difficulty',
			'labels' => [
				'sardegnasentieri:tid:4066' => forestas_testo( 'T — turistico', 'T — tourist' ),
				'sardegnasentieri:tid:4067' => forestas_testo( 'E — escursionistico', 'E — hiking' ),
				'sardegnasentieri:tid:4068' => forestas_testo( 'EE — per esperti', 'EE — for experts' ),
				'sardegnasentieri:tid:4069' => forestas_testo( 'EEA — attrezzato', 'EEA — equipped' ),
			],
			'order'  => [ 'sardegnasentieri:tid:4066', 'sardegnasentieri:tid:4067', 'sardegnasentieri:tid:4068', 'sardegnasentieri:tid:4069' ],
		],
		// «Dove» dell'API (taxonomyWheres) mescola regione, macro aree, settori CAI, province e comuni: si divide
		// riconoscendoli dal nome, ripiego finché l'API non dà i luoghi per tipo (Rubens, 09/11). Le province hanno
		// lo stesso nome del capoluogo: Cagliari, Nuoro, Oristano e Sassari stanno solo fra le province
		[
			'key'             => 'area',
			'label'           => forestas_testo( 'Area', 'Area' ),
			'type'            => 'list',
			'field'           => 'taxonomyWheres',
			'include_pattern' => '^Area ',
		],
		[
			'key'     => 'provincia',
			'label'   => forestas_testo( 'Provincia', 'Province' ),
			'type'    => 'list',
			'field'   => 'taxonomyWheres',
			'include' => FORESTAS_SEARCH_PROVINCES,
		],
		[
			'key'             => 'comune',
			'label'           => forestas_testo( 'Comune', 'Municipality' ),
			'type'            => 'list',
			'field'           => 'taxonomyWheres',
			// La regione vale per tutti i percorsi; «Z» + tre lettere + numero sono i settori CAI (es. ZNUB4)
			'exclude'         => array_merge( [ 'Sardegna' ], FORESTAS_SEARCH_PROVINCES ),
			'exclude_pattern' => '^(Area |Z[A-Z]{3}[0-9]+$)',
		],
	];
}

/** Source della ricerca: i percorsi del plugin con i nomi e i filtri di Forestas, più i contenuti del sito e gli enti. */
add_filter(
	'wp_package_search_sources',
	function ( array $sources ): array {
		if ( isset( $sources['tracks'] ) ) {
			$sources['tracks'] = array_merge(
				$sources['tracks'],
				[
					'slug'            => 'percorsi',
					'label'           => forestas_testo( 'Percorsi', 'Trails' ),
					'nouns'           => [
						// «percorsi» come il tab e il filtro «Tipo di percorso»: i risultati sono anche itinerari e cammini
						'one'   => forestas_testo( '%s percorso', '%s trail' ),
						'other' => forestas_testo( '%s percorsi', '%s trails' ),
					],
					'search_label'    => forestas_testo( 'Cerca un percorso', 'Search for a trail' ),
					'placeholder'     => forestas_testo( 'Nome, codice o luogo — per esempio «721» o «Punta La Marmora»', 'Name, code or place — for example “721” or “Punta La Marmora”' ),
					'facets'          => forestas_search_track_facets(),
					// Stato di validazione del catasto nella riga, come nella tavola 2 (dal 09/11 nell'API del dev)
					'badge_field'     => 'terms.validation-status',
					// Nomi di tipo e stato nella lingua della pagina (l'API li dà solo in italiano)
					'term_labels'     => forestas_search_term_labels(),
					// I comuni all'inizio della riga, come nella tavola 2 («Desulo, Villagrande Strisaili · 5,3 km …»)
					'meta_facet'      => 'comune',
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
		$sources['content'] = [
			'slug'         => 'contenuti',
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
					'key'   => 'contenuto',
					'label' => forestas_testo( 'Tipo di contenuto', 'Content type' ),
				],
			],
		];
		// Enti dello shard (oc:8990): card con immagine, nome e tipo, dalla rotta forestas/v1/entes-search; nessun filtro
		$sources['entes'] = [
			'slug'         => 'enti',
			'label'        => forestas_testo( 'Enti', 'Organisations' ),
			'nouns'        => [
				'one'   => forestas_testo( '%s ente', '%s organisation' ),
				'other' => forestas_testo( '%s enti', '%s organisations' ),
			],
			'script'       => 'forestas-search',
			'layout'       => 'cards',
			'search_label' => forestas_testo( 'Cerca un ente', 'Search for an organisation' ),
			'facets'       => [],
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
 * testo. Il titolo è visibile, come su Drupal.
 */
add_shortcode(
	'forestas_search',
	function (): string {
		$title = sprintf( '<h1 class="forestas-search__title">%s</h1>', esc_html( get_the_title() ) );
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
 * Parametri nuovi per un vecchio link di Drupal alla ricerca filtrata per tipo
 * (`f[N]=cerca_contenuto_nel_sito:<tipo>`, link `rel="nofollow"` dei filtri di /cerca-nel-sito). null senza `f`.
 * `sentiero` e `itinerario` portano al tab «Percorsi» con il filtro «Tipo di percorso», `news`, `evento` e `page` al
 * tab «Contenuti del sito» con il tipo; più tipi dello stesso tab vanno nello stesso filtro (OR), separati da virgole.
 * `poi` e i tipi sconosciuti portano al tab «Tutti».
 */
function forestas_search_from_drupal( array $query ): ?array {
	if ( empty( $query['f'] ) || ! is_array( $query['f'] ) ) {
		return null;
	}
	// tipo di Drupal → [ tab, filtro, valore ]
	$map    = [
		'sentiero'   => [ 'percorsi', 'percorso', 'route-type-trail' ],
		'itinerario' => [ 'percorsi', 'percorso', 'route-type-itinerary' ],
		'news'       => [ 'contenuti', 'contenuto', 'news' ],
		'evento'     => [ 'contenuti', 'contenuto', 'evento' ],
		'page'       => [ 'contenuti', 'contenuto', 'pagina' ],
	];
	$out    = [];
	$values = [];
	foreach ( $query['f'] as $filter ) {
		if ( ! preg_match( '/^cerca_contenuto_nel_sito:(\w+)$/', (string) $filter, $m ) || ! isset( $map[ $m[1] ] ) ) {
			continue;
		}
		[ $tab, $key, $value ] = $map[ $m[1] ];
		if ( ! isset( $out['tipo'] ) ) {
			$out['tipo'] = $tab;
		}
		// Il tab è quello del primo tipo: i tipi di un altro tab non hanno un filtro lì
		if ( $tab === $out['tipo'] ) {
			$values[ $key ][] = $value;
		}
	}
	foreach ( $values as $key => $list ) {
		$out[ $key ] = implode( ',', array_unique( $list ) );
	}
	if ( isset( $query['search_api_fulltext'] ) && '' !== trim( (string) $query['search_api_fulltext'] ) ) {
		$out = [ 'search_api_fulltext' => trim( (string) $query['search_api_fulltext'] ) ] + $out;
	}
	return $out;
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
				wp_safe_redirect( add_query_arg( 'search_api_fulltext', rawurlencode( get_search_query( false ) ), $url ), 302 );
				exit;
			}
			return;
		}
		if ( isset( $_GET['f'] ) && is_page() && FORESTAS_SEARCH_PAGE_KEY === get_post_meta( get_queried_object_id(), '_wpf_pagina', true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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
	$single  = [ 'search_api_fulltext', 'tipo', 'pagina' ];
	$keys    = array_merge( [ 'search_api_fulltext', 'tipo' ], array_column( forestas_search_track_facets(), 'key' ), [ 'contenuto', 'pagina' ] );
	$args    = [];
	foreach ( $keys as $key ) {
		if ( ! isset( $query[ $key ] ) || ! is_string( $query[ $key ] ) || '' === trim( $query[ $key ] ) ) {
			continue;
		}
		$value = trim( $query[ $key ] );
		if ( 'pagina' === $key && (int) $value <= 1 ) {
			continue;
		}
		if ( ! in_array( $key, $single, true ) ) {
			$values = array_values( array_unique( array_filter( array_map( 'trim', explode( ',', $value ) ) ) ) );
			sort( $values, SORT_STRING );
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
