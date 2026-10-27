<?php
/**
 * News sincronizzate da Drupal (oc:8759): post con la categoria «news», scritti da
 * bin/wordpress-config.sh pagine (docker/scripts/config/news-lib.php). Qui c'è come si mostrano e come
 * si raggiungono: tassonomie, indirizzi, schede della pagina News, collegamenti.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Meta scritti dalla sincronizzazione delle news (costanti WPF_META_* di news-lib.php, che il tema non
 * carica). Rinominandone uno negli script va rinominato anche qui.
 */
const FORESTAS_META_IN_EVIDENZA  = 'wpf_in_evidenza';   // «1»: news in evidenza su Drupal
const FORESTAS_META_COLLEGAMENTI = 'wpf_collegamenti';  // [ [ titolo, url ] ]
const FORESTAS_META_SCHEDA       = '_wpf_scheda';       // ordine di una scheda fra le sorelle
const FORESTAS_META_PULSANTE     = '_wpf_pulsante';     // [ testo, verso: chiave della pagina d'elenco ]

/*
 * Tipologia dei contenuti «Da sapere» (vocabolario tipologia_da_sapere di Drupal): una tassonomia propria e
 * non le categorie, che sono dei post (le news). Qui c'è solo la regola: i termini e il tipo di contenuto
 * a cui si collega arrivano con oc:8760, che la aggancia con register_taxonomy_for_object_type.
 */
add_action(
	'init',
	function () {
		register_taxonomy(
			'tipologia_da_sapere',
			[],
			[
				'labels'            => [
					'name'          => forestas_testo( 'Tipologie «Da sapere»', '"Good to know" types' ),
					'singular_name' => forestas_testo( 'Tipologia «Da sapere»', '"Good to know" type' ),
				],
				'hierarchical'      => false,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [ 'slug' => 'tipologia-da-sapere' ],
			]
		);
	}
);

/*
 * Precedenza delle schede della pagina News sui post (oc:8759). Con i permalink «/news/%postname%/»
 * WordPress prova /news/<x>/ come un post prima che come una pagina figlia di News: le schede
 * (/news/news/, /news/attivita-e-comunicazioni/) darebbero 404. Ogni pagina con il meta della scheda ha
 * una regola sua, in testa: le schede si leggono dal database, quindi una scheda nuova (Eventi, oc:8760)
 * non richiede codice, ma le regole vanno rigenerate (lo fa bin/wordpress-config.sh pagine quando le
 * schede cambiano). Con WPML il prefisso /en/ è tolto prima delle regole: valgono per le due lingue.
 */
function forestas_regole_schede(): array {
	$regole = [];
	$schede = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => FORESTAS_META_SCHEDA, 'suppress_filters' => true ] );
	foreach ( array_filter( $schede, fn( $s ) => $s->post_parent ) as $scheda ) {
		$percorso = get_page_uri( $scheda );
		if ( $percorso ) {
			$regole[ '^' . preg_quote( $percorso, '#' ) . '/?$' ] = 'index.php?pagename=' . $percorso;
		}
	}
	return $regole;
}
add_filter( 'rewrite_rules_array', fn( array $regole ) => forestas_regole_schede() + $regole );

/*
 * Vecchi indirizzi di Drupal che portano a una news (301), anche con /en/ davanti:
 * - /node/<nid>: serve soprattutto alla news 1094, che su Drupal non ha alias e sta solo lì;
 * - /news/<alias con accenti>: WordPress toglie accenti e apostrofi tipografici dallo slug (3 news, per
 *   esempio «…galtellì…» diventa «…galtelli…»).
 */
add_action(
	'template_redirect',
	function () {
		global $wp;
		$richiesta = (string) $wp->request;
		if ( ! is_404() ) {
			return;
		}
		$cerca = [ 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 1 ];
		if ( preg_match( '#^node/(\d+)/?$#', $richiesta, $m ) ) {
			$cerca += [ 'meta_key' => '_drupal_nid', 'meta_value' => $m[1] ];
		} elseif ( preg_match( '#^news/([^/]+)/?$#', $richiesta, $m ) && sanitize_title( rawurldecode( $m[1] ) ) !== $m[1] ) {
			$cerca += [ 'name' => sanitize_title( rawurldecode( $m[1] ) ) ];
		} else {
			return;
		}
		$news = get_posts( $cerca );
		if ( $news ) {
			wp_safe_redirect( get_permalink( $news[0] ), 301 );
			exit;
		}
	}
);

// Griglia di una scheda o di una pagina d'elenco: [ tipologia (tid Drupal), in_evidenza, quanti, ordine
// (data), paginata, variante (testo | evidenza | immagine) ], da config/pagine.json
const FORESTAS_META_GRIGLIA = '_wpf_griglia';
// Term meta dei termini importati da Drupal: «<vocabolario>:<tid>» (WPF_TERM_META_DRUPAL di news-lib.php)
const FORESTAS_TERM_META_DRUPAL = '_drupal_chiave';

/** La pagina News nella lingua corrente (chiave «news» di config/pagine.json), o 0. */
function forestas_pagina_news(): int {
	$pagine = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => FORESTAS_META_PAGINA, 'meta_value' => 'news', 'fields' => 'ids' ] );
	return (int) ( $pagine[0] ?? 0 );
}

/** Termine importato da Drupal («tipologia_news:4734»), o null. */
function forestas_termine_drupal( string $chiave, string $tassonomia ): ?WP_Term {
	$termini = get_terms( [ 'taxonomy' => $tassonomia, 'hide_empty' => false, 'number' => 1, 'meta_key' => FORESTAS_TERM_META_DRUPAL, 'meta_value' => $chiave ] );
	return is_array( $termini ) && $termini ? $termini[0] : null;
}

/** Tipologia di una news: la sottocategoria di «news», o null. */
function forestas_news_tipologia( int $id ): ?WP_Term {
	foreach ( get_the_category( $id ) as $c ) {
		if ( $c->parent ) {
			return $c;
		}
	}
	return null;
}

/*
 * Data di una news come su Drupal (formato «j F Y», «26 maggio 2022»): la data del post, che
 * l'import prende da field_data.
 */
function forestas_news_data( int $id ): string {
	return sprintf( '<time datetime="%s">%s</time>', esc_attr( get_the_date( 'Y-m-d', $id ) ), esc_html( wp_date( 'j F Y', get_post_timestamp( $id ) ) ) );
}

/*
 * Pagina della news, con il layout di Drupal (core.entity_view_display.node.news.full.yml). Prima
 * sezione: la testata delle pagine ([forestas_testata]: immagine di sfondo, tipologia, titolo, data,
 * sottotitolo, credits). Seconda: tag, testo, allegati, collegamenti, galleria. I riferimenti a enti,
 * sentieri e itinerari non si mostrano: il dato è salvato (_wpf_riferimenti) per il plugin che li leggerà
 * dallo shard (oc:8759).
 */
add_shortcode(
	'forestas_news',
	function () {
		static $in_corso = false;
		$id = get_the_ID();
		if ( ! $id || $in_corso ) {
			return '';
		}
		$in_corso = true;
		$tag      = '';
		foreach ( get_the_tags( $id ) ?: [] as $t ) {
			$tag .= '<li>' . esc_html( $t->name ) . '</li>';
		}
		$tag      = $tag !== '' ? '<div class="forestas-news-tag"><h2 class="screen-reader-text">Tags</h2><i class="far fa-bookmark" aria-hidden="true"></i><ul role="list">' . $tag . '</ul></div>' : '';
		$testo    = (string) get_post_field( 'post_content', $id );
		$html     = md5( $testo ) === get_post_meta( $id, FORESTAS_META_TESTO_DRUPAL, true ) ? $testo : apply_filters( 'the_content', $testo );
		$in_corso = false;
		return do_shortcode( '[forestas_testata]' )
			. '<div class="forestas-pagina-testo forestas-news-corpo">' . $tag . $html . forestas_allegati( $id ) . forestas_news_collegamenti( $id ) . forestas_galleria( $id ) . '</div>';
	}
);

/*
 * «Collegamenti» come nel tema Drupal (field--field-collegamenti): titolo sase-h5, «Links» tradotto da
 * Drupal in «Collegamenti» (locales_target), poi i link con la freccia davanti nel colore della sezione
 * (sase-link sase-link--icon-right: la classe dice «right», ma l'icona viene prima del testo). Il testo
 * oltre gli 80 caratteri si taglia con «…», come il formatter «link» di Drupal (trim_length: 80).
 */
function forestas_news_collegamenti( int $id ): string {
	$voci = '';
	foreach ( (array) get_post_meta( $id, FORESTAS_META_COLLEGAMENTI, true ) as $c ) {
		if ( empty( $c['url'] ) ) {
			continue;
		}
		$testo = mb_strlen( $c['titolo'] ) > 80 ? mb_substr( $c['titolo'], 0, 79 ) . '…' : $c['titolo'];
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
 * L'etichetta è la tipologia nelle card in evidenza (Canvas) e i tag nelle altre, come le anteprime di
 * Drupal nelle schede e nelle pagine d'elenco.
 * Tutta la card è cliccabile, con il link sul titolo.
 */
function forestas_news_card( int $id, string $variante ): string {
	$immagine = in_array( $variante, [ 'testo' ], true ) ? '' : get_the_post_thumbnail( $id, $variante === 'evidenza' ? 'large' : 'medium_large', [ 'loading' => 'lazy', 'alt' => '' ] );
	if ( ! in_array( $variante, [ 'evidenza', 'compatta' ], true ) ) {
		$etichetta = implode( ', ', wp_list_pluck( get_the_tags( $id ) ?: [], 'name' ) );
	} else {
		$tipologia = forestas_news_tipologia( $id );
		$etichetta = $tipologia ? $tipologia->name : '';
	}
	$classi = 'forestas-card forestas-card--news forestas-card--' . $variante . ( $immagine === '' ? ' forestas-card--senza-immagine' : '' );
	$media  = $immagine !== ''
		? '<div class="forestas-card__media">' . $immagine . ( $variante === 'evidenza' ? '<span class="forestas-card__flag">' . esc_html( forestas_testo( 'In evidenza', 'Highlighted' ) ) . '</span>' : '' ) . '</div>'
		: '';
	return sprintf(
		'<article class="%1$s">%2$s<div class="forestas-card__testo">%3$s<h3 class="forestas-card__titolo"><a href="%4$s">%5$s</a></h3><p class="forestas-card__data">%6$s</p></div></article>',
		esc_attr( $classi ),
		$media,
		$etichetta !== '' ? '<span class="forestas-card__tag">' . esc_html( $etichetta ) . '</span>' : '',
		esc_url( get_permalink( $id ) ),
		esc_html( get_the_title( $id ) ),
		forestas_news_data( $id )
	);
}

/*
 * News di una griglia, come le View di Drupal: categoria «news», filtrate per tipologia o «in evidenza»,
 * ordinate per data (field_data), più recenti prima. Su Drupal la scheda Attività (blocco
 * attivita_e_comunicazioni, block_2) ordinava per field_date, un campo nascosto nel form che le news
 * recenti non hanno: qui per data anche lei (decisione del dev, 27/10).
 */
function forestas_news_query( array $griglia, int $pagina = 1 ): WP_Query {
	$args = [
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'category_name'  => 'news',
		'posts_per_page' => (int) ( $griglia['quanti'] ?? 9 ),
		'paged'          => max( 1, $pagina ),
		// A parità di data prima il nid più basso, come le View di Drupal (le news si creano in ordine di nid)
		'orderby'        => [ 'date' => 'DESC', 'ID' => 'ASC' ],
	];
	$tax = [];
	if ( ! empty( $griglia['tipologia'] ) ) {
		$termine = forestas_termine_drupal( 'tipologia_news:' . (int) $griglia['tipologia'], 'category' );
		// Una tipologia che non c'è (import non ancora fatto) non mostra tutte le news
		$tax[] = [ 'taxonomy' => 'category', 'field' => 'term_id', 'terms' => $termine ? [ $termine->term_id ] : [ 0 ] ];
	}
	if ( $tax ) {
		$args['tax_query'] = $tax;
	}
	$meta = [];
	if ( ! empty( $griglia['in_evidenza'] ) ) {
		$meta[] = [ 'key' => FORESTAS_META_IN_EVIDENZA, 'value' => '1' ];
	}
	if ( $meta ) {
		$args['meta_query'] = $meta;
	}
	return new WP_Query( $args );
}

/*
 * Le griglie di card di una pagina con il meta della griglia, una dopo l'altra: /attivita-e-comunicazioni
 * ha sopra le 4 in evidenza, come su Drupal. Con «paginata» anche la paginazione.
 */
function forestas_news_griglia( int $pagina_id ): string {
	$griglie = get_post_meta( $pagina_id, FORESTAS_META_GRIGLIA, true );
	if ( ! is_array( $griglie ) ) {
		return '';
	}
	return implode( '', array_map( 'forestas_news_una_griglia', array_is_list( $griglie ) ? $griglie : [ $griglie ] ) );
}

function forestas_news_una_griglia( array $griglia ): string {
	$paginata = ! empty( $griglia['paginata'] );
	$q        = forestas_news_query( $griglia, $paginata ? (int) get_query_var( 'paged' ) : 1 );
	$variante = $griglia['variante'] ?? 'immagine';
	$card     = '';
	foreach ( $q->posts as $i => $p ) {
		$card .= forestas_news_card( $p->ID, $variante === 'evidenza' ? ( $i === 0 ? 'evidenza' : 'compatta' ) : $variante );
	}
	if ( $card === '' ) {
		return '<p class="forestas-griglia-vuota">' . esc_html( forestas_testo( 'Nessun contenuto.', 'No content.' ) ) . '</p>';
	}
	$html = '<div class="forestas-griglia forestas-griglia--' . esc_attr( $variante ) . '">' . $card . '</div>';
	if ( $paginata && $q->max_num_pages > 1 ) {
		$html .= '<nav class="forestas-paginazione" aria-label="' . esc_attr( forestas_testo( 'Paginazione', 'Pagination' ) ) . '">'
			. paginate_links( [ 'total' => $q->max_num_pages, 'current' => max( 1, (int) get_query_var( 'paged' ) ), 'prev_text' => '‹', 'next_text' => '›' ] )
			. '</nav>';
	}
	return $html;
}

/*
 * Schede di una pagina a schede (Canvas, tavola 9): fila centrata di link, quella aperta in grassetto con il
 * filetto e aria-current="page"; sotto, il titolo della scheda, la sua griglia e il pulsante verso la
 * pagina d'elenco completa. Le schede sono la pagina madre, se ha il meta della scheda, e le sue figlie
 * con il meta, in ordine: /news/ è la scheda News, /news/attivita-e-comunicazioni/ la seconda
 * (decisione del dev, 27/10: nessun /news/news/). Sotto 860px la fila scorre in orizzontale,
 * posizionata sulla scheda aperta (assets/js/schede.js).
 */
add_shortcode(
	'forestas_schede',
	function () {
		$id = get_the_ID();
		if ( ! $id ) {
			return '';
		}
		$madre  = wp_get_post_parent_id( $id ) ?: $id;
		$figlie = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_parent' => $madre, 'numberposts' => -1, 'meta_key' => FORESTAS_META_SCHEDA, 'fields' => 'ids' ] );
		$schede = get_post_meta( $madre, FORESTAS_META_SCHEDA, true ) !== '' ? array_merge( [ $madre ], $figlie ) : $figlie;
		if ( ! $schede || ! in_array( $id, $schede, true ) ) {
			return '';
		}
		usort( $schede, fn( $a, $b ) => (int) get_post_meta( $a, FORESTAS_META_SCHEDA, true ) <=> (int) get_post_meta( $b, FORESTAS_META_SCHEDA, true ) );
		wp_enqueue_script( 'forestas-schede', get_stylesheet_directory_uri() . '/assets/js/schede.js', [], forestas_versione(), [ 'in_footer' => true, 'strategy' => 'defer' ] );
		$link = '';
		foreach ( $schede as $s ) {
			$link .= sprintf( '<a href="%s"%s>%s</a>', esc_url( get_permalink( $s ) ), $s === $id ? ' aria-current="page"' : '', esc_html( get_the_title( $s ) ) );
		}
		$pulsante = get_post_meta( $id, FORESTAS_META_PULSANTE, true );
		$verso    = is_array( $pulsante ) ? get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => FORESTAS_META_PAGINA, 'meta_value' => $pulsante['verso'], 'fields' => 'ids' ] ) : [];
		return sprintf(
			'<nav class="forestas-schede forestas-sezione-%1$s" aria-label="%2$s">%3$s</nav><div class="forestas-scheda"><div class="forestas-scheda-titolo"><span aria-hidden="true"></span><h2>%4$s</h2></div>%5$s%6$s</div>',
			esc_attr( forestas_sezione_pagina( $madre ) ),
			esc_attr( sprintf( forestas_testo( 'Sezioni di %s', 'Sections of %s' ), get_the_title( $madre ) ) ),
			$link,
			esc_html( get_the_title( $id ) ),
			forestas_news_griglia( $id ),
			$verso ? '<p class="forestas-scheda-pulsante"><a class="forestas-pulsante" href="' . esc_url( get_permalink( $verso[0] ) ) . '">' . esc_html( $pulsante['testo'] ) . '</a></p>' : ''
		);
	}
);

/* Pagina d'elenco completo (/tutte-le-news, /attivita-e-comunicazioni): la griglia paginata. */
add_shortcode( 'forestas_elenco', fn() => get_the_ID() ? '<div class="forestas-elenco">' . forestas_news_griglia( (int) get_the_ID() ) . '</div>' : '' );

/*
 * Sulla pagina di una news la voce del menu che porta a News è quella attiva, come sulle pagine figlie di
 * News (WordPress la segna da sé solo per le pagine, non per i post).
 */
add_filter(
	'nav_menu_css_class',
	function ( array $classi, $voce ) {
		if ( is_singular( 'post' ) && (int) $voce->object_id === forestas_pagina_news() && $voce->object === 'page' ) {
			$classi[] = 'current-menu-ancestor';
			$classi[] = 'current-page-ancestor';
		}
		return $classi;
	},
	10,
	2
);
