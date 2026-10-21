/*
 * «Condividi» come nell'artifact «Canvas Fase 2» (oc:8783, .sh-share), sulle pagine (oc:8758). Su telefono
 * apre il menu di condivisione del sistema (navigator.share); altrove, o se il menu non c'è, il pannello
 * con i collegamenti scritti da forestas_condividi() (inc/pagine-statiche.php). Esc o un clic fuori
 * chiudono il pannello; Esc riporta il fuoco al pulsante. «Copia link» copia l'indirizzo e annuncia la
 * conferma (role="status").
 */
(function () {
	'use strict';

	// Telefono o tablet: schermo a tocco senza mouse, dove il menu di sistema è quello che ci si aspetta
	var telefono = window.matchMedia('(hover: none) and (pointer: coarse)');

	function copia(testo) {
		if (navigator.clipboard && window.isSecureContext) {
			return navigator.clipboard.writeText(testo);
		}
		// Senza HTTPS (o su browser vecchi) l'API non c'è: copia da un campo temporaneo
		return new Promise(function (ok, ko) {
			var campo = document.createElement('textarea');
			campo.value = testo;
			campo.setAttribute('readonly', '');
			campo.style.position = 'fixed';
			campo.style.opacity = '0';
			document.body.appendChild(campo);
			campo.select();
			var riuscito = false;
			try {
				riuscito = document.execCommand('copy');
			} catch (e) {
				riuscito = false;
			}
			document.body.removeChild(campo);
			(riuscito ? ok : ko)();
		});
	}

	document.querySelectorAll('.forestas-condividi').forEach(function (box) {
		var pulsante = box.querySelector('.forestas-condividi__pulsante');
		var pannello = box.querySelector('.forestas-condividi__pannello');
		var esito = box.querySelector('.forestas-condividi__esito');
		var copiaLink = box.querySelector('.forestas-condividi__copia');
		var url = box.getAttribute('data-url');
		var titolo = box.getAttribute('data-titolo');

		function imposta(aperto, rientra) {
			pulsante.setAttribute('aria-expanded', aperto ? 'true' : 'false');
			pannello.hidden = !aperto;
			if (aperto) {
				esito.textContent = '';
				var primo = pannello.querySelector('a');
				if (primo) {
					primo.focus();
				}
			} else if (rientra) {
				pulsante.focus();
			}
		}

		pulsante.addEventListener('click', function () {
			if (pannello.hidden && telefono.matches && navigator.share) {
				navigator.share({ title: titolo, url: url }).catch(function (e) {
					// Chiuso dall'utente: niente; altro errore (condivisione non permessa): il pannello
					if (!e || e.name !== 'AbortError') {
						imposta(true, false);
					}
				});
				return;
			}
			imposta(pannello.hidden, false);
		});
		pannello.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				imposta(false, true);
			}
		});
		document.addEventListener('click', function (e) {
			if (!pannello.hidden && !box.contains(e.target)) {
				imposta(false, false);
			}
		});
		copiaLink.addEventListener('click', function () {
			copia(url).then(function () {
				esito.textContent = copiaLink.getAttribute('data-copiato');
			}, function () {
				esito.textContent = url;
			});
		});
	});
})();
