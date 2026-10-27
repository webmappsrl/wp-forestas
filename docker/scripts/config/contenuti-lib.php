<?php
/**
 * Parte comune della sincronizzazione dei contenuti da Drupal: pagine statiche (oc:8758, pagine-lib.php)
 * e news (oc:8759, news-lib.php). Qui stanno la scrittura di un contenuto (pagina o post) con i suoi
 * media, termini e traduzione WPML, la pulizia del testo e l'elenco degli elementi non riusciti.
 *
 * Drupal vince sempre: a ogni esecuzione il contenuto prende i dati di Drupal; il design sta nei
 * template di Impreza (us_content_id).
 */

require_once __DIR__ . '/comune.php';
require_once __DIR__ . '/wpml.php';
require_once __DIR__ . '/drupal.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/link.php';

const WPF_META_LINGUA   = '_wpf_lingua';
const WPF_META_NID      = '_drupal_nid';
const WPF_META_LANGCODE = '_drupal_langcode';
// Impronta (md5) del testo scritto dalla sincronizzazione: finché il testo del contenuto è quello, il child
// lo mostra così com'è, senza i filtri di the_content (inc/pagine-statiche.php)
const WPF_META_TESTO_DRUPAL = '_wpf_testo_drupal';
const WPF_META_PAGINA_ALLEGATI = 'wpf_allegati';
const WPF_META_PAGINA_GALLERIA = 'wpf_galleria';
// Media di un contenuto: elemento dei mancanti («cosa») => meta in cui si salva. Un media non scaricato non
// alza il codice d'uscita (si ritenta al giro dopo)
const WPF_PAGINA_MEDIA = [
	'immagine' => '_thumbnail_id',
	'allegato' => WPF_META_PAGINA_ALLEGATI,
	'galleria' => WPF_META_PAGINA_GALLERIA,
];
// Elementi non riusciti, da ritentare con --contenuti-mancanti. Ogni voce dice da quale import viene
// («origine»: pagina, news), così ogni import filtra e ritenta solo le sue (oc:8759)
const WPF_OPZIONE_MANCANTI = 'wpf_sync_mancanti';
// Lingua di WPML per una lingua di Drupal (i menu inglesi del sito sono en-us)
const WPF_LINGUE_WPML = [ 'it' => 'it', 'en' => 'en-us' ];
// Term meta di un termine importato: «<vocabolario>:<tid>», non univoco (un termine che ne fonde due
// porta tutte le coppie). È la chiave con cui un import successivo lo ritrova (oc:8760)
const WPF_TERM_META_DRUPAL = '_drupal_chiave';
// Scheda di una pagina a schede (oc:8759): ordine fra le schede, dal campo «scheda» di config/pagine.json.
// La legge anche news-lib.php (slug delle news in conflitto con una scheda)
const WPF_META_SCHEDA = '_wpf_scheda';
// Elenco delle schede per cui sono state generate le regole di rewrite del child (inc/news.php), con la
// versione delle regole: cambiando l'uno o l'altra il giro le rigenera
const WPF_OPZIONE_SCHEDE_REGOLE = 'wpf_schede_regole';
const WPF_REGOLE_VERSIONE       = 2;

/**
 * Contenuto WordPress pubblicato (di qualunque tipo) che porta un nodo Drupal in una lingua, o 0.
 * _drupal_langcode è la lingua normalizzata (questo import scrive «it» anche per un nodo «und»), ma un
 * altro import può scrivere il codice di Drupal: per l'italiano vale anche «und» (wpf_drupal_lingue).
 */
function wpf_contenuto_per_nodo( int $nid, string $lingua ): int {
	global $wpdb;
	$lingue = wpf_drupal_lingue( $lingua );
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = %s AND n.meta_value = %s
			 JOIN {$wpdb->postmeta} l ON l.post_id = p.ID AND l.meta_key = %s
			 WHERE p.post_status = 'publish' AND l.meta_value IN (" . implode( ',', array_fill( 0, count( $lingue ), '%s' ) ) . ')
			 ORDER BY p.ID LIMIT 1',
			WPF_META_NID,
			(string) $nid,
			WPF_META_LANGCODE,
			...$lingue
		)
	);
}

/**
 * Indirizzi e email scritti nel testo trasformati in link, come filter_url di Drupal: solo fuori dai
 * link. make_clickable da solo trasformava anche il testo di un link che è un indirizzo
 * («<a href="…">https://…</a>»), creando un link dentro l'altro: il browser lascia vuoto il primo.
 */
function wpf_pagina_link_dagli_indirizzi( string $html ): string {
	$parti = preg_split( '~(<a\b[^>]*>.*?</a>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
	foreach ( $parti as $i => $parte ) {
		if ( $i % 2 === 0 ) {
			$parti[ $i ] = make_clickable( $parte );
		}
	}
	return implode( '', $parti );
}

/**
 * Pulizia del body di Drupal. Un body in «filtered_html» diventa quello che Drupal mostra: solo i tag e gli
 * attributi ammessi (filter_html) e gli indirizzi scritti nel testo trasformati in link (filter_url). Negli
 * altri formati (la mappa è in «full_html», e il suo testo non si mostra) si tolgono gli stili in linea e
 * gli attributi data-entity-*, poi passa il filtro dei contenuti di WordPress (wp_kses_post): niente
 * script, iframe o attributi on* da un testo che su Drupal la redazione poteva scrivere senza filtri. Lo
 * script gira come amministratore, e senza questo passo wp_insert_post non filtrerebbe nulla.
 */
function wpf_pagina_pulisci( string $html, string $formato = '' ): string {
	if ( $formato === 'filtered_html' ) {
		$html = wpf_pagina_link_dagli_indirizzi( wp_kses( $html, WPF_DRUPAL_FILTERED_HTML ) );
	} else {
		$html = wp_kses_post( preg_replace( '#\s(?:style|data-entity-[a-z-]+)\s*=\s*(["\']).*?\1#is', '', $html ) );
	}
	return trim( str_replace( "\r\n", "\n", $html ) );
}

/**
 * Elenco dei mancanti: [ chiave => [ origine, cosa, elemento, lingua, dettaglio, causa, primo, ultimo ] ].
 * Le voci scritte prima di oc:8759 non hanno «origine» ed erano tutte delle pagine (con la chiave in
 * «pagina»): si leggono come tali.
 */
function wpf_mancanti_leggi(): array {
	$elenco = get_option( WPF_OPZIONE_MANCANTI, [] );
	if ( ! is_array( $elenco ) ) {
		return [];
	}
	$letto = [];
	foreach ( $elenco as $m ) {
		if ( ! isset( $m['origine'] ) ) {
			$m = array_merge( [ 'origine' => 'pagina', 'elemento' => (string) ( $m['pagina'] ?? '' ) ], $m );
			unset( $m['pagina'] );
		}
		$letto[ wpf_mancante_chiave( $m['origine'], $m['cosa'], $m['elemento'], $m['lingua'], (string) $m['dettaglio'] ) ] = $m;
	}
	return $letto;
}

function wpf_mancanti_salva( array $elenco ): void {
	if ( $elenco ) {
		update_option( WPF_OPZIONE_MANCANTI, $elenco, false );
	} else {
		delete_option( WPF_OPZIONE_MANCANTI );
	}
}

/** Chiave di un elemento mancante: origine|cosa|elemento|lingua|dettaglio. */
function wpf_mancante_chiave( string $origine, string $cosa, string $elemento, string $lingua, string $dettaglio = '' ): string {
	return "{$origine}|{$cosa}|{$elemento}|{$lingua}|{$dettaglio}";
}

/**
 * Media di un campo: id degli allegati e file non riusciti. Un file che non si scarica non nasconde gli
 * altri: allegati e galleria mostrano quelli scaricati, e il mancante si aggiunge quando arriva. Per
 * l'immagine in evidenza, che è una sola, il campo tiene invece il valore che aveva: un errore di rete
 * non toglie un'immagine dal sito (oc:8758).
 *
 * @return array [ 'ids' => int[]|null (null = tenere il valore attuale), 'scaricare' => string[],
 *                 'scaricati' => string[], 'riusati' => int, 'aggiornati' => [ nome => campi riallineati ],
 *                 'errori' => [ nome => causa ] ]
 */
function wpf_contenuto_media( array $file, bool $prova ): array {
	$ids        = [];
	$scaricare  = [];
	$scaricati  = [];
	$riusati    = 0;
	$aggiornati = []; // file già importati di cui Drupal ha cambiato alt, autore, credits o titolo
	$errori     = [];
	foreach ( $file as $f ) {
		// Un file già importato si riusa e si riallinea; gli altri li importa wpf_media_importa
		$esistente = wpf_media_per_fid( (int) $f['fid'] );
		$presente  = $esistente > 0;
		$id        = $presente ? $esistente : wpf_media_importa( $f, $prova );
		if ( $presente && ( $cambiati = wpf_media_allinea( $id, $f, $prova ) ) ) {
			$aggiornati[ $f['nome'] ] = $cambiati;
		}
		if ( is_wp_error( $id ) ) {
			$errori[ "fid {$f['fid']} {$f['nome']}" ] = $id->get_error_message();
		} elseif ( $id === 0 ) {
			$scaricare[] = $f['nome'];
		} else {
			$ids[] = $id;
			if ( $presente ) {
				++$riusati;
			} else {
				$scaricati[] = $f['nome'];
			}
		}
	}
	return [ 'ids' => $scaricare ? null : $ids, 'scaricare' => $scaricare, 'scaricati' => $scaricati, 'riusati' => $riusati, 'aggiornati' => $aggiornati, 'errori' => $errori ];
}

/** Termini di un contenuto in una tassonomia, in tutte le lingue di WPML: id ordinati. */
function wpf_contenuto_termini( int $id, string $tassonomia ): array {
	$ids = wpf_wpml_tutte_le_lingue( fn() => wp_get_object_terms( $id, $tassonomia, [ 'fields' => 'ids' ] ) );
	$ids = is_wp_error( $ids ) ? [] : array_map( 'intval', $ids );
	sort( $ids );
	return $ids;
}

/**
 * Campi, meta e termini di un contenuto che la sincronizzazione cambierebbe: [ nome del campo, del meta o
 * della tassonomia ]. Un meta a null va cancellato, quindi cambia se c'è.
 */
function wpf_contenuto_differenze( ?WP_Post $post, array $voluti, array $meta, array $termini = [] ): array {
	if ( ! $post ) {
		return array_merge( array_keys( $voluti ), array_keys( array_filter( $meta, fn( $v ) => $v !== null ) ), array_keys( array_filter( $termini ) ) );
	}
	$campi = [];
	foreach ( $voluti as $campo => $valore ) {
		if ( (string) $post->$campo !== (string) $valore ) {
			$campi[] = $campo;
		}
	}
	foreach ( $meta as $chiave_meta => $valore ) {
		$attuale = get_post_meta( $post->ID, $chiave_meta, true );
		if ( $valore === null ? metadata_exists( 'post', $post->ID, $chiave_meta ) : wpf_diverso( $attuale, $valore ) ) {
			$campi[] = $chiave_meta;
		}
	}
	foreach ( $termini as $tassonomia => $ids ) {
		$voluti_ids = array_map( 'intval', $ids );
		sort( $voluti_ids );
		if ( wpf_contenuto_termini( $post->ID, $tassonomia ) !== $voluti_ids ) {
			$campi[] = $tassonomia;
		}
	}
	return $campi;
}

/**
 * Crea o aggiorna un contenuto (pagina o post) in una lingua con i dati di Drupal.
 *
 * @param array  $c [
 *   'tipo'     => 'page' | 'post',
 *   'cosa'     => elemento dei mancanti del contenuto intero ('pagina', 'news'),
 *   'post'     => ?WP_Post, il contenuto se esiste già,
 *   'lingua'   => it | en,
 *   'slug'     => post_name voluto,
 *   'parent'   => post_parent (0 per i post),
 *   'id_it'    => ?int, il contenuto italiano, per collegare l'inglese in WPML,
 *   'voluti'   => campi del post (post_title, post_content, post_status, post_excerpt…) senza post_name,
 *   'meta'     => [ meta => valore | null ],
 *   'media'    => [ cosa di WPF_PAGINA_MEDIA => file di Drupal ],
 *   'template' => id del template di Impreza (0 se non c'è sul sito),
 *   'nome_template' => chiave del template in config/post.json, per il messaggio,
 *   'termini'  => [ tassonomia => term_id[] ],
 *   'scrittura' => argomenti in più per wp_insert_post e wp_update_post, che non si confrontano (per
 *                  esempio post_date_gmt ed edit_date delle news),
 * ]
 * @param ?array $solo con --contenuti-mancanti: gli elementi da riscrivere (la «cosa» del contenuto = tutti)
 * @return array [ 'id' => ?int, 'esito' => creata|aggiornata|invariata|non creata|non aggiornata,
 *                 'campi' => string[], 'mancanti' => [ cosa => [ dettaglio => causa ] ], 'media' => string[] ]
 */
function wpf_contenuto_scrivi( array $c, bool $prova, ?array $solo = null ): array {
	$post     = $c['post'];
	$lingua   = $c['lingua'];
	$cosa_tutto = $c['cosa'];
	$tutto    = $solo === null || in_array( $cosa_tutto, $solo, true );
	$fa       = fn( string $cosa ) => $tutto || in_array( $cosa, $solo, true );
	$mancanti = [];
	$media    = [];
	$campi    = [];
	$tipo_wpml = 'post_' . $c['tipo'];

	$voluti  = array_merge( $c['voluti'], [ 'post_name' => $c['slug'] ] );
	if ( $c['tipo'] === 'page' ) {
		$voluti['post_parent'] = $c['parent'];
	}
	$meta    = $c['meta'];
	$termini = $c['termini'] ?? [];
	if ( $c['template'] ) {
		$meta['us_content_id'] = (string) $c['template'];
	} elseif ( $fa( 'template' ) ) {
		$mancanti['template'][ $c['nome_template'] ] = "template «{$c['nome_template']}» non presente sul sito (config/post.json)";
	}

	foreach ( WPF_PAGINA_MEDIA as $cosa => $chiave_meta ) {
		$file = $c['media'][ $cosa ] ?? [];
		if ( ! $fa( $cosa ) ) {
			continue;
		}
		$r = wpf_contenuto_media( $file, $prova );
		foreach ( $r['scaricare'] as $nome ) {
			$media[] = "{$cosa} da scaricare: {$nome}";
		}
		foreach ( $r['scaricati'] as $nome ) {
			$media[] = "{$cosa} scaricato: {$nome}";
		}
		if ( $r['riusati'] ) {
			$media[] = "{$cosa}: {$r['riusati']} già nella libreria, riusati";
		}
		foreach ( $r['aggiornati'] as $nome => $cambiati ) {
			$media[] = "{$cosa}: {$nome}, " . implode( ', ', $cambiati ) . ( $prova ? ' da riallineare' : ' riallineati' ) . ' a Drupal';
		}
		foreach ( $r['errori'] as $nome => $causa ) {
			$mancanti[ $cosa ][ $nome ] = $causa;
		}
		if ( $cosa === 'immagine' && $r['errori'] ) {
			continue; // l'immagine in evidenza non scaricata: resta quella che c'era
		}
		if ( $r['ids'] !== null ) {
			$meta[ $chiave_meta ] = $cosa === 'immagine' ? ( $r['ids'] ? (string) $r['ids'][0] : null ) : ( $r['ids'] ?: null );
		} elseif ( $r['scaricare'] ) {
			$campi[] = $chiave_meta; // in anteprima: cambierà quando il file sarà scaricato
		}
	}

	if ( ! $tutto ) {
		// Con --contenuti-mancanti si riscrivono solo i meta degli elementi elencati
		$voluti  = [];
		$meta    = array_intersect_key( $meta, array_flip( [ ...array_values( WPF_PAGINA_MEDIA ), 'us_content_id' ] ) );
		$termini = [];
	}

	$campi = array_merge( wpf_contenuto_differenze( $post, $voluti, $meta, $termini ), $campi );
	// Una traduzione non collegata all'italiano si ricollega anche se il resto non cambia: con
	// --contenuti-mancanti sempre, in un giro normale se il gruppo di WPML non è quello dell'italiano
	// (una traduzione creata quando l'italiano non c'era resterebbe staccata per sempre)
	if ( $lingua !== 'it' && $post && $fa( 'traduzione' )
		&& ( ! $tutto || ( $c['id_it'] && (int) apply_filters( 'wpml_element_trid', null, $post->ID, $tipo_wpml ) !== (int) apply_filters( 'wpml_element_trid', null, $c['id_it'], $tipo_wpml ) ) ) ) {
		$campi[] = 'traduzione';
	}
	$campi = array_values( array_unique( $campi ) );
	$esito = ! $post ? 'creata' : ( $campi ? 'aggiornata' : 'invariata' );
	// Ogni uscita restituisce la stessa forma; $mancanti si legge al momento della chiamata
	$risultato = function ( ?int $id, string $esito ) use ( &$campi, &$mancanti, &$media ): array {
		return [ 'id' => $id, 'esito' => $esito, 'campi' => $campi, 'mancanti' => $mancanti, 'media' => $media ];
	};

	if ( $prova || ( $post && ! $campi ) ) {
		return $risultato( $post->ID ?? null, $esito );
	}

	// Scrittura
	$lingua_wpml = WPF_LINGUE_WPML[ $lingua ];
	if ( ! $post ) {
		$id = wp_insert_post( wp_slash( array_merge( [ 'post_parent' => 0 ], $voluti, $c['scrittura'] ?? [], [ 'post_type' => $c['tipo'] ] ) ), true );
		if ( is_wp_error( $id ) ) {
			$mancanti[ $cosa_tutto ][''] = $id->get_error_message();
			return $risultato( null, 'non creata' );
		}
	} else {
		$id = $post->ID;
		if ( $voluti ) {
			$r = wp_update_post( wp_slash( array_merge( $voluti, $c['scrittura'] ?? [], [ 'ID' => $id ] ) ), true );
			if ( is_wp_error( $r ) ) {
				$mancanti[ $cosa_tutto ][''] = $r->get_error_message();
				return $risultato( $id, 'non aggiornata' );
			}
		}
	}
	foreach ( $meta as $chiave_meta => $valore ) {
		if ( $valore === null ) {
			delete_post_meta( $id, $chiave_meta );
		} else {
			update_post_meta( $id, $chiave_meta, $valore );
		}
	}
	foreach ( $termini as $tassonomia => $ids ) {
		// I termini sono già quelli della lingua del contenuto: WPML non deve convertirli
		wpf_wpml_tutte_le_lingue( fn() => wp_set_object_terms( $id, array_map( 'intval', $ids ), $tassonomia ) );
	}
	if ( $fa( 'traduzione' ) ) {
		// Lingua e gruppo di traduzione; lo slug si riscrive dopo, perché WPML ammetta nella traduzione
		// lo stesso slug dell'originale (sapendo la lingua non lo considera un doppione)
		wpf_wpml_collega( $id, $tipo_wpml, $lingua_wpml, $lingua === 'it' ? null : $c['id_it'] );
		if ( $lingua !== 'it' && $c['id_it'] === null ) {
			$mancanti['traduzione'][''] = 'contenuto italiano assente: traduzione non collegata in WPML';
		}
	}
	if ( $tutto ) {
		if ( get_post_field( 'post_name', $id ) !== $voluti['post_name'] ) {
			wp_update_post( [ 'ID' => $id, 'post_name' => $voluti['post_name'] ] );
			if ( get_post_field( 'post_name', $id ) !== $voluti['post_name'] ) {
				$mancanti[ $cosa_tutto ]['slug'] = 'WordPress ha cambiato lo slug in «' . get_post_field( 'post_name', $id ) . "» invece di «{$voluti['post_name']}»";
			}
		}
	}
	return $risultato( $id, $esito );
}

/*
 * Regole dei termini importati da Drupal (oc:8759), comuni a ogni import: un termine si ritrova dal term
 * meta «<vocabolario>:<tid>», e due vocabolari con lo stesso nome nella stessa tassonomia e sotto lo
 * stesso genitore diventano un termine solo. oc:8760 le usa per eventi, «Da sapere» e carte.
 */
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
function wpf_termine_ritrova_o_crea( string $vid, int $tid, string $nome, string $tassonomia, int $parent, bool $prova ): array {
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

/** Righe LINK di un testo riscritto (wpf_link_riscrivi): link riscritti, in attesa, verso Drupal. */
function wpf_link_log( callable $riga, string $nome, array $l ): void {
	foreach ( $l['riscritti'] as $m ) {
		$riga( 'LINK', "{$nome}: riscritto {$m}" );
	}
	foreach ( array_unique( $l['in_attesa'] ) as $m ) {
		$riga( 'LINK', "{$nome}: in attesa {$m} (contenuto non ancora in WordPress, resta verso Drupal)" );
	}
	foreach ( array_unique( $l['non_contenuti'] ) as $m ) {
		$riga( 'LINK', "{$nome}: verso Drupal {$m} (né /node/N né l'alias di un nodo di Drupal: un modulo come i form, un file o un vecchio indirizzo; resta verso Drupal finché non si corregge a mano o con una regola in link.php)" );
	}
}

/**
 * Elementi non riusciti di un contenuto, in anteprima: una riga AVVISO per ciascuno.
 *
 * @return array [ [ 'cosa' ] ] da contare per il codice d'uscita
 */
function wpf_mancanti_previsti( callable $riga, string $nome, array $mancanti_contenuto ): array {
	$previsti = [];
	foreach ( $mancanti_contenuto as $cosa => $elementi ) {
		foreach ( $elementi as $dettaglio => $causa ) {
			$riga( 'AVVISO', "{$nome}: {$cosa}" . ( $dettaglio !== '' ? " {$dettaglio}" : '' ) . " non riuscirebbe: {$causa}" );
			$previsti[] = [ 'cosa' => $cosa ];
		}
	}
	return $previsti;
}

/**
 * Elenco dei mancanti dopo la scrittura di un contenuto in una lingua: gli elementi trattati in questo
 * giro escono (tutti, o con --contenuti-mancanti quelli ritentati), quelli falliti ci rientrano.
 *
 * @param ?array $ritentati con --contenuti-mancanti: le «cosa» ritentate; null = giro completo
 * @param array  $falliti   [ cosa => [ dettaglio => causa ] ] da wpf_contenuto_scrivi
 */
function wpf_mancanti_aggiorna( array $mancanti, string $origine, string $elemento, string $lingua, ?array $ritentati, array $falliti, string $ora ): array {
	$primo = []; // data del primo fallimento di un elemento che fallisce ancora
	foreach ( $mancanti as $k => $m ) {
		if ( $m['origine'] === $origine && (string) $m['elemento'] === $elemento && $m['lingua'] === $lingua
			&& ( $ritentati === null || in_array( $m['cosa'], $ritentati, true ) ) ) {
			$primo[ $k ] = $m['primo'];
			unset( $mancanti[ $k ] );
		}
	}
	foreach ( $falliti as $cosa => $elementi ) {
		foreach ( $elementi as $dettaglio => $causa ) {
			$k              = wpf_mancante_chiave( $origine, $cosa, $elemento, $lingua, (string) $dettaglio );
			$mancanti[ $k ] = [
				'origine'   => $origine,
				'cosa'      => $cosa,
				'elemento'  => $elemento,
				'lingua'    => $lingua,
				'dettaglio' => (string) $dettaglio,
				'causa'     => $causa,
				'primo'     => $primo[ $k ] ?? $ora,
				'ultimo'    => $ora,
			];
		}
	}
	return $mancanti;
}

/**
 * Schede pubblicate delle pagine a schede: [ id => percorso ] in tutte le lingue. Le regole di rewrite
 * del child (inc/news.php, forestas_regole_schede) le danno solo alle schede figlie: la pagina madre
 * (News) è già raggiungibile come pagina.
 */
function wpf_schede_figlie(): array {
	$schede = wpf_wpml_tutte_le_lingue( fn() => get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => WPF_META_SCHEDA, 'suppress_filters' => true, 'orderby' => 'ID', 'order' => 'ASC' ] ) );
	$figlie = [];
	foreach ( $schede as $s ) {
		if ( $s->post_parent ) {
			$figlie[ $s->ID ] = get_page_uri( $s );
		}
	}
	return $figlie;
}
