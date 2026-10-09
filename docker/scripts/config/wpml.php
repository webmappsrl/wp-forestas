<?php
/**
 * Configurazione di WPML con le sue stesse API: le tabelle wp_icl_* si leggono, mai si scrivono con SQL
 * (oc:8717).
 *
 * Sequenza provata su un WordPress nuovo: sincronizzazione del catalogo delle lingue (senza, l'API
 * risponde «missing_preset»), lingue salvate con l'endpoint della schermata «Lingue», impostazioni,
 * stato del wizard.
 */

require_once __DIR__ . '/comune.php';

// Impostazioni di WPML che WPML calcola o genera da sé: non sono configurazione e, esportate,
// comparirebbero come differenze a ogni export da un sito ricreato
const WPF_WPML_INTERNE = [
	[ 'st', 'was_frontend_visited_key' ],
	[ 'translation-management', 'custom_fields_translation' ],
	[ 'translation-management', 'custom_term_fields_translation' ],
	// Id della pagina usata come root page: su un altro sito sarebbe un'altra pagina. L'export avvisa
	// se è impostata, l'apply lascia quella del sito.
	[ 'urls', 'root_page' ],
];

/**
 * Toglie, a qualsiasi profondità, le impostazioni di WPML che descrivono il sito da cui si esporta e
 * non la configurazione:
 * - i segni delle migrazioni già eseguite («…_has_run», «…migration_complete…») e dei controlli già
 *   fatti: scritti su un sito nuovo, WPML salterebbe migrazioni mai eseguite e non creerebbe tabelle
 *   come wp_icl_mo_files_domains;
 * - i valori che WPML calcola sul sito: default_categories contiene id di termini (su un sito nuovo
 *   l'id della categoria inglese di UAT era il menu), gettext_theme_domain_name e
 *   theme_language_folders dipendono da temi e percorsi del sito, i «…_readonly_config» (e la loro
 *   «…_source») li ricava dai wpml-config.xml dei plugin, setup_wizard_step e
 *   language_selector_initialized sono passi del wizard già fatti, db_ok_for_gettext_context e
 *   autoregister_strings_were_new_translations_loaded sono controlli di String Translation.
 */
function wpf_togli_stato_wpml( array $dati ): array {
	$stato = '/_has_run$|migration_complete|_migrated$|_verified$|^ajx_health_checked$|^migrated_site$'
		. '|^default_categories$|^gettext_theme_domain_name$|^theme_language_folders$|_readonly_config(_source)?$'
		. '|^setup_wizard_step$|^language_selector_initialized$|^db_ok_for_gettext_context$'
		. '|_were_new_translations_loaded$/';
	foreach ( $dati as $nome => $valore ) {
		if ( is_string( $nome ) && preg_match( $stato, $nome ) ) {
			unset( $dati[ $nome ] );
		} elseif ( is_array( $valore ) ) {
			$dati[ $nome ] = wpf_togli_stato_wpml( $valore );
		}
	}
	return $dati;
}

/** Dettagli WPML di un elemento (lingua, trid, lingua di partenza), o null senza WPML. */
function wpf_dettagli_lingua( int $id, string $tipo ): ?object {
	return apply_filters( 'wpml_element_language_details', null, [ 'element_id' => $id, 'element_type' => $tipo ] ) ?: null;
}

/** Lingua WPML di un elemento, o null senza WPML. */
function wpf_lingua( int $id, string $tipo ): ?string {
	return wpf_dettagli_lingua( $id, $tipo )->language_code ?? null;
}

/** Traduzioni WPML di un elemento: [ lingua => id ], originale compreso. */
function wpf_traduzioni( int $id, string $tipo ): array {
	$trid = apply_filters( 'wpml_element_trid', null, $id, $tipo );
	if ( ! $trid ) {
		return [];
	}
	$elenco = apply_filters( 'wpml_get_element_translations', [], $trid, $tipo );
	$out    = [];
	foreach ( (array) $elenco as $lingua => $t ) {
		$out[ $lingua ] = (int) $t->element_id;
	}
	return $out;
}

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
	// Classi interne di WPML usate qui sotto: se un aggiornamento ne toglie una ci si ferma prima di scrivere
	foreach ( [ '\WPML\LanguageEditor\Presets\CatalogueSyncRunner', '\WPML\FP\Right' ] as $classe ) {
		if ( ! class_exists( $classe ) ) {
			WP_CLI::warning( "questa versione di WPML non ha {$classe}: lingue non configurate, va adeguato wpml.php" );
			return false;
		}
	}
	if ( ! function_exists( 'wpml_collect' ) ) {
		WP_CLI::warning( 'questa versione di WPML non ha wpml_collect(): lingue non configurate, va adeguato wpml.php' );
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
		$impostazioni[ $nome ] = wpf_wpml_unisci( $impostazioni_ora[ $nome ] ?? null, $valore );
	}
	$impostazioni_diverse = [];
	foreach ( $impostazioni as $nome => $valore ) {
		if ( wpf_diverso( $impostazioni_ora[ $nome ] ?? null, $valore ) ) {
			$impostazioni_diverse[] = $nome;
		}
	}
	$setup_diverso = wpf_diverso( get_option( 'WPML(setup)' ), $cfg['setup'] );

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
function wpf_wpml_unisci( $sul_sito, $dal_repo ) {
	if ( ! is_array( $sul_sito ) || ! is_array( $dal_repo ) || array_is_list( $dal_repo ) ) {
		return $dal_repo;
	}
	foreach ( $dal_repo as $nome => $valore ) {
		$sul_sito[ $nome ] = wpf_wpml_unisci( $sul_sito[ $nome ] ?? null, $valore );
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

/**
 * Cancella un post togliendo anche la sua riga di traduzione di WPML. WPML la toglie da sé solo quando
 * il post si cancella dal pannello o dal sito: da WP-CLI la riga resterebbe, orfana.
 */
function wpf_wpml_cancella_post( int $id, string $tipo ): void {
	global $sitepress;
	$dettagli = wpf_dettagli_lingua( $id, $tipo );
	wp_delete_post( $id, true );
	if ( $sitepress && method_exists( $sitepress, 'delete_element_translation' ) && $dettagli && ! empty( $dettagli->trid ) ) {
		// WPML vuole il trid come stringa (is_string): con un intero non cancella nulla
		$sitepress->delete_element_translation( (string) $dettagli->trid, $tipo, $dettagli->language_code );
	}
}

/** Collega un elemento a WPML: lingua e, per una traduzione, il gruppo dell'originale. */
function wpf_wpml_collega( int $id, string $tipo, ?string $lingua, ?int $id_originale ): void {
	if ( ! $lingua ) {
		return;
	}
	$ora = wpf_dettagli_lingua( $id, $tipo );
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
