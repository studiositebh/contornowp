<?php
/**
 * Esquema de campos estruturados das Unidades e das CTNs.
 *
 * ESTA E A FONTE UNICA DE VERDADE dos campos. Metaboxes, REST, importador e
 * templates leem daqui — nao declarar campo em outro lugar.
 *
 * Porte fiel de:
 *   - src/lib/contorno/types.ts      -> GymUnit, UnitPlan, UnitPreSale
 *   - src/lib/contorno/ctn/types.ts  -> CTNUnit, CTNPlan, CTNBrand, CTNEquipmentHighlight
 *
 * Tipos suportados:
 *   text | textarea | url | number | select | checkbox | media | media_list
 *   list        -> lista simples de strings   (armazenada como JSON)
 *   repeater    -> lista de objetos           (armazenada como JSON)
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Prefixo unico de meta key. Mudar isto quebra dados ja gravados. */
const CONTORNO_META_PREFIX = '_contorno_';

/**
 * Esquema completo por post type.
 *
 * @return array<string,array<string,mixed>>
 */
function contorno_field_schema(): array {
	static $schema = null;

	if ( null !== $schema ) {
		return $schema;
	}

	$schema = array(
		CONTORNO_CPT_UNIT => array(
			'identidade' => array(
				'label'  => __( 'Identidade', 'contorno' ),
				'fields' => array(
					'short_name'        => array( 'type' => 'text', 'label' => __( 'Nome curto', 'contorno' ) ),
					'kind'              => array(
						'type'    => 'select',
						'label'   => __( 'Tipo', 'contorno' ),
						'options' => array(
							'standard'  => __( 'Padrao', 'contorno' ),
							'prime'     => __( 'Prime', 'contorno' ),
							'ctn-prime' => __( 'CTN / Centro de Treinamento', 'contorno' ),
						),
						'default' => 'standard',
					),
					'badge'             => array( 'type' => 'text', 'label' => __( 'Badge do card', 'contorno' ) ),
					'featured'          => array( 'type' => 'checkbox', 'label' => __( 'Destacar na Home', 'contorno' ) ),
					'short_description' => array( 'type' => 'textarea', 'label' => __( 'Descricao curta (card)', 'contorno' ) ),
				),
			),
			'localizacao' => array(
				'label'  => __( 'Localizacao e contato', 'contorno' ),
				'fields' => array(
					'city'         => array( 'type' => 'text', 'label' => __( 'Cidade', 'contorno' ) ),
					'state'        => array(
						'type'    => 'select',
						'label'   => __( 'Estado', 'contorno' ),
						'options' => array( 'MG' => 'MG', 'SP' => 'SP' ),
						'default' => 'MG',
					),
					'neighborhood' => array( 'type' => 'text', 'label' => __( 'Bairro', 'contorno' ) ),
					'address'      => array( 'type' => 'text', 'label' => __( 'Endereco', 'contorno' ) ),
					'postal_code'  => array( 'type' => 'text', 'label' => __( 'CEP', 'contorno' ) ),
					'phone'        => array( 'type' => 'text', 'label' => __( 'Telefone', 'contorno' ) ),
					'whatsapp'     => array( 'type' => 'text', 'label' => __( 'WhatsApp', 'contorno' ) ),
					'hours'        => array( 'type' => 'text', 'label' => __( 'Horario de funcionamento', 'contorno' ) ),
					'maps_query'   => array( 'type' => 'text', 'label' => __( 'Busca no Google Maps', 'contorno' ) ),
					'latitude'     => array( 'type' => 'number', 'label' => __( 'Latitude', 'contorno' ), 'step' => 'any' ),
					'longitude'    => array( 'type' => 'number', 'label' => __( 'Longitude', 'contorno' ), 'step' => 'any' ),
				),
			),
			'midia' => array(
				'label'  => __( 'Hero, galeria e video', 'contorno' ),
				'fields' => array(
					'image'     => array( 'type' => 'media', 'label' => __( 'Imagem principal / hero', 'contorno' ) ),
					'image_alt' => array( 'type' => 'text', 'label' => __( 'Texto alternativo do hero', 'contorno' ) ),
					'gallery'   => array( 'type' => 'media_list', 'label' => __( 'Galeria', 'contorno' ) ),
					'video_url' => array( 'type' => 'url', 'label' => __( 'Video (YouTube/arquivo)', 'contorno' ) ),
				),
			),
			'conteudo' => array(
				'label'  => __( 'Diferenciais e destaques', 'contorno' ),
				'fields' => array(
					'differentials' => array( 'type' => 'list', 'label' => __( 'Diferenciais', 'contorno' ) ),
					'facilities'    => array( 'type' => 'list', 'label' => __( 'Destaques Contorno (icones)', 'contorno' ) ),
					'modalities'    => array( 'type' => 'list', 'label' => __( 'Modalidades', 'contorno' ) ),
				),
			),
			'planos' => array(
				'label'  => __( 'Planos', 'contorno' ),
				'fields' => array(
					'starting_price' => array( 'type' => 'number', 'label' => __( 'Preco "a partir de"', 'contorno' ), 'step' => '0.01' ),
					'checkout_url'   => array( 'type' => 'url', 'label' => __( 'Checkout padrao da unidade', 'contorno' ) ),
					'prescricao_url' => array( 'type' => 'url', 'label' => __( 'URL de prescricao de treino', 'contorno' ) ),
					'plans'          => array(
						'type'     => 'repeater',
						'label'    => __( 'Planos da unidade', 'contorno' ),
						'subfields' => array(
							'id'           => array( 'type' => 'text', 'label' => __( 'ID', 'contorno' ) ),
							'name'         => array( 'type' => 'text', 'label' => __( 'Nome', 'contorno' ) ),
							'description'  => array( 'type' => 'text', 'label' => __( 'Descricao', 'contorno' ) ),
							'price'        => array( 'type' => 'number', 'label' => __( 'Preco', 'contorno' ), 'step' => '0.01' ),
							'price_label'  => array( 'type' => 'text', 'label' => __( 'Rotulo de preco', 'contorno' ) ),
							'benefits'     => array( 'type' => 'list', 'label' => __( 'Beneficios', 'contorno' ) ),
							'checkout_url' => array( 'type' => 'url', 'label' => __( 'URL de checkout', 'contorno' ) ),
							'badge'        => array( 'type' => 'text', 'label' => __( 'Badge', 'contorno' ) ),
							'featured'     => array( 'type' => 'checkbox', 'label' => __( 'Destaque', 'contorno' ) ),
						),
					),
				),
			),
			'status' => array(
				'label'  => __( 'Status e pre-venda', 'contorno' ),
				'fields' => array(
					'status'                 => array(
						'type'    => 'select',
						'label'   => __( 'Status', 'contorno' ),
						'options' => array(
							'open'     => __( 'Aberta', 'contorno' ),
							'pre_sale' => __( 'Pre-venda', 'contorno' ),
							'closed'   => __( 'Fechada', 'contorno' ),
						),
						'default' => 'open',
						'help'    => __( 'Ao voltar para "Aberta", todos os elementos de pre-venda desaparecem automaticamente.', 'contorno' ),
					),
					'presale_label'          => array( 'type' => 'text', 'label' => __( 'Pill sobre a foto', 'contorno' ), 'placeholder' => 'NOVA UNIDADE' ),
					'presale_opening_label'  => array( 'type' => 'text', 'label' => __( 'Rotulo de abertura', 'contorno' ), 'placeholder' => 'Pre-inauguracao' ),
					'presale_opening_date'   => array( 'type' => 'text', 'label' => __( 'Data de abertura (texto)', 'contorno' ), 'help' => __( 'So preencher com data real — nunca inventar inauguracao.', 'contorno' ) ),
					'presale_promo_text'     => array( 'type' => 'textarea', 'label' => __( 'Texto comercial de pre-venda', 'contorno' ) ),
				),
			),
			'aulas' => array(
				'label'  => __( 'Aulas coletivas (EVO / W12)', 'contorno' ),
				'fields' => array(
					'classes_enabled' => array( 'type' => 'checkbox', 'label' => __( 'Exibir grade de aulas coletivas', 'contorno' ) ),
					'evo_branch_id'   => array( 'type' => 'text', 'label' => __( 'ID da filial no EVO', 'contorno' ), 'help' => __( 'Se preenchido, a URL da agenda e derivada automaticamente.', 'contorno' ) ),
					'classes_url'     => array( 'type' => 'url', 'label' => __( 'URL da agenda (sobrepoe o ID)', 'contorno' ) ),
					'classes_title'   => array( 'type' => 'text', 'label' => __( 'Titulo da secao', 'contorno' ), 'placeholder' => 'Aulas Coletivas - Horarios' ),
				),
			),
			'integracao' => array(
				'label'  => __( 'Integracao', 'contorno' ),
				'fields' => array(
					'erp_id'      => array( 'type' => 'text', 'label' => __( 'ID no ERP', 'contorno' ) ),
					'sync_source' => array(
						'type'    => 'select',
						'label'   => __( 'Origem do cadastro', 'contorno' ),
						'options' => array(
							'manual' => __( 'Manual', 'contorno' ),
							'erp'    => __( 'ERP', 'contorno' ),
						),
						'default' => 'manual',
					),
					'ctn_slug'    => array( 'type' => 'text', 'label' => __( 'Slug da landing CTN vinculada', 'contorno' ) ),
				),
			),
			'editorial' => array(
				'label'  => __( 'Conteudo editorial extra', 'contorno' ),
				'help'   => __( 'O conteudo livre desta unidade e editado no WPBakery, no editor principal desta tela. Aqui voce escolhe apenas ONDE ele aparece no template compartilhado. A estrutura obrigatoria da pagina e preservada.', 'contorno' ),
				'fields' => array(
					'editorial_position' => array(
						'type'    => 'select',
						'label'   => __( 'Posicao do conteudo editorial', 'contorno' ),
						'options' => array(
							'none'          => __( 'Nao exibir', 'contorno' ),
							'after_hero'    => __( 'Depois do hero', 'contorno' ),
							'before_plans'  => __( 'Antes dos planos', 'contorno' ),
							'after_plans'   => __( 'Depois dos planos', 'contorno' ),
							'before_footer' => __( 'Antes do rodape', 'contorno' ),
						),
						'default' => 'before_plans',
					),
				),
			),
			'seo' => array(
				'label'  => __( 'SEO', 'contorno' ),
				'fields' => array(
					'seo_title'       => array( 'type' => 'text', 'label' => __( 'Title', 'contorno' ) ),
					'seo_description' => array( 'type' => 'textarea', 'label' => __( 'Meta description', 'contorno' ) ),
					'seo_image'       => array( 'type' => 'media', 'label' => __( 'Imagem social (og:image)', 'contorno' ) ),
				),
			),
		),

		CONTORNO_CPT_CTN => array(
			'identidade' => array(
				'label'  => __( 'Identidade', 'contorno' ),
				'fields' => array(
					'short_name' => array( 'type' => 'text', 'label' => __( 'Nome curto', 'contorno' ) ),
					'unit_slug'  => array( 'type' => 'text', 'label' => __( 'Slug da unidade em /unidades', 'contorno' ) ),
					'tagline'    => array( 'type' => 'text', 'label' => __( 'Tagline', 'contorno' ), 'placeholder' => 'YOUR ONLY LIMIT IS YOU' ),
				),
			),
			'hero' => array(
				'label'  => __( 'Hero (dark, sem header institucional)', 'contorno' ),
				'fields' => array(
					'hero_eyebrow'   => array( 'type' => 'text', 'label' => __( 'Eyebrow', 'contorno' ) ),
					'hero_title'     => array( 'type' => 'text', 'label' => __( 'Titulo', 'contorno' ) ),
					'hero_headline'  => array( 'type' => 'text', 'label' => __( 'Headline', 'contorno' ) ),
					'hero_subtitle'  => array( 'type' => 'textarea', 'label' => __( 'Subtitulo', 'contorno' ) ),
					'hero_image'     => array( 'type' => 'media', 'label' => __( 'Imagem do hero', 'contorno' ) ),
					'hero_image_alt' => array( 'type' => 'text', 'label' => __( 'Alt do hero', 'contorno' ) ),
				),
			),
			'puv' => array(
				'label'  => __( 'PUV (equipamento protagonista)', 'contorno' ),
				'fields' => array(
					'vp_image'     => array(
						'type'  => 'media',
						'label' => __( 'Imagem da PUV', 'contorno' ),
						'help'  => __( 'Maquina/equipamento premium em primeiro plano (preferencia Panatta). Nunca foto generica de academia.', 'contorno' ),
					),
					'vp_image_alt' => array( 'type' => 'text', 'label' => __( 'Alt da PUV', 'contorno' ) ),
					'intro_title'  => array( 'type' => 'text', 'label' => __( 'Titulo da introducao', 'contorno' ) ),
					'intro_body'   => array( 'type' => 'list', 'label' => __( 'Paragrafos da introducao', 'contorno' ) ),
					'stats'        => array(
						'type'      => 'repeater',
						'label'     => __( 'Numeros / stats', 'contorno' ),
						'subfields' => array(
							'value' => array( 'type' => 'text', 'label' => __( 'Valor', 'contorno' ) ),
							'label' => array( 'type' => 'text', 'label' => __( 'Rotulo', 'contorno' ) ),
						),
					),
					'highlights'   => array( 'type' => 'list', 'label' => __( 'Destaques curtos (cards do hub)', 'contorno' ) ),
				),
			),
			'sobre' => array(
				'label'  => __( 'Sobre (video vertical)', 'contorno' ),
				'fields' => array(
					'about_video_youtube' => array( 'type' => 'text', 'label' => __( 'ID do Short/video vertical no YouTube', 'contorno' ) ),
					'about_video_src'     => array( 'type' => 'url', 'label' => __( 'Arquivo de video local (prioritario)', 'contorno' ) ),
					'about_video_poster'  => array( 'type' => 'media', 'label' => __( 'Poster vertical', 'contorno' ) ),
					'about_video_title'   => array( 'type' => 'text', 'label' => __( 'Titulo do video', 'contorno' ) ),
					'about_video_caption' => array( 'type' => 'text', 'label' => __( 'Legenda sob o video', 'contorno' ) ),
				),
				'help' => __( 'A secao Sobre usa video vertical 9:16 — nao voltar para imagem estatica.', 'contorno' ),
			),
			'estrutura' => array(
				'label'  => __( 'Estrutura, galeria e marcas', 'contorno' ),
				'fields' => array(
					'structure_subtitle' => array( 'type' => 'textarea', 'label' => __( 'Subtitulo da estrutura', 'contorno' ) ),
					'features'           => array( 'type' => 'list', 'label' => __( 'Itens de estrutura', 'contorno' ) ),
					'gallery'            => array( 'type' => 'media_list', 'label' => __( 'Galeria', 'contorno' ) ),
					'brands'             => array(
						'type'      => 'repeater',
						'label'     => __( 'Marcas de equipamento', 'contorno' ),
						'subfields' => array(
							'id'   => array( 'type' => 'text', 'label' => __( 'ID', 'contorno' ) ),
							'name' => array( 'type' => 'text', 'label' => __( 'Nome', 'contorno' ) ),
							'logo' => array( 'type' => 'media', 'label' => __( 'Logo', 'contorno' ) ),
						),
					),
					'equipment'          => array(
						'type'      => 'repeater',
						'label'     => __( 'Destaques de equipamento', 'contorno' ),
						'subfields' => array(
							'id'         => array( 'type' => 'text', 'label' => __( 'ID', 'contorno' ) ),
							'brand'      => array( 'type' => 'text', 'label' => __( 'Marca', 'contorno' ) ),
							'title'      => array( 'type' => 'text', 'label' => __( 'Titulo', 'contorno' ) ),
							'paragraphs' => array( 'type' => 'list', 'label' => __( 'Paragrafos', 'contorno' ) ),
							'video_id'   => array( 'type' => 'text', 'label' => __( 'ID do video', 'contorno' ) ),
							'image'      => array( 'type' => 'media', 'label' => __( 'Imagem', 'contorno' ) ),
						),
					),
				),
			),
			'videos' => array(
				'label'  => __( 'Videos (Ronnie)', 'contorno' ),
				'fields' => array(
					'featured_video_id'    => array( 'type' => 'text', 'label' => __( 'Video principal (ID YouTube)', 'contorno' ) ),
					'featured_video_title' => array( 'type' => 'text', 'label' => __( 'Titulo do video principal', 'contorno' ) ),
					'video_playlist_url'   => array(
						'type'  => 'url',
						'label' => __( 'URL da playlist "Ver mais"', 'contorno' ),
						'help'  => __( 'Playlist real do canal. Nao inventar playlist inexistente.', 'contorno' ),
					),
				),
				'help' => __( 'Um video principal + box vertical "Ver mais" com link de playlist. Nao trazer todos os videos para a landing.', 'contorno' ),
			),
			'localizacao' => array(
				'label'  => __( 'Localizacao e horarios', 'contorno' ),
				'fields' => array(
					'city'                => array( 'type' => 'text', 'label' => __( 'Cidade', 'contorno' ) ),
					'state'               => array(
						'type'    => 'select',
						'label'   => __( 'Estado', 'contorno' ),
						'options' => array( 'MG' => 'MG', 'SP' => 'SP' ),
						'default' => 'MG',
					),
					'neighborhood'        => array( 'type' => 'text', 'label' => __( 'Bairro', 'contorno' ) ),
					'address'             => array( 'type' => 'text', 'label' => __( 'Endereco', 'contorno' ) ),
					'address_complement'  => array( 'type' => 'text', 'label' => __( 'Complemento', 'contorno' ) ),
					'postal_code'         => array( 'type' => 'text', 'label' => __( 'CEP', 'contorno' ) ),
					'maps_query'          => array( 'type' => 'text', 'label' => __( 'Busca no Google Maps', 'contorno' ) ),
					'map_embed_url'       => array( 'type' => 'url', 'label' => __( 'URL do mapa embutido', 'contorno' ) ),
					'phone'               => array( 'type' => 'text', 'label' => __( 'Telefone', 'contorno' ) ),
					'whatsapp'            => array( 'type' => 'text', 'label' => __( 'WhatsApp', 'contorno' ) ),
					'opening_hours'       => array(
						'type'      => 'repeater',
						'label'     => __( 'Horarios', 'contorno' ),
						'subfields' => array(
							'label' => array( 'type' => 'text', 'label' => __( 'Dias', 'contorno' ) ),
							'hours' => array( 'type' => 'text', 'label' => __( 'Horario', 'contorno' ) ),
						),
					),
				),
			),
			'planos' => array(
				'label'  => __( 'Planos (skin dark CTN)', 'contorno' ),
				'fields' => array(
					'plans' => array(
						'type'      => 'repeater',
						'label'     => __( 'Planos', 'contorno' ),
						'subfields' => array(
							'id'              => array( 'type' => 'text', 'label' => __( 'ID', 'contorno' ) ),
							'name'            => array( 'type' => 'text', 'label' => __( 'Nome', 'contorno' ) ),
							'price_from'      => array( 'type' => 'number', 'label' => __( 'Preco "de" (riscado)', 'contorno' ), 'step' => '0.01' ),
							'price'           => array( 'type' => 'number', 'label' => __( 'Preco promocional', 'contorno' ), 'step' => '0.01' ),
							'price_note'      => array( 'type' => 'text', 'label' => __( 'Nota do preco', 'contorno' ) ),
							'recurring_price' => array( 'type' => 'number', 'label' => __( 'Meses seguintes', 'contorno' ), 'step' => '0.01' ),
							'recurring_from'  => array( 'type' => 'number', 'label' => __( 'Meses seguintes "de"', 'contorno' ), 'step' => '0.01' ),
							'enrollment_fee'  => array( 'type' => 'number', 'label' => __( 'Taxa de matricula', 'contorno' ), 'step' => '0.01' ),
							'fidelity'        => array( 'type' => 'text', 'label' => __( 'Fidelidade', 'contorno' ) ),
							'card_note'       => array( 'type' => 'text', 'label' => __( 'Nota do cartao', 'contorno' ) ),
							'benefits'        => array( 'type' => 'list', 'label' => __( 'Beneficios', 'contorno' ) ),
							'checkout_url'    => array( 'type' => 'url', 'label' => __( 'URL de checkout', 'contorno' ) ),
							'badge'           => array( 'type' => 'text', 'label' => __( 'Badge', 'contorno' ) ),
							'featured'        => array( 'type' => 'checkbox', 'label' => __( 'Destaque', 'contorno' ) ),
						),
					),
				),
			),
			'aulas' => array(
				'label'  => __( 'Aulas coletivas (EVO / W12)', 'contorno' ),
				'fields' => array(
					'classes_enabled' => array( 'type' => 'checkbox', 'label' => __( 'Exibir grade de aulas coletivas', 'contorno' ) ),
					'evo_branch_id'   => array( 'type' => 'text', 'label' => __( 'ID da filial no EVO', 'contorno' ) ),
					'classes_url'     => array( 'type' => 'url', 'label' => __( 'URL da agenda', 'contorno' ) ),
					'classes_title'   => array( 'type' => 'text', 'label' => __( 'Titulo da secao', 'contorno' ) ),
				),
			),
			'editorial' => array(
				'label'  => __( 'Conteudo editorial extra', 'contorno' ),
				'help'   => __( 'O conteudo livre desta CTN e editado no WPBakery, no editor principal desta tela. Aqui voce escolhe apenas ONDE ele aparece no template compartilhado.', 'contorno' ),
				'fields' => array(
					'editorial_position' => array(
						'type'    => 'select',
						'label'   => __( 'Posicao do conteudo editorial', 'contorno' ),
						'options' => array(
							'none'          => __( 'Nao exibir', 'contorno' ),
							'after_hero'    => __( 'Depois do hero', 'contorno' ),
							'before_plans'  => __( 'Antes dos planos', 'contorno' ),
							'after_plans'   => __( 'Depois dos planos', 'contorno' ),
							'before_footer' => __( 'Antes do rodape', 'contorno' ),
						),
						'default' => 'none',
					),
				),
			),
			'cta' => array(
				'label'  => __( 'CTA final', 'contorno' ),
				'fields' => array(
					'final_cta_headline' => array( 'type' => 'text', 'label' => __( 'Headline do CTA final', 'contorno' ) ),
					'final_cta_image'    => array( 'type' => 'media', 'label' => __( 'Imagem do CTA final', 'contorno' ) ),
				),
			),
			'seo' => array(
				'label'  => __( 'SEO', 'contorno' ),
				'fields' => array(
					'seo_title'       => array( 'type' => 'text', 'label' => __( 'Title', 'contorno' ) ),
					'seo_description' => array( 'type' => 'textarea', 'label' => __( 'Meta description', 'contorno' ) ),
					'seo_image'       => array( 'type' => 'media', 'label' => __( 'Imagem social (og:image)', 'contorno' ) ),
				),
			),
		),
	);

	/**
	 * Permite extender o esquema (ex.: um plugin de integracao com ERP).
	 *
	 * @param array<string,array<string,mixed>> $schema
	 */
	$schema = (array) apply_filters( 'contorno_field_schema', $schema );

	return $schema;
}

/**
 * Achata o esquema em name => definicao para um post type.
 *
 * @return array<string,array<string,mixed>>
 */
function contorno_flat_fields( string $post_type ): array {
	static $cache = array();

	if ( isset( $cache[ $post_type ] ) ) {
		return $cache[ $post_type ];
	}

	$schema = contorno_field_schema();
	$flat   = array();

	if ( isset( $schema[ $post_type ] ) ) {
		foreach ( $schema[ $post_type ] as $group ) {
			if ( ! isset( $group['fields'] ) || ! is_array( $group['fields'] ) ) {
				continue;
			}
			foreach ( $group['fields'] as $name => $definition ) {
				$flat[ $name ] = $definition;
			}
		}
	}

	$cache[ $post_type ] = $flat;

	return $flat;
}

function contorno_meta_key( string $field ): string {
	return CONTORNO_META_PREFIX . $field;
}

/**
 * Tipos que guardam estrutura em JSON num unico meta.
 *
 * @return string[]
 */
function contorno_json_field_types(): array {
	return array( 'list', 'repeater', 'media_list' );
}

/**
 * Registra os metas no WordPress (REST + revisoes + autorizacao).
 *
 * Prioridade 11: DEPOIS de contorno_register_post_types() (prioridade 10).
 * Registrar antes faz o WordPress avisar que o subtipo nao suporta revisoes,
 * porque o post type ainda nao existe.
 */
add_action(
	'init',
	static function (): void {
		foreach ( contorno_field_schema() as $post_type => $groups ) {
			foreach ( contorno_flat_fields( $post_type ) as $name => $definition ) {
				$type    = (string) ( $definition['type'] ?? 'text' );
				$is_json = in_array( $type, contorno_json_field_types(), true );

				$meta_type = 'string';
				if ( 'number' === $type ) {
					$meta_type = 'number';
				} elseif ( 'checkbox' === $type ) {
					$meta_type = 'boolean';
				}

				register_post_meta(
					$post_type,
					contorno_meta_key( $name ),
					array(
						'type'              => $is_json ? 'string' : $meta_type,
						'single'            => true,
						'show_in_rest'      => true,
						'revisions_enabled' => true,
						'auth_callback'     => static function () use ( $post_type ): bool {
							$object = get_post_type_object( $post_type );
							$cap    = $object && isset( $object->cap->edit_posts ) ? $object->cap->edit_posts : 'edit_posts';

							return current_user_can( $cap );
						},
					)
				);
			}
		}
	},
	11
);
