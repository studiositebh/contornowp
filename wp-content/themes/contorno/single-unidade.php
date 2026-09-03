<?php
/**
 * Template UNICO de /unidades/{slug}.
 *
 * Um so arquivo renderiza TODAS as academias. Os dados vem dos campos da
 * unidade; nada de conteudo hardcoded por academia.
 *
 * Estrutura obrigatoria (fiel a UnitPage do React):
 *   Hero > Faixa informativa > Destaques Contorno > Diferenciais >
 *   [conteudo editorial opcional] > Planos > Video > Galeria > Localizacao >
 *   CTA final > AULAS COLETIVAS (ultima secao antes do footer)
 *
 * O editor pode inserir conteudo proprio da unidade escolhendo a posicao em
 * "Conteudo editorial extra" — sem quebrar a estrutura acima.
 *
 * @package Contorno
 */

declare( strict_types = 1 );

get_header();

while ( have_posts() ) :
	the_post();

	$contorno_unit_id = (int) get_the_ID();
	?>
	<div class="unit-page" id="post-<?php the_ID(); ?>">
		<?php
		contorno_component( 'contorno_unit_hero' );
		contorno_component( 'contorno_unit_info' );

		contorno_editorial_slot( 'after_hero', $contorno_unit_id );

		contorno_component(
			'contorno_unit_highlights',
			array(
				'title'   => __( 'Destaques Contorno', 'contorno' ),
				'columns' => 4,
			)
		);

		contorno_component(
			'contorno_unit_differentials',
			array(
				'eyebrow' => __( 'Por que a Contorno', 'contorno' ),
				'title'   => __( 'Diferenciais desta unidade', 'contorno' ),
				'tone'    => 'dark',
			)
		);

		contorno_editorial_slot( 'before_plans', $contorno_unit_id );

		contorno_component(
			'contorno_plans',
			array(
				'eyebrow'   => __( 'Planos', 'contorno' ),
				'title'     => __( 'Escolha o seu plano', 'contorno' ),
				'skin'      => 'light',
				'cta_label' => __( 'Matricule-se', 'contorno' ),
				'note'      => __( 'Pagamento seguro pelo sistema oficial da Contorno.', 'contorno' ),
			)
		);

		contorno_editorial_slot( 'after_plans', $contorno_unit_id );

		contorno_component(
			'contorno_unit_video',
			array( 'title' => __( 'Conheca a unidade', 'contorno' ) )
		);

		contorno_component(
			'contorno_gallery',
			array(
				'eyebrow' => __( 'Estrutura', 'contorno' ),
				'title'   => __( 'Galeria', 'contorno' ),
				'columns' => 4,
			)
		);

		contorno_component(
			'contorno_location',
			array(
				'eyebrow' => __( 'Onde estamos', 'contorno' ),
				'title'   => __( 'Localizacao', 'contorno' ),
			)
		);

		contorno_editorial_slot( 'before_footer', $contorno_unit_id );

		contorno_component(
			'contorno_cta',
			array(
				'headline'  => sprintf( /* translators: %s: unit name */ __( 'Comece hoje na %s', 'contorno' ), get_the_title() ),
				'cta_label' => __( 'Matricule-se', 'contorno' ),
				'cta_url'   => contorno_enrollment_url(),
			)
		);

		/*
		 * Aulas coletivas: SEMPRE a ultima secao de conteudo antes do footer.
		 * Nao mover. Some sozinha quando a unidade nao tem grade habilitada.
		 */
		contorno_component( 'contorno_classes' );
		?>
	</div>
	<?php
endwhile;

get_footer();
