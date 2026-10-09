<?php
/**
 * Costanti e funzioni che servono anche a init-wordpress.sh, che le legge con «php -r» senza
 * WordPress per non ripeterle in bash (oc:8717). Il file deve restare piccolo e senza dipendenze: se
 * non si carica, l'init usa i suoi valori di riserva e WordPress parte lo stesso. Lo carica comune.php.
 */

// Percorsi montati dal compose (compose.yml)
const WPF_DIR_PLUGIN = '/opt/wp-forestas/plugins';
const WPF_DIR_CONFIG = '/opt/wp-forestas/config';

// Opzioni di controllo, lette anche da init-wordpress.sh. Scritta dall'init quando installa WordPress:
// solo un sito nato così riceve l'apply automatico. La seconda la scrive l'apply riuscito.
const WPF_OPZIONE_DA_FARE = 'wp_forestas_config_da_applicare';
const WPF_OPZIONE_FATTO   = 'wp_forestas_config_applicata';
// Blocco che impedisce due apply nello stesso momento (l'automatico dell'init e uno lanciato dall'host)
const WPF_OPZIONE_APPLY_IN_CORSO = 'wp_forestas_apply_in_corso';
// Dopo quanti secondi un apply «in corso» si considera interrotto e il blocco si può togliere. Deve
// superare il tempo massimo dell'apply automatico, che init-wordpress.sh ricava da qui
const WPF_APPLY_SCADENZA = 2400;
// Plugin commerciali la cui attivazione automatica è fallita: l'init la ritenta (vedi il passo 7c)
const WPF_OPZIONE_DA_ATTIVARE = 'wp_forestas_plugin_da_attivare';
// Data dell'installazione fatta dall'init (passo 4): l'apply automatico vale solo nei giorni subito dopo
const WPF_OPZIONE_INSTALLATO = 'wp_forestas_installato_il';
// Per quanti secondi dall'installazione l'init può ancora applicare config/ da solo: dopo, il sito può
// essere stato cambiato dal pannello e un apply senza anteprima né backup cancellerebbe le modifiche
const WPF_APPLY_FINESTRA = 3 * 86400;

/**
 * Plugin commerciali da docker/plugins/commerciali.txt, montato in WPF_DIR_PLUGIN: «#» apre un
 * commento, spazi e righe vuote si ignorano. È l'unica lettura del file: init-wordpress.sh e
 * bin/wordpress-config.sh la chiamano con «php -r».
 */
function wpf_plugin_commerciali(): array {
	$file = WPF_DIR_PLUGIN . '/commerciali.txt';
	if ( ! is_readable( $file ) ) {
		if ( class_exists( 'WP_CLI' ) ) {
			WP_CLI::warning( "{$file} non trovato: elenco dei plugin commerciali vuoto" );
		}
		return [];
	}
	$slug = array_map( fn( $r ) => preg_replace( '/\s+/', '', preg_replace( '/#.*/', '', $r ) ), file( $file ) );
	return array_values( array_filter( $slug, 'strlen' ) );
}

/**
 * Vero se l'indirizzo punta a questa macchina (localhost o 127.0.0.1): lì licenze e chiavi dei servizi
 * esterni non si applicano, perché il sito si registrerebbe presso i fornitori con un indirizzo locale.
 * init-wordpress.sh la usa con «php -r» su WP_URL.
 */
function wpf_indirizzo_locale( string $url ): bool {
	return in_array( parse_url( $url, PHP_URL_HOST ), [ 'localhost', '127.0.0.1' ], true );
}
