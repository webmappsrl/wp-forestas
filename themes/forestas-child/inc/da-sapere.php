<?php
/**
 * Come si mostrano i «da sapere» (oc:8760): la pagina del contenuto, le pillole e l'elenco delle FAQ delle
 * schede di Da sapere (Canvas Fase 2, tavola 8), la pagina di una tipologia. L'import è
 * docker/scripts/config/da-sapere-lib.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Term meta delle tipologie scritti dall'import (WPF_TERM_META_* di da-sapere-lib.php)
const FORESTAS_TERM_META_IN_LISTA = 'wpf_mostra_in_lista'; // «1»: tipologia fra le pillole
const FORESTAS_TERM_META_PESO     = 'wpf_peso';            // ordine delle pillole
const FORESTAS_TERM_META_IMMAGINE = 'wpf_immagine';        // id dell'immagine della testata della tipologia
// Contenuti per pagina della pagina di una tipologia (termine_tassonomia_da_sapere di Drupal: 10)
const FORESTAS_TIPOLOGIA_PER_PAGINA = 10;

/** Tipologia di un «da sapere», o null. */
function forestas_da_sapere_tipologia( int $id ): ?WP_Term {
	$termini = get_the_terms( $id, 'tipologia_da_sapere' );
	return is_array( $termini ) && $termini ? $termini[0] : null;
}

/*
 * Pagina del «da sapere»: su Drupal il tipo non ha un template suo, quindi il layout predefinito del nodo
 * dentro la testata delle pagine (core.entity_view_display.node.da_sapere.full): tag, testo, allegati,
 * galleria. Come per le news, i tag stanno sopra il testo.
 */
add_shortcode(
	'forestas_da_sapere',
	function () {
		static $in_corso = false;
		$id = get_the_ID();
		if ( ! $id || $in_corso ) {
			return '';
		}
		$in_corso = true;
		$html     = do_shortcode( '[forestas_testata]' )
			. '<div class="forestas-pagina-testo forestas-news-corpo"' . forestas_attributo_lingua( $id ) . '>' . forestas_testo_importato( $id ) . forestas_allegati( $id ) . forestas_galleria( $id ) . '</div>';
		$in_corso = false;
		return $html;
	}
);

/*
 * Pillole di sentieristica (wireframe, tavola 8; da_sapere_le_altre_categorie di Drupal): le tipologie con
 * la spunta «Mostra in lista Da sapere», in ordine di peso, ciascuna verso la sua pagina. La spunta si
 * cambia dalla schermata del termine.
 */
function forestas_da_sapere_pillole( int $livello = 3 ): string {
	$termini = get_terms(
		[
			'taxonomy'   => 'tipologia_da_sapere',
			'hide_empty' => false,
			'meta_query' => [ [ 'key' => FORESTAS_TERM_META_IN_LISTA, 'value' => '1' ] ],
		]
	);
	if ( ! is_array( $termini ) || ! $termini ) {
		return '';
	}
	usort( $termini, fn( $a, $b ) => (int) get_term_meta( $a->term_id, FORESTAS_TERM_META_PESO, true ) <=> (int) get_term_meta( $b->term_id, FORESTAS_TERM_META_PESO, true ) );
	$voci = '';
	foreach ( $termini as $t ) {
		$voci .= sprintf( '<li><h%3$d class="forestas-pillola"><a href="%1$s"><i class="fal fa-book-open" aria-hidden="true"></i>%2$s</a></h%3$d></li>', esc_url( get_term_link( $t ) ), esc_html( $t->name ), $livello );
	}
	return '<ul class="forestas-pillole" role="list">' . $voci . '</ul>';
}

/*
 * Campi della tipologia nella schermata del termine, come il form del termine di Drupal (nome e
 * descrizione sono quelli nativi): l'immagine della testata della sua pagina, la spunta «Mostra in lista
 * Da sapere» (le pillole) e l'ordine fra le pillole (il peso, che su Drupal si cambia trascinando i
 * termini nell'elenco del vocabolario).
 */
add_action(
	'tipologia_da_sapere_edit_form_fields',
	function ( WP_Term $termine ) {
		wp_enqueue_media();
		wp_nonce_field( 'forestas_tipologia', 'forestas_tipologia_nonce' );
		// Come per i contenuti (forestas_riquadro_avviso): con la sincronizzazione accesa i valori di Drupal vincono
		if ( forestas_sincronizzazione_accesa() && get_term_meta( $termine->term_id, FORESTAS_TERM_META_DRUPAL, true ) !== '' ) {
			printf(
				'<tr class="form-field"><td colspan="2"><p class="notice notice-info inline">%s</p></td></tr>',
				esc_html( forestas_testo( 'Questa tipologia è ancora collegata al vecchio sito: nome, descrizione, immagine, spunta delle pillole e ordine vengono aggiornati da lì in automatico, e le modifiche fatte qui si perdono.', 'This type is still linked to the old site: name, description, image, tips checkbox and order are updated from there automatically, and changes made here are lost.' ) )
			);
		}
		$immagine = (int) get_term_meta( $termine->term_id, FORESTAS_TERM_META_IMMAGINE, true );
		printf(
			'<tr class="form-field"><th scope="row">%1$s</th><td><div class="forestas-termine-immagine">%2$s</div><input type="hidden" name="forestas_immagine" value="%3$s"><p><button type="button" class="button forestas-termine-scegli">%4$s</button> <button type="button" class="button-link forestas-termine-togli"%5$s>%6$s</button></p><p class="description">%7$s</p></td></tr>',
			esc_html( forestas_testo( 'Immagine', 'Image' ) ),
			$immagine ? wp_get_attachment_image( $immagine, 'medium' ) : '',
			$immagine ? (string) $immagine : '',
			esc_html( forestas_testo( 'Scegli immagine', 'Choose image' ) ),
			$immagine ? '' : ' hidden',
			esc_html( forestas_testo( 'Togli', 'Remove' ) ),
			esc_html( forestas_testo( 'Sfondo della testata nella pagina della tipologia.', 'Header background of the type page.' ) )
		);
		printf(
			'<tr class="form-field"><th scope="row">%1$s</th><td><label><input type="checkbox" name="forestas_in_lista" value="1"%2$s> %3$s</label></td></tr>',
			esc_html( forestas_testo( 'Pillole di sentieristica', 'Hiking tips' ) ),
			checked( get_term_meta( $termine->term_id, FORESTAS_TERM_META_IN_LISTA, true ), '1', false ),
			esc_html( forestas_testo( 'Mostra la tipologia fra le pillole della pagina Da sapere', 'Show this type among the tips of the Good to know page' ) )
		);
		printf(
			'<tr class="form-field"><th scope="row"><label for="forestas-peso">%1$s</label></th><td><input type="number" id="forestas-peso" name="forestas_peso" value="%2$s" step="1" style="width:6em"><p class="description">%3$s</p></td></tr>',
			esc_html( forestas_testo( 'Ordine', 'Order' ) ),
			esc_attr( (string) (int) get_term_meta( $termine->term_id, FORESTAS_TERM_META_PESO, true ) ),
			esc_html( forestas_testo( 'Posizione fra le pillole: prima i numeri più bassi.', 'Position among the tips: lower numbers first.' ) )
		);
		?>
		<script>
		( function () {
			var riga = document.querySelector( '.forestas-termine-immagine' ).closest( 'td' );
			var campo = riga.querySelector( '[name="forestas_immagine"]' );
			var anteprima = riga.querySelector( '.forestas-termine-immagine' );
			var togli = riga.querySelector( '.forestas-termine-togli' );
			var finestra;
			riga.querySelector( '.forestas-termine-scegli' ).addEventListener( 'click', function () {
				finestra = finestra || wp.media( { library: { type: 'image' }, multiple: false } );
				finestra.off( 'select' ).on( 'select', function () {
					var scelta = finestra.state().get( 'selection' ).first().toJSON();
					var url = ( scelta.sizes && scelta.sizes.medium ? scelta.sizes.medium : scelta ).url;
					campo.value = scelta.id;
					anteprima.innerHTML = '';
					var img = document.createElement( 'img' );
					img.src = url;
					img.alt = '';
					anteprima.appendChild( img );
					togli.hidden = false;
				} );
				finestra.open();
			} );
			togli.addEventListener( 'click', function () {
				campo.value = '';
				anteprima.innerHTML = '';
				togli.hidden = true;
			} );
		} )();
		</script>
		<?php
	}
);
add_action(
	'edited_tipologia_da_sapere',
	function ( int $id ) {
		if ( ! isset( $_POST['forestas_tipologia_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['forestas_tipologia_nonce'] ), 'forestas_tipologia' ) ) {
			return;
		}
		empty( $_POST['forestas_in_lista'] ) ? delete_term_meta( $id, FORESTAS_TERM_META_IN_LISTA ) : update_term_meta( $id, FORESTAS_TERM_META_IN_LISTA, '1' );
		update_term_meta( $id, FORESTAS_TERM_META_PESO, (string) (int) ( $_POST['forestas_peso'] ?? 0 ) );
		$immagine = absint( $_POST['forestas_immagine'] ?? 0 );
		$immagine && wp_attachment_is_image( $immagine ) ? update_term_meta( $id, FORESTAS_TERM_META_IMMAGINE, (string) $immagine ) : delete_term_meta( $id, FORESTAS_TERM_META_IMMAGINE );
	}
);

/*
 * Pagina di una tipologia (/tipologia-da-sapere-argomenti/<termine>, termine_tassonomia_da_sapere di
 * Drupal): 10 per pagina, prima quelli «Sempre in cima» (menu_order), poi i più recenti per data di
 * creazione su Drupal, come la View (sticky, created). Il template è taxonomy-tipologia_da_sapere.php.
 */
add_action(
	'pre_get_posts',
	function ( WP_Query $q ) {
		if ( ! is_admin() && $q->is_main_query() && $q->is_tax( 'tipologia_da_sapere' ) ) {
			$q->set( 'post_type', 'da_sapere' );
			$q->set( 'posts_per_page', FORESTAS_TIPOLOGIA_PER_PAGINA );
			$q->set( 'orderby', [ 'menu_order' => 'DESC', 'date' => 'DESC', 'ID' => 'ASC' ] );
		}
	}
);
