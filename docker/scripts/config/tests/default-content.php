<?php
/**
 * Test (oc:8990): contenuti di default di WordPress nel cestino (default-content.php, wpf_default_content_apply()),
 * con post temporanei passati come contenuti di default al posto di «Ciao mondo!», «Pagina di esempio» e della bozza
 * «Privacy Policy»: i post 1, 2 e 3 veri non si toccano.
 * Contenuto di default non modificato → nel cestino (in prova solo la riga della differenza, nulla scritto); modificato
 * (post_modified_gmt diverso da post_date_gmt), con un altro slug o già nel cestino → nessuna riga, non si tocca; secondo
 * giro → nessuna riga. Opzione wp_page_for_privacy_policy azzerata solo quando la pagina della privacy è quella di
 * default (mandata nel cestino ora, o già nel cestino con lo slug di default), invariata altrimenti (pagina modificata,
 * con un altro slug o tipo, opzione che punta a un'altra pagina). Con i valori predefiniti (WPF_DEFAULT_CONTENT,
 * WPF_DEFAULT_PRIVACY_ID) la prova non scrive nulla.
 * Rimette l'opzione com'era e cancella i post temporanei (con wpf_wpml_cancella_post, che toglie anche la riga di WPML).
 * Si lancia con: docker exec wordpress-forestas wp --allow-root eval-file /usr/local/lib/wp-forestas/tests/default-content.php
 * (docker/scripts/config/ è montato in /usr/local/lib/wp-forestas). Stampa «PASS» o «FAIL: …» ed esce con 0 o 1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/wpml.php';
require_once dirname( __DIR__ ) . '/default-content.php';

$failures = [];
$check    = function ( bool $ok, string $what ) use ( &$failures ) {
	if ( ! $ok ) {
		$failures[] = $what;
	}
};

$opzione = 'wp_page_for_privacy_policy';
$assente = '__assente__';

// Stato da rimettere o da confrontare alla fine: l'opzione e i post 1, 2 e 3 veri
$stato = function ( int $id ): ?array {
	clean_post_cache( $id );
	$post = get_post( $id );
	return $post ? [ $post->post_type, $post->post_status, $post->post_name, $post->post_modified_gmt ] : null;
};
// L'opzione si confronta nel database: update_option() la ripulisce con absint() e la copia in memoria resta un intero
$opzione_db = function () use ( $opzione ): ?array {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare( "SELECT option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $opzione ), ARRAY_A );
};
$prima = [
	'opzione' => get_option( $opzione, $assente ),
	'db'      => $opzione_db(),
	'veri'    => array_map( $stato, array_combine( array_keys( WPF_DEFAULT_CONTENT ), array_keys( WPF_DEFAULT_CONTENT ) ) ),
];

// Post temporanei: id => tipo, cancellati alla fine
$temporanei = [];
$crea       = function ( string $tipo, string $nome, string $stato_post = 'draft' ) use ( &$temporanei ): int {
	$adesso = time();
	$id     = wp_insert_post(
		[
			'post_type'     => $tipo,
			'post_status'   => $stato_post,
			'post_title'    => "Test 8990 {$nome}",
			'post_name'     => 'wpf-test-8990-' . $nome . '-' . strtolower( wp_generate_password( 6, false, false ) ),
			'post_content'  => '',
			// Date scritte come all'installazione: post_modified uguale a post_date, anche per una bozza
			'post_date'     => get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $adesso ) ),
			'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $adesso ),
		],
		true
	);
	if ( is_wp_error( $id ) ) {
		throw new RuntimeException( "post temporaneo {$nome} non creato: " . $id->get_error_message() );
	}
	$temporanei[ (int) $id ] = $tipo;
	return (int) $id;
};
$slug     = fn( int $id ): string => (string) get_post_field( 'post_name', $id );
$modifica = function ( int $id ) {
	global $wpdb;
	$dopo = gmdate( 'Y-m-d H:i:s', time() + HOUR_IN_SECONDS );
	$wpdb->update( $wpdb->posts, [ 'post_modified_gmt' => $dopo, 'post_modified' => get_date_from_gmt( $dopo ) ], [ 'ID' => $id ] );
	clean_post_cache( $id );
};

// Righe delle differenze, come le stampa l'apply (voce, prima, dopo)
$righe = [];
$riga  = fn( string $voce, $prima_valore, $dopo_valore ): string => $voce . ': ' . var_export( $prima_valore, true ) . ' → ' . var_export( $dopo_valore, true );
$diff  = function ( string $voce, $prima_valore, $dopo_valore ) use ( &$righe, $riga ) {
	$righe[] = $riga( $voce, $prima_valore, $dopo_valore );
};
// Un giro dell'apply, dentro wpf_wpml_tutte_le_lingue() come in apply.php: [ esito, righe ]
$applica = function ( bool $prova, array $defaults, int $privacy_id ) use ( &$righe, $diff ): array {
	$righe = [];
	$ok    = wpf_wpml_tutte_le_lingue( fn() => wpf_default_content_apply( $prova, $diff, $defaults, $privacy_id ) );
	return [ $ok, $righe ];
};
$riga_cestino = fn( string $tipo, int $id, string $slug_post ): string => $riga( "default: {$tipo} {$id} {$slug_post}", 'presente', 'nel cestino' );
$riga_opzione = fn( int $id ): string => $riga( 'default: opzione wp_page_for_privacy_policy', $id, 0 );
$valore       = fn() => (int) get_option( $opzione );

// Rimette lo stato com'era: alla fine e, se il test si ferma con un errore fatale, alla chiusura di PHP
$rimesso = false;
$rimetti = function () use ( &$rimesso, &$temporanei, $prima, $opzione, $assente ) {
	if ( $rimesso ) {
		return;
	}
	$rimesso = true;
	foreach ( $temporanei as $id => $tipo ) {
		wpf_wpml_cancella_post( $id, 'post_' . $tipo );
	}
	if ( $assente === $prima['opzione'] ) {
		delete_option( $opzione );
	} else {
		update_option( $opzione, $prima['opzione'] );
	}
};
register_shutdown_function( $rimetti );

set_error_handler(
	function ( $errno, $message, $file, $line ) use ( &$failures ) {
		$failures[] = "avviso PHP: {$message} ({$file}:{$line})";
		return true;
	}
);

try {
	// 0. Valori predefiniti: quelli dell'installazione italiana, e la prova con quelli non scrive nulla
	$check(
		[
			1 => [ 'post', 'ciao-mondo' ],
			2 => [ 'page', 'pagina-di-esempio' ],
			3 => [ 'page', 'privacy-policy' ],
		] === WPF_DEFAULT_CONTENT && 3 === WPF_DEFAULT_PRIVACY_ID,
		'contenuti di default: 1 ciao-mondo, 2 pagina-di-esempio, 3 privacy-policy'
	);
	$parametri = ( new ReflectionFunction( 'wpf_default_content_apply' ) )->getParameters();
	$check( WPF_DEFAULT_CONTENT === $parametri[2]->getDefaultValue() && WPF_DEFAULT_PRIVACY_ID === $parametri[3]->getDefaultValue(), 'parametri facoltativi con i valori predefiniti' );
	$righe = [];
	$ok    = wpf_wpml_tutte_le_lingue( fn() => wpf_default_content_apply( true, $diff ) );
	$check( true === $ok, 'prova con i valori predefiniti → true' );
	$check( $prima['veri'] === array_map( $stato, array_combine( array_keys( WPF_DEFAULT_CONTENT ), array_keys( WPF_DEFAULT_CONTENT ) ) ) && $prima['db'] === $opzione_db(), 'prova con i valori predefiniti: post 1, 2, 3 e opzione invariati (righe: ' . implode( '; ', $righe ) . ')' );

	// 1. Contenuti di default temporanei: uno non modificato, uno modificato, uno con un altro slug, uno già nel
	// cestino, la bozza della privacy non modificata a cui punta l'opzione
	$intatto  = $crea( 'post', 'intatto' );
	$cambiato = $crea( 'page', 'modificato' );
	$modifica( $cambiato );
	$altro    = $crea( 'page', 'altro-slug' );
	$cestino  = $crea( 'post', 'cestino' );
	$slug_cestino = $slug( $cestino );
	wp_trash_post( $cestino );
	$privacy = $crea( 'page', 'privacy', 'draft' );
	update_option( $opzione, $privacy );
	$defaults = [
		$intatto  => [ 'post', $slug( $intatto ) ],
		$cambiato => [ 'page', $slug( $cambiato ) ],
		$altro    => [ 'page', $slug( $altro ) . '-default' ],
		$cestino  => [ 'post', $slug_cestino ],
		$privacy  => [ 'page', $slug( $privacy ) ],
	];
	$check( 'trash' === get_post_status( $cestino ), 'post già nel cestino prima del giro' );
	$check( get_post_field( 'post_date_gmt', $intatto ) === get_post_field( 'post_modified_gmt', $intatto ) && get_post_field( 'post_date_gmt', $privacy ) === get_post_field( 'post_modified_gmt', $privacy ), 'post temporanei non modificati: post_modified_gmt uguale a post_date_gmt' );
	$check( get_post_field( 'post_date_gmt', $cambiato ) !== get_post_field( 'post_modified_gmt', $cambiato ), 'post modificato: post_modified_gmt diverso da post_date_gmt' );
	$attese = [
		$riga_cestino( 'post', $intatto, $defaults[ $intatto ][1] ),
		$riga_cestino( 'page', $privacy, $defaults[ $privacy ][1] ),
		$riga_opzione( $privacy ),
	];
	$prima_giro = array_map( $stato, array_combine( array_keys( $defaults ), array_keys( $defaults ) ) );

	// In prova: solo le righe, nulla scritto
	[ $ok, $trovate ] = $applica( true, $defaults, $privacy );
	$check( true === $ok, 'prova → true' );
	$check( $attese === $trovate, 'prova: righe del non modificato, della privacy e dell\'opzione: ' . implode( '; ', $trovate ) );
	$check( $prima_giro === array_map( $stato, array_combine( array_keys( $defaults ), array_keys( $defaults ) ) ), 'prova: nessun post toccato' );
	$check( $privacy === $valore(), 'prova: opzione invariata' );

	// Con --conferma: non modificato e privacy nel cestino, opzione azzerata, gli altri invariati
	[ $ok, $trovate ] = $applica( false, $defaults, $privacy );
	$check( true === $ok, 'apply → true' );
	$check( $attese === $trovate, 'apply: le stesse righe della prova: ' . implode( '; ', $trovate ) );
	$check( 'trash' === get_post_status( $intatto ), 'non modificato → nel cestino' );
	$check( 'trash' === get_post_status( $privacy ), 'bozza della privacy non modificata → nel cestino' );
	$check( $prima_giro[ $cambiato ] === $stato( $cambiato ), 'modificato → invariato' );
	$check( $prima_giro[ $altro ] === $stato( $altro ), 'altro slug → invariato' );
	$check( $prima_giro[ $cestino ] === $stato( $cestino ), 'già nel cestino → invariato' );
	$check( 0 === $valore(), 'opzione azzerata: la pagina della privacy era quella di default' );

	// Secondo giro: nessuna riga, in prova e con --conferma
	$dopo_giro = array_map( $stato, array_combine( array_keys( $defaults ), array_keys( $defaults ) ) );
	[ $ok, $trovate ] = $applica( true, $defaults, $privacy );
	$check( true === $ok && [] === $trovate, 'secondo giro in prova: nessuna riga: ' . implode( '; ', $trovate ) );
	[ $ok, $trovate ] = $applica( false, $defaults, $privacy );
	$check( true === $ok && [] === $trovate, 'secondo giro: nessuna riga: ' . implode( '; ', $trovate ) );
	$check( $dopo_giro === array_map( $stato, array_combine( array_keys( $defaults ), array_keys( $defaults ) ) ), 'secondo giro: nessun post toccato' );

	// 2. Bozza della privacy già nel cestino con lo slug di default, opzione che punta a lei: si azzera solo l'opzione
	$privacy2      = $crea( 'page', 'privacy-cestino', 'draft' );
	$slug_privacy2 = $slug( $privacy2 );
	wp_trash_post( $privacy2 );
	$check( in_array( $slug( $privacy2 ), [ $slug_privacy2, "{$slug_privacy2}__trashed" ], true ), 'privacy nel cestino con lo slug di default: ' . $slug( $privacy2 ) );
	update_option( $opzione, $privacy2 );
	$defaults2 = [ $privacy2 => [ 'page', $slug_privacy2 ] ];
	[ $ok, $trovate ] = $applica( true, $defaults2, $privacy2 );
	$check( true === $ok && [ $riga_opzione( $privacy2 ) ] === $trovate, 'privacy già nel cestino, prova: solo la riga dell\'opzione: ' . implode( '; ', $trovate ) );
	$check( $privacy2 === $valore(), 'privacy già nel cestino, prova: opzione invariata' );
	[ $ok, $trovate ] = $applica( false, $defaults2, $privacy2 );
	$check( true === $ok && [ $riga_opzione( $privacy2 ) ] === $trovate, 'privacy già nel cestino: riga dell\'opzione: ' . implode( '; ', $trovate ) );
	$check( 0 === $valore(), 'privacy già nel cestino con lo slug di default: opzione azzerata' );
	[ $ok, $trovate ] = $applica( false, $defaults2, $privacy2 );
	$check( true === $ok && [] === $trovate, 'privacy già nel cestino, secondo giro: nessuna riga: ' . implode( '; ', $trovate ) );

	// 3. Pagine della privacy che possono essere vere: modificata, con un altro slug (nel cestino o no), di un altro tipo.
	// L'opzione punta a loro e resta com'è; nessuna riga
	$modificata = $crea( 'page', 'privacy-modificata', 'draft' );
	$modifica( $modificata );
	$altro_slug = $crea( 'page', 'privacy-altro-slug', 'draft' );
	$cestino_altro_slug = $crea( 'page', 'privacy-cestino-altro-slug', 'draft' );
	$slug_cestino_altro = $slug( $cestino_altro_slug );
	wp_trash_post( $cestino_altro_slug );
	$altro_tipo = $crea( 'post', 'privacy-altro-tipo', 'draft' );
	foreach (
		[
			'privacy modificata'                     => [ $modificata, [ 'page', $slug( $modificata ) ] ],
			'privacy con un altro slug'              => [ $altro_slug, [ 'page', $slug( $altro_slug ) . '-default' ] ],
			'privacy nel cestino con un altro slug'  => [ $cestino_altro_slug, [ 'page', $slug_cestino_altro . '-default' ] ],
			'privacy di un altro tipo'               => [ $altro_tipo, [ 'page', $slug( $altro_tipo ) ] ],
		] as $caso => [ $id, $default ]
	) {
		update_option( $opzione, $id );
		$prima_caso = $stato( $id );
		foreach ( [ true => 'prova', false => 'apply' ] as $prova => $modo ) {
			[ $ok, $trovate ] = $applica( (bool) $prova, [ $id => $default ], $id );
			$check( true === $ok && [] === $trovate, "{$caso}, {$modo}: nessuna riga: " . implode( '; ', $trovate ) );
			$check( $id === $valore(), "{$caso}, {$modo}: opzione invariata" );
			$check( $prima_caso === $stato( $id ), "{$caso}, {$modo}: pagina invariata" );
		}
	}

	// 4. Bozza della privacy di default, opzione che punta a un'altra pagina: la bozza va nel cestino, l'opzione resta
	$privacy4 = $crea( 'page', 'privacy-opzione-altrove', 'draft' );
	update_option( $opzione, $cambiato );
	$defaults4 = [ $privacy4 => [ 'page', $slug( $privacy4 ) ] ];
	[ $ok, $trovate ] = $applica( false, $defaults4, $privacy4 );
	$check( true === $ok && [ $riga_cestino( 'page', $privacy4, $defaults4[ $privacy4 ][1] ) ] === $trovate, 'opzione su un\'altra pagina: solo la riga del cestino: ' . implode( '; ', $trovate ) );
	$check( 'trash' === get_post_status( $privacy4 ), 'opzione su un\'altra pagina: bozza nel cestino' );
	$check( $cambiato === $valore(), 'opzione su un\'altra pagina: opzione invariata' );
} catch ( Throwable $e ) {
	$check( false, 'errore: ' . $e->getMessage() );
} finally {
	restore_error_handler();
	$rimetti();
}

// Lo stato è stato rimesso com'era: opzione, post temporanei cancellati, post 1, 2 e 3 veri non toccati
$check( $prima['db'] === $opzione_db(), 'opzione wp_page_for_privacy_policy rimessa com\'era nel database: ' . wp_json_encode( $prima['db'] ) . ' → ' . wp_json_encode( $opzione_db() ) );
foreach ( array_keys( $temporanei ) as $id ) {
	$check( null === $stato( $id ), "post temporaneo {$id} cancellato" );
}
$check( $prima['veri'] === array_map( $stato, array_combine( array_keys( WPF_DEFAULT_CONTENT ), array_keys( WPF_DEFAULT_CONTENT ) ) ), 'post 1, 2 e 3 veri non toccati' );

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
