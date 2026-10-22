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

/*
 * Vero finché la sincronizzazione da Drupal scrive davvero: DRUPAL_SYNC arriva dal .env di wp-forestas
 * nell'ambiente del container, e Apache lo vede (verificato con getenv). Lo stesso controllo è in
 * docker/scripts/config/pagine.php.
 */
function forestas_sincronizzazione_accesa(): bool {
	return getenv( 'DRUPAL_SYNC' ) === 'on';
}

/*
 * Immagini delle pagine sincronizzate (in evidenza, galleria e allegati che sono immagini): quelle che la
 * sincronizzazione riallinea.
 */
function forestas_immagini_sincronizzate(): array {
	static $ids = null;
	if ( $ids === null ) {
		$ids = [];
		$pagine = get_posts( [ 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => FORESTAS_META_PAGINA, 'suppress_filters' => true ] );
		foreach ( $pagine as $pagina ) {
			$ids[] = (int) get_post_thumbnail_id( $pagina );
			array_push( $ids, ...forestas_meta_ids( $pagina, FORESTAS_META_GALLERIA ), ...forestas_meta_ids( $pagina, FORESTAS_META_ALLEGATI ) );
		}
		$ids = array_values( array_unique( array_filter( $ids ) ) );
	}
	return $ids;
}

/** Una voce dell'elenco del riquadro: miniatura o icona, nome, pulsante per toglierla. */
function forestas_riquadro_voce( int $id, string $nome_campo ): string {
	$anteprima = wp_attachment_is_image( $id )
		? wp_get_attachment_image( $id, 'thumbnail' )
		: '<span class="dashicons dashicons-media-document" aria-hidden="true"></span>';
	return sprintf(
		'<li class="forestas-riquadro-voce" data-id="%1$d"><span class="forestas-riquadro-trascina dashicons dashicons-menu" aria-hidden="true"></span>%2$s<span class="forestas-riquadro-nome">%3$s</span><input type="hidden" name="%4$s[]" value="%1$d"><button type="button" class="button-link forestas-riquadro-togli">%5$s</button></li>',
		$id,
		$anteprima,
		esc_html( get_the_title( $id ) ?: basename( (string) get_attached_file( $id ) ) ),
		esc_attr( $nome_campo ),
		'Togli'
	);
}

function forestas_riquadro_pagina( WP_Post $post ): void {
	wp_nonce_field( FORESTAS_RIQUADRO_NONCE, FORESTAS_RIQUADRO_NONCE );
	if ( get_post_meta( $post->ID, FORESTAS_META_PAGINA, true ) !== '' && forestas_sincronizzazione_accesa() ) {
		echo '<p class="notice notice-warning inline forestas-riquadro-avviso">Questa pagina è ancora collegata al vecchio sito: testo, riassunto, allegati e galleria vengono aggiornati da lì in automatico, e le modifiche fatte qui si perdono.</p>';
	} elseif ( md5( $post->post_content ) === get_post_meta( $post->ID, FORESTAS_META_TESTO_DRUPAL, true ) ) {
		// Il testo si mostra così com'è finché coincide con quello importato ([forestas_pagina_testo]): un
		// salvataggio dall'editor lo riscrive, e da lì passa per i filtri di WordPress
		echo '<p class="notice notice-info inline forestas-riquadro-avviso">Il testo è ancora quello importato dal vecchio sito e si mostra così com\'è. Se un salvataggio lo cambia (anche solo per aggiungere un allegato: l\'editor può riscriverne l\'HTML), da lì passa per la formattazione di WordPress (paragrafi, virgolette): controlla la pagina dopo aver salvato.</p>';
	}
	foreach ( [
		FORESTAS_META_ALLEGATI => [ 'Allegati', 'Aggiungi dalla libreria media', '', 'Documenti mostrati sotto il testo, con formato e dimensione. Si riordinano trascinandoli.' ],
		FORESTAS_META_GALLERIA => [ 'Galleria', 'Aggiungi immagini', 'image', 'Immagini mostrate in griglia sotto il testo. Si riordinano trascinandole.' ],
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
		foreach ( [ FORESTAS_META_ALLEGATI, FORESTAS_META_GALLERIA ] as $campo ) {
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
		// Caselle di Autore e Credits larghe quanto gli altri campi della scheda dell'immagine, che si apre
		// anche dalla libreria media: in tutte le pagine del pannello
		wp_add_inline_style( 'common', '.compat-attachment-fields{width:100%}.compat-field-' . FORESTAS_META_AUTORE . ' input.text,.compat-field-' . FORESTAS_META_CREDITS . ' input.text{width:100%;min-width:30em}' );
		if ( ! in_array( $pagina, [ 'post.php', 'post-new.php' ], true ) || get_current_screen()->post_type !== 'page' ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'forestas-riquadro-pagina', get_stylesheet_directory_uri() . '/assets/js/riquadro-pagina.js', [ 'jquery', 'jquery-ui-sortable' ], forestas_versione(), true );
		wp_add_inline_style(
			'common',
			'.forestas-riquadro-avviso{padding:8px 12px}.forestas-riquadro-elenco ul{margin:0 0 8px}.forestas-riquadro-voce{display:flex;align-items:center;gap:10px;padding:6px 8px;border:1px solid #dcdcde;background:#fff;margin-bottom:4px}'
			. '.forestas-riquadro-voce img{width:40px;height:40px;object-fit:cover}.forestas-riquadro-nome{flex:1}.forestas-riquadro-trascina{cursor:move;color:#8c8f94}'
		);
	}
);

/*
 * Campi «Autore» e «Credits» nella scheda di un'immagine della libreria media, come i campi omonimi dei
 * media di Drupal: la testata li mostra dietro il pulsante «Credits foto» (oc:8758). Su un'immagine che
 * una pagina sincronizzata usa ancora e con la sincronizzazione accesa, un avviso dice che testo
 * alternativo, autore e credits si riallineano a Drupal a ogni giro (wpf_media_allinea, solo per i file
 * delle pagine del perimetro).
 */
const FORESTAS_CAMPI_CREDITS = [
	FORESTAS_META_AUTORE  => 'Autore',
	FORESTAS_META_CREDITS => 'Credits',
];

add_filter(
	'attachment_fields_to_edit',
	function ( array $campi, WP_Post $allegato ): array {
		if ( ! wp_attachment_is_image( $allegato->ID ) ) {
			return $campi;
		}
		$da_drupal = forestas_sincronizzazione_accesa() && in_array( $allegato->ID, forestas_immagini_sincronizzate(), true );
		foreach ( FORESTAS_CAMPI_CREDITS as $chiave => $etichetta ) {
			$campi[ $chiave ] = [
				'label' => $etichetta,
				'input' => 'text',
				'value' => (string) get_post_meta( $allegato->ID, $chiave, true ),
			];
		}
		if ( $da_drupal ) {
			$campi[ FORESTAS_META_CREDITS ]['helps'] = 'Immagine del vecchio sito: testo alternativo, autore e credits vengono aggiornati da lì in automatico, e le modifiche fatte qui si perdono.';
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
