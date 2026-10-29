<?php
/**
 * Pagina di una tipologia dei «da sapere» (oc:8760), come termine_tassonomia_da_sapere di Drupal:
 * - la testata delle pagine (sase-hero centrata nel colore di Da sapere): immagine della tipologia sullo
 *   sfondo, titolo con il filo, descrizione sotto;
 * - il percorso con la sola «Home», come su Drupal;
 * - la griglia paginata di card con la foto a sinistra su tre colonne (sase-grid--3, card has-side-thumb-left).
 * Descrizione e immagine le scrive l'import (da-sapere-lib.php) e si cambiano dalla schermata del termine
 * (inc/da-sapere.php).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$termine     = get_queried_object();
$id_immagine = (int) get_term_meta( $termine->term_id, FORESTAS_TERM_META_IMMAGINE, true );
$immagine    = $id_immagine
	? wp_get_attachment_image( $id_immagine, 'full', false, [ 'class' => 'forestas-testata-sfondo', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '100vw' ] )
	: '';
$descrizione = trim( (string) $termine->description );
get_header();
?>
<main id="page-content" class="l-main">
	<section class="l-section wpb_row height_auto width_full">
		<div class="l-section-h i-cf">
			<div class="forestas-testata forestas-sezione-da-sapere<?php echo $immagine ? '' : ' forestas-testata-senza-immagine'; ?>">
				<?php
				echo $immagine; // phpcs:ignore WordPress.Security.EscapeOutput -- da wp_get_attachment_image
				echo forestas_credits_immagine( $id_immagine ); // phpcs:ignore WordPress.Security.EscapeOutput -- già escapato
				?>
				<div class="forestas-testata-testo">
					<span class="forestas-testata-filo" aria-hidden="true"></span>
					<h1 class="forestas-testata-titolo"><?php echo esc_html( $termine->name ); ?></h1>
					<?php if ( $descrizione !== '' ) : ?>
						<div class="forestas-testata-sottotitolo"><?php echo wp_kses_post( wpautop( $descrizione ) ); ?></div>
					<?php endif; ?>
				</div>
			</div>
			<nav class="forestas-percorso" aria-label="<?php echo esc_attr( forestas_testo( 'Briciole di pane', 'Breadcrumb' ) ); ?>">
				<a href="<?php echo esc_url( apply_filters( 'wpml_home_url', home_url( '/' ) ) ); ?>">Home</a>
			</nav>
			<div class="forestas-elenco forestas-sezione-da-sapere">
				<h2 class="screen-reader-text"><?php echo esc_html( forestas_testo( 'Contenuti', 'Contents' ) ); ?></h2>
				<?php
				if ( have_posts() ) {
					update_post_thumbnail_cache();
					echo '<div class="forestas-griglia forestas-griglia--compatta forestas-griglia--tipologia">';
					while ( have_posts() ) {
						the_post();
						echo forestas_card( get_the_ID(), 'compatta', 3 ); // phpcs:ignore WordPress.Security.EscapeOutput -- card già escapata
					}
					echo '</div>';
					the_posts_pagination(
						[
							'prev_text' => '<span aria-hidden="true">‹</span><span class="screen-reader-text">' . esc_html( forestas_testo( 'Pagina precedente', 'Previous page' ) ) . '</span>',
							'next_text' => '<span aria-hidden="true">›</span><span class="screen-reader-text">' . esc_html( forestas_testo( 'Pagina successiva', 'Next page' ) ) . '</span>',
							'class'     => 'forestas-paginazione',
						]
					);
				} else {
					echo '<p class="forestas-griglia-vuota">' . esc_html( forestas_testo( 'Nessun contenuto.', 'No content.' ) ) . '</p>';
				}
				?>
			</div>
		</div>
	</section>
</main>
<?php
get_footer();
