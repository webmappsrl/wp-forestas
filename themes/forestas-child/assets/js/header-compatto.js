/*
 * Riga compatta dell'header su desktop (wireframe oc:8783, call del 16/10/2026). Quando l'header intero
 * è uscito dallo schermo scorrendo, resta fisso in alto ridotto a una riga di 47px con logo e menu;
 * tornando su, quando lo spazio dell'header rientra nello schermo, torna intero. L'aspetto sta in
 * style.css, sotto la classe «forestas-header-compatto» di <html>; qui solo quando metterla.
 *
 * Solo da 1025px di larghezza e con la finestra alta più di 500px, la stessa media query del CSS: sotto
 * c'è l'header mobile, che resta fermo da sé; con poca altezza (zoom alto) la riga non resta fissa
 * (WCAG 1.4.10). Diventando fisso l'header esce dal flusso: --forestas-altezza-header, la sua altezza
 * intera, diventa spazio in cima alla pagina, così il contenuto non si sposta.
 */
( function () {
	'use strict';

	var header = document.querySelector( '.l-header' );
	if ( ! header || ! window.matchMedia ) {
		return;
	}
	var radice = document.documentElement;
	var classe = 'forestas-header-compatto';
	var media = window.matchMedia( '(min-width: 1025px) and (min-height: 501px)' );
	// Scorrimento oltre il quale l'header intero non si vede più
	var soglia = Infinity;

	// Margine superiore dello schermo: il fondo della barra admin dove è fissa, altrimenti 0
	function limiteAlto() {
		var barra = document.getElementById( 'wpadminbar' );
		return barra && getComputedStyle( barra ).position === 'fixed' ? barra.getBoundingClientRect().bottom : 0;
	}

	// Misura l'header intero: se è compatto lo si riporta intero per la misura, senza che il browser
	// arrivi a disegnarlo
	function misura() {
		var compatto = radice.classList.contains( classe );
		radice.classList.remove( classe );
		var box = header.getBoundingClientRect();
		radice.style.setProperty( '--forestas-altezza-header', box.height + 'px' );
		soglia = box.bottom + window.scrollY - limiteAlto();
		radice.classList.toggle( classe, compatto );
	}

	function aggiorna() {
		radice.classList.toggle( classe, media.matches && window.scrollY >= soglia );
	}

	function ricalcola() {
		misura();
		aggiorna();
	}

	window.addEventListener( 'scroll', aggiorna, { passive: true } );
	window.addEventListener( 'resize', ricalcola );
	media.addEventListener( 'change', ricalcola );
	// Font e immagini che arrivano dopo cambiano l'altezza dell'header intero: si rimisura. Da compatto
	// cambia l'altezza della riga, non quella da misurare
	if ( window.ResizeObserver ) {
		new ResizeObserver( function () {
			if ( ! radice.classList.contains( classe ) ) {
				ricalcola();
			}
		} ).observe( header );
	}
	ricalcola();
}() );
