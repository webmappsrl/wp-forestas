<?php
/**
 * Apply della configurazione esportata (oc:8717).
 *
 * Uso: WPF_CONFIG_DIR=/opt/wp-forestas/config [WPF_PROVA=1] wp eval-file /usr/local/lib/wp-forestas/apply.php
 * Con WPF_PROVA=1 non scrive nulla e stampa una riga per differenza. Senza, applica nell'ordine WPML,
 * post, menu, opzioni, e solo se tutto riesce scrive l'opzione wp_forestas_config_applicata.
 * Lanciato due volte non duplica nulla: post e menu si ritrovano dalla chiave stabile
 * (_wp_forestas_chiave); lo slug serve solo a ricollegare quelli di un sito nato prima di config/.
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';

$dir   = getenv( 'WPF_CONFIG_DIR' ) ?: '/opt/wp-forestas/config';
$prova = getenv( 'WPF_PROVA' ) === '1';
$ok    = true;
$righe = 0;

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
	WP_CLI::log( "{$voce}: {$breve( $prima )} → {$breve( $dopo )}" );
};

if ( ! glob( "{$dir}/*.json" ) ) {
	WP_CLI::log( "nessuna configurazione in {$dir}" );
	return;
}

// Versioni da cui è stato fatto l'export: se il sito ha versioni diverse lo si dice, senza fermarsi
foreach ( (array) $leggi( 'versioni.json' ) as $nome => $versione ) {
	$sul_sito = wpf_versioni()[ $nome ] ?? null;
	if ( $sul_sito !== $versione ) {
		WP_CLI::warning( "config/ è stato esportato con {$nome} {$versione}, il sito ha " . ( $sul_sito ?? 'nessuna versione' ) . ': controlla le differenze prima di confermare' );
	}
}

// --- 1. WPML ----------------------------------------------------------------------------------

$wpml = $leggi( 'wpml.json' );
if ( $wpml ) {
	$ok = wpf_wpml_applica( $wpml, $prova, fn( $v, $p, $d ) => $diff( "wpml: {$v}", $p, $d ) ) && $ok;
}

// --- 2. Post: originali prima delle traduzioni ----------------------------------------------------

if ( ! $prova ) {
	wpf_wpml_prepara();
}

$post_cfg = wpf_originali_prima( (array) $leggi( 'post.json' ) );
$viste    = [];
foreach ( $post_cfg as $p ) {
	if ( isset( $viste[ $p['chiave'] ] ) ) {
		// Due post con la stessa chiave: applicarli entrambi sovrascriverebbe il primo con il secondo
		WP_CLI::warning( "post.json contiene due volte la chiave {$p['chiave']}: la seconda voce è saltata, rifai l'export" );
		$ok = false;
		continue;
	}
	$viste[ $p['chiave'] ] = true;
	if ( ! post_type_exists( $p['post_type'] ) ) {
		// Il plugin che registra il tipo è spento: creare il post adesso lo duplicherebbe
		WP_CLI::warning( "post {$p['chiave']}: il tipo {$p['post_type']} non è registrato, lo salto" );
		$ok = false;
		continue;
	}
	$esistente = wpf_post_per_chiave( $p['chiave'] );
	if ( ! $esistente ) {
		// Sito che esisteva prima della configurazione versionata (UAT alla prima messa in opera): il
		// post c'è già, senza chiave. Si ricollega invece di crearne una copia.
		$candidati = get_posts(
			[
				'post_type'        => $p['post_type'],
				'name'             => $p['post_name'],
				'post_status'      => 'any',
				'numberposts'      => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);
		$candidato = $candidati[0] ?? null;
		if ( $candidato && ! get_post_meta( $candidato->ID, WPF_META_CHIAVE, true ) ) {
			$diff( "post {$p['chiave']}", "senza chiave (id {$candidato->ID})", 'ricollegato' );
			if ( ! $prova ) {
				update_post_meta( $candidato->ID, WPF_META_CHIAVE, $p['chiave'] );
			}
			$esistente = $candidato;
		}
	}
	$campi     = [
		'post_type'    => $p['post_type'],
		'post_title'   => $p['post_title'],
		'post_name'    => $p['post_name'],
		'post_status'  => $p['post_status'],
		'post_content' => $p['post_content'],
		'post_excerpt' => $p['post_excerpt'],
		'menu_order'   => $p['menu_order'],
	];
	$diversi = [];
	if ( $esistente ) {
		foreach ( $campi as $nome => $valore ) {
			if ( (string) $esistente->$nome !== (string) $valore ) {
				$diversi[] = $nome;
			}
		}
		foreach ( $p['meta'] as $nome => $valore ) {
			if ( $valore === WPF_SEGRETO ) {
				continue; // segreto tolto dall'export: resta quello del sito
			}
			if ( wpf_json( get_post_meta( $esistente->ID, $nome, true ) ) !== wpf_json( $valore ) ) {
				$diversi[] = "meta {$nome}";
			}
		}
		if ( ! $diversi ) {
			continue;
		}
		$diff( "post {$p['chiave']}", 'diverso in ' . implode( ', ', $diversi ), 'come nel repo' );
	} else {
		$diff( "post {$p['chiave']}", 'assente', "creato ({$p['post_type']})" );
	}
	if ( $prova ) {
		continue;
	}
	// wp_insert_post toglie le barre: il contenuto del builder di Impreza contiene JSON
	$campi = wp_slash( $campi );
	$id    = $esistente ? wp_update_post( $campi + [ 'ID' => $esistente->ID ], true ) : wp_insert_post( $campi, true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::warning( "post {$p['chiave']}: " . $id->get_error_message() );
		$ok = false;
		continue;
	}
	update_post_meta( $id, WPF_META_CHIAVE, $p['chiave'] );
	foreach ( $p['meta'] as $nome => $valore ) {
		if ( $valore !== WPF_SEGRETO ) {
			update_post_meta( $id, $nome, wp_slash( $valore ) );
		}
	}
	$originale = $p['originale'] ? wpf_post_per_chiave( $p['originale'] ) : null;
	wpf_wpml_collega( $id, 'post_' . $p['post_type'], $p['lingua'], $originale ? $originale->ID : null );
}

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

// --- 3. Menu: originali prima delle traduzioni, voci ricreate solo se diverse ------------------------

$menu_cfg = (array) $leggi( 'menu.json' );
$menu     = wpf_originali_prima( (array) ( $menu_cfg['menu'] ?? [] ) );
$id_menu  = []; // chiave => term_id
foreach ( $menu as $m ) {
	$termine = wpf_menu_per_chiave( $m['chiave'] );
	if ( ! $termine ) {
		// Menu che esisteva prima della configurazione versionata: si ricollega dallo slug
		$candidato = get_term_by( 'slug', $m['slug'], 'nav_menu' );
		if ( $candidato && ! get_term_meta( $candidato->term_id, WPF_META_CHIAVE, true ) ) {
			$diff( "menu {$m['chiave']}", "senza chiave ({$candidato->slug})", 'ricollegato' );
			if ( ! $prova ) {
				update_term_meta( $candidato->term_id, WPF_META_CHIAVE, $m['chiave'] );
			}
			$termine = $candidato;
		}
	}
	$voci_ora = $termine ? wpf_voci_menu( $termine->term_id ) : [];
	$uguali   = $termine && wpf_json( $voci_ora ) === wpf_json( $m['voci'] ) && $termine->name === $m['nome'];
	$voci_cfg = array_column( $m['voci'], 'titolo' );
	if ( $termine ) {
		$id_menu[ $m['chiave'] ] = $termine->term_id;
	}
	if ( $uguali ) {
		continue;
	}
	$diff( "menu {$m['chiave']}", $termine ? implode( ', ', array_column( $voci_ora, 'titolo' ) ) . ' (voci diverse)' : 'assente', implode( ', ', $voci_cfg ) );
	if ( $prova ) {
		continue;
	}
	if ( ! $termine ) {
		$nuovo = wp_create_nav_menu( $m['nome'] );
		if ( is_wp_error( $nuovo ) ) {
			WP_CLI::warning( "menu {$m['slug']}: " . $nuovo->get_error_message() );
			$ok = false;
			continue;
		}
		wp_update_term( $nuovo, 'nav_menu', [ 'slug' => $m['slug'] ] );
		update_term_meta( $nuovo, WPF_META_CHIAVE, $m['chiave'] );
		$termine = get_term( $nuovo, 'nav_menu' );
	} else {
		wp_update_term( $termine->term_id, 'nav_menu', [ 'name' => $m['nome'] ] );
		foreach ( (array) wp_get_nav_menu_items( $termine->term_id, [ 'post_status' => 'any' ] ) as $v ) {
			wp_delete_post( $v->ID, true );
		}
	}
	$id_menu[ $m['chiave'] ] = $termine->term_id;
	$creati                  = [];
	foreach ( $m['voci'] as $i => $v ) {
		$args = [
			'menu-item-title'       => $v['titolo'],
			'menu-item-status'      => 'publish',
			'menu-item-target'      => $v['target'],
			'menu-item-classes'     => implode( ' ', $v['classi'] ),
			'menu-item-description' => $v['descrizione'],
			'menu-item-attr-title'  => $v['attr_title'],
			'menu-item-parent-id'   => $v['genitore'] !== null ? ( $creati[ $v['genitore'] ] ?? 0 ) : 0,
			'menu-item-position'    => $i + 1,
		];
		$destinazione = $v['destinazione'];
		$id_post      = $destinazione['tipo'] === 'post' ? $risolvi( $destinazione['post'] ) : null;
		if ( $id_post ) {
			$args += [ 'menu-item-type' => 'post_type', 'menu-item-object' => get_post_type( $id_post ), 'menu-item-object-id' => $id_post ];
		} else {
			$args += [ 'menu-item-type' => 'custom', 'menu-item-url' => $destinazione['url'] ?? '#' ];
		}
		$voce = wp_update_nav_menu_item( $termine->term_id, 0, $args );
		if ( is_wp_error( $voce ) ) {
			WP_CLI::warning( "menu {$m['chiave']}, voce «{$v['titolo']}»: " . $voce->get_error_message() );
			$ok = false;
			continue;
		}
		$creati[ $i ] = $voce;
		// Le voci prendono la lingua del loro menu, altrimenti WPML le vede senza lingua
		wpf_wpml_collega( (int) $voce, 'post_nav_menu_item', $m['lingua'], null );
	}
	$originale = $m['originale'] ? wpf_menu_per_chiave( $m['originale'] ) : null;
	wpf_wpml_collega( (int) $termine->term_taxonomy_id, 'tax_nav_menu', $m['lingua'], $originale ? (int) $originale->term_taxonomy_id : null );
}

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
		if ( $nome === 'permalink_structure' ) {
			flush_rewrite_rules();
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
