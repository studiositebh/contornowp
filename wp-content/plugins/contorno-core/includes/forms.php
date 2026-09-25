<?php
/**
 * Camada de formularios do Contorno Core.
 *
 * POR QUE NAO UM PLUGIN DE FORMULARIO
 * -----------------------------------
 * O site aprovado tem exatamente DOIS formularios, e nenhum dos dois e um
 * formulario de e-mail comum:
 *
 *  1. Fale Conosco  — valida no cliente, tem honeypot e rate limit, e entao
 *     abre um `mailto:` com a mensagem pronta. O e-mail sai do cliente do
 *     visitante; o site nunca envia nada pelo servidor.
 *
 *  2. Matricula     — valida, guarda o lead em sessionStorage e REDIRECIONA
 *     para o checkout externo da EVO. E um passo de fluxo transacional, nao
 *     um formulario de contato.
 *
 * Instalar Contact Form 7 ou WPForms adicionaria envio por servidor, o que
 * exigiria SMTP, DNS e cuidado com entregabilidade — comportamento que o site
 * aprovado NAO tem. Por isso os dois ficam aqui, no plugin.
 *
 * O QUE ESTA CAMADA GARANTE
 * -------------------------
 * Mesmo reproduzindo o comportamento aprovado, o caminho de servidor existe e
 * e seguro: nonce, sanitizacao e validacao server-side, honeypot, rate limit
 * por IP, escape na saida e mensagens de erro controladas. Assim o formulario
 * funciona mesmo sem JavaScript e nao depende de validacao no cliente.
 *
 * ENVIO DE E-MAIL
 * ---------------
 * Desligado por padrao. wp_mail() no host nao foi verificado e nao se envia
 * e-mail em homologacao. Para ligar depois:
 *
 *   add_filter( 'contorno_contact_send_email', '__return_true' );
 *
 * ARMAZENAMENTO
 * -------------
 * Tambem desligado por padrao — guardar dados pessoais no banco e coleta que
 * o fluxo atual nao faz, e teria implicacao de LGPD nao solicitada:
 *
 *   add_filter( 'contorno_contact_store_lead', '__return_true' );
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CONTORNO_CONTACT_ACTION = 'contorno_contact';

/** Janela de rate limit, em segundos (igual ao React: 60s). */
const CONTORNO_CONTACT_RATE_LIMIT = 60;

/**
 * Limites de tamanho — porte de contactFormLimits do React.
 *
 * @return array<string,int>
 */
function contorno_contact_limits(): array {
	return array(
		'name'    => 120,
		'email'   => 254,
		'phone'   => 20,
		'message' => 2000,
	);
}

/**
 * Chave de rate limit por IP. Sem IP identificavel, nao limita.
 */
function contorno_rate_limit_key( string $scope ): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

	if ( '' === $ip ) {
		return '';
	}

	return 'contorno_rl_' . $scope . '_' . md5( $ip );
}

function contorno_rate_limit_hit( string $scope ): bool {
	$key = contorno_rate_limit_key( $scope );

	return '' !== $key && false !== get_transient( $key );
}

function contorno_rate_limit_mark( string $scope, int $seconds = CONTORNO_CONTACT_RATE_LIMIT ): void {
	$key = contorno_rate_limit_key( $scope );

	if ( '' !== $key ) {
		set_transient( $key, time(), $seconds );
	}
}

/**
 * Valida e sanitiza os dados do Fale Conosco no SERVIDOR.
 *
 * Porte de contactFormSchema (zod) do React, com as mesmas mensagens.
 *
 * @param array<string,mixed> $raw
 * @return array{ok:bool,data:array<string,string>,errors:array<string,string>}
 */
function contorno_validate_contact( array $raw ): array {
	$limits = contorno_contact_limits();
	$errors = array();

	$name    = sanitize_text_field( (string) ( $raw['name'] ?? '' ) );
	$email   = sanitize_email( (string) ( $raw['email'] ?? '' ) );
	$phone   = sanitize_text_field( (string) ( $raw['phone'] ?? '' ) );
	$message = sanitize_textarea_field( (string) ( $raw['message'] ?? '' ) );
	$unit    = sanitize_title( (string) ( $raw['unidade'] ?? '' ) );
	$trap    = trim( (string) ( $raw['website'] ?? '' ) );

	// Honeypot: campo escondido preenchido = robo. Erro genérico, sem dica.
	if ( '' !== $trap ) {
		return array(
			'ok'     => false,
			'data'   => array(),
			'errors' => array( 'form' => __( 'Envio bloqueado.', 'contorno' ) ),
		);
	}

	$name = trim( $name );
	if ( mb_strlen( $name ) < 2 ) {
		$errors['name'] = __( 'Informe seu nome.', 'contorno' );
	} elseif ( mb_strlen( $name ) > $limits['name'] ) {
		$errors['name'] = sprintf(
			/* translators: %d: character limit */
			__( 'O nome deve ter no máximo %d caracteres.', 'contorno' ),
			$limits['name']
		);
	}

	if ( '' === $email || ! is_email( $email ) ) {
		$errors['email'] = __( 'Informe um e-mail válido.', 'contorno' );
	} elseif ( mb_strlen( $email ) > $limits['email'] ) {
		$errors['email'] = sprintf(
			/* translators: %d: character limit */
			__( 'O e-mail deve ter no máximo %d caracteres.', 'contorno' ),
			$limits['email']
		);
	}

	$phone = trim( $phone );
	if ( mb_strlen( $phone ) > $limits['phone'] ) {
		$errors['phone'] = sprintf(
			/* translators: %d: character limit */
			__( 'O telefone deve ter no máximo %d caracteres.', 'contorno' ),
			$limits['phone']
		);
	}

	$message = trim( $message );
	if ( mb_strlen( $message ) < 10 ) {
		$errors['message'] = __( 'A mensagem deve ter pelo menos 10 caracteres.', 'contorno' );
	} elseif ( mb_strlen( $message ) > $limits['message'] ) {
		$errors['message'] = sprintf(
			/* translators: %d: character limit */
			__( 'A mensagem deve ter no máximo %d caracteres.', 'contorno' ),
			$limits['message']
		);
	}

	if ( array() !== $errors ) {
		return array( 'ok' => false, 'data' => array(), 'errors' => $errors );
	}

	return array(
		'ok'     => true,
		'errors' => array(),
		'data'   => array(
			'name'    => $name,
			'email'   => $email,
			'phone'   => $phone,
			'message' => $message,
			'unidade' => $unit,
		),
	);
}

/**
 * URL mailto com a mensagem pronta — porte de buildContactMailtoUrl.
 *
 * @param array<string,string> $data
 */
function contorno_contact_mailto( array $data ): string {
	$to = contorno_brand_get( 'email' );

	if ( '' === $to ) {
		return '';
	}

	$subject = sprintf(
		/* translators: %s: sender name */
		__( 'Contato pelo site — %s', 'contorno' ),
		$data['name']
	);

	$phone = '' !== $data['phone'] ? $data['phone'] : __( 'Não informado', 'contorno' );

	$lines = array(
		sprintf( /* translators: %s: name */ __( 'Nome: %s', 'contorno' ), $data['name'] ),
		sprintf( /* translators: %s: email */ __( 'E-mail: %s', 'contorno' ), $data['email'] ),
		sprintf( /* translators: %s: phone */ __( 'Telefone: %s', 'contorno' ), $phone ),
	);

	if ( '' !== $data['unidade'] ) {
		$lines[] = sprintf( /* translators: %s: unit slug */ __( 'Unidade: %s', 'contorno' ), $data['unidade'] );
	}

	$lines[] = '';
	$lines[] = __( 'Mensagem:', 'contorno' );
	$lines[] = $data['message'];

	return 'mailto:' . $to
		. '?subject=' . rawurlencode( $subject )
		. '&body=' . rawurlencode( implode( "\n", $lines ) );
}

/**
 * Processa o POST do Fale Conosco.
 *
 * Roda em `template_redirect` (antes de qualquer saida) para poder redirecionar.
 * O resultado volta para a pagina via query string, nunca via sessao.
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( ! isset( $_POST['contorno_form'] ) || CONTORNO_CONTACT_ACTION !== $_POST['contorno_form'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$referer  = wp_get_referer();
		$fallback = home_url( '/fale-conosco/' );
		$back     = is_string( $referer ) && '' !== $referer ? $referer : $fallback;
		$back     = remove_query_arg( array( 'contorno_status', 'contorno_erro' ), $back );

		$fail = static function ( string $code ) use ( $back ): void {
			wp_safe_redirect( add_query_arg( array( 'contorno_status' => 'erro', 'contorno_erro' => $code ), $back ) . '#contorno-contato' );
			exit;
		};

		// 1. Nonce.
		$nonce = isset( $_POST['contorno_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['contorno_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, CONTORNO_CONTACT_ACTION ) ) {
			$fail( 'nonce' );
		}

		// 2. Rate limit por IP.
		if ( contorno_rate_limit_hit( 'contact' ) ) {
			$fail( 'limite' );
		}

		// 3. Validacao e sanitizacao server-side.
		$result = contorno_validate_contact( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitizado dentro da funcao.

		if ( ! $result['ok'] ) {
			$fail( 'dados' );
		}

		contorno_rate_limit_mark( 'contact' );

		$data = $result['data'];

		/**
		 * Armazenamento do lead — DESLIGADO por padrao.
		 * O fluxo aprovado nao guarda dados pessoais no servidor.
		 */
		if ( (bool) apply_filters( 'contorno_contact_store_lead', false, $data ) ) {
			wp_insert_post(
				array(
					'post_type'    => 'contorno_lead',
					'post_status'  => 'private',
					'post_title'   => $data['name'] . ' — ' . $data['email'],
					'post_content' => $data['message'],
					'meta_input'   => array(
						'_contorno_lead_email'   => $data['email'],
						'_contorno_lead_phone'   => $data['phone'],
						'_contorno_lead_unidade' => $data['unidade'],
					),
				)
			);
		}

		/**
		 * Envio por e-mail — DESLIGADO por padrao (wp_mail nao verificado).
		 */
		if ( (bool) apply_filters( 'contorno_contact_send_email', false, $data ) ) {
			$to = contorno_brand_get( 'email' );

			if ( '' !== $to ) {
				wp_mail(
					$to,
					sprintf( /* translators: %s: sender name */ __( 'Contato pelo site — %s', 'contorno' ), $data['name'] ),
					wp_strip_all_tags( $data['message'] ),
					array( 'Reply-To: ' . $data['email'] )
				);
			}
		}

		/**
		 * Entrega padrao: devolve para a pagina em estado de sucesso, com o
		 * mailto pronto — o mesmo comportamento do site aprovado.
		 */
		$success = add_query_arg( array( 'contorno_status' => 'ok' ), $back );

		wp_safe_redirect( $success . '#contorno-contato' );
		exit;
	},
	5
);

/**
 * Processa o POST da Matricula (caminho sem JavaScript).
 *
 * Com JavaScript, assets/js/enrollment.js valida e navega sozinho; este
 * handler nunca roda. Sem JavaScript o formulario POSTava para a propria
 * pagina e o navegador reexibia o formulario — e, antes de o formulario
 * ganhar method="post", enviava nome, e-mail e telefone na QUERY STRING,
 * onde eles ficam no log de acesso do servidor, no histórico do navegador e
 * no cabecalho Referer da proxima pagina.
 *
 * Regra do destino: sempre /matricula/confirmacao/, interno. Nao existe
 * parametro que escolha para onde redirecionar — nao ha open redirect a
 * explorar. checkout_url/urlSale do plano nao sao mais lidos aqui (ver
 * relatorio consolidado): a jornada com plano+unidade validos passa pelo
 * checkout nativo ou pelo aviso de indisponibilidade, ambos em
 * contorno_enrollment_form (includes/shortcodes/enrollment.php); este
 * handler so cobre o caminho sem JS quando esse shortcode ainda cai no
 * formulario residual (unidade sem plano cadastrado, por exemplo).
 */
add_action(
	'template_redirect',
	static function (): void {
		if ( ! isset( $_POST['contorno_form'] ) || CONTORNO_ENROLL_ACTION !== $_POST['contorno_form'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		$nonce = isset( $_POST['contorno_nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['contorno_nonce'] ) ) : '';

		$back = static function ( string $code ): void {
			$page = get_page_by_path( 'matricula' );
			$url  = $page instanceof WP_Post ? (string) get_permalink( $page ) : home_url( '/matricula/' );

			wp_safe_redirect( add_query_arg( array( 'contorno_status' => 'erro', 'contorno_erro' => $code ), $url ) );
			exit;
		};

		if ( '' === $nonce || ! wp_verify_nonce( $nonce, CONTORNO_ENROLL_ACTION ) ) {
			$back( 'nonce' );
		}

		if ( contorno_rate_limit_hit( 'enroll' ) ) {
			$back( 'limite' );
		}

		// Honeypot + validacao: reaproveita a mesma regra do Fale Conosco,
		// exceto a mensagem, que este formulario nao tem.
		$post = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitizado abaixo.

		if ( '' !== trim( (string) ( $post['website'] ?? '' ) ) ) {
			$back( 'dados' );
		}

		$name  = trim( sanitize_text_field( (string) ( $post['name'] ?? '' ) ) );
		$email = sanitize_email( (string) ( $post['email'] ?? '' ) );
		$phone = (string) preg_replace( '/\D/', '', (string) ( $post['phone'] ?? '' ) );

		if ( mb_strlen( $name ) < 2 || ! is_email( $email ) || strlen( $phone ) < 10 || empty( $post['acceptedTerms'] ) ) {
			$back( 'dados' );
		}

		contorno_rate_limit_mark( 'enroll' );

		// Destino SEMPRE interno — checkout_url/urlSale nunca e usado como
		// redirect publico (ver relatorio consolidado, rodada "matricula
		// 100% interna"). O dado continua no cadastro do plano, so nao e
		// mais lido aqui.
		$confirmation = get_page_by_path( 'matricula/confirmacao' );

		wp_safe_redirect( $confirmation instanceof WP_Post ? (string) get_permalink( $confirmation ) : home_url( '/matricula/confirmacao/' ) );
		exit;
	},
	5
);

/**
 * CPT privado de leads — registrado apenas quando o armazenamento e ligado.
 */
add_action(
	'init',
	static function (): void {
		if ( ! (bool) apply_filters( 'contorno_contact_store_lead', false, array() ) ) {
			return;
		}

		register_post_type(
			'contorno_lead',
			array(
				'labels'          => array(
					'name'          => __( 'Mensagens', 'contorno' ),
					'singular_name' => __( 'Mensagem', 'contorno' ),
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-email',
				'menu_position'   => 25,
				'supports'        => array( 'title', 'editor' ),
				'capability_type' => 'page',
				'map_meta_cap'    => true,
			)
		);
	},
	12
);
