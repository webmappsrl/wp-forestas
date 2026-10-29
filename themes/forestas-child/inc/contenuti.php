<?php
/**
 * Eventi e «da sapere» (oc:8760): i due tipi di contenuto, come su Drupal i tipi «Evento» e «Da Sapere».
 * Li scrive bin/wordpress-config.sh pagine (docker/scripts/config/import-lib.php) e la redazione li crea
 * dal pannello; qui ci sono la registrazione e gli indirizzi, che sono quelli di Drupal: /evento/<slug>/ e
 * /da-sapere/<slug>/ (pathauto: «[node:content-type]/[node:title]» e «da-sapere/[node:title]»).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Tipi di contenuto con un nodo Drupal d'origine: news (post), eventi, «da sapere». Li usano il redirect dei
// vecchi indirizzi, il riquadro dell'editor e le card
const FORESTAS_TIPI_IMPORTATI = [ 'post', 'evento', 'da_sapere' ];

add_action(
	'init',
	function () {
		$comuni = [
			'public'       => true,
			'has_archive'  => false, // gli elenchi sono pagine di config/pagine.json (/eventi, /da-sapere)
			'show_in_rest' => true,
			'supports'     => [ 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ],
		];
		register_post_type(
			'evento',
			$comuni + [
				'labels'    => [
					'name'          => forestas_testo( 'Eventi', 'Events' ),
					'singular_name' => forestas_testo( 'Evento', 'Event' ),
					'add_new_item'  => forestas_testo( 'Aggiungi evento', 'Add event' ),
					'edit_item'     => forestas_testo( 'Modifica evento', 'Edit event' ),
				],
				'menu_icon' => 'dashicons-calendar-alt',
				'rewrite'   => [ 'slug' => 'evento' ],
				'taxonomies' => [ 'post_tag' ],
			]
		);
		register_post_type(
			'da_sapere',
			$comuni + [
				'labels'    => [
					'name'          => forestas_testo( 'Da sapere', 'Good to know' ),
					'singular_name' => forestas_testo( 'Da sapere', 'Good to know' ),
					'add_new_item'  => forestas_testo( 'Aggiungi «da sapere»', 'Add "good to know"' ),
					'edit_item'     => forestas_testo( 'Modifica «da sapere»', 'Edit "good to know"' ),
				],
				'menu_icon' => 'dashicons-info-outline',
				'rewrite'   => [ 'slug' => 'da-sapere' ],
				'taxonomies' => [ 'post_tag', 'tipologia_da_sapere' ],
			]
		);
		// La tassonomia è registrata in inc/news.php, prima che il tipo esistesse
		register_taxonomy_for_object_type( 'tipologia_da_sapere', 'da_sapere' );
	},
	11
);
