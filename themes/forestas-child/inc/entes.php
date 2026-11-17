<?php
/**
 * Enti dello shard sul sito (oc:8990): copia del JSON degli enti e dati che ne leggono le pagine (card dell'elenco,
 * dettaglio, «Sentieri gestiti»).
 *
 * È l'unico punto del child che parla con l'API degli enti dello shard (`api/v1/entes`, oc:8965 di forestas). L'API sta
 * dietro il token del sito: `WP_API_TOKEN` nell'ambiente del container (il `.env` di wp-forestas, lo stesso valore del
 * `.env` dello shard), mandato solo dal server come `Authorization: Bearer`, mai nel browser né nei log.
 *
 * Il JSON si copia in un'opzione non autoload, con le sole chiavi che il child legge (forestas_entes_slim()), e si
 * rinnova ogni 15 minuti con un evento WP-Cron, fuori dalle richieste dei visitatori. La copia si riscrive solo quando i
 * dati cambiano; l'ora dell'ultimo rinnovo sta in un'opzione piccola a parte (forestas_entes_state()), e gli slug degli
 * enti in un'altra (forestas_entes_known_slugs()), scritta insieme alla copia e riallineata a ogni rinnovo. Le pagine
 * degli enti leggono sempre la copia (forestas_entes_all()): solo se una copia non c'è mai stata (sito appena
 * installato), o se da più di un'ora non è partito nessun rinnovo (WP-Cron fermo, o nessuna visita), la pagina la chiede
 * subito. Gira un rinnovo alla volta, da WP-Cron o da una pagina: il lucchetto (forestas_entes_lock()) lo prende uno
 * solo, e una visita che lo trova preso legge la copia che c'è. Dopo un fallimento senza copia (un token mancante) le
 * pagine aspettano 5 minuti prima di chiamare di nuovo lo shard.
 * I dettagli di percorsi e POI e la ricerca con un testo leggono solo gli slug (forestas_entes_known_slugs()), senza
 * deserializzare la copia; la ricerca a testo vuoto elenca gli enti della copia. Nessuno dei due chiama lo shard per la
 * copia. La copia si legge dal database una volta per richiesta (forestas_entes_copy()).
 *
 * Un rinnovo fallito (shard spento, 401/403/429, risposta non valida) o non plausibile lascia l'ultima copia buona e
 * finisce nel log con il prefisso «forestas enti:». Non plausibile è una lista vuota, o con enti o collegamenti
 * ente-traccia sotto la metà della copia precedente: succede su UAT durante l'import dopo il reset notturno, quando
 * `enteables` è vuota o parziale. Un calo sotto la metà che dura più di 3 ore dal primo rifiuto si accetta
 * (FORESTAS_ENTES_DROP_GRACE); per accettarlo subito: `wp option delete forestas_entes_copy`, poi
 * `wp cron event run forestas_entes_refresh`.
 *
 * In locale WP-Cron non parte (il loopback sulla porta 8090 non risponde): il rinnovo si lancia a mano con
 * `wp cron event run forestas_entes_refresh`, o lo fa la prima pagina degli enti dopo un'ora.
 *
 * Le pagine degli enti sono pagine dinamiche del child, senza post: `/enti/`, `/enti/page/<n>/` e `/enti/<slug>/` (e
 * `/en/…`, il prefisso lo toglie WPML prima delle regole) hanno regole di rewrite proprie verso le query var
 * `forestas_entes` e `forestas_ente_slug`; il filtro «Argomento» dell'elenco sta nella query string
 * (`?argomento=<tipo>`, FORESTAS_ENTES_TYPE_PARAM). La rotta (forestas_entes_route()) decide 200, 404 o 503 dalla
 * copia; i template sono `templates/entes-list.php` e `templates/ente-detail.php`, il 503 è la pagina «non
 * disponibile» del plugin, come per i dettagli di percorsi e POI. Titolo del documento, `hreflang` e selettore di
 * lingua di WPML portano alla stessa pagina nell'altra lingua (con lo stesso filtro), come fa il plugin per i
 * dettagli. Chi cambia le regole alza FORESTAS_REGOLE_VERSIONE (inc/news.php), così si rigenerano.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FORESTAS_ENTES_OPTION       = 'forestas_entes_copy';    // copia: [ data ], solo le chiavi lette dal child, non autoload
const FORESTAS_ENTES_SLUGS_OPTION = 'forestas_entes_slugs';   // slug degli enti della copia (slug → true), non autoload
const FORESTAS_ENTES_STATE_OPTION = 'forestas_entes_state';   // stato dei rinnovi: generated_at, fetched_at, checked_at, refused_since
const FORESTAS_ENTES_RETRY        = 'forestas_entes_retry';   // transient: attesa dopo un fallimento senza copia
const FORESTAS_ENTES_LOCK_OPTION  = 'forestas_entes_refresh_lock'; // lucchetto del rinnovo: l'istante in cui è stato preso, non autoload
const FORESTAS_ENTES_LOCK_STALE   = 120;                      // secondi dopo cui il lucchetto è di un rinnovo interrotto, e si riprende
const FORESTAS_ENTES_CRON_WAIT    = 120;                      // secondi in cui una pagina lascia il rinnovo scaduto a WP-Cron appena partito
const FORESTAS_ENTES_REFRESH_HOOK = 'forestas_entes_refresh'; // evento WP-Cron del rinnovo
const FORESTAS_ENTES_SCHEDULE     = 'forestas_quarter_hour';  // intervallo WP-Cron del rinnovo
const FORESTAS_ENTES_INTERVAL     = 900;                      // secondi fra due rinnovi
const FORESTAS_ENTES_RETRY_AFTER  = 300;                      // secondi di attesa dopo un fallimento senza copia
const FORESTAS_ENTES_STALE_AFTER  = 3600;                     // secondi senza rinnovi dopo cui una pagina degli enti rinnova da sé (WP-Cron fermo)
const FORESTAS_ENTES_DROP_GRACE   = 10800;                    // secondi dal primo rifiuto dopo cui un calo sotto la metà si accetta
const FORESTAS_ENTES_TIMEOUT      = 20;                       // secondi di attesa della risposta dello shard
const FORESTAS_ENTES_PER_PAGE     = 9;                        // enti per pagina dell'elenco, come la view `enti` di Drupal
const FORESTAS_ENTES_TYPE_PARAM   = 'argomento';              // parametro del filtro «Argomento» dell'elenco: ?argomento=<tipo>
const FORESTAS_ENTES_VIEW_VAR     = 'forestas_entes';         // query var della vista: list o detail
const FORESTAS_ENTES_SLUG_VAR     = 'forestas_ente_slug';     // query var dello slug del dettaglio
const FORESTAS_ENTES_CARD_IMAGE   = [ 'thumbnail', '400x200', 'url' ]; // misure dell'immagine delle card, in ordine di preferenza
// Chiavi dell'ente e delle sue tracce con il ruolo `gestore` che il child legge (elenco, dettaglio, «Sentieri gestiti»,
// plausibilità): solo queste entrano nella copia (forestas_entes_slim()). Chi legge una chiave nuova la aggiunge qui
const FORESTAS_ENTES_KEYS         = [ 'id', 'slug', 'name', 'type', 'description', 'contacts', 'website', 'related_url', 'feature_image', 'image_gallery', 'tracks', 'created_at', 'updated_at' ];
const FORESTAS_ENTES_TRACK_KEYS   = [ 'id', 'ref', 'name', 'route_type', 'feature_image', 'distance', 'duration_forward', 'roles', 'created_at' ];

/** Token del sito per le API dello shard, dall'ambiente del container; '' se manca. Non va mai stampato né scritto nei log. */
function forestas_entes_token(): string {
	$token = getenv( 'WP_API_TOKEN' );
	return is_string( $token ) ? trim( $token ) : '';
}

/**
 * Il token può viaggiare verso questo indirizzo? Sì in https, o in http verso un host che non esce dalla macchina:
 * `localhost`, `127.0.0.1`, `::1`, `host.docker.internal` o il nome di un servizio Docker (senza punti, es.
 * `php-forestas`). Un http verso un dominio vero lo manderebbe in chiaro in rete.
 */
function forestas_entes_token_allowed( string $url ): bool {
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( 'https' === $scheme ) {
		return true;
	}
	$host = strtolower( trim( (string) wp_parse_url( $url, PHP_URL_HOST ), '[]' ) );
	if ( 'http' !== $scheme || '' === $host ) {
		return false;
	}
	return in_array( $host, [ 'localhost', '127.0.0.1', '::1', 'host.docker.internal' ], true )
		|| ( ! str_contains( $host, '.' ) && ! str_contains( $host, ':' ) );
}

/**
 * Motivo di una risposta dello shard non riuscita, per il log: il messaggio in `error` se c'è (richiesta non partita o
 * errore di rete), altrimenti quello del codice HTTP. Lo usano il rinnovo della copia e la ricerca.
 */
function forestas_entes_http_reason( int $code, string $error = '' ): string {
	if ( '' !== $error ) {
		return $error;
	}
	return match ( true ) {
		0 === $code   => 'shard non raggiungibile',
		401 === $code => 'token rifiutato: WP_API_TOKEN diverso da quello dello shard',
		403 === $code => 'lo shard non ha WP_API_TOKEN configurato',
		429 === $code => 'troppe richieste allo shard: il suo limite al minuto vale per tutto il sito, rinnovo e ricerca insieme',
		503 === $code => 'shard non disponibile o indice degli enti assente',
		$code >= 500  => 'errore dello shard',
		default       => 'risposta inattesa dello shard',
	};
}

/**
 * GET su un'API dello shard dietro il token del sito: l'unico punto che costruisce l'indirizzo (origine dello shard
 * configurata nel plugin, vista dal server con wmwp_server_url()) e aggiunge l'intestazione del token. Senza token,
 * senza origine o con un indirizzo dove il token viaggerebbe in chiaro (forestas_entes_token_allowed()) non chiama lo
 * shard e risponde con il codice 0 e il motivo in `error`, che chi chiama scrive nel log. Un errore di rete ha il codice
 * 0 e in `error` il messaggio, dopo «shard non raggiungibile:».
 *
 * @param string                $path    Percorso dopo l'origine, es. `api/v1/entes`.
 * @param array<string, scalar> $query   Parametri della query string, codificati qui.
 * @param int                   $timeout Secondi di attesa della risposta.
 * @return array{code: int, data: array<mixed>|null, response: array<string, mixed>|null, error: string} La risposta di wmwp_http_json().
 */
function forestas_entes_api_get( string $path, array $query = [], int $timeout = FORESTAS_ENTES_TIMEOUT ): array {
	$not_sent = fn( string $reason ) => [
		'code'     => 0,
		'data'     => null,
		'response' => null,
		'error'    => $reason,
	];
	$token = forestas_entes_token();
	if ( '' === $token ) {
		return $not_sent( 'WP_API_TOKEN mancante nell\'ambiente del container' );
	}
	if ( ! function_exists( 'wmwp_http_json' ) || ! function_exists( 'wmwp_configured_api_urls' ) ) {
		return $not_sent( 'plugin wp-package non attivo' );
	}
	$origin = wmwp_configured_api_urls()['origin'];
	if ( '' === $origin ) {
		return $not_sent( 'origine dello shard non configurata nel plugin wp-package' );
	}
	$url = $origin . '/' . ltrim( $path, '/' );
	if ( $query ) {
		$url .= '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
	}
	$url = wmwp_server_url( $url );
	if ( ! forestas_entes_token_allowed( $url ) ) {
		return $not_sent( sprintf( 'token non mandato: lo shard è in http su un host non locale (%s), serve https', (string) wp_parse_url( $url, PHP_URL_HOST ) ) );
	}
	// Accept JSON: senza, un errore di validazione di Laravel risponde con un redirect invece che con il codice
	$headers = [
		'Authorization' => 'Bearer ' . $token,
		'Accept'        => 'application/json',
	];
	// `error` c'è solo nel plugin dall'oc:8990: con uno più vecchio vale la stringa vuota
	$result = wmwp_http_json( $url, $timeout, $headers ) + [ 'error' => '' ];
	if ( 0 === (int) $result['code'] && '' !== $result['error'] ) {
		$result['error'] = 'shard non raggiungibile: ' . $result['error'];
	}
	return $result;
}

/**
 * Il JSON degli enti dallo shard: `[ 'generated_at' => ?string, 'data' => list<array> ]`. WP_Error senza token (la
 * richiesta non parte: forestas_entes_api_get()), con un codice diverso da 200 (nel messaggio e in
 * `get_error_data()['code']`) o con `data` che non è una lista di enti. Il messaggio non contiene mai il token.
 *
 * @return array{generated_at: ?string, data: list<array<string, mixed>>}|WP_Error
 */
function forestas_entes_fetch(): array|WP_Error {
	$result = forestas_entes_api_get( 'api/v1/entes' );
	$code   = (int) $result['code'];
	if ( 200 !== $code ) {
		return new WP_Error( 'forestas_entes_http', sprintf( 'codice %d (%s)', $code, forestas_entes_http_reason( $code, (string) $result['error'] ) ), [ 'code' => $code ] );
	}
	$data = is_array( $result['data'] ) ? ( $result['data']['data'] ?? null ) : null;
	if ( ! is_array( $data ) || ! array_is_list( $data ) || count( array_filter( $data, 'is_array' ) ) !== count( $data ) ) {
		return new WP_Error( 'forestas_entes_invalid', 'codice 200, ma la risposta non ha la lista degli enti in `data`', [ 'code' => 200 ] );
	}
	$generated_at = $result['data']['generated_at'] ?? null;
	return [
		'generated_at' => is_string( $generated_at ) ? $generated_at : null,
		'data'         => $data,
	];
}

/**
 * Numero di enti e di collegamenti ente-traccia (le tracce di ogni ente) di una lista.
 *
 * @param list<array<string, mixed>> $entes Enti nella forma di `api/v1/entes`.
 * @return array{0: int, 1: int}
 */
function forestas_entes_counts( array $entes ): array {
	$links = 0;
	foreach ( $entes as $ente ) {
		$links += is_array( $ente['tracks'] ?? null ) ? count( $ente['tracks'] ) : 0;
	}
	return [ count( $entes ), $links ];
}

/**
 * Una lista nuova può sostituire la copia? No se è vuota, o se enti o collegamenti ente-traccia scendono sotto la metà
 * della copia precedente (import in corso dopo il reset notturno di UAT). Senza copia precedente basta che non sia vuota.
 * Il calo sotto la metà che dura più di FORESTAS_ENTES_DROP_GRACE lo accetta forestas_entes_refresh().
 *
 * @param list<array<string, mixed>>      $new Enti della risposta.
 * @param list<array<string, mixed>>|null $old Enti della copia salvata, null se non c'è.
 */
function forestas_entes_is_plausible( array $new, ?array $old ): bool {
	if ( [] === $new ) {
		return false;
	}
	if ( null === $old ) {
		return true;
	}
	[ $new_entes, $new_links ] = forestas_entes_counts( $new );
	[ $old_entes, $old_links ] = forestas_entes_counts( $old );
	return $new_entes * 2 >= $old_entes && $new_links * 2 >= $old_links;
}

/**
 * La copia salvata, null se non c'è o non ha la lista degli enti. Si legge e si decodifica una volta per richiesta: le
 * pagine degli enti la chiedono più volte, e get_option() la deserializza a ogni chiamata. La copia della richiesta si
 * dimentica quando l'opzione si scrive o si cancella (forestas_entes_copy_changed()), e con lei il rinnovo già provato
 * dalla richiesta (forestas_entes_refresh_tried()).
 *
 * @param bool $forget true per dimenticare la copia della richiesta (risponde null).
 * @return array{data: list<array<string, mixed>>}|null
 */
function forestas_entes_copy( bool $forget = false ): ?array {
	static $memo = null;
	static $read = false;
	if ( $forget ) {
		$memo = null;
		$read = false;
		forestas_entes_refresh_tried( false );
		return null;
	}
	if ( ! $read ) {
		$copy = get_option( FORESTAS_ENTES_OPTION, null );
		$memo = is_array( $copy ) && isset( $copy['data'] ) && is_array( $copy['data'] ) ? $copy : null;
		$read = true;
	}
	return $memo;
}

/**
 * Insieme degli slug di una lista di enti (slug → true), senza gli enti senza slug.
 *
 * @param list<array<string, mixed>> $entes Enti nella forma di `api/v1/entes`.
 * @return array<string, true>
 */
function forestas_entes_slug_set( array $entes ): array {
	$set = [];
	foreach ( $entes as $ente ) {
		$slug = forestas_entes_slug( $ente );
		if ( '' !== $slug ) {
			$set[ $slug ] = true;
		}
	}
	return $set;
}

/**
 * La copia è cambiata (scritta dal rinnovo, da un test, da `wp option`) o è stata cancellata: si dimentica la copia della
 * richiesta e si riscrive l'opzione piccola degli slug, che così segue sempre la copia; con la copia cancellata si
 * cancella anche quella, e i link agli enti spariscono finché una copia non torna.
 *
 * @param mixed $value Valore nuovo dell'opzione della copia, null se è stata cancellata.
 */
function forestas_entes_copy_changed( $value ): void {
	forestas_entes_copy( true );
	if ( null === $value ) {
		delete_option( FORESTAS_ENTES_SLUGS_OPTION );
		return;
	}
	update_option( FORESTAS_ENTES_SLUGS_OPTION, forestas_entes_slug_set( is_array( $value['data'] ?? null ) ? $value['data'] : [] ), false );
}
add_action( 'add_option_' . FORESTAS_ENTES_OPTION, fn( $option, $value ) => forestas_entes_copy_changed( $value ), 10, 2 );
add_action( 'update_option_' . FORESTAS_ENTES_OPTION, fn( $old, $value ) => forestas_entes_copy_changed( $value ), 10, 2 );
add_action( 'delete_option_' . FORESTAS_ENTES_OPTION, fn() => forestas_entes_copy_changed( null ) );

/**
 * Slug degli enti della copia (slug → true): hanno una pagina `/enti/<slug>/`. Li leggono i link dei dettagli di percorsi
 * e POI e la ricerca con un testo, dall'opzione piccola degli slug, senza deserializzare la copia; senza l'opzione (copia
 * scritta prima che l'opzione esistesse, opzione cancellata a mano) gli slug vengono dalla copia, finché il rinnovo
 * successivo non la riscrive (forestas_entes_refresh()). [] senza copia. Non chiama mai lo shard.
 *
 * @return array<string, true>
 */
function forestas_entes_known_slugs(): array {
	$slugs = get_option( FORESTAS_ENTES_SLUGS_OPTION, null );
	return is_array( $slugs ) ? $slugs : forestas_entes_slug_set( forestas_entes_cached() );
}

/**
 * Stato dei rinnovi, dall'opzione piccola: `generated_at` dello shard e `fetched_at` (UTC) dell'ultimo rinnovo riuscito,
 * `checked_at` (UTC) dell'ultimo rinnovo partito, riuscito o no, `refused_since` (istante Unix) del primo rifiuto di
 * un calo sotto la metà ancora in corso e `left_to_cron` (istante Unix) della prima visita che ha lasciato a WP-Cron il
 * rinnovo scaduto (forestas_entes_leave_to_cron()). [] se non c'è.
 *
 * @return array{generated_at?: ?string, fetched_at?: string, checked_at?: string, refused_since?: int, left_to_cron?: int}
 */
function forestas_entes_state(): array {
	$state = get_option( FORESTAS_ENTES_STATE_OPTION, [] );
	return is_array( $state ) ? $state : [];
}

/** Scrive lo stato dei rinnovi (forestas_entes_state()). */
function forestas_entes_save_state( array $state ): void {
	update_option( FORESTAS_ENTES_STATE_OPTION, $state, false );
}

/**
 * Istante dell'ultimo rinnovo partito (`checked_at` dello stato, o `fetched_at`, o quello di una copia scritta prima
 * dello stato); null se non si sa.
 */
function forestas_entes_last_check(): ?int {
	$state = forestas_entes_state();
	foreach ( [ $state['checked_at'] ?? null, $state['fetched_at'] ?? null, forestas_entes_copy()['fetched_at'] ?? null ] as $value ) {
		if ( is_int( $value ) || ( is_string( $value ) && ctype_digit( $value ) ) ) {
			return (int) $value;
		}
		$time = forestas_entes_time( $value );
		if ( null !== $time ) {
			return $time;
		}
	}
	return null;
}

/**
 * La copia con le sole chiavi che il child legge (FORESTAS_ENTES_KEYS, FORESTAS_ENTES_TRACK_KEYS): fuori `pois`,
 * `geometry` e gli altri campi dell'API. Le immagini tengono indirizzo, miniatura, didascalia, autore e credits, e delle
 * misure solo quelle usate: `400x200` (card e galleria) e `1440x500` (testata del dettaglio); le tracce con il ruolo
 * `gestore` i campi della card dei «Sentieri gestiti» con la sola miniatura, le altre solo id e ruoli, che bastano al
 * conto dei collegamenti. Senza, la copia dei 93 enti del dev pesava circa 2,1 MB, metà nelle misure delle foto delle
 * tracce.
 *
 * @param list<array<string, mixed>> $entes Enti nella forma di `api/v1/entes`.
 * @return list<array<string, mixed>>
 */
function forestas_entes_slim( array $entes ): array {
	$pick  = fn( array $item, array $keys ): array => array_intersect_key( $item, array_flip( $keys ) );
	$image = function ( $image, array $keys, array $sizes = [] ) use ( $pick ) {
		if ( ! is_array( $image ) ) {
			return $image;
		}
		$slim = $pick( $image, $keys );
		if ( $sizes && is_array( $image['sizes'] ?? null ) ) {
			$slim['sizes'] = $pick( $image['sizes'], $sizes );
		}
		return $slim;
	};
	$photo = [ 'url', 'thumbnail', 'caption', 'author', 'credits' ];
	$track = function ( $track ) use ( $pick, $image ) {
		if ( ! is_array( $track ) ) {
			return $track;
		}
		// Una traccia senza il ruolo `gestore` conta solo per i collegamenti ente-traccia (forestas_entes_counts())
		if ( ! in_array( 'gestore', (array) ( $track['roles'] ?? [] ), true ) ) {
			return $pick( $track, [ 'id', 'roles' ] );
		}
		$track = $pick( $track, FORESTAS_ENTES_TRACK_KEYS );
		if ( is_array( $track['route_type'] ?? null ) ) {
			$track['route_type'] = $pick( $track['route_type'], [ 'name', 'identifier' ] );
		}
		if ( array_key_exists( 'feature_image', $track ) ) {
			$track['feature_image'] = $image( $track['feature_image'], [ 'thumbnail' ] );
		}
		return $track;
	};
	$slim = [];
	foreach ( $entes as $ente ) {
		$ente = $pick( $ente, FORESTAS_ENTES_KEYS );
		if ( array_key_exists( 'feature_image', $ente ) ) {
			$ente['feature_image'] = $image( $ente['feature_image'], $photo, [ '400x200', '1440x500' ] );
		}
		if ( is_array( $ente['image_gallery'] ?? null ) ) {
			$ente['image_gallery'] = array_map( fn( $foto ) => $image( $foto, $photo, [ '400x200' ] ), $ente['image_gallery'] );
		}
		if ( is_array( $ente['tracks'] ?? null ) ) {
			$ente['tracks'] = array_map( $track, $ente['tracks'] );
		}
		$slim[] = $ente;
	}
	return $slim;
}

/**
 * Slug che più enti di una lista hanno (lo slug nello shard può mancare e non è unico), ciascuno una volta, in ordine
 * alfabetico: pagina e link portano al primo ente con quello slug (forestas_entes_by_slug()).
 *
 * @param list<array<string, mixed>> $entes Enti nella forma di `api/v1/entes`.
 * @return list<string>
 */
function forestas_entes_duplicate_slugs( array $entes ): array {
	$counts = array_count_values( array_filter( array_map( 'forestas_entes_slug', $entes ), 'strlen' ) );
	$dups   = array_map( 'strval', array_keys( array_filter( $counts, fn( int $n ) => $n > 1 ) ) );
	sort( $dups, SORT_STRING );
	return $dups;
}

/**
 * Prende il lucchetto del rinnovo, che può avere un processo solo: il valore (l'istante della presa) da passare a
 * forestas_entes_unlock(), null se un altro rinnovo lo tiene. Una sola istruzione SQL atomica sulla chiave unica di
 * `option_name`: inserisce la riga se non c'è, la riprende se ha più di FORESTAS_ENTES_LOCK_STALE secondi (un rinnovo
 * interrotto a metà, che non l'ha tolta), altrimenti la lascia com'è e non tocca nessuna riga. add_option() non basta:
 * prima legge e poi scrive con `ON DUPLICATE KEY UPDATE`, e due processi che leggono insieme la riga assente la scrivono
 * tutti e due con successo. La riga non passa da get_option(): si legge solo qui.
 */
function forestas_entes_lock(): ?string {
	global $wpdb;
	$now   = time();
	$value = (string) $now;
	// Righe toccate: 1 inserita, 2 ripresa perché abbandonata, 0 tenuta da un altro rinnovo
	$rows = $wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off') ON DUPLICATE KEY UPDATE option_value = IF( option_value < %d, VALUES( option_value ), option_value )",
			FORESTAS_ENTES_LOCK_OPTION,
			$value,
			$now - FORESTAS_ENTES_LOCK_STALE
		)
	);
	wp_cache_delete( FORESTAS_ENTES_LOCK_OPTION, 'options' );
	if ( false === $rows ) {
		error_log( 'forestas enti: lucchetto del rinnovo non scritto, errore del database: ' . $wpdb->last_error );
	}
	return is_int( $rows ) && $rows > 0 ? $value : null;
}

/**
 * Lascia il lucchetto del rinnovo, solo se è ancora quello preso con forestas_entes_lock(): un lucchetto ripreso da un
 * altro processo (dopo FORESTAS_ENTES_LOCK_STALE secondi) resta suo.
 */
function forestas_entes_unlock( string $value ): void {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", FORESTAS_ENTES_LOCK_OPTION, $value ) );
	wp_cache_delete( FORESTAS_ENTES_LOCK_OPTION, 'options' );
}

/**
 * Rinnova la copia dallo shard (forestas_entes_refresh_now()), un processo alla volta: l'evento WP-Cron, una pagina
 * degli enti (forestas_entes_all()) e `wp cron event run` passano tutti dal lucchetto (forestas_entes_lock()). Se un
 * altro rinnovo lo tiene non chiama lo shard e restituisce false, con una riga di log se `$log_busy` (non per le
 * visite, che leggono la copia che c'è).
 *
 * @param bool $log_busy Una riga di log quando il rinnovo si salta perché ne gira un altro.
 */
function forestas_entes_refresh( bool $log_busy = true ): bool {
	$lock = forestas_entes_lock();
	if ( null === $lock ) {
		if ( $log_busy ) {
			error_log( sprintf( 'forestas enti: rinnovo saltato, ne gira già un altro (lucchetto %s, abbandonato dopo %d secondi)', FORESTAS_ENTES_LOCK_OPTION, FORESTAS_ENTES_LOCK_STALE ) );
		}
		return false;
	}
	try {
		return forestas_entes_refresh_now();
	} finally {
		forestas_entes_unlock( $lock );
	}
}

/**
 * Il rinnovo, con il lucchetto già preso (forestas_entes_refresh()): legge, toglie le chiavi che il child non usa
 * (forestas_entes_slim()), controlla che sia plausibile e salva la copia, solo se i dati sono cambiati; lo stato dei
 * rinnovi (forestas_entes_state()) si scrive sempre, e l'opzione degli slug si riallinea anche quando la copia non
 * cambia. Su errore resta la copia di prima, il motivo va nel log con il codice ricevuto (mai il token) e, se una copia
 * non c'è, parte l'attesa di FORESTAS_ENTES_RETRY_AFTER secondi prima che le pagine riprovino.
 *
 * Un calo sotto la metà si rifiuta a ogni rinnovo, con una riga di log, finché non dura più di FORESTAS_ENTES_DROP_GRACE
 * dal primo rifiuto (`refused_since`): allora si accetta, con una riga di log; una lista vuota non si accetta mai. Una
 * copia nuova (dati cambiati) con tipi di ente sconosciuti (forestas_entes_unknown_types()) o slug doppi
 * (forestas_entes_duplicate_slugs()) dà una riga di log per ciascuno dei due casi.
 */
function forestas_entes_refresh_now(): bool {
	$old   = forestas_entes_copy();
	$state = forestas_entes_state();
	// Il rinnovo parte: una visita che lo aveva lasciato a WP-Cron non aspetta più (forestas_entes_leave_to_cron())
	unset( $state['left_to_cron'] );
	$fetched = forestas_entes_fetch();
	// L'ora del rinnovo partito, riuscito o no: dice che WP-Cron gira (forestas_entes_overdue())
	$state['checked_at'] = gmdate( 'c' );
	if ( is_wp_error( $fetched ) ) {
		forestas_entes_save_state( $state );
		return forestas_entes_refresh_failed( 'rinnovo non riuscito, ' . $fetched->get_error_message(), $old );
	}
	$data = forestas_entes_slim( $fetched['data'] );
	if ( ! forestas_entes_is_plausible( $data, $old['data'] ?? null ) ) {
		[ $new_entes, $new_links ] = forestas_entes_counts( $data );
		[ $old_entes, $old_links ] = forestas_entes_counts( $old['data'] ?? [] );
		$counts = sprintf( '%d enti e %d collegamenti ente-traccia contro %d e %d della copia precedente', $new_entes, $new_links, $old_entes, $old_links );
		$since  = (int) ( $state['refused_since'] ?? 0 );
		if ( [] === $data || 0 === $since || time() - $since <= FORESTAS_ENTES_DROP_GRACE ) {
			$reason = 'copia nuova scartata, non plausibile: ' . $counts;
			if ( [] !== $data ) {
				$state['refused_since'] = $since > 0 ? $since : time();
				$reason                .= sprintf( '; rifiutata dal %s, si accetta se il calo dura più di %d ore', gmdate( 'c', $state['refused_since'] ), FORESTAS_ENTES_DROP_GRACE / HOUR_IN_SECONDS );
			}
			forestas_entes_save_state( $state );
			return forestas_entes_refresh_failed( $reason, $old );
		}
		error_log( sprintf( 'forestas enti: calo accettato, dura da più di %d ore (primo rifiuto il %s): %s', FORESTAS_ENTES_DROP_GRACE / HOUR_IN_SECONDS, gmdate( 'c', $since ), $counts ) );
	}
	$changed = null === $old || $old['data'] !== $data;
	if ( $changed ) {
		update_option( FORESTAS_ENTES_OPTION, [ 'data' => $data ], false );
		// Una copia non salvata (database che rifiuta la scrittura) senza attesa farebbe chiamare lo shard a ogni visita
		if ( ( forestas_entes_copy()['data'] ?? null ) !== $data ) {
			forestas_entes_save_state( $state );
			return forestas_entes_refresh_failed( 'copia nuova non salvata nel database', $old );
		}
	} else {
		// Gli hook della copia riscrivono gli slug solo quando la copia si scrive: un'opzione degli slug cancellata o
		// scritta a mano resterebbe com'è fino ai dati nuovi, e intanto dettagli e ricerca deserializzerebbero la copia
		$slugs = forestas_entes_slug_set( $data );
		if ( get_option( FORESTAS_ENTES_SLUGS_OPTION, null ) !== $slugs ) {
			update_option( FORESTAS_ENTES_SLUGS_OPTION, $slugs, false );
		}
	}
	unset( $state['refused_since'] );
	$state['generated_at'] = $fetched['generated_at'];
	$state['fetched_at']   = $state['checked_at'];
	forestas_entes_save_state( $state );
	delete_transient( FORESTAS_ENTES_RETRY );
	if ( $changed ) {
		// Una volta per copia nuova, non a ogni visita: un tipo che il child non conosce (aggiunto nello shard) non si
		// mostra, e uno slug doppio porta sempre al primo ente
		$unknown = forestas_entes_unknown_types( $data );
		if ( $unknown ) {
			error_log( sprintf( 'forestas enti: tipi di ente sconosciuti nella copia nuova, non si mostrano (forestas_entes_type_labels()): «%s»', implode( '», «', $unknown ) ) );
		}
		$duplicates = forestas_entes_duplicate_slugs( $data );
		if ( $duplicates ) {
			error_log( sprintf( 'forestas enti: slug doppi nella copia nuova, pagina e link portano al primo ente con lo slug: «%s»', implode( '», «', $duplicates ) ) );
		}
	}
	return true;
}

/** Chiusura di un rinnovo non riuscito: attesa se una copia non c'è, una riga nel log. Restituisce sempre false. */
function forestas_entes_refresh_failed( string $reason, ?array $old ): bool {
	if ( null === $old ) {
		set_transient( FORESTAS_ENTES_RETRY, time(), FORESTAS_ENTES_RETRY_AFTER );
		$reason .= sprintf( '; nessuna copia: le pagine riprovano fra %d minuti', FORESTAS_ENTES_RETRY_AFTER / MINUTE_IN_SECONDS );
	} else {
		$reason .= '; resta la copia del ' . ( forestas_entes_state()['fetched_at'] ?? $old['fetched_at'] ?? '?' );
	}
	error_log( 'forestas enti: ' . $reason );
	return false;
}

/**
 * Da più di FORESTAS_ENTES_STALE_AFTER secondi non è partito nessun rinnovo (né da WP-Cron né da una pagina)? Succede
 * con WP-Cron fermo, ma anche con WP-Cron che gira dopo un'ora senza visite, perché WP-Cron parte solo con una visita:
 * le pagine degli enti rinnovano da sé (forestas_entes_all()). Con WP-Cron che gira e visite continue il rinnovo parte
 * ogni 15 minuti anche quando fallisce o viene scartato, e questo resta falso.
 */
function forestas_entes_overdue(): bool {
	$last = forestas_entes_last_check();
	return null === $last || time() - $last > FORESTAS_ENTES_STALE_AFTER;
}

/**
 * WP-Cron è appena partito (transient `doing_cron` del core scritto da meno di WP_CRON_LOCK_TIMEOUT secondi)? Il
 * transient non scade da solo: dove il loopback di WP-Cron non risponde (in locale) nessuno lo cancella, e resta con
 * l'istante dell'ultima partenza, per questo conta solo se è recente.
 */
function forestas_entes_cron_spawned(): bool {
	$timeout = defined( 'WP_CRON_LOCK_TIMEOUT' ) ? (int) WP_CRON_LOCK_TIMEOUT : MINUTE_IN_SECONDS;
	return (float) get_transient( 'doing_cron' ) + $timeout > microtime( true );
}

/**
 * Una pagina degli enti che trova il rinnovo scaduto lo lascia a WP-Cron? Sì se WP-Cron è appena partito
 * (forestas_entes_cron_spawned(), di solito dalla stessa visita, su `init`) con l'evento del rinnovo da eseguire: il
 * rinnovo gira in background e la pagina esce subito con la copia che c'è. Senza questo la visita rinnoverebbe insieme a
 * WP-Cron, dopo un'ora senza visite. La visita che lascia il rinnovo segna l'istante (`left_to_cron` dello stato); se
 * dopo FORESTAS_ENTES_CRON_WAIT secondi nessun rinnovo è partito, WP-Cron è partito ma non gira (loopback che non
 * risponde), e la pagina rinnova da sé.
 */
function forestas_entes_leave_to_cron(): bool {
	$due = wp_next_scheduled( FORESTAS_ENTES_REFRESH_HOOK );
	if ( false === $due || $due > time() || ! forestas_entes_cron_spawned() ) {
		return false;
	}
	$state = forestas_entes_state();
	$since = (int) ( $state['left_to_cron'] ?? 0 );
	// Un segno di prima dell'ultimo rinnovo partito è di un'altra volta
	if ( $since <= (int) forestas_entes_last_check() ) {
		$state['left_to_cron'] = time();
		forestas_entes_save_state( $state );
		return true;
	}
	return time() - $since < FORESTAS_ENTES_CRON_WAIT;
}

/**
 * La richiesta ha già provato a rinnovare la copia da una pagina (forestas_entes_all())? Una pagina chiede gli enti più
 * volte (rotta, titolo, selettore di lingua, `hreflang`): il rinnovo si prova una volta sola, anche quando il lucchetto
 * è di un altro processo, che intanto potrebbe finire e lasciarlo libero. Con un booleano lo scrive; si dimentica con la
 * copia della richiesta (forestas_entes_copy()).
 *
 * @param bool|null $tried true dopo il tentativo, false per dimenticarlo, null per leggerlo.
 */
function forestas_entes_refresh_tried( ?bool $tried = null ): bool {
	static $value = false;
	if ( null !== $tried ) {
		$value = $tried;
	}
	return $value;
}

/**
 * Gli enti per le pagine degli enti, dalla copia; null se non c'è (le pagine rispondono 503). Senza copia, o con il
 * rinnovo scaduto (forestas_entes_overdue()), e senza attesa in corso, rinnova subito. Con la copia il rinnovo scaduto
 * resta a WP-Cron se è appena partito (forestas_entes_leave_to_cron()). Un tentativo per richiesta
 * (forestas_entes_refresh_tried()) e un rinnovo alla volta: se un altro lo sta facendo (WP-Cron, o una visita arrivata
 * prima), il lucchetto ferma questo e la pagina legge la copia che c'è, o risponde 503 senza. Un rinnovo fallito lascia
 * la copia di prima.
 * Solo per le pagine degli enti: dettagli di percorsi e POI e ricerca non lo chiamano.
 *
 * @return list<array<string, mixed>>|null
 */
function forestas_entes_all(): ?array {
	$copy = forestas_entes_copy();
	$refresh = ( null === $copy || forestas_entes_overdue() )
		&& ! forestas_entes_refresh_tried()
		&& false === get_transient( FORESTAS_ENTES_RETRY )
		&& ( null === $copy || ! forestas_entes_leave_to_cron() );
	if ( $refresh ) {
		forestas_entes_refresh_tried( true );
		forestas_entes_refresh( false );
		$copy = forestas_entes_copy();
	}
	return null === $copy ? null : $copy['data'];
}

/**
 * Gli enti della copia, [] se non c'è. Non chiama mai lo shard: è per l'elenco, la faccetta e il tab della ricerca a
 * testo vuoto, che leggono la copia già scaricata.
 *
 * @return list<array<string, mixed>>
 */
function forestas_entes_cached(): array {
	return forestas_entes_copy()['data'] ?? [];
}

/**
 * L'ente con lo slug dato, dalla copia (con la prima richiesta di forestas_entes_all() se una copia non c'è); null se
 * non c'è.
 *
 * @return array<string, mixed>|null
 */
function forestas_entes_by_slug( string $slug ): ?array {
	if ( '' === $slug ) {
		return null;
	}
	foreach ( forestas_entes_all() ?? [] as $ente ) {
		if ( $slug === forestas_entes_slug( $ente ) ) {
			return $ente;
		}
	}
	return null;
}

/**
 * Slug di un ente della copia, '' se non ce l'ha: un ente senza slug non ha una pagina.
 *
 * @param mixed $ente Ente nella forma di `api/v1/entes`.
 */
function forestas_entes_slug( $ente ): string {
	$slug = is_array( $ente ) ? ( $ente['slug'] ?? null ) : null;
	return is_scalar( $slug ) ? trim( (string) $slug ) : '';
}

/*
 * Dati per le pagine: elenco, dettaglio e card degli enti, dalla copia. `$lang` è la lingua della pagina in due lettere
 * (`it`, `en`, le chiavi dei testi dello shard); un codice di WPML come `en-us` vale come la sua lingua.
 */

/**
 * Nomi dei tipi di ente nella lingua corrente di WPML (forestas_testo(), cioè quella della pagina), per i cinque valori
 * di `type`: sono anche l'elenco dei tipi conosciuti (forestas_entes_unknown_types()). I nomi italiani sono quelli dei
 * termini Drupal (tassonomia `tipo_ente_istituzione_societa`); Drupal non ha traduzioni inglesi dei termini (su `/en/`
 * mostra l'italiano), quindi l'inglese è nostro.
 *
 * @return array<string, string> Tipo → nome.
 */
function forestas_entes_type_labels(): array {
	return [
		'complesso-forestale'         => forestas_testo( 'Complesso forestale', 'Forest complex' ),
		'ente-partner'                => forestas_testo( 'Ente partner', 'Partner organisation' ),
		'altre-pubbliche-istituzioni' => forestas_testo( 'Altre Pubbliche Istituzioni', 'Other public institutions' ),
		'privato-associazione'        => forestas_testo( 'Privato/associazione', 'Private body or association' ),
		'comune'                      => forestas_testo( 'Comune', 'Municipality' ),
	];
}

/**
 * Nome del tipo di ente nella lingua corrente di WPML (forestas_entes_type_labels()). Un valore sconosciuto o assente
 * ('') non si mostra (null). Non scrive nel log, perché si chiama a ogni visita: i tipi sconosciuti li segnala il
 * rinnovo della copia, una volta per rinnovo (forestas_entes_refresh()).
 */
function forestas_entes_type_label( string $type ): ?string {
	return forestas_entes_type_labels()[ $type ] ?? null;
}

/**
 * Tipi sconosciuti (non vuoti e senza un nome in forestas_entes_type_labels()) degli enti di una lista, ciascuno una
 * volta, in ordine alfabetico.
 *
 * @param list<array<string, mixed>> $entes Enti nella forma di `api/v1/entes`.
 * @return list<string>
 */
function forestas_entes_unknown_types( array $entes ): array {
	$known   = forestas_entes_type_labels();
	$unknown = [];
	foreach ( $entes as $ente ) {
		$type = forestas_entes_type( $ente );
		if ( '' !== $type && ! isset( $known[ $type ] ) ) {
			$unknown[ $type ] = true;
		}
	}
	$unknown = array_keys( $unknown );
	sort( $unknown, SORT_STRING );
	return array_map( 'strval', $unknown );
}

/**
 * Categoria dell'ente per la card dell'elenco, come la teaser di Drupal (node--ente-istituzione-societa--teaser): il
 * termine padre del tipo, e per un termine senza padre il termine stesso (in Twig `parent.entity` di un termine radice
 * ricade su FieldItemList::getEntity(), il termine). Nella tassonomia di Drupal Comune e Complesso forestale sono figli
 * di Altre Pubbliche Istituzioni; gli altri tre tipi sono radici. Il dettaglio mostra invece il tipo
 * (forestas_entes_type_label()). Un tipo assente o sconosciuto dà null.
 */
function forestas_entes_type_category( string $type ): ?string {
	$parents = [
		'comune'              => 'altre-pubbliche-istituzioni',
		'complesso-forestale' => 'altre-pubbliche-istituzioni',
	];
	$labels  = forestas_entes_type_labels();
	return isset( $labels[ $type ] ) ? $labels[ $parents[ $type ] ?? $type ] : null;
}

/** Lingua in due lettere minuscole (`en-us` → `en`); 'it' se vuota. */
function forestas_entes_lang( string $lang ): string {
	$lang = strtolower( substr( trim( $lang ), 0, 2 ) );
	return '' !== $lang ? $lang : 'it';
}

/**
 * Codice di WPML della lingua (`en` → `en-us`), per i filtri di WPML; la lingua stessa se WPML non la ha. Le lingue si
 * leggono una volta per richiesta. `wpml_active_languages` passa dal filtro `icl_ls_languages` del selettore di lingua:
 * `$codes` vale [] prima della chiamata, così una funzione agganciata a quel filtro che chiede un indirizzo
 * (forestas_entes_url()) non rientra qui all'infinito, e nel frattempo riceve la lingua stessa.
 */
function forestas_entes_wpml_code( string $lang ): string {
	static $codes = null;
	if ( null === $codes ) {
		$codes  = [];
		$active = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
		foreach ( is_array( $active ) ? $active : [] as $code => $info ) {
			$code = (string) ( is_array( $info ) && isset( $info['code'] ) ? $info['code'] : $code );
			$codes[ forestas_entes_lang( $code ) ] ??= $code;
		}
	}
	$lang = forestas_entes_lang( $lang );
	return $codes[ $lang ] ?? $lang;
}

/**
 * Testo dello shard (nome, descrizione, contatti) nella lingua, con la lingua da marcare con `lang` (WCAG 3.1.2) quando
 * non è quella della pagina. Testo e ripiego sono quelli dei dettagli di percorsi e POI (forestas_dettaglio_testo_lingua():
 * la lingua, poi l'italiano, poi la prima lingua con un testo). In più, una chiave `en` uguale a `it` è il testo italiano
 * copiato dall'API, come per tutti gli enti importati da Drupal: si marca `it`. Una traduzione vera, scritta in Nova, ha
 * `lang` null.
 *
 * @param mixed $value Testi per lingua (`{it, en}`, `[]` se vuoti) o una stringa.
 * @return array{text: string, lang: ?string} `lang` null quando il testo è nella lingua della pagina.
 */
function forestas_entes_text( $value, string $lang ): array {
	$lang      = forestas_entes_lang( $lang );
	$text      = forestas_dettaglio_testo_lingua( $value, $lang );
	$text_lang = null !== $text['lingua'] && '' !== $text['testo'] ? forestas_entes_lang( $text['lingua'] ) : null;
	// Una chiave `en` uguale a `it` è il testo italiano copiato dall'API
	$italian = is_array( $value ) ? forestas_dettaglio_testo_lingua( [ 'it' => $value['it'] ?? null ], 'it' )['testo'] : '';
	if ( $lang === $text_lang && 'it' !== $lang && $text['testo'] === $italian ) {
		$text_lang = 'it';
	}
	return [
		'text' => $text['testo'],
		'lang' => $lang !== $text_lang ? $text_lang : null,
	];
}

/**
 * Indirizzo del dettaglio di un ente nella lingua: `/enti/<slug>/`, `/en/enti/<slug>/`. Si parte dalla home senza
 * filtri di lingua (l'opzione, non home_url(), che sul sito WPML porta già la lingua della pagina), come
 * wmwp_detail_url() del plugin: il prefisso lo mette WPML per la lingua chiesta.
 */
function forestas_entes_url( string $slug, string $lang ): string {
	$url = untrailingslashit( (string) get_option( 'home' ) ) . '/enti/' . rawurlencode( $slug ) . '/';
	return (string) apply_filters( 'wpml_permalink', $url, forestas_entes_wpml_code( $lang ) );
}

/** Istante di una data dello shard (ISO o `Y-m-d H:i:s`), null se manca o non si legge. */
function forestas_entes_time( $value ): ?int {
	$time = is_string( $value ) && '' !== trim( $value ) ? strtotime( $value ) : false;
	return false === $time ? null : $time;
}

/**
 * Elementi ordinati per una data dello shard, quelli senza data in fondo nell'ordine del JSON (usort è stabile).
 *
 * @param list<array<string, mixed>> $items
 * @return list<array<string, mixed>>
 */
function forestas_entes_sort_by_date( array $items, string $key, bool $newest_first ): array {
	usort(
		$items,
		function ( array $a, array $b ) use ( $key, $newest_first ): int {
			$time_a = forestas_entes_time( $a[ $key ] ?? null );
			$time_b = forestas_entes_time( $b[ $key ] ?? null );
			if ( null === $time_a || null === $time_b ) {
				return ( null === $time_a ) <=> ( null === $time_b );
			}
			return $newest_first ? $time_b <=> $time_a : $time_a <=> $time_b;
		}
	);
	return $items;
}

/**
 * Primo indirizzo non vuoto di un'immagine dello shard (MediaResource) fra le chiavi date, in ordine; null se nessuno.
 *
 * @param mixed        $image `feature_image` o una foto di `image_gallery`.
 * @param list<string> $keys  Chiavi: `thumbnail`, `url` o una misura di `sizes` (`1440x500`).
 */
function forestas_entes_image_url( $image, array $keys ): ?string {
	if ( ! is_array( $image ) ) {
		return null;
	}
	foreach ( $keys as $key ) {
		$url = $image[ $key ] ?? $image['sizes'][ $key ] ?? null;
		if ( is_string( $url ) && '' !== trim( $url ) ) {
			return trim( $url );
		}
	}
	return null;
}

/**
 * Miniatura di un ente per le card (elenco e ricerca): la prima misura di FORESTAS_ENTES_CARD_IMAGE che c'è; null se
 * nessuna.
 *
 * @param mixed $image `feature_image` dello shard.
 */
function forestas_entes_card_image( $image ): ?string {
	return forestas_entes_image_url( $image, FORESTAS_ENTES_CARD_IMAGE );
}

/** Tipo di un ente della copia (`type`), '' se manca. */
function forestas_entes_type( $ente ): string {
	$type = is_array( $ente ) ? ( $ente['type'] ?? null ) : null;
	return is_string( $type ) ? $type : '';
}

/**
 * Card di un ente, per l'elenco /enti e per il tab della ricerca (che la porta nella forma delle card del plugin,
 * forestas_entes_search_card()). La lingua corrente di WPML deve essere `$lang`: i nomi di tipo e categoria
 * (forestas_testo()) seguono quella. Con `$known` (gli slug della copia, forestas_entes_known_slugs()) il link c'è solo
 * per gli enti che hanno una pagina: un ente trovato dalla ricerca ma non ancora nella copia ha `url` null.
 *
 * @param array<string, mixed>     $ente  Ente nella forma di `api/v1/entes` o della ricerca dello shard, con uno slug.
 * @param array<string, true>|null $known Slug con una pagina; null se l'ente viene dalla copia.
 * @return array{id: int, slug: string, name: string, name_lang: ?string, type: ?string, category: ?string, image: ?string, url: ?string}
 *   `type` è il nome del tipo (forestas_entes_type_label()), `category` quello della card dell'elenco di Drupal
 *   (forestas_entes_type_category()), `image` la miniatura.
 */
function forestas_entes_list_item( array $ente, string $lang, ?array $known = null ): array {
	$slug = forestas_entes_slug( $ente );
	$type = forestas_entes_type( $ente );
	$name = forestas_entes_text( $ente['name'] ?? null, $lang );
	return [
		'id'        => (int) ( $ente['id'] ?? 0 ),
		'slug'      => $slug,
		'name'      => $name['text'],
		'name_lang' => $name['lang'],
		'type'      => forestas_entes_type_label( $type ),
		'category'  => forestas_entes_type_category( $type ),
		'image'     => forestas_entes_card_image( $ente['feature_image'] ?? null ),
		'url'       => null === $known || isset( $known[ $slug ] ) ? forestas_entes_url( $slug, $lang ) : null,
	];
}

/**
 * Gli enti dell'elenco: quelli con una pagina (uno slug) e, con `$type`, solo di quel tipo (filtro «Argomento»). Sono
 * quelli che contano card, pagine e voci della faccetta.
 *
 * @param list<array<string, mixed>> $entes Enti nella forma di `api/v1/entes`.
 * @param string                     $type  Tipo del filtro, '' senza filtro.
 * @return list<array<string, mixed>>
 */
function forestas_entes_listed( array $entes, string $type = '' ): array {
	return array_values(
		array_filter(
			$entes,
			fn( $ente ) => '' !== forestas_entes_slug( $ente ) && ( '' === $type || forestas_entes_type( $ente ) === $type )
		)
	);
}

/**
 * Card dell'elenco degli enti, nell'ordine di creazione su Drupal (`created_at` crescente, senza data in fondo): la view
 * `enti` di Drupal non ha un ordinamento, e quello di creazione è il più vicino a quello che si vede. Gli enti senza slug
 * non hanno una pagina e non ci sono; con `$type` solo gli enti di quel tipo (filtro «Argomento»), nessuno per un tipo
 * che nessun ente ha. Legge solo la copia, senza chiamare lo shard: la prima richiesta di un sito senza copia, e il 503
 * quando non c'è, sono della rotta (forestas_entes_route(), con forestas_entes_all()).
 * La lingua corrente di WPML deve essere `$lang`: i nomi di tipo e categoria (forestas_testo()) seguono quella.
 *
 * @param string $type Tipo del filtro «Argomento» (`type` dello shard), '' per tutti.
 * @return list<array{id: int, slug: string, name: string, name_lang: ?string, type: ?string, category: ?string, image: ?string, url: string}>
 *   Le card di forestas_entes_list_item(), sempre con il link.
 */
function forestas_entes_list( string $lang, string $type = '' ): array {
	return array_map(
		fn( array $ente ): array => forestas_entes_list_item( $ente, $lang ),
		forestas_entes_sort_by_date( forestas_entes_listed( forestas_entes_cached(), $type ), 'created_at', false )
	);
}

/**
 * Voci del filtro «Argomento» dell'elenco, come la faccetta `argomento` della view `enti` di Drupal
 * (facets.facet.argomento: campo `field_tipo_ente`, senza gerarchia, link con il numero di enti, operatore AND):
 * - i tipi degli enti dell'elenco filtrato, ciascuno con il suo nome (forestas_entes_type_label(), nella lingua corrente
 *   di WPML) e il numero di enti. Il valore è il tipo, non la categoria della card: su Drupal la faccetta dà «Comune» e
 *   «Complesso forestale», le card dei due tipi «Altre Pubbliche Istituzioni». Con l'operatore AND, scelto un tipo
 *   restano i tipi che gli enti filtrati hanno: con un tipo per ente (`type` dell'API) solo quello scelto;
 * - in ordine di numero decrescente e a pari numero per nome (count_widget_order e display_value_widget_order di
 *   Drupal);
 * - un tipo sconosciuto (senza nome) non c'è;
 * - con un tipo che nessun ente ha (indirizzo scritto a mano) l'elenco è vuoto, come su Drupal; le voci sono quelle di
 *   tutto l'elenco, senza voce scelta, così da lì si sceglie un tipo vero (Drupal mostra la faccetta vuota).
 * La voce scelta è quella con il valore `$type`, l'unica quando c'è: forestas_filtri_colonna() la mostra come scelta.
 *
 * @param string $type Tipo del filtro, '' senza filtro.
 * @return array<string, array{0: string, 1: int}> Tipo → [ nome, numero di enti ], nella forma di forestas_filtri_colonna().
 */
function forestas_entes_type_facet( string $type ): array {
	$entes = forestas_entes_listed( forestas_entes_cached(), $type );
	if ( [] === $entes ) {
		$entes = forestas_entes_listed( forestas_entes_cached() );
	}
	$values = [];
	foreach ( $entes as $ente ) {
		$value = forestas_entes_type( $ente );
		$label = forestas_entes_type_label( $value );
		if ( null !== $label ) {
			$values[ $value ] = [ $label, ( $values[ $value ][1] ?? 0 ) + 1 ];
		}
	}
	// Per nome senza distinguere maiuscole e accenti, come le faccette delle news (forestas_filtri_conta())
	$sort_name = fn( string $label ): string => strtolower( remove_accents( $label ) );
	uasort( $values, fn( array $a, array $b ): int => [ -$a[1], $sort_name( $a[0] ) ] <=> [ -$b[1], $sort_name( $b[0] ) ] );
	return $values;
}

/**
 * Testo ricco dello shard (descrizione, contatti) pronto per la pagina: filtrato con wp_kses_post(), senza i titoli
 * vuoti di Drupal e con i titoli rinumerati da h3, sotto il titolo h2 della sezione (forestas_dettaglio_ricco()); il
 * template può rinumerarli da un altro livello con forestas_dettaglio_titoli(). Null se il testo è vuoto (`[]`).
 *
 * @param mixed $value Testi per lingua.
 * @return array{html: ?string, lang: ?string}
 */
function forestas_entes_rich( $value, string $lang ): array {
	$text = forestas_entes_text( $value, $lang );
	$html = '' !== $text['text'] ? forestas_dettaglio_ricco( $text['text'], forestas_entes_lang( $lang ), 3 ) : '';
	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return [
			'html' => null,
			'lang' => null,
		];
	}
	return [
		'html' => $html,
		'lang' => $text['lang'],
	];
}

/**
 * Pagina web dell'ente; senza titolo il testo è l'indirizzo, come il formatter «link» di Drupal. Null se manca.
 *
 * @param mixed $website `{url, title}` o null.
 * @return array{url: string, title: string}|null
 */
function forestas_entes_website( $website ): ?array {
	$url = is_array( $website ) && is_string( $website['url'] ?? null ) ? trim( $website['url'] ) : '';
	if ( '' === $url ) {
		return null;
	}
	$title = is_string( $website['title'] ?? null ) ? trim( $website['title'] ) : '';
	return [
		'url'   => $url,
		'title' => '' !== $title ? $title : $url,
	];
}

/**
 * Collegamenti dell'ente da `related_url` (etichetta → indirizzo; senza etichetta l'import usa l'indirizzo come
 * chiave). Una lista semplice di indirizzi vale con l'indirizzo come testo.
 *
 * @param mixed $related `related_url` dello shard.
 * @return list<array{url: string, title: string}>
 */
function forestas_entes_links( $related ): array {
	if ( ! is_array( $related ) ) {
		return [];
	}
	$labelled = ! array_is_list( $related );
	$links    = [];
	foreach ( $related as $label => $url ) {
		$url = is_string( $url ) ? trim( $url ) : '';
		// Un indirizzo con un protocollo che esc_url() non ammette (per esempio `javascript:`) non si mostra
		if ( '' === $url || '' === esc_url( $url ) ) {
			continue;
		}
		$title   = $labelled ? trim( (string) $label ) : '';
		$links[] = [
			'url'   => $url,
			'title' => '' !== $title ? $title : $url,
		];
	}
	return $links;
}

/**
 * Dati del dettaglio di un ente, nell'ordine del template Drupal `node--ente-istituzione-societa--full`. Le sezioni
 * vuote sono null (o []), e il template non le mostra; `*_lang` è la lingua da marcare (forestas_entes_text()).
 * La lingua corrente di WPML deve essere `$lang`: nome del tipo, testi delle foto della galleria e date la seguono.
 *
 * @param array<string, mixed> $ente Ente della copia.
 * @return array{
 *   name: string, name_lang: ?string, type: ?string,
 *   image: ?array{url: string, didascalia: string, autore: string, credits: string},
 *   description: ?string, description_lang: ?string, contacts: ?string, contacts_lang: ?string,
 *   website: ?array{url: string, title: string}, links: list<array{url: string, title: string}>,
 *   gallery: list<array{url: string, miniatura: string, didascalia: string, autore: string, credits: string}>,
 *   updated: ?string, created: ?string
 * }
 */
function forestas_entes_view( array $ente, string $lang ): array {
	$lang        = forestas_entes_lang( $lang );
	$name        = forestas_entes_text( $ente['name'] ?? null, $lang );
	$description = forestas_entes_rich( $ente['description'] ?? null, $lang );
	$contacts    = forestas_entes_rich( $ente['contacts'] ?? null, $lang );
	// Immagine principale come quella del dettaglio traccia: la misura larga della testata, autore e credits della foto
	$photo     = $ente['feature_image'] ?? null;
	$photo_url = forestas_entes_image_url( $photo, [ '1440x500', 'url' ] );
	return [
		'name'             => $name['text'],
		'name_lang'        => $name['lang'],
		'type'             => forestas_entes_type_label( forestas_entes_type( $ente ) ),
		'image'            => null === $photo_url ? null : [
			'url'        => $photo_url,
			'didascalia' => forestas_dettaglio_testo( $photo['caption'] ?? null, $lang ),
			'autore'     => forestas_dettaglio_testo( $photo['author'] ?? null, $lang ),
			'credits'    => forestas_dettaglio_testo( $photo['credits'] ?? null, $lang ),
		],
		'description'      => $description['html'],
		'description_lang' => $description['lang'],
		'contacts'         => $contacts['html'],
		'contacts_lang'    => $contacts['lang'],
		'website'          => forestas_entes_website( $ente['website'] ?? null ),
		'links'            => forestas_entes_links( $ente['related_url'] ?? null ),
		'gallery'          => forestas_dettaglio_galleria( $ente ),
		// Le date come le dà l'API (oggi quelle di Drupal): la parte AAAA-MM-GG, nel formato data del sito
		'updated'          => forestas_dettaglio_data( $ente['updated_at'] ?? null ),
		'created'          => forestas_dettaglio_data( $ente['created_at'] ?? null ),
	];
}

/**
 * Il nome della card è uno dei nomi dell'indice dei dettagli per lo stesso id? Gli spazi non contano (nell'indice i
 * nomi arrivano da Elasticsearch così come sono, a volte con un tab in testa); senza nomi nell'indice, no.
 *
 * @param mixed $names `names` dell'indice: nomi per lingua, o un nome solo.
 */
function forestas_entes_name_matches( string $name, $names ): bool {
	$normalize = fn( string $text ): string => trim( (string) preg_replace( '/[\s\x{00A0}]+/u', ' ', $text ) );
	$name      = $normalize( $name );
	if ( '' === $name ) {
		return false;
	}
	foreach ( is_array( $names ) ? $names : [ $names ] as $candidate ) {
		if ( is_string( $candidate ) && $normalize( $candidate ) === $name ) {
			return true;
		}
	}
	return false;
}

/**
 * «Sentieri gestiti» di un ente: le card di forestas_dettaglio_card_percorso() per le tracce con il ruolo `gestore`,
 * dalla più recente per data di creazione su Drupal (`created_at` decrescente, senza data in fondo). Compare solo una
 * traccia con una pagina di dettaglio (l'`url` della card) e il cui nome è fra i nomi dell'indice dei dettagli del
 * plugin per lo stesso id: dopo il reset di UAT un id può passare a un altro sentiero prima che l'indice, ricostruito
 * ogni ora, se ne accorga, e la card porterebbe al sentiero sbagliato. Vale il nome della card (lingua della pagina, o
 * italiano) o il nome italiano: una traduzione scritta dopo l'ultima ricostruzione dell'indice non toglie la card,
 * mentre un id passato a un altro sentiero non ha nessuno dei due.
 * La lingua corrente di WPML deve essere quella della pagina: nome, tipo e indirizzo delle card la seguono.
 *
 * @param array<string, mixed> $ente Ente della copia.
 * @return list<array<string, mixed>> Card nella forma di forestas_dettaglio_card_percorso().
 */
function forestas_entes_managed_tracks( array $ente ): array {
	$managed = array_values(
		array_filter(
			is_array( $ente['tracks'] ?? null ) ? $ente['tracks'] : [],
			fn( $track ) => is_array( $track ) && in_array( 'gestore', (array) ( $track['roles'] ?? [] ), true )
		)
	);
	if ( ! $managed || ! function_exists( 'wmwp_detail_index' ) ) {
		return [];
	}
	$index = wmwp_detail_index();
	$cards = [];
	foreach ( forestas_entes_sort_by_date( $managed, 'created_at', true ) as $track ) {
		$card = forestas_dettaglio_card_percorso( $track );
		if ( null === $card || null === $card['url'] ) {
			continue;
		}
		$names   = $index['items']['track'][ $card['id'] ]['names'] ?? null;
		$italian = forestas_dettaglio_testo( $track['name'] ?? null, 'it' );
		if ( forestas_entes_name_matches( (string) $card['nome'], $names ) || forestas_entes_name_matches( $italian, $names ) ) {
			$cards[] = $card;
		}
	}
	return $cards;
}

/*
 * Pagine dinamiche degli enti: elenco `/enti/` (paginato, `/enti/page/<n>/`) e dettaglio `/enti/<slug>/`, in italiano e
 * in inglese (`/en/…`).
 */

/**
 * Regole di rewrite delle pagine degli enti, in testa a quelle di WordPress (filtro `rewrite_rules_array`, come le
 * schede di inc/news.php). La paginazione viene prima del dettaglio: `enti/page/2` è la pagina 2, non l'ente «page». Con
 * WPML il prefisso `/en/` è tolto prima delle regole: valgono per le due lingue.
 *
 * @return array<string, string>
 */
function forestas_entes_rewrite_rules(): array {
	$view = FORESTAS_ENTES_VIEW_VAR;
	$slug = FORESTAS_ENTES_SLUG_VAR;
	return [
		'^enti/?$'               => "index.php?{$view}=list",
		'^enti/page/([0-9]+)/?$' => "index.php?{$view}=list&paged=\$matches[1]",
		'^enti/([^/]+)/?$'       => "index.php?{$view}=detail&{$slug}=\$matches[1]",
	];
}
add_filter( 'rewrite_rules_array', fn( array $rules ): array => forestas_entes_rewrite_rules() + $rules );
add_filter( 'query_vars', fn( array $vars ): array => array_merge( $vars, [ FORESTAS_ENTES_VIEW_VAR, FORESTAS_ENTES_SLUG_VAR ] ) );

/**
 * La richiesta è arrivata da una delle regole degli enti? `$wp->matched_rule`, scritta da WP::parse_request(), è fra le
 * chiavi di forestas_entes_rewrite_rules(). Il controllo del filtro `request` e di forestas_entes_requested().
 */
function forestas_entes_rule_matched(): bool {
	$wp = $GLOBALS['wp'] ?? null;
	return $wp instanceof WP && is_string( $wp->matched_rule ) && array_key_exists( $wp->matched_rule, forestas_entes_rewrite_rules() );
}

/**
 * Query var della richiesta sulle pagine degli enti (filtro `request`, alla fine di WP::parse_request(), con
 * `$wp->matched_rule` già scritta).
 * - Le query var degli enti valgono solo dalle loro regole. Devono essere pubbliche perché le regole funzionino, ma così
 *   WordPress le prenderebbe anche dalla query string di qualunque indirizzo, e una query var in più sulla home mostra le
 *   news al posto della pagina iniziale: `/?forestas_entes[]=x` e `/news/?forestas_entes=list` restano le pagine che
 *   sarebbero senza il codice degli enti.
 * - Sulle regole degli enti valgono solo le query var della regola (`$wp->matched_query`: vista, slug, `paged`), non
 *   quelle pubbliche della query string, che WordPress farebbe prevalere: `/enti/?forestas_entes=detail` resta l'elenco,
 *   e `/enti/?p=1`, `/enti/<slug>/?page_id=2`, `?name=…`, `?s=…`, `?feed=…` non accendono is_single, is_page, la ricerca o
 *   il feed delle news su una pagina che non ha un post (avvisi PHP di post-template.php e di get_body_class()). Le query
 *   var non pubbliche (messe da altro codice) restano.
 *
 * @param array<string, mixed> $vars Query var della richiesta.
 * @return array<string, mixed>
 */
function forestas_entes_request_vars( $vars ) {
	if ( ! is_array( $vars ) ) {
		return $vars;
	}
	unset( $vars[ FORESTAS_ENTES_VIEW_VAR ], $vars[ FORESTAS_ENTES_SLUG_VAR ] );
	if ( ! forestas_entes_rule_matched() ) {
		return $vars;
	}
	$wp = $GLOBALS['wp'];
	// Come WP::parse_request() legge la query della regola
	parse_str( (string) $wp->matched_query, $rule_vars );
	$vars = array_diff_key( $vars, array_flip( (array) $wp->public_query_vars ) );
	return array_merge( $vars, array_intersect_key( $rule_vars, array_flip( [ FORESTAS_ENTES_VIEW_VAR, FORESTAS_ENTES_SLUG_VAR, 'paged' ] ) ) );
}
add_filter( 'request', 'forestas_entes_request_vars' );

/**
 * Risposta di una pagina degli enti per le query var della richiesta (`forestas_entes` list|detail, `paged`,
 * `forestas_ente_slug`) e il filtro «Argomento» dell'elenco (`argomento`, dalla query string). Chiama
 * forestas_entes_all() prima di tutto: senza copia è la prima richiesta allo shard, poi elenco e dettaglio leggono la
 * copia. Senza copia (nemmeno dopo la richiesta, con l'attesa o con un altro rinnovo in corso) 503; una pagina
 * dell'elenco oltre l'ultima, uno slug che non è nella copia o una vista sconosciuta 404. Con il filtro le pagine sono
 * quelle dell'elenco filtrato; un tipo che nessun ente ha dà la prima pagina vuota (200, come Drupal) e 404 dalla
 * seconda. Il filtro è un tipo nella forma di `type` (sanitize_key(): `Comune` vale `comune`); un valore che non è un
 * testo (`?argomento[]=…`) non filtra.
 * Non tocca intestazioni né query: lo fa forestas_entes_template_redirect().
 *
 * @param array<string, mixed> $vars Query var e `argomento`.
 * @return array{status: int, view: string, ente?: array<string, mixed>, page?: int, pages?: int, type?: string}
 *   `ente` per il dettaglio; `page`, `pages` (almeno 1) e `type` (il tipo del filtro, '' senza) per l'elenco.
 */
function forestas_entes_route( array $vars ): array {
	$view = is_string( $vars[ FORESTAS_ENTES_VIEW_VAR ] ?? null ) ? $vars[ FORESTAS_ENTES_VIEW_VAR ] : '';
	if ( 'list' !== $view && 'detail' !== $view ) {
		return [
			'status' => 404,
			'view'   => '',
		];
	}
	$entes = forestas_entes_all();
	if ( null === $entes ) {
		return [
			'status' => 503,
			'view'   => $view,
		];
	}
	if ( 'detail' === $view ) {
		$slug = is_scalar( $vars[ FORESTAS_ENTES_SLUG_VAR ] ?? null ) ? trim( (string) $vars[ FORESTAS_ENTES_SLUG_VAR ] ) : '';
		$ente = forestas_entes_by_slug( $slug );
		return null === $ente
			? [
				'status' => 404,
				'view'   => 'detail',
			]
			: [
				'status' => 200,
				'view'   => 'detail',
				'ente'   => $ente,
			];
	}
	// Le pagine contano gli enti dell'elenco, filtrato se c'è il filtro, come le card di forestas_entes_list()
	$type  = is_string( $vars[ FORESTAS_ENTES_TYPE_PARAM ] ?? null ) ? sanitize_key( $vars[ FORESTAS_ENTES_TYPE_PARAM ] ) : '';
	$page  = max( 1, (int) ( $vars['paged'] ?? 1 ) );
	$pages = max( 1, (int) ceil( count( forestas_entes_listed( $entes, $type ) ) / FORESTAS_ENTES_PER_PAGE ) );
	return [
		'status' => $page <= $pages ? 200 : 404,
		'view'   => 'list',
		'page'   => $page,
		'pages'  => $pages,
		'type'   => $type,
	];
}

/**
 * La query chiede una pagina degli enti? Solo la query principale, con la query var `forestas_entes` testo `list` o
 * `detail`, e solo se la richiesta è arrivata da una delle regole degli enti (`$wp->matched_rule`, fra le chiavi di
 * forestas_entes_rewrite_rules()). La query var è pubblica, quindi si può scrivere in qualunque indirizzo: senza il
 * controllo della regola `/news/?forestas_entes=list` diventerebbe l'elenco degli enti, e `/?forestas_entes[]=x` (un
 * array) darebbe avvisi PHP e una pagina vuota con 200. forestas_entes_request_vars() la toglie già dalle richieste che
 * non vengono dalle regole degli enti; questo controllo vale anche per una query con la query var scritta a mano (un
 * altro filtro di `request`, una WP_Query costruita dal codice). È il controllo di forestas_entes_page() e dei filtri
 * della query (parse_query, posts_pre_query, pre_handle_404). La regola la controlla forestas_entes_rule_matched().
 */
function forestas_entes_requested( WP_Query $query ): bool {
	$view = $query->get( FORESTAS_ENTES_VIEW_VAR );
	return is_string( $view ) && in_array( $view, [ 'list', 'detail' ], true ) && $query->is_main_query() && forestas_entes_rule_matched();
}

/**
 * La pagina degli enti della richiesta: forestas_entes_route() sulle query var della query principale e sul filtro
 * «Argomento» della query string, con la lingua della pagina in due lettere (`lang`, quella corrente di WPML: i dati di
 * elenco e dettaglio la vogliono uguale). Null se la richiesta non è una pagina degli enti (forestas_entes_requested()).
 * Non si ricorda fra una chiamata e l'altra: la rotta legge la copia già in memoria (forestas_entes_copy()), e la prima
 * richiesta allo shard parte al massimo una volta (forestas_entes_all()).
 *
 * @return array{status: int, view: string, ente?: array<string, mixed>, page?: int, pages?: int, type?: string, lang: string}|null
 */
function forestas_entes_page(): ?array {
	// La query principale, anche se un query_posts() ha sostituito $wp_query
	$query = $GLOBALS['wp_the_query'] ?? null;
	if ( ! $query instanceof WP_Query || ! forestas_entes_requested( $query ) ) {
		return null;
	}
	return forestas_entes_route(
		[
			FORESTAS_ENTES_VIEW_VAR   => $query->get( FORESTAS_ENTES_VIEW_VAR ),
			'paged'                   => $query->get( 'paged' ),
			FORESTAS_ENTES_SLUG_VAR   => $query->get( FORESTAS_ENTES_SLUG_VAR ),
			// Filtro di sola lettura, come quelli delle news (forestas_filtri_scelti()); lo pulisce la rotta
			FORESTAS_ENTES_TYPE_PARAM => $_GET[ FORESTAS_ENTES_TYPE_PARAM ] ?? '', // phpcs:ignore WordPress.Security.NonceVerification
		]
	) + [ 'lang' => forestas_entes_lang( (string) apply_filters( 'wpml_current_language', 'it' ) ) ];
}

/** Titolo dell'elenco degli enti, come la view `enti` di Drupal. */
function forestas_entes_list_title(): string {
	return forestas_testo( 'Enti, istituzioni e società', 'Organisations, institutions and companies' );
}

/**
 * Indirizzo di una pagina dell'elenco nella lingua: `/enti/`, `/enti/page/2/`, `/en/enti/…`, con il filtro «Argomento»
 * `?argomento=<tipo>` se `$type` non è vuoto. Dalla home senza filtri di lingua, come forestas_entes_url().
 */
function forestas_entes_list_url( string $lang, int $page = 1, string $type = '' ): string {
	$url = untrailingslashit( (string) get_option( 'home' ) ) . '/enti/' . ( $page > 1 ? 'page/' . $page . '/' : '' );
	$url = (string) apply_filters( 'wpml_permalink', $url, forestas_entes_wpml_code( $lang ) );
	return '' !== $type ? add_query_arg( FORESTAS_ENTES_TYPE_PARAM, rawurlencode( $type ), $url ) : $url;
}

/**
 * Indirizzo della stessa pagina degli enti (lo stesso ente, la stessa pagina dell'elenco con lo stesso filtro) in una
 * lingua.
 *
 * @param array<string, mixed> $page forestas_entes_page() con status 200.
 */
function forestas_entes_page_url( array $page, string $lang ): string {
	if ( 'detail' === $page['view'] ) {
		return forestas_entes_url( forestas_entes_slug( $page['ente'] ?? null ), $lang );
	}
	return forestas_entes_list_url( $lang, (int) ( $page['page'] ?? 1 ), (string) ( $page['type'] ?? '' ) );
}

/**
 * Titolo del documento di una pagina degli enti, con il separatore del sito («Comune di Stintino | Sardegna Sentieri»,
 * «Enti, istituzioni e società | Pagina 2 | Sardegna Sentieri»), già con l'escape HTML (pre_get_document_title lo
 * stampa così com'è). '' per un 404: il titolo resta quello di WordPress.
 *
 * @param array<string, mixed> $page forestas_entes_page().
 */
function forestas_entes_document_title( array $page ): string {
	$parts = match ( $page['status'] ) {
		200     => 'detail' === $page['view']
			? [ forestas_entes_text( $page['ente']['name'] ?? null, (string) $page['lang'] )['text'] ]
			: [ forestas_entes_list_title(), ( $page['page'] ?? 1 ) > 1 ? sprintf( forestas_testo( 'Pagina %d', 'Page %d' ), $page['page'] ) : '' ],
		503     => [ forestas_testo( 'Pagina momentaneamente non disponibile', 'Page temporarily unavailable' ) ],
		default => [],
	};
	if ( ! array_filter( $parts ) ) {
		return '';
	}
	$parts[] = get_bloginfo( 'name', 'display' );
	return esc_html( implode( ' ' . apply_filters( 'document_title_separator', '-' ) . ' ', array_filter( $parts ) ) );
}

/*
 * La richiesta di una pagina degli enti non è la home del blog. Senza query var di WordPress WP_Query la tratterebbe
 * come tale (is_home): leggerebbe le news, e WP::handle_404() darebbe 404 a `/enti/page/<n>/` oltre l'ultima pagina
 * delle news. Niente query dei post, e lo stato lo decide forestas_entes_template_redirect(). Solo per una richiesta
 * arrivata da una regola degli enti (forestas_entes_requested()): le altre query restano come sono.
 */
add_action(
	'parse_query',
	function ( WP_Query $query ): void {
		if ( forestas_entes_requested( $query ) ) {
			$query->is_home = false;
		}
	}
);
add_filter(
	'posts_pre_query',
	fn( $posts, WP_Query $query ) => forestas_entes_requested( $query ) ? [] : $posts,
	10,
	2
);
add_filter(
	'pre_handle_404',
	fn( $handled, WP_Query $query ) => forestas_entes_requested( $query ) ? true : $handled,
	10,
	2
);

/**
 * Stato della pagina degli enti, prima del redirect canonico del core (priorità 1), come i dettagli del plugin: 200,
 * 404, o 503 con `Retry-After` (l'attesa prima che la pagina richieda di nuovo la copia) e senza cache, come
 * `includes/detail-render.php` del plugin.
 */
function forestas_entes_template_redirect(): void {
	global $wp_query;
	$page = forestas_entes_page();
	if ( null === $page ) {
		return;
	}
	if ( 503 === $page['status'] ) {
		$wp_query->is_404 = false;
		status_header( 503 );
		header( 'Retry-After: ' . FORESTAS_ENTES_RETRY_AFTER );
		nocache_headers();
		return;
	}
	if ( 200 !== $page['status'] ) {
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
		return;
	}
	$wp_query->is_404 = false;
	status_header( 200 );
}
add_action( 'template_redirect', 'forestas_entes_template_redirect', 1 );

/**
 * Template della pagina degli enti: `templates/entes-list.php` o `templates/ente-detail.php` del child; per il 503 la
 * pagina «non disponibile» del plugin (sostituibile con `wp-package/detail-unavailable.php` del child), come per i
 * dettagli di percorsi e POI. Un 404 resta al template di WordPress.
 */
function forestas_entes_template( string $template ): string {
	$page = forestas_entes_page();
	if ( null === $page ) {
		return $template;
	}
	if ( 503 === $page['status'] ) {
		return function_exists( 'wmwp_locate_template' ) ? wmwp_locate_template( 'detail-unavailable.php' ) : $template;
	}
	if ( 200 !== $page['status'] ) {
		return $template;
	}
	$file = locate_template( 'templates/' . ( 'detail' === $page['view'] ? 'ente-detail.php' : 'entes-list.php' ) );
	return '' !== $file ? $file : $template;
}
// Dopo il plugin (99), che sulle pagine non sue lascia il template com'è
add_filter( 'template_include', 'forestas_entes_template', 100 );

// Titolo del documento (come includes/detail-seo.php del plugin)
add_filter(
	'pre_get_document_title',
	function ( $title ) {
		$page = forestas_entes_page();
		$ours = null === $page ? '' : forestas_entes_document_title( $page );
		return '' !== $ours ? $ours : $title;
	},
	20
);

/**
 * `hreflang` della pagina degli enti verso la stessa pagina in ogni lingua, più `x-default` (WPML li stampa solo per i
 * post): con wmwp_detail_hreflangs() del plugin, come per i dettagli di percorsi e POI. Solo per una pagina con stato 200.
 */
function forestas_entes_head_hreflang(): void {
	$page = forestas_entes_page();
	if ( null === $page || 200 !== $page['status'] || ! function_exists( 'wmwp_detail_hreflangs' ) ) {
		return;
	}
	$active = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
	$urls   = [];
	foreach ( is_array( $active ) ? array_keys( $active ) : [] as $code ) {
		$urls[ (string) $code ] = forestas_entes_page_url( $page, (string) $code );
	}
	foreach ( wmwp_detail_hreflangs( [ 'urls' => $urls ] ) as $tag => $url ) {
		printf( '<link rel="alternate" hreflang="%s" href="%s">' . "\n", esc_attr( $tag ), esc_url( $url ) );
	}
}
add_action( 'wp_head', 'forestas_entes_head_hreflang', 2 );

/**
 * Selettore di lingua di WPML: la stessa pagina degli enti nell'altra lingua, non la home (come includes/detail-wpml.php
 * del plugin). Su un 404 o un 503 resta quello di WPML.
 *
 * WPML tiene in cache per la richiesta il risultato di ogni lettura delle lingue (una per argomenti), filtro compreso.
 * La prima lettura delle lingue del child (forestas_entes_wpml_code(), chiamata da forestas_entes_url()) passa da questo
 * filtro: si fa prima di accendere `$busy`, così anche quella lettura riceve gli indirizzi della pagina. Fatta con
 * `$busy` acceso, resterebbero in cache gli indirizzi della home, e il selettore dell'header li leggerebbe da lì.
 *
 * @param mixed $languages Lingue del selettore (codice WPML → dati, con `url`).
 * @return mixed
 */
function forestas_entes_language_switcher( $languages ) {
	// forestas_entes_url() passa da wpml_permalink, che può richiamare il selettore
	static $busy = false;
	if ( ! is_array( $languages ) || $busy ) {
		return $languages;
	}
	$page = forestas_entes_page();
	if ( null === $page || 200 !== $page['status'] ) {
		return $languages;
	}
	forestas_entes_wpml_code( $page['lang'] );
	$busy = true;
	foreach ( $languages as $code => $language ) {
		if ( is_array( $language ) ) {
			$languages[ $code ]['url'] = forestas_entes_page_url( $page, (string) $code );
		}
	}
	$busy = false;
	return $languages;
}
add_filter( 'icl_ls_languages', 'forestas_entes_language_switcher' );

// Rinnovo ogni FORESTAS_ENTES_INTERVAL secondi con WP-Cron, registrato su init se manca, come l'indice dei dettagli del plugin
add_filter(
	'cron_schedules',
	function ( array $schedules ): array {
		$schedules[ FORESTAS_ENTES_SCHEDULE ] = [
			'interval' => FORESTAS_ENTES_INTERVAL,
			'display'  => sprintf( 'Ogni %d minuti (enti dello shard)', FORESTAS_ENTES_INTERVAL / MINUTE_IN_SECONDS ),
		];
		return $schedules;
	}
);
// Nessun argomento: con uno, do_action() passerebbe '' a `$log_busy`
add_action( FORESTAS_ENTES_REFRESH_HOOK, 'forestas_entes_refresh', 10, 0 );

/** Registra l'evento del rinnovo se manca (su init). */
function forestas_entes_schedule(): void {
	if ( ! wp_next_scheduled( FORESTAS_ENTES_REFRESH_HOOK ) ) {
		wp_schedule_event( time() + MINUTE_IN_SECONDS, FORESTAS_ENTES_SCHEDULE, FORESTAS_ENTES_REFRESH_HOOK );
	}
}
add_action( 'init', 'forestas_entes_schedule' );
/**
 * Toglie l'evento del rinnovo quando si cambia tema (`switch_theme`, che gira con il tema vecchio ancora caricato):
 * resterebbe senza chi lo esegue. Tornando al child lo registra di nuovo init (forestas_entes_schedule()).
 */
function forestas_entes_unschedule(): void {
	wp_clear_scheduled_hook( FORESTAS_ENTES_REFRESH_HOOK );
}
add_action( 'switch_theme', 'forestas_entes_unschedule' );
