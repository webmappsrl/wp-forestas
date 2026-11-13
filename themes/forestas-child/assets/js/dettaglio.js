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

	// Legende degli indicatori dietro la «i» (wireframe, nota 12): una aperta per volta, Esc chiude e torna al pulsante
	var info = root.querySelectorAll('.wm-ind__info[aria-controls]');
	info.forEach(function (button) {
		var panel = document.getElementById(button.getAttribute('aria-controls'));
		if (!panel) {
			return;
		}
		function imposta(aperto) {
			button.setAttribute('aria-expanded', aperto ? 'true' : 'false');
			panel.hidden = !aperto;
		}
		button.addEventListener('click', function () {
			var apri = button.getAttribute('aria-expanded') !== 'true';
			info.forEach(function (altro) {
				var altroPanel = document.getElementById(altro.getAttribute('aria-controls'));
				if (altro !== button && altroPanel) {
					altro.setAttribute('aria-expanded', 'false');
					altroPanel.hidden = true;
				}
			});
			imposta(apri);
		});
		[button, panel].forEach(function (el) {
			el.addEventListener('keydown', function (e) {
				if (e.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
					imposta(false);
					button.focus();
				}
			});
		});
	});

	// Fisarmonica del roadbook: un pulsante con aria-controls apre e chiude il suo pannello
	root.querySelectorAll('.wm-rb__btn').forEach(function (button) {
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

	// Gruppi dei dati del percorso (Percorso, Misure, Enti…): il titolo h3 diventa un pulsante che apre e chiude la tabella,
	// come il roadbook. Senza JavaScript restano aperti. Dove il browser lo sa fare, un testo cercato con Ctrl+F dentro un
	// gruppo chiuso lo apre (hidden="until-found", evento beforematch)
	var trovaChiusi = 'onbeforematch' in document.body;
	root.querySelectorAll('[data-wm-gr]').forEach(function (gruppo) {
		var titolo = gruppo.querySelector('.wm-gr__h');
		var pannello = gruppo.querySelector('.wm-gr__p');
		if (!titolo || !pannello) {
			return;
		}
		var pulsante = document.createElement('button');
		pulsante.type = 'button';
		pulsante.className = 'wm-gr__btn';
		pulsante.setAttribute('aria-controls', pannello.id);
		while (titolo.firstChild) {
			pulsante.appendChild(titolo.firstChild);
		}
		titolo.appendChild(pulsante);
		var imposta = function (aperto) {
			pulsante.setAttribute('aria-expanded', aperto ? 'true' : 'false');
			if (aperto) {
				pannello.removeAttribute('hidden');
			} else if (trovaChiusi) {
				pannello.setAttribute('hidden', 'until-found');
			} else {
				pannello.hidden = true;
			}
		};
		imposta(gruppo.getAttribute('data-wm-gr') !== 'chiuso');
		pulsante.addEventListener('click', function () {
			imposta(pulsante.getAttribute('aria-expanded') !== 'true');
		});
		pannello.addEventListener('beforematch', function () {
			imposta(true);
		});
	});

	// Su mobile la mappa (con legenda e «Vedi sulla mappa interattiva») viene subito dopo i pulsanti Scarica, Stampa,
	// Condividi, Segnala, non in fondo alla colonna (oc:8969). Si sposta nel DOM, non solo a vista, così l'ordine del
	// Tab e dello screen reader resta quello che si vede. Senza JavaScript resta in fondo, dove la mette il template.
	var aside = root.querySelector('.wm-aside');
	var azioni = root.querySelector('.wm-actions');
	if (aside && azioni && window.matchMedia) {
		var posto = document.createComment(' posto della mappa da 768px ');
		aside.parentNode.insertBefore(posto, aside);
		var mobile = window.matchMedia('(max-width: 767px)');
		var spostaMappa = function () {
			var su = mobile.matches;
			if (su === aside.classList.contains('wm-aside--su')) {
				return;
			}
			if (su) {
				azioni.parentNode.insertBefore(aside, azioni.nextSibling);
			} else {
				posto.parentNode.insertBefore(aside, posto.nextSibling);
			}
			aside.classList.toggle('wm-aside--su', su);
			// La mappa ricalcola le sue misure nel posto nuovo
			window.dispatchEvent(new Event('resize'));
		};
		spostaMappa();
		if (mobile.addEventListener) {
			mobile.addEventListener('change', spostaMappa);
		} else {
			mobile.addListener(spostaMappa);
		}
	}
})();
