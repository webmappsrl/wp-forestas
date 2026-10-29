/*
 * Colonna dei filtri di una lista (oc:8760, forestas_news_filtri in inc/news.php), come le faccette di
 * Drupal: la freccia apre e chiude un gruppo; «Mostra di più» apre le voci oltre la quinta e diventa
 * «Mostra meno»; sotto 960px il pulsante «Filtri» apre e chiude la colonna (sase-sidebar-mobile).
 */
( function () {
	'use strict';

	var inglese = document.documentElement.lang.indexOf( 'en' ) === 0;

	// Un pulsante con aria-expanded che mette o toglie una classe sul suo contenitore
	function interruttore( selettore, contenitore, classe, dopo ) {
		document.querySelectorAll( selettore ).forEach( function ( pulsante ) {
			var bersaglio = pulsante.closest( contenitore );
			pulsante.addEventListener( 'click', function () {
				var aperto = pulsante.getAttribute( 'aria-expanded' ) !== 'true';
				pulsante.setAttribute( 'aria-expanded', aperto ? 'true' : 'false' );
				bersaglio.classList.toggle( classe, aperto );
				if ( dopo ) {
					dopo( pulsante, aperto );
				}
			} );
		} );
	}

	interruttore( '.forestas-filtri__apri-gruppo', '.forestas-filtri__gruppo', 'is-aperto' );
	interruttore( '.forestas-filtri__mostra', '.forestas-filtri__voci', 'is-tutte', function ( pulsante, aperto ) {
		pulsante.textContent = aperto ? ( inglese ? 'Show less' : 'Mostra meno' ) : ( inglese ? 'Show more' : 'Mostra di più' );
	} );
	interruttore( '.forestas-filtri__apri', '.forestas-filtri', 'is-aperto' );
} )();
