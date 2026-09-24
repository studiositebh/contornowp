<?php
/**
 * Componentes funcionais de Unidades.
 *
 * COMPONENTES CONTROLADOS: o editor configura propriedades (colunas, limite,
 * cidade, busca on/off), mas nao manipula o HTML interno. Grade, carrossel
 * mobile, filtros e cards dinamicos ficam no codigo.
 *
 * Os DADOS vem dos campos da unidade — nada de preco ou endereco em PHP.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Card de unidade — usado na listagem, na Home e no carrossel mobile.
 */
function contorno_render_unit_card( int $post_id, array $args = array() ): string {
	$is_pre_sale = contorno_is_pre_sale( $post_id );

	// Imagem responsiva: srcset + sizes vindos do proprio anexo.
	$image_value = contorno_field( 'image', $post_id, '' );
	if ( '' === $image_value || array() === $image_value ) {
		$image_value = get_post_thumbnail_id( $post_id );
	}

	$image = contorno_image_tag(
		$image_value,
		'contorno-card',
		array( 'sizes' => '(min-width: 1024px) 25vw, (min-width: 640px) 50vw, 100vw' )
	);

	$price   = contorno_unit_starting_price( $post_id );
	$badge   = contorno_field_text( 'badge', $post_id );
	$city    = contorno_field_text( 'city', $post_id );
	$hood    = contorno_field_text( 'neighborhood', $post_id );
	$state   = contorno_field_text( 'state', $post_id );
	$excerpt = contorno_field_text( 'short_description', $post_id );

	if ( '' === $excerpt ) {
		$excerpt = wp_strip_all_tags( (string) get_the_excerpt( $post_id ) );
	}

	$location = trim( implode( ' • ', array_filter( array( $hood, trim( $city . ( '' !== $state ? ' - ' . $state : '' ) ) ) ) ) );

	ob_start();
	?>
	<article class="unit-card motion-card<?php echo $is_pre_sale ? ' is-pre-sale' : ''; ?>">
		<a class="unit-card__media motion-media" href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>" tabindex="-1" aria-hidden="true">
			<?php if ( '' !== $image ) : ?>
				<?php echo $image; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- montado por contorno_image_tag(). ?>
			<?php else : ?>
				<?php /* Sem foto propria: gradiente turquesa da marca (porte de PlaceholderMedia). */ ?>
				<span class="contorno-placeholder is-variant-<?php echo esc_attr( (string) ( $post_id % 4 ) ); ?>" aria-hidden="true">
					<span class="contorno-placeholder__label"><?php esc_html_e( 'Foto em breve', 'contorno' ); ?></span>
				</span>
			<?php endif; ?>

			<?php if ( $is_pre_sale ) : ?>
				<span class="unit-card__presale-pill"><?php echo esc_html( contorno_pre_sale_label( $post_id ) ); ?></span>
				<span class="unit-card__presale-band"><?php echo esc_html( CONTORNO_PRE_SALE_STATUS_LABEL ); ?></span>
			<?php elseif ( '' !== $badge ) : ?>
				<span class="unit-card__badge"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>
		</a>

		<div class="unit-card__body">
			<h3 class="unit-card__title">
				<a class="motion-title-link" href="<?php echo esc_url( (string) get_permalink( $post_id ) ); ?>"><?php echo esc_html( (string) get_the_title( $post_id ) ); ?></a>
			</h3>

			<?php if ( '' !== $location ) : ?>
				<p class="unit-card__location"><?php echo contorno_icon( 'map-pin', 'unit-card__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $location ); ?></span></p>
			<?php endif; ?>

			<?php if ( isset( $args['distance'] ) && is_numeric( $args['distance'] ) ) : ?>
				<?php /* Busca por CEP: distancia em linha reta ate o ponto pesquisado (ou ate o centro da regiao, quando o CEP exato nao existe). */ ?>
				<p class="unit-card__distance">
					<?php if ( ! empty( $args['distance_approx'] ) ) : ?>
						<?php echo esc_html( sprintf( /* translators: %s: approximate distance */ __( '%s da região', 'contorno' ), contorno_format_distance_approx( (float) $args['distance'] ) ) ); ?>
					<?php else : ?>
						<?php echo esc_html( sprintf( /* translators: %s: formatted distance */ __( 'a %s de você', 'contorno' ), contorno_format_distance( (float) $args['distance'] ) ) ); ?>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( $is_pre_sale ) : ?>
				<?php $info = contorno_pre_sale_info_line( $post_id ); ?>
				<?php if ( '' !== $info ) : ?>
					<p class="unit-card__presale-info"><?php echo esc_html( $info ); ?></p>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( '' !== $excerpt ) : ?>
				<p class="unit-card__text"><?php echo esc_html( $excerpt ); ?></p>
			<?php endif; ?>

			<?php if ( $price > 0 ) : ?>
				<p class="unit-card__price">
					<span class="unit-card__price-label"><?php esc_html_e( 'A partir de', 'contorno' ); ?></span>
					<strong><?php echo esc_html( contorno_format_price( $price ) ); ?></strong>
				</p>
			<?php endif; ?>

			<?php
			$cta_label = ! empty( $args['prescription'] )
				? __( 'Fazer prescrição', 'contorno' )
				: __( 'Conhecer unidade', 'contorno' );

			$cta_url = ! empty( $args['prescription'] ) && '' !== contorno_field_text( 'prescricao_url', $post_id )
				? contorno_field_text( 'prescricao_url', $post_id )
				: (string) get_permalink( $post_id );

			$enrollment_page = get_page_by_path( 'matricula' );
			$enrollment_base = $enrollment_page instanceof WP_Post ? (string) get_permalink( $enrollment_page ) : home_url( '/matricula/' );
			$enrollment_url  = '' !== contorno_field_text( 'checkout_url', $post_id )
				? contorno_field_text( 'checkout_url', $post_id )
				: add_query_arg( 'unidade', (string) get_post_field( 'post_name', $post_id ), $enrollment_base );
			?>
			<div class="unit-card__actions">
				<?php echo contorno_button( $cta_label, $cta_url, ! empty( $args['dual_cta'] ) ? 'outline' : 'primary', array( 'class' => 'unit-know-btn unit-card-cta cta-label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php if ( ! empty( $args['dual_cta'] ) ) : ?>
					<?php echo contorno_button( $is_pre_sale ? __( 'Ver planos', 'contorno' ) : __( 'Matricule-se', 'contorno' ), $enrollment_url, 'primary', array( 'class' => 'unit-enroll-btn unit-card-cta cta-label' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php endif; ?>
			</div>
		</div>
	</article>
	<?php

	return (string) ob_get_clean();
}

/**
 * Textos da busca por CEP (contador + aviso), a partir do resultado de
 * contorno_geo_locate_cep(). Local sempre nomeado sem ambiguidade:
 * "bairro São Paulo, em Belo Horizonte/MG" — nunca "São Paulo, Belo
 * Horizonte - MG", que parece a cidade de São Paulo.
 *
 * @param array{status: string, cep: string, origin: array<string,mixed>|null} $location
 * @return array{count: string, notice: string}
 */
function contorno_units_geo_messages( array $location, int $total, int $radius, bool $nearest_only, bool $by_prefix ): array {
	$cep     = contorno_format_cep( (string) $location['cep'] );
	$origin  = $location['origin'];
	$precise = contorno_geo_is_precise( $location );
	$place   = is_array( $origin ) ? contorno_geo_place_label( $origin ) : '';

	if ( '' === $place ) {
		/* translators: %s: CEP */
		$near = sprintf( __( 'da região do CEP %s', 'contorno' ), $cep );
	} elseif ( str_starts_with( $place, 'bairro ' ) ) {
		/* translators: %s: "bairro X, em Cidade/UF" */
		$near = sprintf( __( 'ao %s', 'contorno' ), $place );
	} else {
		/* translators: %s: "Cidade/UF" */
		$near = sprintf( __( 'a %s', 'contorno' ), $place );
	}

	$count  = '';
	$notice = '';

	if ( is_array( $origin ) && $precise && ! $nearest_only ) {
		$count = '' !== $place
			/* translators: 1: units count, 2: radius in km, 3: CEP, 4: place */
			? sprintf( _n( '%1$d unidade a até %2$d km do CEP %3$s (%4$s)', '%1$d unidades a até %2$d km do CEP %3$s (%4$s)', $total, 'contorno' ), $total, $radius, $cep, $place )
			/* translators: 1: units count, 2: radius in km, 3: CEP */
			: sprintf( _n( '%1$d unidade a até %2$d km do CEP %3$s', '%1$d unidades a até %2$d km do CEP %3$s', $total, 'contorno' ), $total, $radius, $cep );
	} elseif ( is_array( $origin ) ) {
		/* translators: 1: units count, 2: "ao bairro X, em Cidade/UF" */
		$count = sprintf( _n( '%1$d unidade encontrada próxima %2$s', '%1$d unidades encontradas próximas %2$s', $total, 'contorno' ), $total, $near );
	}

	switch ( $location['status'] ) {
		case 'region':
			/* translators: %s: CEP */
			$notice = sprintf( __( 'Não encontramos o CEP %s exatamente. Mostrando unidades próximas a essa região — distâncias aproximadas.', 'contorno' ), $cep );
			break;

		case 'exact':
			if ( ! $precise ) {
				/* translators: 1: CEP, 2: place */
				$notice = sprintf( __( 'Localizamos o CEP %1$s em %2$s, sem precisão de endereço — distâncias aproximadas.', 'contorno' ), $cep, $place );
			}
			break;

		case 'unavailable':
			/* translators: %s: CEP */
			$notice = sprintf( __( 'No momento não foi possível consultar a localização do CEP %s. Você pode buscar pelo nome da unidade, bairro ou cidade.', 'contorno' ), $cep );
			if ( $by_prefix ) {
				$notice .= ' ' . __( 'Enquanto isso, estas são as unidades com CEP da mesma faixa:', 'contorno' );
			}
			break;

		default: // not_found
			$notice = $by_prefix
				/* translators: %s: CEP */
				? sprintf( __( 'Não encontramos o CEP %s. Estas são as unidades com CEP da mesma faixa:', 'contorno' ), $cep )
				/* translators: %s: CEP */
				: sprintf( __( 'Não encontramos o CEP %s. Confira os números ou busque pelo nome da unidade, bairro ou cidade.', 'contorno' ), $cep );
	}

	if ( $nearest_only && is_array( $origin ) ) {
		/* translators: %d: radius in km */
		$notice = trim( $notice . ' ' . sprintf( __( 'Nenhuma unidade a até %d km; estas são as mais próximas.', 'contorno' ), $radius ) );
	}

	return array(
		'count'  => $count,
		'notice' => $notice,
	);
}

/**
 * CONTORNO — Lista de Unidades.
 *
 * Desktop: grade de 3 ou 4 colunas (como no React).
 * Mobile: UMA unidade por vez em carrossel com swipe, snapping e indicadores.
 */
contorno_add_shortcode(
	'contorno_units',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow'      => '',
				'title'        => '',
				'text'         => '',
				'columns'      => '3',
				'limit'        => '-1',
				'featured'     => 'no',
				'city'         => '',
				'kind'         => '',
				'show_search'  => 'yes',
				'prescription' => 'no',
				'tone'         => 'light',
				'align'        => 'left',
				'empty_text'   => '',
				'per_page'     => '15',
				'catalog_mode' => 'auto',
				'show_count'   => 'yes',
				'show_per_page' => 'yes',
				'pagination'   => 'yes',
				'dual_cta'     => 'auto',
				'highlight'    => '',
				'cta_label'    => '',
				'cta_url'      => '',
			),
			(array) $atts,
			'contorno_units'
		);

		$request_path      = isset( $_SERVER['REQUEST_URI'] )
			? trim( (string) wp_parse_url( wp_unslash( (string) $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ), '/' )
			: '';
		$is_units_page     = is_page( 'unidades' ) || 'unidades' === $request_path || 'unidades' === basename( $request_path );
		$looks_like_catalog = 'yes' === $a['show_search']
			&& 'yes' !== $a['featured']
			&& 'yes' !== $a['prescription']
			&& '' === trim( (string) $a['city'] )
			&& '' === trim( (string) $a['kind'] );
		$is_catalog_mode   = 'yes' === $a['catalog_mode'] || ( 'auto' === $a['catalog_mode'] && ( $is_units_page || $looks_like_catalog ) );
		$has_count         = $is_catalog_mode && 'no' !== $a['show_count'];
		$has_per_page      = $is_catalog_mode && 'no' !== $a['show_per_page'];
		$is_paginated_list = $is_catalog_mode && 'no' !== $a['pagination'];
		$has_dual_cta      = 'yes' === $a['dual_cta'] || ( 'auto' === $a['dual_cta'] && ( $is_catalog_mode || is_front_page() ) );
		$allowed_per_page  = array( 9, 15, 45, 60 );
		$requested_page    = isset( $_GET['unidades_page'] ) ? absint( wp_unslash( (string) $_GET['unidades_page'] ) ) : absint( (string) get_query_var( 'paged', 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_page      = max( 1, $requested_page );
		$requested_per     = isset( $_GET['per_page'] ) ? absint( wp_unslash( (string) $_GET['per_page'] ) ) : (int) $a['per_page']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$per_page          = in_array( $requested_per, $allowed_per_page, true ) ? $requested_per : 15;
		$search_query      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$query_args = array(
			'posts_per_page' => -1,
		);

		if ( 'yes' === $a['featured'] ) {
			$query_args['featured_only'] = true;
		}

		if ( '' !== trim( (string) $a['city'] ) ) {
			$query_args['city'] = array_map( 'sanitize_title', contorno_lines_to_array( str_replace( ',', "\n", (string) $a['city'] ) ) );
		}

		if ( '' !== trim( (string) $a['kind'] ) ) {
			$query_args['tax_query'] = array(
				array(
					'taxonomy' => CONTORNO_TAX_UNIT_KIND,
					'field'    => 'slug',
					'terms'    => array_map( 'sanitize_title', explode( ',', (string) $a['kind'] ) ),
				),
			);
		}

		$units = contorno_get_units( $query_args );

		// Busca por CEP (pipeline em includes/data/geo.php):
		//   exact     -> distancia real (Haversine) e filtro pelo raio;
		//   region    -> CEP inexistente: ponto APROXIMADO da regiao do CEP, com
		//                distancias marcadas como aproximadas;
		//   sem ponto -> unidades da mesma faixa de CEP, sem distancia.
		// Os textos saem de contorno_units_geo_messages(): o local e sempre
		// "bairro X, em Cidade/UF" — nunca "X, Cidade", que confunde bairro e cidade.
		$radius       = contorno_geo_requested_radius();
		$geo_cep      = $is_catalog_mode ? contorno_cep_digits( $search_query ) : '';
		$geo_location = '' !== $geo_cep ? contorno_geo_locate_cep( $geo_cep ) : null;
		$geo_origin   = null !== $geo_location ? $geo_location['origin'] : null;
		$geo_approx   = null !== $geo_origin && ! contorno_geo_is_precise( $geo_location );
		$geo_fallback = false;
		$geo_prefix   = false;
		$distances    = array();

		if ( null !== $geo_location && null === $geo_origin ) {
			$nearby     = contorno_units_by_cep_prefix( $units, $geo_cep );
			$geo_prefix = array() !== $nearby;

			if ( $geo_prefix ) {
				$units = $nearby;
			}
		}

		if ( null !== $geo_origin ) {
			$ranked = contorno_units_by_distance( $units, (float) $geo_origin['lat'], (float) $geo_origin['lng'] );
			$within = array_values( array_filter( $ranked, static fn ( array $row ): bool => $row['distance'] <= $radius ) );

			if ( array() === $within ) {
				$within       = array_slice( $ranked, 0, CONTORNO_GEO_NEAREST_FALLBACK );
				$geo_fallback = array() !== $within;
			}

			$units = array();
			foreach ( $within as $row ) {
				$units[]                      = $row['post'];
				$distances[ $row['post']->ID ] = $row['distance'];
			}
		} elseif ( ! $geo_prefix && $is_catalog_mode && '' !== trim( $search_query ) ) {
			$needle = contorno_normalize_search( $search_query );
			$digits = preg_replace( '/\D/', '', $needle );
			$digits = is_string( $digits ) ? $digits : '';
			$units  = array_values(
				array_filter(
					$units,
					static function ( WP_Post $unit ) use ( $needle, $digits ): bool {
						$haystack = contorno_unit_search_haystack( $unit->ID );
						$postal   = contorno_unit_postal_digits( $unit->ID );

						return '' === $needle
							|| false !== strpos( $haystack, $needle )
							|| ( strlen( $digits ) >= 5 && '' !== $postal && false !== strpos( $postal, $digits ) );
					}
				)
			);
		}

		$total_units = count( $units );
		$geo_messages = null !== $geo_location
			? contorno_units_geo_messages( $geo_location, $total_units, $radius, $geo_fallback, $geo_prefix )
			: array( 'count' => '', 'notice' => '' );
		$total_pages = $is_paginated_list ? max( 1, (int) ceil( $total_units / $per_page ) ) : 1;
		$current_page = min( $current_page, $total_pages );

		if ( $is_paginated_list ) {
			$units = array_slice( $units, ( $current_page - 1 ) * $per_page, $per_page );
		} elseif ( (int) $a['limit'] > 0 ) {
			$units = array_slice( $units, 0, (int) $a['limit'] );
		}

		contorno_enqueue_component( 'units-carousel' );

		if ( 'yes' === $a['show_search'] ) {
			contorno_enqueue_component( 'unit-search' );
		}

		$empty_text = '' !== trim( (string) $a['empty_text'] )
			? (string) $a['empty_text']
			: __( 'Nenhuma unidade encontrada para essa busca.', 'contorno' );

		/*
		 * Titulo como no React: trecho em destaque na cor da marca e, na Home,
		 * botao "Ver todas as unidades" alinhado a direita do cabecalho. No
		 * catalogo (/unidades) o titulo e o H1 da pagina.
		 */
		$section_title = esc_html( (string) $a['title'] );
		if ( '' !== trim( (string) $a['highlight'] ) ) {
			$needle        = esc_html( (string) $a['highlight'] );
			$section_title = str_replace( $needle, '<span class="contorno-section-header__highlight">' . $needle . '</span>', $section_title );
		}
		$section_title = str_replace( '|', '<br />', $section_title );

		$header_html = contorno_section_header( (string) $a['eyebrow'], $section_title, (string) $a['text'], (string) $a['align'] );
		if ( $is_catalog_mode ) {
			$header_html = str_replace( array( '<h2 class="contorno-section-header__title">', '</h2>' ), array( '<h1 class="contorno-section-header__title">', '</h1>' ), $header_html );
		}

		ob_start();
		echo contorno_section_open( 'units', array( 'tone' => (string) $a['tone'], 'class' => 'units-section featured-units' . ( $is_catalog_mode ? ' units-section--archive' : '' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( '' !== trim( (string) $a['cta_label'] ) && '' !== trim( (string) $a['cta_url'] ) ) {
			echo '<div class="contorno-section-header-row">' . $header_html . contorno_button( (string) $a['cta_label'], (string) $a['cta_url'], 'outline', array( 'class' => 'cta-label contorno-section-header-row__cta' ) ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo $header_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>

		<div class="contorno-units" data-contorno-units data-empty-text="<?php echo esc_attr( $empty_text ); ?>">
			<?php /* No catalogo a ordem e a do React: titulo, busca, contador/por pagina, grade. */ ?>
			<?php if ( $is_catalog_mode && 'yes' === $a['show_search'] ) : ?>
				<?php echo do_shortcode( '[contorno_units_search target="catalog"]' ); ?>
			<?php endif; ?>

			<?php if ( $is_catalog_mode && ( $has_count || $has_per_page ) ) : ?>
				<div class="contorno-units__toolbar">
					<?php if ( $has_count ) : ?>
						<p class="contorno-units__count">
							<?php
							if ( '' !== $geo_messages['count'] ) {
								echo esc_html( $geo_messages['count'] );
							} else {
								echo esc_html(
									sprintf(
										/* translators: %d: units count */
										_n( '%d unidade encontrada', '%d unidades encontradas', $total_units, 'contorno' ),
										$total_units
									)
								);
							}
							?>
						</p>
					<?php endif; ?>
					<?php if ( $has_per_page ) : ?>
						<form class="contorno-units__per-page" method="get">
							<?php if ( '' !== $search_query ) : ?>
								<input type="hidden" name="q" value="<?php echo esc_attr( $search_query ); ?>" />
							<?php endif; ?>
							<?php if ( null !== $geo_origin && CONTORNO_GEO_DEFAULT_RADIUS !== $radius ) : ?>
								<input type="hidden" name="<?php echo esc_attr( CONTORNO_GEO_RADIUS_PARAM ); ?>" value="<?php echo esc_attr( (string) $radius ); ?>" />
							<?php endif; ?>
							<label for="contorno-units-per-page"><?php esc_html_e( 'Exibir', 'contorno' ); ?></label>
							<select id="contorno-units-per-page" name="per_page" onchange="this.form.submit()">
								<?php foreach ( $allowed_per_page as $option ) : ?>
									<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $per_page, $option ); ?>>
										<?php echo esc_html( sprintf( /* translators: %d: items per page */ __( '%d por página', 'contorno' ), $option ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</form>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! $is_catalog_mode && 'yes' === $a['show_search'] ) : ?>
				<?php echo do_shortcode( '[contorno_units_search target=""]' ); ?>
			<?php endif; ?>

			<?php if ( '' !== $geo_messages['notice'] ) : ?>
				<p class="contorno-units__notice" role="status"><?php echo esc_html( $geo_messages['notice'] ); ?></p>
			<?php endif; ?>

			<?php if ( array() === $units ) : ?>
				<p class="contorno-units__empty"><?php echo esc_html( $empty_text ); ?></p>
			<?php else : ?>
				<p class="contorno-units__empty" data-contorno-units-empty hidden><?php echo esc_html( $empty_text ); ?></p>

				<?php /* Mobile: carrossel de 1 unidade por slide. */ ?>
				<div class="contorno-units__carousel unit-grid-carousel" data-contorno-carousel>
					<div class="contorno-units__track" data-contorno-carousel-track>
						<?php foreach ( $units as $unit ) : ?>
							<div
								class="contorno-units__slide"
								data-contorno-unit
								data-haystack="<?php echo esc_attr( contorno_unit_search_haystack( $unit->ID ) ); ?>"
								data-postal="<?php echo esc_attr( contorno_unit_postal_digits( $unit->ID ) ); ?>"
							>
								<?php echo contorno_render_unit_card( $unit->ID, array( 'prescription' => 'yes' === $a['prescription'], 'dual_cta' => $has_dual_cta, 'distance' => $distances[ $unit->ID ] ?? null, 'distance_approx' => $geo_approx ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="contorno-units__controls">
						<button type="button" class="contorno-units__arrow" data-contorno-carousel-prev aria-label="<?php esc_attr_e( 'Unidade anterior', 'contorno' ); ?>">&#8249;</button>
						<button type="button" class="contorno-units__arrow" data-contorno-carousel-next aria-label="<?php esc_attr_e( 'Próxima unidade', 'contorno' ); ?>">&#8250;</button>
					</div>
					<div class="contorno-units__dots" role="tablist" aria-label="<?php esc_attr_e( 'Slides', 'contorno' ); ?>" data-contorno-carousel-dots></div>
				</div>

				<?php /* Tablet / desktop: grade. */ ?>
				<div class="contorno-units__grid is-columns-<?php echo esc_attr( (string) (int) $a['columns'] ); ?> motion-stagger" data-contorno-reveal>
					<?php foreach ( $units as $unit ) : ?>
						<div
							class="contorno-units__cell motion-item"
							data-contorno-unit
							data-haystack="<?php echo esc_attr( contorno_unit_search_haystack( $unit->ID ) ); ?>"
							data-postal="<?php echo esc_attr( contorno_unit_postal_digits( $unit->ID ) ); ?>"
						>
							<?php echo contorno_render_unit_card( $unit->ID, array( 'prescription' => 'yes' === $a['prescription'], 'dual_cta' => $has_dual_cta, 'distance' => $distances[ $unit->ID ] ?? null, 'distance_approx' => $geo_approx ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( $is_paginated_list && $total_pages > 1 ) : ?>
					<nav class="contorno-units__pagination" aria-label="<?php esc_attr_e( 'Paginação de unidades', 'contorno' ); ?>">
						<?php
						echo wp_kses_post(
							paginate_links(
								array(
									'base'      => str_replace( '999999999', '%#%', esc_url_raw( add_query_arg( 'unidades_page', '999999999', remove_query_arg( 'paged' ) ) ) ),
									'format'    => '',
									'current'   => $current_page,
									'total'     => $total_pages,
									'type'      => 'list',
									'prev_text' => __( 'Anterior', 'contorno' ),
									'next_text' => __( 'Próxima', 'contorno' ),
									'add_args'  => array_filter(
										array(
											'per_page'                 => $per_page,
											'q'                        => $search_query,
											CONTORNO_GEO_RADIUS_PARAM => null !== $geo_origin && CONTORNO_GEO_DEFAULT_RADIUS !== $radius ? $radius : '',
										),
										static fn ( $value ): bool => '' !== $value && null !== $value
									),
								)
							)
						);
						?>
					</nav>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Busca de unidades.
 *
 * Filtra por nome, cidade, bairro, endereco, CEP e tipo — porte de
 * filterUnits() do React, executado no cliente para resposta instantanea.
 */
contorno_add_shortcode(
	'contorno_units_search',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'placeholder' => '',
				'target'      => '',
			),
			(array) $atts,
			'contorno_units_search'
		);

		contorno_enqueue_component( 'unit-search' );

		$placeholder = '' !== trim( (string) $a['placeholder'] )
			? (string) $a['placeholder']
			: __( 'Buscar por unidade, bairro, cidade ou CEP', 'contorno' );

		$archive = (string) get_post_type_archive_link( CONTORNO_CPT_UNIT );

		ob_start();
		?>
		<div class="contorno-unit-search motion-search-wrap" data-contorno-unit-search data-target="<?php echo esc_attr( (string) $a['target'] ); ?>">
			<?php echo contorno_icon( 'search', 'contorno-unit-search__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input
				type="search"
				class="contorno-unit-search__field motion-search-field"
				aria-label="<?php esc_attr_e( 'Buscar unidade por nome, bairro, cidade ou CEP', 'contorno' ); ?>"
				placeholder="<?php echo esc_attr( $placeholder ); ?>"
				data-contorno-unit-search-input
				data-archive="<?php echo esc_url( $archive ); ?>"
				autocomplete="off"
			/>
			<button type="button" class="contorno-unit-search__clear" data-contorno-unit-search-clear hidden aria-label="<?php esc_attr_e( 'Limpar busca', 'contorno' ); ?>">&times;</button>

			<?php if ( 'catalog' === $a['target'] ) : ?>
				<?php /* Raio da busca por CEP. Sem CEP no campo o seletor fica oculto (JS). */ ?>
				<?php $radius = contorno_geo_requested_radius(); ?>
				<label class="contorno-unit-search__radius" data-contorno-unit-search-radius-wrap hidden>
					<span class="screen-reader-text"><?php esc_html_e( 'Raio de busca', 'contorno' ); ?></span>
					<select data-contorno-unit-search-radius data-default="<?php echo esc_attr( (string) CONTORNO_GEO_DEFAULT_RADIUS ); ?>" aria-label="<?php esc_attr_e( 'Raio de busca a partir do CEP', 'contorno' ); ?>">
						<?php foreach ( CONTORNO_GEO_RADIUS_OPTIONS as $option ) : ?>
							<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( $radius, $option ); ?>>
								<?php echo esc_html( sprintf( /* translators: %d: radius in km */ __( 'até %d km', 'contorno' ), $option ) ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>

			<?php if ( 'hero' === $a['target'] ) : ?>
				<?php /* No cartão do hero a busca leva para /unidades, então tem botão próprio. */ ?>
				<button type="button" class="contorno-btn contorno-btn--primary cta-label contorno-unit-search__submit" data-contorno-unit-search-submit>
					<?php esc_html_e( 'Buscar unidades', 'contorno' ); ?>
				</button>
			<?php endif; ?>
		</div>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Hero da Unidade.
 */
contorno_add_shortcode(
	'contorno_unit_hero',
	static function ( array|string $atts ): string {
		$a       = shortcode_atts( array( 'unit' => '' ), (array) $atts, 'contorno_unit_hero' );
		$post_id = contorno_resolve_context_id( (array) $a );

		if ( ! $post_id ) {
			return '';
		}

		$image = contorno_field_image_url( 'image', $post_id, 'contorno-hero' );
		if ( '' === $image ) {
			$image = (string) get_the_post_thumbnail_url( $post_id, 'contorno-hero' );
		}

		$is_pre_sale = contorno_is_pre_sale( $post_id );
		$info        = contorno_pre_sale_info_line( $post_id );
		$gallery     = contorno_field_image_urls( 'gallery', $post_id, 'contorno-card' );
		$features    = array_slice( contorno_unit_attribute_items( 'facilities', $post_id ), 0, 4 );
		$title       = contorno_field_text( 'short_name', $post_id, (string) get_the_title( $post_id ) );
		$location    = trim( implode( ' - ', array_filter( array( contorno_field_text( 'neighborhood', $post_id ), trim( contorno_field_text( 'city', $post_id ) . ( '' !== contorno_field_text( 'state', $post_id ) ? ', ' . contorno_field_text( 'state', $post_id ) : '' ) ) ) ) ) );
		$enroll_page = get_page_by_path( 'matricula' );
		$enroll_base = $enroll_page instanceof WP_Post ? (string) get_permalink( $enroll_page ) : home_url( '/matricula/' );
		$enroll_url  = '' !== contorno_field_text( 'checkout_url', $post_id )
			? contorno_field_text( 'checkout_url', $post_id )
			: add_query_arg( 'unidade', (string) get_post_field( 'post_name', $post_id ), $enroll_base );
		$hero_gallery = array_values( array_filter( array_merge( array( $image ), $gallery ) ) );

		if ( array() !== $hero_gallery ) {
			contorno_enqueue_component( 'lightbox' );
		}

		ob_start();
		?>
		<section class="unit-hero<?php echo $is_pre_sale ? ' is-pre-sale' : ''; ?>">
			<?php if ( $is_pre_sale ) : ?>
				<div class="site-container">
					<div class="unit-hero__presale-bar">
						<span><?php esc_html_e( 'Nova unidade • Pré-venda', 'contorno' ); ?></span>
						<strong><?php echo esc_html( CONTORNO_PRE_SALE_STATUS_LABEL ); ?></strong>
					</div>
				</div>
			<?php endif; ?>

			<div class="site-container unit-hero__inner motion-hero-copy">
				<div class="unit-hero__copy">
					<nav class="unit-hero__breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumb', 'contorno' ); ?>">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Início', 'contorno' ); ?></a>
						<span aria-hidden="true">›</span>
						<a href="<?php echo esc_url( home_url( '/unidades/' ) ); ?>"><?php esc_html_e( 'Unidades', 'contorno' ); ?></a>
						<span aria-hidden="true">›</span>
						<span><?php echo esc_html( $title ); ?></span>
					</nav>

					<p class="eyebrow"><?php echo esc_html( $is_pre_sale ? contorno_pre_sale_label( $post_id ) : __( 'Unidade Premium', 'contorno' ) ); ?></p>
					<h1 class="unit-hero__title"><?php echo esc_html( $title ); ?></h1>

					<?php if ( '' !== $location ) : ?>
						<p class="unit-hero__location"><?php echo contorno_icon( 'map-pin', 'unit-hero__location-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $location ); ?></span></p>
					<?php endif; ?>

					<?php if ( $is_pre_sale && '' !== $info ) : ?>
						<p class="unit-hero__presale-info"><?php echo esc_html( $info ); ?></p>
					<?php endif; ?>

					<?php $short = contorno_field_text( 'short_description', $post_id ); ?>
					<?php if ( '' !== $short ) : ?>
						<p class="unit-hero__text"><?php echo esc_html( $short ); ?></p>
					<?php endif; ?>

					<?php if ( array() !== $features ) : ?>
						<ul class="unit-hero__features">
							<?php foreach ( $features as $feature ) : ?>
								<?php if ( '' === trim( (string) $feature['label'] ) ) : continue; endif; ?>
								<li><?php echo contorno_icon( (string) $feature['icon'], 'unit-hero__feature-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( (string) $feature['label'] ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<div class="unit-hero__actions motion-hero-actions">
						<?php echo contorno_button( __( 'Matricule-se agora', 'contorno' ), $enroll_url, 'primary', array( 'class' => 'unit-page-btn unit-cta-btn' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php /* Sem planos (pre-venda / aguardando EVO) nao ha secao #planos para ancorar. */ ?>
						<?php if ( array() !== contorno_field_list( 'plans', $post_id ) ) : ?>
							<?php echo contorno_button( __( 'Ver planos e preços', 'contorno' ), '#planos', 'outline', array( 'class' => 'unit-page-btn' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php endif; ?>
					</div>
				</div>

				<?php if ( '' !== $image ) : ?>
					<div class="unit-hero__gallery motion-hero-media" data-contorno-lightbox>
						<img
							class="unit-hero__gallery-main"
							src="<?php echo esc_url( $image ); ?>"
							alt="<?php echo esc_attr( contorno_field_text( 'image_alt', $post_id ) ); ?>"
							fetchpriority="high"
							decoding="async"
							data-contorno-lightbox-item
							data-src="<?php echo esc_url( $image ); ?>"
							tabindex="0"
						/>
						<?php foreach ( array_slice( $gallery, 0, 3 ) as $gallery_image ) : ?>
							<img
								src="<?php echo esc_url( $gallery_image ); ?>"
								alt=""
								loading="lazy"
								decoding="async"
								data-contorno-lightbox-item
								data-src="<?php echo esc_url( $gallery_image ); ?>"
								tabindex="0"
							/>
						<?php endforeach; ?>
						<?php if ( count( $gallery ) > 3 ) : ?>
							<a
								class="unit-hero__all-photos"
								href="<?php echo esc_url( $gallery[3] ); ?>"
								data-contorno-lightbox-item
								data-src="<?php echo esc_url( $gallery[3] ); ?>"
							><?php echo esc_html( sprintf( '+%d', count( $gallery ) ) ); ?><span><?php esc_html_e( 'Ver todas as fotos', 'contorno' ); ?></span></a>
							<?php foreach ( array_slice( $gallery, 4 ) as $gallery_image ) : ?>
								<span class="screen-reader-text" data-contorno-lightbox-item data-src="<?php echo esc_url( $gallery_image ); ?>"></span>
							<?php endforeach; ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Faixa informativa da Unidade (endereco, horario, telefone).
 */
contorno_add_shortcode(
	'contorno_unit_info',
	static function ( array|string $atts ): string {
		$a       = shortcode_atts( array( 'unit' => '' ), (array) $atts, 'contorno_unit_info' );
		$post_id = contorno_resolve_context_id( (array) $a );

		if ( ! $post_id ) {
			return '';
		}

		// O endereco livre do cadastro costuma ja trazer bairro/cidade/UF; o helper
		// remove a repeticao e devolve as linhas prontas.
		$address  = contorno_address_lines(
			contorno_field_text( 'address', $post_id ),
			contorno_field_text( 'neighborhood', $post_id ),
			contorno_field_text( 'city', $post_id ),
			contorno_field_text( 'state', $post_id ),
			contorno_field_text( 'postal_code', $post_id )
		);
		$hours    = contorno_hours_lines( contorno_field_text( 'hours', $post_id ) );
		$phone    = contorno_field_text( 'phone', $post_id, contorno_brand_get( 'phone' ) );
		$whatsapp = contorno_field_text( 'whatsapp', $post_id, $phone );
		$maps_url = contorno_maps_url( $post_id );

		$rows = array(
			array(
				'icon'   => 'clock',
				'label'  => __( 'Horário de funcionamento', 'contorno' ),
				'type'   => 'hours',
				'lines'  => $hours,
				'href'   => '',
				'action' => '',
			),
			array(
				'icon'   => 'map-pin',
				'label'  => __( 'Endereço', 'contorno' ),
				'type'   => 'text',
				'lines'  => $address,
				'href'   => '',
				'action' => '',
			),
			array(
				'icon'   => 'phone',
				'label'  => __( 'Telefone / WhatsApp', 'contorno' ),
				'type'   => 'text',
				'lines'  => array_values( array_unique( array_filter( array( contorno_format_phone( $phone ), contorno_format_phone( $whatsapp ) ) ) ) ),
				'href'   => '',
				'action' => '',
			),
			array(
				'icon'   => 'map-pin',
				'label'  => __( 'Como chegar', 'contorno' ),
				'type'   => 'text',
				'lines'  => array( __( 'Ver no mapa', 'contorno' ) ),
				'href'   => $maps_url,
				'action' => 'arrow',
			),
		);

		ob_start();
		?>
		<section class="unit-info-strip">
			<div class="site-container unit-info-strip__inner">
				<?php foreach ( $rows as $row ) : ?>
					<?php if ( array() === $row['lines'] ) : continue; endif; ?>
					<div class="unit-info-strip__item motion-unit-feature">
						<span class="motion-icon"><?php echo contorno_icon( (string) $row['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<div class="unit-info-strip__body">
							<span class="unit-info-strip__label"><?php echo esc_html( (string) $row['label'] ); ?></span>
							<?php if ( '' !== (string) $row['href'] ) : ?>
								<a class="unit-page-link" href="<?php echo esc_url( (string) $row['href'] ); ?>" target="_blank" rel="noopener noreferrer">
									<?php echo esc_html( implode( ' ', array_map( 'strval', $row['lines'] ) ) ); ?>
									<?php if ( 'arrow' === $row['action'] ) : ?>
										<?php echo contorno_icon( 'arrow-right', 'unit-page-link__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
									<?php endif; ?>
								</a>
							<?php elseif ( 'hours' === $row['type'] ) : ?>
								<dl class="unit-info-strip__hours">
									<?php foreach ( $row['lines'] as $line ) : ?>
										<?php if ( '' !== (string) $line['term'] ) : ?>
											<div class="unit-info-strip__hours-row">
												<dt><?php echo esc_html( (string) $line['term'] ); ?></dt>
												<dd><?php echo esc_html( (string) $line['value'] ); ?></dd>
											</div>
										<?php else : ?>
											<div class="unit-info-strip__hours-row unit-info-strip__hours-row--plain">
												<dd><?php echo esc_html( (string) $line['value'] ); ?></dd>
											</div>
										<?php endif; ?>
									<?php endforeach; ?>
								</dl>
							<?php else : ?>
								<p>
									<?php foreach ( $row['lines'] as $line ) : ?>
										<span><?php echo esc_html( (string) $line ); ?></span>
									<?php endforeach; ?>
								</p>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Destaques da Unidade (icones + hover rosa premium).
 *
 * Le o campo `facilities` da unidade.
 */
contorno_add_shortcode(
	'contorno_unit_highlights',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'unit'    => '',
				'eyebrow' => '',
				'title'   => '',
				'columns' => '4',
				'tone'    => 'light',
			),
			(array) $atts,
			'contorno_unit_highlights'
		);

		$post_id = contorno_resolve_context_id( (array) $a );
		$items   = $post_id ? contorno_unit_attribute_items( 'facilities', $post_id ) : array();

		if ( array() === $items ) {
			return '';
		}

		$title = '' !== trim( (string) $a['title'] ) ? (string) $a['title'] : __( 'Destaques Contorno', 'contorno' );

		ob_start();
		echo contorno_section_open( 'unit-highlights', array( 'tone' => (string) $a['tone'], 'class' => 'unit-highlights-band' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo contorno_section_header( (string) $a['eyebrow'], $title, '', 'left' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="contorno-highlights is-columns-<?php echo esc_attr( (string) (int) $a['columns'] ); ?> motion-stagger" data-contorno-reveal>
			<?php foreach ( $items as $item ) : ?>
				<?php if ( '' === trim( (string) $item['label'] ) ) : continue; endif; ?>
				<article class="unit-highlight-card motion-item">
					<span class="unit-highlight-card__icon-wrap"><?php echo contorno_icon( (string) $item['icon'], 'unit-highlight-card__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3 class="unit-highlight-card__label"><?php echo esc_html( (string) $item['label'] ); ?></h3>
				</article>
			<?php endforeach; ?>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Diferenciais da Unidade (lista textual).
 */
contorno_add_shortcode(
	'contorno_unit_differentials',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'unit'    => '',
				'eyebrow' => '',
				'title'   => '',
				'text'    => '',
				'tone'    => 'light',
			),
			(array) $atts,
			'contorno_unit_differentials'
		);

		$post_id = contorno_resolve_context_id( (array) $a );
		$items   = $post_id ? contorno_unit_attribute_items( 'differentials', $post_id ) : array();

		if ( array() === $items ) {
			return '';
		}

		ob_start();
		echo contorno_section_open( 'unit-differentials', array( 'tone' => (string) $a['tone'], 'class' => 'unit-results-section' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'], 'center' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<ul class="contorno-differentials motion-stagger" data-contorno-reveal>
			<?php foreach ( $items as $item ) : ?>
				<?php if ( '' === trim( (string) $item['label'] ) ) : continue; endif; ?>
				<li class="contorno-differentials__item motion-item motion-diff">
					<span class="motion-icon"><?php echo contorno_icon( (string) $item['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<h3><?php echo esc_html( (string) $item['label'] ); ?></h3>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Galeria (unidade ou CTN) com lightbox.
 */
contorno_add_shortcode(
	'contorno_gallery',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'unit'    => '',
				'ctn'     => '',
				'eyebrow' => '',
				'title'   => '',
				'text'    => '',
				'columns' => '4',
				'tone'    => 'light',
				'field'   => 'gallery',
				'align'   => 'left',
				// Id da secao (destino de ancoras). A CTN usa "estrutura" (botao do hero).
				'anchor'  => 'galeria',
			),
			(array) $atts,
			'contorno_gallery'
		);

		$post_type = '' !== trim( (string) $a['ctn'] ) ? CONTORNO_CPT_CTN : '';
		$post_id   = contorno_resolve_context_id( (array) $a, $post_type );

		// Valores crus (IDs de anexo quando ja migrados) para gerar srcset.
		$items  = $post_id ? contorno_field_list( (string) $a['field'], $post_id ) : array();
		$images = $post_id ? contorno_field_image_urls( (string) $a['field'], $post_id, 'contorno-gallery' ) : array();

		if ( array() === $images ) {
			return '';
		}

		contorno_enqueue_component( 'lightbox' );

		ob_start();
		echo contorno_section_open( 'gallery', array( 'tone' => (string) $a['tone'], 'id' => sanitize_html_class( (string) $a['anchor'], 'galeria' ), 'class' => 'unit-gallery-section' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'], (string) $a['align'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="contorno-gallery is-columns-<?php echo esc_attr( (string) (int) $a['columns'] ); ?> motion-stagger" data-contorno-lightbox data-contorno-reveal>
			<?php foreach ( $images as $index => $src ) : ?>
				<button
					type="button"
					class="contorno-gallery__item motion-item motion-gallery-item motion-media"
					data-contorno-lightbox-item
					data-src="<?php echo esc_url( $src ); ?>"
					data-index="<?php echo esc_attr( (string) $index ); ?>"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %d: image number */ __( 'Ampliar imagem %d', 'contorno' ), (int) $index + 1 ) ); ?>"
				>
					<?php
					// Miniatura responsiva; o lightbox abre o original via data-src.
					echo contorno_image_tag( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						$items[ $index ] ?? $src,
						'contorno-gallery',
						array( 'sizes' => '(min-width: 768px) 25vw, 50vw' )
					);
					?>
				</button>
			<?php endforeach; ?>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Planos.
 *
 * Le os planos do registro (unidade ou CTN). Pele clara ou dark CTN — MESMA
 * arquitetura/UX nas duas, como aprovado. Nenhum valor comercial em PHP.
 */
contorno_add_shortcode(
	'contorno_plans',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'unit'      => '',
				'ctn'       => '',
				'eyebrow'   => '',
				'title'     => '',
				'text'      => '',
				'skin'      => 'light',
				'columns'   => '3',
				'note'      => '',
				'cta_label' => '',
				'align'     => 'left',
			),
			(array) $atts,
			'contorno_plans'
		);

		$post_type = '' !== trim( (string) $a['ctn'] ) ? CONTORNO_CPT_CTN : '';
		$post_id   = contorno_resolve_context_id( (array) $a, $post_type );

		if ( ! $post_id ) {
			return '';
		}

		$is_ctn = CONTORNO_CPT_CTN === get_post_type( $post_id );
		$skin   = 'auto' === $a['skin'] ? ( $is_ctn ? 'dark' : 'light' ) : (string) $a['skin'];
		$plans  = contorno_field_list( 'plans', $post_id );

		if ( array() === $plans ) {
			return '';
		}

		$cta_label = '' !== trim( (string) $a['cta_label'] ) ? (string) $a['cta_label'] : __( 'Assinar plano', 'contorno' );

		ob_start();
		echo contorno_section_open( 'plans', array( 'tone' => $skin, 'id' => 'planos', 'class' => 'unit-plans-section' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'], (string) $a['align'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<?php
		/*
		 * Desktop: grade com no minimo 3 planos por linha (nunca quebra em 2).
		 * Abaixo de 1024px o mesmo trilho vira carrossel com swipe (2 por tela
		 * no tablet, 1 no celular) — controles em .contorno-plans__controls.
		 */
		contorno_enqueue_component( 'units-carousel' );
		?>
		<div class="contorno-plans-carousel" data-contorno-carousel>
		<div class="contorno-plans is-columns-<?php echo esc_attr( (string) (int) $a['columns'] ); ?> motion-stagger" data-contorno-reveal data-contorno-carousel-track>
			<?php foreach ( $plans as $plan ) : ?>
				<?php
				if ( ! is_array( $plan ) ) {
					continue;
				}

				$price     = isset( $plan['price'] ) ? (float) $plan['price'] : 0.0;
				$from      = isset( $plan['price_from'] ) ? (float) $plan['price_from'] : 0.0;
				$recurring = isset( $plan['recurring_price'] ) ? (float) $plan['recurring_price'] : 0.0;
				$fee       = isset( $plan['enrollment_fee'] ) ? (float) $plan['enrollment_fee'] : 0.0;
				$benefits  = isset( $plan['benefits'] ) && is_array( $plan['benefits'] ) ? $plan['benefits'] : array();
				$featured  = ! empty( $plan['featured'] );
				?>
				<article class="contorno-plan motion-item motion-card<?php echo $featured ? ' is-featured' : ''; ?>" data-contorno-carousel-slide>
					<?php if ( ! empty( $plan['badge'] ) ) : ?>
						<span class="contorno-plan__badge"><?php echo esc_html( (string) $plan['badge'] ); ?></span>
					<?php endif; ?>

					<h3 class="contorno-plan__name"><?php echo esc_html( (string) ( $plan['name'] ?? '' ) ); ?></h3>

					<?php if ( ! empty( $plan['description'] ) ) : ?>
						<p class="contorno-plan__description"><?php echo esc_html( (string) $plan['description'] ); ?></p>
					<?php endif; ?>

					<div class="contorno-plan__pricing">
						<?php if ( $from > 0 && $from > $price ) : ?>
							<s class="contorno-plan__from"><?php echo esc_html( contorno_format_price( $from ) ); ?></s>
						<?php endif; ?>

						<?php if ( $price > 0 ) : ?>
							<strong class="contorno-plan__price"><?php echo esc_html( contorno_format_price( $price ) ); ?></strong>
						<?php elseif ( ! empty( $plan['price_label'] ) ) : ?>
							<strong class="contorno-plan__price"><?php echo esc_html( (string) $plan['price_label'] ); ?></strong>
						<?php endif; ?>

						<?php if ( ! empty( $plan['price_note'] ) ) : ?>
							<span class="contorno-plan__note"><?php echo esc_html( (string) $plan['price_note'] ); ?></span>
						<?php endif; ?>
					</div>

					<ul class="contorno-plan__meta">
						<?php if ( $recurring > 0 ) : ?>
							<li><?php echo esc_html( sprintf( /* translators: %s: price */ __( 'Meses seguintes: %s', 'contorno' ), contorno_format_price( $recurring ) ) ); ?></li>
						<?php endif; ?>
						<?php if ( $fee > 0 ) : ?>
							<li><?php echo esc_html( sprintf( /* translators: %s: price */ __( 'Matrícula: %s', 'contorno' ), contorno_format_price( $fee ) ) ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $plan['fidelity'] ) ) : ?>
							<li><?php echo esc_html( (string) $plan['fidelity'] ); ?></li>
						<?php endif; ?>
						<?php if ( ! empty( $plan['card_note'] ) ) : ?>
							<li><?php echo esc_html( (string) $plan['card_note'] ); ?></li>
						<?php endif; ?>
					</ul>

					<?php if ( array() !== $benefits ) : ?>
						<ul class="contorno-plan__benefits">
							<?php foreach ( $benefits as $benefit ) : ?>
								<li><?php echo contorno_icon( 'check', 'contorno-plan__check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( (string) $benefit ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php
					// Unidade e CTN -> /matricula (captura do lead) -> checkout da EVO do plano.
					$target = contorno_plan_cta_target( $plan, $post_id );
					echo contorno_button( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						$cta_label,
						$target['url'],
						$featured ? 'primary' : 'outline',
						array( 'class' => 'unit-plan-btn cta-label', 'external' => $target['external'] )
					);
					?>
				</article>
			<?php endforeach; ?>
		</div>

		<div class="contorno-plans__controls contorno-units__controls">
			<button type="button" class="contorno-units__arrow" data-contorno-carousel-prev aria-label="<?php esc_attr_e( 'Plano anterior', 'contorno' ); ?>">&#8249;</button>
			<button type="button" class="contorno-units__arrow" data-contorno-carousel-next aria-label="<?php esc_attr_e( 'Próximo plano', 'contorno' ); ?>">&#8250;</button>
		</div>
		<div class="contorno-plans__dots contorno-units__dots" role="tablist" aria-label="<?php esc_attr_e( 'Planos', 'contorno' ); ?>" data-contorno-carousel-dots data-dot-class="contorno-units__dot" data-dot-label="<?php esc_attr_e( 'Ir para plano', 'contorno' ); ?>"></div>
		</div>

		<?php if ( '' !== trim( (string) $a['note'] ) ) : ?>
			<p class="contorno-plans__note"><?php echo contorno_icon( 'lock', 'contorno-plans__lock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( (string) $a['note'] ); ?></span></p>
		<?php endif; ?>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Localizacao (mapa + endereco).
 */
contorno_add_shortcode(
	'contorno_location',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'unit'    => '',
				'ctn'     => '',
				'eyebrow' => '',
				'title'   => '',
				'tone'    => 'light',
			),
			(array) $atts,
			'contorno_location'
		);

		$post_type = '' !== trim( (string) $a['ctn'] ) ? CONTORNO_CPT_CTN : '';
		$post_id   = contorno_resolve_context_id( (array) $a, $post_type );

		if ( ! $post_id ) {
			return '';
		}

		$embed = contorno_maps_embed_url( $post_id );
		$maps  = contorno_maps_url( $post_id );
		$hours = CONTORNO_CPT_CTN === get_post_type( $post_id ) ? contorno_ctn_hours( $post_id ) : array();

		ob_start();
		echo contorno_section_open( 'location', array( 'tone' => (string) $a['tone'], 'id' => 'localizacao', 'class' => 'unit-location-section unit-location-model' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="contorno-location">
			<div class="contorno-location__info motion-reveal" data-contorno-reveal>
				<p class="contorno-location__address">
					<?php echo contorno_icon( 'map-pin' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<span>
						<?php echo esc_html( contorno_field_text( 'address', $post_id ) ); ?>
						<?php $complement = contorno_field_text( 'address_complement', $post_id ); ?>
						<?php if ( '' !== $complement ) : ?>
							<br /><?php echo esc_html( $complement ); ?>
						<?php endif; ?>
						<br /><?php echo esc_html( trim( contorno_field_text( 'city', $post_id ) . ' - ' . contorno_field_text( 'state', $post_id ), ' -' ) ); ?>
						<?php $cep = contorno_field_text( 'postal_code', $post_id ); ?>
						<?php if ( '' !== $cep ) : ?>
							• <?php echo esc_html( $cep ); ?>
						<?php endif; ?>
					</span>
				</p>

				<?php if ( array() !== $hours ) : ?>
					<ul class="contorno-location__hours">
						<?php foreach ( $hours as $row ) : ?>
							<?php if ( ! is_array( $row ) ) : continue; endif; ?>
							<li><strong><?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?></strong><span><?php echo esc_html( (string) ( $row['hours'] ?? '' ) ); ?></span></li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<?php $unit_hours = contorno_field_text( 'hours', $post_id ); ?>
					<?php if ( '' !== $unit_hours ) : ?>
						<p class="contorno-location__hours-text"><?php echo contorno_icon( 'clock' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php echo esc_html( $unit_hours ); ?></span></p>
					<?php endif; ?>
				<?php endif; ?>

				<div class="contorno-location__actions">
					<?php echo contorno_button( __( 'Ver no mapa', 'contorno' ), $maps, 'outline', array( 'external' => true, 'icon' => 'arrow-right' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					<?php echo contorno_button( __( 'Enviar mensagem', 'contorno' ), contorno_whatsapp_link(), 'primary', array( 'external' => true, 'icon' => 'phone' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</div>
			</div>

			<?php if ( '' !== $embed ) : ?>
				<div class="contorno-location__map unit-map-frame motion-reveal" data-contorno-reveal>
					<iframe
						src="<?php echo esc_url( $embed ); ?>"
						title="<?php echo esc_attr( sprintf( /* translators: %s: unit name */ __( 'Mapa - %s', 'contorno' ), (string) get_the_title( $post_id ) ) ); ?>"
						loading="lazy"
						referrerpolicy="no-referrer-when-downgrade"
						allowfullscreen
					></iframe>
				</div>
			<?php endif; ?>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Tour em Reels da Unidade.
 */
contorno_add_shortcode(
	'contorno_unit_video',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'unit'    => '',
				'eyebrow' => '',
				'title'   => '',
				'text'    => '',
				'tone'    => 'light',
			),
			(array) $atts,
			'contorno_unit_video'
		);

		$post_id = contorno_resolve_context_id( (array) $a );
		$video   = $post_id ? contorno_field_text( 'video_url', $post_id ) : '';
		$embed   = '' !== $video ? contorno_youtube_lazy_embed( $video, (string) get_the_title( $post_id ), '9/16' ) : '';

		if ( '' === $embed ) {
			return '';
		}

		$title = '' !== trim( (string) $a['title'] ) ? (string) $a['title'] : __( 'A unidade por dentro, no seu ritmo', 'contorno' );
		$text  = '' !== trim( (string) $a['text'] ) ? (string) $a['text'] : __( 'Um Reels curto para você sentir a estrutura, equipamentos e clima de quem treina aqui. Sem tour guiado. Só o espaço, como ele é.', 'contorno' );

		ob_start();
		echo contorno_section_open( 'unit-video', array( 'tone' => (string) $a['tone'], 'class' => 'unit-reels-section' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="unit-reels-section__grid">
			<div class="unit-reels-section__copy motion-reveal" data-contorno-reveal>
				<?php echo contorno_section_header( (string) $a['eyebrow'], $title, $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<a class="unit-reels-section__link" href="#planos"><?php esc_html_e( 'Quero treinar aqui', 'contorno' ); ?> <?php echo contorno_icon( 'arrow-right', 'unit-reels-section__link-icon' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></a>
			</div>
			<div class="contorno-unit-video unit-reels-player motion-reveal" data-contorno-reveal><?php echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — App Contorno do Corpo.
 */
contorno_add_shortcode(
	'contorno_unit_app',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'eyebrow' => __( 'App Contorno do Corpo', 'contorno' ),
				'title'   => __( 'Sua academia no seu ritmo', 'contorno' ),
				'text'    => __( 'O app Contorno do Corpo é o seu aliado para treinar melhor, acompanhar resultados e ficar por dentro de tudo.', 'contorno' ),
				'tone'    => 'light',
			),
			(array) $atts,
			'contorno_unit_app'
		);

		$image = contorno_resolve_media( '/brand/app-phones.png', 'contorno-gallery' );
		ob_start();
		echo contorno_section_open( 'unit-app', array( 'tone' => (string) $a['tone'], 'class' => 'unit-app-section' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
		<div class="unit-app-card motion-reveal" data-contorno-reveal>
			<?php if ( '' !== $image ) : ?>
				<figure class="unit-app-card__media"><img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" /></figure>
			<?php endif; ?>
			<div class="unit-app-card__copy">
				<?php echo contorno_section_header( (string) $a['eyebrow'], (string) $a['title'], (string) $a['text'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<ul class="unit-app-card__benefits">
					<li><?php echo contorno_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Acesse seus treinos e evolução', 'contorno' ); ?></span></li>
					<li><?php echo contorno_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Horários de aulas em tempo real', 'contorno' ); ?></span></li>
					<li><?php echo contorno_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Check-in rápido nas unidades', 'contorno' ); ?></span></li>
					<li><?php echo contorno_icon( 'check' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><span><?php esc_html_e( 'Desafios, recompensas e muito mais', 'contorno' ); ?></span></li>
				</ul>
			</div>
			<div class="unit-app-card__download">
				<div class="unit-app-card__qr" aria-hidden="true"></div>
				<div>
					<p class="unit-app-card__download-title"><?php esc_html_e( 'Baixe agora!', 'contorno' ); ?></p>
					<p><?php esc_html_e( 'Escaneie o QR Code e baixe nosso app.', 'contorno' ); ?></p>
					<div class="unit-app-card__stores">
						<span>Google Play</span>
						<span>App Store</span>
					</div>
				</div>
			</div>
		</div>
		<?php
		echo contorno_section_close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return (string) ob_get_clean();
	}
);

/**
 * CONTORNO — Aulas Coletivas.
 *
 * COMPONENTE FUNCIONAL. A grade semanal (horarios nas linhas, dias nas
 * A grade vem de uma URL publica (Google Agenda, EVO/W12 ou outro embed)
 * configurada no cadastro da unidade. O editor configura apenas titulo,
 * banner e URL/filial.
 *
 * Esta secao deve ser a ULTIMA de conteudo antes do footer.
 */
contorno_add_shortcode(
	'contorno_classes',
	static function ( array|string $atts ): string {
		$a = shortcode_atts(
			array(
				'unit'    => '',
				'ctn'     => '',
				'eyebrow' => '',
				'title'   => '',
				'text'    => '',
				'banner'  => '',
				'height'  => (string) CONTORNO_EVO_GRID_HEIGHT,
			),
			(array) $atts,
			'contorno_classes'
		);

		$post_type = '' !== trim( (string) $a['ctn'] ) ? CONTORNO_CPT_CTN : '';
		$post_id   = contorno_resolve_context_id( (array) $a, $post_type );

		if ( ! $post_id ) {
			return '';
		}

		$url = contorno_schedule_url( $post_id );

		if ( '' === $url ) {
			return '';
		}

		$title = '' !== trim( (string) $a['title'] )
			? (string) $a['title']
			: contorno_field_text( 'classes_title', $post_id, __( 'Aulas Coletivas – Horários', 'contorno' ) );

		$eyebrow = '' !== trim( (string) $a['eyebrow'] ) ? (string) $a['eyebrow'] : (string) get_the_title( $post_id );

		// Banner padrão da seção — mesma fotografia usada no React (BANNER_SRC).
		$banner = contorno_attr_image( $a['banner'], 'contorno-hero' );
		if ( '' === $banner ) {
			$banner = contorno_resolve_media( '/units/gallery/aulas.jpg', 'contorno-hero' );
		}
		$text    = '' !== trim( (string) $a['text'] )
			? (string) $a['text']
			: __( 'Confira a grade semanal oficial desta unidade.', 'contorno' );

		contorno_enqueue_component( 'lazy-video' );

		ob_start();
		?>
		<section class="unit-collective-classes" aria-labelledby="contorno-classes-title-<?php echo esc_attr( (string) $post_id ); ?>">
			<div class="unit-collective-classes__head">
				<?php if ( '' !== $banner ) : ?>
					<img class="unit-collective-classes__banner" src="<?php echo esc_url( $banner ); ?>" alt="" loading="lazy" decoding="async" />
				<?php endif; ?>
				<span class="unit-collective-classes__scrim" aria-hidden="true"></span>
				<div class="site-container unit-collective-classes__head-inner motion-reveal" data-contorno-reveal>
					<p class="eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
					<h2 id="contorno-classes-title-<?php echo esc_attr( (string) $post_id ); ?>"><?php echo esc_html( $title ); ?></h2>
					<p class="unit-collective-classes__text"><?php echo esc_html( $text ); ?></p>
				</div>
			</div>

			<div class="site-container">
				<div class="unit-collective-classes-frame">
					<div
						class="unit-collective-classes-scroll"
						tabindex="0"
						role="region"
						aria-label="<?php esc_attr_e( 'Grade semanal de aulas coletivas — deslize horizontalmente no celular', 'contorno' ); ?>"
					>
						<iframe
							src="<?php echo esc_url( $url ); ?>"
							title="<?php echo esc_attr( sprintf( /* translators: 1: section title, 2: unit name */ __( '%1$s — grade semanal — %2$s', 'contorno' ), $title, (string) get_the_title( $post_id ) ) ); ?>"
							loading="lazy"
							referrerpolicy="no-referrer-when-downgrade"
							allow="fullscreen"
							style="min-width:<?php echo esc_attr( (string) CONTORNO_EVO_GRID_MIN_WIDTH ); ?>px;height:<?php echo esc_attr( (string) (int) $a['height'] ); ?>px"
						></iframe>
					</div>
				</div>

				<p class="unit-collective-classes__footnote">
					<span class="unit-collective-classes__hint-mobile"><?php esc_html_e( 'Deslize a grade para o lado para ver todos os dias.', 'contorno' ); ?></span>
					<span class="unit-collective-classes__hint-desktop"><?php esc_html_e( 'Quadro oficial da unidade em grade semanal.', 'contorno' ); ?></span>
					<a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Abrir em nova aba', 'contorno' ); ?>
						<?php echo contorno_icon( 'external', 'unit-collective-classes__external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</a>
				</p>
			</div>
		</section>
		<?php

		return (string) ob_get_clean();
	}
);
