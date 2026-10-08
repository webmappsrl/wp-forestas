<?php
/**
 * Configurazione di WPML con le sue stesse API, mai con SQL sulle tabelle wp_icl_* (oc:8717).
 *
 * Sequenza provata su un WordPress nuovo: sincronizzazione del catalogo delle lingue (senza, l'API
 * risponde «missing_preset»), lingue salvate con l'endpoint della schermata «Lingue», impostazioni,
 * stato del wizard.
 */

/**
 * @param array    $cfg   contenuto di wpml.json
 * @param bool     $prova vero: non scrive, segnala solo le differenze
 * @param callable $diff  fn( string $voce, $prima, $dopo )
 * @return bool falso se qualcosa non è riuscito
 */
function wpf_wpml_applica( array $cfg, bool $prova, callable $diff ): bool {
	global $sitepress;
	if ( ! $sitepress || ! class_exists( '\WPML\LanguageEditor\Endpoint\SaveLanguages' ) ) {
		WP_CLI::warning( 'WPML non è attivo: configurazione delle lingue saltata' );
		return false;
	}

	$attive_ora   = array_keys( $sitepress->get_active_languages() );
	$attive_cfg   = array_column( $cfg['lingue'], 'code' );
	sort( $attive_ora );
	sort( $attive_cfg );
	$lingue_uguali = $attive_ora === $attive_cfg && $sitepress->get_default_language() === $cfg['predefinita'];

	$impostazioni_ora = $sitepress->get_settings();
	// I segreti restano quelli del sito; i segni di stato di WPML non si applicano mai, anche se un
	// config/ esportato prima della loro esclusione li contiene
	$impostazioni     = wpf_ripristina_segreti( wpf_togli_stato_wpml( $cfg['impostazioni'] ), $impostazioni_ora );
	// WPML aggiunge da sé sottochiavi (per esempio quando sposta impostazioni in tabelle proprie): si
	// confrontano e si scrivono solo quelle presenti nell'export, il resto resta com'è sul sito
	foreach ( $impostazioni as $nome => $valore ) {
		$impostazioni[ $nome ] = wpf_unisci( $impostazioni_ora[ $nome ] ?? null, $valore );
	}
	$impostazioni_diverse = [];
	foreach ( $impostazioni as $nome => $valore ) {
		if ( wpf_json( $impostazioni_ora[ $nome ] ?? null ) !== wpf_json( $valore ) ) {
			$impostazioni_diverse[] = $nome;
		}
	}
	$setup_diverso = wpf_json( get_option( 'WPML(setup)' ) ) !== wpf_json( $cfg['setup'] );

	if ( ! $lingue_uguali ) {
		$diff( 'lingue', implode( ',', $attive_ora ) . ' (predefinita ' . $sitepress->get_default_language() . ')', implode( ',', $attive_cfg ) . ' (predefinita ' . $cfg['predefinita'] . ')' );
	}
	foreach ( $impostazioni_diverse as $nome ) {
		$diff( "impostazione {$nome}", $impostazioni_ora[ $nome ] ?? null, $impostazioni[ $nome ] );
	}
	if ( $setup_diverso ) {
		$diff( 'stato del wizard', 'diverso', 'come nel repo' );
	}
	if ( $prova ) {
		return true;
	}

	if ( ! $lingue_uguali ) {
		if ( ! class_exists( '\WPML\LanguageEditor\Presets\CatalogueSyncRunner' ) ) {
			WP_CLI::warning( 'questa versione di WPML non ha CatalogueSyncRunner: lingue non configurate, va adeguato wpml.php' );
			return false;
		}
		\WPML\LanguageEditor\Presets\CatalogueSyncRunner::create()->run();
		$lingue = array_map(
			function ( $l ) {
				$l['presetCode'] = $l['language']; // la lingua di base da cui WPML crea quella col paese
				return $l;
			},
			$cfg['lingue']
		);
		$esito = ( new \WPML\LanguageEditor\Endpoint\SaveLanguages() )->run(
			wpml_collect( [ 'languages' => $lingue, 'defaultCode' => $cfg['predefinita'] ] )
		);
		if ( ! $esito instanceof \WPML\FP\Right ) {
			$errore = $esito->coalesce( fn( $l ) => $l, fn( $v ) => $v )->get();
			WP_CLI::warning( 'WPML ha rifiutato le lingue: ' . json_encode( $errore ) );
			return false;
		}
	}
	if ( $impostazioni_diverse ) {
		$sitepress->save_settings( $impostazioni );
	}
	if ( $setup_diverso ) {
		update_option( 'WPML(setup)', $cfg['setup'] );
	}
	return true;
}

/**
 * Unisce un'impostazione del repo a quella del sito: le chiavi dell'export prevalgono e quelle che
 * WPML aggiunge da sé restano; un elenco del repo (hidden_languages, languages_order…) sostituisce
 * quello del sito per intero, perché unito voce per voce non si accorcerebbe mai.
 */
function wpf_unisci( $sul_sito, $dal_repo ) {
	if ( ! is_array( $sul_sito ) || ! is_array( $dal_repo ) || array_is_list( $dal_repo ) ) {
		return $dal_repo;
	}
	foreach ( $dal_repo as $nome => $valore ) {
		$sul_sito[ $nome ] = wpf_unisci( $sul_sito[ $nome ] ?? null, $valore );
	}
	return $sul_sito;
}

/**
 * Tabelle che WPML String Translation crea di solito alla prima visita del pannello: senza, salvare un
 * post costruito con il builder produce errori SQL. Si usa la sua stessa procedura di aggiornamento.
 */
function wpf_wpml_prepara(): void {
	if ( class_exists( 'WPML_Package_Translation_Schema' ) ) {
		WPML_Package_Translation_Schema::run_update();
	}
}

/** Collega un elemento a WPML: lingua e, per una traduzione, il gruppo dell'originale. */
function wpf_wpml_collega( int $id, string $tipo, ?string $lingua, ?int $id_originale ): void {
	if ( ! $lingua ) {
		return;
	}
	$ora = apply_filters( 'wpml_element_language_details', null, [ 'element_id' => $id, 'element_type' => $tipo ] );
	// Una traduzione va nel gruppo del suo originale. Un originale resta nel gruppo che ha già: con
	// trid falso WPML cancellerebbe la sua riga e ne creerebbe un gruppo nuovo, staccandolo dalle
	// traduzioni (WPML_Set_Language::set, delete_existing_row + insert_new_row).
	$trid = $id_originale
		? apply_filters( 'wpml_element_trid', null, $id_originale, $tipo )
		: ( $ora->trid ?? null );
	if ( $ora && $trid && (int) $ora->trid === (int) $trid && $ora->language_code === $lingua ) {
		return; // già nella lingua e nel gruppo giusti
	}
	do_action(
		'wpml_set_element_language_details',
		[
			'element_id'           => $id,
			'element_type'         => $tipo,
			'trid'                 => $trid ?: false,
			'language_code'        => $lingua,
			'source_language_code' => $trid ? apply_filters( 'wpml_default_language', null ) : null,
		]
	);
}
