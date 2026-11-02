<?php
/**
 * Griglie di card delle schede e delle pagine d'elenco (config/pagine.json, meta _wpf_griglia), per news,
 * eventi e «da sapere»: la query come le View di Drupal, i filtri come le sue faccette, la paginazione. Le
 * card sono in inc/contenuti.php; le schede e le pagine d'elenco che le mostrano in inc/news.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * News di una griglia, come le View di Drupal: categoria «news», filtrate per tipologia o «in evidenza»,
 * ordinate per data (field_data), più recenti prima. Su Drupal la scheda Attività (blocco
 * attivita_e_comunicazioni, block_2) ordinava per field_date, un campo nascosto nel form che le news
 * recenti non hanno: qui per data anche lei (decisione del dev, 27/10).
 */
function forestas_griglia_query( array $griglia, int $pagina = 1 ): WP_Query {
	$tipo = (string) ( $griglia['tipo'] ?? 'post' );
	$args = [
		'post_type'      => $tipo,
		'post_status'    => 'publish',
		'posts_per_page' => (int) ( $griglia['quanti'] ?? 9 ),
		'paged'          => max( 1, $pagina ),
		// A parità di data prima il nid più basso, come le View di Drupal (le news si creano in ordine di nid)
		'orderby'        => [ 'date' => 'DESC', 'ID' => 'ASC' ],
	];
	// I post sono le news solo nella categoria News (la categoria predefinita, ma la redazione può cambiarla)
	if ( $tipo === 'post' ) {
		$args['category_name'] = FORESTAS_NEWS_CATEGORIA;
	}
	/*
	 * Gli eventi per data di inizio, come lista_eventi di Drupal (field_data DESC; i passati restano): l'ordine
	 * lo mette forestas_ordine_eventi, con un join che non esclude un evento senza data (pubblicato dalla
	 * Modifica rapida) e lo manda in fondo. Una meta_query OR EXISTS/NOT EXISTS ordinerebbe quell'evento sul
	 * valore di un suo meta qualsiasi (oc:8760).
	 * I «da sapere» delle schede per data di creazione su Drupal (la data del post), come la View
	 * sa_sapere_in_evidenza (created): «Sempre in cima» conta solo nella pagina della tipologia (inc/da-sapere.php).
	 */
	if ( $tipo === 'evento' ) {
		$args['forestas_ordine_eventi'] = true;
	}
	if ( ! empty( $griglia['tipologia'] ) ) {
		$tassonomia = (string) ( $griglia['tassonomia'] ?? 'category' );
		$termine    = forestas_termine_drupal( ( $griglia['vocabolario'] ?? 'tipologia_news' ) . ':' . (int) $griglia['tipologia'], $tassonomia );
		// Una tipologia che non c'è (import non ancora fatto) non mostra tutti i contenuti
		$args['tax_query'] = [ [ 'taxonomy' => $tassonomia, 'field' => 'term_id', 'terms' => $termine ? [ $termine->term_id ] : [ 0 ] ] ];
	}
	if ( ! empty( $griglia['in_evidenza'] ) ) {
		$args['meta_query'][] = [ 'key' => FORESTAS_META_IN_EVIDENZA, 'value' => '1' ];
	}
	// Filtri scelti nella colonna dei filtri (forestas_filtri): anche il controllo delle pagine oltre
	// l'ultima passa di qui, e conta le pagine della lista filtrata
	foreach ( forestas_filtri_scelti( $griglia ) as $campo => $scelto ) {
		if ( $campo !== 'anno' ) {
			$args['tax_query'][] = [ 'taxonomy' => $scelto->taxonomy, 'field' => 'term_id', 'terms' => [ $scelto->term_id ] ];
		} elseif ( $tipo === 'evento' ) {
			$args['meta_query'][] = [ 'key' => FORESTAS_META_EVENTO_INIZIO, 'value' => $scelto . '-', 'compare' => 'LIKE' ];
		} else {
			$args['date_query'] = [ [ 'year' => $scelto ] ];
		}
	}
	return new WP_Query( $args );
}

/*
 * Ordine degli eventi per data di inizio (forestas_griglia_query): join sul meta con la chiave nella
 * condizione, così un evento senza data resta nella lista e va in fondo; a parità di data il più vecchio
 * per id, come le altre liste.
 */
add_filter(
	'posts_clauses',
	function ( array $clausole, WP_Query $q ): array {
		if ( ! $q->get( 'forestas_ordine_eventi' ) ) {
			return $clausole;
		}
		global $wpdb;
		$clausole['join']   .= $wpdb->prepare( " LEFT JOIN {$wpdb->postmeta} AS forestas_inizio ON ( forestas_inizio.post_id = {$wpdb->posts}.ID AND forestas_inizio.meta_key = %s )", FORESTAS_META_EVENTO_INIZIO );
		$clausole['orderby'] = "forestas_inizio.meta_value IS NULL, forestas_inizio.meta_value DESC, {$wpdb->posts}.ID ASC";
		return $clausole;
	},
	10,
	2
);

// Voci visibili di un gruppo di filtri prima di «Mostra di più» (soft_limit delle faccette di Drupal)
const FORESTAS_FILTRI_VISIBILI = 5;

/*
 * Filtri di una lista, come le faccette di Drupal (Search API + Facets): il campo «filtri» della griglia in
 * config/pagine.json è l'elenco dei gruppi, ciascuno [ campo, titolo { it, en }, numeri, ordine ]:
 * - campo: «tipologia» (nella tassonomia della griglia: la sottocategoria per le news, la tipologia per i
 *   «da sapere»), «tag» (i tag della news o dell'evento), «anno» (della data mostrata: quella della news,
 *   l'inizio dell'evento; su Drupal il vecchio field_date, che le news recenti e gli eventi non hanno);
 * - numeri: false nasconde il numero di contenuti (show_numbers di Drupal, il tag di Tutte le news);
 * - ordine: «numero» (più contenuti prima, poi per valore: count_widget_order) o «valore_desc» (per valore
 *   decrescente: l'anno di Tutte le news).
 * Il valore scelto sta nell'indirizzo, ?<campo>=<slug del termine o anno>; uno che non c'è non filtra.
 */
function forestas_filtri_campi( array $griglia ): array {
	return array_values( array_filter( (array) ( $griglia['filtri'] ?? [] ), fn( $f ) => in_array( $f['campo'] ?? '', [ 'tipologia', 'tag', 'anno' ], true ) ) );
}

/** Tassonomia di un campo dei filtri: i tag, o la tassonomia delle tipologie della griglia. */
function forestas_filtri_tassonomia( array $griglia, string $campo ): string {
	return $campo === 'tag' ? 'post_tag' : (string) ( $griglia['tassonomia'] ?? 'category' );
}

/** I valori scelti nei filtri della griglia: [ campo => WP_Term o anno ]. */
function forestas_filtri_scelti( array $griglia ): array {
	$scelti = [];
	foreach ( forestas_filtri_campi( $griglia ) as $f ) {
		$campo = $f['campo'];
		// Solo un testo: ?anno[]=… (un array) darebbe un avviso PHP a ogni richiesta e un filtro su «array»
		$grezzo = $_GET[ $campo ] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification -- filtro di sola lettura
		$dato   = is_string( $grezzo ) ? sanitize_title( wp_unslash( $grezzo ) ) : '';
		if ( $dato === '' ) {
			continue;
		}
		if ( $campo === 'anno' ) {
			if ( preg_match( '/^\d{4}$/', $dato ) ) {
				$scelti['anno'] = (int) $dato;
			}
			continue;
		}
		$tassonomia = forestas_filtri_tassonomia( $griglia, $campo );
		$termine    = get_term_by( 'slug', $dato, $tassonomia );
		// Fra le categorie solo le tipologie, sottocategorie di News: la categoria News le comprende tutte
		if ( $termine instanceof WP_Term && ( $tassonomia !== 'category' || $termine->parent ) ) {
			$scelti[ $campo ] = $termine;
		}
	}
	return $scelti;
}

/** I valori di un contenuto per un campo dei filtri: [ chiave => nome ] (un tag ne può avere più d'uno). */
function forestas_filtri_valori( WP_Post $p, string $campo, string $tassonomia ): array {
	if ( $campo === 'anno' ) {
		$data = $p->post_type === 'evento' ? (string) get_post_meta( $p->ID, FORESTAS_META_EVENTO_INIZIO, true ) : get_the_date( 'Y', $p );
		return $data !== '' ? [ substr( $data, 0, 4 ) => substr( $data, 0, 4 ) ] : [];
	}
	if ( $tassonomia === 'category' && $campo === 'tipologia' ) {
		$termini = array_filter( [ forestas_news_tipologia( $p->ID ) ] );
	} else {
		$termini = get_the_terms( $p->ID, $tassonomia );
		$termini = is_array( $termini ) ? $termini : [];
	}
	return array_map( 'strval', wp_list_pluck( $termini, 'name', 'slug' ) );
}

/*
 * I numeri di ogni gruppo: [ campo => [ valore => [ nome, numero ] ] ], nell'ordine del gruppo. Un
 * contenuto conta in un gruppo solo se passa i valori scelti negli altri gruppi: le faccette di Drupal sono
 * in OR, e i numeri di un gruppo dicono cosa si ottiene cambiando quel valore e tenendo gli altri.
 */
function forestas_filtri_conta( array $griglia, array $campi, array $scelti ): array {
	$base = forestas_griglia_query( array_merge( $griglia, [ 'filtri' => [], 'quanti' => -1 ] ) )->posts;
	// Tag e tipologie di tutti i contenuti in una query: senza, una per contenuto e per gruppo
	update_object_term_cache( wp_list_pluck( $base, 'ID' ), array_unique( wp_list_pluck( $base, 'post_type' ) ) );
	$valori_di = array_map(
		fn( $p ) => array_combine( array_column( $campi, 'campo' ), array_map( fn( $f ) => forestas_filtri_valori( $p, $f['campo'], forestas_filtri_tassonomia( $griglia, $f['campo'] ) ), $campi ) ),
		$base
	);
	$scelti_chiave = array_map( fn( $s ) => $s instanceof WP_Term ? $s->slug : (string) $s, $scelti );
	$gruppi        = [];
	foreach ( $campi as $f ) {
		$campo  = $f['campo'];
		$attivo = $scelti_chiave[ $campo ] ?? '';
		$conta  = [];
		$nomi   = [];
		foreach ( $valori_di as $valori ) {
			foreach ( $scelti_chiave as $altro => $chiave ) {
				if ( $altro !== $campo && ! isset( $valori[ $altro ][ $chiave ] ) ) {
					continue 2;
				}
			}
			foreach ( $valori[ $campo ] as $chiave => $nome ) {
				$conta[ $chiave ] = ( $conta[ $chiave ] ?? 0 ) + 1;
				$nomi[ $chiave ]  = $nome;
			}
		}
		// Il valore scelto in fondo; poi per numero o per valore, senza distinguere maiuscole e accenti come
		// Drupal («alert» prima di «App»)
		$ord        = array_map( fn( $n ) => strtolower( remove_accents( $n ) ), $nomi );
		$per_valore = ( $f['ordine'] ?? 'numero' ) === 'valore_desc';
		uksort(
			$conta,
			fn( $a, $b ) => $per_valore
				? [ (string) $a === $attivo, $ord[ $b ] ] <=> [ (string) $b === $attivo, $ord[ $a ] ]
				: [ (string) $a === $attivo, -$conta[ $a ], $ord[ $a ] ] <=> [ (string) $b === $attivo, -$conta[ $b ], $ord[ $b ] ]
		);
		$gruppi[ $campo ] = array_map( fn( $chiave ) => [ $nomi[ $chiave ], $conta[ $chiave ] ], array_combine( array_keys( $conta ), array_keys( $conta ) ) );
	}
	return $gruppi;
}

/*
 * Colonna dei filtri, come i blocchi delle faccette di Drupal (sase-block--facets, widget links):
 * - ogni gruppo ha il titolo e la freccia per aprirlo e chiuderlo; all'apertura della pagina è chiuso,
 *   tranne quello con un valore scelto (su Drupal la freccia è in su a gruppo chiuso, in giù ad aperto);
 * - le voci sono link con il numero di contenuti in un riquadro a destra; le prime FORESTAS_FILTRI_VISIBILI,
 *   le altre dietro «Mostra di più» (soft limit);
 * - il valore scelto va in fondo al suo gruppo, e tutto il gruppo ha i valori in grassetto e i numeri su
 *   fondo pieno (.facet-active di Drupal); cliccandolo si toglie.
 */
function forestas_filtri( array $griglia ): string {
	$campi  = forestas_filtri_campi( $griglia );
	$scelti = forestas_filtri_scelti( $griglia );
	$gruppi = forestas_filtri_conta( $griglia, $campi, $scelti );
	$html   = '';
	foreach ( $campi as $f ) {
		$campo = $f['campo'];
		if ( empty( $gruppi[ $campo ] ) ) {
			continue;
		}
		$attivo = isset( $scelti[ $campo ] ) ? ( $scelti[ $campo ] instanceof WP_Term ? $scelti[ $campo ]->slug : (string) $scelti[ $campo ] ) : '';
		$numeri = $f['numeri'] ?? true;
		$voci   = '';
		$i      = 0;
		foreach ( $gruppi[ $campo ] as $valore => [ $nome, $numero ] ) {
			$scelto = (string) $valore === $attivo;
			// Si torna alla prima pagina: la pagina corrente potrebbe non esistere nella lista filtrata
			$url   = remove_query_arg( 'paged', $scelto ? remove_query_arg( $campo ) : add_query_arg( $campo, $valore ) );
			$url   = preg_replace( '#/page/\d+/?#', '/', $url );
			$voci .= sprintf(
				'<li%4$s><a href="%1$s" rel="nofollow"%3$s><span class="forestas-filtri__valore">%5$s%2$s</span>%6$s</a></li>',
				esc_url( $url ),
				esc_html( $nome ),
				$scelto ? ' aria-current="true"' : '',
				++$i > FORESTAS_FILTRI_VISIBILI ? ' class="forestas-filtri__in-piu"' : '',
				$scelto ? '<span class="screen-reader-text">' . esc_html( forestas_testo( 'Togli il filtro', 'Remove filter' ) ) . ': </span>' : '',
				$numeri ? ' <span class="forestas-filtri__numero">' . (int) $numero . '<span class="screen-reader-text"> ' . esc_html( forestas_testo( 'risultati', 'results' ) ) . '</span></span>' : ''
			);
		}
		$titolo = forestas_testo( (string) ( $f['titolo']['it'] ?? $campo ), (string) ( $f['titolo']['en'] ?? $f['titolo']['it'] ?? $campo ) );
		$id     = 'forestas-filtro-' . $campo;
		$altri  = count( $gruppi[ $campo ] ) > FORESTAS_FILTRI_VISIBILI
			? '<button type="button" class="forestas-filtri__mostra" aria-expanded="false">' . esc_html( forestas_testo( 'Mostra di più', 'Show more' ) ) . '</button>'
			: '';
		$html  .= sprintf(
			'<div class="forestas-filtri__gruppo%1$s"><div class="forestas-filtri__testata"><h3 class="forestas-filtri__titolo">%2$s</h3><button type="button" class="forestas-filtri__apri-gruppo" aria-expanded="%3$s" aria-controls="%4$s"><span class="screen-reader-text">%5$s</span><i class="far fa-chevron-down" aria-hidden="true"></i></button></div><div class="forestas-filtri__voci" id="%4$s"><ul role="list">%6$s</ul>%7$s</div></div>',
			$attivo !== '' ? ' is-aperto is-attivo' : '',
			esc_html( $titolo ),
			$attivo !== '' ? 'true' : 'false',
			esc_attr( $id ),
			esc_html( forestas_testo( 'Espandi/collassa filtro', 'Expand/collapse filter' ) . ': ' . $titolo ),
			$voci,
			$altri
		);
	}
	if ( $html === '' ) {
		return '';
	}
	// Sotto 960px i filtri stanno dietro il pulsante «Filtri», come sase-sidebar-mobile di Drupal (assets/js/filtri.js)
	return '<aside class="forestas-filtri" aria-labelledby="forestas-filtri-titolo">'
		. '<button type="button" class="forestas-filtri__apri" aria-expanded="false" aria-controls="forestas-filtri-gruppi">' . esc_html( forestas_testo( 'Filtri', 'Filters' ) ) . ' <i class="far fa-filter" aria-hidden="true"></i></button>'
		. '<div class="forestas-filtri__gruppi" id="forestas-filtri-gruppi"><h2 class="screen-reader-text" id="forestas-filtri-titolo">' . esc_html( forestas_testo( 'Filtri', 'Filters' ) ) . '</h2>' . $html . '</div></aside>';
}

/*
 * Le griglie di card di una pagina con il meta della griglia, una dopo l'altra: /attivita-e-comunicazioni
 * ha sopra le 4 in evidenza, come su Drupal. Con «paginata» anche la paginazione.
 */
function forestas_griglie_pagina( int $pagina_id, int $livello = 3 ): string {
	$griglie = get_post_meta( $pagina_id, FORESTAS_META_GRIGLIA, true );
	if ( ! is_array( $griglie ) ) {
		return '';
	}
	return implode( '', array_map( fn( $g ) => forestas_griglia( $g, $livello ), array_is_list( $griglie ) ? $griglie : [ $griglie ] ) );
}

/*
 * Una griglia di card. $livello è quello dei titoli delle card: h3 nelle schede, sotto il titolo della
 * scheda (h2); h2 nelle pagine d'elenco, subito sotto il titolo della pagina (h1), senza saltare un livello.
 */
function forestas_griglia( array $griglia, int $livello = 3 ): string {
	if ( ! empty( $griglia['pillole'] ) ) {
		return forestas_da_sapere_pillole( $livello ); // le tipologie, non dei contenuti (inc/da-sapere.php)
	}
	$paginata = ! empty( $griglia['paginata'] );
	$q        = forestas_griglia_query( $griglia, $paginata ? (int) get_query_var( 'paged' ) : 1 );
	$variante = $griglia['variante'] ?? 'immagine';
	$card     = '';
	update_post_thumbnail_cache( $q ); // le immagini delle card in una query sola
	foreach ( $q->posts as $i => $p ) {
		$card .= forestas_card( $p->ID, $variante === 'evidenza' ? ( $i === 0 ? 'evidenza' : 'compatta' ) : $variante, $livello );
	}
	$colonne = ! empty( $griglia['colonne'] ) ? ' forestas-griglia--colonne-' . (int) $griglia['colonne'] : '';
	$html    = $card === ''
		? '<p class="forestas-griglia-vuota">' . esc_html( forestas_testo( 'Nessun contenuto.', 'No content.' ) ) . '</p>'
		: '<div class="forestas-griglia forestas-griglia--' . esc_attr( $variante ) . $colonne . '">' . $card . '</div>';
	if ( $card !== '' && $paginata && $q->max_num_pages > 1 ) {
		$html .= '<nav class="forestas-paginazione" aria-label="' . esc_attr( forestas_testo( 'Paginazione', 'Pagination' ) ) . '">'
			// Le frecce con un nome per lo screen reader, che altrimenti leggerebbe «›» o niente
			. paginate_links(
				[
					'total'     => $q->max_num_pages,
					'current'   => max( 1, (int) get_query_var( 'paged' ) ),
					'prev_text' => '<span aria-hidden="true">‹</span><span class="screen-reader-text">' . esc_html( forestas_testo( 'Pagina precedente', 'Previous page' ) ) . '</span>',
					'next_text' => '<span aria-hidden="true">›</span><span class="screen-reader-text">' . esc_html( forestas_testo( 'Pagina successiva', 'Next page' ) ) . '</span>',
				]
			)
			. '</nav>';
	}
	// Con i filtri: colonna dei filtri a sinistra e lista, come la griglia 3-9 della pagina di Drupal
	if ( ! empty( $griglia['filtri'] ) ) {
		wp_enqueue_script( 'forestas-filtri', get_stylesheet_directory_uri() . '/assets/js/filtri.js', [], forestas_versione(), [ 'in_footer' => true, 'strategy' => 'defer' ] );
		$html = '<div class="forestas-filtrabile">' . forestas_filtri( $griglia ) . '<div class="forestas-filtrabile__lista">' . $html . '</div></div>';
	}
	return $html;
}
