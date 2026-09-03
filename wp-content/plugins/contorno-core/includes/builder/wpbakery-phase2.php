<?php
/**
 * Elementos do WPBakery da Fase 2: matricula, contato e blog.
 *
 * Separado de wpbakery.php so por legibilidade — mesma API, mesmas
 * categorias, mesmo padrao de campos amigaveis.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
		 * CONTORNO — Etapas da Matrícula
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Etapas da Matrícula', 'contorno' ),
				'base'        => 'contorno_checkout_steps',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Indicador de 1. Seus dados / 2. Pagamento / 3. Confirmação.', 'contorno' ),
				'params'      => array(
					contorno_vc_select(
						'current',
						__( 'Etapa atual', 'contorno' ),
						array(
							__( '1 — Seus dados', 'contorno' )  => '1',
							__( '2 — Pagamento', 'contorno' )   => '2',
							__( '3 — Confirmação', 'contorno' ) => '3',
						)
					),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Formulário de Matrícula (COMPONENTE CONTROLADO)
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Formulário de Matrícula', 'contorno' ),
				'base'        => 'contorno_enrollment_form',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Componente controlado: campos, validação, máscara de telefone e destino do checkout. A unidade e o plano vêm da URL.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'subtitle', __( 'Subtítulo', 'contorno' ), $editorial ),
					contorno_vc_textarea(
						'privacy',
						__( 'Aviso de privacidade', 'contorno' ),
						$editorial,
						__( 'Texto do bloco cinza acima do aceite. Mantenha alinhado com a Política de Privacidade.', 'contorno' )
					),
					array(
						'type'       => 'param_group',
						'param_name' => 'benefits',
						'heading'    => __( 'Selos de confiança', 'contorno' ),
						'group'      => $editorial,
						'value'      => '',
						'params'     => array(
							contorno_vc_text( 'label', __( 'Título', 'contorno' ) ),
							contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ) ),
							contorno_vc_select(
								'icon',
								__( 'Ícone', 'contorno' ),
								array(
									__( 'Check', 'contorno' )     => 'check',
									__( 'Premium', 'contorno' )   => 'sparkles',
									__( 'Atendimento', 'contorno' ) => 'phone',
									__( 'Relógio', 'contorno' )   => 'clock',
								)
							),
						),
					),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Confirmação de Matrícula
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Confirmação de Matrícula', 'contorno' ),
				'base'        => 'contorno_enrollment_confirmation',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Página de confirmação completa: boas-vindas, PUV, prescrição de treino com busca de unidade e bloco de ajuda.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ), $editorial ),
					contorno_vc_image( 'image', __( 'Imagem', 'contorno' ), $editorial ),
					array(
						'type'       => 'param_group',
						'param_name' => 'steps',
						'heading'    => __( 'Próximos passos', 'contorno' ),
						'group'      => $editorial,
						'value'      => '',
						'params'     => array(
							contorno_vc_text( 'label', __( 'Título', 'contorno' ) ),
							contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ) ),
							contorno_vc_select(
								'icon',
								__( 'Ícone', 'contorno' ),
								array(
									__( 'Check', 'contorno' )   => 'check',
									__( 'Seta', 'contorno' )    => 'arrow-right',
									__( 'Premium', 'contorno' ) => 'sparkles',
								)
							),
						),
					),
					contorno_vc_text( 'puv_eyebrow', __( 'PUV — eyebrow', 'contorno' ), __( 'PUV', 'contorno' ) ),
					contorno_vc_text( 'puv_title', __( 'PUV — título', 'contorno' ), __( 'PUV', 'contorno' ), __( 'Use | para quebrar linha.', 'contorno' ) ),
					contorno_vc_textarea( 'puv_text', __( 'PUV — texto', 'contorno' ), __( 'PUV', 'contorno' ) ),
					contorno_vc_text( 'next_eyebrow', __( 'Prescrição — eyebrow', 'contorno' ), __( 'Prescrição', 'contorno' ) ),
					contorno_vc_text( 'next_title', __( 'Prescrição — título', 'contorno' ), __( 'Prescrição', 'contorno' ) ),
					contorno_vc_textarea( 'next_text', __( 'Prescrição — texto', 'contorno' ), __( 'Prescrição', 'contorno' ) ),
					contorno_vc_text( 'help_title', __( 'Ajuda — título', 'contorno' ), __( 'Ajuda', 'contorno' ) ),
					contorno_vc_textarea( 'help_text', __( 'Ajuda — texto', 'contorno' ), __( 'Ajuda', 'contorno' ) ),
					contorno_vc_text( 'help_label', __( 'Ajuda — texto do botão', 'contorno' ), __( 'Ajuda', 'contorno' ) ),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Formulário de Contato (COMPONENTE CONTROLADO)
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Formulário de Contato', 'contorno' ),
				'base'        => 'contorno_contact_form',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Componente controlado: validação, honeypot, limite de envios e entrega da mensagem. Não se edita os campos aqui.', 'contorno' ),
				'params'      => array(
					contorno_vc_toggle( 'show_title', __( 'Exibir título dentro do bloco', 'contorno' ), false, $look ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'Texto do botão', 'contorno' ), $cta_group ),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Canais de Atendimento
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Canais de Atendimento', 'contorno' ),
				'base'        => 'contorno_contact_channels',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Telefone, WhatsApp e e-mail oficiais, vindos dos dados de marca.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto', 'contorno' ), $editorial ),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Blog (prévia)
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Blog (prévia)', 'contorno' ),
				'base'        => 'contorno_blog_preview',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Últimos posts publicados. Enquanto o blog estiver vazio, mostra os três cards institucionais de exemplo.', 'contorno' ),
				'params'      => array(
					contorno_vc_text( 'eyebrow', __( 'Eyebrow', 'contorno' ), $editorial ),
					contorno_vc_text( 'title', __( 'Título', 'contorno' ), $editorial ),
					contorno_vc_textarea( 'text', __( 'Texto de apoio', 'contorno' ), $editorial ),
					contorno_vc_text( 'cta_label', __( 'Texto do botão', 'contorno' ), $cta_group ),
					contorno_vc_select( 'limit', __( 'Quantidade', 'contorno' ), array( '3' => '3', '6' => '6', '9' => '9' ), $look ),
					contorno_vc_tone(),
				),
			)
		);

		/* ---------------------------------------------------------------
		 * CONTORNO — Listagem do Blog
		 * ------------------------------------------------------------- */
		vc_map(
			array(
				'name'        => __( 'CONTORNO — Listagem do Blog', 'contorno' ),
				'base'        => 'contorno_blog_list',
				'category'    => CONTORNO_VC_CATEGORY,
				'description' => __( 'Grade paginada de posts, para a página /blog.', 'contorno' ),
				'params'      => array(
					contorno_vc_select( 'per_page', __( 'Posts por página', 'contorno' ), array( '9' => '9', '6' => '6', '12' => '12' ), $look ),
					contorno_vc_textarea( 'empty_text', __( 'Texto quando não há posts', 'contorno' ), $editorial ),
					contorno_vc_tone(),
				),
			)
		);
	},
	20
);
