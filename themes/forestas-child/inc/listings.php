<?php
/**
 * Pagine d'elenco Sentieri, Itinerari, Cammini e Punti di interesse (oc:9003): sotto la testata la griglia del plugin
 * wp-package (shortcode [wmwp_tracks_grid] e [wmwp_pois_grid]), configurata qui con il filter `wmwp_grid_config`.
 *
 * Il layer di una pagina non è scritto da nessuna parte: viene dal route-type del tipo (FORESTAS_ROUTE_TYPE_KINDS) con
 * forestas_search_layer_for_route_type(), che sulla copia delle tracce del plugin cerca, fra le opzioni di «Tipo
 * percorso» del config.json (`MAP.filters.layers`), il layer con più percorsi di quel route-type. Le faccette sono quelle della ricerca
 * unica tranne «Tipo di percorso» (fisso), più gli slider di distanza e tempo; itinerari e cammini hanno anche la
 * «Tipologia itinerario». Le categorie del sito Drupal diventano valori di una faccetta nell'indirizzo
 * (`/sentieri/?activity=mtb`). Il child usa il plugin solo se ha le funzioni nuove (0.7.0): con il plugin vecchio la
 * pagina resta con la sola testata.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Chiave della pagina (`_wpf_pagina`, config/pagine.json) → tipo delle pagine di dettaglio. */
const FORESTAS_LISTING_PAGES = [
	'sentieri'  => 'sentiero',
	'itinerari' => 'itinerario',
	'cammini'   => 'cammino',
	'da-vedere' => 'poi',
];

/** Famiglia dei termini della tipologia degli itinerari (LABELS.taxonomies dello shard). */
const FORESTAS_LISTING_ITINERARY_FAMILY = 'itinerary-type';

/** Route-type di un tipo (`sentiero` → `route-type-trail`), null per i POI. */
function forestas_listing_route_type( string $kind ): ?string {
	$route_type = array_search( $kind, FORESTAS_ROUTE_TYPE_KINDS, true );
	return false === $route_type ? null : (string) $route_type;
}

/**
 * Layer della griglia di un tipo: la stessa corrispondenza route-type → layer della ricerca
 * (forestas_search_layer_for_route_type(), dalla copia delle tracce del plugin), senza scaricare nulla nella pagina.
 * Null per i POI o se non si trova: senza copia la griglia mostra comunque un messaggio.
 */
function forestas_listing_layer( string $kind ): ?int {
	$route_type = forestas_listing_route_type( $kind );
	return null === $route_type ? null : forestas_search_layer_for_route_type( $route_type, false );
}

/**
 * Faccette della griglia di un tipo di percorso: quelle della ricerca (slider compresi) senza «Tipo di percorso», con la
 * tipologia degli itinerari dopo le attività (itinerari e cammini).
 *
 * @return list<array<string, mixed>>
 */
function forestas_listing_track_facets( string $kind ): array {
	$facets = [];
	foreach ( forestas_search_track_facets() as $facet ) {
		if ( 'type' === ( $facet['key'] ?? '' ) ) {
			continue;
		}
		$facets[] = $facet;
		if ( 'activity' === ( $facet['key'] ?? '' ) && in_array( $kind, [ 'itinerario', 'cammino' ], true ) ) {
			$facets[] = [ 'from_families' => FORESTAS_LISTING_ITINERARY_FAMILY ];
		}
	}
	return $facets;
}

/** Nomi del conteggio di una griglia («27 sentieri»). */
function forestas_listing_nouns( string $kind ): array {
	$nouns = [
		'sentiero'   => [ forestas_testo( '%s sentiero', '%s trail' ), forestas_testo( '%s sentieri', '%s trails' ) ],
		'itinerario' => [ forestas_testo( '%s itinerario', '%s itinerary' ), forestas_testo( '%s itinerari', '%s itineraries' ) ],
		'cammino'    => [ forestas_testo( '%s cammino', '%s walk' ), forestas_testo( '%s cammini', '%s walks' ) ],
		'poi'        => [ forestas_testo( '%s punto di interesse', '%s point of interest' ), forestas_testo( '%s punti di interesse', '%s points of interest' ) ],
	];
	return [
		'one'   => $nouns[ $kind ][0],
		'other' => $nouns[ $kind ][1],
	];
}

/**
 * Campo «cerca» della griglia di un tipo (oc:9003): nome e suggerimento, come quelli della ricerca unica.
 *
 * @return array{search_label: string, placeholder: string}
 */
function forestas_listing_search_texts( string $kind ): array {
	$labels = [
		'sentiero'   => forestas_testo( 'Cerca un sentiero', 'Search for a trail' ),
		'itinerario' => forestas_testo( 'Cerca un itinerario', 'Search for an itinerary' ),
		'cammino'    => forestas_testo( 'Cerca un cammino', 'Search for a walk' ),
		'poi'        => forestas_testo( 'Cerca un punto di interesse', 'Search for a point of interest' ),
	];
	$placeholder = 'poi' === $kind
		// Parole comuni, non un nome proprio: un nome scritto qui è un dato dello shard che può sparire (oc:9003, review)
		? forestas_testo( 'Nome o luogo — per esempio «nuraghe» o «sorgente»', 'Name or place — for example “nuraghe” or “spring”' )
		: forestas_testo( 'Nome, codice o luogo — per esempio «721» o «Punta La Marmora»', 'Name, code or place — for example “721” or “Punta La Marmora”' );
	return [
		'search_label' => $labels[ $kind ],
		'placeholder'  => $placeholder,
	];
}

/**
 * Configurazione delle griglie delle quattro pagine per il plugin (filter `wmwp_grid_config`); le altre restano del tema.
 *
 * @param array<string, mixed>|null $config Configurazione di altri.
 * @return array<string, mixed>|null
 */
function forestas_listing_config( $config, string $id ) {
	$kind = FORESTAS_LISTING_PAGES[ $id ] ?? null;
	if ( null === $kind ) {
		return $config;
	}
	if ( 'poi' === $kind ) {
		return [
			'type'     => 'pois',
			'per_page' => 12,
			'nouns'    => forestas_listing_nouns( $kind ),
		] + forestas_listing_search_texts( $kind );
	}
	return [
		'type'     => 'tracks',
		'layer'    => forestas_listing_layer( $kind ),
		'facets'   => forestas_listing_track_facets( $kind ),
		'per_page' => 9,
		'nouns'    => forestas_listing_nouns( $kind ),
	] + forestas_listing_search_texts( $kind );
}
add_filter( 'wmwp_grid_config', 'forestas_listing_config', 10, 2 );

/**
 * [forestas_listing]: la griglia della pagina corrente (template «griglia» di config/pagine.json). Vuoto senza le
 * funzioni del plugin 0.7.0 o su una pagina che non è d'elenco.
 */
function forestas_listing_shortcode(): string {
	$id = (string) get_post_meta( (int) apply_filters( 'wpml_object_id', get_queried_object_id(), 'page', true, apply_filters( 'wpml_default_language', null ) ), FORESTAS_META_PAGINA, true );
	if ( ! isset( FORESTAS_LISTING_PAGES[ $id ] ) ) {
		return '';
	}
	$shortcode = 'poi' === FORESTAS_LISTING_PAGES[ $id ] ? 'wmwp_pois_grid' : 'wmwp_tracks_grid';
	return shortcode_exists( $shortcode ) ? do_shortcode( '[' . $shortcode . ' id="' . esc_attr( $id ) . '"]' ) : '';
}
add_shortcode( 'forestas_listing', 'forestas_listing_shortcode' );
