<?php
/**
 * Sincronizzazione delle pagine statiche da Drupal (oc:8758).
 *
 * Uso: [WPF_PROVA=1] [WPF_SOLO_MANCANTI=1] wp eval-file /usr/local/lib/wp-forestas/pagine.php
 * (lo lancia bin/wordpress-config.sh pagine, che ne salva l'output nel log).
 * Scrive solo con DRUPAL_SYNC=on nel .env; con WPF_PROVA=1 mostra le differenze senza scrivere nulla.
 * Ogni riga di output comincia con un tipo: PAGINA, MEDIA, LINK, MENU, MANCANTE, AVVISO, ERRORE,
 * RIASSUNTO. Esce con 1 se c'è un errore, se restano elementi mancanti (non i soli media) o se la
 * sincronizzazione è spenta (DRUPAL_SYNC), anche in anteprima.
 */

require_once __DIR__ . '/pagine-lib.php';

$prova         = getenv( 'WPF_PROVA' ) === '1';
$solo_mancanti = getenv( 'WPF_SOLO_MANCANTI' ) === '1';
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

try {
	$db = wpf_drupal_connessione();
	wpf_drupal_controlla( $db, array_filter( array_column( $voci, 'nid' ) ) );

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
					$ferma( "copia Drupal non integra: il nodo {$voce['nid']} non ha la lingua «{$lingua}» indicata in config/pagine.json" );
				}
			} else {
				// Pagina senza nodo (Cammini): titolo suo, testata della pagina indicata in testata_da
				$fonte = $voci[ $voce['testata_da'] ] ?? null;
				$d     = $fonte ? wpf_drupal_pagina( $db, (int) $fonte['nid'], $lingua ) : null;
				if ( ! $d ) {
					$ferma( "{$chiave}: la pagina «" . ( $voce['testata_da'] ?? '' ) . "» da cui prende la testata non ha la lingua «{$lingua}»" );
				}
				// Lo stato no: Cammini non esiste su Drupal e resta pubblicata anche se Itinerari va in bozza
				$d = array_merge( $d, [ 'titolo' => $voce['titolo'][ $lingua ], 'body' => '', 'allegati' => [], 'galleria' => [], 'status' => true ] );
			}
			$dati[ $chiave ][ $lingua ] = $d;
		}
	}
	$alias = wpf_drupal_alias( $db );
} catch ( RuntimeException | mysqli_sql_exception $e ) {
	$ferma( $e->getMessage() );
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
if ( count( $in_bozza ) > WPF_PAGINE_SOGLIA_BOZZE ) {
	$ferma( 'copia Drupal sospetta: ' . count( $in_bozza ) . ' pagine da mettere in bozza nello stesso giro (' . implode( ', ', array_merge( ...array_values( $in_bozza ) ) ) . '), più di ' . WPF_PAGINE_SOGLIA_BOZZE . ': nulla scritto' );
}

// Template di Impreza per chiave, quelli che config/pagine.json usa
$template = [];
foreach ( array_unique( array_column( $voci, 'template' ) ) as $t ) {
	$p              = wpf_post_per_chiave( WPF_PREFISSO_TEMPLATE . $t );
	$template[ $t ] = $p && $p->post_type === 'us_content_template' ? $p->ID : 0;
}

// Link al contenuto WordPress di un nodo: quello esistente, o il percorso che avrà una pagina del
// perimetro che questa esecuzione crea
$percorsi = [];
foreach ( $voci as $voce ) {
	foreach ( array_keys( $voce['slug'] ) as $lingua ) {
		if ( $voce['nid'] ) {
			$percorsi[ "{$voce['nid']}|{$lingua}" ] = wpf_pagina_percorso( $voce, $lingua );
		}
	}
}
$trova = function ( int $nid, string $lingua ) use ( $percorsi ) {
	$id = wpf_contenuto_per_nodo( $nid, $lingua );
	return $id ? wpf_link_relativo( $id ) : ( $percorsi[ "{$nid}|{$lingua}" ] ?? null );
};

// Elementi dell'elenco dei mancanti. Uno di una pagina o di una lingua tolta da config/pagine.json non
// si ritenterebbe mai e terrebbe l'uscita a 1 per sempre: si toglie, anche dall'anteprima
$mancanti_prima = wpf_mancanti_leggi();
$mancanti       = array_filter( $mancanti_prima, fn( $m ) => isset( $voci[ $m['pagina'] ]['slug'][ $m['lingua'] ] ) );
// Elementi da trattare con --contenuti-mancanti: [ chiave ][ lingua ] => [ cosa ]
$solo = [];
foreach ( $mancanti as $m ) {
	$solo[ $m['pagina'] ][ $m['lingua'] ][] = $m['cosa'];
}
if ( $solo_mancanti && ! $solo ) {
	$riga( 'RIASSUNTO', 'nessun contenuto mancante da ritentare' );
	if ( ! $sync ) {
		WP_CLI::halt( 1 ); // come a fine giro: con la sincronizzazione spenta non si scriverebbe
	}
	return;
}

$conteggi = [];
$ora      = gmdate( 'c' );
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
		// In una pagina inglese un link porta alla versione inglese, se c'è (su Drupal molte pagine
		// inglesi non esistevano e il testo linka l'italiano), e la home è quella della lingua. Un link
		// alla versione inglese di un contenuto che in WordPress è solo italiano porta all'italiano, come
		// su Drupal, che a /en/node/N senza traduzione mostra il contenuto italiano
		$trova_qui = $lingua === 'it'
			? fn( int $nid, string $l ) => $trova( $nid, $l ) ?? ( $l !== 'it' ? $trova( $nid, 'it' ) : null )
			: fn( int $nid, string $l ) => $trova( $nid, $lingua ) ?? $trova( $nid, $l ) ?? $trova( $nid, 'it' );
		$l         = wpf_link_riscrivi( wpf_pagina_pulisci( $d['body'], $d['formato'] ), $alias, $trova_qui, $lingua );
		$d['body'] = $l['html'];
		$r = wpf_pagina_scrivi( $voce, $lingua, $d, $id_it, $template, $prova, $solo_mancanti ? $solo[ $chiave ][ $lingua ] : null );
		if ( $lingua === 'it' ) {
			$id_it = $r['id'];
		}
		$nome = "{$chiave}/{$lingua}";
		$conteggi[ $r['esito'] ] = ( $conteggi[ $r['esito'] ] ?? 0 ) + 1;
		$riga( 'PAGINA', "{$nome}: {$r['esito']}" . ( $r['campi'] ? ' (' . implode( ', ', $r['campi'] ) . ')' : '' ) . ( $d['status'] ? '' : ' — in bozza: non pubblicata su Drupal' ) );
		foreach ( $r['media'] as $m ) {
			$riga( 'MEDIA', "{$nome}: {$m}" );
		}
		foreach ( $l['riscritti'] as $m ) {
			$riga( 'LINK', "{$nome}: riscritto {$m}" );
		}
		foreach ( array_unique( $l['in_attesa'] ) as $m ) {
			$riga( 'LINK', "{$nome}: in attesa {$m} (contenuto non ancora in WordPress, resta verso Drupal)" );
		}
		foreach ( array_unique( $l['non_contenuti'] ) as $m ) {
			$riga( 'LINK', "{$nome}: verso Drupal {$m} (né /node/N né l'alias di un nodo di Drupal: un modulo come i form, un file o un vecchio indirizzo; resta verso Drupal finché non si corregge a mano o con una regola in link.php)" );
		}
		if ( ! $d['status'] && $r['id'] ) {
			foreach ( wpf_pagina_voci_menu( $r['id'] ) as $v ) {
				$riga( 'MENU', "{$nome}: la voce «{$v}» porta a una pagina in bozza (404 per chi non è loggato)" );
			}
		}
		if ( $prova ) {
			foreach ( $r['mancanti'] as $cosa => $elementi ) {
				foreach ( $elementi as $dettaglio => $causa ) {
					$riga( 'AVVISO', "{$nome}: {$cosa}" . ( $dettaglio !== '' ? " {$dettaglio}" : '' ) . " non riuscirebbe: {$causa}" );
					$previsti[] = [ 'cosa' => $cosa ];
				}
			}
			continue;
		}
		// Gli elementi trattati in questo giro escono dall'elenco; quelli falliti ci rientrano
		foreach ( $mancanti as $k => $m ) {
			if ( $m['pagina'] === $chiave && $m['lingua'] === $lingua && ( $solo_mancanti ? in_array( $m['cosa'], $solo[ $chiave ][ $lingua ], true ) : true ) ) {
				unset( $mancanti[ $k ] );
			}
		}
		foreach ( $r['mancanti'] as $cosa => $elementi ) {
			foreach ( $elementi as $dettaglio => $causa ) {
				$k              = wpf_mancante_chiave( $cosa, $chiave, $lingua, (string) $dettaglio );
				$mancanti[ $k ] = [
					'cosa'      => $cosa,
					'pagina'    => $chiave,
					'lingua'    => $lingua,
					'dettaglio' => (string) $dettaglio,
					'causa'     => $causa,
					'primo'     => $mancanti_prima[ $k ]['primo'] ?? $ora,
					'ultimo'    => $ora,
				];
			}
		}
	}
}

// In anteprima l'elenco non cambia: si mostrano gli elementi che risultano mancanti adesso
if ( ! $prova ) {
	wpf_mancanti_salva( $mancanti );
}
foreach ( $mancanti as $m ) {
	$riga( 'MANCANTE', "{$m['cosa']} {$m['pagina']}/{$m['lingua']}" . ( $m['dettaglio'] !== '' ? " {$m['dettaglio']}" : '' ) . ": {$m['causa']} (dal {$m['primo']})" );
}
$riga(
	'RIASSUNTO',
	( $prova ? 'anteprima, nulla scritto — ' : '' ) . implode( ', ', array_map( fn( $e, $n ) => "{$e}: {$n}", array_keys( $conteggi ), $conteggi ) )
	. '; mancanti: ' . count( $mancanti )
);
// Codice d'uscita: 1 solo se manca qualcosa che non si sistema da sé (una pagina non salvata, una
// traduzione non collegata, un template assente). Un media non scaricato (sito Drupal lento) lascia le
// pagine a posto e si ritenta al giro dopo: avviso nel log e uscita 0, così un cron o una catena di
// import non si ferma per questo (decisione del dev, oc:8758)
$gravi = array_filter( array_merge( $mancanti, $previsti ), fn( $m ) => ! isset( WPF_PAGINA_MEDIA[ $m['cosa'] ] ) );
if ( $gravi ) {
	WP_CLI::halt( 1 );
}
if ( $mancanti ) {
	$riga( 'AVVISO', count( $mancanti ) . ' media non scaricati: le pagine sono aggiornate, si ritentano al prossimo giro o con --contenuti-mancanti' );
}
// Con la sincronizzazione spenta anche l'anteprima esce con 1: chi la lancia (o un cron) sa che non
// scriverebbe, anche se le differenze sono quelle di sempre
if ( ! $sync ) {
	WP_CLI::halt( 1 );
}
