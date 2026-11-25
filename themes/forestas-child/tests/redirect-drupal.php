<?php
/**
 * Test (oc:9018): indirizzi vecchi di Drupal dei dettagli. Il child dà al plugin (filtro `wmwp_detail_legacy_paths`)
 * il percorso di `forestas.url` di percorsi e POI, in italiano e in inglese (su Drupal la pagina inglese ha lo stesso
 * alias con `/en/` davanti); solo indirizzi di sardegnasentieri.it e solo `<base>/<slug>` (anche `node/<nid>`). Il plugin
 * li mette nello storico e li porta con un 301 alla pagina nuova.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/redirect-drupal.php
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

$paths = fn( string $type, array $props ) => apply_filters( 'wmwp_detail_legacy_paths', [], $type, $props );

// 1. Percorso con l'alias codificato: italiano e inglese, stesso percorso
$track = $paths( 'track', [ 'forestas' => [ 'url' => 'https://www.sardegnasentieri.it/sentieri/s%E2%80%99araighinos-t-511b' ] ] );
$check( [ [ 'lang' => 'it', 'path' => 'sentieri/s%E2%80%99araighinos-t-511b' ], [ 'lang' => 'en', 'path' => 'sentieri/s%E2%80%99araighinos-t-511b' ] ] === $track, 'percorso: italiano e inglese (' . wp_json_encode( $track ) . ')' );

// 2. POI di pois.geojson e nodo senza alias
$check( 'da-vedere/nuraghe-losa' === ( $paths( 'poi', [ 'forestas' => [ 'url' => 'https://www.sardegnasentieri.it/da-vedere/nuraghe-losa' ] ] )[0]['path'] ?? '' ), 'POI da /da-vedere/' );
$check( 'node/4811' === ( $paths( 'track', [ 'forestas' => [ 'url' => 'https://www.sardegnasentieri.it/node/4811' ] ] )[0]['path'] ?? '' ), 'nodo senza alias: node/<nid>' );

// 3. Niente indirizzi: url vuoto, di un altro sito, non `<base>/<slug>`
$check( [] === $paths( 'track', [] ), 'senza forestas.url: niente' );
$check( [] === $paths( 'track', [ 'forestas' => [ 'url' => '' ] ] ), 'url vuoto: niente' );
$check( [] === $paths( 'track', [ 'forestas' => [ 'url' => 'https://www.altro-sito.example/sentieri/x' ] ] ), 'altro sito: niente' );
$check( [] === $paths( 'track', [ 'forestas' => [ 'url' => 'https://www.sardegnasentieri.it/' ] ] ), 'home: niente' );
$check( [] === $paths( 'track', [ 'forestas' => [ 'url' => 'https://www.sardegnasentieri.it/a/b/c' ] ] ), 'tre segmenti: niente' );

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
