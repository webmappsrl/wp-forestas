<?php
/**
 * Dettaglio di un punto di interesse (oc:8969, Tavola 7 del wireframe di oc:8783). Sostituisce il template del plugin
 * wp-package. Il nome viene dal plugin (il dettaglio del POI dello shard non lo ha ancora); i dati da inc/dettaglio.php.
 * Niente «Segnala», recensioni né accessibilità: il sito attuale non li ha per i punti di interesse.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$detail   = wmwp_detail_current();
$p        = (array) ( $detail['feature']['properties'] ?? [] );
$colori   = forestas_dettaglio_colori( 'poi' );
$dati     = forestas_dettaglio_poi( $p );
$nome     = (string) $detail['name'];
$foto     = $p['feature_image']['sizes']['1440x500'] ?? $p['feature_image']['url'] ?? '';
$galleria = forestas_dettaglio_galleria( $p );
$non_disp = forestas_testo( 'Dato non ancora disponibile', 'Not yet available' );
$origin   = function_exists( 'wmwp_configured_api_urls' ) ? wmwp_configured_api_urls()['origin'] : '';

$titolo = function ( string $testo, string $id ): string {
	return sprintf( '<h2 class="wm-sec__h" id="%s">%s</h2>', esc_attr( $id ), esc_html( $testo ) );
};

wmwp_template_header();
?>
<main id="page-content" class="l-main wm-dettaglio wm-dettaglio--poi" style="--wm-c:<?php echo esc_attr( $colori['colore'] ); ?>;--wm-bg:<?php echo esc_attr( $colori['fascia'] ); ?>">

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
				'chiave'  => 'poi-' . (int) $detail['id'],
			]
		);
		echo $credits_foto; // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato
		?>
		<div class="wm-hero__scrim<?php echo '' !== $credits_foto ? ' wm-hero__scrim--credits' : ''; ?>">
			<p class="wm-hero__occ">
				<span class="wm-tipo wm-tipo--poi"><?php echo esc_html( $dati['tipologia'] ?? forestas_testo( 'Punto di interesse', 'Point of interest' ) ); ?></span>
				<?php if ( $dati['luoghi']['aree'] ) : ?>
					<span><?php echo esc_html( forestas_testo( 'Aree geografiche', 'Geographic areas' ) . ' ' . implode( ', ', $dati['luoghi']['aree'] ) ); ?></span>
				<?php endif; ?>
			</p>
			<h1 class="wm-hero__tit"><?php echo esc_html( $nome ); ?><span class="screen-reader-text">, <?php echo esc_html( forestas_testo( 'punto di interesse', 'point of interest' ) ); ?></span></h1>
			<?php if ( $dati['luoghi']['comuni'] || $dati['luoghi']['provincia'] ) : ?>
				<p class="wm-hero__sub"><?php echo esc_html( implode( ' · ', array_filter( [ implode( ', ', $dati['luoghi']['comuni'] ), $dati['luoghi']['provincia'] ? forestas_testo( 'Provincia', 'Province' ) . ' ' . $dati['luoghi']['provincia'] : '' ] ) ) ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<div class="wm-stick" data-wm-stick aria-hidden="true">
		<span class="wm-tipo"><?php echo esc_html( forestas_testo( 'Punto di interesse', 'Point of interest' ) ); ?></span>
		<span class="wm-stick__tit"><?php echo esc_html( $nome ); ?></span>
	</div>

	<div class="forestas-azioni wm-actions">
		<button type="button" class="forestas-pulsante" aria-disabled="true" aria-describedby="wm-non-disp"><?php echo esc_html( forestas_testo( 'Stampa', 'Print' ) ); ?></button>
		<?php echo forestas_condividi( [ 'url' => (string) $detail['url'], 'titolo' => $nome ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
		<span class="wm-actions__nota" id="wm-non-disp"><?php echo esc_html( forestas_testo( 'La stampa non è ancora disponibile.', 'Print is not yet available.' ) ); ?></span>
	</div>

	<div class="wm-cols">
		<div class="wm-main">
			<section class="wm-sec" aria-labelledby="wm-breve">
				<?php echo $titolo( forestas_testo( 'Il punto in breve', 'At a glance' ), 'wm-breve' ); // phpcs:ignore ?>
				<table class="wm-dt">
					<tbody>
						<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Tipologia', 'Type' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( $dati['tipologia'] ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Comune', 'Municipality' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( implode( ', ', $dati['luoghi']['comuni'] ) ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Area geografica', 'Geographic area' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( implode( ', ', $dati['luoghi']['aree'] ) ) ); ?></td></tr>
						<?php foreach ( forestas_dettaglio_enti( $p ) as $ente ) : ?>
							<tr><th scope="row"><?php echo esc_html( $ente['etichetta'] ); ?></th><td><?php echo esc_html( $ente['nome'] ?? $non_disp ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<?php // Percorsi vicini: li calcola lo shard (entro 1 km, i più vicini per primi), il frontend non calcola niente ?>
			<section class="wm-sec" aria-labelledby="wm-vicini">
				<?php echo $titolo( forestas_testo( 'Percorsi vicini', 'Nearby routes' ), 'wm-vicini' ); // phpcs:ignore ?>
				<?php if ( $dati['percorsi'] ) : ?>
					<?php echo forestas_dettaglio_griglia( $dati['percorsi'], 'percorso', 'wm-vicini-percorsi' ); // phpcs:ignore ?>
					<?php if ( $dati['percorsi_totale'] > count( $dati['percorsi'] ) ) : ?>
						<p class="wm-att"><?php echo esc_html( sprintf( forestas_testo( 'I %1$d percorsi più vicini su %2$d entro 1 km.', 'The %1$d closest routes out of %2$d within 1 km.' ), count( $dati['percorsi'] ), $dati['percorsi_totale'] ) ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p><?php echo esc_html( forestas_testo( 'Nessun percorso entro 1 km.', 'No routes within 1 km.' ) ); ?></p>
				<?php endif; ?>
			</section>

			<?php
			$descrizione = forestas_dettaglio_testo( $p['description'] ?? null );
			?>
			<?php if ( '' !== $descrizione || '' !== $dati['come_arrivare'] ) : ?>
				<section class="wm-sec" aria-labelledby="wm-punto">
					<?php echo $titolo( forestas_testo( 'Il punto di interesse', 'The point of interest' ), 'wm-punto' ); // phpcs:ignore ?>
					<?php if ( '' !== $descrizione ) : ?>
						<div class="wm-blk"><h3><?php echo esc_html( forestas_testo( 'Descrizione', 'Description' ) ); ?></h3><div class="wm-xp__body"><?php echo wp_kses_post( $descrizione ); ?></div></div>
					<?php endif; ?>
					<?php if ( '' !== $dati['come_arrivare'] ) : ?>
						<div class="wm-blk"><h3><?php echo esc_html( forestas_testo( 'Come arrivare', 'How to get there' ) ); ?></h3><div class="wm-xp__body"><?php echo wp_kses_post( $dati['come_arrivare'] ); ?></div></div>
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

			<?php if ( $dati['allegati'] || $dati['video'] ) : ?>
				<section class="wm-sec" aria-labelledby="wm-allegati">
					<?php echo $titolo( forestas_testo( 'Allegati', 'Attachments' ), 'wm-allegati' ); // phpcs:ignore ?>
					<ul class="wm-files">
						<?php foreach ( $dati['allegati'] as $allegato ) : ?>
							<li><a href="<?php echo esc_url( $allegato['url'] ); ?>" download><?php echo esc_html( $allegato['title'] ); ?></a></li>
						<?php endforeach; ?>
						<?php foreach ( $dati['video'] as $i => $filmato ) : ?>
							<li><a href="<?php echo esc_url( $filmato['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( sprintf( forestas_testo( 'Video %d', 'Video %d' ), $i + 1 ) ); ?><span class="screen-reader-text"> <?php echo esc_html( forestas_testo( '(si apre in una nuova finestra)', '(opens in a new window)' ) ); ?></span></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>

		<aside class="wm-aside" aria-label="<?php echo esc_attr( forestas_testo( 'Mappa del punto di interesse', 'Point of interest map' ) ); ?>">
			<?php
			if ( '' !== $origin ) {
				echo do_shortcode( '[wmwp_map geojson="' . esc_url( $origin . '/api/ec/poi/' . (int) $detail['id'] . '.geojson' ) . '" height="380" label="' . esc_attr( forestas_testo( 'Mappa del punto di interesse', 'Point of interest map' ) ) . '"]' );
			}
			?>
		</aside>
	</div>
</main>
<?php
wmwp_template_footer();
