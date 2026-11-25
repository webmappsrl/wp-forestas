<?php
/**
 * Test (oc:9000, oc:9018): filtri della ricerca unica. Percorribilità e validazione dal registro degli stati del plugin
 * (ordine del registro, nomi dei termini; i percorsi senza termine valgono lo stato di assenza); tipo, attività, scale
 * di difficoltà e aree dal config.json (fixture di UAT del plugin); chiavi e parametri in inglese; tab «Punti di
 * interesse» (slug, tipi, aree, vecchi link di Drupal, canonical).
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/search-facets.php
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

// Config.json e aree di UAT del 24/11 (fixture del plugin), al posto della copia del sito: il test non dipende dallo
// shard a cui punta il sito
// Option e transient veri rimessi com'erano alla fine, anche se il test si interrompe con un errore (review interna di
// oc:9018: un'interruzione lasciava la fixture di UAT al posto della copia del sito). Un errore fatale di WordPress
// chiude il processo prima delle funzioni di shutdown: per questo il gestore delle eccezioni
$salvati = [];
foreach ( [ WMWP_APP_CONFIG_OPTION, WMWP_APP_OVERLAYS_OPTION, WMWP_APP_OVERLAY_LAYERS_OPTION ] as $nome ) {
	$salvati[ $nome ] = get_option( $nome, null );
}
$transient_salvati = [];
foreach ( [ FORESTAS_SEARCH_LAYERS_TRANSIENT, FORESTAS_SEARCH_LAYERS_FAILED ] as $nome ) {
	$transient_salvati[ $nome ] = get_transient( $nome );
}
$ripristina_config = function () use ( $salvati, $transient_salvati ) {
	foreach ( $salvati as $nome => $valore ) {
		null === $valore ? delete_option( $nome ) : update_option( $nome, $valore, false );
	}
	foreach ( $transient_salvati as $nome => $valore ) {
		false === $valore ? delete_transient( $nome ) : set_transient( $nome, $valore, HOUR_IN_SECONDS );
	}
};
set_exception_handler(
	function ( Throwable $e ) use ( $ripristina_config ) {
		$ripristina_config();
		echo 'FAIL: errore nel test: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ")\n";
		exit( 1 );
	}
);
$fixture        = json_decode( (string) file_get_contents( WMWP_PATH . 'tests/fixtures/config-uat.json' ), true );
update_option( WMWP_APP_CONFIG_OPTION, [ 'url' => wmwp_app_config_url(), 'config' => $fixture ], false );
$square = [ 'type' => 'Polygon', 'coordinates' => [ [ [ 0, 0 ], [ 1, 0 ], [ 1, 1 ], [ 0, 0 ] ] ] ];
// I filtri leggono l'option piccola con gli id delle aree: senza, li ricavano dalla copia con le geometrie, quella del test
delete_option( WMWP_APP_OVERLAY_LAYERS_OPTION );
update_option(
	WMWP_APP_OVERLAYS_OPTION,
	[ (string) $fixture['MAP']['controls']['overlays'][3]['url'] => [ 'areas' => [ [ 'layer_id' => 3, 'geometry' => $square, 'bbox' => [ 0, 0, 1, 1 ] ] ] ] ],
	false
);

// 1. Una faccetta per una famiglia del registro: ordine del registro, nomi dei termini dello shard (oc:9018); lo stato
// di assenza con il suo nome, perché nessun dato lo porta
$facet = forestas_search_status_facet( 'walkability-status', 'walkability', 'Percorribilità' );
$check( 'terms' === $facet['type'] && 'terms.walkability-status' === $facet['field'], 'faccetta sui termini della famiglia' );
$check( 'walkability-status-unknown' === ( $facet['missing'] ?? null ), 'senza termine vale Sconosciuta' );
$check(
	[ 'walkability-status-unknown', 'walkability-status-not-walkable', 'walkability-status-partially-walkable', 'walkability-status-walkable' ] === ( $facet['order'] ?? null ),
	'ordine dello shard dal registro'
);
$check( ! isset( $facet['groups'] ), 'nessun gruppo: i nomi arrivano con i termini' );
$check( [ 'walkability-status-unknown' => 'Sconosciuta' ] === ( $facet['labels'] ?? null ), 'solo il nome dello stato di assenza' );
$check( 'Percorribilità' === ( $facet['label'] ?? '' ), 'nome del filtro da LABELS.taxonomies' );
$check( null === forestas_search_status_facet( 'nessuna-famiglia', 'x', 'X' ), 'famiglia sconosciuta: nessuna faccetta' );

// 2. I filtri dei percorsi di Forestas: chiavi in inglese, valori dal config.json (oc:9018)
$raw = forestas_search_track_facets();
$check( 'walkability' === ( $raw[0]['key'] ?? '' ) && 'validation' === ( $raw[1]['key'] ?? '' ), 'Percorribilità e validazione restano i primi filtri' );
$check( 'layers' === ( $raw[2]['from_config'] ?? '' ) && 'activities' === ( $raw[3]['from_config'] ?? '' ) && '-difficulty' === ( $raw[4]['from_families'] ?? '' ) && ! empty( $raw[5]['from_overlays'] ), 'tipo, attività, scale e aree dal config.json' );
$tracks = wmwp_search_sources()['tracks'] ?? [];
$facets = array_column( (array) ( $tracks['facets'] ?? [] ), null, 'key' );
foreach ( [ 'percorribilita', 'validazione', 'percorso', 'attivita', 'difficolta', 'area', 'provincia', 'comune' ] as $old ) {
	$check( ! isset( $facets[ $old ] ), "nessun filtro {$old}" );
}
$check( 'validation-status-unverified' === ( $facets['validation']['missing'] ?? null ) && 5 === count( $facets['validation']['order'] ?? [] ), 'Validazione catastale, 5 stati, senza validazione vale Non verificato' );
$check( 'layers' === ( $facets['type']['field'] ?? '' ) && [ '11', '10', '8' ] === ( $facets['type']['order'] ?? null ), 'tipo di percorso dai layer di MAP.filters' );
$check( in_array( 'trail', (array) ( $facets['activity']['include'] ?? [] ), true ), 'attività: nessuna esclusione per nome (la fonte vince)' );
$check( isset( $facets['hiking-difficulty'], $facets['mtb-difficulty'] ) && ! isset( $facets['inclusivity-difficulty'] ), 'una faccetta per scala di difficoltà, senza l\'accessibilità' );
$check( 'hiking-difficulty-T' === ( $facets['hiking-difficulty']['order'][0] ?? '' ), 'ordine di ripiego della scala escursionistica' );
$check( 'layer' === ( $facets['area-3']['param'] ?? '' ) && [ '3' ] === ( $facets['area-3']['ids'] ?? null ), 'aree catastali nel parametro layer' );
$check( [ 'q' => 'q', 'tab' => 'tab', 'page' => 'pg', 'view' => 'view', 'map' => 'map' ] === wmwp_search_params(), 'parametri del plugin in inglese, senza traduzione' );
$source = (string) file_get_contents( get_stylesheet_directory() . '/inc/search.php' );
$check( false === strpos( $source, 'validation-status-walkable' ) && false === strpos( $source, 'validation-status-not-walkable' ), 'nessun valore tolto da oc:8989' );

// 2b. Riga dei percorsi: la percorribilità con il semaforo, senza etichetta per «Sconosciuta» (Rubens, 18/11); all'inizio
// l'ultimo luogo, il comune (oc:9018)
$check( 'terms.walkability-status' === ( $tracks['badge_field'] ?? '' ), 'etichetta della riga dalla percorribilità' );
$check( [ 'walkability-status-unknown' ] === ( $tracks['badge_exclude'] ?? null ), 'nessuna etichetta per Sconosciuta' );
$check( 'taxonomyWheres' === ( $tracks['meta_last_field'] ?? '' ) && empty( $tracks['meta_facet'] ) && ! isset( $tracks['term_labels'] ), 'riga: ultimo luogo, nessun nome scritto nel child' );
$css = (string) file_get_contents( get_stylesheet_directory() . '/style.css' );
$check( false !== strpos( $css, '.wmwp-badge--walkability-status-partially-walkable' ) && false === strpos( $css, 'wmwp-badge--validation-status' ), 'colori dell\'etichetta per la percorribilità, non più per la validazione' );

// 3. Tab «Punti di interesse»: tipi dal config.json, poi le aree
$sources = wmwp_search_sources();
// oc:9018: valori della query string in inglese, uguali in tutte le lingue
$check( isset( $sources['pois'] ) && 'points-of-interest' === ( $sources['pois']['slug'] ?? '' ), 'tab dei POI: points-of-interest' );
$check( [ 'trails', 'points-of-interest', 'content', 'organisations' ] === [ $sources['tracks']['slug'] ?? '', $sources['pois']['slug'] ?? '', $sources['content']['slug'] ?? '', $sources['entes']['slug'] ?? '' ], 'valori di tab in inglese' );
$check( [ 'news', 'event', 'good-to-know', 'page' ] === array_keys( FORESTAS_SEARCH_TYPES ) && array_keys( FORESTAS_SEARCH_TYPES ) === array_keys( forestas_search_type_labels() ), 'tipi di contenuto in inglese' );
$check( 'poi-type' === ( $sources['pois']['facets'][0]['key'] ?? '' ) && 28 === count( $sources['pois']['facets'][0]['include'] ?? [] ), 'faccetta dei tipi del POI dal config.json' );
$check( 'area-3' === ( $sources['pois']['facets'][1]['key'] ?? '' ) && 'layer' === ( $sources['pois']['facets'][1]['param'] ?? '' ), 'POI: le aree come per i percorsi' );
$check( empty( array_filter( (array) $sources['pois']['facets'], fn( $f ) => isset( $f['include_pattern'] ) || in_array( $f['key'] ?? '', [ 'area', 'provincia', 'comune' ], true ) ) ), 'POI: niente area, provincia e comune per nome' );
$order = array_keys( $sources );
$check( array_search( 'pois', $order, true ) === array_search( 'tracks', $order, true ) + 1, 'POI subito dopo i percorsi' );
// 4. Vecchi link della ricerca di Drupal ai parametri nuovi (oc:9018). Il tipo di percorso diventa il layer di
// MAP.filters.layers che ha più percorsi di quel route-type nei risultati (Elastic finto)
$es = function ( $pre, $args, $url ) {
	if ( false === strpos( $url, 'elasticsearch' ) ) {
		return $pre;
	}
	$hit = fn( int $id, string $type, array $layers ) => [ 'id' => $id, 'layers' => $layers, 'terms' => [ 'route-type' => [ [ 'identifier' => $type ] ] ] ];
	return [
		'headers'  => [],
		'body'     => wp_json_encode( [ 'hits' => [ $hit( 1, 'route-type-trail', [ 3, 11 ] ), $hit( 2, 'route-type-trail', [ 11 ] ), $hit( 3, 'route-type-itinerary', [ 5, 10 ] ) ] ] ),
		'response' => [ 'code' => 200, 'message' => 'OK' ],
		'cookies'  => [],
	];
};
add_filter( 'pre_http_request', $es, 10, 3 );
delete_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT );
$check( 11 === forestas_search_layer_for_route_type( 'route-type-trail' ), 'sentieri: il layer 11' );
$check( 10 === forestas_search_layer_for_route_type( 'route-type-itinerary' ), 'itinerari: il layer 10' );
$check( null === forestas_search_layer_for_route_type( 'route-type-x' ), 'tipo senza percorsi: nessun layer' );
$check( [ 'q' => 'oliena', 'tab' => 'trails', 'type' => '11' ] === forestas_search_from_drupal( [ 'f' => [ 'cerca_contenuto_nel_sito:sentiero' ], 'search_api_fulltext' => ' oliena ' ] ), 'link di Drupal dei sentieri con testo' );
$check( [ 'q' => 'oliena' ] === forestas_search_from_drupal( [ 'search_api_fulltext' => 'oliena' ] ), 'link di Drupal con il solo testo, senza f' );
$check( [ 'tab' => 'points-of-interest' ] === forestas_search_from_drupal( [ 'f' => [ 'cerca_contenuto_nel_sito:poi' ] ] ), 'vecchio link di Drupal dei POI al loro tab' );
$check( [ 'tab' => 'content', 'content' => 'news,event' ] === forestas_search_from_drupal( [ 'f' => [ 'cerca_contenuto_nel_sito:news', 'cerca_contenuto_nel_sito:evento' ] ] ), 'news ed eventi nel filtro dei contenuti' );
$check( null === forestas_search_from_drupal( [ 'tab' => 'trails' ] ), 'un indirizzo nuovo non si riscrive' );
remove_filter( 'pre_http_request', $es, 10 );
// Shard giù (review interna): il primo vecchio link aspetta la risposta, quelli dopo no, per 10 minuti
delete_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT );
$giu       = 0;
$shard_giu = function () use ( &$giu ) {
	++$giu;
	return new WP_Error( 'test', 'shard giù' );
};
add_filter( 'pre_http_request', $shard_giu, 10 );
$check( null === forestas_search_layer_for_route_type( 'route-type-trail' ) && null === forestas_search_layer_for_route_type( 'route-type-trail' ), 'shard giù: nessun layer' );
$check( 1 === $giu, "shard giù: una richiesta sola, invece {$giu}" );
// Il rinnovo del cron con lo shard giù tiene la corrispondenza buona (review interna, secondo giro): non la cancella
// prima di riscaricarla
set_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT, [ 'route-type-trail' => 11 ], HOUR_IN_SECONDS );
forestas_search_layers_refresh();
$check( [ 'route-type-trail' => 11 ] === get_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT ), 'rinnovo con lo shard giù: resta la corrispondenza di prima' );
remove_filter( 'pre_http_request', $shard_giu, 10 );
// Con lo shard su, il rinnovo la riscrive anche se c'è già
add_filter( 'pre_http_request', $es, 10, 3 );
set_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT, [ 'route-type-trail' => 99 ], HOUR_IN_SECONDS );
forestas_search_layers_refresh();
$check( 11 === ( get_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT )['route-type-trail'] ?? null ) && false === get_transient( FORESTAS_SEARCH_LAYERS_FAILED ), 'rinnovo con lo shard su: corrispondenza riscritta, fallimento tolto' );
remove_filter( 'pre_http_request', $es, 10 );
delete_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT );
delete_transient( FORESTAS_SEARCH_LAYERS_FAILED );
$canonical = forestas_search_canonical_args( [ 'q' => 'oliena', 'tab' => 'points-of-interest', 'poi-type' => 'rifugio', 'layer' => '50,3', 'pg' => '2', 'utm' => 'x' ] );
$check( [ 'q', 'tab', 'layer', 'poi-type', 'pg' ] === array_keys( $canonical ), 'canonical: parametri nuovi in ordine fisso, niente estranei (' . implode( ',', array_keys( $canonical ) ) . ')' );
$check( rawurlencode( '3,50' ) === ( $canonical['layer'] ?? '' ), 'canonical: aree in ordine' );

// 5. Slug inglese della ricerca tradotto (oc:9018): /en/search-in-our-website/; il vecchio /en/cerca-nel-sito (Drupal e sito
// di prima) porta lì con un 301, con i parametri, così il redirect dei vecchi link della ricerca li traduce dopo
// config/ è montata nel container in /opt/wp-forestas/config (compose)
$pagine_json = json_decode( (string) @file_get_contents( '/opt/wp-forestas/config/pagine.json' ), true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
$voce_ricerca = array_values( array_filter( (array) ( $pagine_json['pagine'] ?? $pagine_json ), fn( $v ) => FORESTAS_SEARCH_PAGE_KEY === ( $v['chiave'] ?? '' ) ) )[0] ?? [];
$check( 'search-in-our-website' === ( $voce_ricerca['slug']['en'] ?? '' ) && 'cerca-nel-sito' === ( $voce_ricerca['slug']['it'] ?? '' ), 'slug della ricerca: italiano cerca-nel-sito, inglese search-in-our-website' );
$check( 'https://sito.example/en/search-in-our-website/?f%5B0%5D=cerca_contenuto_nel_sito%3Asentiero' === forestas_search_old_slug_target( 'cerca-nel-sito', 'f%5B0%5D=cerca_contenuto_nel_sito%3Asentiero', 'https://sito.example/en/search-in-our-website/' ), 'vecchio /en/cerca-nel-sito: al nuovo indirizzo con i parametri' );
$check( 'https://sito.example/en/search-in-our-website/' === forestas_search_old_slug_target( 'cerca-nel-sito/', '', 'https://sito.example/en/search-in-our-website/' ), 'vecchio /en/cerca-nel-sito senza parametri' );
$check( null === forestas_search_old_slug_target( 'cerca-nel-sito', '', 'https://sito.example/cerca-nel-sito/' ), 'nessun redirect verso lo stesso indirizzo (pagina italiana)' );
$check( null === forestas_search_old_slug_target( 'altra-pagina', '', 'https://sito.example/en/search-in-our-website/' ), 'nessun redirect per un altro indirizzo' );

// Intestazione della pagina come le altre pagine del sito (19/11): titolo centrato con il filo sopra
// (.forestas-intestazione, colore della sezione) e breadcrumb «Home › Cerca nel sito», senza «Condividi»
// La pagina italiana: con WPML anche la traduzione ha `_wpf_pagina`, e senza la lingua get_posts() ne prende una a caso
$pagina_ricerca = get_posts( [ 'post_type' => 'page', 'meta_key' => '_wpf_pagina', 'meta_value' => FORESTAS_SEARCH_PAGE_KEY, 'fields' => 'ids', 'numberposts' => 1 ] )[0] ?? 0;
$pagina_ricerca = $pagina_ricerca ? (int) apply_filters( 'wpml_object_id', $pagina_ricerca, 'page', true, 'it' ) : 0;
if ( $pagina_ricerca ) {
	$GLOBALS['post'] = get_post( $pagina_ricerca );
	setup_postdata( $GLOBALS['post'] );
	$ricerca = do_shortcode( '[forestas_search]' );
	wp_reset_postdata();
	$titolo  = esc_html( get_the_title( $pagina_ricerca ) );
	$check( (bool) preg_match( '#<div class="forestas-intestazione forestas-sezione-[a-z-]+"><h1 class="forestas-intestazione-titolo">' . preg_quote( $titolo, '#' ) . '</h1></div>#', $ricerca ), 'ricerca: titolo nell\'intestazione delle pagine' );
	$check( (bool) preg_match( '#<nav class="forestas-percorso" aria-label="Briciole di pane"><a href="[^"]+">Home</a><span aria-hidden="true">›</span><span aria-current="page">' . preg_quote( $titolo, '#' ) . '</span></nav>#', $ricerca ), 'ricerca: breadcrumb Home › titolo' );
	$check( false === strpos( $ricerca, 'forestas-search__title' ) && false === strpos( $ricerca, 'forestas-azioni' ), 'ricerca: niente titolo di prima né «Condividi»' );
} else {
	$check( false, 'pagina della ricerca non trovata' );
}

$ripristina_config();

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
