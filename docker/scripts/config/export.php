<?php
/**
 * Export della configurazione del sito in file JSON (oc:8717).
 *
 * Uso: WPF_EXPORT_DIR=<cartella> [WPF_SOLA_LETTURA=1] wp eval-file /usr/local/lib/wp-forestas/export.php
 * Scrive impreza.json, child.json, sito.json, post.json, menu.json, versioni.json e, se WPML è attivo,
 * wpml.json. Si ferma senza scrivere se un valore ha la forma di una chiave (WPF_FORME_SEGRETE), tranne
 * in sola lettura (backup dell'apply, fuori da git).
 * Assegna la chiave stabile (_wp_forestas_chiave) ai post e ai menu esportati che non l'hanno ancora:
 * è l'unica scrittura sul sito. Con WPF_SOLA_LETTURA=1 (backup di apply --conferma) non scrive nulla,
 * così il backup non cambia ciò che l'apply ricollegherà subito dopo.
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';

$dir = (string) getenv( 'WPF_EXPORT_DIR' );
if ( $dir === '' ) {
	WP_CLI::error( 'manca WPF_EXPORT_DIR: lancia l\'export con bin/wordpress-config.sh export' );
}
if ( ! is_dir( $dir ) && ! mkdir( $dir, 0775, true ) ) {
	WP_CLI::error( "impossibile creare {$dir}" );
}
if ( ! defined( 'US_THEMENAME' ) ) {
	WP_CLI::error( 'Impreza non è il tema attivo (né il padre del tema attivo): niente da esportare' );
}
$scrivi         = getenv( 'WPF_SOLA_LETTURA' ) !== '1';
$assegna_chiave = function ( int $id, string $chiave ) use ( $scrivi ) {
	if ( $scrivi ) {
		update_post_meta( $id, WPF_META_CHIAVE, $chiave );
	}
};

$lingua_predefinita = apply_filters( 'wpml_default_language', null );
$tolti              = [];
$avvisi             = [];

// --- Quali post sono configurazione: quelli del builder e le pagine a cui puntano le opzioni -----

$impreza = (array) get_option( 'usof_options_' . US_THEMENAME, [] );
$sito    = [];
foreach ( WPF_OPZIONI_SITO as $nome ) {
	$sito[ $nome ] = get_option( $nome );
}

$originali = get_posts(
	[
		'post_type'        => WPF_TIPI_BUILDER,
		'post_status'      => [ 'publish', 'draft', 'private' ],
		'numberposts'      => -1,
		'suppress_filters' => true,
		'fields'           => 'ids',
	]
);
foreach ( $impreza as $nome => $valore ) {
	if ( wpf_opzione_riferimento( (string) $nome, $valore ) ) {
		$originali[] = (int) $valore;
	}
}
foreach ( WPF_OPZIONI_SITO_PAGINA as $nome ) {
	if ( (int) $sito[ $nome ] > 0 ) {
		$originali[] = (int) $sito[ $nome ];
	}
}

// --- Chiavi stabili, traduzioni comprese ---------------------------------------------------------

$chiavi = []; // id => chiave
foreach ( array_unique( $originali ) as $id ) {
	$post = get_post( $id );
	if ( ! $post ) {
		$avvisi[] = "riferimento a un post che non esiste: id {$id}";
		continue;
	}
	$tipo_wpml = 'post_' . $post->post_type;
	$tradotti  = wpf_traduzioni( $id, $tipo_wpml );
	// Se l'id indicato è una traduzione, si parte dall'originale nella lingua predefinita
	if ( $lingua_predefinita && isset( $tradotti[ $lingua_predefinita ] ) ) {
		$id   = $tradotti[ $lingua_predefinita ];
		$post = get_post( $id );
	}
	if ( isset( $chiavi[ $id ] ) ) {
		continue; // già raggiunto da un altro riferimento (per esempio dalla sua traduzione)
	}
	$derivata = $post->post_type . '-' . $post->post_name;
	$chiave   = get_post_meta( $id, WPF_META_CHIAVE, true ) ?: $derivata;
	// «Duplica» di Impreza copia tutti i metadati, chiave compresa: la chiave resta al post più vecchio
	// che la porta, il duplicato ne riceve una sua (dal suo slug, o con l'id se anche quella è presa)
	$titolare = wpf_post_per_chiave( $chiave );
	if ( ( $titolare && $titolare->ID !== $id ) || in_array( $chiave, $chiavi, true ) ) {
		$altro    = wpf_post_per_chiave( $derivata );
		$nuova    = ( $derivata !== $chiave && ( ! $altro || $altro->ID === $id ) && ! in_array( $derivata, $chiavi, true ) )
			? $derivata : "{$derivata}-{$id}";
		$avvisi[] = "il post {$id} («{$post->post_title}») aveva la chiave «{$chiave}» di un altro post (copiata da un duplicato): ora è «{$nuova}»";
		$chiave   = $nuova;
	}
	$assegna_chiave( $id, $chiave );
	$chiavi[ $id ] = $chiave;
	foreach ( $tradotti as $lingua => $id_tradotto ) {
		if ( $id_tradotto === $id ) {
			continue;
		}
		$chiave_tradotta = $chiave . '-' . $lingua;
		$assegna_chiave( $id_tradotto, $chiave_tradotta );
		$chiavi[ $id_tradotto ] = $chiave_tradotta;
	}
}

$riferimento = fn( $id ) => isset( $chiavi[ (int) $id ] ) ? WPF_RIFERIMENTO . $chiavi[ (int) $id ] : null;

// --- post.json ----------------------------------------------------------------------------------

$post_json = [];
foreach ( $chiavi as $id => $chiave ) {
	$post      = get_post( $id );
	$tipo_wpml = 'post_' . $post->post_type;
	$meta      = [];
	foreach ( get_post_meta( $id ) as $nome => $valori ) {
		if ( $nome === WPF_META_CHIAVE || in_array( $nome, WPF_META_CALCOLATI, true ) || preg_match( WPF_META_ESCLUSI_POST, $nome ) ) {
			continue;
		}
		if ( in_array( $nome, WPF_META_ALLEGATI, true ) ) {
			$avvisi[] = "post {$chiave}: {$nome} è l'allegato {$valori[0]} della Libreria media, che non sta in config/: su un altro sito va riassegnato dal pannello";
			continue;
		}
		$meta[ $nome ] = maybe_unserialize( $valori[0] );
	}
	$meta   = wpf_togli_segreti( $meta, $tolti );
	$lingua = wpf_lingua( $id, $tipo_wpml );
	if ( preg_match( WPF_ATTRIBUTI_ALLEGATO, $post->post_content ) ) {
		$avvisi[] = "post {$chiave}: il contenuto usa immagini della Libreria media, che non stanno in config/: su un altro sito vanno ricaricate e riassegnate dal pannello";
	}
	$originale = null;
	if ( $lingua && $lingua !== $lingua_predefinita ) {
		$id_originale = wpf_traduzioni( $id, $tipo_wpml )[ $lingua_predefinita ] ?? null;
		$originale    = $id_originale ? ( $chiavi[ $id_originale ] ?? null ) : null;
	}
	$post_json[] = [
		'chiave'       => $chiave,
		'post_type'    => $post->post_type,
		'post_title'   => $post->post_title,
		'post_name'    => $post->post_name,
		'post_status'  => $post->post_status,
		'post_content' => $post->post_content,
		'post_excerpt' => $post->post_excerpt,
		'menu_order'   => $post->menu_order,
		'meta'         => $meta,
		'lingua'       => $lingua,
		'originale'    => $originale,
	];
}

// --- impreza.json: Theme Options con i riferimenti al posto degli id ------------------------------

foreach ( $impreza as $nome => $valore ) {
	if ( wpf_opzione_riferimento( (string) $nome, $valore ) ) {
		$impreza[ $nome ] = $riferimento( $valore ) ?? $valore;
	} elseif ( wpf_opzione_allegato( (string) $nome, $valore ) ) {
		$avvisi[] = "impreza {$nome}: è l'allegato {$valore} della Libreria media, che non sta in config/: su un altro sito va ricaricato dal pannello (l'apply tiene il valore di quel sito)";
	}
}
foreach ( WPF_IMPREZA_STATO as $nome ) {
	unset( $impreza[ $nome ] );
}
$impreza = wpf_togli_segreti( $impreza, $tolti );

// --- sito.json ----------------------------------------------------------------------------------

foreach ( WPF_OPZIONI_SITO_PAGINA as $nome ) {
	$sito[ $nome ] = (int) $sito[ $nome ] > 0 ? $riferimento( $sito[ $nome ] ) : 0;
}

// --- child.json: theme_mods del tema attivo, senza ciò che ha un file o un export proprio ----------

$child = (array) get_option( 'theme_mods_' . get_stylesheet(), [] );
unset( $child['nav_menu_locations'], $child['custom_css_post_id'], $child['sidebars_widgets'] );
$child = wpf_togli_segreti( $child, $tolti );

// --- menu.json ----------------------------------------------------------------------------------

// Chiave stabile dei menu, come per i post: «menu-<slug>» al primo export, «-<lingua>» per le traduzioni
$chiave_menu = function ( WP_Term $termine, string $predefinita ) use ( $scrivi ): string {
	$chiave = get_term_meta( $termine->term_id, WPF_META_CHIAVE, true ) ?: $predefinita;
	if ( $scrivi ) {
		update_term_meta( $termine->term_id, WPF_META_CHIAVE, $chiave );
	}
	return $chiave;
};
$menu        = [];
$chiavi_menu = []; // term_id => chiave
$termini     = wp_get_nav_menus();
// Prima gli originali: la chiave di una traduzione deriva da quella del suo originale
usort( $termini, fn( $a, $b ) => (int) ( wpf_lingua( $a->term_taxonomy_id, 'tax_nav_menu' ) !== $lingua_predefinita ) <=> (int) ( wpf_lingua( $b->term_taxonomy_id, 'tax_nav_menu' ) !== $lingua_predefinita ) );
foreach ( $termini as $termine ) {
	// Per i termini WPML usa il term_taxonomy_id, non il term_id
	$lingua    = wpf_lingua( $termine->term_taxonomy_id, 'tax_nav_menu' );
	$originale = null;
	if ( $lingua && $lingua !== $lingua_predefinita ) {
		$tt_originale = wpf_traduzioni( $termine->term_taxonomy_id, 'tax_nav_menu' )[ $lingua_predefinita ] ?? null;
		$t_originale  = $tt_originale ? get_term_by( 'term_taxonomy_id', $tt_originale, 'nav_menu' ) : null;
		$originale    = $t_originale ? ( $chiavi_menu[ $t_originale->term_id ] ?? null ) : null;
	}
	$chiave                           = $chiave_menu( $termine, $originale ? "{$originale}-{$lingua}" : 'menu-' . $termine->slug );
	$chiavi_menu[ $termine->term_id ] = $chiave;
	$voci                             = wpf_togli_segreti( wpf_voci_menu( $termine->term_id ), $tolti );
	$menu[]                           = [ 'chiave' => $chiave, 'slug' => $termine->slug, 'nome' => $termine->name, 'lingua' => $lingua, 'originale' => $originale, 'voci' => $voci ];
}
$posizioni = [];
foreach ( get_nav_menu_locations() as $posizione => $id_menu ) {
	if ( isset( $chiavi_menu[ $id_menu ] ) ) {
		$posizioni[ $posizione ] = $chiavi_menu[ $id_menu ];
	}
}

// --- wpml.json ----------------------------------------------------------------------------------

$wpml = null;
global $sitepress;
if ( $sitepress && ! class_exists( '\WPML\LanguageEditor\PageData' ) ) {
	// WPML attivo ma con una struttura che wpml.php non conosce: meglio nessun wpml.json che uno sbagliato
	WP_CLI::warning( 'WPML è attivo ma questa versione non ha \WPML\LanguageEditor\PageData: wpml.json non esportato, va adeguato wpml.php' );
} elseif ( $sitepress ) {
	$impostazioni = $sitepress->get_settings();
	unset( $impostazioni['site_key'] );
	foreach ( WPF_WPML_INTERNE as [ $gruppo, $nome ] ) {
		unset( $impostazioni[ $gruppo ][ $nome ] );
	}
	$wpml = [
		'lingue'       => \WPML\LanguageEditor\PageData::active(),
		'predefinita'  => $sitepress->get_default_language(),
		'impostazioni' => wpf_togli_segreti( wpf_togli_stato_wpml( $impostazioni ), $tolti ),
		'setup'        => get_option( 'WPML(setup)' ),
		'stringhe'     => wpf_wpml_stringhe_esporta(),
	];
}

// --- Scrittura --------------------------------------------------------------------------------

$file = [
	'impreza.json'  => $impreza,
	'child.json'    => $child,
	'sito.json'     => $sito,
	'post.json'     => $post_json,
	'menu.json'     => [ 'menu' => $menu, 'posizioni' => $posizioni ],
	'versioni.json' => wpf_versioni(),
];
if ( $wpml ) {
	$file['wpml.json'] = $wpml;
}
$testi = array_map( fn( $dati ) => wpf_json( wpf_url_in_segnaposto( $dati ) ), $file );

// Ultimo controllo prima di scrivere: un valore con la forma di una chiave, sotto un nome qualsiasi
// (anche dentro il contenuto di un post), non deve arrivare nel repo pubblico. Il valore non si stampa.
// Il backup dell'apply (sola lettura) va in backup/, esclusa da git: lì il controllo impedirebbe solo
// l'apply e il ripristino.
$trovati = [];
foreach ( $scrivi ? $testi : [] as $nome => $testo ) {
	foreach ( WPF_FORME_SEGRETE as $tipo => $forma ) {
		if ( preg_match( $forma, $testo ) ) {
			$trovati[] = "{$tipo} in {$nome}";
		}
	}
}
if ( $trovati ) {
	WP_CLI::error( 'export fermato, nessun file scritto: ' . implode( ', ', $trovati ) . '. Togli la chiave dal sito (va nel .env) e rifai l\'export' );
}
array_map( 'unlink', glob( $dir . '/*.json' ) );
foreach ( $testi as $nome => $testo ) {
	file_put_contents( "{$dir}/{$nome}", $testo );
}

WP_CLI::log( 'esportati: ' . implode( ', ', array_keys( $file ) ) . ' (' . count( $post_json ) . ' post, ' . count( $menu ) . ' menu)' );
foreach ( $tolti as $nome ) {
	WP_CLI::log( "escluso: {$nome} (segreto: se serve va nel .env)" );
}
foreach ( $avvisi as $avviso ) {
	WP_CLI::warning( $avviso );
}
