<?php
/**
 * Riquadro «Allegati e galleria» nell'editor delle pagine (oc:8758): gli stessi campi personalizzati
 * (wpf_allegati, wpf_galleria) che legge il template e che scrive la sincronizzazione da Drupal. È
 * l'equivalente dei campi «Allegati Media» e «Galleria» della pagina di Drupal, che la redazione gestisce
 * dalla libreria media. Il sottotitolo della testata è il Riassunto nativo della pagina.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const FORESTAS_RIQUADRO_NONCE = 'forestas_riquadro_pagina';

add_action(
	'add_meta_boxes_page',
	function () {
		add_meta_box( 'forestas-riquadro-pagina', 'Allegati e galleria', 'forestas_riquadro_pagina', 'page', 'normal', 'high' );
	}
);

/*
 * Il riquadro «Riassunto» (il sottotitolo della testata) è nascosto di default da WordPress: sulle pagine
 * si mostra, se no la redazione non lo trova. Vale per chi non ha cambiato le «Impostazioni schermata».
 */
add_filter(
	'default_hidden_meta_boxes',
	function ( array $nascosti, WP_Screen $schermata ): array {
		return $schermata->post_type === 'page' ? array_values( array_diff( $nascosti, [ 'postexcerpt' ] ) ) : $nascosti;
	},
	10,
	2
);

/** Elenco di id di allegati da un meta, in ordine. */
function forestas_meta_ids( int $id, string $chiave ): array {
	return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, $chiave, true ) ) ) );
}

/** Una voce dell'elenco del riquadro: miniatura o icona, nome, pulsante per toglierla. */
function forestas_riquadro_voce( int $id, string $nome_campo ): string {
	$anteprima = wp_attachment_is_image( $id )
		? wp_get_attachment_image( $id, [ 60, 60 ] )
		: '<span class="dashicons dashicons-media-document" aria-hidden="true"></span>';
	return sprintf(
		'<li class="forestas-riquadro-voce" data-id="%1$d"><span class="forestas-riquadro-trascina dashicons dashicons-menu" aria-hidden="true"></span>%2$s<span class="forestas-riquadro-nome">%3$s</span><input type="hidden" name="%4$s[]" value="%1$d"><button type="button" class="button-link forestas-riquadro-togli">%5$s</button></li>',
		$id,
		$anteprima,
		esc_html( get_the_title( $id ) ?: basename( (string) get_attached_file( $id ) ) ),
		esc_attr( $nome_campo ),
		esc_html( 'Togli' )
	);
}

function forestas_riquadro_pagina( WP_Post $post ): void {
	wp_nonce_field( FORESTAS_RIQUADRO_NONCE, FORESTAS_RIQUADRO_NONCE );
	// L'avviso serve solo finché la sincronizzazione scrive davvero: DRUPAL_SYNC arriva dal .env di
	// wp-forestas nell'ambiente del container, e Apache lo vede (verificato con getenv)
	if ( get_post_meta( $post->ID, '_wpf_pagina', true ) !== '' && getenv( 'DRUPAL_SYNC' ) === 'on' ) {
		echo '<p class="notice notice-warning inline" style="padding:8px 12px">Questa pagina è ancora collegata al vecchio sito: testo, riassunto, allegati e galleria vengono aggiornati da lì in automatico, e le modifiche fatte qui si perdono.</p>';
	}
	foreach ( [
		'wpf_allegati' => [ 'Allegati', 'Aggiungi dalla libreria media', '', 'Documenti mostrati sotto il testo, con formato e dimensione. Si riordinano trascinandoli.' ],
		'wpf_galleria' => [ 'Galleria', 'Aggiungi immagini', 'image', 'Immagini mostrate in griglia sotto il testo. Si riordinano trascinandole.' ],
	] as $campo => [ $titolo, $pulsante, $tipo, $aiuto ] ) {
		$voci = implode( '', array_map( fn( $id ) => forestas_riquadro_voce( $id, $campo ), forestas_meta_ids( $post->ID, $campo ) ) );
		printf(
			'<div class="forestas-riquadro-elenco" data-campo="%1$s" data-tipo="%2$s"><p><strong>%3$s</strong></p><ul>%4$s</ul><input type="hidden" name="%1$s_presente" value="1"><button type="button" class="button forestas-riquadro-aggiungi">%5$s</button> <span class="description">%6$s</span></div>',
			esc_attr( $campo ),
			esc_attr( $tipo ),
			esc_html( $titolo ),
			$voci,
			esc_html( $pulsante ),
			esc_html( $aiuto )
		);
	}
}

add_action(
	'save_post_page',
	function ( int $id ) {
		if ( ! isset( $_POST[ FORESTAS_RIQUADRO_NONCE ] )
			|| ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ FORESTAS_RIQUADRO_NONCE ] ) ), FORESTAS_RIQUADRO_NONCE )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		foreach ( [ 'wpf_allegati', 'wpf_galleria' ] as $campo ) {
			if ( empty( $_POST[ "{$campo}_presente" ] ) ) {
				continue; // riquadro non inviato (per esempio salvataggio rapido): il campo non si tocca
			}
			$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $_POST[ $campo ] ?? [] ) ), fn( $a ) => get_post_type( $a ) === 'attachment' ) ) );
			$ids ? update_post_meta( $id, $campo, $ids ) : delete_post_meta( $id, $campo );
		}
	}
);

add_action(
	'admin_enqueue_scripts',
	function ( string $pagina ) {
		if ( ! in_array( $pagina, [ 'post.php', 'post-new.php' ], true ) || get_current_screen()->post_type !== 'page' ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'forestas-riquadro-pagina', get_stylesheet_directory_uri() . '/assets/js/riquadro-pagina.js', [ 'jquery', 'jquery-ui-sortable' ], forestas_versione(), true );
		wp_add_inline_style(
			'common',
			'.forestas-riquadro-elenco ul{margin:0 0 8px}.forestas-riquadro-voce{display:flex;align-items:center;gap:10px;padding:6px 8px;border:1px solid #dcdcde;background:#fff;margin-bottom:4px}'
			. '.forestas-riquadro-voce img{width:40px;height:40px;object-fit:cover}.forestas-riquadro-nome{flex:1}.forestas-riquadro-trascina{cursor:move;color:#8c8f94}'
		);
	}
);

/*
 * Campi «Autore» e «Credits» nella scheda di un'immagine della libreria media, come i campi omonimi dei
 * media di Drupal: la testata li mostra dietro il pulsante «Credits foto» (oc:8758).
 */
const FORESTAS_CAMPI_CREDITS = [
	'wpf_autore'  => 'Autore',
	'wpf_credits' => 'Credits',
];

add_filter(
	'attachment_fields_to_edit',
	function ( array $campi, WP_Post $allegato ): array {
		if ( ! wp_attachment_is_image( $allegato->ID ) ) {
			return $campi;
		}
		foreach ( FORESTAS_CAMPI_CREDITS as $chiave => $etichetta ) {
			$campi[ $chiave ] = [
				'label' => $etichetta,
				'input' => 'text',
				'value' => (string) get_post_meta( $allegato->ID, $chiave, true ),
			];
		}
		return $campi;
	},
	10,
	2
);

add_filter(
	'attachment_fields_to_save',
	function ( array $allegato, array $valori ): array {
		foreach ( array_keys( FORESTAS_CAMPI_CREDITS ) as $chiave ) {
			if ( ! array_key_exists( $chiave, $valori ) ) {
				continue;
			}
			$valore = sanitize_text_field( $valori[ $chiave ] );
			$valore !== '' ? update_post_meta( (int) $allegato['ID'], $chiave, $valore ) : delete_post_meta( (int) $allegato['ID'], $chiave );
		}
		return $allegato;
	},
	10,
	2
);

// Caselle di Autore e Credits larghe quanto gli altri campi della scheda dell'immagine
add_action(
	'admin_enqueue_scripts',
	function () {
		wp_add_inline_style( 'common', '.compat-attachment-fields{width:100%}.compat-field-wpf_autore input.text,.compat-field-wpf_credits input.text{width:100%;min-width:30em}' );
	}
);
