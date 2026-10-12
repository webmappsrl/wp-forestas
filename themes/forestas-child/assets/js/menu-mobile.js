/*
 * Menu mobile dell'header (oc:8757). Impreza apre e chiude il pannello cliccando «.w-nav-control»;
 * qui si aggiunge solo l'accessibilità:
 * - il nome del pulsante segue la scritta visibile, «menu» o «chiudi» (WCAG 2.5.3);
 * - Esc chiude il pannello e riporta il focus sul pulsante;
 * - a pannello aperto Tab e Maiusc+Tab restano fra il pulsante e i link del pannello, invece di finire
 *   sulla pagina coperta dal pannello (WCAG 2.4.3, 2.4.7).
 */
( function () {
	'use strict';

	var testi = window.forestasMenu || { apri: 'Menu', chiudi: 'Chiudi' };
	var pulsante = document.querySelector( '.l-header .forestas-nav .w-nav-control' );
	if ( ! pulsante ) {
		return;
	}
	var pannello = pulsante.parentNode.querySelector( '.w-nav-list.level_1' );

	function aperto() {
		return pulsante.getAttribute( 'aria-expanded' ) === 'true';
	}

	function aggiornaNome() {
		pulsante.setAttribute( 'aria-label', aperto() ? testi.chiudi : testi.apri );
	}

	function elementiFocus() {
		var lista = pannello ? Array.prototype.slice.call( pannello.querySelectorAll( 'a[href], button' ) ) : [];
		return [ pulsante ].concat( lista.filter( function ( el ) {
			return el.offsetWidth > 0 || el.offsetHeight > 0;
		} ) );
	}

	new MutationObserver( aggiornaNome ).observe( pulsante, { attributes: true, attributeFilter: [ 'aria-expanded' ] } );
	aggiornaNome();

	document.addEventListener( 'keydown', function ( evento ) {
		if ( ! aperto() ) {
			return;
		}
		if ( evento.key === 'Escape' ) {
			evento.preventDefault();
			pulsante.click();
			pulsante.focus();
			return;
		}
		if ( evento.key !== 'Tab' ) {
			return;
		}
		var elementi = elementiFocus();
		var primo = elementi[ 0 ];
		var ultimo = elementi[ elementi.length - 1 ];
		var dentro = elementi.indexOf( document.activeElement ) !== -1;
		if ( evento.shiftKey && ( document.activeElement === primo || ! dentro ) ) {
			evento.preventDefault();
			ultimo.focus();
		} else if ( ! evento.shiftKey && ( document.activeElement === ultimo || ! dentro ) ) {
			evento.preventDefault();
			primo.focus();
		}
	} );
}() );
