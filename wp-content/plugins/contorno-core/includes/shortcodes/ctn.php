<?php
/**
 * Componentes das landings CTN (skin dark premium).
 *
 * Regras de identidade que estes componentes preservam:
 *  - Hero dark com /brand/ctn-logo.webp SOBRE o proprio hero; sem header
 *    institucional branco.
 *  - PUV com maquina/equipamento premium como protagonista (Panatta quando o
 *    material oficial permitir) — nunca foto generica de academia.
 *  - Sobre com VIDEO VERTICAL 9:16 (nao voltar para imagem estatica).
 *  - Videos: UM video Ronnie principal + box vertical "Ver Mais" com link de
 *    playlist. Nao trazer todos os videos para a landing.
 *  - Planos com a mesma arquitetura/UX das unidades, em pele dark.
 *
 * Galeria, planos, localizacao e aulas usam os componentes genericos
 * (`[contorno_gallery ctn="..."]`, `[contorno_plans ctn="..." skin="dark"]`),
 * garantindo a mesma UX das unidades.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logo CTN sobre o hero.
 */
function contorno_ctn_logo( string $classes = 'ctn-logo' ): string {
	$src = contorno_asset_url( contorno_brand_get( 'ctn_logo' ) );

	if ( '' === $src ) {
		return '';
	}

	return sprintf(
		'<img class="%s" src="%s" alt="%s" width="220" height="80" fetchpriority="high" decoding="async" />',
		esc_attr( $classes ),
		esc_url( $src ),
		esc_attr__( 'CTN — Centro de Treinamento Contorno', 'contorno' )
	);
}

/**
 * CTN — Hero.
 */
contorno_add_shortcode(
	'ctn_hero',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'ctn'        => '',
				'eyebrow'    => '',
				'title'      => '',
				'headline'   => '',
				'subtitle'   => '',
				'image'      => '',
				'image_alt'  => '',
				'cta_label'  => '',
				'cta_url'    => '',
				'cta2_label' => '',
				'cta2_url'   => '',
				'show_logo'  => 'yes',
				'tagline'    => '',
			),
			(array) $atts,
			'ctn_hero'
		);

		$post_id = contorno_resolve_context_id( (array) $a, CONTORNO_CPT_CTN );

		// Cada campo cai no valor do registro da CTN quando nao informado no builder.
		$eyebrow  = '' !== trim( (string) $a['eyebrow'] ) ? (string) $a['eyebrow'] : contorno_field_text( 'hero_eyebrow', $post_id );
		$title    = '' !== trim( (string) $a['title'] ) ? (string) $a['title'] : contorno_field_text( 'hero_title', $post_id, (string) get_the_title( $post_id ) );
		$headline = '' !== trim( (string) $a['headline'] ) ? (string) $a['headline'] : contorno_field_text( 'hero_headline', $post_id );
		$subtitle = '' !== trim( (string) $a['subtitle'] ) ? (string) $a['subtitle'] : contorno_field_text( 'hero_subtitle', $post_id );
		// O hero aprovado nao mostra a tagline; ela aparece na PUV.
		// O atributo continua aceito para nao quebrar paginas que ja o usem.
		unset( $a['tagline'] );

		$image = contorno_attr_image( $a['image'], 'contorno-hero' );
		if ( '' === $image ) {
			$image = contorno_field_image_url( 'hero_image', $post_id, 'contorno-hero' );
		}
		if ( '' === $image ) {
			$image = (string) get_the_post_thumbnail_url( $post_id, 'contorno-hero' );
		}

		$alt = '' !== trim( (string) $a['image_alt'] ) ? (string) $a['image_alt'] : contorno_field_text( 'hero_image_alt', $post_id );

		ob_start();
		?>
		<?php
		/*
		 * Hierarquia fiel ao CTNHero do React:
		 *   eyebrow  -> linha turquesa pequena
		 *   title    -> linha branca pequena em caixa alta ("CTN CASTELO")
		 *   headline -> H1 grande ("O MELHOR EM ALTA PERFORMANCE ESTA AQUI")
		 * Inverter isso deixava o nome da CTN gigante e a promessa miuda.
		 */
		$cta_label  = '' !== trim( (string) $a['cta_label'] ) ? (string) $a['cta_label'] : __( 'Matricule-se agora', 'contorno' );
		$cta_url    = '' !== trim( (string) $a['cta_url'] ) ? (string) $a['cta_url'] : '#planos';
		$cta2_label = '' !== trim( (string) $a['cta2_label'] ) ? (string) $a['cta2_label'] : __( 'Conheça a estrutura', 'contorno' );
		$cta2_url   = '' !== trim( (string) $a['cta2_url'] ) ? (string) $a['cta2_url'] : '#estrutura';

		// Faixa de informações: bairro • cidade • primeiro número da CTN.
		$stats = contorno_field_list( 'stats', $post_id );
		$facts = array_values(
			array_filter(
				array(
					contorno_field_text( 'neighborhood', $post_id ),
					contorno_field_text( 'city', $post_id ),
					isset( $stats[0]['value'] ) ? (string) $stats[0]['value'] : '',
				),
				static fn ( string $fact ): bool => '' !== trim( $fact )
			)
		);
		?>
		<section class="ctn-hero">
			<?php if ( '' !== $image ) : ?>
				<img class="ctn-hero__media motion-hero-media" src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $alt ); ?>" fetchpriority="high" decoding="async" />
			<?php endif; ?>
			<span class="ctn-hero__scrim" aria-hidden="true"></span>
			<span class="ctn-hero__scrim-v" aria-hidden="true"></span>

			<div class="site-container ctn-hero__inner">
				<?php if ( 'yes' === $a['show_logo'] ) : ?>
					<div class="ctn-hero__logo"><?php echo contorno_ctn_logo( 'ctn-hero__logo-img' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<?php endif; ?>

				<div class="ctn-hero__body">
					<div class="ctn-hero__copy motion-hero-copy">
						<?php if ( '' !== $eyebrow ) : ?>
							<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $title ) : ?>
							<p class="ctn-hero__kicker"><?php echo esc_html( $title ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $headline ) : ?>
							<h1 class="ctn-hero__title"><?php echo esc_html( $headline ); ?></h1>
						<?php elseif ( '' !== $title ) : ?>
							<h1 class="ctn-hero__title"><?php echo esc_html( $title ); ?></h1>
						<?php endif; ?>

						<?php if ( '' !== $subtitle ) : ?>
							<p class="ctn-hero__subtitle"><?php echo esc_html( $subtitle ); ?></p>
						<?php endif; ?>

						<div class="ctn-hero__actions motion-hero-actions">
							<?php echo contorno_button( $cta_label, $cta_url, 'primary', array( 'class' => 'cta-label ctn-prime-btn' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php echo contorno_button( $cta2_label, $cta2_url, 'ghost-dark', array( 'class' => 'cta-label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>

						<?php if ( array() !== $facts ) : ?>
							<ul class="ctn-hero__facts">
								<?php foreach ( $facts as $index => $fact ) : ?>
									<?php if ( $index > 0 ) : ?>
										<li class="ctn-hero__facts-sep" aria-hidden="true">&bull;</li>
									<?php endif; ?>
									<li><?php echo esc_html( $fact ); ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

					</div>
				</div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CTN — PUV.
 *
 * Imagem de equipamento premium como protagonista + introducao + stats.
 * A imagem cai para a fotografia institucional Panatta quando a CTN nao tem
 * foto propria de maquina em primeiro plano.
 */
contorno_add_shortcode(
	'ctn_puv',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'ctn'        => '',
				'eyebrow'    => '',
				'title'      => '',
				'text'       => '',
				'image'      => '',
				'image_alt'  => '',
				'extra_text' => '',
				'cta_label'  => '',
				'cta_url'    => '',
				'show_stats' => 'yes',
			),
			(array) $atts,
			'ctn_puv'
		);

		$post_id = contorno_resolve_context_id( (array) $a, CONTORNO_CPT_CTN );

		$title = '' !== trim( (string) $a['title'] ) ? (string) $a['title'] : contorno_field_text( 'intro_title', $post_id );

		$paragraphs = '' !== trim( (string) $a['text'] )
			? contorno_lines_to_array( (string) $a['text'] )
			: array_map( 'strval', contorno_field_list( 'intro_body', $post_id ) );

		$image = contorno_attr_image( $a['image'], 'contorno-hero' );
		$alt   = (string) $a['image_alt'];

		if ( '' === $image ) {
			$vp    = contorno_ctn_value_proposition( $post_id );
			$image = $vp['image'];
			$alt   = '' !== $alt ? $alt : $vp['alt'];
		}

		$stats = 'yes' === $a['show_stats'] ? contorno_field_list( 'stats', $post_id ) : array();

		ob_start();
		?>
		<section class="ctn-puv">
			<div class="site-container">
				<div class="ctn-puv__grid">
					<div class="ctn-puv__copy motion-reveal" data-contorno-reveal>
						<?php if ( '' !== trim( (string) $a['eyebrow'] ) ) : ?>
							<p class="eyebrow"><?php echo esc_html( (string) $a['eyebrow'] ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== $title ) : ?>
							<h2 class="ctn-puv__title"><?php echo esc_html( $title ); ?></h2>
						<?php endif; ?>

						<?php foreach ( $paragraphs as $paragraph ) : ?>
							<p class="ctn-puv__paragraph"><?php echo esc_html( (string) $paragraph ); ?></p>
						<?php endforeach; ?>

						<?php if ( '' !== trim( (string) $a['extra_text'] ) ) : ?>
							<p class="ctn-puv__extra"><?php echo esc_html( (string) $a['extra_text'] ); ?></p>
						<?php endif; ?>

						<?php echo contorno_button( (string) $a['cta_label'], (string) $a['cta_url'], 'primary', array( 'class' => 'ctn-prime-btn' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<?php if ( '' !== $image ) : ?>
						<figure class="ctn-puv__media motion-reveal motion-ctn motion-ctn-media" data-contorno-reveal>
							<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" decoding="async" />
						</figure>
					<?php endif; ?>
				</div>

				<?php if ( array() !== $stats ) : ?>
					<div class="ctn-stats motion-stagger" data-contorno-reveal>
						<?php foreach ( $stats as $stat ) : ?>
							<?php if ( ! is_array( $stat ) ) : continue; endif; ?>
							<div class="ctn-stats__item motion-item">
								<strong><?php echo esc_html( (string) ( $stat['value'] ?? '' ) ); ?></strong>
								<span><?php echo esc_html( (string) ( $stat['label'] ?? '' ) ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CTN — Sobre (video vertical 9:16).
 */
contorno_add_shortcode(
	'ctn_about',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'ctn'      => '',
				'eyebrow'  => '',
				'title'    => '',
				'text'     => '',
				'video'    => '',
				'video_id' => '',
				'poster'   => '',
				'caption'  => '',
				'features' => '',
			),
			(array) $atts,
			'ctn_about'
		);

		$post_id = contorno_resolve_context_id( (array) $a, CONTORNO_CPT_CTN );

		$paragraphs = '' !== trim( (string) $a['text'] )
			? contorno_lines_to_array( (string) $a['text'] )
			: array_map( 'strval', contorno_field_list( 'intro_body', $post_id ) );

		$features = '' !== trim( (string) $a['features'] )
			? contorno_lines_to_array( (string) $a['features'] )
			: array_map( 'strval', contorno_field_list( 'features', $post_id ) );

		// Arquivo local tem prioridade; senao Short/video vertical do YouTube.
		$src      = '' !== trim( (string) $a['video'] ) ? contorno_resolve_media( $a['video'] ) : contorno_field_text( 'about_video_src', $post_id );
		$youtube  = '' !== trim( (string) $a['video_id'] ) ? (string) $a['video_id'] : contorno_field_text( 'about_video_youtube', $post_id );
		$poster   = contorno_attr_image( $a['poster'], 'contorno-vertical' );
		$caption  = '' !== trim( (string) $a['caption'] ) ? (string) $a['caption'] : contorno_field_text( 'about_video_caption', $post_id );
		$vid_title = contorno_field_text( 'about_video_title', $post_id, (string) get_the_title( $post_id ) );

		if ( '' === $poster ) {
			$poster = contorno_field_image_url( 'about_video_poster', $post_id, 'contorno-vertical' );
		}
		if ( '' === $poster && '' !== $youtube ) {
			$poster = contorno_youtube_vertical_poster( $youtube );
		}

		contorno_enqueue_component( 'lazy-video' );

		ob_start();
		?>
		<section class="ctn-about" id="sobre">
			<div class="site-container">
				<div class="ctn-about__grid">
					<div class="ctn-about__copy motion-reveal" data-contorno-reveal>
						<?php if ( '' !== trim( (string) $a['eyebrow'] ) ) : ?>
							<p class="eyebrow"><?php echo esc_html( (string) $a['eyebrow'] ); ?></p>
						<?php endif; ?>

						<?php if ( '' !== trim( (string) $a['title'] ) ) : ?>
							<h2 class="ctn-about__title"><?php echo esc_html( (string) $a['title'] ); ?></h2>
						<?php endif; ?>

						<?php foreach ( $paragraphs as $paragraph ) : ?>
							<p class="ctn-about__paragraph"><?php echo esc_html( (string) $paragraph ); ?></p>
						<?php endforeach; ?>

						<?php if ( array() !== $features ) : ?>
							<ul class="ctn-about__features motion-stagger">
								<?php foreach ( $features as $feature ) : ?>
									<li class="ctn-feature motion-item">
										<span class="ctn-feature-icon"><?php echo contorno_icon( contorno_icon_for_label( (string) $feature ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<span class="ctn-feature-label"><?php echo esc_html( (string) $feature ); ?></span>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<?php if ( '' !== $src || '' !== $youtube ) : ?>
						<figure class="ctn-about__video motion-reveal" data-contorno-reveal>
							<?php if ( '' !== $src ) : ?>
								<video
									class="ctn-about__video-el"
									src="<?php echo esc_url( $src ); ?>"
									<?php if ( '' !== $poster ) : ?>poster="<?php echo esc_url( $poster ); ?>"<?php endif; ?>
									controls
									playsinline
									preload="none"
								></video>
							<?php else : ?>
								<?php echo contorno_youtube_lazy_embed( $youtube, $vid_title, '9/16' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>

							<?php if ( '' !== $caption ) : ?>
								<figcaption class="ctn-about__caption"><?php echo esc_html( $caption ); ?></figcaption>
							<?php endif; ?>
						</figure>
					<?php endif; ?>
				</div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CTN — Marcas de equipamento.
 */
contorno_add_shortcode(
	'ctn_brands',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'ctn'     => '',
				'eyebrow' => '',
				'title'   => '',
				'text'    => '',
			),
			(array) $atts,
			'ctn_brands'
		);

		$post_id = contorno_resolve_context_id( (array) $a, CONTORNO_CPT_CTN );
		$brands  = contorno_ctn_brands( $post_id );

		if ( array() === $brands ) {
			return '';
		}

		ob_start();
		?>
		<section class="ctn-brands">
			<div class="site-container">
				<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'], 'center' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<div class="ctn-brands__grid motion-stagger" data-contorno-reveal>
					<?php foreach ( $brands as $brand ) : ?>
						<?php
						if ( ! is_array( $brand ) ) {
							continue;
						}
						$logo = contorno_resolve_media( $brand['logo'] ?? '', 'medium' );
						if ( '' === $logo ) {
							continue;
						}
						?>
						<div class="ctn-brands__item motion-item">
							<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( (string) ( $brand['name'] ?? '' ) ); ?>" loading="lazy" decoding="async" />
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CTN — Equipamentos (destaques com marca, titulo e paragrafos).
 */
contorno_add_shortcode(
	'ctn_equipment',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'ctn'        => '',
				'eyebrow'    => '',
				'title'      => '',
				'text'       => '',
				// Padrao TEXTO PURO, como no React: a landing aprovada nao
				// embute os videos de equipamento. Ligar so por escolha do editor.
				'show_media' => 'no',
			),
			(array) $atts,
			'ctn_equipment'
		);

		$post_id    = contorno_resolve_context_id( (array) $a, CONTORNO_CPT_CTN );
		$items      = contorno_field_list( 'equipment', $post_id );
		$show_media = 'yes' === $a['show_media'];

		if ( array() === $items ) {
			return '';
		}

		ob_start();
		?>
		<section class="ctn-equipment" id="equipamentos">
			<div class="site-container">
				<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<?php foreach ( $items as $item ) : ?>
					<?php if ( ! is_array( $item ) ) : continue; endif; ?>
					<?php
					$image      = contorno_resolve_media( $item['image'] ?? '', 'contorno-gallery' );
					$paragraphs = isset( $item['paragraphs'] ) && is_array( $item['paragraphs'] ) ? $item['paragraphs'] : array();
					$video      = (string) ( $item['video_id'] ?? '' );
					?>
					<article class="ctn-equipment__item motion-reveal" data-contorno-reveal>
						<div class="ctn-equipment__copy">
							<?php if ( ! empty( $item['brand'] ) ) : ?>
								<p class="eyebrow"><?php echo esc_html( (string) $item['brand'] ); ?></p>
							<?php endif; ?>
							<h3><?php echo esc_html( (string) ( $item['title'] ?? '' ) ); ?></h3>
							<?php foreach ( $paragraphs as $paragraph ) : ?>
								<p><?php echo esc_html( (string) $paragraph ); ?></p>
							<?php endforeach; ?>
						</div>

						<?php if ( $show_media && '' !== $video ) : ?>
							<div class="ctn-equipment__media"><?php echo contorno_youtube_lazy_embed( $video, (string) ( $item['title'] ?? '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
						<?php elseif ( $show_media && '' !== $image ) : ?>
							<figure class="ctn-equipment__media motion-ctn motion-ctn-media">
								<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( (string) ( $item['title'] ?? '' ) ); ?>" loading="lazy" decoding="async" />
							</figure>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CTN — Ronnie + Playlist.
 *
 * UM video principal 16:9 + box vertical "Ver Mais" com link para a playlist.
 */
contorno_add_shortcode(
	'ctn_videos',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'ctn'            => '',
				'eyebrow'        => '',
				'title'          => '',
				'text'           => '',
				'video_id'       => '',
				'more_title'     => '',
				'more_text'      => '',
				'more_label'     => '',
				'playlist_url'   => '',
			),
			(array) $atts,
			'ctn_videos'
		);

		$post_id = contorno_resolve_context_id( (array) $a, CONTORNO_CPT_CTN );

		$video = '' !== trim( (string) $a['video_id'] ) ? (string) $a['video_id'] : contorno_field_text( 'featured_video_id', $post_id );

		if ( '' === contorno_youtube_id( $video ) ) {
			return '';
		}

		$video_title = contorno_field_text( 'featured_video_title', $post_id, (string) get_the_title( $post_id ) );
		$playlist    = '' !== trim( (string) $a['playlist_url'] ) ? (string) $a['playlist_url'] : contorno_ctn_playlist_url( $post_id );

		$more_title = '' !== trim( (string) $a['more_title'] ) ? (string) $a['more_title'] : __( 'Ver mais', 'contorno' );
		$more_label = '' !== trim( (string) $a['more_label'] ) ? (string) $a['more_label'] : __( 'Abrir playlist', 'contorno' );

		ob_start();
		?>
		<section class="ctn-videos" id="videos">
			<div class="site-container">
				<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'], 'center' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<div class="ctn-videos__grid motion-reveal" data-contorno-reveal>
					<div class="ctn-videos__featured">
						<?php echo contorno_youtube_lazy_embed( $video, $video_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</div>

					<aside class="ctn-videos__more">
						<h3><?php echo esc_html( $more_title ); ?></h3>
						<?php if ( '' !== trim( (string) $a['more_text'] ) ) : ?>
							<p><?php echo esc_html( (string) $a['more_text'] ); ?></p>
						<?php endif; ?>
						<?php echo contorno_button( $more_label, $playlist, 'primary', array( 'icon' => 'arrow-right', 'external' => true, 'class' => 'ctn-prime-btn' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</aside>
				</div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CTN — Lista de CTNs (hub /ctn).
 */
contorno_add_shortcode(
	'ctn_list',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow' => '',
				'title'   => '',
				'text'    => '',
				'columns' => '2',
			),
			(array) $atts,
			'ctn_list'
		);

		$ctns = contorno_get_ctns();

		if ( array() === $ctns ) {
			return '';
		}

		ob_start();
		?>
		<section class="ctn-list">
			<div class="site-container">
				<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'], 'center' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

				<div class="ctn-list__grid is-columns-<?php echo esc_attr( (string) (int) $a['columns'] ); ?> motion-stagger" data-contorno-reveal>
					<?php foreach ( $ctns as $ctn ) : ?>
						<?php
						$image = contorno_field_image_url( 'hero_image', $ctn->ID, 'contorno-card' );
						if ( '' === $image ) {
							$image = (string) get_the_post_thumbnail_url( $ctn->ID, 'contorno-card' );
						}
						$highlights = array_slice( array_map( 'strval', contorno_field_list( 'highlights', $ctn->ID ) ), 0, 3 );
						?>
						<article class="ctn-card motion-item motion-ctn motion-card">
							<a class="ctn-card__media motion-ctn-media" href="<?php echo esc_url( (string) get_permalink( $ctn->ID ) ); ?>" tabindex="-1" aria-hidden="true">
								<?php if ( '' !== $image ) : ?>
									<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
								<?php endif; ?>
							</a>

							<div class="ctn-card__body">
								<p class="eyebrow"><?php echo esc_html( trim( contorno_field_text( 'neighborhood', $ctn->ID ) . ' • ' . contorno_field_text( 'city', $ctn->ID ), ' •' ) ); ?></p>
								<h3><a class="motion-title-link" href="<?php echo esc_url( (string) get_permalink( $ctn->ID ) ); ?>"><?php echo esc_html( (string) get_the_title( $ctn->ID ) ); ?></a></h3>

								<?php if ( array() !== $highlights ) : ?>
									<ul class="ctn-card__highlights">
										<?php foreach ( $highlights as $highlight ) : ?>
											<li><?php echo contorno_icon( 'check', 'ctn-card__check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $highlight ); ?></span></li>
										<?php endforeach; ?>
									</ul>
								<?php endif; ?>

								<?php echo contorno_button( __( 'Conhecer a CTN', 'contorno' ), (string) get_permalink( $ctn->ID ), 'primary', array( 'class' => 'ctn-prime-btn cta-label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);
