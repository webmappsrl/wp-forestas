<?php
/**
 * Funzioni della sincronizzazione delle pagine statiche da Drupal (oc:8758), usate da pagine.php.
 *
 * Una pagina si riconosce dalla sua chiave nel perimetro (config/pagine.json) e dalla lingua:
 * _wpf_pagina e _wpf_lingua. Le pagine che vengono da Drupal portano anche il nodo d'origine
 * (_drupal_nid, _drupal_langcode), con cui si risolvono i link interni. Drupal vince sempre: a ogni
 * esecuzione la pagina prende i dati di Drupal; il design sta nei template di Impreza (us_content_id).
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/drupal.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/link.php';

const WPF_META_PAGINA      = '_wpf_pagina';
const WPF_META_LINGUA      = '_wpf_lingua';
const WPF_META_NID         = '_drupal_nid';
const WPF_META_LANGCODE    = '_drupal_langcode';
// Meta del sottotitolo nella prima versione: ora il sottotitolo è il Riassunto nativo (post_excerpt) e il
// meta si cancella dalle pagine che lo hanno ancora (oc:8758)
const WPF_META_SOTTOTITOLO = 'wpf_sottotitolo';
// Impronta (md5) del testo scritto dalla sincronizzazione: finché il testo della pagina è quello, il child
// lo mostra così com'è, senza i filtri di the_content (inc/pagine-statiche.php)
const WPF_META_TESTO_DRUPAL = '_wpf_testo_drupal';
// Testata di sola intestazione («titolo»), come le pagine Drupal con un layout proprio senza hero (News);
// senza il meta la testata è quella con l'immagine (wpf_drupal_testata)
const WPF_META_TESTATA = '_wpf_testata';
// Colore tematico di Drupal (is-sapere, is-pathway…): il child ne ricava il colore del filo della testata
const WPF_META_COLORE = '_wpf_colore';
const WPF_META_PAGINA_ALLEGATI    = 'wpf_allegati';
const WPF_META_PAGINA_GALLERIA    = 'wpf_galleria';
// Elementi non riusciti, da ritentare con --contenuti-mancanti
const WPF_OPZIONE_MANCANTI = 'wpf_sync_mancanti';
// Più pagine di così da mettere in bozza nello stesso giro: copia Drupal sospetta, ci si ferma
const WPF_PAGINE_SOGLIA_BOZZE = 2;
// Lingua di WPML per una lingua di Drupal (i menu inglesi del sito sono en-us)
const WPF_LINGUE_WPML = [ 'it' => 'it', 'en' => 'en-us' ];

/** Pagina del perimetro in una lingua (it, en), in qualunque stato tranne il cestino. */
function wpf_pagina_trova( string $chiave, string $lingua ): ?WP_Post {
	global $wpdb;
	$id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} k ON k.post_id = p.ID AND k.meta_key = %s AND k.meta_value = %s
			 JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = %s AND l.meta_value = %s
			 WHERE p.post_type = 'page' AND p.post_status NOT IN ('trash', 'auto-draft') ORDER BY p.ID LIMIT 1",
			WPF_META_PAGINA,
			$chiave,
			WPF_META_LINGUA,
			$lingua
		)
	);
	return $id ? get_post( (int) $id ) : null;
}

/** Contenuto WordPress (di qualunque tipo) che porta un nodo Drupal in una lingua, o 0. */
function wpf_contenuto_per_nodo( int $nid, string $lingua ): int {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = %s AND n.meta_value = %s
			 JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = %s AND l.meta_value = %s
			 WHERE p.post_status = 'publish' ORDER BY p.ID LIMIT 1",
			WPF_META_NID,
			(string) $nid,
			WPF_META_LANGCODE,
			$lingua
		)
	);
}

/** Percorso relativo che una pagina del perimetro ha (o avrà) in una lingua. */
function wpf_pagina_percorso( array $voce, string $lingua ): string {
	return ( $lingua === 'it' ? '/' : '/en/' ) . $voce['slug'][ $lingua ] . '/';
}

/*
 * Tag e attributi che il formato «filtered_html» di Drupal lascia passare (filtro filter_html, letto dalla
 * configurazione filter.format.filtered_html della copia): il resto Drupal lo toglie dalla pagina, il testo
 * dentro resta.
 */
const WPF_DRUPAL_FILTERED_HTML = [
	'br' => [], 'p' => [], 'h1' => [], 'h2' => [ 'id' => true ], 'h3' => [ 'id' => true ], 'h4' => [ 'id' => true ],
	'h5' => [ 'id' => true ], 'h6' => [ 'id' => true ], 'cite' => [], 'dl' => [], 'dt' => [], 'dd' => [], 'b' => [],
	'a' => [ 'hreflang' => true, 'href' => true ], 'blockquote' => [ 'cite' => true ], 'ul' => [ 'type' => true ],
	'ol' => [ 'type' => true, 'start' => true ], 'strong' => [], 'em' => [], 'code' => [], 'li' => [], 'table' => [],
	'tr' => [], 'td' => [ 'rowspan' => true, 'colspan' => true ], 'th' => [ 'rowspan' => true, 'colspan' => true ],
	'thead' => [], 'tbody' => [], 'tfoot' => [], 'caption' => [],
];

/**
 * Indirizzi e email scritti nel testo trasformati in link, come filter_url di Drupal: solo fuori dai
 * link. make_clickable da solo trasformava anche il testo di un link che è un indirizzo
 * («<a href="…">https://…</a>»), creando un link dentro l'altro: il browser lascia vuoto il primo.
 */
function wpf_pagina_link_dagli_indirizzi( string $html ): string {
	$parti = preg_split( '~(<a\b[^>]*>.*?</a>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parti as $i => $parte ) {
		if ( $i % 2 === 0 ) {
			$parti[ $i ] = make_clickable( $parte );
		}
	}
	return implode( '', $parti );
}

/**
 * Pulizia del body di Drupal. Un body in «filtered_html» diventa quello che Drupal mostra: solo i tag e gli
 * attributi ammessi (filter_html) e gli indirizzi scritti nel testo trasformati in link (filter_url). Negli
 * altri formati (la mappa è in «full_html») si tolgono solo gli stili in linea e gli attributi data-entity-*.
 */
function wpf_pagina_pulisci( string $html, string $formato = '' ): string {
	if ( $formato === 'filtered_html' ) {
		$html = wpf_pagina_link_dagli_indirizzi( wp_kses( $html, WPF_DRUPAL_FILTERED_HTML ) );
	} else {
		$html = preg_replace( '#\s(?:style|data-entity-[a-z-]+)\s*=\s*(["\']).*?\1#is', '', $html );
	}
	return trim( str_replace( "\r\n", "\n", $html ) );
}

/*
 * Traduzioni nostre delle pagine che Drupal ha solo in italiano (oc:8758): config/traduzioni/<lingua>/
 * <chiave>.json (titolo, sottotitolo, impronta del testo italiano tradotto) e <chiave>.html (testo). Si
 * usano solo finché Drupal non ha una sua traduzione, che vince.
 */
const WPF_DIR_TRADUZIONI = WPF_DIR_CONFIG . '/traduzioni';

/** Impronta del testo italiano di una pagina Drupal: cambia se cambiano titolo, sottotitolo o testo. */
function wpf_traduzione_impronta( array $it ): string {
	return md5( $it['titolo'] . "\n" . $it['sottotitolo'] . "\n" . $it['body'] );
}

/** La traduzione di una pagina in una lingua, o null se non c'è: [ titolo, sottotitolo, sorgente, body ]. */
function wpf_traduzione_leggi( string $chiave, string $lingua ): ?array {
	$json = WPF_DIR_TRADUZIONI . "/{$lingua}/{$chiave}.json";
	if ( ! is_readable( $json ) ) {
		return null;
	}
	$t = json_decode( (string) file_get_contents( $json ), true );
	if ( ! is_array( $t ) || ! is_string( $t['titolo'] ?? null ) || $t['titolo'] === '' ) {
		throw new RuntimeException( "traduzione non valida: {$json} (serve almeno «titolo»)" );
	}
	$html      = WPF_DIR_TRADUZIONI . "/{$lingua}/{$chiave}.html";
	$t['body'] = is_readable( $html ) ? (string) file_get_contents( $html ) : '';
	return $t;
}

/** Elenco dei mancanti: [ chiave => [ cosa, pagina, lingua, dettaglio, causa, primo, ultimo ] ]. */
function wpf_mancanti_leggi(): array {
	$elenco = get_option( WPF_OPZIONE_MANCANTI, [] );
	return is_array( $elenco ) ? $elenco : [];
}

function wpf_mancanti_salva( array $elenco ): void {
	if ( $elenco ) {
		update_option( WPF_OPZIONE_MANCANTI, $elenco, false );
	} else {
		delete_option( WPF_OPZIONE_MANCANTI );
	}
}

/** Chiave di un elemento mancante: cosa|pagina|lingua|dettaglio. */
function wpf_mancante_chiave( string $cosa, string $pagina, string $lingua, string $dettaglio = '' ): string {
	return "{$cosa}|{$pagina}|{$lingua}|{$dettaglio}";
}

/**
 * Media di un campo: id degli allegati e file non riusciti. Un file che non si scarica non nasconde gli
 * altri: allegati e galleria mostrano quelli scaricati, e il mancante si aggiunge quando arriva. Per
 * l'immagine in evidenza, che è una sola, il campo tiene invece il valore che aveva: un errore di rete
 * non toglie un'immagine dal sito (oc:8758).
 *
 * @return array [ 'ids' => int[]|null (null = tenere il valore attuale), 'scaricare' => string[], 'errori' => [ nome => causa ] ]
 */
function wpf_pagina_media( array $file, bool $prova ): array {
	$ids       = [];
	$scaricare = [];
	$errori    = [];
	foreach ( $file as $f ) {
		$id = wpf_media_importa( $f, $prova );
		if ( is_wp_error( $id ) ) {
			$errori[ "fid {$f['fid']} {$f['nome']}" ] = $id->get_error_message();
		} elseif ( $id === 0 ) {
			$scaricare[] = $f['nome'];
		} else {
			$ids[] = $id;
		}
	}
	return [ 'ids' => $scaricare ? null : $ids, 'scaricare' => $scaricare, 'errori' => $errori ];
}

/**
 * Crea o aggiorna una pagina del perimetro in una lingua con i dati di Drupal.
 *
 * @param array    $voce     la voce di config/pagine.json
 * @param string   $lingua   it | en
 * @param array    $dati     da wpf_drupal_pagina, più 'body' già con i link riscritti
 * @param int|null $id_it    la pagina italiana, per collegare l'inglese in WPML
 * @param array    $template chiave del template => id (0 se il template non c'è sul sito)
 * @param ?array   $solo     con --contenuti-mancanti: gli elementi da riscrivere ('pagina' = tutti)
 * @return array [ 'id' => ?int, 'esito' => creata|aggiornata|invariata, 'campi' => string[],
 *                 'mancanti' => [ cosa => [ dettaglio => causa ] ], 'media' => string[] ]
 */
function wpf_pagina_scrivi( array $voce, string $lingua, array $dati, ?int $id_it, array $template, bool $prova, ?array $solo = null ): array {
	$chiave   = $voce['chiave'];
	$post     = wpf_pagina_trova( $chiave, $lingua );
	$tutto    = $solo === null || in_array( 'pagina', $solo, true );
	$fa       = fn( string $cosa ) => $tutto || in_array( $cosa, $solo, true );
	$mancanti = [];
	$media    = [];
	$campi    = [];

	// Campi del post
	$voluti = [
		'post_title'   => $dati['titolo'],
		'post_name'    => $voce['slug'][ $lingua ],
		'post_content' => $dati['body'],
		'post_status'  => $dati['status'] ? 'publish' : 'draft',
		// La «Descrizione testata» di Drupal va nel Riassunto nativo: WordPress, WPML e Impreza lo conoscono
		'post_excerpt' => $dati['sottotitolo'],
	];
	// Meta: null = cancellare, valore = scrivere; i campi media si aggiungono sotto
	$meta = [
		WPF_META_PAGINA      => $chiave,
		WPF_META_LINGUA      => $lingua,
		WPF_META_NID         => $voce['nid'] ? (string) $voce['nid'] : null,
		WPF_META_LANGCODE    => $voce['nid'] ? $lingua : null,
		WPF_META_SOTTOTITOLO => null,
		WPF_META_TESTO_DRUPAL => md5( $dati['body'] ),
		WPF_META_TESTATA      => ( $dati['testata'] ?? 'hero' ) === 'titolo' ? 'titolo' : null,
		WPF_META_COLORE       => ( $dati['colore'] ?? '' ) !== '' ? $dati['colore'] : null,
	];
	$id_template = $template[ $voce['template'] ] ?? 0;
	if ( $id_template ) {
		$meta['us_content_id'] = (string) $id_template;
	} elseif ( $fa( 'template' ) ) {
		$mancanti['template'][ $voce['template'] ] = "template «template-pagina-{$voce['template']}» non presente sul sito (config/post.json)";
	}

	$campi_media = [
		'immagine' => [ '_thumbnail_id', $dati['immagine'] ? [ $dati['immagine'] ] : [] ],
		'allegato' => [ WPF_META_PAGINA_ALLEGATI, $dati['allegati'] ],
		'galleria' => [ WPF_META_PAGINA_GALLERIA, $dati['galleria'] ],
	];
	foreach ( $campi_media as $cosa => [ $chiave_meta, $file ] ) {
		if ( ! $fa( $cosa ) ) {
			continue;
		}
		$r = wpf_pagina_media( $file, $prova );
		foreach ( $r['scaricare'] as $nome ) {
			$media[] = "{$cosa} da scaricare: {$nome}";
		}
		foreach ( $r['errori'] as $nome => $causa ) {
			$mancanti[ $cosa ][ $nome ] = $causa;
		}
		if ( $cosa === 'immagine' && $r['errori'] ) {
			// L'immagine in evidenza non scaricata: resta quella che c'era
		} elseif ( $r['ids'] !== null ) {
			$meta[ $chiave_meta ] = $cosa === 'immagine' ? ( $r['ids'] ? (string) $r['ids'][0] : null ) : ( $r['ids'] ?: null );
		} elseif ( $r['scaricare'] ) {
			$campi[] = $chiave_meta; // in anteprima: cambierà quando il file sarà scaricato
		}
	}

	if ( ! $tutto ) {
		// Con --contenuti-mancanti si riscrivono solo i meta degli elementi elencati
		$voluti = [];
		$meta   = array_intersect_key( $meta, array_flip( array_filter( [ '_thumbnail_id', WPF_META_PAGINA_ALLEGATI, WPF_META_PAGINA_GALLERIA, 'us_content_id' ], fn( $k ) => array_key_exists( $k, $meta ) ) ) );
	}

	// Differenze
	if ( $post ) {
		foreach ( $voluti as $campo => $valore ) {
			if ( (string) $post->$campo !== (string) $valore ) {
				$campi[] = $campo;
			}
		}
		foreach ( $meta as $chiave_meta => $valore ) {
			$attuale = get_post_meta( $post->ID, $chiave_meta, true );
			if ( $valore === null ? metadata_exists( 'post', $post->ID, $chiave_meta ) : wpf_diverso( $attuale, $valore ) ) {
				$campi[] = $chiave_meta;
			}
		}
	} else {
		$campi = array_merge( array_keys( $voluti ), array_keys( array_filter( $meta, fn( $v ) => $v !== null ) ), $campi );
	}
	$campi = array_values( array_unique( $campi ) );
	$esito = ! $post ? 'creata' : ( $campi ? 'aggiornata' : 'invariata' );

	if ( $prova || ( $post && ! $campi ) ) {
		return [ 'id' => $post->ID ?? null, 'esito' => $esito, 'campi' => $campi, 'mancanti' => $mancanti, 'media' => $media ];
	}

	// Scrittura
	$lingua_wpml = WPF_LINGUE_WPML[ $lingua ];
	if ( ! $post ) {
		$id = wp_insert_post( wp_slash( array_merge( $voluti, [ 'post_type' => 'page', 'post_parent' => 0 ] ) ), true );
		if ( is_wp_error( $id ) ) {
			$mancanti['pagina'][''] = $id->get_error_message();
			return [ 'id' => null, 'esito' => 'non creata', 'campi' => $campi, 'mancanti' => $mancanti, 'media' => $media ];
		}
	} else {
		$id = $post->ID;
		if ( $voluti ) {
			$r = wp_update_post( wp_slash( array_merge( $voluti, [ 'ID' => $id ] ) ), true );
			if ( is_wp_error( $r ) ) {
				$mancanti['pagina'][''] = $r->get_error_message();
				return [ 'id' => $id, 'esito' => 'non aggiornata', 'campi' => $campi, 'mancanti' => $mancanti, 'media' => $media ];
			}
		}
	}
	foreach ( $meta as $chiave_meta => $valore ) {
		if ( $valore === null ) {
			delete_post_meta( $id, $chiave_meta );
		} else {
			update_post_meta( $id, $chiave_meta, $valore );
		}
	}
	if ( $tutto ) {
		// Lingua e gruppo di traduzione; lo slug si riscrive dopo, perché WPML ammetta nella traduzione
		// lo stesso slug dell'originale (sapendo la lingua non lo considera un doppione)
		wpf_wpml_collega( $id, 'post_page', $lingua_wpml, $lingua === 'it' ? null : $id_it );
		if ( $lingua !== 'it' && $id_it === null ) {
			$mancanti['traduzione'][''] = 'pagina italiana assente: traduzione non collegata in WPML';
		}
		if ( get_post_field( 'post_name', $id ) !== $voluti['post_name'] ) {
			wp_update_post( [ 'ID' => $id, 'post_name' => $voluti['post_name'] ] );
			if ( get_post_field( 'post_name', $id ) !== $voluti['post_name'] ) {
				$mancanti['pagina']['slug'] = 'WordPress ha cambiato lo slug in «' . get_post_field( 'post_name', $id ) . "» invece di «{$voluti['post_name']}»";
			}
		}
	}
	return [ 'id' => $id, 'esito' => $esito, 'campi' => $campi, 'mancanti' => $mancanti, 'media' => $media ];
}

/** Voci di menu che portano a una pagina, con il loro menu: [ «menu: voce» ]. */
function wpf_pagina_voci_menu( int $id ): array {
	$voci = [];
	foreach ( get_posts( [ 'post_type' => 'nav_menu_item', 'numberposts' => -1, 'suppress_filters' => true, 'meta_key' => '_menu_item_object_id', 'meta_value' => (string) $id ] ) as $voce ) {
		$menu   = wp_get_post_terms( $voce->ID, 'nav_menu', [ 'fields' => 'names' ] );
		$voci[] = ( $menu[0] ?? '?' ) . ': ' . ( $voce->post_title ?: get_the_title( $id ) );
	}
	return $voci;
}
