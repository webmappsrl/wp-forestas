<?php
/**
 * Sincronizzazione delle pagine statiche (oc:8758) e dei contenuti con un nodo da Drupal: news (oc:8759),
 * eventi e «da sapere» (oc:8760), i tipi di wpf_import_tipi (import-lib.php).
 *
 * Uso: [WPF_PROVA=1] [WPF_SOLO_MANCANTI=1] [WPF_ACCETTA_BOZZE=1] wp eval-file /usr/local/lib/wp-forestas/pagine.php
 * (lo lancia bin/wordpress-config.sh pagine, che ne salva l'output nel log).
 * Scrive solo con DRUPAL_SYNC=on nel .env; con WPF_PROVA=1 mostra le differenze senza scrivere nulla.
 * Ogni riga di output comincia con un tipo: PAGINA, quello di ogni tipo importato (NEWS, …), TERMINE, MEDIA,
 * LINK, MENU, MANCANTE, REGOLE, AVVISO, ERRORE, RIASSUNTO. Con WPF_NEWS_PROVA_CONTEGGIO=<n> (e la variabile
 * 'conteggio' degli altri tipi), solo in anteprima, i contenuti pubblicati letti dalla copia contano <n>:
 * serve a provare il controllo sulla copia caricata a metà (import-lib.php).
 * Esce con 1 se c'è un errore, se restano elementi mancanti (non i soli media) o se la
 * sincronizzazione è spenta (DRUPAL_SYNC), anche in anteprima.
 */

require_once __DIR__ . '/pagine-lib.php';
require_once __DIR__ . '/import-lib.php';

$prova         = getenv( 'WPF_PROVA' ) === '1';
$solo_mancanti = getenv( 'WPF_SOLO_MANCANTI' ) === '1';
$accetta_bozze = getenv( 'WPF_ACCETTA_BOZZE' ) === '1';
$sync          = getenv( 'DRUPAL_SYNC' ) === 'on';
$riga          = fn( string $tipo, string $testo ) => WP_CLI::log( "{$tipo} {$testo}" );
$ferma         = function ( string $messaggio ) use ( $riga ) {
	$riga( 'ERRORE', $messaggio );
	WP_CLI::halt( 1 );
};

if ( ! $sync ) {
	if ( ! $prova ) {
		$ferma( 'sincronizzazione da Drupal spenta in questo ambiente (DRUPAL_SYNC non è «on» nel .env di wp-forestas): nulla scritto' );
	}
	$riga( 'AVVISO', 'sincronizzazione da Drupal spenta in questo ambiente (DRUPAL_SYNC): solo anteprima' );
}
if ( ! $prova && ! wpf_blocca_pagine() ) {
	$ferma( 'un\'altra sincronizzazione delle pagine è in corso: riprova più tardi' );
}

// I link dei testi restano relativi: WPML li renderebbe assoluti, diversi in ogni ambiente
if ( ! $prova && class_exists( 'SitePress' ) && wpf_wpml_senza_link_automatici() === 0 ) {
	$riga( 'AVVISO', 'aggiornamento automatico dei link di WPML non trovato: controlla che i link delle traduzioni restino relativi' );
}

$file = WPF_DIR_CONFIG . '/pagine.json';
$cfg  = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
if ( ! is_array( $cfg['pagine'] ?? null ) ) {
	$ferma( "{$file} mancante o non valido" );
}
$voci = array_column( $cfg['pagine'], null, 'chiave' );
// Pagine figlie (oc:8759): il genitore è un'altra voce, senza genitore a sua volta, e si scrive prima
foreach ( $voci as $chiave => $voce ) {
	$genitore = $voce['genitore'] ?? null;
	if ( $genitore !== null && ( ! isset( $voci[ $genitore ] ) || isset( $voci[ $genitore ]['genitore'] ) ) ) {
		$ferma( "{$chiave}: il genitore «{$genitore}» non è in config/pagine.json o è a sua volta una pagina figlia" );
	}
	if ( $genitore !== null && array_diff( array_keys( $voce['slug'] ), array_keys( $voci[ $genitore ]['slug'] ) ) ) {
		$ferma( "{$chiave}: ha lingue che il genitore «{$genitore}» non ha" );
	}
}
uasort( $voci, fn( $a, $b ) => isset( $a['genitore'] ) <=> isset( $b['genitore'] ) );

try {
	$db = wpf_drupal_connessione();
	wpf_drupal_controlla( $db, array_merge( array_filter( array_column( $voci, 'nid' ) ), ...array_column( $voci, 'sostituisce' ) ) );

	// Lettura di tutto prima di scrivere: [ chiave ][ lingua ] => dati
	$dati   = [];
	$avvisi = [];
	foreach ( $voci as $chiave => $voce ) {
		foreach ( array_keys( $voce['slug'] ) as $lingua ) {
			if ( $voce['nid'] ) {
				$d     = wpf_drupal_pagina( $db, (int) $voce['nid'], $lingua );
				$trad  = $lingua !== 'it' ? wpf_traduzione_leggi( $chiave, $lingua ) : null;
				$posto = "config/traduzioni/{$lingua}/{$chiave}.*";
				if ( $d && $trad ) {
					$avvisi[] = "{$chiave}/{$lingua}: Drupal ha ora la sua traduzione, che vince; {$posto} non si usa più e si può cancellare";
				}
				if ( ! $d && $trad ) {
					// Traduzione nostra: dati della pagina italiana, titolo, sottotitolo e testo tradotti
					$it = $dati[ $chiave ]['it'] ?? wpf_drupal_pagina( $db, (int) $voce['nid'], 'it' );
					if ( $it ) {
						$d = array_merge( $it, [ 'titolo' => $trad['titolo'], 'sottotitolo' => (string) ( $trad['sottotitolo'] ?? '' ), 'body' => $trad['body'], 'formato' => 'filtered_html' ] );
						$impronta = wpf_traduzione_impronta( $it );
						if ( ( $trad['sorgente'] ?? '' ) !== $impronta ) {
							$avvisi[] = "{$chiave}/{$lingua}: il testo italiano su Drupal è cambiato dopo la traduzione, {$posto} va rivista (poi «sorgente»: {$impronta})";
						}
					}
				} elseif ( ! $d && isset( $voce['titolo'][ $lingua ] ) ) {
					// Pagina di sola interfaccia in una lingua che Drupal non ha (Mappa in inglese: su Drupal
					// /en/node/1093 mostra il contenuto italiano): dati della pagina italiana, titolo da
					// config/pagine.json, senza testo né sottotitolo, che sarebbero in italiano
					$d = wpf_drupal_pagina( $db, (int) $voce['nid'], 'it' );
					$d = $d ? array_merge( $d, [ 'titolo' => $voce['titolo'][ $lingua ], 'sottotitolo' => '', 'body' => '', 'formato' => '', 'allegati' => [], 'galleria' => [] ] ) : null;
				}
				if ( ! $d ) {
					$ferma( "il nodo {$voce['nid']} ({$chiave}) non ha la lingua «{$lingua}» indicata in config/pagine.json: se la copia Drupal è in ricarica riprova più tardi; se su Drupal la traduzione è stata tolta, scrivi la nostra in config/traduzioni/{$lingua}/{$chiave}.json e .html, o togli slug.{$lingua} da config/pagine.json" );
				}
			} else {
				// Pagina senza nodo (Cammini, schede ed elenchi delle news): titolo suo, testata della pagina
				// indicata in testata_da. La testata (immagine, colore) non dipende dalla lingua: se Drupal non
				// ha la traduzione della pagina d'origine (News in inglese), vale quella italiana
				$fonte = $voci[ $voce['testata_da'] ] ?? null;
				$d     = $fonte ? wpf_drupal_pagina( $db, (int) $fonte['nid'], $lingua ) : null;
				if ( $fonte && ! $d && ( $d = wpf_drupal_pagina( $db, (int) $fonte['nid'], 'it' ) ) ) {
					// Il sottotitolo invece è testo: quello della nostra traduzione della pagina d'origine, o nessuno
					$d['sottotitolo'] = (string) ( wpf_traduzione_leggi( $voce['testata_da'], $lingua )['sottotitolo'] ?? '' );
				}
				if ( ! $d ) {
					$ferma( "{$chiave}: la pagina «" . ( $voce['testata_da'] ?? '' ) . "» da cui prende la testata non ha la lingua «{$lingua}»" );
				}
				// Lo stato no: Cammini non esiste su Drupal e resta pubblicata anche se Itinerari va in bozza
				$d = array_merge( $d, [ 'titolo' => $voce['titolo'][ $lingua ], 'body' => '', 'allegati' => [], 'galleria' => [], 'status' => true ] );
			}
			$dati[ $chiave ][ $lingua ] = $d;
		}
	}
	$alias           = wpf_drupal_alias( $db );
	// Contenuti con un nodo (news, eventi, «da sapere»): si leggono tutti prima di scrivere, anche le
	// pagine, che possono linkarli
	$tipi = [];
	foreach ( wpf_import_tipi() as $tipo ) {
		// Tipi e tassonomie li registra il child: senza (tema non attivo, ordine sbagliato dei passi di
		// ricreare-wordpress-da-zero.md) wp_insert_post creerebbe contenuti di un tipo sconosciuto, senza errori
		$senza = array_filter( $tipo['tassonomie'], fn( $t ) => ! is_object_in_taxonomy( $tipo['post_type'], $t ) );
		if ( ! post_type_exists( $tipo['post_type'] ) || $senza ) {
			$ferma( "il tipo {$tipo['post_type']}" . ( $senza ? ' o le tassonomie ' . implode( ', ', $senza ) : '' ) . ' non sono registrati: il child theme forestas-child è attivo?' );
		}
		$elenco                     = wpf_drupal_bundle_elenco( $db, $tipo['bundle'] );
		$tipi[ $tipo['origine'] ]   = [ 'tipo' => $tipo, 'elenco' => $elenco, 'dati' => wpf_import_leggi( $tipo, $db, $elenco ) ];
	}
	$ultima_modifica = wpf_drupal_ultima_modifica( $db );
} catch ( RuntimeException | mysqli_sql_exception $e ) {
	$ferma( $e->getMessage() );
}

// Pagine sincronizzate che config/pagine.json non elenca più (voce tolta o chiave rinominata): restano
// com'erano e nessuno le aggiorna, perché anche esporta e applica le saltano
foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => WPF_META_PAGINA, 'suppress_filters' => true ] ) as $p ) {
	$chiave = (string) get_post_meta( $p->ID, WPF_META_PAGINA, true );
	$lingua = (string) get_post_meta( $p->ID, WPF_META_LINGUA, true );
	if ( ! isset( $voci[ $chiave ]['slug'][ $lingua ] ) ) {
		$avvisi[] = "la pagina «{$p->post_title}» (id {$p->ID}, {$chiave}/{$lingua}, {$p->post_status}) non è più in config/pagine.json: nessuno la aggiorna; se va tolta, mettila nel cestino dal pannello";
	}
}
if ( $ultima_modifica && time() - $ultima_modifica > WPF_DRUPAL_GIORNI_FERMA * DAY_IN_SECONDS ) {
	$avvisi[] = 'nella copia Drupal l\'ultimo contenuto è stato modificato il ' . gmdate( 'Y-m-d', $ultima_modifica ) . ', più di ' . WPF_DRUPAL_GIORNI_FERMA . ' giorni fa: controlla che il dump arrivi ancora (forestas/docs/howto/copia-database-drupal.md)';
}
foreach ( $avvisi as $a ) {
	$riga( 'AVVISO', $a );
}

// Una copia in ricarica o troncata manderebbe in bozza molte pagine insieme
// Si contano le pagine di Drupal (i nodi), non le copie per lingua: una pagina spubblicata con l'inglese
// sono due copie da mettere in bozza, ma una sola spubblicazione
$in_bozza = [];
foreach ( $dati as $chiave => $lingue ) {
	foreach ( $lingue as $lingua => $d ) {
		$post = wpf_pagina_trova( $chiave, $lingua );
		if ( ! $d['status'] && ( ! $post || $post->post_status !== 'draft' ) ) {
			$in_bozza[ (int) $voci[ $chiave ]['nid'] ][] = "{$chiave}/{$lingua}";
		}
	}
}
$elenco_bozze = $in_bozza ? implode( ', ', array_merge( ...array_values( $in_bozza ) ) ) : '';
if ( count( $in_bozza ) > WPF_PAGINE_SOGLIA_BOZZE && ! $accetta_bozze ) {
	$ferma( 'copia Drupal sospetta: ' . count( $in_bozza ) . " pagine da mettere in bozza nello stesso giro ({$elenco_bozze}), più di " . WPF_PAGINE_SOGLIA_BOZZE . ': nulla scritto. Se su Drupal sono state spubblicate davvero: pagine --accetta-bozze' );
}
if ( count( $in_bozza ) > WPF_PAGINE_SOGLIA_BOZZE ) {
	$riga( 'AVVISO', count( $in_bozza ) . " pagine in bozza nello stesso giro, accettate con --accetta-bozze ({$elenco_bozze})" );
}

// Template di Impreza per chiave, quelli che config/pagine.json usa
$template = [];
foreach ( array_unique( array_column( $voci, 'template' ) ) as $t ) {
	$p              = wpf_post_per_chiave( WPF_PREFISSO_TEMPLATE . $t );
	$template[ $t ] = $p && $p->post_type === 'us_content_template' ? $p->ID : 0;
}

// Link al contenuto WordPress di un nodo: quello pubblicato, o il percorso che avrà una pagina del
// perimetro che questa esecuzione crea o pubblica. Una pagina del perimetro spubblicata su Drupal non ha
// link, anche se in WordPress è ancora pubblicata (va in bozza in questo giro, magari dopo la pagina che
// la linka): il link resta verso Drupal ed è «in attesa», invece di un link relativo che darebbe 404
$percorsi = [];
$in_bozza_nodi = [];
foreach ( $voci as $chiave => $voce ) {
	foreach ( array_keys( $voce['slug'] ) as $lingua ) {
		if ( ! $voce['nid'] ) {
			continue;
		}
		if ( empty( $dati[ $chiave ][ $lingua ]['status'] ) ) {
			$in_bozza_nodi[ "{$voce['nid']}|{$lingua}" ] = true;
		} else {
			$percorsi[ "{$voce['nid']}|{$lingua}" ] = wpf_pagina_percorso( $voce, $lingua, $voci );
		}
	}
}
// I contenuti che questo giro scrive (oc:8759, oc:8760): i link verso uno di loro si riscrivono subito,
// anche se nasce dopo la pagina o il contenuto che lo linka
foreach ( $tipi as $t ) {
	$p             = wpf_import_percorsi( $t['tipo'], $t['dati'] );
	$percorsi      = $p['percorsi'] + $percorsi;
	$in_bozza_nodi = $p['in_bozza'] + $in_bozza_nodi;
}
$trova = function ( int $nid, string $lingua ) use ( $percorsi, $in_bozza_nodi ) {
	if ( isset( $in_bozza_nodi[ "{$nid}|{$lingua}" ] ) ) {
		return null;
	}
	$id = wpf_contenuto_per_nodo( $nid, $lingua );
	return $id ? wpf_link_relativo( $id ) : ( $percorsi[ "{$nid}|{$lingua}" ] ?? null );
};

// Elementi dell'elenco dei mancanti delle pagine (quelli di un altro import restano com'erano). Uno di una
// pagina o di una lingua tolta da config/pagine.json non si ritenterebbe mai e terrebbe l'uscita a 1 per
// sempre: si toglie, anche dall'anteprima
$mancanti_prima = wpf_mancanti_leggi();
$mancanti_altri = array_filter( $mancanti_prima, fn( $m ) => ! in_array( $m['origine'], [ 'pagina', ...array_keys( $tipi ) ], true ) );
// Quelli di ogni tipo importato: uno di un contenuto non più nella copia non si ritenterebbe mai, come per le pagine
foreach ( $tipi as $origine => $t ) {
	$tipi[ $origine ]['mancanti'] = array_filter( $mancanti_prima, fn( $m ) => $m['origine'] === $origine && isset( $t['dati'][ (int) $m['elemento'] ][ $m['lingua'] ] ) );
	$tipi[ $origine ]['solo']     = [];
	foreach ( $tipi[ $origine ]['mancanti'] as $m ) {
		$tipi[ $origine ]['solo'][ (int) $m['elemento'] ][ $m['lingua'] ][] = $m['cosa'];
	}
}
$solo_tipi = array_filter( array_column( $tipi, 'solo' ) );
$mancanti       = array_filter( $mancanti_prima, fn( $m ) => $m['origine'] === 'pagina' && isset( $voci[ $m['elemento'] ]['slug'][ $m['lingua'] ] ) );
// Elementi da trattare con --contenuti-mancanti: [ chiave ][ lingua ] => [ cosa ]
$solo = [];
foreach ( $mancanti as $m ) {
	$solo[ $m['elemento'] ][ $m['lingua'] ][] = $m['cosa'];
}
if ( $solo_mancanti && ! $solo && ! $solo_tipi ) {
	$riga( 'RIASSUNTO', 'nessun contenuto mancante da ritentare' );
	if ( ! $sync ) {
		WP_CLI::halt( 1 ); // come a fine giro: con la sincronizzazione spenta non si scriverebbe
	}
	return;
}

$conteggi   = [];
$ora        = gmdate( 'c' );
$ids_pagine = []; // [ chiave ][ lingua ] => id scritto in questo giro, per il post_parent delle figlie
// Mancanti che questo giro produrrebbe, in anteprima: contano per il codice d'uscita come col --conferma
$previsti = [];
foreach ( $voci as $chiave => $voce ) {
	$id_it = null;
	foreach ( array_keys( WPF_LINGUE_WPML ) as $lingua ) {
		if ( ! isset( $dati[ $chiave ][ $lingua ] ) ) {
			continue;
		}
		if ( $solo_mancanti && ! isset( $solo[ $chiave ][ $lingua ] ) ) {
			$id_it = $lingua === 'it' ? ( wpf_pagina_trova( $chiave, 'it' )->ID ?? null ) : $id_it;
			continue;
		}
		$d = $dati[ $chiave ][ $lingua ];
		$trova_qui = wpf_link_trova_in_lingua( $trova, $lingua );
		$l         = wpf_link_riscrivi( wpf_contenuto_pulisci( $d['body'], $d['formato'] ), $alias, $trova_qui, $lingua );
		$d['body'] = $l['html'];
		$d['alias_drupal'] = wpf_pagina_alias_drupal( $voce, $lingua, $alias );
		$genitore = $voce['genitore'] ?? null;
		$parent   = $genitore !== null ? ( $ids_pagine[ $genitore ][ $lingua ] ?? ( wpf_pagina_trova( $genitore, $lingua )->ID ?? 0 ) ) : 0;
		$r        = wpf_pagina_scrivi( $voce, $lingua, $d, $id_it, $template, $prova, $solo_mancanti ? $solo[ $chiave ][ $lingua ] : null, $parent );
		$ids_pagine[ $chiave ][ $lingua ] = $r['id'];
		if ( $lingua === 'it' ) {
			$id_it = $r['id'];
		}
		$nome = "{$chiave}/{$lingua}";
		$conteggi[ $r['esito'] ] = ( $conteggi[ $r['esito'] ] ?? 0 ) + 1;
		$riga( 'PAGINA', "{$nome}: {$r['esito']}" . ( $r['campi'] ? ' (' . implode( ', ', $r['campi'] ) . ')' : '' ) . ( $d['status'] ? '' : ' — in bozza: non pubblicata su Drupal' ) );
		foreach ( $r['media'] as $m ) {
			$riga( 'MEDIA', "{$nome}: {$m}" );
		}
		wpf_link_log( $riga, $nome, $l );
		if ( ! $d['status'] && $r['id'] ) {
			foreach ( wpf_pagina_voci_menu( $r['id'] ) as $v ) {
				$riga( 'MENU', "{$nome}: la voce «{$v}» porta a una pagina in bozza (404 per chi non è loggato)" );
			}
		}
		if ( $prova ) {
			$previsti = array_merge( $previsti, wpf_mancanti_previsti( $riga, $nome, $r['mancanti'] ) );
			continue;
		}
		$mancanti = wpf_mancanti_aggiorna( $mancanti, 'pagina', $chiave, $lingua, $solo_mancanti ? $solo[ $chiave ][ $lingua ] : null, $r['mancanti'], $ora );
	}
}

// I tipi importati, dopo le pagine e con lo stesso lock (oc:8759, oc:8760). Un errore di un tipo (copia
// sospetta) non toglie quello che le pagine e gli altri tipi hanno scritto: sono perimetri diversi
$esiti = [];
foreach ( $tipi as $origine => $t ) {
	$esiti[ $origine ] = ( $solo_mancanti && ! $t['solo'] )
		? [ 'conteggi' => [], 'mancanti' => $t['mancanti'], 'previsti' => [], 'errore' => false ]
		: wpf_import_sincronizza( $t['tipo'], $db, $t['elenco'], $t['dati'], $alias, $trova, $t['mancanti'], $solo_mancanti ? $t['solo'] : null, $prova, $accetta_bozze, $riga );
	$mancanti = array_merge( $mancanti, $esiti[ $origine ]['mancanti'] );
	$previsti = array_merge( $previsti, $esiti[ $origine ]['previsti'] );
}

// Regole di rewrite del child (inc/news.php, forestas_regole_aggiorna): si rigenerano quando cambia l'elenco
// delle schede figlie pubblicate, se no una scheda nuova darebbe 404, o la versione delle regole del child
if ( ! $prova && function_exists( 'forestas_regole_aggiorna' ) && forestas_regole_aggiorna() ) {
	$riga( 'REGOLE', 'rigenerate per le schede: ' . implode( ', ', array_unique( forestas_schede_figlie() ) ) );
}

// In anteprima l'elenco non cambia: si mostrano gli elementi che risultano mancanti adesso
if ( ! $prova ) {
	wpf_mancanti_salva( array_merge( $mancanti_altri, $mancanti ) );
}
foreach ( $mancanti as $m ) {
	$riga( 'MANCANTE', "{$m['origine']} {$m['elemento']}/{$m['lingua']} {$m['cosa']}" . ( $m['dettaglio'] !== '' ? " {$m['dettaglio']}" : '' ) . ": {$m['causa']} (dal {$m['primo']})" );
}
$riga(
	'RIASSUNTO',
	( $prova ? 'anteprima, nulla scritto — ' : '' ) . 'pagine: ' . implode( ', ', array_map( fn( $e, $n ) => "{$e}: {$n}", array_keys( $conteggi ), $conteggi ) )
	. implode( '', array_map( fn( $o, $e ) => "; {$tipi[ $o ]['tipo']['plurale']}: " . ( $e['errore'] ? "non {$tipi[ $o ]['tipo']['genere'][4]} (copia sospetta)" : implode( ', ', array_map( fn( $k, $n ) => "{$k}: {$n}", array_keys( $e['conteggi'] ), $e['conteggi'] ) ) ), array_keys( $esiti ), $esiti ) )
	. '; mancanti: ' . count( $mancanti )
	. '; copia Drupal: ultima modifica ' . ( $ultima_modifica ? gmdate( 'Y-m-d', $ultima_modifica ) : 'nessuna' )
);
// Codice d'uscita: 1 solo se manca qualcosa che non si sistema da sé (una pagina o un contenuto non salvato, una
// traduzione non collegata, un template assente). Un media non scaricato (sito Drupal lento) lascia pagine e
// contenuti a posto e si ritenta al giro dopo: avviso nel log e uscita 0, così un cron o una catena di
// import non si ferma per questo (decisione del dev, oc:8758)
$gravi = array_filter( array_merge( $mancanti, $previsti ), fn( $m ) => ! isset( WPF_CONTENUTO_MEDIA[ $m['cosa'] ] ) );
if ( $gravi || array_filter( array_column( $esiti, 'errore' ) ) ) {
	WP_CLI::halt( 1 );
}
if ( $mancanti ) {
	$riga( 'AVVISO', count( $mancanti ) . ' media non scaricati: pagine e contenuti sono aggiornati, si ritentano al prossimo giro o con --contenuti-mancanti' );
}
// Con la sincronizzazione spenta anche l'anteprima esce con 1: chi la lancia (o un cron) sa che non
// scriverebbe, anche se le differenze sono quelle di sempre
if ( ! $sync ) {
	WP_CLI::halt( 1 );
}
