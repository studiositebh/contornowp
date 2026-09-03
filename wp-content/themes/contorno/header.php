<?php
/**
 * Abertura do documento e header institucional.
 *
 * IMPORTANTE: nas paginas CTN o header institucional NAO e renderizado.
 * Elas comecam direto no Hero dark, com o logo CTN sobre o proprio hero.
 *
 * @package Contorno
 */

declare( strict_types = 1 );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>

<body <?php body_class( contorno_is_ctn_context() ? 'contorno-ctn-context' : '' ); ?>>
<?php wp_body_open(); ?>

<a class="contorno-skip-link screen-reader-text" href="#contorno-main"><?php esc_html_e( 'Ir para o conteúdo', 'contorno' ); ?></a>

<?php if ( contorno_show_site_header() ) : ?>
	<header class="site-header" data-contorno-header>
		<div class="site-container site-header__inner">
			<a class="site-header__brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
				<?php contorno_render_logo(); ?>
			</a>

			<nav class="site-header__nav" aria-label="<?php esc_attr_e( 'Menu principal', 'contorno' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'site-header__menu',
						'depth'          => 2,
						'link_before'    => '<span class="site-nav-link">',
						'link_after'     => '</span>',
						'fallback_cb'    => 'contorno_default_nav_fallback',
					)
				);
				?>
			</nav>

			<div class="site-header__actions">
				<?php echo contorno_button( __( 'Encontre sua unidade', 'contorno' ), get_post_type_archive_link( CONTORNO_CPT_UNIT ) ?: home_url( '/unidades/' ), 'primary', array( 'class' => 'cta-label site-header__cta' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<button
					type="button"
					class="site-header__toggle"
					data-contorno-menu-toggle
					aria-expanded="false"
					aria-controls="contorno-mobile-menu"
				>
					<span class="screen-reader-text"><?php esc_html_e( 'Abrir menu', 'contorno' ); ?></span>
					<span class="site-header__toggle-bars" aria-hidden="true"></span>
				</button>
			</div>
		</div>

		<div class="site-header__mobile" id="contorno-mobile-menu" data-contorno-mobile-menu hidden>
			<div class="site-container">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'site-header__mobile-menu',
						'depth'          => 2,
						'fallback_cb'    => 'contorno_default_nav_fallback',
					)
				);
				?>
				<?php echo contorno_button( __( 'Matricule-se', 'contorno' ), contorno_enrollment_url(), 'primary', array( 'class' => 'cta-label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</div>
		</div>
	</header>
<?php endif; ?>

<main id="contorno-main" class="site-main">
