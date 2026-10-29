<?php
/**
 * News sincronizzate da Drupal (oc:8759): post con la categoria «news», scritti da
 * bin/wordpress-config.sh pagine (docker/scripts/config/news-lib.php). Qui c'è come si mostrano e come
 * si raggiungono: tassonomie, indirizzi, schede delle pagine (News, Da sapere) e pagine d'elenco. Card,
 * corpo e collegamenti, comuni a tutti i tipi, sono in inc/contenuti.php; griglie e filtri in inc/griglie.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Meta scritti dalla sincronizzazione (costanti WPF_META_* di docker/scripts/config/: import-lib.php per
 * in evidenza e collegamenti, contenuti-lib.php per scheda, nid e termini, pagine-lib.php per pulsante,
 * etichetta e griglia; il tema non le carica). Rinominandone uno negli script va rinominato anche qui; lo
 * stesso vale per le costanti di inc/eventi.php, inc/da-sapere.php e inc/pagine-statiche.php.
 */
const FORESTAS_META_IN_EVIDENZA  = 'wpf_in_evidenza';   // «1»: news in evidenza su Drupal
const FORESTAS_META_COLLEGAMENTI = 'wpf_collegamenti';  // [ [ titolo, url ] ]
const FORESTAS_META_SCHEDA       = '_wpf_scheda';       // ordine di una scheda fra le sorelle
const FORESTAS_META_PULSANTE     = '_wpf_pulsante';     // [ testo, verso: chiave della pagina d'elenco, o «vocabolario:tid» di una tipologia ]
const FORESTAS_META_ETICHETTA    = '_wpf_etichetta';    // nome della scheda nella fila, se non è il titolo della pagina (oc:8760)
const FORESTAS_META_NID          = '_drupal_nid';       // nodo Drupal d'origine (news e pagine con un nodo)
// Griglia di una scheda o di una pagina d'elenco, da config/pagine.json (pagine-lib.php): tipo (tipo di
// contenuto, «post» se manca: le news), tipologia (tid Drupal) con vocabolario e tassonomia (per le news
// tipologia_news e category, i valori se mancano), in_evidenza, quanti (9 se manca), paginata, variante
// (testo | evidenza | immagine | evento | lista), colonne, filtri, pillole. Le più recenti prima: le news
// per data, gli eventi per data di inizio, i «da sapere» per data di creazione su Drupal (forestas_griglia_query)
const FORESTAS_META_GRIGLIA      = '_wpf_griglia';
// Term meta dei termini importati da Drupal: «<vocabolario>:<tid>» (WPF_TERM_META_DRUPAL di contenuti-lib.php)
const FORESTAS_TERM_META_DRUPAL  = '_drupal_chiave';
// Slug della categoria delle news (WPF_NEWS_CATEGORIA di news-lib.php)
const FORESTAS_NEWS_CATEGORIA    = 'news';
// Testo di un collegamento oltre il quale si taglia con «…», come il formatter «link» di Drupal (trim_length)
const FORESTAS_COLLEGAMENTO_MAX  = 80;

/*
 * Tipologia dei contenuti «Da sapere» (vocabolario tipologia_da_sapere di Drupal): una tassonomia propria e
 * non le categorie, che sono dei post (le news). Il tipo da_sapere la aggancia in inc/contenuti.php
 * (oc:8760); l'indirizzo delle sue pagine è quello di Drupal, /tipologia-da-sapere-argomenti/<termine>.
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
					'menu_name'     => forestas_testo( 'Tipologie', 'Types' ),
					'all_items'     => forestas_testo( 'Tutte le tipologie', 'All types' ),
					'edit_item'     => forestas_testo( 'Modifica tipologia', 'Edit type' ),
					'view_item'     => forestas_testo( 'Visualizza tipologia', 'View type' ),
					'update_item'   => forestas_testo( 'Aggiorna tipologia', 'Update type' ),
					'add_new_item'  => forestas_testo( 'Aggiungi tipologia', 'Add type' ),
					'new_item_name' => forestas_testo( 'Nome della nuova tipologia', 'New type name' ),
					'search_items'  => forestas_testo( 'Cerca tipologie', 'Search types' ),
					'not_found'     => forestas_testo( 'Nessuna tipologia trovata.', 'No types found.' ),
					'back_to_items' => forestas_testo( '← Torna alle tipologie', '← Back to types' ),
				],
				'hierarchical'      => false,
				// Più tipologie per contenuto, nell'ordine di Drupal: wp_set_object_terms lo scrive in term_order
				'sort'              => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => [ 'slug' => 'tipologia-da-sapere-argomenti' ],
			]
		);
	}
);

/*
 * Con i permalink «/news/%postname%/» WordPress mette «/news/» davanti a ogni tipo di contenuto e
 * tassonomia registrati con with_front (il default): i tracciati e i POI di wp-geohub finirebbero a
 * /news/track/…, la tassonomia tipologia_da_sapere e i tipi di oc:8760 sotto /news/. «/news/» vale solo
 * per i post: per tutti i tipi e le tassonomie non nativi with_front è falso (categorie e tag, nativi,
 * restano a /news/category/ e /news/tag/, che nessun link usa). Le regole si rigenerano al giro di pagine
 * dopo un cambio di FORESTAS_REGOLE_VERSIONE (qui sotto).
 */
function forestas_senza_front( array $argomenti ): array {
	if ( empty( $argomenti['_builtin'] ) && ( $argomenti['rewrite'] ?? true ) !== false ) {
		$argomenti['rewrite'] = array_merge( is_array( $argomenti['rewrite'] ?? null ) ? $argomenti['rewrite'] : [], [ 'with_front' => false ] );
	}
	return $argomenti;
}
add_filter( 'register_post_type_args', 'forestas_senza_front' );
add_filter( 'register_taxonomy_args', 'forestas_senza_front' );

/*
 * Precedenza delle schede della pagina News sui post (oc:8759). Con i permalink «/news/%postname%/»
 * WordPress prova /news/<x>/ come un post prima che come una pagina figlia di News: le schede
 * (/news/attivita-e-comunicazioni/) darebbero 404. Ogni scheda figlia (pagina con il meta della scheda e un genitore) ha
 * una regola sua, in testa: le schede si leggono dal database, quindi una scheda nuova (Eventi, oc:8760)
 * non richiede codice, ma le regole vanno rigenerate (lo fa bin/wordpress-config.sh pagine quando le
 * schede cambiano). Con WPML il prefisso /en/ è tolto prima delle regole: valgono per le due lingue.
 */
/** Percorsi delle schede figlie pubblicate, in tutte le lingue (la pagina madre è già una pagina). */
function forestas_schede_figlie(): array {
	$schede = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => FORESTAS_META_SCHEDA, 'suppress_filters' => true, 'orderby' => 'ID', 'order' => 'ASC' ] );
	return array_values( array_filter( array_map( fn( $s ) => $s->post_parent ? get_page_uri( $s ) : '', $schede ) ) );
}

function forestas_regole_schede(): array {
	$regole = [];
	foreach ( forestas_schede_figlie() as $percorso ) {
		$regole[ '^' . preg_quote( $percorso, '#' ) . '/?$' ] = 'index.php?pagename=' . $percorso;
	}
	return $regole;
}
add_filter( 'rewrite_rules_array', fn( array $regole ) => forestas_regole_schede() + $regole );

/*
 * Le regole si rigenerano quando cambiano le schede o le regole stesse: va alzata FORESTAS_REGOLE_VERSIONE
 * quando questo file cambia le sue regole (o forestas_senza_front). Il controllo lo fa il giro di
 * bin/wordpress-config.sh pagine (riga REGOLE), il pannello alla prima pagina dopo un aggiornamento del
 * child e dopo il salvataggio di una pagina: così vale anche dopo il lancio, con la sincronizzazione
 * spenta. Vero se le ha rigenerate.
 */
const FORESTAS_REGOLE_VERSIONE = 3;
const FORESTAS_OPZIONE_REGOLE  = 'forestas_regole_schede';

function forestas_regole_aggiorna(): bool {
	$firma = [ 'versione' => FORESTAS_REGOLE_VERSIONE, 'schede' => forestas_schede_figlie() ];
	if ( get_option( FORESTAS_OPZIONE_REGOLE ) === $firma ) {
		return false;
	}
	flush_rewrite_rules( false );
	update_option( FORESTAS_OPZIONE_REGOLE, $firma, false );
	return true;
}
add_action( 'admin_init', 'forestas_regole_aggiorna' );
add_action( 'save_post_page', fn() => delete_option( FORESTAS_OPZIONE_REGOLE ) );

/**
 * Pagina del perimetro con una chiave di config/pagine.json nella lingua corrente, o 0. Con i filtri di
 * WPML (get_posts li toglie per default): la chiave è la stessa sulla pagina italiana e su quella inglese.
 */
function forestas_pagina_per_chiave( string $chiave ): int {
	// Una volta per richiesta e per lingua: il filtro del menu la chiede per ogni voce
	static $trovate = [];
	$lingua         = (string) apply_filters( 'wpml_current_language', '' );
	if ( isset( $trovate[ $lingua ][ $chiave ] ) ) {
		return $trovate[ $lingua ][ $chiave ];
	}
	$pagine = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'meta_key' => FORESTAS_META_PAGINA, 'meta_value' => $chiave, 'fields' => 'ids', 'suppress_filters' => false ] );
	return $trovate[ $lingua ][ $chiave ] = (int) ( $pagine[0] ?? 0 );
}

/** La pagina News nella lingua corrente, o 0. */
function forestas_pagina_news(): int {
	return forestas_pagina_per_chiave( 'news' );
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
			return ''; // lo shortcode dentro il testo della news: niente seconda testata
		}
		$in_corso = true;
		$tag      = '';
		foreach ( get_the_tags( $id ) ?: [] as $t ) {
			$tag .= '<li>' . esc_html( $t->name ) . '</li>';
		}
		$tag = $tag !== '' ? '<div class="forestas-news-tag"><h2 class="screen-reader-text">Tags</h2><i class="fal fa-bookmark forestas-news-tag__icona" aria-hidden="true"></i><ul role="list">' . $tag . '</ul></div>' : '';
		$html     = do_shortcode( '[forestas_testata]' ) . forestas_corpo_contenuto( $id, $tag );
		$in_corso = false;
		return $html;
	}
);

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
		$nome = fn( int $s ) => (string) get_post_meta( $s, FORESTAS_META_ETICHETTA, true ) ?: get_the_title( $s );
		$link = '';
		foreach ( $schede as $s ) {
			$link .= sprintf( '<a href="%s"%s>%s</a>', esc_url( get_permalink( $s ) ), $s === $id ? ' aria-current="page"' : '', esc_html( $nome( $s ) ) );
		}
		$pulsante = get_post_meta( $id, FORESTAS_META_PULSANTE, true );
		$verso    = is_array( $pulsante ) ? forestas_pulsante_verso( (string) $pulsante['verso'] ) : '';
		return sprintf(
			'<nav class="forestas-schede forestas-sezione-%1$s" aria-label="%2$s">%3$s</nav><div class="forestas-scheda forestas-sezione-%1$s"><div class="forestas-scheda-titolo"><span aria-hidden="true"></span><h2>%4$s</h2></div>%5$s%6$s</div>',
			esc_attr( forestas_sezione_pagina( $madre ) ),
			esc_attr( sprintf( forestas_testo( 'Sezioni di %s', 'Sections of %s' ), get_the_title( $madre ) ) ),
			$link,
			esc_html( $nome( $id ) ),
			forestas_griglie_pagina( $id ),
			$verso !== '' ? '<p class="forestas-scheda-pulsante"><a class="forestas-pulsante" href="' . esc_url( $verso ) . '">' . esc_html( $pulsante['testo'] ) . '</a></p>' : ''
		);
	}
);

/*
 * Dove porta il pulsante di una scheda: la pagina d'elenco con quella chiave di config/pagine.json, o la
 * pagina di una tipologia («tipologia_da_sapere:4709», le schede di Da sapere, oc:8760). Vuoto se non c'è.
 */
function forestas_pulsante_verso( string $verso ): string {
	if ( str_contains( $verso, ':' ) ) {
		$termine = forestas_termine_drupal( $verso, strtok( $verso, ':' ) );
		$link    = $termine ? get_term_link( $termine ) : '';
		return is_string( $link ) ? $link : '';
	}
	$pagina = forestas_pagina_per_chiave( $verso );
	return $pagina ? (string) get_permalink( $pagina ) : '';
}

/*
 * Pagine oltre l'ultima: /tutte-le-news/page/99/ o /news/page/2/ (le schede non sono paginate) davano 200
 * con «Nessun contenuto» o con il contenuto della prima pagina. Diventano 404 prima di mostrare la pagina.
 */
add_action(
	'template_redirect',
	function () {
		$pagina = (int) get_query_var( 'paged' );
		if ( $pagina < 2 || ! is_page() ) {
			return;
		}
		$griglie   = get_post_meta( (int) get_queried_object_id(), FORESTAS_META_GRIGLIA, true );
		$griglie   = is_array( $griglie ) ? ( array_is_list( $griglie ) ? $griglie : [ $griglie ] ) : [];
		$paginate  = array_filter( $griglie, fn( $g ) => ! empty( $g['paginata'] ) );
		$ultima    = $paginate ? max( array_map( fn( $g ) => (int) forestas_griglia_query( $g, 1 )->max_num_pages, $paginate ) ) : 0;
		if ( $griglie && $pagina > $ultima ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);

/* Pagina d'elenco completo (/tutte-le-news, /attivita-e-comunicazioni): la griglia paginata. */
add_shortcode( 'forestas_elenco', fn() => get_the_ID() ? '<div class="forestas-elenco">' . forestas_griglie_pagina( (int) get_the_ID(), 2 ) . '</div>' : '' );

/*
 * Sulla pagina di una news o di un evento la voce del menu che porta a News è quella attiva, come sulle
 * pagine figlie di News (WordPress la segna da sé solo per le pagine, non per i post); su quella di un «da
 * sapere» la voce Da sapere (oc:8760).
 */
add_filter(
	'nav_menu_css_class',
	function ( array $classi, $voce ) {
		$madre = match ( true ) {
			is_singular( [ 'post', 'evento' ] ) => forestas_pagina_news(),
			is_singular( 'da_sapere' )          => forestas_pagina_per_chiave( 'da-sapere' ),
			default                             => 0,
		};
		if ( $madre && (int) $voce->object_id === $madre && $voce->object === 'page' ) {
			$classi[] = 'current-menu-ancestor';
			$classi[] = 'current-page-ancestor';
		}
		return $classi;
	},
	10,
	2
);
