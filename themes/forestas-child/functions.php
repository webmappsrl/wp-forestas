<?php
/**
 * Child theme di Impreza per Sardegna Sentieri (Forestas), oc:8717.
 *
 * Lo style.css di questo child lo carica UpSolution Core da sé: qui va solo il PHP che serve davvero.
 * Il file è codice del repo wp-forestas: si modifica nel repo, non dal pannello.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * Testo nella lingua corrente di WPML, per le stringhe del child che si vedono o che lo screen reader
 * legge (oc:8757). Il child non ha file di traduzione: le lingue del sito sono due, italiano e inglese.
 */
function forestas_testo( string $italiano, string $inglese ): string {
	return 'it' === apply_filters( 'wpml_current_language', 'it' ) ? $italiano : $inglese;
}

/*
 * URL di un file del child con la Version del child come «?ver=», così un file sostituito non resta
 * nella cache dei browser (oc:8757).
 */
function forestas_asset_url( string $percorso ): string {
	return add_query_arg( 'ver', forestas_versione(), get_stylesheet_directory_uri() . '/' . $percorso );
}

/*
 * Version del child (intestazione di style.css): da alzare a ogni modifica di CSS, JS o immagini.
 */
function forestas_versione(): string {
	return (string) wp_get_theme()->get( 'Version' );
}

/*
 * Logo del sito, usato da header e footer di Impreza (oc:8757). Il file sta nel child e non negli
 * uploads, che non sono versionati: su un sito ricreato da zero il logo c'è comunque. Il link porta
 * alla home nella lingua corrente di WPML.
 */
add_shortcode(
	'forestas_logo',
	function () {
		return sprintf(
			'<a class="forestas-logo" href="%s"><img src="%s" alt="Sardegna Sentieri" width="1155" height="90"></a>',
			esc_url( apply_filters( 'wpml_home_url', home_url( '/' ) ) ),
			esc_url( forestas_asset_url( 'assets/img/logo.png' ) )
		);
	}
);

/*
 * Anno corrente per il copyright del footer (oc:8757): scritto a mano invecchierebbe a ogni capodanno,
 * in due lingue.
 */
add_shortcode(
	'forestas_anno',
	function () {
		return wp_date( 'Y' );
	}
);

/*
 * Selettore di lingua dell'header, «IT EN» in linea come sul sito attuale (oc:8757). Il dropdown di
 * Impreza mostra solo bandiere o nomi lunghi: qui si leggono le lingue da WPML e si stampa il codice
 * breve, con la lingua corrente evidenziata.
 */
add_shortcode(
	'forestas_lingue',
	function () {
		$lingue = apply_filters( 'wpml_active_languages', null, [ 'skip_missing' => 0 ] );
		if ( empty( $lingue ) ) {
			return '';
		}

		$voci = [];
		foreach ( $lingue as $lingua ) {
			$codice = strtoupper( substr( $lingua['code'], 0, 2 ) );
			$voci[] = $lingua['active']
				? sprintf( '<b lang="%s">%s</b>', esc_attr( $lingua['code'] ), esc_html( $codice ) )
				: sprintf( '<a href="%s" hreflang="%s" lang="%s">%s</a>', esc_url( $lingua['url'] ), esc_attr( $lingua['code'] ), esc_attr( $lingua['code'] ), esc_html( $codice ) );
		}

		return '<nav class="forestas-lingue" aria-label="' . esc_attr( forestas_testo( 'Lingua', 'Language' ) ) . '">' . implode( '', $voci ) . '</nav>';
	}
);

/*
 * Loghi dell'header, gli stessi sei del footer (oc:8757). In testa il blocco istituzionale del PR FESR
 * Sardegna 2021-2027 (Coesione Italia, emblema UE con «Cofinanziato dall'Unione europea», Repubblica,
 * Regione), richiesto dal cliente: è il file ufficiale di sardegnaprogrammazione.it «versione 1» (loghi
 * su una riga), ridotto a 1400px di larghezza, intero, perché ordine e proporzioni dei loghi non vanno
 * alterati (Linee guida beneficiari PR FESR Sardegna, «Blocco istituzionale»). Dopo il blocco, con lo
 * stesso separatore grigio, Forestas e CAI, alti quanto la bandiera UE: l'emblema non è mai più piccolo
 * degli altri loghi. Nel footer gli stessi loghi stanno separati, in [forestas_loghi_footer].
 */
add_shortcode(
	'forestas_fesr',
	function () {
		$immagine = fn( string $file, int $larghezza, int $altezza, string $classe, string $alt ): string => sprintf(
			'<img class="%s" src="%s" alt="%s" width="%d" height="%d">',
			esc_attr( $classe ),
			esc_url( forestas_asset_url( 'assets/img/' . $file ) ),
			esc_attr( $alt ),
			$larghezza,
			$altezza
		);
		$separatore = '<span class="forestas-loghi-header-separatore" aria-hidden="true"></span>';

		return '<div class="forestas-loghi-header">'
			. $immagine(
				'blocco-istituzionale-fesr.png',
				1400,
				98,
				'forestas-fesr',
				forestas_testo(
					'Coesione Italia 21-27 Sardegna, cofinanziato dall\'Unione europea, Repubblica Italiana, Regione Autonoma della Sardegna',
					'Coesione Italia 21-27 Sardegna, co-funded by the European Union, Italian Republic, Autonomous Region of Sardinia'
				)
			)
			. $separatore
			. $immagine( 'loghi-header/forestas-sardegna-foreste.png', 272, 176, 'forestas-logo-header', 'Forestas - Sardegna Foreste' )
			. $separatore
			. $immagine( 'loghi-header/cai-club-alpino-italiano.png', 220, 178, 'forestas-logo-header', forestas_testo( 'CAI - Club Alpino Italiano', 'CAI - Italian Alpine Club' ) )
			. '</div>';
	}
);

/*
 * Riga dei loghi in cima al footer, come sul sito attuale (oc:8757): sei loghi in caselle uguali. I
 * primi quattro sono quelli del blocco istituzionale PR FESR Sardegna 2021-2027, ritagliati dal file
 * ufficiale «versione 2» con la stessa scala e la stessa fascia verticale, così le proporzioni fra i
 * loghi restano quelle del blocco; gli ultimi due, Forestas e CAI, sono i file del sito attuale. Tutti
 * nel child (assets/img/loghi-footer), in PNG trasparenti nel formato dei loghi del sito attuale.
 */
add_shortcode(
	'forestas_loghi_footer',
	function () {
		$loghi = [
			[ 'coesione-italia-sardegna.png', 684, 356, 'Coesione Italia 21-27 Sardegna', 'Coesione Italia 21-27 Sardegna' ],
			[ 'cofinanziato-unione-europea.png', 684, 356, 'Cofinanziato dall\'Unione europea', 'Co-funded by the European Union' ],
			[ 'repubblica-italiana.png', 684, 356, 'Repubblica Italiana', 'Italian Republic' ],
			[ 'regione-sardegna.png', 684, 356, 'Regione Autonoma della Sardegna', 'Autonomous Region of Sardinia' ],
			[ 'forestas-sardegna-foreste.png', 308, 176, 'Forestas - Sardegna Foreste', 'Forestas - Sardegna Foreste' ],
			[ 'cai-club-alpino-italiano.png', 342, 178, 'CAI - Club Alpino Italiano', 'CAI - Italian Alpine Club' ],
		];

		$html = '';
		foreach ( $loghi as [ $file, $larghezza, $altezza, $italiano, $inglese ] ) {
			$html .= sprintf(
				'<div class="forestas-logo-ente"><img src="%s" alt="%s" width="%d" height="%d" loading="lazy"></div>',
				esc_url( forestas_asset_url( 'assets/img/loghi-footer/' . $file ) ),
				esc_attr( forestas_testo( $italiano, $inglese ) ),
				$larghezza,
				$altezza
			);
		}

		return '<div class="forestas-loghi-footer">' . $html . '</div>';
	}
);

/*
 * Voce aggiunta in coda al menu principale per il pannello mobile, nascosta su desktop (oc:8757).
 */
function forestas_voce_pannello( string $classe, string $contenuto ): string {
	return '<li class="menu-item forestas-solo-mobile ' . esc_attr( $classe ) . '">' . $contenuto . '</li>';
}

/*
 * Riga dei link di servizio per il pannello del menu mobile: le voci di «servizio-menu» nella lingua
 * corrente (oc:8757), con target, rel e title impostati nel menu. Il menu di servizio è piatto: le
 * sottovoci, se un giorno ci fossero, compaiono in fila con le altre.
 */
function forestas_pannello_servizio(): string {
	$originale = wp_get_nav_menu_object( 'servizio-menu' );
	$servizio  = $originale ? wp_get_nav_menu_object( apply_filters( 'wpml_object_id', $originale->term_id, 'nav_menu', true ) ) : null;
	if ( ! $servizio ) {
		return '';
	}

	$link = '';
	foreach ( wp_get_nav_menu_items( $servizio ) ?: [] as $voce ) {
		// Valori grezzi nella mappa, escape al momento della stampa
		$attributi = array_filter(
			[
				'href'   => $voce->url,
				'target' => $voce->target,
				'rel'    => $voce->xfn,
				'title'  => $voce->attr_title,
			]
		);
		$html = '';
		foreach ( $attributi as $nome => $valore ) {
			$html .= sprintf( ' %s="%s"', $nome, 'href' === $nome ? esc_url( $valore ) : esc_attr( $valore ) );
		}
		$link .= sprintf( '<a%s>%s</a>', $html, esc_html( $voce->title ) );
	}

	return forestas_voce_pannello( 'forestas-pannello-servizio', $link );
}

/*
 * Riga «Seguici su» con i social per il pannello del menu mobile (oc:8757). Testo e social si leggono
 * dall'header della lingua corrente, così gli URL non si ripetono nel PHP. Gli elementi si cercano per
 * tipo e per classe, non per id: l'Header Builder rinumera gli id quando un elemento si ricrea.
 * Nel JSON gli elementi «html» hanno il contenuto in base64(rawurlencode()).
 */
function forestas_pannello_social(): string {
	if ( ! function_exists( 'us_get_page_area_id' ) ) {
		return '';
	}
	$header = json_decode( (string) get_post_field( 'post_content', us_get_page_area_id( 'header' ) ), true );

	$social  = [];
	$seguici = '';
	foreach ( $header['data'] ?? [] as $id => $elemento ) {
		$tipo   = strtok( (string) $id, ':' );
		$classi = (string) ( $elemento['el_class'] ?? '' );
		if ( 'socials' === $tipo && ! $social ) {
			$social = $elemento['items'] ?? [];
		} elseif ( 'html' === $tipo && str_contains( $classi, 'forestas-seguici-wrap' ) ) {
			$seguici = wp_strip_all_tags( rawurldecode( base64_decode( (string) ( $elemento['content'] ?? '' ) ) ) );
		}
	}
	if ( ! $social ) {
		return '';
	}

	return forestas_voce_pannello(
		'forestas-pannello-social',
		'<span class="forestas-seguici">' . esc_html( $seguici ) . '</span>'
			. do_shortcode( '[us_socials items="' . rawurlencode( wp_json_encode( $social ) ) . '" icons_color="text" shape="none" hide_tooltip="1" el_class="forestas-social"]' )
	);
}

/*
 * Pannello del menu su tablet e mobile, come sul sito attuale (oc:8757): dopo le voci principali, la
 * riga dei link di servizio e la riga «Seguici su» con i social. Sono voci aggiunte in coda al menu
 * principale (in ogni lingua), nascoste su desktop dalla classe «forestas-solo-mobile». Solo nell'elemento
 * «menu» di Impreza (walker US_Walker_Nav_Menu): un widget o un «menu aggiuntivo» che mostra
 * «main-menu» non le riceve.
 */
add_filter(
	'wp_nav_menu_items',
	function ( $voci, $args ) {
		if ( ! class_exists( 'US_Walker_Nav_Menu' ) || ! ( $args->walker instanceof US_Walker_Nav_Menu ) ) {
			return $voci;
		}
		$principale = wp_get_nav_menu_object( 'main-menu' );
		$questo     = $args->menu instanceof WP_Term ? $args->menu : wp_get_nav_menu_object( $args->menu );
		if ( ! $principale || ! $questo ) {
			return $voci;
		}
		// Tutti e due nella lingua predefinita: con i filtri di WPML attivi (UAT) anche «main-menu» cercato
		// per slug arriva già nella lingua della pagina, e il confronto diretto fallirebbe in inglese
		$predefinita = apply_filters( 'wpml_default_language', null );
		$originale   = fn( WP_Term $menu ): int => (int) apply_filters( 'wpml_object_id', $menu->term_id, 'nav_menu', true, $predefinita );
		if ( $originale( $questo ) !== $originale( $principale ) ) {
			return $voci;
		}

		return $voci . forestas_pannello_servizio() . forestas_pannello_social();
	},
	10,
	2
);

/*
 * Link «Vai al contenuto» come primo elemento della pagina (oc:8757): con la tastiera evita di
 * attraversare i circa 25 link dell'header (WCAG 2.4.1, Linee guida AgID). È visibile solo quando
 * riceve il focus; «page-content» è l'id del <main> di Impreza. Perché non l'opzione nativa di Impreza:
 * vedi il commento in style.css.
 */
add_action(
	'wp_body_open',
	function () {
		printf( '<a class="forestas-salta" href="#page-content">%s</a>', esc_html( forestas_testo( 'Vai al contenuto', 'Skip to content' ) ) );
	}
);

/*
 * CSS e JS del child (oc:8717, oc:8757). UpSolution Core carica lo style.css del child («theme-style»)
 * con la versione di Impreza: l'URL cambierebbe solo aggiornando il padre e i browser terrebbero il CSS
 * vecchio, quindi qui prende la Version del child. Priorità massima perché lo stile è registrato da
 * UpSolution Core sullo stesso hook.
 *
 * menu-mobile.js: cosa fa è nel suo commento di testa. La scritta «menu» viene dall'Header Builder;
 * quella a menu aperto («chiudi» / «close») arriva da qui, con wp_localize_script.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$stile = wp_styles()->query( 'theme-style', 'registered' );
		if ( $stile && is_child_theme() ) {
			$stile->ver = forestas_versione();
		}

		wp_enqueue_script(
			'forestas-menu-mobile',
			get_stylesheet_directory_uri() . '/assets/js/menu-mobile.js',
			[],
			forestas_versione(),
			[ 'in_footer' => true, 'strategy' => 'defer' ]
		);
		wp_localize_script( 'forestas-menu-mobile', 'forestasMenu', [ 'chiudi' => forestas_testo( 'chiudi', 'close' ) ] );
	},
	PHP_INT_MAX
);
