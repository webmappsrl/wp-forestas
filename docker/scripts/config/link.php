<?php
/**
 * Riscrittura dei link interni dei testi importati da Drupal (oc:8758). Un link al sito Drupal diventa
 * il link relativo al contenuto WordPress che porta lo stesso nodo nella stessa lingua (_drupal_nid e
 * _drupal_langcode, di qualunque tipo di post). Se quel contenuto non c'è ancora (per esempio un «da
 * sapere», importato da un altro ticket), il link resta verso Drupal ed è «in attesa»: una
 * sincronizzazione successiva lo sistema da sola.
 */

// Link al sito Drupal, con o senza www e index.php/: gruppo 1 il percorso, gruppo 2 query e ancora
const WPF_LINK_DRUPAL = '~https?://(?:www\.)?sardegnasentieri\.it(?:/index\.php)?(/[^"\'\s?#]*)?([?#][^"\'\s]*)?~i';

/**
 * Riscrive i link nel valore degli href di $html.
 *
 * @param array    $alias da wpf_drupal_alias(): '/percorso' => [ 'nid', 'lingua' ]
 * @param callable $trova fn( int $nid, string $lingua ): ?string, il link relativo del contenuto
 *                        WordPress (anche di una pagina che la sincronizzazione sta per creare)
 * @return array [ 'html' => string, 'riscritti' => string[], 'in_attesa' => string[] ]
 */
function wpf_link_riscrivi( string $html, array $alias, callable $trova, string $lingua = 'it' ): array {
	$riscritti = [];
	$in_attesa = [];
	$html      = preg_replace_callback(
		'#(href\s*=\s*)(["\'])(.*?)\2#is',
		function ( $m ) use ( $alias, $trova, $lingua, &$riscritti, &$in_attesa ) {
			$url = html_entity_decode( $m[3] );
			if ( ! preg_match( WPF_LINK_DRUPAL, $url, $p ) || $p[0] !== trim( $url ) ) {
				return $m[0];
			}
			$nuovo = wpf_link_destinazione( rawurldecode( $p[1] ?? '' ), $alias, $trova, $lingua );
			if ( $nuovo === null ) {
				$in_attesa[] = $url;
				return $m[0];
			}
			$nuovo      .= $p[2] ?? '';
			$riscritti[] = "{$url} → {$nuovo}";
			return $m[1] . $m[2] . esc_attr( $nuovo ) . $m[2];
		},
		$html
	);
	return [ 'html' => $html, 'riscritti' => $riscritti, 'in_attesa' => $in_attesa ];
}

/**
 * Link relativo WordPress per un percorso di Drupal, o null se il contenuto non c'è ancora. La home senza
 * lingua, in una pagina inglese, è quella inglese.
 */
function wpf_link_destinazione( string $percorso, array $alias, callable $trova, string $lingua = 'it' ): ?string {
	$percorso = '/' . trim( $percorso, '/' );
	if ( $percorso === '/' || $percorso === '/en' ) {
		return $percorso === '/en' || $lingua === 'en' ? '/en/' : '/';
	}
	if ( preg_match( '#^(?:/(en))?/node/(\d+)$#', $percorso, $n ) ) {
		$nodo = [ 'nid' => (int) $n[2], 'lingua' => $n[1] ?: 'it' ];
	} else {
		$nodo = $alias[ $percorso ] ?? null;
	}
	if ( ! $nodo ) {
		return null;
	}
	return $trova( $nodo['nid'], $nodo['lingua'] );
}
