<?php
/**
 * Apply dei menu di menu.json, con le traduzioni WPML (menu e singole voci) e i metadati delle voci
 * (mega menu e pulsanti di Impreza) (oc:8717).
 */

/**
 * I menu originali vengono prima delle traduzioni: le voci di un menu tradotto si collegano alle voci
 * dell'originale già a posto. Se le voci di un originale vengono ricreate, il legame delle voci tradotte
 * si rompe, il confronto del menu tradotto lo vede e ricrea anche quelle.
 *
 * @param array    $menu    elenco «menu» di menu.json
 * @param bool     $prova   vero: non scrive, segnala solo le differenze
 * @param callable $diff    fn( string $voce, $prima, $dopo )
 * @param callable $risolvi fn( $valore ): ?int, id del post di un riferimento «@chiave:…»
 * @param array    $id_menu riempito con chiave del menu => term_id
 * @return bool falso se qualcosa non è riuscito
 */
function wpf_menu_applica( array $menu, bool $prova, callable $diff, callable $risolvi, array &$id_menu ): bool {
	$ok = true;
	foreach ( wpf_originali_prima( $menu ) as $m ) {
		$termine  = wpf_menu_per_chiave( $m['chiave'] ) ?? wpf_menu_ricollega( $m, $prova, $diff );
		$voci_ora = $termine ? wpf_voci_menu( $termine->term_id ) : [];
		// Le voci di config/ hanno @segreto al posto dei segreti: si confrontano nella stessa forma
		$scarto         = [];
		$voci_confronto = wpf_togli_segreti( $voci_ora, $scarto );
		// Un config/ esportato prima che le voci avessero questi campi vale come se fossero vuoti
		$voci_cfg    = array_map( fn( $v ) => $v + [ 'xfn' => '', 'meta' => [], 'originale' => null ], $m['voci'] );
		$voci_uguali = $termine && wpf_json( $voci_confronto ) === wpf_json( $voci_cfg );
		// Anche lo slug conta: l'header di Impreza richiama il menu per slug («source»: «main-menu»)
		$nome_uguale = $termine && $termine->name === $m['nome'] && $termine->slug === $m['slug'];
		if ( $termine ) {
			$id_menu[ $m['chiave'] ] = $termine->term_id;
		}
		if ( $voci_uguali && $nome_uguale ) {
			continue;
		}
		if ( $termine && ! $nome_uguale ) {
			$diff( "menu {$m['chiave']}: nome e slug", "{$termine->name} ({$termine->slug})", "{$m['nome']} ({$m['slug']})" );
		}
		if ( ! $voci_uguali ) {
			$diff(
				"menu {$m['chiave']}",
				$termine ? implode( ', ', array_column( $voci_ora, 'titolo' ) ) . ' (voci diverse)' : 'assente',
				implode( ', ', array_column( $voci_cfg, 'titolo' ) )
			);
		}
		if ( $prova ) {
			continue;
		}
		if ( ! $termine ) {
			$termine = wpf_menu_crea( $m );
			if ( ! $termine ) {
				$ok = false;
				continue;
			}
		} elseif ( ! $nome_uguale ) {
			$aggiornato = wp_update_term( $termine->term_id, 'nav_menu', [ 'name' => $m['nome'], 'slug' => $m['slug'] ] );
			if ( is_wp_error( $aggiornato ) ) {
				// Per esempio lo slug è già di un altro menu: le voci si riallineano lo stesso
				WP_CLI::warning( "menu {$m['chiave']}: nome o slug non aggiornati, " . $aggiornato->get_error_message() );
				$ok = false;
			}
		}
		$id_menu[ $m['chiave'] ] = $termine->term_id;
		$originale               = $m['originale'] ? wpf_menu_per_chiave( $m['originale'] ) : null;
		wpf_wpml_collega( (int) $termine->term_taxonomy_id, 'tax_nav_menu', $m['lingua'], $originale ? (int) $originale->term_taxonomy_id : null );
		if ( ! $voci_uguali ) {
			// Un segreto tolto dall'export resta quello della voce nella stessa posizione sul sito
			$voci_cfg  = array_map( fn( $v, $i ) => [ 'meta' => wpf_ripristina_segreti( $v['meta'], $voci_ora[ $i ]['meta'] ?? [] ) ] + $v, $voci_cfg, array_keys( $voci_cfg ) );
			$voci_orig = $originale ? array_map( fn( $v ) => (int) $v->ID, wpf_voci_db( $originale->term_id ) ) : [];
			$ok        = wpf_menu_ricrea_voci( $termine, $m, $voci_cfg, $risolvi, $voci_orig ) && $ok;
		}
	}
	return $ok;
}

/** Menu che esisteva prima della configurazione versionata: si ricollega dallo slug. */
function wpf_menu_ricollega( array $m, bool $prova, callable $diff ): ?WP_Term {
	$candidato = get_term_by( 'slug', $m['slug'], 'nav_menu' );
	if ( ! $candidato || get_term_meta( $candidato->term_id, WPF_META_CHIAVE, true ) ) {
		return null;
	}
	$diff( "menu {$m['chiave']}", "senza chiave ({$candidato->slug})", 'ricollegato' );
	if ( ! $prova ) {
		update_term_meta( $candidato->term_id, WPF_META_CHIAVE, $m['chiave'] );
	}
	return $candidato;
}

function wpf_menu_crea( array $m ): ?WP_Term {
	$nuovo = wp_create_nav_menu( $m['nome'] );
	if ( is_wp_error( $nuovo ) ) {
		WP_CLI::warning( "menu {$m['slug']}: " . $nuovo->get_error_message() );
		return null;
	}
	wp_update_term( $nuovo, 'nav_menu', [ 'slug' => $m['slug'] ] );
	update_term_meta( $nuovo, WPF_META_CHIAVE, $m['chiave'] );
	return get_term( $nuovo, 'nav_menu' );
}

/**
 * Sostituisce le voci del menu con quelle di config/, metadati e legami di traduzione compresi. Le voci
 * di un menu non hanno una chiave stabile: si ricreano tutte, solo quando qualcosa è cambiato. Le nuove
 * si creano prima di cancellare le vecchie: se una non si crea, le nuove si tolgono e il menu resta
 * com'era. Se l'apply viene interrotto proprio fra le due cose (tempo massimo, «docker stop»), il menu
 * resta con le voci doppie finché l'apply successivo, che vede la differenza, non lo rimette a posto.
 *
 * @param int[] $voci_orig id delle voci del menu originale, per posizione (vuoto per un originale)
 */
function wpf_menu_ricrea_voci( WP_Term $termine, array $m, array $voci, callable $risolvi, array $voci_orig ): bool {
	$vecchie = array_map( fn( $v ) => (int) $v->ID, wpf_voci_db( $termine->term_id ) );
	$creati  = []; // posizione nell'elenco => id della voce, per ricollegare i sottomenu
	foreach ( $voci as $i => $v ) {
		$args = [
			'menu-item-title'       => $v['titolo'],
			'menu-item-status'      => 'publish',
			'menu-item-target'      => $v['target'],
			'menu-item-classes'     => implode( ' ', $v['classi'] ),
			'menu-item-xfn'         => $v['xfn'],
			'menu-item-description' => $v['descrizione'],
			'menu-item-attr-title'  => $v['attr_title'],
			'menu-item-parent-id'   => $v['genitore'] !== null ? ( $creati[ $v['genitore'] ] ?? 0 ) : 0,
			'menu-item-position'    => $i + 1,
		] + wpf_menu_args_destinazione( $v['destinazione'], $m, $risolvi );
		$voce = wp_update_nav_menu_item( $termine->term_id, 0, $args );
		if ( is_wp_error( $voce ) ) {
			WP_CLI::warning( "menu {$m['chiave']}, voce «{$v['titolo']}»: " . $voce->get_error_message() . '. Il menu resta com\'era' );
			foreach ( $creati as $id ) {
				wpf_wpml_cancella_post( $id, 'post_nav_menu_item' );
			}
			return false;
		}
		$creati[ $i ] = $voce;
		foreach ( $v['meta'] as $nome => $valore ) {
			// Un segreto che il sito non aveva resta @segreto: non si scrive come testo
			if ( $valore !== WPF_SEGRETO && $valore !== null ) {
				update_post_meta( $voce, $nome, wp_slash( $valore ) );
			}
		}
		// Le voci prendono la lingua del loro menu; una voce tradotta entra nel gruppo della sua originale
		$id_originale = $v['originale'] !== null ? ( $voci_orig[ $v['originale'] ] ?? null ) : null;
		wpf_wpml_collega( (int) $voce, 'post_nav_menu_item', $m['lingua'], $id_originale );
	}
	foreach ( $vecchie as $id ) {
		wpf_wpml_cancella_post( $id, 'post_nav_menu_item' );
	}
	return true;
}

/**
 * Argomenti di wp_update_nav_menu_item per la destinazione di una voce. Una «pagina» si cerca per tipo e
 * percorso, nella lingua del menu; se sul sito non c'è (per esempio un contenuto non ancora importato) la
 * voce diventa un link al suo indirizzo, con un avviso, e il confronto successivo la ricrea quando la
 * pagina esiste.
 */
function wpf_menu_args_destinazione( array $destinazione, array $m, callable $risolvi ): array {
	$id_post = null;
	if ( $destinazione['tipo'] === 'post' ) {
		$id_post = $risolvi( $destinazione['post'] );
	} elseif ( $destinazione['tipo'] === 'pagina' ) {
		$trovato = get_page_by_path( $destinazione['percorso'], OBJECT, $destinazione['post_type'] );
		if ( $trovato ) {
			$id_post = (int) apply_filters( 'wpml_object_id', $trovato->ID, $destinazione['post_type'], true, $m['lingua'] );
		} else {
			WP_CLI::warning( "menu {$m['chiave']}: {$destinazione['post_type']} «{$destinazione['percorso']}» non c'è su questo sito, la voce è un link a {$destinazione['url']}" );
		}
	}
	if ( $id_post ) {
		return [ 'menu-item-type' => 'post_type', 'menu-item-object' => get_post_type( $id_post ), 'menu-item-object-id' => $id_post ];
	}
	return [ 'menu-item-type' => 'custom', 'menu-item-url' => $destinazione['url'] ?? '#' ];
}
