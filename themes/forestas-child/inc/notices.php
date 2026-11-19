<?php
/**
 * Pagina /avvisi (oc:9000): gli avvisi attivi dello shard (`api/v1/avvisi`, pubblica, senza token) sul sito.
 *
 * Il JSON si legge con un transient di 60 secondi (forestas_notices()); l'ultima risposta buona resta in un'opzione non
 * autoload, che serve quando lo shard non risponde 200, risponde un JSON non valido o con `data` che non è una lista.
 * Solo senza nessuna copia la pagina dice che gli avvisi non sono disponibili (503). Un elenco vuoto valido è «zero
 * avvisi», non un errore.
 *
 * Rotte `/avvisi/` e `/avvisi/page/<n>/` (`/en/avvisi/…` con WPML), 10 avvisi per pagina, ordinamento dal parametro GET
 * `ordine` (`meno-recenti` → dal meno recente, altrimenti dal più recente), conservato nei link della paginazione.
 * Stesso schema di inc/entes.php (rotta, stato, template, titolo, `hreflang`, selettore di lingua).
 *
 * @package forestas-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FORESTAS_NOTICES_VIEW_VAR   = 'forestas_notices';
const FORESTAS_NOTICES_TRANSIENT  = 'forestas_notices';
const FORESTAS_NOTICES_COPY       = 'forestas_notices_copy';
const FORESTAS_NOTICES_TTL        = 60;
const FORESTAS_NOTICES_PER_PAGE   = 10;
const FORESTAS_NOTICES_ORDER      = 'ordine';
const FORESTAS_NOTICES_ORDER_OLD  = 'meno-recenti';
const FORESTAS_NOTICES_ORDER_NEW  = 'piu-recenti';
const FORESTAS_NOTICES_RETRY_AFTER = 60;

/**
 * Gli avvisi dello shard: dal transient, altrimenti dallo shard, altrimenti dall'ultima copia buona.
 *
 * @return array{ok: bool, items: list<array{track_id: int, ref: string, name: array<string,string>, message: array<string,string>, published_at: ?string}>}
 */
function forestas_notices(): array {
	$cached = get_transient( FORESTAS_NOTICES_TRANSIENT );
	// Errore senza copia ricordato per 60 secondi: la pagina chiama questa funzione più volte (titolo, lingue,
	// template) e uno shard che non risponde non si richiama a ogni chiamata né a ogni visita (oc:9000)
	if ( is_array( $cached ) && false === ( $cached['ok'] ?? true ) ) {
		return [
			'ok'    => false,
			'items' => [],
		];
	}
	if ( is_array( $cached ) && isset( $cached['items'] ) && is_array( $cached['items'] ) ) {
		return [
			'ok'    => true,
			'items' => array_values( $cached['items'] ),
		];
	}
	$items = forestas_notices_fetch();
	if ( null !== $items ) {
		set_transient( FORESTAS_NOTICES_TRANSIENT, [ 'items' => $items ], FORESTAS_NOTICES_TTL );
		update_option( FORESTAS_NOTICES_COPY, $items, false );
		return [
			'ok'    => true,
			'items' => $items,
		];
	}
	$copy = get_option( FORESTAS_NOTICES_COPY, null );
	if ( is_array( $copy ) ) {
		// Anche la copia dura 60 secondi: uno shard fermo non si richiama a ogni visita
		set_transient( FORESTAS_NOTICES_TRANSIENT, [ 'items' => array_values( $copy ) ], FORESTAS_NOTICES_TTL );
		return [
			'ok'    => true,
			'items' => array_values( $copy ),
		];
	}
	set_transient( FORESTAS_NOTICES_TRANSIENT, [ 'ok' => false ], FORESTAS_NOTICES_RETRY_AFTER );
	return [
		'ok'    => false,
		'items' => [],
	];
}

/**
 * Chiede gli avvisi allo shard. Null se non risponde 200, il JSON non è valido o `data` non è una lista.
 *
 * @return list<array<string, mixed>>|null
 */
function forestas_notices_fetch(): ?array {
	if ( ! function_exists( 'wmwp_configured_api_urls' ) ) {
		return null;
	}
	$origin = wmwp_configured_api_urls()['origin'];
	if ( '' === $origin ) {
		return null;
	}
	$url = $origin . '/api/v1/avvisi';
	if ( function_exists( 'wmwp_server_url' ) ) {
		$url = wmwp_server_url( $url );
	}
	$response = wp_remote_get( $url, [ 'timeout' => 5, 'headers' => [ 'Accept' => 'application/json' ] ] );
	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}
	$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $body ) || ! isset( $body['data'] ) || ! is_array( $body['data'] ) || ! array_is_list( $body['data'] ) ) {
		return null;
	}
	return array_values( array_filter( $body['data'], 'is_array' ) );
}

/**
 * Avvisi ordinati per data di pubblicazione: `recent` (default) dal più recente, `oldest` dal meno recente. Quelli senza
 * data stanno sempre in fondo, nell'ordine ricevuto (usort è stabile).
 *
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function forestas_notices_sorted( array $items, string $order = 'recent' ): array {
	$newest = 'oldest' !== $order;
	$time   = static function ( array $item ): ?int {
		$value = $item['published_at'] ?? null;
		$time  = is_string( $value ) && '' !== trim( $value ) ? strtotime( $value ) : false;
		return false === $time ? null : $time;
	};
	usort(
		$items,
		function ( array $a, array $b ) use ( $time, $newest ): int {
			$time_a = $time( $a );
			$time_b = $time( $b );
			if ( null === $time_a || null === $time_b ) {
				return ( null === $time_a ) <=> ( null === $time_b );
			}
			return $newest ? $time_b <=> $time_a : $time_a <=> $time_b;
		}
	);
	return $items;
}

/** Regole di rewrite della pagina, in testa a quelle di WordPress. */
function forestas_notices_rewrite_rules(): array {
	$view = FORESTAS_NOTICES_VIEW_VAR;
	return [
		'^avvisi/?$'               => "index.php?{$view}=list",
		'^avvisi/page/([0-9]+)/?$' => "index.php?{$view}=list&paged=\$matches[1]",
	];
}
add_filter( 'rewrite_rules_array', fn( array $rules ): array => forestas_notices_rewrite_rules() + $rules );
add_filter( 'query_vars', fn( array $vars ): array => array_merge( $vars, [ FORESTAS_NOTICES_VIEW_VAR ] ) );

/** La richiesta è arrivata da una delle regole di /avvisi? */
function forestas_notices_rule_matched(): bool {
	$wp = $GLOBALS['wp'] ?? null;
	return $wp instanceof WP && is_string( $wp->matched_rule ) && array_key_exists( $wp->matched_rule, forestas_notices_rewrite_rules() );
}

/**
 * Query var della richiesta: quelle di /avvisi valgono solo dalle sue regole (la query var è pubblica e WordPress la
 * prenderebbe da qualunque indirizzo), e su quelle regole valgono solo vista e `paged` della regola, non le query var
 * pubbliche della query string. Come forestas_entes_request_vars().
 *
 * @param mixed $vars Query var della richiesta.
 * @return mixed
 */
function forestas_notices_request_vars( $vars ) {
	if ( ! is_array( $vars ) ) {
		return $vars;
	}
	unset( $vars[ FORESTAS_NOTICES_VIEW_VAR ] );
	if ( ! forestas_notices_rule_matched() ) {
		return $vars;
	}
	$wp = $GLOBALS['wp'];
	parse_str( (string) $wp->matched_query, $rule_vars );
	$vars = array_diff_key( $vars, array_flip( (array) $wp->public_query_vars ) );
	return array_merge( $vars, array_intersect_key( $rule_vars, array_flip( [ FORESTAS_NOTICES_VIEW_VAR, 'paged' ] ) ) );
}
add_filter( 'request', 'forestas_notices_request_vars' );

/** La query chiede la pagina degli avvisi? Solo la principale, da una regola di /avvisi, con la vista `list`. */
function forestas_notices_requested( WP_Query $query ): bool {
	return 'list' === $query->get( FORESTAS_NOTICES_VIEW_VAR ) && $query->is_main_query() && forestas_notices_rule_matched();
}

/** Ordinamento scelto (`recent` o `oldest`) dal parametro GET `ordine`. */
function forestas_notices_order(): string {
	$value = $_GET[ FORESTAS_NOTICES_ORDER ] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
	return FORESTAS_NOTICES_ORDER_OLD === $value ? 'oldest' : 'recent';
}

/**
 * La pagina degli avvisi della richiesta, null se la richiesta non è /avvisi. `status` 503 senza copia, 404 oltre
 * l'ultima pagina, altrimenti 200; `items` sono gli avvisi della pagina, già ordinati.
 *
 * @return array{status: int, ok: bool, items: list<array<string, mixed>>, page: int, pages: int, order: string, lang: string}|null
 */
function forestas_notices_page(): ?array {
	$query = $GLOBALS['wp_the_query'] ?? null;
	if ( ! $query instanceof WP_Query || ! forestas_notices_requested( $query ) ) {
		return null;
	}
	$data  = forestas_notices();
	$order = forestas_notices_order();
	$sorted = forestas_notices_sorted( $data['items'], $order );
	$page  = max( 1, (int) $query->get( 'paged' ) );
	$pages = max( 1, (int) ceil( count( $sorted ) / FORESTAS_NOTICES_PER_PAGE ) );
	return [
		'status' => ! $data['ok'] ? 503 : ( $page <= $pages ? 200 : 404 ),
		'ok'     => $data['ok'],
		'items'  => array_slice( $sorted, ( $page - 1 ) * FORESTAS_NOTICES_PER_PAGE, FORESTAS_NOTICES_PER_PAGE ),
		'page'   => $page,
		'pages'  => $pages,
		'order'  => $order,
		'lang'   => forestas_dettaglio_lingua(),
	];
}

/** Indirizzo di una pagina dell'elenco nella lingua (`/avvisi/`, `/avvisi/page/2/`, `/en/avvisi/…`), con l'ordinamento. */
function forestas_notices_list_url( string $lang, int $page = 1, string $order = 'recent' ): string {
	$url = untrailingslashit( (string) get_option( 'home' ) ) . '/avvisi/' . ( $page > 1 ? 'page/' . $page . '/' : '' );
	$url = (string) apply_filters( 'wpml_permalink', $url, forestas_entes_wpml_code( $lang ) );
	return 'oldest' === $order ? add_query_arg( FORESTAS_NOTICES_ORDER, FORESTAS_NOTICES_ORDER_OLD, $url ) : $url;
}

/** Titolo della pagina. */
function forestas_notices_title(): string {
	return forestas_testo( 'Avvisi sui sentieri', 'Trail notices' );
}

// Come per gli enti: niente query dei post e stato deciso qui, non dal 404 di WordPress
add_action(
	'parse_query',
	function ( WP_Query $query ): void {
		if ( forestas_notices_requested( $query ) ) {
			$query->is_home = false;
		}
	}
);
add_filter( 'posts_pre_query', fn( $posts, WP_Query $query ) => forestas_notices_requested( $query ) ? [] : $posts, 10, 2 );
add_filter( 'pre_handle_404', fn( $handled, WP_Query $query ) => forestas_notices_requested( $query ) ? true : $handled, 10, 2 );

/** Stato della pagina: 200, 404 oltre l'ultima pagina, 503 con `Retry-After` senza nessuna copia degli avvisi. */
function forestas_notices_template_redirect(): void {
	global $wp_query;
	$page = forestas_notices_page();
	if ( null === $page ) {
		return;
	}
	if ( 404 === $page['status'] ) {
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}
	$wp_query->is_404 = false;
	status_header( $page['status'] );
	if ( 503 === $page['status'] ) {
		header( 'Retry-After: ' . FORESTAS_NOTICES_RETRY_AFTER );
		nocache_headers();
	}
}
add_action( 'template_redirect', 'forestas_notices_template_redirect', 1 );

/** Template della pagina (anche per il 503, che mostra il suo messaggio). Un 404 resta a WordPress. */
function forestas_notices_template( string $template ): string {
	$page = forestas_notices_page();
	if ( null === $page || 404 === $page['status'] ) {
		return $template;
	}
	$file = locate_template( 'templates/notices-list.php' );
	return '' !== $file ? $file : $template;
}
add_filter( 'template_include', 'forestas_notices_template', 100 );

// Titolo del documento
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$page = forestas_notices_page();
		if ( null === $page || 404 === $page['status'] ) {
			return $title;
		}
		$parts = [ forestas_notices_title(), $page['page'] > 1 ? sprintf( forestas_testo( 'Pagina %d', 'Page %d' ), $page['page'] ) : '', get_bloginfo( 'name', 'display' ) ];
		return esc_html( implode( ' ' . apply_filters( 'document_title_separator', '-' ) . ' ', array_filter( $parts ) ) );
	},
	20
);

/** `hreflang` verso la stessa pagina in ogni lingua, più `x-default` (con wmwp_detail_hreflangs() del plugin). */
function forestas_notices_head_hreflang(): void {
	$page = forestas_notices_page();
	if ( null === $page || 200 !== $page['status'] || ! function_exists( 'wmwp_detail_hreflangs' ) ) {
		return;
	}
	$active = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
	$urls   = [];
	foreach ( is_array( $active ) ? array_keys( $active ) : [] as $code ) {
		$urls[ (string) $code ] = forestas_notices_list_url( (string) $code, $page['page'], $page['order'] );
	}
	foreach ( wmwp_detail_hreflangs( [ 'urls' => $urls ] ) as $tag => $url ) {
		printf( '<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr( $tag ), esc_url( $url ) );
	}
}
add_action( 'wp_head', 'forestas_notices_head_hreflang', 2 );

/** Selettore di lingua di WPML: la stessa pagina degli avvisi nell'altra lingua (come forestas_entes_language_switcher()). */
function forestas_notices_language_switcher( $languages ) {
	static $busy = false;
	if ( ! is_array( $languages ) || $busy ) {
		return $languages;
	}
	$page = forestas_notices_page();
	if ( null === $page || 200 !== $page['status'] ) {
		return $languages;
	}
	// La prima lettura delle lingue va fatta prima di accendere $busy (vedi forestas_entes_language_switcher())
	forestas_entes_wpml_code( $page['lang'] );
	$busy = true;
	foreach ( $languages as $code => $language ) {
		if ( is_array( $language ) ) {
			$languages[ $code ]['url'] = forestas_notices_list_url( (string) $code, $page['page'], $page['order'] );
		}
	}
	$busy = false;
	return $languages;
}
add_filter( 'icl_ls_languages', 'forestas_notices_language_switcher' );

/**
 * Un avviso dell'elenco: etichetta «Avviso» e data come nel dettaglio (forestas_notice_tag_html(),
 * forestas_detail_time_html()), codice e nome del sentiero, testo. Il nome è un link solo se il percorso è nell'indice
 * dei dettagli del plugin (indirizzo della pagina nella lingua): un `track_id` assente, 0 o di un percorso che l'indice
 * non ha porterebbe a un 404.
 *
 * @param array<string, mixed> $item Avviso dell'API `api/v1/avvisi`.
 */
function forestas_notices_item_html( array $item, string $lang ): string {
	$id      = (int) ( $item['track_id'] ?? 0 );
	$name    = forestas_dettaglio_testo_lingua( $item['name'] ?? null, $lang );
	$message = forestas_dettaglio_testo_lingua( $item['message'] ?? null, $lang );
	$link    = $id > 0 && function_exists( 'wmwp_detail_url' ) ? wmwp_detail_url( 'track', $id, forestas_entes_wpml_code( $lang ) ) : '';
	$ref     = trim( (string) ( $item['ref'] ?? '' ) );
	$label   = ( '' !== $ref ? esc_html( $ref ) . forestas_separatore_html() : '' ) . esc_html( trim( $name['testo'] ) );
	$date    = forestas_detail_time_html( is_string( $item['published_at'] ?? null ) ? $item['published_at'] : null );

	$html = '<li class="av-it">' . forestas_notice_tag_html() . '<div>';
	$html .= '' !== $link
		? '<a href="' . esc_url( $link ) . '" class="av-t"' . forestas_dettaglio_lang( $name['lingua'] ) . '>' . $label . '</a>'
		: '<span class="av-t"' . forestas_dettaglio_lang( $name['lingua'] ) . '>' . $label . '</span>';
	if ( '' !== $message['testo'] ) {
		$html .= '<p' . forestas_dettaglio_lang( $message['lingua'] ) . '>' . esc_html( $message['testo'] ) . '</p>';
	}
	if ( '' !== $date ) {
		$html .= '<span class="av-d">' . esc_html( forestas_testo( 'aggiornato il', 'updated on' ) ) . ' ' . $date . '</span>';
	}
	return $html . '</div></li>';
}

/**
 * HTML della pagina degli avvisi (dentro `main`): titolo, ordinamento, elenco, paginazione o messaggio.
 *
 * @param array{ok: bool, items: list<array<string, mixed>>, page: int, pages: int, order: string, lang: string} $page
 */
function forestas_notices_html( array $page ): string {
	$lang     = (string) $page['lang'];
	$html     = '<div class="av-page">';
	$html    .= '<h1 class="av-h">' . esc_html( forestas_notices_title() ) . '</h1>';
	$html    .= '<p class="av-int">' . esc_html( forestas_testo( 'Chiusure, interruzioni e lavori in corso sui sentieri.', 'Closures, interruptions and works in progress on the trails.' ) ) . '</p>';

	if ( ! $page['ok'] ) {
		return $html . '<p class="av-vuoto" data-test="notices-list">' . esc_html( forestas_testo( 'Gli avvisi non sono disponibili in questo momento. Riprova più tardi.', 'Notices are not available right now. Please try again later.' ) ) . '</p></div>';
	}
	if ( ! $page['items'] && 1 === (int) $page['pages'] ) {
		return $html . '<p class="av-vuoto" data-test="notices-list">' . esc_html( forestas_testo( 'Al momento non ci sono avvisi attivi.', 'There are no active notices at the moment.' ) ) . '</p></div>';
	}

	// Ordinamento: un form GET con il suo pulsante, la pagina non cambia al solo cambio della select (WCAG 3.2.2)
	$current = $page['order'];
	$html   .= '<form class="av-tools" method="get" action="' . esc_url( forestas_notices_list_url( $lang ) ) . '">';
	$html   .= '<label for="av-ord">' . esc_html( forestas_testo( 'Ordina per data', 'Sort by date' ) ) . '</label>';
	$html   .= '<select id="av-ord" name="' . FORESTAS_NOTICES_ORDER . '">';
	$html   .= '<option value="' . FORESTAS_NOTICES_ORDER_NEW . '"' . selected( 'recent', $current, false ) . '>' . esc_html( forestas_testo( 'Più recenti prima', 'Newest first' ) ) . '</option>';
	$html   .= '<option value="' . FORESTAS_NOTICES_ORDER_OLD . '"' . selected( 'oldest', $current, false ) . '>' . esc_html( forestas_testo( 'Meno recenti prima', 'Oldest first' ) ) . '</option>';
	$html   .= '</select>';
	$html   .= '<button type="submit" class="av-ord-btn">' . esc_html( forestas_testo( 'Ordina', 'Sort' ) ) . '</button></form>';

	$html .= '<ol class="av-list" data-test="notices-list">';
	foreach ( $page['items'] as $item ) {
		$html .= forestas_notices_item_html( $item, $lang );
	}
	$html .= '</ol>';

	if ( $page['pages'] > 1 ) {
		// Come le griglie del child e l'elenco degli enti: pagina corrente con aria-current, frecce con un nome. La
		// paginazione della ricerca unica è disegnata dal browser (pulsanti, search.js), non è riproducibile qui
		$links = paginate_links(
			[
				'base'      => forestas_notices_list_url( $lang ) . '%_%',
				'format'    => 'page/%#%/',
				'add_args'  => 'oldest' === $current ? [ FORESTAS_NOTICES_ORDER => FORESTAS_NOTICES_ORDER_OLD ] : [],
				'total'     => (int) $page['pages'],
				'current'   => (int) $page['page'],
				'prev_text' => '<span aria-hidden="true">‹</span><span class="screen-reader-text">' . esc_html( forestas_testo( 'Pagina precedente', 'Previous page' ) ) . '</span>',
				'next_text' => '<span aria-hidden="true">›</span><span class="screen-reader-text">' . esc_html( forestas_testo( 'Pagina successiva', 'Next page' ) ) . '</span>',
			]
		);
		$html .= '<nav class="forestas-paginazione" aria-label="' . esc_attr( forestas_testo( 'Paginazione', 'Pagination' ) ) . '">' . $links . '</nav>';
	}
	return $html . '</div>';
}
