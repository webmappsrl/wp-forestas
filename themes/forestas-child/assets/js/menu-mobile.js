/*
 * Menu mobile dell'header (oc:8757). Impreza apre e chiude il pannello cliccando «.w-nav-control», e
 * con Esc lo chiude e riporta il focus sul pulsante (common/js/elements/menu.js). Qui si aggiunge:
 * - la scritta e il nome del pulsante seguono lo stato, «menu» o «chiudi» (WCAG 2.5.3);
 * - a pannello aperto Tab e Maiusc+Tab restano fra il pulsante e i link del pannello, invece di finire
 *   sulla pagina coperta dal pannello (WCAG 2.4.3, 2.4.7). L'ascolto è in fase di cattura: Impreza
 *   chiude il pannello con Maiusc+Tab sulla prima voce, e quel tasto deve invece tornare al pulsante.
 */
( function () {
	'use strict';

	var pulsante = document.querySelector( '.l-header .forestas-nav .w-nav-control' );
	if ( ! pulsante ) {
		return;
	}
	var pannello = pulsante.parentNode.querySelector( '.w-nav-list.level_1' );
	var etichetta = pulsante.querySelector( 'span' );
	var testoChiuso = etichetta ? etichetta.textContent : '';
	var nomeChiuso = pulsante.getAttribute( 'aria-label' ) || testoChiuso;
	var testoAperto = ( window.forestasMenu && window.forestasMenu.chiudi ) || 'chiudi';

	// Aperto solo se il pulsante si vede: passando al desktop (rotazione, finestra allargata) Impreza non
	// chiude il pannello e lascia aria-expanded a true, ma il menu non è più a scomparsa
	function aperto() {
		return pulsante.getAttribute( 'aria-expanded' ) === 'true' && pulsante.offsetParent !== null;
	}

	function aggiornaPulsante() {
		var espanso = pulsante.getAttribute( 'aria-expanded' ) === 'true';
		pulsante.setAttribute( 'aria-label', espanso ? testoAperto : nomeChiuso );
		if ( etichetta ) {
			etichetta.textContent = espanso ? testoAperto : testoChiuso;
		}
	}

	function elementiFocus() {
		var lista = pannello ? Array.prototype.slice.call( pannello.querySelectorAll( 'a[href], button' ) ) : [];
		return [ pulsante ].concat( lista.filter( function ( el ) {
			return el.offsetWidth > 0 || el.offsetHeight > 0;
		} ) );
	}

	new MutationObserver( aggiornaPulsante ).observe( pulsante, { attributes: true, attributeFilter: [ 'aria-expanded' ] } );
	aggiornaPulsante();

	document.addEventListener( 'keydown', function ( evento ) {
		if ( evento.key !== 'Tab' || ! aperto() ) {
			return;
		}
		var elementi = elementiFocus();
		var posizione = elementi.indexOf( document.activeElement );
		var destinazione = null;

		if ( evento.shiftKey ) {
			if ( posizione === -1 || posizione === 0 ) {
				destinazione = elementi[ elementi.length - 1 ];
			} else if ( posizione === 1 ) {
				destinazione = pulsante;
			}
		} else if ( posizione === -1 || posizione === elementi.length - 1 ) {
			destinazione = pulsante;
		}

		if ( destinazione ) {
			evento.preventDefault();
			evento.stopPropagation();
			destinazione.focus();
		}
	}, true );
}() );
