<?php
/**
 * Pagina `/avvisi/` (oc:9000), servita da forestas_notices_template() con stato 200 o 503 (nessuna copia degli avvisi).
 * Il markup è in forestas_notices_html() (inc/notices.php), così si prova senza il tema.
 *
 * @package forestas-child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$notices_page = forestas_notices_page();
if ( null === $notices_page ) {
	return;
}

// Stile dopo lo style.css del child (accodato a PHP_INT_MAX), come /enti
add_action(
	'wp_enqueue_scripts',
	fn() => wp_enqueue_style( 'forestas-notices', get_stylesheet_directory_uri() . '/assets/css/notices.css', [], forestas_versione() ),
	PHP_INT_MAX
);

get_header();
?>
<main id="page-content" class="l-main forestas-notices forestas-sezione-base">
	<?php echo forestas_notices_html( $notices_page ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup di forestas_notices_html(), già con l'escape ?>
</main>
<?php
get_footer();
