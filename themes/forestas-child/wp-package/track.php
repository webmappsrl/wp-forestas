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
		<div class="wm-avviso" role="note">
			<span class="wm-avviso__tag"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 3 2 21h20L12 3z" fill="none" stroke="#fff" stroke-width="2.2" stroke-linejoin="round"/><path d="M12 10v5" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="18" r="1.3" fill="#fff"/></svg><?php echo esc_html( forestas_testo( 'Avviso', 'Notice' ) ); ?></span>
			<span class="wm-segnaposto"><?php echo esc_html( $non_disp ); ?></span>
		</div>
	<?php endif; ?>

	<header class="wm-hero<?php echo '' === $foto ? ' wm-hero--senza-foto' : ''; ?>" data-wm-hero>
		<?php if ( '' !== $foto ) : ?>
			<?php // Testo alternativo dalla didascalia della foto, se c'è (specifica di oc:8783, «Testata con foto»); senza, è decorativa ?>
			<img class="wm-hero__img" src="<?php echo esc_url( $foto ); ?>" alt="<?php echo esc_attr( wp_strip_all_tags( forestas_dettaglio_testo( $p['feature_image']['caption'] ?? null ) ) ); ?>" fetchpriority="high">
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
				<?php
				// Il cammino porta il suo nome al posto delle aree (Tavola 5), con la lingua vera se ripiega
				$cammino = forestas_dettaglio_nome_cammino( $p, $tipo );
				?>
				<?php if ( '' !== $cammino['testo'] ) : ?>
					<span<?php echo null !== $cammino['lingua'] && forestas_dettaglio_lingua() !== $cammino['lingua'] ? ' lang="' . esc_attr( $cammino['lingua'] ) . '"' : ''; ?>><?php echo esc_html( $cammino['testo'] ); ?></span>
				<?php elseif ( $luoghi['aree'] ) : ?>
					<span><?php echo esc_html( ( 1 === count( $luoghi['aree'] ) ? forestas_testo( 'Area geografica', 'Geographic area' ) : forestas_testo( 'Aree geografiche', 'Geographic areas' ) ) . ' ' . implode( ', ', $luoghi['aree'] ) ); ?></span>
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

		<?php
		$livello       = $indicatori['validazione'] ? forestas_dettaglio_livello_validazione( $indicatori['validazione']['chiave'] ) : 0;
		$accessibilita = $indicatori['accessibilita'];
		$spt           = forestas_dettaglio_inclusivita( $p );
		$approfondisci = forestas_dettaglio_link_approfondimento();
		$su            = forestas_testo( 'su', 'of' );
		// Legenda dietro la «i»: titolo, livelli e il link all'articolo di approfondimento (wireframe, nota 12)
		$legenda = function ( string $id, string $titolo_legenda, array $livelli, string $nota = '' ) use ( $approfondisci ): string {
			$html = '<div class="wm-ind__leg" id="' . esc_attr( $id ) . '" hidden><p class="wm-ind__lt">' . esc_html( $titolo_legenda ) . '</p><ol>';
			foreach ( $livelli as $chiave => $nome ) {
				$html .= '<li><b>' . esc_html( (string) $chiave ) . '</b> ' . esc_html( $nome ) . '</li>';
			}
			$html .= '</ol>' . ( '' !== $nota ? '<p class="wm-ind__nota">' . esc_html( $nota ) . '</p>' : '' );
			if ( '' !== $approfondisci ) {
				$html .= '<a href="' . esc_url( $approfondisci ) . '">' . esc_html( forestas_testo( "Leggi l'articolo di approfondimento", 'Read the in-depth article' ) ) . '</a>';
			}
			return $html . '</div>';
		};
		$pulsante = function ( string $id, string $etichetta ): string {
			return '<button type="button" class="wm-ind__info" aria-expanded="false" aria-controls="' . esc_attr( $id ) . '" aria-label="' . esc_attr( $etichetta ) . '">i</button>';
		};
		?>
		<div class="wm-ind<?php echo $spt ? ' wm-ind--spt' : ''; ?>">
			<div class="wm-ind__i wm-ind__i--val">
				<div class="wm-ind__k"><?php echo esc_html( forestas_testo( 'Validazione catastale', 'Trail register validation' ) ); ?> <?php echo $pulsante( 'wm-leg-val', forestas_testo( 'Legenda: livelli di validazione catastale', 'Legend: validation levels' ) ); // phpcs:ignore ?></div>
				<span class="wm-tac" role="img" aria-label="<?php echo esc_attr( $indicatori['validazione'] ? sprintf( forestas_testo( 'Validazione catastale: livello %1$d su %2$d', 'Validation: level %1$d of %2$d' ), $livello, 6 ) : forestas_testo( 'Validazione catastale: ', 'Validation: ' ) . $non_disp ); ?>"><?php echo $tacche( $livello, 6 ); // phpcs:ignore ?></span>
				<div class="wm-ind__v<?php echo $indicatori['validazione'] ? '' : ' wm-segnaposto'; ?>"><?php echo esc_html( $indicatori['validazione'] ? $livello . ' ' . $su . ' 6 · ' . $indicatori['validazione']['etichetta'] : $non_disp ); ?></div>
				<?php
				$ordine = [];
				foreach ( forestas_search_term_labels() as $chiave => $etichetta ) {
					if ( 0 === strpos( $chiave, 'validation-status-' ) ) {
						$ordine[ forestas_dettaglio_livello_validazione( $chiave ) ] = $etichetta;
					}
				}
				ksort( $ordine );
				echo $legenda( 'wm-leg-val', forestas_testo( 'Validazione catastale, da 0 a 6', 'Trail register validation, from 0 to 6' ), $ordine ); // phpcs:ignore
				?>
			</div>
			<div class="wm-ind__i wm-ind__i--per">
				<div class="wm-ind__k"><?php echo esc_html( forestas_testo( 'Percorribilità', 'Walkability' ) ); ?> <?php echo $pulsante( 'wm-leg-per', forestas_testo( 'Legenda: livelli di percorribilità', 'Legend: walkability levels' ) ); // phpcs:ignore ?></div>
				<span class="wm-tac" role="img" aria-label="<?php echo esc_attr( forestas_testo( 'Percorribilità: ', 'Walkability: ' ) . $non_disp ); ?>"><?php echo $tacche( 0, 5 ); // phpcs:ignore ?></span>
				<div class="wm-ind__v wm-segnaposto"><?php echo esc_html( $non_disp ); ?></div>
				<?php
				echo $legenda( // phpcs:ignore
					'wm-leg-per',
					forestas_testo( 'Percorribilità, da 0 a 5', 'Walkability, from 0 to 5' ),
					[
						'0'   => forestas_testo( 'Sconosciuta o non verificata', 'Unknown or not verified' ),
						'1'   => forestas_testo( 'Non percorribile', 'Not walkable' ),
						'2–3' => forestas_testo( 'In revisione o semplicemente percorribile', 'Under review or simply walkable' ),
						'4'   => forestas_testo( 'Percorribile', 'Walkable' ),
						'5'   => forestas_testo( 'Controllato con roadbook e punti tappa', 'Checked with roadbook and stage points' ),
					]
				);
				?>
			</div>
			<div class="wm-ind__i wm-ind__i--acc">
				<div class="wm-ind__k"><?php echo esc_html( forestas_testo( 'Accessibilità', 'Accessibility' ) ); ?> <?php echo $pulsante( 'wm-leg-acc', forestas_testo( 'Legenda: gradi di accessibilità', 'Legend: accessibility grades' ) ); // phpcs:ignore ?></div>
				<span class="wm-tac" role="img" aria-label="<?php echo esc_attr( $accessibilita['grado'] ? forestas_testo( 'Accessibilità: grado ', 'Accessibility: grade ' ) . $accessibilita['grado'] : forestas_testo( 'Accessibilità: non classificato', 'Accessibility: not classified' ) ); ?>"><?php echo $tacche( $accessibilita['livello'], 3 ); // phpcs:ignore ?></span>
				<div class="wm-tac-l" aria-hidden="true"><span>AT</span><span>AE</span><span>AEE</span></div>
				<div class="wm-ind__v<?php echo $accessibilita['grado'] ? '' : ' wm-segnaposto'; ?>"><?php echo esc_html( $accessibilita['grado'] ? $accessibilita['grado'] . ' · ' . $accessibilita['nome'] : forestas_testo( 'Non classificato', 'Not classified' ) ); ?></div>
				<ul class="wm-acc-tip" aria-label="<?php echo esc_attr( forestas_testo( 'Tipologie di disabilità', 'Types of disability' ) ); ?>">
					<?php
					$icone = [
						'motoria'    => [ forestas_testo( 'Motoria', 'Motor' ), '<circle cx="11" cy="3.8" r="1.7"/><path d="M11 7v6h5l2.5 5.5M11 10h4.5"/><path d="M8 11.2a5.2 5.2 0 1 0 7.4 6.4"/>' ],
						'sensoriale' => [ forestas_testo( 'sensoriale', 'sensory' ), '<path d="M3 14s3.5-5 9-5 9 5 9 5-3.5 5-9 5-9-5-9-5z"/><circle cx="12" cy="14" r="2.4"/><path d="M12 3.5V6M6.6 5.2l1.4 2.1M17.4 5.2 16 7.3"/>' ],
						'cognitiva'  => [ forestas_testo( 'psichica cognitiva', 'cognitive' ), '<path d="M12 5.5v13M12 5.5a3 3 0 0 0-5.6 1.2 3 3 0 0 0-1.9 5.1 3 3 0 0 0 1.4 4.5A3 3 0 0 0 12 18.5M12 5.5a3 3 0 0 1 5.6 1.2 3 3 0 0 1 1.9 5.1 3 3 0 0 1-1.4 4.5A3 3 0 0 1 12 18.5"/>' ],
					];
					foreach ( $icone as $chiave => [ $nome_tipo, $disegno ] ) {
						$accesa = $accessibilita['disabilita'][ $chiave ];
						// L'icona del set dello shard, se c'è; altrimenti il disegno a tratto del wireframe
						$dal_set = function_exists( 'wmwp_icon_svg' ) ? wmwp_icon_svg( $accessibilita['icone'][ $chiave ] ) : '';
						printf(
							'<li%1$s><span class="wm-acc-tip__ab">%2$s</span>%3$s<span class="screen-reader-text">: %4$s</span></li>',
							$accesa ? ' class="on"' : '',
							esc_html( $nome_tipo ),
							'' !== $dal_set ? $dal_set : '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $disegno . '</svg>', // phpcs:ignore WordPress.Security.EscapeOutput -- SVG del plugin (path escapati) o disegno fisso
							esc_html( $accesa ? forestas_testo( 'sì', 'yes' ) : forestas_testo( 'non certificato', 'not certified' ) )
						);
					}
					?>
				</ul>
				<?php
				$gradi = [];
				foreach ( forestas_dettaglio_gradi_accessibilita() as $sigla => $grado ) {
					$gradi[ $sigla ] = $grado['nome'];
				}
				$gradi['★ – ★★★'] = forestas_testo( 'dotazioni in più: bagni accessibili, Joelette, visite guidate, informazioni accessibili', 'extra facilities: accessible toilets, Joëlette, guided tours, accessible information' );
				echo $legenda( // phpcs:ignore
					'wm-leg-acc',
					forestas_testo( 'Accessibilità dei sentieri (Allegato H)', 'Trail accessibility (Annex H)' ),
					$gradi,
					forestas_testo( 'Il grado è uno solo per sentiero. Le icone dicono per quali disabilità è accessibile — motoria, sensoriale (anche sordità), psichica cognitiva: verde se sì, grigia e tratteggiata se no.', 'There is one grade per trail. The icons show which disabilities it is accessible for — motor, sensory (including deafness), cognitive: green if yes, grey and dashed if not.' )
				);
				?>
			</div>
		</div>
		<?php if ( $spt ) : ?>
			<?php // «Sentiero per tutti»: unito alla colonna Accessibilità a forma di L rovesciata (wireframe, nota 11) ?>
			<section class="wm-spt" aria-labelledby="wm-spt-h">
				<h2 class="wm-spt__h" id="wm-spt-h"><?php echo esc_html( forestas_testo( 'Sentiero per tutti', 'Trail for everyone' ) ); ?></h2>
				<div class="wm-spt__c">
					<div class="wm-spt__badge" aria-hidden="true"><?php echo esc_html( $spt['classe'] ); ?></div>
					<div>
						<div class="wm-ind__k"><?php echo esc_html( forestas_testo( 'Punteggio di inclusività', 'Inclusivity score' ) ); ?> <?php echo $pulsante( 'wm-leg-spt', forestas_testo( 'Legenda: classi del punteggio di inclusività', 'Legend: inclusivity score classes' ) ); // phpcs:ignore ?></div>
						<span class="wm-tac" role="img" aria-label="<?php echo esc_attr( sprintf( forestas_testo( 'Punteggio di inclusività, classe %1$s: %2$d su 6', 'Inclusivity score, class %1$s: %2$d of 6' ), $spt['classe'], $spt['tacche'] ) ); ?>"><?php echo $tacche( $spt['tacche'], 6 ); // phpcs:ignore ?></span>
						<div class="wm-ind__v"><?php echo esc_html( $spt['classe'] . ' · ' . $spt['nome'] ); ?></div>
						<?php if ( '' !== $spt['frase'] ) : ?>
							<div class="wm-ind__n"><?php echo esc_html( $spt['frase'] ); ?></div>
						<?php endif; ?>
					</div>
					<?php
					$classi_legenda = [];
					foreach ( forestas_dettaglio_classi_inclusivita() as $sigla => $classe ) {
						$classi_legenda[ $sigla ] = $classe['nome'] . ' · ' . $classe['intervallo'];
					}
					echo $legenda( 'wm-leg-spt', forestas_testo( 'Punteggio di inclusività: le sei classi (Allegato H)', 'Inclusivity score: the six classes (Annex H)' ), $classi_legenda ); // phpcs:ignore
					?>
				</div>
				<table class="wm-spt__tab">
					<caption class="screen-reader-text"><?php echo esc_html( forestas_testo( 'Dettaglio del punteggio di inclusività', 'Inclusivity score breakdown' ) ); ?></caption>
					<tbody>
						<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Fruibilità in autonomia', 'Independent use' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( $spt['autonomia'] ) ); ?></td></tr>
						<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Disponibilità di assistenza', 'Assistance available' ) ); ?></th><td><?php echo esc_html( forestas_dettaglio_valore( $spt['assistenza'] ) ); ?></td></tr>
						<?php foreach ( $spt['punteggi'] as $punteggio ) : ?>
							<tr><th scope="row"><?php echo esc_html( $punteggio['etichetta'] ); ?></th><td><span class="wm-tac" role="img" aria-label="<?php echo esc_attr( $punteggio['etichetta'] . ': ' . $punteggio['tacche'] . ' ' . $su . ' 6' ); ?>"><?php echo $tacche( $punteggio['tacche'], 6 ); // phpcs:ignore ?></span><span class="wm-spt__tv"><?php echo esc_html( $punteggio['tacche'] . ' ' . $su . ' 6' ); ?></span></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
		<?php endif; ?>
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
				// Testi ricchi: titoli rinumerati sotto quello del blocco e lingua vera se ripiegano (forestas_dettaglio_ricco)
				$descrizione = forestas_dettaglio_ricco( $p['description'] ?? null );
				$info        = forestas_dettaglio_ricco( $forestas['useful_info'] ?? null );
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
				// Ogni tematismo con la sua lingua, se ripiega sull'italiano (WCAG 3.1.2)
				$tematismi = [];
				foreach ( (array) ( $p['taxonomies']['itinerary-type'] ?? [] ) as $termine ) {
					$tematismo = forestas_dettaglio_testo_lingua( $termine['name'] ?? null );
					if ( '' !== $tematismo['testo'] ) {
						$tematismi[] = '<span' . forestas_dettaglio_lang( $tematismo['lingua'] ) . '>' . esc_html( $tematismo['testo'] ) . '</span>';
					}
				}
				$gestori = array_values( array_filter( $enti, fn( $ente ) => 'gestore' === $ente['ruolo'] ) );
				?>
				<section class="wm-sec" aria-labelledby="wm-sintesi">
					<?php echo $titolo( forestas_testo( 'Il cammino, in sintesi', 'Key facts about the walk' ), 'wm-sintesi' ); // phpcs:ignore ?>
					<table class="wm-dt">
						<tbody>
							<tr><th scope="row"><?php echo esc_html( forestas_testo( 'Tematismo', 'Theme' ) ); ?></th><td><?php echo $tematismi ? implode( ', ', $tematismi ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?></td></tr>
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
							<h3 class="wm-rb__h"><button type="button" class="wm-rb__btn" aria-expanded="<?php echo 0 === $i ? 'true' : 'false'; ?>" aria-controls="wm-rb-<?php echo (int) $i; ?>"><?php
							$titolo_tappa = '' !== $tappa['titolo'] ? $tappa['titolo'] : sprintf( forestas_testo( 'Tappa %d', 'Stage %d' ), $i + 1 );
							// Titolo in un'altra lingua (ripiego): con la sua lingua per lo screen reader (WCAG 3.1.2)
							echo '' !== $tappa['titolo'] && null !== $tappa['lingua'] && $tappa['lingua'] !== forestas_dettaglio_lingua() ? '<span lang="' . esc_attr( $tappa['lingua'] ) . '">' . esc_html( $titolo_tappa ) . '</span>' : esc_html( $titolo_tappa );
							?></button></h3>
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
								<?php foreach ( $difficolta as [ $attivita, $grado, $lingua_grado ] ) : ?>
									<tr><td><?php echo esc_html( $attivita ); ?></td><td><span class="wm-pill"<?php echo forestas_dettaglio_lang( $lingua_grado ); // phpcs:ignore ?>><?php echo esc_html( $grado ); ?></span></td></tr>
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

			<?php $allegati = forestas_dettaglio_allegati_html( forestas_dettaglio_allegati( $forestas ) ); ?>
			<?php if ( '' !== $allegati ) : ?>
				<section class="wm-sec" aria-labelledby="wm-allegati">
					<?php echo $titolo( forestas_testo( 'Allegati', 'Attachments' ), 'wm-allegati' ); // phpcs:ignore ?>
					<?php echo $allegati; // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
				</section>
			<?php endif; ?>

			<section class="wm-sec" aria-labelledby="wm-dati">
				<?php echo $titolo( [ 'sentiero' => forestas_testo( 'Dati catastali e altimetrici', 'Register and elevation data' ), 'itinerario' => forestas_testo( "Dati dell'itinerario", 'Itinerary data' ), 'cammino' => forestas_testo( 'Dati del cammino', 'Walk data' ) ][ $tipo ] ?? forestas_testo( 'Dati del percorso', 'Route data' ), 'wm-dati' ); // phpcs:ignore ?>
				<div class="wm-dt-wrap">
					<?php
					// Gruppi chiusi, da aprire uno per uno; gli enti restano aperti se ce n'è uno solo (Rubens, 13/11)
					foreach ( forestas_dettaglio_tabelle( $p, $tipo ) as $n => $tabella ) {
						echo forestas_dettaglio_gruppo( 'wm-gr-' . (int) $n, $tabella['titolo'], $tabella['righe'], false ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato
					}
					$righe_enti = array_map( fn( $ente ) => [ $ente['etichetta'], $ente['nome'] ?? $non_disp, null === $ente['nome'] ], $enti );
					echo forestas_dettaglio_gruppo( 'wm-gr-enti', forestas_testo( 'Enti', 'Organisations' ), $righe_enti, 1 === count( $enti ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato
					?>
				</div>
			</section>

			<?php if ( $correlati['poi'] ) : ?>
				<section class="wm-sec" aria-labelledby="wm-poi">
					<?php echo $titolo( forestas_testo( 'Punti di interesse correlati', 'Related points of interest' ), 'wm-poi' ); // phpcs:ignore ?>
					<?php echo forestas_dettaglio_griglia( $correlati['poi'], 'poi', 'wm-correlati-poi' ); // phpcs:ignore ?>
				</section>
			<?php endif; ?>
			<?php if ( $correlati['percorsi'] ) : ?>
				<section class="wm-sec" aria-labelledby="wm-correlati">
					<?php // «Percorsi correlati» per tutti i tipi: fra i correlati di un sentiero ci sono anche itinerari e cammini (Rubens, 13/11) ?>
					<?php echo $titolo( forestas_testo( 'Percorsi correlati', 'Related routes' ), 'wm-correlati' ); // phpcs:ignore ?>
					<?php echo forestas_dettaglio_griglia( $correlati['percorsi'], 'percorso', 'wm-correlati-percorsi' ); // phpcs:ignore ?>
				</section>
			<?php endif; ?>
		</div>

		<?php // Senza nome: lo porta già la mappa che contiene («Mappa del percorso»), che lo screen reader annuncerebbe due volte ?>
		<aside class="wm-aside">
			<?php echo forestas_dettaglio_mappa_html( forestas_dettaglio_mappa( $p, $tipo, (int) $detail['id'], (array) ( $detail['feature']['geometry'] ?? [] ) ), forestas_testo( 'Mappa del percorso', 'Route map' ), 460, 'track', (int) $detail['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- markup già escapato ?>
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
