<?php
/**
 * Dettaglio di un percorso (oc:8969): sentiero (Tavola 3 del wireframe di oc:8783), itinerario (Tavola 4) e cammino
 * (Tavola 5, la singola traccia). Sostituisce il template del plugin wp-package. I dati vengono dal JSON dello shard,
 * letto a ogni richiesta, attraverso le funzioni di inc/dettaglio.php; dove un dato non esiste ancora c'è un
 * segnaposto dichiarato che occupa lo spazio del componente finito. Le note numerate del wireframe non si riportano.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$detail     = wmwp_detail_current();
$p          = (array) ( $detail['feature']['properties'] ?? [] );
$forestas   = (array) ( $p['forestas'] ?? [] );
$tipo       = forestas_dettaglio_tipo( $detail );
$colori     = forestas_dettaglio_colori( $tipo );
$tipi       = forestas_search_route_type_labels();
$tipo_nome  = $tipi[ $p['taxonomies']['route-type'][0]['identifier'] ?? 'route-type-trail' ] ?? $tipi['route-type-trail'];
$codice     = forestas_dettaglio_testo( $p['ref'] ?? null );
$nome       = forestas_dettaglio_nome( (string) $detail['name'], $codice );
$luoghi     = forestas_dettaglio_luoghi( $p );
$indicatori = forestas_dettaglio_indicatori( $p );
$foto       = $p['feature_image']['sizes']['1440x500'] ?? $p['feature_image']['url'] ?? '';
$gpx        = (string) ( $forestas['downloads']['gpx'] ?? '' );
$non_disp   = forestas_testo( 'Dato non ancora disponibile', 'Not yet available' );
$correlati  = forestas_dettaglio_correlati( $p );
$galleria   = forestas_dettaglio_galleria( $p );
$enti       = forestas_dettaglio_enti( $p );

// Titolo di sezione su fascia chiara con la barra del colore del tipo
$titolo = function ( string $testo, string $id ): string {
	return sprintf( '<h2 class="wm-sec__h" id="%s">%s</h2>', esc_attr( $id ), esc_html( $testo ) );
};
// Testo lungo con «Mostra tutto» (come sul sito attuale): il testo intero è sempre nel documento
$espandibile = function ( string $html, string $id ): string {
	if ( '' === $html ) {
		return '';
	}
	return sprintf(
		'<div class="wm-xp" data-wm-xp><div class="wm-xp__body" id="%1$s">%2$s</div><div class="wm-xp__more" hidden><button type="button" class="forestas-pulsante" aria-controls="%1$s" aria-expanded="false" data-piu="%3$s" data-meno="%4$s">%3$s</button></div></div>',
		esc_attr( $id ),
		wp_kses_post( $html ),
		esc_attr( forestas_testo( 'Mostra tutto', 'Show all' ) ),
		esc_attr( forestas_testo( 'Mostra meno', 'Show less' ) )
	);
};
// Indicatore a tacche: livello acceso su un totale, con la legenda dietro la «i»
$tacche = function ( int $acceso, int $totale ): string {
	$html = '';
	for ( $i = 1; $i <= $totale; $i++ ) {
		$html .= $i <= $acceso ? '<i class="on"></i>' : '<i></i>';
	}
	return $html;
};

wmwp_template_header();
?>
<main id="page-content" class="l-main wm-dettaglio wm-dettaglio--<?php echo esc_attr( $tipo ); ?>" style="--wm-c:<?php echo esc_attr( $colori['colore'] ); ?>;--wm-bg:<?php echo esc_attr( $colori['fascia'] ); ?>">

	<?php // Avviso del sentiero: il dato non esiste ancora nello shard ?>
	<?php if ( 'itinerario' !== $tipo ) : ?>
		<div class="wm-avviso wm-segnaposto" role="note">
			<span class="wm-avviso__tag"><?php echo esc_html( forestas_testo( 'Avvisi', 'Notices' ) ); ?></span>
			<span><?php echo esc_html( $non_disp ); ?></span>
		</div>
	<?php endif; ?>

	<header class="wm-hero" data-wm-hero>
		<?php if ( '' !== $foto ) : ?>
			<img class="wm-hero__img" src="<?php echo esc_url( $foto ); ?>" alt="" fetchpriority="high">
		<?php endif; ?>
		<?php
		// «Credits foto» come nella testata delle pagine statiche, con autore e credits della foto dello shard
		$credits_foto = forestas_credits_immagine(
			[
				'autore'  => forestas_dettaglio_testo( $p['feature_image']['author'] ?? null ),
				'credits' => forestas_dettaglio_testo( $p['feature_image']['credits'] ?? null ),
				'chiave'  => 'track-' . (int) $detail['id'],
			]
		);
		echo $credits_foto; // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato
		?>
		<div class="wm-hero__scrim<?php echo '' !== $credits_foto ? ' wm-hero__scrim--credits' : ''; ?>">
			<p class="wm-hero__occ">
				<span class="wm-tipo"><?php echo esc_html( $tipo_nome ); ?></span>
				<?php if ( $luoghi['aree'] ) : ?>
					<span><?php echo esc_html( forestas_testo( 'Aree geografiche', 'Geographic areas' ) . ' ' . implode( ', ', $luoghi['aree'] ) ); ?></span>
				<?php endif; ?>
			</p>
			<h1 class="wm-hero__tit">
				<?php echo esc_html( $nome ); ?>
				<span class="screen-reader-text">, <?php echo esc_html( mb_strtolower( $tipo_nome ) ); ?></span>
				<?php if ( '' !== $codice ) : ?>
					<span class="wm-flag"><?php echo esc_html( $codice ); ?></span>
				<?php endif; ?>
			</h1>
			<?php if ( $luoghi['comuni'] || $luoghi['provincia'] ) : ?>
				<p class="wm-hero__sub"><?php echo esc_html( implode( ' · ', array_filter( [ implode( ', ', $luoghi['comuni'] ), $luoghi['provincia'] ? forestas_testo( 'Provincia', 'Province' ) . ' ' . $luoghi['provincia'] : '' ] ) ) ); ?></p>
			<?php endif; ?>
			<?php if ( 'itinerario' !== $tipo ) : ?>
				<p class="wm-hero__meta wm-segnaposto"><?php echo esc_html( forestas_testo( 'Recensioni: ', 'Reviews: ' ) . mb_strtolower( $non_disp ) ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php // Testata compatta che resta in alto scorrendo: la mostra assets/js/dettaglio.js dopo la testata grande ?>
	<div class="wm-stick" data-wm-stick aria-hidden="true">
		<span class="wm-tipo"><?php echo esc_html( $tipo_nome ); ?></span>
		<span class="wm-stick__tit"><?php echo esc_html( $nome ); ?></span>
		<?php if ( '' !== $codice ) : ?>
			<span class="wm-flag"><?php echo esc_html( $codice ); ?></span>
		<?php endif; ?>
		<?php if ( $indicatori['validazione'] && 'itinerario' !== $tipo ) : ?>
			<span class="wm-stick__st"><?php echo esc_html( $indicatori['validazione']['etichetta'] ); ?></span>
		<?php endif; ?>
	</div>

	<div class="forestas-azioni wm-actions">
		<?php if ( '' !== $gpx ) : ?>
			<a class="forestas-pulsante" href="<?php echo esc_url( $gpx ); ?>" download><?php echo esc_html( forestas_testo( 'Scarica GPX', 'Download GPX' ) ); ?></a>
		<?php endif; ?>
		<button type="button" class="forestas-pulsante" aria-disabled="true"><?php echo esc_html( forestas_testo( 'Stampa', 'Print' ) ); ?></button>
		<?php echo forestas_condividi( [ 'url' => (string) $detail['url'], 'titolo' => $nome ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
		<?php if ( 'itinerario' !== $tipo ) : ?>
			<button type="button" class="forestas-pulsante" aria-disabled="true"><?php echo esc_html( forestas_testo( 'Segnala', 'Report' ) ); ?><span class="screen-reader-text"> <?php echo esc_html( forestas_testo( 'un problema', 'a problem' ) ); ?></span></button>
		<?php endif; ?>
	</div>

	<?php if ( 'itinerario' !== $tipo ) : ?>
		<?php // Percorribilità aggiornata dal catasto: il dato non esiste ancora ?>
		<div class="wm-state wm-segnaposto">
			<p class="wm-state__lbl"><?php echo esc_html( forestas_testo( 'Percorribilità', 'Walkability' ) ); ?></p>
			<p><?php echo esc_html( $non_disp ); ?></p>
		</div>

		<div class="wm-ind">
			<?php
			$livello = $indicatori['validazione'] ? forestas_dettaglio_livello_validazione( $indicatori['validazione']['chiave'] ) : 0;
			?>
			<div class="wm-ind__i wm-ind__i--val">
				<p class="wm-ind__k"><?php echo esc_html( forestas_testo( 'Validazione catastale', 'Trail register validation' ) ); ?>
					<button type="button" class="wm-ind__info" aria-expanded="false" aria-controls="wm-leg-val" aria-label="<?php echo esc_attr( forestas_testo( 'Legenda: livelli di validazione catastale', 'Legend: validation levels' ) ); ?>">i</button></p>
				<span class="wm-tac" role="img" aria-label="<?php echo esc_attr( $indicatori['validazione'] ? sprintf( forestas_testo( 'Validazione catastale: livello %1$d su %2$d', 'Validation: level %1$d of %2$d' ), $livello, 6 ) : $non_disp ); ?>"><?php echo $tacche( $livello, 6 ); // phpcs:ignore ?></span>
				<p class="wm-ind__v"><?php echo esc_html( $indicatori['validazione'] ? $livello . ' ' . forestas_testo( 'su', 'of' ) . ' 6 · ' . $indicatori['validazione']['etichetta'] : $non_disp ); ?></p>
				<div class="wm-ind__leg" id="wm-leg-val" hidden>
					<p><?php echo esc_html( forestas_testo( 'Validazione catastale, da 0 a 6', 'Trail register validation, from 0 to 6' ) ); ?></p>
					<ol>
						<?php
						foreach ( forestas_search_term_labels() as $chiave => $etichetta ) {
							if ( 0 === strpos( $chiave, 'validation-status-' ) ) {
								$ordine[ forestas_dettaglio_livello_validazione( $chiave ) ] = $etichetta;
							}
						}
						ksort( $ordine );
						foreach ( $ordine as $n => $etichetta ) {
							printf( '<li><b>%d</b> %s</li>', (int) $n, esc_html( $etichetta ) );
						}
						?>
					</ol>
				</div>
			</div>
			<div class="wm-ind__i wm-ind__i--per wm-segnaposto">
				<p class="wm-ind__k"><?php echo esc_html( forestas_testo( 'Percorribilità', 'Walkability' ) ); ?></p>
				<span class="wm-tac" role="img" aria-label="<?php echo esc_attr( $non_disp ); ?>"><?php echo $tacche( 0, 5 ); // phpcs:ignore ?></span>
				<p class="wm-ind__v"><?php echo esc_html( $non_disp ); ?></p>
			</div>
			<div class="wm-ind__i wm-ind__i--acc<?php echo $indicatori['accessibilita'] ? '' : ' wm-segnaposto'; ?>">
				<p class="wm-ind__k"><?php echo esc_html( forestas_testo( 'Accessibilità', 'Accessibility' ) ); ?></p>
				<?php
				$classi = [ 'A6', 'A5', 'A4', 'A3', 'A2', 'A1' ];
				$acceso = $indicatori['accessibilita'] ? (int) array_search( $indicatori['accessibilita']['classe'], $classi, true ) + 1 : 0;
				?>
				<span class="wm-tac" role="img" aria-label="<?php echo esc_attr( $indicatori['accessibilita'] ? forestas_testo( 'Classe di inclusività ', 'Inclusivity class ' ) . $indicatori['accessibilita']['classe'] : $non_disp ); ?>"><?php echo $tacche( $acceso, 6 ); // phpcs:ignore ?></span>
				<p class="wm-ind__v"><?php echo esc_html( $indicatori['accessibilita'] ? forestas_testo( 'Classe ', 'Class ' ) . $indicatori['accessibilita']['classe'] : $non_disp ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="wm-cols">
		<div class="wm-main">
			<section class="wm-sec" aria-labelledby="wm-breve">
				<?php echo $titolo( forestas_dettaglio_titolo_breve( $tipo ), 'wm-breve' ); // phpcs:ignore ?>
				<dl class="wm-facts">
					<?php
					$fatti = [
						forestas_testo( 'Lunghezza', 'Length' )             => forestas_dettaglio_misura( $p['distance'] ?? null, 'km', 1 ),
						forestas_testo( 'Dislivello totale', 'Total elevation gain' ) => forestas_dettaglio_misura( $p['ascent'] ?? null, 'm' ),
						forestas_testo( 'Tempo di percorrenza', 'Walking time' ) => forestas_dettaglio_durata( $p['duration_forward'] ?? null ),
						forestas_testo( 'Quota min — max', 'Elevation min — max' ) => ( is_numeric( $p['ele_min'] ?? null ) && is_numeric( $p['ele_max'] ?? null ) ) ? (int) $p['ele_min'] . ' — ' . (int) $p['ele_max'] : null,
					];
					foreach ( $fatti as $etichetta => $valore ) {
						printf( '<div class="wm-fact"><dt>%2$s</dt><dd>%1$s</dd></div>', esc_html( forestas_dettaglio_valore( $valore ) ), esc_html( $etichetta ) );
					}
					?>
				</dl>
				<?php
				$descrizione = forestas_dettaglio_testo( $p['description'] ?? null );
				$info        = forestas_dettaglio_testo( $forestas['useful_info'] ?? null );
				?>
				<?php if ( '' !== $descrizione ) : ?>
					<div class="wm-blk"><h3><?php echo esc_html( forestas_testo( 'Descrizione', 'Description' ) ); ?></h3><?php echo $espandibile( $descrizione, 'wm-descrizione' ); // phpcs:ignore ?></div>
				<?php endif; ?>
				<?php if ( '' !== $info ) : ?>
					<div class="wm-blk"><h3><?php echo esc_html( forestas_testo( 'Informazioni utili', 'Useful information' ) ); ?></h3><?php echo $espandibile( $info, 'wm-info' ); // phpcs:ignore ?></div>
				<?php endif; ?>
			</section>

			<?php if ( 'cammino' === $tipo ) : ?>
				<?php
				// «Il cammino, in sintesi» con i soli dati che esistono (Tavola 5, chiesto da Forestas il 17/09)
				$tematismi = [];
				foreach ( (array) ( $p['taxonomies']['itinerary-type'] ?? [] ) as $termine ) {
					$tematismo = forestas_dettaglio_testo( $termine['name'] ?? null );
					if ( '' !== $tematismo ) {
						$tematismi[] = $tematismo;
					}
				}
				$gestori = array_values( array_filter( $enti, fn( $ente ) => 'gestore' === $ente['ruolo'] ) );
				?>
				<section class="wm-sec" aria-labelledby="wm-sintesi">
					<?php echo $titolo( forestas_testo( 'Il cammino, in sintesi', 'The walk at a glance' ), 'wm-sintesi' ); // phpcs:ignore ?>
					<table class="wm-dt">
						<tbody>
							<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Tematismo', 'Theme' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( implode( ', ', $tematismi ) ) ); ?></td></tr>
							<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Gestore', 'Managing authority' ) ); ?></th><td><?php echo esc_html( $gestori ? implode( ', ', array_map( fn( $ente ) => $ente['nome'] ?? $non_disp, $gestori ) ) : '—' ); ?></td></tr>
							<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Iscrizione al Registro', 'Register entry' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( $forestas['registry_code'] ?? null ) ); ?></td></tr>
						</tbody>
					</table>
				</section>
			<?php endif; ?>

			<?php
			// Roadbook dai Blocchi Dettaglio (`config_detail`): una riga per tappa con il titolo dei dati, «Tappa N» (oc:8959)
			$tappe = forestas_dettaglio_blocchi( $p );
			?>
			<?php if ( $tappe ) : ?>
				<section class="wm-sec" aria-labelledby="wm-roadbook">
					<?php echo $titolo( forestas_testo( 'Descrizione tappe (roadbook)', 'Stage description (roadbook)' ), 'wm-roadbook' ); // phpcs:ignore ?>
					<div class="wm-rb">
						<?php foreach ( $tappe as $i => $tappa ) : ?>
							<h3 class="wm-rb__h"><button type="button" class="wm-rb__btn" aria-expanded="<?php echo 0 === $i ? 'true' : 'false'; ?>" aria-controls="wm-rb-<?php echo (int) $i; ?>"><?php echo esc_html( '' !== $tappa['titolo'] ? $tappa['titolo'] : sprintf( forestas_testo( 'Tappa %d', 'Stage %d' ), $i + 1 ) ); ?></button></h3>
							<div class="wm-rb__p" id="wm-rb-<?php echo (int) $i; ?>" <?php echo 0 === $i ? '' : 'hidden'; ?>><?php echo wp_kses_post( $tappa['testo'] ); ?></div>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php
			$difficolta = forestas_dettaglio_difficolta( $p );
			$avvertenze = forestas_dettaglio_avvertenze( $p );
			?>
			<?php if ( $difficolta || $avvertenze ) : ?>
				<section class="wm-sec" aria-labelledby="wm-attivita">
					<?php echo $titolo( forestas_testo( 'Attività e avvertenze', 'Activities and warnings' ), 'wm-attivita' ); // phpcs:ignore ?>
					<?php if ( $difficolta ) : ?>
						<table class="wm-diff">
							<thead><tr><th scope="col"><?php echo esc_html( forestas_testo( 'Attività', 'Activity' ) ); ?></th><th scope="col"><?php echo esc_html( forestas_testo( 'Difficoltà', 'Difficulty' ) ); ?></th></tr></thead>
							<tbody>
								<?php foreach ( $difficolta as [ $attivita, $grado ] ) : ?>
									<tr><td><?php echo esc_html( $attivita ); ?></td><td><span class="wm-pill"><?php echo esc_html( $grado ); ?></span></td></tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
					<?php if ( $avvertenze ) : ?>
						<p class="wm-att"><strong><?php echo esc_html( forestas_testo( 'Avvertenze:', 'Warnings:' ) ); ?></strong> <?php echo esc_html( implode( ', ', $avvertenze ) ); ?></p>
					<?php endif; ?>
				</section>
			<?php endif; ?>

			<?php if ( $galleria ) : ?>
				<?php forestas_galleria_lightbox(); ?>
				<section class="wm-sec" aria-labelledby="wm-galleria">
					<?php echo $titolo( forestas_testo( 'Galleria fotografica', 'Photogallery' ), 'wm-galleria' ); // phpcs:ignore ?>
					<ul class="wm-gal" id="wm-galleria-foto" data-wm-limite="3" data-slick-lightbox-gallery data-precedente="<?php echo esc_attr( forestas_testo( 'Precedente', 'Previous' ) ); ?>" data-successivo="<?php echo esc_attr( forestas_testo( 'Successiva', 'Next' ) ); ?>" data-chiudi="<?php echo esc_attr( forestas_testo( 'Chiudi', 'Close' ) ); ?>">
						<?php foreach ( $galleria as $i => $immagine ) : ?>
							<li><a href="<?php echo esc_url( $immagine['url'] ); ?>" class="blazy__slick-lightbox" data-slick-lightbox-trigger data-b-delta="<?php echo (int) $i; ?>"><img src="<?php echo esc_url( $immagine['miniatura'] ); ?>" alt="<?php echo esc_attr( $immagine['didascalia'] ); ?>" loading="lazy" decoding="async"></a>
								<div class="visually-hidden"><p><?php echo esc_html( $immagine['didascalia'] ); ?></p><em class="auth"><?php echo esc_html( $immagine['autore'] ); ?></em><em class="cred"><?php echo esc_html( $immagine['credits'] ); ?></em></div></li>
						<?php endforeach; ?>
					</ul>
					<?php echo forestas_dettaglio_mostra_tutti( 'wm-galleria-foto', count( $galleria ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
				</section>
			<?php endif; ?>

			<?php
			$allegati = array_values( array_filter( (array) ( $forestas['attachments'] ?? [] ), fn( $a ) => ! empty( $a['url'] ) ) );
			$video    = array_values( array_filter( (array) ( $forestas['videos'] ?? [] ), fn( $v ) => ! empty( $v['url'] ) ) );
			?>
			<?php if ( $allegati || $video ) : ?>
				<section class="wm-sec" aria-labelledby="wm-allegati">
					<?php echo $titolo( forestas_testo( 'Allegati', 'Attachments' ), 'wm-allegati' ); // phpcs:ignore ?>
					<ul class="wm-files">
						<?php foreach ( $allegati as $allegato ) : ?>
							<li><a href="<?php echo esc_url( $allegato['url'] ); ?>" download><?php echo esc_html( $allegato['title'] ?: basename( (string) wp_parse_url( $allegato['url'], PHP_URL_PATH ) ) ); ?></a></li>
						<?php endforeach; ?>
						<?php foreach ( $video as $i => $filmato ) : ?>
							<li><a href="<?php echo esc_url( $filmato['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $filmato['title'] ?: sprintf( forestas_testo( 'Video %d', 'Video %d' ), $i + 1 ) ); ?><span class="screen-reader-text"> <?php echo esc_html( forestas_testo( '(si apre in una nuova finestra)', '(opens in a new window)' ) ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<section class="wm-sec" aria-labelledby="wm-dati">
				<?php echo $titolo( 'sentiero' === $tipo ? forestas_testo( 'Dati catastali e altimetrici', 'Register and elevation data' ) : forestas_testo( 'Dati del percorso', 'Route data' ), 'wm-dati' ); // phpcs:ignore ?>
				<div class="wm-dt-wrap">
					<?php foreach ( forestas_dettaglio_tabelle( $p, $tipo ) as $tabella ) : ?>
						<table class="wm-dt">
							<caption><?php echo esc_html( $tabella['titolo'] ); ?></caption>
							<tbody>
								<?php foreach ( $tabella['righe'] as [ $etichetta, $valore ] ) : ?>
									<tr><th scope="row"><?php echo esc_html( $etichetta ); ?></th><td><?php echo esc_html( $valore ); ?></td></tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endforeach; ?>
					<?php if ( $enti ) : ?>
						<table class="wm-dt">
							<caption><?php echo esc_html( forestas_testo( 'Enti', 'Organisations' ) ); ?></caption>
							<tbody>
								<?php foreach ( $enti as $ente ) : ?>
									<tr><th scope="row"><?php echo esc_html( $ente['etichetta'] ); ?></th><td<?php echo null === $ente['nome'] ? ' class="wm-segnaposto"' : ''; ?>><?php echo esc_html( $ente['nome'] ?? $non_disp ); ?></td></tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					<?php endif; ?>
				</div>
			</section>

			<?php
			?>
			<?php if ( $correlati['poi'] ) : ?>
				<section class="wm-sec" aria-labelledby="wm-poi">
					<?php echo $titolo( forestas_testo( 'Punti di interesse correlati', 'Related points of interest' ), 'wm-poi' ); // phpcs:ignore ?>
					<?php echo forestas_dettaglio_griglia( $correlati['poi'], 'poi', 'wm-correlati-poi' ); // phpcs:ignore ?>
				</section>
			<?php endif; ?>
			<?php if ( $correlati['percorsi'] ) : ?>
				<section class="wm-sec" aria-labelledby="wm-correlati">
					<?php echo $titolo( 'sentiero' === $tipo ? forestas_testo( 'Sentieri correlati', 'Related trails' ) : forestas_testo( 'Percorsi correlati', 'Related routes' ), 'wm-correlati' ); // phpcs:ignore ?>
					<?php echo forestas_dettaglio_griglia( $correlati['percorsi'], 'percorso', 'wm-correlati-percorsi' ); // phpcs:ignore ?>
				</section>
			<?php endif; ?>
		</div>

		<aside class="wm-aside" aria-label="<?php echo esc_attr( forestas_testo( 'Mappa del percorso', 'Route map' ) ); ?>">
			<?php echo do_shortcode( '[wmwp_map tracks="' . (int) $detail['id'] . '" height="460" label="' . esc_attr( forestas_testo( 'Mappa del percorso', 'Route map' ) ) . '"]' ); ?>
		</aside>
	</div>

	<?php if ( 'itinerario' !== $tipo ) : ?>
		<section class="wm-sec wm-sec--last" aria-labelledby="wm-recensioni">
			<?php echo $titolo( forestas_testo( 'Opinioni e recensioni', 'Opinions and reviews' ), 'wm-recensioni' ); // phpcs:ignore ?>
			<p class="wm-segnaposto"><?php echo esc_html( forestas_testo( 'Le recensioni non sono ancora disponibili.', 'Reviews are not yet available.' ) ); ?></p>
		</section>
	<?php endif; ?>
</main>
<?php
wmwp_template_footer();
