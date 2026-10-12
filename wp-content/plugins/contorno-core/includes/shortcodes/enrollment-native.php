<?php
/**
 * Checkout nativo — APRESENTACAO apenas.
 *
 * Este arquivo desenha as etapas. Ele nao sabe o que e a EVO: nao monta
 * payload, nao conhece idBranch nem idMembership, nao ve DNS nem token e nao
 * decide preco. Tudo isso vive no contorno-evo-sync, atras da API interna
 * contorno-evo/v1 — ver Contorno_Evo_Checkout_Rest.
 *
 * Divisao de responsabilidade, e por que ela e assim:
 *
 *   contorno-core       etapas, campos, estados, texto, design
 *   contorno-evo-sync   credencial, resolucao, preco, prospect, venda
 *
 * Um segundo cliente HTTP aqui significaria dois lugares para errar timeout,
 * SSRF, sanitizacao de log e tratamento de 429. Por isso nao existe nenhuma
 * chamada wp_remote_* neste plugin.
 *
 * O RESUMO exibido na etapa 1 vem do repeater local so como primeira pintura
 * (evita tela vazia enquanto a API responde). Assim que /checkout/open
 * responde, o resumo e SUBSTITUIDO pelo orcamento oficial da EVO. O valor
 * local nunca e enviado para nada.
 *
 * @package ContornoCore
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * O checkout nativo esta ligado para este post?
 *
 * function_exists antes de tudo: com o contorno-evo-sync desativado a
 * pergunta nem e feita e /matricula/ segue no comportamento atual.
 */
function contorno_native_checkout_active( ?WP_Post $post ): bool {
	if ( ! $post instanceof WP_Post ) {
		return false;
	}

	return function_exists( 'contorno_evo_native_checkout_enabled' )
		&& function_exists( 'contorno_evo_checkout_boot_data' )
		&& contorno_evo_native_checkout_enabled( (string) $post->post_name );
}

/**
 * Etapas do checkout nativo.
 *
 * A etapa de endereco e condicional: quem decide e a configuracao do
 * contorno-evo-sync, e ela comeca desligada porque a especificacao da EVO nao
 * exige endereco. Sem evidencia, nao se inventa campo obrigatorio.
 *
 * @return array<int,array{key:string,label:string}>
 */
function contorno_native_checkout_steps( bool $with_address ): array {
	// Sem etapa "Confirme seu plano": o resumo fica fixo ao lado. A primeira
	// etapa e o LEAD (nome, WhatsApp, e-mail), salvo na EVO antes do resto —
	// quem desiste no meio continua na base da unidade.
	$steps = array(
		array( 'key' => 'data', 'label' => __( 'Seus dados', 'contorno' ) ),
	);

	if ( $with_address ) {
		$steps[] = array( 'key' => 'address', 'label' => __( 'Endereço', 'contorno' ) );
	}

	$steps[] = array( 'key' => 'payment', 'label' => __( 'Pagamento', 'contorno' ) );
	$steps[] = array( 'key' => 'done', 'label' => __( 'Confirmação', 'contorno' ) );

	return $steps;
}

/**
 * Um campo de texto do checkout.
 *
 * @param array<string,string> $args
 */
function contorno_native_field( array $args ): string {
	$id    = 'contorno-ck-' . sanitize_key( $args['name'] );
	$type  = $args['type'] ?? 'text';
	$attrs = '';

	foreach ( array( 'autocomplete', 'inputmode', 'maxlength', 'placeholder', 'pattern' ) as $attr ) {
		if ( ! empty( $args[ $attr ] ) ) {
			$attrs .= sprintf( ' %s="%s"', $attr, esc_attr( (string) $args[ $attr ] ) );
		}
	}

	if ( ! empty( $args['mask'] ) ) {
		$attrs .= ' data-contorno-mask="' . esc_attr( (string) $args['mask'] ) . '"';
	}

	ob_start();
	?>
	<div class="contorno-field-row<?php echo ! empty( $args['wide'] ) ? ' is-wide' : ''; ?>">
		<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( (string) $args['label'] ); ?></label>
		<span class="contorno-input<?php echo ! empty( $args['prefix'] ) ? ' has-prefix' : ''; ?>">
			<?php if ( ! empty( $args['prefix'] ) ) : ?>
				<span class="contorno-input__prefix" aria-hidden="true"><?php echo esc_html( (string) $args['prefix'] ); ?></span>
			<?php elseif ( ! empty( $args['icon'] ) ) : ?>
				<?php echo contorno_icon( (string) $args['icon'], 'contorno-input__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
			<input
				type="<?php echo esc_attr( $type ); ?>"
				id="<?php echo esc_attr( $id ); ?>"
				name="<?php echo esc_attr( (string) $args['name'] ); ?>"
				<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado com esc_attr acima. ?>
				<?php echo empty( $args['optional'] ) ? 'required' : ''; ?>
			/>
		</span>
		<?php if ( ! empty( $args['help'] ) ) : ?>
			<p class="contorno-field-help"><?php echo esc_html( (string) $args['help'] ); ?></p>
		<?php endif; ?>
		<p class="contorno-field-error" data-error-for="<?php echo esc_attr( (string) $args['name'] ); ?>" hidden></p>
	</div>
	<?php

	return (string) ob_get_clean();
}

/**
 * Markup completo do checkout nativo.
 *
 * @param array<string,mixed>|null $plan
 */
function contorno_enrollment_native_markup( WP_Post $post, ?array $plan, string $back_url ): string {
	$boot = contorno_evo_checkout_boot_data();

	// A etapa de endereco existe porque a integracao pediu, nao porque o
	// formulario achou bonito. O padrao e nao pedir — ver o comentario de
	// checkout_require_address em Contorno_Evo_Settings.
	$with_address = ! empty( $boot['requiresAddress'] );
	$steps        = contorno_native_checkout_steps( $with_address );
	$privacy_page = get_page_by_path( 'politica-de-privacidade' );

	contorno_enqueue_component( 'enrollment-native' );

	$phone       = contorno_field_text( 'whatsapp', $post->ID, contorno_field_text( 'phone', $post->ID ) );
	$contact_url = '' !== trim( $phone ) ? contorno_whatsapp_link( $phone ) : '';

	wp_localize_script(
		'contorno-enrollment-native',
		'contornoCheckout',
		array(
			'nonce'  => (string) $boot['nonce'],
			'routes' => (array) $boot['routes'],
			'slug'   => (string) $post->post_name,
			'plan'   => (string) ( $plan['id'] ?? '' ),
			/*
			 * A jornada nunca sai do site: quando a API diz explicitamente
			 * que nenhuma venda foi criada (fallback=true), o checkout so
			 * mostra o aviso de indisponibilidade + este contato — nunca
			 * um destino externo (ver assets/js/enrollment-native.js,
			 * Checkout.prototype.fallbackTo). Nunca depois de uma resposta
			 * ambigua.
			 */
			'contactUrl' => esc_url_raw( $contact_url ),
			'i18n'   => array(
				'required'    => __( 'Preencha este campo.', 'contorno' ),
				'email'       => __( 'Informe um e-mail válido.', 'contorno' ),
				'phone'       => __( 'Informe um WhatsApp válido com DDD, ex.: (31) 99999-9999.', 'contorno' ),
				'cpf'         => __( 'CPF inválido. Confira os 11 números.', 'contorno' ),
				'terms'       => __( 'Para finalizar, marque que leu e concorda com os termos e a política de privacidade.', 'contorno' ),
				'fullName'    => __( 'Informe nome e sobrenome.', 'contorno' ),
				'consent'     => __( 'Para continuar, autorize o contato da unidade.', 'contorno' ),
				'saving'      => __( 'Salvando seus dados...', 'contorno' ),
				'loading'     => __( 'Carregando seu plano...', 'contorno' ),
				'validating'  => __( 'Validando seus dados...', 'contorno' ),
				'processing'  => __( 'Processando o pagamento...', 'contorno' ),
				'dontClose'   => __( 'Não feche esta página.', 'contorno' ),
				'generic'     => __( 'Não foi possível concluir agora. Tente novamente em alguns instantes.', 'contorno' ),
				'stepOf'      => __( 'Etapa %1$d de %2$d', 'contorno' ),
				'cardMissing' => __( 'O pagamento por cartão ainda não está disponível nesta página.', 'contorno' ),
				'payLabel'       => __( 'Finalizar matrícula', 'contorno' ),
				'validatingCard' => __( 'Validando o cartão...', 'contorno' ),
				'cardTimeout'    => __( 'Não conseguimos validar o cartão a tempo. Confira os dados e tente novamente.', 'contorno' ),
				'unavailable' => __( 'Não foi possível iniciar sua matrícula online no momento. A integração com o sistema da academia ainda não está disponível. Tente novamente em alguns instantes ou entre em contato com a unidade.', 'contorno' ),
				'contactUnit' => __( 'Falar com a unidade', 'contorno' ),
			),
		)
	);

	ob_start();
	?>
	<section class="contorno-enroll-section contorno-ck">
	<div class="site-container">
		<div
			class="contorno-ck__shell"
			data-contorno-checkout
			data-total-steps="<?php echo esc_attr( (string) count( $steps ) ); ?>"
			data-address="<?php echo $with_address ? '1' : '0'; ?>"
		>
			<nav class="contorno-ck__steps" aria-label="<?php esc_attr_e( 'Etapas da matrícula', 'contorno' ); ?>">
				<p class="contorno-ck__progress" data-ck-progress aria-live="polite">
					<?php echo esc_html( sprintf( __( 'Etapa %1$d de %2$d', 'contorno' ), 1, count( $steps ) ) ); ?>
				</p>
				<ol class="contorno-ck__steps-list">
					<?php foreach ( $steps as $index => $step ) : ?>
						<li
							class="contorno-ck__step<?php echo 0 === $index ? ' is-current' : ''; ?>"
							data-ck-step-marker="<?php echo esc_attr( $step['key'] ); ?>"
						>
							<span class="contorno-ck__step-badge"><?php echo esc_html( (string) ( $index + 1 ) ); ?></span>
							<span class="contorno-ck__step-label"><?php echo esc_html( $step['label'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>

			<div class="contorno-ck__grid">
				<div class="contorno-ck__main">
					<?php /* Mensagem global de estado — erro, aviso de preco, sucesso. */ ?>
					<div class="contorno-ck__alert" data-ck-alert role="status" aria-live="polite" hidden></div>

					<?php /* ---------------- Etapa 1 — lead ---------------- */ ?>
					<section class="contorno-ck__panel is-current" data-ck-panel="data">
						<h1 class="contorno-ck__title"><?php esc_html_e( 'Seus dados', 'contorno' ); ?></h1>
						<p class="contorno-ck__lead"><?php esc_html_e( 'Comece sua matrícula. Se precisar parar no meio, a unidade entra em contato para ajudar.', 'contorno' ); ?></p>

						<div class="contorno-ck__loading" data-ck-loading>
							<span class="contorno-ck__spinner" aria-hidden="true"></span>
							<span><?php esc_html_e( 'Carregando seu plano...', 'contorno' ); ?></span>
						</div>

						<div class="contorno-ck__fields">
							<?php
							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- markup escapado na funcao.
							echo contorno_native_field( array( 'name' => 'fullName', 'label' => __( 'Nome completo', 'contorno' ), 'icon' => 'user', 'autocomplete' => 'name', 'maxlength' => '140', 'wide' => true ) );
							// +55 e prefixo VISUAL fixo; o input guarda so o numero
							// nacional. tel-national + normalizacao no JS evitam
							// que o DDI do autocomplete vire DDD. maxlength folgado:
							// o Chrome trunca o autofill no maxlength, e
							// "+55 (31) 97524-8061" tem 19 caracteres.
							echo contorno_native_field( array( 'name' => 'phone', 'label' => __( 'WhatsApp', 'contorno' ), 'type' => 'tel', 'prefix' => '+55', 'autocomplete' => 'tel-national', 'inputmode' => 'numeric', 'maxlength' => '25', 'mask' => 'phone', 'placeholder' => '(31) 99999-9999' ) );
							echo contorno_native_field( array( 'name' => 'email', 'label' => __( 'E-mail', 'contorno' ), 'type' => 'email', 'icon' => 'mail', 'autocomplete' => 'email', 'maxlength' => '254', 'placeholder' => 'seu@email.com' ) );
							// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>

						<label class="contorno-ck__terms contorno-ck__terms--consent">
							<input type="checkbox" name="consent" value="1" required />
							<span>
								<?php
								printf(
									/* translators: %s: privacy policy link */
									esc_html__( 'Autorizo a Contorno do Corpo a entrar em contato comigo sobre a minha matrícula (WhatsApp, e-mail ou telefone) e li a %s.', 'contorno' ),
									sprintf(
										'<a href="%s" target="_blank" rel="noopener">%s</a>',
										esc_url( $privacy_page instanceof WP_Post ? (string) get_permalink( $privacy_page ) : home_url( '/politica-de-privacidade/' ) ),
										esc_html__( 'política de privacidade', 'contorno' )
									)
								);
								?>
							</span>
						</label>
						<p class="contorno-field-error" data-error-for="consent" hidden></p>

						<div class="contorno-ck__actions">
							<button type="button" class="contorno-btn contorno-btn--primary cta-label" data-ck-next="data" disabled>
								<span data-ck-label><?php esc_html_e( 'Continuar', 'contorno' ); ?></span>
								<?php echo contorno_icon( 'arrow-right', 'contorno-btn__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</button>
							<a class="contorno-btn contorno-btn--outline cta-label" href="<?php echo esc_url( $back_url ); ?>">
								<?php esc_html_e( '← Voltar para planos', 'contorno' ); ?>
							</a>
						</div>
					</section>

					<?php /* ---------------- Endereco (condicional) ---------------- */ ?>
					<?php if ( $with_address ) : ?>
						<section class="contorno-ck__panel" data-ck-panel="address" hidden>
							<h2 class="contorno-ck__title"><?php esc_html_e( 'Endereço', 'contorno' ); ?></h2>
							<p class="contorno-ck__lead"><?php esc_html_e( 'Usado no seu contrato de matrícula.', 'contorno' ); ?></p>

							<div class="contorno-ck__fields">
								<?php
								// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
								echo contorno_native_field( array( 'name' => 'zipCode', 'label' => __( 'CEP', 'contorno' ), 'icon' => 'map-pin', 'inputmode' => 'numeric', 'maxlength' => '9', 'mask' => 'cep', 'autocomplete' => 'postal-code' ) );
								echo contorno_native_field( array( 'name' => 'address', 'label' => __( 'Endereço', 'contorno' ), 'autocomplete' => 'address-line1', 'maxlength' => '120', 'wide' => true ) );
								echo contorno_native_field( array( 'name' => 'number', 'label' => __( 'Número', 'contorno' ), 'inputmode' => 'numeric', 'maxlength' => '10' ) );
								echo contorno_native_field( array( 'name' => 'complement', 'label' => __( 'Complemento', 'contorno' ), 'maxlength' => '60', 'optional' => true ) );
								echo contorno_native_field( array( 'name' => 'neighborhood', 'label' => __( 'Bairro', 'contorno' ), 'maxlength' => '80' ) );
								echo contorno_native_field( array( 'name' => 'city', 'label' => __( 'Cidade', 'contorno' ), 'maxlength' => '80' ) );
								echo contorno_native_field( array( 'name' => 'state', 'label' => __( 'Estado (UF)', 'contorno' ), 'maxlength' => '2', 'placeholder' => 'MG' ) );
								// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
								?>
							</div>

							<div class="contorno-ck__actions">
								<button type="button" class="contorno-btn contorno-btn--primary cta-label" data-ck-next="address"><?php esc_html_e( 'Continuar', 'contorno' ); ?></button>
								<button type="button" class="contorno-btn contorno-btn--outline cta-label" data-ck-back><?php esc_html_e( 'Voltar', 'contorno' ); ?></button>
							</div>
						</section>
					<?php endif; ?>

					<?php /* ---------------- Pagamento: CPF + cartao ---------------- */ ?>
					<section class="contorno-ck__panel" data-ck-panel="payment" hidden>
						<h2 class="contorno-ck__title"><?php esc_html_e( 'Pagamento', 'contorno' ); ?></h2>
						<p class="contorno-ck__lead"><?php esc_html_e( 'Para emitir o contrato, informe seu CPF e os dados do cartão de crédito.', 'contorno' ); ?></p>

						<div class="contorno-ck__fields">
							<?php
							// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- markup escapado na funcao.
							echo contorno_native_field(
								array(
									'name'      => 'document',
									'label'     => __( 'CPF', 'contorno' ),
									'icon'      => 'user',
									'inputmode' => 'numeric',
									'maxlength' => '14',
									'mask'      => 'cpf',
									'placeholder' => '000.000.000-00',
									// Justificativa do campo, para o visitante e
									// para quem revisar o formulario depois: o CPF
									// e o que evita cadastro duplicado na EVO.
									'help'      => __( 'Usamos o CPF para localizar seu cadastro e não duplicá-lo.', 'contorno' ),
								)
							);
							echo contorno_native_field( array( 'name' => 'birthday', 'label' => __( 'Data de nascimento', 'contorno' ), 'type' => 'date', 'autocomplete' => 'bday', 'optional' => true ) );
							// phpcs:enable WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>

						<?php
						/*
						 * O componente do EVO Pay e montado pelo script oficial
						 * da EVO dentro deste container. NAO existe input de
						 * numero de cartao nem de CVV neste HTML — de proposito:
						 * um campo desses postaria PAN para o WordPress.
						 */
						?>
						<div class="contorno-ck__card" data-ck-card-mount>
							<p class="contorno-ck__card-empty" data-ck-card-empty>
								<?php esc_html_e( 'Carregando o formulário seguro de cartão...', 'contorno' ); ?>
							</p>
						</div>

						<div class="contorno-field-row" data-ck-installments hidden>
							<label for="contorno-ck-installments"><?php esc_html_e( 'Parcelas', 'contorno' ); ?></label>
							<span class="contorno-input">
								<select id="contorno-ck-installments" name="installments"></select>
							</span>
						</div>

						<p class="contorno-field-error" data-error-for="card" hidden></p>

						<p class="contorno-ck__secure">
							<?php echo contorno_icon( 'lock', 'contorno-ck__secure-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span><?php esc_html_e( 'Os dados do seu cartão são enviados direto ao processador de pagamento da academia. Este site não recebe nem armazena o número do cartão.', 'contorno' ); ?></span>
						</p>

						<?php
						/*
						 * Aceite contratual: colado nos botoes (mesma posicao para
						 * qualquer forma de pagamento), separado do aviso de
						 * seguranca acima e do contato comercial da etapa 1.
						 */
						?>
						<label class="contorno-ck__terms contorno-ck__terms--accept">
							<input type="checkbox" name="acceptedTerms" value="1" required />
							<span>
								<?php
								printf(
									/* translators: 1: terms link, 2: privacy policy link */
									esc_html__( 'Li e concordo com os %1$s e a %2$s', 'contorno' ),
									sprintf(
										'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
										esc_url( home_url( '/termos-de-uso/' ) ),
										esc_html__( 'termos', 'contorno' )
									),
									sprintf(
										'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
										esc_url( home_url( '/politica-de-privacidade/' ) ),
										esc_html__( 'política de privacidade', 'contorno' )
									)
								);
								?>
							</span>
						</label>
						<p class="contorno-field-error" data-error-for="acceptedTerms" hidden></p>

						<div class="contorno-ck__actions contorno-ck__actions--pay">
							<button type="button" class="contorno-btn contorno-btn--primary cta-label contorno-ck__pay" data-ck-pay disabled>
								<span data-ck-pay-label><?php esc_html_e( 'Finalizar matrícula', 'contorno' ); ?></span>
								<span class="contorno-ck__spinner" data-ck-pay-spinner aria-hidden="true" hidden></span>
							</button>
							<button type="button" class="contorno-btn contorno-btn--outline cta-label" data-ck-back><?php esc_html_e( 'Voltar', 'contorno' ); ?></button>
						</div>
					</section>

					<?php /* ---------------- Etapa 5 — confirmacao ---------------- */ ?>
					<section class="contorno-ck__panel" data-ck-panel="done" hidden>
						<h2 class="contorno-ck__title"><?php esc_html_e( 'Matrícula confirmada!', 'contorno' ); ?></h2>
						<p class="contorno-ck__lead"><?php esc_html_e( 'Estamos levando você para a página de confirmação...', 'contorno' ); ?></p>
						<span class="contorno-ck__spinner" aria-hidden="true"></span>
					</section>
				</div>

				<?php /* ---------------- Resumo fixo ---------------- */ ?>
				<aside class="contorno-ck__summary" data-ck-summary aria-label="<?php esc_attr_e( 'Resumo da matrícula', 'contorno' ); ?>">
					<h2 class="contorno-ck__summary-title"><?php esc_html_e( 'Resumo', 'contorno' ); ?></h2>

					<dl class="contorno-ck__summary-list">
						<div>
							<dt><?php esc_html_e( 'Unidade', 'contorno' ); ?></dt>
							<dd data-ck-sum="unit"><?php echo esc_html( (string) get_the_title( $post ) ); ?></dd>
						</div>
						<div>
							<dt><?php esc_html_e( 'Plano', 'contorno' ); ?></dt>
							<dd data-ck-sum="plan"><?php echo esc_html( (string) ( $plan['name'] ?? '—' ) ); ?></dd>
						</div>
						<div data-ck-sum-row="firstValue" hidden>
							<dt><?php esc_html_e( 'Valor agora', 'contorno' ); ?></dt>
							<dd data-ck-sum="firstValue">—</dd>
						</div>
						<div data-ck-sum-row="recurrent" hidden>
							<dt><?php esc_html_e( 'Valor recorrente', 'contorno' ); ?></dt>
							<dd data-ck-sum="recurrent">—</dd>
						</div>
						<div data-ck-sum-row="condition" hidden>
							<dt><?php esc_html_e( 'Condição', 'contorno' ); ?></dt>
							<dd data-ck-sum="condition">—</dd>
						</div>
						<div data-ck-sum-row="fidelity" hidden>
							<dt><?php esc_html_e( 'Fidelidade', 'contorno' ); ?></dt>
							<dd data-ck-sum="fidelity">—</dd>
						</div>
						<div data-ck-sum-row="enrollment" hidden>
							<dt><?php esc_html_e( 'Taxa de matrícula', 'contorno' ); ?></dt>
							<dd data-ck-sum="enrollment">—</dd>
						</div>
						<div data-ck-sum-row="installments" hidden>
							<dt><?php esc_html_e( 'Parcelamento', 'contorno' ); ?></dt>
							<dd data-ck-sum="installments">—</dd>
						</div>
					</dl>

					<p class="contorno-ck__summary-note">
						<?php esc_html_e( 'Valores confirmados junto ao sistema da academia no momento do pagamento.', 'contorno' ); ?>
					</p>
				</aside>
			</div>

			<?php /* Sem JavaScript o checkout nativo nao roda. Nunca externo: so o contato da propria unidade. */ ?>
			<noscript>
				<p class="contorno-ck__noscript">
					<?php esc_html_e( 'Para concluir a matrícula nesta página é necessário ativar o JavaScript.', 'contorno' ); ?>
					<?php if ( '' !== $contact_url ) : ?>
						<a href="<?php echo esc_url( $contact_url ); ?>"><?php esc_html_e( 'Falar com a unidade', 'contorno' ); ?></a>
					<?php endif; ?>
				</p>
			</noscript>
		</div>
	</div>
	</section>
	<?php

	return (string) ob_get_clean();
}
