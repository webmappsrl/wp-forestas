/*
 * Menu mobile dell'header (oc:8757). Impreza apre e chiude il pannello cliccando «.w-nav-control», e
 * con Esc lo chiude e riporta il focus sul pulsante (common/js/elements/menu.js). Qui si aggiunge:
 * - la scritta e il nome del pulsante seguono lo stato, «menu» o «chiudi» (WCAG 2.5.3);
 * - il pannello si apre dal bordo inferiore dell'header e la riga del pulsante torna tutta in vista,
 *   sotto la barra admin se c'è (l'header resta fermo in cima, ma non sugli schermi bassi);
 * - un tocco sul velo scuro che copre la pagina chiude il menu (lo fa Impreza) senza attivare il link
 *   che sta sotto;
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
	var header = pulsante.closest( '.l-header' );
	var riga = pulsante.closest( '.l-subheader' );
	var pannello = pulsante.parentNode.querySelector( '.w-nav-list.level_1' );
	var etichetta = pulsante.querySelector( 'span' );
	var testoChiuso = etichetta ? etichetta.textContent : '';
	var nomeChiuso = pulsante.getAttribute( 'aria-label' ) || testoChiuso;
	var testoAperto = ( window.forestasMenu && window.forestasMenu.chiudi ) || 'chiudi';

	// Aperto solo se il pulsante si vede: passando al desktop (rotazione, finestra allargata) Impreza non
	// chiude il pannello e lascia aria-expanded a true, ma il menu non è più a scomparsa
	function espanso() {
		return pulsante.getAttribute( 'aria-expanded' ) === 'true';
	}

	function aperto() {
		return espanso() && pulsante.offsetParent !== null;
	}

	// Margine superiore dello schermo: il fondo della barra admin dove è fissa (da 601px, solo per chi è
	// loggato), altrimenti 0
	function limiteAlto() {
		var barra = document.getElementById( 'wpadminbar' );
		return barra && getComputedStyle( barra ).position === 'fixed' ? barra.getBoundingClientRect().bottom : 0;
	}

	// Il pannello parte dal bordo inferiore dell'header così come si vede. L'header è fermo in cima, ma
	// non sugli schermi bassi né sui tablet in orizzontale, dove scorre con la pagina: a menu aperto la
	// pagina non scorre, quindi se la riga del pulsante è tagliata in alto o finisce sotto la barra admin
	// la si porta prima tutta in vista, o «chiudi» resterebbe in parte nascosto (WCAG 2.4.11)
	function posizionaPannello() {
		var sopra = riga.getBoundingClientRect().top - limiteAlto();
		if ( sopra < 0 ) {
			window.scrollBy( 0, sopra );
		}
		document.documentElement.style.setProperty( '--forestas-sotto-header', header.getBoundingClientRect().bottom + 'px' );
	}

	function aggiornaPulsante() {
		var stato = espanso();
		if ( stato ) {
			posizionaPannello();
		}
		pulsante.setAttribute( 'aria-label', stato ? testoAperto : nomeChiuso );
		if ( etichetta ) {
			etichetta.textContent = stato ? testoAperto : testoChiuso;
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
	// Si aspetta che l'header di Impreza abbia la geometria finale (header.js ricalcola al resize con un
	// debounce) prima di misurarlo. Se il pulsante si vede lo decide invece subito una media query del
	// CSS dell'header
	var attesa;
	window.addEventListener( 'resize', function () {
		clearTimeout( attesa );
		attesa = setTimeout( function () {
			if ( aperto() ) {
				posizionaPannello();
			}
		}, 50 );
	} );

	// Un tocco sul velo scuro che copre la pagina chiude il menu: lo fa Impreza al touchend
	// (closeOnClickOutside in common/js/elements/menu.js). Ma il velo, pseudo-elemento del body, sparisce
	// subito, e il click sintetico che il browser genera dopo il touchend cadrebbe sul link sotto. Qui,
	// in fase di cattura e prima di Impreza, si annulla quel click, solo per i tocchi sul velo (bersaglio
	// body o html): la barra di amministrazione e il resto dell'header restano cliccabili. Col mouse non
	// serve: il click va all'antenato comune di mousedown e mouseup, cioè al body
	document.addEventListener( 'touchend', function ( evento ) {
		if ( aperto() && ( evento.target === document.body || evento.target === document.documentElement ) ) {
			evento.preventDefault();
		}
	}, { capture: true, passive: false } );

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
