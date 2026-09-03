<?php
/**
 * Rodape — mesmo desenho nas paginas comuns e nas CTNs.
 *
 * @package Contorno
 */

declare( strict_types = 1 );

$contorno_whatsapp = function_exists( 'contorno_whatsapp_link' ) ? contorno_whatsapp_link() : '';
$contorno_brand    = function_exists( 'contorno_brand' ) ? contorno_brand() : array();

?>
</main>

<footer class="site-footer">
	<div class="site-container site-footer__top">
		<div class="site-footer__brand">
			<?php contorno_render_logo( 'light' ); ?>
			<?php if ( ! empty( $contorno_brand['description'] ) ) : ?>
				<p class="site-footer__about"><?php echo esc_html( (string) $contorno_brand['description'] ); ?></p>
			<?php endif; ?>

			<?php if ( ! empty( $contorno_brand['instagram'] ) ) : ?>
				<ul class="site-footer__social">
					<li>
						<a href="<?php echo esc_url( (string) $contorno_brand['instagram'] ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Instagram', 'contorno' ); ?>
						</a>
					</li>
				</ul>
			<?php endif; ?>
		</div>

		<nav class="site-footer__col" aria-label="<?php esc_attr_e( 'Unidades', 'contorno' ); ?>">
			<h2 class="site-footer__title"><?php esc_html_e( 'Unidades', 'contorno' ); ?></h2>
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'footer_units',
					'container'      => false,
					'menu_class'     => 'site-footer__menu',
					'depth'          => 1,
					'fallback_cb'    => false,
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
					'fallback_cb'    => false,
				)
			);
			?>
		</nav>

		<div class="site-footer__col">
			<h2 class="site-footer__title"><?php esc_html_e( 'Contato', 'contorno' ); ?></h2>
			<ul class="site-footer__menu">
				<?php if ( ! empty( $contorno_brand['email'] ) ) : ?>
					<li><a href="mailto:<?php echo esc_attr( (string) $contorno_brand['email'] ); ?>"><?php echo esc_html( (string) $contorno_brand['email'] ); ?></a></li>
				<?php endif; ?>
				<?php if ( '' !== $contorno_whatsapp ) : ?>
					<li><a href="<?php echo esc_url( $contorno_whatsapp ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Falar no WhatsApp', 'contorno' ); ?></a></li>
				<?php endif; ?>
			</ul>
		</div>
	</div>

	<div class="site-container site-footer__bottom">
		<p class="site-footer__copy">
			&copy; <?php echo esc_html( (string) gmdate( 'Y' ) ); ?> <?php echo esc_html( (string) ( $contorno_brand['long_name'] ?? '' ) ); ?>
		</p>
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'footer_legal',
				'container'      => false,
				'menu_class'     => 'site-footer__legal',
				'depth'          => 1,
				'fallback_cb'    => false,
			)
		);
		?>
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
