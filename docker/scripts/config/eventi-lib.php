<?php
/**
 * Eventi da Drupal (oc:8760): il tipo «evento» del giro di import (import-lib.php). Ogni evento it/und di
 * Drupal è un post del tipo evento (registrato dal child, inc/contenuti.php) con date, luogo, «in evidenza»,
 * collegamenti e tag; le traduzioni inglesi sono collegate in WPML.
 *
 * La data del post resta quella dell'import: un evento futuro datato al suo giorno sarebbe «programmato»
 * (future) e WordPress non lo mostrerebbe. Gli elenchi ordinano per la data di inizio (WPF_META_EVENTO_INIZIO).
 */

require_once __DIR__ . '/contenuti-lib.php';

// Più eventi (o traduzioni) di così da mettere in bozza nello stesso giro: copia Drupal sospetta (11 eventi)
const WPF_EVENTI_SOGLIA_BOZZE = 2;
// Più di così fra gli eventi pubblicati in WordPress e nella copia: copia a metà
const WPF_EVENTI_SCARTO_COPIA = 2;
// Date dell'evento (field_date_event_single, field_data_evento_fine): AAAA-MM-GG, la fine solo se c'è
const WPF_META_EVENTO_INIZIO = 'wpf_evento_inizio';
const WPF_META_EVENTO_FINE   = 'wpf_evento_fine';
// Luogo (field_luogo_evento): [ organization, address_line1, address_line2, postal_code, locality,
// administrative_area, country_code ] con le sole parti presenti, le chiavi del modulo address di Drupal
const WPF_META_EVENTO_LUOGO = 'wpf_evento_luogo';
// Chiave in config/post.json del template di Impreza dell'evento
const WPF_TEMPLATE_EVENTO = 'template-evento';

/** Il tipo «evento» per wpf_import_sincronizza (import-lib.php). */
function wpf_eventi_tipo(): array {
	return [
		'origine'      => 'evento',
		'riga'         => 'EVENTO',
		'nome'         => 'evento',
		'plurale'      => 'eventi',
		'questo'       => 'l\'evento',
		'genere'       => [ 'nessun evento scritto', 'pubblicati', 'stati tolti', 'accettati', 'sincronizzati' ],
		'post_type'    => 'evento',
		'prefisso'     => 'evento',
		'schede'       => null,
		'pagina'       => null,
		'soglia_bozze' => WPF_EVENTI_SOGLIA_BOZZE,
		'scarto_copia' => WPF_EVENTI_SCARTO_COPIA,
		'conteggio'    => 'WPF_EVENTO_PROVA_CONTEGGIO',
		'bundle'       => 'evento',
		'nodo'         => 'wpf_drupal_evento',
		'termini'      => fn( mysqli $db, array $dati, bool $prova ) => wpf_import_tag( $db, $dati, $prova ),
		'scrivi'       => 'wpf_eventi_scrivi',
		'template'     => WPF_TEMPLATE_EVENTO,
		'tassonomie'   => [ 'post_tag' ],
	];
}

/**
 * Crea o aggiorna un evento in una lingua (wpf_contenuto_scrivi).
 *
 * @param array $d       da wpf_drupal_evento, con 'body' e 'collegamenti' già riscritti
 * @param array $termini da wpf_import_tag
 * @return array come wpf_contenuto_scrivi, più 'avvisi' => string[]
 */
function wpf_eventi_scrivi( int $nid, string $lingua, array $d, ?WP_Post $post, ?int $id_it, array $termini, int $template, bool $prova, ?array $solo = null ): array {
	$tag = wpf_import_tag_contenuto( $d, $termini['tag'] );
	$r   = wpf_contenuto_scrivi(
		[
			'tipo'          => 'evento',
			'cosa'          => 'evento',
			'post'          => $post,
			'lingua'        => $lingua,
			'slug'          => $d['slug'] ?? wpf_import_slug( $d ),
			'parent'        => 0,
			'id_it'         => $id_it,
			'voluti'        => [
				'post_title'   => trim( $d['titolo'] ),
				'post_content' => $d['body'],
				'post_status'  => $d['status'] ? 'publish' : 'draft',
				'post_excerpt' => $d['sottotitolo'],
			],
			'meta'          => [
				WPF_META_LINGUA        => $lingua,
				WPF_META_NID           => (string) $nid,
				WPF_META_LANGCODE      => $lingua,
				WPF_META_TESTO_DRUPAL  => md5( $d['body'] ),
				WPF_META_ALIAS_DRUPAL  => wpf_import_alias( $nid, $d ),
				WPF_META_EVENTO_INIZIO => $d['inizio'] !== '' ? $d['inizio'] : null,
				WPF_META_EVENTO_FINE   => $d['fine'] !== '' ? $d['fine'] : null,
				WPF_META_EVENTO_LUOGO  => $d['luogo'] ?: null,
				WPF_META_IN_EVIDENZA   => $d['in_evidenza'] ? '1' : null,
				WPF_META_COLLEGAMENTI  => $d['collegamenti'] ?: null,
			],
			'media'         => [
				'immagine' => $d['immagine'] ? [ $d['immagine'] ] : [],
				'allegato' => $d['allegati'],
				'galleria' => $d['galleria'],
			],
			'template'      => $template,
			'nome_template' => WPF_TEMPLATE_EVENTO,
			'termini'       => [ 'post_tag' => $tag['id'] ],
		],
		$prova,
		$solo
	);
	return array_merge( $r, [ 'avvisi' => $tag['avvisi'] ] );
}
