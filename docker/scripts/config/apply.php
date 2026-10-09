<?php
/**
 * Apply della configurazione esportata (oc:8717).
 *
 * Uso: [WPF_CONFIG_DIR=<cartella>] [WPF_PROVA=1] [WPF_AUTOMATICO=1] [WPF_PAGINE=1]
 *      wp eval-file /usr/local/lib/wp-forestas/apply.php
 * La cartella è config/ del repo (WPF_DIR_CONFIG), o un backup con bin/wordpress-config.sh apply --da.
 * WPF_PAGINE=1 aggiorna anche le pagine che esistono già, come la Home (vedi post.php).
 * Con WPF_PROVA=1 non scrive nulla e stampa una riga per differenza. Senza, applica nell'ordine: WPML
 * (lingue e impostazioni), post, menu, Theme Options, traduzioni dei loro testi, theme_mods, posizioni
 * dei menu, impostazioni del sito; solo se tutto riesce scrive l'opzione WPF_OPZIONE_FATTO.
 * WPF_AUTOMATICO=1 (apply dell'init) si rifiuta di applicare un config/ esportato da un'altra versione
 * (principale o minore, «9.4» contro «9.5») di Impreza o di un plugin: nessuno guarda le differenze.
 * Lanciato due volte non duplica nulla: post e menu si ritrovano dalla chiave stabile
 * (_wp_forestas_chiave); lo slug serve solo a ricollegare quelli di un sito nato prima di config/.
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';
require_once __DIR__ . '/post.php';
require_once __DIR__ . '/menu.php';
require_once __DIR__ . '/opzioni.php';

$dir        = getenv( 'WPF_CONFIG_DIR' ) ?: WPF_DIR_CONFIG;
$prova      = getenv( 'WPF_PROVA' ) === '1';
$automatico = getenv( 'WPF_AUTOMATICO' ) === '1';
$pagine     = getenv( 'WPF_PAGINE' ) === '1';
$ok         = true;
$righe      = 0;

$leggi = function ( string $nome ) use ( $dir, &$ok ) {
	$file = "{$dir}/{$nome}";
	if ( ! is_file( $file ) ) {
		return null;
	}
	$dati = json_decode( file_get_contents( $file ), true );
	if ( json_last_error() !== JSON_ERROR_NONE ) {
		// Un file rotto (per esempio un conflitto di merge) non va scambiato per una sezione vuota
		WP_CLI::warning( "{$nome} non è un JSON valido (" . json_last_error_msg() . '): sezione saltata' );
		$ok = false;
		return null;
	}
	return wpf_segnaposto_in_url( $dati );
};
$breve = fn( $v ) => mb_strimwidth( is_scalar( $v ) || $v === null ? var_export( $v, true ) : json_encode( $v, JSON_UNESCAPED_UNICODE ), 0, WPF_DIFF_LARGHEZZA, '…' );
$diff  = function ( string $voce, $prima, $dopo ) use ( &$righe, $breve ) {
	$righe++;
	$ultima = preg_replace( '/^.*[ .:]/', '', $voce );
	if ( wpf_valore_segreto( $ultima ) ) {
		$prima = $dopo = '(segreto)'; // il valore non deve finire nei log
	}
	// Anche dentro un'impostazione composta (WPML, theme_mods) un segreto non va stampato
	$scarto = [];
	$prima  = is_array( $prima ) ? wpf_togli_segreti( $prima, $scarto ) : $prima;
	$dopo   = is_array( $dopo ) ? wpf_togli_segreti( $dopo, $scarto ) : $dopo;
	WP_CLI::log( "{$voce}: {$breve( $prima )} → {$breve( $dopo )}" );
};

if ( ! glob( "{$dir}/*.json" ) ) {
	WP_CLI::log( "nessuna configurazione in {$dir}" );
	return;
}

if ( ! $prova && ! wpf_blocca_apply() ) {
	WP_CLI::error( "un altro apply è in corso su questo sito (opzione " . WPF_OPZIONE_APPLY_IN_CORSO . '): riprova quando è finito' );
}

// Versioni da cui è stato fatto l'export: se il sito ha versioni diverse lo si dice. A mano si decide
// guardando le differenze; l'apply automatico non può guardarle, quindi con una versione diversa (lo
// schema delle opzioni può essere cambiato) non applica.
$versioni_sito = wpf_versioni();
foreach ( (array) $leggi( 'versioni.json' ) as $nome => $versione ) {
	$sul_sito = $versioni_sito[ $nome ] ?? null;
	if ( $sul_sito === $versione ) {
		continue;
	}
	WP_CLI::warning( "config/ è stato esportato con {$nome} {$versione}, il sito ha " . ( $sul_sito ?? 'nessuna versione' ) . ': controlla le differenze prima di confermare' );
	$serie = fn( $v ) => implode( '.', array_slice( explode( '.', (string) $v ), 0, 2 ) ); // «9.4.1» → «9.4»
	if ( $automatico && ! $sul_sito ) {
		// Senza Impreza o un plugin (zip non ancora copiato) metà della configurazione non si potrebbe
		// applicare: l'apply automatico aspetta, senza consumare un tentativo (vedi init-wordpress.sh, 8c)
		WP_CLI::warning( "manca {$nome}: l'apply automatico aspetta che sia installato" );
		WP_CLI::halt( 3 );
	}
	if ( $automatico && $serie( $versione ) !== $serie( $sul_sito ) ) {
		WP_CLI::warning( 'versione diversa: l\'apply automatico non applica, guarda le differenze con bin/wordpress-config.sh apply' );
		WP_CLI::halt( 1 );
	}
}

// --- 1. WPML: lingue e impostazioni ---------------------------------------------------------

$wpml = $leggi( 'wpml.json' );
if ( $wpml ) {
	$ok = wpf_wpml_applica( $wpml, $prova, fn( $v, $p, $d ) => $diff( "wpml: {$v}", $p, $d ) ) && $ok;
}

// --- 2. Post e menu: originali prima delle traduzioni ---------------------------------------

if ( ! $prova ) {
	wpf_wpml_prepara();
}
$post_cfg = (array) $leggi( 'post.json' );
$ok       = wpf_post_applica( $post_cfg, $prova, $diff, $pagine ) && $ok;

/**
 * Id del post a cui punta un riferimento «@chiave:…»; null se il valore non è un riferimento, 0 se la
 * chiave non trova un post (segnalato: l'apply non è riuscito del tutto). In modalità prova i post da
 * creare o da ricollegare non esistono ancora con la loro chiave: il riferimento si segnala come
 * differenza, perché con --conferma l'opzione che lo usa cambierà.
 */
$chiavi_cfg = array_column( $post_cfg, 'chiave' );
$segnalati  = [];
$risolvi    = function ( $valore ) use ( &$ok, &$segnalati, $prova, $chiavi_cfg, $diff ) {
	if ( ! is_string( $valore ) || strpos( $valore, WPF_RIFERIMENTO ) !== 0 ) {
		return null;
	}
	$chiave = substr( $valore, strlen( WPF_RIFERIMENTO ) );
	$post   = wpf_post_per_chiave( $chiave );
	if ( ! $post && $prova && in_array( $chiave, $chiavi_cfg, true ) && ! isset( $segnalati[ $chiave ] ) ) {
		$segnalati[ $chiave ] = true;
		$diff( "riferimento {$valore}", 'post da creare o ricollegare', 'risolto con --conferma (header, footer, Home…)' );
	} elseif ( ! $post && ! $prova ) {
		WP_CLI::warning( "riferimento {$valore} non risolto: nessun post con quella chiave" );
		$ok = false;
	}
	return $post ? $post->ID : 0;
};

$menu_cfg = (array) $leggi( 'menu.json' );
$id_menu  = []; // chiave => term_id
$ok       = wpf_menu_applica( (array) ( $menu_cfg['menu'] ?? [] ), $prova, $diff, $risolvi, $id_menu ) && $ok;

// --- 3. Opzioni: Theme Options e loro traduzioni, tema, posizioni dei menu, sito ------------

$impreza = $leggi( 'impreza.json' );
if ( $impreza !== null ) {
	$ok = wpf_impreza_applica( $impreza, $prova, $diff, $risolvi ) && $ok;
}
// WPML registra le stringhe originali leggendo le opzioni, quindi le traduzioni vanno dopo
if ( $wpml ) {
	$ok = wpf_wpml_stringhe_applica( (array) ( $wpml['stringhe'] ?? [] ), $prova, $diff ) && $ok;
}
$child = $leggi( 'child.json' );
if ( $child !== null ) {
	$ok = wpf_child_applica( $child, $prova, $diff ) && $ok;
}
$ok = wpf_posizioni_applica( (array) ( $menu_cfg['posizioni'] ?? [] ), $id_menu, $prova, $diff ) && $ok;
$ok = wpf_sito_applica( (array) $leggi( 'sito.json' ), $prova, $diff, $risolvi ) && $ok;

// --- Esito ----------------------------------------------------------------------------------

if ( $prova ) {
	WP_CLI::log( $righe ? "{$righe} differenze" : 'nessuna differenza' );
	return;
}
if ( $ok ) {
	update_option( WPF_OPZIONE_FATTO, gmdate( 'c' ), false );
	delete_option( WPF_OPZIONE_DA_FARE );
	WP_CLI::success( "configurazione applicata ({$righe} modifiche)" );
} else {
	// Esce con errore: l'init lo registra come AVVISO e ritenta se ha ancora tentativi, il comando
	// sull'host termina con un codice diverso da 0
	WP_CLI::warning( 'configurazione applicata solo in parte, vedi gli avvisi sopra: rilancia con bin/wordpress-config.sh apply --conferma dopo aver tolto la causa' );
	WP_CLI::halt( 1 );
}
