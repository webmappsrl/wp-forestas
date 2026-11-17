<?php
/**
 * Dettaglio di un ente (oc:8990): `/enti/<slug>/` e `/en/enti/<slug>/`, servito da forestas_entes_template() con stato
 * 200. Contenuti e ordine del template Drupal node--ente-istituzione-societa--full (su node--base--full): nome e
 * immagine principale con i credits, «Informazioni» con la descrizione e «Mostra tutto», tipo di ente, contatti, pagina
 * web, collegamenti, «Sentieri gestiti», galleria, date di aggiornamento e di creazione. Su Drupal i sentieri gestiti
 * vengono prima della galleria (blocco content_infos_managed prima di content_infos_gallery). Sezioni vuote non mostrate.
 * Lo stile è quello delle pagine e delle news del child: testata, percorso e «Condividi», testo nella colonna di 960px
 * (forestas-pagina-testo); «Sentieri gestiti» e galleria con i componenti del dettaglio traccia
 * (forestas_dettaglio_griglia(), la lightbox, «Mostra tutti (N)»), che assets/js/dettaglio.js anima anche qui.
 * Dati: forestas_entes_page() (`ente`, `lang`), forestas_entes_view() e forestas_entes_managed_tracks().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entes_page = forestas_entes_page();
if ( null === $entes_page || 'detail' !== $entes_page['view'] || ! isset( $entes_page['ente'] ) ) {
	return;
}
$ente_data    = $entes_page['ente'];
$ente_slug    = forestas_entes_slug( $ente_data );
$ente_view    = forestas_entes_view( $ente_data, $entes_page['lang'] );
$ente_lang    = forestas_dettaglio_lang( $ente_view['name_lang'] );
$ente_managed = forestas_entes_managed_tracks( $ente_data );
$ente_gallery = $ente_view['gallery'];
// Pagina web: un indirizzo con un protocollo non ammesso (esc_url() → '') non si mostra, invece di un link vuoto
$ente_website_url = null === $ente_view['website'] ? '' : esc_url( $ente_view['website']['url'] );
// Testo del link alla pagina web come il formatter «link» di Drupal (trim_length 80); i collegamenti non si tagliano
$ente_website_text = '' === $ente_website_url ? '' : forestas_trim_link_text( $ente_view['website']['title'] );
$ente_has_fields = null !== $ente_view['type'] || null !== $ente_view['contacts'] || '' !== $ente_website_url || $ente_view['links'];

// Stile delle pagine degli enti e componenti del dettaglio traccia (griglia, galleria, «Mostra tutto»), nello stesso
// ordine del dettaglio traccia: dopo lo style.css del child (accodato da UpSolution Core, versione del child a
// PHP_INT_MAX in functions.php), così a parità di selettore vincono questi
add_action(
	'wp_enqueue_scripts',
	function () use ( $ente_gallery ) {
		$assets = get_stylesheet_directory_uri() . '/assets/';
		wp_enqueue_style( 'forestas-dettaglio', $assets . 'css/dettaglio.css', [], forestas_versione() );
		wp_enqueue_style( 'forestas-entes', $assets . 'css/entes.css', [ 'forestas-dettaglio' ], forestas_versione() );
		wp_enqueue_script( 'forestas-dettaglio', $assets . 'js/dettaglio.js', [], forestas_versione(), [ 'in_footer' => true, 'strategy' => 'defer' ] );
		if ( $ente_gallery ) {
			forestas_galleria_lightbox();
		}
	},
	PHP_INT_MAX
);

get_header();
?>
<main id="page-content" class="l-main forestas-ente forestas-sezione-base">
	<?php
	// Testata delle pagine del child ([forestas_testata]): foto con la sfumatura, filo, nome; senza foto quella del tema
	// Drupal. Testo alternativo dalla didascalia della foto, come la testata del dettaglio traccia
	$ente_image = $ente_view['image'];
	?>
	<div class="forestas-testata forestas-sezione-base<?php echo null === $ente_image ? ' forestas-testata-senza-immagine' : ''; ?>">
		<?php if ( null !== $ente_image ) : ?>
			<img class="forestas-testata-sfondo" src="<?php echo esc_url( $ente_image['url'] ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( $ente_image['didascalia'] ) ); ?>" loading="eager" fetchpriority="high">
			<?php
			echo forestas_credits_immagine( // phpcs:ignore WordPress.Security.EscapeOutput -- markup già con l'escape
				[
					'autore'  => $ente_image['autore'],
					'credits' => $ente_image['credits'],
					'chiave'  => 'ente-' . (int) ( $ente_data['id'] ?? 0 ),
				]
			);
			?>
		<?php endif; ?>
		<div class="forestas-testata-testo">
			<span class="forestas-testata-filo" aria-hidden="true"></span>
			<h1 class="forestas-testata-titolo" data-test="ente-detail" data-slug="<?php echo esc_attr( $ente_slug ); ?>"<?php echo $ente_lang; // phpcs:ignore WordPress.Security.EscapeOutput -- attributo già con l'escape ?>><?php echo esc_html( $ente_view['name'] ); ?></h1>
		</div>
	</div>
	<nav class="forestas-percorso" aria-label="<?php echo esc_attr( forestas_testo( 'Briciole di pane', 'Breadcrumb' ) ); ?>"><a href="<?php echo esc_url( apply_filters( 'wpml_home_url', home_url( '/' ) ) ); ?>">Home</a><span aria-hidden="true">›</span><a href="<?php echo esc_url( forestas_entes_list_url( $entes_page['lang'] ) ); ?>"><?php echo esc_html( forestas_entes_list_title() ); ?></a><span aria-hidden="true">›</span><span aria-current="page"<?php echo $ente_lang; // phpcs:ignore WordPress.Security.EscapeOutput -- attributo già con l'escape ?>><?php echo esc_html( $ente_view['name'] ); ?></span></nav>
	<?php // «Condividi» al posto della condivisione AddToAny della testata di Drupal, come nelle pagine e nelle news ?>
	<div class="forestas-azioni"><?php echo forestas_condividi( [ 'url' => forestas_entes_url( $ente_slug, $entes_page['lang'] ), 'titolo' => $ente_view['name'] ] ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già con l'escape ?></div>

	<div class="forestas-pagina-testo forestas-ente__corpo">
		<?php if ( null !== $ente_view['description'] || $ente_has_fields ) : ?>
			<section class="forestas-ente__sezione" aria-labelledby="forestas-ente-informazioni">
				<h2 class="forestas-ente__titolo" id="forestas-ente-informazioni"><?php echo esc_html( forestas_testo( 'Informazioni', 'Information' ) ); ?></h2>
				<?php if ( null !== $ente_view['description'] ) : ?>
					<?php
					// Descrizione con «Mostra tutto» come su Drupal (sase-expandable-section): il testo intero è sempre nel
					// documento, chiuso a 30em solo se li supera. Titoli da h3, sotto «Informazioni» (forestas_entes_view())
					?>
					<div class="wm-xp forestas-ente__descrizione" data-wm-xp>
						<div class="wm-xp__body forestas-pagina-testo" id="forestas-ente-descrizione"<?php echo forestas_dettaglio_lang( $ente_view['description_lang'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- attributo già con l'escape ?>><?php echo $ente_view['description']; // phpcs:ignore WordPress.Security.EscapeOutput -- HTML già filtrato da forestas_entes_view() ?></div>
						<div class="wm-xp__more" hidden><button type="button" class="forestas-pulsante" aria-controls="forestas-ente-descrizione" aria-expanded="false" data-piu="<?php echo esc_attr( forestas_testo( 'Mostra tutto', 'Show all' ) ); ?>" data-meno="<?php echo esc_attr( forestas_testo( 'Mostra meno', 'Show less' ) ); ?>"><?php echo esc_html( forestas_testo( 'Mostra tutto', 'Show all' ) ); ?></button></div>
					</div>
				<?php endif; ?>
				<?php if ( $ente_has_fields ) : ?>
					<div class="forestas-ente__campi">
						<?php if ( null !== $ente_view['type'] ) : ?>
							<div class="forestas-ente__campo">
								<h3 class="forestas-ente__etichetta"><?php echo esc_html( forestas_testo( 'Tipo di ente', 'Type of organisation' ) ); ?></h3>
								<p class="forestas-ente__valore"><?php echo esc_html( $ente_view['type'] ); ?></p>
							</div>
						<?php endif; ?>
						<?php if ( null !== $ente_view['contacts'] ) : ?>
							<div class="forestas-ente__campo">
								<h3 class="forestas-ente__etichetta"><?php echo esc_html( forestas_testo( 'Contatti', 'Contacts' ) ); ?></h3>
								<?php // Titoli dei contatti da h4, sotto «Contatti» (h3) ?>
								<div class="forestas-ente__valore forestas-pagina-testo"<?php echo forestas_dettaglio_lang( $ente_view['contacts_lang'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- attributo già con l'escape ?>><?php echo forestas_dettaglio_titoli( $ente_view['contacts'], 4 ); // phpcs:ignore WordPress.Security.EscapeOutput -- HTML già filtrato da forestas_entes_view() ?></div>
							</div>
						<?php endif; ?>
						<?php if ( '' !== $ente_website_url ) : ?>
							<div class="forestas-ente__campo">
								<h3 class="forestas-ente__etichetta"><?php echo esc_html( forestas_testo( 'Pagina web', 'Website' ) ); ?></h3>
								<ul class="forestas-ente__link" role="list"><li><a class="forestas-link-esterno" href="<?php echo $ente_website_url; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url() sopra ?>"><?php echo esc_html( $ente_website_text ); ?><i class="far fa-external-link" aria-hidden="true"></i></a></li></ul>
							</div>
						<?php endif; ?>
						<?php if ( $ente_view['links'] ) : ?>
							<div class="forestas-ente__campo forestas-ente__campo--collegamenti">
								<h3 class="forestas-ente__etichetta"><?php echo esc_html( forestas_testo( 'Collegamenti', 'Links' ) ); ?></h3>
								<ul class="forestas-ente__link" role="list">
									<?php foreach ( $ente_view['links'] as $ente_link ) : ?>
										<li><a class="forestas-link-freccia" href="<?php echo esc_url( $ente_link['url'] ); ?>"><i class="fas fa-arrow-right" aria-hidden="true"></i><?php echo esc_html( $ente_link['title'] ); ?></a></li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( $ente_managed ) : ?>
			<?php // Come i «Percorsi correlati» del dettaglio traccia: la stessa card, tre colonne, «Mostra tutti (N)» oltre le prime tre ?>
			<section class="forestas-ente__sezione" aria-labelledby="forestas-ente-sentieri-titolo">
				<h2 class="forestas-ente__titolo" id="forestas-ente-sentieri-titolo"><?php echo esc_html( forestas_testo( 'Sentieri gestiti', 'Managed trails' ) ); ?></h2>
				<?php echo forestas_dettaglio_griglia( $ente_managed, 'percorso', 'forestas-ente-sentieri' ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già con l'escape ?>
			</section>
		<?php endif; ?>

		<?php if ( $ente_gallery ) : ?>
			<?php // Come la galleria del dettaglio traccia: lightbox del child, le prime tre foto e «Mostra tutti (N)» ?>
			<section class="forestas-ente__sezione" aria-labelledby="forestas-ente-galleria-titolo">
				<h2 class="forestas-ente__titolo" id="forestas-ente-galleria-titolo"><?php echo esc_html( forestas_testo( 'Galleria fotografica', 'Photogallery' ) ); ?></h2>
				<ul class="wm-gal" id="forestas-ente-galleria" data-wm-limite="3" data-slick-lightbox-gallery data-precedente="<?php echo esc_attr( forestas_testo( 'Precedente', 'Previous' ) ); ?>" data-successivo="<?php echo esc_attr( forestas_testo( 'Successiva', 'Next' ) ); ?>" data-chiudi="<?php echo esc_attr( forestas_testo( 'Chiudi', 'Close' ) ); ?>">
					<?php foreach ( $ente_gallery as $ente_i => $ente_photo ) : ?>
						<li><a href="<?php echo esc_url( $ente_photo['url'] ); ?>" class="blazy__slick-lightbox" data-slick-lightbox-trigger data-b-delta="<?php echo (int) $ente_i; ?>"><img src="<?php echo esc_url( $ente_photo['miniatura'] ); ?>" alt="<?php echo esc_attr( $ente_photo['didascalia'] ); ?>" loading="lazy" decoding="async"></a>
							<div class="visually-hidden"><p><?php echo esc_html( $ente_photo['didascalia'] ); ?></p><em class="auth"><?php echo esc_html( $ente_photo['autore'] ); ?></em><em class="cred"><?php echo esc_html( $ente_photo['credits'] ); ?></em></div></li>
					<?php endforeach; ?>
				</ul>
				<?php echo forestas_dettaglio_mostra_tutti( 'forestas-ente-galleria', count( $ente_gallery ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già con l'escape ?>
			</section>
		<?php endif; ?>

		<?php if ( null !== $ente_view['updated'] || null !== $ente_view['created'] ) : ?>
			<?php // Informazioni redazionali come su Drupal: titolo solo per lo screen reader, le due date con la loro etichetta ?>
			<section class="forestas-ente__date" aria-labelledby="forestas-ente-date-titolo">
				<h2 class="screen-reader-text" id="forestas-ente-date-titolo"><?php echo esc_html( forestas_testo( 'Informazioni redazionali', 'Editorial information' ) ); ?></h2>
				<?php
				$ente_dates = [
					// Attributo datetime di <time>: la parte AAAA-MM-GG, come la data mostrata (forestas_dettaglio_data())
					[ forestas_testo( 'Aggiornato il', 'Updated on' ), $ente_view['updated'], forestas_date_iso( $ente_data['updated_at'] ?? null ) ],
					[ forestas_testo( 'Creato il', 'Created on' ), $ente_view['created'], forestas_date_iso( $ente_data['created_at'] ?? null ) ],
				];
				?>
				<?php foreach ( $ente_dates as [ $ente_label, $ente_date, $ente_machine ] ) : ?>
					<?php if ( null !== $ente_date ) : ?>
						<div class="forestas-ente__data">
							<h3 class="forestas-ente__etichetta"><?php echo esc_html( $ente_label ); ?></h3>
							<time datetime="<?php echo esc_attr( (string) $ente_machine ); ?>"><?php echo esc_html( $ente_date ); ?></time>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</section>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
