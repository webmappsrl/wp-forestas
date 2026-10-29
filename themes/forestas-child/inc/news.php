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
// per data, gli eventi per data di inizio, i «da sapere» per data di creazione su Drupal (forestas_news_query)
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
		$html     = do_shortcode( '[forestas_testata]' )
			. '<div class="forestas-pagina-testo forestas-news-corpo"' . forestas_attributo_lingua( $id ) . '>' . $tag . forestas_testo_importato( $id ) . forestas_allegati( $id ) . forestas_news_collegamenti( $id ) . forestas_galleria( $id ) . '</div>';
		$in_corso = false;
		return $html;
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
function forestas_news_card( int $id, string $variante, int $livello = 3 ): string {
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
	// L'etichetta è un testo dell'interfaccia («Evento») o i nomi di termini: questi, in inglese, possono
	// essere italiani (ripiego di WPML) e hanno allora lang="it" (WCAG 3.1.2)
	$termini = [];
	if ( $tipo === 'evento' ) {
		$etichetta = forestas_testo( 'Evento', 'Event' ); // come nel Canvas (uc--evento): il tipo, non i tag
	} else {
		if ( $tipo === 'da_sapere' ) {
			$termini = array_filter( [ forestas_da_sapere_tipologia( $id ) ] );
		} elseif ( ! in_array( $variante, [ 'evidenza', 'compatta' ], true ) ) {
			$termini = get_the_tags( $id ) ?: [];
		} else {
			$termini = array_filter( [ forestas_news_tipologia( $id ) ] );
		}
		$etichetta = implode( ', ', wp_list_pluck( $termini, 'name' ) );
	}
	$lingua_etichetta = $termini ? forestas_attributo_lingua_termine( reset( $termini ) ) : '';
	$data   = match ( $tipo ) {
		'post'   => forestas_news_data( $id ),
		'evento' => forestas_evento_quando( $id ),
		default  => '',
	};
	$classi = 'forestas-card forestas-card--' . ( [ 'evento' => 'evento', 'da_sapere' => 'da-sapere' ][ $tipo ] ?? 'news' ) . ' forestas-card--' . $variante . ( $immagine === '' ? ' forestas-card--senza-immagine' : '' );
	$media  = $immagine !== ''
		? '<div class="forestas-card__media">' . $immagine . ( $variante === 'evidenza' && $tipo === 'post' ? '<span class="forestas-card__flag">' . esc_html( forestas_testo( 'In evidenza', 'Highlighted' ) ) . '</span>' : '' ) . ( $tipo === 'evento' ? forestas_evento_data( $id ) : '' ) . '</div>'
		: '';
	return sprintf(
		'<article class="%1$s">%2$s<div class="forestas-card__testo">%3$s<h%7$d class="forestas-card__titolo"%8$s><a href="%4$s">%5$s</a></h%7$d>%6$s</div></article>',
		esc_attr( $classi ),
		$media,
		$etichetta !== '' ? '<span class="forestas-card__tag"' . $lingua_etichetta . '>' . esc_html( $etichetta ) . '</span>' : '',
		esc_url( get_permalink( $id ) ),
		esc_html( get_the_title( $id ) ),
		$data !== '' ? '<p class="forestas-card__data">' . $data . '</p>' : '',
		$livello,
		forestas_attributo_lingua( $id )
	);
}

/*
 * News di una griglia, come le View di Drupal: categoria «news», filtrate per tipologia o «in evidenza»,
 * ordinate per data (field_data), più recenti prima. Su Drupal la scheda Attività (blocco
 * attivita_e_comunicazioni, block_2) ordinava per field_date, un campo nascosto nel form che le news
 * recenti non hanno: qui per data anche lei (decisione del dev, 27/10).
 */
function forestas_news_query( array $griglia, int $pagina = 1 ): WP_Query {
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
	// Filtri scelti nella colonna dei filtri (forestas_news_filtri): anche il controllo delle pagine oltre
	// l'ultima passa di qui, e conta le pagine della lista filtrata
	foreach ( forestas_news_filtri_scelti( $griglia ) as $campo => $scelto ) {
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
 * Ordine degli eventi per data di inizio (forestas_news_query): join sul meta con la chiave nella
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
function forestas_news_filtri_campi( array $griglia ): array {
	return array_values( array_filter( (array) ( $griglia['filtri'] ?? [] ), fn( $f ) => in_array( $f['campo'] ?? '', [ 'tipologia', 'tag', 'anno' ], true ) ) );
}

/** Tassonomia di un campo dei filtri: i tag, o la tassonomia delle tipologie della griglia. */
function forestas_news_filtri_tassonomia( array $griglia, string $campo ): string {
	return $campo === 'tag' ? 'post_tag' : (string) ( $griglia['tassonomia'] ?? 'category' );
}

/** I valori scelti nei filtri della griglia: [ campo => WP_Term o anno ]. */
function forestas_news_filtri_scelti( array $griglia ): array {
	$scelti = [];
	foreach ( forestas_news_filtri_campi( $griglia ) as $f ) {
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
		$tassonomia = forestas_news_filtri_tassonomia( $griglia, $campo );
		$termine    = get_term_by( 'slug', $dato, $tassonomia );
		// Fra le categorie solo le tipologie, sottocategorie di News: la categoria News le comprende tutte
		if ( $termine instanceof WP_Term && ( $tassonomia !== 'category' || $termine->parent ) ) {
			$scelti[ $campo ] = $termine;
		}
	}
	return $scelti;
}

/** I valori di un contenuto per un campo dei filtri: [ chiave => nome ] (un tag ne può avere più d'uno). */
function forestas_news_filtri_valori( WP_Post $p, string $campo, string $tassonomia ): array {
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
function forestas_news_filtri_conta( array $griglia, array $campi, array $scelti ): array {
	$base = forestas_news_query( array_merge( $griglia, [ 'filtri' => [], 'quanti' => -1 ] ) )->posts;
	// Tag e tipologie di tutti i contenuti in una query: senza, una per contenuto e per gruppo
	update_object_term_cache( wp_list_pluck( $base, 'ID' ), array_unique( wp_list_pluck( $base, 'post_type' ) ) );
	$valori_di = array_map(
		fn( $p ) => array_combine( array_column( $campi, 'campo' ), array_map( fn( $f ) => forestas_news_filtri_valori( $p, $f['campo'], forestas_news_filtri_tassonomia( $griglia, $f['campo'] ) ), $campi ) ),
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
function forestas_news_filtri( array $griglia ): string {
	$campi  = forestas_news_filtri_campi( $griglia );
	$scelti = forestas_news_filtri_scelti( $griglia );
	$gruppi = forestas_news_filtri_conta( $griglia, $campi, $scelti );
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
function forestas_news_griglia( int $pagina_id, int $livello = 3 ): string {
	$griglie = get_post_meta( $pagina_id, FORESTAS_META_GRIGLIA, true );
	if ( ! is_array( $griglie ) ) {
		return '';
	}
	return implode( '', array_map( fn( $g ) => forestas_news_una_griglia( $g, $livello ), array_is_list( $griglie ) ? $griglie : [ $griglie ] ) );
}

/*
 * Una griglia di card. $livello è quello dei titoli delle card: h3 nelle schede, sotto il titolo della
 * scheda (h2); h2 nelle pagine d'elenco, subito sotto il titolo della pagina (h1), senza saltare un livello.
 */
function forestas_news_una_griglia( array $griglia, int $livello = 3 ): string {
	if ( ! empty( $griglia['pillole'] ) ) {
		return forestas_da_sapere_pillole( $livello ); // le tipologie, non dei contenuti (inc/da-sapere.php)
	}
	$paginata = ! empty( $griglia['paginata'] );
	$q        = forestas_news_query( $griglia, $paginata ? (int) get_query_var( 'paged' ) : 1 );
	$variante = $griglia['variante'] ?? 'immagine';
	$card     = '';
	update_post_thumbnail_cache( $q ); // le immagini delle card in una query sola
	foreach ( $q->posts as $i => $p ) {
		$card .= forestas_news_card( $p->ID, $variante === 'evidenza' ? ( $i === 0 ? 'evidenza' : 'compatta' ) : $variante, $livello );
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
		$html = '<div class="forestas-filtrabile">' . forestas_news_filtri( $griglia ) . '<div class="forestas-filtrabile__lista">' . $html . '</div></div>';
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
			forestas_news_griglia( $id ),
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
		$ultima    = $paginate ? max( array_map( fn( $g ) => (int) forestas_news_query( $g, 1 )->max_num_pages, $paginate ) ) : 0;
		if ( $griglie && $pagina > $ultima ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}
);

/* Pagina d'elenco completo (/tutte-le-news, /attivita-e-comunicazioni): la griglia paginata. */
add_shortcode( 'forestas_elenco', fn() => get_the_ID() ? '<div class="forestas-elenco">' . forestas_news_griglia( (int) get_the_ID(), 2 ) . '</div>' : '' );

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
