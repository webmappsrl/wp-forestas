<?php
/**
 * Apply dei menu di menu.json, con le traduzioni WPML (menu e singole voci) e i metadati delle voci
 * (mega menu e pulsanti di Impreza) (oc:8717).
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';

// Metadati standard delle voci di menu: già rappresentati dai campi di wpf_voci_menu(). Gli altri (mega
// menu e pulsante di Impreza, «us_mega_menu_settings», «_menu_item_btn_style»…) si esportano a parte.
const WPF_META_VOCE_STANDARD = [ '_menu_item_type', '_menu_item_menu_item_parent', '_menu_item_object_id', '_menu_item_object', '_menu_item_target', '_menu_item_classes', '_menu_item_xfn', '_menu_item_url', '_menu_item_orphaned' ];
// Altri metadati delle voci che non si esportano: per una voce di menu nessun «_wp_…» è un'impostazione
const WPF_META_ESCLUSI_VOCE = '/^(_edit_|_wp_|_wpml|_icl_)/';

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
		$voci_uguali = $termine && ! wpf_diverso( $voci_confronto, $voci_cfg );
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
	$slug = wp_update_term( $nuovo, 'nav_menu', [ 'slug' => $m['slug'] ] );
	if ( is_wp_error( $slug ) || get_term( $nuovo, 'nav_menu' )->slug !== $m['slug'] ) {
		// Lo slug è già di un altro menu (con un'altra chiave): l'header lo richiama per slug, quindi un
		// menu con uno slug diverso non servirebbe. Si toglie e lo si segnala.
		WP_CLI::warning( "menu {$m['chiave']}: lo slug «{$m['slug']}» è già di un altro menu, il menu non è stato creato" );
		wp_delete_nav_menu( $nuovo );
		return null;
	}
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
