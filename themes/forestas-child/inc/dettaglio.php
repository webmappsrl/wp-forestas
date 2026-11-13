<?php
/**
 * Pagine di dettaglio di percorsi e punti di interesse (oc:8969).
 *
 * Le pagine le serve il plugin wp-package, senza post: questo file imposta le basi degli indirizzi per Forestas e
 * trasforma il JSON dello shard nei dati delle sezioni del wireframe (oc:8783, Tavole 3, 4, 5 e 7). I template stanno
 * in `wp-package/track.php` e `wp-package/poi.php` del child.
 *
 * Indirizzi: le tracce dal loro tipo (`/sentiero/`, `/itinerario/`, `/cammino/`, `/track/` senza tipo), i POI sotto
 * la pagina «Punti di interesse» (chiave `da-vedere` di config/pagine.json), cercata per chiave: nel database non
 * resta l'id di una pagina scritto a mano.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Id della pagina «Punti di interesse» (chiave `da-vedere`) nella lingua predefinita, 0 se non c'è. */
function forestas_dettaglio_pagina_poi(): int {
	$id = forestas_pagina_per_chiave( 'da-vedere' );
	return $id ? (int) apply_filters( 'wpml_object_id', $id, 'page', true, apply_filters( 'wpml_default_language', null ) ) : 0;
}

// Basi degli indirizzi: le impone il tema, il pannello del plugin le mostra disattivate
add_filter(
	'pre_option_wmwp_detail_tracks_use_page',
	function () {
		return '0';
	}
);
add_filter(
	'pre_option_wmwp_detail_pois_use_page',
	function () {
		return forestas_dettaglio_pagina_poi() > 0 ? '1' : '0';
	}
);
add_filter(
	'pre_option_wmwp_detail_pois_page',
	function () {
		return (string) forestas_dettaglio_pagina_poi();
	}
);

/*
 * Dati del dettaglio. Le chiavi strutturate del JSON dello shard (enti, indicatori, tabelle, correlati, mappa, allegati,
 * dati del POI) si leggono solo qui: quando lo shard le cambia (gli enti da `entities` a `organizations`, il `forestas`
 * dei POI da ripulire) si corregge un punto solo. I template leggono da sé solo campi semplici e stabili (`ref`,
 * `description`, `feature_image`, `forestas.downloads.gpx`, `forestas.useful_info`, `forestas.registry_code`). Ogni dato può mancare
 * (dettaglio di ripiego del package, traccia appena creata): una funzione restituisce null o un elenco vuoto, mai un
 * avviso.
 */

/** Lingua della pagina in due lettere, per i testi dello shard (`it`, `en`). */
function forestas_dettaglio_lingua(): string {
	$codice = apply_filters( 'wpml_current_language', 'it' );
	return strtolower( substr( is_string( $codice ) && '' !== $codice ? $codice : 'it', 0, 2 ) );
}

/**
 * Testo nella lingua con ripiego sull'italiano. Un valore per lingua può essere una lista di blocchi (roadbook,
 * informazioni utili): si uniscono con un a capo.
 *
 * @param mixed $valore Stringa, o array per lingua di stringhe o di liste.
 */
function forestas_dettaglio_testo( $valore, ?string $lingua = null ): string {
	return forestas_dettaglio_testo_lingua( $valore, $lingua )['testo'];
}

/**
 * Come forestas_dettaglio_testo(), con la lingua da cui viene il testo (null per un testo senza lingue): serve a marcare
 * con `lang` un testo che ripiega su un'altra lingua (WCAG 3.1.2).
 *
 * @return array{testo: string, lingua: ?string}
 */
function forestas_dettaglio_testo_lingua( $valore, ?string $lingua = null ): array {
	$lingua = $lingua ?? forestas_dettaglio_lingua();
	if ( is_string( $valore ) ) {
		return [
			'testo'  => trim( $valore ),
			'lingua' => null,
		];
	}
	if ( is_array( $valore ) ) {
		// Poi la prima lingua non vuota: nello shard i nomi dei comuni hanno spesso solo `en`
		foreach ( array_merge( [ $lingua, 'it' ], array_keys( $valore ) ) as $chiave ) {
			$testo = $valore[ $chiave ] ?? null;
			if ( is_array( $testo ) ) {
				$testo = implode( "\n", array_filter( array_map( 'trim', array_filter( $testo, 'is_string' ) ) ) );
			}
			if ( is_string( $testo ) && '' !== trim( $testo ) ) {
				return [
					'testo'  => trim( $testo ),
					'lingua' => (string) $chiave,
				];
			}
		}
	}
	return [
		'testo'  => '',
		'lingua' => null,
	];
}

/**
 * Attributo `lang` per un testo che ripiega su un'altra lingua (WCAG 3.1.2): ` lang="it"` se la lingua del testo è
 * diversa da quella della pagina, altrimenti ''.
 */
function forestas_dettaglio_lang( ?string $lingua ): string {
	return null !== $lingua && '' !== $lingua && forestas_dettaglio_lingua() !== $lingua ? ' lang="' . esc_attr( $lingua ) . '"' : '';
}

/**
 * Testo ricco dello shard (descrizione, informazioni utili, come arrivare, tappe) pronto per la pagina, accessibile:
 * - i titoli scritti in Drupal hanno livelli qualsiasi (un h3 dentro «Descrizione», che è già un h3; salti da h4 a h6):
 *   si rinumerano da `$livello` (h4, sotto il titolo del blocco) mantenendo l'ordine relativo e senza salti (WCAG 1.3.1);
 * - se il testo ripiega su un'altra lingua (una traccia senza inglese sulla pagina inglese) si racchiude in un `div`
 *   con la sua lingua, così lo screen reader lo pronuncia giusto (WCAG 3.1.2).
 * Il risultato è già filtrato con wp_kses_post().
 */
function forestas_dettaglio_ricco( $valore, ?string $lingua = null, int $livello = 4 ): string {
	$lingua = $lingua ?? forestas_dettaglio_lingua();
	$dato   = forestas_dettaglio_testo_lingua( $valore, $lingua );
	if ( '' === $dato['testo'] ) {
		return '';
	}
	// Titoli vuoti (`<h4>&nbsp;</h4>` nei testi di Drupal) fuori: per lo screen reader sono titoli senza nome
	$html = (string) preg_replace( '/<h([1-6])\b[^>]*>(?:\s|&nbsp;|&#160;|\x{00A0}|<br\s*\/?>)*<\/h\1>/iu', '', wp_kses_post( $dato['testo'] ) );
	$html = forestas_dettaglio_titoli( $html, $livello );
	if ( null !== $dato['lingua'] && $dato['lingua'] !== $lingua ) {
		$html = '<div lang="' . esc_attr( $dato['lingua'] ) . '">' . $html . '</div>';
	}
	return $html;
}

/**
 * Titoli di un HTML rinumerati da `$livello`: ogni livello presente prende il suo posto in ordine (il più alto diventa
 * `$livello`), e un titolo non scende mai più di un livello sotto il precedente; mai oltre h6.
 */
function forestas_dettaglio_titoli( string $html, int $livello = 4 ): string {
	if ( ! preg_match_all( '/<h([1-6])\b/i', $html, $trovati ) ) {
		return $html;
	}
	$livelli = array_values( array_unique( array_map( 'intval', $trovati[1] ) ) );
	sort( $livelli );
	$posto      = array_flip( $livelli );
	$precedente = $livello - 1;
	$aperti     = [];
	return (string) preg_replace_callback(
		'/<(\/?)h([1-6])\b([^>]*)>/i',
		function ( array $m ) use ( $posto, $livello, &$precedente, &$aperti ): string {
			if ( '/' === $m[1] ) {
				$nuovo = array_pop( $aperti ) ?? $livello;
				return '</h' . $nuovo . '>';
			}
			$nuovo      = min( 6, $livello + $posto[ (int) $m[2] ], $precedente + 1 );
			$precedente = $nuovo;
			$aperti[]   = $nuovo;
			return '<h' . $nuovo . $m[3] . '>';
		},
		$html
	);
}

/**
 * Righe dei Blocchi Dettaglio della traccia (`properties.config_detail`, campo «Detail Blocks» del package): le righe
 * di tutti i blocchi `info` in un solo elenco, come le mostra la webapp. Oggi l'unico blocco è il roadbook, che l'import
 * scrive con una riga per tappa («Tappa 1», «Tappa 2»…, oc:8959); i blocchi aggiunti in Nova seguono. Titolo e testo
 * nella lingua, con ripiego sull'italiano come il resto della pagina; le righe senza testo si saltano.
 *
 * @param array<string, mixed> $p Proprietà della traccia.
 * @return list<array{titolo: string, lingua: ?string, testo: string}> `testo` già pronto (forestas_dettaglio_ricco()),
 *   `lingua` quella del titolo.
 */
function forestas_dettaglio_blocchi( array $p, ?string $lingua = null ): array {
	$righe = [];
	foreach ( is_array( $p['config_detail'] ?? null ) ? $p['config_detail'] : [] as $blocco ) {
		if ( ! is_array( $blocco ) || 'info' !== ( $blocco['box_type'] ?? null ) || ! is_array( $blocco['items'] ?? null ) ) {
			continue;
		}
		foreach ( $blocco['items'] as $riga ) {
			$testo = is_array( $riga ) ? forestas_dettaglio_ricco( $riga['content'] ?? null, $lingua ?? forestas_dettaglio_lingua() ) : '';
			if ( '' === wp_strip_all_tags( $testo ) ) {
				continue;
			}
			$righe[] = [
				'titolo' => forestas_dettaglio_testo( $riga['title'] ?? null, $lingua ),
				'lingua' => forestas_dettaglio_testo_lingua( $riga['title'] ?? null, $lingua )['lingua'],
				'testo'  => $testo,
			];
		}
	}
	return $righe;
}

/**
 * Tipo del dettaglio: `sentiero`, `itinerario`, `cammino` (dal `route-type`; senza, il sentiero) o `poi`.
 *
 * @param array<string, mixed> $detail wmwp_detail_current().
 */
function forestas_dettaglio_tipo( array $detail ): string {
	if ( 'poi' === ( $detail['type'] ?? '' ) ) {
		return 'poi';
	}
	$tipi = [
		'route-type-trail'     => 'sentiero',
		'route-type-itinerary' => 'itinerario',
		'route-type-walk'      => 'cammino',
	];
	$id   = $detail['feature']['properties']['taxonomies']['route-type'][0]['identifier'] ?? '';
	return $tipi[ $id ] ?? 'sentiero';
}

/**
 * Nome del cammino per la riga sopra il titolo (Tavola 5: «Cammino · Cammino Minerario di Santa Barbara»): il layer di
 * tipologia della traccia, `properties.layer_name` (oc:8959). Oggi i cammini stanno tutti in «Cammini di Sardegna»;
 * quando ognuno avrà il suo layer arriverà il nome del cammino senza cambiare niente qui. Vuoto per sentieri e
 * itinerari, il cui layer è solo il nome del tipo («Sentieri», «Itinerari»).
 *
 * @param array<string, mixed> $properties `properties` del dettaglio.
 * @return array{testo: string, lingua: ?string}
 */
function forestas_dettaglio_nome_cammino( array $properties, string $tipo, ?string $lingua = null ): array {
	if ( 'cammino' !== $tipo ) {
		return [
			'testo'  => '',
			'lingua' => null,
		];
	}
	return forestas_dettaglio_testo_lingua( $properties['layer_name'] ?? null, $lingua );
}

/**
 * Chiave stabile di tracce e POI per il plugin (filtro `wmwp_detail_source`): il nid di Drupal, che a differenza dell'id
 * dello shard non cambia con il reset notturno di dev e UAT. Il plugin lo usa per riconoscere l'elemento dopo un
 * reimport e per il suffisso degli slug doppi, che così non cambiano. Arriva in `forestas.sardegnasentieri_id` (dettaglio
 * di tracce e POI, `pois.geojson` dopo il reimport) o piatto, `sardegnasentieri_id` (indice Elasticsearch delle tracce)
 * (oc:8959). Senza, resta quello del plugin: `out_source_feature_id`, che in `pois.geojson` è lo stesso nid.
 *
 * @param string               $fonte      Chiave trovata dal plugin.
 * @param string               $tipo       `track` o `poi`.
 * @param array<string, mixed> $properties Proprietà del dettaglio, di `pois.geojson` o `_source` dell'indice.
 */
function forestas_dettaglio_nid( string $fonte, string $tipo, array $properties ): string {
	$nid = $properties['forestas']['sardegnasentieri_id'] ?? $properties['sardegnasentieri_id'] ?? null;
	return is_scalar( $nid ) && '' !== trim( (string) $nid ) ? trim( (string) $nid ) : $fonte;
}
add_filter( 'wmwp_detail_source', 'forestas_dettaglio_nid', 10, 3 );

/**
 * Colore del tipo e della fascia dei titoli di sezione (implementazione-wordpress.md di oc:8783).
 *
 * @return array{colore: string, fascia: string}
 */
function forestas_dettaglio_colori( string $tipo ): array {
	$colori = [
		'sentiero'   => [ '#DE1B0D', '#FDEEED' ],
		'itinerario' => [ '#58810B', '#EFF4E6' ],
		'cammino'    => [ '#1A416F', '#E8ECF1' ],
		'poi'        => [ '#B7127F', '#F8E9F3' ],
	];
	[ $colore, $fascia ] = $colori[ $tipo ] ?? $colori['sentiero'];
	return [
		'colore' => $colore,
		'fascia' => $fascia,
	];
}

/**
 * Ruoli degli enti nell'ordine della scheda Drupal, con l'etichetta di Drupal (in inglese quella del sito inglese
 * dove c'è: «Managing authority», «Maintainer»). Le chiavi inglesi sono quelle di `organizations`, le italiane quelle
 * di `entities` (formato prima dello scrum del 12/11).
 *
 * @return array<string, array{chiavi: list<string>, etichetta: string}>
 */
function forestas_dettaglio_ruoli(): array {
	return [
		'gestore'             => [
			'chiavi'    => [ 'manager', 'gestore' ],
			'etichetta' => forestas_testo( 'Soggetto gestore', 'Managing authority' ),
		],
		'manutentore'         => [
			'chiavi'    => [ 'maintainer', 'manutentore' ],
			'etichetta' => forestas_testo( 'Soggetto manutentore', 'Maintainer' ),
		],
		'rilevatore'          => [
			'chiavi'    => [ 'surveyor', 'rilevatore' ],
			'etichetta' => forestas_testo( 'Soggetto rilevatore', 'Surveyor' ),
		],
		'operatore'           => [
			'chiavi'    => [ 'operator', 'operatore' ],
			'etichetta' => forestas_testo( 'Operatore', 'Operator' ),
		],
		'complesso_forestale' => [
			'chiavi'    => [ 'forest_complex', 'complesso_forestale' ],
			'etichetta' => forestas_testo( 'Complesso forestale', 'Forest complex' ),
		],
	];
}

/**
 * Enti collegati, un elemento per ente e ruolo, nell'ordine dei ruoli. Il nome è null nel formato vecchio
 * (`entities`, solo id e ruoli).
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return list<array{ruolo: string, etichetta: string, id: int, nome: ?string, slug: ?string}>
 */
function forestas_dettaglio_enti( array $properties ): array {
	$forestas = (array) ( $properties['forestas'] ?? [] );
	$enti     = [];
	foreach ( forestas_dettaglio_ruoli() as $ruolo => $info ) {
		$lista = [];
		if ( isset( $forestas['organizations'] ) && is_array( $forestas['organizations'] ) ) {
			foreach ( $info['chiavi'] as $chiave ) {
				$lista = array_merge( $lista, (array) ( $forestas['organizations'][ $chiave ] ?? [] ) );
			}
		} else {
			foreach ( (array) ( $forestas['entities'] ?? [] ) as $ente ) {
				if ( array_intersect( $info['chiavi'], (array) ( $ente['roles'] ?? [] ) ) ) {
					$lista[] = [ 'id' => $ente['id'] ?? 0 ];
				}
			}
		}
		foreach ( $lista as $ente ) {
			if ( ! is_array( $ente ) ) {
				continue;
			}
			$nome   = forestas_dettaglio_testo( $ente['name'] ?? null );
			$enti[] = [
				'ruolo'     => $ruolo,
				'etichetta' => $info['etichetta'],
				'id'        => (int) ( $ente['id'] ?? 0 ),
				'nome'      => '' !== $nome ? $nome : null,
				'slug'      => isset( $ente['slug'] ) && '' !== (string) $ente['slug'] ? (string) $ente['slug'] : null,
			];
		}
	}
	return $enti;
}

/**
 * Tre indicatori della scheda: validazione (dal termine `validation-status`), percorribilità (il dato non esiste
 * ancora: sempre null) e accessibilità (da `forestas.inclusivity`).
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return array{validazione: ?array{chiave: string, etichetta: string}, percorribilita: null, accessibilita: ?array{classe: string, punteggio: ?int}}
 */
function forestas_dettaglio_indicatori( array $properties ): array {
	$termine     = $properties['taxonomies']['validation-status'][0] ?? null;
	$validazione = null;
	if ( is_array( $termine ) && ! empty( $termine['identifier'] ) ) {
		$etichette   = forestas_search_term_labels();
		$validazione = [
			'chiave'    => (string) $termine['identifier'],
			'etichetta' => $etichette[ $termine['identifier'] ] ?? forestas_dettaglio_testo( $termine['name'] ?? null ),
		];
	}
	return [
		'validazione'    => $validazione,
		'percorribilita' => null,
		'accessibilita'  => forestas_dettaglio_accessibilita( $properties ),
	];
}

/**
 * Gradi di accessibilità dell'Allegato H (DGR 40/40), dal più semplice: il livello è la posizione (1-3).
 *
 * @return array<string, array{livello: int, nome: string}>
 */
function forestas_dettaglio_gradi_accessibilita(): array {
	return [
		'AT'  => [ 'livello' => 1, 'nome' => forestas_testo( 'Accessibile Turistico', 'Accessible for tourists' ) ],
		'AE'  => [ 'livello' => 2, 'nome' => forestas_testo( 'Accessibile Escursionistico', 'Accessible for hikers' ) ],
		'AEE' => [ 'livello' => 3, 'nome' => forestas_testo( 'Accessibile Escursionistico per Esperti', 'Accessible for expert hikers' ) ],
	];
}

/**
 * Colonna «Accessibilità» degli indicatori (wireframe di oc:8783, Tavola 3, nota 10): il grado AT · AE · AEE del
 * sentiero (`inclusivity-difficulty`, uno solo) e le tre tipologie di disabilità (`inclusivity-disabilities`), accese o
 * spente. Senza grado il sentiero non è classificato: tre gradi spenti.
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return array{grado: ?string, livello: int, nome: ?string, disabilita: array{motoria: bool, sensoriale: bool, cognitiva: bool}, icone: array{motoria: string, sensoriale: string, cognitiva: string}}
 */
function forestas_dettaglio_accessibilita( array $properties ): array {
	$gradi   = forestas_dettaglio_gradi_accessibilita();
	$termine = $properties['taxonomies']['inclusivity-difficulty'][0] ?? null;
	$grado   = is_array( $termine ) ? strtoupper( (string) preg_replace( '/^inclusivity-difficulty-/', '', (string) ( $termine['identifier'] ?? '' ) ) ) : '';
	$grado   = isset( $gradi[ $grado ] ) ? $grado : null;
	$accese  = [];
	// Icona di ogni tipologia: quella del termine (`icon_name`, scelta in Nova fra le icone del set dello shard); le
	// tipologie spente nel JSON non ci sono, e per loro vale l'icona di Forestas caricata nel set (txn-disability-*)
	$icone = [
		'motoria'    => 'txn-disability-motor',
		'sensoriale' => 'txn-disability-sensory',
		'cognitiva'  => 'txn-disability-cognitive',
	];
	$chiavi = [
		'inclusivity-disabilities-physical'  => 'motoria',
		'inclusivity-disabilities-sensory'   => 'sensoriale',
		'inclusivity-disabilities-cognitive' => 'cognitiva',
	];
	foreach ( (array) ( $properties['taxonomies']['inclusivity-disabilities'] ?? [] ) as $disabilita ) {
		$identifier = is_array( $disabilita ) ? (string) ( $disabilita['identifier'] ?? '' ) : '';
		$accese[]   = $identifier;
		if ( isset( $chiavi[ $identifier ] ) && '' !== (string) ( $disabilita['icon_name'] ?? '' ) ) {
			$icone[ $chiavi[ $identifier ] ] = (string) $disabilita['icon_name'];
		}
	}
	return [
		'grado'      => $grado,
		'livello'    => null !== $grado ? $gradi[ $grado ]['livello'] : 0,
		'nome'       => null !== $grado ? $gradi[ $grado ]['nome'] : null,
		'disabilita' => [
			'motoria'    => in_array( 'inclusivity-disabilities-physical', $accese, true ),
			'sensoriale' => in_array( 'inclusivity-disabilities-sensory', $accese, true ),
			'cognitiva'  => in_array( 'inclusivity-disabilities-cognitive', $accese, true ),
		],
		'icone'      => $icone,
	];
}

/**
 * Classi del punteggio di inclusività (Allegato H, legenda del wireframe): nome, intervallo e la frase della classe,
 * che il wireframe dà solo per A2. Le tacche vanno da sei (A1) a una (A6).
 *
 * @return array<string, array{nome: string, intervallo: string, frase: string}>
 */
function forestas_dettaglio_classi_inclusivita(): array {
	return [
		'A1' => [ 'nome' => forestas_testo( 'Turistico-accessibile avanzato', 'Advanced tourist-accessible' ), 'intervallo' => '80–100', 'frase' => '' ],
		'A2' => [ 'nome' => forestas_testo( 'Turistico-accessibile', 'Tourist-accessible' ), 'intervallo' => '57–79', 'frase' => forestas_testo( 'Percorsi facili/moderati, servizi buoni, digitale buono.', 'Easy/moderate routes, good services, good digital access.' ) ],
		'A3' => [ 'nome' => forestas_testo( 'Escursionistico-accessibile', 'Hiking-accessible' ), 'intervallo' => '41–56', 'frase' => '' ],
		'A4' => [ 'nome' => forestas_testo( 'Accessibile con accompagnatore', 'Accessible with a companion' ), 'intervallo' => '25–40', 'frase' => '' ],
		'A5' => [ 'nome' => forestas_testo( 'Condizionatamente accessibile', 'Conditionally accessible' ), 'intervallo' => '15–24', 'frase' => '' ],
		'A6' => [ 'nome' => forestas_testo( 'Non sufficientemente accessibile', 'Not sufficiently accessible' ), 'intervallo' => '12–14', 'frase' => '' ],
	];
}

/**
 * Blocco «Sentiero per tutti» (wireframe, Tavola 3, nota 11) da `forestas.inclusivity`: la classe con le sue tacche, i
 * due testi del redattore e i tre punteggi dell'Allegato H riproporzionati su sei tacche (una per ogni sesto
 * dell'intervallo raggiunto per intero: al minimo nessuna, al massimo sei; il numero non si mostra). Null senza classe:
 * la classe c'è solo con tutti e tre i punteggi, e il blocco compare solo allora.
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return array{classe: string, tacche: int, nome: string, frase: string, autonomia: string, assistenza: string, punteggi: list<array{etichetta: string, tacche: int}>}|null
 */
function forestas_dettaglio_inclusivita( array $properties ): ?array {
	$dati   = $properties['forestas']['inclusivity'] ?? null;
	$classi = forestas_dettaglio_classi_inclusivita();
	$classe = is_array( $dati ) ? (string) ( $dati['class'] ?? '' ) : '';
	if ( ! isset( $classi[ $classe ] ) ) {
		return null;
	}
	$scale    = [
		'physical' => [ forestas_testo( 'Accessibilità fisica', 'Physical accessibility' ), 11, 55 ],
		'aids'     => [ forestas_testo( 'Presenza di ausili', 'Available aids' ), 1, 23 ],
		'digital'  => [ forestas_testo( 'Accessibilità digitale', 'Digital accessibility' ), 0, 22 ],
	];
	$punteggi = [];
	foreach ( $scale as $chiave => [ $etichetta, $minimo, $massimo ] ) {
		$valore     = is_numeric( $dati[ $chiave ] ?? null ) ? (float) $dati[ $chiave ] : $minimo;
		$punteggi[] = [
			'etichetta' => $etichetta,
			'tacche'    => (int) max( 0, min( 6, floor( ( $valore - $minimo ) / ( $massimo - $minimo ) * 6 ) ) ),
		];
	}
	return [
		'classe'     => $classe,
		'tacche'     => 7 - (int) substr( $classe, 1 ),
		'nome'       => $classi[ $classe ]['nome'],
		'frase'      => $classi[ $classe ]['frase'],
		'autonomia'  => forestas_dettaglio_testo( $dati['independent_use'] ?? null ),
		'assistenza' => forestas_dettaglio_testo( $dati['assistance'] ?? null ),
		'punteggi'   => $punteggi,
	];
}

/**
 * Link «Leggi l'articolo di approfondimento» delle legende: il «da sapere» sulle normative della rete dei sentieri,
 * che su Drupal è il nodo 5701 (`_drupal_nid`), nella lingua della pagina. '' se non c'è.
 */
function forestas_dettaglio_link_approfondimento(): string {
	return forestas_dettaglio_pagina_drupal( '5701' );
}

/**
 * Indirizzo della pagina o del «da sapere» che su Drupal è il nodo `$nid` (`_drupal_nid`, scritto dalla
 * sincronizzazione), nella lingua della pagina. '' se non c'è. Serve ai link che Drupal ha scritti nei template con il
 * numero del nodo: 5701 (normative), 1102 (gradi di difficoltà), 87 (Note legali, con i livelli di validazione).
 */
function forestas_dettaglio_pagina_drupal( string $nid ): string {
	static $url = [];
	if ( ! isset( $url[ $nid ] ) ) {
		$id           = get_posts(
			[
				'post_type'        => array_merge( [ 'page' ], FORESTAS_TIPI_IMPORTATI ),
				'post_status'      => 'publish',
				'meta_key'         => FORESTAS_META_NID, // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'       => $nid, // phpcs:ignore WordPress.DB.SlowDBQuery
				'fields'           => 'ids',
				'numberposts'      => 1,
				'suppress_filters' => true,
			]
		);
		$id           = $id ? (int) apply_filters( 'wpml_object_id', (int) $id[0], get_post_type( (int) $id[0] ), true ) : 0;
		$url[ $nid ]  = $id ? (string) get_permalink( $id ) : '';
	}
	return $url[ $nid ];
}

/** Valore di una tabella: il testo, o il trattino del wireframe se manca. */
function forestas_dettaglio_valore( $valore ): string {
	if ( null === $valore || '' === $valore || [] === $valore ) {
		return '—';
	}
	return is_bool( $valore ) ? forestas_testo( $valore ? 'Sì' : 'No', $valore ? 'Yes' : 'No' ) : (string) $valore;
}

/** Numero con la virgola o il punto della lingua e l'unità, o null. */
function forestas_dettaglio_misura( $numero, string $unita, int $decimali = 0 ): ?string {
	if ( ! is_numeric( $numero ) ) {
		return null;
	}
	return number_format_i18n( (float) $numero, $decimali ) . ' ' . $unita;
}

/** Durata in minuti come «3 h 20 min», o null. */
function forestas_dettaglio_durata( $minuti ): ?string {
	if ( ! is_numeric( $minuti ) || (int) $minuti <= 0 ) {
		return null;
	}
	$ore = intdiv( (int) $minuti, 60 );
	$min = (int) $minuti % 60;
	return trim( ( $ore ? $ore . ' h ' : '' ) . ( $min ? $min . ' min' : '' ) );
}

/**
 * Un gruppo dei dati del percorso (Percorso, Misure, Dati dell'itinerario, Enti…): titolo `h3`, pannello e tabella che
 * prende il nome dal titolo (`aria-labelledby`, al posto della didascalia, che lo screen reader leggerebbe due volte).
 * `assets/js/dettaglio.js` mette nel titolo il pulsante che apre e chiude il pannello (`aria-expanded`), come il
 * roadbook, e lo chiude se `data-wm-gr` è `chiuso`; senza JavaScript il pannello resta aperto (oc:8969, Rubens 13/11).
 *
 * @param list<array{0: string, 1: string, 2?: bool, 3?: array{url: string, nascosto: string}|null, 4?: ?string}> $righe
 *        Etichetta, valore, se il valore è un segnaposto, il link «Maggiori info» (con il resto del nome, nascosto, per
 *        lo screen reader) e la lingua del valore se ripiega su un'altra (`lang`).
 */
function forestas_dettaglio_gruppo( string $id, string $titolo, array $righe, bool $aperto ): string {
	if ( ! $righe ) {
		return '';
	}
	$html = '';
	foreach ( $righe as $riga ) {
		$link  = ! empty( $riga[3]['url'] )
			? ' <a class="wm-dt__mi" href="' . esc_url( $riga[3]['url'] ) . '">' . esc_html( forestas_testo( 'Maggiori info', 'More info' ) ) . '<span class="screen-reader-text"> ' . esc_html( (string) ( $riga[3]['nascosto'] ?? '' ) ) . '</span></a>'
			: '';
		$html .= sprintf( '<tr><th scope="row">%1$s</th><td%3$s%5$s>%2$s%4$s</td></tr>', esc_html( (string) $riga[0] ), esc_html( (string) $riga[1] ), ! empty( $riga[2] ) ? ' class="wm-segnaposto"' : '', $link, forestas_dettaglio_lang( $riga[4] ?? null ) );
	}
	return sprintf(
		'<div class="wm-gr" data-wm-gr="%3$s"><h3 class="wm-gr__h" id="%1$s">%2$s</h3><div class="wm-gr__p" id="%1$s-p"><table class="wm-dt" aria-labelledby="%1$s"><tbody>%4$s</tbody></table></div></div>',
		esc_attr( $id ),
		esc_html( $titolo ),
		$aperto ? 'aperto' : 'chiuso',
		$html
	);
}

/**
 * Gruppi dei dati, come le tavole del wireframe: sentiero Percorso, Misure e Catasto (Tavola 3); itinerario Percorso e
 * Misure (Tavola 4, niente dati catastali: l'itinerario non è nel catasto, call del 02/11); cammino Misure e Stato
 * (Tavola 5). In Misure il grado di difficoltà, accanto alla validazione lo stato, ciascuno con «Maggiori info» verso
 * la pagina che Drupal linka (forestas_dettaglio_pagina_drupal()). Ogni valore assente è il trattino.
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return list<array{titolo: string, righe: list<array>}>
 */
function forestas_dettaglio_tabelle( array $properties, string $tipo ): array {
	$forestas   = (array) ( $properties['forestas'] ?? [] );
	$indicatori = forestas_dettaglio_indicatori( $properties );
	$partenza   = forestas_dettaglio_testo_lingua( $properties['from'] ?? null );
	$arrivo     = forestas_dettaglio_testo_lingua( $properties['to'] ?? null );
	$roundtrip  = isset( $properties['roundtrip'] ) ? (bool) $properties['roundtrip'] : null;
	$info       = function ( string $nid, string $nascosto ): array {
		$url = forestas_dettaglio_pagina_drupal( $nid );
		return '' !== $url ? [ 3 => [ 'url' => $url, 'nascosto' => $nascosto ] ] : [];
	};
	$difficolta = implode( '; ', array_map( fn( $d ) => $d[0] . ': ' . $d[1], forestas_dettaglio_difficolta( $properties ) ) );
	$percorso   = [
		'titolo' => forestas_testo( 'Percorso', 'Route' ),
		'righe'  => [
			[ forestas_testo( 'Partenza', 'Start' ), forestas_dettaglio_valore( $partenza['testo'] ), false, null, $partenza['lingua'] ],
			[ forestas_testo( 'Arrivo', 'End' ), forestas_dettaglio_valore( $arrivo['testo'] ), false, null, $arrivo['lingua'] ],
			[ forestas_testo( 'Andata e ritorno', 'Round trip' ), forestas_dettaglio_valore( $roundtrip ) ],
		],
	];
	$misure     = [
		'titolo' => forestas_testo( 'Misure', 'Measures' ),
		'righe'  => [
			[ forestas_testo( 'Lunghezza', 'Length' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['distance'] ?? null, 'km', 1 ) ) ],
			[ forestas_testo( 'Dislivello in salita', 'Elevation gain' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['ascent'] ?? null, 'm' ) ) ],
			[ forestas_testo( 'Dislivello in discesa', 'Elevation loss' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['descent'] ?? null, 'm' ) ) ],
			[ forestas_testo( 'Quota minima', 'Minimum elevation' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['ele_min'] ?? null, 'm' ) ) ],
			[ forestas_testo( 'Quota massima', 'Maximum elevation' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['ele_max'] ?? null, 'm' ) ) ],
			[ forestas_testo( 'Tempo di percorrenza', 'Walking time' ), forestas_dettaglio_valore( forestas_dettaglio_durata( $properties['duration_forward'] ?? null ) ) ],
			[ forestas_testo( 'Grado di difficoltà', 'Difficulty level' ), forestas_dettaglio_valore( $difficolta ) ] + $info( '1102', forestas_testo( 'sui gradi di difficoltà', 'about difficulty levels' ) ),
		],
	];
	$validazione = [ forestas_testo( 'Stato di validazione', 'Validation status' ), forestas_dettaglio_valore( $indicatori['validazione']['etichetta'] ?? null ) ] + $info( '87', forestas_testo( 'sui livelli di validazione', 'about validation levels' ) );
	if ( 'itinerario' === $tipo ) {
		return [ $percorso, $misure ];
	}
	if ( 'cammino' === $tipo ) {
		return [
			$misure,
			[
				'titolo' => forestas_testo( 'Stato', 'Status' ),
				'righe'  => [ $validazione ],
			],
		];
	}
	return [
		$percorso,
		$misure,
		[
			'titolo' => forestas_testo( 'Catasto', 'Trail register' ),
			'righe'  => [
				[ forestas_testo( 'Codice catastale', 'Register code' ), forestas_dettaglio_valore( $forestas['registry_code'] ?? null ) ],
				$validazione,
				[ forestas_testo( 'Data del rilievo', 'Survey date' ), forestas_dettaglio_valore( forestas_dettaglio_data( $forestas['survey_date'] ?? null ) ) ],
				// Il dato non esiste ancora nello shard: trattino, come nel wireframe
				[ forestas_testo( 'Data di accatastamento', 'Registration date' ), '—' ],
			],
		],
	];
}

/** Data `AAAA-MM-GG` nel formato del sito, o null. */
function forestas_dettaglio_data( $data ): ?string {
	if ( ! is_string( $data ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}/', $data ) ) {
		return null;
	}
	$time = strtotime( substr( $data, 0, 10 ) );
	return false === $time ? null : date_i18n( get_option( 'date_format' ), $time );
}

/**
 * Card dei correlati: i POI della traccia (`related_pois`) e i sentieri vicini (`forestas.nearby_tracks`, che sono i
 * «sentieri correlati»). L'indirizzo viene dal plugin (`wmwp_detail_url`), nella lingua della pagina.
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return array{poi: list<array<string, mixed>>, percorsi: list<array<string, mixed>>}
 */
function forestas_dettaglio_correlati( array $properties ): array {
	$codice    = (string) apply_filters( 'wpml_current_language', 'it' );
	$url       = function ( string $tipo, int $id ) use ( $codice ): ?string {
		$link = function_exists( 'wmwp_detail_url' ) ? wmwp_detail_url( $tipo, $id, $codice ) : '';
		return '' !== $link ? $link : null;
	};
	$correlati = [
		'poi'      => [],
		'percorsi' => [],
	];
	foreach ( (array) ( $properties['related_pois'] ?? [] ) as $feature ) {
		$poi = (array) ( $feature['properties'] ?? [] );
		$id  = (int) ( $poi['id'] ?? 0 );
		if ( $id <= 0 ) {
			continue;
		}
		$tipo                 = forestas_dettaglio_testo( $poi['taxonomy']['poi_type']['name'] ?? ( $poi['taxonomies']['poi-type'][0]['name'] ?? null ) );
		$correlati['poi'][] = [
			'id'       => $id,
			'nome'     => forestas_dettaglio_testo( $poi['name'] ?? null ),
			'tipo'     => '' !== $tipo ? $tipo : null,
			'immagine' => $poi['feature_image']['thumbnail'] ?? null,
			'url'      => $url( 'poi', $id ),
		];
	}
	foreach ( (array) ( $properties['forestas']['nearby_tracks'] ?? [] ) as $traccia ) {
		$card = forestas_dettaglio_card_percorso( (array) $traccia );
		if ( null !== $card ) {
			$correlati['percorsi'][] = $card;
		}
	}
	return $correlati;
}

/**
 * Card di un percorso (sentieri correlati di una traccia, percorsi vicini di un POI), dai dati della card dello shard.
 *
 * @param array<string, mixed> $traccia Elemento di `nearby_tracks`.
 * @return array<string, mixed>|null
 */
function forestas_dettaglio_card_percorso( array $traccia ): ?array {
	$id = (int) ( $traccia['id'] ?? 0 );
	if ( $id <= 0 ) {
		return null;
	}
	$tipo   = forestas_dettaglio_testo( $traccia['route_type']['name'] ?? null );
	$link   = function_exists( 'wmwp_detail_url' ) ? wmwp_detail_url( 'track', $id, (string) apply_filters( 'wpml_current_language', 'it' ) ) : '';
	return [
		'id'        => $id,
		'nome'      => forestas_dettaglio_testo( $traccia['name'] ?? null ),
		'codice'    => isset( $traccia['ref'] ) && '' !== trim( (string) $traccia['ref'] ) ? trim( (string) $traccia['ref'] ) : null,
		'tipo'      => '' !== $tipo ? $tipo : null,
		'tipo_id'   => $traccia['route_type']['identifier'] ?? null,
		'immagine'  => $traccia['feature_image']['thumbnail'] ?? null,
		'lunghezza' => forestas_dettaglio_misura( $traccia['distance'] ?? null, 'km', 1 ),
		'tempo'     => forestas_dettaglio_durata( $traccia['duration_forward'] ?? null ),
		// Distanza dal POI (percorsi vicini, metri); fra due percorsi lo shard non la dà ancora
		'distanza'  => forestas_dettaglio_distanza( $traccia['distance_from_poi'] ?? null ),
		'url'       => '' !== $link ? $link : null,
	];
}

/** Distanza in metri come «a 49 m» o «a 1,5 km», o null. */
function forestas_dettaglio_distanza( $metri ): ?string {
	if ( ! is_numeric( $metri ) ) {
		return null;
	}
	$metri = (float) $metri;
	return forestas_testo( 'a ', '' ) . ( $metri < 1000 ? (int) round( $metri ) . ' m' : number_format_i18n( $metri / 1000, 1 ) . ' km' ) . forestas_testo( '', ' away' );
}

/** Card di un correlato (POI o percorso), nella griglia a tre colonne. */
function forestas_dettaglio_card( array $item, string $classe ): string {
	// Card unica del sito (forestas_card() di inc/contenuti.php, la .uc del wireframe), come i post e la ricerca: il
	// filetto dell'etichetta ha il colore del menu del tipo (percorsi: sentiero, itinerario, cammino; POI: punti di
	// interesse), e tutta la card si apre dal link del titolo
	$tipi  = [
		'route-type-trail'     => 'sentiero',
		'route-type-itinerary' => 'itinerario',
		'route-type-walk'      => 'cammino',
	];
	$tipo  = 'poi' === $classe ? 'poi' : ( $tipi[ $item['tipo_id'] ?? '' ] ?? 'sentiero' );
	$meta  = array_filter( [ $item['lunghezza'] ?? null, $item['tempo'] ?? null, $item['distanza'] ?? null ] );
	$media = ! empty( $item['immagine'] )
		? '<div class="forestas-card__media"><img src="' . esc_url( $item['immagine'] ) . '" alt="" loading="lazy" decoding="async"></div>'
		: '<div class="forestas-card__media forestas-card__media--segnaposto"></div>';
	$nome  = esc_html( (string) $item['nome'] );
	return sprintf(
		'<li class="forestas-card forestas-card--immagine forestas-card--%1$s">%2$s<div class="forestas-card__testo">%3$s<h3 class="forestas-card__titolo">%4$s</h3>%5$s</div></li>',
		esc_attr( $tipo ),
		$media,
		// Un POI senza tipologia ha comunque l'etichetta, e con lei il filetto del colore dei punti di interesse
		( static function ( string $etichetta ): string {
			return '' !== $etichetta ? '<span class="forestas-card__tag">' . esc_html( $etichetta ) . '</span>' : '';
		} )( ! empty( $item['tipo'] ) ? (string) $item['tipo'] : ( 'poi' === $tipo ? forestas_testo( 'Punto di interesse', 'Point of interest' ) : '' ) ),
		! empty( $item['url'] ) ? '<a href="' . esc_url( $item['url'] ) . '">' . $nome . '</a>' : $nome,
		$meta ? '<p class="forestas-card__data">' . esc_html( implode( ' · ', $meta ) ) . '</p>' : ''
	);
}

/** Griglia di card con le prime 3 visibili e «Mostra tutti (N)». */
function forestas_dettaglio_griglia( array $items, string $classe, string $id ): string {
	$html = sprintf( '<ul class="wm-ucg forestas-griglia" id="%s" data-wm-limite="3">', esc_attr( $id ) );
	foreach ( $items as $item ) {
		$html .= forestas_dettaglio_card( $item, $classe );
	}
	return $html . '</ul>' . forestas_dettaglio_mostra_tutti( $id, count( $items ) );
}

/**
 * Luoghi dalla tassonomia `where`: aree geografiche (i nomi che iniziano con «Area»), comuni (admin_level 8) e
 * provincia (admin_level 6). Le zone catastali (ZNUB5…) e la regione non si mostrano.
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return array{aree: list<string>, comuni: list<string>, provincia: ?string}
 */
function forestas_dettaglio_luoghi( array $properties ): array {
	$luoghi = [
		'aree'      => [],
		'comuni'    => [],
		'provincia' => null,
	];
	foreach ( (array) ( $properties['taxonomies']['where'] ?? [] ) as $luogo ) {
		// Nello shard alcuni nomi sono l'identifier (en: «R19621461»): quei valori non contano
		$nomi    = array_filter( (array) ( $luogo['name'] ?? [] ), fn( $v ) => is_string( $v ) && $v !== (string) ( $luogo['identifier'] ?? '' ) );
		$nome    = forestas_dettaglio_testo( $nomi );
		$livello = (int) ( $luogo['admin_level'] ?? $luogo['_admin_level'] ?? 0 );
		if ( '' === $nome ) {
			continue;
		}
		if ( 8 === $livello ) {
			$luoghi['comuni'][] = $nome;
		} elseif ( 6 === $livello && null === $luoghi['provincia'] ) {
			$luoghi['provincia'] = $nome;
		} elseif ( 0 === $livello && 0 === stripos( $nome, 'Area ' ) ) {
			$luoghi['aree'][] = $nome;
		}
	}
	return $luoghi;
}

/**
 * Difficoltà dichiarata per attività (una riga per famiglia `*-difficulty` che la traccia ha), come nella tabella della
 * Tavola 3: [ attività, valore ]. Il grado di accessibilità (`inclusivity-difficulty`) no: sta nell'indicatore
 * Accessibilità, e il link dei gradi di difficoltà non lo spiega (review 13/11).
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return list<array{0: string, 1: string, 2: ?string}> attività, valore e lingua del valore
 */
function forestas_dettaglio_difficolta( array $properties ): array {
	$attivita = [
		'hiking-difficulty'             => forestas_testo( 'Escursionismo', 'Hiking' ),
		'mtb-difficulty'                => forestas_testo( 'Mountain bike', 'Mountain bike' ),
		'horse-difficulty'              => forestas_testo( 'Ippovia', 'Horse riding' ),
		'nordic-walking-difficulty'     => forestas_testo( 'Nordic walking', 'Nordic walking' ),
		'soft-mobility-difficulty'      => forestas_testo( 'Mobilità dolce', 'Soft mobility' ),
		'educational-trails-difficulty' => forestas_testo( 'Sentiero didattico', 'Educational trail' ),
	];
	$righe    = [];
	foreach ( $attivita as $chiave => $nome ) {
		$valore = forestas_dettaglio_testo_lingua( $properties['taxonomies'][ $chiave ][0]['name'] ?? null );
		if ( '' !== $valore['testo'] ) {
			$righe[] = [ $nome, $valore['testo'], $valore['lingua'] ];
		}
	}
	return $righe;
}

/**
 * Avvertenze (`warnings`) nella lingua della pagina, con la maiuscola iniziale.
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return list<string>
 */
function forestas_dettaglio_avvertenze( array $properties ): array {
	$avvertenze = [];
	foreach ( (array) ( $properties['taxonomies']['warnings'] ?? [] ) as $termine ) {
		$nome = forestas_dettaglio_testo( $termine['name'] ?? null );
		if ( '' !== $nome ) {
			$avvertenze[] = mb_strtoupper( mb_substr( $nome, 0, 1 ) ) . mb_substr( $nome, 1 );
		}
	}
	return $avvertenze;
}

/** Livello della validazione catastale sulla scala 0-6 della legenda (ordine dei termini dello shard). */
function forestas_dettaglio_livello_validazione( string $chiave ): int {
	$livelli = [
		'validation-status-unverified'       => 0,
		'validation-status-not-walkable'     => 1,
		'validation-status-under-review'     => 2,
		'validation-status-pre-registration' => 3,
		'validation-status-walkable'         => 4,
		'validation-status-validated'        => 5,
		'validation-status-certified'        => 6,
	];
	return $livelli[ $chiave ] ?? 0;
}

/**
 * Foto della galleria: immagine intera, miniatura e i crediti che lo shard mette su ogni foto.
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return list<array{url: string, miniatura: string, didascalia: string, autore: string, credits: string}>
 */
function forestas_dettaglio_galleria( array $properties ): array {
	$foto = [];
	foreach ( (array) ( $properties['image_gallery'] ?? [] ) as $immagine ) {
		$url = is_array( $immagine ) ? (string) ( $immagine['url'] ?? '' ) : '';
		if ( '' === $url ) {
			continue;
		}
		$foto[] = [
			'url'        => $url,
			'miniatura'  => (string) ( $immagine['sizes']['400x200'] ?? $immagine['thumbnail'] ?? $url ),
			'didascalia' => forestas_dettaglio_testo( $immagine['caption'] ?? null ),
			'autore'     => forestas_dettaglio_testo( $immagine['author'] ?? null ),
			'credits'    => forestas_dettaglio_testo( $immagine['credits'] ?? null ),
		];
	}
	return $foto;
}

// CSS e JS del dettaglio, solo sulle pagine di dettaglio
add_action(
	'wp_enqueue_scripts',
	function () {
		if ( ! function_exists( 'wmwp_detail_current' ) || ! wmwp_detail_current() ) {
			return;
		}
		$base = get_stylesheet_directory_uri() . '/assets/';
		wp_enqueue_style( 'forestas-dettaglio', $base . 'css/dettaglio.css', [], forestas_versione() );
		wp_enqueue_script( 'forestas-dettaglio', $base . 'js/dettaglio.js', [], forestas_versione(), [ 'in_footer' => true, 'strategy' => 'defer' ] );
	},
	20
);

/**
 * Nome senza il codice tra parentesi in coda («Anello di Munduge (B 532A)»), quando il codice è già nella bandierina:
 * nei nomi importati da Drupal il codice c'è quasi sempre.
 */
function forestas_dettaglio_nome( string $nome, string $codice ): string {
	// I codici si confrontano con le sole lettere e cifre: «(B 401)» nel nome e «B-401» nel `ref` sono lo stesso
	$segni = fn( string $testo ): string => mb_strtolower( (string) preg_replace( '/[^\p{L}\p{N}]+/u', '', $testo ) );
	if ( '' === $segni( $codice ) || ! preg_match( '/^(.*?)\s*\(([^()]*)\)\s*$/u', $nome, $m ) ) {
		return $nome;
	}
	return $segni( $m[2] ) === $segni( $codice ) && '' !== trim( $m[1] ) ? trim( $m[1] ) : $nome;
}

/**
 * Dati del punto di interesse. Il `forestas` dei POI ha ancora le chiavi dell'import (`come_arrivare`, `allegati` come
 * elenco di URL, `video`), che lo shard ripulirà come quello delle tracce: si leggono solo qui, con le chiavi nuove per
 * prime. I luoghi vengono da `taxonomy_where` (oggetto id → nomi, con `_admin_level`).
 *
 * @param array<string, mixed> $properties properties del JSON del POI.
 * @return array{tipologia: ?string, come_arrivare: string, allegati: list<array{url: string, title: string}>, video: list<array{url: string, title: ?string}>, luoghi: array{aree: list<string>, comuni: list<string>, provincia: ?string}}
 */
function forestas_dettaglio_poi( array $properties ): array {
	$forestas  = (array) ( $properties['forestas'] ?? [] );
	$tipologia = forestas_dettaglio_testo_lingua( $properties['taxonomies']['poi-type'][0]['name'] ?? $properties['taxonomy']['poi_type']['name'] ?? null );
	$percorsi = array_values( array_filter( array_map( fn( $t ) => forestas_dettaglio_card_percorso( (array) $t ), (array) ( $forestas['nearby_tracks'] ?? [] ) ) ) );
	$where    = [];
	foreach ( (array) ( $properties['taxonomy_where'] ?? [] ) as $id => $nomi ) {
		$nomi    = (array) $nomi;
		$where[] = [
			'identifier'  => (string) $id,
			'name'        => array_filter( $nomi, fn( $v, $k ) => is_string( $v ) && '_' !== substr( (string) $k, 0, 1 ), ARRAY_FILTER_USE_BOTH ),
			'admin_level' => (int) ( $nomi['_admin_level'] ?? 0 ),
		];
	}
	return [
		'tipologia'     => '' !== $tipologia['testo'] ? $tipologia['testo'] : null,
		'tipologia_lang' => $tipologia['lingua'],
		'come_arrivare' => forestas_dettaglio_ricco( $forestas['directions'] ?? $forestas['come_arrivare'] ?? null ),
		'allegati'      => forestas_dettaglio_allegati( $forestas )['allegati'],
		'video'         => forestas_dettaglio_allegati( $forestas )['video'],
		'luoghi'        => forestas_dettaglio_luoghi( [ 'taxonomies' => [ 'where' => $where ] ] ),
		// Percorsi vicini (raggio deciso dallo shard, i più vicini per primi; al massimo 10 e il totale)
		'percorsi'        => $percorsi,
		'percorsi_totale' => max( count( $percorsi ), (int) ( $forestas['nearby_tracks_count'] ?? 0 ) ),
	];
}

/** Titolo della prima sezione, con l'articolo giusto: «Il sentiero in breve», «L'itinerario in breve». */
function forestas_dettaglio_titolo_breve( string $tipo ): string {
	$titoli = [
		'sentiero'   => forestas_testo( 'Il sentiero in breve', 'The trail at a glance' ),
		'itinerario' => forestas_testo( "L'itinerario in breve", 'The itinerary at a glance' ),
		'cammino'    => forestas_testo( 'Il cammino in breve', 'The walk at a glance' ),
	];
	return $titoli[ $tipo ] ?? $titoli['sentiero'];
}

/**
 * Pulsante «Mostra tutti (N)» sotto un elenco con `data-wm-limite` (correlati, galleria): lo mostra
 * assets/js/dettaglio.js quando gli elementi sono più del limite; senza JavaScript resta nascosto e l'elenco è intero.
 */
function forestas_dettaglio_mostra_tutti( string $id, int $totale, int $limite = 3 ): string {
	if ( $totale <= $limite ) {
		return '';
	}
	return sprintf(
		'<div class="wm-ucg__more" hidden><button type="button" class="forestas-pulsante" aria-controls="%1$s" aria-expanded="false" data-piu="%2$s" data-meno="%3$s">%2$s</button></div>',
		esc_attr( $id ),
		esc_attr( sprintf( forestas_testo( 'Mostra tutti (%d)', 'Show all (%d)' ), $totale ) ),
		esc_attr( forestas_testo( 'Mostra meno', 'Show less' ) )
	);
}

/**
 * Mappa del dettaglio (wireframe di oc:8783, Tavola 3 per sentiero, itinerario e cammino, Tavola 7 per il POI), per
 * `wmwp_map()` del plugin e per la legenda sotto la mappa.
 * - Percorso: i percorsi collegati (`forestas.nearby_tracks`) tratteggiati #5B7A99 e di sfondo, la traccia della scheda
 *   nel colore del suo tipo da 4 px, i primi tre punti di interesse correlati (`related_pois`, con la posizione)
 *   magenta e numerati come nella legenda; partenza (cerchio verde) e arrivo (bandiera a scacchi) della traccia della
 *   scheda, come la mappa di GeoHub del sito attuale; in un anello (fine entro 50 m dall'inizio, la stessa regola del
 *   plugin) la legenda ha solo la partenza. Senza la geometria la legenda le ha tutte e due.
 * - POI: il punto nero e i percorsi vicini (`forestas.nearby_tracks`) nel colore del loro tipo; la legenda nomina i
 *   tipi presenti.
 *
 * @param array<string, mixed>      $properties properties del JSON.
 * @param array<string, mixed>|null $geometria  geometria del POI (Point), per il suo punto, o della traccia, per sapere
 *                                              se è un anello.
 * @return array{tracks: list<array<string, mixed>>, points: list<array<string, mixed>>, legenda: list<array<string, string>>}
 */
function forestas_dettaglio_mappa( array $properties, string $tipo, int $id, ?array $geometria = null ): array {
	$lingua   = (string) apply_filters( 'wpml_current_language', 'it' );
	$url      = function ( string $tipo_url, int $id_url ) use ( $lingua ): string {
		return function_exists( 'wmwp_detail_url' ) ? wmwp_detail_url( $tipo_url, $id_url, $lingua ) : '';
	};
	$tipi     = [
		'route-type-trail'     => 'sentiero',
		'route-type-itinerary' => 'itinerario',
		'route-type-walk'      => 'cammino',
	];
	$vicini   = [];
	foreach ( (array) ( $properties['forestas']['nearby_tracks'] ?? [] ) as $traccia ) {
		$traccia = (array) $traccia;
		$vicino  = (int) ( $traccia['id'] ?? 0 );
		if ( $vicino <= 0 ) {
			continue;
		}
		$codice   = trim( (string) ( $traccia['ref'] ?? '' ) );
		$nome     = forestas_dettaglio_nome( forestas_dettaglio_testo( $traccia['name'] ?? null ), $codice );
		$vicini[] = [
			'id'     => $vicino,
			'codice' => $codice,
			'nome'   => $nome,
			'tipo'   => $tipi[ $traccia['route_type']['identifier'] ?? '' ] ?? 'sentiero',
			'url'    => $url( 'track', $vicino ),
		];
	}
	$tooltip  = fn( array $v ): string => implode( ' · ', array_filter( [ $v['codice'], $v['nome'] ] ) );
	$mappa    = [
		'tracks'  => [],
		'points'  => [],
		'legenda' => [],
	];

	if ( 'poi' === $tipo ) {
		$coordinate = $geometria['coordinates'] ?? null;
		if ( 'Point' === ( $geometria['type'] ?? '' ) && is_array( $coordinate ) && isset( $coordinate[0], $coordinate[1] ) ) {
			$mappa['points'][] = [
				'coordinates' => [ (float) $coordinate[0], (float) $coordinate[1] ],
				'color'       => '#1A1A1A',
				'tooltip'     => forestas_dettaglio_testo( $properties['name'] ?? null ),
			];
		}
		$presenti = [];
		foreach ( $vicini as $vicino ) {
			$mappa['tracks'][]           = [
				'id'      => $vicino['id'],
				'color'   => forestas_dettaglio_colori( $vicino['tipo'] )['colore'],
				'width'   => 3,
				'tooltip' => $tooltip( $vicino ),
				'url'     => $vicino['url'],
			];
			$presenti[ $vicino['tipo'] ] = true;
		}
		$nomi = [
			'sentiero'   => forestas_testo( 'Sentieri', 'Trails' ),
			'itinerario' => forestas_testo( 'Itinerari', 'Itineraries' ),
			'cammino'    => forestas_testo( 'Cammini', 'Walks' ),
		];
		foreach ( $nomi as $chiave => $nome ) {
			if ( isset( $presenti[ $chiave ] ) ) {
				$mappa['legenda'][] = [
					'tipo'   => 'linea',
					'colore' => forestas_dettaglio_colori( $chiave )['colore'],
					'testo'  => $nome,
				];
			}
		}
		return $mappa;
	}

	foreach ( $vicini as $vicino ) {
		$mappa['tracks'][] = [
			'id'      => $vicino['id'],
			'color'   => '#5B7A99',
			'width'   => 2.5,
			'dash'    => [ 7, 5 ],
			'context' => true,
			'tooltip' => $tooltip( $vicino ),
			'url'     => $vicino['url'],
		];
	}
	$colore            = forestas_dettaglio_colori( $tipo )['colore'];
	$codice            = trim( (string) ( $properties['ref'] ?? '' ) );
	$partenza          = forestas_testo( 'Partenza', 'Start' );
	$arrivo            = forestas_testo( 'Arrivo', 'Finish' );
	$mappa['tracks'][] = [
		'id'    => $id,
		'color' => $colore,
		'width' => 4,
		'label' => $codice,
		'ends'  => [
			'start' => $partenza,
			'end'   => $arrivo,
		],
	];
	$della             = [
		'sentiero'   => forestas_testo( 'sentiero della scheda', 'trail on this page' ),
		'itinerario' => forestas_testo( 'itinerario della scheda', 'itinerary on this page' ),
		'cammino'    => forestas_testo( 'cammino della scheda', 'walk on this page' ),
	][ $tipo ] ?? forestas_testo( 'percorso della scheda', 'route on this page' );
	$mappa['legenda'][] = [
		'tipo'   => 'linea',
		'colore' => $colore,
		'testo'  => '' !== $codice ? $codice . ' · ' . $della : ucfirst( $della ),
	];
	$mappa['legenda'][] = [
		'tipo'   => 'partenza',
		'colore' => '#1F8F35',
		'testo'  => $partenza,
	];
	if ( ! forestas_dettaglio_anello( $geometria ) ) {
		$mappa['legenda'][] = [
			'tipo'   => 'arrivo',
			'colore' => '#1A1A1A',
			'testo'  => $arrivo,
		];
	}
	if ( $vicini ) {
		// Stesse parole del titolo della sezione «Percorsi correlati» (Rubens, 13/11)
		$mappa['legenda'][] = [
			'tipo'   => 'tratteggio',
			'colore' => '#5B7A99',
			'testo'  => forestas_testo( 'Percorsi correlati', 'Related routes' ) . ': ' . implode( ', ', array_map( fn( $v ) => '' !== $v['codice'] ? $v['codice'] : $v['nome'], $vicini ) ),
		];
	}
	// I primi tre punti di interesse correlati con la posizione, come nella tavola
	$numero = 0;
	foreach ( (array) ( $properties['related_pois'] ?? [] ) as $feature ) {
		$coordinate = $feature['geometry']['coordinates'] ?? null;
		$poi        = (array) ( $feature['properties'] ?? [] );
		if ( 'Point' !== ( $feature['geometry']['type'] ?? '' ) || ! is_array( $coordinate ) || ! isset( $coordinate[0], $coordinate[1] ) || (int) ( $poi['id'] ?? 0 ) <= 0 ) {
			continue;
		}
		$nome              = forestas_dettaglio_testo( $poi['name'] ?? null );
		$mappa['points'][] = [
			'coordinates' => [ (float) $coordinate[0], (float) $coordinate[1] ],
			'color'       => '#B7127F',
			'number'      => (string) ++$numero,
			'tooltip'     => $nome,
			'url'         => $url( 'poi', (int) $poi['id'] ),
		];
		$mappa['legenda'][] = [
			'tipo'   => 'punto',
			'colore' => '#B7127F',
			'testo'  => $nome,
			'numero' => (string) $numero,
		];
		if ( 3 === $numero ) {
			break;
		}
	}
	return $mappa;
}

/**
 * La traccia è un anello? Fine entro 50 m dall'inizio, come in assets/js/track-map.js del plugin, che allora non
 * disegna l'arrivo. Senza geometria (o non lineare) no.
 *
 * @param array<string, mixed>|null $geometria LineString o MultiLineString.
 */
function forestas_dettaglio_anello( ?array $geometria ): bool {
	$linee = 'MultiLineString' === ( $geometria['type'] ?? '' ) ? (array) $geometria['coordinates'] : ( 'LineString' === ( $geometria['type'] ?? '' ) ? [ (array) $geometria['coordinates'] ] : [] );
	$linee = array_values( array_filter( $linee, fn( $l ) => is_array( $l ) && $l ) );
	if ( ! $linee ) {
		return false;
	}
	$ultima = end( $linee );
	$a      = $linee[0][0];
	$b      = end( $ultima );
	if ( ! isset( $a[0], $a[1], $b[0], $b[1] ) ) {
		return false;
	}
	$rad = M_PI / 180;
	$h   = sin( ( $b[1] - $a[1] ) * $rad / 2 ) ** 2 + cos( $a[1] * $rad ) * cos( $b[1] * $rad ) * sin( ( $b[0] - $a[0] ) * $rad / 2 ) ** 2;
	return 2 * 6371000 * asin( min( 1, sqrt( $h ) ) ) <= 50;
}

/**
 * Contenuto della colonna della mappa: la mappa del plugin (`wmwp_map()`), la legenda sotto la mappa e «Vedi sulla mappa
 * interattiva», come la Tavola 3 e la Tavola 7 del wireframe (`.map-leg`, `.foot`).
 *
 * @param array{tracks: list<array<string, mixed>>, points: list<array<string, mixed>>, legenda: list<array<string, string>>} $mappa Da forestas_dettaglio_mappa().
 */
function forestas_dettaglio_mappa_html( array $mappa, string $etichetta, int $altezza, string $tipo = '', int $id = 0 ): string {
	if ( ! function_exists( 'wmwp_map' ) ) {
		return '';
	}
	$html = wmwp_map(
		[
			'tracks' => $mappa['tracks'],
			'points' => $mappa['points'],
			'height' => $altezza,
			'label'  => $etichetta,
		]
	);
	if ( '' === $html ) {
		return '';
	}
	if ( $mappa['legenda'] ) {
		$html .= '<ul class="wm-map-leg" aria-label="' . esc_attr( forestas_testo( 'Legenda della mappa', 'Map legend' ) ) . '">';
		foreach ( $mappa['legenda'] as $voce ) {
			if ( 'punto' === $voce['tipo'] ) {
				$segno = '<span class="wm-map-leg__poi" style="background:' . esc_attr( $voce['colore'] ) . '" aria-hidden="true">' . esc_html( $voce['numero'] ?? '' ) . '</span>';
			} elseif ( in_array( $voce['tipo'], [ 'partenza', 'arrivo' ], true ) ) {
				$segno = '<span class="wm-map-leg__' . ( 'partenza' === $voce['tipo'] ? 'start' : 'end' ) . '" aria-hidden="true"></span>';
			} else {
				$segno = '<span class="wm-map-leg__' . ( 'tratteggio' === $voce['tipo'] ? 'near' : 'main' ) . '" style="--wm-leg:' . esc_attr( $voce['colore'] ) . '" aria-hidden="true"></span>';
			}
			$html .= '<li>' . $segno . esc_html( $voce['testo'] ) . '</li>';
		}
		$html .= '</ul>';
	}
	$pagina = forestas_pagina_per_chiave( 'mappa-interattiva' );
	if ( $pagina ) {
		// Con il focus sull'elemento, come `/mappa-interattiva?id=<nid>` di Drupal (forestas_mappa_url())
		$indirizzo = (string) get_permalink( $pagina );
		$indirizzo = in_array( $tipo, [ 'track', 'poi' ], true ) && $id > 0 ? add_query_arg( $tipo, $id, $indirizzo ) : $indirizzo;
		$html     .= '<div class="wm-map-foot"><a class="forestas-pulsante wm-map-foot__btn" href="' . esc_url( $indirizzo ) . '">'
			. esc_html( forestas_testo( 'Vedi sulla mappa interattiva', 'View on the interactive map' ) )
			. '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M9 4 3 6.5v13.5l6-2.5 6 2.5 6-2.5V4l-6 2.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M9 4v13.5M15 6.5V20" fill="none" stroke="currentColor" stroke-width="1.8"/></svg></a></div>';
	}
	return $html;
}

/**
 * Allegati e video di una traccia o di un POI, dal `forestas`: chiavi nuove (`attachments`, `videos`: elenchi di
 * `{ url, title }`) o vecchie dei POI (`allegati`, `video`: elenchi di URL). Senza titolo, l'allegato prende il nome del
 * file decodificato; il video resta senza titolo (l'HTML scrive «Video N»).
 *
 * @param array<string, mixed> $forestas `properties.forestas`.
 * @return array{allegati: list<array{url: string, title: string}>, video: list<array{url: string, title: ?string}>}
 */
function forestas_dettaglio_allegati( array $forestas ): array {
	$elenco = function ( $voci ): array {
		$risultato = [];
		foreach ( (array) $voci as $voce ) {
			$url = is_array( $voce ) ? (string) ( $voce['url'] ?? '' ) : (string) $voce;
			if ( '' !== $url ) {
				$titolo      = is_array( $voce ) ? trim( (string) ( $voce['title'] ?? '' ) ) : '';
				$risultato[] = [
					'url'   => $url,
					'title' => '' !== $titolo ? $titolo : null,
				];
			}
		}
		return $risultato;
	};
	$allegati = array_map(
		fn( $a ) => [
			'url'   => $a['url'],
			'title' => $a['title'] ?? rawurldecode( basename( (string) wp_parse_url( $a['url'], PHP_URL_PATH ) ) ),
		],
		$elenco( $forestas['attachments'] ?? $forestas['allegati'] ?? [] )
	);
	return [
		'allegati' => $allegati,
		'video'    => $elenco( $forestas['videos'] ?? $forestas['video'] ?? [] ),
	];
}

/**
 * Elenco degli allegati (da scaricare) e dei video (in una finestra nuova, detto allo screen reader); '' se non ce ne
 * sono.
 *
 * @param array{allegati: list<array{url: string, title: string}>, video: list<array{url: string, title: ?string}>} $allegati
 */
function forestas_dettaglio_allegati_html( array $allegati ): string {
	if ( ! $allegati['allegati'] && ! $allegati['video'] ) {
		return '';
	}
	$html = '<ul class="wm-files">';
	foreach ( $allegati['allegati'] as $allegato ) {
		$html .= '<li><a href="' . esc_url( $allegato['url'] ) . '" download>' . esc_html( $allegato['title'] ) . '</a></li>';
	}
	foreach ( $allegati['video'] as $i => $filmato ) {
		$html .= '<li><a href="' . esc_url( $filmato['url'] ) . '" target="_blank" rel="noopener">'
			. esc_html( $filmato['title'] ?? sprintf( forestas_testo( 'Video %d', 'Video %d' ), $i + 1 ) )
			. '<span class="screen-reader-text"> ' . esc_html( forestas_testo( '(si apre in una nuova finestra)', '(opens in a new window)' ) ) . '</span></a></li>';
	}
	return $html . '</ul>';
}
