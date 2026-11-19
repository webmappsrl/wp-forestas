<?php
/**
 * Elenco degli enti (oc:8990): `/enti/`, `/enti/page/<n>/` e `/en/enti/…`, servito da forestas_entes_template() con
 * stato 200. Contenuti e ordine della view `enti` di Drupal (titolo «Enti, istituzioni e società», 9 card per pagina con
 * immagine, categoria e nome, paginazione); lo stile è quello delle pagine d'elenco del child (/tutte-le-news/):
 * intestazione con il filo, percorso, griglia di card `forestas-card` con i titoli h2 sotto l'h1, paginazione di
 * WordPress. L'etichetta della card è la categoria della teaser di Drupal (forestas_entes_type_category()), non il tipo.
 * A sinistra il filtro «Argomento», la faccetta per tipo della pagina di Drupal, con la colonna dei filtri delle liste
 * di news ed eventi (forestas_filtri_colonna(), assets/js/filtri.js): chiuso all'apertura come su Drupal, aperto con un
 * tipo scelto; sotto 960px dietro il pulsante «Filtri». Il tipo scelto sta nell'indirizzo (`?argomento=<tipo>`) e la
 * paginazione lo tiene.
 * Dati: forestas_entes_page() (`page`, `pages`, `type`, `lang`), forestas_entes_list() e forestas_entes_type_facet(),
 * 9 enti per pagina (FORESTAS_ENTES_PER_PAGE).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$entes_page = forestas_entes_page();
if ( null === $entes_page || 'list' !== $entes_page['view'] ) {
	return;
}
$entes_type  = (string) ( $entes_page['type'] ?? '' );
$entes_items = array_slice( forestas_entes_list( $entes_page['lang'], $entes_type ), ( $entes_page['page'] - 1 ) * FORESTAS_ENTES_PER_PAGE, FORESTAS_ENTES_PER_PAGE );
$entes_title = forestas_entes_list_title();
// Filtro «Argomento»: un gruppo solo, senza la colonna se non ci sono voci (copia senza enti)
$entes_facet  = forestas_entes_type_facet( $entes_type );
$entes_filter = forestas_filtri_colonna(
	[
		[
			'campo'  => FORESTAS_ENTES_TYPE_PARAM,
			'titolo' => [
				'it' => 'Argomento',
				'en' => 'Topic',
			],
		],
	],
	[ FORESTAS_ENTES_TYPE_PARAM => $entes_facet ],
	[ FORESTAS_ENTES_TYPE_PARAM => isset( $entes_facet[ $entes_type ] ) ? $entes_type : '' ]
);

// Stile delle pagine degli enti dopo lo style.css del child (accodato da UpSolution Core, versione del child a
// PHP_INT_MAX in functions.php), così a parità di selettore vince questo
add_action(
	'wp_enqueue_scripts',
	fn() => wp_enqueue_style( 'forestas-entes', get_stylesheet_directory_uri() . '/assets/css/entes.css', [], forestas_versione() ),
	PHP_INT_MAX
);
// Apre e chiude il gruppo e la colonna dei filtri, come nelle liste di news ed eventi (forestas_griglia())
if ( '' !== $entes_filter ) {
	wp_enqueue_script( 'forestas-filtri', get_stylesheet_directory_uri() . '/assets/js/filtri.js', [], forestas_versione(), [ 'in_footer' => true, 'strategy' => 'defer' ] );
}

get_header();
?>
<main id="page-content" class="l-main forestas-entes forestas-sezione-base">
	<div class="forestas-intestazione forestas-sezione-base"><h1 class="forestas-intestazione-titolo"><?php echo esc_html( $entes_title ); ?></h1></div>
	<nav class="forestas-percorso" aria-label="<?php echo esc_attr( forestas_testo( 'Briciole di pane', 'Breadcrumb' ) ); ?>"><a href="<?php echo esc_url( apply_filters( 'wpml_home_url', home_url( '/' ) ) ); ?>">Home</a><span aria-hidden="true">›</span><span aria-current="page"><?php echo esc_html( $entes_title ); ?></span></nav>
	<div class="forestas-elenco">
		<?php if ( '' !== $entes_filter ) : ?>
			<?php // Colonna dei filtri a sinistra e lista, come la griglia 3-9 della pagina di Drupal ?>
			<div class="forestas-filtrabile">
			<?php echo $entes_filter; // phpcs:ignore WordPress.Security.EscapeOutput -- markup di forestas_filtri_colonna(), già con l'escape ?>
			<div class="forestas-filtrabile__lista">
			<?php
			// «Filtri attivi», come nelle liste di news ed eventi (forestas_filtri_attivi_html())
			$entes_label = '' !== $entes_type ? forestas_entes_type_label( $entes_type ) : null;
			echo forestas_filtri_attivi_html( // phpcs:ignore WordPress.Security.EscapeOutput -- markup già con l'escape
				null !== $entes_label && isset( $entes_facet[ $entes_type ] ) ? [ [ 'gruppo' => forestas_testo( 'Argomento', 'Topic' ), 'nome' => $entes_label, 'url' => forestas_filtri_togli_url( [ FORESTAS_ENTES_TYPE_PARAM ] ) ] ] : [],
				forestas_filtri_togli_url( [ FORESTAS_ENTES_TYPE_PARAM ] )
			);
			?>
		<?php endif; ?>
		<?php if ( $entes_items ) : ?>
			<ul class="forestas-griglia forestas-griglia--immagine forestas-entes__griglia" role="list" data-test="entes-list" data-page="<?php echo (int) $entes_page['page']; ?>" data-pages="<?php echo (int) $entes_page['pages']; ?>">
				<?php foreach ( $entes_items as $entes_item ) : ?>
					<?php // Un solo link, sul nome: tutta la card si apre da lì (forestas-card); l'immagine è decorativa ?>
					<li class="forestas-card forestas-card--immagine forestas-card--ente">
						<?php if ( null !== $entes_item['image'] ) : ?>
							<div class="forestas-card__media"><img src="<?php echo esc_url( $entes_item['image'] ); ?>" alt="" loading="lazy" decoding="async"></div>
						<?php else : ?>
							<div class="forestas-card__media forestas-card__media--segnaposto"></div>
						<?php endif; ?>
						<div class="forestas-card__testo">
							<?php if ( null !== $entes_item['category'] ) : ?>
								<span class="forestas-card__tag"><?php echo esc_html( $entes_item['category'] ); ?></span>
							<?php endif; ?>
							<h2 class="forestas-card__titolo"<?php echo forestas_dettaglio_lang( $entes_item['name_lang'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- attributo già con l'escape ?>><a href="<?php echo esc_url( $entes_item['url'] ); ?>"><?php echo esc_html( $entes_item['name'] ); ?></a></h2>
						</div>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $entes_page['pages'] > 1 ) : ?>
				<nav class="forestas-paginazione" aria-label="<?php echo esc_attr( forestas_testo( 'Paginazione', 'Pagination' ) ); ?>">
					<?php
					// Come le griglie del child (inc/griglie.php): la pagina corrente con aria-current, le frecce con un nome
					echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput -- markup di WordPress, già con l'escape
						[
							'base'      => forestas_entes_list_url( $entes_page['lang'] ) . '%_%',
							'format'    => 'page/%#%/',
							// Le pagine tengono il filtro (le pagine sono quelle dell'elenco filtrato)
							'add_args'  => '' !== $entes_type ? [ FORESTAS_ENTES_TYPE_PARAM => $entes_type ] : [],
							'total'     => $entes_page['pages'],
							'current'   => $entes_page['page'],
							'prev_text' => '<span aria-hidden="true">‹</span><span class="screen-reader-text">' . esc_html( forestas_testo( 'Pagina precedente', 'Previous page' ) ) . '</span>',
							'next_text' => '<span aria-hidden="true">›</span><span class="screen-reader-text">' . esc_html( forestas_testo( 'Pagina successiva', 'Next page' ) ) . '</span>',
						]
					);
					?>
				</nav>
			<?php endif; ?>
		<?php else : ?>
			<p class="forestas-griglia-vuota" data-test="entes-list" data-page="<?php echo (int) $entes_page['page']; ?>" data-pages="<?php echo (int) $entes_page['pages']; ?>"><?php echo esc_html( forestas_testo( 'Non ci sono enti da mostrare.', 'There are no organisations to show.' ) ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $entes_filter ) : ?>
			</div>
			</div>
		<?php endif; ?>
	</div>
</main>
<?php
get_footer();
