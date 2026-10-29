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

/**
 * Corpo della pagina di una news, un evento o un «da sapere», sotto la testata: $prima (tag delle news, Dove
 * e Quando degli eventi), testo, allegati, collegamenti, galleria. Un «da sapere» non ha collegamenti.
 */
function forestas_corpo_contenuto( int $id, string $prima = '', string $classe = '' ): string {
	return '<div class="forestas-pagina-testo forestas-corpo-contenuto' . ( $classe !== '' ? ' ' . esc_attr( $classe ) : '' ) . '"' . forestas_attributo_lingua( $id ) . '>'
		. $prima . forestas_testo_importato( $id ) . forestas_allegati( $id ) . forestas_collegamenti( $id ) . forestas_galleria( $id ) . '</div>';
}

/*
 * «Collegamenti» come nel tema Drupal (field--field-collegamenti): titolo sase-h5, «Links» tradotto da
 * Drupal in «Collegamenti» (locales_target), poi i link con la freccia davanti nel colore della sezione
 * (sase-link sase-link--icon-right: la classe dice «right», ma l'icona viene prima del testo). Il testo
 * oltre gli 80 caratteri si taglia con «…», come il formatter «link» di Drupal (trim_length: 80).
 */
function forestas_collegamenti( int $id ): string {
	$voci = '';
	foreach ( (array) get_post_meta( $id, FORESTAS_META_COLLEGAMENTI, true ) as $c ) {
		if ( empty( $c['url'] ) ) {
			continue;
		}
		// Senza testo si mostra l'indirizzo, come il formatter «link» di Drupal (e come dice il riquadro)
		$testo = trim( (string) ( $c['titolo'] ?? '' ) ) !== '' ? (string) $c['titolo'] : (string) $c['url'];
		$testo = mb_strlen( $testo ) > FORESTAS_COLLEGAMENTO_MAX ? mb_substr( $testo, 0, FORESTAS_COLLEGAMENTO_MAX - 1 ) . '…' : $testo;
		$voci .= sprintf( '<li><a class="forestas-link-freccia" href="%s"><i class="fas fa-arrow-right" aria-hidden="true"></i>%s</a></li>', esc_url( $c['url'] ), esc_html( $testo ) );
	}
	return $voci === '' ? '' : '<section class="forestas-collegamenti"><h2 class="forestas-collegamenti-titolo">' . esc_html( forestas_testo( 'Collegamenti', 'Links' ) ) . '</h2><ul>' . $voci . '</ul></section>';
}

/*
 * Card unica del Canvas (tavola 9, «Una card sola, con varianti»), con i dati delle anteprime di Drupal:
 * - «evidenza» (node--news--teaser-in-evidenza): foto a tutta card con la sfumatura, etichetta «In evidenza»;
 * - «compatta» (le altre accanto a quella in evidenza): foto a lato;
 * - «testo» (node--news--teaser-senza-immagine): filetto in alto;
 * - «immagine» (le pagine d'elenco): foto in alto.
 * - «evento» (oc:8760): foto in alto con la data del Canvas sopra (.uc-date, «30 GIU»), etichetta «Evento»,
 *   sotto il titolo la data per intero o l'intervallo.
 * L'etichetta «In evidenza» sulla foto è solo delle news: nel Canvas la prima card di Da sapere non ce l'ha.
 * L'etichetta è la tipologia nelle card in evidenza (Canvas) e i tag nelle altre, come le anteprime di
 * Drupal nelle schede e nelle pagine d'elenco; per un «da sapere» è sempre la sua tipologia
 * (node--da-sapere--teaser: field_tipo_da_sapere), e la card non ha data.
 * Tutta la card è cliccabile, con il link sul titolo.
 */
function forestas_card( int $id, string $variante, int $livello = 3 ): string {
	// «lista» (node--da-sapere--anteprima-lista, la scheda FAQ): solo il titolo con «›», come nel wireframe
	if ( $variante === 'lista' ) {
		return sprintf(
			'<article class="forestas-card forestas-card--lista"><h%3$d class="forestas-card__titolo"%4$s><a href="%1$s">%2$s<span aria-hidden="true">›</span></a></h%3$d></article>',
			esc_url( get_permalink( $id ) ),
			esc_html( get_the_title( $id ) ),
			$livello,
			forestas_attributo_lingua( $id )
		);
	}
	$tipo     = get_post_type( $id );
	$immagine = $variante === 'testo' ? '' : get_the_post_thumbnail( $id, $variante === 'evidenza' ? 'large' : 'medium_large', [ 'loading' => 'lazy', 'alt' => '' ] );
	// L'etichetta è un testo dell'interfaccia («Evento») o i nomi di termini, già escapati: questi, in inglese,
	// possono essere italiani (ripiego di WPML) e hanno allora lang="it" (forestas_nomi_termini)
	if ( $tipo === 'evento' ) {
		$etichetta = esc_html( forestas_testo( 'Evento', 'Event' ) ); // come nel Canvas (uc--evento): il tipo, non i tag
	} elseif ( $tipo === 'da_sapere' ) {
		// Tutte le tipologie, separate da uno spazio come in field--node--field-tipo-da-sapere--teaser
		$etichetta = forestas_nomi_termini( forestas_da_sapere_tipologie( $id ), ' ' );
	} elseif ( ! in_array( $variante, [ 'evidenza', 'compatta' ], true ) ) {
		$etichetta = forestas_nomi_termini( get_the_tags( $id ) ?: [], ', ' );
	} else {
		$etichetta = forestas_nomi_termini( array_filter( [ forestas_news_tipologia( $id ) ] ), '' );
	}
	$data   = match ( $tipo ) {
		'post'   => forestas_news_data( $id ),
		'evento' => forestas_evento_quando( $id ),
		default  => '',
	};
	$classi = 'forestas-card forestas-card--' . ( [ 'evento' => 'evento', 'da_sapere' => 'da-sapere' ][ $tipo ] ?? 'news' ) . ' forestas-card--' . $variante . ( $immagine === '' && $tipo !== 'evento' ? ' forestas-card--senza-immagine' : '' );
	// Un evento senza immagine ha il riquadro vuoto con la data, come il thumb-placeholder di node--evento--teaser
	$media  = $immagine !== '' || $tipo === 'evento'
		? '<div class="forestas-card__media' . ( $immagine === '' ? ' forestas-card__media--segnaposto' : '' ) . '">' . $immagine . ( $variante === 'evidenza' && $tipo === 'post' ? '<span class="forestas-card__flag">' . esc_html( forestas_testo( 'In evidenza', 'Highlighted' ) ) . '</span>' : '' ) . ( $tipo === 'evento' ? forestas_evento_data( $id ) : '' ) . '</div>'
		: '';
	return sprintf(
		'<article class="%1$s">%2$s<div class="forestas-card__testo">%3$s<h%7$d class="forestas-card__titolo"%8$s><a href="%4$s">%5$s</a></h%7$d>%6$s</div></article>',
		esc_attr( $classi ),
		$media,
		$etichetta !== '' ? '<span class="forestas-card__tag">' . $etichetta . '</span>' : '',
		esc_url( get_permalink( $id ) ),
		esc_html( get_the_title( $id ) ),
		$data !== '' ? '<p class="forestas-card__data">' . $data . '</p>' : '',
		$livello,
		forestas_attributo_lingua( $id )
	);
}
