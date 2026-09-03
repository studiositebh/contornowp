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

				<div class="contorno-contact__row">
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
						<label for="contorno-contact-phone">
							<?php esc_html_e( 'Telefone', 'contorno' ); ?>
							<span class="contorno-field-optional"><?php esc_html_e( '(opcional)', 'contorno' ); ?></span>
						</label>
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

				<button type="submit" class="contorno-btn contorno-btn--primary cta-label contorno-contact__submit">
					<?php echo esc_html( '' !== trim( (string) $a['cta_label'] ) ? (string) $a['cta_label'] : __( 'Enviar mensagem', 'contorno' ) ); ?>
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
				'eyebrow' => '',
				'title'   => '',
				'text'    => '',
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
		?>
		<div class="contorno-channels motion-reveal" data-contorno-reveal>
			<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

			<ul class="contorno-channels__list">
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
