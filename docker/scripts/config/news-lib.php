<?php
/**
 * Sincronizzazione delle news da Drupal (oc:8759), lanciata da pagine.php dopo le pagine statiche.
 *
 * Ogni news it/und di Drupal è un post con la categoria «news»; le tipologie (tipologia_news) sono
 * sottocategorie di «news», i tag (tag_news e tags) sono post_tag. Una news si riconosce da _drupal_nid e
 * _wpf_lingua, come le pagine dal perimetro. Le 4 news tradotte su Drupal hanno il post inglese collegato
 * in WPML; le altre si vedono in italiano anche sul sito inglese (ripiego di WPML, config/wpml.json).
 *
 * Categorie e tag sono gli stessi nelle due lingue: su Drupal tipologia_news, tag_news e tags non hanno
 * traduzioni, e WPML li mostra come sono (ripiego anche sulle tassonomie).
 */

require_once __DIR__ . '/contenuti-lib.php';

// Più news di così da mettere in bozza nello stesso giro: copia Drupal sospetta, ci si ferma. Vale anche
// come scarto massimo fra le news pubblicate in WordPress e quelle trovate nella copia (copia a metà)
const WPF_NEWS_SOGLIA_BOZZE = 5;
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
// Term meta di un termine importato: «<vocabolario>:<tid>», non univoco (un termine che ne fonde due
// porta tutte le coppie). È la chiave con cui un import successivo lo ritrova (oc:8760)
const WPF_TERM_META_DRUPAL = '_drupal_chiave';

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

/** Termine importato da Drupal in una tassonomia, dal suo vocabolario e tid, o 0. */
function wpf_termine_per_drupal( string $vid, int $tid, string $tassonomia ): int {
	$ids = wpf_wpml_tutte_le_lingue(
		fn() => get_terms( [ 'taxonomy' => $tassonomia, 'hide_empty' => false, 'fields' => 'ids', 'meta_key' => WPF_TERM_META_DRUPAL, 'meta_value' => "{$vid}:{$tid}" ] )
	);
	return is_array( $ids ) && $ids ? (int) $ids[0] : 0;
}

/**
 * Il termine di WordPress di un termine Drupal: ritrovato dal term meta; se no uno con lo stesso slug
 * nella stessa tassonomia e sotto lo stesso genitore (due vocabolari con lo stesso nome diventano un
 * termine solo, che prende anche questa coppia); se no creato. In anteprima un termine da creare è 0.
 *
 * @return array [ 'id' => int, 'esito' => ritrovato|fuso|creato|da creare, 'errore' => ?string ]
 */
function wpf_news_termine( string $vid, int $tid, string $nome, string $tassonomia, int $parent, bool $prova ): array {
	$id = wpf_termine_per_drupal( $vid, $tid, $tassonomia );
	if ( $id ) {
		return [ 'id' => $id, 'esito' => 'ritrovato', 'errore' => null ];
	}
	$uguale = wpf_wpml_tutte_le_lingue( fn() => get_term_by( 'slug', sanitize_title( $nome ), $tassonomia ) );
	if ( $uguale && (int) $uguale->parent === $parent ) {
		if ( ! $prova ) {
			add_term_meta( $uguale->term_id, WPF_TERM_META_DRUPAL, "{$vid}:{$tid}" );
		}
		return [ 'id' => (int) $uguale->term_id, 'esito' => 'fuso', 'errore' => null ];
	}
	if ( $prova ) {
		return [ 'id' => 0, 'esito' => 'da creare', 'errore' => null ];
	}
	$nuovo = wpf_wpml_tutte_le_lingue( fn() => wp_insert_term( $nome, $tassonomia, [ 'parent' => $parent ] ) );
	if ( is_wp_error( $nuovo ) ) {
		return [ 'id' => 0, 'esito' => 'non creato', 'errore' => $nuovo->get_error_message() ];
	}
	add_term_meta( (int) $nuovo['term_id'], WPF_TERM_META_DRUPAL, "{$vid}:{$tid}" );
	wpf_wpml_collega( (int) $nuovo['term_id'], "tax_{$tassonomia}", WPF_LINGUE_WPML['it'], null );
	return [ 'id' => (int) $nuovo['term_id'], 'esito' => 'creato', 'errore' => null ];
}

/** La categoria che contiene le news, creata se manca: [ 'id', 'esito', 'errore' ] come wpf_news_termine. */
function wpf_news_categoria( bool $prova ): array {
	$c = wpf_wpml_tutte_le_lingue( fn() => get_term_by( 'slug', WPF_NEWS_CATEGORIA, 'category' ) );
	if ( $c ) {
		return [ 'id' => (int) $c->term_id, 'esito' => 'ritrovato', 'errore' => null ];
	}
	if ( $prova ) {
		return [ 'id' => 0, 'esito' => 'da creare', 'errore' => null ];
	}
	$nuovo = wpf_wpml_tutte_le_lingue( fn() => wp_insert_term( 'News', 'category', [ 'slug' => WPF_NEWS_CATEGORIA ] ) );
	if ( is_wp_error( $nuovo ) ) {
		return [ 'id' => 0, 'esito' => 'non creato', 'errore' => $nuovo->get_error_message() ];
	}
	wpf_wpml_collega( (int) $nuovo['term_id'], 'tax_category', WPF_LINGUE_WPML['it'], null );
	return [ 'id' => (int) $nuovo['term_id'], 'esito' => 'creato', 'errore' => null ];
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
		$url   = preg_replace( [ '#^internal:/?#', '#^entity:node/#' ], [ 'https://www.sardegnasentieri.it/', 'https://www.sardegnasentieri.it/node/' ], $uri );
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
	return ( $lingua === 'it' ? '/' : WPF_PREFISSO_INGLESE ) . WPF_NEWS_CATEGORIA . "/{$slug}/";
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
 * per volta) passa wpf_drupal_controlla appena ogni tabella ha una riga, ma restituisce meno news.
 *
 * @param array $elenco     da wpf_drupal_news_elenco
 * @param array $esistenti  da wpf_news_esistenti
 * @param ?int  $conteggio  per provare il controllo: news pubblicate da considerare lette dalla copia
 * @return array [ 'in_bozza' => [ nid => motivo ], 'errore' => ?string ]
 */
function wpf_news_controlla( array $elenco, array $esistenti, bool $accetta_bozze, ?int $conteggio = null ): array {
	$in_bozza = [];
	foreach ( $esistenti as $nid => $lingue ) {
		$pubblicata = (bool) array_filter( $lingue, fn( $p ) => $p->post_status !== 'draft' );
		if ( ! $pubblicata ) {
			continue;
		}
		if ( ! isset( $elenco[ $nid ] ) ) {
			$in_bozza[ $nid ] = 'non più nella copia Drupal';
		} elseif ( ! $elenco[ $nid ]['status'] ) {
			$in_bozza[ $nid ] = 'non pubblicata su Drupal';
		}
	}
	$pubblicate_wp    = count( array_filter( $esistenti, fn( $l ) => isset( $l['it'] ) && $l['it']->post_status === 'publish' ) );
	$pubblicate_copia = $conteggio ?? count( array_filter( $elenco, fn( $n ) => $n['status'] ) );
	$errore           = null;
	if ( ! $accetta_bozze && count( $in_bozza ) > WPF_NEWS_SOGLIA_BOZZE ) {
		$errore = 'copia Drupal sospetta: ' . count( $in_bozza ) . ' news da mettere in bozza nello stesso giro (nid ' . implode( ', ', array_keys( $in_bozza ) ) . '), più di ' . WPF_NEWS_SOGLIA_BOZZE;
	} elseif ( ! $accetta_bozze && $pubblicate_copia < $pubblicate_wp - WPF_NEWS_SOGLIA_BOZZE ) {
		$errore = "copia Drupal sospetta: {$pubblicate_copia} news pubblicate nella copia contro {$pubblicate_wp} in WordPress, più di " . WPF_NEWS_SOGLIA_BOZZE . ' di differenza';
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
	// le schede e le pagine d'elenco mostrano solo la categoria News e le sue tipologie
	if ( $categoria['id'] && (int) get_option( 'default_category' ) !== $categoria['id'] ) {
		$righe[] = 'categoria predefinita dei post: News' . ( $prova ? ' (da impostare)' : '' );
		if ( ! $prova ) {
			update_option( 'default_category', $categoria['id'] );
		}
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
	$termini   = wpf_drupal_termini( $db, array_merge( $tid_tipologie, array_column( $coppie_tag, 1 ) ) );
	$tipologie = [];
	foreach ( array_unique( $tid_tipologie ) as $tid ) {
		if ( ! isset( $termini[ $tid ] ) ) {
			continue; // tipologia che su Drupal non esiste più: lo dice la riga della news
		}
		$r = wpf_news_termine( $termini[ $tid ]['vid'], $tid, $termini[ $tid ]['nome']['it'] ?? reset( $termini[ $tid ]['nome'] ), 'category', $categoria['id'], $prova );
		$tipologie[ $tid ] = $r['id'];
		if ( $r['esito'] !== 'ritrovato' ) {
			$righe[] = "tipologia «" . reset( $termini[ $tid ]['nome'] ) . "» ({$tid}): {$r['esito']}" . ( $r['errore'] ? " ({$r['errore']})" : '' );
		}
		if ( $r['errore'] ) {
			$mancanti[ "tipologia {$tid}" ] = $r['errore'];
		}
	}
	$tag = [];
	foreach ( $coppie_tag as $chiave => [ $vid, $tid ] ) {
		$r             = wpf_news_termine( $vid, $tid, $termini[ $tid ]['nome']['it'] ?? reset( $termini[ $tid ]['nome'] ), 'post_tag', 0, $prova );
		$tag[ $chiave ] = $r['id'];
		if ( $r['esito'] !== 'ritrovato' ) {
			$righe[] = "tag «" . reset( $termini[ $tid ]['nome'] ) . "» ({$chiave}): {$r['esito']}" . ( $r['errore'] ? " ({$r['errore']})" : '' );
		}
		if ( $r['errore'] ) {
			$mancanti[ "tag {$chiave}" ] = $r['errore'];
		}
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
	$tag = array_map( fn( $t ) => $termini['tag'][ "{$t[1]}:{$t[0]}" ] ?? 0, $d['tag'] );
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
	// Ora fissa a mezzogiorno di Roma: field_data è solo una data, e così non cambia giorno con il fuso
	$data = $d['data'] !== '' ? "{$d['data']} 12:00:00" : null;
	$r    = wpf_contenuto_scrivi(
		[
			'tipo'          => 'post',
			'cosa'          => 'news',
			'post'          => $post,
			'lingua'        => $lingua,
			'slug'          => wpf_news_slug( $d ),
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
		$conteggio  = $prova && getenv( 'WPF_NEWS_PROVA_CONTEGGIO' ) !== false ? (int) getenv( 'WPF_NEWS_PROVA_CONTEGGIO' ) : null;
		$controllo  = wpf_news_controlla( $elenco, $esistenti, $accetta_bozze, $conteggio );
		if ( $controllo['errore'] ) {
			$riga( 'ERRORE', $controllo['errore'] );
			return [ 'conteggi' => [], 'mancanti' => $mancanti, 'previsti' => [], 'errore' => true ];
		}
		if ( count( $controllo['in_bozza'] ) > WPF_NEWS_SOGLIA_BOZZE ) {
			$riga( 'AVVISO', count( $controllo['in_bozza'] ) . ' news in bozza nello stesso giro, accettate con --accetta-bozze' );
		}
	}

	$termini = wpf_news_termini( $db, $solo === null ? $dati : [], $prova );
	foreach ( $termini['righe'] as $r ) {
		$riga( 'TERMINE', $r );
	}
	foreach ( $termini['mancanti'] as $dettaglio => $causa ) {
		$riga( 'ERRORE', "termine non creato, {$dettaglio}: {$causa}" );
		$previsti[] = [ 'cosa' => 'termine' ];
	}
	$p        = wpf_post_per_chiave( WPF_TEMPLATE_NEWS );
	$template = $p && $p->post_type === 'us_content_template' ? $p->ID : 0;
	// Slug delle schede della pagina News: un post con lo stesso slug non si raggiungerebbe
	$schede = array_map( fn( $s ) => $s->post_name, wpf_wpml_tutte_le_lingue( fn() => get_posts( [ 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'meta_key' => WPF_META_SCHEDA, 'suppress_filters' => true ] ) ) );

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
			// Come per le pagine: in inglese un link porta alla versione inglese se c'è, se no all'italiano
			$trova_qui = $lingua === 'it'
				? fn( int $n, string $l ) => $trova( $n, $l ) ?? ( $l !== 'it' ? $trova( $n, 'it' ) : null )
				: fn( int $n, string $l ) => $trova( $n, $lingua ) ?? $trova( $n, $l ) ?? $trova( $n, 'it' );
			$l                 = wpf_link_riscrivi( wpf_pagina_pulisci( $d['body'], $d['formato'] ), $alias, $trova_qui, $lingua );
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
			if ( in_array( wpf_news_slug( $d ), $schede, true ) ) {
				$riga( 'AVVISO', "news {$nome}: lo slug «" . wpf_news_slug( $d ) . '» è quello di una scheda della pagina News, che ha la precedenza: la news non si raggiunge' );
			}
			foreach ( $r['media'] as $m ) {
				$riga( 'MEDIA', "news {$nome}: {$m}" );
			}
			foreach ( [ $l, $c['link'] ] as $link ) {
				foreach ( $link['riscritti'] as $m ) {
					$riga( 'LINK', "news {$nome}: riscritto {$m}" );
				}
				foreach ( array_unique( $link['in_attesa'] ) as $m ) {
					$riga( 'LINK', "news {$nome}: in attesa {$m} (contenuto non ancora in WordPress, resta verso Drupal)" );
				}
				foreach ( array_unique( $link['non_contenuti'] ) as $m ) {
					$riga( 'LINK', "news {$nome}: verso Drupal {$m} (non è un nodo di Drupal: resta verso Drupal)" );
				}
			}
			if ( $prova ) {
				foreach ( $r['mancanti'] as $cosa => $elementi ) {
					foreach ( $elementi as $dettaglio => $causa ) {
						$riga( 'AVVISO', "news {$nome}: {$cosa}" . ( $dettaglio !== '' ? " {$dettaglio}" : '' ) . " non riuscirebbe: {$causa}" );
						$previsti[] = [ 'cosa' => $cosa ];
					}
				}
				continue;
			}
			// Gli elementi trattati in questo giro escono dall'elenco; quelli falliti ci rientrano
			foreach ( $mancanti as $k => $m ) {
				if ( (int) $m['elemento'] === $nid && $m['lingua'] === $lingua && ( $solo === null || in_array( $m['cosa'], $solo[ $nid ][ $lingua ], true ) ) ) {
					unset( $mancanti[ $k ] );
				}
			}
			foreach ( $r['mancanti'] as $cosa => $elementi ) {
				foreach ( $elementi as $dettaglio => $causa ) {
					$k              = wpf_mancante_chiave( 'news', $cosa, (string) $nid, $lingua, (string) $dettaglio );
					$mancanti[ $k ] = [
						'origine'   => 'news',
						'cosa'      => $cosa,
						'elemento'  => (string) $nid,
						'lingua'    => $lingua,
						'dettaglio' => (string) $dettaglio,
						'causa'     => $causa,
						'primo'     => $mancanti[ $k ]['primo'] ?? $ora,
						'ultimo'    => $ora,
					];
				}
			}
		}
	}

	// News che non sono più nella copia (cancellate su Drupal): in bozza, mai cancellate
	if ( $solo === null ) {
		foreach ( $esistenti as $nid => $lingue ) {
			if ( isset( $elenco[ $nid ] ) ) {
				continue;
			}
			foreach ( $lingue as $lingua => $post ) {
				if ( $post->post_status === 'draft' ) {
					continue;
				}
				$conteggi['in bozza'] = ( $conteggi['in bozza'] ?? 0 ) + 1;
				$riga( 'NEWS', "{$nid}/{$lingua}: in bozza — non più nella copia Drupal" );
				if ( ! $prova ) {
					wp_update_post( [ 'ID' => $post->ID, 'post_status' => 'draft' ] );
				}
			}
		}
	}
	return [ 'conteggi' => $conteggi, 'mancanti' => $mancanti, 'previsti' => $previsti, 'errore' => false ];
}
