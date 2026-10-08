<?php
/**
 * Funzioni condivise da export, apply e ritratto della configurazione (oc:8717).
 *
 * Nei file di config/ tre segnaposti sostituiscono ciò che cambia da un sito all'altro:
 * - @url_sito       l'indirizzo del sito (@url_sito_json nella forma con le barre protette);
 * - @segreto        un valore tolto perché segreto (il repo è pubblico);
 * - @chiave:<nome>  un post esportato, al posto del suo id.
 */

const WPF_SEGNAPOSTO_URL = '@url_sito';
const WPF_SEGRETO        = '@segreto';
const WPF_RIFERIMENTO    = '@chiave:';
const WPF_META_CHIAVE    = '_wp_forestas_chiave';
// I nomi di queste due opzioni sono ripetuti in docker/scripts/init-wordpress.sh: vanno cambiati insieme
const WPF_OPZIONE_FATTO  = 'wp_forestas_config_applicata';
// Scritta dall'init quando installa WordPress: solo un sito nato così riceve l'apply automatico
const WPF_OPZIONE_DA_FARE = 'wp_forestas_config_da_applicare';

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

// Impostazioni di WPML che WPML calcola o genera da sé: non sono configurazione e, esportate,
// comparirebbero come differenze a ogni export da un sito ricreato
const WPF_WPML_INTERNE = [
	[ 'st', 'was_frontend_visited_key' ],
	[ 'translation-management', 'custom_fields_translation' ],
	[ 'translation-management', 'custom_term_fields_translation' ],
];

// Theme Options di Impreza che non sono configurazione: la modalità manutenzione la accende la licenza
// di sviluppo (su UAT è sempre attiva) o il pannello, e versionata accenderebbe la manutenzione anche
// su un sito di produzione nuovo
// Lo stesso vale per i campi del pannello che descrivono un'operazione o un controllo in corso
// (ottimizzazione degli asset, backup, informazioni su icone e immagini)
const WPF_IMPREZA_STATO = [ 'maintenance_mode', 'optimize_assets_start', 'optimize_assets_end', 'of_backup', 'used_icons_info', 'img_size_info' ];

// Opzioni di Impreza con l'id di una pagina che il nome non rivela (vedi wpf_opzione_riferimento)
const WPF_IMPREZA_PAGINE = [ 'page_404', 'search_page' ];

// Nomi che sembrano segreti ma sono impostazioni
const WPF_NON_SEGRETI = [ 'sync_password' ];

/**
 * Vero se il nome di un'opzione indica un segreto. Si confrontano le parole separate da «_», così
 * «gmaps_api_key» è un segreto e «h_keyboard_accessibility» no.
 */
function wpf_valore_segreto( string $nome ): bool {
	if ( in_array( $nome, WPF_NON_SEGRETI, true ) ) {
		return false;
	}
	return (bool) preg_match( '/(^|_)(key|secret|token|password|api)(_|$)/i', $nome );
}

/**
 * Sostituisce con @segreto i valori segreti non vuoti, a qualsiasi profondità, e ne raccoglie i nomi.
 */
function wpf_togli_segreti( array $dati, array &$tolti, string $percorso = '' ): array {
	foreach ( $dati as $nome => $valore ) {
		$qui = $percorso === '' ? (string) $nome : $percorso . '.' . $nome;
		if ( is_array( $valore ) ) {
			$dati[ $nome ] = wpf_togli_segreti( $valore, $tolti, $qui );
		} elseif ( is_string( $nome ) && wpf_valore_segreto( $nome ) && $valore !== '' && $valore !== null ) {
			$dati[ $nome ] = WPF_SEGRETO;
			$tolti[]       = $qui;
		}
	}
	return $dati;
}

/**
 * Toglie, a qualsiasi profondità, le impostazioni di WPML che descrivono il sito da cui si esporta e
 * non la configurazione:
 * - i segni delle migrazioni già eseguite («…_has_run», «…migration_complete…») e dei controlli già
 *   fatti: scritti su un sito nuovo, WPML salterebbe migrazioni mai eseguite e non creerebbe tabelle
 *   come wp_icl_mo_files_domains;
 * - i valori che WPML calcola sul sito: default_categories contiene id di termini (su un sito nuovo
 *   l'id della categoria inglese di UAT era il menu), gettext_theme_domain_name e
 *   theme_language_folders dipendono da temi e percorsi del sito, i «…_readonly_config» li ricava dai
 *   wpml-config.xml dei plugin, setup_wizard_step e language_selector_initialized sono passi del
 *   wizard già fatti.
 */
function wpf_togli_stato_wpml( array $dati ): array {
	$stato = '/_has_run$|migration_complete|_verified$|^ajx_health_checked$|^migrated_site$'
		. '|^default_categories$|^gettext_theme_domain_name$|^theme_language_folders$|_readonly_config$'
		. '|^setup_wizard_step$|^language_selector_initialized$/';
	foreach ( $dati as $nome => $valore ) {
		if ( is_string( $nome ) && preg_match( $stato, $nome ) ) {
			unset( $dati[ $nome ] );
		} elseif ( is_array( $valore ) ) {
			$dati[ $nome ] = wpf_togli_stato_wpml( $valore );
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
 * L'indirizzo del sito compare in due forme: normale e con le barre protette, dentro il JSON che il
 * builder di Impreza salva nel contenuto. Ognuna ha il suo segnaposto, altrimenti l'apply non saprebbe
 * quale rimettere. La forma protetta va per prima: «@url_sito» è il suo prefisso.
 */
function wpf_forme_url(): array {
	$url = untrailingslashit( home_url() );
	return [ str_replace( '/', '\/', $url ), $url ];
}

function wpf_url_in_segnaposto( $dati ) {
	return wpf_sostituisci( $dati, wpf_forme_url(), [ WPF_SEGNAPOSTO_URL . '_json', WPF_SEGNAPOSTO_URL ] );
}

function wpf_segnaposto_in_url( $dati ) {
	return wpf_sostituisci( $dati, [ WPF_SEGNAPOSTO_URL . '_json', WPF_SEGNAPOSTO_URL ], wpf_forme_url() );
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
 * Voci di un menu nella forma dei file di config/: la stessa per l'export e per il confronto
 * dell'apply, così ogni differenza (destinazione, gerarchia, target, classi) viene vista.
 * Una voce verso un post con chiave stabile punta alla chiave; verso un altro post, al suo link
 * relativo.
 */
function wpf_voci_menu( int $id_menu ): array {
	$voci   = [];
	$indici = []; // id della voce => posizione nell'elenco, per ricollegare i sottomenu
	foreach ( (array) wp_get_nav_menu_items( $id_menu, [ 'post_status' => 'any' ] ) as $i => $voce ) {
		$indici[ $voce->ID ] = $i;
		$destinazione        = [ 'tipo' => 'custom', 'url' => $voce->url ];
		if ( $voce->type === 'post_type' ) {
			$chiave       = get_post_meta( (int) $voce->object_id, WPF_META_CHIAVE, true );
			$destinazione = $chiave
				? [ 'tipo' => 'post', 'post' => WPF_RIFERIMENTO . $chiave ]
				: [ 'tipo' => 'custom', 'url' => wp_make_link_relative( get_permalink( $voce->object_id ) ) ];
		} elseif ( $voce->type === 'taxonomy' ) {
			// Con la tassonomia non registrata (plugin spento) get_term_link restituisce un errore
			$link         = get_term_link( (int) $voce->object_id, $voce->object );
			$destinazione = [ 'tipo' => 'custom', 'url' => is_wp_error( $link ) ? $voce->url : wp_make_link_relative( $link ) ];
		}
		$voci[] = [
			'titolo'       => $voce->title,
			'destinazione' => $destinazione,
			'genitore'     => $voce->menu_item_parent ? ( $indici[ (int) $voce->menu_item_parent ] ?? null ) : null,
			'target'       => $voce->target,
			'classi'       => array_values( array_filter( (array) $voce->classes ) ),
			'descrizione'  => $voce->description,
			'attr_title'   => $voce->attr_title,
		];
	}
	return $voci;
}

/**
 * Plugin commerciali da docker/plugins/commerciali.txt, montato in /opt/wp-forestas/plugins. Stesse
 * regole di init-wordpress.sh e bin/wordpress-config.sh: «#» apre un commento, spazi e righe vuote
 * si ignorano.
 */
function wpf_plugin_commerciali(): array {
	$file = '/opt/wp-forestas/plugins/commerciali.txt';
	if ( ! is_readable( $file ) ) {
		WP_CLI::warning( "{$file} non trovato: elenco dei plugin commerciali vuoto" );
		return [];
	}
	$slug = array_map( fn( $r ) => preg_replace( '/\s+/', '', preg_replace( '/#.*/', '', $r ) ), file( $file ) );
	return array_values( array_filter( $slug, 'strlen' ) );
}

/**
 * Versioni del codice da cui dipende il formato di config/: registrate dall'export (versioni.json) e
 * confrontate dall'apply, perché opzioni esportate da un Impreza o un WPML di versione diversa possono
 * avere uno schema diverso.
 */
function wpf_versioni(): array {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$versioni = [ 'Impreza' => wp_get_theme( 'Impreza' )->exists() ? wp_get_theme( 'Impreza' )->get( 'Version' ) : null ];
	foreach ( get_plugins() as $file => $dati ) {
		if ( in_array( dirname( $file ), array_merge( [ 'us-core' ], wpf_plugin_commerciali() ), true ) ) {
			$versioni[ dirname( $file ) ] = $dati['Version'];
		}
	}
	ksort( $versioni );
	return $versioni;
}

/** Ordina un elenco di post o menu esportati mettendo gli originali prima delle traduzioni. */
function wpf_originali_prima( array $elenco ): array {
	usort( $elenco, fn( $a, $b ) => (int) ! empty( $a['originale'] ) <=> (int) ! empty( $b['originale'] ) );
	return $elenco;
}

/**
 * Vero se il sito risponde su questa macchina (localhost o 127.0.0.1): lì licenze e chiavi dei
 * servizi esterni non si applicano. Stesso criterio di url_locale() in init-wordpress.sh.
 */
function wpf_url_locale(): bool {
	return in_array( parse_url( home_url(), PHP_URL_HOST ), [ 'localhost', '127.0.0.1' ], true );
}

/**
 * Il menu con la chiave stabile indicata, salvata come metadato del termine. Lo slug di un menu si
 * cambia dal pannello e WPML lo modifica nelle traduzioni: la chiave no.
 */
function wpf_menu_per_chiave( string $chiave ): ?WP_Term {
	$termini = get_terms(
		[
			'taxonomy'   => 'nav_menu',
			'hide_empty' => false,
			'meta_key'   => WPF_META_CHIAVE,
			'meta_value' => $chiave,
			'number'     => 1,
		]
	);
	return ( ! is_wp_error( $termini ) && $termini ) ? $termini[0] : null;
}

/** Vero per le opzioni di Impreza che contengono l'id di un post («header_id», «maintenance_page»…). */
function wpf_opzione_riferimento( string $nome, $valore ): bool {
	return ( preg_match( '/_(id|page)$/', $nome ) || in_array( $nome, WPF_IMPREZA_PAGINE, true ) )
		&& is_scalar( $valore ) && ctype_digit( (string) $valore ) && (int) $valore > 0;
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

/** Lingua WPML di un elemento, o null senza WPML. */
function wpf_lingua( int $id, string $tipo ): ?string {
	$dettagli = apply_filters( 'wpml_element_language_details', null, [ 'element_id' => $id, 'element_type' => $tipo ] );
	return $dettagli->language_code ?? null;
}

/** Traduzioni WPML di un elemento: [ lingua => id ], originale compreso. */
function wpf_traduzioni( int $id, string $tipo ): array {
	$trid = apply_filters( 'wpml_element_trid', null, $id, $tipo );
	if ( ! $trid ) {
		return [];
	}
	$elenco = apply_filters( 'wpml_get_element_translations', [], $trid, $tipo );
	$out    = [];
	foreach ( (array) $elenco as $lingua => $t ) {
		$out[ $lingua ] = (int) $t->element_id;
	}
	return $out;
}
