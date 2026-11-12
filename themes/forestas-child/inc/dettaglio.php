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

/** Id della pagina «Punti di interesse» nella lingua predefinita, 0 se non c'è. */
function forestas_dettaglio_pagina_poi(): int {
	static $id = null;
	if ( null === $id ) {
		$pagine = get_posts(
			[
				'post_type'        => 'page',
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'meta_key'         => '_wpf_pagina',
				'meta_value'       => 'da-vedere',
				'suppress_filters' => true,
			]
		);
		$id     = $pagine ? (int) apply_filters( 'wpml_object_id', (int) $pagine[0], 'page', true, apply_filters( 'wpml_default_language', null ) ) : 0;
	}
	return $id;
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
 * Dati del dettaglio. Le chiavi del JSON dello shard si leggono solo qui: quando lo shard le cambia (gli enti da
 * `entities` a `organizations`, il `forestas` dei POI da ripulire) si corregge un punto solo. Ogni dato può mancare
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
	$lingua = $lingua ?? forestas_dettaglio_lingua();
	if ( is_string( $valore ) ) {
		return trim( $valore );
	}
	if ( ! is_array( $valore ) ) {
		return '';
	}
	// Poi la prima lingua non vuota: nello shard i nomi dei comuni hanno spesso solo `en`
	foreach ( array_merge( [ $lingua, 'it' ], array_keys( $valore ) ) as $chiave ) {
		$testo = $valore[ $chiave ] ?? null;
		if ( is_array( $testo ) ) {
			$testo = implode( "\n", array_filter( array_map( 'trim', array_filter( $testo, 'is_string' ) ) ) );
		}
		if ( is_string( $testo ) && '' !== trim( $testo ) ) {
			return trim( $testo );
		}
	}
	return '';
}

/**
 * Righe dei Blocchi Dettaglio della traccia (`properties.config_detail`, campo «Detail Blocks» del package): le righe
 * di tutti i blocchi `info` in un solo elenco, come le mostra la webapp. Oggi l'unico blocco è il roadbook, che l'import
 * scrive con una riga per tappa («Tappa 1», «Tappa 2»…, oc:8959); i blocchi aggiunti in Nova seguono. Titolo e testo
 * nella lingua, con ripiego sull'italiano come il resto della pagina; le righe senza testo si saltano.
 *
 * @param array<string, mixed> $p Proprietà della traccia.
 * @return list<array{titolo: string, testo: string}>
 */
function forestas_dettaglio_blocchi( array $p, ?string $lingua = null ): array {
	$righe = [];
	foreach ( is_array( $p['config_detail'] ?? null ) ? $p['config_detail'] : [] as $blocco ) {
		if ( ! is_array( $blocco ) || 'info' !== ( $blocco['box_type'] ?? null ) || ! is_array( $blocco['items'] ?? null ) ) {
			continue;
		}
		foreach ( $blocco['items'] as $riga ) {
			$testo = is_array( $riga ) ? forestas_dettaglio_testo( $riga['content'] ?? null, $lingua ) : '';
			if ( '' === wp_strip_all_tags( $testo ) ) {
				continue;
			}
			$righe[] = [
				'titolo' => forestas_dettaglio_testo( $riga['title'] ?? null, $lingua ),
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
		$etichette   = function_exists( 'forestas_search_term_labels' ) ? forestas_search_term_labels() : [];
		$validazione = [
			'chiave'    => (string) $termine['identifier'],
			'etichetta' => $etichette[ $termine['identifier'] ] ?? forestas_dettaglio_testo( $termine['name'] ?? null ),
		];
	}
	$inclusivita   = $properties['forestas']['inclusivity'] ?? null;
	$accessibilita = is_array( $inclusivita ) && ! empty( $inclusivita['class'] )
		? [
			'classe'    => (string) $inclusivita['class'],
			'punteggio' => isset( $inclusivita['score'] ) ? (int) $inclusivita['score'] : null,
		]
		: null;
	return [
		'validazione'    => $validazione,
		'percorribilita' => null,
		'accessibilita'  => $accessibilita,
	];
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
 * Tabelle dei dati: per il sentiero Percorso, Misure e Catasto; per itinerario e cammino una tabella sola, senza i
 * dati catastali (l'itinerario non è nel catasto, call del 02/11). Ogni valore assente è il trattino.
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return list<array{titolo: string, righe: list<array{0: string, 1: string}>}>
 */
function forestas_dettaglio_tabelle( array $properties, string $tipo ): array {
	$forestas    = (array) ( $properties['forestas'] ?? [] );
	$indicatori  = forestas_dettaglio_indicatori( $properties );
	$partenza    = forestas_dettaglio_testo( $properties['from'] ?? null );
	$arrivo      = forestas_dettaglio_testo( $properties['to'] ?? null );
	$roundtrip   = isset( $properties['roundtrip'] ) ? (bool) $properties['roundtrip'] : null;
	$percorso    = [
		[ forestas_testo( 'Partenza', 'Start' ), forestas_dettaglio_valore( $partenza ) ],
		[ forestas_testo( 'Arrivo', 'End' ), forestas_dettaglio_valore( $arrivo ) ],
		[ forestas_testo( 'Andata e ritorno', 'Round trip' ), forestas_dettaglio_valore( $roundtrip ) ],
	];
	$misure      = [
		[ forestas_testo( 'Lunghezza', 'Length' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['distance'] ?? null, 'km', 1 ) ) ],
		[ forestas_testo( 'Dislivello in salita', 'Elevation gain' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['ascent'] ?? null, 'm' ) ) ],
		[ forestas_testo( 'Dislivello in discesa', 'Elevation loss' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['descent'] ?? null, 'm' ) ) ],
		[ forestas_testo( 'Quota minima', 'Minimum elevation' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['ele_min'] ?? null, 'm' ) ) ],
		[ forestas_testo( 'Quota massima', 'Maximum elevation' ), forestas_dettaglio_valore( forestas_dettaglio_misura( $properties['ele_max'] ?? null, 'm' ) ) ],
		[ forestas_testo( 'Tempo di percorrenza', 'Walking time' ), forestas_dettaglio_valore( forestas_dettaglio_durata( $properties['duration_forward'] ?? null ) ) ],
	];
	if ( 'sentiero' !== $tipo ) {
		$titolo = 'cammino' === $tipo ? forestas_testo( 'Dati del cammino', 'Walk data' ) : forestas_testo( "Dati dell'itinerario", 'Itinerary data' );
		return [
			[
				'titolo' => $titolo,
				'righe'  => array_merge( [ [ forestas_testo( 'Codice', 'Code' ), forestas_dettaglio_valore( forestas_dettaglio_testo( $properties['ref'] ?? null ) ) ] ], $percorso, $misure ),
			],
		];
	}
	return [
		[
			'titolo' => forestas_testo( 'Percorso', 'Route' ),
			'righe'  => $percorso,
		],
		[
			'titolo' => forestas_testo( 'Misure', 'Measures' ),
			'righe'  => $misure,
		],
		[
			'titolo' => forestas_testo( 'Catasto', 'Trail register' ),
			'righe'  => [
				[ forestas_testo( 'Codice catastale', 'Register code' ), forestas_dettaglio_valore( $forestas['registry_code'] ?? null ) ],
				[ forestas_testo( 'Stato di validazione', 'Validation status' ), forestas_dettaglio_valore( $indicatori['validazione']['etichetta'] ?? null ) ],
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
	$meta = array_filter( [ $item['lunghezza'] ?? null, $item['tempo'] ?? null, $item['distanza'] ?? null ] );
	return sprintf(
		'<li class="wm-uc wm-uc--%1$s"><a class="wm-uc__link"%2$s><span class="wm-uc__media">%3$s</span><span class="wm-uc__body">%4$s<span class="wm-uc__title">%5$s</span>%6$s</span></a></li>',
		esc_attr( $classe ),
		! empty( $item['url'] ) ? ' href="' . esc_url( $item['url'] ) . '"' : '',
		! empty( $item['immagine'] ) ? '<img src="' . esc_url( $item['immagine'] ) . '" alt="" loading="lazy" decoding="async">' : '',
		! empty( $item['tipo'] ) ? '<span class="wm-uc__tag">' . esc_html( $item['tipo'] ) . '</span>' : '',
		esc_html( (string) $item['nome'] ),
		$meta ? '<span class="wm-uc__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>' : ''
	);
}

/** Griglia di card con le prime 3 visibili e «Mostra tutti (N)». */
function forestas_dettaglio_griglia( array $items, string $classe, string $id ): string {
	$html = sprintf( '<ul class="wm-ucg" id="%s" data-wm-limite="3">', esc_attr( $id ) );
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
 * Tavola 3: [ attività, valore ].
 *
 * @param array<string, mixed> $properties properties del JSON.
 * @return list<array{0: string, 1: string}>
 */
function forestas_dettaglio_difficolta( array $properties ): array {
	$attivita = [
		'hiking-difficulty'             => forestas_testo( 'Escursionismo', 'Hiking' ),
		'mtb-difficulty'                => forestas_testo( 'Mountain bike', 'Mountain bike' ),
		'horse-difficulty'              => forestas_testo( 'Ippovia', 'Horse riding' ),
		'nordic-walking-difficulty'     => forestas_testo( 'Nordic walking', 'Nordic walking' ),
		'soft-mobility-difficulty'      => forestas_testo( 'Mobilità dolce', 'Soft mobility' ),
		'educational-trails-difficulty' => forestas_testo( 'Sentiero didattico', 'Educational trail' ),
		'inclusivity-difficulty'        => forestas_testo( 'Accessibilità', 'Accessibility' ),
	];
	$righe    = [];
	foreach ( $attivita as $chiave => $nome ) {
		$valore = forestas_dettaglio_testo( $properties['taxonomies'][ $chiave ][0]['name'] ?? null );
		if ( '' !== $valore ) {
			$righe[] = [ $nome, $valore ];
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
	if ( '' === $codice ) {
		return $nome;
	}
	$senza = preg_replace( '/\s*\(\s*' . preg_quote( $codice, '/' ) . '\s*\)\s*$/u', '', $nome );
	return is_string( $senza ) && '' !== trim( $senza ) ? trim( $senza ) : $nome;
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
	$tipologia = forestas_dettaglio_testo( $properties['taxonomies']['poi-type'][0]['name'] ?? $properties['taxonomy']['poi_type']['name'] ?? null );
	$elenco    = function ( $voci ): array {
		$risultato = [];
		foreach ( (array) $voci as $voce ) {
			$url = is_array( $voce ) ? (string) ( $voce['url'] ?? '' ) : (string) $voce;
			if ( '' === $url ) {
				continue;
			}
			$titolo      = is_array( $voce ) ? (string) ( $voce['title'] ?? '' ) : '';
			$risultato[] = [
				'url'   => $url,
				'title' => '' !== $titolo ? $titolo : rawurldecode( basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ),
			];
		}
		return $risultato;
	};
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
		'tipologia'     => '' !== $tipologia ? $tipologia : null,
		'come_arrivare' => forestas_dettaglio_testo( $forestas['directions'] ?? $forestas['come_arrivare'] ?? null ),
		'allegati'      => $elenco( $forestas['attachments'] ?? $forestas['allegati'] ?? [] ),
		'video'         => array_map(
			fn( $v ) => [
				'url'   => $v['url'],
				'title' => null,
			],
			$elenco( $forestas['videos'] ?? $forestas['video'] ?? [] )
		),
		'luoghi'        => forestas_dettaglio_luoghi( [ 'taxonomies' => [ 'where' => $where ] ] ),
		// Percorsi vicini (entro 1 km, i più vicini per primi; al massimo 10 e il totale)
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
