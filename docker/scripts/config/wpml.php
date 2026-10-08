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

	$attive_ora = array_keys( $sitepress->get_active_languages() );
	$attive_cfg = array_column( $cfg['lingue'], 'code' );
	sort( $attive_ora );
	sort( $attive_cfg );
	$lingue_uguali = $attive_ora === $attive_cfg && $sitepress->get_default_language() === $cfg['predefinita'];

	$impostazioni_ora = $sitepress->get_settings();
	// I segreti restano quelli del sito; i segni di stato di WPML non si applicano mai, anche se un
	// config/ esportato prima della loro esclusione li contiene
	$impostazioni = wpf_ripristina_segreti( wpf_togli_stato_wpml( $cfg['impostazioni'] ), $impostazioni_ora );
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
	// Solo una traduzione ha una lingua di partenza: quella del suo originale
	$partenza = $id_originale ? ( wpf_lingua( $id_originale, $tipo ) ?? apply_filters( 'wpml_default_language', null ) ) : null;
	if ( $ora && $trid && (int) $ora->trid === (int) $trid && $ora->language_code === $lingua
		&& ( $ora->source_language_code ?? null ) === $partenza ) {
		return; // già nella lingua, nel gruppo e con la lingua di partenza giusti
	}
	do_action(
		'wpml_set_element_language_details',
		[
			'element_id'           => $id,
			'element_type'         => $tipo,
			'trid'                 => $trid ?: false,
			'language_code'        => $lingua,
			'source_language_code' => $partenza,
		]
	);
}

/**
 * Traduzioni dei testi delle opzioni registrati da WPML String Translation («admin texts»: messaggio
 * dei cookie di Impreza, formati di data…). Le altre stringhe vengono dai file di traduzione di temi e
 * plugin, non dalla configurazione, e le «traduzioni» nella lingua stessa della stringa (formati di
 * data che WPML crea da sé) non sono traduzioni. Lettura delle tabelle di String Translation, mai
 * scrittura.
 *
 * @return array [ [ 'contesto', 'nome', 'traduzioni' => [ lingua => valore ] ] ]
 */
function wpf_wpml_stringhe_esporta(): array {
	global $wpdb;
	if ( ! defined( 'WPML_ST_VERSION' ) ) {
		return [];
	}
	$righe = $wpdb->get_results(
		"SELECT s.context, s.name, t.language, t.value FROM {$wpdb->prefix}icl_strings s
		 JOIN {$wpdb->prefix}icl_string_translations t ON t.string_id = s.id
		 WHERE s.context LIKE 'admin\\_texts\\_%' AND t.language <> s.language
		   AND t.status = " . (int) ICL_TM_COMPLETE . ' AND t.value IS NOT NULL
		 ORDER BY s.context, s.name, t.language',
		ARRAY_A
	);
	$stringhe = [];
	foreach ( $righe as $r ) {
		$chiave = $r['context'] . "\0" . $r['name'];
		$stringhe[ $chiave ] ??= [ 'contesto' => $r['context'], 'nome' => $r['name'], 'traduzioni' => [] ];
		$stringhe[ $chiave ]['traduzioni'][ $r['language'] ] = $r['value'];
	}
	return array_values( $stringhe );
}

/**
 * Applica le traduzioni esportate da wpf_wpml_stringhe_esporta(). Le stringhe originali le registra
 * String Translation leggendo i wpml-config.xml e le opzioni: di solito al caricamento del pannello,
 * qui con la stessa procedura che usa il wizard di WPML (WPML_Config::load_config_run), dopo le Theme
 * Options. Una stringa che resta senza registrazione dà un avviso e un apply parziale.
 */
function wpf_wpml_stringhe_applica( array $stringhe, bool $prova, callable $diff ): bool {
	global $wpdb;
	if ( ! $stringhe ) {
		return true;
	}
	if ( ! function_exists( 'icl_add_string_translation' ) ) {
		WP_CLI::warning( 'WPML String Translation non è attivo: traduzioni dei testi delle opzioni saltate' );
		return false;
	}
	if ( ! $prova && class_exists( 'WPML_Config' ) ) {
		WPML_Config::load_config_run();
	}
	$ok = true;
	foreach ( $stringhe as $s ) {
		$id = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}icl_strings WHERE context = %s AND name = %s", $s['contesto'], $s['nome'] )
		);
		if ( ! $id ) {
			if ( $prova ) {
				// La registrazione avviene solo eseguendo: qui si mostra cosa verrà tradotto
				foreach ( $s['traduzioni'] as $lingua => $valore ) {
					$diff( "traduzione {$lingua} di {$s['nome']}", '(stringa da registrare)', $valore );
				}
			} else {
				WP_CLI::warning( "stringa «{$s['nome']}» non registrata da WPML su questo sito: traduzioni non applicate, apri String Translation nel pannello e rilancia l'apply" );
				$ok = false;
			}
			continue;
		}
		foreach ( $s['traduzioni'] as $lingua => $valore ) {
			$ora = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT value FROM {$wpdb->prefix}icl_string_translations WHERE string_id = %d AND language = %s AND status = %d",
					$id,
					$lingua,
					ICL_TM_COMPLETE
				)
			);
			if ( $ora === $valore ) {
				continue;
			}
			$diff( "traduzione {$lingua} di {$s['nome']}", $ora, $valore );
			if ( ! $prova && ! icl_add_string_translation( $id, $lingua, $valore, ICL_TM_COMPLETE ) ) {
				WP_CLI::warning( "traduzione {$lingua} di «{$s['nome']}» non salvata" );
				$ok = false;
			}
		}
	}
	return $ok;
}
