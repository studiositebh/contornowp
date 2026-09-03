<?php
/**
 * Fluxo de matricula.
 *
 * FLUXO REAL, portado do React (MatriculaForm.tsx):
 *
 *   CTA "Matricule-se" no plano da unidade
 *     -> /matricula/?unidade={slug}&plano={id}
 *     -> formulario "Seus dados" (nome, e-mail, telefone, aceite)
 *     -> valida
 *     -> se o plano tem checkout_url  : redireciona para a EVO (externo)
 *     -> se nao tem                   : segue para /matricula/confirmacao/
 *
 * O que o React faz e NAO reproduzimos por decisao consciente:
 *   - saveCheckoutLead() em sessionStorage. Guardar o lead so no navegador
 *     nao serve a ninguem: o dado nunca chega a Contorno e some ao fechar a
 *     aba. Aqui o formulario passa pelo servidor (nonce + validacao) e segue
 *     para o mesmo destino. O armazenamento fica disponivel por filtro, mas
 *     desligado — ver includes/forms.php.
 *
 * O que NAO inventamos:
 *   - nenhuma etapa de pagamento propria. O pagamento e da EVO, no
 *     checkout_url cadastrado no plano.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_ENROLL_ACTION = 'contorno_enroll';

/**
 * Unidade e plano vindos da query string, com o mesmo fallback do React:
 * plano informado > plano destacado > primeiro plano.
 *
 * @return array{unit:?WP_Post,plan:array<string,mixed>|null}
 */
function contorno_enrollment_context(): array {
	$slug = isset( $_GET['unidade'] ) ? sanitize_title( wp_unslash( (string) $_GET['unidade'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$plan_id = isset( $_GET['plano'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['plano'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$unit = '' !== $slug ? contorno_get_unit_by_slug( $slug ) : null;

	if ( ! $unit instanceof WP_Post ) {
		return array( 'unit' => null, 'plan' => null );
	}

	$plans = contorno_field_list( 'plans', $unit->ID );
	$plans = array_values( array_filter( $plans, 'is_array' ) );

	if ( array() === $plans ) {
		return array( 'unit' => $unit, 'plan' => null );
	}

	if ( '' !== $plan_id ) {
		foreach ( $plans as $plan ) {
			if ( (string) ( $plan['id'] ?? '' ) === $plan_id ) {
				return array( 'unit' => $unit, 'plan' => $plan );
			}
		}
	}

	foreach ( $plans as $plan ) {
		if ( ! empty( $plan['featured'] ) ) {
			return array( 'unit' => $unit, 'plan' => $plan );
		}
	}

	return array( 'unit' => $unit, 'plan' => $plans[0] );
}

/**
 * CONTORNO — Etapas da Matricula.
 */
contorno_add_shortcode(
	'contorno_checkout_steps',
	static function ( array|string $atts ): string {
		$a = shortcode_atts( array( 'current' => '1' ), (array) $atts, 'contorno_checkout_steps' );

		$current = max( 1, min( 3, (int) $a['current'] ) );

		$steps = array(
			1 => __( 'Seus dados', 'contorno' ),
			2 => __( 'Pagamento', 'contorno' ),
			3 => __( 'Confirmação', 'contorno' ),
		);

		ob_start();
		?>
		<nav class="contorno-steps" aria-label="<?php esc_attr_e( 'Etapas da matrícula', 'contorno' ); ?>">
			<ol class="contorno-steps__list">
				<?php foreach ( $steps as $number => $label ) : ?>
					<li class="contorno-steps__item<?php echo $number === $current ? ' is-current' : ''; ?>">
						<?php if ( $number > 1 ) : ?>
							<span class="contorno-steps__rule" aria-hidden="true"></span>
						<?php endif; ?>
						<span class="contorno-steps__badge"<?php echo $number === $current ? ' aria-current="step"' : ''; ?>><?php echo esc_html( (string) $number ); ?></span>
						<span class="contorno-steps__label"><?php echo esc_html( $number . '. ' . $label ); ?></span>
					</li>
				<?php endforeach; ?>
			</ol>
		</nav>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Formulário de Matrícula.
 */
contorno_add_shortcode(
	'contorno_enrollment_form',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'title'    => '',
				'subtitle' => '',
				'privacy'  => '',
				'benefits' => '',
			),
			(array) $atts,
			'contorno_enrollment_form'
		);

		$context = contorno_enrollment_context();
		$unit    = $context['unit'];
		$plan    = $context['plan'];

		$title = '' !== trim( (string) $a['title'] )
			? (string) $a['title']
			: __( 'Antes de finalizar sua matrícula', 'contorno' );

		$subtitle = '' !== trim( (string) $a['subtitle'] )
			? (string) $a['subtitle']
			: __( 'Preencha seus dados para continuar para o checkout.', 'contorno' );

		$privacy = '' !== trim( (string) $a['privacy'] )
			? (string) $a['privacy']
			: __( 'Utilizaremos seus dados apenas para finalizar sua matrícula, entrar em contato sobre sua unidade e enviar informações sobre seu plano.', 'contorno' );

		// Destino do "Voltar para planos": ancora de planos da unidade, quando houver.
		$back_url = $unit instanceof WP_Post
			? (string) get_permalink( $unit ) . '#planos'
			: (string) ( get_page_by_path( 'unidades' ) instanceof WP_Post ? get_permalink( get_page_by_path( 'unidades' ) ) : home_url( '/unidades/' ) );

		$checkout = $plan ? contorno_plan_checkout_url( $plan, $unit instanceof WP_Post ? $unit->ID : null ) : '';

		$benefits = '' !== trim( (string) $a['benefits'] )
			? contorno_decode_param_group( (string) $a['benefits'] )
			: array(
				array(
					'icon'  => 'check',
					'label' => __( 'Sem burocracia', 'contorno' ),
					'text'  => __( 'Processo simples e rápido para você começar hoje mesmo.', 'contorno' ),
				),
				array(
					'icon'  => 'sparkles',
					'label' => __( 'Ativação rápida', 'contorno' ),
					'text'  => __( 'Após a confirmação, você já pode treinar.', 'contorno' ),
				),
				array(
					'icon'  => 'phone',
					'label' => __( 'Suporte da equipe', 'contorno' ),
					'text'  => __( 'Estamos prontos para te ajudar sempre que precisar.', 'contorno' ),
				),
			);

		contorno_enqueue_component( 'enrollment' );

		$terms   = get_page_by_path( 'termos-de-uso' );
		$privacy_page = get_page_by_path( 'politica-de-privacidade' );

		ob_start();
		?>
		<section class="contorno-enroll-section">
		<div class="site-container">
		<div class="contorno-enroll">
			<?php echo do_shortcode( '[contorno_checkout_steps current="1"]' ); ?>

			<header class="contorno-enroll__head motion-reveal" data-contorno-reveal>
				<h1 class="contorno-enroll__title"><?php echo esc_html( $title ); ?></h1>
				<p class="contorno-enroll__subtitle"><?php echo esc_html( $subtitle ); ?></p>
			</header>

			<div class="contorno-enroll__card motion-reveal" data-contorno-reveal>
				<form
					class="contorno-enroll__form"
					novalidate
					data-contorno-enroll
					data-checkout="<?php echo esc_url( $checkout ); ?>"
					data-fallback="<?php echo esc_url( (string) ( get_page_by_path( 'matricula/confirmacao' ) instanceof WP_Post ? get_permalink( get_page_by_path( 'matricula/confirmacao' ) ) : home_url( '/matricula/confirmacao/' ) ) ); ?>"
				>
					<?php wp_nonce_field( CONTORNO_ENROLL_ACTION, 'contorno_nonce' ); ?>
					<input type="hidden" name="contorno_form" value="<?php echo esc_attr( CONTORNO_ENROLL_ACTION ); ?>" />
					<input type="hidden" name="unidade" value="<?php echo esc_attr( $unit instanceof WP_Post ? (string) $unit->post_name : '' ); ?>" />
					<input type="hidden" name="plano" value="<?php echo esc_attr( $plan ? (string) ( $plan['id'] ?? '' ) : '' ); ?>" />
					<?php /* Honeypot. */ ?>
					<input type="text" name="website" class="contorno-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true" />

					<h2 class="contorno-enroll__legend"><?php esc_html_e( 'Seus dados', 'contorno' ); ?></h2>
					<p class="contorno-enroll__hint"><?php esc_html_e( 'Precisamos de algumas informações para continuar.', 'contorno' ); ?></p>

					<?php if ( $unit instanceof WP_Post ) : ?>
						<p class="contorno-enroll__selection">
							<?php echo contorno_icon( 'map-pin', 'contorno-enroll__selection-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<span>
								<?php echo esc_html( (string) get_the_title( $unit ) ); ?>
								<?php if ( $plan && ! empty( $plan['name'] ) ) : ?>
									&bull; <strong><?php echo esc_html( (string) $plan['name'] ); ?></strong>
								<?php endif; ?>
							</span>
						</p>
					<?php endif; ?>

					<div class="contorno-enroll__fields">
						<div class="contorno-field-row">
							<label for="contorno-enroll-name"><?php esc_html_e( 'Nome completo', 'contorno' ); ?></label>
							<span class="contorno-input">
								<?php echo contorno_icon( 'users', 'contorno-input__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<input
									type="text"
									id="contorno-enroll-name"
									name="name"
									autocomplete="name"
									maxlength="120"
									placeholder="<?php esc_attr_e( 'Digite seu nome completo', 'contorno' ); ?>"
									required
								/>
							</span>
							<p class="contorno-field-error" data-error-for="name" hidden></p>
						</div>

						<div class="contorno-field-row">
							<label for="contorno-enroll-email"><?php esc_html_e( 'E-mail', 'contorno' ); ?></label>
							<span class="contorno-input">
								<?php echo contorno_icon( 'external', 'contorno-input__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<input
									type="email"
									id="contorno-enroll-email"
									name="email"
									autocomplete="email"
									maxlength="254"
									placeholder="seu@email.com"
									required
								/>
							</span>
							<p class="contorno-field-error" data-error-for="email" hidden></p>
						</div>

						<div class="contorno-field-row">
							<label for="contorno-enroll-phone"><?php esc_html_e( 'Telefone com DDD', 'contorno' ); ?></label>
							<span class="contorno-input">
								<?php echo contorno_icon( 'phone', 'contorno-input__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<input
									type="tel"
									id="contorno-enroll-phone"
									name="phone"
									inputmode="tel"
									autocomplete="tel"
									maxlength="20"
									placeholder="(11) 99999-9999"
									data-contorno-phone-mask
									required
								/>
							</span>
							<p class="contorno-field-error" data-error-for="phone" hidden></p>
						</div>
					</div>

					<p class="contorno-enroll__privacy">
						<?php echo contorno_icon( 'lock', 'contorno-enroll__privacy-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span><?php echo esc_html( $privacy ); ?></span>
					</p>

					<label class="contorno-enroll__terms">
						<input type="checkbox" name="acceptedTerms" value="1" required />
						<span>
							<?php
							printf(
								/* translators: 1: terms link, 2: privacy policy link */
								esc_html__( 'Li e concordo com os %1$s e a %2$s', 'contorno' ),
								sprintf(
									'<a href="%s">%s</a>',
									esc_url( $terms instanceof WP_Post ? (string) get_permalink( $terms ) : home_url( '/termos-de-uso/' ) ),
									esc_html__( 'termos', 'contorno' )
								),
								sprintf(
									'<a href="%s">%s</a>',
									esc_url( $privacy_page instanceof WP_Post ? (string) get_permalink( $privacy_page ) : home_url( '/politica-de-privacidade/' ) ),
									esc_html__( 'política de privacidade', 'contorno' )
								)
							);
							?>
						</span>
					</label>
					<p class="contorno-field-error" data-error-for="acceptedTerms" hidden></p>

					<button type="submit" class="contorno-btn contorno-btn--primary cta-label contorno-enroll__submit">
						<span data-contorno-enroll-label><?php esc_html_e( 'Continuar para o checkout', 'contorno' ); ?></span>
						<?php echo contorno_icon( 'arrow-right', 'contorno-btn__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</button>

					<a class="contorno-btn contorno-btn--outline cta-label contorno-enroll__back" href="<?php echo esc_url( $back_url ); ?>">
						<?php esc_html_e( '← Voltar para planos', 'contorno' ); ?>
					</a>
				</form>
			</div>

			<?php if ( array() !== $benefits ) : ?>
				<div class="contorno-enroll__benefits motion-reveal" data-contorno-reveal>
					<ul>
						<?php foreach ( $benefits as $item ) : ?>
							<?php if ( ! is_array( $item ) ) : continue; endif; ?>
							<li>
								<?php echo contorno_icon( (string) ( $item['icon'] ?? 'check' ), 'contorno-enroll__benefit-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div>
									<h3><?php echo esc_html( (string) ( $item['label'] ?? '' ) ); ?></h3>
									<p><?php echo esc_html( (string) ( $item['text'] ?? '' ) ); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
		</div>
		</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Confirmação de Matrícula.
 *
 * Reune as quatro secoes da pagina aprovada: hero de confirmacao, PUV,
 * prescricao de treino com busca de unidade, e o bloco de ajuda.
 */
contorno_add_shortcode(
	'contorno_enrollment_confirmation',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow'      => '',
				'title'        => '',
				'text'         => '',
				'image'        => '',
				'steps'        => '',
				'puv_eyebrow'  => '',
				'puv_title'    => '',
				'puv_text'     => '',
				'next_eyebrow' => '',
				'next_title'   => '',
				'next_text'    => '',
				'help_title'   => '',
				'help_text'    => '',
				'help_label'   => '',
			),
			(array) $atts,
			'contorno_enrollment_confirmation'
		);

		$image = contorno_attr_image( $a['image'], 'contorno-gallery' );
		if ( '' === $image ) {
			$image = contorno_resolve_media( '/brand/cta-gym.jpg', 'contorno-gallery' );
		}

		$steps = '' !== trim( (string) $a['steps'] )
			? contorno_decode_param_group( (string) $a['steps'] )
			: array(
				array( 'icon' => 'check', 'label' => __( 'Matrícula realizada com sucesso', 'contorno' ), 'text' => __( 'Seus dados estão seguros com a gente.', 'contorno' ) ),
				array( 'icon' => 'arrow-right', 'label' => __( 'Próximo passo', 'contorno' ), 'text' => __( 'Agende a montagem do seu treino.', 'contorno' ) ),
				array( 'icon' => 'sparkles', 'label' => __( 'Seu melhor começa agora', 'contorno' ), 'text' => __( 'Estamos prontos para te receber!', 'contorno' ) ),
			);

		$whatsapp = contorno_whatsapp_link();
		$contact  = get_page_by_path( 'fale-conosco' );
		$help_url = '' !== $whatsapp ? $whatsapp : ( $contact instanceof WP_Post ? (string) get_permalink( $contact ) : home_url( '/fale-conosco/' ) );

		contorno_enqueue_component( 'unit-search' );

		ob_start();
		?>
		<section class="contorno-confirm-hero">
			<div class="site-container contorno-confirm-hero__grid">
				<div class="motion-reveal" data-contorno-reveal>
					<p class="contorno-confirm-hero__badge">
						<?php echo contorno_icon( 'check', 'contorno-confirm-hero__badge-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php echo esc_html( '' !== trim( (string) $a['eyebrow'] ) ? (string) $a['eyebrow'] : __( 'Matrícula confirmada', 'contorno' ) ); ?>
					</p>
					<h1 class="contorno-confirm-hero__title">
						<?php echo esc_html( '' !== trim( (string) $a['title'] ) ? (string) $a['title'] : __( 'Obrigado por se matricular na Contorno!', 'contorno' ) ); ?>
					</h1>
					<p class="contorno-confirm-hero__text">
						<?php
						echo esc_html(
							'' !== trim( (string) $a['text'] )
								? (string) $a['text']
								: __( 'Sua matrícula foi recebida com sucesso. Agora siga os próximos passos para começar sua jornada.', 'contorno' )
						);
						?>
					</p>

					<ul class="contorno-confirm-hero__steps">
						<?php foreach ( $steps as $step ) : ?>
							<?php if ( ! is_array( $step ) ) : continue; endif; ?>
							<li>
								<?php echo contorno_icon( (string) ( $step['icon'] ?? 'check' ), 'contorno-confirm-hero__step-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<div>
									<p class="contorno-confirm-hero__step-title"><?php echo esc_html( (string) ( $step['label'] ?? '' ) ); ?></p>
									<p class="contorno-confirm-hero__step-text"><?php echo esc_html( (string) ( $step['text'] ?? '' ) ); ?></p>
								</div>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<?php if ( '' !== $image ) : ?>
					<figure class="contorno-confirm-hero__media motion-reveal" data-contorno-reveal>
						<img src="<?php echo esc_url( $image ); ?>" alt="<?php esc_attr_e( 'Alunos treinando na Academia Contorno do Corpo', 'contorno' ); ?>" loading="lazy" decoding="async" />
					</figure>
				<?php endif; ?>
			</div>
		</section>

		<section class="contorno-confirm-puv">
			<div class="site-container motion-reveal" data-contorno-reveal>
				<p class="contorno-confirm-puv__eyebrow">
					<?php echo esc_html( '' !== trim( (string) $a['puv_eyebrow'] ) ? (string) $a['puv_eyebrow'] : __( 'Proposta única de valor', 'contorno' ) ); ?>
				</p>
				<h2 class="contorno-confirm-puv__title">
					<?php
					$puv_title = '' !== trim( (string) $a['puv_title'] )
						? (string) $a['puv_title']
						: __( 'Experiência premium.|Resultados reais.|Na prática, todo dia.', 'contorno' );
					echo wp_kses_post( str_replace( '|', '<br />', esc_html( $puv_title ) ) );
					?>
				</h2>
				<p class="contorno-confirm-puv__text">
					<?php
					echo esc_html(
						'' !== trim( (string) $a['puv_text'] )
							? (string) $a['puv_text']
							: __( 'Academias completas, estrutura de alto padrão, aulas incríveis e a maior rede do Brasil para transformar sua rotina.', 'contorno' )
					);
					?>
				</p>
			</div>
		</section>

		<section class="contorno-confirm-next">
			<div class="site-container">
				<header class="contorno-confirm-next__head motion-reveal" data-contorno-reveal>
					<p class="contorno-confirm-next__eyebrow">
						<?php echo esc_html( '' !== trim( (string) $a['next_eyebrow'] ) ? (string) $a['next_eyebrow'] : __( 'Próximo passo', 'contorno' ) ); ?>
					</p>
					<h2><?php echo esc_html( '' !== trim( (string) $a['next_title'] ) ? (string) $a['next_title'] : __( 'Prescrição de Treino', 'contorno' ) ); ?></h2>
					<p>
						<?php
						echo esc_html(
							'' !== trim( (string) $a['next_text'] )
								? (string) $a['next_text']
								: __( 'Clique na unidade onde você está matriculado para acessar o formulário e agendar a montagem do seu treino.', 'contorno' )
						);
						?>
					</p>
				</header>

				<div class="contorno-confirm-next__search motion-reveal" data-contorno-reveal>
					<?php echo do_shortcode( '[contorno_units_search placeholder="Busque sua unidade"]' ); ?>
				</div>

				<h3 class="contorno-confirm-next__subtitle"><?php esc_html_e( 'Unidades', 'contorno' ); ?></h3>

				<div class="contorno-confirm-next__grid" data-contorno-units>
					<p class="contorno-units__empty" data-contorno-units-empty hidden>
						<?php esc_html_e( 'Nenhuma unidade encontrada para essa busca. Tente outro CEP, bairro, cidade ou nome.', 'contorno' ); ?>
					</p>
					<div class="contorno-prescription-grid motion-stagger" data-contorno-reveal>
						<?php foreach ( contorno_get_units() as $unit ) : ?>
							<?php
							$prescricao = contorno_field_text( 'prescricao_url', $unit->ID );
							$fallback   = $contact instanceof WP_Post
								? add_query_arg( 'unidade', $unit->post_name, (string) get_permalink( $contact ) )
								: home_url( '/fale-conosco/?unidade=' . $unit->post_name );
							?>
							<div
								class="motion-item"
								data-contorno-unit
								data-haystack="<?php echo esc_attr( contorno_unit_search_haystack( $unit->ID ) ); ?>"
								data-postal="<?php echo esc_attr( contorno_unit_postal_digits( $unit->ID ) ); ?>"
							>
								<article class="contorno-prescription-card">
									<div class="contorno-prescription-card__head">
										<span class="contorno-prescription-card__pin"><?php echo contorno_icon( 'map-pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<div>
											<h4><?php echo esc_html( (string) get_the_title( $unit ) ); ?></h4>
											<p><?php echo esc_html( contorno_field_text( 'address', $unit->ID ) ); ?></p>
											<p><?php echo esc_html( trim( contorno_field_text( 'city', $unit->ID ) . ', ' . contorno_field_text( 'state', $unit->ID ), ', ' ) ); ?></p>
											<?php $cep = contorno_field_text( 'postal_code', $unit->ID ); ?>
											<?php if ( '' !== $cep ) : ?>
												<p><?php echo esc_html( sprintf( /* translators: %s: postal code */ __( 'CEP: %s', 'contorno' ), $cep ) ); ?></p>
											<?php endif; ?>
										</div>
									</div>
									<?php
									echo contorno_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
										__( 'Acessar formulário', 'contorno' ),
										'' !== $prescricao ? $prescricao : $fallback,
										'' !== $prescricao ? 'primary' : 'outline',
										array( 'class' => 'cta-label', 'external' => '' !== $prescricao )
									);
									?>
								</article>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			</div>
		</section>

		<section class="contorno-confirm-help">
			<div class="site-container">
				<div class="contorno-confirm-help__card motion-reveal" data-contorno-reveal>
					<div class="contorno-confirm-help__copy">
						<span class="contorno-confirm-help__icon"><?php echo contorno_icon( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div>
							<h2><?php echo esc_html( '' !== trim( (string) $a['help_title'] ) ? (string) $a['help_title'] : __( 'Precisa de ajuda?', 'contorno' ) ); ?></h2>
							<p>
								<?php
								echo esc_html(
									'' !== trim( (string) $a['help_text'] )
										? (string) $a['help_text']
										: __( 'Nossa equipe está pronta para te ajudar a iniciar sua jornada com tudo!', 'contorno' )
								);
								?>
							</p>
						</div>
					</div>
					<?php
					echo contorno_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						'' !== trim( (string) $a['help_label'] ) ? (string) $a['help_label'] : __( 'Fale conosco', 'contorno' ),
						$help_url,
						'primary',
						array( 'class' => 'cta-label', 'external' => '' !== $whatsapp )
					);
					?>
				</div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);
