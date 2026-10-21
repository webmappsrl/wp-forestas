/*
 * Riquadro «Allegati e galleria» dell'editor delle pagine (oc:8758): aggiunge allegati e immagini dalla
 * libreria media di WordPress, li toglie e li riordina. Il salvataggio lo fa il modulo della pagina
 * (inc/pagine-statiche-admin.php).
 */
(function ($) {
	'use strict';

	function voce(campo, allegato) {
		var anteprima = allegato.type === 'image' && allegato.sizes
			? $('<img alt="">').attr('src', (allegato.sizes.thumbnail || allegato.sizes.full).url)
			: $('<span class="dashicons dashicons-media-document" aria-hidden="true"></span>');
		return $('<li class="forestas-riquadro-voce">').attr('data-id', allegato.id).append(
			$('<span class="forestas-riquadro-trascina dashicons dashicons-menu" aria-hidden="true"></span>'),
			anteprima,
			$('<span class="forestas-riquadro-nome">').text(allegato.title || allegato.filename),
			$('<input type="hidden">').attr('name', campo + '[]').val(allegato.id),
			$('<button type="button" class="button-link forestas-riquadro-togli">').text('Togli')
		);
	}

	$(function () {
		$('.forestas-riquadro-elenco ul').sortable({ handle: '.forestas-riquadro-trascina', axis: 'y' });

		$(document).on('click', '.forestas-riquadro-togli', function () {
			$(this).closest('li').remove();
		});

		$(document).on('click', '.forestas-riquadro-aggiungi', function () {
			var elenco = $(this).closest('.forestas-riquadro-elenco');
			var campo = elenco.data('campo');
			var tipo = elenco.data('tipo');
			var finestra = wp.media({
				title: $(this).text(),
				button: { text: 'Aggiungi' },
				multiple: true,
				library: tipo ? { type: tipo } : {}
			});
			finestra.on('select', function () {
				var presenti = elenco.find('li').map(function () { return String($(this).data('id')); }).get();
				finestra.state().get('selection').each(function (scelto) {
					var allegato = scelto.toJSON();
					if (presenti.indexOf(String(allegato.id)) === -1) {
						elenco.find('ul').append(voce(campo, allegato));
					}
				});
			});
			finestra.open();
		});
	});
})(jQuery);
