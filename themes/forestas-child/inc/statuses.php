<?php
/**
 * Stati delle famiglie di forestas nel registro degli stati del plugin (oc:9000): validazione catastale e
 * accessibilità. La percorribilità è di wm-package e il plugin ce l'ha già.
 *
 * Gli stati, il loro ordine e i nomi italiani sono quelli di `app/Console/Commands/ConfigureTaxonomyTermsCommand.php`
 * dello shard (dopo oc:8989, senza «Percorribile» e «Non percorribile», passati alla percorribilità); i nomi inglesi
 * sono nostri, come in forestas_search_term_labels(). Lo shard non espone ancora l'elenco degli stati di una famiglia:
 * quando lo farà, il registro del plugin lo leggerà da lì e questa lista si toglie.
 *
 * Le tacche sono gli stati della famiglia e ogni stato accende fino alla sua posizione: validazione 5 tacche («Non
 * verificato» nessuna, «In revisione-validazione» 2 … «Certificato» 5), accessibilità 3 (la famiglia non ha uno stato
 * di assenza; senza grado nessuna tacca).
 *
 * @package forestas-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Famiglie di forestas nel registro del plugin.
 *
 * @param array<string, array<string, mixed>> $families Famiglie già registrate.
 * @return array<string, array<string, mixed>>
 */
function forestas_status_families( array $families ): array {
	$families['validation-status'] = [
		'absent' => 'validation-status-unverified',
		'states' => [
			[
				'identifier' => 'validation-status-unverified',
				'name'       => [
					'it' => 'Non verificato',
					'en' => 'Not verified',
				],
			],
			[
				'identifier' => 'validation-status-under-review',
				'name'       => [
					'it' => 'In revisione-validazione',
					'en' => 'Under review',
				],
			],
			[
				'identifier' => 'validation-status-pre-registration',
				'name'       => [
					'it' => 'In pre-accatastamento o manutenzione',
					'en' => 'Pre-registration or maintenance',
				],
			],
			[
				'identifier' => 'validation-status-validated',
				'name'       => [
					'it' => 'Validato',
					'en' => 'Validated',
				],
			],
			[
				'identifier' => 'validation-status-certified',
				'name'       => [
					'it' => 'Certificato',
					'en' => 'Certified',
				],
			],
		],
	];
	$families['inclusivity-difficulty'] = [
		'absent' => null,
		'states' => [
			[
				'identifier' => 'inclusivity-difficulty-at',
				'name'       => [
					'it' => 'AT',
					'en' => 'AT',
				],
			],
			[
				'identifier' => 'inclusivity-difficulty-ae',
				'name'       => [
					'it' => 'AE',
					'en' => 'AE',
				],
			],
			[
				'identifier' => 'inclusivity-difficulty-aee',
				'name'       => [
					'it' => 'AEE',
					'en' => 'AEE',
				],
			],
		],
	];
	return $families;
}
add_filter( 'wmwp_status_families', 'forestas_status_families' );

/** Versione di wp-package da cui il child legge dati del dettaglio, stati e percorribilità con le funzioni del plugin. */
const FORESTAS_WMWP_MIN_VERSION = '0.5.0';

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
 * Stati di una famiglia del registro del plugin, nell'ordine dello shard, con il nome nella lingua della pagina:
 * identifier → { text, lang } (wmwp_text()). Vuoto se il registro non conosce la famiglia o il plugin è più vecchio.
 *
 * @return array<string, array{text: string, lang: ?string}>
 */
function forestas_status_names( string $family ): array {
	if ( ! forestas_plugin_ready() ) {
		return [];
	}
	$names = [];
	foreach ( (array) ( wmwp_status_families()[ $family ]['states'] ?? [] ) as $state ) {
		$id = is_array( $state ) ? (string) ( $state['identifier'] ?? '' ) : '';
		if ( '' !== $id ) {
			$names[ $id ] = wmwp_text( $state['name'] ?? null, forestas_dettaglio_lingua() );
		}
	}
	return $names;
}

/**
 * Legenda di una famiglia per gli indicatori a tacche: livello → nome dello stato, dal livello più basso (lo stato di
 * assenza vale 0, wmwp_status_level()).
 *
 * @return array<int, string>
 */
function forestas_status_legend( string $family ): array {
	$legend = [];
	foreach ( forestas_status_names( $family ) as $id => $name ) {
		$legend[ wmwp_status_level( $family, $id )['level'] ] = $name['text'];
	}
	ksort( $legend );
	return $legend;
}
