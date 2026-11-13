<?php
/**
 * Test (oc:8969): dati del dettaglio di percorsi e POI letti dal JSON dello shard in un solo punto (inc/dettaglio.php).
 * Una traccia completa e una vuota (dettaglio di ripiego o traccia nuova): tipo, colori, enti nel formato nuovo
 * (`organizations`) e in quello vecchio (`entities`, senza nome), tabelle con il trattino per i dati assenti, correlati,
 * testi nella lingua con ripiego sull'italiano. Nessun avviso PHP.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/dettaglio.php
 * Stampa «PASS» o «FAIL: …» ed esce con 0 o 1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$failures = [];
$check    = function ( bool $ok, string $what ) use ( &$failures ) {
	if ( ! $ok ) {
		$failures[] = $what;
	}
};
set_error_handler(
	function ( $errno, $message, $file, $line ) use ( &$failures ) {
		$failures[] = "avviso PHP: {$message} ({$file}:{$line})";
		return true;
	}
);

if ( ! function_exists( 'forestas_dettaglio_tipo' ) ) {
	echo "FAIL: forestas_dettaglio_tipo non esiste\n";
	exit( 1 );
}

$completa = [
	'type'    => 'track',
	'id'      => 520,
	'lang'    => 'it',
	'feature' => [
		'properties' => [
			'ref'           => 'B 532A',
			'from'          => 'Dispensa Su Carcinargiu',
			'to'            => 'Paule Mundugia',
			'roundtrip'     => false,
			'taxonomies'    => [
				'route-type'        => [ [ 'identifier' => 'route-type-walk', 'name' => [ 'it' => 'Cammino', 'en' => 'Walk' ] ] ],
				'validation-status' => [ [ 'identifier' => 'validation-status-validated', 'name' => [ 'it' => 'Validato' ] ] ],
			],
			'forestas'      => [
				'registry_code' => 'Z-NU-B-532A',
				'survey_date'   => '2009-08-12',
				'roadbook'      => [ 'it' => [ '<p>Primo tratto</p>', '<p>Secondo tratto</p>' ], 'en' => [ '<p>First</p>' ] ],
				'inclusivity'   => [ 'score' => 90, 'class' => 'A1' ],
				'organizations' => [
					'surveyor' => [ [ 'id' => 3, 'name' => [ 'it' => 'Forestas Lanusei' ], 'slug' => 'forestas-lanusei' ] ],
					'manager'  => [ [ 'id' => 31, 'name' => [ 'it' => 'Comune di Talana' ], 'slug' => 'comune-di-talana' ] ],
				],
				'nearby_tracks' => [
					[ 'id' => 260, 'ref' => null, 'name' => [ 'it' => 'Ogliastra' ], 'route_type' => [ 'identifier' => 'route-type-itinerary', 'name' => [ 'it' => 'Itinerario' ] ], 'feature_image' => [ 'thumbnail' => 'http://x/t.jpg' ], 'distance' => 12.4, 'duration_forward' => 300 ],
					[ 'id' => 261, 'name' => [ 'it' => 'Senza foto' ] ],
				],
			],
			'related_pois'  => [
				[ 'properties' => [ 'id' => 9, 'name' => [ 'it' => 'Nuraghe' ], 'feature_image' => [ 'thumbnail' => 'http://x/n.jpg' ], 'taxonomy' => [ 'poi_type' => [ 'name' => [ 'it' => 'Nuraghi' ] ] ] ] ],
			],
		],
	],
];
$vuota    = [
	'type'    => 'track',
	'id'      => 7,
	'lang'    => 'it',
	'feature' => [ 'properties' => [ 'id' => 7, 'name' => [ 'it' => 'Nuova' ] ] ],
];
$poi      = [
	'type'    => 'poi',
	'id'      => 9,
	'lang'    => 'it',
	'feature' => [ 'properties' => [ 'id' => 9 ] ],
];

// 1. Tipo e colori
$check( 'cammino' === forestas_dettaglio_tipo( $completa ), 'tipo dal route-type' );
$check( 'sentiero' === forestas_dettaglio_tipo( $vuota ), 'senza route-type → sentiero' );
$check( 'poi' === forestas_dettaglio_tipo( $poi ), 'POI' );
$check( [ 'colore' => '#1A416F', 'fascia' => '#E8ECF1' ] === forestas_dettaglio_colori( 'cammino' ), 'colori del cammino' );
$check( '#B7127F' === forestas_dettaglio_colori( 'poi' )['colore'], 'colore del POI' );

// 1b. Nome del cammino nella testata: il layer di tipologia (`layer_name`, oc:8959), solo per i cammini
$check( [ 'testo' => 'Cammini di Sardegna', 'lingua' => 'it' ] === forestas_dettaglio_nome_cammino( [ 'layer_name' => [ 'it' => 'Cammini di Sardegna', 'en' => ' ' ] ], 'cammino', 'en' ), 'nome del cammino, con la lingua del ripiego' );
$check( '' === forestas_dettaglio_nome_cammino( [ 'layer_name' => [ 'it' => 'Sentieri' ] ], 'sentiero', 'it' )['testo'], 'sentiero: il layer è solo il nome del tipo' );
$check( '' === forestas_dettaglio_nome_cammino( [], 'cammino', 'it' )['testo'], 'cammino senza layer_name' );

// 1c. Chiave stabile dei dettagli per il plugin (filtro wmwp_detail_source): il nid di Drupal, che non cambia con il
// reset notturno degli id dello shard
$check( '137' === forestas_dettaglio_nid( '', 'poi', [ 'forestas' => [ 'sardegnasentieri_id' => '137' ] ] ), 'nid dal forestas del dettaglio' );
$check( '4076' === forestas_dettaglio_nid( '', 'track', [ 'sardegnasentieri_id' => 4076 ] ), 'nid piatto dell\'indice Elasticsearch' );
$check( '5646' === forestas_dettaglio_nid( '5646', 'poi', [ 'out_source_feature_id' => '5646' ] ), 'pois.geojson prima del reimport: resta out_source_feature_id' );
$check( '' === forestas_dettaglio_nid( '', 'track', [ 'forestas' => [ 'sardegnasentieri_id' => null ] ] ), 'traccia che non viene da Drupal' );
$check( false !== has_filter( 'wmwp_detail_source', 'forestas_dettaglio_nid' ), 'filtro wmwp_detail_source agganciato' );

// 2. Testi nella lingua con ripiego; le liste di blocchi si uniscono
$check( "<p>Primo tratto</p>\n<p>Secondo tratto</p>" === forestas_dettaglio_testo( $completa['feature']['properties']['forestas']['roadbook'], 'it' ), 'roadbook italiano' );
$check( '<p>First</p>' === forestas_dettaglio_testo( $completa['feature']['properties']['forestas']['roadbook'], 'en' ), 'roadbook inglese' );
$check( 'Solo' === forestas_dettaglio_testo( [ 'it' => 'Solo', 'en' => ' ' ], 'en' ), 'inglese vuoto → italiano' );
$check( '' === forestas_dettaglio_testo( null, 'it' ), 'niente → stringa vuota' );

// 2b. Blocchi Dettaglio (`config_detail`, oc:8959): le righe dei blocchi `info` in un solo elenco, come le mostra la
// webapp; titolo e testo nella lingua con ripiego sull'italiano; righe senza testo e altri tipi di blocco si saltano
$blocchi_p = [
	'config_detail' => [
		[
			'box_type' => 'info',
			'items'    => [
				[ 'title' => [ 'it' => 'Tappa 1', 'en' => 'Stage 1' ], 'content' => [ 'it' => '<p>Lasciata Nuxis</p>', 'en' => '<p>Leaving Nuxis</p>' ] ],
				[ 'title' => [ 'it' => 'Tappa 2' ], 'content' => [ 'it' => '<p>Ritornati sulla strada</p>' ] ],
				[ 'title' => [ 'it' => 'Vuota' ], 'content' => [ 'it' => ' ' ] ],
			],
		],
		[ 'box_type' => 'graph', 'items' => [ [ 'title' => [ 'it' => 'No' ], 'content' => [ 'it' => '<p>No</p>' ] ] ] ],
		[ 'box_type' => 'info', 'items' => [ [ 'title' => [ 'it' => 'Da sapere' ], 'content' => [ 'it' => '<p>Acqua</p>' ] ] ] ],
	],
];
$check(
	[
		[ 'titolo' => 'Tappa 1', 'lingua' => 'it', 'testo' => '<p>Lasciata Nuxis</p>' ],
		[ 'titolo' => 'Tappa 2', 'lingua' => 'it', 'testo' => '<p>Ritornati sulla strada</p>' ],
		[ 'titolo' => 'Da sapere', 'lingua' => 'it', 'testo' => '<p>Acqua</p>' ],
	] === forestas_dettaglio_blocchi( $blocchi_p, 'it' ),
	'blocchi info in un solo elenco, righe vuote e altri tipi saltati'
);
$inglese = forestas_dettaglio_blocchi( $blocchi_p, 'en' );
$check( 'Stage 1' === ( $inglese[0]['titolo'] ?? '' ) && '<p>Leaving Nuxis</p>' === ( $inglese[0]['testo'] ?? '' ), 'blocchi in inglese' );
$check( 'Tappa 2' === ( $inglese[1]['titolo'] ?? '' ) && 'it' === ( $inglese[1]['lingua'] ?? '' ) && '<div lang="it"><p>Ritornati sulla strada</p></div>' === ( $inglese[1]['testo'] ?? '' ), 'riga senza inglese → italiano, marcato lang="it"' );
$check( [] === forestas_dettaglio_blocchi( [], 'it' ) && [] === forestas_dettaglio_blocchi( [ 'config_detail' => 'x' ], 'it' ), 'senza config_detail, nessun blocco' );

// 2c. Accessibilità dei testi ricchi: titoli rinumerati sotto quello del blocco (h4 in giù, senza salti, WCAG 1.3.1)
// e lingua vera sul testo che ripiega su un'altra lingua (WCAG 3.1.2)
$ricco = forestas_dettaglio_ricco( [ 'it' => '<h4>Uno</h4><p>a</p><h3 class="x">Due</h3><h5>Tre</h5><h4>Quattro</h4><h6>Cinque</h6>' ], 'it' );
$check( '<h4>Uno</h4><p>a</p><h4 class="x">Due</h4><h5>Tre</h5><h5>Quattro</h5><h6>Cinque</h6>' === $ricco, 'titoli rinumerati da h4, senza salti: ' . $ricco );
$check( '<h4>Solo</h4>' === forestas_dettaglio_ricco( [ 'it' => '<h1>Solo</h1>' ], 'it' ), 'un h1 nel testo diventa h4' );
$check( '<div lang="it"><p>Ciao</p></div>' === forestas_dettaglio_ricco( [ 'it' => '<p>Ciao</p>' ], 'en' ), 'testo italiano su pagina inglese: lang="it"' );
$check( '<p>Hello</p>' === forestas_dettaglio_ricco( [ 'it' => '<p>Ciao</p>', 'en' => '<p>Hello</p>' ], 'en' ), 'testo nella lingua della pagina: senza lang' );
$check( '' === forestas_dettaglio_ricco( null, 'it' ), 'niente testo, stringa vuota' );
$check( [ 'testo' => 'Ciao', 'lingua' => 'it' ] === forestas_dettaglio_testo_lingua( [ 'it' => 'Ciao' ], 'en' ), 'lingua usata dal ripiego' );

// 3. Enti: formato nuovo, in ordine gestore, manutentore, rilevatore…
$enti = forestas_dettaglio_enti( $completa['feature']['properties'] );
$check( 2 === count( $enti ), 'due enti' );
$check( 'gestore' === ( $enti[0]['ruolo'] ?? '' ) && 'Comune di Talana' === ( $enti[0]['nome'] ?? '' ), 'il gestore per primo, con il nome' );
$check( 'forestas-lanusei' === ( $enti[1]['slug'] ?? '' ), 'slug dell\'ente' );
$check( '' !== ( $enti[0]['etichetta'] ?? '' ), 'etichetta del ruolo' );
// Formato vecchio: solo id e ruoli, il nome manca
$vecchio = forestas_dettaglio_enti( [ 'forestas' => [ 'entities' => [ [ 'id' => 3, 'roles' => [ 'rilevatore', 'gestore' ] ] ] ] ] );
$check( 2 === count( $vecchio ) && null === $vecchio[0]['nome'] && 'gestore' === $vecchio[0]['ruolo'], 'formato vecchio: ruoli senza nome' );
$check( [] === forestas_dettaglio_enti( $vuota['feature']['properties'] ), 'nessun ente' );

// 4. Indicatori
$ind = forestas_dettaglio_indicatori( $completa['feature']['properties'] );
$check( 'validation-status-validated' === ( $ind['validazione']['chiave'] ?? '' ), 'validazione dal termine' );
$check( null === $ind['percorribilita'], 'percorribilità: dato che non esiste ancora' );
// Accessibilità = grado AT · AE · AEE (inclusivity-difficulty) e tipologie di disabilità (wireframe Tavola 3, nota 10)
$acc_p = [
	'taxonomies' => [
		'inclusivity-difficulty'   => [ [ 'identifier' => 'inclusivity-difficulty-ae', 'name' => [ 'it' => 'AE' ] ] ],
		'inclusivity-disabilities' => [ [ 'identifier' => 'inclusivity-disabilities-sensory' ], [ 'identifier' => 'inclusivity-disabilities-physical' ] ],
	],
];
$acc   = forestas_dettaglio_indicatori( $acc_p )['accessibilita'];
$check( 'AE' === ( $acc['grado'] ?? '' ) && 2 === ( $acc['livello'] ?? 0 ) && 'Accessibile Escursionistico' === ( $acc['nome'] ?? '' ), 'grado AE, livello 2 su 3, con il nome' );
$check( [ 'motoria' => true, 'sensoriale' => true, 'cognitiva' => false ] === ( $acc['disabilita'] ?? [] ), 'tipologie di disabilità accese e spente' );
// Icone dal set dello shard: il nome da `icon_name` del termine; per le tipologie spente, che nel JSON non ci sono, il
// nome delle icone di Forestas nel set (txn-disability-*)
$acc_p['taxonomies']['inclusivity-disabilities'][0]['icon_name'] = 'txn-occhio-nuovo';
$icone = forestas_dettaglio_accessibilita( $acc_p )['icone'] ?? [];
$check( [ 'motoria' => 'txn-disability-motor', 'sensoriale' => 'txn-occhio-nuovo', 'cognitiva' => 'txn-disability-cognitive' ] === $icone, 'icone: icon_name del termine, altrimenti quelle di Forestas' );
$senza = forestas_dettaglio_indicatori( [] )['accessibilita'];
$check( null === $senza['grado'] && 0 === $senza['livello'] && [ 'motoria' => false, 'sensoriale' => false, 'cognitiva' => false ] === $senza['disabilita'], 'non classificato: tre gradi spenti, icone spente' );

// «Sentiero per tutti»: classe, tacche della classe, due testi e tre punteggi riproporzionati su sei tacche (nota 11)
$spt = forestas_dettaglio_inclusivita( [ 'forestas' => [ 'inclusivity' => [ 'physical' => 41, 'aids' => 13, 'digital' => 11, 'score' => 65, 'class' => 'A2', 'independent_use' => 'Fondo stabile', 'assistance' => 'Joelette' ] ] ] );
$check( 'A2' === ( $spt['classe'] ?? '' ) && 5 === ( $spt['tacche'] ?? 0 ) && 'Turistico-accessibile' === ( $spt['nome'] ?? '' ), 'classe A2: cinque tacche e nome' );
$check( 'Fondo stabile' === ( $spt['autonomia'] ?? '' ) && 'Joelette' === ( $spt['assistenza'] ?? '' ), 'due testi del redattore' );
$check( [ 4, 3, 3 ] === array_column( $spt['punteggi'] ?? [], 'tacche' ), 'fisica 41, ausili 13, digitale 11 → 4, 3, 3 tacche (esempi del wireframe)' );
$estremi = forestas_dettaglio_inclusivita( [ 'forestas' => [ 'inclusivity' => [ 'physical' => 55, 'aids' => 1, 'digital' => 22, 'class' => 'A1' ] ] ] );
$check( [ 6, 0, 6 ] === array_column( $estremi['punteggi'] ?? [], 'tacche' ) && 6 === ( $estremi['tacche'] ?? 0 ), 'al minimo nessuna tacca, al massimo sei; A1 sei tacche' );
$check( null === forestas_dettaglio_inclusivita( [ 'forestas' => [ 'inclusivity' => [ 'physical' => 30 ] ] ] ) && null === forestas_dettaglio_inclusivita( [] ), 'senza classe il blocco non c\'è' );
$ind = forestas_dettaglio_indicatori( $vuota['feature']['properties'] );
$check( null === $ind['validazione'] && null === $ind['accessibilita']['grado'], 'indicatori vuoti' );

// 5. Tabelle: i valori assenti sono il trattino
$tabelle = forestas_dettaglio_tabelle( $vuota['feature']['properties'], 'sentiero' );
$check( 3 === count( $tabelle ), 'sentiero: tre tabelle' );
$valori = [];
foreach ( $tabelle as $tabella ) {
	foreach ( $tabella['righe'] as $riga ) {
		$valori[] = $riga[1];
	}
}
$check( [] !== $valori && [ '—' ] === array_values( array_unique( $valori ) ), 'traccia vuota: tutti trattini' );
// Gruppi come le tavole: sentiero Percorso, Misure, Catasto (Tavola 3); itinerario Percorso e Misure (Tavola 4);
// cammino Misure e Stato (Tavola 5). In Misure il grado di difficoltà, con l'attività davanti
$titoli  = fn( array $t ) => array_column( $t, 'titolo' );
$riga    = function ( array $t, string $gruppo, string $etichetta ) {
	foreach ( $t as $tabella ) {
		if ( $gruppo === $tabella['titolo'] ) {
			foreach ( $tabella['righe'] as $r ) {
				if ( $etichetta === $r[0] ) {
					return $r;
				}
			}
		}
	}
	return null;
};
$check( [ 'Percorso', 'Misure', 'Catasto' ] === $titoli( forestas_dettaglio_tabelle( [], 'sentiero' ) ), 'sentiero: Percorso, Misure, Catasto' );
$check( [ 'Percorso', 'Misure' ] === $titoli( forestas_dettaglio_tabelle( [], 'itinerario' ) ), 'itinerario: Percorso e Misure' );
$tabelle = forestas_dettaglio_tabelle( $completa['feature']['properties'], 'cammino' );
$check( [ 'Misure', 'Stato' ] === $titoli( $tabelle ), 'cammino: Misure e Stato' );
$check( 'Validato' === ( $riga( $tabelle, 'Stato', 'Stato di validazione' )[1] ?? null ), 'cammino: stato di validazione in Stato' );
$diff_p  = [ 'taxonomies' => [ 'hiking-difficulty' => [ [ 'name' => [ 'it' => 'E' ] ] ], 'inclusivity-difficulty' => [ [ 'name' => [ 'it' => 'AT' ] ] ] ] ];
$check( 'Escursionismo: E; Accessibilità: AT' === ( $riga( forestas_dettaglio_tabelle( $diff_p, 'sentiero' ), 'Misure', 'Grado di difficoltà' )[1] ?? null ), 'grado di difficoltà in Misure, con l\'attività' );
$check( '—' === ( $riga( forestas_dettaglio_tabelle( [], 'itinerario' ), 'Misure', 'Grado di difficoltà' )[1] ?? null ), 'senza difficoltà: trattino' );

// «Maggiori info» accanto al grado di difficoltà e alla validazione, come su Drupal: il «da sapere» dei gradi (nodo
// 1102) e le Note legali (nodo 87), che spiegano i livelli 0-6. Solo se la pagina c'è
$link_diff = forestas_dettaglio_pagina_drupal( '1102' );
$link_val  = forestas_dettaglio_pagina_drupal( '87' );
$check( '' === forestas_dettaglio_pagina_drupal( '999999999' ), 'nodo che non c\'è: niente link' );
$r = $riga( forestas_dettaglio_tabelle( [], 'sentiero' ), 'Misure', 'Grado di difficoltà' );
$check( ( '' === $link_diff && ! isset( $r[3] ) ) || ( $link_diff === ( $r[3]['url'] ?? '' ) && 'sui gradi di difficoltà' === ( $r[3]['nascosto'] ?? '' ) ), 'link ai gradi di difficoltà' );
$r = $riga( forestas_dettaglio_tabelle( [], 'sentiero' ), 'Catasto', 'Stato di validazione' );
$check( ( '' === $link_val && ! isset( $r[3] ) ) || ( $link_val === ( $r[3]['url'] ?? '' ) && 'sui livelli di validazione' === ( $r[3]['nascosto'] ?? '' ) ), 'link ai livelli di validazione' );
$gr = forestas_dettaglio_gruppo( 'g', 'Misure', [ [ 'Grado di difficoltà', 'E', false, [ 'url' => 'http://x/gradi/', 'nascosto' => 'sui gradi di difficoltà' ] ] ], true );
$check( false !== strpos( $gr, '<td>E <a class="wm-dt__mi" href="http://x/gradi/">Maggiori info<span class="screen-reader-text"> sui gradi di difficoltà</span></a></td>' ), 'link «Maggiori info» nella riga, con il testo nascosto per lo screen reader' );

// 6. Correlati
$correlati = forestas_dettaglio_correlati( $completa['feature']['properties'] );
$check( 2 === count( $correlati['percorsi'] ) && 1 === count( $correlati['poi'] ), 'correlati: 2 percorsi e 1 POI' );
$check( 'Ogliastra' === $correlati['percorsi'][0]['nome'] && 'http://x/t.jpg' === $correlati['percorsi'][0]['immagine'], 'card del percorso' );
$check( null === $correlati['percorsi'][1]['immagine'] && null === $correlati['percorsi'][1]['lunghezza'], 'card senza foto né lunghezza' );
$check( 'Nuraghe' === $correlati['poi'][0]['nome'] && 'Nuraghi' === $correlati['poi'][0]['tipo'], 'card del POI' );
$check( [ 'poi' => [], 'percorsi' => [] ] === forestas_dettaglio_correlati( $vuota['feature']['properties'] ), 'nessun correlato' );

// 7. Condividi su una pagina senza post: URL e titolo dati, non quelli di un post
$html = forestas_condividi( [ 'url' => 'http://example.test/sentiero/salita/', 'titolo' => 'Salita' ] );
$check( false !== strpos( $html, rawurlencode( 'http://example.test/sentiero/salita/' ) ), 'condividi con l\'URL del dettaglio' );
$check( false !== strpos( $html, 'Salita' ), 'condividi con il titolo del dettaglio' );

// 8. Luoghi: aree geografiche, comuni e provincia dalla tassonomia where
$luoghi = forestas_dettaglio_luoghi( [ 'taxonomies' => [ 'where' => [
	[ 'identifier' => '80', 'name' => [ 'it' => 'Area B (Centro-EST)' ] ],
	[ 'identifier' => '748', 'name' => [ 'en' => 'ZNUB5' ] ],
	[ 'identifier' => 'R1', 'name' => [ 'en' => 'Sardegna' ], 'admin_level' => 4 ],
	[ 'identifier' => 'R2', 'name' => [ 'it' => 'Ogliastra' ], 'admin_level' => 6 ],
	[ 'identifier' => 'R3', 'name' => [ 'en' => 'Talana' ], 'admin_level' => 8 ],
] ] ] );
$check( [ 'Area B (Centro-EST)' ] === $luoghi['aree'], 'aree geografiche' );
$check( [ 'Talana' ] === $luoghi['comuni'] && 'Ogliastra' === $luoghi['provincia'], 'comuni e provincia' );
$check( [ 'aree' => [], 'comuni' => [], 'provincia' => null ] === forestas_dettaglio_luoghi( [] ), 'luoghi vuoti' );
// Nello shard alcuni nomi inglesi sono l'identifier (R19621461): si usa un'altra lingua
$luoghi = forestas_dettaglio_luoghi( [ 'taxonomies' => [ 'where' => [ [ 'identifier' => 'R2', 'name' => [ 'en' => 'R2', 'it' => 'Ogliastra' ], 'admin_level' => 6 ] ] ] ] );
$check( 'Ogliastra' === ( function () { do_action( 'wpml_switch_language', 'en-us' ); $l = forestas_dettaglio_luoghi( [ 'taxonomies' => [ 'where' => [ [ 'identifier' => 'R2', 'name' => [ 'en' => 'R2', 'it' => 'Ogliastra' ], 'admin_level' => 6 ] ] ] ] ); do_action( 'wpml_switch_language', 'it' ); return $l['provincia']; } )(), 'nome uguale all\'identifier scartato' );

// 9. Difficoltà per attività e avvertenze
$p = [ 'taxonomies' => [
	'hiking-difficulty' => [ [ 'identifier' => 'x', 'name' => [ 'it' => 'EE' ] ] ],
	'mtb-difficulty'    => [ [ 'identifier' => 'y', 'name' => [ 'it' => 'MC' ] ] ],
	'warnings'          => [ [ 'identifier' => 'w', 'name' => [ 'it' => 'Cinghiali', 'en' => 'wild boars' ] ] ],
] ];
$diff = forestas_dettaglio_difficolta( $p );
$check( 2 === count( $diff ) && 'EE' === $diff[0][1], 'difficoltà per attività' );
$check( [ 'Cinghiali' ] === forestas_dettaglio_avvertenze( $p ), 'avvertenze' );
$check( [] === forestas_dettaglio_difficolta( [] ) && [] === forestas_dettaglio_avvertenze( [] ), 'niente difficoltà né avvertenze' );

// 10. Livello della validazione sulla scala 0-6 della legenda
$check( 4 === forestas_dettaglio_livello_validazione( 'validation-status-walkable' ), 'percorribile = 4' );
$check( 6 === forestas_dettaglio_livello_validazione( 'validation-status-certified' ), 'certificato = 6' );
$check( 0 === forestas_dettaglio_livello_validazione( 'sconosciuto' ), 'sconosciuto = 0' );

// 11. Galleria: immagine grande, miniatura, didascalia, autore, credits
$gal = forestas_dettaglio_galleria( [ 'image_gallery' => [ [ 'url' => 'http://x/a.jpg', 'thumbnail' => 'http://x/a-t.jpg', 'caption' => 'A', 'author' => null, 'credits' => 'C' ], [ 'caption' => 'senza url' ] ] ] );
$check( 1 === count( $gal ) && 'http://x/a-t.jpg' === $gal[0]['miniatura'] && 'C' === $gal[0]['credits'] && '' === $gal[0]['autore'], 'galleria' );

// 12. Nome senza il codice tra parentesi quando c'è già la bandierina
$check( 'Anello di Munduge' === forestas_dettaglio_nome( 'Anello di Munduge (B 532A)', 'B 532A' ), 'codice tolto dal nome' );
$check( 'Salita (vecchia)' === forestas_dettaglio_nome( 'Salita (vecchia)', 'B 1' ), 'parentesi diverse dal codice restano' );
$check( 'Nome' === forestas_dettaglio_nome( 'Nome', '' ), 'senza codice il nome resta' );

// 13. Punto di interesse: chiavi vecchie del forestas dei POI e luoghi da taxonomy_where
$poi_p = [
	'taxonomy'       => [ 'poi_type' => [ 'name' => [ 'it' => 'Nuraghi' ] ] ],
	'taxonomy_where' => [
		'82'     => [ 'it' => 'Area C (Sud-EST )', 'en' => 'Area C (Sud-EST )' ],
		'R39986' => [ 'en' => 'Goni', '_admin_level' => 8 ],
		'R276'   => [ 'en' => 'Cagliari', '_admin_level' => 6 ],
	],
	'forestas'       => [ 'come_arrivare' => [ 'it' => '<p>Dalla SS 125</p>' ], 'allegati' => [ 'https://x/guida%20pantaleo.pdf' ], 'video' => [ 'https://youtu.be/a' ] ],
];
$dati = forestas_dettaglio_poi( $poi_p );
$check( 'Nuraghi' === $dati['tipologia'], 'tipologia del POI' );
$check( '<p>Dalla SS 125</p>' === $dati['come_arrivare'], 'come arrivare' );
$check( [ [ 'url' => 'https://x/guida%20pantaleo.pdf', 'title' => 'guida pantaleo.pdf' ] ] === $dati['allegati'], 'allegati come elenco di URL' );
$check( 1 === count( $dati['video'] ), 'video' );
$check( [ 'Goni' ] === $dati['luoghi']['comuni'] && 'Cagliari' === $dati['luoghi']['provincia'] && [ 'Area C (Sud-EST )' ] === $dati['luoghi']['aree'], 'luoghi del POI' );
$vuoto = forestas_dettaglio_poi( [] );
$check( null === $vuoto['tipologia'] && '' === $vuoto['come_arrivare'] && [] === $vuoto['allegati'], 'POI vuoto' );

// 14. Titolo «in breve» con l'articolo giusto per tipo
$check( 'Il sentiero in breve' === forestas_dettaglio_titolo_breve( 'sentiero' ), 'il sentiero' );
$check( "L'itinerario in breve" === forestas_dettaglio_titolo_breve( 'itinerario' ), "l'itinerario" );
$check( 'Il cammino in breve' === forestas_dettaglio_titolo_breve( 'cammino' ), 'il cammino' );

// 15. «Credits foto» della testata anche con i dati dello shard (feature_image.author e credits)
$html = forestas_credits_immagine( [ 'autore' => 'ales&ales', 'credits' => 'Sardegna Turismo', 'chiave' => 'd185' ] );
$check( false !== strpos( $html, 'ales&amp;ales' ) && false !== strpos( $html, 'Sardegna Turismo' ) && false !== strpos( $html, 'forestas-credits-d185' ), 'credits dai dati dello shard' );
$check( '' === forestas_credits_immagine( [ 'autore' => '', 'credits' => null, 'chiave' => 'x' ] ), 'senza autore né credits niente pulsante' );

// 16. Dettaglio del POI nel formato nuovo dello shard (oc:8959): directions, percorsi vicini con la distanza dal POI
$nuovo = forestas_dettaglio_poi( [ 'forestas' => [
	'directions'          => [ 'it' => '<p>Da Santadi</p>' ],
	'nearby_tracks'       => [ [ 'id' => 334, 'name' => [ 'it' => 'Pixinamanna' ], 'distance_from_poi' => 49.1 ], [ 'id' => 335, 'name' => [ 'it' => 'Lontano' ], 'distance_from_poi' => 1530 ] ],
	'nearby_tracks_count' => 11,
] ] );
$check( '<p>Da Santadi</p>' === $nuovo['come_arrivare'], 'come arrivare da directions' );
$check( 2 === count( $nuovo['percorsi'] ) && 11 === $nuovo['percorsi_totale'], 'percorsi vicini e totale' );
$check( 'a 49 m' === $nuovo['percorsi'][0]['distanza'] && 'a 1,5 km' === $nuovo['percorsi'][1]['distanza'], 'distanza dal POI in m e km' );
$check( false !== strpos( forestas_dettaglio_card( $nuovo['percorsi'][0], 'percorso' ), 'a 49 m' ), 'la card mostra la distanza' );
// Card unica del sito (forestas-card, la .uc del wireframe), come quelle dei post e della ricerca: filetto nel colore del
// menu del tipo (percorso: sentiero, itinerario, cammino; POI: punti di interesse), titolo h3 con il link
$card = forestas_dettaglio_card( [ 'id' => 1, 'nome' => 'Monte', 'tipo' => 'Itinerario', 'tipo_id' => 'route-type-itinerary', 'immagine' => 'http://x/t.jpg', 'lunghezza' => '12,4 km', 'url' => 'http://x/itinerario/monte/' ], 'percorso' );
$check( false !== strpos( $card, '<li class="forestas-card forestas-card--immagine forestas-card--itinerario">' ), 'card del percorso con la classe del suo tipo: ' . $card );
$check( false !== strpos( $card, '<span class="forestas-card__tag">Itinerario</span>' ) && false !== strpos( $card, '<h3 class="forestas-card__titolo"><a href="http://x/itinerario/monte/">Monte</a></h3>' ) && false !== strpos( $card, '<p class="forestas-card__data">12,4 km</p>' ), 'etichetta, titolo con link e dati come la card dei post' );
$check( false !== strpos( $card, '<div class="forestas-card__media"><img src="http://x/t.jpg" alt="" loading="lazy" decoding="async"></div>' ), 'foto in alto' );
$poi_card = forestas_dettaglio_card( [ 'id' => 2, 'nome' => 'Nuraghe', 'tipo' => 'Nuraghi', 'url' => null ], 'poi' );
$check( false !== strpos( $poi_card, 'forestas-card--poi' ) && false !== strpos( $poi_card, 'forestas-card__media forestas-card__media--segnaposto' ) && false !== strpos( $poi_card, '<h3 class="forestas-card__titolo">Nuraghe</h3>' ), 'POI senza foto né link: segnaposto e titolo senza link' );
$check( false !== strpos( forestas_dettaglio_card( [ 'id' => 4, 'nome' => 'Biancone' ], 'poi' ), '<span class="forestas-card__tag">Punto di interesse</span>' ), 'POI senza tipologia: etichetta «Punto di interesse»' );
$check( false !== strpos( forestas_dettaglio_card( [ 'id' => 3, 'nome' => 'X', 'tipo_id' => null ], 'percorso' ), 'forestas-card--sentiero' ), 'percorso senza tipo: colore dei sentieri' );
$check( [] === forestas_dettaglio_poi( [] )['percorsi'] && 0 === forestas_dettaglio_poi( [] )['percorsi_totale'], 'POI senza percorsi vicini' );

// Mappa del dettaglio (Tavola 3 e Tavola 7 del wireframe): tracce, punti e legenda dal JSON
$p_mappa = [
	'ref'          => 'C 104 A',
	'related_pois' => [
		[ 'geometry' => [ 'type' => 'Point', 'coordinates' => [ 9.52, 39.87 ] ], 'properties' => [ 'id' => 401, 'name' => [ 'it' => 'Tavolo' ] ] ],
		[ 'geometry' => [ 'type' => 'Point', 'coordinates' => [ 9.53, 39.88 ] ], 'properties' => [ 'id' => 402, 'name' => [ 'it' => 'Incrocio' ] ] ],
		[ 'geometry' => null, 'properties' => [ 'id' => 403, 'name' => [ 'it' => 'Senza punto' ] ] ],
		[ 'geometry' => [ 'type' => 'Point', 'coordinates' => [ 9.54, 39.89 ] ], 'properties' => [ 'id' => 404, 'name' => [ 'it' => 'Panoramico' ] ] ],
		[ 'geometry' => [ 'type' => 'Point', 'coordinates' => [ 9.55, 39.9 ] ], 'properties' => [ 'id' => 405, 'name' => [ 'it' => 'Quarto' ] ] ],
	],
	'forestas'     => [
		'nearby_tracks' => [
			[ 'id' => 265, 'ref' => 'C 104', 'name' => [ 'it' => 'Monte' ], 'route_type' => [ 'identifier' => 'route-type-trail' ] ],
			[ 'id' => 318, 'ref' => null, 'name' => [ 'it' => 'Bosco' ], 'route_type' => [ 'identifier' => 'route-type-trail' ] ],
		],
	],
];
$mappa = forestas_dettaglio_mappa( $p_mappa, 'sentiero', 344 );
$check( [ 265, 318, 344 ] === array_column( $mappa['tracks'], 'id' ), 'mappa del sentiero: prima i collegati, poi la traccia della scheda' );
$check( true === ( $mappa['tracks'][0]['context'] ?? false ) && [ 7, 5 ] === ( $mappa['tracks'][0]['dash'] ?? [] ) && '#5B7A99' === ( $mappa['tracks'][0]['color'] ?? '' ), 'collegati tratteggiati e di sfondo' );
$check( '#DE1B0D' === ( $mappa['tracks'][2]['color'] ?? '' ) && 4 === ( $mappa['tracks'][2]['width'] ?? 0 ) && empty( $mappa['tracks'][2]['context'] ), 'traccia della scheda nel colore del sentiero' );
$check( [ '1', '2', '3' ] === array_column( $mappa['points'], 'number' ) && [ 9.54, 39.89 ] === ( $mappa['points'][2]['coordinates'] ?? null ), 'i primi tre POI con la posizione, numerati' );
$check( '#B7127F' === ( $mappa['points'][0]['color'] ?? '' ) && 'Tavolo' === ( $mappa['points'][0]['tooltip'] ?? '' ), 'POI magenta con il nome' );
$check(
	[
		[ 'tipo' => 'linea', 'colore' => '#DE1B0D', 'testo' => 'C 104 A · sentiero della scheda' ],
		[ 'tipo' => 'partenza', 'colore' => '#1F8F35', 'testo' => 'Partenza' ],
		[ 'tipo' => 'arrivo', 'colore' => '#1A1A1A', 'testo' => 'Arrivo' ],
		[ 'tipo' => 'tratteggio', 'colore' => '#5B7A99', 'testo' => 'Percorsi correlati: C 104, Bosco' ],
		[ 'tipo' => 'punto', 'colore' => '#B7127F', 'testo' => 'Tavolo', 'numero' => '1' ],
		[ 'tipo' => 'punto', 'colore' => '#B7127F', 'testo' => 'Incrocio', 'numero' => '2' ],
		[ 'tipo' => 'punto', 'colore' => '#B7127F', 'testo' => 'Panoramico', 'numero' => '3' ],
	] === $mappa['legenda'],
	'legenda del sentiero come la tavola'
);
// Partenza e arrivo sulla traccia della scheda (cerchio verde e bandiera a scacchi, come la mappa di GeoHub di Drupal)
$check( [ 'start' => 'Partenza', 'end' => 'Arrivo' ] === ( $mappa['tracks'][2]['ends'] ?? null ) && ! isset( $mappa['tracks'][0]['ends'] ), 'partenza e arrivo solo sulla traccia della scheda' );
$anello = forestas_dettaglio_mappa( [ 'ref' => null ], 'sentiero', 9, [ 'type' => 'LineString', 'coordinates' => [ [ 9, 40 ], [ 9.01, 40.01 ], [ 9.0002, 40.0002 ] ] ] );
$check( [ 'partenza' ] === array_values( array_intersect( array_column( $anello['legenda'], 'tipo' ), [ 'partenza', 'arrivo' ] ) ), 'anello: in legenda solo la partenza' );
$lineare = forestas_dettaglio_mappa( [ 'ref' => null ], 'sentiero', 9, [ 'type' => 'MultiLineString', 'coordinates' => [ [ [ 9, 40 ], [ 9.01, 40.01 ] ], [ [ 9.02, 40.02 ], [ 9.05, 40.05 ] ] ] ] );
$check( [ 'partenza', 'arrivo' ] === array_values( array_intersect( array_column( $lineare['legenda'], 'tipo' ), [ 'partenza', 'arrivo' ] ) ), 'percorso lineare: partenza e arrivo' );
$cammino = forestas_dettaglio_mappa( [ 'ref' => null ], 'cammino', 9 );
$check( [ 9 ] === array_column( $cammino['tracks'], 'id' ) && [] === $cammino['points'] && '#1A416F' === ( $cammino['tracks'][0]['color'] ?? '' ), 'cammino senza collegati né POI' );
$check( [ [ 'tipo' => 'linea', 'colore' => '#1A416F', 'testo' => 'Cammino della scheda' ], [ 'tipo' => 'partenza', 'colore' => '#1F8F35', 'testo' => 'Partenza' ], [ 'tipo' => 'arrivo', 'colore' => '#1A1A1A', 'testo' => 'Arrivo' ] ] === $cammino['legenda'], 'legenda senza codice' );

$poi_mappa = forestas_dettaglio_mappa(
	[
		'name'     => [ 'it' => 'Perda Liana' ],
		'forestas' => [
			'nearby_tracks' => [
				[ 'id' => 74, 'ref' => null, 'name' => [ 'it' => 'Itinerario' ], 'route_type' => [ 'identifier' => 'route-type-itinerary' ] ],
				[ 'id' => 183, 'ref' => 'C 1', 'name' => [ 'it' => 'Sentiero' ], 'route_type' => [ 'identifier' => 'route-type-trail' ] ],
			],
		],
	],
	'poi',
	1,
	[ 'type' => 'Point', 'coordinates' => [ 9.4, 39.9 ] ]
);
$check( [ 74, 183 ] === array_column( $poi_mappa['tracks'], 'id' ) && '#58810B' === ( $poi_mappa['tracks'][0]['color'] ?? '' ) && '#DE1B0D' === ( $poi_mappa['tracks'][1]['color'] ?? '' ), 'POI: percorsi vicini nel colore del loro tipo' );
$check( 1 === count( $poi_mappa['points'] ) && '#1A1A1A' === ( $poi_mappa['points'][0]['color'] ?? '' ) && ! isset( $poi_mappa['points'][0]['number'] ), 'POI: il punto nero, senza numero' );
$check( [ [ 'tipo' => 'linea', 'colore' => '#DE1B0D', 'testo' => 'Sentieri' ], [ 'tipo' => 'linea', 'colore' => '#58810B', 'testo' => 'Itinerari' ] ] === $poi_mappa['legenda'], 'POI: legenda dei tipi presenti, nell\'ordine sentieri, itinerari, cammini' );

// Mappa interattiva con il focus (oc:8969): il pulsante del dettaglio porta a /mappa-interattiva/?track=<id> o ?poi=<id>,
// e la pagina mette nell'iframe la webapp dello shard con l'elemento a fuoco (wmwp_webapp_url() del plugin)
$html_mappa = forestas_dettaglio_mappa_html( [ 'tracks' => [ [ 'id' => 344 ] ], 'points' => [], 'legenda' => [] ], 'Mappa', 300, 'track', 344 );
$check( (bool) preg_match( '#href="[^"]*mappa-interattiva/\?track=344"#', $html_mappa ), 'pulsante con ?track=<id>' );
$html_poi = forestas_dettaglio_mappa_html( [ 'tracks' => [], 'points' => [ [ 'coordinates' => [ 9, 40 ] ] ], 'legenda' => [] ], 'Mappa', 300, 'poi', 7 );
$check( (bool) preg_match( '#href="[^"]*mappa-interattiva/\?poi=7"#', $html_poi ), 'pulsante con ?poi=<id>' );
$check( wmwp_webapp_url( 'track', 312 ) === forestas_mappa_url( [ 'track' => '312' ] ), 'pagina della mappa con ?track: webapp con il focus' );
$check( wmwp_webapp_url( 'poi', 7 ) === forestas_mappa_url( [ 'poi' => '7' ] ), 'pagina della mappa con ?poi' );
$check( wmwp_webapp_url() === forestas_mappa_url( [ 'track' => 'x' ] ) && wmwp_webapp_url() === forestas_mappa_url( [] ), 'senza parametri validi: la mappa intera' );

restore_error_handler();
// Gruppi dei dati (Percorso, Misure, Enti…): titolo h3 che dettaglio.js trasforma in pulsante, tabella con il nome dal
// titolo; senza JavaScript tutto aperto. Lo stato iniziale sta in data-wm-gr
$gr = forestas_dettaglio_gruppo( 'wm-gr-0', 'Percorso & tappe', [ [ 'Partenza', 'Bivio <A>' ], [ 'Gestore', 'Dato non ancora disponibile', true ] ], false );
$check( false !== strpos( $gr, '<h3 class="wm-gr__h" id="wm-gr-0">Percorso &amp; tappe</h3>' ), 'titolo h3 del gruppo con l\'id' );
$check( false !== strpos( $gr, 'data-wm-gr="chiuso"' ) && false !== strpos( forestas_dettaglio_gruppo( 'x', 'Enti', [ [ 'a', 'b' ] ], true ), 'data-wm-gr="aperto"' ), 'stato iniziale chiuso o aperto' );
$check( false !== strpos( $gr, '<div class="wm-gr__p" id="wm-gr-0-p">' ) && false === strpos( $gr, ' hidden' ), 'pannello aperto senza JavaScript' );
$check( false !== strpos( $gr, '<table class="wm-dt" aria-labelledby="wm-gr-0">' ) && false === strpos( $gr, '<caption' ), 'tabella con il nome dal titolo, senza didascalia ripetuta' );
$check( false !== strpos( $gr, '<th scope="row">Partenza</th><td>Bivio &lt;A&gt;</td>' ), 'righe con i valori escapati' );
$check( false !== strpos( $gr, '<td class="wm-segnaposto">Dato non ancora disponibile</td>' ), 'segnaposto' );
$check( '' === forestas_dettaglio_gruppo( 'y', 'Vuoto', [], false ), 'gruppo senza righe: niente' );

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
