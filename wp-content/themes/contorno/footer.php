<?php
/**
 * Rodape — mesmo desenho nas paginas comuns e nas CTNs.
 *
 * @package Contorno
 */

declare( strict_types = 1 );

$contorno_whatsapp = function_exists( 'contorno_whatsapp_link' ) ? contorno_whatsapp_link() : '';
$contorno_brand    = function_exists( 'contorno_brand' ) ? contorno_brand() : array();
$contorno_phone    = (string) ( $contorno_brand['phone'] ?? '(31) 4042-0177' );
$contorno_email    = (string) ( $contorno_brand['email'] ?? 'contato@contornodocorpo.com.br' );
$contorno_cta_bg   = function_exists( 'contorno_asset_url' ) ? contorno_asset_url( '/brand/cta-gym.jpg' ) : '';

// "(31) 4042-0177" — mesmo formato de formatBrazilianPhone() do React.
$contorno_phone_digits = (string) preg_replace( '/\D+/', '', $contorno_phone );
$contorno_phone_label  = 10 === strlen( $contorno_phone_digits )
	? sprintf( '(%s) %s-%s', substr( $contorno_phone_digits, 0, 2 ), substr( $contorno_phone_digits, 2, 4 ), substr( $contorno_phone_digits, 6 ) )
	: ( 11 === strlen( $contorno_phone_digits )
		? sprintf( '(%s) %s-%s', substr( $contorno_phone_digits, 0, 2 ), substr( $contorno_phone_digits, 2, 5 ), substr( $contorno_phone_digits, 7 ) )
		: $contorno_phone );

?>
</main>

<?php if ( is_front_page() ) : ?>
	<section class="site-footer-cta" <?php echo '' !== $contorno_cta_bg ? 'style="--footer-cta-bg:url(' . esc_url( $contorno_cta_bg ) . ')"' : ''; ?>>
		<div class="site-container site-footer-cta__inner">
			<div class="site-footer-cta__copy">
				<h2><?php esc_html_e( 'O momento de mudar é agora.', 'contorno' ); ?><br /><?php esc_html_e( 'Matricule-se e transforme sua vida!', 'contorno' ); ?></h2>
			</div>
			<?php echo contorno_button( __( 'Matricule-se', 'contorno' ), contorno_enrollment_url(), 'primary', array( 'class' => 'cta-label site-footer-cta__button', 'icon' => 'arrow-right' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</section>
<?php endif; ?>

<footer class="site-footer">
	<div class="site-container site-footer__top">
		<div class="site-footer__brand">
			<?php if ( function_exists( 'contorno_is_ctn_context' ) && contorno_is_ctn_context() && function_exists( 'contorno_ctn_logo' ) ) : ?>
				<?php /* Variante CTN do Footer.tsx: logo CTN e tagline oficial. */ ?>
				<?php echo contorno_ctn_logo( 'site-footer__ctn-logo' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<p class="site-footer__about"><?php esc_html_e( 'YOUR ONLY LIMIT IS YOU. O maior e mais completo CT de BH espera por você.', 'contorno' ); ?></p>
			<?php else : ?>
				<?php contorno_render_logo( 'light' ); ?>
				<p class="site-footer__about"><?php esc_html_e( 'A maior rede de academias do Brasil, com unidades modernas, completas e feitas para você evoluir todos os dias.', 'contorno' ); ?></p>
			<?php endif; ?>

			<?php
			/*
			 * Redes sociais como no React: quatro circulos; os canais ainda sem
			 * URL aparecem esmaecidos ("em breve") em vez de sumir.
			 */
			$contorno_social = array(
				'instagram' => array( __( 'Instagram', 'contorno' ), (string) ( $contorno_brand['instagram'] ?? '' ) ),
				'facebook'  => array( __( 'Facebook', 'contorno' ), (string) ( $contorno_brand['facebook'] ?? '' ) ),
				'youtube'   => array( __( 'YouTube', 'contorno' ), (string) ( $contorno_brand['youtube'] ?? '' ) ),
				'tiktok'    => array( __( 'TikTok', 'contorno' ), (string) ( $contorno_brand['tiktok'] ?? '' ) ),
			);
			?>
			<ul class="site-footer__social">
				<?php foreach ( $contorno_social as $contorno_icon_name => $contorno_item ) : ?>
					<li>
						<?php if ( '' !== $contorno_item[1] ) : ?>
							<a class="site-footer__social-link" href="<?php echo esc_url( $contorno_item[1] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr( $contorno_item[0] ); ?>">
								<?php echo contorno_icon( $contorno_icon_name, 'site-footer__social-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</a>
						<?php else : ?>
							<span class="site-footer__social-link is-soon" title="<?php echo esc_attr( sprintf( /* translators: %s: social network */ __( '%s em breve', 'contorno' ), $contorno_item[0] ) ); ?>">
								<?php echo contorno_icon( $contorno_icon_name, 'site-footer__social-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: %s: social network */ __( '%s em breve', 'contorno' ), $contorno_item[0] ) ); ?></span>
							</span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>

		<nav class="site-footer__col" aria-label="<?php esc_attr_e( 'Links rápidos', 'contorno' ); ?>">
			<h2 class="site-footer__title"><?php esc_html_e( 'Links rápidos', 'contorno' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer_units',
					'container'      => false,
					'menu_class'     => 'site-footer__menu',
					'depth'          => 1,
					'fallback_cb'    => static function (): void {
						echo '<ul class="site-footer__menu">';
						foreach (
							array(
								'/unidades/'     => __( 'Unidades', 'contorno' ),
								'/ctn/'          => __( 'CTN', 'contorno' ),
								'/sobre/'        => __( 'Sobre', 'contorno' ),
								'/blog/'         => __( 'Blog', 'contorno' ),
								'/fale-conosco/' => __( 'Fale Conosco', 'contorno' ),
							) as $url => $label
						) {
							printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( $url ) ), esc_html( $label ) );
						}
						echo '</ul>';
					},
				)
			);
			?>
		</nav>

		<nav class="site-footer__col" aria-label="<?php esc_attr_e( 'Institucional', 'contorno' ); ?>">
			<h2 class="site-footer__title"><?php esc_html_e( 'Institucional', 'contorno' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer_inst',
					'container'      => false,
					'menu_class'     => 'site-footer__menu',
					'depth'          => 1,
					'fallback_cb'    => static function (): void {
						echo '<ul class="site-footer__menu">';
						foreach (
							array(
								'/clube-contorno/'          => __( 'Clube Contorno', 'contorno' ),
								'/aplicativo/'              => __( 'Aplicativo', 'contorno' ),
								'/regulamentos/'            => __( 'Regulamentos', 'contorno' ),
								'/planos/'                  => __( 'Planos', 'contorno' ),
								'/politica-de-privacidade/' => __( 'Política de Privacidade', 'contorno' ),
								'/termos-de-uso/'           => __( 'Termos de Uso', 'contorno' ),
							) as $url => $label
						) {
							printf( '<li><a href="%s">%s</a></li>', esc_url( home_url( $url ) ), esc_html( $label ) );
						}
						echo '</ul>';
					},
				)
			);
			?>
		</nav>

		<div class="site-footer__col">
			<h2 class="site-footer__title"><?php esc_html_e( 'Atendimento', 'contorno' ); ?></h2>
			<ul class="site-footer__contact">
				<?php if ( '' !== $contorno_phone ) : ?>
					<li>
						<?php echo contorno_icon( 'phone', 'site-footer__contact-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<a href="tel:+55<?php echo esc_attr( $contorno_phone_digits ); ?>"><?php echo esc_html( $contorno_phone_label ); ?></a>
					</li>
				<?php endif; ?>
				<?php if ( '' !== $contorno_email ) : ?>
					<li>
						<?php echo contorno_icon( 'mail', 'site-footer__contact-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<a href="mailto:<?php echo esc_attr( $contorno_email ); ?>"><?php echo esc_html( $contorno_email ); ?></a>
					</li>
				<?php endif; ?>
				<li>
					<?php echo contorno_icon( 'clock', 'site-footer__contact-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span><?php esc_html_e( 'Seg a Sex: 06h às 23h', 'contorno' ); ?><br /><?php esc_html_e( 'Sáb e Dom: 06h às 18h', 'contorno' ); ?></span>
				</li>
			</ul>
		</div>
	</div>

	<div class="site-footer__bottom-wrap">
		<div class="site-container site-footer__bottom">
			<p class="site-footer__copy">
				&copy; <?php echo esc_html( (string) gmdate( 'Y' ) ); ?> <?php echo esc_html( (string) ( $contorno_brand['long_name'] ?? '' ) ); ?>. <?php esc_html_e( 'Todos os direitos reservados.', 'contorno' ); ?>
			</p>
			<a class="site-footer__credit" href="https://voceconecta.com.br/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Desenvolvido por Conecta Digital', 'contorno' ); ?></a>
		</div>
	</div>
</footer>

<?php if ( '' !== $contorno_whatsapp ) : ?>
	<a
		class="contorno-floating-whatsapp animate-whatsapp-pulse"
		href="<?php echo esc_url( $contorno_whatsapp ); ?>"
		target="_blank"
		rel="noopener noreferrer"
		aria-label="<?php esc_attr_e( 'Falar no WhatsApp', 'contorno' ); ?>"
	>
		<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
			<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.174.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.174-.297-.019-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.05-.52-.099-.148-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.695.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c0-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L0 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413" />
		</svg>
	</a>
<?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
