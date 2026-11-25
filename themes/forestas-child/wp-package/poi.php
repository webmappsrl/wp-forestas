<?php
/**
 * Dettaglio di un punto di interesse (oc:8969, Tavola 7 del wireframe di oc:8783). Sostituisce il template del plugin
 * wp-package. Il nome viene dal plugin (il dettaglio del POI dello shard non lo ha ancora); i dati da inc/dettaglio.php.
 * Niente «Segnala», recensioni né accessibilità: il sito attuale non li ha per i punti di interesse.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
// Con un wp-package più vecchio della 0.5.0 il dettaglio usa il template del plugin, invece di andare in fatal (oc:9000)
if ( ! forestas_plugin_ready() ) {
	require WMWP_PATH . 'templates/poi.php';
	return;
}

$detail   = wmwp_detail_current();
$p        = (array) ( $detail['feature']['properties'] ?? [] );
$colori   = forestas_dettaglio_colori( 'poi' );
$dati     = forestas_dettaglio_poi( $p );
$nome     = (string) $detail['name'];
$main_photo = wmwp_detail_feature_image( $p, forestas_dettaglio_lingua() );
$foto     = $main_photo['large'] ?? '';
$galleria = forestas_dettaglio_galleria( $p );
$non_disp = forestas_testo( 'Dato non ancora disponibile', 'Not yet available' );

$titolo = function ( string $testo, string $id ): string {
	return sprintf( '<h2 class="wm-sec__h" id="%s">%s</h2>', esc_attr( $id ), esc_html( $testo ) );
};

wmwp_template_header();
?>
<main id="page-content" class="l-main wm-dettaglio wm-dettaglio--poi" style="--wm-c:<?php echo esc_attr( $colori['colore'] ); ?>;--wm-bg:<?php echo esc_attr( $colori['fascia'] ); ?>">

	<header class="wm-hero<?php echo '' === $foto ? ' wm-hero--senza-foto' : ''; ?>" data-wm-hero>
		<?php if ( '' !== $foto ) : ?>
			<?php // Testo alternativo dalla didascalia della foto, se c'è (specifica di oc:8783, «Testata con foto»); senza, è decorativa ?>
			<img class="wm-hero__img" src="<?php echo esc_url( $foto ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $main_photo['caption'] ?? '' ) ); ?>" fetchpriority="high">
		<?php endif; ?>
		<?php
		// «Credits foto» come nella testata delle pagine statiche, con autore e credits della foto dello shard
		$credits_foto = forestas_credits_immagine(
			[
				'autore'  => $main_photo['author'] ?? '',
				'credits' => $main_photo['credits'] ?? '',
				'chiave'  => 'poi-' . (int) $detail['id'],
			]
		);
		echo $credits_foto; // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato
		?>
		<div class="wm-hero__scrim<?php echo '' !== $credits_foto ? ' wm-hero__scrim--credits' : ''; ?>">
			<p class="wm-hero__occ">
				<span class="wm-tipo wm-tipo--poi"<?php echo forestas_dettaglio_lang( $dati['tipologia'] ? $dati['tipologia_lang'] : null ); // phpcs:ignore ?>><?php echo esc_html( $dati['tipologia'] ?? forestas_testo( 'Punto di interesse', 'Point of interest' ) ); ?></span>
				<?php if ( $dati['luoghi']['aree'] ) : ?>
					<span><?php echo esc_html( ( 1 === count( $dati['luoghi']['aree'] ) ? forestas_testo( 'Area geografica', 'Geographic area' ) : forestas_testo( 'Aree geografiche', 'Geographic areas' ) ) . ' ' . implode( ', ', $dati['luoghi']['aree'] ) ); ?></span>
				<?php endif; ?>
			</p>
			<h1 class="wm-hero__tit"><?php echo esc_html( $nome ); ?><span class="screen-reader-text">, <?php echo esc_html( forestas_testo( 'punto di interesse', 'point of interest' ) ); ?></span></h1>
			<?php if ( $dati['luoghi']['comuni'] || $dati['luoghi']['provincia'] ) : ?>
				<p class="wm-hero__sub"><?php echo esc_html( implode( ' · ', array_filter( [ implode( ', ', $dati['luoghi']['comuni'] ), $dati['luoghi']['provincia'] ? forestas_testo( 'Provincia', 'Province' ) . ' ' . $dati['luoghi']['provincia'] : '' ] ) ) ); ?></p>
			<?php endif; ?>
		</div>
	</header>

	<?php echo forestas_dettaglio_percorso( 'poi', $nome ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>

	<div class="wm-stick" data-wm-stick aria-hidden="true">
		<span class="wm-tipo"><?php echo esc_html( forestas_testo( 'Punto di interesse', 'Point of interest' ) ); ?></span>
		<span class="wm-stick__tit"><?php echo esc_html( $nome ); ?></span>
	</div>

	<div class="forestas-azioni wm-actions">
		<?php echo forestas_dettaglio_stampa( $detail ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
		<?php echo forestas_condividi( [ 'url' => (string) $detail['url'], 'titolo' => $nome ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
	</div>

	<div class="wm-cols">
		<div class="wm-main">
			<section class="wm-sec" aria-labelledby="wm-breve">
				<?php echo $titolo( forestas_testo( 'Il punto in breve', 'At a glance' ), 'wm-breve' ); // phpcs:ignore ?>
				<table class="wm-dt">
					<tbody>
						<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Tipologia', 'Type' ) ); ?></th><td<?php echo forestas_dettaglio_lang( $dati['tipologia'] ? $dati['tipologia_lang'] : null ); // phpcs:ignore ?>><?php echo esc_html( forestas_dettaglio_valore( $dati['tipologia'] ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Comune', 'Municipality' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( implode( ', ', $dati['luoghi']['comuni'] ) ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Area geografica', 'Geographic area' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( implode( ', ', $dati['luoghi']['aree'] ) ) ); ?></td></tr>
						<?php // Quota e area sosta attrezzata (Tavola 7): solo quando ci sono, la quota appena lo shard calcola il DEM dei POI ?>
						<?php if ( null !== $dati['quota'] ) : ?>
							<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Quota', 'Elevation' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_quota( $dati['quota'] ) ); ?></td></tr>
						<?php endif; ?>
						<?php if ( $dati['area_sosta'] ) : ?>
							<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Area sosta attrezzata', 'Equipped rest area' ) ); ?></th><td><?php echo esc_html( forestas_testo( 'Sì', 'Yes' ) ); ?></td></tr>
						<?php endif; ?>
						<?php foreach ( forestas_dettaglio_enti( $p ) as $ente ) : ?>
							<tr><th scope="row"><?php echo esc_html( $ente['etichetta'] ); ?></th><td><?php echo ! empty( $ente['url'] ) ? '<a href="' . esc_url( $ente['url'] ) . '">' . esc_html( $ente['nome'] ?? $non_disp ) . '</a>' : esc_html( $ente['nome'] ?? $non_disp ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>

			<?php
			// Testi ricchi: titoli rinumerati sotto quello del blocco e lingua vera se ripiegano (forestas_dettaglio_ricco)
			$descrizione = forestas_dettaglio_ricco( $p['description'] ?? null );
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

			<?php // «Percorsi correlati», con il titolo della traccia (Rubens, 19/11): sono i percorsi vicini al punto, sotto la galleria come i contenuti collegati della traccia. Li calcola lo shard (raggio in sardegnasentieri.poi_tracks_radius_meters, i più vicini per primi), il frontend non calcola niente; il raggio non si scrive, lo decide lo shard ?>
			<section class="wm-sec" aria-labelledby="wm-vicini">
				<?php echo $titolo( forestas_testo( 'Percorsi correlati', 'Related routes' ), 'wm-vicini' ); // phpcs:ignore ?>
				<?php if ( $dati['percorsi'] ) : ?>
					<?php echo forestas_dettaglio_griglia( $dati['percorsi'], 'percorso', 'wm-vicini-percorsi' ); // phpcs:ignore ?>
					<?php if ( $dati['percorsi_totale'] > count( $dati['percorsi'] ) ) : ?>
						<p class="wm-att"><?php echo esc_html( sprintf( forestas_testo( 'I %1$d percorsi correlati più vicini su %2$d.', 'The %1$d closest related routes out of %2$d.' ), count( $dati['percorsi'] ), $dati['percorsi_totale'] ) ); ?></p>
					<?php endif; ?>
				<?php else : ?>
					<p><?php echo esc_html( forestas_testo( 'Nessun percorso correlato.', 'No related routes.' ) ); ?></p>
				<?php endif; ?>
			</section>

			<?php // Punti di interesse correlati, come nella traccia (Rubens, 19/11): i POI correlati di Drupal, dallo shard ?>
			<?php if ( $dati['poi_correlati'] ) : ?>
				<section class="wm-sec" aria-labelledby="wm-poi">
					<?php echo $titolo( forestas_testo( 'Punti di interesse correlati', 'Related points of interest' ), 'wm-poi' ); // phpcs:ignore ?>
					<?php echo forestas_dettaglio_griglia( $dati['poi_correlati'], 'poi', 'wm-correlati-poi' ); // phpcs:ignore ?>
				</section>
			<?php endif; ?>

			<?php $allegati = forestas_dettaglio_allegati_html( [ 'allegati' => $dati['allegati'], 'video' => $dati['video'] ] ); ?>
			<?php if ( '' !== $allegati ) : ?>
				<section class="wm-sec" aria-labelledby="wm-allegati">
					<?php echo $titolo( forestas_testo( 'Allegati', 'Attachments' ), 'wm-allegati' ); // phpcs:ignore ?>
					<?php echo $allegati; // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
				</section>
			<?php endif; ?>
		</div>

		<?php // Senza nome: lo porta già la mappa che contiene, che lo screen reader annuncerebbe due volte ?>
		<aside class="wm-aside">
			<?php echo forestas_dettaglio_mappa_html( forestas_dettaglio_mappa( $p, 'poi', (int) $detail['id'], (array) ( $detail['feature']['geometry'] ?? [] ) ), forestas_testo( 'Mappa del punto di interesse', 'Point of interest map' ), 380, 'poi', (int) $detail['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
		</aside>
	</div>
</main>
<?php
wmwp_template_footer();
