<?php
/**
 * Costanti e funzioni condivise da export, apply e ritratto della configurazione (oc:8717): segreti,
 * segnaposti dell'URL, chiavi stabili dei post, Theme Options di Impreza, .htaccess e blocco
 * dell'apply. Quelle che servono anche a init-wordpress.sh stanno in costanti.php; quelle dei menu in
 * menu.php, quelle di WPML in wpml.php.
 *
 * Nei file di config/ tre segnaposti sostituiscono ciò che cambia da un sito all'altro:
 * - @url_sito       l'indirizzo del sito (@url_sito_json nella forma con le barre protette,
 *                   @url_sito_urlenc in quella codificata per gli URL);
 * - @segreto        un valore tolto perché segreto (il repo è pubblico);
 * - @chiave:<nome>  un post esportato, al posto del suo id.
 */

require_once __DIR__ . '/costanti.php';

const WPF_SEGNAPOSTO_URL = '@url_sito';
const WPF_SEGRETO        = '@segreto';
const WPF_RIFERIMENTO    = '@chiave:';
const WPF_META_CHIAVE    = '_wp_forestas_chiave';

// Impostazioni del sito versionate in sito.json (export) e mostrate nel ritratto
const WPF_OPZIONI_SITO = [ 'blogname', 'blogdescription', 'permalink_structure', 'show_on_front', 'page_on_front', 'page_for_posts' ];
// Opzioni di sito.json che contengono l'id di una pagina
const WPF_OPZIONI_SITO_PAGINA = [ 'page_on_front', 'page_for_posts' ];

// Lunghezza massima di un valore nelle righe delle differenze dell'apply
const WPF_DIFF_LARGHEZZA = 70;
// Giri massimi del download dei Google Fonts in locale: Impreza scarica a lotti, uno per giro
const WPF_GFONTS_GIRI = 20;

// Tipi di post costruiti con il builder di Impreza: sono configurazione, non contenuti
const WPF_TIPI_BUILDER = [ 'us_header', 'us_page_block', 'us_content_template', 'us_grid_layout' ];

// Theme Options di Impreza che non sono configurazione: la modalità manutenzione (la accende la licenza
// di sviluppo, su UAT sempre, o il pannello: versionata accenderebbe la manutenzione anche su un sito di
// produzione nuovo) e i campi che descrivono un'operazione o un controllo in corso (ottimizzazione degli
// asset, backup, informazioni su icone e immagini)
const WPF_IMPREZA_STATO = [ 'maintenance_mode', 'optimize_assets_start', 'optimize_assets_end', 'of_backup', 'used_icons_info', 'img_size_info' ];

// Opzioni di Impreza con l'id di una pagina che il nome non rivela (vedi wpf_opzione_riferimento)
const WPF_IMPREZA_PAGINE = [ 'page_404', 'search_page' ];

// Metadati dei post che i plugin ricavano da sé a ogni salvataggio: UpSolution Core dal contenuto
// (us_save_post: stili degli elementi, filtri, schema FAQ), WPML Media dagli allegati usati (id che
// cambiano da un sito all'altro). Non si esportano e non si applicano: li ricalcola il salvataggio
// del post fatto dall'apply.
const WPF_META_CALCOLATI = [ '_us_jsoncss_data', '_us_faceted_filter_items', '_us_schema_markup_faq', 'copied_media_ids', 'referenced_media_ids' ];

// Metadati dei post che non si esportano: modifica in corso e slug vecchi di WordPress, collegamenti di
// WPML (le traduzioni si esportano a parte). «_wp_page_template», il modello della pagina, invece resta.
const WPF_META_ESCLUSI_POST = '/^(_edit_|_wp_old|_wpml|_icl_)/';
// Metadati dei post con l'id di un allegato della Libreria media: non si esportano, l'export avvisa
const WPF_META_ALLEGATI = [ '_thumbnail_id' ];

// Attributi del builder che contengono id di allegati della Libreria media («image="12"», «images="3,4"»)
const WPF_ATTRIBUTI_ALLEGATO = '/\b(image|images|img|ids|bg_image|logo|icon_image)="\d/';

// Valori che hanno la forma di una chiave anche se il nome non lo dice: un export che li trova si ferma
// (il repo è pubblico). Nome del tipo di chiave => espressione.
const WPF_FORME_SEGRETE = [
	'chiave Google'  => '/AIza[0-9A-Za-z_\-]{35}/',
	'chiave Stripe'  => '/\b(sk|rk)_live_[0-9A-Za-z]{16,}/',
	'chiave privata' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
	'token GitHub'   => '/\bgh[pousr]_[0-9A-Za-z]{30,}/',
	'chiave AWS'     => '/\bAKIA[0-9A-Z]{16}\b/',
	'token Slack'    => '/\bxox[abposr]-[0-9A-Za-z-]{10,}/',
];

// Nomi che sembrano segreti ma sono impostazioni
const WPF_NON_SEGRETI = [ 'sync_password' ];

/**
 * Vero se il nome di un'opzione indica un segreto. Il nome si spezza in parole (separate da «_», «-»
 * o da una maiuscola: «privateKey» → private, key) e si cercano quelle dei segreti, così
 * «gmaps_api_key» e «consumerSecret» sono segreti e «h_keyboard_accessibility» no. «api» da sola non
 * basta («apiUrl», «header_api» sono impostazioni). In più le forme attaccate comuni («apikey»,
 * «licensekey»).
 */
function wpf_valore_segreto( string $nome ): bool {
	if ( in_array( $nome, WPF_NON_SEGRETI, true ) ) {
		return false;
	}
	$parole = strtolower( preg_replace( '/(?<=[a-z0-9])(?=[A-Z])|-/', '_', $nome ) );
	return (bool) preg_match( '/(^|_)(key|keys|secret|secrets|token|tokens|password|passwd|pwd|pass|credentials)(_|$)/', $parole )
		|| (bool) preg_match( '/apikey|licen[cs]ekey|secretkey|accesstoken|privatekey|passphrase/i', $nome );
}

/**
 * Sostituisce con @segreto i valori segreti, a qualsiasi profondità, e ne raccoglie i nomi. Un segreto
 * con un elenco come valore si toglie per intero. Restano i valori che non dicono nulla (vuoto, null,
 * vero/falso, elenco vuoto): toglierli farebbe solo comparire differenze inutili.
 */
function wpf_togli_segreti( array $dati, array &$tolti, string $percorso = '' ): array {
	foreach ( $dati as $nome => $valore ) {
		$qui     = $percorso === '' ? (string) $nome : $percorso . '.' . $nome;
		$segreto = is_string( $nome ) && wpf_valore_segreto( $nome );
		if ( $segreto && ! in_array( $valore, [ '', null, [] ], true ) && ! is_bool( $valore ) ) {
			$dati[ $nome ] = WPF_SEGRETO;
			$tolti[]       = $qui;
		} elseif ( is_array( $valore ) ) {
			$dati[ $nome ] = wpf_togli_segreti( $valore, $tolti, $qui );
		}
	}
	return $dati;
}

/** Rimette, a qualsiasi profondità, il valore del sito al posto di ogni @segreto. */
function wpf_ripristina_segreti( $dati, $sul_sito ) {
	if ( $dati === WPF_SEGRETO ) {
		return $sul_sito;
	}
	if ( ! is_array( $dati ) ) {
		return $dati;
	}
	foreach ( $dati as $nome => $valore ) {
		$dati[ $nome ] = wpf_ripristina_segreti( $valore, is_array( $sul_sito ) ? ( $sul_sito[ $nome ] ?? null ) : null );
	}
	return $dati;
}

/**
 * L'indirizzo del sito compare in tre forme: normale, con le barre protette dentro il JSON che il
 * builder di Impreza salva nel contenuto, e codificata dentro un parametro di un link
 * («http%3A%2F%2F…», per esempio nei pulsanti di condivisione). Ognuna ha il suo segnaposto,
 * altrimenti l'apply non saprebbe quale rimettere. La forma normale va per ultima: è contenuta nella
 * protetta.
 */
function wpf_forme_url(): array {
	$url = untrailingslashit( home_url() );
	return [ str_replace( '/', '\/', $url ), rawurlencode( $url ), $url ];
}

/** Segnaposti nello stesso ordine di wpf_forme_url(): i più lunghi prima, «@url_sito» è il loro prefisso. */
function wpf_segnaposti_url(): array {
	return [ WPF_SEGNAPOSTO_URL . '_json', WPF_SEGNAPOSTO_URL . '_urlenc', WPF_SEGNAPOSTO_URL ];
}

function wpf_url_in_segnaposto( $dati ) {
	return wpf_sostituisci( $dati, wpf_forme_url(), wpf_segnaposti_url() );
}

function wpf_segnaposto_in_url( $dati ) {
	return wpf_sostituisci( $dati, wpf_segnaposti_url(), wpf_forme_url() );
}

function wpf_sostituisci( $dati, array $da, array $a ) {
	if ( is_array( $dati ) ) {
		return array_map( fn( $v ) => wpf_sostituisci( $v, $da, $a ), $dati );
	}
	return is_string( $dati ) ? str_replace( $da, $a, $dati ) : $dati;
}

/**
 * Il post con la chiave stabile indicata, in qualsiasi stato tranne il cestino. Si interrogano i
 * metadati senza filtrare per tipo di post: se il plugin che registra un tipo (per esempio UpSolution
 * Core per us_header) è spento, il post esiste lo stesso e non va ricreato. Con più post con la stessa
 * chiave vale il più vecchio.
 */
function wpf_post_per_chiave( string $chiave ): ?WP_Post {
	global $wpdb;
	$id = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			 WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_status NOT IN ('trash', 'auto-draft')
			 ORDER BY p.ID LIMIT 1",
			WPF_META_CHIAVE,
			$chiave
		)
	);
	return $id ? get_post( (int) $id ) : null;
}

/**
 * Versioni del codice da cui dipende il formato di config/: registrate dall'export (versioni.json) e
 * confrontate dall'apply, perché opzioni esportate da un Impreza o un WPML di versione diversa possono
 * avere uno schema diverso.
 */
function wpf_versioni(): array {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$versioni = [ 'Impreza' => wpf_versione_tema( 'Impreza' ) ];
	foreach ( get_plugins() as $file => $dati ) {
		if ( in_array( dirname( $file ), array_merge( [ 'us-core' ], wpf_plugin_commerciali() ), true ) ) {
			$versioni[ dirname( $file ) ] = $dati['Version'];
		}
	}
	ksort( $versioni );
	return $versioni;
}

/** Versione di un tema installato, o null se non c'è. */
function wpf_versione_tema( string $tema ): ?string {
	$t = wp_get_theme( $tema );
	return $t->exists() ? $t->get( 'Version' ) : null;
}

/** Ordina un elenco di post o menu esportati mettendo gli originali prima delle traduzioni. */
function wpf_originali_prima( array $elenco ): array {
	usort( $elenco, fn( $a, $b ) => (int) ! empty( $a['originale'] ) <=> (int) ! empty( $b['originale'] ) );
	return $elenco;
}

/** Vero se il sito risponde su questa macchina (vedi wpf_indirizzo_locale). */
function wpf_sito_locale(): bool {
	return wpf_indirizzo_locale( home_url() );
}

/**
 * Vero per le opzioni di Impreza che contengono l'id di un post: header, footer, contenuto, sidebar e
 * titlebar per tipo di pagina («header_id», «footer_post_id»…) e le pagine («maintenance_page»…).
 * Un id di un servizio esterno («facebook_app_id») non è un riferimento e resta com'è.
 */
function wpf_opzione_riferimento( string $nome, $valore ): bool {
	return ( preg_match( '/^(header|footer|content|sidebar|titlebar)_(.+_)?id$|_page$/', $nome ) || in_array( $nome, WPF_IMPREZA_PAGINE, true ) )
		&& is_scalar( $valore ) && ctype_digit( (string) $valore ) && (int) $valore > 0;
}

/**
 * Theme Options di Impreza di tipo «upload» (icona del sito, immagini di sfondo, segnaposto…), lette
 * dalla definizione delle Theme Options della versione installata invece che da un elenco a mano.
 */
function wpf_campi_upload(): array {
	static $campi = null;
	if ( $campi === null ) {
		$campi = [];
		foreach ( function_exists( 'us_config' ) ? (array) us_config( 'theme-options' ) : [] as $sezione ) {
			foreach ( (array) ( $sezione['fields'] ?? [] ) as $nome => $campo ) {
				if ( ( $campo['type'] ?? '' ) === 'upload' ) {
					$campi[] = $nome;
				}
			}
		}
	}
	return $campi;
}

/**
 * Vero per le Theme Options che contengono uno o più allegati della Libreria media: non stanno in
 * config/, quindi l'id di un sito non vale su un altro. Impreza salva «12», «12|full» o «12,13».
 */
function wpf_opzione_allegato( string $nome, $valore ): bool {
	return in_array( $nome, wpf_campi_upload(), true ) && is_scalar( $valore )
		&& preg_match( '/^\d+(\|[\w-]+)?(,\d+(\|[\w-]+)?)*$/', (string) $valore ) && (int) $valore > 0;
}

/**
 * Scrive in .htaccess le regole dei permalink, come fa il pannello salvandoli. Da WP-CLI WordPress
 * non sa che mod_rewrite c'è (got_mod_rewrite() legge i moduli di Apache) e non scriverebbe nulla:
 * l'immagine lo abilita (a2enmod rewrite), quindi lo si dichiara. Tocca solo la sezione
 * «# BEGIN WordPress … # END WordPress»: le altre regole del file restano.
 */
function wpf_scrivi_htaccess(): bool {
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	add_filter( 'got_rewrite', '__return_true' );
	// Struttura dei permalink riletta: appena cambiata dall'apply, $wp_rewrite ha ancora quella vecchia
	global $wp_rewrite;
	$wp_rewrite->init();
	flush_rewrite_rules( false );
	return (bool) save_mod_rewrite_rules();
}

/**
 * Impedisce due apply nello stesso momento. Il blocco è una riga di wp_options inserita con
 * INSERT IGNORE: il database la accetta una volta sola, quindi solo il primo apply la ottiene
 * (add_option non basta: scrive con ON DUPLICATE KEY UPDATE e si fida della cache). Un blocco più
 * vecchio di WPF_APPLY_SCADENZA viene da un apply interrotto e si toglie; l'init toglie all'avvio del
 * container i blocchi presi prima dell'avvio (vedi wpf_sblocca_apply), perché un apply gira dentro il
 * container e muore con lui.
 */
function wpf_blocca_apply(): bool {
	global $wpdb;
	wpf_sblocca_apply( time() - WPF_APPLY_SCADENZA );
	$preso = $wpdb->query(
		$wpdb->prepare(
			"INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
			WPF_OPZIONE_APPLY_IN_CORSO,
			(string) time()
		)
	);
	if ( $preso !== 1 ) {
		return false;
	}
	register_shutdown_function( 'wpf_sblocca_apply' );
	return true;
}

/**
 * Toglie il blocco dell'apply (vedi wpf_blocca_apply). Con $prima_di toglie solo un blocco preso prima
 * di quell'istante: l'init passa l'ora del proprio avvio, così non toglie il blocco di un apply lanciato
 * dall'host mentre l'init sta ancora girando.
 */
function wpf_sblocca_apply( ?int $prima_di = null ): void {
	global $wpdb;
	if ( $prima_di === null ) {
		$wpdb->delete( $wpdb->options, [ 'option_name' => WPF_OPZIONE_APPLY_IN_CORSO ] );
	} else {
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name = %s AND CAST(option_value AS UNSIGNED) < %d",
				WPF_OPZIONE_APPLY_IN_CORSO,
				$prima_di
			)
		);
	}
	wp_cache_delete( WPF_OPZIONE_APPLY_IN_CORSO, 'options' );
}

/** Ordina le chiavi degli array associativi, per file con differenze stabili fra un export e l'altro. */
function wpf_ordina( $dati ) {
	if ( ! is_array( $dati ) ) {
		return $dati;
	}
	$dati = array_map( 'wpf_ordina', $dati );
	if ( array_keys( $dati ) !== range( 0, count( $dati ) - 1 ) ) {
		ksort( $dati );
	}
	return $dati;
}

function wpf_json( $dati ): string {
	return json_encode( wpf_ordina( $dati ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
}

/** Vero se due valori sono diversi nella forma dei file di config/ (chiavi ordinate, stesso JSON). */
function wpf_diverso( $a, $b ): bool {
	return wpf_json( $a ) !== wpf_json( $b );
}

/** Nome dell'opzione con le Theme Options di Impreza («usof_options_Impreza»). */
function wpf_nome_theme_options(): string {
	return 'usof_options_' . ( defined( 'US_THEMENAME' ) ? US_THEMENAME : 'Impreza' );
}
