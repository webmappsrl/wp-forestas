<?php
/**
 * Test (oc:9000): pagina /avvisi (inc/notices.php). Copia degli avvisi (transient di 60 secondi e ultima copia buona in
 * opzione: risposta 500 o JSON non valido con copia → la copia, senza copia → `ok` false, elenco vuoto valido → `ok`
 * true), ordinamenti (gli avvisi senza data sempre in fondo), HTML della pagina (errore, zero avvisi, link al dettaglio
 * della traccia, data «aggiornato il», `lang` del messaggio solo italiano su pagina inglese). Le richieste HTTP sono finte.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/notices.php
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

foreach ( [ 'forestas_notices', 'forestas_notices_sorted', 'forestas_notices_html' ] as $funzione ) {
	if ( ! function_exists( $funzione ) ) {
		echo "FAIL: {$funzione} non esiste\n";
		exit( 1 );
	}
}

$transient = 'forestas_notices';
$opzione   = 'forestas_notices_copy';
$prima     = [
	'opzione'  => get_option( $opzione, null ),
	'lingua'   => (string) apply_filters( 'wpml_current_language', 'it' ),
];
$pulisci   = function () use ( $transient, $opzione ) {
	delete_transient( $transient );
	delete_option( $opzione );
};

// Risposta finta dello shard: [ codice, corpo ]
$risposta = [ 200, '' ];
$richieste = 0;
$http      = function ( $pre, $args, $url ) use ( &$risposta, &$richieste ) {
	if ( false === strpos( (string) $url, '/api/v1/avvisi' ) ) {
		return $pre;
	}
	++$richieste;
	return [
		'headers'  => [],
		'body'     => $risposta[1],
		'response' => [ 'code' => $risposta[0], 'message' => '' ],
		'cookies'  => [],
		'filename' => null,
	];
};
add_filter( 'pre_http_request', $http, 1, 3 );

$json = fn( array $data ) => (string) wp_json_encode( [ 'data' => $data, 'generated_at' => '2026-11-18T10:00:00+00:00' ] );
$avviso = fn( int $id, ?string $data, array $message = [ 'it' => 'Testo italiano' ] ) => [
	'track_id'     => $id,
	'ref'          => "T {$id}",
	'name'         => [ 'it' => "Sentiero {$id}", 'en' => "Trail {$id}" ],
	'message'      => $message,
	'published_at' => $data,
];
$tre = [
	$avviso( 1, null ),
	$avviso( 2, '2026-11-10T09:00:00+00:00' ),
	$avviso( 3, '2026-11-17T11:34:00+00:00' ),
];
$ids = fn( array $items ) => implode( ',', array_column( $items, 'track_id' ) );

try {
	$pulisci();

	// Ordinamenti: senza data sempre in fondo
	$check( '3,2,1' === $ids( forestas_notices_sorted( $tre, 'recent' ) ), 'recent: 3,2,1' );
	$check( '2,3,1' === $ids( forestas_notices_sorted( $tre, 'oldest' ) ), 'oldest: 2,3,1 con quello senza data in fondo' );
	$check( '3,2,1' === $ids( forestas_notices_sorted( $tre, 'altro' ) ), 'ordine sconosciuto: recent' );

	// Risposta valida: ok, copia scritta
	$risposta = [ 200, $json( $tre ) ];
	$r        = forestas_notices();
	$check( true === $r['ok'] && 3 === count( $r['items'] ), '200: ok con tre avvisi' );
	$check( is_array( get_option( $opzione ) ), '200: copia scritta' );

	// Entro i 60 secondi non richiama lo shard
	$prima_richieste = $richieste;
	forestas_notices();
	$check( $richieste === $prima_richieste, 'transient: nessuna seconda richiesta' );

	// 500 con copia
	delete_transient( $transient );
	$risposta = [ 500, 'errore' ];
	$r        = forestas_notices();
	$check( true === $r['ok'] && 3 === count( $r['items'] ), '500 con copia: ok e copia' );

	// JSON non valido con copia
	delete_transient( $transient );
	$risposta = [ 200, '{non json' ];
	$r        = forestas_notices();
	$check( true === $r['ok'] && 3 === count( $r['items'] ), 'JSON non valido con copia: ok e copia' );

	// `data` che non è una lista, con copia
	delete_transient( $transient );
	$risposta = [ 200, (string) wp_json_encode( [ 'data' => 'x' ] ) ];
	$r        = forestas_notices();
	$check( true === $r['ok'] && 3 === count( $r['items'] ), 'data non lista con copia: ok e copia' );

	// 500 senza copia
	$pulisci();
	$risposta = [ 500, 'errore' ];
	$r        = forestas_notices();
	$check( false === $r['ok'] && [] === $r['items'], '500 senza copia: ok false' );
	// Senza copia l'errore resta per 60 secondi: la pagina chiama forestas_notices() più volte (titolo, lingue,
	// template) e ogni visita non deve richiamare uno shard che non risponde (review finale oc:9000, I1)
	$prima_richieste = $richieste;
	$r               = forestas_notices();
	forestas_notices();
	$check( $richieste === $prima_richieste && false === $r['ok'], '500 senza copia: nessuna nuova richiesta entro i 60 secondi' );

	// JSON non valido senza copia
	$pulisci();
	$risposta = [ 200, '{non json' ];
	$check( false === forestas_notices()['ok'], 'JSON non valido senza copia: ok false' );

	// Elenco vuoto valido: ok true, zero avvisi, e sostituisce la copia (che prima aveva tre avvisi)
	$pulisci();
	$risposta = [ 200, $json( $tre ) ];
	forestas_notices();
	delete_transient( $transient );
	$risposta = [ 200, $json( [] ) ];
	$r        = forestas_notices();
	$check( true === $r['ok'] && [] === $r['items'], 'elenco vuoto valido: ok true, zero avvisi' );
	$check( [] === get_option( $opzione, null ), 'elenco vuoto valido: sostituisce la copia' );

	// HTML della pagina
	do_action( 'wpml_switch_language', 'it' );
	$pagina = fn( bool $ok, array $items, string $order = 'recent' ) => forestas_notices_html(
		[
			'ok'    => $ok,
			'items' => $items,
			'page'  => 1,
			'pages' => 1,
			'order' => $order,
			'lang'  => forestas_dettaglio_lingua(),
		]
	);
	$html = $pagina( false, [] );
	$check( false !== strpos( $html, 'Gli avvisi non sono disponibili in questo momento' ), 'ok false: messaggio d\'errore' );
	$html = $pagina( true, [] );
	$check( false !== strpos( $html, 'Al momento non ci sono avvisi attivi' ), 'zero avvisi: messaggio' );
	// Link al dettaglio solo per un percorso che l'indice dei dettagli ha, con l'indirizzo nella lingua; id 0 o che
	// l'indice non ha: nome senza link (porterebbe a un 404)
	$vero = (int) array_key_first( (array) ( wmwp_detail_index()['urls']['track'] ?? [] ) );
	$check( $vero > 0, 'l\'indice dei dettagli ha almeno un percorso' );
	$html = $pagina( true, [ $avviso( $vero, '2026-11-17T11:34:00+00:00' ) ] );
	$check( false !== strpos( $html, 'href="' . esc_url( wmwp_detail_url( 'track', $vero, 'it' ) ) . '" class="av-t"' ), 'link al dettaglio dall\'indice' );
	$check( false !== strpos( $html, 'aggiornato il' ) && false !== strpos( $html, '<time datetime="2026-11-17">' ), 'data «aggiornato il» in time, come nel dettaglio' );
	$check( false !== strpos( $html, 'class="wm-avviso__tag"' ), 'etichetta «Avviso» del dettaglio' );
	$html = $pagina( true, [ $avviso( 0, null ), $avviso( 999999999, null ) ] );
	$check( false === strpos( $html, '<a ' ) && 2 === substr_count( $html, '<span class="av-t"' ), 'id 0 o fuori dall\'indice: nessun link' );
	$check( false !== strpos( $html, 'name="ordine"' ) && false !== strpos( $html, 'Ordina' ), 'form di ordinamento con pulsante' );
	$html = $pagina( true, [ $avviso( 2, null ) ] );
	$check( false === strpos( $html, 'aggiornato il' ), 'senza data niente «aggiornato il»' );
	$html = $pagina( true, [ $avviso( 3, null ) ], 'oldest' );
	$check( (bool) preg_match( '/<option value="meno-recenti" selected/', $html ), 'opzione corrente selected' );

	// Messaggio solo italiano su pagina inglese: lang="it"
	do_action( 'wpml_switch_language', 'en-us' );
	$html = $pagina( true, [ $avviso( 3, null ) ] );
	$check( (bool) preg_match( '/<p lang="it"[^>]*>Testo italiano/', $html ), 'messaggio solo italiano su pagina inglese ha lang="it"' );
	// Il «·» fra codice e nome è nascosto allo screen reader, che legge una virgola (oc:9000, controllo di accessibilità)
	$check( (bool) preg_match( '/class="av-t">T 3<span aria-hidden="true"> · <\/span><span class="screen-reader-text">, <\/span>Trail 3</', $html ), 'nome in inglese senza lang, con il separatore letto come virgola' );
	// Dopo «Avviso» lo screen reader legge i due punti: «Avviso: Strada chiusa», non «Avviso Strada chiusa»
	$check( false !== strpos( forestas_notice_tag_html(), 'Notice<span class="screen-reader-text">:</span></span>' ), 'etichetta «Avviso» seguita dai due punti letti' );
} finally {
	remove_filter( 'pre_http_request', $http, 1 );
	do_action( 'wpml_switch_language', $prima['lingua'] );
	delete_transient( $transient );
	delete_option( $opzione );
	if ( null !== $prima['opzione'] ) {
		update_option( $opzione, $prima['opzione'], false );
	}
}

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
