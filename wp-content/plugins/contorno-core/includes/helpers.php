<?php
/**
 * Helpers de template compartilhados por templates, partials e shortcodes.
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Estamos em contexto CTN?
 *
 * Usado para (a) carregar a skin dark premium e (b) SUPRIMIR o header
 * institucional. As paginas CTN comecam direto no Hero dark com o
 * /brand/ctn-logo.webp sobre o proprio hero — nunca menu branco tradicional.
 */
function contorno_is_ctn_context(): bool {
	if ( is_singular( CONTORNO_CPT_CTN ) || is_post_type_archive( CONTORNO_CPT_CTN ) ) {
		return true;
	}

	// Uma pagina montada no builder pode declarar-se CTN.
	if ( is_page() ) {
		$page_id = get_queried_object_id();
		if ( $page_id && get_post_meta( $page_id, '_contorno_layout', true ) === 'ctn' ) {
			return true;
		}
	}

	return (bool) apply_filters( 'contorno_is_ctn_context', false );
}

/**
 * O header institucional deve ser renderizado?
 */
function contorno_show_site_header(): bool {
	return (bool) apply_filters( 'contorno_show_site_header', ! contorno_is_ctn_context() );
}

/**
 * Extrai o ID de um video do YouTube de um ID puro ou de qualquer URL comum.
 */
function contorno_youtube_id( string $value ): string {
	$value = trim( $value );

	if ( '' === $value ) {
		return '';
	}

	if ( preg_match( '/^[A-Za-z0-9_-]{11}$/', $value ) ) {
		return $value;
	}

	if ( preg_match( '#(?:youtu\.be/|v/|embed/|shorts/|watch\?v=|&v=)([A-Za-z0-9_-]{11})#', $value, $matches ) ) {
		return $matches[1];
	}

	return '';
}

/**
 * Poster de um video vertical do YouTube (Short).
 */
function contorno_youtube_vertical_poster( string $video_id ): string {
	$video_id = contorno_youtube_id( $video_id );

	return '' !== $video_id ? 'https://i.ytimg.com/vi/' . $video_id . '/oar2.jpg' : '';
}

function contorno_youtube_poster( string $video_id ): string {
	$video_id = contorno_youtube_id( $video_id );

	return '' !== $video_id ? 'https://i.ytimg.com/vi/' . $video_id . '/maxresdefault.jpg' : '';
}

/**
 * Embed lazy do YouTube — clique carrega o iframe (porte de YouTubeLazyEmbed).
 * Usa youtube-nocookie, como no React.
 */
function contorno_youtube_lazy_embed( string $video_id, string $title = '', string $ratio = '16/9' ): string {
	$video_id = contorno_youtube_id( $video_id );

	if ( '' === $video_id ) {
		return '';
	}

	contorno_enqueue_component( 'lazy-video' );

	$poster = '9/16' === $ratio
		? contorno_youtube_vertical_poster( $video_id )
		: contorno_youtube_poster( $video_id );

	return sprintf(
		'<div class="contorno-video" data-contorno-video data-video-id="%1$s" style="--contorno-video-ratio:%2$s">
			<button type="button" class="contorno-video__trigger" data-contorno-video-play>
				<img class="contorno-video__poster" src="%3$s" alt="" loading="lazy" decoding="async" />
				<span class="contorno-video__play" aria-hidden="true"></span>
				<span class="screen-reader-text">%4$s</span>
			</button>
		</div>',
		esc_attr( $video_id ),
		esc_attr( $ratio ),
		esc_url( $poster ),
		esc_html( '' !== $title ? sprintf( /* translators: %s: video title */ __( 'Assistir: %s', 'contorno' ), $title ) : __( 'Assistir ao vídeo', 'contorno' ) )
	);
}

/**
 * Abre um wrapper com animacao de entrada (porte de <Reveal />).
 */
function contorno_reveal_open( string $classes = '', bool $stagger = false ): string {
	$class = trim( 'motion-reveal ' . ( $stagger ? 'motion-stagger ' : '' ) . $classes );

	return '<div class="' . esc_attr( $class ) . '" data-contorno-reveal>';
}

function contorno_reveal_close(): string {
	return '</div>';
}

/**
 * Registro de icones inline (porte de siteIconRegistry.ts).
 *
 * SVGs traco 1.5 no estilo lucide, para os Destaques Contorno.
 */
function contorno_icon( string $name, string $classes = 'contorno-icon' ): string {
	$paths = array(
		'dumbbell'    => '<path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/>',
		'activity'    => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
		'users'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
		'shower'      => '<path d="M4 4 2.5 2.5"/><path d="M13.5 6.5a5 5 0 0 0-7 0"/><path d="M4 20v-9a5 5 0 0 1 5-5"/><path d="M12 12v.01"/><path d="M16 12v.01"/><path d="M20 12v.01"/><path d="M12 16v.01"/><path d="M16 16v.01"/><path d="M20 16v.01"/><path d="M12 20v.01"/><path d="M16 20v.01"/><path d="M20 20v.01"/>',
		'car'         => '<path d="M19 17h2l.64-2.54a6 6 0 0 0-.42-4.36l-1.5-3A2 2 0 0 0 18 6H6a2 2 0 0 0-1.72 1.1l-1.5 3a6 6 0 0 0-.42 4.36L3 17h2"/><circle cx="7" cy="17" r="2"/><circle cx="17" cy="17" r="2"/><path d="M9 17h6"/>',
		'wifi'        => '<path d="M5 13a10 10 0 0 1 14 0"/><path d="M8.5 16.5a5 5 0 0 1 7 0"/><path d="M2 8.82a15 15 0 0 1 20 0"/><path d="M12 20h.01"/>',
		'heart-pulse' => '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/><path d="M3.22 13H9.5l.5-1 2 4 .5-2 1 1h6.78"/>',
		'clock'       => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
		'map-pin'     => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
		'phone'       => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92Z"/>',
		'check'       => '<polyline points="20 6 9 17 4 12"/>',
		'arrow-right' => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
		'play'        => '<polygon points="6 3 20 12 6 21 6 3"/>',
		'external'    => '<path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
		'lock'        => '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'sparkles'    => '<path d="m12 3-1.9 5.8L4.3 10.7l5.8 1.9L12 18.4l1.9-5.8 5.8-1.9-5.8-1.9Z"/>',
		'search'      => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'mail'        => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
		'menu'        => '<path d="M4 6h16"/><path d="M4 12h16"/><path d="M4 18h16"/>',
		'instagram'   => '<rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>',
		'facebook'    => '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
		'youtube'     => '<path d="M2.5 17a24.12 24.12 0 0 1 0-10 2 2 0 0 1 1.4-1.4 49.56 49.56 0 0 1 16.2 0A2 2 0 0 1 21.5 7a24.12 24.12 0 0 1 0 10 2 2 0 0 1-1.4 1.4 49.55 49.55 0 0 1-16.2 0A2 2 0 0 1 2.5 17"/><path d="m10 15 5-3-5-3z"/>',
		'tiktok'      => '<path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/>',
		'user'        => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
		'landmark'    => '<path d="M10 18v-7"/><path d="M11.12 2.198a2 2 0 0 1 1.76.006l7.866 3.847c.476.233.31.949-.22.949H3.474c-.53 0-.695-.716-.22-.949z"/><path d="M14 18v-7"/><path d="M18 18v-7"/><path d="M3 22h18"/><path d="M6 18v-7"/>',
		'smartphone'  => '<rect width="14" height="20" x="5" y="2" rx="2" ry="2"/><path d="M12 18h.01"/>',
		'armchair'    => '<path d="M19 9V6a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v3"/><path d="M3 16a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-5a2 2 0 0 0-4 0v2H7v-2a2 2 0 0 0-4 0Z"/><path d="M5 18v2"/><path d="M19 18v2"/>',
		'check-circle' => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
		'zap'         => '<path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/>',
		'headphones'  => '<path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H3z"/><path d="M21 14h-3a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h3z"/><path d="M3 14v-3a9 9 0 0 1 18 0v3"/>',
	);

	$path = $paths[ $name ] ?? $paths['sparkles'];

	return sprintf(
		'<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $classes ),
		$path
	);
}

/**
 * Icone adequado para um rotulo de "Destaques Contorno".
 *
 * Porte da heuristica de siteIconRegistry.ts: casa por palavra-chave.
 */
function contorno_icon_for_label( string $label ): string {
	$normalized = contorno_normalize_search( $label );

	$rules = array(
		'estacionamento' => 'car',
		'wi-fi'          => 'wifi',
		'wifi'           => 'wifi',
		'vestiario'      => 'shower',
		'chuveiro'       => 'shower',
		'aula'           => 'users',
		'coletiva'       => 'users',
		'equipe'         => 'users',
		'funcional'      => 'activity',
		'cardio'         => 'heart-pulse',
		'musculacao'     => 'dumbbell',
		'equipamento'    => 'dumbbell',
		'estrutura'      => 'dumbbell',
		'horario'        => 'clock',
		'avaliacao'      => 'heart-pulse',
	);

	foreach ( $rules as $needle => $icon ) {
		if ( str_contains( $normalized, $needle ) ) {
			return $icon;
		}
	}

	return 'sparkles';
}

/**
 * Renderiza um partial passando variaveis explicitamente.
 *
 * Ordem de resolucao: o TEMA pode sobrescrever qualquer partial do plugin
 * criando o mesmo caminho em parts/. Isso permite ajustar a pele visual sem
 * tocar na funcionalidade.
 *
 * @param array<string,mixed> $vars
 */
function contorno_part( string $relative_path, array $vars = array() ): void {
	$relative = ltrim( $relative_path, '/' ) . '.php';

	$candidates = array(
		get_stylesheet_directory() . '/parts/' . $relative,
		get_template_directory() . '/parts/' . $relative,
		CONTORNO_CORE_DIR . 'templates/parts/' . $relative,
	);

	$file = '';
	foreach ( $candidates as $candidate ) {
		if ( is_readable( $candidate ) ) {
			$file = $candidate;
			break;
		}
	}

	if ( '' === $file ) {
		return;
	}

	// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- escopo controlado.
	extract( $vars, EXTR_SKIP );

	require $file;
}
