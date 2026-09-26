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
 * A unidade usa de fato o recurso "Conteudo editorial extra"?
 *
 * O WPBakery so tem uma funcao real no CPT unidade: e o editor do
 * post_content injetado pelo slot escolhido em editorial_position (ver
 * includes/shortcodes/institutional.php e o registry.php). Nenhuma outra
 * parte da tela de unidade depende dele.
 */
function contorno_unit_has_editorial_content( int $post_id ): bool {
	if ( 'none' !== contorno_field_text( 'editorial_position', $post_id, 'none' ) ) {
		return true;
	}

	return '' !== trim( (string) get_post_field( 'post_content', $post_id ) );
}

/**
 * Esconde a caixa "WPBakery Page Builder" na tela de edicao de unidade —
 * ela nao serve pra nada la alem do recurso acima, e confundia o editor com
 * um builder de pagina que nao e usado nas unidades.
 *
 * So some se a unidade REALMENTE usa o recurso: editorial_position != none
 * ou post_content ja tem algo (unidade legada ou em uso). Nesse caso a caixa
 * continua aparecendo, com um aviso explicando o porque — nada de conteudo
 * apagado, nada de recurso desligado, so a interface fica limpa quando nao
 * ha nada pra editar ali.
 *
 * Nao mexe em nenhum outro post type: CTN e paginas continuam com o
 * WPBakery normalmente.
 */
add_action(
	'add_meta_boxes_' . CONTORNO_CPT_UNIT,
	static function ( WP_Post $post ): void {
		if ( contorno_unit_has_editorial_content( $post->ID ) ) {
			add_action(
				'edit_form_after_title',
				static function ( WP_Post $notice_post ) use ( $post ): void {
					if ( (int) $notice_post->ID !== (int) $post->ID ) {
						return;
					}

					printf(
						'<div class="notice notice-info inline"><p>%s</p></div>',
						esc_html__( 'O WPBakery aparece aqui porque esta unidade usa o recurso "Conteúdo editorial extra" (aba Conteúdo editorial extra, campo Posição). Sem isso configurado, esta caixa fica oculta.', 'contorno' )
					);
				}
			);

			return;
		}

		remove_meta_box( 'wpb_wpbakery', CONTORNO_CPT_UNIT, 'normal' );
	}
);

/**
 * Mesma logica, pro editor nativo (post_content) — o "editor grande" que
 * aparecia mesmo com o WPBakery ja oculto, porque e renderizado direto por
 * wp-admin/edit-form-advanced.php quando o post type suporta 'editor',
 * independente de qualquer metabox.
 *
 * remove_post_type_support() aqui, dentro de add_meta_boxes_unidade, roda
 * ANTES do edit-form-advanced.php checar o suporte (mesma requisicao,
 * add_meta_boxes sempre fura antes do template) — e so vale para esta
 * unidade, nesta tela: o suporte volta a valer no proximo request de outra
 * unidade que precise dele.
 *
 * Nao apaga post_content, nao mexe no recurso "Conteudo editorial extra":
 * se a unidade usa esse recurso, o editor nativo continua aparecendo,
 * exatamente como o WPBakery acima.
 */
add_action(
	'add_meta_boxes_' . CONTORNO_CPT_UNIT,
	static function ( WP_Post $post ): void {
		if ( contorno_unit_has_editorial_content( $post->ID ) ) {
			return;
		}

		remove_post_type_support( CONTORNO_CPT_UNIT, 'editor' );
	}
);

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
		 * CONTORNO — Pagina simples
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Pagina simples', 'contorno' ),
				'base'        => 'contorno_simple_page',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Secao editorial centralizada, igual as paginas simples do template HTML.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Titulo', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'CTA — texto', 'contorno' ), $cta_group ),
					contorno_vc_url( 'cta_url', __( 'CTA — link', 'contorno' ), $cta_group ),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Cabecalho de pagina interna
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Cabeçalho de página', 'contorno' ),
				'base'        => 'contorno_page_header',
				'category'    => CONTORNO_VC_CATEGORY,
				'icon'        => 'contorno-vc-icon',
				'description' => __( 'Topo das páginas internas: breadcrumb, eyebrow, título e introdução, fundo branco (sem foto).', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial, __( 'Vazio = título da página.', 'contorno' ) ),
					contorno_vc_textarea( 'intro', __( 'Introdução', 'contorno' ), $editorial ),
					contorno_vc_text( 'crumb', __( 'Rótulo no breadcrumb', 'contorno' ), $editorial, __( 'Vazio = mesmo do título.', 'contorno' ) ),
				),
			)
		);

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
		 * CONTORNO — Slider da Home
		 *
		 * Elemento sem NENHUM parametro de instancia — os slides sao
		 * cadastro GLOBAL em Contorno > Slider da Home, o bloco so posiciona
		 * o slider na pagina. Um vc_map() com 'params' => array() (literal,
		 * vazio) faz o modal de edicao do WPBakery quebrar (backend.min.js:
		 * "Cannot read properties of null (reading 'get')" ao abrir o
		 * formulario) — o form JS espera pelo menos um param pra montar o
		 * model. Por isso ha exatamente UM param aqui, do tipo
		 * "custom_markup" (documentado pelo proprio WPBakery para markup
		 * estatico dentro do form — nunca vira atributo do shortcode: nao
		 * ha <input> nele, so o aviso e o botao abaixo).
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Slider da Home', 'contorno' ),
				'base'        => 'contorno_home_slider',
				'category'    => CONTORNO_VC_CATEGORY,
				'icon'        => 'contorno-vc-icon',
				'description' => __( 'Banners rotativos simples, sem escurecimento nem texto sobreposto. Slides geridos em Contorno > Slider da Home.', 'contorno' ),
				'params'      => array(
					array(
						'type'       => 'custom_markup',
						'heading'    => '',
						'param_name' => 'contorno_info',
						'value'      => sprintf(
							'<p style="margin:0 0 12px;max-width:360px">%s</p><a class="button button-primary" href="%s" target="_blank" rel="noopener">%s</a>',
							esc_html__( 'Este bloco exibe o Slider da Home configurado em Contorno → Slider da Home. Não há nada para configurar aqui — os slides são cadastrados naquela tela.', 'contorno' ),
							esc_url( admin_url( 'admin.php?page=' . CONTORNO_HOME_SLIDER_PAGE ) ),
							esc_html__( 'Gerenciar Slider da Home', 'contorno' )
						),
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
					contorno_vc_select(
						'catalog_mode',
						__( 'Modo catálogo (/unidades)', 'contorno' ),
						array(
							__( 'Automático na página /unidades', 'contorno' ) => 'auto',
							__( 'Ativar', 'contorno' )                         => 'yes',
							__( 'Desativar', 'contorno' )                      => 'no',
						),
						$look
					),
					contorno_vc_toggle( 'show_count', __( 'Exibir quantidade encontrada', 'contorno' ), true, $look ),
					contorno_vc_toggle( 'show_per_page', __( 'Exibir seletor por página', 'contorno' ), true, $look ),
					contorno_vc_select(
						'per_page',
						__( 'Itens por página', 'contorno' ),
						array( '15' => '15', '9' => '9', '45' => '45', '60' => '60' ),
						$look
					),
					contorno_vc_toggle( 'pagination', __( 'Exibir paginação', 'contorno' ), true, $look ),
					contorno_vc_select(
						'dual_cta',
						__( 'Botões nos cards', 'contorno' ),
						array(
							__( 'Automático no modo catálogo', 'contorno' ) => 'auto',
							__( 'Dois botões', 'contorno' )                 => 'yes',
							__( 'Um botão', 'contorno' )                    => 'no',
						),
						$cta_group
					),
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
				'name'        => __( 'CTN — Hub', 'contorno' ),
				'base'        => 'ctn_hub',
				'category'    => CONTORNO_VC_CATEGORY_CTN,
				'description' => __( 'Pagina /ctn completa: hero dark, PUV, busca e cards de CTNs.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Hero — eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Hero — titulo', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'subtitle', __( 'Hero — subtitulo', 'contorno' ), $editorial ),
					contorno_vc_image( 'image', __( 'Hero — imagem', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'Hero — botao', 'contorno' ), $cta_group ),
					contorno_vc_text( 'puv_eyebrow', __( 'PUV — eyebrow', 'contorno' ), __( 'PUV', 'contorno' ) ),
					contorno_vc_text( 'puv_title', __( 'PUV — titulo', 'contorno' ), __( 'PUV', 'contorno' ) ),
					contorno_vc_textarea( 'puv_text', __( 'PUV — texto', 'contorno' ), __( 'PUV', 'contorno' ) ),
					contorno_vc_image( 'puv_image', __( 'PUV — imagem', 'contorno' ), __( 'PUV', 'contorno' ) ),
					contorno_vc_text( 'puv_image_alt', __( 'PUV — texto alternativo', 'contorno' ), __( 'PUV', 'contorno' ) ),
					contorno_vc_text( 'search_label', __( 'Busca — rotulo', 'contorno' ), __( 'Busca', 'contorno' ) ),
					contorno_vc_text( 'search_placeholder', __( 'Busca — placeholder', 'contorno' ), __( 'Busca', 'contorno' ) ),
				),
			)
		);

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
