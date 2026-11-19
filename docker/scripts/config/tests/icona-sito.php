<?php
/**
 * Test (oc:9000): icona del sito dallo script di configurazione (allegati.php, wpf_sito_applica()). Lavora su una cartella
 * di config temporanea con PNG generati qui, non su config/ del repo.
 * - `site_icon: "@file:media/<file>"` in prova: una differenza, nessun allegato creato, opzione invariata;
 * - con la conferma: allegato creato con percorso e impronta del file, contesto `site-icon` e misura da 32 pixel,
 *   `site_icon` impostato; secondo giro: nessuna differenza, nessun allegato in più;
 * - file cambiato: allegato nuovo; file mancante o fuori da `media/`: avviso e falso;
 * - export: `@file:media/icona-sito.png` con il file copiato; un'icona caricata dal pannello (senza percorso) si esporta
 *   con lo stesso nome e, quando l'export scrive, l'apply dopo la ritrova; senza icona 0;
 * - `site_icon` fra le impostazioni del sito e fra le copie di Impreza (non si esporta né si applica da impreza.json).
 * Rimette `site_icon` com'era e cancella allegati e cartelle di prova; gli allegati veri dell'icona (percorso
 * `media/icona-sito.png`, creati dall'apply del sito) restano.
 * Si lancia con: docker exec wordpress-forestas wp --allow-root eval-file /usr/local/lib/wp-forestas/tests/icona-sito.php
 * (docker/scripts/config/ è montato in /usr/local/lib/wp-forestas). Stampa «PASS» o «FAIL: …» ed esce con 0 o 1.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once dirname( __DIR__ ) . '/comune.php';
require_once dirname( __DIR__ ) . '/opzioni.php';
require_once dirname( __DIR__ ) . '/allegati.php';

$failures = [];
$check    = function ( bool $ok, string $what ) use ( &$failures ) {
	if ( ! $ok ) {
		$failures[] = $what;
	}
};

$prima   = get_option( 'site_icon' );
$dir     = sys_get_temp_dir() . '/wpf-icona-' . getmypid();
$creati  = [];
$righe   = [];
$diff    = function ( string $etichetta ) use ( &$righe ) {
	$righe[] = $etichetta;
};
$risolvi = fn( $valore ) => null;
$png     = function ( string $file, array $rgb ) {
	$im = imagecreatetruecolor( 64, 64 );
	imagefill( $im, 0, 0, imagecolorallocate( $im, $rgb[0], $rgb[1], $rgb[2] ) );
	imagepng( $im, $file );
};
$allegati = fn( string $percorso ) => array_map(
	'intval',
	get_posts(
		[
			'post_type'        => 'attachment',
			'post_status'      => 'inherit',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => true,
			'meta_key'         => WPF_META_FILE, // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'       => $percorso, // phpcs:ignore WordPress.DB.SlowDBQuery
		]
	)
);
$applica = function ( string $valore, bool $prova, ?string $cartella = null ) use ( &$righe, $diff, $risolvi, $dir ) {
	$righe = [];
	return wpf_sito_applica( [ 'site_icon' => $valore ], $prova, $diff, $risolvi, $cartella ?? $dir );
};

// Allegati veri dell'icona (creati dall'apply del sito): il test non li tocca e non li cancella
$esistenti = $allegati( 'media/icona-sito.png' );

try {
	wp_mkdir_p( "{$dir}/media" );
	$png( "{$dir}/media/icona-prova.png", [ 210, 80, 20 ] );
	update_option( 'site_icon', 0 );
	$valore = WPF_FILE . 'media/icona-prova.png';

	// 1. In prova: una differenza, niente scritto
	$ok = $applica( $valore, true );
	$check( $ok && [ 'sito: site_icon' ] === $righe, 'prova: una differenza, ' . wp_json_encode( $righe ) );
	$check( [] === $allegati( 'media/icona-prova.png' ) && 0 === (int) get_option( 'site_icon' ), 'prova: nessun allegato, opzione invariata' );

	// 2. Con la conferma: allegato con percorso, impronta e contesto dell'icona, opzione impostata
	$ok     = $applica( $valore, false );
	$ids    = $allegati( 'media/icona-prova.png' );
	$creati = array_merge( $creati, $ids );
	$id     = $ids[0] ?? 0;
	$check( $ok && 1 === count( $ids ) && (int) get_option( 'site_icon' ) === $id, 'conferma: un allegato, site_icon impostato' );
	$check( md5_file( "{$dir}/media/icona-prova.png" ) === get_post_meta( $id, WPF_META_IMPRONTA, true ), 'conferma: impronta del file' );
	$check( 'site-icon' === get_post_meta( $id, '_wp_attachment_context', true ), 'conferma: contesto site-icon (fuori dalla griglia dei media)' );
	$check( isset( wp_get_attachment_metadata( $id )['sizes']['site_icon-32'] ) && '' !== get_site_icon_url( 32 ), 'conferma: misura da 32 pixel dell\'icona' );

	// 3. Secondo giro: niente da fare
	$ok = $applica( $valore, false );
	$check( $ok && [] === $righe && 1 === count( $allegati( 'media/icona-prova.png' ) ), 'secondo giro: nessuna differenza, nessun allegato in più' );

	// 4. File cambiato: allegato nuovo
	$png( "{$dir}/media/icona-prova.png", [ 20, 120, 40 ] );
	$ok     = $applica( $valore, false );
	$ids    = $allegati( 'media/icona-prova.png' );
	$creati = array_unique( array_merge( $creati, $ids ) );
	$check( $ok && 2 === count( $ids ) && (int) get_option( 'site_icon' ) === max( $ids ), 'file cambiato: allegato nuovo' );

	// 5. File mancante o fuori da media/: avviso e falso, opzione invariata
	$attuale = (int) get_option( 'site_icon' );
	$check( false === $applica( WPF_FILE . 'media/manca.png', false ) && $attuale === (int) get_option( 'site_icon' ), 'file mancante: falso' );
	$check( false === $applica( WPF_FILE . '../sito.json', false ) && $attuale === (int) get_option( 'site_icon' ), 'percorso fuori da media/: falso' );

	// 6. Export: valore e file
	$export = "{$dir}/export";
	$check( WPF_FILE . 'media/icona-sito.png' === wpf_allegato_esporta( $attuale, $export, 'icona-sito', false ), 'export: valore @file' );
	$check( is_file( "{$export}/media/icona-sito.png" ) && md5_file( "{$export}/media/icona-sito.png" ) === md5_file( "{$dir}/media/icona-prova.png" ), 'export: file copiato' );
	$check( 0 === wpf_allegato_esporta( 0, $export, 'icona-sito', false ), 'export senza icona: 0' );

	// 7. Icona caricata dal pannello: senza percorso; l'export le dà il nome, e quando scrive l'apply dopo la ritrova
	$png( "{$dir}/pannello.png", [ 30, 60, 200 ] );
	$pannello = wpf_allegato_importa( "{$dir}/pannello.png", '', true );
	$creati[] = $pannello;
	delete_post_meta( $pannello, WPF_META_FILE );
	delete_post_meta( $pannello, WPF_META_IMPRONTA );
	update_option( 'site_icon', $pannello );
	$export2 = "{$dir}/export2";
	$v       = wpf_allegato_esporta( $pannello, $export2, 'icona-sito', true );
	$check( WPF_FILE . 'media/icona-sito.png' === $v && 'media/icona-sito.png' === get_post_meta( $pannello, WPF_META_FILE, true ), 'icona dal pannello: esportata con il nome dell\'icona' );
	$ok = $applica( $v, false, $export2 );
	$check( $ok && [] === $righe && $pannello === (int) get_option( 'site_icon' ), 'icona dal pannello: l\'apply la ritrova' );
	$creati = array_unique( array_merge( $creati, array_diff( $allegati( 'media/icona-sito.png' ), $esistenti ) ) );

	// 8. Impostazioni del sito e copie di Impreza
	$check( in_array( 'site_icon', WPF_OPZIONI_SITO, true ) && in_array( 'site_icon', WPF_OPZIONI_SITO_FILE, true ), 'site_icon fra le impostazioni del sito, con un file' );
	$check( in_array( 'site_icon', WPF_IMPREZA_COPIE, true ), 'site_icon di Impreza è una copia: non sta in impreza.json' );
} finally {
	false === $prima ? delete_option( 'site_icon' ) : update_option( 'site_icon', $prima );
	foreach ( array_diff( array_unique( $creati ), $esistenti ) as $id ) {
		wp_delete_attachment( (int) $id, true );
	}
	if ( is_dir( $dir ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}
}

if ( $failures ) {
	echo 'FAIL: ' . implode( '; ', $failures ) . "\n";
	exit( 1 );
}
echo "PASS\n";
