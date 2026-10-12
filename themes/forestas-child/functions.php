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
 * UpSolution Core carica lo style.css del child («theme-style») con la versione di Impreza: l'URL
 * cambierebbe solo aggiornando il padre e i browser terrebbero il CSS vecchio. Qui prende la Version
 * del child, da alzare a ogni modifica del CSS.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		$stile = wp_styles()->query( 'theme-style', 'registered' );
		if ( $stile && is_child_theme() ) {
			$stile->ver = wp_get_theme()->get( 'Version' );
		}
	},
	PHP_INT_MAX
);

/*
 * Logo del sito, usato da header e footer di Impreza (oc:8757). Il file sta nel child e non negli
 * uploads, che non sono versionati: su un sito ricreato da zero il logo c'è comunque. La Version del
 * child nell'URL fa scaricare il file nuovo quando viene sostituito. Il link porta alla home nella
 * lingua corrente di WPML.
 */
add_shortcode(
	'forestas_logo',
	function () {
		$src = get_stylesheet_directory_uri() . '/assets/img/logo.png';
		$src = add_query_arg( 'ver', wp_get_theme()->get( 'Version' ), $src );

		return sprintf(
			'<a class="forestas-logo" href="%s"><img src="%s" alt="%s" width="1155" height="90"></a>',
			esc_url( apply_filters( 'wpml_home_url', home_url( '/' ) ) ),
			esc_url( $src ),
			esc_attr__( 'Sardegna Sentieri', 'forestas-child' )
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
 * Selettore di lingua dell'header, «IT · EN» in linea come nel mockup (oc:8757). Il dropdown di
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
 * Pannello del menu su tablet e mobile, come sul sito attuale (oc:8757): dopo le voci principali, una
 * riga con i link di servizio (menu «servizio-menu») e la riga «Seguici su» con i social. Le due righe
 * sono voci aggiunte in coda al menu principale, nascoste su desktop dalla classe
 * «forestas-solo-mobile». Testo e social si leggono dall'header della lingua corrente (elementi
 * «html:1» e «socials:1»), così gli URL restano solo in config/post.json.
 */
add_filter(
	'wp_nav_menu_items',
	function ( $voci, $args ) {
		$principale = wp_get_nav_menu_object( 'main-menu' );
		$servizio   = wp_get_nav_menu_object( apply_filters( 'wpml_object_id', wp_get_nav_menu_object( 'servizio-menu' )->term_id ?? 0, 'nav_menu', true ) );
		$questo     = $args->menu instanceof WP_Term ? $args->menu : wp_get_nav_menu_object( $args->menu );

		if ( ! $principale || ! $servizio || ! $questo ) {
			return $voci;
		}
		if ( (int) apply_filters( 'wpml_object_id', $questo->term_id, 'nav_menu', true, apply_filters( 'wpml_default_language', null ) ) !== (int) $principale->term_id ) {
			return $voci;
		}

		$link = '';
		foreach ( wp_get_nav_menu_items( $servizio ) ?: [] as $voce ) {
			$link .= sprintf( '<a href="%s">%s</a>', esc_url( $voce->url ), esc_html( $voce->title ) );
		}
		$voci .= '<li class="menu-item forestas-solo-mobile forestas-pannello-servizio">' . $link . '</li>';

		$header = function_exists( 'us_get_page_area_id' ) ? json_decode( (string) get_post_field( 'post_content', us_get_page_area_id( 'header' ) ), true ) : null;
		$social = $header['data']['socials:1']['items'] ?? [];
		if ( $social ) {
			$seguici = wp_strip_all_tags( rawurldecode( base64_decode( $header['data']['html:1']['content'] ?? '' ) ) );
			$voci   .= sprintf(
				'<li class="menu-item forestas-solo-mobile forestas-pannello-social"><span class="forestas-seguici">%s</span>%s</li>',
				esc_html( $seguici ),
				do_shortcode( '[us_socials items="' . rawurlencode( wp_json_encode( $social ) ) . '" icons_color="text" shape="none" hide_tooltip="1" el_class="forestas-social"]' )
			);
		}

		return $voci;
	},
	10,
	2
);

/*
 * Blocco istituzionale del PR FESR Sardegna 2021-2027 (Coesione Italia, emblema UE con «Cofinanziato
 * dall'Unione europea», Repubblica, Regione), richiesto dal cliente in header e footer (oc:8757). Sono i
 * file ufficiali di sardegnaprogrammazione.it ridotti a 1400px di larghezza, nel child come il logo e
 * non negli uploads: «versione 1» (loghi su una riga, header) e, con versione="2", «versione 2» (testi
 * sotto i loghi, footer). L'ordine e le proporzioni dei loghi sono quelli dei file e non vanno alterati
 * (Linee guida beneficiari PR FESR Sardegna, «Blocco istituzionale»).
 */
add_shortcode(
	'forestas_fesr',
	function ( $atts ) {
		$versioni = [
			'1' => [ 'blocco-istituzionale-fesr.png', 1400, 98 ],
			'2' => [ 'blocco-istituzionale-fesr-2.png', 1400, 161 ],
		];
		$versione = shortcode_atts( [ 'versione' => '1' ], $atts )['versione'];
		[ $file, $larghezza, $altezza ] = $versioni[ $versione ] ?? $versioni['1'];

		$src = get_stylesheet_directory_uri() . '/assets/img/' . $file;
		$src = add_query_arg( 'ver', wp_get_theme()->get( 'Version' ), $src );

		return sprintf(
			'<img class="forestas-fesr forestas-fesr-%s" src="%s" alt="%s" width="%d" height="%d">',
			esc_attr( $versione ),
			esc_url( $src ),
			esc_attr__( 'Coesione Italia 21-27 Sardegna, cofinanziato dall\'Unione europea, Repubblica Italiana, Regione Autonoma della Sardegna', 'forestas-child' ),
			$larghezza,
			$altezza
		);
	}
);

/*
 * Testo nella lingua corrente di WPML, per le poche stringhe del child che lo screen reader legge
 * (oc:8757). Il child non ha file di traduzione: le lingue del sito sono due.
 */
function forestas_testo( string $italiano, string $inglese ): string {
	return 'it' === apply_filters( 'wpml_current_language', 'it' ) ? $italiano : $inglese;
}

/*
 * Link «Vai al contenuto» come primo elemento della pagina (oc:8757): con la tastiera evita di
 * attraversare i circa 25 link dell'header (WCAG 2.4.1, Linee guida AgID). È visibile solo quando
 * riceve il focus; «page-content» è l'id del <main> di Impreza.
 */
add_action(
	'wp_body_open',
	function () {
		printf( '<a class="forestas-salta" href="#page-content">%s</a>', esc_html( forestas_testo( 'Vai al contenuto', 'Skip to content' ) ) );
	}
);

/*
 * Accessibilità del menu mobile (oc:8757): nome del pulsante uguale alla scritta visibile («menu» /
 * «chiudi», WCAG 2.5.3), chiusura con Esc e focus che resta nel pannello finché è aperto.
 */
add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_script(
			'forestas-menu-mobile',
			get_stylesheet_directory_uri() . '/assets/js/menu-mobile.js',
			[],
			wp_get_theme()->get( 'Version' ),
			[ 'in_footer' => true, 'strategy' => 'defer' ]
		);
		wp_localize_script(
			'forestas-menu-mobile',
			'forestasMenu',
			[
				'apri'   => forestas_testo( 'Menu', 'Menu' ),
				'chiudi' => forestas_testo( 'Chiudi', 'Close' ),
			]
		);
	}
);
