/*
 * Lightbox della galleria delle pagine, come sul sito Drupal (oc:8758): la stessa libreria
 * (slick-lightbox 0.2.12 su Slick 1.8.1) con le impostazioni e il caricamento del modulo slick_lightbox
 * (js/slick-lightbox.load.js, ridotto alle immagini: niente video) e la didascalia del tema
 * sardegnasentieri (js/scripts.js). Il markup lo scrive forestas_galleria() (inc/pagine-statiche.php).
 */
(function ($) {
	'use strict';

	// drupalSettings.slickLightbox del modulo: optionset «slick_lightbox» senza i valori predefiniti di Slick
	var impostazioniSlick = {
		mobileFirst: true,
		centerMode: true,
		centerPadding: '',
		lazyLoad: 'ondemand',
		swipeToSlide: true,
		waitForAnimate: false
	};
	var selettore = '[data-slick-lightbox-trigger]';
	var base = '.slick-lightbox-slick';

	// Pulsante con il markup della libreria e l'etichetta tradotta (Slick e slick-lightbox l'hanno solo in inglese)
	function pulsante(classe, etichetta) {
		return $('<button type="button">').addClass(classe).attr('aria-label', etichetta).text(etichetta).prop('outerHTML');
	}

	function avvia(galleria) {
		var $galleria = $(galleria);
		var slickTradotto = $.extend({}, impostazioniSlick, {
			prevArrow: pulsante('slick-prev', $galleria.attr('data-precedente')),
			nextArrow: pulsante('slick-next', $galleria.attr('data-successivo'))
		});
		var chiudi = $galleria.attr('data-chiudi');
		var $voci = $(selettore, galleria);

		function avviaSlick(modale) {
			var $slick = $(base, modale);
			var $slide = $slick.children();

			if (!$slick.hasClass('slick-initialized')) {
				$slick.on('init', function () {
					$voci.each(function (i) {
						var $s = $($slide[i]);
						$('img', $s).attr('loading', 'lazy');
						$s.addClass('slick-slide--image');
					});
				});
			}
			var istanza = $slick.slick(slickTradotto);
			// Come il modulo: con una sola immagine Slick sposterebbe la traccia
			if ($slide.length === 1) {
				var slick = $slick.slick('getSlick');
				$slick.addClass('slick-lightbox-slick--unslick');
				slick.$slideTrack.css({ left: '', transform: '' });
			}
			return istanza;
		}

		var opzioni = {
			closeOnBackdropClick: false,
			itemSelector: selettore,
			caption: function (voce) {
				var $didascalia = $(voce).next();
				return $didascalia.length && $didascalia.hasClass('visually-hidden') ? $didascalia[0].innerHTML : '';
			},
			src: function (voce) {
				return $(voce).attr('href');
			},
			slick: avviaSlick,
			// Pulsante di chiusura della libreria, con un nome (senza, uno screen reader legge solo «pulsante»)
			layouts: { closeButton: $('<button type="button" class="slick-lightbox-close">').attr('aria-label', chiudi).prop('outerHTML') }
		};

		var origine = null;

		/*
		 * Accessibilità, che la libreria non gestisce (e su Drupal manca): la lightbox è una finestra di
		 * dialogo con il nome della galleria, le immagini hanno il testo alternativo delle miniature, il
		 * fuoco va sul pulsante «Chiudi» e il Tab resta dentro finché è aperta (WCAG 1.1.1, 2.4.3, 4.1.2);
		 * alla chiusura il fuoco torna alla miniatura da cui si è partiti.
		 */
		function accessibile() {
			var $modale = $($galleria[0].slickLightbox.$modalElement);
			var nome = $galleria.closest('.forestas-galleria').find('h2').first().text();
			$modale.attr({ role: 'dialog', 'aria-modal': 'true', 'aria-label': nome });
			$modale.find('.slick-lightbox-slick-img').each(function () {
				var indice = parseInt($(this).closest('.slick-slide').attr('data-slick-index'), 10) || 0;
				var n = $voci.length;
				$(this).attr('alt', $voci.eq(((indice % n) + n) % n).find('img').attr('alt') || '');
			});
			$modale.find('.slick-lightbox-close').trigger('focus');
			$modale.on('keydown.forestasTab', function (e) {
				if (e.key !== 'Tab') {
					return;
				}
				var $fuochi = $modale.find('button, a[href]').filter(':visible');
				if (!$fuochi.length) {
					return;
				}
				var primo = $fuochi[0], ultimo = $fuochi[$fuochi.length - 1];
				if (e.shiftKey && (document.activeElement === primo || !$modale[0].contains(document.activeElement))) {
					e.preventDefault();
					ultimo.focus();
				} else if (!e.shiftKey && (document.activeElement === ultimo || !$modale[0].contains(document.activeElement))) {
					e.preventDefault();
					primo.focus();
				}
			});
		}

		// Apre sull'immagine cliccata: la libreria non va da sola alla posizione giusta
		function apri(e) {
			origine = e.currentTarget;
			var posizione = parseInt($(e.currentTarget).attr('data-b-delta'), 10) || 0;
			setTimeout(function () {
				var lightbox = $galleria[0].slickLightbox;
				if (lightbox && lightbox.slick) {
					lightbox.slick.slick('slickGoTo', posizione, true);
				}
			});
		}

		$galleria.slickLightbox(opzioni).on({
			'shown.slickLightbox': accessibile,
			'hide.slickLightbox': function () {
				if (origine) {
					origine.focus();
				}
			},
			'show.slickLightbox': function () {
				// Chiude con un clic fuori dall'immagine (closeOnBackdropClick è spento dal modulo)
				$($galleria[0].slickLightbox.$modalElement).on('click.slbox', base + '-item', function (e) {
					if (e.target === this) {
						$('.slick-lightbox-close').click();
					}
				});
			}
		});
		$galleria.off('click.slboxlink').on('click.slboxlink', selettore, apri);
		$galleria.addClass('slick-lightbox-gallery--on');
	}

	$(function () {
		$('[data-slick-lightbox-gallery]:not(.slick-lightbox-gallery--on)').each(function () {
			avvia(this);
		});

		// Tema sardegnasentieri: segna autore e credits presenti, che il CSS precede con le etichette
		$('body').on('click', '.blazy__slick-lightbox', function () {
			var $didascalia = $('body').find('.slick-lightbox-slick-caption');
			$didascalia.find('.auth:not(:empty)').addClass('has-value');
			$didascalia.find('.cred:not(:empty)').addClass('has-value');
		});
	});
})(jQuery);
