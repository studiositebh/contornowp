<?php
/**
 * Template UNICO de /ctn/{slug}.
 *
 * Castelo, Buritis e qualquer CTN futura usam ESTE arquivo. Nenhuma
 * implementacao independente por CTN.
 *
 * Sem header institucional: a pagina comeca no Hero dark com o logo CTN.
 *
 * Ordem fiel a CTNPage do React:
 *   Hero > PUV > Sobre (video vertical) > Galeria > Marcas >
 *   Ronnie + Playlist > CTA intermediario > Localizacao > Planos (dark) >
 *   CTA final > Aulas coletivas (quando habilitada)
 *
 * @package Contorno
 */

declare( strict_types = 1 );

get_header();

while ( have_posts() ) :
	the_post();

	$contorno_ctn_id = (int) get_the_ID();
	?>
	<div class="ctn-page" id="post-<?php the_ID(); ?>">
		<?php
		contorno_component( 'ctn_hero' );

		contorno_editorial_slot( 'after_hero', $contorno_ctn_id );

		contorno_component( 'ctn_puv' );
		contorno_component( 'ctn_about' );

		contorno_component(
			'contorno_gallery',
			array(
				'eyebrow' => __( 'Estrutura', 'contorno' ),
				'title'   => __( 'Galeria', 'contorno' ),
				'columns' => 4,
				'tone'    => 'ctn',
			)
		);

		contorno_component( 'ctn_brands' );
		contorno_component( 'ctn_equipment' );
		contorno_component( 'ctn_videos' );

		contorno_component(
			'contorno_location',
			array(
				'eyebrow' => __( 'Onde estamos', 'contorno' ),
				'title'   => __( 'Localizacao', 'contorno' ),
				'tone'    => 'ctn',
			)
		);

		contorno_editorial_slot( 'before_plans', $contorno_ctn_id );

		contorno_component(
			'contorno_plans',
			array(
				'eyebrow'   => __( 'Planos', 'contorno' ),
				'title'     => __( 'Escolha o seu plano', 'contorno' ),
				'skin'      => 'dark',
				'cta_label' => __( 'Assinar plano', 'contorno' ),
				'note'      => __( 'Pagamento seguro pelo sistema oficial da Contorno.', 'contorno' ),
			)
		);

		contorno_editorial_slot( 'after_plans', $contorno_ctn_id );

		contorno_component(
			'contorno_cta',
			array(
				'headline'  => contorno_field_text( 'final_cta_headline', $contorno_ctn_id, CONTORNO_CTN_TAGLINE ),
				'cta_label' => __( 'Quero treinar aqui', 'contorno' ),
				'cta_url'   => '#planos',
				'image'     => contorno_field_text( 'final_cta_image', $contorno_ctn_id ),
				'tone'      => 'ctn',
			)
		);

		contorno_editorial_slot( 'before_footer', $contorno_ctn_id );

		contorno_component( 'contorno_classes' );
		?>
	</div>
	<?php
endwhile;

get_footer();
