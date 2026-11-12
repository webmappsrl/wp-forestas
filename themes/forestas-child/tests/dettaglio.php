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

// 2. Testi nella lingua con ripiego; le liste di blocchi si uniscono
$check( "<p>Primo tratto</p>\n<p>Secondo tratto</p>" === forestas_dettaglio_testo( $completa['feature']['properties']['forestas']['roadbook'], 'it' ), 'roadbook italiano' );
$check( '<p>First</p>' === forestas_dettaglio_testo( $completa['feature']['properties']['forestas']['roadbook'], 'en' ), 'roadbook inglese' );
$check( 'Solo' === forestas_dettaglio_testo( [ 'it' => 'Solo', 'en' => ' ' ], 'en' ), 'inglese vuoto → italiano' );
$check( '' === forestas_dettaglio_testo( null, 'it' ), 'niente → stringa vuota' );

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
$check( 'A1' === ( $ind['accessibilita']['classe'] ?? '' ), 'accessibilità dalla classe di inclusività' );
$ind = forestas_dettaglio_indicatori( $vuota['feature']['properties'] );
$check( null === $ind['validazione'] && null === $ind['accessibilita'], 'indicatori vuoti' );

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
$tabelle = forestas_dettaglio_tabelle( $completa['feature']['properties'], 'cammino' );
$check( 1 === count( $tabelle ), 'cammino: una tabella' );

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
$check( [] === forestas_dettaglio_poi( [] )['percorsi'] && 0 === forestas_dettaglio_poi( [] )['percorsi_totale'], 'POI senza percorsi vicini' );

restore_error_handler();
if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
