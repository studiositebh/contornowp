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
 * Mascara um telefone brasileiro para exibicao: (31) 4042-0177 / (31) 99999-0177.
 *
 * Aceita o numero com ou sem pontuacao e com o DDI 55 na frente. Quando nao
 * sobram 10 ou 11 digitos (0800, numeros curtos), devolve o texto original.
 */
function contorno_format_phone( string $phone ): string {
	if ( '' === trim( $phone ) ) {
		return '';
	}

	$digits = contorno_phone_digits( $phone );

	if ( ( 12 === strlen( $digits ) || 13 === strlen( $digits ) ) && str_starts_with( $digits, '55' ) ) {
		$digits = substr( $digits, 2 );
	}

	// 0800/0300 nao tem DDD — fica como o cliente cadastrou.
	if ( str_starts_with( $digits, '0' ) ) {
		return $phone;
	}

	if ( 11 === strlen( $digits ) ) {
		return sprintf( '(%s) %s-%s', substr( $digits, 0, 2 ), substr( $digits, 2, 5 ), substr( $digits, 7 ) );
	}

	if ( 10 === strlen( $digits ) ) {
		return sprintf( '(%s) %s-%s', substr( $digits, 0, 2 ), substr( $digits, 2, 4 ), substr( $digits, 6 ) );
	}

	return $phone;
}

/**
 * Apenas os digitos de um telefone — para href tel: e wa.me.
 */
function contorno_phone_digits( string $phone ): string {
	return (string) preg_replace( '/\D+/', '', $phone );
}

/**
 * Chave de comparacao: sem acento, sem pontuacao, caixa baixa.
 *
 * Usada para descobrir se bairro/cidade/UF ja aparecem no endereco livre que o
 * cliente cadastrou — os campos do dataset vieram de planilhas diferentes e
 * repetem informacao com grafias distintas.
 */
function contorno_compare_key( string $value ): string {
	$value = function_exists( 'remove_accents' ) ? remove_accents( $value ) : $value;

	return (string) preg_replace( '/[^a-z0-9]+/', '', strtolower( $value ) );
}

/**
 * Quebra o endereco da unidade em ate tres linhas, sem repetir bairro/cidade/UF.
 *
 * Entrada tipica do dataset:
 * "Rua Kepler, 441 (Falls Shopping) – São Bento – Belo Horizonte - MG" + campos
 * separados de bairro, cidade, UF e CEP. Saida:
 *   Rua Kepler, 441 (Falls Shopping)
 *   São Bento • Belo Horizonte/MG
 *   CEP: 30360-240
 *
 * @return string[] Linhas ja prontas para exibicao.
 */
function contorno_address_lines( string $address, string $neighborhood = '', string $city = '', string $state = '', string $postal_code = '' ): array {
	$address      = trim( $address );
	$neighborhood = trim( $neighborhood );
	$city         = trim( $city );
	$state        = trim( $state );
	$postal_code  = trim( $postal_code );

	// Separadores usados pelo cadastro: virgula, ponto e virgula e travessao/hifen cercado de espacos.
	$segments = preg_split( '/\s*[,;]\s*|\s+[–—-]\s+/u', $address );
	$segments = is_array( $segments ) ? array_values( array_filter( array_map( 'trim', $segments ), static fn( $part ) => '' !== $part ) ) : array();

	// Ruido que nunca entra na primeira linha: "Brasil" e o proprio CEP.
	$segments = array_values(
		array_filter(
			$segments,
			static function ( string $part ): bool {
				$key = contorno_compare_key( $part );

				return '' !== $key && 'brasil' !== $key && 'brazil' !== $key && 1 !== preg_match( '/^\d{5}-?\d{3}$/', $part );
			}
		)
	);

	// Descarta, do fim para o comeco, os trechos que so repetem bairro/cidade/UF.
	$tail = array_filter(
		array(
			contorno_compare_key( $state ),
			contorno_compare_key( $city ),
			contorno_compare_key( $city . $state ),
			contorno_compare_key( $neighborhood ),
			// Parte do cadastro abrevia a capital.
			'belohorizonte' === contorno_compare_key( $city ) ? 'bh' : '',
		)
	);

	while ( array() !== $segments ) {
		$last = contorno_compare_key( (string) end( $segments ) );

		if ( '' === $last || in_array( $last, $tail, true ) ) {
			array_pop( $segments );
			continue;
		}

		break;
	}

	$street     = implode( ', ', $segments );
	$street_key = contorno_compare_key( '' !== $street ? $street : $address );

	if ( '' === $street ) {
		$street = $address;
	}

	// A segunda linha so recebe o que ainda nao foi dito na primeira.
	$locality = array();

	if ( '' !== $neighborhood && ! str_contains( $street_key, contorno_compare_key( $neighborhood ) ) ) {
		$locality[] = $neighborhood;
	}

	if ( '' !== $city && ! str_contains( $street_key, contorno_compare_key( $city ) ) ) {
		$locality[] = '' !== $state ? $city . '/' . $state : $city;
	}

	return array_values(
		array_filter(
			array(
				$street,
				implode( ' • ', $locality ),
				'' !== $postal_code ? 'CEP: ' . $postal_code : '',
			),
			static fn( string $line ): bool => '' !== trim( $line )
		)
	);
}

/**
 * Quebra o horario da unidade em pares "dia" + "faixa de horario".
 *
 * O dataset guarda uma linha por dia separada por quebra de linha
 * ("Segunda a quinta, 05h às 23h"), o que virava um paragrafo corrido no HTML.
 *
 * @return array<int, array{term: string, value: string}>
 */
function contorno_hours_lines( string $hours ): array {
	$hours = (string) preg_replace( '#<br\s*/?>#i', "\n", $hours );
	$parts = preg_split( '/[\r\n]+|\s*\|\s*/u', $hours );
	$parts = is_array( $parts ) ? $parts : array();

	/*
	 * No banco NAO ha quebra de linha: o campo e do tipo texto e o
	 * sanitize_text_field() do WordPress troca "\n" por espaco na gravacao.
	 * Entao a segunda quebra e pelo proprio nome do dia — "... 23h Sexta,
	 * 05h ..." vira duas faixas.
	 */
	$split = array();

	foreach ( $parts as $part ) {
		$pieces = preg_split(
			'/(?=\b(?:segunda|terça|terca|quarta|quinta|sexta|sábado|sabado|domingo|feriado)s?\b)/iu',
			(string) $part
		);

		if ( ! is_array( $pieces ) ) {
			$split[] = (string) $part;
			continue;
		}

		/*
		 * "Segunda a quinta" e "Sábados e feriados" sao UMA faixa: o pedaco
		 * cortado no meio nao tem horario nenhum, entao volta a se juntar ao
		 * seguinte em vez de virar linha propria.
		 */
		$buffer = '';

		foreach ( $pieces as $piece ) {
			$buffer .= $piece;

			if ( 1 === preg_match( '/\d/', $piece ) ) {
				$split[] = $buffer;
				$buffer  = '';
			}
		}

		if ( '' !== trim( $buffer ) ) {
			$split[] = $buffer;
		}
	}

	$parts = $split;
	$lines = array();

	foreach ( $parts as $part ) {
		$part = trim( (string) preg_replace( '/\s+/u', ' ', $part ) );
		$part = rtrim( $part, '.;' );

		if ( '' === $part ) {
			continue;
		}

		// "Segunda a quinta, 05h às 23h" / "Sexta: 05h às 22h" — o rotulo comeca
		// por letra, entao faixas puras ("04:00 às 00:00") ficam sem rotulo.
		if ( 1 === preg_match( '/^([^\d,:][^,:]*)[,:]\s*(.+)$/u', $part, $match ) ) {
			$lines[] = array(
				'term'  => trim( $match[1] ),
				'value' => trim( $match[2] ),
			);
			continue;
		}

		$lines[] = array(
			'term'  => '',
			'value' => $part,
		);
	}

	return $lines;
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
 * Icone (ou imagem personalizada) de um item do catalogo de atributos.
 *
 * Ordem: imagem personalizada valida > icone da biblioteca > fallback
 * seguro de contorno_icon(). Nunca os dois juntos.
 *
 * @param array{icon?:string,icon_type?:string,image_id?:int,label?:string} $item
 */
function contorno_attribute_icon( array $item, string $classes = 'contorno-icon' ): string {
	$image_id = (int) ( $item['image_id'] ?? 0 );

	if ( 'image' === ( $item['icon_type'] ?? 'icon' ) && $image_id > 0 ) {
		$html = wp_get_attachment_image(
			$image_id,
			'thumbnail',
			false,
			array(
				'class'   => trim( $classes . ' contorno-icon--image' ),
				'alt'     => (string) ( $item['label'] ?? '' ),
				'loading' => 'lazy',
			)
		);

		if ( is_string( $html ) && '' !== $html ) {
			return $html;
		}
	}

	return contorno_icon( (string) ( $item['icon'] ?? '' ), $classes );
}

/**
 * Registro de icones inline (porte de siteIconRegistry.ts).
 *
 * SVGs traco 1.5 no estilo lucide, para os Destaques Contorno.
 */
function contorno_icon( string $name, string $classes = 'contorno-icon' ): string {
	$paths = contorno_icon_paths();

	/*
	 * As chaves legadas (allowlist original) sao checadas primeiro e
	 * NUNCA mudam de traco. A biblioteca ampliada (includes/attributes/
	 * icon-library.php, gerada do Lucide) so entra pra chave que nao
	 * existia antes — e so acrescenta opcoes, nunca sobrescreve.
	 */
	$path = $paths[ $name ]
		?? ( function_exists( 'contorno_icon_library_paths' ) ? ( contorno_icon_library_paths()[ $name ] ?? null ) : null )
		?? $paths['sparkles'];

	return sprintf(
		'<svg class="%s" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
		esc_attr( $classes ),
		$path
	);
}

/**
 * Os traços de cada ícone, por chave.
 *
 * E tambem a ALLOWLIST do seletor de icones do catalogo de atributos: quem
 * administra escolhe uma destas chaves, nunca digita SVG.
 *
 * @return array<string,string>
 */
function contorno_icon_paths(): array {
	return array(
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
