<?php
/**
 * Riscrittura dei link interni dei testi importati da Drupal (oc:8758). Un link al sito Drupal diventa
 * il link relativo al contenuto WordPress che porta lo stesso nodo nella stessa lingua (_drupal_nid e
 * _drupal_langcode, di qualunque tipo di post). Se quel contenuto non c'è ancora (per esempio un «da
 * sapere», importato da un altro ticket), il link resta verso Drupal ed è «in attesa»: una
 * sincronizzazione successiva lo sistema da sola. Un link a una pagina di Drupal che non è un contenuto
 * (un modulo come /form/segnalazione, un file) resta verso Drupal e non si sistema da solo: va rifatto
 * quando quella pagina esisterà in WordPress. Lo stesso vale per un vecchio indirizzo di un nodo che su
 * Drupal funziona solo grazie a un redirect (tabella redirect, che non si legge): oggi nei testi non ce
 * ne sono.
 */

// Link al sito Drupal, con o senza www e index.php/: gruppo 1 il percorso, gruppo 2 query e ancora
const WPF_LINK_DRUPAL = '~https?://(?:www\.)?sardegnasentieri\.it(?:/index\.php)?(/[^"\'\s?#]*)?([?#][^"\'\s]*)?~i';

/**
 * Riscrive i link nel valore degli href di $html.
 *
 * @param array    $alias da wpf_drupal_alias(): '/percorso' => [ 'nid', 'lingua' ]
 * @param callable $trova fn( int $nid, string $lingua ): ?string, il link relativo del contenuto
 *                        WordPress (anche di una pagina che la sincronizzazione sta per creare)
 * @return array [ 'html' => string, 'riscritti' => string[], 'in_attesa' => string[], 'non_contenuti' => string[] ]
 */
function wpf_link_riscrivi( string $html, array $alias, callable $trova, string $lingua = 'it' ): array {
	$riscritti     = [];
	$in_attesa     = [];
	$non_contenuti = [];
	$html          = preg_replace_callback(
		'#(href\s*=\s*)(["\'])(.*?)\2#is',
		function ( $m ) use ( $alias, $trova, $lingua, &$riscritti, &$in_attesa, &$non_contenuti ) {
			$url = html_entity_decode( $m[3] );
			if ( ! preg_match( WPF_LINK_DRUPAL, $url, $p ) || $p[0] !== trim( $url ) ) {
				return $m[0];
			}
			$nuovo = wpf_link_destinazione( rawurldecode( $p[1] ?? '' ), $alias, $trova, $lingua );
			if ( $nuovo === false ) {
				$non_contenuti[] = $url;
				return $m[0];
			}
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
	return [ 'html' => $html, 'riscritti' => $riscritti, 'in_attesa' => $in_attesa, 'non_contenuti' => $non_contenuti ];
}

/**
 * Link relativo WordPress per un percorso di Drupal: null se il contenuto non c'è ancora, false se il
 * percorso non è un contenuto di Drupal. La home senza lingua, in una pagina inglese, è quella inglese.
 *
 * @return string|null|false
 */
function wpf_link_destinazione( string $percorso, array $alias, callable $trova, string $lingua = 'it' ) {
	$percorso = '/' . trim( $percorso, '/' );
	if ( $percorso === '/' || $percorso === '/en' ) {
		return $percorso === '/en' || $lingua === 'en' ? '/en/' : '/';
	}
	$nodo = wpf_link_nodo( $percorso, $alias );
	if ( ! $nodo ) {
		return false;
	}
	return $trova( $nodo['nid'], $nodo['lingua'] );
}

/**
 * Il nodo Drupal di un percorso, [ 'nid', 'lingua' ], o null se il percorso non è un contenuto. Un alias
 * «und» vale per tutte le lingue: «/en/<alias>» è la versione inglese dello stesso nodo. Come Drupal,
 * che cerca l'alias nel database con un confronto che ignora accenti e maiuscole, un link a
 * «/da-sapere/cosè-…» trova l'alias «/da-sapere/cose-…».
 */
function wpf_link_nodo( string $percorso, array $alias ): ?array {
	if ( preg_match( '#^(?:/(en))?/node/(\d+)$#', $percorso, $n ) ) {
		return [ 'nid' => (int) $n[2], 'lingua' => $n[1] ?: 'it' ];
	}
	static $simili = [];
	$chiave = md5( (string) count( $alias ) . array_key_first( $alias ) );
	if ( ! isset( $simili[ $chiave ] ) ) {
		$simili[ $chiave ] = [];
		foreach ( $alias as $a => $nodo ) {
			$simili[ $chiave ][ wpf_link_normalizza( $a ) ] ??= $nodo;
		}
	}
	$trova = fn( string $p ) => $alias[ $p ] ?? $simili[ $chiave ][ wpf_link_normalizza( $p ) ] ?? null;
	if ( $nodo = $trova( $percorso ) ) {
		return $nodo;
	}
	if ( str_starts_with( $percorso, '/en/' ) && ( $nodo = $trova( substr( $percorso, 3 ) ) ) ) {
		return [ 'nid' => $nodo['nid'], 'lingua' => 'en' ];
	}
	return null;
}

/** Percorso senza accenti e in minuscolo, per confrontare gli alias come fa il database di Drupal. */
function wpf_link_normalizza( string $percorso ): string {
	return strtolower( remove_accents( $percorso ) );
}
