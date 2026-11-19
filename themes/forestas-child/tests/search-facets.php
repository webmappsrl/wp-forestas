<?php
/**
 * Test (oc:9000): filtri «Percorribilità» e «Validazione catastale» della ricerca unica generati dal registro degli
 * stati del plugin (un'opzione per stato, nell'ordine dello shard; i percorsi senza termine valgono lo stato di
 * assenza) e tab «Punti di interesse» di Forestas (slug, faccetta dei tipi, vecchi link di Drupal, canonical).
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

// 1. Una faccetta per una famiglia del registro
$facet = forestas_search_status_facet( 'walkability-status', 'percorribilita', 'Percorribilità' );
$check( 'terms' === $facet['type'] && 'terms.walkability-status' === $facet['field'], 'faccetta sui termini della famiglia' );
$check( 'walkability-status-unknown' === ( $facet['missing'] ?? null ), 'senza termine vale Sconosciuta' );
$check(
	[ 'walkability-status-unknown', 'walkability-status-not-walkable', 'walkability-status-partially-walkable', 'walkability-status-walkable' ] === array_column( $facet['groups'], 'id' ),
	'un gruppo per stato, nell\'ordine dello shard'
);
$check( [ 'walkability-status-walkable' ] === ( $facet['groups'][3]['values'] ?? null ), 'ogni gruppo ha il suo stato' );
$check( array_column( $facet['groups'], 'id' ) === $facet['order'], 'ordine dei gruppi' );
$check( 'Parzialmente percorribile' === ( $facet['groups'][2]['label'] ?? '' ), 'nome nella lingua della pagina' );
$check( null === forestas_search_status_facet( 'nessuna-famiglia', 'x', 'X' ), 'famiglia sconosciuta: nessuna faccetta' );

// 2. I filtri dei percorsi di Forestas
$facets = array_column( forestas_search_track_facets(), null, 'key' );
$check( 'terms.walkability-status' === ( $facets['percorribilita']['field'] ?? '' ) && 4 === count( $facets['percorribilita']['groups'] ?? [] ), 'Percorribilità sulla percorribilità, 4 stati' );
$check( 'terms.validation-status' === ( $facets['validazione']['field'] ?? '' ) && 5 === count( $facets['validazione']['groups'] ?? [] ), 'Validazione catastale, 5 stati' );
$check( 'validation-status-unverified' === ( $facets['validazione']['missing'] ?? null ), 'senza validazione vale Non verificato' );
$check( 'percorribilita' === ( forestas_search_track_facets()[0]['key'] ?? '' ), 'Percorribilità resta il primo filtro' );
$source = (string) file_get_contents( get_stylesheet_directory() . '/inc/search.php' );
$check( false === strpos( $source, 'validation-status-walkable' ) && false === strpos( $source, 'validation-status-not-walkable' ), 'nessun valore tolto da oc:8989' );

// 2b. Etichetta della riga dei percorsi: la percorribilità con il semaforo, senza etichetta per «Sconosciuta» (Rubens,
// 18/11: il semaforo del wireframe riassume la percorribilità)
$tracks = wmwp_search_sources()['tracks'] ?? [];
$check( 'terms.walkability-status' === ( $tracks['badge_field'] ?? '' ), 'etichetta della riga dalla percorribilità' );
$check( [ 'walkability-status-unknown' ] === ( $tracks['badge_exclude'] ?? null ), 'nessuna etichetta per Sconosciuta' );
$check( 'Parzialmente percorribile' === ( $tracks['term_labels']['walkability-status-partially-walkable'] ?? '' ), 'nome della percorribilità nella lingua della pagina' );
$css = (string) file_get_contents( get_stylesheet_directory() . '/style.css' );
$check( false !== strpos( $css, '.wmwp-badge--walkability-status-partially-walkable' ) && false === strpos( $css, 'wmwp-badge--validation-status' ), 'colori dell\'etichetta per la percorribilità, non più per la validazione' );

// 3. Tab «Punti di interesse»
$sources = wmwp_search_sources();
$check( isset( $sources['pois'] ) && 'punti-di-interesse' === ( $sources['pois']['slug'] ?? '' ), 'tab dei POI con lo slug italiano' );
$check( 'tipo-poi' === ( $sources['pois']['facets'][0]['key'] ?? '' ), 'faccetta dei tipi del POI' );
// Filtri dei luoghi dei POI (oc:9000): gli stessi dei percorsi, con le stesse chiavi e le stesse regole
$luoghi_percorsi = array_values( array_filter( forestas_search_track_facets(), fn( $f ) => in_array( $f['key'], [ 'area', 'provincia', 'comune' ], true ) ) );
$check( $luoghi_percorsi === array_slice( (array) ( $sources['pois']['facets'] ?? [] ), 1 ), 'POI: area, provincia e comune come per i percorsi' );
$order = array_keys( $sources );
$check( array_search( 'pois', $order, true ) === array_search( 'tracks', $order, true ) + 1, 'POI subito dopo i percorsi' );
$check( [ 'tipo' => 'punti-di-interesse' ] === forestas_search_from_drupal( [ 'f' => [ 'cerca_contenuto_nel_sito:poi' ] ] ), 'vecchio link di Drupal dei POI al loro tab' );
$check( isset( forestas_search_canonical_args( [ 'tipo' => 'punti-di-interesse', 'tipo-poi' => 'rifugio' ] )['tipo-poi'] ), 'il filtro dei tipi resta nel canonical' );

// Intestazione della pagina come le altre pagine del sito (19/11): titolo centrato con il filo sopra
// (.forestas-intestazione, colore della sezione) e breadcrumb «Home › Cerca nel sito», senza «Condividi»
$pagina_ricerca = get_posts( [ 'post_type' => 'page', 'meta_key' => '_wpf_pagina', 'meta_value' => FORESTAS_SEARCH_PAGE_KEY, 'fields' => 'ids', 'numberposts' => 1 ] )[0] ?? 0;
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

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
