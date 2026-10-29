<?php
/**
 * I tipi del giro di import dei contenuti con un nodo (oc:8760), nell'ordine del giro: pagine.php li
 * percorre dopo le pagine statiche. Il descrittore è documentato in import-lib.php; un tipo nuovo ha il suo
 * *-lib.php, si aggiunge qui e, se ha un indirizzo suo, nel child (inc/contenuti.php).
 */

require_once __DIR__ . '/news-lib.php';
require_once __DIR__ . '/eventi-lib.php';
require_once __DIR__ . '/da-sapere-lib.php';

// Chiavi che ogni descrittore deve avere ('prefisso', se manca, si ricava dal tipo registrato)
const WPF_IMPORT_CHIAVI = [
	'origine', 'riga', 'nome', 'plurale', 'questo', 'genere', 'post_type', 'tassonomie', 'prefisso', 'schede', 'pagina',
	'soglia_bozze', 'scarto_copia', 'conteggio', 'bundle', 'nodo', 'termini', 'scrivi', 'template',
];

/**
 * I tipi importati, nell'ordine del giro, completi del prefisso. Un descrittore senza una chiave è un
 * errore di programmazione: si ferma subito, non a metà del giro.
 */
function wpf_import_tipi(): array {
	$tipi = [ wpf_news_tipo(), wpf_eventi_tipo(), wpf_da_sapere_tipo() ];
	foreach ( $tipi as &$tipo ) {
		// Lo slug del rewrite del child: null se il tipo non è registrato, e pagine.php allora si ferma
		$tipo['prefisso'] ??= get_post_type_object( $tipo['post_type'] )?->rewrite['slug'] ?? null;
		$mancano = array_diff( WPF_IMPORT_CHIAVI, array_keys( $tipo ) );
		$genere  = array_diff( WPF_IMPORT_GENERE, array_keys( (array) ( $tipo['genere'] ?? [] ) ) );
		if ( $mancano || $genere ) {
			throw new LogicException( 'descrittore del tipo ' . ( $tipo['origine'] ?? '?' ) . ' incompleto: ' . implode( ', ', [ ...$mancano, ...array_map( fn( $g ) => "genere.{$g}", $genere ) ] ) );
		}
	}
	return $tipi;
}
