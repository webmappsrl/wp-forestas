/*
 * Dettaglio di percorsi e punti di interesse (oc:8969): testata compatta che compare dopo la testata grande, sotto
 * l'header del sito; «Mostra tutto» dei testi lunghi; roadbook a fisarmonica; legende degli indicatori dietro la «i».
 * Senza JavaScript tutto il testo resta leggibile: i testi non sono tagliati e le legende restano nascoste.
 */
(function () {
	'use strict';

	var root = document.querySelector('.wm-dettaglio');
	if (!root) {
		return;
	}

	// Altezza dell'header del sito in alto (sticky su mobile, riga compatta su desktop): la testata compatta va sotto
	function altezzaHeader() {
		var header = document.querySelector('.l-header');
		if (!header) {
			return 0;
		}
		var box = header.getBoundingClientRect();
		return box.bottom > 0 ? Math.round(box.bottom) : 0;
	}

	var stick = root.querySelector('[data-wm-stick]');
	var hero = root.querySelector('[data-wm-hero]');
	function aggiornaStick() {
		var top = altezzaHeader();
		root.style.setProperty('--wm-stick-top', top + 'px');
		if (!stick || !hero) {
			return;
		}
		var on = hero.getBoundingClientRect().bottom < top;
		stick.classList.toggle('is-on', on);
		stick.setAttribute('aria-hidden', on ? 'false' : 'true');
		// Il focus da tastiera non deve finire sotto testata compatta e header (WCAG 2.4.11)
		document.documentElement.style.scrollPaddingTop = (top + (on ? stick.offsetHeight : 0) + 16) + 'px';
	}
	window.addEventListener('scroll', aggiornaStick, { passive: true });
	window.addEventListener('resize', aggiornaStick);
	aggiornaStick();

	// «Mostra tutto» solo per i testi che superano l'altezza chiusa
	root.querySelectorAll('[data-wm-xp]').forEach(function (xp) {
		var body = xp.querySelector('.wm-xp__body');
		var more = xp.querySelector('.wm-xp__more');
		var button = more && more.querySelector('button');
		xp.classList.add('is-chiuso');
		if (body.scrollHeight <= body.clientHeight + 4) {
			xp.classList.remove('is-chiuso');
			return;
		}
		more.hidden = false;
		button.addEventListener('click', function () {
			var aperto = button.getAttribute('aria-expanded') === 'true';
			button.setAttribute('aria-expanded', aperto ? 'false' : 'true');
			button.textContent = aperto ? button.dataset.piu : button.dataset.meno;
			xp.classList.toggle('is-chiuso', aperto);
		});
	});

	// Correlati: le prime card visibili, le altre con «Mostra tutti (N)». Aprendo, il focus va sulla prima card nuova
	root.querySelectorAll('[data-wm-limite]').forEach(function (lista) {
		var limite = parseInt(lista.getAttribute('data-wm-limite'), 10) || 3;
		var voci = Array.prototype.slice.call(lista.children);
		var contenitore = lista.nextElementSibling;
		var button = contenitore && contenitore.querySelector('button[aria-controls="' + lista.id + '"]');
		if (!button || voci.length <= limite) {
			return;
		}
		function mostra(tutte) {
			voci.forEach(function (voce, i) {
				voce.hidden = !tutte && i >= limite;
			});
			button.setAttribute('aria-expanded', tutte ? 'true' : 'false');
			button.textContent = tutte ? button.dataset.meno : button.dataset.piu;
		}
		mostra(false);
		contenitore.hidden = false;
		button.addEventListener('click', function () {
			var apri = button.getAttribute('aria-expanded') !== 'true';
			mostra(apri);
			if (apri) {
				var prima = voci[limite] && voci[limite].querySelector('a, [tabindex]');
				if (prima) {
					prima.focus();
				}
			}
		});
	});

	// Fisarmonica del roadbook e legende: un pulsante con aria-controls apre e chiude il suo pannello
	root.querySelectorAll('.wm-rb__btn, .wm-ind__info').forEach(function (button) {
		var panel = document.getElementById(button.getAttribute('aria-controls'));
		if (!panel) {
			return;
		}
		button.addEventListener('click', function () {
			var aperto = button.getAttribute('aria-expanded') === 'true';
			button.setAttribute('aria-expanded', aperto ? 'false' : 'true');
			panel.hidden = aperto;
		});
	});
})();
