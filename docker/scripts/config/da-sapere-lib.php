<?php
/**
 * «Da sapere» da Drupal (oc:8760): il tipo «da_sapere» del giro di import (import-lib.php). Ogni «da sapere»
 * it/und di Drupal è un post del tipo da_sapere (registrato dal child, inc/contenuti.php) con la sua
 * tipologia (tipologia_da_sapere) e i tag; le traduzioni inglesi sono collegate in WPML.
 *
 * Le 9 tipologie si importano tutte, anche quelle senza contenuti, con due term meta che la pagina Da sapere
 * legge: la spunta «Mostra in lista Da sapere» (le pillole) e il peso (il loro ordine). Su Drupal una sola è
 * tradotta, con lo stesso nome («F.A.Q.»): la tassonomia si mostra col ripiego di WPML, un termine per le due
 * lingue, come le categorie delle news.
 */

require_once __DIR__ . '/contenuti-lib.php';

// Più «da sapere» (o traduzioni) di così da mettere in bozza nello stesso giro: copia Drupal sospetta
const WPF_DA_SAPERE_SOGLIA_BOZZE = 5;
const WPF_DA_SAPERE_SCARTO_COPIA = 5;
// Term meta della tipologia: «1» se Drupal la mostra fra le pillole (field_mostra_in_lista_da_sapere_), e il
// peso del termine su Drupal (l'ordine delle pillole)
const WPF_TERM_META_IN_LISTA = 'wpf_mostra_in_lista';
const WPF_TERM_META_PESO     = 'wpf_peso';
// Immagine della tipologia (field_immagine_principale_media): l'id dell'allegato, la testata della pagina
// della tipologia. Nel termine anche i vecchi indirizzi della pagina (/taxonomy/term/<tid> e l'alias, nel
// meta WPF_META_ALIAS_DRUPAL dei contenuti: il child ci manda con un 301) e l'impronta della descrizione di
// Drupal (WPF_META_TESTO_DRUPAL)
const WPF_TERM_META_IMMAGINE = 'wpf_immagine';
// Chiave in config/post.json del template di Impreza del «da sapere»
const WPF_TEMPLATE_DA_SAPERE = 'template-da-sapere';

/** Il tipo «da_sapere» per wpf_import_sincronizza (import-lib.php). */
function wpf_da_sapere_tipo(): array {
	return [
		'origine'      => 'da_sapere',
		'riga'         => 'DA_SAPERE',
		'nome'         => 'da sapere',
		'plurale'      => '«da sapere»',
		'questo'       => 'il contenuto',
		'genere'       => [ 'nessun «da sapere» scritto', 'pubblicati', 'stati tolti', 'accettati', 'sincronizzati' ],
		'post_type'    => 'da_sapere',
		'prefisso'     => 'da-sapere',
		'schede'       => 'da-sapere',
		'pagina'       => 'Da sapere',
		'soglia_bozze' => WPF_DA_SAPERE_SOGLIA_BOZZE,
		'scarto_copia' => WPF_DA_SAPERE_SCARTO_COPIA,
		'conteggio'    => 'WPF_DA_SAPERE_PROVA_CONTEGGIO',
		'bundle'       => 'da_sapere',
		'nodo'         => 'wpf_drupal_da_sapere',
		'termini'      => 'wpf_da_sapere_termini',
		'scrivi'       => 'wpf_da_sapere_scrivi',
		'template'     => WPF_TEMPLATE_DA_SAPERE,
		'tassonomie'   => [ 'tipologia_da_sapere', 'post_tag' ],
	];
}

/**
 * Termini dei «da sapere»: tutte le tipologie della copia, con spunta e peso, e i tag. [ 'tipologie' =>
 * [ tid => id ], 'tag' => [ "vid:tid" => id ], 'righe', 'mancanti' ].
 */
function wpf_da_sapere_termini( mysqli $db, array $dati, bool $prova ): array {
	$tipologie = wpf_drupal_tipologie_da_sapere( $db );
	$voci      = [];
	foreach ( array_keys( $tipologie ) as $tid ) {
		$voci[ $tid ] = [ 'tipologia', (string) $tid, 'tipologia_da_sapere', $tid, 'tipologia_da_sapere', 0 ];
	}
	$t        = wpf_import_termini( $db, $voci, $prova );
	$righe    = $t['righe'];
	$mancanti = $t['mancanti'];
	foreach ( $t['id'] as $tid => $id ) {
		$nome = $tipologie[ $tid ]['nome']['it'] ?? reset( $tipologie[ $tid ]['nome'] );
		// Descrizione (la testata della pagina della tipologia): un termine per le due lingue, come il nome.
		// Si confronta l'impronta del testo di Drupal: WordPress ripulisce l'HTML della descrizione al salvataggio
		$descrizione = (string) ( $tipologie[ $tid ]['descrizione']['it'] ?? reset( $tipologie[ $tid ]['descrizione'] ) );
		if ( $id && get_term_meta( $id, WPF_META_TESTO_DRUPAL, true ) !== md5( $descrizione ) ) {
			$righe[] = "tipologia «{$nome}» ({$tid}): descrizione " . ( $prova ? 'da aggiornare' : 'aggiornata' );
			if ( ! $prova ) {
				wp_update_term( $id, 'tipologia_da_sapere', [ 'description' => $descrizione ] );
				update_term_meta( $id, WPF_META_TESTO_DRUPAL, md5( $descrizione ) );
			}
		}
		$immagine = '';
		if ( $tipologie[ $tid ]['immagine'] ) {
			$m = wpf_contenuto_media( [ $tipologie[ $tid ]['immagine'] ], $prova );
			foreach ( $m['errori'] as $file => $causa ) {
				$mancanti[ "immagine della tipologia {$tid} {$file}" ] = $causa;
			}
			if ( $m['errori'] ) {
				// Download fallito (file assente su Drupal, rete): l'immagine già scritta resta, si ritenta al giro dopo
				$immagine = null;
			} else {
				$immagine = $m['ids'] ? (string) $m['ids'][0] : 'da scaricare';
			}
		}
		$voluti = [
			WPF_TERM_META_IN_LISTA => $tipologie[ $tid ]['in_lista'] ? '1' : '',
			WPF_TERM_META_PESO     => (string) $tipologie[ $tid ]['peso'],
			WPF_TERM_META_IMMAGINE => $immagine,
			WPF_META_ALIAS_DRUPAL  => $tipologie[ $tid ]['alias'],
		];
		foreach ( $voluti as $chiave => $valore ) {
			if ( ! $id || $valore === null || get_term_meta( $id, $chiave, true ) === $valore ) {
				continue;
			}
			$righe[] = "tipologia «{$nome}» ({$tid}): {$chiave} " . ( $prova ? 'da aggiornare' : 'aggiornato' ) . ( is_array( $valore ) ? '' : " a «{$valore}»" );
			if ( ! $prova ) {
				$valore === '' ? delete_term_meta( $id, $chiave ) : update_term_meta( $id, $chiave, $valore );
			}
		}
	}
	$tag = wpf_import_tag( $db, $dati, $prova );
	return [
		'tipologie' => $t['id'],
		'tag'       => $tag['tag'],
		'righe'     => array_merge( $righe, $tag['righe'] ),
		'mancanti'  => array_merge( $mancanti, $tag['mancanti'] ),
	];
}

/**
 * Crea o aggiorna un «da sapere» in una lingua (wpf_contenuto_scrivi).
 *
 * @param array $d       da wpf_drupal_da_sapere, con 'body' già riscritto
 * @param array $termini da wpf_da_sapere_termini
 * @return array come wpf_contenuto_scrivi, più 'avvisi' => string[]
 */
function wpf_da_sapere_scrivi( int $nid, string $lingua, array $d, ?WP_Post $post, ?int $id_it, array $termini, int $template, bool $prova, ?array $solo = null ): array {
	$tag    = wpf_import_tag_contenuto( $d, $termini['tag'] );
	$avvisi = $tag['avvisi'];
	$tipologia = [];
	foreach ( $d['tipologie'] as $tid ) {
		if ( isset( $termini['tipologie'][ $tid ] ) ) {
			$tipologia[] = $termini['tipologie'][ $tid ];
		} else {
			$avvisi[] = "la tipologia {$tid} non esiste più su Drupal: il contenuto resta senza quella tipologia";
		}
	}
	// La data di creazione di Drupal, nel fuso del sito: le pagine delle tipologie e le schede ordinano per
	// «Sempre in cima» (menu_order 1) e poi per questa data, come le View di Drupal (sticky, created)
	$data = wp_date( 'Y-m-d H:i:s', $d['creato'] );
	$r = wpf_contenuto_scrivi(
		[
			'tipo'          => 'da_sapere',
			'cosa'          => 'da_sapere',
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
				'post_date'    => $data,
				'menu_order'   => $d['in_cima'] ? 1 : 0,
			],
			'meta'          => [
				WPF_META_LINGUA       => $lingua,
				WPF_META_NID          => (string) $nid,
				WPF_META_LANGCODE     => $lingua,
				WPF_META_TESTO_DRUPAL => md5( $d['body'] ),
				WPF_META_ALIAS_DRUPAL => wpf_import_alias( $nid, $d ),
			],
			'media'         => [
				'immagine' => $d['immagine'] ? [ $d['immagine'] ] : [],
				'allegato' => $d['allegati'],
				'galleria' => $d['galleria'],
			],
			'template'      => $template,
			'nome_template' => WPF_TEMPLATE_DA_SAPERE,
			'termini'       => [
				'tipologia_da_sapere' => array_values( array_filter( $tipologia ) ),
				'post_tag'            => $tag['id'],
			],
			// Come per le news: con la data GMT e edit_date WordPress tiene la data anche su una bozza
			'scrittura'     => [ 'post_date_gmt' => get_gmt_from_date( $data ), 'edit_date' => true ],
		],
		$prova,
		$solo
	);
	return array_merge( $r, [ 'avvisi' => $avvisi ] );
}
