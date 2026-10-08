<?php
/**
 * Ritratto del sito: righe «voce: valore» ordinate, da confrontare prima e dopo un reset (oc:8717).
 * Gli id cambiano da un sito all'altro, quindi i riferimenti si stampano con la chiave stabile del post.
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

$righe  = [];
$chiave = fn( $id ) => $id ? ( get_post_meta( (int) $id, WPF_META_CHIAVE, true ) ?: 'post senza chiave (' . get_post_type( (int) $id ) . ')' ) : '-';
// Versione e stato di un plugin cercato dalla sua cartella, come negli zip di docker/plugins/
$plugin = function ( string $cartella ): string {
	foreach ( get_plugins() as $file => $dati ) {
		if ( dirname( $file ) === $cartella ) {
			return $dati['Version'] . ( is_plugin_active( $file ) ? '' : ' (non attivo)' );
		}
	}
	return 'assente';
};
$commerciali = wpf_plugin_commerciali();

global $wp_version;
$righe[] = 'core: ' . implode( '.', array_slice( explode( '.', $wp_version ), 0, 2 ) );
$righe[] = 'tema attivo: ' . get_stylesheet();
$righe[] = 'versione Impreza: ' . ( wpf_versione_tema( 'Impreza' ) ?? 'assente' );
$righe[] = 'versione forestas-child: ' . ( wpf_versione_tema( 'forestas-child' ) ?? 'assente' );
foreach ( array_merge( [ 'us-core', 'wm-package' ], $commerciali ) as $cartella ) {
	$righe[] = "versione {$cartella}: " . $plugin( $cartella );
}

$impreza = (array) get_option( 'usof_options_' . ( defined( 'US_THEMENAME' ) ? US_THEMENAME : 'Impreza' ), [] );
foreach ( [ 'header_id', 'footer_id', 'maintenance_page' ] as $nome ) {
	$righe[] = "impreza {$nome}: " . $chiave( $impreza[ $nome ] ?? 0 );
}
$tolti     = [];
$confronto = wpf_togli_segreti( $impreza, $tolti );
foreach ( $confronto as $nome => $valore ) {
	if ( wpf_opzione_riferimento( (string) $nome, $valore ) ) {
		unset( $confronto[ $nome ] ); // già stampati con la chiave: gli id cambiano da un sito all'altro
	} elseif ( in_array( $nome, wpf_campi_upload(), true ) ) {
		// Allegati della Libreria media: l'id cambia da un sito all'altro e l'apply non li porta, quindi
		// restano fuori dall'impronta. Si segnala solo che ci sono.
		if ( wpf_opzione_allegato( (string) $nome, $valore ) ) {
			$righe[] = "impreza {$nome}: un allegato";
		}
		unset( $confronto[ $nome ] );
	}
}
foreach ( WPF_IMPREZA_STATO as $nome ) {
	unset( $confronto[ $nome ] ); // stato che Impreza cambia da sé: l'impronta cambierebbe senza motivo
}
$righe[] = 'impreza theme options (impronta): ' . md5( wpf_json( $confronto ) );
$righe[] = 'impreza store_gfonts_locally: ' . ( $impreza['store_gfonts_locally'] ?? '-' );
$righe[] = 'impreza colore primario: ' . ( $impreza['color_content_primary'] ?? '-' );
$righe[] = 'impreza font del testo: ' . ( $impreza['body']['font-family'] ?? '-' );
// Il nome del font viene dalle opzioni; se i file ci sono davvero lo dice lo stato del download di Impreza
$righe[] = 'impreza Google Fonts salvati sul sito e aggiornati: '
	. ( function_exists( 'us_get_local_google_fonts_state' ) ? ( us_get_local_google_fonts_state()['is_current'] ? 'sì' : 'no' ) : '-' );

foreach ( WPF_OPZIONI_SITO as $nome ) {
	$righe[] = "sito {$nome}: " . ( in_array( $nome, WPF_OPZIONI_SITO_PAGINA, true ) ? $chiave( get_option( $nome ) ) : get_option( $nome ) );
}

foreach ( wp_get_nav_menus() as $m ) {
	$voci    = wpf_voci_menu( $m->term_id ); // metadati delle voci compresi (mega menu di Impreza)
	$righe[] = "menu {$m->slug}: " . implode( ', ', array_column( $voci, 'titolo' ) ) . ' (' . md5( wpf_json( $voci ) ) . ')';
}
foreach ( get_nav_menu_locations() as $posizione => $id_menu ) {
	$righe[] = "posizione menu {$posizione}: " . ( get_term_meta( $id_menu, WPF_META_CHIAVE, true ) ?: '-' );
}

global $sitepress;
if ( $sitepress ) {
	$lingue = array_keys( $sitepress->get_active_languages() );
	sort( $lingue );
	$righe[] = 'wpml lingue: ' . implode( ',', $lingue ) . ' (predefinita ' . $sitepress->get_default_language() . ')';
	$righe[] = 'wpml formato URL: ' . $sitepress->get_setting( 'language_negotiation_type' );
	$righe[] = 'wpml traduzioni dei testi delle opzioni: ' . md5( wpf_json( wpf_wpml_stringhe_esporta() ) );
	$righe[] = 'wpml setup completato: ' . ( function_exists( 'wpml_is_setup_complete' ) && wpml_is_setup_complete() ? 'sì' : 'no' );
}

// Post con la chiave stabile: titolo, lingua e impronta del contenuto (una modifica della redazione a
// una pagina esportata cambia la riga anche se l'apply non la tocca senza --pagine)
$post = get_posts(
	[
		'post_type'        => array_values( get_post_types() ),
		'post_status'      => 'any',
		'meta_key'         => WPF_META_CHIAVE,
		'numberposts'      => -1,
		'suppress_filters' => true,
	]
);
foreach ( $post as $p ) {
	$lingua  = wpf_lingua( $p->ID, 'post_' . $p->post_type ) ?? '-';
	$righe[] = 'post ' . get_post_meta( $p->ID, WPF_META_CHIAVE, true ) . ": {$p->post_title} ({$lingua}, " . md5( $p->post_content ) . ')';
}

$righe[] = '.htaccess: ' . ( is_file( ABSPATH . '.htaccess' ) ? 'presente' : 'assente' );

sort( $righe );
echo implode( "\n", $righe ), "\n";
