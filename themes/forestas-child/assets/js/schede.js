/*
 * Schede di una pagina a schede (oc:8759, [forestas_schede] in inc/news.php): sotto 860px la fila scorre
 * in orizzontale per conto suo; all'apertura si porta sulla scheda aperta, senza muovere la pagina.
 */
( function () {
	'use strict';

	document.querySelectorAll( '.forestas-schede' ).forEach( function ( fila ) {
		var aperta = fila.querySelector( '[aria-current="page"]' );
		if ( aperta && fila.scrollWidth > fila.clientWidth ) {
			fila.scrollLeft = aperta.offsetLeft - ( fila.clientWidth - aperta.offsetWidth ) / 2;
		}
	} );
} )();
