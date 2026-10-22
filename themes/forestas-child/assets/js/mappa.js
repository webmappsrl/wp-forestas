/*
 * Mappa interattiva come su Drupal (oc:8758). Misura altezza della finestra, dell'header e del percorso,
 * che il CSS sottrae per avere la mappa alta quanto lo spazio sotto il percorso (header-navigation.js e
 * scripts.js del tema: --vh, --header-height, --breadcrumb-height). Poi i pulsanti per allargarla a
 * tutto schermo e riportarla com'era, che si chiude anche con Esc o uscendo dalla mappa col Tab
 * (scripts.js, «HERO MAP EXPAND/SHRINK» e «KEYBOARD MANAGEMENT»). Unica differenza voluta: aperta, il
 * fuoco va sul pulsante che la richiude; su Drupal il fuoco si perde e il Tab successivo la richiude.
 */
(function () {
	'use strict';

	var mappa = document.querySelector('.forestas-mappa');
	if (!mappa) {
		return;
	}
	var radice = document.documentElement;
	var allarga = document.getElementById('mapExpander');
	var riduci = document.getElementById('mapShrinker');

	function misura() {
		var header = document.querySelector('.l-header');
		var percorso = document.querySelector('.forestas-percorso');
		radice.style.setProperty('--forestas-vh', window.innerHeight + 'px');
		radice.style.setProperty('--forestas-mappa-header', (header ? header.offsetHeight : 0) + 'px');
		radice.style.setProperty('--forestas-mappa-percorso', (percorso ? percorso.offsetHeight : 0) + 'px');
	}

	function aperta() {
		return mappa.classList.contains('is-expanded');
	}

	function apri() {
		mappa.classList.remove('is-shrinked');
		mappa.classList.add('is-expanded');
		document.body.classList.add('forestas-mappa-aperta');
		riduci.focus();
	}

	function chiudi() {
		mappa.classList.remove('is-expanded');
		mappa.classList.add('is-shrinked');
		document.body.classList.remove('forestas-mappa-aperta');
		allarga.focus();
	}

	misura();
	window.addEventListener('resize', misura);
	window.addEventListener('load', misura);
	allarga.addEventListener('click', apri);
	riduci.addEventListener('click', chiudi);
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && aperta()) {
			chiudi();
		}
	});
	document.addEventListener('keyup', function (e) {
		if (e.key === 'Tab' && aperta() && !mappa.contains(e.target)) {
			chiudi();
		}
	});
})();
