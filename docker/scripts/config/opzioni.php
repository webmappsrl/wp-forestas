<?php
/**
 * Apply delle opzioni: Theme Options di Impreza (con i Google Fonts), theme_mods del tema attivo,
 * posizioni dei menu e impostazioni del sito (oc:8717). Stesso schema di post.php e menu.php: ogni
 * funzione restituisce falso se qualcosa non è riuscito.
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';

/**
 * Theme Options di Impreza: riferimenti «@chiave:» risolti, segreti, stato del pannello e allegati
 * lasciati come sono sul sito.
 *
 * @param callable $risolvi fn( $valore ): ?int, id del post di un riferimento «@chiave:…»
 */
function wpf_impreza_applica( array $impreza, bool $prova, callable $diff, callable $risolvi ): bool {
	// Funzioni interne di UpSolution Core: se un aggiornamento ne toglie una, ci si ferma prima di scrivere
	foreach ( [ 'usof_save_options', 'usof_backup', 'us_get_option' ] as $funzione ) {
		if ( ! function_exists( $funzione ) || ! defined( 'US_THEMENAME' ) ) {
			WP_CLI::warning( "UpSolution Core non è attivo o non ha {$funzione}(): Theme Options di Impreza saltate" );
			return false;
		}
	}
	$ora     = (array) get_option( wpf_nome_theme_options(), [] );
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
			// Stesso tipo del valore sul sito: Impreza salva gli id come numeri. Riferimento non risolto
			// (già segnalato): resta il valore del sito.
			$impreza[ $nome ] = $id ? ( is_string( $ora[ $nome ] ?? null ) ? (string) $id : $id ) : ( $ora[ $nome ] ?? '' );
		}
	}
	// Come le licenze: la chiave del .env non si applica a un sito locale
	$chiave_manutenzione = wpf_sito_locale() ? '' : getenv( 'IMPREZA_MAINTENANCE_KEY' );
	if ( $chiave_manutenzione ) {
		$impreza['maintenance_private_key'] = $chiave_manutenzione;
	}
	$diverse = array_keys( array_filter( $impreza, fn( $v, $k ) => wpf_diverso( $ora[ $k ] ?? null, $v ), ARRAY_FILTER_USE_BOTH ) );
	foreach ( $diverse as $nome ) {
		$diff( "impreza: {$nome}", $ora[ $nome ] ?? null, $impreza[ $nome ] );
	}
	if ( $diverse && ! $prova ) {
		usof_backup(); // backup nativo di Impreza, ripristinabile dal pannello delle Theme Options
		usof_save_options( array_merge( $ora, $impreza ) ); // rigenera anche il CSS del tema
	}
	return wpf_google_fonts_applica( $prova, $diff );
}

/**
 * Google Fonts serviti dal sito: Impreza li scarica solo quando si apre la pagina delle Theme Options,
 * quindi su un sito ricreato da script li scarica l'apply, con la stessa funzione. Se il download non
 * riesce (per esempio senza rete verso Google) è un avviso, non un errore: la configurazione è applicata,
 * e a ogni avvio l'init ricorda di rilanciare l'apply, che riprova solo i font.
 */
function wpf_google_fonts_applica( bool $prova, callable $diff ): bool {
	if ( ! function_exists( 'us_get_local_google_fonts_state' ) || ! function_exists( 'us_download_local_google_fonts' )
		|| ! us_get_option( 'store_gfonts_locally' ) || us_get_local_google_fonts_state()['is_current'] ) {
		return true;
	}
	$diff( 'impreza: Google Fonts in locale', 'da scaricare', 'scaricati' );
	if ( $prova ) {
		return true;
	}
	$esito = false;
	for ( $giro = 0; $giro < WPF_GFONTS_GIRI; $giro++ ) {
		$esito = us_download_local_google_fonts();
		if ( $esito === false || empty( $esito['remaining'] ) ) {
			break;
		}
	}
	if ( $esito === false || ! us_get_local_google_fonts_state()['is_current'] ) {
		WP_CLI::warning( 'Google Fonts non scaricati: il sito usa i font di ripiego. Rilancia l\'apply quando la rete risponde, o apri le Theme Options' );
	}
	return true;
}

/** theme_mods del tema attivo (child.json): segreti lasciati come sono sul sito. */
function wpf_child_applica( array $child, bool $prova, callable $diff ): bool {
	foreach ( $child as $nome => $valore ) {
		$ora    = get_theme_mod( $nome );
		$valore = wpf_ripristina_segreti( $valore, $ora );
		if ( ! wpf_diverso( $ora, $valore ) ) {
			continue;
		}
		$diff( "tema: {$nome}", $ora, $valore );
		if ( ! $prova ) {
			set_theme_mod( $nome, $valore );
		}
	}
	return true;
}

/**
 * Posizioni dei menu: chiave del menu → posizione del tema. Senza posizioni nel repo quelle del sito
 * restano: un config/ che non ne dice nulla non le azzera.
 *
 * @param array $id_menu chiave del menu => term_id, da wpf_menu_applica()
 */
function wpf_posizioni_applica( array $posizioni_cfg, array $id_menu, bool $prova, callable $diff ): bool {
	$ok        = true;
	$posizioni = [];
	foreach ( $posizioni_cfg as $posizione => $chiave ) {
		if ( isset( $id_menu[ $chiave ] ) ) {
			$posizioni[ $posizione ] = $id_menu[ $chiave ];
		} else {
			WP_CLI::warning( "posizione {$posizione}: il menu {$chiave} non c'è" );
			$ok = false;
		}
	}
	$ora   = get_nav_menu_locations();
	$nuove = array_replace( $ora, $posizioni );
	if ( $posizioni && wpf_diverso( $ora, $nuove ) ) {
		$diff( 'posizioni dei menu', $ora, $nuove );
		if ( ! $prova ) {
			set_theme_mod( 'nav_menu_locations', $nuove );
		}
	}
	return $ok;
}

/**
 * Impostazioni del sito (sito.json): titolo, permalink, home statica. Un valore null indica un'opzione
 * che sul sito dell'export non c'era; un riferimento non risolto (già segnalato) lascia il valore del
 * sito.
 */
function wpf_sito_applica( array $sito, bool $prova, callable $diff, callable $risolvi ): bool {
	$ok = true;
	foreach ( $sito as $nome => $valore ) {
		if ( ! in_array( $nome, WPF_OPZIONI_SITO, true ) ) {
			// Solo le opzioni che l'export scrive: un sito.json modificato con «siteurl» o «home»
			// sposterebbe il sito su un altro indirizzo
			WP_CLI::warning( "sito.json: {$nome} non è fra le impostazioni gestite (WPF_OPZIONI_SITO), la salto" );
			$ok = false;
			continue;
		}
		if ( $valore === null ) {
			continue;
		}
		$id = $risolvi( $valore );
		if ( $id === 0 ) {
			continue;
		}
		if ( $id !== null ) {
			$valore = $id;
		}
		if ( (string) get_option( $nome ) === (string) $valore ) {
			continue;
		}
		$diff( "sito: {$nome}", get_option( $nome ), $valore );
		if ( $prova ) {
			continue;
		}
		update_option( $nome, $valore );
		// Con i permalink «belli» serve .htaccess: da WP-CLI flush_rewrite_rules non lo scrive
		if ( $nome === 'permalink_structure' && ! wpf_scrivi_htaccess() ) {
			WP_CLI::warning( 'permalink cambiati ma .htaccess non scritto: le pagine rispondono 404 finché non si salvano i permalink dal pannello' );
			$ok = false;
		}
	}
	return $ok;
}
