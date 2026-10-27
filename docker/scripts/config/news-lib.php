<?php
/**
 * Sincronizzazione delle news da Drupal (oc:8759), lanciata da pagine.php dopo le pagine statiche.
 *
 * Ogni news it/und di Drupal è un post con la categoria «news»; le tipologie (tipologia_news) sono
 * sottocategorie di «news», i tag (tag_news e tags) sono post_tag. Una news si riconosce da _drupal_nid e
 * _wpf_lingua, come le pagine dal perimetro. Le news tradotte su Drupal hanno il post inglese collegato
 * in WPML; le altre si vedono in italiano anche sul sito inglese (ripiego di WPML, config/wpml.json).
 *
 * Categorie e tag sono gli stessi nelle due lingue: su Drupal tipologia_news, tag_news e tags non hanno
 * traduzioni, e WPML li mostra come sono (ripiego anche sulle tassonomie).
 */

require_once __DIR__ . '/contenuti-lib.php';

// Più news (o traduzioni) di così da mettere in bozza nello stesso giro: copia Drupal sospetta, ci si ferma
const WPF_NEWS_SOGLIA_BOZZE = 5;
// Più di così fra le news pubblicate in WordPress e quelle pubblicate nella copia: copia a metà, ci si ferma
const WPF_NEWS_SCARTO_COPIA = 5;
// «In evidenza» di Drupal (field_in_evidenza): «1» o assente. Le griglie «in evidenza» filtrano su questo
const WPF_META_IN_EVIDENZA = 'wpf_in_evidenza';
// «Collegamenti» (field_collegamenti): [ [ 'titolo', 'url' ] ], url con i link interni già riscritti
const WPF_META_COLLEGAMENTI = 'wpf_collegamenti';
// Riferimenti (field_references) a contenuti del catasto: [ [ 'tipo', 'nid' ] ]. Non si mostrano: il dato
// resta per il plugin che li leggerà dallo shard, dove ogni contenuto ha il suo nid Drupal (oc:8759)
const WPF_META_RIFERIMENTI = '_wpf_riferimenti';
// Tipo di un riferimento per bundle di Drupal: nomi nostri, non quelli di Drupal
const WPF_NEWS_TIPI_RIFERIMENTO = [
	'ente_istituzione_societa' => 'ente',
	'sentiero'                 => 'sentiero',
	'itinerario'               => 'itinerario',
	'poi'                      => 'poi',
];
// Chiave in config/post.json del template di Impreza della news
const WPF_TEMPLATE_NEWS = 'template-news';
// Categoria che contiene tutte le news; le tipologie di Drupal sono sue figlie
const WPF_NEWS_CATEGORIA = 'news';
// Prefisso degli indirizzi delle news, quello di permalink_structure in config/sito.json (/news/%postname%/)
const WPF_NEWS_PREFISSO = 'news';

/** Post delle news, di qualunque stato tranne il cestino: [ nid ][ lingua ] => WP_Post. */
function wpf_news_esistenti(): array {
	$news = [];
	$post = wpf_wpml_tutte_le_lingue(
		fn() => get_posts(
			[
				'post_type'        => 'post',
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
		$news[ $nid ][ $lingua ] ??= $p;
	}
	return $news;
}

/**
 * La categoria che contiene le news, ritrovata per slug o creata: [ 'id', 'esito', 'errore' ] come
 * wpf_termine_ritrova_o_crea. Non viene da un termine di Drupal, quindi non ha il term meta.
 */
function wpf_news_categoria( bool $prova ): array {
	$c = wpf_wpml_tutte_le_lingue( fn() => get_term_by( 'slug', WPF_NEWS_CATEGORIA, 'category' ) );
	if ( $c ) {
		return [ 'id' => (int) $c->term_id, 'esito' => 'ritrovato', 'errore' => null ];
	}
	return $prova ? [ 'id' => 0, 'esito' => 'da creare', 'errore' => null ] : wpf_termine_crea( 'News', 'category', [ 'slug' => WPF_NEWS_CATEGORIA ] );
}

/**
 * Categoria predefinita dei post impostata su News, in tutte le lingue di WPML (in WPML vale per lingua:
 * un post creato in inglese prenderebbe quella della sua lingua). Vero se c'era da cambiarla.
 */
function wpf_news_categoria_predefinita( int $categoria, bool $prova ): bool {
	global $sitepress;
	$lingue   = $sitepress ? array_keys( (array) apply_filters( 'wpml_active_languages', [], [ 'skip_missing' => 0 ] ) ) : [];
	$per_lingua = $sitepress ? (array) $sitepress->get_setting( 'default_categories', [] ) : [];
	$da_fare  = (int) get_option( 'default_category' ) !== $categoria
		|| array_filter( $lingue, fn( $l ) => (int) ( $per_lingua[ $l ] ?? 0 ) !== $categoria );
	if ( $da_fare && ! $prova ) {
		update_option( 'default_category', $categoria );
		if ( $sitepress && $lingue ) {
			$sitepress->set_setting( 'default_categories', array_merge( $per_lingua, array_fill_keys( $lingue, (string) $categoria ) ), true );
		}
	}
	return (bool) $da_fare;
}

/**
 * Collegamenti di Drupal con l'indirizzo che il sito userà: «internal:/x» ed «entity:node/N» diventano
 * indirizzi del sito Drupal e passano dalla stessa riscrittura dei link del testo.
 *
 * @return array [ 'collegamenti' => [ [ 'titolo', 'url' ] ], 'link' => risultato di wpf_link_riscrivi ]
 */
function wpf_news_collegamenti( array $collegamenti, array $alias, callable $trova, string $lingua ): array {
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

/**
 * Slug di una news: l'ultimo pezzo dell'alias di Drupal, o il titolo se il nodo non ha alias. Passa da
 * sanitize_title come farebbe WordPress, che toglie accenti e apostrofi tipografici («galtellì» diventa
 * «galtelli»): così lo slug voluto è quello che WordPress salva, e il vecchio indirizzo con gli accenti
 * porta alla news con il redirect del child (inc/news.php).
 */
function wpf_news_slug( array $d ): string {
	return sanitize_title( $d['alias'] !== null ? rawurldecode( $d['alias'] ) : $d['titolo'] );
}

/** Percorso relativo che una news ha (o avrà) in una lingua: /news/<slug>/ o /en/news/<slug>/. */
function wpf_news_percorso( string $slug, string $lingua ): string {
	return ( $lingua === 'it' ? '/' : WPF_PREFISSO_INGLESE ) . WPF_NEWS_PREFISSO . "/{$slug}/";
}

/**
 * Lettura di tutte le news della copia: [ nid ][ lingua ] => dati di wpf_drupal_news. Le lingue sono
 * quelle che il nodo ha su Drupal (it per «und»).
 */
function wpf_news_leggi( mysqli $db, array $elenco ): array {
	$dati = [];
	foreach ( $elenco as $nid => $n ) {
		foreach ( array_unique( $n['lingue'] ) as $lingua ) {
			if ( ! isset( WPF_LINGUE_WPML[ $lingua ] ) ) {
				continue;
			}
			$d = wpf_drupal_news( $db, $nid, $lingua );
			if ( $d ) {
				$dati[ $nid ][ $lingua ] = $d;
			}
		}
	}
	return $dati;
}

/**
 * Controlli prima di scrivere le news: una copia Drupal caricata a metà (reload delle 10:00, una tabella
 * per volta) passa wpf_drupal_controlla appena ogni tabella ha una riga, ma restituisce meno news. I conti
 * si fanno sulle stesse coppie nid/lingua che il giro poi mette in bozza (wpf_news_sincronizza).
 *
 * @param array $dati       da wpf_news_leggi: [ nid ][ lingua ] => dati
 * @param array $esistenti  da wpf_news_esistenti
 * @param ?int  $conteggio  per provare il controllo: news pubblicate da considerare lette dalla copia
 * @return array [ 'in_bozza' => [ "nid/lingua" => motivo ], 'errore' => ?string ]
 */
function wpf_news_controlla( array $dati, array $esistenti, bool $accetta_bozze, ?int $conteggio = null ): array {
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
	$errore           = null;
	if ( ! $accetta_bozze && count( $in_bozza ) > WPF_NEWS_SOGLIA_BOZZE ) {
		$errore = 'copia Drupal sospetta: ' . count( $in_bozza ) . ' news da mettere in bozza nello stesso giro (' . implode( ', ', array_keys( $in_bozza ) ) . '), più di ' . WPF_NEWS_SOGLIA_BOZZE;
	} elseif ( ! $accetta_bozze && $pubblicate_copia < $pubblicate_wp - WPF_NEWS_SCARTO_COPIA ) {
		$errore = "copia Drupal sospetta: {$pubblicate_copia} news pubblicate nella copia contro {$pubblicate_wp} in WordPress, più di " . WPF_NEWS_SCARTO_COPIA . ' di differenza';
	}
	if ( $errore ) {
		$errore .= ': nessuna news scritta. Se la copia è in ricarica riprova più tardi; se su Drupal sono state tolte davvero: pagine --accetta-bozze';
	}
	return [ 'in_bozza' => $in_bozza, 'errore' => $errore ];
}

/**
 * Termini delle news (categoria, tipologie, tag): [ 'categoria' => id, 'tipologie' => [ tid => id ],
 * 'tag' => [ "vid:tid" => id ], 'righe' => righe di log, 'mancanti' => [ dettaglio => causa ] ]. In
 * anteprima un termine da creare ha id 0.
 */
function wpf_news_termini( mysqli $db, array $dati, bool $prova ): array {
	$righe     = [];
	$mancanti  = [];
	$categoria = wpf_news_categoria( $prova );
	if ( $categoria['esito'] !== 'ritrovato' ) {
		$righe[] = "categoria «News»: {$categoria['esito']}" . ( $categoria['errore'] ? " ({$categoria['errore']})" : '' );
	}
	if ( $categoria['errore'] ) {
		$mancanti['categoria News'] = $categoria['errore'];
	}
	// Una news scritta dalla redazione in WordPress parte nella categoria News, non in «Senza categoria»:
	// le schede e le pagine d'elenco mostrano solo la categoria News e le sue tipologie. Con WPML la
	// categoria predefinita è per lingua: la stessa per tutte (le categorie si mostrano col ripiego)
	if ( $categoria['id'] && wpf_news_categoria_predefinita( $categoria['id'], $prova ) ) {
		$righe[] = 'categoria predefinita dei post: News' . ( $prova ? ' (da impostare)' : '' );
	}
	$tid_tipologie = [];
	$coppie_tag    = [];
	foreach ( $dati as $lingue ) {
		foreach ( $lingue as $d ) {
			if ( $d['tipologia'] !== null ) {
				$tid_tipologie[] = $d['tipologia'];
			}
			foreach ( $d['tag'] as [ $tid, $vid ] ) {
				$coppie_tag[ "{$vid}:{$tid}" ] = [ $vid, $tid ];
			}
		}
	}
	$termini = wpf_drupal_termini( $db, array_merge( $tid_tipologie, array_column( $coppie_tag, 1 ) ) );
	// Un termine ritrovato, fuso o creato: il nome è quello italiano, come lo crea wpf_termine_ritrova_o_crea
	$termine = function ( string $etichetta, string $chiave, string $vid, int $tid, string $tassonomia, int $parent ) use ( $termini, $prova, &$righe, &$mancanti ): int {
		$nome = $termini[ $tid ]['nome']['it'] ?? reset( $termini[ $tid ]['nome'] );
		$r    = wpf_termine_ritrova_o_crea( $vid, $tid, $nome, $tassonomia, $parent, $prova );
		if ( $r['esito'] !== 'ritrovato' ) {
			$righe[] = "{$etichetta} «{$nome}» ({$chiave}): {$r['esito']}" . ( $r['errore'] ? " ({$r['errore']})" : '' );
		}
		if ( $r['errore'] ) {
			$mancanti[ "{$etichetta} {$chiave}" ] = $r['errore'];
		}
		return $r['id'];
	};
	$tipologie = [];
	foreach ( array_unique( $tid_tipologie ) as $tid ) {
		if ( isset( $termini[ $tid ] ) ) { // una tipologia che su Drupal non esiste più lo dice la riga della news
			$tipologie[ $tid ] = $termine( 'tipologia', (string) $tid, $termini[ $tid ]['vid'], $tid, 'category', $categoria['id'] );
		}
	}
	$tag = [];
	foreach ( $coppie_tag as $chiave => [ $vid, $tid ] ) {
		$tag[ $chiave ] = $termine( 'tag', $chiave, $vid, $tid, 'post_tag', 0 );
	}
	return [ 'categoria' => $categoria['id'], 'tipologie' => $tipologie, 'tag' => $tag, 'righe' => $righe, 'mancanti' => $mancanti ];
}

/**
 * Crea o aggiorna una news in una lingua (wpf_contenuto_scrivi).
 *
 * @param array  $d       da wpf_drupal_news, con 'body' già con i link riscritti e 'collegamenti' riscritti
 * @param array  $termini da wpf_news_termini
 * @return array come wpf_contenuto_scrivi, più 'avvisi' => string[]
 */
function wpf_news_scrivi( int $nid, string $lingua, array $d, ?WP_Post $post, ?int $id_it, array $termini, int $template, bool $prova, ?array $solo = null ): array {
	$avvisi    = [];
	$categorie = [ $termini['categoria'] ];
	if ( $d['tipologia'] !== null ) {
		if ( isset( $termini['tipologie'][ $d['tipologia'] ] ) ) {
			$categorie[] = $termini['tipologie'][ $d['tipologia'] ];
		} else {
			$avvisi[] = "la tipologia {$d['tipologia']} non esiste più su Drupal: la news resta senza tipologia";
		}
	}
	// Due tag di Drupal fusi in un termine solo (wpf_termine_ritrova_o_crea) darebbero lo stesso id due volte,
	// e i termini risulterebbero cambiati a ogni giro
	$tag = array_values( array_unique( array_map( fn( $t ) => $termini['tag'][ "{$t[1]}:{$t[0]}" ] ?? 0, $d['tag'] ) ) );
	foreach ( $d['tag_mancanti'] as $tid ) {
		$avvisi[] = "il tag {$tid} non esiste più su Drupal: non si importa";
	}
	$riferimenti = [];
	foreach ( $d['riferimenti'] as [ $bundle, $collegato ] ) {
		if ( isset( WPF_NEWS_TIPI_RIFERIMENTO[ $bundle ] ) ) {
			$riferimenti[] = [ 'tipo' => WPF_NEWS_TIPI_RIFERIMENTO[ $bundle ], 'nid' => $collegato ];
		} else {
			$avvisi[] = "riferimento al nodo {$collegato} di tipo «{$bundle}», che non è un contenuto del catasto: non si salva";
		}
	}
	// field_data è solo una data: la news si data all'inizio di quel giorno, nel fuso del sito
	// (timezone_string in config/sito.json). Non a un'ora del giorno: con una data di oggi e un giro prima
	// di quell'ora WordPress la metterebbe «programmata» (future) e la mostrerebbe solo dopo
	$data = $d['data'] !== '' ? "{$d['data']} 00:00:00" : null;
	$r    = wpf_contenuto_scrivi(
		[
			'tipo'          => 'post',
			'cosa'          => 'news',
			'post'          => $post,
			'lingua'        => $lingua,
			'slug'          => $d['slug'] ?? wpf_news_slug( $d ),
			'parent'        => 0,
			'id_it'         => $id_it,
			'voluti'        => array_filter(
				[
					// Senza spazi in fondo: WordPress li toglie, e il titolo risulterebbe cambiato a ogni giro
					'post_title'   => trim( $d['titolo'] ),
					'post_content' => $d['body'],
					'post_status'  => $d['status'] ? 'publish' : 'draft',
					'post_excerpt' => $d['sottotitolo'],
					'post_date'    => $data,
				],
				fn( $v ) => $v !== null
			),
			'meta'          => [
				WPF_META_LINGUA       => $lingua,
				WPF_META_NID          => (string) $nid,
				WPF_META_LANGCODE     => $lingua,
				WPF_META_TESTO_DRUPAL => md5( $d['body'] ),
				WPF_META_IN_EVIDENZA  => $d['in_evidenza'] ? '1' : null,
				WPF_META_COLLEGAMENTI => $d['collegamenti'] ?: null,
				WPF_META_RIFERIMENTI  => $riferimenti ?: null,
			],
			'media'         => [
				'immagine' => $d['immagine'] ? [ $d['immagine'] ] : [],
				'allegato' => $d['allegati'],
				'galleria' => $d['galleria'],
			],
			'template'      => $template,
			'nome_template' => WPF_TEMPLATE_NEWS,
			'termini'       => [
				'category' => array_values( array_filter( $categorie ) ),
				'post_tag' => array_values( array_filter( $tag ) ),
			],
			// Con la data GMT e edit_date WordPress tiene la data anche su una bozza: senza, wp_update_post
			// di una bozza la sostituisce con l'ora corrente (e una news pubblicata dopo uscirebbe con la
			// data del giorno)
			'scrittura'     => $data !== null ? [ 'post_date_gmt' => get_gmt_from_date( $data ), 'edit_date' => true ] : [],
		],
		$prova,
		$solo
	);
	return array_merge( $r, [ 'avvisi' => $avvisi ] );
}

/** Percorsi delle news per la riscrittura dei link: [ "nid|lingua" => percorso ] delle pubblicate, e le spubblicate. */
function wpf_news_percorsi( array $dati ): array {
	$percorsi = [];
	$in_bozza = [];
	foreach ( $dati as $nid => $lingue ) {
		foreach ( $lingue as $lingua => $d ) {
			if ( $d['status'] ) {
				$percorsi[ "{$nid}|{$lingua}" ] = wpf_news_percorso( wpf_news_slug( $d ), $lingua );
			} else {
				$in_bozza[ "{$nid}|{$lingua}" ] = true;
			}
		}
	}
	return [ 'percorsi' => $percorsi, 'in_bozza' => $in_bozza ];
}

/**
 * Il giro delle news, dopo quello delle pagine (pagine.php): controlli sulla copia, termini, scrittura
 * delle news in italiano e in inglese, bozza per le news spubblicate o non più nella copia.
 *
 * @param array    $mancanti  elenco dei mancanti con origine «news» (da wpf_mancanti_leggi), già filtrato
 * @param ?array   $solo      con --contenuti-mancanti: [ nid ][ lingua ] => [ cosa ]
 * @param callable $trova     fn( int $nid, string $lingua ): ?string, come per le pagine
 * @param callable $riga      fn( string $tipo, string $testo )
 * @return array [ 'conteggi' => [ esito => n ], 'mancanti' => elenco aggiornato, 'previsti' => [ [ cosa ] ],
 *                 'errore' => bool ]
 */
function wpf_news_sincronizza( mysqli $db, array $elenco, array $dati, array $alias, callable $trova, array $mancanti, ?array $solo, bool $prova, bool $accetta_bozze, callable $riga ): array {
	$conteggi  = [];
	$previsti  = [];
	$ora       = gmdate( 'c' );
	$esistenti = wpf_news_esistenti();

	if ( $solo === null ) {
		$simulato   = getenv( 'WPF_NEWS_PROVA_CONTEGGIO' );
		$conteggio  = $prova && $simulato !== false ? (int) $simulato : null;
		$controllo  = wpf_news_controlla( $dati, $esistenti, $accetta_bozze, $conteggio );
		if ( $controllo['errore'] ) {
			$riga( 'ERRORE', $controllo['errore'] );
			return [ 'conteggi' => [], 'mancanti' => $mancanti, 'previsti' => [], 'errore' => true ];
		}
		if ( count( $controllo['in_bozza'] ) > WPF_NEWS_SOGLIA_BOZZE ) {
			$riga( 'AVVISO', count( $controllo['in_bozza'] ) . ' news in bozza nello stesso giro, accettate con --accetta-bozze' );
		}
	}

	// Con --contenuti-mancanti i termini delle sole news da ritentare: una news ritentata per intero li riscrive
	$termini = wpf_news_termini( $db, $solo === null ? $dati : array_intersect_key( $dati, $solo ), $prova );
	foreach ( $termini['righe'] as $r ) {
		$riga( 'TERMINE', $r );
	}
	foreach ( $termini['mancanti'] as $dettaglio => $causa ) {
		$riga( 'ERRORE', "termine non creato, {$dettaglio}: {$causa}" );
		$previsti[] = [ 'cosa' => 'termine' ];
	}
	$p        = wpf_post_per_chiave( WPF_TEMPLATE_NEWS );
	$template = $p && $p->post_type === 'us_content_template' ? $p->ID : 0;
	// Slug delle schede figlie (quelle con una regola di rewrite): un post con lo stesso slug non si raggiungerebbe
	// (l'elenco è quello del child, inc/news.php, che WP-CLI carica con il tema)
	$schede = function_exists( 'forestas_schede_figlie' ) ? array_map( 'basename', forestas_schede_figlie() ) : [];

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
			$d = $lingue[ $lingua ];
			$trova_qui = wpf_link_trova_in_lingua( $trova, $lingua );
			$d['slug'] = wpf_news_slug( $d );
			$l                 = wpf_link_riscrivi( wpf_contenuto_pulisci( $d['body'], $d['formato'] ), $alias, $trova_qui, $lingua );
			$d['body']         = $l['html'];
			$c                 = wpf_news_collegamenti( $d['collegamenti'], $alias, $trova_qui, $lingua );
			$d['collegamenti'] = $c['collegamenti'];
			$r                 = wpf_news_scrivi( $nid, $lingua, $d, $post, $id_it, $termini, $template, $prova, $solo[ $nid ][ $lingua ] ?? null );
			if ( $lingua === 'it' ) {
				$id_it = $r['id'];
			}
			$nome                    = "{$nid}/{$lingua}";
			$conteggi[ $r['esito'] ] = ( $conteggi[ $r['esito'] ] ?? 0 ) + 1;
			$riga( 'NEWS', "{$nome}: {$r['esito']}" . ( $r['campi'] ? ' (' . implode( ', ', $r['campi'] ) . ')' : '' ) . ( $d['status'] ? '' : ' — in bozza: non pubblicata su Drupal' ) );
			foreach ( $r['avvisi'] as $a ) {
				$riga( 'AVVISO', "news {$nome}: {$a}" );
			}
			if ( in_array( $d['slug'], $schede, true ) ) {
				$riga( 'AVVISO', "news {$nome}: lo slug «{$d['slug']}» è quello di una scheda della pagina News, che ha la precedenza: la news non si raggiunge" );
			}
			foreach ( $r['media'] as $m ) {
				$riga( 'MEDIA', "news {$nome}: {$m}" );
			}
			wpf_link_log( $riga, "news {$nome}", $l );
			wpf_link_log( $riga, "news {$nome}", $c['link'] );
			if ( $prova ) {
				$previsti = array_merge( $previsti, wpf_mancanti_previsti( $riga, "news {$nome}", $r['mancanti'] ) );
				continue;
			}
			$mancanti = wpf_mancanti_aggiorna( $mancanti, 'news', (string) $nid, $lingua, $solo[ $nid ][ $lingua ] ?? null, $r['mancanti'], $ora );
		}
	}

	// News, o loro traduzioni, che non sono più nella copia (cancellate su Drupal): in bozza, mai cancellate.
	// Anche la sola traduzione inglese tolta su Drupal: il post inglese non lo aggiornerebbe più nessuno
	if ( $solo === null ) {
		foreach ( $esistenti as $nid => $lingue ) {
			foreach ( $lingue as $lingua => $post ) {
				if ( isset( $dati[ $nid ][ $lingua ] ) || $post->post_status === 'draft' ) {
					continue;
				}
				$conteggi['in bozza'] = ( $conteggi['in bozza'] ?? 0 ) + 1;
				$riga( 'NEWS', "{$nid}/{$lingua}: in bozza — " . ( isset( $elenco[ $nid ] ) ? 'la traduzione non è più nella copia Drupal' : 'non più nella copia Drupal' ) );
				if ( ! $prova ) {
					wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'draft' ] );
				}
			}
		}
	}
	return [ 'conteggi' => $conteggi, 'mancanti' => $mancanti, 'previsti' => $previsti, 'errore' => false ];
}
