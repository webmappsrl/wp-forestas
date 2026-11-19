<?php
/**
 * Test (oc:9000): «Filtri attivi» sopra le liste filtrabili (forestas_filtri_attivi_html(), forestas_griglia() e
 * l'elenco degli enti), come nella ricerca unica: una voce per ogni filtro scelto, link che lo toglie (con il nome
 * del gruppo per lo screen reader), «Togli tutti i filtri»; niente senza filtri scelti.
 * Si lancia con: wp eval-file wp-content/themes/forestas-child/tests/filtri-attivi.php
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

if ( ! function_exists( 'forestas_filtri_attivi_html' ) ) {
	echo "FAIL: forestas_filtri_attivi_html non esiste\n";
	exit( 1 );
}

// 1. Senza filtri scelti, niente
$check( '' === forestas_filtri_attivi_html( [], '/eventi/' ), 'nessun filtro scelto: niente' );

// 2. Markup: titolo, una voce per filtro con il link che lo toglie, «Togli tutti i filtri»
$html = forestas_filtri_attivi_html(
	[
		[ 'gruppo' => 'Tipologia', 'nome' => 'eventi', 'url' => '/eventi/?anno=2026' ],
		[ 'gruppo' => 'Anno', 'nome' => '2026', 'url' => '/eventi/?tag=eventi' ],
	],
	'/eventi/'
);
$check( false !== strpos( $html, '<div class="forestas-filtri-attivi">' ) && false !== strpos( $html, '<span class="forestas-filtri-attivi__titolo">Filtri attivi:</span>' ), 'titolo «Filtri attivi:»: ' . $html );
$check( false !== strpos( $html, '<a class="forestas-chip" href="/eventi/?anno=2026" aria-label="Togli il filtro Tipologia: eventi">eventi<span class="forestas-chip__x" aria-hidden="true">✕</span></a>' ), 'voce con il link che toglie il filtro' );
$check( 2 === substr_count( $html, 'class="forestas-chip"' ), 'una voce per filtro' );
$check( false !== strpos( $html, '<a class="forestas-filtri-attivi__tutti" href="/eventi/">Togli tutti i filtri</a>' ), '«Togli tutti i filtri»' );

// 3. Lista degli eventi con il filtro dell'indirizzo: le voci sopra la lista, dentro la colonna della lista
$griglia = [
	'tipo'     => 'evento',
	'quanti'   => 8,
	'paginata' => true,
	'variante' => 'evento',
	'filtri'   => [
		[ 'campo' => 'tag', 'titolo' => [ 'it' => 'Tipologia', 'en' => 'Type' ] ],
		[ 'campo' => 'anno', 'titolo' => [ 'it' => 'Anno', 'en' => 'Year' ] ],
	],
];
$tag  = get_terms( [ 'taxonomy' => 'post_tag', 'number' => 1, 'hide_empty' => true, 'fields' => 'slugs' ] );
$tag  = is_array( $tag ) ? (string) ( $tag[0] ?? '' ) : '';
$prima = [ $_GET, $_SERVER['REQUEST_URI'] ?? null ];
try {
	$_GET = [];
	$_SERVER['REQUEST_URI'] = '/eventi/';
	$check( false === strpos( forestas_griglia( $griglia ), 'forestas-filtri-attivi' ), 'lista senza filtri: niente «Filtri attivi»' );
	if ( '' !== $tag ) {
		$_GET = [ 'tag' => $tag ];
		$_SERVER['REQUEST_URI'] = '/eventi/?tag=' . $tag;
		$lista = forestas_griglia( $griglia );
		$check( false !== strpos( $lista, '<div class="forestas-filtrabile__lista"><div class="forestas-filtri-attivi">' ), 'lista filtrata: «Filtri attivi» in cima alla lista' );
		$check( false !== strpos( $lista, 'href="/eventi/" aria-label="Togli il filtro Tipologia: ' ), 'lista filtrata: la voce toglie il filtro dall\'indirizzo' );
	} else {
		$check( false, 'nessun tag con contenuti per la prova' );
	}
} finally {
	$_GET = $prima[0];
	if ( null === $prima[1] ) {
		unset( $_SERVER['REQUEST_URI'] );
	} else {
		$_SERVER['REQUEST_URI'] = $prima[1];
	}
}

// 4. Elenco degli enti: il template mostra le voci del filtro «Argomento»
$template = (string) file_get_contents( get_stylesheet_directory() . '/templates/entes-list.php' );
$check( false !== strpos( $template, 'forestas_filtri_attivi_html(' ), 'elenco degli enti: «Filtri attivi»' );

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
