<?php
/**
 * News da Drupal (oc:8759): il tipo «news» del giro di import (import-lib.php, oc:8760), con la sua
 * categoria, i suoi termini e la sua scrittura.
 *
 * Ogni news it/und di Drupal è un post con la categoria «news»; le tipologie (tipologia_news) sono
 * sottocategorie di «news», i tag (tag_news e tags) sono post_tag. Una news si riconosce da _drupal_nid e
 * _wpf_lingua, come le pagine dal perimetro. Le news tradotte su Drupal hanno il post inglese collegato
 * in WPML; le altre si vedono in italiano anche sul sito inglese (ripiego di WPML, config/wpml.json).
 *
 * Categorie e tag sono gli stessi nelle due lingue: su Drupal tipologia_news, tag_news e tags non hanno
 * traduzioni, e WPML li mostra come sono (ripiego anche sulle tassonomie).
 */

require_once __DIR__ . '/contenuti-lib.php';

// Più news (o traduzioni) di così da mettere in bozza nello stesso giro: copia Drupal sospetta, ci si ferma
const WPF_NEWS_SOGLIA_BOZZE = 5;
// Più di così fra le news pubblicate in WordPress e quelle pubblicate nella copia: copia a metà, ci si ferma
const WPF_NEWS_SCARTO_COPIA = 5;
// Chiave in config/post.json del template di Impreza della news
const WPF_TEMPLATE_NEWS = 'template-news';
// Categoria che contiene tutte le news; le tipologie di Drupal sono sue figlie
const WPF_NEWS_CATEGORIA = 'news';
// Prefisso degli indirizzi delle news, quello di permalink_structure in config/sito.json (/news/%postname%/)
const WPF_NEWS_PREFISSO = 'news';

/** Il tipo «news» per wpf_import_sincronizza (import-lib.php). */
function wpf_news_tipo(): array {
	return [
		'origine'      => 'news',
		'riga'         => 'NEWS',
		'nome'         => 'news',
		'plurale'      => 'news',
		'questo'       => 'la news',
		'genere'       => [ 'nessuna news scritta', 'pubblicate', 'state tolte', 'accettate', 'sincronizzate' ],
		'post_type'    => 'post',
		'prefisso'     => WPF_NEWS_PREFISSO,
		'schede'       => 'news',
		'pagina'       => 'News',
		'soglia_bozze' => WPF_NEWS_SOGLIA_BOZZE,
		'scarto_copia' => WPF_NEWS_SCARTO_COPIA,
		'conteggio'    => 'WPF_NEWS_PROVA_CONTEGGIO',
		'bundle'       => 'news',
		'nodo'         => 'wpf_drupal_news',
		'termini'      => 'wpf_news_termini',
		'scrivi'       => 'wpf_news_scrivi',
		'template'     => WPF_TEMPLATE_NEWS,
		'tassonomie'   => [ 'category', 'post_tag' ],
	];
}

/**
 * La categoria che contiene le news, ritrovata per slug o creata: [ 'id', 'esito', 'errore' ] come
 * wpf_termine_ritrova_o_crea. Non viene da un termine di Drupal, quindi non ha il term meta.
 */
function wpf_news_categoria( bool $prova ): array {
	$c = wpf_wpml_tutte_le_lingue( fn() => get_term_by( 'slug', WPF_NEWS_CATEGORIA, 'category' ) );
	if ( $c ) {
		return [ 'id' => (int) $c->term_id, 'esito' => 'ritrovato', 'errore' => null ];
	}
	return $prova ? [ 'id' => 0, 'esito' => 'da creare', 'errore' => null ] : wpf_termine_crea( 'News', 'category', [ 'slug' => WPF_NEWS_CATEGORIA ] );
}

/**
 * Categoria predefinita dei post impostata su News, in tutte le lingue di WPML (in WPML vale per lingua:
 * un post creato in inglese prenderebbe quella della sua lingua). Vero se c'era da cambiarla.
 */
function wpf_news_categoria_predefinita( int $categoria, bool $prova ): bool {
	global $sitepress;
	$lingue   = $sitepress ? array_keys( (array) apply_filters( 'wpml_active_languages', [], [ 'skip_missing' => 0 ] ) ) : [];
	$per_lingua = $sitepress ? (array) $sitepress->get_setting( 'default_categories', [] ) : [];
	$da_fare  = (int) get_option( 'default_category' ) !== $categoria
		|| array_filter( $lingue, fn( $l ) => (int) ( $per_lingua[ $l ] ?? 0 ) !== $categoria );
	if ( $da_fare && ! $prova ) {
		update_option( 'default_category', $categoria );
		if ( $sitepress && $lingue ) {
			$sitepress->set_setting( 'default_categories', array_merge( $per_lingua, array_fill_keys( $lingue, (string) $categoria ) ), true );
		}
	}
	return (bool) $da_fare;
}

/**
 * Termini delle news (categoria, tipologie, tag): [ 'categoria' => id, 'tipologie' => [ tid => id ],
 * 'tag' => [ "vid:tid" => id ], 'righe' => righe di log, 'mancanti' => [ dettaglio => causa ] ]. In
 * anteprima un termine da creare ha id 0.
 */
function wpf_news_termini( mysqli $db, array $dati, bool $prova ): array {
	$righe     = [];
	$mancanti  = [];
	$categoria = wpf_news_categoria( $prova );
	if ( $categoria['esito'] !== 'ritrovato' ) {
		$righe[] = "categoria «News»: {$categoria['esito']}" . ( $categoria['errore'] ? " ({$categoria['errore']})" : '' );
	}
	if ( $categoria['errore'] ) {
		$mancanti['categoria News'] = $categoria['errore'];
	}
	// Una news scritta dalla redazione in WordPress parte nella categoria News, non in «Senza categoria»:
	// le schede e le pagine d'elenco mostrano solo la categoria News e le sue tipologie. Con WPML la
	// categoria predefinita è per lingua: la stessa per tutte (le categorie si mostrano col ripiego)
	if ( $categoria['id'] && wpf_news_categoria_predefinita( $categoria['id'], $prova ) ) {
		$righe[] = 'categoria predefinita dei post: News' . ( $prova ? ' (da impostare)' : '' );
	}
	$tid_tipologie = [];
	foreach ( $dati as $lingue ) {
		foreach ( $lingue as $d ) {
			if ( $d['tipologia'] !== null ) {
				$tid_tipologie[] = $d['tipologia'];
			}
		}
	}
	$termini = wpf_drupal_termini( $db, $tid_tipologie );
	$voci    = [];
	foreach ( array_unique( $tid_tipologie ) as $tid ) {
		if ( isset( $termini[ $tid ] ) ) { // una tipologia che su Drupal non esiste più lo dice la riga della news
			$voci[ $tid ] = [ 'tipologia', (string) $tid, $termini[ $tid ]['vid'], $tid, 'category', $categoria['id'] ];
		}
	}
	$tipologie = wpf_import_termini( $db, $voci, $prova );
	$tag       = wpf_import_tag( $db, $dati, $prova );
	$righe     = array_merge( $righe, $tipologie['righe'], $tag['righe'] );
	$mancanti  = array_merge( $mancanti, $tipologie['mancanti'], $tag['mancanti'] );
	return [ 'categoria' => $categoria['id'], 'tipologie' => $tipologie['id'], 'tag' => $tag['tag'], 'righe' => $righe, 'mancanti' => $mancanti ];
}

/**
 * Crea o aggiorna una news in una lingua (wpf_contenuto_scrivi).
 *
 * @param array  $d       da wpf_drupal_news, con 'body' già con i link riscritti e 'collegamenti' riscritti
 * @param array  $termini da wpf_news_termini
 * @return array come wpf_contenuto_scrivi, più 'avvisi' => string[]
 */
function wpf_news_scrivi( int $nid, string $lingua, array $d, ?WP_Post $post, ?int $id_it, array $termini, int $template, bool $prova, ?array $solo = null ): array {
	$avvisi    = [];
	$categorie = [ $termini['categoria'] ];
	if ( $d['tipologia'] !== null ) {
		if ( isset( $termini['tipologie'][ $d['tipologia'] ] ) ) {
			$categorie[] = $termini['tipologie'][ $d['tipologia'] ];
		} else {
			$avvisi[] = "la tipologia {$d['tipologia']} non esiste più su Drupal: la news resta senza tipologia";
		}
	}
	$tag    = wpf_import_tag_contenuto( $d, $termini['tag'] );
	$avvisi = array_merge( $avvisi, $tag['avvisi'] );
	$riferimenti = wpf_import_riferimenti( $d['riferimenti'] );
	$avvisi      = array_merge( $avvisi, $riferimenti['avvisi'] );
	// field_data è solo una data: la news si data all'inizio di quel giorno, nel fuso del sito
	// (timezone_string in config/sito.json). Non a un'ora del giorno: con una data di oggi e un giro prima
	// di quell'ora WordPress la metterebbe «programmata» (future) e la mostrerebbe solo dopo
	$data = $d['data'] !== '' ? "{$d['data']} 00:00:00" : null;
	$r    = wpf_contenuto_scrivi(
		[
			'tipo'          => 'post',
			'cosa'          => 'news',
			'post'          => $post,
			'lingua'        => $lingua,
			'slug'          => $d['slug'] ?? wpf_import_slug( $d ),
			'parent'        => 0,
			'id_it'         => $id_it,
			'voluti'        => array_filter(
				[
					// Senza spazi in fondo: WordPress li toglie, e il titolo risulterebbe cambiato a ogni giro
					'post_title'   => trim( $d['titolo'] ),
					'post_content' => $d['body'],
					'post_status'  => $d['status'] ? 'publish' : 'draft',
					'post_excerpt' => $d['sottotitolo'],
					'post_date'    => $data,
				],
				fn( $v ) => $v !== null
			),
			'meta'          => [
				WPF_META_LINGUA       => $lingua,
				WPF_META_NID          => (string) $nid,
				WPF_META_LANGCODE     => $lingua,
				WPF_META_TESTO_DRUPAL => md5( $d['body'] ),
				WPF_META_ALIAS_DRUPAL => wpf_import_alias( $nid, $d ),
				WPF_META_IN_EVIDENZA  => $d['in_evidenza'] ? '1' : null,
				WPF_META_COLLEGAMENTI => $d['collegamenti'] ?: null,
				WPF_META_RIFERIMENTI  => $riferimenti['riferimenti'] ?: null,
			],
			'media'         => [
				'immagine' => $d['immagine'] ? [ $d['immagine'] ] : [],
				'allegato' => $d['allegati'],
				'galleria' => $d['galleria'],
			],
			'template'      => $template,
			'nome_template' => WPF_TEMPLATE_NEWS,
			'termini'       => [
				'category' => array_values( array_filter( $categorie ) ),
				'post_tag' => $tag['id'],
			],
			// Con la data GMT e edit_date WordPress tiene la data anche su una bozza: senza, wp_update_post
			// di una bozza la sostituisce con l'ora corrente (e una news pubblicata dopo uscirebbe con la
			// data del giorno)
			'scrittura'     => $data !== null ? [ 'post_date_gmt' => get_gmt_from_date( $data ), 'edit_date' => true ] : [],
		],
		$prova,
		$solo
	);
	return array_merge( $r, [ 'avvisi' => $avvisi ] );
}

