<?php
/**
 * Card di un risultato della ricerca (sostituisce templates/card.php del plugin wp-package): la card unica del sito,
 * `forestas-card` come i post e il dettaglio (oc:8969), con gli elementi `data-wmwp` che il JS della ricerca riempie.
 * Il filetto dell'etichetta prende il colore del menu dal tipo (`wmwp-card--type-<typeKey>`, aggiunta dal JS: style.css).
 * Tutta la card è un link, come nel template del plugin: il riquadro della mappa e la ricerca la usano così.
 * Le classi `wmwp-card*` restano perché il JS e lo stile del riquadro della mappa le usano; il riquadro della foto non
 * ha `wmwp-card__media`, così senza foto il JS toglie solo l'immagine e resta il segnaposto di Drupal (style.css).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<li class="wmwp-card forestas-card forestas-card--immagine">
	<a class="wmwp-card__link forestas-card__collegamento" data-wmwp="link">
		<span class="forestas-card__media"><img class="wmwp-card__image" data-wmwp="image" alt="" loading="lazy" decoding="async"></span>
		<span class="wmwp-card__body forestas-card__testo">
			<span class="wmwp-card__type forestas-card__tag" data-wmwp="type"></span>
			<span class="wmwp-card__title forestas-card__titolo" data-wmwp="title"></span>
			<span class="wmwp-card__meta forestas-card__data" data-wmwp="meta"></span>
		</span>
	</a>
</li>
