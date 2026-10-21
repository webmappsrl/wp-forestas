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

/** Allinea autore e credits di un allegato a quelli del media Drupal (un campo vuoto si cancella). */
function wpf_media_credits( int $id, array $file ): void {
	foreach ( [ WPF_META_AUTORE => 'autore', WPF_META_CREDITS => 'credits' ] as $meta => $campo ) {
		$valore = (string) ( $file[ $campo ] ?? '' );
		if ( $valore === '' ) {
			delete_post_meta( $id, $meta );
		} elseif ( get_post_meta( $id, $meta, true ) !== $valore ) {
			update_post_meta( $id, $meta, $valore );
		}
	}
}
// Attesa massima del download di un file, in secondi
const WPF_MEDIA_ATTESA = 60;

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
	$id = wpf_media_per_fid( (int) $file['fid'] );
	if ( $id ) {
		// Alt e descrizione possono cambiare su Drupal senza che cambi il file
		if ( ! $prova && wp_attachment_is_image( $id ) && get_post_meta( $id, '_wp_attachment_image_alt', true ) !== $file['alt'] ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $file['alt'] );
		}
		if ( ! $prova && ! wp_attachment_is_image( $id ) && isset( $file['titolo'] ) && get_post_field( 'post_title', $id ) !== $file['titolo'] ) {
			wp_update_post( [ 'ID' => $id, 'post_title' => wp_slash( $file['titolo'] ) ] );
		}
		if ( ! $prova && wp_attachment_is_image( $id ) ) {
			wpf_media_credits( $id, $file );
		}
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
	$titolo = ( ! str_starts_with( (string) ( $file['mime'] ?? '' ), 'image/' ) && ! empty( $file['titolo'] ) ) ? $file['titolo'] : pathinfo( $file['nome'], PATHINFO_FILENAME );
	$id     = media_handle_sideload( [ 'name' => $file['nome'], 'tmp_name' => $tmp ], 0, $titolo );
	remove_filter( 'fallback_intermediate_image_sizes', '__return_empty_array' );
	if ( is_wp_error( $id ) ) {
		@unlink( $tmp );
		return $falliti[ $file['fid'] ] = new WP_Error( 'wpf_media_registra', "{$file['nome']}: " . $id->get_error_message() );
	}
	update_post_meta( $id, WPF_META_FID, (string) $file['fid'] );
	if ( wp_attachment_is_image( $id ) ) {
		wpf_media_credits( $id, $file );
		update_post_meta( $id, '_wp_attachment_image_alt', $file['alt'] );
	}
	return $id;
}
