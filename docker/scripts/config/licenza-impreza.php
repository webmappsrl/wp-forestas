<?php
/**
 * Attiva la licenza di Impreza con il segreto del .env, se non è già quello salvato (oc:8717).
 *
 * Fa la stessa chiamata che fa il tema (us_check_and_activate_theme in common/functions/helpers.php):
 * /envato_auth con segreto e dominio del sito, e salva ciò che risponde l'API di UpSolution.
 * Esce con errore se l'attivazione non riesce, così l'init lo segnala e ritenta al prossimo avvio.
 */

$segreto = (string) getenv( 'IMPREZA_LICENSE_SECRET' );
if ( $segreto === '' || ! function_exists( 'us_api' ) ) {
	WP_CLI::error( 'manca IMPREZA_LICENSE_SECRET o Impreza non è il tema attivo' );
}
if ( get_option( 'us_license_secret' ) === $segreto ) {
	WP_CLI::log( 'licenza di Impreza già attiva con il segreto del .env' );
	return;
}

$dominio  = parse_url( site_url(), PHP_URL_HOST );
$risposta = us_api(
	'/envato_auth',
	[ 'secret' => $segreto, 'domain' => $dominio, 'version' => US_THEMEVERSION ],
	US_API_RETURN_ARRAY
);
$corpo = is_array( $risposta ) ? ( $risposta['body'] ?? null ) : null;
if ( ! is_array( $corpo ) || (int) ( $corpo['status'] ?? 0 ) !== 1 ) {
	WP_CLI::error( "UpSolution non ha attivato la licenza per {$dominio}" );
}

if ( ( $corpo['site_type'] ?? '' ) === 'dev' ) {
	update_option( 'us_license_dev_activated', 1 );
	delete_option( 'us_license_activated' );
} else {
	update_option( 'us_license_activated', 1 );
	delete_option( 'us_license_dev_activated' );
}
if ( isset( $corpo['can_modify_favorite_sections'] ) ) {
	update_option( 'us_can_modify_favorite_sections', (int) $corpo['can_modify_favorite_sections'] );
}
update_option( 'us_license_secret', $segreto );
delete_transient( 'us_update_addons_data_' . US_THEMENAME );
WP_CLI::success( 'licenza di Impreza attivata (' . ( $corpo['site_type'] ?? 'produzione' ) . ')' );
