/*
 * «Credits foto» della testata delle pagine statiche (oc:8758): il pulsante mostra e nasconde autore e
 * credits dell'immagine, come sul sito Drupal.
 */
document.addEventListener('click', function (evento) {
	var pulsante = evento.target.closest('.forestas-credits__pulsante');
	if (!pulsante) {
		return;
	}
	var credits = pulsante.closest('.forestas-credits');
	var nascosti = credits.classList.toggle('is-hidden');
	pulsante.setAttribute('aria-expanded', nascosti ? 'false' : 'true');
});
