<?php
/**
 * Test (oc:8990): copia del JSON degli enti (inc/entes.php), con lo shard finto (pre_http_request).
 * Lettura con il token e dall'origine del server, token solo in https o verso un host locale, copia salvata non
 * autoload con le sole chiavi lette dal child e riscritta solo quando cambia, opzioni piccole degli slug e dello stato,
 * ultima copia buona su risposta vuota, dimezzata o in errore (401, 429), calo accettato dopo 3 ore di rifiuti, riga di
 * log senza il token, attesa di 5 minuti dopo un fallimento senza copia, nessuna chiamata senza token, copia letta senza
 * chiamare lo shard, rinnovo da una pagina quando il rinnovo è scaduto (lasciato a WP-Cron appena partito, per
 * FORESTAS_ENTES_CRON_WAIT secondi), un rinnovo alla volta con il lucchetto atomico (anche abbandonato), opzione degli
 * slug riallineata anche con gli stessi dati, ricerca per slug, evento WP-Cron ogni 15 minuti (tolto cambiando tema),
 * una riga di log per copia nuova con i tipi di ente sconosciuti e con gli slug doppi.
 * Rimette copia, opzioni degli slug e dello stato, lucchetto, transient (compreso `doing_cron` del core), evento WP-Cron,
 * variabile d'ambiente e file di log com'erano.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/entes-copy.php
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

if ( ! function_exists( 'forestas_entes_refresh' ) ) {
	echo "FAIL: forestas_entes_refresh non esiste\n";
	exit( 1 );
}

// Nomi dell'interfaccia scritti per esteso: il test controlla anche le costanti
$opzione = 'forestas_entes_copy';
$slug_op = 'forestas_entes_slugs';
$stato   = 'forestas_entes_state';
$attesa  = 'forestas_entes_retry';
$evento  = 'forestas_entes_refresh';
$chiave  = 'forestas_entes_refresh_lock';
$token   = 'token-di-prova-8990-' . wp_generate_password( 12, false );
// Il lucchetto non passa da get_option(): si legge dalla tabella
$lucchetto = function () use ( $chiave ) {
	global $wpdb;
	return $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $chiave ) );
};
$metti_lucchetto = function ( ?string $valore ) use ( $chiave ) {
	global $wpdb;
	$wpdb->delete( $wpdb->options, [ 'option_name' => $chiave ] );
	if ( null !== $valore ) {
		$wpdb->insert( $wpdb->options, [ 'option_name' => $chiave, 'option_value' => $valore, 'autoload' => 'off' ] );
	}
};

// Stato da rimettere alla fine
$prima = [
	'copia'    => get_option( $opzione, null ),
	'slug'     => get_option( $slug_op, null ),
	'stato'    => get_option( $stato, null ),
	'attesa'   => get_transient( $attesa ),
	'scadenza' => (int) get_option( '_transient_timeout_' . $attesa, 0 ),
	'evento'   => wp_next_scheduled( $evento ),
	'lucchetto' => $lucchetto(),
	'cron'     => get_transient( 'doing_cron' ),
	'token'    => getenv( 'WP_API_TOKEN' ),
	'log'      => ini_get( 'error_log' ),
];
$file_log = tempnam( sys_get_temp_dir(), 'entes-log-' );

// Shard finto: risponde solo alle richieste degli enti, conta le richieste, ricorda indirizzo e intestazione del token.
// `durante`: funzione chiamata una volta mentre la richiesta è «in corso» (un'altra visita nello stesso momento)
$finto     = [ 'codice' => 200, 'corpo' => '', 'richieste' => 0, 'url' => '', 'auth' => '', 'durante' => null ];
$risponde  = function ( int $codice, $json ) use ( &$finto ) {
	$finto['codice']    = $codice;
	$finto['corpo']     = is_string( $json ) ? $json : wp_json_encode( $json );
	$finto['richieste'] = 0;
	$finto['url']       = '';
	$finto['auth']      = '';
	$finto['durante']   = null;
};
$http      = function ( $pre, $args, $url ) use ( &$finto ) {
	if ( false === strpos( (string) $url, '/api/v1/entes' ) ) {
		return $pre;
	}
	++$finto['richieste'];
	$finto['url']  = (string) $url;
	$finto['auth'] = (string) ( $args['headers']['Authorization'] ?? '' );
	if ( null !== $finto['durante'] ) {
		// Una volta sola: se la visita «concorrente» richiamasse lo shard non si rientra all'infinito
		$durante          = $finto['durante'];
		$finto['durante'] = null;
		$durante();
	}
	return [
		'headers'  => [],
		'body'     => $finto['corpo'],
		'response' => [ 'code' => $finto['codice'], 'message' => '' ],
		'cookies'  => [],
		'filename' => null,
	];
};
$origine   = fn() => [ 'elastic_api' => 'https://shard.example/api/v2/elasticsearch', 'origin' => 'https://shard.example', 'aws_api' => '' ];
$dal_server_url = 'https://interno.example';
$dal_server     = function () use ( &$dal_server_url ) {
	return $dal_server_url;
};

// Enti finti nella forma di api/v1/entes: una traccia per collegamento ente-traccia
$ente = function ( int $id, string $slug, int $tracce = 1 ): array {
	return [
		'id'     => $id,
		'slug'   => $slug,
		'name'   => [ 'it' => 'Ente ' . $id, 'en' => 'Ente ' . $id ],
		'type'   => 'comune',
		'tracks' => array_map(
			fn( $i ) => [ 'id' => $id * 100 + $i, 'ref' => null, 'name' => [ 'it' => 'Traccia ' . $i ], 'roles' => [ 'gestore' ], 'created_at' => '2020-01-01 00:00:00' ],
			$tracce > 0 ? range( 1, $tracce ) : []
		),
		'pois'   => [],
	];
};
$json = fn( array $enti ) => [ 'generated_at' => '2026-11-16T10:00:00+00:00', 'data' => $enti ];
$tre  = [ $ente( 1, 'agenzia-forestas', 2 ), $ente( 2, 'comune-di-stintino', 3 ), $ente( 3, 'gal-bmg', 0 ) ];
$log  = function () use ( $file_log ): string {
	clearstatcache();
	return (string) file_get_contents( $file_log );
};
$azzera = function () use ( $opzione, $stato, $attesa, $file_log, $metti_lucchetto ) {
	delete_option( $opzione );
	delete_option( $stato );
	delete_transient( $attesa );
	$metti_lucchetto( null );
	file_put_contents( $file_log, '' );
	// Una richiesta nuova: copia della richiesta e tentativo di rinnovo dimenticati
	forestas_entes_copy( true );
};
// Righe del log degli enti
$righe_log = fn() => array_values( array_filter( explode( "\n", $log() ), fn( $r ) => false !== strpos( $r, 'forestas enti: ' ) ) );
// Scritture della copia (add_option/update_option)
$scritture = 0;
$scrive    = function () use ( &$scritture ) {
	++$scritture;
};
// Letture dell'opzione dal database (get_option), per la copia ricordata nella richiesta
$letture = 0;
$conta   = function ( $valore ) use ( &$letture ) {
	++$letture;
	return $valore;
};

// Rimette il database com'era: alla fine e, se il test si ferma con un errore fatale, alla chiusura di PHP
$rimesso = false;
$rimetti = function () use ( &$rimesso, $prima, $opzione, $slug_op, $stato, $attesa, $evento, $file_log, $http, $origine, $dal_server, $conta, $scrive, $metti_lucchetto ) {
	if ( $rimesso ) {
		return;
	}
	$rimesso = true;
	remove_filter( 'pre_http_request', $http, 10 );
	remove_filter( 'option_' . $opzione, $conta );
	remove_action( 'add_option_' . $opzione, $scrive );
	remove_action( 'update_option_' . $opzione, $scrive );
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
	wp_clear_scheduled_hook( $evento );
	if ( false !== $prima['evento'] ) {
		wp_schedule_event( $prima['evento'], 'forestas_quarter_hour', $evento );
	}
	$metti_lucchetto( $prima['lucchetto'] );
	delete_transient( 'doing_cron' );
	if ( false !== $prima['cron'] ) {
		set_transient( 'doing_cron', $prima['cron'] );
	}
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
// Senza WP-Cron appena partito: le pagine non lasciano il rinnovo a WP-Cron, salvo nei casi del punto 9b
delete_transient( 'doing_cron' );

try {
	// 0. Costanti dell'interfaccia e intervallo di 15 minuti
	$check( defined( 'FORESTAS_ENTES_OPTION' ) && $opzione === FORESTAS_ENTES_OPTION, 'FORESTAS_ENTES_OPTION' );
	$check( defined( 'FORESTAS_ENTES_RETRY' ) && $attesa === FORESTAS_ENTES_RETRY, 'FORESTAS_ENTES_RETRY' );
	$check( defined( 'FORESTAS_ENTES_REFRESH_HOOK' ) && $evento === FORESTAS_ENTES_REFRESH_HOOK, 'FORESTAS_ENTES_REFRESH_HOOK' );
	$check( defined( 'FORESTAS_ENTES_INTERVAL' ) && 900 === FORESTAS_ENTES_INTERVAL, 'FORESTAS_ENTES_INTERVAL = 900' );
	$check( defined( 'FORESTAS_ENTES_RETRY_AFTER' ) && 300 === FORESTAS_ENTES_RETRY_AFTER, 'FORESTAS_ENTES_RETRY_AFTER = 300' );
	$check( 900 === ( wp_get_schedules()['forestas_quarter_hour']['interval'] ?? null ), 'intervallo forestas_quarter_hour di 900 secondi' );

	// 1. Risposta 200 con 3 enti: copia salvata, lettura dall'origine del server con il token; stato e slug nelle
	// opzioni piccole
	$azzera();
	$risponde( 200, $json( $tre ) );
	$check( true === forestas_entes_refresh(), 'rinnovo con 3 enti → true' );
	$check( 1 === $finto['richieste'], 'una richiesta allo shard' );
	$check( 'https://interno.example/api/v1/entes' === $finto['url'], 'indirizzo dall\'origine dello shard vista dal server (wmwp_server_url)' );
	$check( 'Bearer ' . $token === $finto['auth'], 'intestazione Authorization con il token di prova' );
	$tutti = forestas_entes_all();
	$check( is_array( $tutti ) && 3 === count( $tutti ), 'forestas_entes_all() ha 3 enti' );
	$check( 1 === $finto['richieste'], 'con la copia forestas_entes_all() non chiama lo shard' );
	$copia = get_option( $opzione );
	$check( is_array( $copia ) && [ 'data' ] === array_keys( $copia ), 'copia con la sola chiave data: ' . wp_json_encode( is_array( $copia ) ? array_keys( $copia ) : $copia ) );
	$stato_ora = get_option( $stato );
	$check( '2026-11-16T10:00:00+00:00' === ( $stato_ora['generated_at'] ?? null ), 'stato: generated_at della risposta' );
	$check( is_string( $stato_ora['fetched_at'] ?? null ) && $stato_ora['fetched_at'] === ( $stato_ora['checked_at'] ?? null ), 'stato: fetched_at e checked_at scritti' );
	$check( ! isset( $stato_ora['refused_since'] ), 'stato: nessun rifiuto in corso' );
	$check( [ 'agenzia-forestas' => true, 'comune-di-stintino' => true, 'gal-bmg' => true ] === get_option( $slug_op ), 'opzione degli slug scritta con la copia: ' . wp_json_encode( get_option( $slug_op ) ) );
	$check( get_option( $slug_op ) === forestas_entes_known_slugs(), 'forestas_entes_known_slugs() dall\'opzione degli slug' );
	foreach ( [ $opzione, $slug_op, $stato ] as $nome ) {
		$autoload = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", $nome ) );
		$check( in_array( $autoload, [ 'no', 'off' ], true ), "{$nome} non autoload (autoload = " . var_export( $autoload, true ) . ')' );
	}
	$check( false === get_transient( $attesa ), 'dopo un rinnovo riuscito nessuna attesa' );

	// 1-bis. Solo le chiavi lette dal child: fuori pois, geometry, chiavi sconosciute, misure delle foto non usate e campi
	// delle tracce che la card non legge
	$foto_api   = fn( string $n ) => [
		'id'        => 1,
		'name'      => [ 'it' => $n ],
		'url'       => "https://img.example/{$n}.jpg",
		'thumbnail' => "https://img.example/{$n}-t.jpg",
		'api_url'   => 'https://shard.example/api/media/1',
		'sizes'     => [ '108x148' => 'x', '400x200' => "https://img.example/{$n}-400.jpg", '1440x500' => "https://img.example/{$n}-1440.jpg", '1920x1080' => 'y' ],
		'caption'   => $n,
		'author'    => 'A',
		'credits'   => null,
	];
	$completo   = array_merge(
		$ente( 1, 'agenzia-forestas', 2 ),
		[
			'description'   => [ 'it' => '<p>D</p>' ],
			'contacts'      => [],
			'website'       => [ 'url' => 'https://x.example', 'title' => 'X' ],
			'related_url'   => [],
			'feature_image' => $foto_api( 'fi' ),
			'image_gallery' => [ $foto_api( 'g1' ) ],
			'created_at'    => '2015-01-01 00:00:00',
			'updated_at'    => '2024-01-01 00:00:00',
			'geometry'      => [ 'type' => 'Point' ],
			'pois'          => [ [ 'id' => 9, 'roles' => [ 'operatore' ] ] ],
			'chiave_nuova'  => 'x',
		]
	);
	$completo['tracks'][0] += [ 'validation_status' => 'validation-status-validated', 'route_type' => [ 'id' => 3, 'name' => [ 'it' => 'Sentiero' ], 'identifier' => 'route-type-trail' ], 'feature_image' => $foto_api( 't1' ), 'distance' => 5.2, 'duration_forward' => 90 ];
	$snella = forestas_entes_slim( [ $completo ] )[0];
	$chiavi = array_keys( $snella );
	sort( $chiavi );
	$attese = FORESTAS_ENTES_KEYS;
	sort( $attese );
	$check( $attese === $chiavi, 'ente snello: solo le chiavi lette dal child: ' . implode( ', ', $chiavi ) );
	$chiavi = array_keys( $snella['feature_image'] );
	sort( $chiavi );
	$check( [ 'author', 'caption', 'credits', 'sizes', 'thumbnail', 'url' ] === $chiavi && [ '400x200', '1440x500' ] === array_keys( $snella['feature_image']['sizes'] ), 'immagine principale: indirizzo, miniatura, misure 400x200 e 1440x500, testi: ' . wp_json_encode( $snella['feature_image'] ) );
	$check( [ '400x200' ] === array_keys( $snella['image_gallery'][0]['sizes'] ?? [] ) && 'g1' === ( $snella['image_gallery'][0]['caption'] ?? null ), 'galleria: misura 400x200 e testi' );
	$traccia = $snella['tracks'][0];
	$check( ! isset( $traccia['validation_status'] ) && [ 'thumbnail' => 'https://img.example/t1-t.jpg' ] === $traccia['feature_image'] && [ 'name' => [ 'it' => 'Sentiero' ], 'identifier' => 'route-type-trail' ] === $traccia['route_type'] && 5.2 === $traccia['distance'] && 90 === $traccia['duration_forward'] && [ 'gestore' ] === $traccia['roles'], 'traccia snella: campi della card, ruoli e data: ' . wp_json_encode( $traccia ) );
	$check( null === forestas_entes_slim( [ array_merge( $ente( 2, 'b' ), [ 'feature_image' => null ] ) ] )[0]['feature_image'], 'immagine assente: resta null' );
	$operatore = forestas_entes_slim( [ array_merge( $ente( 3, 'c' ), [ 'tracks' => [ [ 'id' => 7, 'name' => [ 'it' => 'T' ], 'roles' => [ 'operatore', 'rilevatore' ], 'feature_image' => $foto_api( 'o' ), 'created_at' => '2020-01-01' ] ] ] ) ] )[0];
	$check( [ [ 'id' => 7, 'roles' => [ 'operatore', 'rilevatore' ] ] ] === $operatore['tracks'], 'traccia senza il ruolo gestore: solo id e ruoli: ' . wp_json_encode( $operatore['tracks'] ) );
	$check( 1 === forestas_entes_counts( [ $operatore ] )[1], 'traccia senza il ruolo gestore: conta per i collegamenti' );
	// La card e il dettaglio dall'ente snello sono quelli dall'ente completo
	$check( forestas_entes_view( $completo, 'it' ) === forestas_entes_view( $snella, 'it' ), 'dettaglio uguale dall\'ente snello' );
	$check( forestas_entes_list_item( $completo, 'it' ) === forestas_entes_list_item( $snella, 'it' ), 'card dell\'elenco uguale dall\'ente snello' );
	$check( forestas_dettaglio_card_percorso( $completo['tracks'][0] ) === forestas_dettaglio_card_percorso( $traccia ), 'card del sentiero gestito uguale dalla traccia snella' );

	// 1-ter. Un rinnovo con gli stessi dati non riscrive la copia (né l'opzione degli slug), ma aggiorna lo stato
	$risponde( 200, $json( $tre ) );
	update_option( $stato, [ 'generated_at' => 'vecchio', 'fetched_at' => '2026-01-01T00:00:00+00:00', 'checked_at' => '2026-01-01T00:00:00+00:00' ], false );
	add_action( 'add_option_' . $opzione, $scrive );
	add_action( 'update_option_' . $opzione, $scrive );
	$scritture = 0;
	$check( true === forestas_entes_refresh(), 'rinnovo con gli stessi dati → true' );
	$check( 0 === $scritture, 'stessi dati: la copia non si riscrive (' . $scritture . ' scritture)' );
	$stato_ora = get_option( $stato );
	$check( '2026-01-01T00:00:00+00:00' !== ( $stato_ora['fetched_at'] ?? '' ) && '2026-11-16T10:00:00+00:00' === ( $stato_ora['generated_at'] ?? null ), 'stessi dati: fetched_at e generated_at aggiornati nello stato' );
	$risponde( 200, $json( array_merge( $tre, [ $ente( 4, 'oasi-tepilora' ) ] ) ) );
	$check( true === forestas_entes_refresh() && 1 === $scritture, 'dati cambiati: la copia si riscrive una volta (' . $scritture . ')' );
	$check( isset( get_option( $slug_op )['oasi-tepilora'] ), 'dati cambiati: lo slug nuovo è nell\'opzione degli slug' );
	// Opzione degli slug cancellata, o scritta a mano con altri slug: il rinnovo con gli stessi dati la riallinea, senza
	// riscrivere la copia; già giusta, non la riscrive
	$attesi = get_option( $slug_op );
	delete_option( $slug_op );
	$scritture = 0;
	$check( true === forestas_entes_refresh() && 0 === $scritture && $attesi === get_option( $slug_op ), 'opzione degli slug cancellata, stessi dati: il rinnovo la riscrive, la copia no (' . $scritture . ' scritture): ' . wp_json_encode( get_option( $slug_op ) ) );
	update_option( $slug_op, [ 'vecchio' => true ], false );
	$check( true === forestas_entes_refresh() && 0 === $scritture && $attesi === get_option( $slug_op ), 'opzione degli slug sbagliata, stessi dati: il rinnovo la corregge: ' . wp_json_encode( get_option( $slug_op ) ) );
	$scritture_slug = 0;
	$scrive_slug    = function () use ( &$scritture_slug ) {
		++$scritture_slug;
	};
	add_action( 'update_option_' . $slug_op, $scrive_slug );
	$check( true === forestas_entes_refresh() && 0 === $scritture_slug, 'opzione degli slug già giusta: nessuna scrittura (' . $scritture_slug . ')' );
	remove_action( 'update_option_' . $slug_op, $scrive_slug );
	remove_action( 'add_option_' . $opzione, $scrive );
	remove_action( 'update_option_' . $opzione, $scrive );

	// 1-quater. Copia scritta prima dell'opzione degli slug (sito aggiornato, prima del primo rinnovo): gli slug dalla
	// copia; copia cancellata: l'opzione degli slug sparisce con lei
	delete_option( $slug_op );
	$check( 4 === count( forestas_entes_known_slugs() ) && isset( forestas_entes_known_slugs()['oasi-tepilora'] ), 'senza l\'opzione degli slug: gli slug dalla copia' );
	update_option( $opzione, [ 'data' => [ $ente( 9, ' con-spazi ' ), $ente( 10, '' ), [ 'id' => 11, 'slug' => [ 'x' ] ] ] ], false );
	$check( [ 'con-spazi' => true ] === get_option( $slug_op ), 'opzione degli slug dopo una scrittura della copia: slug puliti, senza vuoti e non testuali: ' . wp_json_encode( get_option( $slug_op ) ) );
	delete_option( $opzione );
	$check( null === get_option( $slug_op, null ) && [] === forestas_entes_known_slugs(), 'copia cancellata: niente slug' );

	// 1a. Tipi di ente sconosciuti e slug doppi: una riga di log ciascuno per copia nuova che li elenca (non a ogni visita,
	// in forestas_entes_type_label(), né a ogni rinnovo con gli stessi dati); nessuna riga con i soli tipi conosciuti
	$azzera();
	$risponde( 200, $json( $tre ) );
	forestas_entes_refresh();
	file_put_contents( $file_log, '' );
	$sconosciuti = array_merge( $tre, [ array_merge( $ente( 4, 'ente-pippo' ), [ 'type' => 'pippo' ] ), array_merge( $ente( 5, 'ente-boh' ), [ 'type' => 'boh' ] ), array_merge( $ente( 6, 'ente-boh-2' ), [ 'type' => 'boh' ] ), array_merge( $ente( 7, 'ente-senza-tipo' ), [ 'type' => null ] ) ] );
	$risponde( 200, $json( $sconosciuti ) );
	$check( true === forestas_entes_refresh(), 'rinnovo con tipi sconosciuti → true (la copia si salva)' );
	$righe = $righe_log();
	$check( 1 === count( $righe ), 'tipi sconosciuti: una riga di log (' . count( $righe ) . ')' );
	$check( false !== strpos( $righe[0] ?? '', '«boh», «pippo»' ), 'tipi sconosciuti: la riga li elenca una volta, in ordine: ' . ( $righe[0] ?? '' ) );
	$check( false === strpos( $righe[0] ?? '', 'comune' ), 'tipi sconosciuti: la riga non elenca i tipi conosciuti' );
	file_put_contents( $file_log, '' );
	forestas_entes_list( 'it' );
	forestas_entes_type_facet( '' );
	$check( '' === $log(), 'pagine con tipi sconosciuti: nessuna riga di log: ' . $log() );
	$check( true === forestas_entes_refresh() && '' === $log(), 'stessi dati con tipi sconosciuti: nessuna riga di log: ' . $log() );
	// Slug doppi (lo slug nello shard non è unico): una riga con gli slug, una volta ciascuno
	$doppi = array_merge( $sconosciuti, [ $ente( 8, 'agenzia-forestas' ), $ente( 9, 'gal-bmg' ), $ente( 10, 'gal-bmg' ) ] );
	$risponde( 200, $json( $doppi ) );
	$check( true === forestas_entes_refresh(), 'rinnovo con slug doppi → true' );
	$righe = array_values( array_filter( $righe_log(), fn( $r ) => false !== strpos( $r, 'slug doppi' ) ) );
	$check( 1 === count( $righe ) && false !== strpos( $righe[0], '«agenzia-forestas», «gal-bmg»' ), 'slug doppi: una riga che li elenca una volta: ' . implode( ' | ', $righe ) );
	$check( 3 === ( forestas_entes_by_slug( 'gal-bmg' )['id'] ?? null ), 'slug doppio: il primo ente con lo slug' );
	$check( [ 'agenzia-forestas', 'gal-bmg' ] === forestas_entes_duplicate_slugs( $doppi ), 'forestas_entes_duplicate_slugs()' );
	// Senza la copia da 10 enti: 3 sarebbero sotto la metà, e il rinnovo verrebbe scartato
	delete_option( $opzione );
	file_put_contents( $file_log, '' );
	$risponde( 200, $json( $tre ) );
	$check( true === forestas_entes_refresh(), 'rinnovo con i soli tipi conosciuti → true' );
	$check( '' === $log(), 'soli tipi conosciuti: nessuna riga di log: ' . $log() );

	// 1b. Funzione comune delle chiamate allo shard: query string codificata, risposta di wmwp_http_json()
	$risponde( 200, [ 'sources' => [] ] );
	$ricerca = forestas_entes_api_get( 'api/v1/entes/search', [ 'query' => 'monte arci' ] );
	$check( 'https://interno.example/api/v1/entes/search?query=monte%20arci' === $finto['url'], 'ricerca con la query codificata: ' . $finto['url'] );
	$check( 200 === ( $ricerca['code'] ?? null ) && [ 'sources' => [] ] === ( $ricerca['data'] ?? null ), 'risposta nella forma di wmwp_http_json()' );
	$check( 'Bearer ' . $token === $finto['auth'], 'ricerca con il token' );

	// 1c. Il token solo in https o verso un host locale: in http verso un dominio la richiesta non parte, e il motivo va
	// nel log del rinnovo
	foreach ( [ 'http://localhost:8000', 'http://127.0.0.1:8000', 'http://host.docker.internal:8000', 'http://php-forestas', 'https://shard.example' ] as $indirizzo ) {
		$dal_server_url = $indirizzo;
		$risponde( 200, [ 'sources' => [] ] );
		forestas_entes_api_get( 'api/v1/entes/search', [ 'query' => 'x' ] );
		$check( 1 === $finto['richieste'] && 'Bearer ' . $token === $finto['auth'], "token verso {$indirizzo}" );
	}
	foreach ( [ 'http://shard.example', 'http://forestas.dev.maphub.it', 'http://10.0.0.5', 'ftp://php-forestas' ] as $indirizzo ) {
		$dal_server_url = $indirizzo;
		$risponde( 200, $json( $tre ) );
		$senza = forestas_entes_api_get( 'api/v1/entes' );
		$check( 0 === $finto['richieste'] && 0 === $senza['code'] && false !== strpos( $senza['error'], 'token non mandato' ), "nessun token verso {$indirizzo}: " . wp_json_encode( $senza['error'] ) );
	}
	$dal_server_url = 'http://shard.example';
	file_put_contents( $file_log, '' );
	$check( false === forestas_entes_refresh() && 0 === $finto['richieste'], 'rinnovo in http verso un dominio: non parte' );
	$check( false !== strpos( $log(), 'token non mandato' ) && false === strpos( $log(), $token ), 'rinnovo in http verso un dominio: il motivo nel log, senza il token: ' . $log() );
	$dal_server_url = 'https://interno.example';

	// 1d. Errore di rete: codice 0 con il messaggio dopo «shard non raggiungibile»
	$rete = fn( $pre, $args, $url ) => false !== strpos( (string) $url, '/api/v1/entes' ) ? new WP_Error( 'http_request_failed', 'cURL error 7: Failed to connect' ) : $pre;
	add_filter( 'pre_http_request', $rete, 20, 3 ); // dopo lo shard finto
	$errore = forestas_entes_api_get( 'api/v1/entes' );
	remove_filter( 'pre_http_request', $rete, 20 );
	$check( 0 === $errore['code'] && 'shard non raggiungibile: cURL error 7: Failed to connect' === $errore['error'], 'errore di rete: ' . wp_json_encode( $errore['error'] ) );

	// 2. Copia da 3 enti, risposta 200 con data vuota: resta la copia
	$risponde( 200, $json( [] ) );
	file_put_contents( $file_log, '' );
	$check( false === forestas_entes_refresh(), 'data vuota → false' );
	$check( 3 === count( forestas_entes_cached() ), 'data vuota: la copia ha ancora 3 enti' );
	$check( false !== strpos( $log(), 'forestas enti: ' ), 'data vuota: riga di log' );

	// 3. Copia da 4 enti con 10 collegamenti, risposta con 4 enti e 4 collegamenti: copia invariata
	$azzera();
	$risponde( 200, $json( [ $ente( 1, 'a', 3 ), $ente( 2, 'b', 3 ), $ente( 3, 'c', 2 ), $ente( 4, 'd', 2 ) ] ) );
	$check( true === forestas_entes_refresh(), 'copia da 4 enti e 10 collegamenti' );
	$copia_buona = get_option( $opzione );
	$risponde( 200, $json( [ $ente( 1, 'a' ), $ente( 2, 'b' ), $ente( 3, 'c' ), $ente( 4, 'd' ) ] ) );
	file_put_contents( $file_log, '' );
	$check( false === forestas_entes_refresh(), 'collegamenti sotto la metà → false' );
	$check( $copia_buona === get_option( $opzione ), 'collegamenti sotto la metà: copia invariata' );
	$check( false !== strpos( $log(), 'forestas enti: ' ) && false !== strpos( $log(), 'si accetta se il calo dura più di 3 ore' ), 'collegamenti sotto la metà: riga di log con le 3 ore: ' . $log() );
	$primo_rifiuto = (int) ( get_option( $stato )['refused_since'] ?? 0 );
	$check( $primo_rifiuto > time() - 60, 'primo rifiuto: refused_since scritto nello stato' );

	// 3a. Il calo si rifiuta finché non dura più di 3 ore dal primo rifiuto, poi si accetta con una riga di log; una
	// lista vuota mai; un rinnovo plausibile chiude il rifiuto
	$check( false === forestas_entes_refresh() && $primo_rifiuto === (int) ( get_option( $stato )['refused_since'] ?? 0 ), 'secondo rifiuto: refused_since resta quello del primo' );
	$stato_ora                  = get_option( $stato );
	$stato_ora['refused_since'] = time() - FORESTAS_ENTES_DROP_GRACE + 60;
	update_option( $stato, $stato_ora, false );
	$check( false === forestas_entes_refresh() && $copia_buona === get_option( $opzione ), 'calo da meno di 3 ore: ancora rifiutato' );
	$stato_ora['refused_since'] = time() - FORESTAS_ENTES_DROP_GRACE - 1;
	update_option( $stato, $stato_ora, false );
	$risponde( 200, $json( [] ) );
	$check( false === forestas_entes_refresh() && $copia_buona === get_option( $opzione ), 'lista vuota dopo 3 ore: mai accettata' );
	$risponde( 200, $json( [ $ente( 1, 'a' ), $ente( 2, 'b' ), $ente( 3, 'c' ), $ente( 4, 'd' ) ] ) );
	file_put_contents( $file_log, '' );
	$check( true === forestas_entes_refresh(), 'calo da più di 3 ore → accettato' );
	$check( 4 === forestas_entes_counts( forestas_entes_cached() )[1], 'calo accettato: la copia ha i 4 collegamenti nuovi' );
	$check( false !== strpos( $log(), 'calo accettato' ), 'calo accettato: riga di log: ' . $log() );
	$check( ! isset( get_option( $stato )['refused_since'] ), 'calo accettato: refused_since tolto' );
	// Un rinnovo plausibile durante il rifiuto chiude il rifiuto
	$risponde( 200, $json( [ $ente( 1, 'a', 3 ), $ente( 2, 'b', 3 ), $ente( 3, 'c', 2 ), $ente( 4, 'd', 2 ) ] ) );
	forestas_entes_refresh();
	$risponde( 200, $json( [ $ente( 1, 'a' ), $ente( 2, 'b' ), $ente( 3, 'c' ), $ente( 4, 'd' ) ] ) );
	forestas_entes_refresh();
	$check( isset( get_option( $stato )['refused_since'] ), 'nuovo rifiuto: refused_since scritto' );
	$risponde( 200, $json( [ $ente( 1, 'a', 3 ), $ente( 2, 'b', 3 ), $ente( 3, 'c', 2 ), $ente( 4, 'd', 2 ) ] ) );
	$check( true === forestas_entes_refresh() && ! isset( get_option( $stato )['refused_since'] ), 'rinnovo plausibile: il rifiuto si chiude' );
	$copia_buona = get_option( $opzione );

	// 3b. Soglia di plausibilità: vuota mai, senza copia precedente sì, la metà esatta sì, sotto la metà no
	$check( false === forestas_entes_is_plausible( [], null ), 'lista vuota senza copia: non plausibile' );
	$check( true === forestas_entes_is_plausible( [ $ente( 1, 'a' ) ], null ), 'prima copia: plausibile' );
	$check( true === forestas_entes_is_plausible( [ $ente( 1, 'a', 5 ) ], [ $ente( 1, 'a', 5 ), $ente( 2, 'b', 5 ) ] ), 'la metà degli enti e dei collegamenti: plausibile' );
	$check( false === forestas_entes_is_plausible( [ $ente( 1, 'a', 10 ) ], [ $ente( 1, 'a' ), $ente( 2, 'b' ), $ente( 3, 'c' ) ] ), 'enti sotto la metà: non plausibile' );

	// 4. Copia presente, risposta 401: copia invariata, una riga di log con il codice e senza il token
	$risponde( 401, [ 'message' => 'Invalid WordPress API token.' ] );
	file_put_contents( $file_log, '' );
	$check( false === forestas_entes_refresh(), '401 → false' );
	$check( $copia_buona === get_option( $opzione ), '401: copia invariata' );
	$righe = array_values( array_filter( explode( "\n", $log() ), fn( $r ) => false !== strpos( $r, 'forestas enti: ' ) ) );
	$check( 1 === count( $righe ), '401: una riga di log (' . count( $righe ) . ')' );
	$check( false !== strpos( $righe[0] ?? '', '401' ), '401: il codice nel log' );
	$check( false === strpos( $log(), $token ), '401: il token non è nel log' );
	$check( false === get_transient( $attesa ), '401 con la copia: nessuna attesa' );

	// 4a. Risposta 429 (limite al minuto dello shard): copia invariata, il codice e il motivo nel log
	$risponde( 429, [ 'message' => 'Too Many Attempts.' ] );
	file_put_contents( $file_log, '' );
	$check( false === forestas_entes_refresh() && $copia_buona === get_option( $opzione ), '429: copia invariata' );
	$check( false !== strpos( $log(), 'codice 429 (troppe richieste' ), '429: il codice e il motivo nel log: ' . $log() );
	$check( false !== strpos( forestas_entes_http_reason( 503 ), 'indice degli enti' ) && 'errore dello shard' === forestas_entes_http_reason( 502 ) && 'x' === forestas_entes_http_reason( 401, 'x' ), 'forestas_entes_http_reason(): per codice, e il messaggio quando c\'è' );

	// 5. Nessuna copia, risposta 503: null, attesa scritta, la seconda lettura non richiama lo shard
	$azzera();
	$risponde( 503, [ 'message' => 'Service Unavailable' ] );
	$check( null === forestas_entes_all(), 'senza copia e con 503 → null' );
	$check( 1 === $finto['richieste'], 'senza copia la prima lettura chiama lo shard' );
	$check( false !== get_transient( $attesa ), '503 senza copia: attesa scritta' );
	$timeout = (int) get_option( '_transient_timeout_' . $attesa, 0 );
	$check( $timeout > time() + 240 && $timeout <= time() + 300, 'attesa di 5 minuti' );
	$check( null === forestas_entes_all(), 'durante l\'attesa → null' );
	$check( 1 === $finto['richieste'], 'durante l\'attesa nessuna seconda richiesta' );
	$check( false !== strpos( $log(), '503' ), '503 nel log' );

	// 5a. Prima richiesta senza copia: durante la richiesta il lucchetto è preso, così una visita che arriva intanto (una
	// richiesta nuova: forestas_entes_copy( true )) non la ripete e non scrive nel log; la richiesta riuscita lo lascia
	$azzera();
	$risponde( 200, $json( $tre ) );
	$durante          = [ 'lucchetto' => null, 'lettura' => 'non chiamata', 'richieste' => null, 'log' => null ];
	$finto['durante'] = function () use ( &$durante, &$finto, $lucchetto, $log ) {
		$durante['lucchetto'] = $lucchetto();
		forestas_entes_copy( true );
		$durante['lettura']   = forestas_entes_all();
		$durante['richieste'] = $finto['richieste'];
		$durante['log']       = $log();
	};
	$tutti = forestas_entes_all();
	$check( is_string( $durante['lucchetto'] ) && ctype_digit( $durante['lucchetto'] ), 'prima richiesta senza copia: lucchetto preso durante la richiesta (' . wp_json_encode( $durante['lucchetto'] ) . ')' );
	$check( null === $durante['lettura'], 'una visita durante la prima richiesta non ha la copia (null)' );
	$check( 1 === $durante['richieste'] && 1 === $finto['richieste'], 'una visita durante la prima richiesta non richiama lo shard (' . $finto['richieste'] . ' richieste)' );
	$check( '' === $durante['log'], 'una visita durante la prima richiesta: nessuna riga di log: ' . $durante['log'] );
	$check( is_array( $tutti ) && 3 === count( $tutti ), 'prima richiesta riuscita: 3 enti' );
	$check( false === get_transient( $attesa ) && null === $lucchetto(), 'prima richiesta riuscita: nessuna attesa, lucchetto lasciato' );

	// 5b. Risposte non valide: JSON rotto, data che non è una lista
	$risponde( 200, 'non è JSON' );
	$check( is_wp_error( forestas_entes_fetch() ), 'JSON rotto → WP_Error' );
	$risponde( 200, [ 'generated_at' => 'x', 'data' => [ 'slug' => 'a' ] ] );
	$check( is_wp_error( forestas_entes_fetch() ), 'data che non è una lista → WP_Error' );
	$risponde( 200, [ 'generated_at' => 'x' ] );
	$check( is_wp_error( forestas_entes_fetch() ), 'senza data → WP_Error' );

	// 5c. Senza token: nessuna chiamata allo shard, WP_Error, attesa scritta
	$azzera();
	$risponde( 200, $json( $tre ) );
	putenv( 'WP_API_TOKEN' );
	$senza = forestas_entes_api_get( 'api/v1/entes' );
	$check( 0 === ( $senza['code'] ?? null ) && array_key_exists( 'data', $senza ) && null === $senza['data'] && '' !== ( $senza['error'] ?? '' ), 'senza token la funzione comune risponde codice 0, con il motivo' );
	$check( is_wp_error( forestas_entes_fetch() ), 'senza token → WP_Error' );
	$check( null === forestas_entes_all(), 'senza token e senza copia → null' );
	$check( 0 === $finto['richieste'], 'senza token nessuna richiesta allo shard' );
	$check( false !== get_transient( $attesa ), 'senza token: attesa scritta' );
	$check( false !== strpos( $log(), 'forestas enti: ' ), 'senza token: riga di log' );
	putenv( 'WP_API_TOKEN=' );
	$check( '' === ( getenv( 'WP_API_TOKEN' ) ?: '' ) && is_wp_error( forestas_entes_fetch() ) && 0 === $finto['richieste'], 'token vuoto: come senza token' );
	putenv( 'WP_API_TOKEN=' . $token );

	// 6. Copia letta senza chiamare lo shard (dettagli di percorsi e POI)
	$azzera();
	$risponde( 200, $json( $tre ) );
	$check( [] === forestas_entes_cached(), 'senza copia forestas_entes_cached() → []' );
	$check( 0 === $finto['richieste'], 'forestas_entes_cached() non chiama lo shard' );
	$check( [] === forestas_entes_known_slugs() && 0 === $finto['richieste'], 'senza copia forestas_entes_known_slugs() → [] senza chiamare lo shard' );
	$check( false === get_transient( $attesa ), 'forestas_entes_cached() non scrive l\'attesa' );

	// 7. Ricerca per slug
	$check( true === forestas_entes_refresh(), 'copia da 3 enti per lo slug' );
	$stintino = forestas_entes_by_slug( 'comune-di-stintino' );
	$check( 2 === ( $stintino['id'] ?? null ), 'forestas_entes_by_slug() trova l\'ente' );
	$check( null === forestas_entes_by_slug( 'non-esiste' ), 'slug inesistente → null' );
	$check( null === forestas_entes_by_slug( '' ), 'slug vuoto → null' );

	// 7b. Copia letta dal database una volta per richiesta (le pagine degli enti la chiedono più volte) e dimenticata
	// quando l'opzione si scrive o si cancella. Si parte senza opzione: un rinnovo con gli stessi dati non riscrive
	// l'opzione, e la copia già ricordata resta (giustamente) quella
	$azzera();
	$risponde( 200, $json( $tre ) );
	$check( true === forestas_entes_refresh(), 'copia da 3 enti per la lettura unica' );
	add_filter( 'option_' . $opzione, $conta );
	$letture = 0;
	forestas_entes_copy( true ); // una richiesta nuova
	forestas_entes_cached();
	forestas_entes_cached();
	forestas_entes_by_slug( 'gal-bmg' );
	forestas_entes_all();
	$check( 1 === $letture, 'copia letta dal database una volta per richiesta (' . $letture . ' letture)' );
	$risponde( 200, $json( array_merge( $tre, [ $ente( 4, 'oasi-tepilora', 2 ) ] ) ) );
	$check( true === forestas_entes_refresh(), 'rinnovo con 4 enti' );
	$check( 4 === count( forestas_entes_cached() ), 'dopo un rinnovo la copia della richiesta è quella nuova' );
	$check( 4 === ( forestas_entes_by_slug( 'oasi-tepilora' )['id'] ?? null ), 'dopo un rinnovo lo slug nuovo si trova' );
	delete_option( $opzione );
	$check( [] === forestas_entes_cached(), 'dopo la cancellazione dell\'opzione la copia della richiesta non c\'è più' );
	update_option( $opzione, [ 'generated_at' => null, 'fetched_at' => gmdate( 'c' ), 'data' => [ $ente( 9, 'scritta-a-mano' ) ] ], false );
	$check( 9 === ( forestas_entes_cached()[0]['id'] ?? null ), 'dopo una scrittura dell\'opzione la copia della richiesta è quella scritta' );
	remove_filter( 'option_' . $opzione, $conta );

	// 8. Evento WP-Cron: registrato su init se manca, ogni 15 minuti, collegato al rinnovo. Non do_action( 'init' ): da
	// eval-file rilancia l'init di Impreza, che si ferma con un errore fatale (classe già dichiarata)
	$check( false !== has_action( 'init', 'forestas_entes_schedule' ), 'registrazione dell\'evento agganciata a init' );
	wp_clear_scheduled_hook( $evento );
	forestas_entes_schedule();
	$check( false !== wp_next_scheduled( $evento ), 'evento forestas_entes_refresh registrato su init' );
	$quando = wp_next_scheduled( $evento );
	forestas_entes_schedule();
	$check( $quando === wp_next_scheduled( $evento ), 'evento già registrato: init non lo sposta' );
	$check( 'forestas_quarter_hour' === wp_get_schedule( $evento ), 'evento con l\'intervallo forestas_quarter_hour' );
	$check( false !== has_action( $evento, 'forestas_entes_refresh' ), 'evento collegato a forestas_entes_refresh()' );
	$check( 'Ogni 15 minuti (enti dello shard)' === ( wp_get_schedules()['forestas_quarter_hour']['display'] ?? null ), 'nome dell\'intervallo dalla costante' );
	// Cambiando tema l'evento si toglie (forestas_entes_unschedule() su switch_theme); init lo registra di nuovo
	$check( false !== has_action( 'switch_theme', 'forestas_entes_unschedule' ), 'forestas_entes_unschedule() agganciata a switch_theme' );
	forestas_entes_unschedule();
	$check( false === wp_next_scheduled( $evento ), 'cambio di tema: evento tolto' );
	forestas_entes_schedule();
	$check( false !== wp_next_scheduled( $evento ), 'tornando al child: evento di nuovo registrato' );

	// 9. WP-Cron fermo: con la copia, una pagina degli enti (forestas_entes_all()) rinnova se da più di un'ora non è
	// partito nessun rinnovo, una volta per richiesta, e una visita che arriva intanto trova il lucchetto preso; un
	// rinnovo fallito lascia la copia. I dettagli (forestas_entes_known_slugs(), forestas_entes_cached()) non rinnovano mai
	$azzera();
	$risponde( 200, $json( $tre ) );
	forestas_entes_refresh();
	$check( false === forestas_entes_overdue(), 'rinnovo appena fatto: non in ritardo' );
	$vecchio = function ( int $secondi ) use ( $stato ) {
		$ora               = get_option( $stato );
		$ora['checked_at'] = gmdate( 'c', time() - $secondi );
		$ora['fetched_at'] = $ora['checked_at'];
		update_option( $stato, $ora, false );
	};
	$vecchio( FORESTAS_ENTES_STALE_AFTER - 60 );
	$risponde( 200, $json( array_merge( $tre, [ $ente( 4, 'oasi-tepilora' ) ] ) ) );
	$check( 3 === count( forestas_entes_all() ?? [] ) && 0 === $finto['richieste'], 'ultimo rinnovo da meno di un\'ora: nessuna richiesta' );
	$vecchio( FORESTAS_ENTES_STALE_AFTER + 60 );
	$check( true === forestas_entes_overdue(), 'ultimo rinnovo da più di un\'ora: in ritardo' );
	forestas_entes_known_slugs();
	forestas_entes_cached();
	$check( 0 === $finto['richieste'], 'in ritardo: i dettagli (slug, copia) non rinnovano' );
	$durante          = [ 'lettura' => 'non chiamata', 'richieste' => null ];
	$finto['durante'] = function () use ( &$durante, &$finto ) {
		forestas_entes_copy( true );
		$durante['lettura']   = forestas_entes_all();
		$durante['richieste'] = $finto['richieste'];
	};
	forestas_entes_copy( true );
	$tutti = forestas_entes_all();
	$check( 1 === $finto['richieste'] && 4 === count( $tutti ?? [] ), 'in ritardo: la pagina rinnova una volta e legge la copia nuova (' . $finto['richieste'] . ' richieste)' );
	$check( 1 === $durante['richieste'] && 3 === count( (array) $durante['lettura'] ), 'una visita durante il rinnovo trova il lucchetto preso: legge la copia di prima e non richiama lo shard' );
	$check( false === forestas_entes_overdue() && false === get_transient( $attesa ), 'dopo il rinnovo da una pagina: non in ritardo, nessuna attesa' );
	forestas_entes_copy( true );
	forestas_entes_all();
	$check( 1 === $finto['richieste'], 'dopo il rinnovo da una pagina: nessuna seconda richiesta' );
	// Rinnovo da una pagina fallito: resta la copia, e checked_at fa rinnovare le pagine solo fra un'ora; nella stessa
	// richiesta (rotta, titolo, selettore di lingua…) un tentativo solo
	$vecchio( FORESTAS_ENTES_STALE_AFTER + 60 );
	$risponde( 503, [ 'message' => 'Service Unavailable' ] );
	forestas_entes_copy( true );
	$check( 4 === count( forestas_entes_all() ?? [] ) && 1 === $finto['richieste'], 'rinnovo da una pagina fallito: resta la copia' );
	$check( false === forestas_entes_overdue() && null === $lucchetto(), 'rinnovo da una pagina fallito: non più in ritardo, lucchetto lasciato' );
	forestas_entes_all();
	forestas_entes_copy( true );
	forestas_entes_all();
	$check( 1 === $finto['richieste'], 'rinnovo da una pagina fallito: le visite dopo non richiamano lo shard' );
	$vecchio( FORESTAS_ENTES_STALE_AFTER + 60 );
	forestas_entes_copy( true );
	$metti_lucchetto( (string) time() );
	forestas_entes_all();
	$metti_lucchetto( null );
	forestas_entes_all();
	$check( 1 === $finto['richieste'], 'lucchetto preso da un altro e poi lasciato: nella stessa richiesta nessun secondo tentativo (' . $finto['richieste'] . ')' );
	// Copia scritta prima dello stato (sito aggiornato): vale il suo fetched_at
	delete_option( $stato );
	delete_transient( $attesa );
	update_option( $opzione, [ 'generated_at' => null, 'fetched_at' => gmdate( 'c' ), 'data' => $tre ], false );
	$check( false === forestas_entes_overdue(), 'copia senza stato con fetched_at recente: non in ritardo' );
	update_option( $opzione, [ 'generated_at' => null, 'fetched_at' => gmdate( 'c', time() - 2 * HOUR_IN_SECONDS ), 'data' => $tre ], false );
	$check( true === forestas_entes_overdue(), 'copia senza stato con fetched_at di 2 ore fa: in ritardo' );
	update_option( $opzione, [ 'data' => $tre ], false );
	$check( true === forestas_entes_overdue(), 'copia senza stato né fetched_at: in ritardo' );

	// 9b. Rinnovo scaduto con WP-Cron appena partito (transient `doing_cron` recente) e l'evento del rinnovo da eseguire:
	// la pagina con la copia lo lascia a WP-Cron ed esce subito, e segna l'istante; dopo FORESTAS_ENTES_CRON_WAIT secondi
	// senza rinnovi (WP-Cron partito ma fermo) rinnova da sé. `doing_cron` vecchio, evento non scaduto o nessuna copia:
	// la pagina rinnova subito
	$azzera();
	$risponde( 200, $json( $tre ) );
	forestas_entes_refresh();
	$scaduto = function () use ( $evento, $vecchio ) {
		$vecchio( FORESTAS_ENTES_STALE_AFTER + 60 );
		wp_clear_scheduled_hook( $evento );
		wp_schedule_event( time() - 10, 'forestas_quarter_hour', $evento );
		set_transient( 'doing_cron', sprintf( '%.22F', microtime( true ) ) );
		forestas_entes_copy( true );
	};
	$scaduto();
	$risponde( 200, $json( array_merge( $tre, [ $ente( 4, 'oasi-tepilora' ) ] ) ) );
	$check( true === forestas_entes_cron_spawned(), 'doing_cron appena scritto: WP-Cron appena partito' );
	$check( 3 === count( forestas_entes_all() ?? [] ) && 0 === $finto['richieste'], 'WP-Cron appena partito: la pagina esce con la copia, senza richieste (' . $finto['richieste'] . ')' );
	$segno = (int) ( get_option( $stato )['left_to_cron'] ?? 0 );
	$check( $segno >= time() - 2 && $segno <= time(), 'WP-Cron appena partito: istante segnato nello stato (' . $segno . ')' );
	forestas_entes_copy( true );
	forestas_entes_all();
	$check( 0 === $finto['richieste'] && $segno === (int) ( get_option( $stato )['left_to_cron'] ?? 0 ), 'seconda visita entro ' . FORESTAS_ENTES_CRON_WAIT . ' secondi: nessuna richiesta, il segno resta' );
	// WP-Cron fa il rinnovo: il segno sparisce
	$check( true === forestas_entes_refresh() && 1 === $finto['richieste'] && ! isset( get_option( $stato )['left_to_cron'] ), 'il rinnovo di WP-Cron toglie il segno' );
	// WP-Cron partito ma fermo: dopo FORESTAS_ENTES_CRON_WAIT secondi dal segno la pagina rinnova da sé
	$scaduto();
	$ora                 = get_option( $stato );
	$ora['left_to_cron'] = time() - FORESTAS_ENTES_CRON_WAIT - 1;
	update_option( $stato, $ora, false );
	$risponde( 200, $json( array_merge( $tre, [ $ente( 4, 'oasi-tepilora' ), $ente( 5, 'ente-cinque' ) ] ) ) );
	$check( 5 === count( forestas_entes_all() ?? [] ) && 1 === $finto['richieste'], 'WP-Cron fermo da più di ' . FORESTAS_ENTES_CRON_WAIT . ' secondi: la pagina rinnova (' . $finto['richieste'] . ')' );
	$check( ! isset( get_option( $stato )['left_to_cron'] ), 'rinnovo dalla pagina: il segno sparisce' );
	// Un segno di prima dell'ultimo rinnovo partito non vale: la visita ne scrive uno nuovo e lascia il rinnovo a WP-Cron
	$scaduto();
	$ora                 = get_option( $stato );
	$ora['left_to_cron'] = time() - 2 * FORESTAS_ENTES_STALE_AFTER;
	update_option( $stato, $ora, false );
	$risponde( 200, $json( $tre ) );
	forestas_entes_all();
	$check( 0 === $finto['richieste'] && (int) ( get_option( $stato )['left_to_cron'] ?? 0 ) >= time() - 2, 'segno di un\'altra volta: segno nuovo, rinnovo lasciato a WP-Cron' );
	// doing_cron vecchio: dove il loopback non risponde (in locale) nessuno lo cancella, e non conta
	$scaduto();
	set_transient( 'doing_cron', sprintf( '%.22F', microtime( true ) - ( defined( 'WP_CRON_LOCK_TIMEOUT' ) ? WP_CRON_LOCK_TIMEOUT : 60 ) - 5 ) );
	$risponde( 200, $json( $tre ) );
	$check( false === forestas_entes_cron_spawned() && 3 === count( forestas_entes_all() ?? [] ) && 1 === $finto['richieste'], 'doing_cron vecchio: la pagina rinnova (' . $finto['richieste'] . ')' );
	// Evento del rinnovo non ancora da eseguire: WP-Cron appena partito non lo farà, la pagina rinnova
	$scaduto();
	wp_clear_scheduled_hook( $evento );
	wp_schedule_event( time() + 600, 'forestas_quarter_hour', $evento );
	$risponde( 200, $json( $tre ) );
	forestas_entes_all();
	$check( 1 === $finto['richieste'], 'evento non scaduto: la pagina rinnova (' . $finto['richieste'] . ')' );
	// Senza copia non si aspetta WP-Cron: la pagina non ha niente da mostrare
	$scaduto();
	delete_option( $opzione );
	$risponde( 200, $json( $tre ) );
	$check( 3 === count( forestas_entes_all() ?? [] ) && 1 === $finto['richieste'], 'senza copia e con WP-Cron appena partito: la pagina rinnova (' . $finto['richieste'] . ')' );
	delete_transient( 'doing_cron' );

	// 10. Un rinnovo alla volta: il lucchetto (riga forestas_entes_refresh_lock, scritta con una sola istruzione SQL) lo
	// prende uno solo; WP-Cron o `wp cron event run` con il lucchetto preso saltano il rinnovo con una riga di log; un
	// lucchetto di più di FORESTAS_ENTES_LOCK_STALE secondi è di un rinnovo interrotto e si riprende
	$azzera();
	$risponde( 200, $json( $tre ) );
	$check( 'forestas_entes_refresh_lock' === FORESTAS_ENTES_LOCK_OPTION && 120 === FORESTAS_ENTES_LOCK_STALE, 'FORESTAS_ENTES_LOCK_OPTION e FORESTAS_ENTES_LOCK_STALE' );
	$preso = forestas_entes_lock();
	$check( is_string( $preso ) && $preso === $lucchetto(), 'lucchetto preso: la riga con l\'istante (' . wp_json_encode( $preso ) . ')' );
	$autoload = $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", $chiave ) );
	$check( 'off' === $autoload, 'lucchetto non autoload (' . $autoload . ')' );
	$check( null === forestas_entes_lock() && $preso === $lucchetto(), 'lucchetto già preso: un secondo processo non lo prende e non lo cambia' );
	$check( false === forestas_entes_refresh() && 0 === $finto['richieste'], 'WP-Cron con il lucchetto preso: nessuna richiesta' );
	$saltati = array_values( array_filter( $righe_log(), fn( $r ) => false !== strpos( $r, 'rinnovo saltato' ) ) );
	$check( 1 === count( $saltati ), 'WP-Cron con il lucchetto preso: una riga di log (' . implode( ' | ', $saltati ) . ')' );
	$check( $preso === $lucchetto() && false === get_option( $stato ), 'rinnovo saltato: lucchetto e stato non toccati' );
	file_put_contents( $file_log, '' );
	forestas_entes_copy( true );
	$check( null === forestas_entes_all() && 0 === $finto['richieste'] && '' === $log() && false === get_transient( $attesa ), 'pagina senza copia con il lucchetto preso: null, nessuna richiesta, nessun log, nessuna attesa' );
	forestas_entes_unlock( '1' );
	$check( $preso === $lucchetto(), 'forestas_entes_unlock() con un altro valore non toglie il lucchetto' );
	forestas_entes_unlock( $preso );
	$check( null === $lucchetto(), 'forestas_entes_unlock() con il suo valore lo toglie' );
	// Lucchetto di un rinnovo interrotto: oltre FORESTAS_ENTES_LOCK_STALE secondi si riprende, prima no
	$metti_lucchetto( (string) ( time() - FORESTAS_ENTES_LOCK_STALE + 5 ) );
	$check( false === forestas_entes_refresh() && 0 === $finto['richieste'], 'lucchetto di ' . ( FORESTAS_ENTES_LOCK_STALE - 5 ) . ' secondi: non si riprende' );
	$metti_lucchetto( (string) ( time() - FORESTAS_ENTES_LOCK_STALE - 1 ) );
	$check( true === forestas_entes_refresh() && 1 === $finto['richieste'] && null === $lucchetto(), 'lucchetto abbandonato: ripreso, rinnovo fatto, lucchetto lasciato' );
	// Rinnovo fallito: il lucchetto si lascia lo stesso
	$risponde( 503, [ 'message' => 'Service Unavailable' ] );
	$check( false === forestas_entes_refresh() && 1 === $finto['richieste'] && null === $lucchetto(), 'rinnovo fallito: lucchetto lasciato' );
	// L'evento WP-Cron chiama il rinnovo senza argomenti: `$log_busy` resta true
	global $wp_filter;
	$cb = $wp_filter[ $evento ]->callbacks[10]['forestas_entes_refresh'] ?? null;
	$check( 0 === ( $cb['accepted_args'] ?? null ), 'evento WP-Cron: forestas_entes_refresh() senza argomenti' );

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
$check( $prima['evento'] === wp_next_scheduled( $evento ), 'evento WP-Cron rimesso com\'era' );
$check( $prima['token'] === getenv( 'WP_API_TOKEN' ), 'variabile d\'ambiente rimessa com\'era' );

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
