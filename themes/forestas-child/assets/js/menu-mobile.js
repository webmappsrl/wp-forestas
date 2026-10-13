/*
 * Menu mobile dell'header (oc:8757). Impreza apre e chiude il pannello cliccando «.w-nav-control», e
 * con Esc lo chiude e riporta il focus sul pulsante (common/js/elements/menu.js). Qui si aggiunge:
 * - la scritta e il nome del pulsante seguono lo stato, «menu» o «chiudi» (WCAG 2.5.3);
 * - il pannello si apre dal bordo inferiore dell'header, che scorre con la pagina;
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

	// L'header scorre con la pagina e sopra può esserci la barra admin, che sotto i 600px scorre anche
	// lei: il pannello parte dal bordo inferiore dell'header così come si vede, mai sopra di esso
	function posizionaPannello() {
		var header = pulsante.closest( '.l-header' );
		// A menu aperto la pagina non scorre: se la riga del pulsante è tagliata in alto la si porta
		// tutta nello schermo, o «chiudi» resterebbe in parte nascosto (WCAG 2.4.11)
		var riga = pulsante.closest( '.l-subheader' ) || pulsante;
		var sopra = riga.getBoundingClientRect().top;
		if ( sopra < 0 ) {
			window.scrollBy( 0, sopra );
		}
		if ( header ) {
			document.documentElement.style.setProperty( '--forestas-sotto-header', Math.max( 0, header.getBoundingClientRect().bottom ) + 'px' );
		}
	}

	function aggiornaPulsante() {
		var espanso = pulsante.getAttribute( 'aria-expanded' ) === 'true';
		if ( espanso ) {
			posizionaPannello();
		}
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
	window.addEventListener( 'resize', function () {
		if ( aperto() ) {
			posizionaPannello();
		}
	} );

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
