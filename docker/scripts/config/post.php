<?php
/**
 * Apply dei post di configurazione di post.json: header, Page Block, Content Template, Grid Layout di
 * Impreza e pagine a cui puntano le opzioni (oc:8717).
 */

/**
 * @param array    $post_cfg contenuto di post.json
 * @param bool     $prova    vero: non scrive, segnala solo le differenze
 * @param callable $diff     fn( string $voce, $prima, $dopo )
 * @return bool falso se qualcosa non è riuscito
 */
function wpf_post_applica( array $post_cfg, bool $prova, callable $diff ): bool {
	$ok    = true;
	$viste = [];
	foreach ( wpf_originali_prima( $post_cfg ) as $p ) {
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
		$esistente = wpf_post_per_chiave( $p['chiave'] ) ?? wpf_post_ricollega( $p, $prova, $diff );
		$campi     = [
			'post_type'    => $p['post_type'],
			'post_title'   => $p['post_title'],
			'post_name'    => $p['post_name'],
			'post_status'  => $p['post_status'],
			'post_content' => $p['post_content'],
			'post_excerpt' => $p['post_excerpt'],
			'menu_order'   => $p['menu_order'],
		];
		// Segreti tolti dall'export e valori ricalcolati dai plugin restano quelli del sito
		$meta = array_filter(
			$p['meta'],
			fn( $valore, $nome ) => $valore !== WPF_SEGRETO && ! in_array( $nome, WPF_META_CALCOLATI, true ),
			ARRAY_FILTER_USE_BOTH
		);
		if ( $esistente ) {
			$diversi = [];
			foreach ( $campi as $nome => $valore ) {
				if ( (string) $esistente->$nome !== (string) $valore ) {
					$diversi[] = $nome;
				}
			}
			foreach ( $meta as $nome => $valore ) {
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
		// wp_insert_post toglie le barre: il contenuto del builder di Impreza contiene JSON. Il salvataggio
		// fa ricalcolare a UpSolution Core e WPML i metadati di WPF_META_CALCOLATI.
		$campi = wp_slash( $campi );
		$id    = $esistente ? wp_update_post( $campi + [ 'ID' => $esistente->ID ], true ) : wp_insert_post( $campi, true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( "post {$p['chiave']}: " . $id->get_error_message() );
			$ok = false;
			continue;
		}
		update_post_meta( $id, WPF_META_CHIAVE, $p['chiave'] );
		foreach ( $meta as $nome => $valore ) {
			update_post_meta( $id, $nome, wp_slash( $valore ) );
		}
		$originale = $p['originale'] ? wpf_post_per_chiave( $p['originale'] ) : null;
		wpf_wpml_collega( $id, 'post_' . $p['post_type'], $p['lingua'], $originale ? $originale->ID : null );
	}
	return $ok;
}

/**
 * Sito che esisteva prima della configurazione versionata (UAT alla prima messa in opera): il post c'è
 * già, senza chiave, con lo stesso tipo e slug. Si ricollega invece di crearne una copia.
 */
function wpf_post_ricollega( array $p, bool $prova, callable $diff ): ?WP_Post {
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
	if ( ! $candidato || get_post_meta( $candidato->ID, WPF_META_CHIAVE, true ) ) {
		return null;
	}
	$diff( "post {$p['chiave']}", "senza chiave (id {$candidato->ID})", 'ricollegato' );
	if ( ! $prova ) {
		update_post_meta( $candidato->ID, WPF_META_CHIAVE, $p['chiave'] );
	}
	return $candidato;
}
