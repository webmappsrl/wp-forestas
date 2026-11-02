<?php
/**
 * Come si mostrano gli eventi (oc:8760): la data grande delle card, il luogo e le date della pagina
 * dell'evento (evento--full di Drupal). L'import è docker/scripts/config/eventi-lib.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Meta scritti dall'import e dal riquadro dell'editor (WPF_META_EVENTO_* di eventi-lib.php)
const FORESTAS_META_EVENTO_INIZIO = 'wpf_evento_inizio'; // AAAA-MM-GG
const FORESTAS_META_EVENTO_FINE   = 'wpf_evento_fine';   // AAAA-MM-GG, solo se l'evento dura più giorni
const FORESTAS_META_EVENTO_LUOGO  = 'wpf_evento_luogo';  // [ organization, address_line1, address_line2, postal_code, locality, administrative_area, country_code ]

/** Timestamp di una data AAAA-MM-GG del meta, a mezzogiorno nel fuso del sito (nessun giorno spostato), o 0. */
function forestas_evento_giorno( string $data ): int {
	$d = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', "{$data} 12:00", wp_timezone() );
	return $d ? $d->getTimestamp() : 0;
}

/** Inizio e fine di un evento: [ inizio, fine ] come timestamp (0 se manca). */
function forestas_evento_date( int $id ): array {
	return [
		forestas_evento_giorno( (string) get_post_meta( $id, FORESTAS_META_EVENTO_INIZIO, true ) ),
		forestas_evento_giorno( (string) get_post_meta( $id, FORESTAS_META_EVENTO_FINE, true ) ),
	];
}

/*
 * Data per intero come su Drupal («30 giugno 2026»), o l'intervallo «Dal 30 giugno 2026 al 2 luglio 2026»
 * (evento--full: «From … to …», tradotti da Drupal in «Dal» e «al»).
 */
function forestas_evento_quando( int $id ): string {
	[ $inizio, $fine ] = forestas_evento_date( $id );
	if ( ! $inizio ) {
		return '';
	}
	$giorno = fn( int $t ) => sprintf( '<time datetime="%s">%s</time>', esc_attr( wp_date( 'Y-m-d', $t ) ), esc_html( wp_date( 'j F Y', $t ) ) );
	return $fine && $fine !== $inizio
		? esc_html( forestas_testo( 'Dal', 'From' ) ) . ' ' . $giorno( $inizio ) . ' ' . esc_html( forestas_testo( 'al', 'to' ) ) . ' ' . $giorno( $fine )
		: $giorno( $inizio );
}

/*
 * La data della card dell'evento (Canvas, .uc-date): un riquadro bianco sulla foto, in alto a sinistra, con il
 * giorno grande e il mese abbreviato sotto. Si vede ma il lettore di schermo non lo legge: la data per intero
 * è sotto il titolo (WCAG 1.3.1).
 */
function forestas_evento_data( int $id ): string {
	[ $inizio ] = forestas_evento_date( $id );
	if ( ! $inizio ) {
		return '';
	}
	return sprintf( '<span class="forestas-card__giorno" aria-hidden="true"><b>%1$s</b>%2$s</span>', esc_html( wp_date( 'j', $inizio ) ), esc_html( wp_date( 'M', $inizio ) ) );
}

/*
 * Pagina dell'evento, con il layout di Drupal (node--evento--full.html.twig): la testata delle pagine, poi
 * «Dove» e «Quando» affiancati, testo, allegati, collegamenti, galleria.
 */
add_shortcode(
	'forestas_evento',
	function () {
		static $in_corso = false;
		$id = get_the_ID();
		if ( ! $id || $in_corso ) {
			return '';
		}
		$in_corso = true;
		$info     = '';
		$luogo    = array_filter( (array) get_post_meta( $id, FORESTAS_META_EVENTO_LUOGO, true ) );
		// Il solo paese non fa un luogo: si vedrebbe «Dove» senza testo, perché il paese è nascosto (evento 1476)
		if ( array_diff_key( $luogo, [ 'country_code' => true ] ) ) {
			// Le righe come il formatter dell'indirizzo di Drupal (formato italiano): organizzazione, via, seconda
			// riga, CAP e località con la provincia
			$righe = array_filter(
				[
					$luogo['organization'] ?? '',
					$luogo['address_line1'] ?? '',
					$luogo['address_line2'] ?? '',
					trim( ( $luogo['postal_code'] ?? '' ) . ' ' . ( $luogo['locality'] ?? '' ) . ( ! empty( $luogo['administrative_area'] ) ? ' ' . $luogo['administrative_area'] : '' ) ),
				]
			);
			// Il paese è nel markup come su Drupal, che però non lo mostra (.country nascosto dal tema)
			$paese = ( $luogo['country_code'] ?? '' ) === 'IT' ? forestas_testo( 'Italia', 'Italy' ) : (string) ( $luogo['country_code'] ?? '' );
			$info .= forestas_evento_info(
				'fa-map-marker-alt',
				forestas_testo( 'Dove', 'Where' ),
				implode( '<br>', array_map( 'esc_html', $righe ) ) . ( $paese !== '' ? '<span class="forestas-evento-info__paese"><br>' . esc_html( $paese ) . '</span>' : '' )
			);
		}
		$quando = forestas_evento_quando( $id );
		if ( $quando !== '' ) {
			$info .= forestas_evento_info( 'fa-calendar-edit', forestas_testo( 'Quando', 'When' ), $quando );
		}
		$html     = do_shortcode( '[forestas_testata]' )
			. forestas_corpo_contenuto( $id, $info !== '' ? '<div class="forestas-evento-info">' . $info . '</div>' : '', 'forestas-evento-corpo' );
		$in_corso = false;
		return $html;
	}
);

/** Un riquadro «Dove» o «Quando» (sase-content-info di Drupal): icona, titolo, testo già pronto. */
function forestas_evento_info( string $icona, string $titolo, string $testo ): string {
	return sprintf(
		'<section class="forestas-evento-info__voce"><i class="fal %1$s" aria-hidden="true"></i><div><h2 class="forestas-evento-info__titolo">%2$s</h2><div class="forestas-evento-info__testo">%3$s</div></div></section>',
		esc_attr( $icona ),
		esc_html( $titolo ),
		$testo
	);
}
