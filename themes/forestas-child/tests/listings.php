<?php
/**
 * Test (oc:9003): griglie delle pagine d'elenco. Layer dal route-type sulla copia delle tracce del plugin (nessun id
 * scritto), faccette della ricerca senza «Tipo di percorso» più gli slider (e «Tipologia itinerario» per itinerari e
 * cammini), card per pagina e nomi del conteggio, shortcode che non scrive nulla senza il plugin nuovo.
 * Fixture: config.json di UAT del plugin (tests/fixtures/config-uat.json) e una copia finta delle tracce.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/listings.php
 * Stampa «PASS» o «FAIL: …» ed esce con 0 o 1. Rimette option e transient com'erano, anche su errore.
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

$names = [ WMWP_APP_CONFIG_OPTION, WMWP_TRACKS_COPY_OPTION ];
$saved = [];
foreach ( $names as $name ) {
	$saved[ $name ] = get_option( $name, null );
}
$saved_map = get_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT );
$restore   = function () use ( $saved, $saved_map ) {
	foreach ( $saved as $name => $value ) {
		null === $value ? delete_option( $name ) : update_option( $name, $value, false );
	}
	false === $saved_map ? delete_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT ) : set_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT, $saved_map, 2 * HOUR_IN_SECONDS );
};
set_exception_handler(
	function ( Throwable $e ) use ( $restore ) {
		$restore();
		echo 'FAIL: errore nel test: ' . $e->getMessage() . ' (' . basename( $e->getFile() ) . ':' . $e->getLine() . ")\n";
		exit( 1 );
	}
);

update_option(
	WMWP_APP_CONFIG_OPTION,
	[
		'url'    => wmwp_app_config_url(),
		'config' => json_decode( (string) file_get_contents( WMWP_PATH . 'tests/fixtures/config-uat.json' ), true ),
	],
	false
);
$track = fn( int $id, string $type, array $layers ) => [
	'id'     => $id,
	'name'   => [ 'it' => 'Percorso ' . $id ],
	'layers' => $layers,
	'terms'  => [ 'route-type' => [ [ 'identifier' => $type ] ] ],
];
// «Sentieri» è il layer 11 del config.json di UAT; il 9 (Sentieri Accessibili) non è fra le opzioni di «Tipo percorso»
update_option(
	WMWP_TRACKS_COPY_OPTION,
	[ $track( 1, 'route-type-trail', [ 11, 9 ] ), $track( 2, 'route-type-trail', [ 11, 9 ] ), $track( 3, 'route-type-trail', [ 9 ] ), $track( 4, 'route-type-walk', [ 8 ] ), $track( 5, 'route-type-itinerary', [ 10 ] ) ],
	false
);
delete_transient( FORESTAS_SEARCH_LAYERS_TRANSIENT );

// 1. Route-type e layer, senza id scritti
$check( 'route-type-walk' === forestas_listing_route_type( 'cammino' ), 'route-type del cammino' );
$check( null === forestas_listing_route_type( 'poi' ), 'i POI non hanno route-type' );
$check( 11 === forestas_listing_layer( 'sentiero' ), 'layer dei sentieri: 11, non il 9 che non è fra i tipi' );
$check( 8 === forestas_listing_layer( 'cammino' ) && 10 === forestas_listing_layer( 'itinerario' ), 'layer di cammini e itinerari' );
$check( 11 === forestas_search_layer_for_route_type( 'route-type-trail', false ), 'la ricerca usa la stessa corrispondenza, dalla copia' );

// 2. Faccette
$keys = fn( array $facets ) => array_map( fn( $f ) => $f['key'] ?? ( $f['from_config'] ?? ( $f['from_families'] ?? ( empty( $f['from_overlays'] ) ? '?' : 'overlays' ) ) ), $facets );
$trail = $keys( forestas_listing_track_facets( 'sentiero' ) );
$check( ! in_array( 'type', $trail, true ), 'sentieri: niente «Tipo di percorso»' );
$check( in_array( 'track_distance', $trail, true ) && in_array( 'track_duration', $trail, true ), 'sentieri: slider di distanza e tempo' );
$check( ! in_array( 'itinerary-type', $trail, true ), 'sentieri: niente tipologia itinerario' );
$itinerary = $keys( forestas_listing_track_facets( 'itinerario' ) );
$check( array_search( 'itinerary-type', $itinerary, true ) === array_search( 'activity', $itinerary, true ) + 1, 'itinerari: tipologia subito dopo le attività' );
$check( in_array( 'itinerary-type', $keys( forestas_listing_track_facets( 'cammino' ) ), true ), 'cammini: tipologia itinerario' );

// 3. Configurazione delle griglie (filter wmwp_grid_config)
$sentieri = apply_filters( 'wmwp_grid_config', null, 'sentieri' );
$check( 'tracks' === ( $sentieri['type'] ?? '' ) && 11 === ( $sentieri['layer'] ?? null ) && 9 === ( $sentieri['per_page'] ?? null ), 'sentieri: percorsi del layer 11, 9 per pagina' );
$check( false !== strpos( (string) ( $sentieri['nouns']['other'] ?? '' ), '%s' ), 'sentieri: nomi del conteggio' );
$check( '' !== (string) ( $sentieri['search_label'] ?? '' ) && '' !== (string) ( $sentieri['placeholder'] ?? '' ), 'sentieri: nome e suggerimento del campo «cerca»' );
$poi = apply_filters( 'wmwp_grid_config', null, 'da-vedere' );
$check( 'pois' === ( $poi['type'] ?? '' ) && 12 === ( $poi['per_page'] ?? null ) && ! isset( $poi['layer'] ), 'punti di interesse: POI, 12 per pagina, senza layer' );
$check( '' !== (string) ( $poi['search_label'] ?? '' ), 'punti di interesse: nome del campo «cerca»' );
$check( null === apply_filters( 'wmwp_grid_config', null, 'altro' ), 'una griglia che il child non conosce resta del tema' );
update_option( WMWP_TRACKS_COPY_OPTION, [], false );
$vuota = apply_filters( 'wmwp_grid_config', null, 'sentieri' );
$check( array_key_exists( 'layer', $vuota ) && null === $vuota['layer'], 'copia vuota: layer null' );

// 4. [forestas_listing] su una pagina d'elenco: senza lo shortcode del plugin 0.7.0 (plugin vecchio) non scrive nulla;
// con il plugin, la griglia o il suo messaggio. Shortcode e query sono di questo processo: non serve ripristinarli su disco
global $shortcode_tags, $wp_query;
$page        = forestas_pagina_per_chiave( 'sentieri' );
$saved_tag   = $shortcode_tags['wmwp_tracks_grid'] ?? null;
$saved_query = [ $wp_query->queried_object, $wp_query->queried_object_id ];
$wp_query->queried_object    = get_post( $page );
$wp_query->queried_object_id = $page;
remove_shortcode( 'wmwp_tracks_grid' );
$check( $page > 0 && '' === forestas_listing_shortcode(), 'shortcode senza il plugin nuovo: niente' );
if ( $saved_tag ) {
	add_shortcode( 'wmwp_tracks_grid', $saved_tag );
	wmwp_grid_rendered( false );
	$check( '' !== forestas_listing_shortcode(), 'shortcode con il plugin nuovo: la griglia o il suo messaggio' );
	wmwp_grid_rendered( false );
}
[ $wp_query->queried_object, $wp_query->queried_object_id ] = $saved_query;

$restore();

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
