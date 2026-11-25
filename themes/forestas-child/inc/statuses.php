<?php
/**
 * Stati delle famiglie di forestas nel registro degli stati del plugin (oc:9000): validazione catastale e
 * accessibilità. La percorribilità è di wm-package e il plugin ce l'ha già.
 *
 * Dal registro restano solo ordine e stato di assenza (oc:9018): i nomi arrivano con i termini dello shard. Gli stati e
 * il loro ordine sono quelli di `app/Console/Commands/ConfigureTaxonomyTermsCommand.php` dello shard (dopo oc:8989).
 * Restano due ripieghi dichiarati, finché lo shard non pubblica l'elenco degli stati di una famiglia (ticket sullo
 * shard): il nome dello stato di assenza della validazione, che non arriva con nessun dato, e i nomi delle legende delle
 * tacche (FORESTAS_STATUS_LEGEND_NAMES), che elencano anche gli stati che il percorso non ha.
 *
 * Le tacche sono gli stati della famiglia e ogni stato accende fino alla sua posizione: validazione 5 tacche («Non
 * verificato» nessuna, «In revisione-validazione» 2 … «Certificato» 5), accessibilità 3 (la famiglia non ha uno stato
 * di assenza; senza grado nessuna tacca). I gradi hanno le sigle maiuscole dello shard (`inclusivity-difficulty-AE`):
 * scritte minuscole le tacche restavano spente.
 *
 * @package forestas-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Nomi delle legende delle tacche, per tutti gli stati di una famiglia (ripiego dichiarato, oc:9018): la legenda elenca
 * anche gli stati che il percorso non ha, e i loro nomi non arrivano da nessuna API. Come le tre tipologie di
 * disabilità, la legenda resta com'era (decisione del 24/11); lo shard deve avere gli stessi nomi.
 */
const FORESTAS_STATUS_LEGEND_NAMES = [
	'validation-status'      => [
		'validation-status-unverified'       => [ 'Non verificato', 'Not verified' ],
		'validation-status-under-review'     => [ 'In revisione-validazione', 'Under review' ],
		'validation-status-pre-registration' => [ 'In pre-accatastamento o manutenzione', 'Pre-registration or maintenance' ],
		'validation-status-validated'        => [ 'Validato', 'Validated' ],
		'validation-status-certified'        => [ 'Certificato', 'Certified' ],
	],
	'walkability-status'     => [
		'walkability-status-unknown'            => [ 'Sconosciuta', 'Unknown' ],
		'walkability-status-not-walkable'       => [ 'Non percorribile', 'Not walkable' ],
		'walkability-status-partially-walkable' => [ 'Parzialmente percorribile', 'Partially walkable' ],
		'walkability-status-walkable'           => [ 'Percorribile', 'Walkable' ],
	],
	'inclusivity-difficulty' => [
		'inclusivity-difficulty-AT'  => [ 'AT', 'AT' ],
		'inclusivity-difficulty-AE'  => [ 'AE', 'AE' ],
		'inclusivity-difficulty-AEE' => [ 'AEE', 'AEE' ],
	],
];

/**
 * Famiglie di forestas nel registro del plugin: ordine, stato di assenza e il suo nome.
 *
 * @param array<string, array<string, mixed>> $families Famiglie già registrate.
 * @return array<string, array<string, mixed>>
 */
function forestas_status_families( array $families ): array {
	$families['validation-status']      = [
		'absent' => 'validation-status-unverified',
		'states' => [
			[
				'identifier' => 'validation-status-unverified',
				'name'       => [
					'it' => 'Non verificato',
					'en' => 'Not verified',
				],
			],
			[ 'identifier' => 'validation-status-under-review' ],
			[ 'identifier' => 'validation-status-pre-registration' ],
			[ 'identifier' => 'validation-status-validated' ],
			[ 'identifier' => 'validation-status-certified' ],
		],
	];
	$families['inclusivity-difficulty'] = [
		'absent' => null,
		'states' => [
			[ 'identifier' => 'inclusivity-difficulty-AT' ],
			[ 'identifier' => 'inclusivity-difficulty-AE' ],
			[ 'identifier' => 'inclusivity-difficulty-AEE' ],
		],
	];
	return $families;
}
add_filter( 'wmwp_status_families', 'forestas_status_families' );

/** Versione di wp-package da cui il child legge dati del dettaglio, stati e percorribilità con le funzioni del plugin. */
const FORESTAS_WMWP_MIN_VERSION = '0.6.0';

/**
 * Il plugin ha le funzioni che il child usa da oc:9000? Con un wp-package più vecchio (`WP_PACKAGE_REF` non aggiornato,
 * o l'init non ancora rifatto dopo il pull del child) il child degrada invece di andare in fatal: dettagli con il
 * template del plugin, ricerca senza i filtri e le etichette degli stati, testi senza la lingua da cui vengono. Una
 * condizione sola, controllata nei punti d'ingresso, invece di una guardia per ogni chiamata.
 *
 * @param string|null $version Versione da controllare; null = quella del plugin attivo.
 */
function forestas_plugin_ready( ?string $version = null ): bool {
	$version = $version ?? ( defined( 'WMWP_VERSION' ) ? (string) WMWP_VERSION : '' );
	return '' !== $version && version_compare( $version, FORESTAS_WMWP_MIN_VERSION, '>=' );
}

/** Famiglia della percorribilità: quella del plugin, `walkability-status` con un plugin più vecchio. */
function forestas_walkability_family(): string {
	return forestas_plugin_ready() ? wmwp_walkability_taxonomy() : 'walkability-status';
}

/**
 * Legenda di una famiglia per gli indicatori a tacche: livello → nome dello stato, dal livello più basso (lo stato di
 * assenza vale 0, wmwp_status_level()), con i nomi di FORESTAS_STATUS_LEGEND_NAMES nella lingua della pagina. Vuota se
 * il registro non conosce la famiglia o il plugin è più vecchio.
 *
 * @return array<int, string>
 */
function forestas_status_legend( string $family ): array {
	if ( ! forestas_plugin_ready() ) {
		return [];
	}
	$names  = FORESTAS_STATUS_LEGEND_NAMES[ $family ] ?? [];
	$legend = [];
	foreach ( (array) ( wmwp_status_families()[ $family ]['states'] ?? [] ) as $state ) {
		$id = is_array( $state ) ? (string) ( $state['identifier'] ?? '' ) : '';
		if ( '' !== $id ) {
			$legend[ wmwp_status_level( $family, $id )['level'] ] = isset( $names[ $id ] ) ? forestas_testo( $names[ $id ][0], $names[ $id ][1] ) : $id;
		}
	}
	ksort( $legend );
	return $legend;
}
