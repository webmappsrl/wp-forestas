<?php
/**
 * Pagine statiche sincronizzate da Drupal (oc:8758): elementi dei tre template di Impreza
 * («template-pagina-testata», «template-pagina-testo», «template-pagina-mappa» in config/post.json).
 *
 * I dati li scrive bin/wordpress-config.sh pagine: il sottotitolo nel Riassunto nativo della pagina,
 * allegati e galleria nei meta wpf_allegati e wpf_galleria, più l'immagine in evidenza; qui c'è solo
 * come si mostrano. La testata segue l'artifact «Canvas Fase 2» con le modifiche concordate col cliente
 * (tavola 8, sezioni redazionali); testo, allegati e galleria seguono il tema Drupal «sardegnasentieri»,
 * che l'artifact non ridisegna.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Riassunto nativo anche per le pagine (WordPress lo ha solo per gli articoli): è il sottotitolo della
 * testata, la «Descrizione testata» di Drupal (oc:8758).
 */
add_action( 'init', fn() => add_post_type_support( 'page', 'excerpt' ) );

/*
 * Chiave della pagina del perimetro (config/pagine.json), scritta dalla sincronizzazione: è WPF_META_PAGINA
 * degli script di docker/scripts/config/, che il tema non carica.
 */
const FORESTAS_META_PAGINA = '_wpf_pagina';

/** Id degli allegati salvati in un meta come elenco (wpf_allegati, wpf_galleria), nell'ordine scritto. */
function forestas_meta_ids( int $id, string $chiave ): array {
	return array_values( array_filter( array_map( 'intval', (array) get_post_meta( $id, $chiave, true ) ) ) );
}

/*
 * Sezione del sito di una pagina del perimetro (_wpf_pagina): dà il colore della barretta della testata,
 * lo stesso della voce di menu che porta alla pagina (colori dell'artifact, oc:8783). Una pagina fuori dal
 * menu principale prende la sezione dal suo colore tematico di Drupal (_wpf_colore: App e Privacy sono
 * «is-sapere»); senza, il colore di base.
 */
function forestas_sezione_pagina( int $id ): string {
	$sezioni = [
		'sentieri'          => 'sentieri',
		'itinerari'         => 'itinerari',
		'cammini'           => 'cammini',
		'da-vedere'         => 'punti-di-interesse',
		'da-sapere'         => 'da-sapere',
		'news'              => 'news',
	];
	$colori = [
		'is-pathway'     => 'sentieri',
		'is-itineraries' => 'itinerari',
		'is-poi'         => 'punti-di-interesse',
		'is-sapere'      => 'da-sapere',
		'is-news'        => 'news',
	];
	return $sezioni[ (string) get_post_meta( $id, FORESTAS_META_PAGINA, true ) ]
		?? $colori[ (string) get_post_meta( $id, '_wpf_colore', true ) ]
		?? 'base';
}

/*
 * Percorso di navigazione «Home › pagina», sempre subito sotto la testata (o sotto il titolo, dove la
 * testata non c'è), seguito dalla riga dei pulsanti della pagina.
 */
function forestas_percorso( int $id ): string {
	return sprintf(
		'<nav class="forestas-percorso" aria-label="%1$s"><a href="%2$s">Home</a><span aria-hidden="true">›</span><span aria-current="page">%3$s</span></nav>',
		esc_attr( forestas_testo( 'Briciole di pane', 'Breadcrumb' ) ),
		esc_url( apply_filters( 'wpml_home_url', home_url( '/' ) ) ),
		esc_html( get_the_title( $id ) )
	) . forestas_azioni( $id );
}

/*
 * Pagine senza «Condividi»: su Drupal il blocco di condivisione (AddToAny) è nascosto su questi indirizzi
 * (block.block.addtoanysharebuttons, visibilità per percorso); Cammini segue Itinerari.
 */
const FORESTAS_PAGINE_SENZA_CONDIVIDI = [ 'sentieri', 'itinerari', 'cammini', 'da-vedere', 'da-sapere', 'mappa-interattiva', 'news' ];

/*
 * Riga dei pulsanti sotto il percorso (.sh-actions dell'artifact «Canvas Fase 2»): per le pagine c'è solo
 * «Condividi», al posto di quello di Drupal. Niente riga se non c'è nessun pulsante.
 */
function forestas_azioni( int $id ): string {
	if ( in_array( (string) get_post_meta( $id, FORESTAS_META_PAGINA, true ), FORESTAS_PAGINE_SENZA_CONDIVIDI, true ) ) {
		return '';
	}
	return '<div class="forestas-azioni">' . forestas_condividi( $id ) . '</div>';
}

/*
 * «Condividi» come nell'artifact (.sh-share, oc:8783): senza servizi esterni. Su telefono apre il menu di
 * condivisione del sistema; altrove un pannello con i collegamenti di condivisione di WhatsApp, Facebook,
 * X, Telegram ed Email e «Copia link», che conferma «Link copiato». I collegamenti sono indirizzi
 * scritti: nessuno script né cookie di terzi finché non si sceglie un servizio
 * (assets/js/condividi.js).
 */
function forestas_condividi( int $id ): string {
	wp_enqueue_script( 'forestas-condividi', get_stylesheet_directory_uri() . '/assets/js/condividi.js', [], forestas_versione(), [ 'in_footer' => true, 'strategy' => 'defer' ] );
	$url    = (string) get_permalink( $id );
	$titolo = html_entity_decode( get_the_title( $id ), ENT_QUOTES, 'UTF-8' );
	$u      = rawurlencode( $url );
	$t      = rawurlencode( $titolo );
	$nuova  = '<span class="screen-reader-text"> ' . esc_html( forestas_testo( '(si apre in una nuova finestra)', '(opens in a new window)' ) ) . '</span>';
	// Icone dell'artifact, 24×24
	$servizi = [
		'WhatsApp' => [ "https://wa.me/?text={$t}%20{$u}", 'M12 2a10 10 0 0 0-8.5 15.2L2 22l4.9-1.4A10 10 0 1 0 12 2zm0 2a8 8 0 0 1 0 16 8 8 0 0 1-4.2-1.2l-.3-.2-2.5.7.7-2.4-.2-.3A8 8 0 0 1 12 4zm-3.3 4h.5c.1 0 .3 0 .5.4l.7 1.7v.5l-.3.5-.4.5c-.1.1-.2.3-.1.5.1.2.6 1 1.3 1.6.9.8 1.7 1.1 1.9 1.2.2.1.4.1.5-.1l.7-.9c.1-.2.3-.2.5-.1l1.4.7.6.3c.1.1.1.5-.1 1.1-.2.5-1.2 1.1-1.6 1.1-.4 0-.8.2-2.7-.6-2.4-.9-3.9-3.3-4-3.5-.1-.2-.9-1.3-.9-2.5s.6-1.7.8-2c.2-.3.5-.4.7-.4z' ],
		'Facebook' => [ "https://www.facebook.com/sharer/sharer.php?u={$u}", 'M12 2a10 10 0 1 0-1.6 19.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.7-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 12 2z' ],
		'X'        => [ "https://twitter.com/intent/tweet?text={$t}&url={$u}", 'M22 5.9c-.7.3-1.5.5-2.3.6.8-.5 1.4-1.3 1.7-2.2-.8.5-1.6.8-2.5 1a4 4 0 0 0-6.8 3.6C9.5 8.7 6.4 7 4.3 4.5a4 4 0 0 0 1.2 5.3c-.6 0-1.2-.2-1.8-.5a4 4 0 0 0 3.2 3.9c-.6.2-1.2.2-1.8.1a4 4 0 0 0 3.7 2.8A8 8 0 0 1 3 17.7a11.3 11.3 0 0 0 6.1 1.8c7.4 0 11.4-6.1 11.4-11.4v-.5c.8-.6 1.4-1.3 1.9-2.1z' ],
		'Telegram' => [ "https://t.me/share/url?url={$u}&text={$t}", 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm4.6 6.6-1.6 7.5c-.1.5-.4.6-.9.4l-2.4-1.8-1.2 1.1c-.1.1-.3.3-.6.3l.2-2.5 4.5-4c.2-.2 0-.3-.3-.1l-5.6 3.5-2.4-.8c-.5-.2-.5-.5.1-.8l9.4-3.6c.4-.2.8.1.8.8z' ],
	];
	$voci = '';
	foreach ( $servizi as $nome => [ $href, $icona ] ) {
		$voci .= sprintf(
			'<li><a href="%1$s" target="_blank" rel="noopener noreferrer"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="%2$s"/></svg><span>%3$s</span>%4$s</a></li>',
			esc_url( $href ),
			esc_attr( $icona ),
			esc_html( $nome ),
			$nuova
		);
	}
	$voci .= sprintf(
		'<li><a href="%s"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 6h18v12H3z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="m3 7 9 6 9-6" fill="none" stroke="currentColor" stroke-width="1.8"/></svg><span>Email</span></a></li>',
		esc_url( 'mailto:?subject=' . $t . '&body=' . $u )
	);
	$pannello = 'forestas-condividi-' . $id;
	return sprintf(
		'<span class="forestas-condividi" data-url="%1$s" data-titolo="%2$s"><button type="button" class="forestas-pulsante forestas-condividi__pulsante" aria-expanded="false" aria-controls="%3$s">%4$s</button>'
		. '<span class="forestas-condividi__pannello" id="%3$s" hidden><span class="forestas-condividi__titolo" id="%3$s-titolo">%5$s</span><ul aria-labelledby="%3$s-titolo">%6$s</ul>'
		. '<button type="button" class="forestas-condividi__copia" data-copiato="%8$s"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg><span>%7$s</span></button>'
		. '<span class="forestas-condividi__esito" role="status"></span></span></span>',
		esc_attr( $url ),
		esc_attr( $titolo ),
		esc_attr( $pannello ),
		esc_html( forestas_testo( 'Condividi', 'Share' ) ),
		esc_html( forestas_testo( 'Condividi questa pagina', 'Share this page' ) ),
		$voci,
		esc_html( forestas_testo( 'Copia link', 'Copy link' ) ),
		esc_attr( forestas_testo( 'Link copiato', 'Link copied' ) )
	);
}

/*
 * Testata: immagine in evidenza con la sfumatura dal basso (senza immagine, quella del tema Drupal sul
 * bianco), barretta nel colore della sezione, titolo e sottotitolo in basso e centrati; sotto, il
 * percorso di navigazione e i pulsanti. L'immagine ha il suo testo alternativo, come su Drupal (formatter
 * «image» del core); si carica subito, non in differita come lì, perché è il primo elemento grande della
 * pagina.
 */
add_shortcode(
	'forestas_testata',
	function () {
		$id = get_the_ID();
		if ( ! $id ) {
			return '';
		}
		/*
		 * Sola intestazione, come le pagine di Drupal con un layout proprio senza hero (News): il titolo
		 * scuro con il filo sopra (sase-container sase-heading-has-decoration, h1.sase-h1), poi il percorso,
		 * come in tutte le pagine.
		 */
		if ( get_post_meta( $id, '_wpf_testata', true ) === 'titolo' ) {
			return sprintf(
				'<div class="forestas-intestazione forestas-sezione-%1$s"><h1 class="forestas-intestazione-titolo">%2$s</h1></div>',
				esc_attr( forestas_sezione_pagina( $id ) ),
				esc_html( get_the_title( $id ) )
			) . forestas_percorso( $id );
		}
		$id_immagine = (int) get_post_thumbnail_id( $id );
		$immagine    = $id_immagine
			? wp_get_attachment_image( $id_immagine, 'full', false, [ 'class' => 'forestas-testata-sfondo', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '100vw' ] )
			: '';
		// Il Riassunto scritto, non quello che WordPress ricava dal testo quando il campo è vuoto
		$sottotitolo = trim( (string) get_post_field( 'post_excerpt', $id ) );
		return sprintf(
			'<div class="forestas-testata forestas-sezione-%1$s%2$s">%3$s' . forestas_credits_immagine( $id_immagine ) . '<div class="forestas-testata-testo"><span class="forestas-testata-filo" aria-hidden="true"></span><h1 class="forestas-testata-titolo">%4$s</h1>%5$s</div></div>',
			esc_attr( forestas_sezione_pagina( $id ) ),
			$immagine ? '' : ' forestas-testata-senza-immagine',
			$immagine,
			esc_html( get_the_title( $id ) ),
			$sottotitolo !== '' ? '<p class="forestas-testata-sottotitolo">' . esc_html( $sottotitolo ) . '</p>' : ''
		) . forestas_percorso( $id );
	}
);

/*
 * «Credits foto» della testata, come nel tema Drupal (media--image--testata-pagina.html.twig): un
 * pulsante in basso che mostra e nasconde «Autore foto» e «Credits immagine», separati da «•». Nascosti
 * all'apertura. Niente pulsante se l'immagine non ha né autore né credits.
 */
function forestas_credits_immagine( int $immagine ): string {
	// Come field--media--field-autore e field--media--field-credits: etichetta in grassetto, poi il valore
	$campi = [
		'author'  => [ forestas_testo( 'Autore foto', 'Photo author' ), (string) get_post_meta( $immagine, 'wpf_autore', true ) ],
		'credits' => [ forestas_testo( 'Credits immagine', 'Image credits' ), (string) get_post_meta( $immagine, 'wpf_credits', true ) ],
	];
	$parti = '';
	foreach ( $campi as $classe => [ $etichetta, $valore ] ) {
		if ( $valore !== '' ) {
			$parti .= sprintf( ' <span class="forestas-credits__%s"><strong>%s</strong> %s</span>', $classe, esc_html( $etichetta ), esc_html( $valore ) );
		}
	}
	if ( ! $immagine || $parti === '' ) {
		return '';
	}
	wp_enqueue_script( 'forestas-credits', get_stylesheet_directory_uri() . '/assets/js/credits-foto.js', [], forestas_versione(), [ 'in_footer' => true, 'strategy' => 'defer' ] );
	$id_testo = 'forestas-credits-' . $immagine;
	return sprintf(
		'<div class="forestas-credits is-hidden"><button type="button" class="forestas-credits__pulsante" aria-expanded="false" aria-controls="%1$s" aria-label="%2$s"><i class="far fa-info-circle" aria-hidden="true"></i> %3$s <i class="far fa-eye forestas-credits__mostra" aria-hidden="true"></i><i class="far fa-eye-slash forestas-credits__nascondi" aria-hidden="true"></i></button><p class="forestas-credits__testo" id="%1$s">%4$s</p></div>',
		esc_attr( $id_testo ),
		esc_attr( forestas_testo( 'Mostra/nascondi credits foto', 'Show/hide photo credits' ) ),
		esc_html( forestas_testo( 'Credits foto', 'Photo credits' ) ),
		$parti
	);
}

/*
 * Corpo di una pagina di testo: il testo importato da Drupal, poi allegati e galleria, nella colonna di
 * 960px del tema Drupal.
 *
 * Finché il testo è quello scritto dalla sincronizzazione (la sua impronta è in _wpf_testo_drupal) si
 * mostra così com'è: è già l'HTML che mostra Drupal, filtrato all'import come il suo «filtered_html». I
 * filtri di the_content lo cambierebbero: wpautop fa di due <br> di fila un nuovo paragrafo e mette in un
 * paragrafo il testo dei blockquote, wptexturize cambia apostrofi e virgolette, paragraph_fix di Impreza
 * toglie il <br> dopo una «]» (nelle FAQ, dopo «[...]»). Un testo modificato in WordPress passa invece
 * per the_content, che serve all'editor classico (salva i paragrafi come righe vuote). Se quel testo
 * contiene lo shortcode stesso, non lo si mostra di nuovo: sarebbe una ricorsione senza fine.
 */
add_shortcode(
	'forestas_pagina_testo',
	function () {
		static $in_corso = false;
		$id = get_the_ID();
		if ( ! $id || $in_corso ) {
			return '';
		}
		$in_corso = true;
		$testo    = (string) get_post_field( 'post_content', $id );
		$html     = md5( $testo ) === get_post_meta( $id, '_wpf_testo_drupal', true ) ? $testo : apply_filters( 'the_content', $testo );
		$in_corso = false;
		return '<div class="forestas-pagina-testo">' . $html . do_shortcode( '[forestas_allegati][forestas_galleria]' ) . '</div>';
	}
);

/*
 * Dimensione di un file scritta come la scrive Drupal (format_size): byte, KB o MB con al più due
 * decimali e il punto, «86.74 KB».
 */
function forestas_dimensione_file( int $byte ): string {
	if ( $byte < 1024 ) {
		return $byte . ' bytes';
	}
	foreach ( [ 'KB', 'MB', 'GB' ] as $unita ) {
		$byte /= 1024;
		if ( $byte < 1024 || 'GB' === $unita ) {
			return round( $byte, 2 ) . ' ' . $unita;
		}
	}
	return '';
}

/*
 * Allegati, come nel tema Drupal (field--field-allegati-media e file-link): titolo «Allegati», poi per ogni
 * file il testo del link (la descrizione del documento su Drupal, o il nome del file) e, sotto, l'icona
 * del file nel colore del marchio con formato e dimensione.
 */
add_shortcode(
	'forestas_allegati',
	function () {
		$ids = forestas_meta_ids( (int) get_the_ID(), 'wpf_allegati' );
		if ( ! $ids ) {
			return '';
		}
		$voci = '';
		foreach ( $ids as $id ) {
			$file = get_attached_file( $id );
			if ( ! $file ) {
				continue;
			}
			$voci .= sprintf(
				'<li><a class="forestas-file" href="%s" download><span class="forestas-file__descrizione">%s</span><span class="forestas-file__info"><i class="far fa-file forestas-file__icona" aria-hidden="true"></i> %s - %s</span></a></li>',
				esc_url( wp_get_attachment_url( $id ) ),
				esc_html( get_the_title( $id ) ),
				esc_html( strtoupper( pathinfo( $file, PATHINFO_EXTENSION ) ) ),
				esc_html( is_readable( $file ) ? forestas_dimensione_file( (int) filesize( $file ) ) : '' )
			);
		}
		return $voci === '' ? '' : sprintf(
			'<section class="forestas-allegati"><h2 class="forestas-allegati-titolo">%s</h2><ul>%s</ul></section>',
			esc_html( forestas_testo( 'Allegati', 'Attachments' ) ),
			$voci
		);
	}
);

/*
 * Galleria, come nel tema Drupal (field--field-galleria-media e field--field-block-with-title-base): titolo
 * «Photogallery» con il filo colorato a sinistra, griglia di miniature quadrate (3 colonne su telefono, 4
 * da tablet); al passaggio del mouse la lente di Blazy. Il clic apre la lightbox di Drupal
 * (slick_lightbox) sull'immagine intera, con la didascalia configurata nel formatter della galleria:
 * testo alternativo, poi autore e credits dell'immagine. Link e didascalia nascosta hanno le classi e gli
 * attributi di blazy.html.twig, che lo script del modulo legge (assets/js/galleria-lightbox.js).
 */
add_shortcode(
	'forestas_galleria',
	function () {
		$voci      = '';
		$posizione = 0;
		// Un'immagine tolta dalla libreria resta nel meta finché la pagina non si risalva: si salta
		foreach ( forestas_meta_ids( (int) get_the_ID(), 'wpf_galleria' ) as $id ) {
			$miniatura = wp_get_attachment_image( $id, 'medium', false, [ 'loading' => 'lazy' ] );
			if ( $miniatura === '' ) {
				continue;
			}
			$voci .= sprintf(
				'<li><a href="%1$s" class="blazy__slick-lightbox" data-slick-lightbox-trigger data-b-delta="%2$d">%3$s<span class="forestas-galleria__lente" aria-hidden="true"></span></a>'
				. '<div class="visually-hidden"><p>%4$s</p><em class="auth">%5$s</em><em class="cred">%6$s</em></div></li>',
				// Il file originale, come su Drupal (box_style vuoto), non la copia ridotta «-scaled» di WordPress
				esc_url( (string) ( wp_get_original_image_url( $id ) ?: wp_get_attachment_image_url( $id, 'full' ) ) ),
				$posizione++,
				$miniatura,
				esc_html( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ),
				esc_html( (string) get_post_meta( $id, 'wpf_autore', true ) ),
				esc_html( (string) get_post_meta( $id, 'wpf_credits', true ) )
			);
		}
		if ( $voci === '' ) {
			return '';
		}
		forestas_galleria_lightbox();
		// Etichette per i pulsanti della lightbox, che la libreria ha solo in inglese (assets/js/galleria-lightbox.js)
		return sprintf(
			'<section class="forestas-galleria"><h2 class="forestas-galleria-titolo">%1$s</h2><ul data-slick-lightbox-gallery data-precedente="%2$s" data-successivo="%3$s" data-chiudi="%4$s">%5$s</ul></section>',
			esc_html( forestas_testo( 'Galleria fotografica', 'Photogallery' ) ),
			esc_attr( forestas_testo( 'Precedente', 'Previous' ) ),
			esc_attr( forestas_testo( 'Successiva', 'Next' ) ),
			esc_attr( forestas_testo( 'Chiudi', 'Close' ) ),
			$voci
		);
	}
);

/*
 * File della lightbox, solo nelle pagine con una galleria: i CSS dei moduli Slick hanno regole globali
 * (assets/vendor/drupal-slick/README.md). Stesso ordine delle librerie di Drupal: Slick, CSS dei moduli,
 * slick-lightbox, poi il caricamento del modulo.
 */
function forestas_galleria_lightbox(): void {
	$base     = get_stylesheet_directory_uri() . '/assets/vendor/';
	$versione = forestas_versione();
	wp_enqueue_style( 'slick', $base . 'slick/slick.css', [], '1.8.1' );
	wp_enqueue_style( 'drupal-slick-module', $base . 'drupal-slick/slick.module.css', [ 'slick' ], $versione );
	wp_enqueue_style( 'drupal-slick-theme', $base . 'drupal-slick/slick.theme.css', [ 'drupal-slick-module' ], $versione );
	wp_enqueue_style( 'drupal-slick-skin', $base . 'drupal-slick/slick.theme--default.css', [ 'drupal-slick-theme' ], $versione );
	wp_enqueue_style( 'slick-lightbox', $base . 'slick-lightbox/slick-lightbox.css', [ 'drupal-slick-skin' ], '0.2.12' );
	wp_enqueue_style( 'drupal-slick-lightbox', $base . 'drupal-slick/slick-lightbox.module.css', [ 'slick-lightbox' ], $versione );
	wp_enqueue_style( 'forestas-galleria-lightbox', get_stylesheet_directory_uri() . '/assets/css/galleria-lightbox.css', [ 'drupal-slick-lightbox' ], $versione );
	wp_enqueue_script( 'slick', $base . 'slick/slick.min.js', [ 'jquery' ], '1.8.1', true );
	wp_enqueue_script( 'slick-lightbox', $base . 'slick-lightbox/slick-lightbox.min.js', [ 'slick' ], '0.2.12', true );
	wp_enqueue_script( 'forestas-galleria-lightbox', get_stylesheet_directory_uri() . '/assets/js/galleria-lightbox.js', [ 'slick-lightbox' ], $versione, true );
}

/*
 * Mappa interattiva come su Drupal (oc:8758): il percorso, il titolo della pagina nascosto alla vista, poi
 * il blocco «mappa_interattiva» del tema (block--mappa-interattiva.html.twig): titolo nascosto, barra con
 * i pulsanti per allargare la mappa a tutto schermo e tornare indietro, iframe dell'app. L'indirizzo è
 * quello scritto nel blocco (MappaInterattivaBlock), non l'iframe del testo, che su Drupal non si mostra.
 * Il cambio di mappa con ?id= (sentiero, itinerario o punto di interesse) arriverà con le loro pagine.
 */
const FORESTAS_MAPPA_URL = 'https://32.app.webmapp.it/#/map';

add_shortcode(
	'forestas_mappa',
	function () {
		$id = get_the_ID();
		if ( ! $id ) {
			return '';
		}
		wp_enqueue_script( 'forestas-mappa', get_stylesheet_directory_uri() . '/assets/js/mappa.js', [], forestas_versione(), [ 'in_footer' => true, 'strategy' => 'defer' ] );
		$titolo = get_the_title( $id );
		return sprintf(
			'%1$s<h1 class="screen-reader-text">%2$s</h1>'
			. '<div class="forestas-mappa is-shrinked"><h2 class="screen-reader-text">%3$s</h2>'
			. '<div class="forestas-mappa__barra">'
			. '<button type="button" id="mapExpander" class="forestas-pulsante forestas-pulsante--icona is-map-expander"><span>%4$s</span><i aria-hidden="true" class="far fa-expand-arrows forestas-pulsante__icona"></i></button>'
			. '<button type="button" id="mapShrinker" class="forestas-pulsante forestas-pulsante--icona is-map-shrinker"><span>%5$s</span><i aria-hidden="true" class="far fa-compress-arrows-alt forestas-pulsante__icona"></i></button>'
			. '</div>'
			. '<div><iframe id="trailMap" src="%6$s" name="MapIframe" scrolling="no" frameborder="0" marginheight="0px" marginwidth="0px" height="100%%" width="100%%" allowfullscreen="" title="%7$s"></iframe></div>'
			. '</div>',
			forestas_percorso( $id ),
			esc_html( $titolo ),
			esc_html( forestas_testo( "Mappa interattiva: cerca sentieri, itinerari, punti d'interesse", 'Interactive map: search for trails, routes, Point of Interest' ) ),
			esc_html( forestas_testo( 'Espandi la mappa', 'Expand map' ) ),
			esc_html( forestas_testo( 'Esci dalla modalità mappa a schermo intero', 'Minimize fullscreen map' ) ),
			esc_url( FORESTAS_MAPPA_URL ),
			esc_attr( forestas_testo( 'Mappa interattiva Sardegna Sentieri', 'Sardegna Sentieri interactive map' ) )
		);
	}
);
