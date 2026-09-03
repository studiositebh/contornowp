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
				'label'  => __( 'Dados gerais', 'contorno' ),
				'help'   => __( 'O nome que aparece no site é o título no topo desta tela. O endereço da página vem do Slug, na caixa "Slug".', 'contorno' ),
				'fields' => array(
					'short_name'        => array(
						'type'  => 'text',
						'label' => __( 'Nome curto', 'contorno' ),
						'help'  => __( 'Só o bairro ou a cidade, sem "Contorno do Corpo". Usado em espaços estreitos.', 'contorno' ),
					),
					'kind'              => array(
						'type'    => 'select',
						'label'   => __( 'Tipo de unidade', 'contorno' ),
						'options' => array(
							'standard'  => __( 'Padrão', 'contorno' ),
							'prime'     => __( 'Prime', 'contorno' ),
							'ctn-prime' => __( 'CTN / Centro de Treinamento', 'contorno' ),
						),
						'default' => 'standard',
						'help'    => __( 'Define o selo do card e o agrupamento na listagem.', 'contorno' ),
					),
					'badge'             => array(
						'type'  => 'text',
						'label' => __( 'Selo do card', 'contorno' ),
						'help'  => __( 'Texto curto sobre a foto na listagem, por exemplo "Nova" ou "Mais procurada". Deixe vazio para não exibir selo. Em pré-venda este campo é ignorado.', 'contorno' ),
					),
					'featured'          => array(
						'type'  => 'checkbox',
						'label' => __( 'Destacar na Home', 'contorno' ),
						'help'  => __( 'Marque para esta unidade aparecer no bloco de destaques da Home. A ordem segue o campo "Ordem" da caixa Atributos.', 'contorno' ),
					),
					'short_description' => array(
						'type'  => 'textarea',
						'label' => __( 'Descrição curta (card)', 'contorno' ),
						'help'  => __( 'Uma ou duas linhas. Aparece no card da listagem e abaixo do título no hero.', 'contorno' ),
					),
				),
			),
			'localizacao' => array(
				'label'  => __( 'Localização e contato', 'contorno' ),
				'fields' => array(
					'city'         => array( 'type' => 'text', 'label' => __( 'Cidade', 'contorno' ) ),
					'state'        => array(
						'type'    => 'select',
						'label'   => __( 'Estado', 'contorno' ),
						'options' => array( 'MG' => 'MG', 'SP' => 'SP' ),
						'default' => 'MG',
					),
					'neighborhood' => array( 'type' => 'text', 'label' => __( 'Bairro', 'contorno' ) ),
					'address'      => array( 'type' => 'text', 'label' => __( 'Endereço', 'contorno' ) ),
					'postal_code'  => array( 'type' => 'text', 'label' => __( 'CEP', 'contorno' ) ),
					'phone'        => array( 'type' => 'text', 'label' => __( 'Telefone', 'contorno' ) ),
					'whatsapp'     => array( 'type' => 'text', 'label' => __( 'WhatsApp', 'contorno' ) ),
					'hours'        => array(
						'type'  => 'text',
						'label' => __( 'Horário de funcionamento', 'contorno' ),
						'help'  => __( 'Texto livre, exibido na faixa informativa. Ex.: "Seg a sex 5h às 23h · Sáb 8h às 14h".', 'contorno' ),
					),
					'maps_query'   => array(
						'type'  => 'text',
						'label' => __( 'Busca no Google Maps', 'contorno' ),
						'help'  => __( 'O que o site pesquisa no Maps para montar o mapa e o botão "Ver no mapa". Se ficar vazio, usa o endereço acima.', 'contorno' ),
					),
					'latitude'     => array(
						'type'  => 'number',
						'label' => __( 'Latitude', 'contorno' ),
						'step'  => 'any',
						'help'  => __( 'Opcional. Melhora a ficha da academia no Google. Preencha os dois ou nenhum.', 'contorno' ),
					),
					'longitude'    => array( 'type' => 'number', 'label' => __( 'Longitude', 'contorno' ), 'step' => 'any' ),
				),
			),
			'midia' => array(
				'label'  => __( 'Hero, galeria e vídeo', 'contorno' ),
				'help'   => __( 'Clique em "Selecionar da biblioteca" para trocar qualquer imagem. Nunca é preciso mexer em arquivo ou código.', 'contorno' ),
				'fields' => array(
					'image'     => array(
						'type'  => 'media',
						'label' => __( 'Imagem principal (hero e card)', 'contorno' ),
						'help'  => __( 'Foto horizontal, de preferência a partir de 1600px de largura. Sem foto, o card usa o gradiente da marca com a legenda "Foto em breve".', 'contorno' ),
					),
					'image_alt' => array(
						'type'  => 'text',
						'label' => __( 'Texto alternativo da imagem principal', 'contorno' ),
						'help'  => __( 'Descreve a foto para leitores de tela e para o Google.', 'contorno' ),
					),
					'gallery'   => array(
						'type'  => 'media_list',
						'label' => __( 'Galeria', 'contorno' ),
						'help'  => __( 'Uma imagem por linha. Abre em tela cheia no site.', 'contorno' ),
					),
					'video_url' => array(
						'type'  => 'url',
						'label' => __( 'Vídeo da unidade', 'contorno' ),
						'help'  => __( 'Link do YouTube ou de um arquivo de vídeo. Deixe vazio para esconder a seção de vídeo.', 'contorno' ),
					),
				),
			),
			'conteudo' => array(
				'label'  => __( 'Destaques e diferenciais', 'contorno' ),
				'help'   => __( 'Listas simples: um item por linha. Os ícones dos Destaques são escolhidos automaticamente pelo texto do item.', 'contorno' ),
				'fields' => array(
					'facilities'    => array(
						'type'  => 'list',
						'label' => __( 'Destaques Contorno (cards com ícone)', 'contorno' ),
						'help'  => __( 'Itens curtos, de uma a três palavras. Ex.: "Estacionamento", "Aulas coletivas", "Vestiário".', 'contorno' ),
					),
					'differentials' => array(
						'type'  => 'list',
						'label' => __( 'Diferenciais (faixa escura)', 'contorno' ),
						'help'  => __( 'Frases um pouco mais longas que os Destaques.', 'contorno' ),
					),
					'modalities'    => array(
						'type'  => 'list',
						'label' => __( 'Modalidades', 'contorno' ),
						'help'  => __( 'Usado em buscas e dados estruturados.', 'contorno' ),
					),
				),
			),
			'planos' => array(
				'label'  => __( 'Planos e valores', 'contorno' ),
				'help'   => __( 'Área comercial. Tudo aqui aparece nos cards de plano da página da unidade — preço, condição, benefícios, selo e botão de checkout. Nenhum valor está fixo no código.', 'contorno' ),
				'fields' => array(
					'starting_price' => array(
						'type'  => 'number',
						'label' => __( 'Preço "a partir de"', 'contorno' ),
						'step'  => '0.01',
						'help'  => __( 'Valor exibido no card da listagem. Se deixar vazio, o site usa automaticamente o menor preço entre os planos abaixo.', 'contorno' ),
					),
					'checkout_url'   => array(
						'type'  => 'url',
						'label' => __( 'Checkout padrão da unidade', 'contorno' ),
						'help'  => __( 'Usado pelo botão "Matricule-se" do hero e por qualquer plano sem URL própria.', 'contorno' ),
					),
					'plans'          => array(
						'type'      => 'repeater',
						'label'     => __( 'Planos da unidade', 'contorno' ),
						'help'      => __( 'Arraste para reordenar não está disponível: a ordem é a de cadastro. Remova e adicione para reorganizar.', 'contorno' ),
						'subfields' => array(
							'id'           => array( 'type' => 'text', 'label' => __( 'Identificador', 'contorno' ) ),
							'name'         => array( 'type' => 'text', 'label' => __( 'Nome do plano', 'contorno' ) ),
							'description'  => array( 'type' => 'text', 'label' => __( 'Descrição', 'contorno' ) ),
							'price'        => array( 'type' => 'number', 'label' => __( 'Preço', 'contorno' ), 'step' => '0.01' ),
							'price_label'  => array( 'type' => 'text', 'label' => __( 'Texto no lugar do preço', 'contorno' ) ),
							'benefits'     => array( 'type' => 'list', 'label' => __( 'Benefícios', 'contorno' ) ),
							'checkout_url' => array( 'type' => 'url', 'label' => __( 'URL de checkout', 'contorno' ) ),
							'badge'        => array( 'type' => 'text', 'label' => __( 'Selo', 'contorno' ) ),
							'featured'     => array( 'type' => 'checkbox', 'label' => __( 'Plano destacado', 'contorno' ) ),
						),
					),
				),
			),
			'status' => array(
				'label'  => __( 'Status e pré-venda', 'contorno' ),
				'help'   => __( 'Enquanto o status for "Pré-venda", a unidade exibe a tarja sobre a foto e a faixa PRÉ-VENDA no hero. Ao mudar para "Aberta", os dois desaparecem do site na hora — não é preciso apagar os campos abaixo.', 'contorno' ),
				'fields' => array(
					'status'                 => array(
						'type'    => 'select',
						'label'   => __( 'Status', 'contorno' ),
						'options' => array(
							'open'     => __( 'Aberta', 'contorno' ),
							'pre_sale' => __( 'Pré-venda', 'contorno' ),
							'closed'   => __( 'Fechada', 'contorno' ),
						),
						'default' => 'open',
						'help'    => __( 'Ao voltar para "Aberta", todos os elementos de pré-venda desaparecem automaticamente.', 'contorno' ),
					),
					'presale_label'          => array( 'type' => 'text', 'label' => __( 'Pill sobre a foto', 'contorno' ), 'placeholder' => 'NOVA UNIDADE' ),
					'presale_opening_label'  => array( 'type' => 'text', 'label' => __( 'Rótulo de abertura', 'contorno' ), 'placeholder' => 'Pre-inauguracao' ),
					'presale_opening_date'   => array( 'type' => 'text', 'label' => __( 'Data de abertura (texto)', 'contorno' ), 'help' => __( 'Só preencher com data real — nunca inventar inauguração.', 'contorno' ) ),
					'presale_promo_text'     => array( 'type' => 'textarea', 'label' => __( 'Texto comercial de pré-venda', 'contorno' ) ),
				),
			),
			'aulas' => array(
				'label'  => __( 'Aulas coletivas (EVO / W12)', 'contorno' ),
				'help'   => __( 'A grade de horários vem pronta do sistema EVO da Contorno. Aqui você só liga a seção e diz qual é a filial — horário, professor e vaga são administrados no próprio EVO, nunca neste painel.', 'contorno' ),
				'fields' => array(
					'classes_enabled' => array( 'type' => 'checkbox', 'label' => __( 'Exibir grade de aulas coletivas', 'contorno' ) ),
					'evo_branch_id'   => array( 'type' => 'text', 'label' => __( 'ID da filial no EVO', 'contorno' ), 'help' => __( 'Se preenchido, a URL da agenda é derivada automaticamente.', 'contorno' ) ),
					'classes_url'     => array( 'type' => 'url', 'label' => __( 'URL da agenda (sobrepõe o ID)', 'contorno' ) ),
					'classes_title'   => array( 'type' => 'text', 'label' => __( 'Título da seção', 'contorno' ), 'placeholder' => 'Aulas Coletivas - Horarios' ),
				),
			),
			'integracao' => array(
				'label'  => __( 'Integrações e links de sistema', 'contorno' ),
				'help'   => __( 'Campos técnicos. Normalmente só mudam quando algum sistema externo muda.', 'contorno' ),
				'fields' => array(
					'prescricao_url' => array(
						'type'  => 'url',
						'label' => __( 'URL de prescrição de treino', 'contorno' ),
						'help'  => __( 'Destino do botão quando a listagem estiver no modo prescrição.', 'contorno' ),
					),
					'erp_id'         => array(
						'type'  => 'text',
						'label' => __( 'ID no ERP', 'contorno' ),
						'help'  => __( 'Reservado para a futura sincronização automática. Pode ficar vazio.', 'contorno' ),
					),
					'sync_source'    => array(
						'type'    => 'select',
						'label'   => __( 'Origem do cadastro', 'contorno' ),
						'options' => array(
							'manual' => __( 'Manual', 'contorno' ),
							'erp'    => __( 'ERP', 'contorno' ),
						),
						'default' => 'manual',
						'help'    => __( 'Marque "ERP" apenas quando a unidade passar a ser atualizada por integração.', 'contorno' ),
					),
					'ctn_slug'       => array(
						'type'  => 'text',
						'label' => __( 'CTN vinculada (slug)', 'contorno' ),
						'help'  => __( 'Preencha somente se esta unidade também tiver uma landing CTN, por exemplo "castelo".', 'contorno' ),
					),
				),
			),
			'editorial' => array(
				'label'  => __( 'Conteúdo editorial extra', 'contorno' ),
				'help'   => __( 'O conteúdo livre desta unidade é editado no WPBakery, no editor principal desta tela. Aqui você escolhe apenas ONDE ele aparece no template compartilhado. A estrutura obrigatória da página é preservada.', 'contorno' ),
				'fields' => array(
					'editorial_position' => array(
						'type'    => 'select',
						'label'   => __( 'Posição do conteúdo editorial', 'contorno' ),
						'options' => array(
							'none'          => __( 'Não exibir', 'contorno' ),
							'after_hero'    => __( 'Depois do hero', 'contorno' ),
							'before_plans'  => __( 'Antes dos planos', 'contorno' ),
							'after_plans'   => __( 'Depois dos planos', 'contorno' ),
							'before_footer' => __( 'Antes do rodapé', 'contorno' ),
						),
						'default' => 'before_plans',
					),
				),
			),
			'seo' => array(
				'label'  => __( 'Busca e compartilhamento (SEO)', 'contorno' ),
				'help'   => __( 'Todos opcionais: deixando vazio, o site monta sozinho a partir do título, do resumo e da imagem principal.', 'contorno' ),
				'fields' => array(
					'seo_title'       => array(
						'type'  => 'text',
						'label' => __( 'Título no Google', 'contorno' ),
						'help'  => __( 'Ideal até 60 caracteres.', 'contorno' ),
					),
					'seo_description' => array(
						'type'  => 'textarea',
						'label' => __( 'Descrição no Google', 'contorno' ),
						'help'  => __( 'Ideal entre 120 e 155 caracteres.', 'contorno' ),
					),
					'seo_image'       => array(
						'type'  => 'media',
						'label' => __( 'Imagem ao compartilhar', 'contorno' ),
						'help'  => __( 'Aparece no WhatsApp, Instagram e Facebook. Sem imagem própria, usa a imagem principal e, na falta dela, o logo oficial de compartilhamento.', 'contorno' ),
					),
				),
			),
		),

		CONTORNO_CPT_CTN => array(
			'identidade' => array(
				'label'  => __( 'Dados gerais', 'contorno' ),
				'help'   => __( 'Esta tela controla a landing dark de /ctn/{slug}. A academia correspondente em /unidades tem cadastro próprio, em Unidades.', 'contorno' ),
				'fields' => array(
					'short_name' => array(
						'type'  => 'text',
						'label' => __( 'Nome curto', 'contorno' ),
						'help'  => __( 'Ex.: "Castelo". Usado em espaços estreitos.', 'contorno' ),
					),
					'unit_slug'  => array(
						'type'  => 'text',
						'label' => __( 'Unidade correspondente (slug)', 'contorno' ),
						'help'  => __( 'Slug da academia em /unidades, por exemplo "ctn-castelo". É o que liga as duas páginas.', 'contorno' ),
					),
					'tagline'    => array(
						'type'        => 'text',
						'label'       => __( 'Tagline', 'contorno' ),
						'placeholder' => 'YOUR ONLY LIMIT IS YOU',
						'help'        => __( 'Deixe vazio para usar a tagline oficial CTN.', 'contorno' ),
					),
				),
			),
			'hero' => array(
				'label'  => __( 'Hero (dark, sem header institucional)', 'contorno' ),
				'fields' => array(
					'hero_eyebrow'   => array( 'type' => 'text', 'label' => __( 'Eyebrow', 'contorno' ) ),
					'hero_title'     => array( 'type' => 'text', 'label' => __( 'Título', 'contorno' ) ),
					'hero_headline'  => array( 'type' => 'text', 'label' => __( 'Headline', 'contorno' ) ),
					'hero_subtitle'  => array( 'type' => 'textarea', 'label' => __( 'Subtítulo', 'contorno' ) ),
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
						'help'  => __( 'Máquina/equipamento premium em primeiro plano (preferencia Panatta). Nunca foto genérica de academia.', 'contorno' ),
					),
					'vp_image_alt' => array( 'type' => 'text', 'label' => __( 'Alt da PUV', 'contorno' ) ),
					'intro_title'  => array( 'type' => 'text', 'label' => __( 'Título da introducao', 'contorno' ) ),
					'intro_body'   => array( 'type' => 'list', 'label' => __( 'Parágrafos da introducao', 'contorno' ) ),
					'stats'        => array(
						'type'      => 'repeater',
						'label'     => __( 'Números / stats', 'contorno' ),
						'subfields' => array(
							'value' => array( 'type' => 'text', 'label' => __( 'Valor', 'contorno' ) ),
							'label' => array( 'type' => 'text', 'label' => __( 'Rótulo', 'contorno' ) ),
						),
					),
					'highlights'   => array( 'type' => 'list', 'label' => __( 'Destaques curtos (cards do hub)', 'contorno' ) ),
				),
			),
			'sobre' => array(
				'label'  => __( 'Sobre (vídeo vertical)', 'contorno' ),
				'fields' => array(
					'about_video_youtube' => array( 'type' => 'text', 'label' => __( 'ID do Short/vídeo vertical no YouTube', 'contorno' ) ),
					'about_video_src'     => array( 'type' => 'url', 'label' => __( 'Arquivo de vídeo local (prioritario)', 'contorno' ) ),
					'about_video_poster'  => array( 'type' => 'media', 'label' => __( 'Poster vertical', 'contorno' ) ),
					'about_video_title'   => array( 'type' => 'text', 'label' => __( 'Título do vídeo', 'contorno' ) ),
					'about_video_caption' => array( 'type' => 'text', 'label' => __( 'Legenda sob o vídeo', 'contorno' ) ),
				),
				'help' => __( 'A seção Sobre usa vídeo vertical 9:16 — não voltar para imagem estatica.', 'contorno' ),
			),
			'estrutura' => array(
				'label'  => __( 'Estrutura, galeria e marcas', 'contorno' ),
				'fields' => array(
					'structure_subtitle' => array( 'type' => 'textarea', 'label' => __( 'Subtítulo da estrutura', 'contorno' ) ),
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
							'title'      => array( 'type' => 'text', 'label' => __( 'Título', 'contorno' ) ),
							'paragraphs' => array( 'type' => 'list', 'label' => __( 'Parágrafos', 'contorno' ) ),
							'video_id'   => array( 'type' => 'text', 'label' => __( 'ID do vídeo', 'contorno' ) ),
							'image'      => array( 'type' => 'media', 'label' => __( 'Imagem', 'contorno' ) ),
						),
					),
				),
			),
			'videos' => array(
				'label'  => __( 'Vídeos (Ronnie)', 'contorno' ),
				'fields' => array(
					'featured_video_id'    => array( 'type' => 'text', 'label' => __( 'Vídeo principal (ID YouTube)', 'contorno' ) ),
					'featured_video_title' => array( 'type' => 'text', 'label' => __( 'Título do vídeo principal', 'contorno' ) ),
					'video_playlist_url'   => array(
						'type'  => 'url',
						'label' => __( 'URL da playlist "Ver mais"', 'contorno' ),
						'help'  => __( 'Playlist real do canal. Não inventar playlist inexistente.', 'contorno' ),
					),
				),
				'help' => __( 'Um vídeo principal + box vertical "Ver mais" com link de playlist. Não trazer todos os vídeos para a landing.', 'contorno' ),
			),
			'localizacao' => array(
				'label'  => __( 'Localização e horários', 'contorno' ),
				'fields' => array(
					'city'                => array( 'type' => 'text', 'label' => __( 'Cidade', 'contorno' ) ),
					'state'               => array(
						'type'    => 'select',
						'label'   => __( 'Estado', 'contorno' ),
						'options' => array( 'MG' => 'MG', 'SP' => 'SP' ),
						'default' => 'MG',
					),
					'neighborhood'        => array( 'type' => 'text', 'label' => __( 'Bairro', 'contorno' ) ),
					'address'             => array( 'type' => 'text', 'label' => __( 'Endereço', 'contorno' ) ),
					'address_complement'  => array( 'type' => 'text', 'label' => __( 'Complemento', 'contorno' ) ),
					'postal_code'         => array( 'type' => 'text', 'label' => __( 'CEP', 'contorno' ) ),
					'maps_query'          => array( 'type' => 'text', 'label' => __( 'Busca no Google Maps', 'contorno' ) ),
					'map_embed_url'       => array( 'type' => 'url', 'label' => __( 'URL do mapa embutido', 'contorno' ) ),
					'phone'               => array( 'type' => 'text', 'label' => __( 'Telefone', 'contorno' ) ),
					'whatsapp'            => array( 'type' => 'text', 'label' => __( 'WhatsApp', 'contorno' ) ),
					'opening_hours'       => array(
						'type'      => 'repeater',
						'label'     => __( 'Horários', 'contorno' ),
						'subfields' => array(
							'label' => array( 'type' => 'text', 'label' => __( 'Dias', 'contorno' ) ),
							'hours' => array( 'type' => 'text', 'label' => __( 'Horário', 'contorno' ) ),
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
							'price_from'      => array( 'type' => 'number', 'label' => __( 'Preço "de" (riscado)', 'contorno' ), 'step' => '0.01' ),
							'price'           => array( 'type' => 'number', 'label' => __( 'Preço promocional', 'contorno' ), 'step' => '0.01' ),
							'price_note'      => array( 'type' => 'text', 'label' => __( 'Nota do preço', 'contorno' ) ),
							'recurring_price' => array( 'type' => 'number', 'label' => __( 'Meses seguintes', 'contorno' ), 'step' => '0.01' ),
							'recurring_from'  => array( 'type' => 'number', 'label' => __( 'Meses seguintes "de"', 'contorno' ), 'step' => '0.01' ),
							'enrollment_fee'  => array( 'type' => 'number', 'label' => __( 'Taxa de matrícula', 'contorno' ), 'step' => '0.01' ),
							'fidelity'        => array( 'type' => 'text', 'label' => __( 'Fidelidade', 'contorno' ) ),
							'card_note'       => array( 'type' => 'text', 'label' => __( 'Nota do cartao', 'contorno' ) ),
							'benefits'        => array( 'type' => 'list', 'label' => __( 'Benefícios', 'contorno' ) ),
							'checkout_url'    => array( 'type' => 'url', 'label' => __( 'URL de checkout', 'contorno' ) ),
							'badge'           => array( 'type' => 'text', 'label' => __( 'Badge', 'contorno' ) ),
							'featured'        => array( 'type' => 'checkbox', 'label' => __( 'Destaque', 'contorno' ) ),
						),
					),
				),
			),
			'aulas' => array(
				'label'  => __( 'Aulas coletivas (EVO / W12)', 'contorno' ),
				'help'   => __( 'A grade de horários vem pronta do sistema EVO da Contorno. Aqui você só liga a seção e diz qual é a filial — horário, professor e vaga são administrados no próprio EVO, nunca neste painel.', 'contorno' ),
				'fields' => array(
					'classes_enabled' => array( 'type' => 'checkbox', 'label' => __( 'Exibir grade de aulas coletivas', 'contorno' ) ),
					'evo_branch_id'   => array( 'type' => 'text', 'label' => __( 'ID da filial no EVO', 'contorno' ) ),
					'classes_url'     => array( 'type' => 'url', 'label' => __( 'URL da agenda', 'contorno' ) ),
					'classes_title'   => array( 'type' => 'text', 'label' => __( 'Título da seção', 'contorno' ) ),
				),
			),
			'editorial' => array(
				'label'  => __( 'Conteúdo editorial extra', 'contorno' ),
				'help'   => __( 'O conteúdo livre desta CTN é editado no WPBakery, no editor principal desta tela. Aqui você escolhe apenas ONDE ele aparece no template compartilhado.', 'contorno' ),
				'fields' => array(
					'editorial_position' => array(
						'type'    => 'select',
						'label'   => __( 'Posição do conteúdo editorial', 'contorno' ),
						'options' => array(
							'none'          => __( 'Não exibir', 'contorno' ),
							'after_hero'    => __( 'Depois do hero', 'contorno' ),
							'before_plans'  => __( 'Antes dos planos', 'contorno' ),
							'after_plans'   => __( 'Depois dos planos', 'contorno' ),
							'before_footer' => __( 'Antes do rodapé', 'contorno' ),
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
				'label'  => __( 'Busca e compartilhamento (SEO)', 'contorno' ),
				'help'   => __( 'Todos opcionais: deixando vazio, o site monta sozinho a partir do título, do resumo e da imagem principal.', 'contorno' ),
				'fields' => array(
					'seo_title'       => array(
						'type'  => 'text',
						'label' => __( 'Título no Google', 'contorno' ),
						'help'  => __( 'Ideal até 60 caracteres.', 'contorno' ),
					),
					'seo_description' => array(
						'type'  => 'textarea',
						'label' => __( 'Descrição no Google', 'contorno' ),
						'help'  => __( 'Ideal entre 120 e 155 caracteres.', 'contorno' ),
					),
					'seo_image'       => array(
						'type'  => 'media',
						'label' => __( 'Imagem ao compartilhar', 'contorno' ),
						'help'  => __( 'Aparece no WhatsApp, Instagram e Facebook. Sem imagem própria, usa a imagem principal e, na falta dela, o logo oficial de compartilhamento.', 'contorno' ),
					),
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
