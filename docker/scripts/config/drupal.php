<?php
/**
 * Lettura della copia SQL del database Drupal di Sardegna Sentieri (oc:8758), in sola lettura.
 *
 * Dal container WordPress la copia si raggiunge per nome sulla rete Docker dello shard
 * (mysql-sardegnasentieri-dump:3306, UAT e produzione); in locale, col tunnel SSH aperto sull'host,
 * con DRUPAL_DB_HOST=host.docker.internal e DRUPAL_DB_PORT=3307. Lo schema è descritto in
 * forestas/docs/knowledge/copia-database-drupal.md.
 */

// Tabelle lette dalla sincronizzazione: se una manca o è vuota la copia è in ricarica o troncata. Tutte
// quelle lette vanno qui, se no un reload a metà dà un errore di tabella mancante invece di questo
const WPF_DRUPAL_TABELLE = [
	'node', 'node_field_data', 'node__body', 'path_alias', 'media_field_data', 'file_managed',
	'node__field_descrizione_testata_pagina', 'node__field_immagine_principale_media', 'node__field_allegati_media',
	'node__field_galleria_media', 'node__field_colore_tematico', 'taxonomy_term__field_colore_tematico',
	'node__layout_builder__layout', 'media__field_media_image', 'media__field_media_document',
	'media__field_autore', 'media__field_credits',
];
// I file public:// di Drupal, scaricati dal sito. L'indirizzo può cambiare (copia dei file allo spegnimento
// di Drupal) senza che cambi il sito a cui puntano i link dei testi, che è WPF_LINK_DRUPAL in link.php
const WPF_DRUPAL_FILES = 'https://www.sardegnasentieri.it/sites/default/files/';

/**
 * Connessione alla copia, dai DRUPAL_DB_* del .env. Host, porta e database hanno i valori di UAT e
 * produzione per default; utente e password no: il repo è pubblico e la copia contiene dati personali.
 */
function wpf_drupal_connessione(): mysqli {
	$host     = getenv( 'DRUPAL_DB_HOST' ) ?: 'mysql-sardegnasentieri-dump';
	$port     = (int) ( getenv( 'DRUPAL_DB_PORT' ) ?: 3306 );
	$utente   = (string) getenv( 'DRUPAL_DB_USER' );
	$password = (string) getenv( 'DRUPAL_DB_PASSWORD' );
	if ( $utente === '' || $password === '' ) {
		throw new RuntimeException( 'DRUPAL_DB_USER e DRUPAL_DB_PASSWORD mancano nel .env di wp-forestas: servono per leggere la copia Drupal (forestas/docs/howto/copia-database-drupal.md)' );
	}
	// Una query rifiutata deve essere un'eccezione, non un risultato vuoto scambiato per «nessuna pagina»
	mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
	try {
		$db = new mysqli(
			$host,
			$utente,
			$password,
			getenv( 'DRUPAL_DB_NAME' ) ?: 'sardegnasentieri',
			$port
		);
	} catch ( mysqli_sql_exception $e ) {
		throw new RuntimeException( "copia Drupal non raggiungibile su {$host}:{$port}: " . $e->getMessage() );
	}
	$db->set_charset( 'utf8mb4' );
	return $db;
}

/** Righe di una query preparata, come array associativi. */
function wpf_drupal_righe( mysqli $db, string $sql, array $parametri = [] ): array {
	$st = $db->prepare( $sql );
	if ( $parametri ) {
		$st->bind_param( str_repeat( 's', count( $parametri ) ), ...array_map( 'strval', $parametri ) );
	}
	$st->execute();
	return $st->get_result()->fetch_all( MYSQLI_ASSOC );
}

/**
 * Controlla che la copia sia integra prima di scrivere qualsiasi cosa: le tabelle lette esistono e non
 * sono vuote, e ogni nid del perimetro c'è. Durante il reload (DROP DATABASE e caricamento tabella per
 * tabella, forestas/scripts/drupal-dump-reload.sh) una di queste condizioni manca.
 */
function wpf_drupal_controlla( mysqli $db, array $nid ): void {
	foreach ( WPF_DRUPAL_TABELLE as $tabella ) {
		try {
			$righe = wpf_drupal_righe( $db, "SELECT 1 AS c FROM `{$tabella}` LIMIT 1" );
		} catch ( mysqli_sql_exception $e ) {
			throw new RuntimeException( "copia Drupal non integra: tabella {$tabella} non leggibile (" . $e->getMessage() . ')' );
		}
		if ( ! $righe ) {
			throw new RuntimeException( "copia Drupal non integra: tabella {$tabella} vuota (copia in ricarica?)" );
		}
	}
	$nid = array_values( array_unique( array_map( 'intval', $nid ) ) );
	if ( ! $nid ) {
		return;
	}
	$segnaposti = implode( ',', array_fill( 0, count( $nid ), '?' ) );
	$trovati    = array_map( 'intval', array_column( wpf_drupal_righe( $db, "SELECT DISTINCT nid FROM node_field_data WHERE nid IN ({$segnaposti})", $nid ), 'nid' ) );
	$mancano    = array_diff( $nid, $trovati );
	if ( $mancano ) {
		throw new RuntimeException( 'copia Drupal non integra: nodi non trovati ' . implode( ', ', $mancano ) . ' (nessuna pagina va in bozza per un nodo assente)' );
	}
}

/** Lingue di Drupal da cercare per una lingua: «und» (lingua non specificata) vale come italiano. */
function wpf_drupal_lingue( string $lingua ): array {
	return $lingua === 'it' ? [ 'it', 'und' ] : [ $lingua ];
}

/** «langcode IN (?, …)» per le lingue di wpf_drupal_lingue, da usare con i suoi valori come parametri. */
function wpf_drupal_in_lingua( string $colonna, string $lingua ): string {
	return "{$colonna} IN (" . implode( ',', array_fill( 0, count( wpf_drupal_lingue( $lingua ) ), '?' ) ) . ')';
}

/**
 * Valori di un campo del nodo nella lingua chiesta. I campi non traducibili (immagini, allegati) hanno
 * righe solo nella lingua originale del nodo: per una traduzione senza righe proprie valgono quelle.
 */
function wpf_drupal_campo( mysqli $db, string $campo, string $colonna, int $nid, string $lingua, string $originale ): array {
	foreach ( array_unique( array_merge( wpf_drupal_lingue( $lingua ), wpf_drupal_lingue( $originale ) ) ) as $l ) {
		$righe = wpf_drupal_righe(
			$db,
			"SELECT `{$campo}_{$colonna}` AS v FROM `node__{$campo}` WHERE entity_id = ? AND langcode = ? AND deleted = 0 ORDER BY delta",
			[ $nid, $l ]
		);
		if ( $righe ) {
			return array_column( $righe, 'v' );
		}
	}
	return [];
}

/** Il file di un media (immagine o documento), o null se il media non ha file. */
function wpf_drupal_file_media( mysqli $db, int $mid ): ?array {
	$righe = wpf_drupal_righe(
		$db,
		"SELECT f.fid, f.uri, f.filename, f.filemime, i.field_media_image_alt AS alt, d.field_media_document_description AS descrizione,
		        au.field_autore_value AS autore, cr.field_credits_value AS credits
		 FROM media_field_data m
		 LEFT JOIN media__field_media_image i ON i.entity_id = m.mid AND i.deleted = 0 AND i.langcode = m.langcode
		 LEFT JOIN media__field_media_document d ON d.entity_id = m.mid AND d.deleted = 0 AND d.langcode = m.langcode
		 LEFT JOIN media__field_autore au ON au.entity_id = m.mid AND au.deleted = 0 AND au.langcode = m.langcode
		 LEFT JOIN media__field_credits cr ON cr.entity_id = m.mid AND cr.deleted = 0 AND cr.langcode = m.langcode
		 JOIN file_managed f ON f.fid = COALESCE(i.field_media_image_target_id, d.field_media_document_target_id)
		 WHERE m.mid = ? AND m.default_langcode = 1 LIMIT 1",
		[ $mid ]
	);
	if ( ! $righe ) {
		return null;
	}
	$r = $righe[0];
	return [
		'fid'     => (int) $r['fid'],
		'uri'     => $r['uri'],
		'nome'    => $r['filename'],
		'alt'     => (string) ( $r['alt'] ?? '' ),
		// Su Drupal il link di un documento mostra la descrizione, o il nome del file se manca
		'titolo'  => trim( (string) ( $r['descrizione'] ?? '' ) ) !== '' ? trim( (string) $r['descrizione'] ) : $r['filename'],
		// Campi «Autore» e «Credits» del media immagine, mostrati nella testata dietro «Credits foto»
		'autore'  => trim( (string) ( $r['autore'] ?? '' ) ),
		'credits' => trim( (string) ( $r['credits'] ?? '' ) ),
		'mime'    => $r['filemime'],
	];
}

/**
 * Una pagina Drupal nella lingua chiesta, o null se la traduzione non esiste. Immagini, allegati e
 * galleria vengono dai campi «_media», mai dai campi legacy (doppioni dei file vecchi).
 */
function wpf_drupal_pagina( mysqli $db, int $nid, string $lingua ): ?array {
	$righe = wpf_drupal_righe(
		$db,
		'SELECT f.title, f.status, f.langcode, n.langcode AS originale
		 FROM node_field_data f JOIN node n ON n.nid = f.nid
		 WHERE f.nid = ? AND ' . wpf_drupal_in_lingua( 'f.langcode', $lingua ) . ' LIMIT 1',
		array_merge( [ $nid ], wpf_drupal_lingue( $lingua ) )
	);
	if ( ! $righe ) {
		return null;
	}
	$r         = $righe[0];
	$originale = $r['originale'] === 'und' ? 'it' : $r['originale'];
	$campo     = fn( string $nome, string $colonna ) => wpf_drupal_campo( $db, $nome, $colonna, $nid, $lingua, $originale );
	$file      = fn( array $mid ) => array_values( array_filter( array_map( fn( $m ) => wpf_drupal_file_media( $db, (int) $m ), $mid ) ) );
	// Il body è traducibile: si prende solo nella lingua chiesta, senza ripiegare sull'originale
	$body      = wpf_drupal_righe(
		$db,
		'SELECT body_value, body_format FROM node__body WHERE entity_id = ? AND deleted = 0 AND ' . wpf_drupal_in_lingua( 'langcode', $lingua ) . ' LIMIT 1',
		array_merge( [ $nid ], wpf_drupal_lingue( $lingua ) )
	);
	$immagine = $file( array_slice( $campo( 'field_immagine_principale_media', 'target_id' ), 0, 1 ) );
	return [
		'titolo'      => $r['title'],
		'status'      => (bool) $r['status'],
		'sottotitolo' => (string) ( wpf_drupal_sottotitolo( $db, $nid, $lingua ) ?? '' ),
		'testata'     => wpf_drupal_testata( $db, $nid ),
		'colore'      => wpf_drupal_colore( $db, $nid ),
		'body'        => (string) ( $body[0]['body_value'] ?? '' ),
		'formato'     => (string) ( $body[0]['body_format'] ?? '' ),
		'immagine'    => $immagine[0] ?? null,
		'allegati'    => $file( $campo( 'field_allegati_media', 'target_id' ) ),
		'galleria'    => $file( $campo( 'field_galleria_media', 'target_id' ) ),
	];
}

/**
 * Tipo di testata della pagina: «hero» (titolo, sottotitolo e immagine in evidenza, la sezione «Header
 * pagina» del layout predefinito delle pagine) o «titolo» (solo il titolo, senza immagine). È «titolo»
 * quando la redazione ha dato alla pagina un layout proprio senza la sezione hero: oggi News.
 * Le sezioni sono oggetti PHP serializzati di Drupal: si leggono come array, senza le sue classi.
 */
function wpf_drupal_testata( mysqli $db, int $nid ): string {
	$righe = wpf_drupal_righe( $db, 'SELECT layout_builder__layout_section AS s FROM node__layout_builder__layout WHERE entity_id = ? AND deleted = 0', [ $nid ] );
	if ( ! $righe ) {
		return 'hero';
	}
	$array = function ( $valore ) use ( &$array ) {
		if ( is_object( $valore ) ) {
			$valore = (array) $valore;
		}
		if ( ! is_array( $valore ) ) {
			return $valore;
		}
		$risultato = [];
		foreach ( $valore as $chiave => $v ) {
			// Le proprietà protette di un oggetto convertito hanno il nome preceduto da «\0*\0»
			$risultato[ preg_replace( '/^\0.*\0/', '', (string) $chiave ) ] = $array( $v );
		}
		return $risultato;
	};
	foreach ( $righe as $r ) {
		$sezione = $array( unserialize( $r['s'], [ 'allowed_classes' => false ] ) );
		if ( ! empty( $sezione['layoutSettings']['layout_builder_styles_style']['section_page_header_hero'] ) ) {
			return 'hero';
		}
	}
	return 'titolo';
}

/**
 * Colore tematico della pagina (campo «Colore tematico», un termine con la classe CSS: is-sapere,
 * is-pathway…), o '' se non c'è. Su Drupal la classe va sul contenuto e dà il colore al filo della testata.
 */
function wpf_drupal_colore( mysqli $db, int $nid ): string {
	$righe = wpf_drupal_righe(
		$db,
		'SELECT t.field_colore_tematico_value AS v FROM node__field_colore_tematico n
		 JOIN taxonomy_term__field_colore_tematico t ON t.entity_id = n.field_colore_tematico_target_id AND t.deleted = 0
		 WHERE n.entity_id = ? AND n.deleted = 0 LIMIT 1',
		[ $nid ]
	);
	return (string) ( $righe[0]['v'] ?? '' );
}

/** Sottotitolo della testata: traducibile, quindi solo nella lingua chiesta. */
function wpf_drupal_sottotitolo( mysqli $db, int $nid, string $lingua ): ?string {
	$righe = wpf_drupal_righe(
		$db,
		'SELECT field_descrizione_testata_pagina_value AS v FROM node__field_descrizione_testata_pagina WHERE entity_id = ? AND deleted = 0 AND ' . wpf_drupal_in_lingua( 'langcode', $lingua ) . ' LIMIT 1',
		array_merge( [ $nid ], wpf_drupal_lingue( $lingua ) )
	);
	return $righe[0]['v'] ?? null;
}

/**
 * Alias Drupal dei nodi: '/percorso' => [ 'nid', 'lingua' ]. Un alias «und» vale per tutte le lingue
 * del nodo e si registra come italiano; un link «/en/<alias>» lo risolve wpf_link_nodo (link.php)
 * togliendo il prefisso e prendendo la versione inglese dello stesso nodo.
 */
function wpf_drupal_alias( mysqli $db ): array {
	$alias = [];
	foreach ( wpf_drupal_righe( $db, "SELECT path, alias, langcode FROM path_alias WHERE path LIKE '/node/%' AND status = 1 ORDER BY id" ) as $r ) {
		if ( ! preg_match( '#^/node/(\d+)$#', $r['path'], $m ) ) {
			continue;
		}
		$lingua                                       = $r['langcode'] === 'und' ? 'it' : $r['langcode'];
		$alias[ $lingua === 'it' ? $r['alias'] : "/{$lingua}{$r['alias']}" ] = [ 'nid' => (int) $m[1], 'lingua' => $lingua ];
	}
	return $alias;
}

/** URL da cui scaricare un file public:// di Drupal, o null per un altro schema. */
function wpf_drupal_url_file( string $uri ): ?string {
	if ( ! str_starts_with( $uri, 'public://' ) ) {
		return null;
	}
	return WPF_DRUPAL_FILES . implode( '/', array_map( 'rawurlencode', explode( '/', substr( $uri, strlen( 'public://' ) ) ) ) );
}
