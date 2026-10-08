<?php
/**
 * Costanti e funzioni condivise da export, apply e ritratto della configurazione e da
 * init-wordpress.sh, che ne legge nomi e percorsi con «php -r» per non ripeterli (oc:8717). Il file
 * si carica anche senza WordPress: solo le funzioni che lo usano lo richiedono.
 *
 * Nei file di config/ tre segnaposti sostituiscono ciò che cambia da un sito all'altro:
 * - @url_sito       l'indirizzo del sito (@url_sito_json nella forma con le barre protette,
 *                   @url_sito_urlenc in quella codificata per gli URL);
 * - @segreto        un valore tolto perché segreto (il repo è pubblico);
 * - @chiave:<nome>  un post esportato, al posto del suo id.
 */

const WPF_SEGNAPOSTO_URL = '@url_sito';
const WPF_SEGRETO        = '@segreto';
const WPF_RIFERIMENTO    = '@chiave:';
const WPF_META_CHIAVE    = '_wp_forestas_chiave';

// Percorsi montati dal compose (compose.yml): li legge anche init-wordpress.sh
const WPF_DIR_PLUGIN = '/opt/wp-forestas/plugins';
const WPF_DIR_CONFIG = '/opt/wp-forestas/config';

// Opzioni di controllo, lette anche da init-wordpress.sh. Scritta dall'init quando installa WordPress:
// solo un sito nato così riceve l'apply automatico. La seconda la scrive l'apply riuscito.
const WPF_OPZIONE_DA_FARE = 'wp_forestas_config_da_applicare';
const WPF_OPZIONE_FATTO   = 'wp_forestas_config_applicata';
// Blocco che impedisce due apply nello stesso momento (l'automatico dell'init e uno lanciato dall'host)
const WPF_OPZIONE_APPLY_IN_CORSO = 'wp_forestas_apply_in_corso';
// Dopo quanti secondi un apply «in corso» si considera interrotto e il blocco si può togliere. Deve
// superare il tempo massimo dell'apply automatico, che init-wordpress.sh ricava da qui
const WPF_APPLY_SCADENZA = 2400;
// Plugin commerciali la cui attivazione automatica è fallita: l'init la ritenta (vedi il passo 7c)
const WPF_OPZIONE_DA_ATTIVARE = 'wp_forestas_plugin_da_attivare';

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
	// Id della pagina usata come root page: su un altro sito sarebbe un'altra pagina. L'export avvisa
	// se è impostata, l'apply lascia quella del sito.
	[ 'urls', 'root_page' ],
];

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

// Metadati standard delle voci di menu: già rappresentati dai campi di wpf_voci_menu(). Gli altri (mega
// menu e pulsante di Impreza, «us_mega_menu_settings», «_menu_item_btn_style»…) si esportano a parte.
const WPF_META_VOCE_STANDARD = [ '_menu_item_type', '_menu_item_menu_item_parent', '_menu_item_object_id', '_menu_item_object', '_menu_item_target', '_menu_item_classes', '_menu_item_xfn', '_menu_item_url', '_menu_item_orphaned' ];
// Altri metadati delle voci che non si esportano: per una voce di menu nessun «_wp_…» è un'impostazione
const WPF_META_ESCLUSI_VOCE = '/^(_edit_|_wp_|_wpml|_icl_)/';

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

/**
 * Toglie, a qualsiasi profondità, le impostazioni di WPML che descrivono il sito da cui si esporta e
 * non la configurazione:
 * - i segni delle migrazioni già eseguite («…_has_run», «…migration_complete…») e dei controlli già
 *   fatti: scritti su un sito nuovo, WPML salterebbe migrazioni mai eseguite e non creerebbe tabelle
 *   come wp_icl_mo_files_domains;
 * - i valori che WPML calcola sul sito: default_categories contiene id di termini (su un sito nuovo
 *   l'id della categoria inglese di UAT era il menu), gettext_theme_domain_name e
 *   theme_language_folders dipendono da temi e percorsi del sito, i «…_readonly_config» (e la loro
 *   «…_source») li ricava dai wpml-config.xml dei plugin, setup_wizard_step e
 *   language_selector_initialized sono passi del wizard già fatti, db_ok_for_gettext_context e
 *   autoregister_strings_were_new_translations_loaded sono controlli di String Translation.
 */
function wpf_togli_stato_wpml( array $dati ): array {
	$stato = '/_has_run$|migration_complete|_migrated$|_verified$|^ajx_health_checked$|^migrated_site$'
		. '|^default_categories$|^gettext_theme_domain_name$|^theme_language_folders$|_readonly_config(_source)?$'
		. '|^setup_wizard_step$|^language_selector_initialized$|^db_ok_for_gettext_context$'
		. '|_were_new_translations_loaded$/';
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
 * Voci di un menu lette dal database, nell'ordine del menu. Non si usa wp_get_nav_menu_items(): il suo
 * filtro lascia a WPML aggiungere le voci del selettore di lingua (che non esistono nel database) e
 * togliere quella della «root page», e da WP-CLI quel filtro è attivo.
 *
 * @return WP_Post[]
 */
function wpf_voci_db( int $id_menu ): array {
	$ids = get_objects_in_term( $id_menu, 'nav_menu' );
	if ( is_wp_error( $ids ) || ! $ids ) {
		return [];
	}
	$voci = get_posts(
		[
			'post_type'        => 'nav_menu_item',
			'post__in'         => $ids,
			'post_status'      => 'any',
			'orderby'          => 'menu_order',
			'order'            => 'ASC',
			'numberposts'      => -1,
			'suppress_filters' => true,
		]
	);
	return array_map( 'wp_setup_nav_menu_item', $voci );
}

/**
 * Link relativo di un post nella sua lingua. Da WP-CLI WPML converte il link di una traduzione in quello
 * del post nella lingua corrente (la predefinita): si passa alla lingua del post e poi si torna indietro.
 */
function wpf_link_relativo( int $id ): string {
	$lingua = wpf_lingua( $id, 'post_' . get_post_type( $id ) );
	$prima  = apply_filters( 'wpml_current_language', null );
	if ( $lingua ) {
		do_action( 'wpml_switch_language', $lingua );
	}
	$link = wp_make_link_relative( get_permalink( $id ) );
	if ( $lingua ) {
		do_action( 'wpml_switch_language', $prima );
	}
	return $link;
}

/**
 * Posizione di una voce nel suo menu (0 = la prima), o null. Serve a collegare le voci di un menu
 * tradotto a quelle dell'originale: gli id cambiano da un sito all'altro, la posizione no.
 */
function wpf_posizione_voce( int $id_voce ): ?int {
	$menu = wp_get_object_terms( $id_voce, 'nav_menu', [ 'fields' => 'ids' ] );
	if ( is_wp_error( $menu ) || ! $menu ) {
		return null;
	}
	$posizione = array_search( $id_voce, array_map( fn( $v ) => (int) $v->ID, wpf_voci_db( (int) $menu[0] ) ), true );
	return $posizione === false ? null : $posizione;
}

/**
 * Voci di un menu nella forma dei file di config/: la stessa per l'export e per il confronto
 * dell'apply, così ogni differenza (destinazione, gerarchia, target, classi, metadati, legame con la
 * traduzione) viene vista. La destinazione è:
 * - «post», la chiave stabile, per un post esportato in config/;
 * - «pagina», tipo e percorso, per un altro post o pagina: l'apply lo ritrova sul sito di destinazione
 *   e la voce resta un collegamento a quel post (con link e traduzione che WordPress e WPML seguono);
 * - «custom», l'URL, per un link e per una categoria.
 * «originale» è, per una voce di un menu tradotto, la posizione della voce che traduce nel menu
 * originale (il gruppo di traduzione di WPML).
 */
function wpf_voci_menu( int $id_menu ): array {
	$voci        = [];
	$indici      = []; // id della voce => posizione nell'elenco, per ricollegare i sottomenu
	$predefinita = apply_filters( 'wpml_default_language', null );
	foreach ( wpf_voci_db( $id_menu ) as $i => $voce ) {
		$indici[ $voce->ID ] = $i;
		$destinazione        = [ 'tipo' => 'custom', 'url' => $voce->url ];
		if ( $voce->type === 'post_type' ) {
			$chiave = get_post_meta( (int) $voce->object_id, WPF_META_CHIAVE, true );
			if ( $chiave ) {
				$destinazione = [ 'tipo' => 'post', 'post' => WPF_RIFERIMENTO . $chiave ];
			} elseif ( get_post( (int) $voce->object_id ) ) {
				$destinazione = [
					'tipo'      => 'pagina',
					'post_type' => $voce->object,
					'percorso'  => get_page_uri( (int) $voce->object_id ),
					'url'       => wpf_link_relativo( (int) $voce->object_id ),
				];
			}
		} elseif ( $voce->type === 'taxonomy' ) {
			// Con la tassonomia non registrata (plugin spento) get_term_link restituisce un errore
			$link         = get_term_link( (int) $voce->object_id, $voce->object );
			$destinazione = [ 'tipo' => 'custom', 'url' => is_wp_error( $link ) ? $voce->url : wp_make_link_relative( $link ) ];
		}
		$originale = null;
		$lingua    = wpf_lingua( (int) $voce->ID, 'post_nav_menu_item' );
		if ( $lingua && $predefinita && $lingua !== $predefinita ) {
			$id_originale = wpf_traduzioni( (int) $voce->ID, 'post_nav_menu_item' )[ $predefinita ] ?? null;
			$originale    = $id_originale && $id_originale !== (int) $voce->ID ? wpf_posizione_voce( $id_originale ) : null;
		}
		$voci[] = [
			'titolo'       => $voce->title,
			'destinazione' => $destinazione,
			'genitore'     => $voce->menu_item_parent ? ( $indici[ (int) $voce->menu_item_parent ] ?? null ) : null,
			'target'       => $voce->target,
			'classi'       => array_values( array_filter( (array) $voce->classes ) ),
			'descrizione'  => $voce->description,
			'attr_title'   => $voce->attr_title,
			'xfn'          => $voce->xfn,
			'meta'         => wpf_meta_voce( (int) $voce->ID ),
			'originale'    => $originale,
		];
	}
	return $voci;
}

/**
 * Metadati di una voce di menu oltre a quelli standard: le impostazioni di Impreza (mega menu, voce
 * come pulsante, righe tolte) e di altri plugin. Senza, un sito ricreato avrebbe le voci ma non il mega
 * menu.
 */
function wpf_meta_voce( int $id ): array {
	$meta = [];
	foreach ( get_post_meta( $id ) as $nome => $valori ) {
		if ( in_array( $nome, WPF_META_VOCE_STANDARD, true ) || preg_match( WPF_META_ESCLUSI_VOCE, $nome ) ) {
			continue;
		}
		$meta[ $nome ] = maybe_unserialize( $valori[0] );
	}
	return $meta;
}

/**
 * Plugin commerciali da docker/plugins/commerciali.txt, montato in WPF_DIR_PLUGIN: «#» apre un
 * commento, spazi e righe vuote si ignorano. È l'unica lettura del file: init-wordpress.sh e
 * bin/wordpress-config.sh la chiamano con «php -r».
 */
function wpf_plugin_commerciali(): array {
	$file = WPF_DIR_PLUGIN . '/commerciali.txt';
	if ( ! is_readable( $file ) ) {
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::warning( "{$file} non trovato: elenco dei plugin commerciali vuoto" );
		}
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

/**
 * Vero se l'indirizzo punta a questa macchina (localhost o 127.0.0.1): lì licenze e chiavi dei servizi
 * esterni non si applicano, perché il sito si registrerebbe presso i fornitori con un indirizzo locale.
 * init-wordpress.sh la usa con «php -r» su WP_URL.
 */
function wpf_indirizzo_locale( string $url ): bool {
	return in_array( parse_url( $url, PHP_URL_HOST ), [ 'localhost', '127.0.0.1' ], true );
}

/** Vero se il sito risponde su questa macchina (vedi wpf_indirizzo_locale). */
function wpf_sito_locale(): bool {
	return wpf_indirizzo_locale( home_url() );
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

/** Dettagli WPML di un elemento (lingua, trid, lingua di partenza), o null senza WPML. */
function wpf_dettagli_lingua( int $id, string $tipo ): ?object {
	return apply_filters( 'wpml_element_language_details', null, [ 'element_id' => $id, 'element_type' => $tipo ] ) ?: null;
}

/** Lingua WPML di un elemento, o null senza WPML. */
function wpf_lingua( int $id, string $tipo ): ?string {
	return wpf_dettagli_lingua( $id, $tipo )->language_code ?? null;
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
