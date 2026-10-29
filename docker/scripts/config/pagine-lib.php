<?php
/**
 * Funzioni della sincronizzazione delle pagine statiche da Drupal (oc:8758), usate da pagine.php.
 *
 * Una pagina si riconosce dalla sua chiave nel perimetro (config/pagine.json) e dalla lingua:
 * _wpf_pagina e _wpf_lingua. Le pagine che vengono da Drupal portano anche il nodo d'origine
 * (_drupal_nid, _drupal_langcode), con cui si risolvono i link interni. Drupal vince sempre: a ogni
 * esecuzione la pagina prende i dati di Drupal; il design sta nei template di Impreza (us_content_id).
 */

require_once __DIR__ . '/contenuti-lib.php';
require_once __DIR__ . '/menu.php';

// Testata di sola intestazione («titolo»), come le pagine Drupal con un layout proprio senza hero (News);
// senza il meta la testata è quella con l'immagine (wpf_drupal_testata)
const WPF_META_TESTATA = '_wpf_testata';
// Colore tematico di Drupal (is-sapere, is-pathway…): il child ne ricava il colore del filo della testata
const WPF_META_COLORE = '_wpf_colore';
// Chiave in config/post.json del template di Impreza di una pagina: prefisso + «template» di config/pagine.json
const WPF_PREFISSO_TEMPLATE = 'template-pagina-';
// Pulsante di una scheda verso la pagina d'elenco completa ([ testo, verso: chiave della voce ], un array:
// in JSON update_post_meta toglierebbe le «\» degli accenti), dal campo «pulsante» di config/pagine.json
const WPF_META_PULSANTE = '_wpf_pulsante';
// Le news che una scheda o una pagina d'elenco mostra, dal campo «griglia» di config/pagine.json: il child
// le legge così, senza nomi di View nel codice (inc/news.php)
const WPF_META_GRIGLIA = '_wpf_griglia';
// Nome di una scheda nella fila delle schede, quando non è il titolo della pagina: la prima scheda di Da
// sapere è la pagina «Da sapere» ma si chiama «Come organizzare l'escursione» (oc:8760)
const WPF_META_ETICHETTA = '_wpf_etichetta';
// Più pagine di così da mettere in bozza nello stesso giro: copia Drupal sospetta, ci si ferma
const WPF_PAGINE_SOGLIA_BOZZE = 2;

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

/** Percorso relativo che una pagina del perimetro ha (o avrà) in una lingua; una figlia sta sotto il genitore. */
function wpf_pagina_percorso( array $voce, string $lingua, array $voci = [] ): string {
	$genitore = isset( $voce['genitore'] ) ? $voci[ $voce['genitore'] ]['slug'][ $lingua ] . '/' : '';
	return ( $lingua === 'it' ? '/' : WPF_PREFISSO_INGLESE ) . $genitore . $voce['slug'][ $lingua ] . '/';
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

/**
 * Crea o aggiorna una pagina del perimetro in una lingua con i dati di Drupal (wpf_contenuto_scrivi).
 *
 * @param array    $voce     la voce di config/pagine.json
 * @param string   $lingua   it | en
 * @param array    $dati     da wpf_drupal_pagina, più 'body' già con i link riscritti
 * @param int|null $id_it    la pagina italiana, per collegare l'inglese in WPML
 * @param array    $template chiave del template => id (0 se il template non c'è sul sito)
 * @param int      $parent   la pagina del genitore nella stessa lingua (0 se la voce non ne ha)
 * @param ?array   $solo     con --contenuti-mancanti: gli elementi da riscrivere ('pagina' = tutti)
 * @return array come wpf_contenuto_scrivi
 */
function wpf_pagina_scrivi( array $voce, string $lingua, array $dati, ?int $id_it, array $template, bool $prova, ?array $solo = null, int $parent = 0 ): array {
	return wpf_contenuto_scrivi(
		[
			'tipo'          => 'page',
			'cosa'          => 'pagina',
			'post'          => wpf_pagina_trova( $voce['chiave'], $lingua ),
			'lingua'        => $lingua,
			'slug'          => $voce['slug'][ $lingua ],
			'parent'        => $parent,
			'id_it'         => $id_it,
			'voluti'        => [
				'post_title'   => $dati['titolo'],
				'post_content' => $dati['body'],
				'post_status'  => $dati['status'] ? 'publish' : 'draft',
				// La «Descrizione testata» di Drupal va nel Riassunto nativo: WordPress, WPML e Impreza lo conoscono
				'post_excerpt' => $dati['sottotitolo'],
			],
			// Meta: null = cancellare, valore = scrivere; i campi media li aggiunge wpf_contenuto_scrivi
			'meta'          => [
				WPF_META_PAGINA       => $voce['chiave'],
				WPF_META_LINGUA       => $lingua,
				WPF_META_NID          => $voce['nid'] ? (string) $voce['nid'] : null,
				WPF_META_LANGCODE     => $voce['nid'] ? $lingua : null,
				WPF_META_TESTO_DRUPAL => md5( $dati['body'] ),
				WPF_META_TESTATA      => ( $dati['testata'] ?? 'hero' ) === 'titolo' ? 'titolo' : null,
				WPF_META_COLORE       => ( $dati['colore'] ?? '' ) !== '' ? $dati['colore'] : null,
				WPF_META_SCHEDA       => isset( $voce['scheda'] ) ? (string) $voce['scheda'] : null,
				WPF_META_PULSANTE     => isset( $voce['pulsante'] ) ? [ 'testo' => $voce['pulsante'][ $lingua ], 'verso' => $voce['pulsante']['verso'] ] : null,
				WPF_META_GRIGLIA      => isset( $voce['griglia'] ) ? $voce['griglia'] : null,
				WPF_META_ETICHETTA    => isset( $voce['etichetta'] ) ? $voce['etichetta'][ $lingua ] : null,
				WPF_META_ALIAS_DRUPAL => ( $dati['alias_drupal'] ?? [] ) ?: null,
			],
			'media'         => [
				'immagine' => $dati['immagine'] ? [ $dati['immagine'] ] : [],
				'allegato' => $dati['allegati'],
				'galleria' => $dati['galleria'],
			],
			'template'      => $template[ $voce['template'] ] ?? 0,
			'nome_template' => WPF_PREFISSO_TEMPLATE . $voce['template'],
		],
		$prova,
		$solo
	);
}

/**
 * Vecchi indirizzi di Drupal di una pagina in una lingua (WPF_META_ALIAS_DRUPAL): gli alias dei nodi nid e
 * «sostituisce» della voce in quella lingua, e /node/<nid>, in ordine alfabetico.
 *
 * @param array $alias da wpf_drupal_alias
 */
function wpf_pagina_alias_drupal( array $voce, string $lingua, array $alias ): array {
	$nodi = array_map( 'intval', array_filter( [ $voce['nid'] ?? null, ...( $voce['sostituisce'] ?? [] ) ] ) );
	$percorsi = array_map( fn( $nid ) => "node/{$nid}", $nodi );
	foreach ( $alias as $percorso => $nodo ) {
		if ( $nodo['lingua'] === $lingua && in_array( $nodo['nid'], $nodi, true ) ) {
			$percorso   = $lingua === 'it' ? (string) $percorso : substr( (string) $percorso, strlen( "/{$lingua}" ) );
			$percorsi[] = ltrim( wpf_drupal_normalizza_alias( $percorso ), '/' );
		}
	}
	$percorsi = array_values( array_unique( $percorsi ) );
	sort( $percorsi );
	return $percorsi;
}

/** Voci di menu che portano a una pagina, con il loro menu: [ «menu: voce» ]. */
function wpf_pagina_voci_menu( int $id ): array {
	$voci = [];
	foreach ( get_posts( [ 'post_type' => 'nav_menu_item', 'numberposts' => -1, 'suppress_filters' => true, 'meta_key' => '_menu_item_object_id', 'meta_value' => (string) $id ] ) as $voce ) {
		// Con i filtri di WPML attivi i menu tradotti non si vedono (CLAUDE.md, «Trappole»)
		$menu   = wpf_wpml_tutte_le_lingue( fn() => wp_get_post_terms( $voce->ID, 'nav_menu', [ 'fields' => 'names' ] ) );
		$voci[] = ( $menu[0] ?? '?' ) . ': ' . ( $voce->post_title ?: get_the_title( $id ) );
	}
	return $voci;
}
