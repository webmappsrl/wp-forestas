<?php
/**
 * Apply della configurazione esportata (oc:8717).
 *
 * Uso: [WPF_CONFIG_DIR=<cartella>] [WPF_PROVA=1] [WPF_AUTOMATICO=1] wp eval-file /usr/local/lib/wp-forestas/apply.php
 * La cartella è config/ del repo (WPF_DIR_CONFIG), o un backup con bin/wordpress-config.sh apply --da.
 * Con WPF_PROVA=1 non scrive nulla e stampa una riga per differenza. Senza, applica nell'ordine WPML,
 * post, menu, opzioni, traduzioni dei testi delle opzioni, e solo se tutto riesce scrive l'opzione
 * wp_forestas_config_applicata. WPF_AUTOMATICO=1 (apply dell'init) si rifiuta di applicare un config/
 * esportato da un'altra versione principale di Impreza o di un plugin.
 * Lanciato due volte non duplica nulla: post e menu si ritrovano dalla chiave stabile
 * (_wp_forestas_chiave); lo slug serve solo a ricollegare quelli di un sito nato prima di config/.
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';
require_once __DIR__ . '/post.php';
require_once __DIR__ . '/menu.php';

$dir        = getenv( 'WPF_CONFIG_DIR' ) ?: WPF_DIR_CONFIG;
$prova      = getenv( 'WPF_PROVA' ) === '1';
$automatico = getenv( 'WPF_AUTOMATICO' ) === '1';
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
// guardando le differenze; l'apply automatico non può guardarle, quindi con una versione principale
// diversa (lo schema delle opzioni può essere cambiato) non applica.
$versioni_sito = wpf_versioni();
foreach ( (array) $leggi( 'versioni.json' ) as $nome => $versione ) {
	$sul_sito = $versioni_sito[ $nome ] ?? null;
	if ( $sul_sito === $versione ) {
		continue;
	}
	WP_CLI::warning( "config/ è stato esportato con {$nome} {$versione}, il sito ha " . ( $sul_sito ?? 'nessuna versione' ) . ': controlla le differenze prima di confermare' );
	if ( $automatico && $sul_sito && strtok( (string) $versione, '.' ) !== strtok( $sul_sito, '.' ) ) {
		WP_CLI::warning( 'versione principale diversa: l\'apply automatico non applica, guarda le differenze con bin/wordpress-config.sh apply' );
		WP_CLI::halt( 1 );
	}
}

// --- 1. WPML ----------------------------------------------------------------------------------

$wpml = $leggi( 'wpml.json' );
if ( $wpml ) {
	$ok = wpf_wpml_applica( $wpml, $prova, fn( $v, $p, $d ) => $diff( "wpml: {$v}", $p, $d ) ) && $ok;
}

// --- 2. Post e menu: originali prima delle traduzioni -------------------------------------------------

if ( ! $prova ) {
	wpf_wpml_prepara();
}
$ok = wpf_post_applica( (array) $leggi( 'post.json' ), $prova, $diff ) && $ok;

/**
 * Id del post a cui punta un riferimento «@chiave:…»; null se il valore non è un riferimento, 0 se la
 * chiave non trova un post (segnalato: l'apply non è riuscito del tutto).
 */
$risolvi = function ( $valore ) use ( &$ok, $prova ) {
	if ( ! is_string( $valore ) || strpos( $valore, WPF_RIFERIMENTO ) !== 0 ) {
		return null;
	}
	$post = wpf_post_per_chiave( substr( $valore, strlen( WPF_RIFERIMENTO ) ) );
	if ( ! $post && ! $prova ) {
		WP_CLI::warning( "riferimento {$valore} non risolto: nessun post con quella chiave" );
		$ok = false;
	}
	return $post ? $post->ID : 0;
};

$menu_cfg = (array) $leggi( 'menu.json' );
$id_menu  = []; // chiave => term_id
$ok       = wpf_menu_applica( (array) ( $menu_cfg['menu'] ?? [] ), $prova, $diff, $risolvi, $id_menu ) && $ok;

// --- 4. Opzioni -------------------------------------------------------------------------------

// Theme Options di Impreza: riferimenti risolti, segreti lasciati come sono sul sito
$impreza = $leggi( 'impreza.json' );
if ( $impreza !== null ) {
	if ( ! function_exists( 'usof_save_options' ) || ! defined( 'US_THEMENAME' ) ) {
		WP_CLI::warning( 'UpSolution Core non è attivo: Theme Options di Impreza saltate' );
		$ok = false;
	} else {
		$ora = (array) get_option( 'usof_options_' . US_THEMENAME, [] );
		$impreza = wpf_ripristina_segreti( $impreza, $ora );
		foreach ( WPF_IMPREZA_STATO as $nome ) {
			unset( $impreza[ $nome ] ); // stato, non configurazione: resta quello del sito
		}
		foreach ( $impreza as $nome => $valore ) {
			if ( wpf_opzione_allegato( (string) $nome, $valore ) ) {
				// L'id viene da un altro sito: qui sarebbe un'immagine qualsiasi, o nessuna. Resta quello del sito.
				if ( (string) ( $ora[ $nome ] ?? '' ) !== (string) $valore ) {
					WP_CLI::warning( "impreza {$nome}: config/ indica l'allegato {$valore} di un altro sito, resta il valore di questo: caricalo dal pannello" );
				}
				$impreza[ $nome ] = $ora[ $nome ] ?? '';
				continue;
			}
			$id = $risolvi( $valore );
			if ( $id !== null ) {
				// stesso tipo del valore sul sito: Impreza salva gli id come numeri
				$impreza[ $nome ] = $id ? ( is_string( $ora[ $nome ] ?? null ) ? (string) $id : $id ) : ( $ora[ $nome ] ?? '' );
			}
		}
		// Come le licenze: la chiave del .env non si applica a un sito locale
		$chiave_manutenzione = wpf_url_locale() ? '' : getenv( 'IMPREZA_MAINTENANCE_KEY' );
		if ( $chiave_manutenzione ) {
			$impreza['maintenance_private_key'] = $chiave_manutenzione;
		}
		$nuove   = array_merge( $ora, $impreza );
		$diverse = array_keys( array_filter( $impreza, fn( $v, $k ) => wpf_json( $ora[ $k ] ?? null ) !== wpf_json( $v ), ARRAY_FILTER_USE_BOTH ) );
		foreach ( $diverse as $nome ) {
			$diff( "impreza: {$nome}", $ora[ $nome ] ?? null, $impreza[ $nome ] );
		}
		if ( $diverse && ! $prova ) {
			usof_backup(); // backup nativo di Impreza, ripristinabile dal pannello delle Theme Options
			usof_save_options( $nuove ); // rigenera anche il CSS del tema
		}

		// Google Fonts serviti dal sito: Impreza li scarica solo quando si apre la pagina delle Theme
		// Options, quindi su un sito ricreato da script lo fa l'apply, con la stessa funzione
		if ( function_exists( 'us_get_local_google_fonts_state' ) && us_get_option( 'store_gfonts_locally' )
			&& ! us_get_local_google_fonts_state()['is_current'] ) {
			$diff( 'impreza: Google Fonts in locale', 'da scaricare', 'scaricati' );
			if ( ! $prova ) {
				for ( $giro = 0; $giro < WPF_GFONTS_GIRI; $giro++ ) {
					$esito = us_download_local_google_fonts();
					if ( $esito === false || empty( $esito['remaining'] ) ) {
						break;
					}
				}
				if ( $esito === false || ! us_get_local_google_fonts_state()['is_current'] ) {
					// Non blocca il segno: la configurazione è applicata, e ritentare l'apply a ogni avvio
					// riscriverebbe tutto il resto. I font si scaricano aprendo le Theme Options.
					WP_CLI::warning( 'Google Fonts non scaricati: il sito usa i font di ripiego finché non si aprono le Theme Options' );
				}
			}
		}
	}
}

// Traduzioni dei testi delle Theme Options e delle altre opzioni: WPML registra le stringhe originali
// leggendo le opzioni, quindi vanno dopo le Theme Options
if ( $wpml ) {
	$ok = wpf_wpml_stringhe_applica( (array) ( $wpml['stringhe'] ?? [] ), $prova, $diff ) && $ok;
}

// theme_mods del tema attivo e posizioni dei menu
$child = $leggi( 'child.json' );
if ( $child !== null ) {
	foreach ( $child as $nome => $valore ) {
		$ora    = get_theme_mod( $nome );
		$valore = wpf_ripristina_segreti( $valore, $ora );
		if ( wpf_json( $ora ) === wpf_json( $valore ) ) {
			continue;
		}
		$diff( "tema: {$nome}", $ora, $valore );
		if ( ! $prova ) {
			set_theme_mod( $nome, $valore );
		}
	}
}
$posizioni = [];
foreach ( (array) ( $menu_cfg['posizioni'] ?? [] ) as $posizione => $chiave ) {
	if ( isset( $id_menu[ $chiave ] ) ) {
		$posizioni[ $posizione ] = $id_menu[ $chiave ];
	} else {
		WP_CLI::warning( "posizione {$posizione}: il menu {$chiave} non c'è" );
		$ok = false;
	}
}
$posizioni_ora = get_nav_menu_locations();
// Senza posizioni nel repo quelle del sito restano: un config/ che non ne dice nulla non le azzera
if ( $posizioni && wpf_json( $posizioni_ora ) !== wpf_json( array_replace( $posizioni_ora, $posizioni ) ) ) {
	$posizioni = array_replace( $posizioni_ora, $posizioni );
	$diff( 'posizioni dei menu', $posizioni_ora, $posizioni );
	if ( ! $prova ) {
		set_theme_mod( 'nav_menu_locations', $posizioni );
	}
}

// Impostazioni del sito
$sito = $leggi( 'sito.json' );
foreach ( (array) $sito as $nome => $valore ) {
	if ( $valore === null ) {
		continue; // opzione assente sul sito dell'export: non c'è nulla da applicare
	}
	$id = $risolvi( $valore );
	if ( $id === 0 ) {
		continue; // riferimento non risolto (già segnalato): resta il valore del sito
	}
	if ( $id !== null ) {
		$valore = $id;
	}
	if ( (string) get_option( $nome ) === (string) $valore ) {
		continue;
	}
	$diff( "sito: {$nome}", get_option( $nome ), $valore );
	if ( ! $prova ) {
		update_option( $nome, $valore );
		// Con i permalink «belli» serve .htaccess: da WP-CLI flush_rewrite_rules non lo scrive
		if ( $nome === 'permalink_structure' && ! wpf_scrivi_htaccess() ) {
			WP_CLI::warning( 'permalink cambiati ma .htaccess non scritto: le pagine rispondono 404 finché non si salvano i permalink dal pannello' );
			$ok = false;
		}
	}
}

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
