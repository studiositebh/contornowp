<?php
/**
 * Integracao com o WPBakery Page Builder (js_composer 8.x).
 *
 * NUNCA editar arquivos de plugins/js_composer/. Toda a integracao vive aqui.
 *
 * O que este arquivo faz:
 *  1. Habilita o builder nos CPTs unidade e ctn (alem de paginas e posts).
 *  2. Registra os elementos "CONTORNO — ..." e "CTN — ..." no seletor, cada um
 *     com campos amigaveis (Eyebrow, Titulo, Imagem, CTA...) — nunca um
 *     textarea gigante de HTML.
 *  3. Marca como CONTROLADOS os componentes funcionais: o editor configura
 *     propriedades, mas nao manipula o HTML interno.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function contorno_wpbakery_active(): bool {
	return defined( 'WPB_VC_VERSION' );
}

/**
 * Habilita o builder onde ele faz sentido.
 */
add_action(
	'vc_before_init',
	static function (): void {
		if ( ! function_exists( 'vc_editor_set_post_types' ) ) {
			return;
		}

		vc_editor_set_post_types(
			array(
				'page',
				'post',
				CONTORNO_CPT_UNIT,
				CONTORNO_CPT_CTN,
			)
		);
	}
);

/**
 * Categorias proprias no seletor de elementos.
 */
const CONTORNO_VC_CATEGORY     = 'CONTORNO';
const CONTORNO_VC_CATEGORY_CTN = 'CTN';

/**
 * Atalhos de definicao de parametro, para os mapeamentos ficarem legiveis.
 *
 * @return array<string,mixed>
 */
function contorno_vc_text( string $param, string $heading, string $group = '', string $description = '' ): array {
	return array_filter(
		array(
			'type'        => 'textfield',
			'param_name'  => $param,
			'heading'     => $heading,
			'group'       => $group,
			'description' => $description,
		)
	);
}

/**
 * @return array<string,mixed>
 */
function contorno_vc_textarea( string $param, string $heading, string $group = '', string $description = '' ): array {
	return array_filter(
		array(
			'type'        => 'textarea',
			'param_name'  => $param,
			'heading'     => $heading,
			'group'       => $group,
			'description' => $description,
		)
	);
}

/**
 * Campo de imagem com o seletor da Biblioteca de Midia.
 *
 * @return array<string,mixed>
 */
function contorno_vc_image( string $param, string $heading, string $group = '', string $description = '' ): array {
	return array_filter(
		array(
			'type'        => 'attach_image',
			'param_name'  => $param,
			'heading'     => $heading,
			'group'       => $group,
			'description' => '' !== $description ? $description : __( 'Selecione da Biblioteca de Mídia.', 'contorno' ),
		)
	);
}

/**
 * @return array<string,mixed>
 */
function contorno_vc_url( string $param, string $heading, string $group = '' ): array {
	return array_filter(
		array(
			'type'        => 'textfield',
			'param_name'  => $param,
			'heading'     => $heading,
			'group'       => $group,
			'description' => __( 'URL completa, incluindo https://', 'contorno' ),
		)
	);
}

/**
 * @param array<string,string> $options rotulo => valor
 * @return array<string,mixed>
 */
function contorno_vc_select( string $param, string $heading, array $options, string $group = '' ): array {
	return array_filter(
		array(
			'type'        => 'dropdown',
			'param_name'  => $param,
			'heading'     => $heading,
			'value'       => $options,
			'group'       => $group,
		)
	);
}

/**
 * @return array<string,mixed>
 */
function contorno_vc_toggle( string $param, string $heading, bool $default_yes = true, string $group = '' ): array {
	return contorno_vc_select(
		$param,
		$heading,
		$default_yes
			? array( __( 'Sim', 'contorno' ) => 'yes', __( 'Não', 'contorno' ) => 'no' )
			: array( __( 'Não', 'contorno' ) => 'no', __( 'Sim', 'contorno' ) => 'yes' ),
		$group
	);
}

/**
 * Seletor de tom (claro / escuro / dark CTN).
 *
 * @return array<string,mixed>
 */
function contorno_vc_tone( string $default = 'light' ): array {
	$options = array(
		__( 'Claro', 'contorno' )    => 'light',
		__( 'Escuro', 'contorno' )   => 'dark',
		__( 'Dark CTN', 'contorno' ) => 'ctn',
	);

	if ( 'dark' === $default ) {
		$options = array(
			__( 'Escuro', 'contorno' )   => 'dark',
			__( 'Claro', 'contorno' )    => 'light',
			__( 'Dark CTN', 'contorno' ) => 'ctn',
		);
	}

	return contorno_vc_select( 'tone', __( 'Tom da seção', 'contorno' ), $options, __( 'Aparência', 'contorno' ) );
}

/**
 * Seletor de unidade — lista as unidades cadastradas por slug.
 *
 * @return array<string,mixed>
 */
function contorno_vc_unit_picker(): array {
	$options = array( __( 'Unidade da página atual', 'contorno' ) => '' );

	foreach ( contorno_get_units() as $unit ) {
		$options[ (string) get_the_title( $unit->ID ) ] = (string) $unit->post_name;
	}

	return contorno_vc_select( 'unit', __( 'Unidade', 'contorno' ), $options, __( 'Fonte de dados', 'contorno' ) );
}

/**
 * Seletor de CTN.
 *
 * @return array<string,mixed>
 */
function contorno_vc_ctn_picker(): array {
	$options = array( __( 'CTN da página atual', 'contorno' ) => '' );

	foreach ( contorno_get_ctns() as $ctn ) {
		$options[ (string) get_the_title( $ctn->ID ) ] = (string) $ctn->post_name;
	}

	return contorno_vc_select( 'ctn', __( 'CTN', 'contorno' ), $options, __( 'Fonte de dados', 'contorno' ) );
}

/**
 * Registro dos elementos.
 */
add_action(
	'vc_before_init',
	static function (): void {
		if ( ! function_exists( 'vc_map' ) ) {
			return;
		}

		$editorial = __( 'Conteúdo', 'contorno' );
		$look      = __( 'Aparência', 'contorno' );
		$cta_group = __( 'CTA', 'contorno' );

		/* ---------------------------------------------------------------
		 * CONTORNO — Hero
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Hero', 'contorno' ),
				'base'        => 'contorno_hero',
				'category'    => CONTORNO_VC_CATEGORY,
				'icon'        => 'contorno-vc-icon',
				'description' => __( 'Hero institucional com imagem, headline e CTAs.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial, __( "Use o caractere | para forçar uma quebra de linha.", "contorno" ) ),
					contorno_vc_text( 'highlight', __( 'Trecho destacado do título', 'contorno' ), $editorial, __( 'Parte do título que recebe a cor da marca. Ex.: "Na pratica".', 'contorno' ) ),
					contorno_vc_textarea( 'subtitle', __( 'Subtítulo', 'contorno' ), $editorial ),
					contorno_vc_image( 'image', __( 'Imagem de fundo', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'CTA principal — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta_url', __( 'CTA principal — link', 'contorno' ), $cta_group ),
					contorno_vc_text( 'cta2_label', __( 'CTA secundário — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta2_url', __( 'CTA secundário — link', 'contorno' ), $cta_group ),
					contorno_vc_toggle( 'show_search', __( 'Exibir busca de unidades', 'contorno' ), false, $look ),
					contorno_vc_select(
						'height',
						__( 'Altura', 'contorno' ),
						array(
							__( 'Alto (tela cheia)', 'contorno' ) => 'tall',
							__( 'Medio', 'contorno' )             => 'medium',
							__( 'Compacto', 'contorno' )          => 'short',
						),
						$look
					),
					contorno_vc_text( 'overlay', __( 'Opacidade do escurecimento (0-100)', 'contorno' ), $look ),
					contorno_vc_select(
						'scrim',
						__( 'Direção do escurecimento', 'contorno' ),
						array(
							__( 'Lateral (texto à esquerda)', 'contorno' ) => 'side',
							__( 'De baixo para cima', 'contorno' )         => 'bottom',
						),
						$look
					),
					contorno_vc_text(
						'focal',
						__( 'Enquadramento da foto', 'contorno' ),
						$look,
						__( 'Qual ponto da foto fica visível. Ex.: "center", "62% 18%", "top".', 'contorno' )
					),
					contorno_vc_text(
						'search_card',
						__( 'Título do cartão de busca', 'contorno' ),
						$editorial,
						__( 'Só aparece quando a busca de unidades está ativada.', 'contorno' )
					),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — PUV
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — PUV', 'contorno' ),
				'base'        => 'contorno_puv',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Bloco texto + imagem para proposta de valor e conteúdo institucional.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'bullets', __( 'Itens com check', 'contorno' ), $editorial, __( 'Um item por linha.', 'contorno' ) ),
					contorno_vc_text( 'extra_text', __( 'Texto complementar', 'contorno' ), $editorial ),
					contorno_vc_image( 'image', __( 'Imagem', 'contorno' ), $editorial ),
					contorno_vc_text( 'image_alt', __( 'Texto alternativo da imagem', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'CTA — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta_url', __( 'CTA — link', 'contorno' ), $cta_group ),
					contorno_vc_select(
						'layout',
						__( 'Posição da imagem', 'contorno' ),
						array(
							__( 'Imagem a direita', 'contorno' ) => 'image-right',
							__( 'Imagem a esquerda', 'contorno' ) => 'image-left',
						),
						$look
					),
					contorno_vc_tone(),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Banner
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Banner', 'contorno' ),
				'base'        => 'contorno_banner',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Faixa promocional com imagem de fundo e CTA.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ), $editorial ),
					contorno_vc_image( 'image', __( 'Imagem de fundo', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'CTA — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta_url', __( 'CTA — link', 'contorno' ), $cta_group ),
					contorno_vc_text( 'overlay', __( 'Opacidade do escurecimento (0-100)', 'contorno' ), $look ),
					contorno_vc_select(
						'align',
						__( 'Alinhamento', 'contorno' ),
						array(
							__( 'Esquerda', 'contorno' ) => 'left',
							__( 'Centro', 'contorno' )   => 'center',
						),
						$look
					),
					contorno_vc_tone( 'dark' ),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — CTA final
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — CTA', 'contorno' ),
				'base'        => 'contorno_cta',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Faixa de fechamento de página com headline e botão.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'headline', __( 'Headline', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'Botao — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta_url', __( 'Botao — link', 'contorno' ), $cta_group ),
					contorno_vc_image( 'image', __( 'Imagem de fundo', 'contorno' ), $editorial ),
					contorno_vc_tone( 'dark' ),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Destaques
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Destaques', 'contorno' ),
				'base'        => 'contorno_highlights',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Grade de cards com ícone, título e texto.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					array(
						'type'        => 'param_group',
						'param_name'  => 'items',
						'heading'     => __( 'Destaques', 'contorno' ),
						'group'       => $editorial,
						'value'       => '',
						'params'      => array(
							contorno_vc_text( 'label', __( 'Título do destaque', 'contorno' ) ),
							contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ) ),
							contorno_vc_select(
								'icon',
								__( 'Ícone', 'contorno' ),
								array(
									__( 'Automático pelo título', 'contorno' ) => '',
									__( 'Musculação', 'contorno' )   => 'dumbbell',
									__( 'Funcional', 'contorno' )    => 'activity',
									__( 'Cardio', 'contorno' )       => 'heart-pulse',
									__( 'Aulas / equipe', 'contorno' ) => 'users',
									__( 'Vestiário', 'contorno' )    => 'shower',
									__( 'Estacionamento', 'contorno' ) => 'car',
									__( 'Wi-Fi', 'contorno' )        => 'wifi',
									__( 'Horário', 'contorno' )      => 'clock',
									__( 'Localização', 'contorno' )  => 'map-pin',
									__( 'Premium', 'contorno' )      => 'sparkles',
								)
							),
						),
					),
					contorno_vc_select(
						'columns',
						__( 'Colunas', 'contorno' ),
						array( '4' => '4', '3' => '3', '2' => '2' ),
						$look
					),
					contorno_vc_tone(),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Lista de Unidades (COMPONENTE CONTROLADO)
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Lista de Unidades', 'contorno' ),
				'base'        => 'contorno_units',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Componente controlado: grade no desktop, carrossel de 1 unidade no mobile, busca e filtros.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_select(
						'columns',
						__( 'Colunas no desktop', 'contorno' ),
						array( '3' => '3', '4' => '4' ),
						$look
					),
					contorno_vc_text( 'limit', __( 'Quantidade (-1 para todas)', 'contorno' ), __( 'Fonte de dados', 'contorno' ) ),
					contorno_vc_toggle( 'featured', __( 'Somente unidades em destaque', 'contorno' ), false, __( 'Fonte de dados', 'contorno' ) ),
					contorno_vc_text( 'city', __( 'Filtrar por cidade (slug, separado por vírgula)', 'contorno' ), __( 'Fonte de dados', 'contorno' ) ),
					contorno_vc_toggle( 'show_search', __( 'Exibir campo de busca', 'contorno' ), true, $look ),
					contorno_vc_toggle( 'prescription', __( 'CTA de prescrição de treino', 'contorno' ), false, $cta_group ),
					contorno_vc_text( 'empty_text', __( 'Texto quando nada é encontrado', 'contorno' ), $editorial ),
					contorno_vc_tone(),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Planos (COMPONENTE CONTROLADO)
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Planos', 'contorno' ),
				'base'        => 'contorno_plans',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Cards de planos. Preços, benefícios, badge e checkout vêm dos campos da Unidade/CTN — não se edita aqui.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'Texto do botao dos cards', 'contorno' ), $cta_group ),
					contorno_vc_text( 'note', __( 'Nota de rodapé (ex.: pagamento seguro)', 'contorno' ), $editorial ),
					contorno_vc_unit_picker(),
					contorno_vc_ctn_picker(),
					contorno_vc_select(
						'skin',
						__( 'Pele', 'contorno' ),
						array(
							__( 'Automática (dark nas CTNs)', 'contorno' ) => 'auto',
							__( 'Clara', 'contorno' )                      => 'light',
							__( 'Dark CTN', 'contorno' )                   => 'dark',
						),
						$look
					),
					contorno_vc_select( 'columns', __( 'Colunas', 'contorno' ), array( '3' => '3', '2' => '2', '4' => '4' ), $look ),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Aulas Coletivas (COMPONENTE CONTROLADO)
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Aulas Coletivas', 'contorno' ),
				'base'        => 'contorno_classes',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Componente controlado: grade semanal oficial EVO com horários, filtros e drawer. A filial é configurada nos campos da Unidade.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título da seção', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_image( 'banner', __( 'Imagem do cabecalho', 'contorno' ), $editorial ),
					contorno_vc_unit_picker(),
					contorno_vc_ctn_picker(),
					contorno_vc_text( 'height', __( 'Altura do quadro (px)', 'contorno' ), $look ),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * Secoes de Unidade
		 * ------------------------------------------------------------- */
		$unit_sections = array(
			'contorno_unit_hero'          => array( __( 'CONTORNO — Hero da Unidade', 'contorno' ), __( 'Hero com foto, endereço, status de pré-venda e CTAs da unidade.', 'contorno' ), false ),
			'contorno_unit_info'          => array( __( 'CONTORNO — Faixa Informativa', 'contorno' ), __( 'Endereço, horário e contato da unidade.', 'contorno' ), false ),
			'contorno_unit_highlights'    => array( __( 'CONTORNO — Destaques da Unidade', 'contorno' ), __( 'Cards com ícone a partir do campo Destaques da unidade.', 'contorno' ), true ),
			'contorno_unit_differentials' => array( __( 'CONTORNO — Diferenciais da Unidade', 'contorno' ), __( 'Lista de diferenciais cadastrados na unidade.', 'contorno' ), true ),
			'contorno_unit_video'         => array( __( 'CONTORNO — Vídeo da Unidade', 'contorno' ), __( 'Vídeo da unidade com carregamento sob demanda.', 'contorno' ), true ),
		);

		foreach ( $unit_sections as $base => $meta ) {
			list( $label, $description, $has_header ) = $meta;

			$params = array( contorno_vc_unit_picker() );

			if ( $has_header ) {
				$params[] = contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial );
				$params[] = contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial );
				$params[] = contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial );
				$params[] = contorno_vc_tone();
			}

			vc_map(
				array(
					'name'        => $label,
					'base'        => $base,
					'category'    => CONTORNO_VC_CATEGORY,
					'description' => $description,
					'params'      => $params,
				)
			);
		}

		/* ---------------------------------------------------------------
		 * CONTORNO — Galeria
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Galeria', 'contorno' ),
				'base'        => 'contorno_gallery',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Galeria com lightbox a partir do campo Galeria da Unidade/CTN.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_unit_picker(),
					contorno_vc_ctn_picker(),
					contorno_vc_select( 'columns', __( 'Colunas', 'contorno' ), array( '4' => '4', '3' => '3', '2' => '2' ), $look ),
					contorno_vc_tone(),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Localizacao
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Localização', 'contorno' ),
				'base'        => 'contorno_location',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Mapa + endereço + horários a partir dos campos da Unidade/CTN.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_unit_picker(),
					contorno_vc_ctn_picker(),
					contorno_vc_tone(),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Area editorial da Unidade/CTN
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Area Editorial', 'contorno' ),
				'base'        => 'contorno_editorial_area',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Injeta o conteúdo editorial livre daquela Unidade/CTN na posição escolhida do template compartilhado.', 'contorno' ),
				'params'      => array(
					contorno_vc_select(
						'slot',
						__( 'Slot', 'contorno' ),
						array(
							__( 'Conteúdo principal', 'contorno' )     => 'main',
							__( 'Antes dos planos', 'contorno' )       => 'before_plans',
							__( 'Depois dos planos', 'contorno' )      => 'after_plans',
							__( 'Antes do rodapé', 'contorno' )        => 'before_footer',
						)
					),
					contorno_vc_unit_picker(),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CTN
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CTN — Hero', 'contorno' ),
				'base'        => 'ctn_hero',
				'category'    => CONTORNO_VC_CATEGORY_CTN,
				'description' => __( 'Hero dark com o logo CTN sobre a imagem. Não usa o header institucional.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_text( 'headline', __( 'Headline', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'subtitle', __( 'Subtítulo', 'contorno' ), $editorial ),
					contorno_vc_text( 'tagline', __( 'Tagline', 'contorno' ), $editorial ),
					contorno_vc_image( 'image', __( 'Imagem do hero', 'contorno' ), $editorial ),
					contorno_vc_text( 'image_alt', __( 'Texto alternativo', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'CTA principal — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta_url', __( 'CTA principal — link', 'contorno' ), $cta_group ),
					contorno_vc_text( 'cta2_label', __( 'CTA secundário — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta2_url', __( 'CTA secundário — link', 'contorno' ), $cta_group ),
					contorno_vc_toggle( 'show_logo', __( 'Exibir logo CTN sobre o hero', 'contorno' ), true, $look ),
					contorno_vc_ctn_picker(),
				),
			)
		);

		vc_map(
			array(
				'name'        => __( 'CTN — PUV', 'contorno' ),
				'base'        => 'ctn_puv',
				'category'    => CONTORNO_VC_CATEGORY_CTN,
				'description' => __( 'PUV com equipamento premium protagonista (Panatta quando disponível) + números.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Descrição', 'contorno' ), $editorial, __( 'Um parágrafo por linha.', 'contorno' ) ),
					contorno_vc_image( 'image', __( 'Imagem do equipamento', 'contorno' ), $editorial, __( 'Máquina/equipamento premium em primeiro plano. Nunca foto genérica de academia.', 'contorno' ) ),
					contorno_vc_text( 'image_alt', __( 'Texto alternativo', 'contorno' ), $editorial ),
					contorno_vc_text( 'extra_text', __( 'Texto complementar', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'CTA — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta_url', __( 'CTA — link', 'contorno' ), $cta_group ),
					contorno_vc_toggle( 'show_stats', __( 'Exibir números', 'contorno' ), true, $look ),
					contorno_vc_ctn_picker(),
				),
			)
		);

		vc_map(
			array(
				'name'        => __( 'CTN — Sobre', 'contorno' ),
				'base'        => 'ctn_about',
				'category'    => CONTORNO_VC_CATEGORY_CTN,
				'description' => __( 'Sobre com vídeo vertical 9:16. Não voltar para imagem estatica.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ), $editorial, __( 'Um parágrafo por linha.', 'contorno' ) ),
					contorno_vc_textarea( 'features', __( 'Itens de estrutura', 'contorno' ), $editorial, __( 'Um item por linha.', 'contorno' ) ),
					contorno_vc_text( 'video_id', __( 'Vídeo vertical — ID do YouTube/Short', 'contorno' ), __( 'Vídeo', 'contorno' ) ),
					contorno_vc_url( 'video', __( 'Vídeo vertical — arquivo (prioritario)', 'contorno' ), __( 'Vídeo', 'contorno' ) ),
					contorno_vc_image( 'poster', __( 'Poster vertical', 'contorno' ), __( 'Vídeo', 'contorno' ) ),
					contorno_vc_text( 'caption', __( 'Legenda de apoio', 'contorno' ), __( 'Vídeo', 'contorno' ) ),
					contorno_vc_ctn_picker(),
				),
			)
		);

		vc_map(
			array(
				'name'        => __( 'CTN — Marcas', 'contorno' ),
				'base'        => 'ctn_brands',
				'category'    => CONTORNO_VC_CATEGORY_CTN,
				'description' => __( 'Faixa com logos das marcas de equipamento.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_ctn_picker(),
				),
			)
		);

		vc_map(
			array(
				'name'        => __( 'CTN — Equipamentos', 'contorno' ),
				'base'        => 'ctn_equipment',
				'category'    => CONTORNO_VC_CATEGORY_CTN,
				'description' => __( 'Destaques de equipamento cadastrados na CTN. Não entra na landing por padrão — insira quando quiser a seção.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_toggle(
						'show_media',
						__( 'Exibir vídeo ou foto de cada equipamento', 'contorno' ),
						false,
						$look
					),
					contorno_vc_ctn_picker(),
				),
			)
		);

		vc_map(
			array(
				'name'        => __( 'CTN — Ronnie + Playlist', 'contorno' ),
				'base'        => 'ctn_videos',
				'category'    => CONTORNO_VC_CATEGORY_CTN,
				'description' => __( 'Um vídeo principal + box vertical "Ver mais" com link de playlist.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_text( 'video_id', __( 'Vídeo principal — ID do YouTube', 'contorno' ), __( 'Vídeo', 'contorno' ) ),
					contorno_vc_text( 'more_title', __( 'Box "Ver mais" — título', 'contorno' ), __( 'Ver mais', 'contorno' ) ),
					contorno_vc_textarea( 'more_text', __( 'Box "Ver mais" — texto', 'contorno' ), __( 'Ver mais', 'contorno' ) ),
					contorno_vc_text( 'more_label', __( 'Box "Ver mais" — texto do botão', 'contorno' ), __( 'Ver mais', 'contorno' ) ),
					contorno_vc_url( 'playlist_url', __( 'URL da playlist', 'contorno' ), __( 'Ver mais', 'contorno' ) ),
					contorno_vc_ctn_picker(),
				),
			)
		);

		vc_map(
			array(
				'name'        => __( 'CTN — Lista de CTNs', 'contorno' ),
				'base'        => 'ctn_list',
				'category'    => CONTORNO_VC_CATEGORY_CTN,
				'description' => __( 'Cards das CTNs cadastradas — usado no hub /ctn.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_select( 'columns', __( 'Colunas', 'contorno' ), array( '2' => '2', '3' => '3' ), $look ),
				),
			)
		);
	}
);

/**
 * Icone dos elementos Contorno no seletor do builder.
 */
add_action(
	'vc_backend_editor_enqueue_js_css',
	static function (): void {
		wp_enqueue_style(
			'contorno-vc-admin',
			contorno_core_url( 'assets/css/vc-admin.css' ),
			array(),
			contorno_core_asset_version( 'assets/css/vc-admin.css' )
		);
	}
);
