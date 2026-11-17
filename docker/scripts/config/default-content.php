<?php
/**
 * Contenuti di default dell'installazione di WordPress (oc:8990).
 *
 * «Ciao mondo!» (id 1) esce nella ricerca del sito come una news, la «Pagina di esempio» (id 2) e la
 * bozza «Privacy Policy» (id 3) non servono. Vanno nel cestino con wp_trash_post() e non cancellati
 * (wpf_wpml_cancella_post cancella per sempre): dal cestino si recuperano per 30 giorni.
 * Si toccano solo se esistono ancora, hanno lo slug di default, non sono già nel cestino e non sono mai
 * stati modificati (post_modified_gmt uguale a post_date_gmt). Un secondo giro non fa nulla.
 * L'opzione wp_page_for_privacy_policy si azzera solo se punta alla pagina 3 e quella pagina è di default:
 * la sposta nel cestino questo giro, o è già nel cestino con lo slug di default (anche «privacy-policy__trashed»).
 * Se la pagina 3 è stata modificata o ha un altro slug o tipo, può essere una vera pagina della privacy:
 * l'opzione resta com'è.
 * Contenuti e pagina della privacy si possono passare alla funzione (il test, tests/default-content.php, usa post
 * temporanei); senza, valgono WPF_DEFAULT_CONTENT e WPF_DEFAULT_PRIVACY_ID.
 */

// Contenuti di default dell'installazione di WordPress italiana: id => [ tipo, slug ]
const WPF_DEFAULT_CONTENT = [
	1 => [ 'post', 'ciao-mondo' ],
	2 => [ 'page', 'pagina-di-esempio' ],
	3 => [ 'page', 'privacy-policy' ],
];
// La bozza «Privacy Policy» fra i contenuti di default: l'opzione wp_page_for_privacy_policy punta a lei
const WPF_DEFAULT_PRIVACY_ID = 3;

/**
 * Sposta nel cestino i contenuti di default e azzera la pagina della privacy che puntava alla bozza.
 *
 * @param bool                              $dry_run    Se vero non scrive nulla, segnala soltanto
 * @param callable                          $diff       function ( string $voce, $prima, $dopo ): segnala una differenza
 * @param array<int, array{string, string}> $defaults   Contenuti di default: id => [ tipo, slug ]
 * @param int                               $privacy_id Id della bozza della privacy fra $defaults
 * @return bool vero se tutto è riuscito
 */
function wpf_default_content_apply( bool $dry_run, callable $diff, array $defaults = WPF_DEFAULT_CONTENT, int $privacy_id = WPF_DEFAULT_PRIVACY_ID ): bool {
	$ok       = true;
	$privacy  = false; // la pagina della privacy è quella di default (nel cestino o da mandarci)
	foreach ( $defaults as $id => [ $tipo, $slug ] ) {
		$post = get_post( $id );
		if ( $post && $id === $privacy_id && $post->post_type === $tipo && $post->post_status === 'trash'
			&& in_array( $post->post_name, [ $slug, "{$slug}__trashed" ], true ) ) {
			$privacy = true; // già nel cestino con lo slug di default
		}
		if ( ! $post || $post->post_type !== $tipo || $post->post_name !== $slug || $post->post_status === 'trash' ) {
			continue;
		}
		if ( $post->post_modified_gmt !== $post->post_date_gmt ) {
			continue; // modificato dopo l'installazione: non si tocca
		}
		$diff( "default: {$tipo} {$id} {$slug}", 'presente', 'nel cestino' );
		$privacy = $privacy || $id === $privacy_id;
		if ( ! $dry_run && ! wp_trash_post( $id ) ) {
			WP_CLI::warning( "default: {$tipo} {$id} {$slug} non spostato nel cestino" );
			$ok = false;
		}
	}
	if ( $privacy && (int) get_option( 'wp_page_for_privacy_policy' ) === $privacy_id ) {
		$diff( 'default: opzione wp_page_for_privacy_policy', $privacy_id, 0 );
		if ( ! $dry_run ) {
			update_option( 'wp_page_for_privacy_policy', 0 );
		}
	}
	return $ok;
}
