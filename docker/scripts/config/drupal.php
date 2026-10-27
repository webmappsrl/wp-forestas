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
	// News (oc:8759)
	'node__field_data', 'node__field_top_image_media', 'node__field_file_media',
	'node__field_tipologia', 'node__field_tag_news', 'node__field_in_evidenza', 'node__field_collegamenti',
	'node__field_references', 'taxonomy_term_field_data',
];
// I file public:// di Drupal, scaricati dal sito: la copia SQL ha solo le righe di file_managed, non i
// file. L'indirizzo si cambia con DRUPAL_FILES_URL nel .env (oc:8759), per esempio verso una copia della
// cartella sites/default/files sul server quando Drupal verrà spento, senza che cambi il sito a cui
// puntano i link dei testi, che è WPF_LINK_DRUPAL in link.php
const WPF_DRUPAL_FILES = 'https://www.sardegnasentieri.it/sites/default/files/';

/** Indirizzo base dei file di Drupal, con la «/» finale: DRUPAL_FILES_URL del .env, o il sito pubblico. */
function wpf_drupal_files_base(): string {
	$url = trim( (string) getenv( 'DRUPAL_FILES_URL' ) );
	return $url === '' ? WPF_DRUPAL_FILES : rtrim( $url, '/' ) . '/';
}

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
		throw new RuntimeException( 'nodi di config/pagine.json non trovati nella copia Drupal: ' . implode( ', ', $mancano ) . '. Nulla scritto (nessuna pagina va in bozza per un nodo assente). Se la copia è in ricarica riprova più tardi; se su Drupal il nodo è stato cancellato, togli la sua voce da config/pagine.json' );
	}
}

/*
 * Tag e attributi che il formato «filtered_html» di Drupal lascia passare (filtro filter_html, letto dalla
 * configurazione filter.format.filtered_html della copia): il resto Drupal lo toglie dalla pagina, il testo
 * dentro resta.
 */
const WPF_DRUPAL_FILTERED_HTML = [
	'br' => [], 'p' => [], 'h1' => [], 'h2' => [ 'id' => true ], 'h3' => [ 'id' => true ], 'h4' => [ 'id' => true ],
	'h5' => [ 'id' => true ], 'h6' => [ 'id' => true ], 'cite' => [], 'dl' => [], 'dt' => [], 'dd' => [], 'b' => [],
	'a' => [ 'hreflang' => true, 'href' => true ], 'blockquote' => [ 'cite' => true ], 'ul' => [ 'type' => true ],
	'ol' => [ 'type' => true, 'start' => true ], 'strong' => [], 'em' => [], 'code' => [], 'li' => [], 'table' => [],
	'tr' => [], 'td' => [ 'rowspan' => true, 'colspan' => true ], 'th' => [ 'rowspan' => true, 'colspan' => true ],
	'thead' => [], 'tbody' => [], 'tfoot' => [], 'caption' => [],
];

/** Lingua di Drupal come la usa il sito: «und» (lingua non specificata) vale come italiano. */
function wpf_drupal_lingua( string $langcode ): string {
	return $langcode === 'und' ? 'it' : $langcode;
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
	return wpf_drupal_file_media_tutti( $db, [ $mid ] )[ $mid ] ?? null;
}

/**
 * I file di più media in una query sola: [ mid => file ] (un media senza file non c'è). I risultati
 * restano in memoria per l'esecuzione: le news ne leggono centinaia, e dal Mac col tunnel ogni query
 * costa quasi mezzo secondo (oc:8759).
 */
function wpf_drupal_file_media_tutti( mysqli $db, array $mid ): array {
	static $letti = [];
	$mid      = array_values( array_unique( array_map( 'intval', $mid ) ) );
	$da_leggere = array_values( array_filter( $mid, fn( $m ) => ! array_key_exists( $m, $letti ) ) );
	if ( $da_leggere ) {
		foreach ( $da_leggere as $m ) {
			$letti[ $m ] = null;
		}
		$righe = wpf_drupal_righe(
			$db,
			"SELECT m.mid, f.fid, f.uri, f.filename, f.filemime, i.field_media_image_alt AS alt, d.field_media_document_description AS descrizione,
			        au.field_autore_value AS autore, cr.field_credits_value AS credits
			 FROM media_field_data m
			 LEFT JOIN media__field_media_image i ON i.entity_id = m.mid AND i.deleted = 0 AND i.langcode = m.langcode
			 LEFT JOIN media__field_media_document d ON d.entity_id = m.mid AND d.deleted = 0 AND d.langcode = m.langcode
			 LEFT JOIN media__field_autore au ON au.entity_id = m.mid AND au.deleted = 0 AND au.langcode = m.langcode
			 LEFT JOIN media__field_credits cr ON cr.entity_id = m.mid AND cr.deleted = 0 AND cr.langcode = m.langcode
			 JOIN file_managed f ON f.fid = COALESCE(i.field_media_image_target_id, d.field_media_document_target_id)
			 WHERE m.mid IN (" . implode( ',', array_fill( 0, count( $da_leggere ), '?' ) ) . ') AND m.default_langcode = 1',
			$da_leggere
		);
		foreach ( $righe as $r ) {
			$letti[ (int) $r['mid'] ] ??= [
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
	}
	return array_filter( array_intersect_key( $letti, array_flip( $mid ) ) );
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
	$originale = wpf_drupal_lingua( $r['originale'] );
	$campo     = fn( string $nome, string $colonna ) => wpf_drupal_campo( $db, $nome, $colonna, $nid, $lingua, $originale );
	$file      = fn( array $mid ) => array_values( array_filter( array_map( fn( $m ) => wpf_drupal_file_media( $db, (int) $m ), $mid ) ) );
	$body      = wpf_drupal_campo_tradotto( $db, 'body', [ 'value', 'format' ], $nid, $lingua );
	$immagine = $file( array_slice( $campo( 'field_immagine_principale_media', 'target_id' ), 0, 1 ) );
	return [
		'titolo'      => $r['title'],
		'status'      => (bool) $r['status'],
		// La «Descrizione testata» di Drupal
		'sottotitolo' => (string) ( wpf_drupal_campo_tradotto( $db, 'field_descrizione_testata_pagina', [ 'value' ], $nid, $lingua )['value'] ?? '' ),
		'testata'     => wpf_drupal_testata( $db, $nid ),
		'colore'      => wpf_drupal_colore( $db, $nid ),
		'body'        => (string) ( $body['value'] ?? '' ),
		'formato'     => (string) ( $body['format'] ?? '' ),
		'immagine'    => $immagine[0] ?? null,
		'allegati'    => $file( $campo( 'field_allegati_media', 'target_id' ) ),
		'galleria'    => $file( $campo( 'field_galleria_media', 'target_id' ) ),
	];
}

/**
 * Righe di una query, lette una volta per esecuzione e tenute per chiave: [ entity_id ][ langcode ] =>
 * righe nell'ordine della query. Dal Mac col tunnel ogni query costa quasi mezzo secondo: una query per
 * campo e per contenuto vorrebbe dire più di dieci minuti a giro (oc:8759). La chiave deve dire tutto
 * quello che cambia la query (bundle, campo, colonne): due query con la stessa chiave darebbero le
 * stesse righe.
 *
 * @param string $sql query con entity_id e langcode fra le colonne
 */
function wpf_drupal_in_memoria( mysqli $db, string $chiave, string $sql ): array {
	static $tabelle = [];
	if ( ! isset( $tabelle[ $chiave ] ) ) {
		$tabelle[ $chiave ] = [];
		foreach ( wpf_drupal_righe( $db, $sql ) as $r ) {
			$tabelle[ $chiave ][ (int) $r['entity_id'] ][ $r['langcode'] ][] = $r;
		}
	}
	return $tabelle[ $chiave ];
}

/** Le righe di un campo per tutti i nodi di un bundle (wpf_drupal_in_memoria), in ordine di delta. */
function wpf_drupal_bundle_tabella( mysqli $db, string $bundle, string $campo, array $colonne ): array {
	$scelte = implode( ', ', array_map( fn( $c ) => "`{$campo}_{$c}` AS `{$c}`", $colonne ) );
	return wpf_drupal_in_memoria(
		$db,
		"{$bundle}|{$campo}|" . implode( ',', $colonne ),
		"SELECT entity_id, langcode, {$scelte} FROM `node__{$campo}` WHERE bundle = '" . addslashes( $bundle ) . "' AND deleted = 0 ORDER BY entity_id, delta"
	);
}

/**
 * Valori di un campo di un nodo di un bundle, come wpf_drupal_campo: nella lingua chiesta e, se non ne ha
 * (campi non traducibili), in quella originale. Con $tradotto solo nella lingua chiesta, come
 * wpf_drupal_campo_tradotto. Ogni valore è la riga con le colonne chieste.
 */
function wpf_drupal_bundle_campo( mysqli $db, string $bundle, string $campo, array $colonne, int $nid, string $lingua, string $originale, bool $tradotto = false ): array {
	$righe  = wpf_drupal_bundle_tabella( $db, $bundle, $campo, $colonne );
	$lingue = $tradotto ? wpf_drupal_lingue( $lingua ) : array_unique( array_merge( wpf_drupal_lingue( $lingua ), wpf_drupal_lingue( $originale ) ) );
	foreach ( $lingue as $l ) {
		if ( ! empty( $righe[ $nid ][ $l ] ) ) {
			return $righe[ $nid ][ $l ];
		}
	}
	return [];
}

/** I file di tutti i media di un bundle, in una query sola (wpf_drupal_file_media_tutti). */
function wpf_drupal_bundle_precarica_media( mysqli $db, string $bundle, array $campi ): void {
	static $fatti = [];
	if ( isset( $fatti[ $bundle ] ) ) {
		return;
	}
	$fatti[ $bundle ] = true;
	$mid              = [];
	foreach ( $campi as $campo ) {
		foreach ( wpf_drupal_bundle_tabella( $db, $bundle, $campo, [ 'target_id' ] ) as $lingue ) {
			foreach ( $lingue as $righe ) {
				$mid = array_merge( $mid, array_column( $righe, 'target_id' ) );
			}
		}
	}
	wpf_drupal_file_media_tutti( $db, $mid );
}

/**
 * Le news della copia: [ nid => [ 'status' => bool, 'lingue' => [ 'it', 'en'? ] ] ]. Lo stato è quello
 * della versione originale (italiana o «und»); «lingue» sono quelle che il nodo ha, «und» come italiano.
 */
function wpf_drupal_news_elenco( mysqli $db ): array {
	$news = [];
	foreach ( wpf_drupal_righe( $db, "SELECT nid, langcode, status, default_langcode FROM node_field_data WHERE type = 'news' ORDER BY nid, default_langcode DESC" ) as $r ) {
		$nid = (int) $r['nid'];
		if ( (int) $r['default_langcode'] === 1 ) {
			$news[ $nid ] = [ 'status' => (bool) $r['status'], 'lingue' => [] ];
		}
		if ( isset( $news[ $nid ] ) ) {
			$news[ $nid ]['lingue'][] = wpf_drupal_lingua( $r['langcode'] );
		}
	}
	return $news;
}

/**
 * Una news nella lingua chiesta, o null se la traduzione non esiste: i campi di wpf_drupal_pagina più
 * data mostrata (field_data, AAAA-MM-GG), tipologia, tag, «in evidenza», collegamenti, riferimenti e
 * alias. I collegamenti hanno l'uri com'è su Drupal (internal:/…, entity:node/N, http…); i riferimenti
 * sono [ bundle, nid ] dei nodi collegati.
 */
function wpf_drupal_news( mysqli $db, int $nid, string $lingua ): ?array {
	$nodi = wpf_drupal_in_memoria(
		$db,
		'news|nodi',
		"SELECT f.nid AS entity_id, f.langcode, f.title, f.status, n.langcode AS originale
		 FROM node_field_data f JOIN node n ON n.nid = f.nid WHERE f.type = 'news'"
	);
	$r = null;
	foreach ( wpf_drupal_lingue( $lingua ) as $l ) {
		$r ??= $nodi[ $nid ][ $l ][0] ?? null;
	}
	if ( ! $r ) {
		return null;
	}
	$originale = wpf_drupal_lingua( $r['originale'] );
	wpf_drupal_bundle_precarica_media( $db, 'news', [ 'field_top_image_media', 'field_file_media', 'field_galleria_media' ] );
	$campo     = fn( string $nome, string $colonna ) => array_column( wpf_drupal_bundle_campo( $db, 'news', $nome, [ $colonna ], $nid, $lingua, $originale ), $colonna );
	$tradotto  = fn( string $nome, array $colonne ) => wpf_drupal_bundle_campo( $db, 'news', $nome, $colonne, $nid, $lingua, $originale, true )[0] ?? [];
	// I file nell'ordine del campo su Drupal (delta), non in quello in cui sono stati letti
	$file      = function ( array $mid ) use ( $db ): array {
		$letti = wpf_drupal_file_media_tutti( $db, $mid );
		return array_values( array_filter( array_map( fn( $m ) => $letti[ (int) $m ] ?? null, $mid ) ) );
	};
	$body      = $tradotto( 'body', [ 'value', 'format' ] );
	$immagine  = $file( array_slice( $campo( 'field_top_image_media', 'target_id' ), 0, 1 ) );
	$tid_tag   = array_map( 'intval', $campo( 'field_tag_news', 'target_id' ) );
	$termini   = wpf_drupal_termini( $db, $tid_tag );
	$tipi      = wpf_drupal_in_memoria(
		$db,
		'news|riferimenti',
		"SELECT DISTINCT n.nid AS entity_id, n.langcode, n.type FROM node n
		 JOIN node__field_references r ON r.field_references_target_id = n.nid WHERE r.bundle = 'news' AND r.deleted = 0"
	);
	$riferimenti = [];
	foreach ( array_map( 'intval', $campo( 'field_references', 'target_id' ) ) as $collegato ) {
		$tipo = isset( $tipi[ $collegato ] ) ? reset( $tipi[ $collegato ] )[0]['type'] : null;
		if ( $tipo !== null ) {
			$riferimenti[] = [ $tipo, $collegato ];
		}
	}
	// Il nid è il resto del percorso dopo «/node/» (6 caratteri, quindi dal settimo)
	$alias = wpf_drupal_in_memoria(
		$db,
		'alias',
		"SELECT CAST(SUBSTRING(path, 7) AS UNSIGNED) AS entity_id, langcode, alias FROM path_alias
		 WHERE path LIKE '/node/%' AND status = 1 ORDER BY id DESC"
	);
	$alias_news = null;
	foreach ( wpf_drupal_lingue( $lingua ) as $l ) {
		$alias_news ??= $alias[ $nid ][ $l ][0]['alias'] ?? null;
	}
	$tipologia = $campo( 'field_tipologia', 'target_id' );
	$data      = $campo( 'field_data', 'value' );
	return [
		'titolo'       => $r['title'],
		'status'       => (bool) $r['status'],
		'sottotitolo'  => (string) ( $tradotto( 'field_descrizione_testata_pagina', [ 'value' ] )['value'] ?? '' ),
		'body'         => (string) ( $body['value'] ?? '' ),
		'formato'      => (string) ( $body['format'] ?? '' ),
		'immagine'     => $immagine[0] ?? null,
		'allegati'     => $file( $campo( 'field_file_media', 'target_id' ) ),
		'galleria'     => $file( $campo( 'field_galleria_media', 'target_id' ) ),
		'data'         => (string) ( $data[0] ?? '' ),
		'tipologia'    => isset( $tipologia[0] ) ? (int) $tipologia[0] : null,
		'tag'          => array_values( array_map( fn( $tid ) => [ $tid, $termini[ $tid ]['vid'] ], array_filter( $tid_tag, fn( $tid ) => isset( $termini[ $tid ] ) ) ) ),
		'tag_mancanti' => array_values( array_filter( $tid_tag, fn( $tid ) => ! isset( $termini[ $tid ] ) ) ),
		'in_evidenza'  => (bool) ( $campo( 'field_in_evidenza', 'value' )[0] ?? false ),
		'collegamenti' => array_map( fn( $c ) => [ (string) $c['title'], (string) $c['uri'] ], wpf_drupal_bundle_campo( $db, 'news', 'field_collegamenti', [ 'uri', 'title' ], $nid, $lingua, $originale ) ),
		'riferimenti'  => $riferimenti,
		// Ultimo pezzo dell'alias (/news/<x>): lo slug del post; null se il nodo non ha alias (nid 1094)
		'alias'        => $alias_news !== null ? basename( $alias_news ) : null,
	];
}

/**
 * Termini della copia: [ tid => [ 'vid', 'nome' => [ lingua => nome ] ] ]. Un tid che non esiste
 * più (la tipologia 3905 di due news) non è nell'elenco.
 */
function wpf_drupal_termini( mysqli $db, array $tid ): array {
	static $tutti = null;
	if ( $tutti === null ) {
		$tutti = [];
		foreach ( wpf_drupal_righe( $db, 'SELECT tid, vid, langcode, name FROM taxonomy_term_field_data ORDER BY tid, default_langcode DESC' ) as $r ) {
			$id            = (int) $r['tid'];
			$tutti[ $id ] ??= [ 'vid' => $r['vid'], 'nome' => [] ];
			$tutti[ $id ]['nome'][ wpf_drupal_lingua( $r['langcode'] ) ] ??= $r['name'];
		}
	}
	return array_intersect_key( $tutti, array_flip( array_map( 'intval', $tid ) ) );
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

/**
 * Un campo traducibile del nodo (body, sottotitolo), solo nella lingua chiesta e senza ripiegare
 * sull'originale come fa wpf_drupal_campo: una traduzione senza testo resta senza testo.
 * Restituisce [ colonna => valore ] della prima riga, o [] se il campo non c'è in quella lingua.
 */
function wpf_drupal_campo_tradotto( mysqli $db, string $campo, array $colonne, int $nid, string $lingua ): array {
	$scelte = implode( ', ', array_map( fn( $c ) => "`{$campo}_{$c}` AS `{$c}`", $colonne ) );
	return wpf_drupal_righe(
		$db,
		"SELECT {$scelte} FROM `node__{$campo}` WHERE entity_id = ? AND deleted = 0 AND " . wpf_drupal_in_lingua( 'langcode', $lingua ) . ' LIMIT 1',
		array_merge( [ $nid ], wpf_drupal_lingue( $lingua ) )
	)[0] ?? [];
}

/*
 * Data dell'ultima modifica di un contenuto nella copia (timestamp, 0 se vuota). Non è la data del dump,
 * che la copia non registra, ma un dump che smette di arrivare la ferma: dopo WPF_DRUPAL_GIORNI_FERMA
 * giorni senza modifiche la sincronizzazione lo segnala.
 */
const WPF_DRUPAL_GIORNI_FERMA = 30;

function wpf_drupal_ultima_modifica( mysqli $db ): int {
	return (int) ( wpf_drupal_righe( $db, 'SELECT MAX(changed) AS v FROM node_field_data' )[0]['v'] ?? 0 );
}

/**
 * Alias Drupal dei nodi: '/percorso' => [ 'nid', 'lingua' ]. Un alias «und» vale per tutte le lingue
 * del nodo e si registra come italiano; un link «/en/<alias>» lo risolve wpf_link_nodo (link.php)
 * togliendo il prefisso e prendendo la versione inglese dello stesso nodo. Dopo gli alias esatti, ognuno
 * anche nella forma senza accenti e in minuscolo (wpf_drupal_normalizza_alias), se non è già un alias:
 * Drupal cerca gli alias con un confronto che ignora accenti e maiuscole.
 */
function wpf_drupal_alias( mysqli $db ): array {
	$alias = [];
	foreach ( wpf_drupal_righe( $db, "SELECT path, alias, langcode FROM path_alias WHERE path LIKE '/node/%' AND status = 1 ORDER BY id" ) as $r ) {
		if ( ! preg_match( '#^/node/(\d+)$#', $r['path'], $m ) ) {
			continue;
		}
		$lingua = wpf_drupal_lingua( $r['langcode'] );
		$alias[ $lingua === 'it' ? $r['alias'] : "/{$lingua}{$r['alias']}" ] = [ 'nid' => (int) $m[1], 'lingua' => $lingua ];
	}
	foreach ( $alias as $percorso => $nodo ) {
		$alias[ wpf_drupal_normalizza_alias( (string) $percorso ) ] ??= $nodo;
	}
	return $alias;
}

/** Percorso senza accenti e in minuscolo, per confrontare gli alias come fa il database di Drupal. */
function wpf_drupal_normalizza_alias( string $percorso ): string {
	return strtolower( remove_accents( $percorso ) );
}

/** URL da cui scaricare un file public:// di Drupal, o null per un altro schema. */
function wpf_drupal_url_file( string $uri ): ?string {
	if ( ! str_starts_with( $uri, 'public://' ) ) {
		return null;
	}
	return wpf_drupal_files_base() . implode( '/', array_map( 'rawurlencode', explode( '/', substr( $uri, strlen( 'public://' ) ) ) ) );
}
