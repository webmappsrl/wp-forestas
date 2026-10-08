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
