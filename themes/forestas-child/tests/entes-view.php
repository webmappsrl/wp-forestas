<?php
/**
 * Test (oc:8990): dati degli enti per le pagine (inc/entes.php), da una copia di prova scritta nell'opzione.
 * Nome del tipo in italiano e in inglese, tipo sconosciuto senza riga di log (la scrive il rinnovo, vedi entes-copy.php)
 * ed elenco dei tipi sconosciuti di una lista, testo `en` uguale a `it` marcato in
 * italiano, indirizzo nella lingua, elenco ordinato per data di creazione con gli enti senza data in fondo, dati del
 * dettaglio (immagine, testi ricchi, pagina web, collegamenti, galleria, date). Poi i «Sentieri gestiti», con l'indice
 * dei dettagli del plugin finto (filtro pre_transient_wmwp_detail_index): ruolo, ordine, nome che non torna con
 * l'indice (con il nome italiano che basta anche sulla pagina inglese), traccia senza pagina, dati della card. Il testo
 * dello shard segue forestas_dettaglio_testo_lingua() (ripiego, testi a blocchi).
 * Nessuna richiesta HTTP: le blocca e le conta. Rimette copia, opzioni degli slug e dello stato, transient di attesa,
 * lingua di WPML, file di log e indice del plugin com'erano.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/entes-view.php
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
// Valore di una chiave che può valere null (con `??` un null sembrerebbe assente): 'assente' se la chiave non c'è
$campo = fn( $array, string $key ) => is_array( $array ) && array_key_exists( $key, $array ) ? $array[ $key ] : 'assente';

foreach ( [ 'forestas_entes_type_label', 'forestas_entes_type_labels', 'forestas_entes_unknown_types', 'forestas_entes_text', 'forestas_entes_url', 'forestas_entes_list', 'forestas_entes_view' ] as $funzione ) {
	if ( ! function_exists( $funzione ) ) {
		echo "FAIL: {$funzione} non esiste\n";
		exit( 1 );
	}
}

$opzione = 'forestas_entes_copy';
$slug_op = 'forestas_entes_slugs';
$stato   = 'forestas_entes_state';
$attesa  = 'forestas_entes_retry';

// Stato da rimettere alla fine
$prima    = [
	'copia'    => get_option( $opzione, null ),
	'slug'     => get_option( $slug_op, null ),
	'stato'    => get_option( $stato, null ),
	'attesa'   => get_transient( $attesa ),
	'scadenza' => (int) get_option( '_transient_timeout_' . $attesa, 0 ),
	'lingua'   => (string) apply_filters( 'wpml_current_language', 'it' ),
	'log'      => ini_get( 'error_log' ),
];
$file_log = tempnam( sys_get_temp_dir(), 'entes-view-log-' );
$log      = function () use ( $file_log ): string {
	clearstatcache();
	return (string) file_get_contents( $file_log );
};

// Nessuna richiesta HTTP: con la copia salvata le pagine degli enti non chiamano lo shard
$richieste = [];
$http      = function ( $pre, $args, $url ) use ( &$richieste ) {
	$richieste[] = (string) $url;
	return new WP_Error( 'test_entes_view', 'richiesta HTTP bloccata dal test' );
};

$lingua = function ( string $codice ) {
	do_action( 'wpml_switch_language', $codice );
};

// Indice dei dettagli del plugin finto senza scrivere nel database: get_transient() lo prende dal filtro, e la copia
// che il plugin ricorda nella richiesta ($GLOBALS['wmwp_detail_index_memo']) si butta prima e dopo. L'indice vero, la
// sua scadenza e l'ora dell'ultima ricostruzione si confrontano alla fine
$indice_finto  = null;
$indice_filtro = function ( $pre ) use ( &$indice_finto ) {
	return null !== $indice_finto ? $indice_finto : $pre;
};
$indice_prima  = [
	'memo'        => array_key_exists( 'wmwp_detail_index_memo', $GLOBALS ) ? [ $GLOBALS['wmwp_detail_index_memo'] ] : null,
	'transient'   => get_option( '_transient_wmwp_detail_index', null ),
	'scadenza'    => get_option( '_transient_timeout_wmwp_detail_index', null ),
	'ricostruito' => get_option( 'wmwp_detail_index_rebuilt_at', null ),
];

// Rimette lo stato com'era: alla fine e, se il test si ferma con un errore fatale, alla chiusura di PHP
$rimesso = false;
$rimetti = function () use ( &$rimesso, &$indice_finto, $prima, $opzione, $slug_op, $stato, $attesa, $file_log, $http, $lingua, $indice_filtro, $indice_prima ) {
	if ( $rimesso ) {
		return;
	}
	$rimesso      = true;
	$indice_finto = null;
	remove_filter( 'pre_transient_wmwp_detail_index', $indice_filtro );
	unset( $GLOBALS['wmwp_detail_index_memo'] );
	if ( null !== $indice_prima['memo'] ) {
		$GLOBALS['wmwp_detail_index_memo'] = $indice_prima['memo'][0];
	}
	remove_filter( 'pre_http_request', $http, 1 );
	ini_set( 'error_log', (string) $prima['log'] );
	@unlink( $file_log );
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

// Copia di prova nella forma di api/v1/entes
$foto       = function ( string $nome ): array {
	return [
		'id'        => crc32( $nome ),
		'url'       => "https://shard.example/{$nome}.jpg",
		'thumbnail' => "https://shard.example/{$nome}-400x200.jpg",
		'sizes'     => [
			'400x200'  => "https://shard.example/{$nome}-400x200.jpg",
			'1440x500' => "https://shard.example/{$nome}-1440x500.jpg",
		],
		'caption'   => [ 'it' => "Didascalia {$nome}" ],
		'author'    => [ 'it' => "Autore {$nome}" ],
		'credits'   => [ 'it' => "Credits {$nome}" ],
	];
};
$stintino   = [
	'id'            => 11,
	'slug'          => 'comune-di-stintino',
	'name'          => [ 'it' => 'Comune di Stintino', 'en' => 'Comune di Stintino' ],
	'type'          => 'comune',
	'description'   => [
		'it' => '<h2>Storia</h2><p>Il borgo.</p><h4>Il porto</h4><p>Le barche.</p><script>alert(1)</script>',
		'en' => '<h2>Storia</h2><p>Il borgo.</p><h4>Il porto</h4><p>Le barche.</p><script>alert(1)</script>',
	],
	'contacts'      => [ 'it' => '<p>Via Torre Falcone 26</p>', 'en' => '<p>26 Torre Falcone street</p>' ],
	'website'       => [ 'url' => 'https://www.comune.stintino.ss.it', 'title' => null ],
	'feature_image' => $foto( 'stintino' ),
	'image_gallery' => [ $foto( 'pelosa' ), $foto( 'asinara' ) ],
	'related_url'   => [
		'Sito del Parco dell\'Asinara' => 'https://www.parcoasinara.org',
		'https://example.org/senza-etichetta' => 'https://example.org/senza-etichetta',
	],
	'created_at'    => '2015-03-10T10:00:00+00:00',
	'updated_at'    => '2024-02-21 10:37:26',
	'tracks'        => [],
	'pois'          => [],
];
$gal        = [
	'id'            => 12,
	'slug'          => 'gal-bmg',
	'name'          => [ 'it' => 'GAL BMG' ],
	'type'          => 'ente-partner',
	'description'   => [],
	'contacts'      => [],
	'website'       => null,
	'feature_image' => null,
	'image_gallery' => [],
	'related_url'   => [],
	'created_at'    => null,
	'updated_at'    => null,
	'tracks'        => [],
	'pois'          => [],
];
$agenzia    = [
	'id'            => 13,
	'slug'          => 'agenzia-forestas',
	'name'          => [ 'it' => 'Agenzia Forestas', 'en' => 'Forestas Agency' ],
	'type'          => 'boh',
	'description'   => [ 'it' => '<p>Agenzia</p>' ],
	'contacts'      => [],
	'website'       => [ 'url' => 'https://www.sardegnaambiente.it/forestas', 'title' => 'Sito di Forestas' ],
	'feature_image' => $foto( 'forestas' ),
	'image_gallery' => [],
	'related_url'   => [],
	'created_at'    => '2013-05-09T11:11:26+00:00',
	'updated_at'    => null,
	'tracks'        => [],
	'pois'          => [],
];
$senza_slug = array_merge( $gal, [ 'id' => 14, 'slug' => null, 'name' => [ 'it' => 'Senza slug' ], 'created_at' => '2010-01-01T00:00:00+00:00' ] );
$enti       = [ $stintino, $gal, $agenzia, $senza_slug ];

try {
	delete_transient( $attesa );
	update_option( $opzione, [ 'generated_at' => '2026-11-16T10:00:00+00:00', 'fetched_at' => gmdate( 'c' ), 'data' => $enti ], false );
	$lingua( 'it' );

	// 1. Nome del tipo: i cinque valori in italiano (i nomi dei termini Drupal) e in inglese
	$attesi = [
		'complesso-forestale'         => [ 'Complesso forestale', 'Forest complex' ],
		'ente-partner'                => [ 'Ente partner', 'Partner organisation' ],
		'altre-pubbliche-istituzioni' => [ 'Altre Pubbliche Istituzioni', 'Other public institutions' ],
		'privato-associazione'        => [ 'Privato/associazione', 'Private body or association' ],
		'comune'                      => [ 'Comune', 'Municipality' ],
	];
	foreach ( $attesi as $tipo => [ $it, $en ] ) {
		$check( $it === forestas_entes_type_label( $tipo ), "tipo {$tipo} in italiano → «{$it}» (" . var_export( forestas_entes_type_label( $tipo ), true ) . ')' );
	}
	$lingua( 'en-us' );
	foreach ( $attesi as $tipo => [ $it, $en ] ) {
		$check( $en === forestas_entes_type_label( $tipo ), "tipo {$tipo} in inglese → «{$en}» (" . var_export( forestas_entes_type_label( $tipo ), true ) . ')' );
	}
	$lingua( 'it' );
	// Tipo sconosciuto o assente: null, senza log (si chiama a ogni visita: i tipi sconosciuti li segnala il rinnovo)
	file_put_contents( $file_log, '' );
	$check( null === forestas_entes_type_label( 'boh' ), 'tipo sconosciuto → null' );
	$check( null === forestas_entes_type_label( 'boh' ), 'tipo sconosciuto, seconda volta → null' );
	$check( null === forestas_entes_type_label( '' ), 'tipo assente → null' );
	$check( '' === $log(), 'tipo sconosciuto o assente: nessuna riga di log: ' . $log() );
	$check( array_keys( $attesi ) === array_keys( forestas_entes_type_labels() ), 'forestas_entes_type_labels(): i cinque tipi conosciuti' );
	$check( [ 'boh', 'pippo' ] === forestas_entes_unknown_types( [ [ 'type' => 'pippo' ], [ 'type' => 'comune' ], [ 'type' => 'boh' ], [ 'type' => '' ], [ 'type' => null ], [ 'type' => 'boh' ], 'non un ente' ] ), 'tipi sconosciuti: una volta, in ordine, senza i conosciuti e gli assenti' );
	$check( [] === forestas_entes_unknown_types( [ [ 'type' => 'comune' ], [ 'type' => 'ente-partner' ] ] ), 'nessun tipo sconosciuto → []' );

	// 2. Testo nella lingua: `en` uguale a `it` è il testo italiano copiato, `en` diverso è inglese
	$uguale  = forestas_entes_text( [ 'it' => 'Comune di Stintino', 'en' => 'Comune di Stintino' ], 'en' );
	$diverso = forestas_entes_text( [ 'it' => 'Agenzia Forestas', 'en' => 'Forestas Agency' ], 'en' );
	$manca   = forestas_entes_text( [ 'it' => 'GAL BMG' ], 'en' );
	$check( [ 'text' => 'Comune di Stintino', 'lang' => 'it' ] === $uguale, '`en` uguale a `it` → lang «it»: ' . wp_json_encode( $uguale ) );
	$check( [ 'text' => 'Forestas Agency', 'lang' => null ] === $diverso, '`en` diverso da `it` → testo inglese, lang null: ' . wp_json_encode( $diverso ) );
	$check( [ 'text' => 'GAL BMG', 'lang' => 'it' ] === $manca, '`en` assente → italiano con lang «it»: ' . wp_json_encode( $manca ) );
	$check( [ 'text' => 'Agenzia Forestas', 'lang' => null ] === forestas_entes_text( [ 'it' => 'Agenzia Forestas', 'en' => 'Forestas Agency' ], 'it' ), 'pagina italiana → italiano, lang null' );
	$check( [ 'text' => 'Forestas Agency', 'lang' => null ] === forestas_entes_text( [ 'it' => 'Agenzia Forestas', 'en' => 'Forestas Agency' ], 'en-us' ), 'codice WPML «en-us» come «en»' );
	$check( [ 'text' => '', 'lang' => null ] === forestas_entes_text( [], 'en' ), 'valore [] → testo vuoto' );
	$check( [ 'text' => '', 'lang' => null ] === forestas_entes_text( null, 'it' ), 'valore null → testo vuoto' );

	// 2b. Stesso testo del dettaglio di percorsi e POI (forestas_dettaglio_testo_lingua(): ripiego e testi a blocchi);
	// gli enti aggiungono solo `en` uguale a `it` marcato in italiano
	foreach (
		[
			[ [ 'it' => [ 'Primo blocco', 'Secondo blocco' ] ], 'it' ],
			[ [ 'en' => 'Only English' ], 'it' ],
			[ [ 'it' => '', 'de' => 'Nur Deutsch' ], 'en' ],
			[ [ 'it' => 'Italiano', 'en' => '  ' ], 'en-us' ],
			[ '  Testo semplice ', 'en' ],
		] as [ $valore, $codice ]
	) {
		$testo_enti = forestas_entes_text( $valore, $codice )['text'];
		$testo_dett = forestas_dettaglio_testo_lingua( $valore, forestas_entes_lang( $codice ) )['testo'];
		$check( $testo_dett === $testo_enti, 'stesso testo del dettaglio per ' . wp_json_encode( $valore ) . " in {$codice}: «{$testo_enti}» / «{$testo_dett}»" );
	}
	$blocchi = forestas_entes_text( [ 'it' => [ 'Primo', 'Secondo' ], 'en' => [ 'Primo', 'Secondo' ] ], 'en' );
	$check( [ 'text' => "Primo\nSecondo", 'lang' => 'it' ] === $blocchi, 'blocchi `en` uguali a `it` → uniti, lang «it»: ' . wp_json_encode( $blocchi ) );
	$solo_en = forestas_entes_text( [ 'en' => 'Only English' ], 'it' );
	$check( [ 'text' => 'Only English', 'lang' => 'en' ] === $solo_en, 'pagina italiana con solo `en` → lang «en»: ' . wp_json_encode( $solo_en ) );
	$check( [ 'text' => 'Testo semplice', 'lang' => null ] === forestas_entes_text( '  Testo semplice ', 'en' ), 'stringa → testo senza lang' );

	// 3. Indirizzo del dettaglio nella lingua
	$url_en = forestas_entes_url( 'comune-di-stintino', 'en' );
	$url_it = forestas_entes_url( 'comune-di-stintino', 'it' );
	$check( str_ends_with( $url_en, '/en/enti/comune-di-stintino/' ), 'indirizzo inglese: ' . $url_en );
	$check( untrailingslashit( (string) get_option( 'home' ) ) . '/enti/comune-di-stintino/' === $url_it, 'indirizzo italiano: ' . $url_it );

	// 4. Elenco: per data di creazione crescente, senza data in fondo, senza gli enti senza slug
	$elenco = forestas_entes_list( 'it' );
	$check( [ 'agenzia-forestas', 'comune-di-stintino', 'gal-bmg' ] === array_column( $elenco, 'slug' ), 'elenco ordinato 2013, 2015, senza data: ' . implode( ', ', array_column( $elenco, 'slug' ) ) );
	$chiavi = array_keys( $elenco[1] ?? [] );
	sort( $chiavi );
	$check( [ 'category', 'id', 'image', 'name', 'name_lang', 'slug', 'type', 'url' ] === $chiavi, 'chiavi della card dell\'elenco: ' . implode( ', ', $chiavi ) );
	$check( 'Comune di Stintino' === ( $elenco[1]['name'] ?? null ) && null === $campo( $elenco[1], 'name_lang' ), 'card: nome in italiano' );
	$check( 'Comune' === ( $elenco[1]['type'] ?? null ), 'card: nome del tipo' );
	$check( null === $campo( $elenco[0], 'type' ), 'card: tipo sconosciuto → null' );
	// Categoria della card come la teaser di Drupal: il termine padre del tipo, o il tipo se è una radice
	$check( 'Altre Pubbliche Istituzioni' === ( $elenco[1]['category'] ?? null ), 'card: categoria di un Comune → Altre Pubbliche Istituzioni' );
	$check( 'Ente partner' === ( $elenco[2]['category'] ?? null ), 'card: categoria di un ente partner → Ente partner' );
	$check( null === $campo( $elenco[0], 'category' ), 'card: categoria di un tipo sconosciuto → null' );
	$categorie = [
		'comune'                      => 'Altre Pubbliche Istituzioni',
		'complesso-forestale'         => 'Altre Pubbliche Istituzioni',
		'altre-pubbliche-istituzioni' => 'Altre Pubbliche Istituzioni',
		'ente-partner'                => 'Ente partner',
		'privato-associazione'        => 'Privato/associazione',
		''                            => null,
	];
	// Non $attesa: è il nome del transient di attesa, che si confronta alla fine
	foreach ( $categorie as $tipo => $expected_category ) {
		$check( $expected_category === forestas_entes_type_category( $tipo ), "categoria di «{$tipo}» → " . var_export( $expected_category, true ) . ' (' . var_export( forestas_entes_type_category( $tipo ), true ) . ')' );
	}
	$check( 'https://shard.example/stintino-400x200.jpg' === ( $elenco[1]['image'] ?? null ), 'card: immagine piccola' );
	$check( null === $campo( $elenco[2], 'image' ), 'card: senza immagine → null' );
	$check( $url_it === ( $elenco[1]['url'] ?? null ), 'card: indirizzo del dettaglio' );
	$check( 11 === ( $elenco[1]['id'] ?? null ), 'card: id dell\'ente' );
	$lingua( 'en-us' );
	$elenco_en = forestas_entes_list( 'en' );
	$check( 'Forestas Agency' === ( $elenco_en[0]['name'] ?? null ) && null === $campo( $elenco_en[0], 'name_lang' ), 'card inglese: nome tradotto senza lang' );
	$check( 'it' === ( $elenco_en[1]['name_lang'] ?? null ), 'card inglese: nome uguale all\'italiano con lang «it»' );
	$check( 'Municipality' === ( $elenco_en[1]['type'] ?? null ), 'card inglese: tipo in inglese' );
	$check( 'Other public institutions' === ( $elenco_en[1]['category'] ?? null ), 'card inglese: categoria in inglese' );
	$check( $url_en === ( $elenco_en[1]['url'] ?? null ), 'card inglese: indirizzo inglese' );
	$lingua( 'it' );

	// 5. Dettaglio completo
	$vista = forestas_entes_view( $stintino, 'it' );
	$check( 'Comune di Stintino' === ( $vista['name'] ?? null ) && null === $campo( $vista, 'name_lang' ), 'dettaglio: nome' );
	$check( 'Comune' === ( $vista['type'] ?? null ), 'dettaglio: tipo' );
	$check(
		[
			'url'        => 'https://shard.example/stintino-1440x500.jpg',
			'didascalia' => 'Didascalia stintino',
			'autore'     => 'Autore stintino',
			'credits'    => 'Credits stintino',
		] === ( $vista['image'] ?? null ),
		'dettaglio: immagine principale con autore e credits: ' . wp_json_encode( $vista['image'] ?? null )
	);
	$descrizione = (string) ( $vista['description'] ?? '' );
	$check( false !== strpos( $descrizione, '<h3>Storia</h3>' ) && false !== strpos( $descrizione, '<h4>Il porto</h4>' ), 'dettaglio: titoli della descrizione da h3, senza salti: ' . $descrizione );
	$check( false === strpos( $descrizione, '<script' ), 'dettaglio: descrizione filtrata (niente script)' );
	$check( null === $campo( $vista, 'description_lang' ), 'dettaglio: descrizione italiana senza lang' );
	$check( '<p>Via Torre Falcone 26</p>' === ( $vista['contacts'] ?? null ), 'dettaglio: contatti' );
	$check( [ 'url' => 'https://www.comune.stintino.ss.it', 'title' => 'https://www.comune.stintino.ss.it' ] === ( $vista['website'] ?? null ), 'dettaglio: pagina web senza titolo → l\'indirizzo come testo' );
	$check(
		[
			[ 'url' => 'https://www.parcoasinara.org', 'title' => 'Sito del Parco dell\'Asinara' ],
			[ 'url' => 'https://example.org/senza-etichetta', 'title' => 'https://example.org/senza-etichetta' ],
		] === ( $vista['links'] ?? null ),
		'dettaglio: collegamenti da related_url: ' . wp_json_encode( $vista['links'] ?? null )
	);
	$collegamenti = forestas_entes_links( [ 'Cattivo' => 'javascript:alert(1)', 'Buono' => 'https://example.org/ok', 'Vuoto' => '  ' ] );
	$check( [ [ 'url' => 'https://example.org/ok', 'title' => 'Buono' ] ] === $collegamenti, 'collegamenti: javascript: scartato, https tenuto: ' . wp_json_encode( $collegamenti ) );
	$check( 2 === count( (array) ( $vista['gallery'] ?? [] ) ) && 'https://shard.example/pelosa.jpg' === ( $vista['gallery'][0]['url'] ?? null ), 'dettaglio: galleria' );
	$check( date_i18n( get_option( 'date_format' ), strtotime( '2024-02-21' ) ) === ( $vista['updated'] ?? null ), 'dettaglio: data di aggiornamento nel formato del sito: ' . var_export( $vista['updated'] ?? null, true ) );
	$check( date_i18n( get_option( 'date_format' ), strtotime( '2015-03-10' ) ) === ( $vista['created'] ?? null ), 'dettaglio: data di creazione nel formato del sito: ' . var_export( $vista['created'] ?? null, true ) );
	$check( 'Sito di Forestas' === ( forestas_entes_view( $agenzia, 'it' )['website']['title'] ?? null ), 'dettaglio: pagina web con il suo titolo' );

	// 5b. Dettaglio inglese: testi uguali all'italiano con lang «it», traduzioni vere senza lang
	$lingua( 'en-us' );
	$vista_en = forestas_entes_view( $stintino, 'en' );
	$check( 'it' === ( $vista_en['name_lang'] ?? null ), 'dettaglio inglese: nome copiato con lang «it»' );
	$check( 'it' === ( $vista_en['description_lang'] ?? null ), 'dettaglio inglese: descrizione copiata con lang «it»' );
	$check( '<p>26 Torre Falcone street</p>' === ( $vista_en['contacts'] ?? null ) && null === $campo( $vista_en, 'contacts_lang' ), 'dettaglio inglese: contatti tradotti senza lang' );
	$check( 'Municipality' === ( $vista_en['type'] ?? null ), 'dettaglio inglese: tipo in inglese' );
	$lingua( 'it' );

	// 5c. Dettaglio vuoto: sezioni assenti
	$vuota = forestas_entes_view( $gal, 'it' );
	$check( null === $campo( $vuota, 'description' ) && null === $campo( $vuota, 'description_lang' ), 'description [] → null' );
	$check( null === $campo( $vuota, 'contacts' ), 'contacts [] → null' );
	$check( null === $campo( $vuota, 'image' ), 'senza feature_image → image null' );
	$check( null === $campo( $vuota, 'website' ), 'senza website → null' );
	$check( [] === $campo( $vuota, 'links' ) && [] === $campo( $vuota, 'gallery' ), 'senza collegamenti né foto → []' );
	$check( null === $campo( $vuota, 'updated' ) && null === $campo( $vuota, 'created' ), 'senza date → null' );
	$check( 'Ente partner' === ( $vuota['type'] ?? null ), 'tipo ente-partner' );

	// 6. «Sentieri gestiti»: tracce con ruolo `gestore`, dalla più recente, solo con una pagina nell'indice del plugin e
	// con lo stesso nome dell'indice per quell'id (dopo il reset di UAT un id può passare a un altro sentiero)
	if ( ! function_exists( 'forestas_entes_managed_tracks' ) ) {
		$check( false, 'forestas_entes_managed_tracks non esiste' );
	} elseif ( ! function_exists( 'wmwp_detail_index' ) || ! function_exists( 'wmwp_detail_base_signature' ) ) {
		$check( false, 'plugin wp-package non attivo: indice dei dettagli assente' );
	} else {
		// L'impronta delle basi non deve dipendere dalla lingua corrente: se cambiasse, wmwp_detail_index() butterebbe
		// l'indice in cache (quello vero) al primo cambio di lingua del test
		$lingua( 'en-us' );
		$firma_en = wmwp_detail_base_signature();
		$lingua( 'it' );
		$firma = wmwp_detail_base_signature();
		if ( $firma !== $firma_en ) {
			$check( false, "impronta delle basi diversa fra le lingue ({$firma} / {$firma_en}): indice finto non usato" );
		} else {
			$url_pagina   = fn( string $percorso ) => [ 'it' => 'sentiero/' . $percorso, 'en-us' => 'trail/' . $percorso ];
			$indice_finto = [
				'langs'    => [ 'it', 'en-us' ],
				'paths'    => [],
				'urls'     => [
					'track' => [
						901 => $url_pagina( 'sentiero-901' ),
						902 => $url_pagina( 'sentiero-902' ),
						903 => [ 'it' => 'sentiero/sentiero-903', 'en-us' => 'trail/trail-903' ],
						904 => $url_pagina( 'sentiero-904' ),
						905 => $url_pagina( 'un-altro-sentiero' ),
						907 => $url_pagina( 'sentiero-907' ),
						909 => [ 'it' => 'sentiero/sentiero-909', 'en-us' => 'trail/sentiero-909' ],
						910 => $url_pagina( 'un-altro-910' ),
					],
				],
				'items'    => [
					'track' => [
						901 => [ 'names' => [ 'it' => 'Sentiero 901' ], 'source' => null ],
						// Nell'indice vero i nomi arrivano da Elasticsearch così come sono, anche con un tab in testa
						902 => [ 'names' => [ 'it' => "\tSentiero  902 (B 902)" ], 'source' => null ],
						903 => [ 'names' => [ 'it' => 'Sentiero 903', 'en' => 'Trail 903' ], 'source' => null ],
						904 => [ 'names' => [ 'it' => 'Sentiero 904' ], 'source' => null ],
						905 => [ 'names' => [ 'it' => 'Un altro sentiero' ], 'source' => null ],
						907 => [ 'source' => null ],
						// Nomi giusti ma nessun indirizzo: senza pagina di dettaglio la card non c'è
						908 => [ 'names' => [ 'it' => 'Sentiero 908' ], 'source' => null ],
						// Indice ricostruito prima della traduzione inglese del nome: solo il nome italiano
						909 => [ 'names' => [ 'it' => 'Sentiero 909' ], 'source' => null ],
						// Id passato a un altro sentiero: né il nome inglese né quello italiano tornano
						910 => [ 'names' => [ 'it' => 'Un altro 910', 'en' => 'Another 910' ], 'source' => null ],
					],
				],
				'suffixes' => [],
				'counts'   => [ 'track' => 6 ],
				'count'    => 6,
				'config'   => $firma,
			];
			add_filter( 'pre_transient_wmwp_detail_index', $indice_filtro );
			unset( $GLOBALS['wmwp_detail_index_memo'] );
			$check( 6 === ( wmwp_detail_index()['count'] ?? null ), 'indice finto in uso' );

			$traccia = fn( int $id, array $ruoli, ?string $creata, array $nome, array $card = [] ) => array_merge(
				[
					'id'                => $id,
					'ref'               => null,
					'name'              => $nome,
					'validation_status' => 'validation-status-validated',
					'roles'             => $ruoli,
					'created_at'        => $creata,
				],
				$card
			);
			$gestore = array_merge(
				$stintino,
				[
					'tracks' => [
						$traccia( 901, [ 'manutentore' ], '2025-01-01 00:00:00', [ 'it' => 'Sentiero 901' ] ),
						$traccia(
							902,
							[ 'gestore' ],
							'2020-05-01 10:00:00',
							[ 'it' => 'Sentiero 902 (B 902)' ],
							[
								'ref'              => 'B 902',
								'route_type'       => [ 'identifier' => 'route-type-trail', 'name' => [ 'it' => 'Sentiero', 'en' => 'Trail' ] ],
								'feature_image'    => [ 'url' => 'https://shard.example/902.jpg', 'thumbnail' => 'https://shard.example/902-400x200.jpg' ],
								'distance'         => 12.4,
								'duration_forward' => 150,
							]
						),
						$traccia( 904, [ 'gestore' ], null, [ 'it' => 'Sentiero 904' ] ),
						$traccia( 903, [ 'gestore', 'manutentore' ], '2024-01-10 09:00:00', [ 'it' => 'Sentiero 903', 'en' => 'Trail 903' ] ),
						$traccia( 905, [ 'gestore' ], '2023-01-01 00:00:00', [ 'it' => 'Sentiero 905' ] ),
						$traccia( 906, [ 'gestore' ], '2022-01-01 00:00:00', [ 'it' => 'Sentiero 906' ] ),
						$traccia( 907, [ 'gestore' ], '2021-01-01 00:00:00', [ 'it' => 'Sentiero 907' ] ),
						$traccia( 908, [ 'gestore' ], '2021-06-01 00:00:00', [ 'it' => 'Sentiero 908' ] ),
					],
				]
			);

			$gestiti = forestas_entes_managed_tracks( $gestore );
			$check( [ 903, 902, 904 ] === array_column( $gestiti, 'id' ), 'sentieri gestiti: solo gestore, 2024, 2020, senza data; non il manutentore, non il nome diverso dall\'indice, non quello senza pagina, non quello senza nomi nell\'indice: ' . implode( ', ', array_column( $gestiti, 'id' ) ) );
			$card = $gestiti[1] ?? [];
			$check( 'https://shard.example/902-400x200.jpg' === ( $card['immagine'] ?? null ), 'card: foto' );
			$check( 'Sentiero 902 (B 902)' === ( $card['nome'] ?? null ) && 'B 902' === ( $card['codice'] ?? null ), 'card: nome con codice' );
			$check( 'Sentiero' === ( $card['tipo'] ?? null ), 'card: tipo di percorso' );
			$check( null !== forestas_dettaglio_misura( 12.4, 'km', 1 ) && forestas_dettaglio_misura( 12.4, 'km', 1 ) === ( $card['lunghezza'] ?? null ), 'card: lunghezza dai campi della card: ' . var_export( $card['lunghezza'] ?? null, true ) );
			$check( null !== forestas_dettaglio_durata( 150 ) && forestas_dettaglio_durata( 150 ) === ( $card['tempo'] ?? null ), 'card: tempo dai campi della card: ' . var_export( $card['tempo'] ?? null, true ) );
			$check( untrailingslashit( (string) get_option( 'home' ) ) . '/sentiero/sentiero-902/' === ( $card['url'] ?? null ), 'card: indirizzo del dettaglio dall\'indice: ' . var_export( $card['url'] ?? null, true ) );

			$lingua( 'en-us' );
			$gestiti_en = forestas_entes_managed_tracks( $gestore );
			$check( [ 903, 902, 904 ] === array_column( $gestiti_en, 'id' ), 'sentieri gestiti in inglese: ' . implode( ', ', array_column( $gestiti_en, 'id' ) ) );
			$check( 'Trail 903' === ( $gestiti_en[0]['nome'] ?? null ) && str_ends_with( (string) ( $gestiti_en[0]['url'] ?? '' ), '/en/trail/trail-903/' ), 'sentiero gestito in inglese: nome inglese confrontato con l\'indice, indirizzo inglese' );
			$lingua( 'it' );

			// Il nome italiano della traccia basta anche sulla pagina inglese; un id passato a un altro sentiero resta fuori
			$tradotto = array_merge(
				$stintino,
				[
					'tracks' => [
						$traccia( 909, [ 'gestore' ], '2024-01-01 00:00:00', [ 'it' => 'Sentiero 909', 'en' => 'Trail 909' ] ),
						$traccia( 910, [ 'gestore' ], '2023-01-01 00:00:00', [ 'it' => 'Sentiero 910', 'en' => 'Trail 910' ] ),
					],
				]
			);
			$check( [ 909 ] === array_column( forestas_entes_managed_tracks( $tradotto ), 'id' ), 'nome italiano nell\'indice, pagina italiana: 909 sì, 910 no' );
			$lingua( 'en-us' );
			$tradotti_en = forestas_entes_managed_tracks( $tradotto );
			$check( [ 909 ] === array_column( $tradotti_en, 'id' ), 'nome inglese non ancora nell\'indice, pagina inglese: 909 con il nome italiano, 910 no: ' . implode( ', ', array_column( $tradotti_en, 'id' ) ) );
			$check( 'Trail 909' === ( $tradotti_en[0]['nome'] ?? null ), 'card inglese con il nome inglese' );
			$lingua( 'it' );

			$check( [] === forestas_entes_managed_tracks( $gal ), 'ente senza tracce → nessun sentiero gestito' );
			$check( [] === forestas_entes_managed_tracks( array_merge( $gal, [ 'tracks' => null ] ) ), 'tracks non lista → nessun sentiero gestito' );
		}
	}

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
// L'indice vero del plugin non è stato toccato; se lo fosse, si rimette com'era
foreach ( [ 'transient' => '_transient_wmwp_detail_index', 'scadenza' => '_transient_timeout_wmwp_detail_index', 'ricostruito' => 'wmwp_detail_index_rebuilt_at' ] as $chiave => $nome ) {
	if ( $indice_prima[ $chiave ] !== get_option( $nome, null ) ) {
		$check( false, "{$nome} cambiato durante il test: rimesso com'era" );
		delete_option( $nome );
		if ( null !== $indice_prima[ $chiave ] ) {
			update_option( $nome, $indice_prima[ $chiave ], false );
		}
	}
}

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
