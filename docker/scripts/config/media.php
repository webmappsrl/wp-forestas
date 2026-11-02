<?php
/**
 * Media di Drupal nella Libreria media di WordPress (oc:8758). Un file si scarica una volta sola: gli
 * allegati portano il fid di Drupal (_drupal_fid) e a ogni esecuzione si riusano.
 */

require_once __DIR__ . '/drupal.php';

const WPF_META_FID = '_drupal_fid';
// Autore e credits di un'immagine, come i campi «Autore» e «Credits» dei media di Drupal: la redazione li
// modifica nella scheda dell'immagine della libreria media (child, inc/pagine-statiche-admin.php)
const WPF_META_AUTORE  = 'wpf_autore';
const WPF_META_CREDITS = 'wpf_credits';
// Attesa massima del download di un file, in secondi
const WPF_MEDIA_ATTESA = 60;

/**
 * Allinea a Drupal i dati di un allegato che possono cambiare senza che cambi il file: per un'immagine
 * testo alternativo, autore e credits (un campo vuoto si cancella), per un documento il titolo, che è il
 * testo del link. Restituisce i campi diversi; in prova non scrive.
 */
function wpf_media_allinea( int $id, array $file, bool $prova ): array {
	$cambiati = [];
	if ( wp_attachment_is_image( $id ) ) {
		// wpf_drupal_file_media dà sempre alt, autore e credits come stringhe
		$meta = [
			'_wp_attachment_image_alt' => [ 'alt', $file['alt'] ],
			WPF_META_AUTORE            => [ 'autore', $file['autore'] ],
			WPF_META_CREDITS           => [ 'credits', $file['credits'] ],
		];
		foreach ( $meta as $chiave => [ $campo, $valore ] ) {
			if ( (string) get_post_meta( $id, $chiave, true ) === $valore ) {
				continue;
			}
			$cambiati[] = $campo;
			if ( ! $prova ) {
				// L'alt vuoto resta scritto (un'immagine decorativa), autore e credits vuoti si cancellano
				$valore === '' && $chiave !== '_wp_attachment_image_alt' ? delete_post_meta( $id, $chiave ) : update_post_meta( $id, $chiave, $valore );
			}
		}
	} elseif ( get_post_field( 'post_title', $id ) !== $file['titolo'] ) {
		$cambiati[] = 'titolo';
		if ( ! $prova ) {
			wp_update_post( [ 'ID' => $id, 'post_title' => wp_slash( $file['titolo'] ) ] );
		}
	}
	return $cambiati;
}

/** L'allegato già importato per un fid di Drupal, o 0. */
function wpf_media_per_fid( int $fid ): int {
	global $wpdb;
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id
			 WHERE m.meta_key = %s AND m.meta_value = %s AND p.post_type = 'attachment' ORDER BY p.ID LIMIT 1",
			WPF_META_FID,
			(string) $fid
		)
	);
}

/**
 * Importa un file di Drupal (come restituito da wpf_drupal_file_media) e ne restituisce l'id
 * dell'allegato. In prova non scarica nulla: restituisce l'id se il file c'è già, 0 se andrebbe
 * scaricato. Un errore di rete o un 404 è un WP_Error con la causa, e non lascia file a metà.
 */
function wpf_media_importa( array $file, bool $prova ) {
	// Un file che non si è scaricato non si ritenta nella stessa esecuzione (italiano e inglese della
	// stessa pagina usano gli stessi file): con il sito lento ogni tentativo costa WPF_MEDIA_ATTESA
	static $falliti = [];
	if ( isset( $falliti[ $file['fid'] ] ) ) {
		return $falliti[ $file['fid'] ];
	}
	// Un file già importato si riusa: i suoi dati li allinea wpf_media_allinea
	$id = wpf_media_per_fid( (int) $file['fid'] );
	if ( $id ) {
		return $id;
	}
	if ( $prova ) {
		return 0;
	}
	$url = wpf_drupal_url_file( $file['uri'] );
	if ( ! $url ) {
		return new WP_Error( 'wpf_media_uri', "uri non scaricabile: {$file['uri']}" );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = download_url( $url, WPF_MEDIA_ATTESA );
	if ( is_wp_error( $tmp ) ) {
		return $falliti[ $file['fid'] ] = new WP_Error( 'wpf_media_download', "{$url}: " . $tmp->get_error_message() );
	}
	// Niente anteprime dei PDF: richiederebbero Ghostscript, che l'immagine non ha (e i PDF si
	// mostrano come link, non come immagini)
	add_filter( 'fallback_intermediate_image_sizes', '__return_empty_array' );
	// Titolo dell'allegato: per i documenti il testo del link che mostra Drupal (descrizione o nome del file)
	$titolo = ! str_starts_with( $file['mime'], 'image/' ) ? $file['titolo'] : pathinfo( $file['nome'], PATHINFO_FILENAME );
	// Il nome senza spazi in fondo: «….pdf » non ha un'estensione che WordPress riconosca (oc:8759, fid 1768).
	// Alcuni file vecchi hanno come nome una descrizione senza estensione («Zaino da escursione (foto da
	// wikipedia)», fid 1036): allora vale il nome del file nell'uri (Rucksack1.jpg), che ce l'ha (oc:8760)
	$nome = trim( $file['nome'] );
	if ( ! wp_check_filetype( $nome )['ext'] ) {
		$nome = wp_basename( $file['uri'] );
	}
	// I KML (tracce e aree per Google Earth, allegati di due «da sapere») non sono fra i tipi che WordPress
	// accetta: si accettano solo in questo import, non dal pannello (oc:8760)
	$kml   = fn( array $tipi ) => $tipi + [ 'kml' => 'application/vnd.google-earth.kml+xml' ];
	$xml   = fn( array $dati, $f, string $nome_file ) => str_ends_with( strtolower( $nome_file ), '.kml' )
		? [ 'ext' => 'kml', 'type' => 'application/vnd.google-earth.kml+xml', 'proper_filename' => false ]
		: $dati;
	add_filter( 'upload_mimes', $kml );
	add_filter( 'wp_check_filetype_and_ext', $xml, 10, 3 );
	$id = media_handle_sideload( [ 'name' => $nome, 'tmp_name' => $tmp ], 0, $titolo );
	remove_filter( 'upload_mimes', $kml );
	remove_filter( 'wp_check_filetype_and_ext', $xml, 10 );
	remove_filter( 'fallback_intermediate_image_sizes', '__return_empty_array' );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return $falliti[ $file['fid'] ] = new WP_Error( 'wpf_media_registra', "{$file['nome']}: " . $id->get_error_message() );
	}
	update_post_meta( $id, WPF_META_FID, (string) $file['fid'] );
	wpf_media_allinea( $id, $file, false );
	return $id;
}
