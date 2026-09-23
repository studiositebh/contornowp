<?php
/**
 * Formulario de Fale Conosco.
 *
 * Reproduz o comportamento aprovado (validacao, honeypot, rate limit e
 * entrega por `mailto:`) e adiciona o caminho de servidor com nonce e
 * validacao — ver includes/forms.php para o porque de nao usar plugin.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CONTORNO — Formulário de Contato.
 */
contorno_add_shortcode(
	'contorno_contact_form',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'title'      => '',
				'text'       => '',
				'cta_label'  => '',
				'show_title' => 'no',
			),
			(array) $atts,
			'contorno_contact_form'
		);

		$limits = contorno_contact_limits();

		// Unidade pre-selecionada (?unidade=), usada pelos cards de prescricao.
		$unit_slug = isset( $_GET['unidade'] ) ? sanitize_title( wp_unslash( (string) $_GET['unidade'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$unit      = '' !== $unit_slug ? contorno_get_unit_by_slug( $unit_slug ) : null;

		// Estado devolvido pelo servidor apos o POST.
		$status = isset( $_GET['contorno_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['contorno_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$error  = isset( $_GET['contorno_erro'] ) ? sanitize_key( wp_unslash( (string) $_GET['contorno_erro'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$messages = array(
			'nonce'  => __( 'Sua sessão expirou. Recarregue a página e envie novamente.', 'contorno' ),
			'limite' => __( 'Aguarde um momento antes de enviar novamente.', 'contorno' ),
			'dados'  => __( 'Revise os campos destacados e tente novamente.', 'contorno' ),
		);

		contorno_enqueue_component( 'contact-form' );

		ob_start();
		?>
		<div class="contorno-contact" id="contorno-contato">
			<?php if ( 'yes' === $a['show_title'] ) : ?>
				<header class="contorno-contact__head">
					<?php if ( '' !== trim( (string) $a['title'] ) ) : ?>
						<h2><?php echo esc_html( (string) $a['title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( '' !== trim( (string) $a['text'] ) ) : ?>
						<p><?php echo esc_html( (string) $a['text'] ); ?></p>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<?php if ( 'ok' === $status ) : ?>
				<div class="contorno-contact__notice is-success" role="status">
					<?php echo contorno_icon( 'check', 'contorno-contact__notice-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<div>
						<p><strong><?php esc_html_e( 'Mensagem pronta para envio.', 'contorno' ); ?></strong></p>
						<p><?php esc_html_e( 'Abrimos seu aplicativo de e-mail com a mensagem preenchida. Se ele não abrir, escreva para o endereço abaixo.', 'contorno' ); ?></p>
						<p>
							<a href="mailto:<?php echo esc_attr( contorno_brand_get( 'email' ) ); ?>">
								<?php echo esc_html( contorno_brand_get( 'email' ) ); ?>
							</a>
						</p>
					</div>
				</div>
			<?php elseif ( 'erro' === $status ) : ?>
				<div class="contorno-contact__notice is-error" role="alert">
					<p><?php echo esc_html( $messages[ $error ] ?? __( 'Não foi possível enviar. Tente novamente.', 'contorno' ) ); ?></p>
				</div>
			<?php endif; ?>

			<form class="contorno-contact__form" method="post" novalidate data-contorno-contact data-mailto="<?php echo esc_attr( contorno_brand_get( 'email' ) ); ?>">
				<?php wp_nonce_field( CONTORNO_CONTACT_ACTION, 'contorno_nonce' ); ?>
				<input type="hidden" name="contorno_form" value="<?php echo esc_attr( CONTORNO_CONTACT_ACTION ); ?>" />
				<?php /* Honeypot — mesmo campo do React. */ ?>
				<input type="text" name="website" class="contorno-honeypot" tabindex="-1" autocomplete="off" aria-hidden="true" />

				<?php if ( $unit instanceof WP_Post ) : ?>
					<input type="hidden" name="unidade" value="<?php echo esc_attr( (string) $unit->post_name ); ?>" />
					<p class="contorno-contact__unit">
						<?php echo contorno_icon( 'map-pin', 'contorno-contact__unit-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<span>
							<?php
							printf(
								/* translators: %s: unit name */
								esc_html__( 'Sobre a unidade %s', 'contorno' ),
								'<strong>' . esc_html( (string) get_the_title( $unit ) ) . '</strong>'
							);
							?>
						</span>
					</p>
				<?php endif; ?>

				<div class="contorno-field-row">
					<label for="contorno-contact-name"><?php esc_html_e( 'Nome', 'contorno' ); ?></label>
					<input
						type="text"
						id="contorno-contact-name"
						name="name"
						maxlength="<?php echo esc_attr( (string) $limits['name'] ); ?>"
						autocomplete="name"
						required
					/>
					<p class="contorno-field-error" data-error-for="name" hidden></p>
				</div>

				<?php /* Campos empilhados, um por linha, como no ContactPageForm do React. */ ?>
				<div class="contorno-contact__fields">
					<div class="contorno-field-row">
						<label for="contorno-contact-email"><?php esc_html_e( 'E-mail', 'contorno' ); ?></label>
						<input
							type="email"
							id="contorno-contact-email"
							name="email"
							maxlength="<?php echo esc_attr( (string) $limits['email'] ); ?>"
							autocomplete="email"
							required
						/>
						<p class="contorno-field-error" data-error-for="email" hidden></p>
					</div>

					<div class="contorno-field-row">
						<label for="contorno-contact-phone"><?php esc_html_e( 'Telefone', 'contorno' ); ?></label>
						<input
							type="tel"
							id="contorno-contact-phone"
							name="phone"
							maxlength="<?php echo esc_attr( (string) $limits['phone'] ); ?>"
							inputmode="tel"
							autocomplete="tel"
							data-contorno-phone-mask
						/>
						<p class="contorno-field-error" data-error-for="phone" hidden></p>
					</div>

					<?php if ( ! $unit instanceof WP_Post ) : ?>
						<div class="contorno-field-row">
							<label for="contorno-contact-unit"><?php esc_html_e( 'Unidade', 'contorno' ); ?></label>
							<select id="contorno-contact-unit" name="unidade">
								<option value=""><?php esc_html_e( 'Selecione', 'contorno' ); ?></option>
								<?php foreach ( contorno_get_units() as $contact_unit ) : ?>
									<option value="<?php echo esc_attr( (string) $contact_unit->post_name ); ?>" <?php selected( $unit_slug, (string) $contact_unit->post_name ); ?>><?php echo esc_html( (string) get_the_title( $contact_unit ) ); ?></option>
								<?php endforeach; ?>
							</select>
						</div>
					<?php endif; ?>
				</div>

				<div class="contorno-field-row">
					<label for="contorno-contact-message"><?php esc_html_e( 'Mensagem', 'contorno' ); ?></label>
					<textarea
						id="contorno-contact-message"
						name="message"
						rows="6"
						maxlength="<?php echo esc_attr( (string) $limits['message'] ); ?>"
						required
					></textarea>
					<p class="contorno-field-error" data-error-for="message" hidden></p>
				</div>

				<p class="contorno-field-error" data-error-for="form" hidden></p>

				<button type="submit" class="contorno-btn contorno-btn--primary contorno-contact__submit">
					<?php echo esc_html( '' !== trim( (string) $a['cta_label'] ) ? (string) $a['cta_label'] : __( 'Enviar', 'contorno' ) ); ?>
				</button>

				<p class="contorno-contact__privacy">
					<?php
					$privacy = get_page_by_path( 'politica-de-privacidade' );
					printf(
						/* translators: %s: privacy policy link */
						esc_html__( 'Ao enviar, você concorda com a %s.', 'contorno' ),
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( $privacy instanceof WP_Post ? (string) get_permalink( $privacy ) : home_url( '/politica-de-privacidade/' ) ),
							esc_html__( 'política de privacidade', 'contorno' )
						)
					);
					?>
				</p>
			</form>
		</div>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Canais de Atendimento.
 *
 * Bloco de telefone, WhatsApp e e-mail ao lado do formulario.
 */
contorno_add_shortcode(
	'contorno_contact_channels',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow'       => '',
				'title'         => '',
				'text'          => '',
				// O React mostra so eyebrow + H1 + texto; a lista de canais e opcional.
				'show_channels' => 'no',
				// "cards": canais lado a lado, sem formulario (pagina Fale Conosco).
				'layout'        => 'list',
			),
			(array) $atts,
			'contorno_contact_channels'
		);

		$phone    = contorno_brand_get( 'phone' );
		$email    = contorno_brand_get( 'email' );
		$whatsapp = contorno_whatsapp_link();

		$digits = preg_replace( '/\D/', '', $phone );
		$digits = is_string( $digits ) ? $digits : '';

		// Formato brasileiro de exibicao — porte de formatBrazilianPhone.
		$display = $phone;
		if ( 10 === strlen( $digits ) ) {
			$display = sprintf( '(%s) %s-%s', substr( $digits, 0, 2 ), substr( $digits, 2, 4 ), substr( $digits, 6 ) );
		} elseif ( 11 === strlen( $digits ) ) {
			$display = sprintf( '(%s) %s-%s', substr( $digits, 0, 2 ), substr( $digits, 2, 5 ), substr( $digits, 7 ) );
		}

		ob_start();

		/*
		 * layout="cards": pagina Fale Conosco sem formulario — os canais viram
		 * cartoes lado a lado (WhatsApp em destaque, e-mail, telefone), empilhados
		 * no celular. O layout padrao (lista) continua para quem ja o usa.
		 */
		if ( 'cards' === $a['layout'] ) {
			$cards = array();

			if ( '' !== $whatsapp ) {
				$cards[] = array(
					'key'      => 'whatsapp',
					'label'    => __( 'WhatsApp', 'contorno' ),
					'value'    => $display,
					'text'     => __( 'Atendimento mais rápido: fale com a nossa equipe agora.', 'contorno' ),
					'cta'      => __( 'Chamar no WhatsApp', 'contorno' ),
					'url'      => $whatsapp,
					'external' => true,
				);
			}

			if ( '' !== $email ) {
				$cards[] = array(
					'key'      => 'email',
					'label'    => __( 'E-mail', 'contorno' ),
					'value'    => $email,
					'text'     => __( 'Para dúvidas, sugestões e assuntos que pedem mais detalhes.', 'contorno' ),
					'cta'      => __( 'Enviar e-mail', 'contorno' ),
					'url'      => 'mailto:' . $email,
					'external' => false,
				);
			}

			if ( '' !== $display ) {
				$cards[] = array(
					'key'      => 'phone',
					'label'    => __( 'Telefone', 'contorno' ),
					'value'    => $display,
					'text'     => __( 'Prefere conversar? Ligue para a nossa central.', 'contorno' ),
					'cta'      => __( 'Ligar agora', 'contorno' ),
					'url'      => 'tel:+55' . $digits,
					'external' => false,
				);
			}
			?>
			<div class="contorno-channels contorno-channels--cards motion-reveal" data-contorno-reveal>
				<?php echo str_replace( array( '<h2 class="contorno-section-header__title">', '</h2>' ), array( '<h1 class="contorno-section-header__title contorno-channels__title">', '</h1>' ), contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<ul class="contorno-channels__cards">
					<?php foreach ( $cards as $card ) : ?>
						<li class="contorno-channel-card is-<?php echo esc_attr( $card['key'] ); ?>">
							<a
								class="contorno-channel-card__link"
								href="<?php echo 'email' === $card['key'] || 'phone' === $card['key'] ? esc_attr( $card['url'] ) : esc_url( $card['url'] ); ?>"
								<?php echo $card['external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
							>
								<span class="contorno-channel-card__icon" aria-hidden="true">
									<?php if ( 'whatsapp' === $card['key'] ) : ?>
										<svg viewBox="0 0 24 24" fill="currentColor" focusable="false"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.174-.297-.019-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.05-.52-.099-.148-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.695.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413"/></svg>
									<?php else : ?>
										<?php echo contorno_icon( 'email' === $card['key'] ? 'mail' : 'phone', 'contorno-channel-card__svg' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php endif; ?>
								</span>
								<span class="contorno-channel-card__label"><?php echo esc_html( $card['label'] ); ?></span>
								<span class="contorno-channel-card__value"><?php echo esc_html( $card['value'] ); ?></span>
								<span class="contorno-channel-card__text"><?php echo esc_html( $card['text'] ); ?></span>
								<span class="contorno-channel-card__cta cta-label">
									<?php echo esc_html( $card['cta'] ); ?>
									<?php echo contorno_icon( 'arrow-right', 'contorno-channel-card__arrow' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php

			return (string) ob_get_clean();
		}
		?>
		<div class="contorno-channels motion-reveal" data-contorno-reveal>
			<?php echo str_replace( array( '<h2 class="contorno-section-header__title">', '</h2>' ), array( '<h1 class="contorno-section-header__title contorno-channels__title">', '</h1>' ), contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<ul class="contorno-channels__list" <?php echo 'yes' === $a['show_channels'] ? '' : 'hidden'; ?>>
				<?php if ( '' !== $display ) : ?>
					<li>
						<?php echo contorno_icon( 'phone', 'contorno-channels__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div>
							<span class="contorno-channels__label"><?php esc_html_e( 'Telefone', 'contorno' ); ?></span>
							<a href="tel:+55<?php echo esc_attr( $digits ); ?>"><?php echo esc_html( $display ); ?></a>
						</div>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $whatsapp ) : ?>
					<li>
						<?php echo contorno_icon( 'phone', 'contorno-channels__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div>
							<span class="contorno-channels__label"><?php esc_html_e( 'WhatsApp', 'contorno' ); ?></span>
							<a href="<?php echo esc_url( $whatsapp ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Falar agora', 'contorno' ); ?></a>
						</div>
					</li>
				<?php endif; ?>

				<?php if ( '' !== $email ) : ?>
					<li>
						<?php echo contorno_icon( 'external', 'contorno-channels__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<div>
							<span class="contorno-channels__label"><?php esc_html_e( 'E-mail', 'contorno' ); ?></span>
							<a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
						</div>
					</li>
				<?php endif; ?>
			</ul>
		</div>
		<?php

		return (string) ob_get_clean();
	}
);
