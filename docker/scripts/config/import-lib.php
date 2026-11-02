<?php
/**
 * Import da Drupal dei contenuti con un nodo (oc:8760): news, eventi, «da sapere». Ogni tipo è un
 * descrittore (wpf_import_tipi) con la sua lettura dalla copia, i suoi termini e la sua scrittura; il giro
 * comune (controlli sulla copia, lingue, link, log, mancanti, bozze) è wpf_import_sincronizza, che
 * pagine.php chiama per ogni tipo dopo le pagine statiche.
 *
 * Descrittore di un tipo:
 *   'origine'      chiave dei mancanti (wpf_sync_mancanti) e prefisso dei messaggi: news, evento, da_sapere
 *   'riga'         tipo della riga di log: NEWS, EVENTO, DA_SAPERE
 *   'nome'         come il log chiama un contenuto («news 4606/it: …»)
 *   'plurale'      come i messaggi chiamano più contenuti («5 news in bozza»)
 *   'questo'       come l'avviso dello slug chiama il contenuto («la news non si raggiunge»)
 *   'genere'       accordo dei messaggi sui contenuti, con le chiavi di WPF_IMPORT_GENERE («nessuno» =>
 *                  «nessuna news scritta», «pubblicati» => «pubblicate», «tolti» => «state tolte»,
 *                  «accettati» => «accettate», «sincronizzati» => «sincronizzate»)
 *   'post_type'    tipo di contenuto WordPress
 *   'tassonomie'   tassonomie in cui scrive i termini: con il tipo, devono essere registrate (dal child) prima
 *                  del giro, se no i contenuti nascerebbero di un tipo sconosciuto e senza termini
 *   'prefisso'     primo pezzo dell'indirizzo (/news/<slug>/): se manca, lo slug del rewrite del tipo
 *                  registrato dal child (/evento/<slug>/, /da-sapere/<slug>/), letto da wpf_import_tipi
 *   'schede'       chiave in config/pagine.json della pagina le cui schede figlie stanno sotto il prefisso
 *                  (null se nessuna): uno slug uguale a quello di una scheda non si raggiunge
 *   'pagina'       nome di quella pagina nell'avviso («della pagina News»)
 *   'soglia_bozze' più contenuti (o traduzioni) da mettere in bozza nello stesso giro: copia sospetta
 *   'scarto_copia' più di così fra i pubblicati in WordPress e nella copia: copia a metà
 *   'conteggio'    variabile d'ambiente che, in anteprima, simula i pubblicati letti dalla copia
 *   'bundle'       tipo di contenuto Drupal
 *   'nodo'         fn( mysqli, int $nid, string $lingua ): ?array, un contenuto dalla copia
 *   'termini'      fn( mysqli, array $dati, bool $prova ): array con 'righe', 'mancanti' e i termini
 *   'scrivi'       fn( int $nid, string $lingua, array $d, ?WP_Post, ?int $id_it, array $termini,
 *                      int $template, bool $prova, ?array $solo ): array come wpf_contenuto_scrivi + 'avvisi'
 *   'template'     chiave in config/post.json del template di Impreza
 *
 * L'elenco dei tipi è in import-tipi.php.
 */

require_once __DIR__ . '/contenuti-lib.php';

// Chiavi di 'genere' nel descrittore di un tipo
const WPF_IMPORT_GENERE = [ 'nessuno', 'pubblicati', 'tolti', 'accettati', 'sincronizzati' ];

// «In evidenza» di Drupal (field_in_evidenza): «1» o assente. Le griglie «in evidenza» filtrano su questo
const WPF_META_IN_EVIDENZA = 'wpf_in_evidenza';
// «Collegamenti» (field_collegamenti): [ [ 'titolo', 'url' ] ], url con i link interni già riscritti
const WPF_META_COLLEGAMENTI = 'wpf_collegamenti';
// Riferimenti a contenuti del catasto: [ [ 'tipo', 'nid' ] ]. Non si mostrano: il dato resta per il plugin
// che li leggerà dallo shard, dove ogni contenuto ha il suo nid Drupal (oc:8759)
const WPF_META_RIFERIMENTI = '_wpf_riferimenti';
// Tipo di un riferimento per bundle di Drupal: nomi nostri, non quelli di Drupal
const WPF_TIPI_RIFERIMENTO = [
	'ente_istituzione_societa' => 'ente',
	'sentiero'                 => 'sentiero',
	'itinerario'               => 'itinerario',
	'poi'                      => 'poi',
];

/**
 * Termini di Drupal ritrovati, fusi o creati in WordPress (wpf_termine_ritrova_o_crea), con il nome italiano.
 *
 * @param array $voci [ chiave => [ etichetta, dettaglio, vid, tid, tassonomia, parent ] ]; etichetta e
 *                    dettaglio fanno la riga di log («tag «sentieri» (tags:4213): creato»)
 * @return array [ 'id' => [ chiave => id, 0 in anteprima se da creare ], 'righe' => string[],
 *                 'mancanti' => [ "etichetta dettaglio" => causa ] ]
 */
function wpf_import_termini( mysqli $db, array $voci, bool $prova ): array {
	$termini  = wpf_drupal_termini( $db, array_column( $voci, 3 ) );
	$id       = [];
	$righe    = [];
	$mancanti = [];
	foreach ( $voci as $chiave => [ $etichetta, $dettaglio, $vid, $tid, $tassonomia, $parent ] ) {
		$nome = $termini[ $tid ]['nome']['it'] ?? reset( $termini[ $tid ]['nome'] );
		$r    = wpf_termine_ritrova_o_crea( $vid, $tid, $nome, $tassonomia, $parent, $prova );
		if ( $r['esito'] !== 'ritrovato' ) {
			$righe[] = "{$etichetta} «{$nome}» ({$dettaglio}): {$r['esito']}" . ( $r['errore'] ? " ({$r['errore']})" : '' );
		}
		if ( $r['errore'] ) {
			$mancanti[ "{$etichetta} {$dettaglio}" ] = $r['errore'];
		}
		$id[ $chiave ] = $r['id'];
	}
	return [ 'id' => $id, 'righe' => $righe, 'mancanti' => $mancanti ];
}

/**
 * Tag di Drupal dei contenuti (vocabolari tags, tag_news, tag_eventi) come post_tag: [ "vid:tid" => id ],
 * più righe e mancanti come wpf_import_termini.
 */
function wpf_import_tag( mysqli $db, array $dati, bool $prova ): array {
	$voci = [];
	foreach ( $dati as $lingue ) {
		foreach ( $lingue as $d ) {
			foreach ( $d['tag'] as [ $tid, $vid ] ) {
				$voci[ "{$vid}:{$tid}" ] = [ 'tag', "{$vid}:{$tid}", $vid, $tid, 'post_tag', 0 ];
			}
		}
	}
	$t = wpf_import_termini( $db, $voci, $prova );
	return [ 'tag' => $t['id'], 'righe' => $t['righe'], 'mancanti' => $t['mancanti'] ];
}

/**
 * Id dei post_tag di un contenuto. Due tag di Drupal fusi in un termine solo (wpf_termine_ritrova_o_crea)
 * darebbero lo stesso id due volte, e i termini risulterebbero cambiati a ogni giro.
 *
 * @return array [ 'id' => int[], 'avvisi' => string[] ]
 */
function wpf_import_tag_contenuto( array $d, array $tag ): array {
	return [
		'id'     => array_values( array_filter( array_unique( array_map( fn( $t ) => $tag[ "{$t[1]}:{$t[0]}" ] ?? 0, $d['tag'] ) ) ) ),
		'avvisi' => array_map( fn( $tid ) => "il tag {$tid} non esiste più su Drupal: non si importa", $d['tag_mancanti'] ),
	];
}

/**
 * Riferimenti di Drupal ([ bundle, nid ]) come si salvano in WPF_META_RIFERIMENTI, più gli avvisi per i
 * nodi che non sono contenuti del catasto.
 *
 * @return array [ 'riferimenti' => [ [ 'tipo', 'nid' ] ], 'avvisi' => string[] ]
 */
function wpf_import_riferimenti( array $riferimenti ): array {
	$r      = [];
	$avvisi = [];
	foreach ( $riferimenti as [ $bundle, $collegato ] ) {
		if ( isset( WPF_TIPI_RIFERIMENTO[ $bundle ] ) ) {
			$r[] = [ 'tipo' => WPF_TIPI_RIFERIMENTO[ $bundle ], 'nid' => $collegato ];
		} else {
			$avvisi[] = "riferimento al nodo {$collegato} di tipo «{$bundle}», che non è un contenuto del catasto: non si salva";
		}
	}
	return [ 'riferimenti' => $r, 'avvisi' => $avvisi ];
}

/**
 * Collegamenti di Drupal con l'indirizzo che il sito userà: «internal:/x» ed «entity:node/N» diventano
 * indirizzi del sito Drupal e passano dalla stessa riscrittura dei link del testo.
 *
 * @return array [ 'collegamenti' => [ [ 'titolo', 'url' ] ], 'link' => risultato di wpf_link_riscrivi ]
 */
function wpf_import_collegamenti( array $collegamenti, array $alias, callable $trova, string $lingua ): array {
	$html = '';
	foreach ( $collegamenti as [ , $uri ] ) {
		$url   = preg_replace( [ '#^internal:/?#', '#^entity:node/#' ], [ WPF_SITO_DRUPAL . '/', WPF_SITO_DRUPAL . '/node/' ], $uri );
		$html .= '<a href="' . esc_attr( $url ) . '"></a>' . "\n";
	}
	$l = wpf_link_riscrivi( $html, $alias, $trova, $lingua );
	preg_match_all( '#href="([^"]*)"#', $l['html'], $m );
	$risultato = [];
	foreach ( array_values( $collegamenti ) as $i => [ $titolo ] ) {
		$url         = html_entity_decode( $m[1][ $i ] ?? '' );
		$risultato[] = [ 'titolo' => $titolo !== '' ? $titolo : $url, 'url' => $url ];
	}
	return [ 'collegamenti' => $risultato, 'link' => $l ];
}

/** Vecchi indirizzi Drupal di un contenuto (WPF_META_ALIAS_DRUPAL): /node/<nid> e il suo alias, se c'è. */
function wpf_import_alias( int $nid, array $d ): array {
	$percorsi = array_values( array_unique( array_filter( [ "node/{$nid}", $d['percorso'] ?? null ] ) ) );
	sort( $percorsi );
	return $percorsi;
}

/**
 * Slug di un contenuto: l'ultimo pezzo dell'alias di Drupal, o il titolo se il nodo non ha alias. Passa da
 * sanitize_title come farebbe WordPress, che toglie accenti e apostrofi tipografici («galtellì» diventa
 * «galtelli»): così lo slug voluto è quello che WordPress salva, e il vecchio indirizzo con gli accenti
 * porta al contenuto con il redirect del child (inc/pagine-statiche.php, WPF_META_ALIAS_DRUPAL).
 */
function wpf_import_slug( array $d ): string {
	return sanitize_title( $d['alias'] !== null ? rawurldecode( $d['alias'] ) : $d['titolo'] );
}

/** Percorso relativo che un contenuto ha (o avrà) in una lingua: /<prefisso>/<slug>/ o /en/<prefisso>/<slug>/. */
function wpf_import_percorso( array $tipo, string $slug, string $lingua ): string {
	return ( $lingua === 'it' ? '/' : WPF_PREFISSO_INGLESE ) . $tipo['prefisso'] . "/{$slug}/";
}

/** Contenuti di un tipo, di qualunque stato tranne il cestino: [ nid ][ lingua ] => WP_Post. */
function wpf_import_esistenti( array $tipo ): array {
	$trovati = [];
	$post    = wpf_wpml_tutte_le_lingue(
		fn() => get_posts(
			[
				'post_type'        => $tipo['post_type'],
				'post_status'      => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'numberposts'      => -1,
				'meta_key'         => WPF_META_NID,
				'suppress_filters' => true,
				'orderby'          => 'ID',
				'order'            => 'ASC',
			]
		)
	);
	foreach ( $post as $p ) {
		$nid    = (int) get_post_meta( $p->ID, WPF_META_NID, true );
		$lingua = (string) get_post_meta( $p->ID, WPF_META_LINGUA, true );
		$trovati[ $nid ][ $lingua ] ??= $p;
	}
	return $trovati;
}

/**
 * Lettura di tutti i contenuti di un tipo dalla copia: [ nid ][ lingua ] => dati del 'nodo' del tipo. Le
 * lingue sono quelle che il nodo ha su Drupal (it per «und»).
 */
function wpf_import_leggi( array $tipo, mysqli $db, array $elenco ): array {
	$dati = [];
	foreach ( $elenco as $nid => $n ) {
		foreach ( array_unique( $n['lingue'] ) as $lingua ) {
			if ( ! isset( WPF_LINGUE_WPML[ $lingua ] ) ) {
				continue;
			}
			$d = ( $tipo['nodo'] )( $db, $nid, $lingua );
			if ( $d ) {
				$dati[ $nid ][ $lingua ] = $d;
			}
		}
	}
	return $dati;
}

/**
 * Controlli prima di scrivere: una copia Drupal caricata a metà (reload delle 10:00, una tabella per
 * volta) passa wpf_drupal_controlla appena ogni tabella ha una riga, ma restituisce meno contenuti. I conti
 * si fanno sulle stesse coppie nid/lingua che il giro poi mette in bozza (wpf_import_sincronizza).
 *
 * @param ?int $conteggio per provare il controllo: contenuti pubblicati da considerare letti dalla copia
 * @return array [ 'in_bozza' => [ "nid/lingua" => motivo ], 'errore' => ?string ]
 */
function wpf_import_controlla( array $tipo, array $dati, array $esistenti, bool $accetta_bozze, ?int $conteggio = null ): array {
	$in_bozza = [];
	foreach ( $esistenti as $nid => $lingue ) {
		foreach ( $lingue as $lingua => $post ) {
			if ( $post->post_status === 'draft' ) {
				continue;
			}
			if ( ! isset( $dati[ $nid ][ $lingua ] ) ) {
				$in_bozza[ "{$nid}/{$lingua}" ] = 'non più nella copia Drupal';
			} elseif ( ! $dati[ $nid ][ $lingua ]['status'] ) {
				$in_bozza[ "{$nid}/{$lingua}" ] = 'non pubblicata su Drupal';
			}
		}
	}
	$pubblicate_wp    = count( array_filter( $esistenti, fn( $l ) => isset( $l['it'] ) && $l['it']->post_status === 'publish' ) );
	$pubblicate_copia = $conteggio ?? count( array_filter( $dati, fn( $l ) => ! empty( $l['it']['status'] ) ) );
	$plurale          = $tipo['plurale'];
	[ 'nessuno' => $nessuno, 'pubblicati' => $pubblicati, 'tolti' => $tolti ] = $tipo['genere'];
	$errore           = null;
	if ( ! $accetta_bozze && count( $in_bozza ) > $tipo['soglia_bozze'] ) {
		$errore = 'copia Drupal sospetta: ' . count( $in_bozza ) . " {$plurale} da mettere in bozza nello stesso giro (" . implode( ', ', array_keys( $in_bozza ) ) . '), più di ' . $tipo['soglia_bozze'];
	} elseif ( ! $accetta_bozze && $pubblicate_copia < $pubblicate_wp - $tipo['scarto_copia'] ) {
		$errore = "copia Drupal sospetta: {$pubblicate_copia} {$plurale} {$pubblicati} nella copia contro {$pubblicate_wp} in WordPress, più di {$tipo['scarto_copia']} di differenza";
	}
	if ( $errore ) {
		$errore .= ": {$nessuno}. Se la copia è in ricarica riprova più tardi; se su Drupal sono {$tolti} davvero: pagine --accetta-bozze";
	}
	return [ 'in_bozza' => $in_bozza, 'errore' => $errore ];
}

/** Percorsi di un tipo per la riscrittura dei link: [ "nid|lingua" => percorso ] dei pubblicati, e gli spubblicati. */
function wpf_import_percorsi( array $tipo, array $dati ): array {
	$percorsi = [];
	$in_bozza = [];
	foreach ( $dati as $nid => $lingue ) {
		foreach ( $lingue as $lingua => $d ) {
			if ( $d['status'] ) {
				$percorsi[ "{$nid}|{$lingua}" ] = wpf_import_percorso( $tipo, wpf_import_slug( $d ), $lingua );
			} else {
				$in_bozza[ "{$nid}|{$lingua}" ] = true;
			}
		}
	}
	return [ 'percorsi' => $percorsi, 'in_bozza' => $in_bozza ];
}

/**
 * Il giro di un tipo, dopo quello delle pagine (pagine.php): controlli sulla copia, termini, scrittura in
 * italiano e in inglese, bozza per i contenuti spubblicati o non più nella copia.
 *
 * @param array    $mancanti  elenco dei mancanti con l'origine del tipo (da wpf_mancanti_leggi), già filtrato
 * @param ?array   $solo      con --contenuti-mancanti: [ nid ][ lingua ] => [ cosa ]
 * @param callable $trova     fn( int $nid, string $lingua ): ?string, come per le pagine
 * @param callable $riga      fn( string $tipo, string $testo )
 * @return array [ 'conteggi' => [ esito => n ], 'mancanti' => elenco aggiornato, 'previsti' => [ [ cosa ] ],
 *                 'errore' => bool ]
 */
function wpf_import_sincronizza( array $tipo, mysqli $db, array $elenco, array $dati, array $alias, callable $trova, array $mancanti, ?array $solo, bool $prova, bool $accetta_bozze, callable $riga ): array {
	$conteggi  = [];
	$previsti  = [];
	$ora       = gmdate( 'c' );
	$esistenti = wpf_import_esistenti( $tipo );
	$nome_tipo = $tipo['nome'];

	if ( $solo === null ) {
		$simulato  = getenv( $tipo['conteggio'] );
		$conteggio = $prova && $simulato !== false ? (int) $simulato : null;
		$controllo = wpf_import_controlla( $tipo, $dati, $esistenti, $accetta_bozze, $conteggio );
		if ( $controllo['errore'] ) {
			$riga( 'ERRORE', $controllo['errore'] );
			return [ 'conteggi' => [], 'mancanti' => $mancanti, 'previsti' => [], 'errore' => true ];
		}
		if ( count( $controllo['in_bozza'] ) > $tipo['soglia_bozze'] ) {
			$riga( 'AVVISO', count( $controllo['in_bozza'] ) . " {$tipo['plurale']} in bozza nello stesso giro, {$tipo['genere']['accettati']} con --accetta-bozze" );
		}
	}

	// Con --contenuti-mancanti i termini dei soli contenuti da ritentare: uno ritentato per intero li riscrive
	$termini = ( $tipo['termini'] )( $db, $solo === null ? $dati : array_intersect_key( $dati, $solo ), $prova );
	foreach ( $termini['righe'] as $r ) {
		$riga( 'TERMINE', $r );
	}
	foreach ( $termini['mancanti'] as $dettaglio => $causa ) {
		$riga( 'ERRORE', "termine non creato, {$dettaglio}: {$causa}" );
		$previsti[] = [ 'cosa' => 'termine' ];
	}
	$p        = wpf_post_per_chiave( $tipo['template'] );
	$template = $p && $p->post_type === 'us_content_template' ? $p->ID : 0;
	// Slug delle schede figlie della pagina sotto il prefisso (quelle con una regola di rewrite): un
	// contenuto con lo stesso slug non si raggiungerebbe (l'elenco è quello del child, inc/news.php, che
	// WP-CLI carica con il tema)
	$schede = [];
	if ( $tipo['schede'] !== null && function_exists( 'forestas_schede_figlie' ) ) {
		$sotto  = array_filter( forestas_schede_figlie(), fn( $s ) => dirname( $s ) === $tipo['prefisso'] );
		$schede = array_map( 'basename', $sotto );
	}

	foreach ( $dati as $nid => $lingue ) {
		$id_it = null;
		foreach ( array_keys( WPF_LINGUE_WPML ) as $lingua ) {
			if ( ! isset( $lingue[ $lingua ] ) ) {
				continue;
			}
			$post = $esistenti[ $nid ][ $lingua ] ?? null;
			if ( $solo !== null && ! isset( $solo[ $nid ][ $lingua ] ) ) {
				$id_it = $lingua === 'it' ? ( $post->ID ?? null ) : $id_it;
				continue;
			}
			$d         = $lingue[ $lingua ];
			$trova_qui = wpf_link_trova_in_lingua( $trova, $lingua );
			$d['slug'] = wpf_import_slug( $d );
			$l         = wpf_link_riscrivi( wpf_contenuto_pulisci( $d['body'], $d['formato'] ), $alias, $trova_qui, $lingua );
			$d['body'] = $l['html'];
			$c         = wpf_import_collegamenti( $d['collegamenti'] ?? [], $alias, $trova_qui, $lingua );
			if ( isset( $d['collegamenti'] ) ) {
				$d['collegamenti'] = $c['collegamenti'];
			}
			$r = ( $tipo['scrivi'] )( $nid, $lingua, $d, $post, $id_it, $termini, $template, $prova, $solo[ $nid ][ $lingua ] ?? null );
			if ( $lingua === 'it' ) {
				$id_it = $r['id'];
			}
			$nome                    = "{$nid}/{$lingua}";
			$conteggi[ $r['esito'] ] = ( $conteggi[ $r['esito'] ] ?? 0 ) + 1;
			$riga( $tipo['riga'], "{$nome}: {$r['esito']}" . ( $r['campi'] ? ' (' . implode( ', ', $r['campi'] ) . ')' : '' ) . ( $d['status'] ? '' : ' — in bozza: non pubblicata su Drupal' ) );
			foreach ( $r['avvisi'] as $a ) {
				$riga( 'AVVISO', "{$nome_tipo} {$nome}: {$a}" );
			}
			if ( in_array( $d['slug'], $schede, true ) ) {
				$riga( 'AVVISO', "{$nome_tipo} {$nome}: lo slug «{$d['slug']}» è quello di una scheda della pagina {$tipo['pagina']}, che ha la precedenza: {$tipo['questo']} non si raggiunge" );
			}
			foreach ( $r['media'] as $m ) {
				$riga( 'MEDIA', "{$nome_tipo} {$nome}: {$m}" );
			}
			wpf_link_log( $riga, "{$nome_tipo} {$nome}", $l );
			wpf_link_log( $riga, "{$nome_tipo} {$nome}", $c['link'] );
			if ( $prova ) {
				$previsti = array_merge( $previsti, wpf_mancanti_previsti( $riga, "{$nome_tipo} {$nome}", $r['mancanti'] ) );
				continue;
			}
			$mancanti = wpf_mancanti_aggiorna( $mancanti, $tipo['origine'], (string) $nid, $lingua, $solo[ $nid ][ $lingua ] ?? null, $r['mancanti'], $ora );
		}
	}

	// Contenuti, o loro traduzioni, che non sono più nella copia (cancellati su Drupal): in bozza, mai
	// cancellati. Anche la sola traduzione inglese tolta su Drupal: il post inglese non lo aggiornerebbe più nessuno
	if ( $solo === null ) {
		foreach ( $esistenti as $nid => $lingue ) {
			foreach ( $lingue as $lingua => $post ) {
				if ( isset( $dati[ $nid ][ $lingua ] ) || $post->post_status === 'draft' ) {
					continue;
				}
				$conteggi['in bozza'] = ( $conteggi['in bozza'] ?? 0 ) + 1;
				$riga( $tipo['riga'], "{$nid}/{$lingua}: in bozza — " . ( isset( $elenco[ $nid ] ) ? 'la traduzione non è più nella copia Drupal' : 'non più nella copia Drupal' ) );
				if ( ! $prova ) {
					wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'draft' ] );
				}
			}
		}
	}
	return [ 'conteggi' => $conteggi, 'mancanti' => $mancanti, 'previsti' => $previsti, 'errore' => false ];
}
