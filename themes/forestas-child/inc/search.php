<?php
/**
 * Ricerca unica del sito (oc:8952), parte del child. La pagina e i percorsi li fa il plugin wp-package;
 * qui la source «Contenuti del sito» (news, eventi, «da sapere», pagine) con la sua rotta REST.
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
				'args'                => [
					'q'        => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'type'     => [
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
				],
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
		do_action( 'wpml_switch_language', $lang );
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

/** Source della ricerca: i percorsi del plugin con i nomi e i filtri di Forestas, più i contenuti del sito. */
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
					'endpoint' => rest_url( 'forestas/v1/search' ),
					'types'    => forestas_search_type_labels(),
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
