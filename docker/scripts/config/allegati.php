<?php
/**
 * Allegati con il file in config/ (oc:9000): oggi l'icona del sito. L'id di un allegato non vale da un sito all'altro,
 * quindi sito.json indica il file (`@file:media/icona-sito.png`) e l'apply ne crea l'allegato, che ritrova ai giri
 * dopo dal percorso e dall'impronta del file (meta WPF_META_FILE e WPF_META_IMPRONTA): un file cambiato diventa un
 * allegato nuovo, quello vecchio resta nella Libreria media. L'export fa il contrario: copia il file dell'allegato in
 * `media/` della cartella di export, anche se è stato caricato dal pannello.
 */

require_once __DIR__ . '/comune.php';

/**
 * File di config/ indicato da un percorso «media/<nome>»; null se il percorso esce da `media/` o il file non c'è.
 */
function wpf_file_config( string $dir, string $percorso ): ?string {
	if ( ! preg_match( '#^media/[A-Za-z0-9][A-Za-z0-9._-]*$#', $percorso ) ) {
		return null;
	}
	$file = rtrim( $dir, '/' ) . '/' . $percorso;
	return is_file( $file ) ? $file : null;
}

/** Allegato creato da un file di config/ con quel percorso e quell'impronta, se c'è ancora con il suo file; 0 se no. */
function wpf_allegato_cerca( string $percorso, string $impronta ): int {
	$ids = get_posts(
		[
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'meta_query'       => [ // phpcs:ignore WordPress.DB.SlowDBQuery
				[
					'key'   => WPF_META_FILE,
					'value' => $percorso,
				],
				[
					'key'   => WPF_META_IMPRONTA,
					'value' => $impronta,
				],
			],
		]
	);
	foreach ( $ids as $id ) {
		$file = get_attached_file( (int) $id );
		if ( $file && is_file( $file ) ) {
			return (int) $id;
		}
	}
	return 0;
}

/**
 * Crea l'allegato da un file (copiato: la Libreria media sposta quello che riceve). Con `$icona` genera anche le misure
 * dell'icona del sito (WP_Site_Icon, come il Customizer) e lo tiene fuori dalla griglia dei media come fa WordPress.
 * `$percorso` vuoto: nessun percorso registrato. Restituisce l'id, 0 se non riesce (con l'avviso).
 */
function wpf_allegato_importa( string $file, string $percorso, bool $icona ): int {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$tmp = wp_tempnam( basename( $file ) );
	if ( ! $tmp || ! copy( $file, $tmp ) ) {
		WP_CLI::warning( "allegato da {$file}: copia temporanea non riuscita" );
		return 0;
	}
	$misure = null;
	if ( $icona ) {
		require_once ABSPATH . 'wp-admin/includes/class-wp-site-icon.php';
		$misure = [ new WP_Site_Icon(), 'additional_sizes' ];
		add_filter( 'intermediate_image_sizes_advanced', $misure );
	}
	$id = media_handle_sideload(
		[
			'name'     => basename( $file ),
			'tmp_name' => $tmp,
		],
		0
	);
	if ( $misure ) {
		remove_filter( 'intermediate_image_sizes_advanced', $misure );
	}
	if ( is_wp_error( $id ) ) {
		if ( is_file( $tmp ) ) {
			unlink( $tmp );
		}
		WP_CLI::warning( "allegato da {$file} non creato: " . $id->get_error_message() );
		return 0;
	}
	if ( '' !== $percorso ) {
		update_post_meta( $id, WPF_META_FILE, $percorso );
		update_post_meta( $id, WPF_META_IMPRONTA, md5_file( $file ) );
	}
	if ( $icona ) {
		update_post_meta( $id, '_wp_attachment_context', 'site-icon' );
	}
	return (int) $id;
}

/**
 * Valore di sito.json per un'opzione con un allegato (WPF_OPZIONI_SITO_FILE): «@file:media/<nome>.<estensione>», con il
 * file originale dell'allegato copiato in `<dir>/media/`; 0 senza allegato o senza il suo file (con l'avviso). Con
 * `$scrivi` l'allegato riceve percorso e impronta, così l'apply dopo lo ritrova invece di crearne un altro (un'icona
 * caricata dal pannello non li ha).
 *
 * @return string|int
 */
function wpf_allegato_esporta( int $id, string $dir, string $nome, bool $scrivi ) {
	if ( $id <= 0 ) {
		return 0;
	}
	$file = wp_get_original_image_path( $id ) ?: get_attached_file( $id );
	if ( ! $file || ! is_file( $file ) ) {
		WP_CLI::warning( "allegato {$id}: il file non c'è, esportato come 0 (nessun allegato)" );
		return 0;
	}
	$estensione = strtolower( (string) pathinfo( $file, PATHINFO_EXTENSION ) );
	$percorso   = "media/{$nome}" . ( '' !== $estensione ? ".{$estensione}" : '' );
	wp_mkdir_p( rtrim( $dir, '/' ) . '/media' );
	if ( ! copy( $file, rtrim( $dir, '/' ) . '/' . $percorso ) ) {
		WP_CLI::warning( "allegato {$id}: copia in {$percorso} non riuscita, esportato come 0" );
		return 0;
	}
	if ( $scrivi ) {
		update_post_meta( $id, WPF_META_FILE, $percorso );
		update_post_meta( $id, WPF_META_IMPRONTA, md5_file( $file ) );
	}
	return WPF_FILE . $percorso;
}

/** Descrizione stabile di un allegato per il ritratto: il suo file di config/ e l'impronta, non l'id del sito. */
function wpf_allegato_descrivi( $id ): string {
	$id = (int) $id;
	if ( $id <= 0 ) {
		return '0';
	}
	$percorso = (string) get_post_meta( $id, WPF_META_FILE, true );
	return '' !== $percorso ? WPF_FILE . $percorso . ' (' . substr( (string) get_post_meta( $id, WPF_META_IMPRONTA, true ), 0, 8 ) . ')' : 'allegato senza file in config/';
}
