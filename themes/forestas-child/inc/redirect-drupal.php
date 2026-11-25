<?php
/**
 * Vecchi indirizzi di Drupal dei dettagli (oc:9018): quando il sito prenderà www.sardegnasentieri.it, un link vecchio a
 * un percorso o a un POI con l'alias di Drupal (`/sentieri/…`, `/sentiero/…`, `/itinerario/…`, `/da-vedere/…`, `/poi/…`)
 * porta con un 301 alla pagina nuova dello stesso elemento. Gli indirizzi `/node/<nid>` non sono coperti: lo shard dà solo
 * l'alias (`forestas.url`), e Drupal non li mostra nelle sue pagine.
 *
 * Il child dà al plugin l'indirizzo Drupal di ogni elemento (filtro `wmwp_detail_legacy_paths`): il percorso di
 * `properties.forestas.url`, che l'import scrive dall'alias italiano più recente del nodo. Lo stesso percorso vale per
 * la pagina inglese di Drupal (`/en/<alias>`): l'alias inglese è uguale all'italiano in 152 sentieri su 153 e in 170
 * POI su 412 (UAT, 24/11); per gli altri serve l'alias inglese dallo shard (ticket sullo shard). Il plugin mette gli
 * indirizzi nello storico prima che servano (POI da pois.geojson, percorsi dal dettaglio letto una volta), con la
 * chiave stabile dell'elemento (il nid, forestas_dettaglio_nid()), e da lì non si cancellano.
 *
 * @package forestas-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Host del sito Drupal di cui si portano gli indirizzi vecchi. */
const FORESTAS_DRUPAL_HOSTS = [ 'www.sardegnasentieri.it', 'sardegnasentieri.it' ];

/**
 * Indirizzi vecchi di un elemento per il plugin: il percorso di `forestas.url` in italiano e in inglese, solo per
 * sardegnasentieri.it e solo nella forma `<base>/<slug>`.
 *
 * @param list<array{lang: string, path: string}> $paths Indirizzi già dati.
 * @param string                                  $type  `track` o `poi`.
 * @param array<string, mixed>                    $props Proprietà dell'elemento.
 * @return list<array{lang: string, path: string}>
 */
function forestas_detail_legacy_paths( array $paths, string $type, array $props ): array {
	$url  = is_array( $props['forestas'] ?? null ) ? trim( (string) ( $props['forestas']['url'] ?? '' ) ) : '';
	$host = '' !== $url ? strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) ) : '';
	if ( ! in_array( $host, FORESTAS_DRUPAL_HOSTS, true ) ) {
		return $paths;
	}
	$path = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	if ( '' === $path || 1 !== substr_count( $path, '/' ) ) {
		return $paths;
	}
	foreach ( [ 'it', 'en' ] as $lang ) {
		$paths[] = [
			'lang' => $lang,
			'path' => $path,
		];
	}
	return $paths;
}
add_filter( 'wmwp_detail_legacy_paths', 'forestas_detail_legacy_paths', 10, 3 );
