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

		/*
		 * Ordem e textos do CTNPage.tsx: PUV (tagline + subtitulo do hero) com os
		 * numeros, "Sobre" (texto + video vertical), galeria, marcas, video,
		 * CTA central, localizacao, planos e CTA final.
		 */
		contorno_component(
			'ctn_puv',
			array(
				'eyebrow' => __( 'Centro de Treinamento Contorno', 'contorno' ),
				'title'   => contorno_field_text( 'tagline', $contorno_ctn_id, CONTORNO_CTN_TAGLINE ),
				'text'    => contorno_field_text( 'hero_subtitle', $contorno_ctn_id ),
			)
		);
		contorno_component( 'ctn_about' );

		contorno_component(
			'contorno_gallery',
			array(
				'eyebrow' => __( 'Estrutura', 'contorno' ),
				'title'   => __( 'Estrutura e equipamentos de ponta', 'contorno' ),
				'text'    => contorno_field_text( 'structure_subtitle', $contorno_ctn_id ),
				'align'   => 'center',
				'columns' => 4,
				'tone'    => 'ctn',
			)
		);

		contorno_component( 'ctn_brands' );

		/*
		 * ctn_equipment NAO entra aqui de proposito.
		 *
		 * A landing aprovada nao mostra a secao de destaques de equipamento —
		 * o proprio React marca o componente como "mantido para reuso futuro"
		 * e nao o renderiza. Manter aqui traria os videos de equipamento para
		 * a landing, contrariando a regra de um unico video principal.
		 *
		 * O elemento continua disponivel no WPBakery: quando o cliente quiser
		 * a secao, insere "CTN — Equipamentos" na area editorial da CTN.
		 */

		contorno_component( 'ctn_videos' );

		// CTA intermediario, entre os videos e a localizacao.
		contorno_component(
			'contorno_cta',
			array(
				'headline'  => __( 'Pronto para treinar em outro nível?', 'contorno' ),
				'cta_label' => sprintf(
					/* translators: %s: CTN name */
					__( 'Quero treinar na %s', 'contorno' ),
					get_the_title()
				),
				'cta_url'   => '#planos',
				'tone'      => 'ctn',
				'align'     => 'center',
			)
		);

		contorno_component(
			'contorno_location',
			array(
				'eyebrow' => __( 'Onde estamos', 'contorno' ),
				'title'   => __( 'Onde estamos', 'contorno' ),
				'tone'    => 'ctn',
			)
		);

		contorno_editorial_slot( 'before_plans', $contorno_ctn_id );

		contorno_component(
			'contorno_plans',
			array(
				'eyebrow'   => __( 'Planos', 'contorno' ),
				'title'     => __( 'Escolha como você quer treinar', 'contorno' ),
				'text'      => sprintf( /* translators: %s: CTN name */ __( 'Planos da %s com benefícios e condições específicas desta unidade.', 'contorno' ), get_the_title() ),
				'align'     => 'center',
				'skin'      => 'dark',
				'cta_label' => __( 'Assinar plano', 'contorno' ),
				'note'      => __( 'Pagamento seguro pelo sistema oficial da Contorno.', 'contorno' ),
			)
		);

		contorno_editorial_slot( 'after_plans', $contorno_ctn_id );

		contorno_component(
			'contorno_cta',
			array(
				'eyebrow'   => get_the_title(),
				'headline'  => contorno_field_text( 'final_cta_headline', $contorno_ctn_id, CONTORNO_CTN_TAGLINE ),
				'cta_label' => __( 'Matricule-se agora', 'contorno' ),
				'cta_url'   => '#planos',
				'image'     => contorno_field_text( 'final_cta_image', $contorno_ctn_id ),
				'tone'      => 'ctn',
				'align'     => 'center',
			)
		);

		contorno_editorial_slot( 'before_footer', $contorno_ctn_id );

		contorno_component( 'contorno_classes' );
		?>
	</div>
	<?php
endwhile;

get_footer();
