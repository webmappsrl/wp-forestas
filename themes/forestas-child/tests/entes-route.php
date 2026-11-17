<?php
/**
 * Test (oc:8990): pagine dinamiche degli enti (inc/entes.php), `/enti/`, `/enti/page/<n>/` e `/enti/<slug>/`.
 * Regole di rewrite nell'array di `rewrite_rules_array`, con `enti/page/2` sulla paginazione e non sul dettaglio; query
 * var pubbliche; risoluzione della rotta da una copia di prova di 20 enti con pagina (200, 404 oltre l'ultima pagina,
 * slug che c'è e che non c'è, solo gli enti con uno slug nel conto delle pagine, prima richiesta allo shard senza copia
 * (shard finto), nessuna copia con l'attesa in corso → 503 senza chiamare lo shard); per la richiesta corrente (query var
 * della query principale): template, titolo del documento, `hreflang` e selettore di lingua di WPML verso la stessa
 * pagina nell'altra lingua, anche quando il selettore dell'header lo prende dalla cache di WPML. Pagina degli enti solo
 * per la query principale arrivata da una regola degli enti (`$wp->matched_rule`) con la vista `list` o `detail`: senza
 * query var, su una query non principale, con un valore array o da un'altra regola (`/news/?forestas_entes=list`) i
 * filtri della query non fanno nulla e la pagina resta quella di WordPress; il filtro `request` toglie le query var degli
 * enti dalle richieste che non vengono dalle loro regole e su quelle tiene solo i valori della regola, senza le query var
 * pubbliche di WordPress della query string (`?p=1`, `?page_id=2`, `?s=…`).
 * Filtro «Argomento» dell'elenco (`?argomento=<tipo>`): pagine contate sull'elenco filtrato (404 oltre l'ultima), tipo
 * sconosciuto senza enti, valore non testuale che non filtra, card filtrate, voci della faccetta (tipi con il numero
 * di enti, ordine di Drupal, voce scelta, tipo sconosciuto), indirizzi, selettore di lingua e `hreflang` con il filtro.
 * Altre richieste HTTP: le blocca e le conta. Rimette copia, opzioni degli slug e dello stato, transient di attesa, lingua
 * di WPML, query var, regola della richiesta, parametro `argomento` della richiesta, variabile d'ambiente del token e file
 * di log com'erano.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/entes-route.php
 * Stampa «PASS» o «FAIL: …» ed esce con 0 o 1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$failures = [];
$check    = function ( bool $ok, string $what ) use ( &$failures ) {
	if ( ! $ok ) {
		$failures[] = $what;
	}
};

foreach ( [ 'forestas_entes_rewrite_rules', 'forestas_entes_route', 'forestas_entes_page', 'forestas_entes_template', 'forestas_entes_list_url', 'forestas_entes_type_facet' ] as $funzione ) {
	if ( ! function_exists( $funzione ) ) {
		echo "FAIL: {$funzione} non esiste\n";
		exit( 1 );
	}
}

$opzione   = 'forestas_entes_copy';
$slug_op   = 'forestas_entes_slugs';
$stato     = 'forestas_entes_state';
$attesa    = 'forestas_entes_retry';
$query_var = [ 'forestas_entes', 'forestas_ente_slug', 'paged' ];

// Stato da rimettere alla fine
global $wp_query;
$prima = [
	'copia'    => get_option( $opzione, null ),
	'slug'     => get_option( $slug_op, null ),
	'stato'    => get_option( $stato, null ),
	'attesa'   => get_transient( $attesa ),
	'scadenza' => (int) get_option( '_transient_timeout_' . $attesa, 0 ),
	'lingua'   => (string) apply_filters( 'wpml_current_language', 'it' ),
	'vars'     => array_intersect_key( (array) $wp_query->query_vars, array_flip( $query_var ) ),
	'regola'   => $GLOBALS['wp']->matched_rule ?? null,
	'query_wp' => $GLOBALS['wp']->matched_query ?? null,
	'token'    => getenv( 'WP_API_TOKEN' ),
	'log'      => ini_get( 'error_log' ),
	'get'      => $_GET['argomento'] ?? null, // phpcs:ignore WordPress.Security.NonceVerification -- stato del test
];
$file_log = tempnam( sys_get_temp_dir(), 'entes-route-log-' );

// Richieste HTTP bloccate e contate; lo shard finto risponde agli enti solo quando `$shard` ha un corpo (prima richiesta)
$richieste = [];
$shard     = null;
$http      = function ( $pre, $args, $url ) use ( &$richieste, &$shard ) {
	$richieste[] = (string) $url;
	if ( null !== $shard && false !== strpos( (string) $url, '/api/v1/entes' ) ) {
		return [
			'headers'  => [],
			'body'     => $shard,
			'response' => [ 'code' => 200, 'message' => '' ],
			'cookies'  => [],
			'filename' => null,
		];
	}
	return new WP_Error( 'test_entes_route', 'richiesta HTTP bloccata dal test' );
};

$lingua = function ( string $codice ) {
	do_action( 'wpml_switch_language', $codice );
};

// Query var della richiesta corrente, come le lascerebbe WP::parse_request() dopo le regole, con la regola che ha
// corrisposto (`$wp->matched_rule`): quella degli enti della vista, o `$regola` quando è data (null: nessuna regola)
$regola_di = fn( array $vars ): ?string => match ( true ) {
	'detail' === ( $vars['forestas_entes'] ?? null )                              => '^enti/([^/]+)/?$',
	'list' === ( $vars['forestas_entes'] ?? null ) && ! empty( $vars['paged'] ) => '^enti/page/([0-9]+)/?$',
	'list' === ( $vars['forestas_entes'] ?? null )                                => '^enti/?$',
	default                                                                       => null,
};
$richiesta = function ( array $vars, $regola = false ) use ( $query_var, $regola_di ) {
	global $wp_query;
	foreach ( $query_var as $nome ) {
		unset( $wp_query->query_vars[ $nome ] );
	}
	foreach ( $vars as $nome => $valore ) {
		$wp_query->set( $nome, $valore );
	}
	$GLOBALS['wp']->matched_rule = false === $regola ? $regola_di( $vars ) : $regola;
};

// Rimette lo stato com'era: alla fine e, se il test si ferma con un errore fatale, alla chiusura di PHP
$rimesso = false;
$rimetti = function () use ( &$rimesso, $prima, $opzione, $slug_op, $stato, $attesa, $http, $lingua, $richiesta, $file_log ) {
	if ( $rimesso ) {
		return;
	}
	$rimesso = true;
	remove_filter( 'pre_http_request', $http, 1 );
	putenv( false === $prima['token'] ? 'WP_API_TOKEN' : 'WP_API_TOKEN=' . $prima['token'] );
	ini_set( 'error_log', (string) $prima['log'] );
	@unlink( $file_log );
	unset( $_GET['argomento'] );
	if ( null !== $prima['get'] ) {
		$_GET['argomento'] = $prima['get'];
	}
	$richiesta( $prima['vars'], $prima['regola'] );
	$lingua( $prima['lingua'] );
	delete_option( $opzione );
	if ( null !== $prima['copia'] ) {
		update_option( $opzione, $prima['copia'], false );
	}
	// Dopo la copia, che riscrive l'opzione degli slug
	foreach ( [ $slug_op => $prima['slug'], $stato => $prima['stato'] ] as $nome => $valore ) {
		delete_option( $nome );
		if ( null !== $valore ) {
			update_option( $nome, $valore, false );
		}
	}
	delete_transient( $attesa );
	if ( false !== $prima['attesa'] ) {
		set_transient( $attesa, $prima['attesa'], max( 1, $prima['scadenza'] - time() ) );
	}
};
register_shutdown_function( $rimetti );

set_error_handler(
	function ( $errno, $message, $file, $line ) use ( &$failures ) {
		$failures[] = "avviso PHP: {$message} ({$file}:{$line})";
		return true;
	}
);
add_filter( 'pre_http_request', $http, 1, 3 );
ini_set( 'error_log', $file_log );
// Senza lo stato dei rinnovi vale il fetched_at delle copie di prova (appena scritte): nessun rinnovo da una pagina
delete_option( $stato );

// Copia di prova: 20 enti con pagina (ente-01 … ente-20) e uno senza slug, che non conta per le pagine. Tipi per il
// filtro «Argomento»: 9 comuni (una pagina sola: il comune senza slug non conta), 6 enti partner, 2 complessi forestali,
// 2 «altre pubbliche istituzioni» (a pari numero con i complessi), uno di tipo sconosciuto
$tipo_di = fn( int $i ): string => match ( true ) {
	$i <= 9  => 'comune',
	$i <= 15 => 'ente-partner',
	$i <= 17 => 'complesso-forestale',
	$i <= 19 => 'altre-pubbliche-istituzioni',
	default  => 'tipo-sconosciuto',
};
$enti = [];
for ( $i = 1; $i <= 20; $i++ ) {
	$numero = sprintf( '%02d', $i );
	$enti[] = [
		'id'         => $i,
		'slug'       => "ente-{$numero}",
		'name'       => 1 === $i ? [ 'it' => 'Comune di Prova', 'en' => 'Test Municipality' ] : [ 'it' => "Ente {$numero}", 'en' => "Ente {$numero}" ],
		'type'       => $tipo_di( $i ),
		'created_at' => "2015-01-{$numero}T10:00:00+00:00",
		'tracks'     => [],
	];
}
$enti[] = [
	'id'         => 99,
	'slug'       => null,
	'name'       => [ 'it' => 'Senza slug' ],
	'type'       => 'comune',
	'created_at' => null,
	'tracks'     => [],
];
$scrivi_copia = function () use ( $opzione, $attesa, $enti ) {
	delete_transient( $attesa );
	update_option( $opzione, [ 'generated_at' => '2026-11-16T10:00:00+00:00', 'fetched_at' => gmdate( 'c' ), 'data' => $enti ], false );
};
$home = untrailingslashit( (string) get_option( 'home' ) );

try {
	$lingua( 'it' );

	// 1. Regole: le tre, nell'ordine, verso le query var; nell'array di rewrite_rules_array davanti alle altre
	$attese = [
		'^enti/?$'                => 'index.php?forestas_entes=list',
		'^enti/page/([0-9]+)/?$'  => 'index.php?forestas_entes=list&paged=$matches[1]',
		'^enti/([^/]+)/?$'        => 'index.php?forestas_entes=detail&forestas_ente_slug=$matches[1]',
	];
	$regole = forestas_entes_rewrite_rules();
	$check( $attese === $regole, 'regole degli enti: ' . wp_json_encode( $regole ) );
	$filtrate = apply_filters( 'rewrite_rules_array', [ '^altro/?$' => 'index.php?pagename=altro' ] );
	$check( array_keys( $attese ) === array_values( array_intersect( array_keys( $filtrate ), array_keys( $attese ) ) ), 'rewrite_rules_array: le tre regole, nell\'ordine' );
	$check( array_search( '^enti/?$', array_keys( $filtrate ), true ) < array_search( '^altro/?$', array_keys( $filtrate ), true ), 'rewrite_rules_array: regole degli enti prima delle altre' );

	// Le regole complete di WordPress (senza salvarle): la prima che corrisponde decide, come in WP::parse_request()
	global $wp_rewrite;
	$tutte = $wp_rewrite->rewrite_rules();
	$prima_regola = function ( string $percorso ) use ( $tutte ): string {
		foreach ( $tutte as $regola => $verso ) {
			if ( preg_match( "#^{$regola}#", $percorso ) ) {
				return (string) $verso;
			}
		}
		return '';
	};
	foreach (
		[
			'enti'                     => 'index.php?forestas_entes=list',
			'enti/'                    => 'index.php?forestas_entes=list',
			'enti/page/2'              => 'index.php?forestas_entes=list&paged=$matches[1]',
			'enti/page/2/'             => 'index.php?forestas_entes=list&paged=$matches[1]',
			'enti/comune-di-stintino/' => 'index.php?forestas_entes=detail&forestas_ente_slug=$matches[1]',
		] as $percorso => $verso
	) {
		$trovata = $prima_regola( $percorso );
		$check( $verso === $trovata, "«{$percorso}» → {$verso} ({$trovata})" );
	}
	$query_pubbliche = apply_filters( 'query_vars', [] );
	$check( in_array( 'forestas_entes', $query_pubbliche, true ) && in_array( 'forestas_ente_slug', $query_pubbliche, true ), 'query var forestas_entes e forestas_ente_slug pubbliche' );
	$check( 9 === FORESTAS_ENTES_PER_PAGE, '9 enti per pagina' );

	// 2. Rotta con 20 enti: 3 pagine
	$scrivi_copia();
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'list' ] );
	$check( [ 'status' => 200, 'view' => 'list', 'page' => 1, 'pages' => 3, 'type' => '' ] === $rotta, 'elenco senza pagina → 200, pagina 1 di 3, senza filtro: ' . wp_json_encode( $rotta ) );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'list', 'paged' => '0' ] );
	$check( 200 === ( $rotta['status'] ?? null ) && 1 === ( $rotta['page'] ?? null ), 'elenco con paged 0 → pagina 1: ' . wp_json_encode( $rotta ) );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'list', 'paged' => '3' ] );
	$check( 200 === ( $rotta['status'] ?? null ) && 3 === ( $rotta['pages'] ?? null ) && 3 === ( $rotta['page'] ?? null ), 'pagina 3 → 200 con pages 3: ' . wp_json_encode( $rotta ) );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'list', 'paged' => '4' ] );
	$check( 404 === ( $rotta['status'] ?? null ), 'pagina 4 → 404: ' . wp_json_encode( $rotta ) );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-07' ] );
	$check( 200 === ( $rotta['status'] ?? null ) && 'detail' === ( $rotta['view'] ?? null ) && 7 === ( $rotta['ente']['id'] ?? null ), 'slug esistente → 200 con l\'ente: ' . wp_json_encode( array_diff_key( $rotta, [ 'ente' => 1 ] ) ) );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'non-esiste' ] );
	$check( 404 === ( $rotta['status'] ?? null ) && ! isset( $rotta['ente'] ), 'slug inesistente → 404: ' . wp_json_encode( $rotta ) );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => '' ] );
	$check( 404 === ( $rotta['status'] ?? null ), 'slug vuoto → 404' );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'boh' ] );
	$check( 404 === ( $rotta['status'] ?? null ), 'vista sconosciuta → 404: ' . wp_json_encode( $rotta ) );

	// Le pagine contano solo gli enti con uno slug: 18 con la pagina e uno senza sono 2 pagine
	update_option( $opzione, [ 'generated_at' => null, 'fetched_at' => gmdate( 'c' ), 'data' => array_slice( $enti, 2 ) ], false );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'list', 'paged' => '3' ] );
	$check( 404 === ( $rotta['status'] ?? null ) && 2 === ( $rotta['pages'] ?? null ), '18 enti con pagina e uno senza → 2 pagine, pagina 3 → 404: ' . wp_json_encode( $rotta ) );

	// 2a. Filtro «Argomento» (`?argomento=<tipo>`): le pagine contano l'elenco filtrato
	$scrivi_copia();
	$filtro = fn( $tipo, $pagina = null ) => forestas_entes_route( array_filter( [ 'forestas_entes' => 'list', 'paged' => $pagina, 'argomento' => $tipo ], fn( $v ) => null !== $v ) );
	$rotta  = $filtro( 'comune' );
	$check( [ 'status' => 200, 'view' => 'list', 'page' => 1, 'pages' => 1, 'type' => 'comune' ] === $rotta, '9 comuni con pagina (e uno senza) → 1 pagina: ' . wp_json_encode( $rotta ) );
	$rotta = $filtro( 'comune', '2' );
	$check( 404 === ( $rotta['status'] ?? null ) && 1 === ( $rotta['pages'] ?? null ), 'comuni, pagina 2 → 404: ' . wp_json_encode( $rotta ) );
	$rotta = $filtro( 'ente-partner' );
	$check( 200 === ( $rotta['status'] ?? null ) && 1 === ( $rotta['pages'] ?? null ) && 'ente-partner' === ( $rotta['type'] ?? null ), 'enti partner → 1 pagina: ' . wp_json_encode( $rotta ) );
	$rotta = $filtro( 'Comune' );
	$check( 'comune' === ( $rotta['type'] ?? null ) && 1 === ( $rotta['pages'] ?? null ), 'valore in maiuscolo → stesso tipo: ' . wp_json_encode( $rotta ) );
	// Tipo che nessun ente ha: elenco vuoto con la sua prima pagina, come Drupal con un termine che non c'è
	$rotta = $filtro( 'pippo' );
	$check( [ 'status' => 200, 'view' => 'list', 'page' => 1, 'pages' => 1, 'type' => 'pippo' ] === $rotta, 'tipo sconosciuto → 200, una pagina vuota: ' . wp_json_encode( $rotta ) );
	$rotta = $filtro( 'pippo', '2' );
	$check( 404 === ( $rotta['status'] ?? null ), 'tipo sconosciuto, pagina 2 → 404: ' . wp_json_encode( $rotta ) );
	// Un valore che non è un testo (`?argomento[]=…`) o vuoto non filtra
	$rotta = $filtro( [ 'comune' ], '3' );
	$check( 200 === ( $rotta['status'] ?? null ) && 3 === ( $rotta['pages'] ?? null ) && '' === ( $rotta['type'] ?? null ), 'argomento non testuale → nessun filtro: ' . wp_json_encode( $rotta ) );
	$rotta = $filtro( '', '3' );
	$check( 200 === ( $rotta['status'] ?? null ) && '' === ( $rotta['type'] ?? null ), 'argomento vuoto → nessun filtro: ' . wp_json_encode( $rotta ) );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-12', 'argomento' => 'comune' ] );
	$check( 200 === ( $rotta['status'] ?? null ) && 12 === ( $rotta['ente']['id'] ?? null ) && ! isset( $rotta['type'] ), 'dettaglio: il filtro non conta: ' . wp_json_encode( array_diff_key( $rotta, [ 'ente' => 1 ] ) ) );

	// Card filtrate: solo il tipo chiesto, nell'ordine dell'elenco; nessuna per un tipo che nessuno ha
	$slug_di = fn( array $card ) => $card['slug'];
	$card    = array_map( $slug_di, forestas_entes_list( 'it', 'complesso-forestale' ) );
	$check( [ 'ente-16', 'ente-17' ] === $card, 'card dei complessi forestali: ' . implode( ', ', $card ) );
	$check( [] === forestas_entes_list( 'it', 'pippo' ), 'card di un tipo sconosciuto: nessuna' );
	$check( 20 === count( forestas_entes_list( 'it' ) ), 'card senza filtro: tutte e 20' );

	// Faccetta: i tipi degli enti dell'elenco filtrato con il loro numero (operatore AND di Drupal), per numero
	// decrescente e a pari numero per nome; il tipo sconosciuto non c'è. Nomi nella lingua della pagina
	$faccetta    = forestas_entes_type_facet( '' );
	$voci_attese = [
		'comune'                      => [ 'Comune', 9 ],
		'ente-partner'                => [ 'Ente partner', 6 ],
		'altre-pubbliche-istituzioni' => [ 'Altre Pubbliche Istituzioni', 2 ],
		'complesso-forestale'         => [ 'Complesso forestale', 2 ],
	];
	$check( $voci_attese === $faccetta, 'faccetta senza filtro: ' . wp_json_encode( $faccetta ) );
	$check( [ 'comune' => [ 'Comune', 9 ] ] === forestas_entes_type_facet( 'comune' ), 'faccetta con «comune» scelto: solo la sua voce: ' . wp_json_encode( forestas_entes_type_facet( 'comune' ) ) );
	$check( $voci_attese === forestas_entes_type_facet( 'pippo' ), 'faccetta con un tipo sconosciuto: le voci di tutto l\'elenco' );
	$lingua( 'en-us' );
	$faccetta    = forestas_entes_type_facet( '' );
	$check( [ 'comune', 'ente-partner', 'complesso-forestale', 'altre-pubbliche-istituzioni' ] === array_keys( $faccetta ) && [ 'Forest complex', 2 ] === ( $faccetta['complesso-forestale'] ?? null ), 'faccetta inglese: nomi inglesi, pari numero per nome inglese: ' . wp_json_encode( $faccetta ) );
	$lingua( 'it' );

	// 2b. Senza copia e senza attesa la rotta fa la prima richiesta allo shard (forestas_entes_all()), poi legge la copia
	delete_option( $opzione );
	delete_transient( $attesa );
	putenv( 'WP_API_TOKEN=token-di-prova-8990-rotta' );
	$shard = (string) wp_json_encode( [ 'generated_at' => '2026-11-16T10:00:00+00:00', 'data' => $enti ] );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-07' ] );
	$check( 200 === ( $rotta['status'] ?? null ) && 7 === ( $rotta['ente']['id'] ?? null ), 'senza copia: prima richiesta allo shard, poi il dettaglio dalla copia: ' . wp_json_encode( array_diff_key( $rotta, [ 'ente' => 1 ] ) ) );
	$check( 1 === count( $richieste ) && false !== strpos( (string) ( $richieste[0] ?? '' ), '/api/v1/entes' ), 'senza copia: una richiesta agli enti dello shard: ' . implode( ', ', $richieste ) );
	$shard     = null;
	$richieste = [];
	putenv( false === $prima['token'] ? 'WP_API_TOKEN' : 'WP_API_TOKEN=' . $prima['token'] );

	// 3. Nessuna copia e attesa in corso → 503, senza chiamare lo shard
	delete_option( $opzione );
	set_transient( $attesa, time(), 300 );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'list' ] );
	$check( 503 === ( $rotta['status'] ?? null ) && 'list' === ( $rotta['view'] ?? null ), 'elenco senza copia → 503: ' . wp_json_encode( $rotta ) );
	$rotta = forestas_entes_route( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-07' ] );
	$check( 503 === ( $rotta['status'] ?? null ) && 'detail' === ( $rotta['view'] ?? null ), 'dettaglio senza copia → 503: ' . wp_json_encode( $rotta ) );
	$check( [] === $richieste, 'nessuna richiesta allo shard con l\'attesa in corso: ' . implode( ', ', $richieste ) );

	// 4. Richiesta corrente: rotta con la lingua, template, titolo, hreflang e selettore di lingua
	$scrivi_copia();

	// Selettore di lingua letto da WPML come nella pagina vera, prima di ogni altro indirizzo degli enti: WPML tiene in
	// cache ogni lettura delle lingue (per argomenti), e la prima lettura del child (forestas_entes_wpml_code()) passa dal
	// filtro mentre il selettore calcola gli indirizzi. Prima `skip_missing` 1 (gli hreflang di WPML), poi 0 (il
	// selettore dell'header): anche la seconda, che viene dalla cache, porta all'ente
	$richiesta( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-01' ] );
	if ( function_exists( 'wp_cache_flush_group' ) ) {
		wp_cache_flush_group( 'ls_languages' );
	}
	apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 1 ] );
	$dalla_cache = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
	$check( $home . '/enti/ente-01/' === ( $dalla_cache['it']['url'] ?? null ) && str_ends_with( (string) ( $dalla_cache['en-us']['url'] ?? '' ), '/en/enti/ente-01/' ), 'selettore dell\'header dalla cache di WPML verso lo stesso ente: ' . wp_json_encode( array_column( (array) $dalla_cache, 'url' ) ) );

	$richiesta( [] );
	$check( null === forestas_entes_page(), 'senza query var: non è una pagina degli enti' );
	$check( '/x/index.php' === forestas_entes_template( '/x/index.php' ), 'senza query var: template invariato' );
	$check( 'Titolo' === apply_filters( 'pre_get_document_title', 'Titolo' ), 'senza query var: titolo invariato' );

	// 4a. Pagina degli enti solo per la query principale arrivata da una regola degli enti (forestas_entes_requested()):
	// la query var è pubblica, e `/news/?forestas_entes=list` o `/?forestas_entes[]=x` restano le pagine di WordPress,
	// senza avvisi PHP (l'error handler del test li conta come errori). I filtri della query (parse_query,
	// posts_pre_query, pre_handle_404) non fanno nulla sulle altre query. La query principale di prova sostituisce per
	// un momento quella di WordPress ($wp_the_query), che non si tocca
	$query_principale = $GLOBALS['wp_the_query'];
	$principale       = new WP_Query();
	$regola_pagine    = '(.?.+?)(?:/([0-9]+))?/?$'; // la regola delle pagine di WordPress, come per /news/
	$casi             = [
		// caso => [ query var, regola che ha corrisposto, query principale?, pagina degli enti? ]
		'elenco dalla regola degli enti'                 => [ [ 'forestas_entes' => 'list' ], '^enti/?$', true, true ],
		'pagina 2 dalla regola degli enti'               => [ [ 'forestas_entes' => 'list', 'paged' => 2 ], '^enti/page/([0-9]+)/?$', true, true ],
		'dettaglio dalla regola degli enti'              => [ [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-01' ], '^enti/([^/]+)/?$', true, true ],
		'senza query var'                                => [ [ 'forestas_ente_slug' => 'ente-01' ], '^enti/([^/]+)/?$', true, false ],
		'query non principale'                           => [ [ 'forestas_entes' => 'list' ], '^enti/?$', false, false ],
		'valore array (/?forestas_entes[]=x)'            => [ [ 'forestas_entes' => [ 'x' ] ], null, true, false ],
		'valore array dalla regola degli enti'           => [ [ 'forestas_entes' => [ 'x' ] ], '^enti/?$', true, false ],
		'vista sconosciuta'                              => [ [ 'forestas_entes' => 'boh' ], '^enti/?$', true, false ],
		'/news/?forestas_entes=list (regola di una pagina)' => [ [ 'forestas_entes' => 'list' ], $regola_pagine, true, false ],
		'/?forestas_entes=list (nessuna regola)'         => [ [ 'forestas_entes' => 'list' ], null, true, false ],
	];
	try {
		$GLOBALS['wp_the_query'] = $principale;
		foreach ( $casi as $caso => [ $vars, $regola, $main, $atteso ] ) {
			$query                       = $main ? $principale : new WP_Query();
			$GLOBALS['wp']->matched_rule = $regola;
			$query->parse_query( $vars ); // lancia parse_query
			$check( $main === $query->is_main_query(), "{$caso}: query principale " . var_export( $main, true ) );
			$check( $atteso === forestas_entes_requested( $query ), "{$caso}: forestas_entes_requested() → " . var_export( $atteso, true ) );
			$check( ! $atteso === $query->is_home, "{$caso}: parse_query → is_home " . var_export( ! $atteso, true ) . ' (' . var_export( $query->is_home, true ) . ')' );
			$check( ( $atteso ? [] : null ) === apply_filters( 'posts_pre_query', null, $query ), "{$caso}: posts_pre_query → " . ( $atteso ? '[] (nessuna query dei post)' : 'invariato' ) );
			$check( $atteso === apply_filters( 'pre_handle_404', false, $query ), "{$caso}: pre_handle_404 → " . var_export( $atteso, true ) );
			if ( $main ) {
				$pagina = forestas_entes_page();
				$check( $atteso === ( null !== $pagina ), "{$caso}: forestas_entes_page() " . ( $atteso ? 'con la pagina' : 'null' ) . ': ' . wp_json_encode( null === $pagina ? null : array_diff_key( $pagina, [ 'ente' => 1 ] ) ) );
				$check( $atteso === ( '/x/index.php' !== forestas_entes_template( '/x/index.php' ) ), "{$caso}: template " . ( $atteso ? 'degli enti' : 'invariato' ) );
				$check( $atteso === ( 'Titolo' !== apply_filters( 'pre_get_document_title', 'Titolo' ) ), "{$caso}: titolo " . ( $atteso ? 'degli enti' : 'invariato' ) );
			}
		}
	} finally {
		$GLOBALS['wp_the_query'] = $query_principale;
	}
	$check( $query_principale === $GLOBALS['wp_the_query'] && $wp_query === $GLOBALS['wp_the_query'], 'query principale di WordPress rimessa' );

	// 4b. Query var degli enti della richiesta solo dalle regole degli enti, con i valori della regola (filtro `request`,
	// forestas_entes_request_vars()): da un altro indirizzo si tolgono, così la home resta la pagina iniziale
	$richiesta_wp = function ( array $vars, ?string $regola, string $query_regola = '' ): array {
		$GLOBALS['wp']->matched_rule  = $regola;
		$GLOBALS['wp']->matched_query = $query_regola;
		return forestas_entes_request_vars( $vars );
	};
	$prima_wp = [ $GLOBALS['wp']->matched_rule ?? null, $GLOBALS['wp']->matched_query ?? null ];
	try {
		$check( false !== has_filter( 'request', 'forestas_entes_request_vars' ), 'forestas_entes_request_vars() agganciata a request' );
		$vars = $richiesta_wp( [ 'forestas_entes' => [ 'x' ] ], null );
		$check( [] === $vars, '/?forestas_entes[]=x: query var tolta (la home resta la pagina iniziale): ' . wp_json_encode( $vars ) );
		$vars = $richiesta_wp( [ 'pagename' => 'news', 'forestas_entes' => 'list', 'forestas_ente_slug' => 'x' ], $regola_pagine, 'pagename=news&page=' );
		$check( [ 'pagename' => 'news' ] === $vars, '/news/?forestas_entes=list: query var degli enti tolte: ' . wp_json_encode( $vars ) );
		$vars = $richiesta_wp( [ 'forestas_entes' => 'list' ], '^enti/?$', 'forestas_entes=list' );
		$check( [ 'forestas_entes' => 'list' ] === $vars, '/enti/: elenco: ' . wp_json_encode( $vars ) );
		$vars = $richiesta_wp( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-02' ], '^enti/?$', 'forestas_entes=list' );
		$check( [ 'forestas_entes' => 'list' ] === $vars, '/enti/?forestas_entes=detail&forestas_ente_slug=ente-02: resta l\'elenco della regola: ' . wp_json_encode( $vars ) );
		$vars = $richiesta_wp( [ 'forestas_entes' => [ 'x' ], 'paged' => '2' ], '^enti/page/([0-9]+)/?$', 'forestas_entes=list&paged=2' );
		$check( [ 'forestas_entes' => 'list', 'paged' => '2' ] === $vars, '/enti/page/2/?forestas_entes[]=x: elenco e paged della regola: ' . wp_json_encode( $vars ) );
		// Le query var pubbliche di WordPress della query string non valgono sulle regole degli enti: con `p`, `page_id`,
		// `name` o `s` WordPress accenderebbe is_single, is_page o la ricerca su una pagina senza post (avvisi PHP). Una
		// query var non pubblica, messa da altro codice, resta
		$pubbliche = [ 'p' => '1', 'page_id' => '2', 'name' => 'ciao-mondo', 'pagename' => 'contatti', 's' => 'x', 'feed' => 'rss2', 'paged' => '9', 'post_type' => 'post' ];
		$vars      = $richiesta_wp( $pubbliche + [ 'forestas_entes' => 'list', 'privata_di_prova' => 'resta' ], '^enti/?$', 'forestas_entes=list' );
		$check( [ 'privata_di_prova' => 'resta', 'forestas_entes' => 'list' ] === $vars, '/enti/?p=1&page_id=2&…: solo la query var della regola e quella non pubblica: ' . wp_json_encode( $vars ) );
		$vars = $richiesta_wp( [ 'page_id' => '2', 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-01' ], '^enti/([^/]+)/?$', 'forestas_entes=detail&forestas_ente_slug=ente-01' );
		$check( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-01' ] === $vars, '/enti/ente-01/?page_id=2: page_id tolto: ' . wp_json_encode( $vars ) );
		$vars = $richiesta_wp( $pubbliche, $regola_pagine, 'pagename=news&page=' );
		$check( $pubbliche === $vars, 'altre regole: le query var pubbliche restano: ' . wp_json_encode( $vars ) );
		$vars = $richiesta_wp( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-02' ], '^enti/([^/]+)/?$', 'forestas_entes=detail&forestas_ente_slug=ente-01' );
		$check( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-01' ] === $vars, '/enti/ente-01/?forestas_ente_slug=ente-02: lo slug della regola: ' . wp_json_encode( $vars ) );
		$check( 'non un array' === forestas_entes_request_vars( 'non un array' ), 'valore che non è un array: invariato' );
	} finally {
		[ $GLOBALS['wp']->matched_rule, $GLOBALS['wp']->matched_query ] = $prima_wp;
	}

	$check( $home . '/enti/' === forestas_entes_list_url( 'it' ), 'indirizzo dell\'elenco: ' . forestas_entes_list_url( 'it' ) );
	$check( $home . '/enti/page/2/' === forestas_entes_list_url( 'it', 2 ), 'indirizzo della pagina 2: ' . forestas_entes_list_url( 'it', 2 ) );
	$check( str_ends_with( forestas_entes_list_url( 'en-us', 2 ), '/en/enti/page/2/' ), 'indirizzo inglese della pagina 2: ' . forestas_entes_list_url( 'en-us', 2 ) );

	$separatore = (string) apply_filters( 'document_title_separator', '-' );
	$sito       = get_bloginfo( 'name', 'display' );
	$selettore  = [
		'it'    => [ 'code' => 'it', 'url' => $home . '/' ],
		'en-us' => [ 'code' => 'en-us', 'url' => $home . '/en/' ],
	];
	$hreflang   = function (): string {
		ob_start();
		forestas_entes_head_hreflang();
		return (string) ob_get_clean();
	};

	// Dettaglio in italiano
	$richiesta( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'ente-01' ] );
	$pagina = forestas_entes_page();
	$check( 200 === ( $pagina['status'] ?? null ) && 'it' === ( $pagina['lang'] ?? null ) && 1 === ( $pagina['ente']['id'] ?? null ), 'dettaglio: rotta con la lingua della pagina' );
	$check( str_ends_with( forestas_entes_template( '/x/index.php' ), '/templates/ente-detail.php' ) && is_file( forestas_entes_template( '/x/index.php' ) ), 'dettaglio: template ente-detail.php: ' . forestas_entes_template( '/x/index.php' ) );
	$titolo = apply_filters( 'pre_get_document_title', '' );
	$check( "Comune di Prova {$separatore} {$sito}" === $titolo, 'dettaglio: titolo del documento: ' . $titolo );
	$lingue = apply_filters( 'icl_ls_languages', $selettore );
	$check( $home . '/enti/ente-01/' === ( $lingue['it']['url'] ?? null ) && str_ends_with( (string) ( $lingue['en-us']['url'] ?? '' ), '/en/enti/ente-01/' ), 'dettaglio: selettore di lingua verso lo stesso ente: ' . wp_json_encode( array_column( $lingue, 'url' ) ) );
	$head = $hreflang();
	$check( false !== strpos( $head, 'hreflang="en-US" href="' . $home . '/en/enti/ente-01/"' ) && false !== strpos( $head, 'hreflang="it-IT" href="' . $home . '/enti/ente-01/"' ) && false !== strpos( $head, 'hreflang="x-default" href="' . $home . '/enti/ente-01/"' ), 'dettaglio: hreflang delle due lingue e x-default: ' . $head );

	// Dettaglio in inglese: nome tradotto nel titolo
	$lingua( 'en-us' );
	$pagina = forestas_entes_page();
	$check( 'en' === ( $pagina['lang'] ?? null ), 'dettaglio inglese: lingua «en»' );
	$titolo = apply_filters( 'pre_get_document_title', '' );
	$check( "Test Municipality {$separatore} {$sito}" === $titolo, 'dettaglio inglese: titolo con il nome inglese: ' . $titolo );
	$lingua( 'it' );

	// Elenco, pagina 2
	$richiesta( [ 'forestas_entes' => 'list', 'paged' => 2 ] );
	$check( str_ends_with( forestas_entes_template( '/x/index.php' ), '/templates/entes-list.php' ) && is_file( forestas_entes_template( '/x/index.php' ) ), 'elenco: template entes-list.php: ' . forestas_entes_template( '/x/index.php' ) );
	$titolo = apply_filters( 'pre_get_document_title', '' );
	$check( "Enti, istituzioni e società {$separatore} Pagina 2 {$separatore} {$sito}" === $titolo, 'elenco: titolo con la pagina: ' . $titolo );
	$lingue = apply_filters( 'icl_ls_languages', $selettore );
	$check( $home . '/enti/page/2/' === ( $lingue['it']['url'] ?? null ) && str_ends_with( (string) ( $lingue['en-us']['url'] ?? '' ), '/en/enti/page/2/' ), 'elenco: selettore di lingua verso la stessa pagina: ' . wp_json_encode( array_column( $lingue, 'url' ) ) );
	$check( false !== strpos( $hreflang(), 'href="' . $home . '/enti/page/2/"' ), 'elenco: hreflang della pagina 2' );

	// Elenco filtrato: il tipo dal parametro della richiesta; indirizzi, selettore di lingua e hreflang lo tengono
	$check( $home . '/enti/page/2/?argomento=ente-partner' === forestas_entes_list_url( 'it', 2, 'ente-partner' ), 'indirizzo della pagina 2 filtrata: ' . forestas_entes_list_url( 'it', 2, 'ente-partner' ) );
	$check( str_ends_with( forestas_entes_list_url( 'en-us', 1, 'comune' ), '/en/enti/?argomento=comune' ), 'indirizzo inglese filtrato: ' . forestas_entes_list_url( 'en-us', 1, 'comune' ) );
	$_GET['argomento'] = 'comune';
	$richiesta( [ 'forestas_entes' => 'list' ] );
	$pagina = forestas_entes_page();
	$check( 200 === ( $pagina['status'] ?? null ) && 'comune' === ( $pagina['type'] ?? null ) && 1 === ( $pagina['pages'] ?? null ), 'elenco filtrato: tipo dalla richiesta, pagine filtrate: ' . wp_json_encode( $pagina ) );
	$lingue = apply_filters( 'icl_ls_languages', $selettore );
	$check( $home . '/enti/?argomento=comune' === ( $lingue['it']['url'] ?? null ) && str_ends_with( (string) ( $lingue['en-us']['url'] ?? '' ), '/en/enti/?argomento=comune' ), 'elenco filtrato: selettore di lingua con il filtro: ' . wp_json_encode( array_column( $lingue, 'url' ) ) );
	$check( false !== strpos( $hreflang(), 'hreflang="en-US" href="' . $home . '/en/enti/?argomento=comune"' ), 'elenco filtrato: hreflang con il filtro: ' . $hreflang() );
	$richiesta( [ 'forestas_entes' => 'list', 'paged' => 2 ] );
	$check( 404 === ( forestas_entes_page()['status'] ?? null ), 'elenco filtrato, pagina 2 oltre l\'ultima: 404' );
	unset( $_GET['argomento'] );

	// Elenco, prima pagina: senza «Pagina»
	$richiesta( [ 'forestas_entes' => 'list' ] );
	$titolo = apply_filters( 'pre_get_document_title', '' );
	$check( "Enti, istituzioni e società {$separatore} {$sito}" === $titolo, 'elenco: titolo della prima pagina: ' . $titolo );

	// 404: tutto com'è (template e titolo di WordPress, selettore e hreflang di WPML)
	$richiesta( [ 'forestas_entes' => 'detail', 'forestas_ente_slug' => 'non-esiste' ] );
	$check( 404 === ( forestas_entes_page()['status'] ?? null ), 'slug inesistente: 404' );
	$check( '/x/404.php' === forestas_entes_template( '/x/404.php' ), '404: template di WordPress' );
	$check( 'Titolo' === apply_filters( 'pre_get_document_title', 'Titolo' ), '404: titolo di WordPress' );
	$check( $selettore === apply_filters( 'icl_ls_languages', $selettore ), '404: selettore di lingua invariato' );
	$check( '' === $hreflang(), '404: nessun hreflang' );
	$richiesta( [ 'forestas_entes' => 'list', 'paged' => 4 ] );
	$check( 404 === ( forestas_entes_page()['status'] ?? null ), 'pagina 4: 404' );

	// 503: la pagina «non disponibile» del plugin, come per i dettagli di percorsi e POI
	delete_option( $opzione );
	set_transient( $attesa, time(), 300 );
	$richiesta( [ 'forestas_entes' => 'list' ] );
	$check( 503 === ( forestas_entes_page()['status'] ?? null ), 'senza copia: 503' );
	$check( str_ends_with( forestas_entes_template( '/x/index.php' ), '/detail-unavailable.php' ), '503: template della pagina non disponibile: ' . forestas_entes_template( '/x/index.php' ) );
	$check( '' === $hreflang(), '503: nessun hreflang' );
	$check( [] === $richieste, 'nessuna richiesta HTTP: ' . implode( ', ', $richieste ) );
} finally {
	restore_error_handler();
	$rimetti();
}

// Lo stato è stato rimesso com'era
$check( $prima['copia'] === get_option( $opzione, null ), 'opzione rimessa com\'era' );
$check( $prima['slug'] === get_option( $slug_op, null ) && $prima['stato'] === get_option( $stato, null ), 'opzioni degli slug e dello stato rimesse com\'erano' );
$check( $prima['attesa'] === get_transient( $attesa ), 'transient rimesso com\'era' );
$check( $prima['lingua'] === (string) apply_filters( 'wpml_current_language', 'it' ), 'lingua di WPML rimessa com\'era' );
$check( $prima['vars'] === array_intersect_key( (array) $wp_query->query_vars, array_flip( $query_var ) ), 'query var rimesse com\'erano' );
$check( $prima['regola'] === ( $GLOBALS['wp']->matched_rule ?? null ) && $prima['query_wp'] === ( $GLOBALS['wp']->matched_query ?? null ), 'regola della richiesta ($wp->matched_rule, matched_query) rimessa com\'era' );
$check( $prima['token'] === getenv( 'WP_API_TOKEN' ), 'variabile d\'ambiente WP_API_TOKEN rimessa com\'era' );
$check( $prima['get'] === ( $_GET['argomento'] ?? null ), 'parametro argomento della richiesta rimesso com\'era' );

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
