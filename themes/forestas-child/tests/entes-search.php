<?php
/**
 * Test (oc:8990): tab «Enti» della ricerca unica (inc/search.php), con lo shard finto (pre_http_request).
 * Source `entes` nel filtro wp_package_search_sources e `entesEndpoint` nella configurazione dello script; rotta
 * forestas/v1/entes-search: testo vuoto → gli enti della copia, a pagine, senza chiamare lo shard; testo → una richiesta
 * a api/v1/entes/search con il token a ogni ricerca (nessuna cache), testo troncato a 200 caratteri, card nella forma di
 * quelle dei contenuti con il link al dettaglio solo per gli enti della copia; shard in errore, risposta non valida,
 * token assente o copia mai scaricata → 503 con un messaggio, senza il token nel log. Freni verso lo shard: limite per
 * visitatore (429 con Retry-After, senza chiamare lo shard; contatori in un'opzione sola, senza le finestre chiuse e con
 * un tetto di visitatori), pausa dopo un errore (503 senza chiamare lo shard, una riga di log per pausa), IP del
 * visitatore da X-Forwarded-For solo dietro un REMOTE_ADDR privato. Testo rifiutato dallo shard (422, 400):
 * nessun risultato, senza pausa né log; testo di soli caratteri invisibili (U+200B, U+00A0, U+FEFF): testo vuoto, senza
 * chiamare lo shard.
 * Rimette copia, opzioni degli slug, dello stato e dei contatori, transient di attesa e pausa, lingua di WPML, variabile
 * d'ambiente, REMOTE_ADDR, X-Forwarded-For e file di log com'erano.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/entes-search.php
 * Stampa «PASS» o «FAIL: …» ed esce con 0 o 1. Il token di prova non compare mai nell'output.
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

if ( ! function_exists( 'forestas_entes_search_rest' ) ) {
	echo "FAIL: forestas_entes_search_rest non esiste\n";
	exit( 1 );
}

$opzione = 'forestas_entes_copy';
$slug_op = 'forestas_entes_slugs';
$stato   = 'forestas_entes_state';
$attesa  = 'forestas_entes_retry';
$pausa   = 'forestas_entes_search_pause';
$conti   = 'forestas_entes_search_counters';
// IP dei visitatori di prova (indirizzi di documentazione, pubblici per filter_var())
$ip_test = [ '203.0.113.7', '203.0.113.8', '198.51.100.4' ];
$token   = 'token-di-prova-8990-' . wp_generate_password( 12, false );
$home    = untrailingslashit( (string) get_option( 'home' ) );

// Stato da rimettere alla fine
$prima    = [
	'copia'    => get_option( $opzione, null ),
	'slug'     => get_option( $slug_op, null ),
	'stato'    => get_option( $stato, null ),
	'attesa'   => get_transient( $attesa ),
	'scadenza' => (int) get_option( '_transient_timeout_' . $attesa, 0 ),
	'pausa'    => get_transient( $pausa ),
	'fine'     => (int) get_option( '_transient_timeout_' . $pausa, 0 ),
	'contatori' => get_option( $conti, null ),
	'remote'   => $_SERVER['REMOTE_ADDR'] ?? null,
	'xff'      => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
	'token'    => getenv( 'WP_API_TOKEN' ),
	'log'      => ini_get( 'error_log' ),
	'lingua'   => (string) apply_filters( 'wpml_current_language', 'it' ),
];
$file_log = tempnam( sys_get_temp_dir(), 'entes-search-log-' );

// Shard finto: risponde solo alle richieste degli enti, conta le richieste, ricorda indirizzo e intestazione del token
$finto    = [ 'codice' => 200, 'corpo' => '', 'richieste' => 0, 'url' => '', 'auth' => '' ];
// Ogni risposta nuova dello shard riparte senza pausa e con i contatori del limite a zero
$freni_a_zero = function () use ( $pausa, $conti ) {
	delete_transient( $pausa );
	delete_option( $conti );
};
$risponde = function ( int $codice, $json ) use ( &$finto, $freni_a_zero ) {
	$freni_a_zero();
	$finto['codice']    = $codice;
	$finto['corpo']     = is_string( $json ) ? $json : wp_json_encode( $json );
	$finto['richieste'] = 0;
	$finto['url']       = '';
	$finto['auth']      = '';
};
$http     = function ( $pre, $args, $url ) use ( &$finto ) {
	if ( false === strpos( (string) $url, '/api/v1/entes' ) ) {
		return $pre;
	}
	++$finto['richieste'];
	$finto['url']  = (string) $url;
	$finto['auth'] = (string) ( $args['headers']['Authorization'] ?? '' );
	return [
		'headers'  => [],
		'body'     => $finto['corpo'],
		'response' => [ 'code' => $finto['codice'], 'message' => '' ],
		'cookies'  => [],
		'filename' => null,
	];
};
$origine    = fn() => [ 'elastic_api' => 'https://shard.example/api/v2/elasticsearch', 'origin' => 'https://shard.example', 'aws_api' => '' ];
$dal_server = fn() => 'https://interno.example';
$lingua     = function ( string $codice ) {
	do_action( 'wpml_switch_language', $codice );
};
$log        = function () use ( $file_log ): string {
	clearstatcache();
	return (string) file_get_contents( $file_log );
};

// Copia degli enti nella forma di api/v1/entes. Ordine dell'elenco (created_at crescente, senza data in fondo, senza
// slug fuori): Stintino (2013), Tepilora (2015), Agenzia (2020), GAL (senza data)
$immagine = fn( string $nome ) => [
	'url'       => 'https://img.example/' . $nome . '.jpg',
	'thumbnail' => 'https://img.example/' . $nome . '-thumb.jpg',
	'sizes'     => [ '400x200' => 'https://img.example/' . $nome . '-400x200.jpg' ],
];
$copia    = [
	'generated_at' => '2026-11-16T10:00:00+00:00',
	'fetched_at'   => gmdate( 'c' ),
	'data'         => [
		[ 'id' => 11, 'slug' => 'oasi-tepilora', 'name' => [ 'it' => 'Oasi Tepilora', 'en' => 'Oasi Tepilora' ], 'type' => 'complesso-forestale', 'feature_image' => $immagine( 'tepilora' ), 'created_at' => '2015-03-01 10:00:00', 'tracks' => [ [ 'id' => 1, 'roles' => [ 'gestore', 'manutentore' ] ], [ 'id' => 4, 'roles' => [ 'gestore' ] ] ] ],
		[ 'id' => 12, 'slug' => 'comune-di-stintino', 'name' => [ 'it' => 'Comune di Stintino', 'en' => 'Comune di Stintino' ], 'type' => 'comune', 'feature_image' => null, 'created_at' => '2013-01-20 09:00:00', 'tracks' => [ [ 'id' => 2, 'roles' => [ 'rilevatore', 'ruolo-sconosciuto' ] ] ] ],
		[ 'id' => 13, 'slug' => 'agenzia-forestas', 'name' => [ 'it' => 'Agenzia Forestas', 'en' => 'Forestas Agency' ], 'type' => 'boh', 'feature_image' => null, 'created_at' => '2020-05-05 08:00:00', 'tracks' => [ [ 'id' => 3, 'roles' => [ 'gestore' ] ] ] ],
		[ 'id' => 14, 'slug' => '', 'name' => [ 'it' => 'Senza slug' ], 'type' => 'comune', 'feature_image' => null, 'created_at' => '2010-01-01 00:00:00', 'tracks' => [] ],
		[ 'id' => 15, 'slug' => 'gal-bmg', 'name' => [ 'it' => 'GAL BMG', 'en' => 'GAL BMG' ], 'type' => 'ente-partner', 'feature_image' => null, 'created_at' => null, 'tracks' => [] ],
	],
];
// Card della ricerca dello shard (EnteController::search): `name` con le traduzioni, `feature_image` con url e thumbnail
$trovati = [
	'data' => [
		[ 'id' => 11, 'slug' => 'oasi-tepilora', 'name' => [ 'it' => 'Oasi Tepilora', 'en' => 'Oasi Tepilora' ], 'type' => 'complesso-forestale', 'feature_image' => [ 'url' => 'https://img.example/tepilora.jpg', 'thumbnail' => 'https://img.example/tepilora-thumb.jpg' ] ],
		// Ente nell'indice della ricerca ma non ancora nella copia (creato da meno di 15 minuti): card senza link
		[ 'id' => 99, 'slug' => 'ente-nuovo', 'name' => [ 'it' => 'Ente nuovo' ], 'type' => 'comune', 'feature_image' => null ],
		// Senza slug non ha una pagina: fuori
		[ 'id' => 98, 'slug' => null, 'name' => [ 'it' => 'Senza slug' ], 'type' => 'comune', 'feature_image' => null ],
	],
];

// Chiamata della rotta come dal browser (argomenti, valori predefiniti e controlli della rotta compresi)
$cerca = function ( array $params ): WP_REST_Response {
	$request = new WP_REST_Request( 'GET', '/forestas/v1/entes-search' );
	foreach ( $params as $chiave => $valore ) {
		$request->set_param( $chiave, $valore );
	}
	return rest_ensure_response( rest_do_request( $request ) );
};
$ids   = fn( WP_REST_Response $r ) => array_map( fn( $i ) => $i['id'] ?? null, (array) ( $r->get_data()['items'] ?? [] ) );
$campo = fn( $array, string $chiave ) => is_array( $array ) && array_key_exists( $chiave, $array ) ? $array[ $chiave ] : 'assente';

// Rimette il database com'era: alla fine e, se il test si ferma con un errore fatale, alla chiusura di PHP
$rimesso = false;
$rimetti = function () use ( &$rimesso, $prima, $opzione, $slug_op, $stato, $attesa, $pausa, $conti, $file_log, $http, $origine, $dal_server, $lingua ) {
	if ( $rimesso ) {
		return;
	}
	$rimesso = true;
	remove_filter( 'pre_http_request', $http, 10 );
	remove_filter( 'wmwp_api_urls', $origine, PHP_INT_MAX );
	remove_filter( 'wmwp_server_origin', $dal_server, PHP_INT_MAX );
	ini_set( 'error_log', (string) $prima['log'] );
	@unlink( $file_log );
	putenv( false === $prima['token'] ? 'WP_API_TOKEN' : 'WP_API_TOKEN=' . $prima['token'] );
	delete_option( $opzione );
	if ( null !== $prima['copia'] ) {
		update_option( $opzione, $prima['copia'], false );
	}
	// Dopo la copia, che riscrive l'opzione degli slug
	foreach ( [ $slug_op => $prima['slug'], $stato => $prima['stato'], $conti => $prima['contatori'] ] as $nome => $valore ) {
		delete_option( $nome );
		if ( null !== $valore ) {
			update_option( $nome, $valore, false );
		}
	}
	delete_transient( $attesa );
	if ( false !== $prima['attesa'] ) {
		set_transient( $attesa, $prima['attesa'], max( 1, $prima['scadenza'] - time() ) );
	}
	delete_transient( $pausa );
	if ( false !== $prima['pausa'] ) {
		set_transient( $pausa, $prima['pausa'], max( 1, $prima['fine'] - time() ) );
	}
	foreach ( [ 'REMOTE_ADDR' => $prima['remote'], 'HTTP_X_FORWARDED_FOR' => $prima['xff'] ] as $chiave => $valore ) {
		unset( $_SERVER[ $chiave ] );
		if ( null !== $valore ) {
			$_SERVER[ $chiave ] = $valore;
		}
	}
	$lingua( $prima['lingua'] );
};
register_shutdown_function( $rimetti );

set_error_handler(
	function ( $errno, $message, $file, $line ) use ( &$failures ) {
		$failures[] = "avviso PHP: {$message} ({$file}:{$line})";
		return true;
	}
);
add_filter( 'pre_http_request', $http, 10, 3 );
add_filter( 'wmwp_api_urls', $origine, PHP_INT_MAX );
add_filter( 'wmwp_server_origin', $dal_server, PHP_INT_MAX );
ini_set( 'error_log', $file_log );
putenv( 'WP_API_TOKEN=' . $token );
delete_transient( $attesa );
update_option( $opzione, $copia, false );
$_SERVER['REMOTE_ADDR'] = $ip_test[0];
unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
$freni_a_zero();

try {
	$lingua( 'it' );

	// 1. Source `entes` nel filtro del plugin, in italiano e in inglese
	$sorgente = apply_filters( 'wp_package_search_sources', [] )['entes'] ?? null;
	$check( is_array( $sorgente ), 'source entes nel filtro wp_package_search_sources' );
	$check( 'enti' === $campo( $sorgente, 'slug' ), 'source entes: slug enti' );
	$check( 'Enti' === $campo( $sorgente, 'label' ), 'source entes: label Enti' );
	$check( [ 'one' => '%s ente', 'other' => '%s enti' ] === $campo( $sorgente, 'nouns' ), 'source entes: nouns in italiano' );
	$check( 'forestas-search' === $campo( $sorgente, 'script' ), 'source entes: script forestas-search' );
	$check( 'cards' === $campo( $sorgente, 'layout' ), 'source entes: layout cards' );
	$check( [ [ 'key' => 'tipo-ente', 'label' => 'Tipo di ente' ], [ 'key' => 'ruolo', 'label' => 'Ruolo sui sentieri' ] ] === $campo( $sorgente, 'facets' ), 'source entes: filtri tipo di ente e ruolo sui sentieri (' . wp_json_encode( $campo( $sorgente, 'facets' ) ) . ')' );
	$lingua( 'en-us' );
	$sorgente = apply_filters( 'wp_package_search_sources', [] )['entes'] ?? null;
	$check( 'Organisations' === $campo( $sorgente, 'label' ), 'source entes: label Organisations' );
	$check( [ 'one' => '%s organisation', 'other' => '%s organisations' ] === $campo( $sorgente, 'nouns' ), 'source entes: nouns in inglese' );
	$check( [ 'Type of organisation', 'Role on trails' ] === array_column( (array) $campo( $sorgente, 'facets' ), 'label' ), 'source entes: nomi dei filtri in inglese' );
	$lingua( 'it' );
	// Ordine dei gruppi del tab «Tutti»: dopo percorsi e contenuti
	$chiavi = array_keys( apply_filters( 'wp_package_search_sources', [ 'tracks' => [ 'label' => 'x' ] ] ) );
	$check( [ 'tracks', 'content', 'entes' ] === $chiavi, 'ordine delle source: ' . implode( ', ', $chiavi ) );

	// 2. Rotta registrata con gli argomenti della rotta dei contenuti (senza il tipo)
	$rotte = rest_get_server()->get_routes();
	$check( isset( $rotte['/forestas/v1/entes-search'] ), 'rotta forestas/v1/entes-search registrata' );
	// WPML aggiunge a ogni rotta il suo `wpml_language`: qui contano i nostri
	$argomenti = array_values( array_intersect( array_keys( $rotte['/forestas/v1/entes-search'][0]['args'] ?? [] ), [ 'q', 'type', 'page', 'per_page', 'lang' ] ) );
	sort( $argomenti );
	$check( [ 'lang', 'page', 'per_page', 'q' ] === $argomenti, 'argomenti della rotta: ' . implode( ', ', $argomenti ) );

	// 3. Indirizzo della rotta nella configurazione dello script della ricerca (lo shortcode della pagina)
	if ( shortcode_exists( 'wmwp_search' ) ) {
		do_shortcode( '[forestas_search]' );
		$prima_script = implode( "\n", (array) wp_scripts()->get_data( 'forestas-search', 'before' ) );
		$check( false !== strpos( $prima_script, '"entesEndpoint":' . wp_json_encode( rest_url( 'forestas/v1/entes-search' ) ) ), 'entesEndpoint nella configurazione dello script' );
	} else {
		$check( false, 'plugin wp-package non attivo: configurazione dello script non controllabile' );
	}

	// 4. Testo vuoto: gli enti della copia nell'ordine dell'elenco, senza chiamare lo shard
	$risponde( 200, $trovati );
	$risposta = $cerca( [ 'lang' => 'it' ] );
	$dati     = $risposta->get_data();
	$check( 200 === $risposta->get_status(), 'testo vuoto: 200 (' . $risposta->get_status() . ')' );
	$check( 4 === $campo( $dati, 'total' ), 'testo vuoto: total uguale agli enti della copia con slug (4)' );
	$check( [ 12, 11, 13, 15 ] === $ids( $risposta ), 'testo vuoto: ordine dell\'elenco (' . implode( ', ', $ids( $risposta ) ) . ')' );
	$check( 0 === $finto['richieste'], 'testo vuoto: nessuna richiesta allo shard (' . $finto['richieste'] . ')' );
	$stintino = $dati['items'][0] ?? [];
	$tepilora = $dati['items'][1] ?? [];
	$agenzia  = $dati['items'][2] ?? [];
	$check( 'Comune di Stintino' === $campo( $stintino, 'title' ), 'testo vuoto: titolo è il nome' );
	$check( $home . '/enti/comune-di-stintino/' === $campo( $stintino, 'url' ), 'testo vuoto: url del dettaglio (' . wp_json_encode( $campo( $stintino, 'url' ) ) . ')' );
	$check( 'ente' === $campo( $stintino, 'typeKey' ), 'testo vuoto: typeKey ente' );
	$check( 'Comune' === $campo( $stintino, 'typeLabel' ), 'testo vuoto: typeLabel è il tipo dell\'ente' );
	$check( null === $campo( $stintino, 'image' ), 'testo vuoto: senza foto image null' );
	$check( [ 'src' => 'https://img.example/tepilora-thumb.jpg', 'alt' => '' ] === $campo( $tepilora, 'image' ), 'testo vuoto: miniatura con alt vuoto' );
	$check( 'Ente' === $campo( $agenzia, 'typeLabel' ), 'testo vuoto: tipo sconosciuto → «Ente»' );
	foreach ( [ 'code' => null, 'meta' => [], 'badge' => null ] as $chiave => $valore ) {
		$check( $valore === $campo( $stintino, $chiave ), "card nella forma del plugin: {$chiave}" );
	}

	// 4b. Testo vuoto, a pagine (e pagina oltre l'ultima → l'ultima, come la rotta dei contenuti)
	$risposta = $cerca( [ 'lang' => 'it', 'per_page' => 2, 'page' => 2 ] );
	$check( 4 === $campo( $risposta->get_data(), 'total' ) && [ 13, 15 ] === $ids( $risposta ), 'testo vuoto, pagina 2 di 2: ' . implode( ', ', $ids( $risposta ) ) );
	$risposta = $cerca( [ 'lang' => 'it', 'per_page' => 2, 'page' => 9 ] );
	$check( [ 13, 15 ] === $ids( $risposta ), 'testo vuoto, pagina oltre l\'ultima → l\'ultima' );
	$risposta = $cerca( [ 'lang' => 'it', 'q' => '   ' ] );
	$check( 4 === $campo( $risposta->get_data(), 'total' ) && 0 === $finto['richieste'], 'testo di soli spazi = testo vuoto, senza richieste' );

	// 4c. Testo vuoto in inglese: nome, tipo e indirizzo nella lingua chiesta
	$risposta = $cerca( [ 'lang' => 'en' ] );
	$dati     = $risposta->get_data();
	$check( $home . '/en/enti/comune-di-stintino/' === $campo( $dati['items'][0] ?? [], 'url' ), 'testo vuoto in inglese: url /en/enti/ (' . wp_json_encode( $campo( $dati['items'][0] ?? [], 'url' ) ) . ')' );
	$check( 'Municipality' === $campo( $dati['items'][0] ?? [], 'typeLabel' ), 'testo vuoto in inglese: tipo in inglese' );
	$check( 'Forestas Agency' === $campo( $dati['items'][2] ?? [], 'title' ), 'testo vuoto in inglese: nome inglese' );
	$check( 'Organisation' === $campo( $dati['items'][2] ?? [], 'typeLabel' ), 'testo vuoto in inglese: tipo sconosciuto → «Organisation»' );
	$lingua( 'it' );

	// 5. Testo «tepilora»: una richiesta con il testo e il token, card con il link solo per gli enti della copia
	$risponde( 200, $trovati );
	$risposta = $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] );
	$dati     = $risposta->get_data();
	$check( 200 === $risposta->get_status(), 'tepilora: 200 (' . $risposta->get_status() . ')' );
	$check( 1 === $finto['richieste'], 'tepilora: una richiesta allo shard (' . $finto['richieste'] . ')' );
	$check( 'https://interno.example/api/v1/entes/search?query=tepilora' === $finto['url'], 'tepilora: indirizzo della ricerca (' . $finto['url'] . ')' );
	$check( 'Bearer ' . $token === $finto['auth'], 'tepilora: intestazione Authorization con il token' );
	$check( 2 === $campo( $dati, 'total' ) && [ 11, 99 ] === $ids( $risposta ), 'tepilora: due card, senza quella senza slug (' . implode( ', ', $ids( $risposta ) ) . ')' );
	$tepilora = $dati['items'][0] ?? [];
	$nuovo    = $dati['items'][1] ?? [];
	$check( 'Oasi Tepilora' === $campo( $tepilora, 'title' ), 'tepilora: titolo' );
	$check( $home . '/enti/oasi-tepilora/' === $campo( $tepilora, 'url' ), 'tepilora: url /enti/oasi-tepilora/ (' . wp_json_encode( $campo( $tepilora, 'url' ) ) . ')' );
	$check( 'ente' === $campo( $tepilora, 'typeKey' ) && 'Complesso forestale' === $campo( $tepilora, 'typeLabel' ), 'tepilora: typeKey e typeLabel' );
	$check( [ 'src' => 'https://img.example/tepilora-thumb.jpg', 'alt' => '' ] === $campo( $tepilora, 'image' ), 'tepilora: miniatura' );
	$check( null === $campo( $nuovo, 'url' ), 'ente non ancora nella copia: card senza link' );
	$check( 'Ente nuovo' === $campo( $nuovo, 'title' ) && 'Comune' === $campo( $nuovo, 'typeLabel' ), 'ente non ancora nella copia: nome e tipo' );

	// 5b. Nessuna cache: lo stesso testo chiama di nuovo lo shard
	$cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] );
	$check( 2 === $finto['richieste'], 'stesso testo una seconda volta: seconda richiesta (' . $finto['richieste'] . ')' );

	// 5c. In inglese: indirizzo /en/enti/ e tipo in inglese
	$risponde( 200, $trovati );
	$risposta = $cerca( [ 'lang' => 'en', 'q' => 'tepilora' ] );
	$dati     = $risposta->get_data();
	$check( $home . '/en/enti/oasi-tepilora/' === $campo( $dati['items'][0] ?? [], 'url' ), 'tepilora in inglese: url /en/enti/ (' . wp_json_encode( $campo( $dati['items'][0] ?? [], 'url' ) ) . ')' );
	$check( 'Forest complex' === $campo( $dati['items'][0] ?? [], 'typeLabel' ), 'tepilora in inglese: tipo in inglese' );
	$lingua( 'it' );

	// 5d. Risultati dello shard a pagine (l'API ne dà fino a 50 insieme)
	// Gli enti della copia con lo slug (tolto quello senza slug), più Tepilora una seconda volta in fondo: 5 card
	$con_slug = array_values( array_filter( $copia['data'], fn( $e ) => '' !== $e['slug'] ) );
	$cinque   = [ 'data' => array_map( fn( $e ) => array_intersect_key( $e, array_flip( [ 'id', 'slug', 'name', 'type', 'feature_image' ] ) ), array_merge( $con_slug, [ $copia['data'][0] ] ) ) ];
	$risponde( 200, $cinque );
	$risposta = $cerca( [ 'lang' => 'it', 'q' => 'ente', 'per_page' => 2, 'page' => 3 ] );
	$check( 5 === $campo( $risposta->get_data(), 'total' ) && [ 11 ] === $ids( $risposta ), 'risultati dello shard, pagina 3 di 3: total ' . wp_json_encode( $campo( $risposta->get_data(), 'total' ) ) . ', ' . implode( ', ', $ids( $risposta ) ) );
	$check( 1 === $finto['richieste'], 'risultati a pagine: una richiesta' );

	// 6. Testo di 201 caratteri: troncato a 200 prima della richiesta (l'API risponde 422 oltre i 200)
	$risponde( 200, [ 'data' => [] ] );
	$risposta = $cerca( [ 'lang' => 'it', 'q' => str_repeat( 'à', 201 ) ] );
	parse_str( (string) wp_parse_url( $finto['url'], PHP_URL_QUERY ), $query );
	$check( 1 === $finto['richieste'] && str_repeat( 'à', 200 ) === ( $query['query'] ?? null ), 'testo di 201 caratteri troncato a 200 (' . mb_strlen( (string) ( $query['query'] ?? '' ) ) . ')' );
	$check( 200 === $risposta->get_status() && 0 === $campo( $risposta->get_data(), 'total' ), 'nessun ente trovato: 200 con total 0' );

	// 7. Shard 503 (indice degli enti assente): risposta 503 con un messaggio, riga di log senza il token
	file_put_contents( $file_log, '' );
	$risponde( 503, [ 'message' => 'Indice degli enti assente' ] );
	$risposta = $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] );
	$check( 503 === $risposta->get_status(), 'shard 503 → 503 (' . $risposta->get_status() . ')' );
	$messaggio = $campo( $risposta->get_data(), 'message' );
	$check( is_string( $messaggio ) && '' !== $messaggio, 'shard 503: messaggio nella risposta' );
	$check( 'assente' === $campo( $risposta->get_data(), 'items' ), 'shard 503: nessun item' );
	$check( false !== strpos( $log(), 'forestas enti:' ) && false !== strpos( $log(), '503' ), 'shard 503: riga di log con il codice' );
	$check( (string) FORESTAS_ENTES_SEARCH_PAUSE === (string) ( $risposta->get_headers()['Retry-After'] ?? '' ), 'shard 503: Retry-After con la pausa' );
	$check( false === strpos( $log(), $token ), 'shard 503: il token non è nel log' );
	$lingua( 'en-us' );
	$risposta = $cerca( [ 'lang' => 'en', 'q' => 'tepilora' ] );
	$check( 503 === $risposta->get_status() && $messaggio !== $campo( $risposta->get_data(), 'message' ), 'shard 503 in inglese: messaggio in inglese' );
	$lingua( 'it' );

	// 7b. Risposta 200 non valida (senza `data` o non lista): 503
	$risponde( 200, [ 'sources' => [] ] );
	$check( 503 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status(), 'risposta senza data → 503' );
	$check( 1 === $finto['richieste'], 'risposta senza data: lo shard è stato chiamato' );
	$risponde( 200, '{rotto' );
	$check( 503 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && 1 === $finto['richieste'], 'JSON rotto → 503' );
	$risponde( 200, [ 'data' => [ 'a' => 1 ] ] );
	$check( 503 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && 1 === $finto['richieste'], 'data non lista → 503' );

	// 7c. Senza token: 503 senza chiamare lo shard; a testo vuoto la copia basta
	putenv( 'WP_API_TOKEN' );
	$risponde( 200, $trovati );
	$check( 503 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && 0 === $finto['richieste'], 'senza token: 503 e nessuna richiesta' );
	$check( 4 === $campo( $cerca( [ 'lang' => 'it' ] )->get_data(), 'total' ), 'senza token, testo vuoto: gli enti della copia' );
	putenv( 'WP_API_TOKEN=' . $token );

	// 8. Copia mai scaricata, testo vuoto: 503 senza chiamare lo shard (la prima richiesta è delle pagine degli enti) e
	// senza scrivere l'attesa
	delete_option( $opzione );
	$risponde( 200, [ 'generated_at' => null, 'data' => $copia['data'] ] );
	$risposta = $cerca( [ 'lang' => 'it' ] );
	$check( 503 === $risposta->get_status(), 'copia mai scaricata, testo vuoto: 503 (' . $risposta->get_status() . ')' );
	$check( 0 === $finto['richieste'], 'copia mai scaricata, testo vuoto: nessuna richiesta allo shard' );
	$check( false === get_transient( $attesa ), 'copia mai scaricata, testo vuoto: nessuna attesa scritta' );
	// …mentre la ricerca con un testo funziona: le card sono senza link, le pagine degli enti non ci sono ancora
	$risponde( 200, $trovati );
	$risposta = $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] );
	$check( 200 === $risposta->get_status() && null === $campo( $risposta->get_data()['items'][0] ?? [], 'url' ), 'copia mai scaricata, con testo: card senza link' );
	update_option( $opzione, $copia, false );

	// 9. Limite per visitatore: FORESTAS_ENTES_SEARCH_LIMIT ricerche con un testo per finestra, poi 429 con Retry-After e
	// senza chiamare lo shard; un altro visitatore no; il testo vuoto non conta
	$risponde( 200, $trovati );
	$_SERVER['REMOTE_ADDR'] = $ip_test[0];
	$stati                  = [];
	for ( $i = 0; $i < FORESTAS_ENTES_SEARCH_LIMIT; $i++ ) {
		$stati[] = $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status();
	}
	$check( [ 200 ] === array_values( array_unique( $stati ) ) && FORESTAS_ENTES_SEARCH_LIMIT === $finto['richieste'], 'entro il limite: ' . FORESTAS_ENTES_SEARCH_LIMIT . ' ricerche con 200 (' . $finto['richieste'] . ' richieste)' );
	$oltre = $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] );
	$retry = (int) ( $oltre->get_headers()['Retry-After'] ?? 0 );
	$check( 429 === $oltre->get_status(), 'oltre il limite: 429 (' . $oltre->get_status() . ')' );
	$check( $retry >= 1 && $retry <= FORESTAS_ENTES_SEARCH_WINDOW, 'oltre il limite: Retry-After fra 1 e ' . FORESTAS_ENTES_SEARCH_WINDOW . ' (' . $retry . ')' );
	$check( 'forestas_entes_rate_limited' === $campo( $oltre->get_data(), 'code' ) && is_string( $campo( $oltre->get_data(), 'message' ) ), 'oltre il limite: codice e messaggio' );
	$check( FORESTAS_ENTES_SEARCH_LIMIT === $finto['richieste'], 'oltre il limite: nessuna richiesta allo shard (' . $finto['richieste'] . ')' );
	$check( 200 === $cerca( [ 'lang' => 'it' ] )->get_status(), 'oltre il limite, testo vuoto: 200 (non conta e non chiama lo shard)' );
	$_SERVER['REMOTE_ADDR'] = $ip_test[1];
	$check( 200 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status(), 'un altro visitatore: 200' );
	// Finestra scaduta: si riparte da zero
	$chiave = forestas_entes_search_visitor_key( $ip_test[0] );
	update_option( $conti, [ $chiave => [ FORESTAS_ENTES_SEARCH_LIMIT, time() - FORESTAS_ENTES_SEARCH_WINDOW ] ], false );
	$_SERVER['REMOTE_ADDR'] = $ip_test[0];
	$check( 200 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status(), 'finestra scaduta: di nuovo 200' );
	$check( 1 === ( get_option( $conti )[ $chiave ][0] ?? null ), 'finestra scaduta: il contatore riparte da 1' );
	$check( false === strpos( (string) wp_json_encode( get_option( $conti ) ), $ip_test[0] ) && false === strpos( $chiave, $ip_test[0] ), 'l\'IP non è nel database (impronta)' );
	$autoload = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", $conti ) );
	$check( in_array( $autoload, [ 'off', 'no' ], true ), 'contatori in un\'opzione non autoload (' . $autoload . ')' );

	// 9b. Contatori: a ogni scrittura escono le finestre chiuse degli altri visitatori; oltre il tetto restano i visitatori
	// con la finestra più recente, e chi cerca adesso; oltre il limite nessuna scrittura
	$risponde( 200, $trovati );
	$aperta = [ 3, time() - 10 ];
	update_option(
		$conti,
		[
			'chiusa' => [ 5, time() - FORESTAS_ENTES_SEARCH_WINDOW - 5 ],
			'aperta' => $aperta,
			'rotta'  => 'x',
		],
		false
	);
	$_SERVER['REMOTE_ADDR'] = $ip_test[1];
	$check( 200 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status(), 'contatori con finestre chiuse: 200' );
	$ora = get_option( $conti );
	$check( ! isset( $ora['chiusa'] ) && ! isset( $ora['rotta'] ) && $aperta === ( $ora['aperta'] ?? null ) && [ 1 ] === array_slice( $ora[ forestas_entes_search_visitor_key( $ip_test[1] ) ] ?? [], 0, 1 ), 'finestre chiuse e voci non valide tolte, le aperte restano: ' . wp_json_encode( $ora ) );
	$pieni = [];
	for ( $i = 0; $i < FORESTAS_ENTES_SEARCH_VISITORS; $i++ ) {
		$pieni[ 'v' . $i ] = [ 1, time() - ( $i % 50 ) ];
	}
	$pieni['vecchia'] = [ 1, time() - FORESTAS_ENTES_SEARCH_WINDOW + 1 ];
	update_option( $conti, $pieni, false );
	$check( 200 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status(), 'contatori pieni: 200' );
	$ora = get_option( $conti );
	$check( FORESTAS_ENTES_SEARCH_VISITORS === count( $ora ) && ! isset( $ora['vecchia'] ) && isset( $ora[ forestas_entes_search_visitor_key( $ip_test[1] ) ] ), 'oltre il tetto: ' . FORESTAS_ENTES_SEARCH_VISITORS . ' visitatori, fuori la finestra più vecchia, dentro chi cerca (' . count( $ora ) . ')' );
	$check( strlen( serialize( $ora ) ) < 200000, 'contatori pieni sotto i 200 KB (' . strlen( serialize( $ora ) ) . ' byte)' );
	update_option( $conti, [ forestas_entes_search_visitor_key( $ip_test[1] ) => [ FORESTAS_ENTES_SEARCH_LIMIT, time() ] ], false );
	$scritture = 0;
	$scrive    = function () use ( &$scritture ) {
		++$scritture;
	};
	add_action( 'update_option_' . $conti, $scrive );
	$check( 429 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && 0 === $scritture, 'oltre il limite: nessuna scrittura dei contatori (' . $scritture . ')' );
	remove_action( 'update_option_' . $conti, $scrive );
	$_SERVER['REMOTE_ADDR'] = $ip_test[0];

	// 10. Pausa dopo un errore della ricerca: per FORESTAS_ENTES_SEARCH_PAUSE secondi 503 con Retry-After senza chiamare
	// lo shard, e una sola riga di log per pausa
	file_put_contents( $file_log, '' );
	$risponde( 503, [ 'message' => 'Indice degli enti assente' ] );
	$check( 503 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && 1 === $finto['richieste'], 'errore dello shard: 503 e una richiesta' );
	$finto['codice'] = 200;
	$finto['corpo']  = (string) wp_json_encode( $trovati );
	$durante         = $cerca( [ 'lang' => 'it', 'q' => 'tepilor' ] );
	$retry           = (int) ( $durante->get_headers()['Retry-After'] ?? 0 );
	$check( 503 === $durante->get_status() && 1 === $finto['richieste'], 'durante la pausa: 503 senza chiamare lo shard (' . $finto['richieste'] . ' richieste)' );
	$check( $retry >= 1 && $retry <= FORESTAS_ENTES_SEARCH_PAUSE, 'durante la pausa: Retry-After con i secondi che restano (' . $retry . ')' );
	$cerca( [ 'lang' => 'it', 'q' => 'tepilo' ] );
	$righe = array_values( array_filter( explode( "\n", $log() ), fn( $r ) => false !== strpos( $r, 'forestas enti:' ) ) );
	$check( 1 === count( $righe ) && false !== strpos( $righe[0], 'per ' . FORESTAS_ENTES_SEARCH_PAUSE . ' secondi' ), 'durante la pausa: una sola riga di log: ' . implode( ' | ', $righe ) );
	$check( 200 === $cerca( [ 'lang' => 'it' ] )->get_status(), 'durante la pausa, testo vuoto: 200 dalla copia' );
	// Fine della pausa: si chiama di nuovo lo shard
	delete_transient( $pausa );
	$check( 200 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && 2 === $finto['richieste'], 'dopo la pausa: lo shard di nuovo chiamato' );
	// 429 dello shard (limite al minuto di tutto il sito): stessa pausa, con il motivo nel log
	file_put_contents( $file_log, '' );
	$risponde( 429, [ 'message' => 'Too Many Attempts.' ] );
	$check( 503 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && forestas_entes_search_paused() > 0, 'shard 429: 503 e pausa' );
	$check( false !== strpos( $log(), 'codice 429 (troppe richieste' ), 'shard 429: il codice e il motivo nel log: ' . $log() );
	$check( 503 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && 1 === $finto['richieste'], 'shard 429: durante la pausa nessuna richiesta' );
	// Errore di rete
	$risponde( 200, $trovati );
	$rete = fn( $pre, $args, $url ) => false !== strpos( (string) $url, '/api/v1/entes' ) ? new WP_Error( 'http_request_failed', 'cURL error 28: timeout' ) : $pre;
	add_filter( 'pre_http_request', $rete, 20, 3 );
	file_put_contents( $file_log, '' );
	$check( 503 === $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] )->get_status() && forestas_entes_search_paused() > 0, 'errore di rete: 503 e pausa' );
	$check( false !== strpos( $log(), 'codice 0 (shard non raggiungibile: cURL error 28' ), 'errore di rete: il motivo nel log: ' . $log() );
	remove_filter( 'pre_http_request', $rete, 20 );

	// 11. IP del visitatore: X-Forwarded-For solo dietro un REMOTE_ADDR privato o di loopback (il proxy dell'host), l'ultimo
	// indirizzo pubblico da destra
	$ip = function ( string $remote, ?string $xff ): string {
		$_SERVER['REMOTE_ADDR'] = $remote;
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		if ( null !== $xff ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $xff;
		}
		return forestas_entes_visitor_ip();
	};
	$casi_ip = [
		// [ REMOTE_ADDR, X-Forwarded-For, atteso ]
		'proxy di Docker, un IP'                    => [ '172.27.0.1', '203.0.113.9', '203.0.113.9' ],
		'proxy di Docker, IP scritto dal visitatore' => [ '172.27.0.1', '198.51.100.66, 203.0.113.9', '203.0.113.9' ],
		'loopback, proxy privati in coda'           => [ '127.0.0.1', '203.0.113.9, 10.0.0.2', '203.0.113.9' ],
		'proxy privato senza X-Forwarded-For'        => [ '172.27.0.1', null, '172.27.0.1' ],
		'proxy privato, X-Forwarded-For non valido'  => [ '172.27.0.1', 'pippo, 10.0.0.3', '172.27.0.1' ],
		'REMOTE_ADDR pubblico: X-Forwarded-For ignorato' => [ '198.51.100.4', '203.0.113.9', '198.51.100.4' ],
		'IPv6 di loopback'                          => [ '::1', '2001:db8::5', '2001:db8::5' ],
		'REMOTE_ADDR non valido'                    => [ 'x', '203.0.113.9', '' ],
	];
	foreach ( $casi_ip as $caso => [ $remote, $xff, $atteso ] ) {
		$trovato = $ip( $remote, $xff );
		$check( $atteso === $trovato, "IP del visitatore, {$caso}: «{$trovato}»" );
	}
	// Due visitatori dietro lo stesso proxy hanno contatori diversi
	$risponde( 200, $trovati );
	$ip( '172.27.0.1', $ip_test[0] );
	for ( $i = 0; $i < FORESTAS_ENTES_SEARCH_LIMIT; $i++ ) {
		$cerca( [ 'lang' => 'it', 'q' => 'x' ] );
	}
	$check( 429 === $cerca( [ 'lang' => 'it', 'q' => 'x' ] )->get_status(), 'dietro il proxy: il primo visitatore oltre il limite' );
	$ip( '172.27.0.1', $ip_test[1] );
	$check( 200 === $cerca( [ 'lang' => 'it', 'q' => 'x' ] )->get_status(), 'dietro il proxy: il secondo visitatore no' );
	unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
	$_SERVER['REMOTE_ADDR'] = $ip_test[0];

	// 12. Testo rifiutato dallo shard (422 di validazione, 400): nessun risultato, senza pausa né log, e la ricerca dopo
	// chiama di nuovo lo shard. Un testo fatto apposta non deve fermare la ricerca per tutti
	foreach ( [ 422 => [ 'message' => 'The query field is required.', 'errors' => [ 'query' => [ 'The query field is required.' ] ] ], 400 => [ 'message' => 'Bad Request' ] ] as $codice => $corpo ) {
		file_put_contents( $file_log, '' );
		$risponde( $codice, $corpo );
		$risposta = $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] );
		$check( 200 === $risposta->get_status() && [ 'total' => 0, 'items' => [], 'facets' => [ 'tipo-ente' => [], 'ruolo' => [] ] ] === $risposta->get_data(), "shard {$codice}: nessun risultato (" . $risposta->get_status() . ' ' . wp_json_encode( $risposta->get_data() ) . ')' );
		$check( false === get_transient( $pausa ) && 0 === forestas_entes_search_paused(), "shard {$codice}: nessuna pausa" );
		$check( '' === $log(), "shard {$codice}: nessuna riga di log: " . $log() );
		$cerca( [ 'lang' => 'it', 'q' => 'tepilor' ] );
		$check( 2 === $finto['richieste'], "shard {$codice}: la ricerca dopo chiama di nuovo lo shard (" . $finto['richieste'] . ' richieste)' );
	}
	$check( forestas_entes_search_refused( 422 ) && forestas_entes_search_refused( 400 ) && ! forestas_entes_search_refused( 401 ) && ! forestas_entes_search_refused( 403 ) && ! forestas_entes_search_refused( 429 ) && ! forestas_entes_search_refused( 404 ) && ! forestas_entes_search_refused( 410 ) && ! forestas_entes_search_refused( 0 ) && ! forestas_entes_search_refused( 500 ) && ! forestas_entes_search_refused( 200 ), 'forestas_entes_search_refused(): solo i 4xx della validazione' );

	// 12a. Rotta mancante sullo shard (404, come su uno shard senza oc:8965): guasto, con la pausa e il messaggio nel tab
	delete_transient( $pausa );
	file_put_contents( $file_log, '' );
	$finto['richieste'] = 0;
	$risponde( 404, [ 'message' => 'Not Found' ] );
	$risposta = $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] );
	$check( 503 === $risposta->get_status() && forestas_entes_search_paused() > 0, 'shard 404: 503 con la pausa (' . $risposta->get_status() . ')' );
	$check( '' !== $log(), 'shard 404: una riga di log' );
	$cerca( [ 'lang' => 'it', 'q' => 'tepilor' ] );
	$check( 1 === $finto['richieste'], 'shard 404: nella pausa nessuna nuova richiesta (' . $finto['richieste'] . ')' );
	delete_transient( $pausa );
	$finto['richieste'] = 0;

	// 12b. Testo di soli caratteri invisibili o di spaziatura: testo vuoto, senza chiamare lo shard (Laravel lo
	// renderebbe null e risponderebbe 422); i caratteri in testa e in coda si tolgono anche da un testo vero
	$risponde( 200, $trovati );
	foreach ( [ "\u{200B}", "\u{00A0}", "\u{FEFF}", "\u{00A0}\u{200B} \u{FEFF}\u{3164}" ] as $invisibile ) {
		$risposta = $cerca( [ 'lang' => 'it', 'q' => $invisibile ] );
		$check( 200 === $risposta->get_status() && 4 === $campo( $risposta->get_data(), 'total' ), 'testo ' . wp_json_encode( $invisibile ) . ': gli enti della copia (' . $risposta->get_status() . ')' );
	}
	$check( 0 === $finto['richieste'], 'testi invisibili: nessuna richiesta allo shard (' . $finto['richieste'] . ')' );
	$cerca( [ 'lang' => 'it', 'q' => "\u{200B}\u{00A0}tepilora\u{FEFF}" ] );
	$check( 'https://interno.example/api/v1/entes/search?query=tepilora' === $finto['url'], 'caratteri invisibili in testa e in coda tolti: ' . $finto['url'] );
	$check( "a\u{200B}b" === forestas_entes_search_text( "a\u{200B}b" ) && 'x y' === forestas_entes_search_text( "\u{00A0}x y\u{2060}" ), 'forestas_entes_search_text(): tolti solo in testa e in coda' );
	// La rotta vera, con l'indirizzo come lo manda il browser (%E2%80%8B): testo vuoto e nessuna pausa
	$richiesta = WP_REST_Request::from_url( rest_url( 'forestas/v1/entes-search' ) . '?q=%E2%80%8B&lang=it' );
	$risposta  = rest_ensure_response( rest_do_request( $richiesta ) );
	$check( 200 === $risposta->get_status() && 4 === $campo( $risposta->get_data(), 'total' ) && 0 === forestas_entes_search_paused(), '?q=%E2%80%8B: testo vuoto, senza pausa' );

	// 13. Filtri «Tipo di ente» e «Ruolo sui sentieri» (oc:9000). Ogni filtro conta gli enti senza il proprio filtro e con
	// l'altro (come la rotta dei contenuti); valori con almeno un ente o scelti, per numero e poi per nome; tipi e ruoli
	// senza nome fuori; valori sconosciuti nell'indirizzo ignorati. Il ruolo di un ente: quelli dei suoi sentieri nella copia
	$voci = fn( WP_REST_Response $r, string $chiave ) => array_map(
		fn( $v ) => [ $v['id'] ?? null, $v['label'] ?? null, $v['count'] ?? null, $v['selected'] ?? null ],
		(array) ( $r->get_data()['facets'][ $chiave ] ?? [ 'assente' ] )
	);
	$risponde( 200, $trovati );
	$risposta = $cerca( [ 'lang' => 'it' ] );
	$check(
		[ [ 'complesso-forestale', 'Complesso forestale', 1, false ], [ 'comune', 'Comune', 1, false ], [ 'ente-partner', 'Ente partner', 1, false ] ] === $voci( $risposta, 'tipo-ente' ),
		'filtri, testo vuoto: voci del tipo di ente ' . wp_json_encode( $voci( $risposta, 'tipo-ente' ) )
	);
	$check(
		[ [ 'gestore', 'Soggetto gestore', 2, false ], [ 'manutentore', 'Soggetto manutentore', 1, false ], [ 'rilevatore', 'Soggetto rilevatore', 1, false ] ] === $voci( $risposta, 'ruolo' ),
		'filtri, testo vuoto: voci del ruolo ' . wp_json_encode( $voci( $risposta, 'ruolo' ) )
	);
	$risposta = $cerca( [ 'lang' => 'it', 'ruolo' => 'gestore' ] );
	$check( 2 === $campo( $risposta->get_data(), 'total' ) && [ 11, 13 ] === $ids( $risposta ), 'ruolo gestore: Tepilora e Agenzia (' . implode( ', ', $ids( $risposta ) ) . ')' );
	$check( [ [ 'complesso-forestale', 'Complesso forestale', 1, false ] ] === $voci( $risposta, 'tipo-ente' ), 'ruolo gestore: il tipo conta solo i gestori ' . wp_json_encode( $voci( $risposta, 'tipo-ente' ) ) );
	$check(
		[ [ 'gestore', 'Soggetto gestore', 2, true ], [ 'manutentore', 'Soggetto manutentore', 1, false ], [ 'rilevatore', 'Soggetto rilevatore', 1, false ] ] === $voci( $risposta, 'ruolo' ),
		'ruolo gestore: il ruolo conta senza il proprio filtro, gestore scelto ' . wp_json_encode( $voci( $risposta, 'ruolo' ) )
	);
	$risposta = $cerca( [ 'lang' => 'it', 'tipo-ente' => 'ente-partner,comune' ] );
	$check( [ 12, 15 ] === $ids( $risposta ), 'tipo comune o ente partner: Stintino e GAL (' . implode( ', ', $ids( $risposta ) ) . ')' );
	$check( [ [ 'rilevatore', 'Soggetto rilevatore', 1, false ] ] === $voci( $risposta, 'ruolo' ), 'tipo comune o ente partner: il ruolo conta solo quelli ' . wp_json_encode( $voci( $risposta, 'ruolo' ) ) );
	$risposta = $cerca( [ 'lang' => 'it', 'tipo-ente' => 'comune', 'ruolo' => 'gestore' ] );
	$check( 0 === $campo( $risposta->get_data(), 'total' ), 'comune e gestore: nessun ente' );
	$check( [ [ 'complesso-forestale', 'Complesso forestale', 1, false ], [ 'comune', 'Comune', 0, true ] ] === $voci( $risposta, 'tipo-ente' ), 'comune e gestore: la voce scelta resta con 0 ' . wp_json_encode( $voci( $risposta, 'tipo-ente' ) ) );
	$risposta = $cerca( [ 'lang' => 'it', 'ruolo' => 'inventato', 'tipo-ente' => 'boh' ] );
	$check( 4 === $campo( $risposta->get_data(), 'total' ), 'valori sconosciuti nell\'indirizzo: ignorati' );
	// Con un testo: il tipo dalla ricerca dello shard, i ruoli dalla copia (un ente non ancora nella copia non ne ha)
	$risposta = $cerca( [ 'lang' => 'it', 'q' => 'tepilora' ] );
	$check( [ [ 'complesso-forestale', 'Complesso forestale', 1, false ], [ 'comune', 'Comune', 1, false ] ] === $voci( $risposta, 'tipo-ente' ), 'tepilora: voci del tipo ' . wp_json_encode( $voci( $risposta, 'tipo-ente' ) ) );
	$check( [ [ 'gestore', 'Soggetto gestore', 1, false ], [ 'manutentore', 'Soggetto manutentore', 1, false ] ] === $voci( $risposta, 'ruolo' ), 'tepilora: ruoli dalla copia ' . wp_json_encode( $voci( $risposta, 'ruolo' ) ) );
	$risposta = $cerca( [ 'lang' => 'it', 'q' => 'tepilora', 'ruolo' => 'gestore' ] );
	$check( [ 11 ] === $ids( $risposta ), 'tepilora e gestore: solo Tepilora (' . implode( ', ', $ids( $risposta ) ) . ')' );
	$risposta = $cerca( [ 'lang' => 'en' ] );
	$check( 'Municipality' === ( $voci( $risposta, 'tipo-ente' )[1][1] ?? null ) && 'Managing authority' === ( $voci( $risposta, 'ruolo' )[0][1] ?? null ), 'filtri in inglese' );
	$lingua( 'it' );
	// Parametri nell'indirizzo canonico, con i valori in ordine
	$canonici = forestas_search_canonical_args( [ 'tipo' => 'enti', 'ruolo' => 'rilevatore,gestore', 'tipo-ente' => 'comune' ] );
	$check( [ 'tipo' => 'enti', 'tipo-ente' => 'comune', 'ruolo' => rawurlencode( 'gestore,rilevatore' ) ] === $canonici, 'canonical con i filtri degli enti: ' . wp_json_encode( $canonici ) );

	// Il token non deve mai finire nell'output del test
	$failures = array_map( fn( $f ) => str_replace( $token, '<token>', $f ), $failures );
} finally {
	restore_error_handler();
	$rimetti();
}

// Lo stato è stato rimesso com'era
$check( $prima['copia'] === get_option( $opzione, null ), 'opzione rimessa com\'era' );
$check( $prima['slug'] === get_option( $slug_op, null ) && $prima['stato'] === get_option( $stato, null ), 'opzioni degli slug e dello stato rimesse com\'erano' );
$check( $prima['attesa'] === get_transient( $attesa ), 'transient rimesso com\'era' );
$check( $prima['token'] === getenv( 'WP_API_TOKEN' ), 'variabile d\'ambiente rimessa com\'era' );
$check( $prima['lingua'] === (string) apply_filters( 'wpml_current_language', 'it' ), 'lingua di WPML rimessa com\'era' );

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
