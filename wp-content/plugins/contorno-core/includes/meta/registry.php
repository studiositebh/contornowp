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
 * As 27 UFs do Brasil, pra nao restringir o cadastro de unidade a MG/SP
 * (a lista antiga so tinha essas duas — coincidencia de quais unidades
 * existiam quando o campo foi criado, nao uma regra de negocio).
 *
 * @return array<string,string>
 */
function contorno_brazilian_states(): array {
	return array(
		'AC' => 'AC', 'AL' => 'AL', 'AP' => 'AP', 'AM' => 'AM', 'BA' => 'BA',
		'CE' => 'CE', 'DF' => 'DF', 'ES' => 'ES', 'GO' => 'GO', 'MA' => 'MA',
		'MT' => 'MT', 'MS' => 'MS', 'MG' => 'MG', 'PA' => 'PA', 'PB' => 'PB',
		'PR' => 'PR', 'PE' => 'PE', 'PI' => 'PI', 'RJ' => 'RJ', 'RN' => 'RN',
		'RS' => 'RS', 'RO' => 'RO', 'RR' => 'RR', 'SC' => 'SC', 'SP' => 'SP',
		'SE' => 'SE', 'TO' => 'TO',
	);
}

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
						'type'      => 'text',
						'label'     => __( 'Nome curto', 'contorno' ),
						'help'      => __( 'Opcional — vazio, o site usa o título do topo desta tela. Só o bairro ou a cidade, sem "Contorno do Corpo". Usado em espaços estreitos.', 'contorno' ),
						'maxlength' => 40,
					),
					'kind'              => array(
						'type'    => 'segmented',
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
						'type'      => 'text',
						'label'     => __( 'Selo do card', 'contorno' ),
						'help'      => __( 'Texto curto sobre a foto na listagem, por exemplo "Nova" ou "Mais procurada". Deixe vazio para não exibir selo. Em pré-venda este campo é ignorado.', 'contorno' ),
						'maxlength' => 24,
					),
					'featured'          => array(
						'type'  => 'checkbox',
						'label' => __( 'Destacar na Home', 'contorno' ),
						'help'  => __( 'Marque para esta unidade aparecer no bloco de destaques da Home. A ordem segue o campo "Ordem" da caixa Atributos.', 'contorno' ),
					),
					'short_description' => array(
						'type'      => 'textarea',
						'label'     => __( 'Descrição curta (card)', 'contorno' ),
						'help'      => __( 'Uma ou duas linhas. Aparece no card da listagem e abaixo do título no hero.', 'contorno' ),
						'maxlength' => 160,
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
						'options' => contorno_brazilian_states(),
						'default' => 'MG',
					),
					'neighborhood' => array( 'type' => 'text', 'label' => __( 'Bairro', 'contorno' ) ),
					'address'      => array( 'type' => 'text', 'label' => __( 'Endereço', 'contorno' ) ),
					'postal_code'  => array(
						'type'  => 'cep',
						'label' => __( 'CEP', 'contorno' ),
						'help'  => __( 'Só números ou já formatado — o painel formata sozinho (00000-000).', 'contorno' ),
					),
					'phone'        => array(
						'type'  => 'phone',
						'label' => __( 'Telefone', 'contorno' ),
						'help'  => __( 'Com DDD. Fixo ou celular — o painel formata enquanto você digita.', 'contorno' ),
						'row'   => 'contact',
					),
					'whatsapp'     => array(
						'type'  => 'phone',
						'label' => __( 'WhatsApp', 'contorno' ),
						'help'  => __( 'Com DDD, sem o "55" na frente. Deixe vazio para usar o telefone acima.', 'contorno' ),
						'row'   => 'contact',
					),
					'hours'        => array(
						'type'  => 'text',
						'label' => __( 'Horário de funcionamento', 'contorno' ),
						'help'  => __( 'Texto livre, exibido na faixa informativa. Ex.: "Seg a sex 5h às 23h · Sáb 8h às 14h".', 'contorno' ),
					),
					'maps_query'   => array(
						'type'  => 'text',
						'label' => __( 'Busca no Google Maps', 'contorno' ),
						'help'  => __( 'Avançado. O que o site pesquisa no Maps para montar o mapa e o botão "Ver no mapa". Deixe vazio: por padrão usa o endereço acima, e só vale a pena preencher se o endereço sozinho não achar o lugar certo no Maps.', 'contorno' ),
					),
					'latitude'     => array(
						'type'    => 'coordinate',
						'label'   => __( 'Latitude', 'contorno' ),
						'min'     => -90,
						'max'     => 90,
						'row'     => 'coords',
						'placeholder' => '-19.9636285',
						'help'    => __( 'Opcional. Aceita vírgula ou ponto decimal. Preencha os dois (latitude e longitude) ou nenhum.', 'contorno' ),
					),
					'longitude'    => array(
						'type'    => 'coordinate',
						'label'   => __( 'Longitude', 'contorno' ),
						'min'     => -180,
						'max'     => 180,
						'row'     => 'coords',
						'placeholder' => '-43.9492036',
					),
				),
			),
			'midia' => array(
				'label'  => __( 'Hero, galeria e vídeo', 'contorno' ),
				'help'   => __( 'Selecione imagens direto da Biblioteca de Mídia. Nunca é preciso digitar caminho de arquivo, URL ou código.', 'contorno' ),
				'fields' => array(
					'image'     => array(
						'type'  => 'media',
						'label' => __( 'Imagem principal (hero e card)', 'contorno' ),
						'help'  => __( 'Foto horizontal, de preferência a partir de 1600px de largura. Sem foto, o card usa o gradiente da marca com a legenda "Foto em breve".', 'contorno' ),
					),
					'image_alt' => array(
						'type'  => 'text',
						'label' => __( 'Texto alternativo da imagem principal', 'contorno' ),
						'help'  => __( 'Descreve ESTA foto nesta unidade para leitores de tela e para o Google — é o que aparece no site, mesmo que a mesma foto tenha outro texto alternativo cadastrado na Biblioteca de Mídia (ela pode ser reaproveitada em outras unidades).', 'contorno' ),
					),
					'gallery'   => array(
						'type'  => 'media_list',
						'label' => __( 'Galeria', 'contorno' ),
						'help'  => __( 'Adicione, arraste para reordenar e remova. A ordem aqui é a mesma do site.', 'contorno' ),
					),
					'video_url' => array(
						'type'  => 'url',
						'label' => __( 'Vídeo da unidade', 'contorno' ),
						'help'  => __( 'Link de um vídeo do YouTube (ex.: https://youtu.be/XXXXXXXXXXX ou https://www.youtube.com/watch?v=XXXXXXXXXXX) — é o único formato reconhecido hoje. Deixe vazio para esconder a seção de vídeo.', 'contorno' ),
					),
				),
			),
			'conteudo' => array(
				'label'  => __( 'Destaques e diferenciais', 'contorno' ),
				'help'   => __( 'Marque o que esta unidade oferece. Os itens vêm do catálogo em Unidades → Atributos das unidades, onde ficam o nome e o ícone de cada um.', 'contorno' ),
				'fields' => array(
					'facilities'    => array(
						'type'           => 'attributes',
						'attribute_type' => 'highlight',
						'label'          => __( 'Destaques Contorno (cards com ícone)', 'contorno' ),
						'help'           => __( 'Cards com ícone na página da unidade. A ordem exibida é a do catálogo.', 'contorno' ),
					),
					'differentials' => array(
						'type'           => 'attributes',
						'attribute_type' => 'differential',
						'label'          => __( 'Diferenciais (faixa escura)', 'contorno' ),
						'help'           => __( 'Lista da seção "Diferenciais da unidade".', 'contorno' ),
					),
					'modalities'    => array(
						'type'           => 'attributes',
						'attribute_type' => 'modality',
						'label'          => __( 'Modalidades', 'contorno' ),
						'help'           => __( 'Usado em buscas e dados estruturados.', 'contorno' ),
					),
				),
			),
			'planos' => array(
				'label'  => __( 'Planos e valores', 'contorno' ),
				'help'   => __( 'Área comercial. Tudo aqui aparece nos cards de plano da página da unidade — preço, condição, benefícios, selo e botão de checkout. Nenhum valor está fixo no código.', 'contorno' ),
				'fields' => array(
					'starting_price' => array(
						'type'  => 'money',
						'label' => __( 'Preço "a partir de"', 'contorno' ),
						'help'  => __( 'Valor exibido no card da listagem. Se deixar vazio, o site usa automaticamente o menor preço entre os planos abaixo.', 'contorno' ),
					),
					'checkout_url'   => array(
						'type'  => 'url',
						'label' => __( 'Checkout padrão da unidade', 'contorno' ),
						'help'  => __( 'Usado pelo botão "Matricule-se" do hero e por qualquer plano sem URL própria.', 'contorno' ),
					),
					'plans'          => array(
						'type'      => 'repeater',
						'label'     => __( 'Lista de planos', 'contorno' ),
						'help'      => __( 'Arraste para reordenar não está disponível: a ordem é a de cadastro. Remova e adicione para reorganizar.', 'contorno' ),
						'subfields' => array(
							'id'           => array( 'type' => 'text', 'label' => __( 'Identificador', 'contorno' ) ),
							'name'         => array( 'type' => 'text', 'label' => __( 'Nome do plano', 'contorno' ) ),
							'description'  => array( 'type' => 'text', 'label' => __( 'Descrição', 'contorno' ) ),
							'price'        => array( 'type' => 'money', 'label' => __( 'Preço', 'contorno' ), 'evo_locked' => true ),
							'price_label'  => array( 'type' => 'text', 'label' => __( 'Texto no lugar do preço', 'contorno' ) ),
							'benefits'     => array( 'type' => 'list', 'label' => __( 'Benefícios', 'contorno' ) ),
							'checkout_url' => array( 'type' => 'url', 'label' => __( 'URL de checkout', 'contorno' ) ),
							'badge'        => array( 'type' => 'text', 'label' => __( 'Selo', 'contorno' ) ),
							'featured'     => array( 'type' => 'checkbox', 'label' => __( 'Plano destacado', 'contorno' ) ),
							'evo_membership_id' => array( 'type' => 'text', 'label' => __( 'EVO idMembership', 'contorno' ), 'help' => __( 'Vincula este card ao plano do EVO. Vazio = detectado pela URL de checkout.', 'contorno' ) ),
						),
					),
				),
			),
			'status' => array(
				'label'  => __( 'Status e pré-venda', 'contorno' ),
				'help'   => __( 'Enquanto o status for "Pré-venda", a unidade exibe a tarja sobre a foto e a faixa PRÉ-VENDA no hero. Ao mudar para "Aberta", os dois desaparecem do site na hora — não é preciso apagar os campos abaixo.', 'contorno' ),
				'fields' => array(
					'status'                 => array(
						'type'    => 'segmented',
						'label'   => __( 'Status', 'contorno' ),
						'options' => array(
							'open'     => __( 'Aberta', 'contorno' ),
							'pre_sale' => __( 'Pré-venda', 'contorno' ),
							'closed'   => __( 'Fechada', 'contorno' ),
						),
						// "Fechada" nao aparece como botao: nenhuma das 70
						// unidades usa esse valor hoje. Continua valido pra
						// validacao/gravacao — so nao vira botao, a menos
						// que a unidade ja esteja assim (entao o botao
						// aparece, pra nao esconder o proprio estado atual).
						'hide_options' => array( 'closed' ),
						'default' => 'open',
						'help'    => __( 'Ao voltar para "Aberta", todos os elementos de pré-venda desaparecem automaticamente.', 'contorno' ),
					),
					'presale_label'          => array(
						'type'        => 'text',
						'label'       => __( 'Pill sobre a foto', 'contorno' ),
						'placeholder' => 'NOVA UNIDADE',
						'conditional' => array( 'field' => 'status', 'values' => array( 'pre_sale' ) ),
					),
					'presale_opening_label'  => array(
						'type'        => 'text',
						'label'       => __( 'Rótulo de abertura', 'contorno' ),
						'placeholder' => 'Pre-inauguracao',
						'conditional' => array( 'field' => 'status', 'values' => array( 'pre_sale' ) ),
					),
					'presale_opening_date'   => array(
						'type'        => 'date',
						'label'       => __( 'Data de abertura', 'contorno' ),
						'help'        => __( 'Só preencher com data real — nunca inventar inauguração. Formatada automaticamente no site (ex.: "15 de outubro de 2026").', 'contorno' ),
						'conditional' => array( 'field' => 'status', 'values' => array( 'pre_sale' ) ),
					),
					'presale_promo_text'     => array(
						'type'        => 'textarea',
						'label'       => __( 'Texto comercial de pré-venda', 'contorno' ),
						'conditional' => array( 'field' => 'status', 'values' => array( 'pre_sale' ) ),
					),
				),
			),
			'aulas' => array(
				'label'  => __( 'Aulas coletivas - horários', 'contorno' ),
				'help'   => __( 'Informe a URL pública da agenda da unidade, como Google Agenda ou EVO/W12. Quando a URL estiver preenchida, a seção aparece automaticamente na single da unidade.', 'contorno' ),
				'fields' => array(
					'classes_enabled' => array( 'type' => 'checkbox', 'label' => __( 'Exibir grade por ID da filial EVO', 'contorno' ) ),
					'evo_branch_id'   => array( 'type' => 'text', 'label' => __( 'ID da filial no EVO', 'contorno' ), 'help' => __( 'Se preenchido, a URL da agenda é derivada automaticamente.', 'contorno' ) ),
					'classes_url'     => array( 'type' => 'url', 'label' => __( 'URL da agenda (Google Agenda ou EVO)', 'contorno' ), 'help' => __( 'Cole aqui o link público/embutível da agenda. Este campo tem prioridade sobre o ID da filial.', 'contorno' ) ),
					'classes_title'   => array( 'type' => 'text', 'label' => __( 'Título da seção', 'contorno' ), 'placeholder' => 'Aulas Coletivas - Horarios' ),
				),
			),
			'integracao' => array(
				'label'  => __( 'Avançado / Integrações', 'contorno' ),
				'help'   => __( 'Campos técnicos, não editoriais. Normalmente só mudam quando algum sistema externo muda — nenhum é gerado automaticamente pela EVO ainda.', 'contorno' ),
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
						'type'    => 'segmented',
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
						'type'    => 'segmented',
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
						'type'      => 'text',
						'label'     => __( 'Título no Google', 'contorno' ),
						'help'      => __( 'Ideal até 60 caracteres.', 'contorno' ),
						'maxlength' => 70,
					),
					'seo_description' => array(
						'type'      => 'textarea',
						'label'     => __( 'Descrição no Google', 'contorno' ),
						'help'      => __( 'Ideal entre 120 e 155 caracteres.', 'contorno' ),
						'maxlength' => 160,
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
						'placeholder' => 'SEU ÚNICO LIMITE É VOCÊ MESMO!',
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
							'price_from'      => array( 'type' => 'money', 'label' => __( 'Preço "de" (riscado)', 'contorno' ) ),
							'price'           => array( 'type' => 'money', 'label' => __( 'Preço promocional', 'contorno' ), 'evo_locked' => true ),
							'price_note'      => array( 'type' => 'text', 'label' => __( 'Nota do preço', 'contorno' ) ),
							'recurring_price' => array( 'type' => 'money', 'label' => __( 'Meses seguintes', 'contorno' ) ),
							'recurring_from'  => array( 'type' => 'money', 'label' => __( 'Meses seguintes "de"', 'contorno' ) ),
							'enrollment_fee'  => array( 'type' => 'money', 'label' => __( 'Taxa de matrícula', 'contorno' ) ),
							'fidelity'        => array( 'type' => 'text', 'label' => __( 'Fidelidade', 'contorno' ) ),
							'card_note'       => array( 'type' => 'text', 'label' => __( 'Nota do cartao', 'contorno' ) ),
							'benefits'        => array( 'type' => 'list', 'label' => __( 'Benefícios', 'contorno' ) ),
							'checkout_url'    => array( 'type' => 'url', 'label' => __( 'URL de checkout', 'contorno' ) ),
							'badge'           => array( 'type' => 'text', 'label' => __( 'Badge', 'contorno' ) ),
							'featured'        => array( 'type' => 'checkbox', 'label' => __( 'Destaque', 'contorno' ) ),
							'evo_membership_id' => array( 'type' => 'text', 'label' => __( 'EVO idMembership', 'contorno' ) ),
						),
					),
				),
			),
			'aulas' => array(
				'label'  => __( 'Aulas coletivas - horários', 'contorno' ),
				'help'   => __( 'Informe a URL pública da agenda da unidade, como Google Agenda ou EVO/W12. Quando a URL estiver preenchida, a seção aparece automaticamente na single.', 'contorno' ),
				'fields' => array(
					'classes_enabled' => array( 'type' => 'checkbox', 'label' => __( 'Exibir grade por ID da filial EVO', 'contorno' ) ),
					'evo_branch_id'   => array( 'type' => 'text', 'label' => __( 'ID da filial no EVO', 'contorno' ) ),
					'classes_url'     => array( 'type' => 'url', 'label' => __( 'URL da agenda (Google Agenda ou EVO)', 'contorno' ) ),
					'classes_title'   => array( 'type' => 'text', 'label' => __( 'Título da seção', 'contorno' ) ),
				),
			),
			'editorial' => array(
				'label'  => __( 'Conteúdo editorial extra', 'contorno' ),
				'help'   => __( 'O conteúdo livre desta CTN é editado no WPBakery, no editor principal desta tela. Aqui você escolhe apenas ONDE ele aparece no template compartilhado.', 'contorno' ),
				'fields' => array(
					'editorial_position' => array(
						'type'    => 'segmented',
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
	return array( 'list', 'repeater', 'media_list', 'attributes' );
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
