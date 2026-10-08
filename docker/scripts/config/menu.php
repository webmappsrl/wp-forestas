<?php
/**
 * Apply dei menu di menu.json, con le traduzioni WPML e i metadati delle voci (mega menu e pulsanti di
 * Impreza) (oc:8717).
 */

/**
 * @param array    $menu     elenco «menu» di menu.json
 * @param bool     $prova    vero: non scrive, segnala solo le differenze
 * @param callable $diff     fn( string $voce, $prima, $dopo )
 * @param callable $risolvi  fn( $valore ): ?int, id del post di un riferimento «@chiave:…»
 * @param array    $id_menu  riempito con chiave del menu => term_id
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
		// Un config/ esportato prima che le voci avessero xfn e metadati vale come se fossero vuoti
		$voci_cfg    = array_map( fn( $v ) => $v + [ 'xfn' => '', 'meta' => [] ], $m['voci'] );
		$voci_uguali = $termine && wpf_json( $voci_confronto ) === wpf_json( $voci_cfg );
		// Anche lo slug conta: l'header di Impreza richiama il menu per slug («source»: «main-menu»)
		$nome_uguale = $termine && $termine->name === $m['nome'] && $termine->slug === $m['slug'];
		if ( $voci_uguali && $nome_uguale ) {
			$id_menu[ $m['chiave'] ] = $termine->term_id;
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
			if ( $termine ) {
				$id_menu[ $m['chiave'] ] = $termine->term_id;
			}
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
		if ( ! $voci_uguali ) {
			// Un segreto tolto dall'export resta quello della voce nella stessa posizione sul sito
			$voci_cfg = array_map( fn( $v, $i ) => [ 'meta' => wpf_ripristina_segreti( $v['meta'], $voci_ora[ $i ]['meta'] ?? [] ) ] + $v, $voci_cfg, array_keys( $voci_cfg ) );
			$ok       = wpf_menu_ricrea_voci( $termine, $m, $voci_cfg, $risolvi ) && $ok;
		}
		$originale = $m['originale'] ? wpf_menu_per_chiave( $m['originale'] ) : null;
		wpf_wpml_collega( (int) $termine->term_taxonomy_id, 'tax_nav_menu', $m['lingua'], $originale ? (int) $originale->term_taxonomy_id : null );
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
 * Sostituisce le voci del menu con quelle di config/, metadati compresi. Le voci di un menu non hanno
 * una chiave stabile: si ricreano tutte, solo quando qualcosa è cambiato.
 */
function wpf_menu_ricrea_voci( WP_Term $termine, array $m, array $voci, callable $risolvi ): bool {
	$ok = true;
	foreach ( (array) wp_get_nav_menu_items( $termine->term_id, [ 'post_status' => 'any' ] ) as $v ) {
		wp_delete_post( $v->ID, true );
	}
	$creati = []; // posizione nell'elenco => id della voce, per ricollegare i sottomenu
	foreach ( $voci as $i => $v ) {
		$args         = [
			'menu-item-title'       => $v['titolo'],
			'menu-item-status'      => 'publish',
			'menu-item-target'      => $v['target'],
			'menu-item-classes'     => implode( ' ', $v['classi'] ),
			'menu-item-xfn'         => $v['xfn'],
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
		foreach ( $v['meta'] as $nome => $valore ) {
			// Un segreto che il sito non aveva resta @segreto: non si scrive come testo
			if ( $valore !== WPF_SEGRETO && $valore !== null ) {
				update_post_meta( $voce, $nome, wp_slash( $valore ) );
			}
		}
		// Le voci prendono la lingua del loro menu, altrimenti WPML le vede senza lingua
		wpf_wpml_collega( (int) $voce, 'post_nav_menu_item', $m['lingua'], null );
	}
	return $ok;
}
